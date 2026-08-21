<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

$allowedFields = ['email'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$email = isset($data['email'])
    ? strtolower(trim((string)$data['email']))
    : '';

if ($email === '') {
    sendResponse(false, 'Email is required.', null, 422);
}

if (mb_strlen($email) > 150) {
    sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', null, 422);
}

function sendPasswordResetOtpEmail(
    string $email,
    string $name,
    string $otp
): void {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;

        if (MAIL_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (MAIL_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            MAIL_FROM_EMAIL,
            MAIL_FROM_NAME
        );

        $mail->addAddress(
            $email,
            $name
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Password Reset OTP - ' .
            MAIL_FROM_NAME;

        $safeName = htmlspecialchars(
            $name,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeOtp = htmlspecialchars(
            $otp,
            ENT_QUOTES,
            'UTF-8'
        );

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
            </head>
            <body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;'>
                <div style='max-width:600px;margin:30px auto;background:#ffffff;border-radius:10px;padding:30px;'>
                    <h2 style='margin-top:0;color:#222222;'>
                        Password Reset
                    </h2>

                    <p style='font-size:15px;color:#444444;'>
                        Hi {$safeName},
                    </p>

                    <p style='font-size:15px;color:#444444;'>
                        We received a request to reset your password.
                        Use the OTP below to continue.
                    </p>

                    <div style='text-align:center;margin:30px 0;'>
                        <span style='display:inline-block;font-size:30px;font-weight:bold;letter-spacing:8px;padding:15px 25px;background:#f3f3f3;border-radius:8px;color:#111111;'>
                            {$safeOtp}
                        </span>
                    </div>

                    <p style='font-size:15px;color:#444444;'>
                        This OTP is valid for
                        <strong>5 minutes</strong>.
                    </p>

                    <p style='font-size:15px;color:#444444;'>
                        Do not share this OTP with anyone.
                    </p>

                    <p style='font-size:13px;color:#777777;margin-top:30px;'>
                        If you did not request a password reset,
                        you can safely ignore this email.
                    </p>

                    <hr style='border:none;border-top:1px solid #eeeeee;margin:25px 0;'>

                    <p style='font-size:13px;color:#777777;'>
                        " . htmlspecialchars(
                            MAIL_FROM_NAME,
                            ENT_QUOTES,
                            'UTF-8'
                        ) . "
                    </p>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody =
            "Hi {$name},\n\n" .
            "Your password reset OTP is: {$otp}\n" .
            "This OTP is valid for 5 minutes.\n" .
            "Do not share this OTP with anyone.";

        $mail->send();

    } catch (Exception $e) {
        throw new RuntimeException(
            'Unable to send OTP email: ' .
            $mail->ErrorInfo
        );
    }
}

try {
    $userStmt = $pdo->prepare(
        "SELECT
            id,
            name,
            email,
            status
         FROM users
         WHERE LOWER(email) = LOWER(:email)
         LIMIT 1"
    );

    $userStmt->bindValue(
        ':email',
        $email,
        PDO::PARAM_STR
    );

    $userStmt->execute();

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse(
            false,
            'No account found with this email address.',
            null,
            404
        );
    }

    $userId = (int)$user['id'];

    if ($user['email'] === null || trim((string)$user['email']) === '') {
        sendResponse(
            false,
            'Email address is not available for this account.',
            null,
            422
        );
    }

    $status = strtolower(
        trim((string)$user['status'])
    );

    if ($status === 'inactive') {
        sendResponse(
            false,
            'Your account is inactive.',
            null,
            403
        );
    }

    if ($status === 'blocked') {
        sendResponse(
            false,
            'Your account is blocked.',
            null,
            403
        );
    }

    if ($status !== 'active') {
        sendResponse(
            false,
            'Your account is not active.',
            null,
            403
        );
    }

    $recentStmt = $pdo->prepare(
        "SELECT
            id,
            created_at
         FROM email_password_reset_otps
         WHERE user_id = :user_id
         AND email = :email
         AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );

    $recentStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $recentStmt->bindValue(
        ':email',
        $email,
        PDO::PARAM_STR
    );

    $recentStmt->execute();

    $recentOtp = $recentStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($recentOtp) {
        $createdTimestamp = strtotime(
            (string)$recentOtp['created_at']
        );

        if ($createdTimestamp !== false) {
            $secondsPassed =
                time() - $createdTimestamp;

            if (
                $secondsPassed >= 0 &&
                $secondsPassed < 60
            ) {
                sendResponse(
                    false,
                    'Please wait before requesting another OTP.',
                    [
                        'retry_after_seconds' =>
                            60 - $secondsPassed
                    ],
                    429
                );
            }
        }
    }

    $otp = (string)random_int(
        100000,
        999999
    );

    $otpHash = password_hash(
        $otp,
        PASSWORD_DEFAULT
    );

    if ($otpHash === false) {
        sendResponse(
            false,
            'Unable to generate OTP securely.',
            null,
            500
        );
    }

    $expiresAt = date(
        'Y-m-d H:i:s',
        time() + 300
    );

    $pdo->beginTransaction();

    $expireStmt = $pdo->prepare(
        "UPDATE email_password_reset_otps
         SET status = 'expired'
         WHERE user_id = :user_id
         AND email = :email
         AND status = 'pending'"
    );

    $expireStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $expireStmt->bindValue(
        ':email',
        $email,
        PDO::PARAM_STR
    );

    $expireStmt->execute();

    $insertStmt = $pdo->prepare(
        "INSERT INTO email_password_reset_otps (
            user_id,
            email,
            otp_hash,
            attempts,
            max_attempts,
            expires_at,
            verified_at,
            used_at,
            status
        ) VALUES (
            :user_id,
            :email,
            :otp_hash,
            0,
            5,
            :expires_at,
            NULL,
            NULL,
            'pending'
        )"
    );

    $insertStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':email',
        $email,
        PDO::PARAM_STR
    );

    $insertStmt->bindValue(
        ':otp_hash',
        $otpHash,
        PDO::PARAM_STR
    );

    $insertStmt->bindValue(
        ':expires_at',
        $expiresAt,
        PDO::PARAM_STR
    );

    $insertStmt->execute();

    $otpRequestId =
        (int)$pdo->lastInsertId();

    sendPasswordResetOtpEmail(
        $email,
        (string)$user['name'],
        $otp
    );

    $pdo->commit();

    $emailParts = explode('@', $email);

    $emailName = $emailParts[0] ?? '';
    $emailDomain = $emailParts[1] ?? '';

    if (mb_strlen($emailName) <= 2) {
        $maskedEmailName =
            mb_substr($emailName, 0, 1) .
            '***';
    } else {
        $maskedEmailName =
            mb_substr($emailName, 0, 2) .
            str_repeat(
                '*',
                max(
                    3,
                    mb_strlen($emailName) - 2
                )
            );
    }

    $maskedEmail =
        $maskedEmailName .
        '@' .
        $emailDomain;

    sendResponse(
        true,
        'OTP sent successfully to your registered email address.',
        [
            'otp_request_id' => $otpRequestId,
            'email' => $maskedEmail,
            'expires_in' => 300,
            'expires_at' => $expiresAt,
            'max_attempts' => 5
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to process forgot password request.',
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

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to send OTP to your email address.',
        (
            defined('APP_ENV') &&
            APP_ENV === 'development'
        )
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        502
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