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
| AUTHENTICATE TOKEN
|--------------------------------------------------------------------------
|
| First identify whether the token belongs to:
| - admin
| - user
|
|--------------------------------------------------------------------------
*/

$decoded = authenticate();

validateJWTData($decoded);

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

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
| ADDRESS ID
|--------------------------------------------------------------------------
*/

$addressIdInput = isset($_GET['address_id'])
    ? trim((string)$_GET['address_id'])
    : '';

if ($addressIdInput === '') {
    sendResponse(
        false,
        'Address ID is required.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| ADDRESS ID FORMAT
|--------------------------------------------------------------------------
*/

if (!preg_match('/^[1-9][0-9]*$/', $addressIdInput)) {
    sendResponse(
        false,
        'Address ID must be a valid positive integer.',
        null,
        422
    );
}

$addressId = (int)$addressIdInput;

if ($addressId <= 0) {
    sendResponse(
        false,
        'Invalid address ID.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS
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
    | FETCH ANY USER ADDRESS
    |--------------------------------------------------------------------------
    |
    | Admin can see any address.
    | Also include basic user information.
    |
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $pdo->prepare(
            "SELECT
                ua.id,
                ua.user_id,
                ua.address_type,
                ua.door_no,
                ua.street,
                ua.area,
                ua.city,
                ua.district,
                ua.state,
                ua.pincode,
                ua.landmark,
                ua.is_default,
                ua.status,
                ua.created_at,
                ua.updated_at,

                u.name AS user_name,
                u.mobile AS user_mobile,
                u.email AS user_email,
                u.date_of_birth AS user_date_of_birth,
                u.status AS user_status,
                u.last_login AS user_last_login,
                u.created_at AS user_created_at,
                u.updated_at AS user_updated_at

             FROM user_addresses ua

             INNER JOIN users u
                ON u.id = ua.user_id

             WHERE ua.id = :address_id

             LIMIT 1"
        );

        $stmt->bindValue(
            ':address_id',
            $addressId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $address = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        sendResponse(
            false,
            'Unable to retrieve address details.',
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
    | ADDRESS NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (!$address) {
        sendResponse(
            false,
            'Address not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Address details retrieved successfully.',
        [
            'address' => [

                'id' =>
                    (int)$address['id'],

                'user_id' =>
                    (int)$address['user_id'],

                'address_type' =>
                    $address['address_type'],

                'door_no' =>
                    $address['door_no'],

                'street' =>
                    $address['street'],

                'area' =>
                    $address['area'],

                'city' =>
                    $address['city'],

                'district' =>
                    $address['district'],

                'state' =>
                    $address['state'],

                'pincode' =>
                    $address['pincode'],

                'landmark' =>
                    $address['landmark'],

                'is_default' =>
                    (int)$address['is_default'],

                'status' =>
                    $address['status'],

                'created_at' =>
                    $address['created_at'],

                'updated_at' =>
                    $address['updated_at']
            ],

            'user' => [

                'id' =>
                    (int)$address['user_id'],

                'name' =>
                    $address['user_name'],

                'mobile' =>
                    $address['user_mobile'],

                'email' =>
                    $address['user_email'],

                'date_of_birth' =>
                    $address['user_date_of_birth'],

                'status' =>
                    $address['user_status'],

                'last_login' =>
                    $address['user_last_login'],

                'created_at' =>
                    $address['user_created_at'],

                'updated_at' =>
                    $address['user_updated_at']
            ],

            'access' => [
                'viewer_type' => 'admin',
                'can_view_all_addresses' => true
            ]
        ],
        200
    );
}

/*
|--------------------------------------------------------------------------
| USER ACCESS
|--------------------------------------------------------------------------
*/

if ($accountType === 'user') {

    /*
    |--------------------------------------------------------------------------
    | VERIFY USER
    |--------------------------------------------------------------------------
    */

    $userAuth = authenticateUser();

    $userId = getAuthenticatedId($userAuth);

    if ($userId <= 0) {
        sendResponse(
            false,
            'Invalid authenticated user.',
            null,
            401
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH OWN ADDRESS ONLY
    |--------------------------------------------------------------------------
    |
    | Important:
    |
    | WHERE ua.id = address_id
    | AND ua.user_id = JWT user ID
    |
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $pdo->prepare(
            "SELECT
                ua.id,
                ua.user_id,
                ua.address_type,
                ua.door_no,
                ua.street,
                ua.area,
                ua.city,
                ua.district,
                ua.state,
                ua.pincode,
                ua.landmark,
                ua.is_default,
                ua.status,
                ua.created_at,
                ua.updated_at

             FROM user_addresses ua

             WHERE ua.id = :address_id
             AND ua.user_id = :user_id

             LIMIT 1"
        );

        $stmt->bindValue(
            ':address_id',
            $addressId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $address = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        sendResponse(
            false,
            'Unable to retrieve address details.',
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
    | ADDRESS NOT FOUND / NOT OWNED
    |--------------------------------------------------------------------------
    |
    | We intentionally return 404 instead of telling the user
    | that the address belongs to another user.
    |
    |--------------------------------------------------------------------------
    */

    if (!$address) {
        sendResponse(
            false,
            'Address not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USER SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Address details retrieved successfully.',
        [
            'address' => [

                'id' =>
                    (int)$address['id'],

                'user_id' =>
                    (int)$address['user_id'],

                'address_type' =>
                    $address['address_type'],

                'door_no' =>
                    $address['door_no'],

                'street' =>
                    $address['street'],

                'area' =>
                    $address['area'],

                'city' =>
                    $address['city'],

                'district' =>
                    $address['district'],

                'state' =>
                    $address['state'],

                'pincode' =>
                    $address['pincode'],

                'landmark' =>
                    $address['landmark'],

                'is_default' =>
                    (int)$address['is_default'],

                'status' =>
                    $address['status'],

                'created_at' =>
                    $address['created_at'],

                'updated_at' =>
                    $address['updated_at']
            ],

            'access' => [
                'viewer_type' => 'user',
                'can_view_all_addresses' => false,
                'own_address' => true
            ]
        ],
        200
    );
}

/*
|--------------------------------------------------------------------------
| INVALID ACCOUNT TYPE
|--------------------------------------------------------------------------
*/

sendResponse(
    false,
    'Invalid account type.',
    null,
    401
);