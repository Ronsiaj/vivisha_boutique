<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$data = getJsonInput();

$name = isset($data['name']) ? trim($data['name']) : '';
$email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
$mobile = isset($data['mobile']) ? trim($data['mobile']) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($name === '') {
    sendResponse(false, 'Name is required.', null, 422);
}

if ($email === '') {
    sendResponse(false, 'Email is required.', null, 422);
}

if ($mobile === '') {
    sendResponse(false, 'Mobile number is required.', null, 422);
}

if ($password === '') {
    sendResponse(false, 'Password is required.', null, 422);
}

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    sendResponse(false, 'Name must be between 2 and 100 characters.', null, 422);
}

if (preg_match('/\s{2,}/u', $name)) {
    sendResponse(false, 'Name must not contain multiple consecutive spaces.', null, 422);
}

if (!preg_match("/^[\p{L}][\p{L}\s.'-]*$/u", $name)) {
    sendResponse(false, 'Name contains invalid characters.', null, 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', null, 422);
}

if (strlen($email) > 150) {
    sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
}

if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    sendResponse(false, 'Please enter a valid 10-digit Indian mobile number.', null, 422);
}

if (preg_match('/^(\d)\1{9}$/', $mobile)) {
    sendResponse(false, 'Invalid mobile number.', null, 422);
}

if (strlen($password) < 8) {
    sendResponse(false, 'Password must contain at least 8 characters.', null, 422);
}

if (strlen($password) > 72) {
    sendResponse(false, 'Password must not exceed 72 characters.', null, 422);
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
    $stmt = $pdo->prepare(
        "SELECT id FROM admins WHERE email = :email LIMIT 1"
    );
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        sendResponse(false, 'Email address is already registered.', null, 409);
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM admins WHERE mobile = :mobile LIMIT 1"
    );
    $stmt->execute([':mobile' => $mobile]);

    if ($stmt->fetch()) {
        sendResponse(false, 'Mobile number is already registered.', null, 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if ($hashedPassword === false) {
        sendResponse(false, 'Unable to secure password.', null, 500);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO admins (name, email, mobile, password, role, status)
         VALUES (:name, :email, :mobile, :password, 'admin', 'active')"
    );

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':mobile' => $mobile,
        ':password' => $hashedPassword
    ]);

    $adminId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "SELECT id, name, email, mobile, role, status, last_login, created_at, updated_at
         FROM admins
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([':id' => $adminId]);

    $admin = $stmt->fetch();

    if (!$admin) {
        sendResponse(false, 'Admin registered but details could not be retrieved.', null, 500);
    }

    $token = generateAdminJWT($admin);

    sendResponse(
        true,
        'Admin registered successfully.',
        [
            'admin' => [
                'id' => (int)$admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'mobile' => $admin['mobile'],
                'role' => $admin['role'],
                'status' => $admin['status'],
                'last_login' => $admin['last_login'],
                'created_at' => $admin['created_at'],
                'updated_at' => $admin['updated_at']
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ],
        201
    );

} catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        sendResponse(false, 'Email or mobile number already exists.', null, 409);
    }

    sendResponse(
        false,
        'Unable to register admin.',
        APP_ENV === 'development' ? ['error' => $e->getMessage()] : null,
        500
    );
}