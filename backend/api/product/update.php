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
    'hsn_profile_id',
    'name',
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
    sendResponse(false, 'id is required.', null, 422);
}

if (
    filter_var($idInput, FILTER_VALIDATE_INT) === false ||
    (int)$idInput <= 0
) {
    sendResponse(false, 'id must be a valid positive integer.', null, 422);
}

$id = (int)$idInput;

$updateData = $data;
unset($updateData['id']);

if (count($updateData) === 0) {
    sendResponse(false, 'At least one field is required for update.', [
        'updatable_fields' => [
            'category_id',
            'hsn_profile_id',
            'name',
            'description',
            'is_new_arrival',
            'is_featured',
            'is_best_seller',
            'status'
        ]
    ], 422);
}

function parseProductUpdateBoolean(mixed $value, string $field): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    if (is_int($value) && in_array($value, [0, 1], true)) {
        return $value;
    }

    if (is_string($value)) {
        $value = strtolower(trim($value));

        if (in_array($value, ['1', 'true'], true)) {
            return 1;
        }

        if (in_array($value, ['0', 'false'], true)) {
            return 0;
        }
    }

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return 0;
}

$updates = [];
$params = [];

if (array_key_exists('category_id', $updateData)) {
    $value = $updateData['category_id'];

    if ($value === null || $value === '') {
        sendResponse(false, 'category_id cannot be empty.', null, 422);
    }

    if (
        filter_var($value, FILTER_VALIDATE_INT) === false ||
        (int)$value <= 0
    ) {
        sendResponse(false, 'category_id must be a valid positive integer.', null, 422);
    }

    $params[':category_id'] = (int)$value;
    $updates[] = 'category_id = :category_id';
}

if (array_key_exists('hsn_profile_id', $updateData)) {
    $value = $updateData['hsn_profile_id'];

    if ($value === null || $value === '') {
        sendResponse(false, 'hsn_profile_id cannot be empty.', null, 422);
    }

    if (
        filter_var($value, FILTER_VALIDATE_INT) === false ||
        (int)$value <= 0
    ) {
        sendResponse(false, 'hsn_profile_id must be a valid positive integer.', null, 422);
    }

    $params[':hsn_profile_id'] = (int)$value;
    $updates[] = 'hsn_profile_id = :hsn_profile_id';
}

if (array_key_exists('name', $updateData)) {
    if ($updateData['name'] === null) {
        sendResponse(false, 'Product name cannot be null.', null, 422);
    }

    $name = trim((string)$updateData['name']);

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

    $params[':name'] = $name;
    $updates[] = 'name = :name';
}

if (array_key_exists('description', $updateData)) {
    if ($updateData['description'] === null) {
        $description = null;
    } else {
        $description = trim((string)$updateData['description']);
        $description = $description === '' ? null : $description;
    }

    if ($description !== null && mb_strlen($description) > 10000) {
        sendResponse(false, 'Description must not exceed 10000 characters.', null, 422);
    }

    $params[':description'] = $description;
    $updates[] = 'description = :description';
}

if (array_key_exists('is_new_arrival', $updateData)) {
    $params[':is_new_arrival'] = parseProductUpdateBoolean(
        $updateData['is_new_arrival'],
        'is_new_arrival'
    );

    $updates[] = 'is_new_arrival = :is_new_arrival';
}

if (array_key_exists('is_featured', $updateData)) {
    $params[':is_featured'] = parseProductUpdateBoolean(
        $updateData['is_featured'],
        'is_featured'
    );

    $updates[] = 'is_featured = :is_featured';
}

if (array_key_exists('is_best_seller', $updateData)) {
    $params[':is_best_seller'] = parseProductUpdateBoolean(
        $updateData['is_best_seller'],
        'is_best_seller'
    );

    $updates[] = 'is_best_seller = :is_best_seller';
}

if (array_key_exists('status', $updateData)) {
    if ($updateData['status'] === null) {
        sendResponse(false, 'status cannot be null.', null, 422);
    }

    $status = strtolower(trim((string)$updateData['status']));
    $allowedStatuses = ['active', 'inactive'];

    if (!in_array($status, $allowedStatuses, true)) {
        sendResponse(false, 'Invalid status.', [
            'allowed_statuses' => $allowedStatuses
        ], 422);
    }

    $params[':status'] = $status;
    $updates[] = 'status = :status';
}

try {
    $existingStmt = $pdo->prepare(
        "SELECT
            id,
            category_id,
            hsn_profile_id,
            name,
            slug,
            description,
            is_new_arrival,
            is_featured,
            is_best_seller,
            status
         FROM products
         WHERE id = :id
         LIMIT 1"
    );

    $existingStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $existingStmt->execute();

    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    $finalCategoryId = array_key_exists(':category_id', $params)
        ? (int)$params[':category_id']
        : (int)$existing['category_id'];

    $finalHsnProfileId = array_key_exists(':hsn_profile_id', $params)
        ? (int)$params[':hsn_profile_id']
        : (
            $existing['hsn_profile_id'] !== null
                ? (int)$existing['hsn_profile_id']
                : null
        );

    $finalName = array_key_exists(':name', $params)
        ? (string)$params[':name']
        : (string)$existing['name'];

    $categoryStmt = $pdo->prepare(
        "SELECT id, name, status
         FROM categories
         WHERE id = :id
         LIMIT 1"
    );

    $categoryStmt->bindValue(':id', $finalCategoryId, PDO::PARAM_INT);
    $categoryStmt->execute();

    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        sendResponse(false, 'Category not found.', null, 404);
    }

    if ($category['status'] !== 'active') {
        sendResponse(false, 'Selected category is inactive.', [
            'category_id' => $finalCategoryId,
            'category_name' => $category['name']
        ], 422);
    }

    if ($finalHsnProfileId === null) {
        sendResponse(false, 'Product must have a valid hsn_profile_id.', null, 422);
    }

    $hsnStmt = $pdo->prepare(
        "SELECT
            id,
            category_id,
            name,
            hsn_code,
            description,
            status
         FROM hsn_profiles
         WHERE id = :id
         LIMIT 1"
    );

    $hsnStmt->bindValue(':id', $finalHsnProfileId, PDO::PARAM_INT);
    $hsnStmt->execute();

    $hsnProfile = $hsnStmt->fetch(PDO::FETCH_ASSOC);

    if (!$hsnProfile) {
        sendResponse(false, 'HSN profile not found.', null, 404);
    }

    if ($hsnProfile['status'] !== 'active') {
        sendResponse(false, 'Selected HSN profile is inactive.', [
            'hsn_profile_id' => $finalHsnProfileId,
            'hsn_profile_name' => $hsnProfile['name']
        ], 422);
    }

    if ((int)$hsnProfile['category_id'] !== $finalCategoryId) {
        sendResponse(false, 'Selected HSN profile does not belong to the selected category.', [
            'category_id' => $finalCategoryId,
            'category_name' => $category['name'],
            'hsn_profile_id' => $finalHsnProfileId,
            'hsn_profile_name' => $hsnProfile['name']
        ], 422);
    }

    $duplicateNameStmt = $pdo->prepare(
        "SELECT id
         FROM products
         WHERE LOWER(name) = LOWER(:name)
           AND id != :id
         LIMIT 1"
    );

    $duplicateNameStmt->bindValue(':name', $finalName, PDO::PARAM_STR);
    $duplicateNameStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $duplicateNameStmt->execute();

    if ($duplicateNameStmt->fetch()) {
        sendResponse(false, 'Product name already exists.', [
            'name' => $finalName
        ], 409);
    }

    $duplicateSlugStmt = $pdo->prepare(
        "SELECT id
         FROM products
         WHERE slug = :slug
           AND id != :id
         LIMIT 1"
    );

    $duplicateSlugStmt->bindValue(':slug', $existing['slug'], PDO::PARAM_STR);
    $duplicateSlugStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $duplicateSlugStmt->execute();

    if ($duplicateSlugStmt->fetch()) {
        sendResponse(false, 'Product slug already exists.', [
            'slug' => $existing['slug']
        ], 409);
    }

    $stmt = $pdo->prepare(
        "UPDATE products
         SET " . implode(', ', $updates) . "
         WHERE id = :id"
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
        } elseif ($key === ':description' && $value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $fetchStmt = $pdo->prepare(
        "SELECT
            p.id,
            p.category_id,
            c.name AS category_name,
            c.status AS category_status,
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
         WHERE p.id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $fetchStmt->execute();

    $product = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(false, 'Product updated but unable to retrieve product.', null, 500);
    }

    sendResponse(true, 'Product updated successfully.', [
        'product' => [
            'id' => (int)$product['id'],
            'category' => [
                'id' => (int)$product['category_id'],
                'name' => $product['category_name'],
                'status' => $product['category_status']
            ],
            'hsn_profile' => [
                'id' => (int)$product['hsn_profile_id'],
                'name' => $product['hsn_profile_name'],
                'hsn_code' => $product['hsn_code'],
                'description' => $product['hsn_description'],
                'status' => $product['hsn_profile_status']
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
    ]);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to update product.',
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