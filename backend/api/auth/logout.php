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

    if (isset($data['account_type']) && trim((string)$data['account_type']) !== '') {
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
    } elseif ($userRefreshToken !== null && $adminRefreshToken !== null) {
        sendResponse(
            false,
            'Multiple login sessions detected. Please provide account_type.',
            null,
            409
        );
    }
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Revoke Current Access Token
    |--------------------------------------------------------------------------
    |
    | Authorization: Bearer ACCESS_TOKEN
    |
    | Token contains unique jti.
    | jti will be stored in revoked_access_tokens.
    |
    */

    $accessTokenRevoked = revokeCurrentAccessToken(
        $pdo,
        $accountType
    );

    /*
    |--------------------------------------------------------------------------
    | Revoke Refresh Token
    |--------------------------------------------------------------------------
    */

    $refreshTokenRevoked = false;

    if ($accountType !== null) {
        $refreshToken = getRefreshTokenFromCookie($accountType);

        if ($refreshToken !== null) {
            $refreshTokenHash = hashRefreshToken($refreshToken);

            $stmt = $pdo->prepare(
                "UPDATE auth_refresh_tokens
                 SET revoked_at = COALESCE(revoked_at, CURRENT_TIMESTAMP)
                 WHERE token_hash = :token_hash
                 AND account_type = :account_type"
            );

            $stmt->execute([
                ':token_hash' => $refreshTokenHash,
                ':account_type' => $accountType
            ]);

            $refreshTokenRevoked = $stmt->rowCount() > 0;

            if (!$refreshTokenRevoked) {
                $checkStmt = $pdo->prepare(
                    "SELECT id
                     FROM auth_refresh_tokens
                     WHERE token_hash = :token_hash
                     AND account_type = :account_type
                     AND revoked_at IS NOT NULL
                     LIMIT 1"
                );

                $checkStmt->execute([
                    ':token_hash' => $refreshTokenHash,
                    ':account_type' => $accountType
                ]);

                $refreshTokenRevoked = (bool)$checkStmt->fetchColumn();
            }
        }
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Clear Refresh Cookie
    |--------------------------------------------------------------------------
    */

    if ($accountType !== null) {
        clearRefreshTokenCookie($accountType);
    } else {
        clearRefreshTokenCookie('user');
        clearRefreshTokenCookie('admin');
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        $accountType !== null
            ? ucfirst($accountType) . ' logout successful.'
            : 'Logout successful.',
        [
            'account_type' => $accountType,
            'logged_out' => true,
            'access_token_revoked' => $accessTokenRevoked,
            'refresh_token_revoked' => $refreshTokenRevoked
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to logout.',
        APP_ENV === 'development'
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
        'Unable to logout.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}