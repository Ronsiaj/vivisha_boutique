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

if ($accountType !== 'admin') {
    sendResponse(false, 'Admin access only.', null, 403);
}

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (filter_var($pageInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Page must be a valid integer.', null, 422);
}

$page = (int)$pageInput;

if ($page < 1) {
    sendResponse(false, 'Page must be greater than 0.', null, 422);
}

if (filter_var($limitInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Limit must be a valid integer.', null, 422);
}

$limit = (int)$limitInput;

if ($limit < 1 || $limit > 100) {
    sendResponse(false, 'Limit must be between 1 and 100.', null, 422);
}

if ($q !== '' && mb_strlen($q) > 100) {
    sendResponse(false, 'Search query must not exceed 100 characters.', null, 422);
}

$allowedStatuses = ['active', 'inactive'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_statuses' => $allowedStatuses
    ], 422);
}

$allowedSortColumns = [
    'id',
    'name',
    'hex_code',
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

if (!in_array($sortBy, $allowedSortColumns, true)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_values' => $allowedSortColumns
    ], 422);
}

if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$offset = ($page - 1) * $limit;

try {
    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(
            CAST(id AS CHAR) LIKE :q_id
            OR name LIKE :q_name
            OR hex_code LIKE :q_hex_code
            OR status LIKE :q_status
        )";

        $searchValue = '%' . $q . '%';

        $params[':q_id'] = $searchValue;
        $params[':q_name'] = $searchValue;
        $params[':q_hex_code'] = $searchValue;
        $params[':q_status'] = $searchValue;
    }

    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    $whereSql = !empty($where)
        ? ' WHERE ' . implode(' AND ', $where)
        : '';

    $countSql = "SELECT COUNT(*) FROM colors $whereSql";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    $sql = "
        SELECT
            id,
            name,
            hex_code,
            status,
            created_at,
            updated_at
        FROM colors
        $whereSql
        ORDER BY `$sortBy` $sortOrder, id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedColors = [];

    foreach ($colors as $color) {
        $formattedColors[] = [
            'id' => (int)$color['id'],
            'name' => $color['name'],
            'hex_code' => $color['hex_code'],
            'status' => $color['status'],
            'created_at' => $color['created_at'],
            'updated_at' => $color['updated_at']
        ];
    }

    sendResponse(true, 'Colors retrieved successfully.', [
        'colors' => $formattedColors,
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
            'status' => $status !== '' ? $status : null
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve colors.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
} catch (Throwable $e) {
    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}