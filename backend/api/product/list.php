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
    'hsn_profile_id',
    'hsn_code',
    'status',
    'is_new_arrival',
    'is_featured',
    'is_best_seller',
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
$hsnProfileIdInput = $_GET['hsn_profile_id'] ?? null;
$hsnCode = isset($_GET['hsn_code']) ? trim((string)$_GET['hsn_code']) : '';
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$isNewArrivalInput = $_GET['is_new_arrival'] ?? null;
$isFeaturedInput = $_GET['is_featured'] ?? null;
$isBestSellerInput = $_GET['is_best_seller'] ?? null;
$pageInput = $_GET['page'] ?? 1;
$limitInput = $_GET['limit'] ?? 20;
$sortBy = isset($_GET['sort_by'])
    ? strtolower(trim((string)$_GET['sort_by']))
    : 'created_at';
$sortOrder = isset($_GET['sort_order'])
    ? strtolower(trim((string)$_GET['sort_order']))
    : 'desc';

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
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

$hsnProfileId = null;

if ($hsnProfileIdInput !== null && $hsnProfileIdInput !== '') {
    if (
        filter_var($hsnProfileIdInput, FILTER_VALIDATE_INT) === false ||
        (int)$hsnProfileIdInput <= 0
    ) {
        sendResponse(false, 'hsn_profile_id must be a valid positive integer.', null, 422);
    }

    $hsnProfileId = (int)$hsnProfileIdInput;
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

function parseFilterBoolean(mixed $value, string $field): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    $value = strtolower(trim((string)$value));

    if (in_array($value, ['1', 'true'], true)) {
        return 1;
    }

    if (in_array($value, ['0', 'false'], true)) {
        return 0;
    }

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return null;
}

$isNewArrival = parseFilterBoolean($isNewArrivalInput, 'is_new_arrival');
$isFeatured = parseFilterBoolean($isFeaturedInput, 'is_featured');
$isBestSeller = parseFilterBoolean($isBestSellerInput, 'is_best_seller');

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
    'id' => 'p.id',
    'name' => 'p.name',
    'slug' => 'p.slug',
    'category_name' => 'c.name',
    'hsn_profile_name' => 'hp.name',
    'hsn_code' => 'hp.hsn_code',
    'status' => 'p.status',
    'created_at' => 'p.created_at',
    'updated_at' => 'p.updated_at'
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
            "SELECT id, name
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

    if ($hsnProfileId !== null) {
        $hsnStmt = $pdo->prepare(
            "SELECT id, category_id, name, hsn_code
             FROM hsn_profiles
             WHERE id = :id
             LIMIT 1"
        );

        $hsnStmt->bindValue(':id', $hsnProfileId, PDO::PARAM_INT);
        $hsnStmt->execute();

        $hsnProfile = $hsnStmt->fetch(PDO::FETCH_ASSOC);

        if (!$hsnProfile) {
            sendResponse(false, 'HSN profile not found.', null, 404);
        }

        if (
            $categoryId !== null &&
            (int)$hsnProfile['category_id'] !== $categoryId
        ) {
            sendResponse(false, 'HSN profile does not belong to the selected category.', [
                'category_id' => $categoryId,
                'hsn_profile_id' => $hsnProfileId
            ], 422);
        }
    }

    $conditions = [];
    $params = [];

    if ($q !== '') {
        $conditions[] = "LOWER(
            CONCAT_WS(
                ' ',
                p.name,
                p.slug,
                p.description,
                c.name,
                hp.name,
                hp.hsn_code
            )
        ) LIKE LOWER(:q)";

        $params[':q'] = '%' . $q . '%';
    }

    if ($categoryId !== null) {
        $conditions[] = 'p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($hsnProfileId !== null) {
        $conditions[] = 'p.hsn_profile_id = :hsn_profile_id';
        $params[':hsn_profile_id'] = $hsnProfileId;
    }

    if ($hsnCode !== '') {
        $conditions[] = 'hp.hsn_code = :hsn_code';
        $params[':hsn_code'] = $hsnCode;
    }

    if ($status !== '') {
        $conditions[] = 'p.status = :status';
        $params[':status'] = $status;
    }

    if ($isNewArrival !== null) {
        $conditions[] = 'p.is_new_arrival = :is_new_arrival';
        $params[':is_new_arrival'] = $isNewArrival;
    }

    if ($isFeatured !== null) {
        $conditions[] = 'p.is_featured = :is_featured';
        $params[':is_featured'] = $isFeatured;
    }

    if ($isBestSeller !== null) {
        $conditions[] = 'p.is_best_seller = :is_best_seller';
        $params[':is_best_seller'] = $isBestSeller;
    }

    $where = $conditions
        ? ' WHERE ' . implode(' AND ', $conditions)
        : '';

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM products p
         INNER JOIN categories c
            ON c.id = p.category_id
         LEFT JOIN hsn_profiles hp
            ON hp.id = p.hsn_profile_id
         {$where}"
    );

    foreach ($params as $key => $value) {
        if (in_array($key, [
            ':category_id',
            ':hsn_profile_id',
            ':is_new_arrival',
            ':is_featured',
            ':is_best_seller'
        ], true)) {
            $countStmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
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
            p.id,
            p.category_id,
            c.name AS category_name,
            p.hsn_profile_id,
            hp.name AS hsn_profile_name,
            hp.hsn_code,
            hp.description AS hsn_description,
            hp.status AS hsn_profile_status,
            p.name,
            p.slug,
            p.description,
            p.is_new_arrival,
            p.is_featured,
            p.is_best_seller,
            p.status,
            p.created_at,
            p.updated_at
         FROM products p
         INNER JOIN categories c
            ON c.id = p.category_id
         LEFT JOIN hsn_profiles hp
            ON hp.id = p.hsn_profile_id
         {$where}
         ORDER BY {$sortColumn} {$order}
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        if (in_array($key, [
            ':category_id',
            ':hsn_profile_id',
            ':is_new_arrival',
            ':is_featured',
            ':is_best_seller'
        ], true)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'category_id' => (int)$row['category_id'],
            'category_name' => $row['category_name'],
            'hsn_profile_id' => $row['hsn_profile_id'] !== null
                ? (int)$row['hsn_profile_id']
                : null,
            'hsn_profile_name' => $row['hsn_profile_name'],
            'hsn_code' => $row['hsn_code'],
            'hsn_description' => $row['hsn_description'],
            'hsn_profile_status' => $row['hsn_profile_status'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'is_new_arrival' => (int)$row['is_new_arrival'],
            'is_featured' => (int)$row['is_featured'],
            'is_best_seller' => (int)$row['is_best_seller'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }, $rows);

    sendResponse(true, 'Products retrieved successfully.', [
        'products' => $products,
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
            'hsn_profile_id' => $hsnProfileId,
            'hsn_code' => $hsnCode !== '' ? $hsnCode : null,
            'status' => $status !== '' ? $status : null,
            'is_new_arrival' => $isNewArrival,
            'is_featured' => $isFeatured,
            'is_best_seller' => $isBestSeller,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve products.',
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