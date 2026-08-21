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
    'otp'
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
$otp = isset($data['otp'])
    ? trim((string)$data['otp'])
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
    sendResponse(
        false,
        'Email must not exceed 150 characters.',
        null,
        422
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(
        false,
        'Please enter a valid email address.',
        null,
        422
    );
}

if ($otp === '') {
    sendResponse(false, 'OTP is required.', null, 422);
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    sendResponse(
        false,
        'OTP must be exactly 6 digits.',
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
            epro.otp_hash,
            epro.attempts,
            epro.max_attempts,
            epro.expires_at,
            epro.verified_at,
            epro.used_at,
            epro.status,
            epro.created_at,
            epro.updated_at,

            u.name AS user_name,
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
            'Invalid OTP request.',
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
            'This OTP has already been used.',
            null,
            409
        );
    }

    if ($otpStatus === 'verified') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This OTP has already been verified.',
            [
                'otp_request_id' => $otpRequestId,
                'verified_at' => $otpRecord['verified_at']
            ],
            409
        );
    }

    if ($otpStatus === 'blocked') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This OTP request has been blocked due to too many failed attempts.',
            null,
            423
        );
    }

    if ($otpStatus === 'expired') {
        $pdo->rollBack();

        sendResponse(
            false,
            'OTP has expired. Please request a new OTP.',
            null,
            410
        );
    }

    if ($otpStatus !== 'pending') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This OTP request is no longer valid.',
            null,
            409
        );
    }

    $attempts = (int)$otpRecord['attempts'];
    $maxAttempts = (int)$otpRecord['max_attempts'];

    if ($attempts >= $maxAttempts) {
        $blockStmt = $pdo->prepare(
            "UPDATE email_password_reset_otps
             SET status = 'blocked'
             WHERE id = :id"
        );

        $blockStmt->bindValue(
            ':id',
            $otpRequestId,
            PDO::PARAM_INT
        );

        $blockStmt->execute();

        $pdo->commit();

        sendResponse(
            false,
            'Maximum OTP attempts exceeded. Please request a new OTP.',
            null,
            423
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
             WHERE id = :id"
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
            'OTP has expired. Please request a new OTP.',
            null,
            410
        );
    }

    $isValidOtp = password_verify(
        $otp,
        (string)$otpRecord['otp_hash']
    );

    if (!$isValidOtp) {
        $newAttempts = $attempts + 1;

        $remainingAttempts =
            $maxAttempts - $newAttempts;

        if ($remainingAttempts <= 0) {
            $invalidStmt = $pdo->prepare(
                "UPDATE email_password_reset_otps
                 SET
                    attempts = :attempts,
                    status = 'blocked'
                 WHERE id = :id"
            );

            $invalidStmt->bindValue(
                ':attempts',
                $newAttempts,
                PDO::PARAM_INT
            );

            $invalidStmt->bindValue(
                ':id',
                $otpRequestId,
                PDO::PARAM_INT
            );

            $invalidStmt->execute();

            $pdo->commit();

            sendResponse(
                false,
                'Invalid OTP. Maximum attempts exceeded. Please request a new OTP.',
                [
                    'remaining_attempts' => 0
                ],
                423
            );
        }

        $invalidStmt = $pdo->prepare(
            "UPDATE email_password_reset_otps
             SET attempts = :attempts
             WHERE id = :id"
        );

        $invalidStmt->bindValue(
            ':attempts',
            $newAttempts,
            PDO::PARAM_INT
        );

        $invalidStmt->bindValue(
            ':id',
            $otpRequestId,
            PDO::PARAM_INT
        );

        $invalidStmt->execute();

        $pdo->commit();

        sendResponse(
            false,
            'Invalid OTP.',
            [
                'remaining_attempts' =>
                    $remainingAttempts
            ],
            422
        );
    }

    $verifyStmt = $pdo->prepare(
        "UPDATE email_password_reset_otps
         SET
            status = 'verified',
            verified_at = CURRENT_TIMESTAMP
         WHERE id = :id
         AND status = 'pending'"
    );

    $verifyStmt->bindValue(
        ':id',
        $otpRequestId,
        PDO::PARAM_INT
    );

    $verifyStmt->execute();

    if ($verifyStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to verify OTP.'
        );
    }

    $fetchStmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            email,
            attempts,
            max_attempts,
            expires_at,
            verified_at,
            used_at,
            status,
            created_at,
            updated_at
         FROM email_password_reset_otps
         WHERE id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(
        ':id',
        $otpRequestId,
        PDO::PARAM_INT
    );

    $fetchStmt->execute();

    $verifiedOtp = $fetchStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$verifiedOtp) {
        throw new RuntimeException(
            'Unable to retrieve verified OTP.'
        );
    }

    $pdo->commit();

    sendResponse(
        true,
        'OTP verified successfully.',
        [
            'otp_request_id' =>
                (int)$verifiedOtp['id'],

            'email' =>
                $verifiedOtp['email'],

            'status' =>
                $verifiedOtp['status'],

            'verified_at' =>
                $verifiedOtp['verified_at'],

            'remaining_attempts' =>
                max(
                    0,
                    (int)$verifiedOtp['max_attempts'] -
                    (int)$verifiedOtp['attempts']
                ),

            'next_step' =>
                'You can now reset your password.'
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to verify OTP.',
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
        'An unexpected error occurred.',
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