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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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

$data = getJsonInput();

if (!is_array($data)) {
    sendResponse(false, 'Invalid JSON request body.', null, 400);
}

$allowedFields = ['name', 'mobile', 'email', 'password', 'date_of_birth'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", ['allowed_fields' => $allowedFields], 422);
    }
}

$name = isset($data['name']) ? trim((string)$data['name']) : '';
$mobile = isset($data['mobile']) ? trim((string)$data['mobile']) : '';
$email = isset($data['email']) ? strtolower(trim((string)$data['email'])) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';
$dateOfBirth = isset($data['date_of_birth']) ? trim((string)$data['date_of_birth']) : '';

if ($name === '') {
    sendResponse(false, 'Name is required.', null, 422);
}

if ($mobile === '') {
    sendResponse(false, 'Mobile number is required.', null, 422);
}

if ($email === '') {
    sendResponse(false, 'Email address is required.', null, 422);
}

if ($password === '') {
    sendResponse(false, 'Password is required.', null, 422);
}

$name = preg_replace('/\s+/u', ' ', $name);

if ($name === null || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    sendResponse(false, 'Name must be between 2 and 100 characters.', null, 422);
}

if (!preg_match("/^[\p{L}][\p{L}\s.'-]*$/u", $name)) {
    sendResponse(false, 'Name contains invalid characters.', null, 422);
}

$mobile = preg_replace('/\s+/', '', $mobile);

if ($mobile === null || $mobile === '') {
    sendResponse(false, 'Invalid mobile number.', null, 422);
}

if (str_starts_with($mobile, '+91')) {
    $mobile = substr($mobile, 3);
} elseif (str_starts_with($mobile, '91') && strlen($mobile) === 12) {
    $mobile = substr($mobile, 2);
}

if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    sendResponse(false, 'Please enter a valid 10-digit Indian mobile number.', null, 422);
}

if (preg_match('/^(\d)\1{9}$/', $mobile)) {
    sendResponse(false, 'Invalid mobile number.', null, 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', null, 422);
}

if (strlen($email) > 150) {
    sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
}

if ($dateOfBirth !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
    $errors = DateTime::getLastErrors();

    $hasErrors = $errors !== false &&
        ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $dateOfBirth) {
        sendResponse(false, 'Date of birth must be in YYYY-MM-DD format.', null, 422);
    }

    if ($date > new DateTime('today')) {
        sendResponse(false, 'Date of birth cannot be a future date.', null, 422);
    }
} else {
    $dateOfBirth = null;
}

if (strlen($password) < 8) {
    sendResponse(false, 'Password must contain at least 8 characters.', null, 422);
}

if (strlen($password) > 72) {
    sendResponse(false, 'Password must not exceed 72 characters.', null, 422);
}

if (preg_match('/\s/', $password)) {
    sendResponse(false, 'Password must not contain spaces.', null, 422);
}

if (!preg_match('/[A-Z]/', $password)) {
    sendResponse(false, 'Password must contain at least one uppercase letter.', null, 422);
}

if (!preg_match('/[a-z]/', $password)) {
    sendResponse(false, 'Password must contain at least one lowercase letter.', null, 422);
}

if (!preg_match('/[0-9]/', $password)) {
    sendResponse(false, 'Password must contain at least one number.', null, 422);
}

if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    sendResponse(false, 'Password must contain at least one special character.', null, 422);
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = :mobile LIMIT 1");
    $stmt->bindValue(':mobile', $mobile, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(false, 'Mobile number is already registered.', null, 409);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse(false, 'Email address is already registered.', null, 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if ($hashedPassword === false) {
        sendResponse(false, 'Unable to secure password.', null, 500);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO users
        (name, mobile, email, password, date_of_birth, status)
        VALUES
        (:name, :mobile, :email, :password, :date_of_birth, 'active')"
    );

    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':mobile', $mobile, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);

    if ($dateOfBirth === null) {
        $stmt->bindValue(':date_of_birth', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':date_of_birth', $dateOfBirth, PDO::PARAM_STR);
    }

    $stmt->execute();

    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "SELECT id, name, mobile, email, date_of_birth, status, last_login, created_at, updated_at
        FROM users
        WHERE id = :id
        LIMIT 1"
    );

    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new RuntimeException('Registered user could not be retrieved.');
    }

    $token = generateUserJWT($user);

    $pdo->commit();

    sendResponse(true, 'User registered successfully.', [
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
        ],
        'token' => $token
    ], 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'mobile')) {
            sendResponse(false, 'Mobile number is already registered.', null, 409);
        }

        if (str_contains($message, 'email')) {
            sendResponse(false, 'Email address is already registered.', null, 409);
        }

        sendResponse(false, 'Mobile number or email address is already registered.', null, 409);
    }

    sendResponse(
        false,
        'Unable to register user.',
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
        'Unable to register user.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}