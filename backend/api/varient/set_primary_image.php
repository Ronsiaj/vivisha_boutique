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

if (getAuthenticatedId($decoded) <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if (getAuthenticatedType($decoded) !== 'admin') {
    sendResponse(false, 'Admin access only.', null, 403);
}

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    sendResponse(false, 'Content-Type must be application/json.', null, 415);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, 'Invalid JSON body.', null, 400);
}

$allowedFields = ['product_variant_id','image_id'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$variantIdInput = trim((string)($data['product_variant_id'] ?? ''));
$imageIdInput = trim((string)($data['image_id'] ?? ''));

if ($variantIdInput === '') {
    sendResponse(false, 'product_variant_id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $variantIdInput)) {
    sendResponse(false, 'product_variant_id must be a valid positive integer.', null, 422);
}

if ($imageIdInput === '') {
    sendResponse(false, 'image_id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $imageIdInput)) {
    sendResponse(false, 'image_id must be a valid positive integer.', null, 422);
}

$variantId = (int)$variantIdInput;
$imageId = (int)$imageIdInput;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id,product_id,sku,variant_name,is_available
         FROM product_variants
         WHERE id=:variant_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        $pdo->rollBack();
        sendResponse(false, 'Product variant not found.', null, 404);
    }

    $stmt = $pdo->prepare(
        "SELECT id,variant_id,image,alt_text,is_primary,sort_order,status,created_at,updated_at
         FROM product_variant_images
         WHERE id=:image_id
           AND variant_id=:variant_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':image_id', $imageId, PDO::PARAM_INT);
    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        $pdo->rollBack();
        sendResponse(false, 'Image not found for this product variant.', [
            'product_variant_id' => $variantId,
            'image_id' => $imageId
        ], 404);
    }

    if ($image['status'] !== 'active') {
        $pdo->rollBack();
        sendResponse(false, 'Inactive image cannot be set as primary.', [
            'image_id' => $imageId,
            'status' => $image['status']
        ], 422);
    }

    $stmt = $pdo->prepare(
        "UPDATE product_variant_images
         SET is_primary=0
         WHERE variant_id=:variant_id"
    );

    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare(
        "UPDATE product_variant_images
         SET is_primary=1
         WHERE id=:image_id
           AND variant_id=:variant_id"
    );

    $stmt->bindValue(':image_id', $imageId, PDO::PARAM_INT);
    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Unable to set primary image.');
    }

    $stmt = $pdo->prepare(
        "SELECT id,variant_id,image,alt_text,is_primary,sort_order,status,created_at,updated_at
         FROM product_variant_images
         WHERE variant_id=:variant_id
         ORDER BY is_primary DESC,sort_order ASC,id ASC"
    );

    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $images = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'variant_id' => (int)$row['variant_id'],
        'image' => $row['image'],
        'alt_text' => $row['alt_text'],
        'is_primary' => (int)$row['is_primary'],
        'sort_order' => (int)$row['sort_order'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $primaryImage = null;

    foreach ($images as $row) {
        if ($row['id'] === $imageId) {
            $primaryImage = $row;
            break;
        }
    }

    $pdo->commit();

    sendResponse(true, 'Primary image updated successfully.', [
        'variant' => [
            'id' => $variantId,
            'product_id' => (int)$variant['product_id'],
            'sku' => $variant['sku'],
            'variant_name' => $variant['variant_name'],
            'is_available' => (int)$variant['is_available']
        ],
        'primary_image' => $primaryImage,
        'images' => $images
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    sendResponse(
        false,
        'Unable to update primary image.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}