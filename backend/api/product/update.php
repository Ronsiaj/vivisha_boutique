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
header('Access-Control-Allow-Methods: PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
    sendResponse(false, 'Only PUT or PATCH method is allowed.', null, 405);
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
    'id',
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

$idInput = $data['id'] ?? null;

if ($idInput === null || $idInput === '') {
    sendResponse(false, 'Product ID is required.', null, 422);
}

if (
    filter_var($idInput, FILTER_VALIDATE_INT) === false ||
    (int)$idInput <= 0
) {
    sendResponse(false, 'Product ID must be a valid positive integer.', null, 422);
}

$productId = (int)$idInput;

$hasCategoryId = array_key_exists('category_id', $data);
$hasName = array_key_exists('name', $data);
$hasSlug = array_key_exists('slug', $data);
$hasDescription = array_key_exists('description', $data);
$hasNewArrival = array_key_exists('is_new_arrival', $data);
$hasFeatured = array_key_exists('is_featured', $data);
$hasBestSeller = array_key_exists('is_best_seller', $data);
$hasStatus = array_key_exists('status', $data);

if (
    !$hasCategoryId &&
    !$hasName &&
    !$hasSlug &&
    !$hasDescription &&
    !$hasNewArrival &&
    !$hasFeatured &&
    !$hasBestSeller &&
    !$hasStatus
) {
    sendResponse(false, 'At least one field must be provided for update.', [
        'updatable_fields' => [
            'category_id',
            'name',
            'slug',
            'description',
            'is_new_arrival',
            'is_featured',
            'is_best_seller',
            'status'
        ]
    ], 422);
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

$categoryId = null;
$name = null;
$slug = null;
$description = null;
$isNewArrival = null;
$isFeatured = null;
$isBestSeller = null;
$status = null;

if ($hasCategoryId) {
    if (
        filter_var($data['category_id'], FILTER_VALIDATE_INT) === false ||
        (int)$data['category_id'] <= 0
    ) {
        sendResponse(false, 'category_id must be a valid positive integer.', null, 422);
    }

    $categoryId = (int)$data['category_id'];
}

if ($hasName) {
    if (!is_string($data['name'])) {
        sendResponse(false, 'Product name must be a string.', null, 422);
    }

    $name = trim($data['name']);

    if ($name === '') {
        sendResponse(false, 'Product name cannot be empty.', null, 422);
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
}

if ($hasSlug) {
    if (!is_string($data['slug'])) {
        sendResponse(false, 'Slug must be a string.', null, 422);
    }

    $slugInput = trim($data['slug']);

    if ($slugInput === '') {
        sendResponse(false, 'Slug cannot be empty.', null, 422);
    }

    $slug = createProductSlug($slugInput);

    if ($slug === '') {
        sendResponse(false, 'Invalid product slug.', null, 422);
    }

    if (mb_strlen($slug) > 220) {
        sendResponse(false, 'Slug must not exceed 220 characters.', null, 422);
    }
}

if ($hasDescription) {
    if ($data['description'] !== null && !is_string($data['description'])) {
        sendResponse(false, 'Description must be a string or null.', null, 422);
    }

    $description = $data['description'] === null
        ? null
        : trim($data['description']);

    if ($description !== null && mb_strlen($description) > 10000) {
        sendResponse(false, 'Description must not exceed 10000 characters.', null, 422);
    }
}

if ($hasNewArrival) {
    $isNewArrival = parseProductBoolean(
        $data['is_new_arrival'],
        'is_new_arrival'
    );
}

if ($hasFeatured) {
    $isFeatured = parseProductBoolean(
        $data['is_featured'],
        'is_featured'
    );
}

if ($hasBestSeller) {
    $isBestSeller = parseProductBoolean(
        $data['is_best_seller'],
        'is_best_seller'
    );
}

if ($hasStatus) {
    if (!is_string($data['status'])) {
        sendResponse(false, 'Status must be a string.', null, 422);
    }

    $status = strtolower(trim($data['status']));

    $allowedStatuses = ['active', 'inactive'];

    if (!in_array($status, $allowedStatuses, true)) {
        sendResponse(false, 'Invalid status.', [
            'allowed_statuses' => $allowedStatuses
        ], 422);
    }
}

try {
    $checkStmt = $pdo->prepare(
        "SELECT
            id,
            category_id,
            name,
            slug,
            description,
            is_new_arrival,
            is_featured,
            is_best_seller,
            status,
            created_at,
            updated_at
         FROM products
         WHERE id = :id
         LIMIT 1"
    );

    $checkStmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $checkStmt->execute();

    $existingProduct = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingProduct) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    if ($hasCategoryId) {
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
    }

    if ($hasName) {
        $duplicateNameStmt = $pdo->prepare(
            "SELECT id
             FROM products
             WHERE LOWER(name) = LOWER(:name)
             AND id != :id
             LIMIT 1"
        );

        $duplicateNameStmt->bindValue(':name', $name, PDO::PARAM_STR);
        $duplicateNameStmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $duplicateNameStmt->execute();

        if ($duplicateNameStmt->fetch()) {
            sendResponse(false, 'Product name already exists.', null, 409);
        }
    }

    if ($hasSlug) {
        $duplicateSlugStmt = $pdo->prepare(
            "SELECT id
             FROM products
             WHERE slug = :slug
             AND id != :id
             LIMIT 1"
        );

        $duplicateSlugStmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $duplicateSlugStmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $duplicateSlugStmt->execute();

        if ($duplicateSlugStmt->fetch()) {
            sendResponse(false, 'Product slug already exists.', null, 409);
        }
    }

    $updateFields = [];
    $params = [
        ':id' => $productId
    ];

    if ($hasCategoryId) {
        $updateFields[] = 'category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($hasName) {
        $updateFields[] = 'name = :name';
        $params[':name'] = $name;

        if (!$hasSlug) {
            $autoSlug = createProductSlug($name);

            if ($autoSlug === '') {
                sendResponse(false, 'Unable to generate slug from product name.', null, 422);
            }

            if (mb_strlen($autoSlug) > 220) {
                sendResponse(false, 'Generated slug must not exceed 220 characters.', null, 422);
            }

            $duplicateAutoSlugStmt = $pdo->prepare(
                "SELECT id
                 FROM products
                 WHERE slug = :slug
                 AND id != :id
                 LIMIT 1"
            );

            $duplicateAutoSlugStmt->bindValue(':slug', $autoSlug, PDO::PARAM_STR);
            $duplicateAutoSlugStmt->bindValue(':id', $productId, PDO::PARAM_INT);
            $duplicateAutoSlugStmt->execute();

            if ($duplicateAutoSlugStmt->fetch()) {
                sendResponse(false, 'Generated product slug already exists.', null, 409);
            }

            $updateFields[] = 'slug = :auto_slug';
            $params[':auto_slug'] = $autoSlug;
        }
    }

    if ($hasSlug) {
        $updateFields[] = 'slug = :slug';
        $params[':slug'] = $slug;
    }

    if ($hasDescription) {
        $updateFields[] = 'description = :description';

        $params[':description'] = (
            $description === null ||
            $description === ''
        ) ? null : $description;
    }

    if ($hasNewArrival) {
        $updateFields[] = 'is_new_arrival = :is_new_arrival';
        $params[':is_new_arrival'] = $isNewArrival;
    }

    if ($hasFeatured) {
        $updateFields[] = 'is_featured = :is_featured';
        $params[':is_featured'] = $isFeatured;
    }

    if ($hasBestSeller) {
        $updateFields[] = 'is_best_seller = :is_best_seller';
        $params[':is_best_seller'] = $isBestSeller;
    }

    if ($hasStatus) {
        $updateFields[] = 'status = :status';
        $params[':status'] = $status;
    }

    if (empty($updateFields)) {
        sendResponse(false, 'No valid fields provided for update.', null, 422);
    }

    $pdo->beginTransaction();

    $sql = "
        UPDATE products
        SET " . implode(', ', $updateFields) . "
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if (
            in_array(
                $key,
                [
                    ':id',
                    ':category_id',
                    ':is_new_arrival',
                    ':is_featured',
                    ':is_best_seller'
                ],
                true
            )
        ) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } elseif ($value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();

    $fetchStmt = $pdo->prepare(
        "SELECT
            p.id,
            p.category_id,
            p.name,
            p.slug,
            p.description,
            p.is_new_arrival,
            p.is_featured,
            p.is_best_seller,
            p.status,
            p.created_at,
            p.updated_at,
            c.name AS category_name,
            c.slug AS category_slug,
            c.status AS category_status
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
        throw new RuntimeException('Unable to retrieve updated product.');
    }

    $pdo->commit();

    sendResponse(true, 'Product updated successfully.', [
        'product' => [
            'id' => (int)$product['id'],
            'category_id' => (int)$product['category_id'],
            'category' => [
                'id' => (int)$product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug'],
                'status' => $product['category_status']
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
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to update product.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}