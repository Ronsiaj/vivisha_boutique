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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');



header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(
        false,
        'Only GET method is allowed.',
        null,
        405
    );
}

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
|
| We first authenticate the token to identify whether the requester
| is an admin or a normal user.
|
|--------------------------------------------------------------------------
*/

$decoded = authenticate();

validateJWTData($decoded);

/*
|--------------------------------------------------------------------------
| GET AUTHENTICATED ACCOUNT TYPE
|--------------------------------------------------------------------------
*/

$accountType = getAuthenticatedType($decoded);

$authenticatedId = getAuthenticatedId($decoded);

/*
|--------------------------------------------------------------------------
| VALIDATE AUTHENTICATED ID
|--------------------------------------------------------------------------
*/

if ($authenticatedId <= 0) {
    sendResponse(
        false,
        'Invalid authenticated account ID.',
        null,
        401
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
|--------------------------------------------------------------------------
|
| Admin can view any user by:
|
| GET /api/users/view.php?id=10
|
|--------------------------------------------------------------------------
*/

if ($accountType === 'admin') {

    /*
    |--------------------------------------------------------------------------
    | VERIFY ADMIN ACCOUNT
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
    | USER ID REQUIRED FOR ADMIN
    |--------------------------------------------------------------------------
    */

    if (!isset($_GET['id'])) {
        sendResponse(
            false,
            'User ID is required.',
            null,
            422
        );
    }

    $userIdInput = trim(
        (string) $_GET['id']
    );

    /*
    |--------------------------------------------------------------------------
    | USER ID VALIDATION
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
    | Only positive integer IDs allowed
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
    | Prevent integer overflow / invalid large IDs
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
    | FETCH USER
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
            'Unable to retrieve user details.',
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
    | FORMAT ADMIN RESPONSE
    |--------------------------------------------------------------------------
    */

    $userData = [
        'id' => (int) $user['id'],

        'name' => $user['name'],

        'mobile' => $user['mobile'],

        'email' => $user['email'],

        'date_of_birth' =>
            $user['date_of_birth'],

        'status' =>
            $user['status'],

        'last_login' =>
            $user['last_login'],

        'created_at' =>
            $user['created_at'],

        'updated_at' =>
            $user['updated_at']
    ];

    /*
    |--------------------------------------------------------------------------
    | ADMIN RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'User details retrieved successfully.',
        [
            'user' => $userData,

            'access' => [
                'viewer_type' => 'admin',
                'can_view_all_users' => true
            ]
        ],
        200
    );
}


if ($accountType === 'user') {


    $userAuth = authenticateUser();

    $authenticatedUserId =
        getAuthenticatedId($userAuth);

    if (isset($_GET['id'])) {

        $requestedId = trim(
            (string) $_GET['id']
        );

        /*
        |--------------------------------------------------------------------------
        | Validate requested ID
        |--------------------------------------------------------------------------
        */

        if ($requestedId === '') {
            sendResponse(
                false,
                'User ID cannot be empty.',
                null,
                422
            );
        }

        if (
            !preg_match(
                '/^[1-9][0-9]*$/',
                $requestedId
            )
        ) {
            sendResponse(
                false,
                'User ID must be a valid positive integer.',
                null,
                422
            );
        }

        $requestedUserId =
            (int) $requestedId;

        /*
        |--------------------------------------------------------------------------
        | OWN DATA CHECK
        |--------------------------------------------------------------------------
        |
        | This is the most important authorization check.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $requestedUserId !==
            $authenticatedUserId
        ) {
            sendResponse(
                false,
                'You can only view your own user details.',
                null,
                403
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch only authenticated user's data
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
            $authenticatedUserId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $user = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    } catch (PDOException $e) {

        sendResponse(
            false,
            'Unable to retrieve your user details.',
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
            'User account not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT USER RESPONSE
    |--------------------------------------------------------------------------
    */

    $userData = [
        'id' => (int) $user['id'],

        'name' => $user['name'],

        'mobile' => $user['mobile'],

        'email' => $user['email'],

        'date_of_birth' =>
            $user['date_of_birth'],

        'status' =>
            $user['status'],

        'last_login' =>
            $user['last_login'],

        'created_at' =>
            $user['created_at'],

        'updated_at' =>
            $user['updated_at']
    ];

    /*
    |--------------------------------------------------------------------------
    | USER RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Your user details retrieved successfully.',
        [
            'user' => $userData,

            'access' => [
                'viewer_type' => 'user',
                'can_view_all_users' => false
            ]
        ],
        200
    );
}

/*
|--------------------------------------------------------------------------
| UNKNOWN ACCOUNT TYPE
|--------------------------------------------------------------------------
*/

sendResponse(
    false,
    'Invalid account type.',
    null,
    401
);