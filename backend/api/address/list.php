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
| OPTIONS
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


$decoded = authenticate();

validateJWTData($decoded);

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {

    sendResponse(
        false,
        'Invalid authenticated account.',
        null,
        401
    );
}

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/

$isAdmin = false;
$isUser = false;
$userId = null;

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

if ($accountType === 'admin') {

    $adminAuth = authenticateAdmin();

    checkAdminRole(
        $adminAuth,
        ['admin']
    );

    $isAdmin = true;
}

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

elseif ($accountType === 'user') {


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

    $isUser = true;
}

/*
|--------------------------------------------------------------------------
| INVALID ACCOUNT TYPE
|--------------------------------------------------------------------------
*/

else {

    sendResponse(
        false,
        'Invalid account type.',
        null,
        401
    );
}

$q = isset($_GET['q'])
    ? trim((string)$_GET['q'])
    : '';

/*
|--------------------------------------------------------------------------
| ADDRESS TYPE
|--------------------------------------------------------------------------
*/

$addressType = isset($_GET['address_type'])
    ? strtolower(trim((string)$_GET['address_type']))
    : '';

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status = isset($_GET['status'])
    ? strtolower(trim((string)$_GET['status']))
    : '';

/*
|--------------------------------------------------------------------------
| IS DEFAULT
|--------------------------------------------------------------------------
*/

$isDefault = isset($_GET['is_default'])
    ? trim((string)$_GET['is_default'])
    : '';

/*
|--------------------------------------------------------------------------
| CREATED DATE
|--------------------------------------------------------------------------
*/

$createdFrom = isset($_GET['created_from'])
    ? trim((string)$_GET['created_from'])
    : '';

$createdTo = isset($_GET['created_to'])
    ? trim((string)$_GET['created_to'])
    : '';


$filterUserId = isset($_GET['user_id'])
    ? trim((string)$_GET['user_id'])
    : '';

/*
|--------------------------------------------------------------------------
| USER_ID ACCESS VALIDATION
|--------------------------------------------------------------------------
*/

if ($filterUserId !== '' && !$isAdmin) {

    sendResponse(
        false,
        'You are not allowed to filter addresses by user ID.',
        null,
        403
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN USER ID FILTER VALIDATION
|--------------------------------------------------------------------------
*/

$adminFilterUserId = null;

if ($filterUserId !== '') {

    if (!preg_match('/^[1-9][0-9]*$/', $filterUserId)) {

        sendResponse(
            false,
            'user_id must be a valid positive integer.',
            null,
            422
        );
    }

    $adminFilterUserId = (int)$filterUserId;

    if ($adminFilterUserId <= 0) {

        sendResponse(
            false,
            'Invalid user ID.',
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$pageInput = $_GET['page'] ?? '1';

if (
    filter_var(
        $pageInput,
        FILTER_VALIDATE_INT
    ) === false
) {

    sendResponse(
        false,
        'Page must be a valid integer.',
        null,
        422
    );
}

$page = (int)$pageInput;

if ($page < 1) {

    sendResponse(
        false,
        'Page must be greater than 0.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| LIMIT
|--------------------------------------------------------------------------
*/

$limitInput = $_GET['limit'] ?? '20';

if (
    filter_var(
        $limitInput,
        FILTER_VALIDATE_INT
    ) === false
) {

    sendResponse(
        false,
        'Limit must be a valid integer.',
        null,
        422
    );
}

$limit = (int)$limitInput;

if ($limit < 1 || $limit > 100) {

    sendResponse(
        false,
        'Limit must be between 1 and 100.',
        null,
        422
    );
}

$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| q VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $q !== '' &&
    mb_strlen($q) > 150
) {

    sendResponse(
        false,
        'Search query must not exceed 150 characters.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| ADDRESS TYPE VALIDATION
|--------------------------------------------------------------------------
*/

$allowedAddressTypes = [
    'home',
    'work',
    'other'
];

if (
    $addressType !== '' &&
    !in_array(
        $addressType,
        $allowedAddressTypes,
        true
    )
) {

    sendResponse(
        false,
        'Invalid address type.',
        [
            'allowed_address_types' =>
                $allowedAddressTypes
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| STATUS VALIDATION
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'inactive'
];

if (
    $status !== '' &&
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    sendResponse(
        false,
        'Invalid status.',
        [
            'allowed_statuses' =>
                $allowedStatuses
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| IS DEFAULT VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $isDefault !== '' &&
    !in_array(
        $isDefault,
        ['0', '1'],
        true
    )
) {

    sendResponse(
        false,
        'is_default must be 0 or 1.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

function validateAddressListDate(
    string $value,
    string $field
): void {

    if ($value === '') {
        return;
    }

    $date = DateTime::createFromFormat(
        'Y-m-d',
        $value
    );

    $errors = DateTime::getLastErrors();

    $hasErrors = false;

    if ($errors !== false) {

        $hasErrors =
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0;
    }

    if (
        !$date ||
        $hasErrors ||
        $date->format('Y-m-d') !== $value
    ) {

        sendResponse(
            false,
            $field . ' must be in YYYY-MM-DD format.',
            null,
            422
        );
    }
}

validateAddressListDate(
    $createdFrom,
    'created_from'
);

validateAddressListDate(
    $createdTo,
    'created_to'
);

/*
|--------------------------------------------------------------------------
| DATE RANGE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $createdFrom !== '' &&
    $createdTo !== '' &&
    $createdFrom > $createdTo
) {

    sendResponse(
        false,
        'created_from cannot be greater than created_to.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$allowedSortColumns = [
    'id',
    'user_id',
    'address_type',
    'city',
    'district',
    'state',
    'pincode',
    'is_default',
    'status',
    'created_at',
    'updated_at'
];

$sortBy = isset($_GET['sort_by'])
    ? trim((string)$_GET['sort_by'])
    : 'created_at';

$sortOrder = isset($_GET['sort_order'])
    ? strtolower(trim((string)$_GET['sort_order']))
    : 'desc';

/*
|--------------------------------------------------------------------------
| SORT BY VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $sortBy,
        $allowedSortColumns,
        true
    )
) {

    sendResponse(
        false,
        'Invalid sort_by value.',
        [
            'allowed_values' =>
                $allowedSortColumns
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| SORT ORDER VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $sortOrder,
        ['asc', 'desc'],
        true
    )
) {

    sendResponse(
        false,
        'sort_order must be asc or desc.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | WHERE CONDITIONS
    |--------------------------------------------------------------------------
    */

    $where = [];
    $params = [];


    if ($isUser) {

        $where[] =
            'ua.user_id = :authenticated_user_id';

        $params[':authenticated_user_id'] =
            $userId;
    }

    if (
        $isAdmin &&
        $adminFilterUserId !== null
    ) {

        /*
        |--------------------------------------------------------------------------
        | Verify requested user exists
        |--------------------------------------------------------------------------
        */

        $userCheckStmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE id = :user_id
             LIMIT 1"
        );

        $userCheckStmt->bindValue(
            ':user_id',
            $adminFilterUserId,
            PDO::PARAM_INT
        );

        $userCheckStmt->execute();

        if (!$userCheckStmt->fetch()) {

            sendResponse(
                false,
                'User not found.',
                null,
                404
            );
        }

        $where[] =
            'ua.user_id = :filter_user_id';

        $params[':filter_user_id'] =
            $adminFilterUserId;
    }


    if ($q !== '') {

        $where[] = "
            (
                CAST(ua.id AS CHAR) LIKE :q_address_id

                OR CAST(ua.user_id AS CHAR) LIKE :q_user_id

                OR ua.address_type LIKE :q_address_type

                OR ua.door_no LIKE :q_door_no

                OR ua.street LIKE :q_street

                OR ua.area LIKE :q_area

                OR ua.city LIKE :q_city

                OR ua.district LIKE :q_district

                OR ua.state LIKE :q_state

                OR ua.pincode LIKE :q_pincode

                OR ua.landmark LIKE :q_landmark

                OR u.name LIKE :q_user_name

                OR u.mobile LIKE :q_user_mobile

                OR u.email LIKE :q_user_email
            )
        ";

        $searchValue =
            '%' . $q . '%';

        $params[':q_address_id'] =
            $searchValue;

        $params[':q_user_id'] =
            $searchValue;

        $params[':q_address_type'] =
            $searchValue;

        $params[':q_door_no'] =
            $searchValue;

        $params[':q_street'] =
            $searchValue;

        $params[':q_area'] =
            $searchValue;

        $params[':q_city'] =
            $searchValue;

        $params[':q_district'] =
            $searchValue;

        $params[':q_state'] =
            $searchValue;

        $params[':q_pincode'] =
            $searchValue;

        $params[':q_landmark'] =
            $searchValue;

        $params[':q_user_name'] =
            $searchValue;

        $params[':q_user_mobile'] =
            $searchValue;

        $params[':q_user_email'] =
            $searchValue;
    }

    /*
    |--------------------------------------------------------------------------
    | ADDRESS TYPE FILTER
    |--------------------------------------------------------------------------
    */

    if ($addressType !== '') {

        $where[] =
            'ua.address_type = :address_type';

        $params[':address_type'] =
            $addressType;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS FILTER
    |--------------------------------------------------------------------------
    */

    if ($status !== '') {

        $where[] =
            'ua.status = :status';

        $params[':status'] =
            $status;
    }

    /*
    |--------------------------------------------------------------------------
    | DEFAULT FILTER
    |--------------------------------------------------------------------------
    */

    if ($isDefault !== '') {

        $where[] =
            'ua.is_default = :is_default';

        $params[':is_default'] =
            (int)$isDefault;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATED FROM
    |--------------------------------------------------------------------------
    */

    if ($createdFrom !== '') {

        $where[] =
            'ua.created_at >= :created_from';

        $params[':created_from'] =
            $createdFrom . ' 00:00:00';
    }

    /*
    |--------------------------------------------------------------------------
    | CREATED TO
    |--------------------------------------------------------------------------
    */

    if ($createdTo !== '') {

        $where[] =
            'ua.created_at <= :created_to';

        $params[':created_to'] =
            $createdTo . ' 23:59:59';
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD WHERE SQL
    |--------------------------------------------------------------------------
    */

    $whereSql = '';

    if (!empty($where)) {

        $whereSql =
            ' WHERE ' .
            implode(' AND ', $where);
    }

    /*
    |--------------------------------------------------------------------------
    | COUNT QUERY
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)

        FROM user_addresses ua

        INNER JOIN users u
            ON u.id = ua.user_id

        $whereSql
    ";

    $countStmt =
        $pdo->prepare($countSql);

    /*
    |--------------------------------------------------------------------------
    | BIND COUNT PARAMETERS
    |--------------------------------------------------------------------------
    */

    foreach ($params as $key => $value) {

        if (is_int($value)) {

            $countStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } else {

            $countStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }

    $countStmt->execute();

    $totalRecords =
        (int)$countStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | TOTAL PAGES
    |--------------------------------------------------------------------------
    */

    $totalPages =
        $totalRecords > 0
            ? (int)ceil(
                $totalRecords / $limit
            )
            : 0;

 
    $sql = "
        SELECT

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
            u.status AS user_status

        FROM user_addresses ua

        INNER JOIN users u
            ON u.id = ua.user_id

        $whereSql

        ORDER BY
            ua.is_default DESC,
            ua.`$sortBy` $sortOrder

        LIMIT :limit
        OFFSET :offset
    ";

    $stmt =
        $pdo->prepare($sql);

    /*
    |--------------------------------------------------------------------------
    | BIND PARAMETERS
    |--------------------------------------------------------------------------
    */

    foreach ($params as $key => $value) {

        if (is_int($value)) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } else {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
    */

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $addresses =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | FORMAT RESULT
    |--------------------------------------------------------------------------
    */

    $formattedAddresses = [];

    foreach ($addresses as $address) {

        $addressData = [

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
        ];

        /*
        |--------------------------------------------------------------------------
        | ADMIN OWNER DETAILS
        |--------------------------------------------------------------------------
        |
        | Admin gets user information for each address.
        |
        |--------------------------------------------------------------------------
        */

        if ($isAdmin) {

            $addressData['user'] = [

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
                    $address['user_status']
            ];
        }

        $formattedAddresses[] =
            $addressData;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Addresses retrieved successfully.',
        [
            /*
            |--------------------------------------------------------------------------
            | ACCESS INFORMATION
            |--------------------------------------------------------------------------
            */

            'access' => [

                'viewer_type' =>
                    $isAdmin
                        ? 'admin'
                        : 'user',

                'can_view_all_addresses' =>
                    $isAdmin
            ],

            /*
            |--------------------------------------------------------------------------
            | ADDRESSES
            |--------------------------------------------------------------------------
            */

            'addresses' =>
                $formattedAddresses,

            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            'pagination' => [

                'page' =>
                    $page,

                'limit' =>
                    $limit,

                'total_records' =>
                    $totalRecords,

                'total_pages' =>
                    $totalPages,

                'has_previous' =>
                    $page > 1,

                'has_next' =>
                    $page < $totalPages
            ],

            /*
            |--------------------------------------------------------------------------
            | FILTERS
            |--------------------------------------------------------------------------
            */

            'filters' => [

                'q' =>
                    $q !== ''
                        ? $q
                        : null,

                'user_id' =>
                    $adminFilterUserId,

                'address_type' =>
                    $addressType !== ''
                        ? $addressType
                        : null,

                'status' =>
                    $status !== ''
                        ? $status
                        : null,

                'is_default' =>
                    $isDefault !== ''
                        ? (int)$isDefault
                        : null,

                'created_from' =>
                    $createdFrom !== ''
                        ? $createdFrom
                        : null,

                'created_to' =>
                    $createdTo !== ''
                        ? $createdTo
                        : null
            ],

            /*
            |--------------------------------------------------------------------------
            | SORTING
            |--------------------------------------------------------------------------
            */

            'sorting' => [

                'sort_by' =>
                    $sortBy,

                'sort_order' =>
                    $sortOrder
            ]
        ],
        200
    );

} catch (PDOException $e) {

    sendResponse(
        false,
        'Unable to retrieve addresses.',
        APP_ENV === 'development'
            ? [
                'error' =>
                    $e->getMessage()
            ]
            : null,
        500
    );

} catch (Throwable $e) {

    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development'
            ? [
                'error' =>
                    $e->getMessage()
            ]
            : null,
        500
    );
}