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

$admin = authenticateAdmin();

/*
|--------------------------------------------------------------------------
| ADMIN ROLE CHECK
|--------------------------------------------------------------------------
*/

checkAdminRole($admin, ['admin']);

$q = isset($_GET['q'])
    ? trim((string) $_GET['q'])
    : '';

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status = isset($_GET['status'])
    ? trim((string) $_GET['status'])
    : '';


/*
|--------------------------------------------------------------------------
| DATE OF BIRTH FILTERS
|--------------------------------------------------------------------------
*/

$dobFrom = isset($_GET['dob_from'])
    ? trim((string) $_GET['dob_from'])
    : '';

$dobTo = isset($_GET['dob_to'])
    ? trim((string) $_GET['dob_to'])
    : '';

/*
|--------------------------------------------------------------------------
| CREATED DATE FILTERS
|--------------------------------------------------------------------------
*/

$createdFrom = isset($_GET['created_from'])
    ? trim((string) $_GET['created_from'])
    : '';

$createdTo = isset($_GET['created_to'])
    ? trim((string) $_GET['created_to'])
    : '';

/*
|--------------------------------------------------------------------------
| LAST LOGIN FILTERS
|--------------------------------------------------------------------------
*/

$lastLoginFrom = isset($_GET['last_login_from'])
    ? trim((string) $_GET['last_login_from'])
    : '';

$lastLoginTo = isset($_GET['last_login_to'])
    ? trim((string) $_GET['last_login_to'])
    : '';

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

$page = (int) $pageInput;

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

$limit = (int) $limitInput;

if ($limit < 1 || $limit > 100) {
    sendResponse(
        false,
        'Limit must be between 1 and 100.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| OFFSET
|--------------------------------------------------------------------------
*/

$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| SEARCH VALIDATION
|--------------------------------------------------------------------------
*/

if ($q !== '' && mb_strlen($q) > 150) {
    sendResponse(
        false,
        'Search query must not exceed 150 characters.',
        null,
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
    'inactive',
    'blocked'
];

if (
    $status !== '' &&
    !in_array($status, $allowedStatuses, true)
) {
    sendResponse(
        false,
        'Invalid status. Allowed values are active, inactive, blocked.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| DATE VALIDATION FUNCTION
|--------------------------------------------------------------------------
*/

function validateDateFilter(
    string $value,
    string $fieldName
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
            $fieldName . ' must be in YYYY-MM-DD format.',
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| VALIDATE ALL DATE FILTERS
|--------------------------------------------------------------------------
*/

validateDateFilter(
    $dobFrom,
    'dob_from'
);

validateDateFilter(
    $dobTo,
    'dob_to'
);

validateDateFilter(
    $createdFrom,
    'created_from'
);

validateDateFilter(
    $createdTo,
    'created_to'
);

validateDateFilter(
    $lastLoginFrom,
    'last_login_from'
);

validateDateFilter(
    $lastLoginTo,
    'last_login_to'
);

/*
|--------------------------------------------------------------------------
| DATE RANGE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $dobFrom !== '' &&
    $dobTo !== '' &&
    $dobFrom > $dobTo
) {
    sendResponse(
        false,
        'dob_from cannot be greater than dob_to.',
        null,
        422
    );
}

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

if (
    $lastLoginFrom !== '' &&
    $lastLoginTo !== '' &&
    $lastLoginFrom > $lastLoginTo
) {
    sendResponse(
        false,
        'last_login_from cannot be greater than last_login_to.',
        null,
        422
    );
}

$allowedSortColumns = [
    'id',
    'name',
    'mobile',
    'email',
    'date_of_birth',
    'status',
    'last_login',
    'created_at',
    'updated_at'
];

$sortBy = isset($_GET['sort_by'])
    ? trim((string) $_GET['sort_by'])
    : 'created_at';

$sortOrder = isset($_GET['sort_order'])
    ? strtolower(trim((string) $_GET['sort_order']))
    : 'desc';

/*
|--------------------------------------------------------------------------
| SORT COLUMN VALIDATION
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
            'allowed_values' => $allowedSortColumns
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
        'sort_order must be either asc or desc.',
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
    $where = [];

    $params = [];


    if ($q !== '') {

        $where[] = "
            (
                CAST(id AS CHAR) LIKE :q_id
                OR name LIKE :q_name
                OR mobile LIKE :q_mobile
                OR email LIKE :q_email
            )
        ";

        $searchValue = '%' . $q . '%';

        $params[':q_id'] = $searchValue;
        $params[':q_name'] = $searchValue;
        $params[':q_mobile'] = $searchValue;
        $params[':q_email'] = $searchValue;
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if ($status !== '') {

        $where[] = 'status = :status';

        $params[':status'] = $status;
    }

    /*
    |--------------------------------------------------------------------------
    | DOB FROM
    |--------------------------------------------------------------------------
    */

    if ($dobFrom !== '') {

        $where[] =
            'date_of_birth >= :dob_from';

        $params[':dob_from'] = $dobFrom;
    }

    /*
    |--------------------------------------------------------------------------
    | DOB TO
    |--------------------------------------------------------------------------
    */

    if ($dobTo !== '') {

        $where[] =
            'date_of_birth <= :dob_to';

        $params[':dob_to'] = $dobTo;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATED FROM
    |--------------------------------------------------------------------------
    */

    if ($createdFrom !== '') {

        $where[] =
            'created_at >= :created_from';

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
            'created_at <= :created_to';

        $params[':created_to'] =
            $createdTo . ' 23:59:59';
    }

    /*
    |--------------------------------------------------------------------------
    | LAST LOGIN FROM
    |--------------------------------------------------------------------------
    */

    if ($lastLoginFrom !== '') {

        $where[] =
            'last_login >= :last_login_from';

        $params[':last_login_from'] =
            $lastLoginFrom . ' 00:00:00';
    }

    /*
    |--------------------------------------------------------------------------
    | LAST LOGIN TO
    |--------------------------------------------------------------------------
    */

    if ($lastLoginTo !== '') {

        $where[] =
            'last_login <= :last_login_to';

        $params[':last_login_to'] =
            $lastLoginTo . ' 23:59:59';
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD WHERE CLAUSE
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
    | TOTAL RECORD COUNT
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*)
        FROM users
        $whereSql
    ";

    $countStmt = $pdo->prepare(
        $countSql
    );

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
        (int) $countStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | TOTAL PAGES
    |--------------------------------------------------------------------------
    */

    $totalPages = $totalRecords > 0
        ? (int) ceil(
            $totalRecords / $limit
        )
        : 0;


    $sql = "
        SELECT
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
        $whereSql
        ORDER BY `$sortBy` $sortOrder
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare(
        $sql
    );

    /*
    |--------------------------------------------------------------------------
    | BIND WHERE PARAMETERS
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
    | BIND PAGINATION
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

    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt->execute();

    $users =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | FORMAT USER DATA
    |--------------------------------------------------------------------------
    */

    $formattedUsers = [];

    foreach ($users as $user) {

        $formattedUsers[] = [
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
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Users retrieved successfully.',
        [
            'users' => $formattedUsers,

            'pagination' => [
                'page' => $page,

                'limit' => $limit,

                'total_records' =>
                    $totalRecords,

                'total_pages' =>
                    $totalPages,

                'has_previous' =>
                    $page > 1,

                'has_next' =>
                    $page < $totalPages
            ],

            'filters' => [
                'q' =>
                    $q !== ''
                        ? $q
                        : null,

                'status' =>
                    $status !== ''
                        ? $status
                        : null,

                'dob_from' =>
                    $dobFrom !== ''
                        ? $dobFrom
                        : null,

                'dob_to' =>
                    $dobTo !== ''
                        ? $dobTo
                        : null,

                'created_from' =>
                    $createdFrom !== ''
                        ? $createdFrom
                        : null,

                'created_to' =>
                    $createdTo !== ''
                        ? $createdTo
                        : null,

                'last_login_from' =>
                    $lastLoginFrom !== ''
                        ? $lastLoginFrom
                        : null,

                'last_login_to' =>
                    $lastLoginTo !== ''
                        ? $lastLoginTo
                        : null
            ],

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

    /*
    |--------------------------------------------------------------------------
    | DATABASE ERROR
    |--------------------------------------------------------------------------
    */

    sendResponse(
        false,
        'Unable to retrieve users.',
        APP_ENV === 'development'
            ? [
                'error' =>
                    $e->getMessage()
            ]
            : null,
        500
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | GENERAL ERROR
    |--------------------------------------------------------------------------
    */

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