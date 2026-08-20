<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateJWT(array $account, string $userType): string
{
    if (!in_array($userType, ['admin', 'user'], true)) {
        throw new InvalidArgumentException('Invalid account type.');
    }

    $issuedAt = time();
    $expireAt = $issuedAt + JWT_EXPIRY;

    $payload = [
        'iss' => APP_NAME,
        'iat' => $issuedAt,
        'nbf' => $issuedAt,
        'exp' => $expireAt,
        'data' => [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'type' => $userType,
            'status' => $account['status']
        ]
    ];

    if (isset($account['email']) && $account['email'] !== null) {
        $payload['data']['email'] = $account['email'];
    }

    if ($userType === 'admin' && isset($account['role'])) {
        $payload['data']['role'] = $account['role'];
    }

    return JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);
}

function generateAdminJWT(array $admin): string
{
    return generateJWT($admin, 'admin');
}

function generateUserJWT(array $user): string
{
    return generateJWT($user, 'user');
}

function getAuthorizationHeader(): ?string
{
    $authorization = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorization = trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    if (empty($authorization) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authorization = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (empty($authorization) && function_exists('getallheaders')) {
        $headers = getallheaders();

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authorization = trim($value);
                break;
            }
        }
    }

    return !empty($authorization) ? $authorization : null;
}

function getBearerToken(): ?string
{
    $authorization = getAuthorizationHeader();

    if (!$authorization) {
        return null;
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function decodeJWT(string $token): object
{
    try {
        return JWT::decode(
            $token,
            new Key(JWT_SECRET, JWT_ALGORITHM)
        );
    } catch (\Firebase\JWT\ExpiredException $e) {
        sendResponse(false, 'Token has expired.', null, 401);
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        sendResponse(false, 'Invalid token signature.', null, 401);
    } catch (\Firebase\JWT\BeforeValidException $e) {
        sendResponse(false, 'Token is not yet valid.', null, 401);
    } catch (Exception $e) {
        sendResponse(false, 'Invalid or malformed token.', null, 401);
    }

    sendResponse(false, 'Unable to authenticate token.', null, 401);
}

function authenticate(): object
{
    $token = getBearerToken();

    if (!$token) {
        sendResponse(false, 'Authorization token is required.', null, 401);
    }

    return decodeJWT($token);
}

function validateJWTData(object $decoded): void
{
    if (
        !isset($decoded->data) ||
        !isset($decoded->data->id) ||
        !isset($decoded->data->type)
    ) {
        sendResponse(false, 'Invalid authentication token.', null, 401);
    }

    if (!in_array($decoded->data->type, ['admin', 'user'], true)) {
        sendResponse(false, 'Invalid account type.', null, 401);
    }

    if ((int)$decoded->data->id <= 0) {
        sendResponse(false, 'Invalid account ID.', null, 401);
    }
}

function authenticateAdmin(): object
{
    global $pdo;

    $decoded = authenticate();
    validateJWTData($decoded);

    if ($decoded->data->type !== 'admin') {
        sendResponse(false, 'Admin authentication required.', null, 403);
    }

    $adminId = (int)$decoded->data->id;

    try {
        $stmt = $pdo->prepare(
            "SELECT id,name,email,mobile,password,role,status,last_login,created_at,updated_at
             FROM admins
             WHERE id=:id
             LIMIT 1"
        );
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(false, 'Unable to verify admin account.', null, 500);
    }

    if (!$admin) {
        sendResponse(false, 'Admin account not found.', null, 401);
    }

    if ($admin['status'] !== 'active') {
        if ($admin['status'] === 'blocked') {
            sendResponse(false, 'Admin account has been blocked.', null, 403);
        }

        sendResponse(false, 'Admin account is inactive.', null, 403);
    }

    $decoded->data->name = $admin['name'];
    $decoded->data->email = $admin['email'];
    $decoded->data->role = $admin['role'];
    $decoded->data->status = $admin['status'];

    return $decoded;
}

function authenticateUser(): object
{
    global $pdo;

    $decoded = authenticate();
    validateJWTData($decoded);

    if ($decoded->data->type !== 'user') {
        sendResponse(false, 'User authentication required.', null, 403);
    }

    $userId = (int)$decoded->data->id;

    try {
        $stmt = $pdo->prepare(
            "SELECT id,name,mobile,email,password,date_of_birth,status,last_login,created_at,updated_at
             FROM users
             WHERE id=:id
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(false, 'Unable to verify user account.', null, 500);
    }

    if (!$user) {
        sendResponse(false, 'User account not found.', null, 401);
    }

    if ($user['status'] !== 'active') {
        if ($user['status'] === 'blocked') {
            sendResponse(false, 'User account has been blocked.', null, 403);
        }

        sendResponse(false, 'User account is inactive.', null, 403);
    }

    $decoded->data->name = $user['name'];
    $decoded->data->email = $user['email'];
    $decoded->data->mobile = $user['mobile'];
    $decoded->data->status = $user['status'];

    return $decoded;
}

function getAuthenticatedId(object $decoded): int
{
    return (int)$decoded->data->id;
}

function getAuthenticatedType(object $decoded): string
{
    return (string)$decoded->data->type;
}

function checkAdminRole(object $decoded, array $allowedRoles): void
{
    if (
        !isset($decoded->data->role) ||
        !in_array($decoded->data->role, $allowedRoles, true)
    ) {
        sendResponse(
            false,
            'You do not have permission to perform this action.',
            null,
            403
        );
    }
}