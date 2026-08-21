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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method is allowed.', null, 405);
}

$decoded = authenticate();
validateJWTData($decoded);

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if (!in_array($accountType, ['admin', 'user'], true)) {
    sendResponse(false, 'Access denied.', null, 403);
}

if ($accountType === 'admin') {
    $adminAuth = authenticateAdmin();
    checkAdminRole($adminAuth, ['admin']);
} else {
    $userAuth = authenticateUser();
    $authenticatedId = getAuthenticatedId($userAuth);

    if ($authenticatedId <= 0) {
        sendResponse(false, 'Invalid authenticated user.', null, 401);
    }
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$dobFrom = isset($_GET['dob_from']) ? trim((string)$_GET['dob_from']) : '';
$dobTo = isset($_GET['dob_to']) ? trim((string)$_GET['dob_to']) : '';
$createdFrom = isset($_GET['created_from']) ? trim((string)$_GET['created_from']) : '';
$createdTo = isset($_GET['created_to']) ? trim((string)$_GET['created_to']) : '';
$lastLoginFrom = isset($_GET['last_login_from']) ? trim((string)$_GET['last_login_from']) : '';
$lastLoginTo = isset($_GET['last_login_to']) ? trim((string)$_GET['last_login_to']) : '';

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (filter_var($pageInput, FILTER_VALIDATE_INT) === false || (int)$pageInput < 1) {
    sendResponse(false, 'Page must be a valid positive integer.', null, 422);
}

if (
    filter_var($limitInput, FILTER_VALIDATE_INT) === false ||
    (int)$limitInput < 1 ||
    (int)$limitInput > 100
) {
    sendResponse(false, 'Limit must be between 1 and 100.', null, 422);
}

$page = (int)$pageInput;
$limit = (int)$limitInput;
$offset = ($page - 1) * $limit;

if ($q !== '' && mb_strlen($q) > 150) {
    sendResponse(false, 'Search query must not exceed 150 characters.', null, 422);
}

$allowedStatuses = ['active', 'inactive', 'blocked'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_statuses' => $allowedStatuses
    ], 422);
}

function validateUserListDate(string $value, string $field): void
{
    if ($value === '') {
        return;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    $hasErrors = $errors !== false &&
        ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

validateUserListDate($dobFrom, 'dob_from');
validateUserListDate($dobTo, 'dob_to');
validateUserListDate($createdFrom, 'created_from');
validateUserListDate($createdTo, 'created_to');
validateUserListDate($lastLoginFrom, 'last_login_from');
validateUserListDate($lastLoginTo, 'last_login_to');

if ($dobFrom !== '' && $dobTo !== '' && $dobFrom > $dobTo) {
    sendResponse(false, 'dob_from cannot be greater than dob_to.', null, 422);
}

if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

if ($lastLoginFrom !== '' && $lastLoginTo !== '' && $lastLoginFrom > $lastLoginTo) {
    sendResponse(false, 'last_login_from cannot be greater than last_login_to.', null, 422);
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
    ? trim((string)$_GET['sort_by'])
    : 'created_at';

$sortOrder = isset($_GET['sort_order'])
    ? strtolower(trim((string)$_GET['sort_order']))
    : 'desc';

if (!in_array($sortBy, $allowedSortColumns, true)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_values' => $allowedSortColumns
    ], 422);
}

if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

try {
    $where = [];
    $params = [];

    if ($accountType === 'user') {
        $where[] = 'id = :authenticated_user_id';
        $params[':authenticated_user_id'] = $authenticatedId;
    }

    if ($q !== '') {
        $where[] = "(
            CAST(id AS CHAR) LIKE :q_id
            OR name LIKE :q_name
            OR mobile LIKE :q_mobile
            OR email LIKE :q_email
            OR status LIKE :q_status
            OR DATE_FORMAT(date_of_birth, '%Y-%m-%d') LIKE :q_dob
            OR DATE_FORMAT(last_login, '%Y-%m-%d %H:%i:%s') LIKE :q_last_login
            OR DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') LIKE :q_created_at
            OR DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') LIKE :q_updated_at
        )";

        $searchValue = '%' . $q . '%';

        $params[':q_id'] = $searchValue;
        $params[':q_name'] = $searchValue;
        $params[':q_mobile'] = $searchValue;
        $params[':q_email'] = $searchValue;
        $params[':q_status'] = $searchValue;
        $params[':q_dob'] = $searchValue;
        $params[':q_last_login'] = $searchValue;
        $params[':q_created_at'] = $searchValue;
        $params[':q_updated_at'] = $searchValue;
    }

    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    if ($dobFrom !== '') {
        $where[] = 'date_of_birth >= :dob_from';
        $params[':dob_from'] = $dobFrom;
    }

    if ($dobTo !== '') {
        $where[] = 'date_of_birth <= :dob_to';
        $params[':dob_to'] = $dobTo;
    }

    if ($createdFrom !== '') {
        $where[] = 'created_at >= :created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'created_at <= :created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    if ($lastLoginFrom !== '') {
        $where[] = 'last_login >= :last_login_from';
        $params[':last_login_from'] = $lastLoginFrom . ' 00:00:00';
    }

    if ($lastLoginTo !== '') {
        $where[] = 'last_login <= :last_login_to';
        $params[':last_login_to'] = $lastLoginTo . ' 23:59:59';
    }

    $whereSql = !empty($where)
        ? ' WHERE ' . implode(' AND ', $where)
        : '';

    $countSql = "
        SELECT COUNT(*)
        FROM users
        $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    if ($totalPages > 0 && $page > $totalPages) {
        sendResponse(false, 'Requested page exceeds total available pages.', [
            'requested_page' => $page,
            'total_pages' => $totalPages
        ], 422);
    }

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
        ORDER BY `$sortBy` $sortOrder, id DESC
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedUsers = [];

    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'mobile' => $user['mobile'],
            'email' => $user['email'],
            'date_of_birth' => $user['date_of_birth'],
            'status' => $user['status'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];
    }

    sendResponse(true, 'Users retrieved successfully.', [
        'viewer_type' => $accountType,
        'users' => $formattedUsers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ],
        'filters' => [
            'q' => $q !== '' ? $q : null,
            'status' => $status !== '' ? $status : null,
            'dob_from' => $dobFrom !== '' ? $dobFrom : null,
            'dob_to' => $dobTo !== '' ? $dobTo : null,
            'created_from' => $createdFrom !== '' ? $createdFrom : null,
            'created_to' => $createdTo !== '' ? $createdTo : null,
            'last_login_from' => $lastLoginFrom !== '' ? $lastLoginFrom : null,
            'last_login_to' => $lastLoginTo !== '' ? $lastLoginTo : null
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve users.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
} catch (Throwable $e) {
    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}