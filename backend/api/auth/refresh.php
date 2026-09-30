<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$accountType = null;
$rawInput = file_get_contents('php://input');

if ($rawInput !== false && trim($rawInput) !== '') {
    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        sendResponse(false, 'Invalid JSON request body.', null, 400);
    }

    $allowedFields = ['account_type'];

    foreach (array_keys($data) as $field) {
        if (!in_array($field, $allowedFields, true)) {
            sendResponse(false, "Invalid field: {$field}.", [
                'allowed_fields' => $allowedFields
            ], 422);
        }
    }

    if (isset($data['account_type']) && $data['account_type'] !== '') {
        $accountType = strtolower(trim((string)$data['account_type']));

        if (!in_array($accountType, ['user', 'admin'], true)) {
            sendResponse(false, 'account_type must be user or admin.', null, 422);
        }
    }
}

$userRefreshToken = getRefreshTokenFromCookie('user');
$adminRefreshToken = getRefreshTokenFromCookie('admin');

if ($accountType === null) {
    if ($userRefreshToken !== null && $adminRefreshToken === null) {
        $accountType = 'user';
    } elseif ($adminRefreshToken !== null && $userRefreshToken === null) {
        $accountType = 'admin';
    } elseif ($userRefreshToken === null && $adminRefreshToken === null) {
        sendResponse(false, 'Refresh token is required. Please login again.', null, 401);
    } else {
        sendResponse(false, 'Multiple login sessions detected. Please provide account_type.', null, 409);
    }
}

$refreshToken = $accountType === 'user'
    ? $userRefreshToken
    : $adminRefreshToken;

if ($refreshToken === null || $refreshToken === '') {
    clearRefreshTokenCookie($accountType);

    sendResponse(
        false,
        ucfirst($accountType) . ' refresh token is required. Please login again.',
        null,
        401
    );
}

$tokenHash = hashRefreshToken($refreshToken);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            id,
            account_id,
            account_type,
            token_hash,
            family_id,
            replaced_by_hash,
            expires_at,
            last_used_at,
            revoked_at,
            user_agent,
            ip_address,
            created_at
         FROM auth_refresh_tokens
         WHERE token_hash = :token_hash
         AND account_type = :account_type
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([
        ':token_hash' => $tokenHash,
        ':account_type' => $accountType
    ]);

    $refreshSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$refreshSession) {
        $pdo->rollBack();
        clearRefreshTokenCookie($accountType);

        sendResponse(
            false,
            'Invalid refresh token. Please login again.',
            null,
            401
        );
    }

    if ($refreshSession['revoked_at'] !== null) {
        $revokeFamilyStmt = $pdo->prepare(
            "UPDATE auth_refresh_tokens
             SET revoked_at = COALESCE(revoked_at, CURRENT_TIMESTAMP)
             WHERE family_id = :family_id
             AND account_type = :account_type"
        );

        $revokeFamilyStmt->execute([
            ':family_id' => $refreshSession['family_id'],
            ':account_type' => $accountType
        ]);

        $pdo->commit();
        clearRefreshTokenCookie($accountType);

        sendResponse(
            false,
            'Refresh token is no longer valid. Please login again.',
            null,
            401
        );
    }

    $expiresAt = strtotime((string)$refreshSession['expires_at']);

    if ($expiresAt === false || $expiresAt <= time()) {
        $expireStmt = $pdo->prepare(
            "UPDATE auth_refresh_tokens
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $expireStmt->execute([
            ':id' => (int)$refreshSession['id']
        ]);

        $pdo->commit();
        clearRefreshTokenCookie($accountType);

        sendResponse(
            false,
            'Refresh token has expired. Please login again.',
            null,
            401
        );
    }

    $accountId = (int)$refreshSession['account_id'];

    if ($accountType === 'user') {
        $accountStmt = $pdo->prepare(
            "SELECT
                id,
                name,
                mobile,
                email,
                date_of_birth,
                status,
                last_login,
                created_at,
                updated_at
             FROM users
             WHERE id = :id
             LIMIT 1"
        );
    } else {
        $accountStmt = $pdo->prepare(
            "SELECT
                id,
                name,
                email,
                mobile,
                role,
                status,
                last_login,
                created_at,
                updated_at
             FROM admins
             WHERE id = :id
             LIMIT 1"
        );
    }

    $accountStmt->execute([
        ':id' => $accountId
    ]);

    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        $revokeStmt = $pdo->prepare(
            "UPDATE auth_refresh_tokens
             SET revoked_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );

        $revokeStmt->execute([
            ':id' => (int)$refreshSession['id']
        ]);

        $pdo->commit();
        clearRefreshTokenCookie($accountType);

        sendResponse(
            false,
            ucfirst($accountType) . ' account not found. Please login again.',
            null,
            401
        );
    }

    if ($account['status'] !== 'active') {
        $revokeAccountStmt = $pdo->prepare(
            "UPDATE auth_refresh_tokens
             SET revoked_at = COALESCE(revoked_at, CURRENT_TIMESTAMP)
             WHERE account_id = :account_id
             AND account_type = :account_type"
        );

        $revokeAccountStmt->execute([
            ':account_id' => $accountId,
            ':account_type' => $accountType
        ]);

        $pdo->commit();
        clearRefreshTokenCookie($accountType);

        if ($account['status'] === 'blocked') {
            sendResponse(
                false,
                ucfirst($accountType) . ' account has been blocked.',
                null,
                403
            );
        }

        sendResponse(
            false,
            ucfirst($accountType) . ' account is inactive.',
            null,
            403
        );
    }

    $newRefreshSession = createRefreshTokenSession(
        $pdo,
        $accountId,
        $accountType,
        $refreshSession['family_id']
    );

    $rotateStmt = $pdo->prepare(
        "UPDATE auth_refresh_tokens
         SET
            revoked_at = CURRENT_TIMESTAMP,
            last_used_at = CURRENT_TIMESTAMP,
            replaced_by_hash = :replaced_by_hash
         WHERE id = :id
         AND revoked_at IS NULL"
    );

    $rotateStmt->execute([
        ':replaced_by_hash' => $newRefreshSession['token_hash'],
        ':id' => (int)$refreshSession['id']
    ]);

    if ($rotateStmt->rowCount() !== 1) {
        throw new RuntimeException('Unable to rotate refresh token.');
    }

    if ($accountType === 'user') {
        $accessToken = generateUserJWT($account);
    } else {
        $accessToken = generateAdminJWT($account);
    }

    $pdo->commit();

    setRefreshTokenCookie(
        $newRefreshSession['token'],
        $accountType
    );

    if ($accountType === 'user') {
        $responseAccount = [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'mobile' => $account['mobile'],
            'email' => $account['email'],
            'date_of_birth' => $account['date_of_birth'],
            'status' => $account['status'],
            'last_login' => $account['last_login']
        ];
    } else {
        $responseAccount = [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'email' => $account['email'],
            'mobile' => $account['mobile'],
            'role' => $account['role'],
            'status' => $account['status'],
            'last_login' => $account['last_login']
        ];
    }

    sendResponse(
        true,
        'Access token refreshed successfully.',
        [
            'account_type' => $accountType,
            'account' => $responseAccount,
            'token' => $accessToken,
            'refresh_token_expires_at' => $newRefreshSession['expires_at']
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to refresh access token.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to refresh access token.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}