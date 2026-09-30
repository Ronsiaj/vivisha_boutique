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

$allowedParams = [
    'q',
    'category_id',
    'hsn_code',
    'status',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$categoryIdInput = $_GET['category_id'] ?? null;
$hsnCode = isset($_GET['hsn_code']) ? trim((string)$_GET['hsn_code']) : '';
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$pageInput = $_GET['page'] ?? 1;
$limitInput = $_GET['limit'] ?? 20;
$sortBy = isset($_GET['sort_by']) ? strtolower(trim((string)$_GET['sort_by'])) : 'created_at';
$sortOrder = isset($_GET['sort_order']) ? strtolower(trim((string)$_GET['sort_order'])) : 'desc';

if ($q !== '' && mb_strlen($q) > 150) {
    sendResponse(false, 'Search query must not exceed 150 characters.', null, 422);
}

$categoryId = null;

if ($categoryIdInput !== null && $categoryIdInput !== '') {
    if (
        filter_var($categoryIdInput, FILTER_VALIDATE_INT) === false ||
        (int)$categoryIdInput <= 0
    ) {
        sendResponse(false, 'category_id must be a valid positive integer.', null, 422);
    }

    $categoryId = (int)$categoryIdInput;
}

if ($hsnCode !== '' && !preg_match('/^\d{1,10}$/', $hsnCode)) {
    sendResponse(false, 'hsn_code must contain digits only and must not exceed 10 digits.', null, 422);
}

$allowedStatuses = ['active', 'inactive'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_statuses' => $allowedStatuses
    ], 422);
}

if (
    filter_var($pageInput, FILTER_VALIDATE_INT) === false ||
    (int)$pageInput <= 0
) {
    sendResponse(false, 'page must be a valid positive integer.', null, 422);
}

if (
    filter_var($limitInput, FILTER_VALIDATE_INT) === false ||
    (int)$limitInput <= 0 ||
    (int)$limitInput > 100
) {
    sendResponse(false, 'limit must be between 1 and 100.', null, 422);
}

$page = (int)$pageInput;
$limit = (int)$limitInput;
$offset = ($page - 1) * $limit;

$sortColumns = [
    'id' => 'hp.id',
    'name' => 'hp.name',
    'hsn_code' => 'hp.hsn_code',
    'category_name' => 'c.name',
    'status' => 'hp.status',
    'created_at' => 'hp.created_at',
    'updated_at' => 'hp.updated_at'
];

if (!array_key_exists($sortBy, $sortColumns)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_sort_by' => array_keys($sortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

try {
    if ($categoryId !== null) {
        $categoryStmt = $pdo->prepare(
            "SELECT id, name, status
             FROM categories
             WHERE id = :id
             LIMIT 1"
        );

        $categoryStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
        $categoryStmt->execute();

        if (!$categoryStmt->fetch(PDO::FETCH_ASSOC)) {
            sendResponse(false, 'Category not found.', null, 404);
        }
    }

    $conditions = [];
    $params = [];

    if ($q !== '') {
        $conditions[] = "(
            hp.name LIKE :q
            OR hp.hsn_code LIKE :q
            OR hp.description LIKE :q
            OR c.name LIKE :q
        )";
        $params[':q'] = '%' . $q . '%';
    }

    if ($categoryId !== null) {
        $conditions[] = 'hp.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($hsnCode !== '') {
        $conditions[] = 'hp.hsn_code = :hsn_code';
        $params[':hsn_code'] = $hsnCode;
    }

    if ($status !== '') {
        $conditions[] = 'hp.status = :status';
        $params[':status'] = $status;
    }

    $where = $conditions
        ? ' WHERE ' . implode(' AND ', $conditions)
        : '';

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM hsn_profiles hp
         INNER JOIN categories c ON c.id = hp.category_id
         {$where}"
    );

    foreach ($params as $key => $value) {
        $type = $key === ':category_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $countStmt->bindValue($key, $value, $type);
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    $sortColumn = $sortColumns[$sortBy];
    $order = strtoupper($sortOrder);

    $stmt = $pdo->prepare(
        "SELECT
            hp.id,
            hp.category_id,
            c.name AS category_name,
            hp.name,
            hp.hsn_code,
            hp.description,
            hp.status,
            hp.created_at,
            hp.updated_at
         FROM hsn_profiles hp
         INNER JOIN categories c ON c.id = hp.category_id
         {$where}
         ORDER BY {$sortColumn} {$order}
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        $type = $key === ':category_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $profiles = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'category_id' => (int)$row['category_id'],
            'category_name' => $row['category_name'],
            'name' => $row['name'],
            'hsn_code' => $row['hsn_code'],
            'description' => $row['description'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }, $rows);

    sendResponse(true, 'HSN profiles retrieved successfully.', [
        'hsn_profiles' => $profiles,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous_page' => $page > 1,
            'has_next_page' => $totalPages > 0 && $page < $totalPages
        ],
        'filters' => [
            'q' => $q !== '' ? $q : null,
            'category_id' => $categoryId,
            'hsn_code' => $hsnCode !== '' ? $hsnCode : null,
            'status' => $status !== '' ? $status : null,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve HSN profiles.',
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