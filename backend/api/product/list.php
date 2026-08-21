<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method is allowed.', null, 405);
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$categoryIdInput = isset($_GET['category_id']) ? trim((string)$_GET['category_id']) : '';
$isNewArrivalInput = isset($_GET['is_new_arrival']) ? trim((string)$_GET['is_new_arrival']) : '';
$isFeaturedInput = isset($_GET['is_featured']) ? trim((string)$_GET['is_featured']) : '';
$isBestSellerInput = isset($_GET['is_best_seller']) ? trim((string)$_GET['is_best_seller']) : '';
$createdFrom = isset($_GET['created_from']) ? trim((string)$_GET['created_from']) : '';
$createdTo = isset($_GET['created_to']) ? trim((string)$_GET['created_to']) : '';
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

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
}

$categoryId = null;

if ($categoryIdInput !== '') {
    if (!preg_match('/^[1-9][0-9]*$/', $categoryIdInput)) {
        sendResponse(false, 'category_id must be a valid positive integer.', null, 422);
    }

    $categoryId = (int)$categoryIdInput;
}

function validateBooleanFilter(string $value, string $field): ?int
{
    if ($value === '') {
        return null;
    }

    $value = strtolower($value);

    if (in_array($value, ['1', 'true'], true)) {
        return 1;
    }

    if (in_array($value, ['0', 'false'], true)) {
        return 0;
    }

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
}

function validateProductListDate(string $value, string $field): void
{
    if ($value === '') {
        return;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    $hasErrors = $errors !== false && (
        $errors['warning_count'] > 0 ||
        $errors['error_count'] > 0
    );

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

$isNewArrival = validateBooleanFilter($isNewArrivalInput, 'is_new_arrival');
$isFeatured = validateBooleanFilter($isFeaturedInput, 'is_featured');
$isBestSeller = validateBooleanFilter($isBestSellerInput, 'is_best_seller');

validateProductListDate($createdFrom, 'created_from');
validateProductListDate($createdTo, 'created_to');

if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$allowedSortColumns = [
    'id',
    'category_id',
    'name',
    'slug',
    'is_new_arrival',
    'is_featured',
    'is_best_seller',
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
    $where = [
        "p.status = 'active'",
        "c.status = 'active'"
    ];

    $params = [];

    if ($q !== '') {
        $where[] = "(
            CAST(p.id AS CHAR) LIKE :q_product_id
            OR CAST(p.category_id AS CHAR) LIKE :q_category_id
            OR p.name LIKE :q_name
            OR p.slug LIKE :q_slug
            OR p.description LIKE :q_description
            OR c.name LIKE :q_category_name
            OR CAST(p.is_new_arrival AS CHAR) LIKE :q_new_arrival
            OR CAST(p.is_featured AS CHAR) LIKE :q_featured
            OR CAST(p.is_best_seller AS CHAR) LIKE :q_best_seller
            OR DATE_FORMAT(p.created_at, '%Y-%m-%d %H:%i:%s') LIKE :q_created_at
            OR DATE_FORMAT(p.updated_at, '%Y-%m-%d %H:%i:%s') LIKE :q_updated_at
        )";

        $searchValue = '%' . $q . '%';

        $params[':q_product_id'] = $searchValue;
        $params[':q_category_id'] = $searchValue;
        $params[':q_name'] = $searchValue;
        $params[':q_slug'] = $searchValue;
        $params[':q_description'] = $searchValue;
        $params[':q_category_name'] = $searchValue;
        $params[':q_new_arrival'] = $searchValue;
        $params[':q_featured'] = $searchValue;
        $params[':q_best_seller'] = $searchValue;
        $params[':q_created_at'] = $searchValue;
        $params[':q_updated_at'] = $searchValue;
    }

    if ($categoryId !== null) {
        $categoryStmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE id = :category_id
            AND status = 'active'
            LIMIT 1
        ");

        $categoryStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $categoryStmt->execute();

        if (!$categoryStmt->fetch()) {
            sendResponse(false, 'Active category not found.', null, 404);
        }

        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($isNewArrival !== null) {
        $where[] = 'p.is_new_arrival = :is_new_arrival';
        $params[':is_new_arrival'] = $isNewArrival;
    }

    if ($isFeatured !== null) {
        $where[] = 'p.is_featured = :is_featured';
        $params[':is_featured'] = $isFeatured;
    }

    if ($isBestSeller !== null) {
        $where[] = 'p.is_best_seller = :is_best_seller';
        $params[':is_best_seller'] = $isBestSeller;
    }

    if ($createdFrom !== '') {
        $where[] = 'p.created_at >= :created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'p.created_at <= :created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
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
            p.id,
            p.category_id,
            c.name AS category_name,
            c.slug AS category_slug,
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
        INNER JOIN categories c ON c.id = p.category_id
        $whereSql
        ORDER BY p.`$sortBy` $sortOrder, p.id DESC
        LIMIT :limit OFFSET :offset
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

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $formattedProducts = [];

    foreach ($products as $product) {
        $formattedProducts[] = [
            'id' => (int)$product['id'],
            'category_id' => (int)$product['category_id'],
            'category' => [
                'id' => (int)$product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug']
            ],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'description' => $product['description'],
            'is_new_arrival' => (int)$product['is_new_arrival'],
            'is_featured' => (int)$product['is_featured'],
            'is_best_seller' => (int)$product['is_best_seller'],
            'status' => $product['status'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }

    sendResponse(true, 'Products retrieved successfully.', [
        'products' => $formattedProducts,
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
            'category_id' => $categoryId,
            'status' => 'active',
            'is_new_arrival' => $isNewArrival,
            'is_featured' => $isFeatured,
            'is_best_seller' => $isBestSeller,
            'created_from' => $createdFrom !== '' ? $createdFrom : null,
            'created_to' => $createdTo !== '' ? $createdTo : null
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve products.',
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