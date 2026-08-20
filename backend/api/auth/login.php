<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$data = getJsonInput();

$email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($email === '') {
    sendResponse(false, 'Email is required.', null, 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', null, 422);
}

if (strlen($email) > 150) {
    sendResponse(false, 'Email must not exceed 150 characters.', null, 422);
}

if ($password === '') {
    sendResponse(false, 'Password is required.', null, 422);
}

if (strlen($password) > 72) {
    sendResponse(false, 'Invalid login credentials.', null, 401);
}

try {
    /*
    |--------------------------------------------------------------------------
    | First check Admin
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT id,name,email,mobile,password,role,status,last_login,created_at,updated_at
         FROM admins
         WHERE email = :email
         LIMIT 1"
    );

    $stmt->execute([
        ':email' => $email
    ]);

    $account = $stmt->fetch();

    if ($account) {
        $accountType = 'admin';
    } else {
        /*
        |--------------------------------------------------------------------------
        | If Admin not found, check User
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "SELECT id,name,mobile,email,password,date_of_birth,status,last_login,created_at,updated_at
             FROM users
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute([
            ':email' => $email
        ]);

        $account = $stmt->fetch();

        if (!$account) {
            sendResponse(
                false,
                'Invalid email or password.',
                null,
                401
            );
        }

        $accountType = 'user';
    }

    /*
    |--------------------------------------------------------------------------
    | Account Status
    |--------------------------------------------------------------------------
    */

    if ($account['status'] !== 'active') {
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

    /*
    |--------------------------------------------------------------------------
    | Password Verification
    |--------------------------------------------------------------------------
    */

    if (!password_verify($password, $account['password'])) {
        sendResponse(
            false,
            'Invalid email or password.',
            null,
            401
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Last Login
    |--------------------------------------------------------------------------
    */

    $table = $accountType === 'admin' ? 'admins' : 'users';

    $stmt = $pdo->prepare(
        "UPDATE {$table}
         SET last_login = CURRENT_TIMESTAMP
         WHERE id = :id"
    );

    $stmt->execute([
        ':id' => (int)$account['id']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Generate JWT Based On Account Type
    |--------------------------------------------------------------------------
    */

    if ($accountType === 'admin') {
        $token = generateAdminJWT($account);

        $responseAccount = [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'email' => $account['email'],
            'mobile' => $account['mobile'],
            'role' => $account['role'],
            'status' => $account['status'],
            'last_login' => date('Y-m-d H:i:s')
        ];
    } else {
        $token = generateUserJWT($account);

        $responseAccount = [
            'id' => (int)$account['id'],
            'name' => $account['name'],
            'mobile' => $account['mobile'],
            'email' => $account['email'],
            'date_of_birth' => $account['date_of_birth'],
            'status' => $account['status'],
            'last_login' => date('Y-m-d H:i:s')
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        ucfirst($accountType) . ' login successful.',
        [
            'account_type' => $accountType,
            'account' => $responseAccount,
            'token' => $token
        ],
        200
    );

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to process login.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}