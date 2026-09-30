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

$categoryIdInput = $data['category_id'] ?? null;
$name = isset($data['name']) ? trim((string)$data['name']) : '';
$hsnCode = isset($data['hsn_code']) ? trim((string)$data['hsn_code']) : '';
$description = isset($data['description']) ? trim((string)$data['description']) : '';
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
    sendResponse(false, 'HSN profile name is required.', null, 422);
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

if ($hsnCode === '') {
    sendResponse(false, 'hsn_code is required.', null, 422);
}

if (!preg_match('/^(?:\d{4}|\d{6}|\d{8})$/', $hsnCode)) {
    sendResponse(false, 'hsn_code must contain exactly 4, 6 or 8 digits.', [
        'examples' => ['5007', '610429', '61042900']
    ], 422);
}

if ($description !== '' && mb_strlen($description) > 255) {
    sendResponse(false, 'Description must not exceed 255 characters.', null, 422);
}

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
        sendResponse(false, 'Selected category is inactive.', [
            'category_id' => $categoryId,
            'category_name' => $category['name']
        ], 422);
    }

    $duplicateStmt = $pdo->prepare(
        "SELECT id
         FROM hsn_profiles
         WHERE category_id = :category_id
           AND LOWER(name) = LOWER(:name)
         LIMIT 1"
    );

    $duplicateStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $duplicateStmt->bindValue(':name', $name, PDO::PARAM_STR);
    $duplicateStmt->execute();

    if ($duplicateStmt->fetch()) {
        sendResponse(false, 'HSN profile name already exists for this category.', [
            'category_id' => $categoryId,
            'name' => $name
        ], 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO hsn_profiles (
            category_id,
            name,
            hsn_code,
            description,
            status
        ) VALUES (
            :category_id,
            :name,
            :hsn_code,
            :description,
            :status
        )"
    );

    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':hsn_code', $hsnCode, PDO::PARAM_STR);

    if ($description === '') {
        $stmt->bindValue(':description', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    }

    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->execute();

    $hsnProfileId = (int)$pdo->lastInsertId();

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

    $fetchStmt->bindValue(':id', $hsnProfileId, PDO::PARAM_INT);
    $fetchStmt->execute();

    $hsnProfile = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$hsnProfile) {
        sendResponse(
            false,
            'HSN profile created but unable to retrieve profile.',
            null,
            500
        );
    }

    sendResponse(true, 'HSN profile created successfully.', [
        'hsn_profile' => [
            'id' => (int)$hsnProfile['id'],
            'category_id' => (int)$hsnProfile['category_id'],
            'category_name' => $hsnProfile['category_name'],
            'name' => $hsnProfile['name'],
            'hsn_code' => $hsnProfile['hsn_code'],
            'description' => $hsnProfile['description'],
            'status' => $hsnProfile['status'],
            'created_at' => $hsnProfile['created_at'],
            'updated_at' => $hsnProfile['updated_at']
        ]
    ], 201);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to create HSN profile.',
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