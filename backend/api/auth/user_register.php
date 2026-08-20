<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$data = getJsonInput();

$name = isset($data['name']) ? trim($data['name']) : '';
$mobile = isset($data['mobile']) ? trim($data['mobile']) : '';
$email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';
$dateOfBirth = isset($data['date_of_birth']) ? trim($data['date_of_birth']) : '';

if ($name === '') {
    sendResponse(false, 'Name is required.', null, 422);
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

if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    sendResponse(false, 'Please enter a valid 10-digit Indian mobile number.', null, 422);
}

if (preg_match('/^(\d)\1{9}$/', $mobile)) {
    sendResponse(false, 'Invalid mobile number.', null, 422);
}

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Please enter a valid email address.', null, 422);
    }

    if (strlen($email) > 150) {
        sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
    }
} else {
    $email = null;
}

if ($dateOfBirth !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $dateOfBirth);

    if (!$date || $date->format('Y-m-d') !== $dateOfBirth) {
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
    $stmt->execute([':mobile' => $mobile]);

    if ($stmt->fetch()) {
        sendResponse(false, 'Mobile number is already registered.', null, 409);
    }

    if ($email !== null) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch()) {
            sendResponse(false, 'Email address is already registered.', null, 409);
        }
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    if ($hashedPassword === false) {
        sendResponse(false, 'Unable to secure password.', null, 500);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO users
        (name, mobile, email, password, date_of_birth,status)
        VALUES
        (:name, :mobile, :email, :password, :date_of_birth, 'active')"
    );

    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':mobile', $mobile, PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
    $stmt->bindValue(':date_of_birth', $dateOfBirth, $dateOfBirth === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->execute();

    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "SELECT id, name, mobile, email, date_of_birth, status, last_login, created_at, updated_at
         FROM users
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendResponse(false, 'User registered but details could not be retrieved.', null, 500);
    }

    $token = generateUserJWT($user);

    sendResponse(
        true,
        'User registered successfully.',
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
            ],
        
        ],
        201
    );

} catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        sendResponse(false, 'Mobile number or email address already exists.', null, 409);
    }

    sendResponse(
        false,
        'Unable to register user.',
        APP_ENV === 'development' ? ['error' => $e->getMessage()] : null,
        500
    );
}