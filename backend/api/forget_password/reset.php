<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') === false) {
    sendResponse(false, 'Content-Type must be application/json.', null, 415);
}

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    sendResponse(false, 'Request body is required.', null, 400);
}

$data = json_decode($rawInput, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {
    sendResponse(false, 'Invalid JSON request body.', null, 400);
}

$allowedFields = [
    'otp_request_id',
    'email',
    'new_password',
    'confirm_password'
];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$otpRequestIdInput = $data['otp_request_id'] ?? null;

$email = isset($data['email'])
    ? strtolower(trim((string)$data['email']))
    : '';

$newPassword = isset($data['new_password'])
    ? (string)$data['new_password']
    : '';

$confirmPassword = isset($data['confirm_password'])
    ? (string)$data['confirm_password']
    : '';

if ($otpRequestIdInput === null || $otpRequestIdInput === '') {
    sendResponse(false, 'otp_request_id is required.', null, 422);
}

if (
    filter_var($otpRequestIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$otpRequestIdInput <= 0
) {
    sendResponse(
        false,
        'otp_request_id must be a valid positive integer.',
        null,
        422
    );
}

$otpRequestId = (int)$otpRequestIdInput;

if ($email === '') {
    sendResponse(false, 'Email is required.', null, 422);
}

if (mb_strlen($email) > 150) {
    sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', null, 422);
}

if ($newPassword === '') {
    sendResponse(false, 'New password is required.', null, 422);
}

if ($confirmPassword === '') {
    sendResponse(false, 'Confirm password is required.', null, 422);
}

if (strlen($newPassword) < 8) {
    sendResponse(
        false,
        'Password must contain at least 8 characters.',
        null,
        422
    );
}

if (strlen($newPassword) > 72) {
    sendResponse(
        false,
        'Password must not exceed 72 characters.',
        null,
        422
    );
}

if (preg_match('/\s/', $newPassword)) {
    sendResponse(
        false,
        'Password must not contain spaces.',
        null,
        422
    );
}

if (!preg_match('/[A-Z]/', $newPassword)) {
    sendResponse(
        false,
        'Password must contain at least one uppercase letter.',
        null,
        422
    );
}

if (!preg_match('/[a-z]/', $newPassword)) {
    sendResponse(
        false,
        'Password must contain at least one lowercase letter.',
        null,
        422
    );
}

if (!preg_match('/[0-9]/', $newPassword)) {
    sendResponse(
        false,
        'Password must contain at least one number.',
        null,
        422
    );
}

if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
    sendResponse(
        false,
        'Password must contain at least one special character.',
        null,
        422
    );
}

if ($newPassword !== $confirmPassword) {
    sendResponse(
        false,
        'New password and confirm password do not match.',
        null,
        422
    );
}

try {
    $pdo->beginTransaction();

    $otpStmt = $pdo->prepare(
        "SELECT
            epro.id,
            epro.user_id,
            epro.email,
            epro.expires_at,
            epro.verified_at,
            epro.used_at,
            epro.status,

            u.name AS user_name,
            u.password AS current_password,
            u.status AS user_status

         FROM email_password_reset_otps epro

         INNER JOIN users u
            ON u.id = epro.user_id

         WHERE epro.id = :otp_request_id
         AND LOWER(epro.email) = LOWER(:email)

         LIMIT 1
         FOR UPDATE"
    );

    $otpStmt->bindValue(
        ':otp_request_id',
        $otpRequestId,
        PDO::PARAM_INT
    );

    $otpStmt->bindValue(
        ':email',
        $email,
        PDO::PARAM_STR
    );

    $otpStmt->execute();

    $otpRecord = $otpStmt->fetch(PDO::FETCH_ASSOC);

    if (!$otpRecord) {
        $pdo->rollBack();

        sendResponse(
            false,
            'Invalid password reset request.',
            null,
            404
        );
    }

    $userStatus = strtolower(
        trim((string)$otpRecord['user_status'])
    );

    if ($userStatus !== 'active') {
        $pdo->rollBack();

        sendResponse(
            false,
            'Your account is not active.',
            null,
            403
        );
    }

    $otpStatus = strtolower(
        trim((string)$otpRecord['status'])
    );

    if ($otpStatus === 'used') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This password reset request has already been used.',
            null,
            409
        );
    }

    if ($otpStatus === 'expired') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This password reset request has expired.',
            null,
            410
        );
    }

    if ($otpStatus === 'blocked') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This password reset request is blocked.',
            null,
            423
        );
    }

    if ($otpStatus !== 'verified') {
        $pdo->rollBack();

        sendResponse(
            false,
            'OTP verification is required before resetting the password.',
            null,
            403
        );
    }

    if ($otpRecord['verified_at'] === null) {
        $pdo->rollBack();

        sendResponse(
            false,
            'OTP verification is incomplete.',
            null,
            403
        );
    }

    $expiresTimestamp = strtotime(
        (string)$otpRecord['expires_at']
    );

    if (
        $expiresTimestamp === false ||
        time() > $expiresTimestamp
    ) {
        $expireStmt = $pdo->prepare(
            "UPDATE email_password_reset_otps
             SET status = 'expired'
             WHERE id = :id
             AND status = 'verified'"
        );

        $expireStmt->bindValue(
            ':id',
            $otpRequestId,
            PDO::PARAM_INT
        );

        $expireStmt->execute();

        $pdo->commit();

        sendResponse(
            false,
            'Password reset session has expired. Please request a new OTP.',
            null,
            410
        );
    }

    if (
        password_verify(
            $newPassword,
            (string)$otpRecord['current_password']
        )
    ) {
        $pdo->rollBack();

        sendResponse(
            false,
            'New password must be different from your current password.',
            null,
            422
        );
    }

    $passwordHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        throw new RuntimeException(
            'Unable to securely hash the new password.'
        );
    }

    $userId = (int)$otpRecord['user_id'];

    $updatePasswordStmt = $pdo->prepare(
        "UPDATE users
         SET password = :password
         WHERE id = :user_id"
    );

    $updatePasswordStmt->bindValue(
        ':password',
        $passwordHash,
        PDO::PARAM_STR
    );

    $updatePasswordStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $updatePasswordStmt->execute();

    if ($updatePasswordStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to update the password.'
        );
    }

    $usedStmt = $pdo->prepare(
        "UPDATE email_password_reset_otps
         SET
            status = 'used',
            used_at = CURRENT_TIMESTAMP
         WHERE id = :id
         AND user_id = :user_id
         AND status = 'verified'"
    );

    $usedStmt->bindValue(
        ':id',
        $otpRequestId,
        PDO::PARAM_INT
    );

    $usedStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $usedStmt->execute();

    if ($usedStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to complete password reset.'
        );
    }

    $expireOtherStmt = $pdo->prepare(
        "UPDATE email_password_reset_otps
         SET status = 'expired'
         WHERE user_id = :user_id
         AND id != :current_id
         AND status IN ('pending', 'verified')"
    );

    $expireOtherStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $expireOtherStmt->bindValue(
        ':current_id',
        $otpRequestId,
        PDO::PARAM_INT
    );

    $expireOtherStmt->execute();

    $fetchStmt = $pdo->prepare(
        "SELECT
            id,
            name,
            email,
            mobile,
            status,
            updated_at
         FROM users
         WHERE id = :user_id
         LIMIT 1"
    );

    $fetchStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $fetchStmt->execute();

    $user = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException(
            'Unable to retrieve updated user.'
        );
    }

    $pdo->commit();

    sendResponse(
        true,
        'Password reset successfully. You can now login with your new password.',
        [
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'mobile' => $user['mobile'],
                'status' => $user['status']
            ],
            'password_reset' => [
                'otp_request_id' => $otpRequestId,
                'status' => 'used'
            ]
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to reset password.',
        (
            defined('APP_ENV') &&
            APP_ENV === 'development'
        )
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'An unexpected error occurred while resetting password.',
        (
            defined('APP_ENV') &&
            APP_ENV === 'development'
        )
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );
}