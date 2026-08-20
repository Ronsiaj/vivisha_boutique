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

$idInput = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($idInput === '') {
    sendResponse(false, 'Product ID is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'Product ID must be a valid positive integer.', null, 422);
}

$productId = (int)$idInput;

try {
    $stmt = $pdo->prepare(
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
            c.description AS category_description,
            c.image AS category_image,
            c.sort_order AS category_sort_order,
            c.status AS category_status
         FROM products p
         INNER JOIN categories c
            ON c.id = p.category_id
         WHERE p.id = :id
         LIMIT 1"
    );

    $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    sendResponse(true, 'Product retrieved successfully.', [
        'product' => [
            'id' => (int)$product['id'],
            'category_id' => (int)$product['category_id'],
            'category' => [
                'id' => (int)$product['category_id'],
                'name' => $product['category_name'],
                'slug' => $product['category_slug'],
                'description' => $product['category_description'],
                'image' => $product['category_image'],
                'sort_order' => (int)$product['category_sort_order'],
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
    sendResponse(
        false,
        'Unable to retrieve product.',
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