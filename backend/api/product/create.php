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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') === false) {
    sendResponse(false, 'Content-Type must be application/json.', null, 415);
}

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    sendResponse(false, 'Request body is required.', null, 400);
}

$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, 'Invalid JSON request body.', null, 400);
}

$allowedFields = [
    'category_id',
    'name',
    'slug',
    'description',
    'is_new_arrival',
    'is_featured',
    'is_best_seller',
    'status'
];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$categoryIdInput = $data['category_id'] ?? null;
$name = isset($data['name']) ? trim((string)$data['name']) : '';
$slugInput = isset($data['slug']) ? trim((string)$data['slug']) : '';
$description = isset($data['description']) ? trim((string)$data['description']) : '';
$isNewArrivalInput = $data['is_new_arrival'] ?? 0;
$isFeaturedInput = $data['is_featured'] ?? 0;
$isBestSellerInput = $data['is_best_seller'] ?? 0;
$status = isset($data['status'])
    ? strtolower(trim((string)$data['status']))
    : 'active';

if ($categoryIdInput === null || $categoryIdInput === '') {
    sendResponse(false, 'category_id is required.', null, 422);
}

if (
    filter_var($categoryIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$categoryIdInput <= 0
) {
    sendResponse(false, 'category_id must be a valid positive integer.', null, 422);
}

$categoryId = (int)$categoryIdInput;

if ($name === '') {
    sendResponse(false, 'Product name is required.', null, 422);
}

if (mb_strlen($name) < 2) {
    sendResponse(false, 'Product name must contain at least 2 characters.', null, 422);
}

if (mb_strlen($name) > 200) {
    sendResponse(false, 'Product name must not exceed 200 characters.', null, 422);
}

if (!preg_match('/^[\p{L}\p{N}\s\-\&\'\/().,+]+$/u', $name)) {
    sendResponse(false, 'Product name contains invalid characters.', null, 422);
}

if ($description !== '' && mb_strlen($description) > 10000) {
    sendResponse(false, 'Description must not exceed 10000 characters.', null, 422);
}

function createProductSlug(string $value): string
{
    $value = trim($value);

    if (function_exists('transliterator_transliterate')) {
        $converted = transliterator_transliterate(
            'Any-Latin; Latin-ASCII;',
            $value
        );

        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);

    return trim((string)$value, '-');
}

$slug = $slugInput !== ''
    ? createProductSlug($slugInput)
    : createProductSlug($name);

if ($slug === '') {
    sendResponse(false, 'Unable to generate a valid product slug.', null, 422);
}

if (mb_strlen($slug) > 220) {
    sendResponse(false, 'Slug must not exceed 220 characters.', null, 422);
}

function parseProductBoolean(mixed $value, string $field): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_int($value) && in_array($value, [0, 1], true)) {
        return $value;
    }

    if (is_string($value)) {
        $value = strtolower(trim($value));

        if (in_array($value, ['0', 'false'], true)) {
            return 0;
        }

        if (in_array($value, ['1', 'true'], true)) {
            return 1;
        }
    }

    sendResponse(
        false,
        "{$field} must be 0, 1, true or false.",
        null,
        422
    );

    return 0;
}

$isNewArrival = parseProductBoolean(
    $isNewArrivalInput,
    'is_new_arrival'
);

$isFeatured = parseProductBoolean(
    $isFeaturedInput,
    'is_featured'
);

$isBestSeller = parseProductBoolean(
    $isBestSellerInput,
    'is_best_seller'
);

$allowedStatuses = ['active', 'inactive'];

if (!in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_statuses' => $allowedStatuses
    ], 422);
}

try {
    $categoryStmt = $pdo->prepare(
        "SELECT id, name, status
         FROM categories
         WHERE id = :id
         LIMIT 1"
    );

    $categoryStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
    $categoryStmt->execute();

    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        sendResponse(false, 'Category not found.', null, 404);
    }

    if ($category['status'] !== 'active') {
        sendResponse(false, 'Selected category is inactive.', null, 422);
    }

    $duplicateNameStmt = $pdo->prepare(
        "SELECT id
         FROM products
         WHERE LOWER(name) = LOWER(:name)
         LIMIT 1"
    );

    $duplicateNameStmt->bindValue(':name', $name, PDO::PARAM_STR);
    $duplicateNameStmt->execute();

    if ($duplicateNameStmt->fetch()) {
        sendResponse(false, 'Product name already exists.', null, 409);
    }

    $duplicateSlugStmt = $pdo->prepare(
        "SELECT id
         FROM products
         WHERE slug = :slug
         LIMIT 1"
    );

    $duplicateSlugStmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    $duplicateSlugStmt->execute();

    if ($duplicateSlugStmt->fetch()) {
        sendResponse(false, 'Product slug already exists.', null, 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO products (
            category_id,
            name,
            slug,
            description,
            is_new_arrival,
            is_featured,
            is_best_seller,
            status
        ) VALUES (
            :category_id,
            :name,
            :slug,
            :description,
            :is_new_arrival,
            :is_featured,
            :is_best_seller,
            :status
        )"
    );

    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);

    if ($description === '') {
        $stmt->bindValue(':description', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    }

    $stmt->bindValue(':is_new_arrival', $isNewArrival, PDO::PARAM_INT);
    $stmt->bindValue(':is_featured', $isFeatured, PDO::PARAM_INT);
    $stmt->bindValue(':is_best_seller', $isBestSeller, PDO::PARAM_INT);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);

    $stmt->execute();

    $productId = (int)$pdo->lastInsertId();

    $fetchStmt = $pdo->prepare(
        "SELECT
            p.id,
            p.category_id,
            c.name AS category_name,
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
         WHERE p.id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $fetchStmt->execute();

    $product = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(
            false,
            'Product created but unable to retrieve product.',
            null,
            500
        );
    }

    sendResponse(true, 'Product created successfully.', [
        'product' => [
            'id' => (int)$product['id'],
            'category_id' => (int)$product['category_id'],
            'category_name' => $product['category_name'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'description' => $product['description'],
            'is_new_arrival' => (int)$product['is_new_arrival'],
            'is_featured' => (int)$product['is_featured'],
            'is_best_seller' => (int)$product['is_best_seller'],
            'status' => $product['status'],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ]
    ], 201);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to create product.',
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