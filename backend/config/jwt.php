<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (!defined('USER_ACCESS_TOKEN_EXPIRY')) define('USER_ACCESS_TOKEN_EXPIRY', 60 * 30);
if (!defined('ADMIN_ACCESS_TOKEN_EXPIRY')) define('ADMIN_ACCESS_TOKEN_EXPIRY', defined('JWT_EXPIRY') ? JWT_EXPIRY : 60 * 60 * 24);
if (!defined('USER_REFRESH_TOKEN_EXPIRY')) define('USER_REFRESH_TOKEN_EXPIRY', 60 * 60 * 24 * 30);
if (!defined('ADMIN_REFRESH_TOKEN_EXPIRY')) define('ADMIN_REFRESH_TOKEN_EXPIRY', 60 * 60 * 24 * 7);
if (!defined('USER_REFRESH_COOKIE')) define('USER_REFRESH_COOKIE', 'vivisha_user_refresh_token');
if (!defined('ADMIN_REFRESH_COOKIE')) define('ADMIN_REFRESH_COOKIE', 'vivisha_admin_refresh_token');
if (!defined('REFRESH_TOKEN_TABLE')) define('REFRESH_TOKEN_TABLE', 'auth_refresh_tokens');

function validateAccountType(string $type): void
{
    if (!in_array($type, ['admin', 'user'], true)) {
        throw new InvalidArgumentException('Invalid account type.');
    }
}

function getAccessTokenExpiry(string $type): int
{
    validateAccountType($type);
    return $type === 'user' ? USER_ACCESS_TOKEN_EXPIRY : ADMIN_ACCESS_TOKEN_EXPIRY;
}

function getRefreshTokenExpiry(string $type): int
{
    validateAccountType($type);
    return $type === 'user' ? USER_REFRESH_TOKEN_EXPIRY : ADMIN_REFRESH_TOKEN_EXPIRY;
}

function getRefreshCookieName(string $type): string
{
    validateAccountType($type);
    return $type === 'user' ? USER_REFRESH_COOKIE : ADMIN_REFRESH_COOKIE;
}

function generateJWT(array $account, string $userType): string
{
    validateAccountType($userType);

    if (!isset($account['id']) || (int)$account['id'] <= 0) {
        throw new InvalidArgumentException('Invalid account ID.');
    }

    if (!isset($account['name']) || trim((string)$account['name']) === '') {
        throw new InvalidArgumentException('Account name is required.');
    }

    if (!isset($account['status']) || trim((string)$account['status']) === '') {
        throw new InvalidArgumentException('Account status is required.');
    }

    $issuedAt = time();
    $expireAt = $issuedAt + getAccessTokenExpiry($userType);
    $jti = bin2hex(random_bytes(16));

    $payload = [
        'iss' => APP_NAME,
        'iat' => $issuedAt,
        'nbf' => $issuedAt,
        'exp' => $expireAt,
        'jti' => $jti,
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

function generateRefreshToken(): string
{
    return bin2hex(random_bytes(64));
}

function hashRefreshToken(string $token): string
{
    return hash('sha256', $token);
}

function generateRefreshTokenFamilyId(): string
{
    return bin2hex(random_bytes(16));
}

function createRefreshTokenSession(PDO $pdo, int $accountId, string $accountType, ?string $familyId = null): array
{
    validateAccountType($accountType);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Invalid account ID.');
    }

    $token = generateRefreshToken();
    $tokenHash = hashRefreshToken($token);
    $familyId = ($familyId !== null && trim($familyId) !== '')
        ? $familyId
        : generateRefreshTokenFamilyId();

    $expiresTimestamp = time() + getRefreshTokenExpiry($accountType);
    $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp);

    $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
        ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255)
        : null;

    $ipAddress = isset($_SERVER['REMOTE_ADDR'])
        ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45)
        : null;

    $table = REFRESH_TOKEN_TABLE;

    $stmt = $pdo->prepare(
        "INSERT INTO {$table}
        (account_id,account_type,token_hash,family_id,expires_at,user_agent,ip_address)
        VALUES
        (:account_id,:account_type,:token_hash,:family_id,:expires_at,:user_agent,:ip_address)"
    );

    $stmt->execute([
        ':account_id' => $accountId,
        ':account_type' => $accountType,
        ':token_hash' => $tokenHash,
        ':family_id' => $familyId,
        ':expires_at' => $expiresAt,
        ':user_agent' => $userAgent,
        ':ip_address' => $ipAddress
    ]);

    return [
        'token' => $token,
        'token_hash' => $tokenHash,
        'family_id' => $familyId,
        'expires_at' => $expiresAt,
        'expires_timestamp' => $expiresTimestamp
    ];
}

function setRefreshTokenCookie(string $token, string $accountType): void
{
    validateAccountType($accountType);

    $secure = defined('APP_ENV') && APP_ENV === 'production';

    setcookie(
        getRefreshCookieName($accountType),
        $token,
        [
            'expires' => time() + getRefreshTokenExpiry($accountType),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

function getRefreshTokenFromCookie(string $accountType): ?string
{
    validateAccountType($accountType);

    $cookieName = getRefreshCookieName($accountType);

    if (!isset($_COOKIE[$cookieName]) || !is_string($_COOKIE[$cookieName])) {
        return null;
    }

    $token = trim($_COOKIE[$cookieName]);

    return $token !== '' ? $token : null;
}

function clearRefreshTokenCookie(string $accountType): void
{
    validateAccountType($accountType);

    $secure = defined('APP_ENV') && APP_ENV === 'production';

    setcookie(
        getRefreshCookieName($accountType),
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

function revokeCurrentRefreshToken(PDO $pdo, string $accountType): void
{
    validateAccountType($accountType);

    $token = getRefreshTokenFromCookie($accountType);

    if ($token !== null) {
        $table = REFRESH_TOKEN_TABLE;

        $stmt = $pdo->prepare(
            "UPDATE {$table}
             SET revoked_at=COALESCE(revoked_at,CURRENT_TIMESTAMP)
             WHERE token_hash=:token_hash
             AND account_type=:account_type"
        );

        $stmt->execute([
            ':token_hash' => hashRefreshToken($token),
            ':account_type' => $accountType
        ]);
    }

    clearRefreshTokenCookie($accountType);
}

function revokeAllRefreshTokens(PDO $pdo, int $accountId, string $accountType): void
{
    validateAccountType($accountType);

    if ($accountId <= 0) return;

    $table = REFRESH_TOKEN_TABLE;

    $stmt = $pdo->prepare(
        "UPDATE {$table}
         SET revoked_at=COALESCE(revoked_at,CURRENT_TIMESTAMP)
         WHERE account_id=:account_id
         AND account_type=:account_type"
    );

    $stmt->execute([
        ':account_id' => $accountId,
        ':account_type' => $accountType
    ]);
}

function isAccessTokenRevoked(string $jti): bool
{
    global $pdo;

    $jti = trim($jti);

    if ($jti === '') {
        return false;
    }

    try {

        $stmt = $pdo->prepare(
            "SELECT id
             FROM revoked_access_tokens
             WHERE jti = :jti
             LIMIT 1"
        );

        $stmt->execute([
            ':jti' => $jti
        ]);

        return (bool)$stmt->fetchColumn();

    } catch (PDOException $e) {

        sendResponse(
            false,
            'Unable to verify authentication session.',
            APP_ENV === 'development'
                ? ['error' => $e->getMessage()]
                : null,
            500
        );
    }

    return false;
}

function revokeAccessToken(PDO $pdo, object $decoded): bool
{
    if (
        !isset($decoded->jti) ||
        trim((string)$decoded->jti) === '' ||
        !isset($decoded->exp) ||
        !isset($decoded->data) ||
        !isset($decoded->data->id) ||
        !isset($decoded->data->type)
    ) {
        return false;
    }

    $jti = trim((string)$decoded->jti);
    $accountId = (int)$decoded->data->id;
    $accountType = (string)$decoded->data->type;
    $expiresTimestamp = (int)$decoded->exp;

    if (
        $accountId <= 0 ||
        $expiresTimestamp <= 0 ||
        !in_array($accountType, ['user', 'admin'], true)
    ) {
        return false;
    }

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO revoked_access_tokens
        (
            jti,
            account_id,
            account_type,
            expires_at
        )
        VALUES
        (
            :jti,
            :account_id,
            :account_type,
            FROM_UNIXTIME(:expires_timestamp)
        )"
    );

    $stmt->bindValue(':jti', $jti, PDO::PARAM_STR);
    $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
    $stmt->bindValue(':account_type', $accountType, PDO::PARAM_STR);
    $stmt->bindValue(':expires_timestamp', $expiresTimestamp, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        return true;
    }

    $checkStmt = $pdo->prepare(
        "SELECT id
         FROM revoked_access_tokens
         WHERE jti = :jti
         LIMIT 1"
    );

    $checkStmt->execute([
        ':jti' => $jti
    ]);

    return (bool)$checkStmt->fetchColumn();
}

function revokeCurrentAccessToken(
    PDO $pdo,
    ?string $expectedAccountType = null
): bool {

    $token = getBearerToken();

    if (!$token) {
        return false;
    }

    try {

        $decoded = JWT::decode(
            $token,
            new Key(
                JWT_SECRET,
                JWT_ALGORITHM
            )
        );

        validateJWTData($decoded);

        if (
            $expectedAccountType !== null &&
            $decoded->data->type !== $expectedAccountType
        ) {
            return false;
        }

        if (
            !isset($decoded->jti) ||
            trim((string)$decoded->jti) === ''
        ) {
            return false;
        }

        return revokeAccessToken(
            $pdo,
            $decoded
        );

    } catch (\Firebase\JWT\ExpiredException $e) {

        return false;

    } catch (Throwable $e) {

        return false;
    }
}

function cleanupExpiredRevokedAccessTokens(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM revoked_access_tokens
             WHERE expires_at<=CURRENT_TIMESTAMP"
        );

        $stmt->execute();
    } catch (PDOException $e) {
    }
}

function getAuthorizationHeader(): ?string
{
    $authorization = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authorization = trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    }

    if (
        empty($authorization) &&
        isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
    ) {
        $authorization = trim(
            (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        );
    }

    if (
        empty($authorization) &&
        function_exists('getallheaders')
    ) {
        $headers = getallheaders();

        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'authorization') {
                $authorization = trim((string)$value);
                break;
            }
        }
    }

    return !empty($authorization)
        ? $authorization
        : null;
}

function getBearerToken(): ?string
{
    $authorization = getAuthorizationHeader();

    if (!$authorization) return null;

    if (
        preg_match(
            '/^Bearer\s+(.+)$/i',
            $authorization,
            $matches
        )
    ) {
        return trim($matches[1]);
    }

    return null;
}

function decodeJWT(string $token): object
{
    try {
        $decoded = JWT::decode(
            $token,
            new Key(JWT_SECRET, JWT_ALGORITHM)
        );

        if (
            isset($decoded->jti) &&
            isAccessTokenRevoked(
                (string)$decoded->jti
            )
        ) {
            sendResponse(
                false,
                'Access token has been revoked.',
                null,
                401
            );
        }

        return $decoded;

    } catch (\Firebase\JWT\ExpiredException $e) {
        sendResponse(
            false,
            'Token has expired.',
            null,
            401
        );

    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        sendResponse(
            false,
            'Invalid token signature.',
            null,
            401
        );

    } catch (\Firebase\JWT\BeforeValidException $e) {
        sendResponse(
            false,
            'Token is not yet valid.',
            null,
            401
        );

    } catch (Throwable $e) {
        sendResponse(
            false,
            'Invalid or malformed token.',
            null,
            401
        );
    }

    sendResponse(
        false,
        'Unable to authenticate token.',
        null,
        401
    );
}

function authenticate(): object
{
    static $cachedToken = null;
    static $cachedDecoded = null;

    $token = getBearerToken();

    if (!$token) {
        sendResponse(
            false,
            'Authorization token is required.',
            null,
            401
        );
    }

    if (
        $cachedToken !== null &&
        $cachedDecoded !== null &&
        hash_equals($cachedToken, $token)
    ) {
        return $cachedDecoded;
    }

    $decoded = decodeJWT($token);

    $cachedToken = $token;
    $cachedDecoded = $decoded;

    return $decoded;
}

function validateJWTData(object $decoded): void
{
    if (
        !isset($decoded->data) ||
        !isset($decoded->data->id) ||
        !isset($decoded->data->type)
    ) {
        sendResponse(
            false,
            'Invalid authentication token.',
            null,
            401
        );
    }

    if (
        !in_array(
            $decoded->data->type,
            ['admin', 'user'],
            true
        )
    ) {
        sendResponse(
            false,
            'Invalid account type.',
            null,
            401
        );
    }

    if ((int)$decoded->data->id <= 0) {
        sendResponse(
            false,
            'Invalid account ID.',
            null,
            401
        );
    }
}

function authenticateAdmin(): object
{
    global $pdo;

    static $cachedAdminId = null;
    static $cachedAdminDecoded = null;

    $decoded = authenticate();

    validateJWTData($decoded);

    if ($decoded->data->type !== 'admin') {
        sendResponse(
            false,
            'Admin authentication required.',
            null,
            403
        );
    }

    $adminId = (int)$decoded->data->id;

    if (
        $cachedAdminId === $adminId &&
        $cachedAdminDecoded !== null
    ) {
        return $cachedAdminDecoded;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id,name,email,mobile,password,role,status,last_login,created_at,updated_at
             FROM admins
             WHERE id=:id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $adminId
        ]);

        $admin = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    } catch (PDOException $e) {
        sendResponse(
            false,
            'Unable to verify admin account.',
            null,
            500
        );
    }

    if (!$admin) {
        sendResponse(
            false,
            'Admin account not found.',
            null,
            401
        );
    }

    if ($admin['status'] !== 'active') {
        if ($admin['status'] === 'blocked') {
            sendResponse(
                false,
                'Admin account has been blocked.',
                null,
                403
            );
        }

        sendResponse(
            false,
            'Admin account is inactive.',
            null,
            403
        );
    }

    $decoded->data->name = $admin['name'];
    $decoded->data->email = $admin['email'];
    $decoded->data->role = $admin['role'];
    $decoded->data->status = $admin['status'];

    $cachedAdminId = $adminId;
    $cachedAdminDecoded = $decoded;

    return $decoded;
}

function authenticateUser(): object
{
    global $pdo;

    static $cachedUserId = null;
    static $cachedUserDecoded = null;

    $decoded = authenticate();

    validateJWTData($decoded);

    if ($decoded->data->type !== 'user') {
        sendResponse(
            false,
            'User authentication required.',
            null,
            403
        );
    }

    $userId = (int)$decoded->data->id;

    if (
        $cachedUserId === $userId &&
        $cachedUserDecoded !== null
    ) {
        return $cachedUserDecoded;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id,name,mobile,email,password,date_of_birth,status,last_login,created_at,updated_at
             FROM users
             WHERE id=:id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $userId
        ]);

        $user = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    } catch (PDOException $e) {
        sendResponse(
            false,
            'Unable to verify user account.',
            null,
            500
        );
    }

    if (!$user) {
        sendResponse(
            false,
            'User account not found.',
            null,
            401
        );
    }

    if ($user['status'] !== 'active') {
        if ($user['status'] === 'blocked') {
            sendResponse(
                false,
                'User account has been blocked.',
                null,
                403
            );
        }

        sendResponse(
            false,
            'User account is inactive.',
            null,
            403
        );
    }

    $decoded->data->name = $user['name'];
    $decoded->data->email = $user['email'];
    $decoded->data->mobile = $user['mobile'];
    $decoded->data->status = $user['status'];

    $cachedUserId = $userId;
    $cachedUserDecoded = $decoded;

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
        !in_array(
            $decoded->data->role,
            $allowedRoles,
            true
        )
    ) {
        sendResponse(
            false,
            'You do not have permission to perform this action.',
            null,
            403
        );
    }
}