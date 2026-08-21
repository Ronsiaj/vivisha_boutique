<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
    sendResponse(false, 'Only PUT or PATCH method is allowed.', null, 405);
}

$decoded = authenticate();
validateJWTData($decoded);

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if ($accountType !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
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

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, 'Invalid JSON request body.', null, 400);
}

$allowedFields = [
    'name',
    'mobile',
    'email',
    'date_of_birth'
];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$hasName = array_key_exists('name', $data);
$hasMobile = array_key_exists('mobile', $data);
$hasEmail = array_key_exists('email', $data);
$hasDateOfBirth = array_key_exists('date_of_birth', $data);

if (
    !$hasName &&
    !$hasMobile &&
    !$hasEmail &&
    !$hasDateOfBirth
) {
    sendResponse(false, 'At least one field must be provided for update.', [
        'updatable_fields' => $allowedFields
    ], 422);
}

$name = null;
$mobile = null;
$email = null;
$dateOfBirth = null;

if ($hasName) {
    if (!is_string($data['name'])) {
        sendResponse(false, 'Name must be a string.', null, 422);
    }

    $name = trim($data['name']);

    if ($name === '') {
        sendResponse(false, 'Name cannot be empty.', null, 422);
    }

    if (mb_strlen($name) < 2) {
        sendResponse(false, 'Name must contain at least 2 characters.', null, 422);
    }

    if (mb_strlen($name) > 100) {
        sendResponse(false, 'Name must not exceed 100 characters.', null, 422);
    }

    if (!preg_match("/^[\p{L}\s.'-]+$/u", $name)) {
        sendResponse(false, 'Name contains invalid characters.', null, 422);
    }
}

if ($hasMobile) {
    if (!is_string($data['mobile']) && !is_int($data['mobile'])) {
        sendResponse(false, 'Mobile number must be a string or integer.', null, 422);
    }

    $mobile = trim((string)$data['mobile']);
    $mobile = preg_replace('/\s+/', '', $mobile);

    if ($mobile === null || $mobile === '') {
        sendResponse(false, 'Mobile number cannot be empty.', null, 422);
    }

    if (str_starts_with($mobile, '+91')) {
        $mobile = substr($mobile, 3);
    } elseif (
        str_starts_with($mobile, '91') &&
        strlen($mobile) === 12
    ) {
        $mobile = substr($mobile, 2);
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        sendResponse(
            false,
            'Mobile number must be a valid 10-digit Indian mobile number.',
            null,
            422
        );
    }
}

if ($hasEmail) {
    if ($data['email'] !== null && !is_string($data['email'])) {
        sendResponse(false, 'Email must be a string or null.', null, 422);
    }

    $email = $data['email'] === null
        ? null
        : strtolower(trim($data['email']));

    if ($email === '') {
        $email = null;
    }

    if ($email !== null) {
        if (mb_strlen($email) > 150) {
            sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, 'Please enter a valid email address.', null, 422);
        }
    }
}

if ($hasDateOfBirth) {
    if (
        $data['date_of_birth'] !== null &&
        !is_string($data['date_of_birth'])
    ) {
        sendResponse(false, 'date_of_birth must be a string or null.', null, 422);
    }

    $dateOfBirth = $data['date_of_birth'] === null
        ? null
        : trim($data['date_of_birth']);

    if ($dateOfBirth === '') {
        $dateOfBirth = null;
    }

    if ($dateOfBirth !== null) {
        $date = DateTime::createFromFormat(
            'Y-m-d',
            $dateOfBirth
        );

        $errors = DateTime::getLastErrors();

        $hasErrors = $errors !== false &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            );

        if (
            !$date ||
            $hasErrors ||
            $date->format('Y-m-d') !== $dateOfBirth
        ) {
            sendResponse(
                false,
                'date_of_birth must be in YYYY-MM-DD format.',
                null,
                422
            );
        }

        $today = new DateTime('today');

        if ($date > $today) {
            sendResponse(
                false,
                'date_of_birth cannot be a future date.',
                null,
                422
            );
        }
    }
}

try {
    $pdo->beginTransaction();

    $currentStmt = $pdo->prepare(
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
         LIMIT 1
         FOR UPDATE"
    );

    $currentStmt->bindValue(
        ':id',
        $userId,
        PDO::PARAM_INT
    );

    $currentStmt->execute();

    $currentUser = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        $pdo->rollBack();

        sendResponse(
            false,
            'User not found.',
            null,
            404
        );
    }

    if ($currentUser['status'] === 'blocked') {
        $pdo->rollBack();

        sendResponse(
            false,
            'Blocked users cannot update their profile.',
            null,
            403
        );
    }

    if ($currentUser['status'] === 'inactive') {
        $pdo->rollBack();

        sendResponse(
            false,
            'Inactive users cannot update their profile.',
            null,
            403
        );
    }

    if ($hasMobile) {
        $mobileStmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE mobile = :mobile
             AND id != :id
             LIMIT 1"
        );

        $mobileStmt->bindValue(
            ':mobile',
            $mobile,
            PDO::PARAM_STR
        );

        $mobileStmt->bindValue(
            ':id',
            $userId,
            PDO::PARAM_INT
        );

        $mobileStmt->execute();

        if ($mobileStmt->fetch()) {
            $pdo->rollBack();

            sendResponse(
                false,
                'Mobile number is already registered with another account.',
                null,
                409
            );
        }
    }

    if ($hasEmail && $email !== null) {
        $emailStmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE LOWER(email) = LOWER(:email)
             AND id != :id
             LIMIT 1"
        );

        $emailStmt->bindValue(
            ':email',
            $email,
            PDO::PARAM_STR
        );

        $emailStmt->bindValue(
            ':id',
            $userId,
            PDO::PARAM_INT
        );

        $emailStmt->execute();

        if ($emailStmt->fetch()) {
            $pdo->rollBack();

            sendResponse(
                false,
                'Email address is already registered with another account.',
                null,
                409
            );
        }
    }

    $updateFields = [];
    $params = [
        ':id' => $userId
    ];

    if ($hasName) {
        $updateFields[] = 'name = :name';
        $params[':name'] = $name;
    }

    if ($hasMobile) {
        $updateFields[] = 'mobile = :mobile';
        $params[':mobile'] = $mobile;
    }

    if ($hasEmail) {
        $updateFields[] = 'email = :email';
        $params[':email'] = $email;
    }

    if ($hasDateOfBirth) {
        $updateFields[] = 'date_of_birth = :date_of_birth';
        $params[':date_of_birth'] = $dateOfBirth;
    }

    if (empty($updateFields)) {
        $pdo->rollBack();

        sendResponse(
            false,
            'No valid fields provided for update.',
            null,
            422
        );
    }

    $sql = "
        UPDATE users
        SET " . implode(', ', $updateFields) . "
        WHERE id = :id
    ";

    $updateStmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if ($key === ':id') {
            $updateStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );
        } elseif ($value === null) {
            $updateStmt->bindValue(
                $key,
                null,
                PDO::PARAM_NULL
            );
        } else {
            $updateStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }

    $updateStmt->execute();

    $fetchStmt = $pdo->prepare(
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

    $fetchStmt->bindValue(
        ':id',
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
        'Profile updated successfully.',
        [
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'mobile' => $user['mobile'],
                'email' => $user['email'],
                'date_of_birth' => $user['date_of_birth'],
                'status' => $user['status'],
                'last_login' => $user['last_login'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at']
            ]
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'mobile')) {
            sendResponse(
                false,
                'Mobile number is already registered with another account.',
                null,
                409
            );
        }

        if (str_contains($message, 'email')) {
            sendResponse(
                false,
                'Email address is already registered with another account.',
                null,
                409
            );
        }
    }

    sendResponse(
        false,
        'Unable to update profile.',
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
        'An unexpected error occurred.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}