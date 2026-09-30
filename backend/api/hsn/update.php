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
    'hsn_code',
    'description',
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
            'name',
            'hsn_code',
            'description',
            'status'
        ]
    ], 422);
}

$updates = [];
$params = [];

if (array_key_exists('category_id', $updateData)) {
    $categoryIdInput = $updateData['category_id'];

    if ($categoryIdInput === null || $categoryIdInput === '') {
        sendResponse(false, 'category_id cannot be empty.', null, 422);
    }

    if (
        filter_var($categoryIdInput, FILTER_VALIDATE_INT) === false ||
        (int)$categoryIdInput <= 0
    ) {
        sendResponse(false, 'category_id must be a valid positive integer.', null, 422);
    }

    $params[':category_id'] = (int)$categoryIdInput;
    $updates[] = 'category_id = :category_id';
}

if (array_key_exists('name', $updateData)) {
    if ($updateData['name'] === null) {
        sendResponse(false, 'HSN profile name cannot be null.', null, 422);
    }

    $name = trim((string)$updateData['name']);

    if ($name === '') {
        sendResponse(false, 'HSN profile name cannot be empty.', null, 422);
    }

    if (mb_strlen($name) < 2) {
        sendResponse(false, 'HSN profile name must contain at least 2 characters.', null, 422);
    }

    if (mb_strlen($name) > 150) {
        sendResponse(false, 'HSN profile name must not exceed 150 characters.', null, 422);
    }

    if (!preg_match('/^[\p{L}\p{N}\s\-\&\'\/().,+]+$/u', $name)) {
        sendResponse(false, 'HSN profile name contains invalid characters.', null, 422);
    }

    $params[':name'] = $name;
    $updates[] = 'name = :name';
}

if (array_key_exists('hsn_code', $updateData)) {
    if ($updateData['hsn_code'] === null) {
        sendResponse(false, 'hsn_code cannot be null.', null, 422);
    }

    $hsnCode = trim((string)$updateData['hsn_code']);

    if ($hsnCode === '') {
        sendResponse(false, 'hsn_code cannot be empty.', null, 422);
    }

    if (!preg_match('/^(?:\d{4}|\d{6}|\d{8})$/', $hsnCode)) {
        sendResponse(false, 'hsn_code must contain exactly 4, 6 or 8 digits.', [
            'examples' => ['5007', '610429', '61042900']
        ], 422);
    }

    $params[':hsn_code'] = $hsnCode;
    $updates[] = 'hsn_code = :hsn_code';
}

if (array_key_exists('description', $updateData)) {
    if ($updateData['description'] === null) {
        $description = null;
    } else {
        $description = trim((string)$updateData['description']);
        $description = $description === '' ? null : $description;
    }

    if ($description !== null && mb_strlen($description) > 255) {
        sendResponse(false, 'Description must not exceed 255 characters.', null, 422);
    }

    $params[':description'] = $description;
    $updates[] = 'description = :description';
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
        "SELECT id, category_id, name, hsn_code, description, status
         FROM hsn_profiles
         WHERE id = :id
         LIMIT 1"
    );

    $existingStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $existingStmt->execute();

    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(false, 'HSN profile not found.', null, 404);
    }

    $finalCategoryId = array_key_exists(':category_id', $params)
        ? (int)$params[':category_id']
        : (int)$existing['category_id'];

    $finalName = array_key_exists(':name', $params)
        ? (string)$params[':name']
        : (string)$existing['name'];

    if (array_key_exists(':category_id', $params)) {
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
    }

    if (
        array_key_exists(':category_id', $params) ||
        array_key_exists(':name', $params)
    ) {
        $duplicateStmt = $pdo->prepare(
            "SELECT id
             FROM hsn_profiles
             WHERE category_id = :category_id
               AND LOWER(name) = LOWER(:name)
               AND id != :id
             LIMIT 1"
        );

        $duplicateStmt->bindValue(':category_id', $finalCategoryId, PDO::PARAM_INT);
        $duplicateStmt->bindValue(':name', $finalName, PDO::PARAM_STR);
        $duplicateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $duplicateStmt->execute();

        if ($duplicateStmt->fetch()) {
            sendResponse(false, 'HSN profile name already exists for this category.', [
                'category_id' => $finalCategoryId,
                'name' => $finalName
            ], 409);
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE hsn_profiles
         SET " . implode(', ', $updates) . "
         WHERE id = :id"
    );

    foreach ($params as $key => $value) {
        if ($key === ':category_id') {
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
         WHERE hp.id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $fetchStmt->execute();

    $profile = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        sendResponse(false, 'HSN profile updated but unable to retrieve profile.', null, 500);
    }

    sendResponse(true, 'HSN profile updated successfully.', [
        'hsn_profile' => [
            'id' => (int)$profile['id'],
            'category_id' => (int)$profile['category_id'],
            'category_name' => $profile['category_name'],
            'name' => $profile['name'],
            'hsn_code' => $profile['hsn_code'],
            'description' => $profile['description'],
            'status' => $profile['status'],
            'created_at' => $profile['created_at'],
            'updated_at' => $profile['updated_at']
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to update HSN profile.',
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