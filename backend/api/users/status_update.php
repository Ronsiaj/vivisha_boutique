<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

/*
|--------------------------------------------------------------------------
| RESPONSE HEADERS
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header('Pragma: no-cache');

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
|
| Development:
| *
|
| Production:
| Replace * with your actual frontend/admin panel domain.
|
|--------------------------------------------------------------------------
*/

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header(
    'Access-Control-Allow-Headers: Content-Type, Authorization'
);

/*
|--------------------------------------------------------------------------
| OPTIONS REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {

    sendResponse(
        false,
        'Only PUT method is allowed.',
        null,
        405
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
|
| authenticateAdmin() performs:
|
| - Bearer token validation
| - JWT validation
| - Admin account verification
| - Admin status verification
|
|--------------------------------------------------------------------------
*/

$admin = authenticateAdmin();

/*
|--------------------------------------------------------------------------
| ADMIN ROLE CHECK
|--------------------------------------------------------------------------
*/

checkAdminRole(
    $admin,
    ['admin']
);

/*
|--------------------------------------------------------------------------
| READ JSON INPUT
|--------------------------------------------------------------------------
*/

$data = getJsonInput();

/*
|--------------------------------------------------------------------------
| VALIDATE JSON BODY
|--------------------------------------------------------------------------
*/

if (!is_array($data)) {

    sendResponse(
        false,
        'Invalid JSON request body.',
        null,
        400
    );
}

/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/

$userIdInput = isset($data['id'])
    ? trim((string) $data['id'])
    : '';

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$newStatus = isset($data['status'])
    ? strtolower(trim((string) $data['status']))
    : '';

/*
|--------------------------------------------------------------------------
| USER ID REQUIRED
|--------------------------------------------------------------------------
*/

if ($userIdInput === '') {

    sendResponse(
        false,
        'User ID is required.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| USER ID FORMAT VALIDATION
|--------------------------------------------------------------------------
|
| Only positive integer IDs are accepted.
|
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[1-9][0-9]*$/',
        $userIdInput
    )
) {

    sendResponse(
        false,
        'User ID must be a valid positive integer.',
        null,
        422
    );
}

$userId = (int) $userIdInput;

/*
|--------------------------------------------------------------------------
| INTEGER SAFETY
|--------------------------------------------------------------------------
*/

if ($userId <= 0) {

    sendResponse(
        false,
        'Invalid user ID.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| STATUS REQUIRED
|--------------------------------------------------------------------------
*/

if ($newStatus === '') {

    sendResponse(
        false,
        'Status is required.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| ALLOWED USER STATUSES
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'inactive',
    'blocked'
];

/*
|--------------------------------------------------------------------------
| STATUS VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $newStatus,
        $allowedStatuses,
        true
    )
) {

    sendResponse(
        false,
        'Invalid status. Allowed values are active, inactive, blocked.',
        [
            'allowed_statuses' => $allowedStatuses
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
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

    $stmt->bindValue(
        ':id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $user = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    sendResponse(
        false,
        'Unable to verify user account.',
        APP_ENV === 'development'
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );
}

/*
|--------------------------------------------------------------------------
| USER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$user) {

    sendResponse(
        false,
        'User not found.',
        null,
        404
    );
}

/*
|--------------------------------------------------------------------------
| CURRENT STATUS
|--------------------------------------------------------------------------
*/

$currentStatus =
    (string) $user['status'];

/*
|--------------------------------------------------------------------------
| SAME STATUS CHECK
|--------------------------------------------------------------------------
*/

if ($currentStatus === $newStatus) {

    sendResponse(
        true,
        'User status is already ' . $newStatus . '.',
        [
            'user' => [
                'id' =>
                    (int) $user['id'],

                'name' =>
                    $user['name'],

                'mobile' =>
                    $user['mobile'],

                'email' =>
                    $user['email'],

                'date_of_birth' =>
                    $user['date_of_birth'],

                'status' =>
                    $currentStatus,

                'last_login' =>
                    $user['last_login'],

                'created_at' =>
                    $user['created_at'],

                'updated_at' =>
                    $user['updated_at']
            ],

            'status_update' => [
                'previous_status' =>
                    $currentStatus,

                'new_status' =>
                    $newStatus,

                'changed' =>
                    false
            ]
        ],
        200
    );
}

/*
|--------------------------------------------------------------------------
| UPDATE USER STATUS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "UPDATE users
         SET
            status = :status,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->bindValue(
        ':status',
        $newStatus,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

} catch (PDOException $e) {

    sendResponse(
        false,
        'Unable to update user status.',
        APP_ENV === 'development'
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );
}

/*
|--------------------------------------------------------------------------
| VERIFY UPDATED USER
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
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

    $stmt->bindValue(
        ':id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $updatedUser =
        $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    sendResponse(
        false,
        'User status updated, but updated details could not be retrieved.',
        APP_ENV === 'development'
            ? [
                'error' =>
                    $e->getMessage()
            ]
            : null,
        500
    );
}

/*
|--------------------------------------------------------------------------
| UPDATED USER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$updatedUser) {

    sendResponse(
        false,
        'User status updated, but user details could not be retrieved.',
        null,
        500
    );
}

/*
|--------------------------------------------------------------------------
| SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/

sendResponse(
    true,
    'User status updated successfully.',
    [
        'user' => [
            'id' =>
                (int) $updatedUser['id'],

            'name' =>
                $updatedUser['name'],

            'mobile' =>
                $updatedUser['mobile'],

            'email' =>
                $updatedUser['email'],

            'date_of_birth' =>
                $updatedUser['date_of_birth'],

            'status' =>
                $updatedUser['status'],

            'last_login' =>
                $updatedUser['last_login'],

            'created_at' =>
                $updatedUser['created_at'],

            'updated_at' =>
                $updatedUser['updated_at']
        ],

        'status_update' => [
            'previous_status' =>
                $currentStatus,

            'new_status' =>
                $newStatus,

            'changed' =>
                true
        ],

        'updated_by' => [
            'admin_id' =>
                (int) $admin->data->id,

            'admin_name' =>
                $admin->data->name,

            'admin_email' =>
                $admin->data->email
        ]
    ],
    200
);