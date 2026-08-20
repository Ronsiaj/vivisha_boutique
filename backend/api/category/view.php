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
    sendResponse(false, 'Category ID is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'Category ID must be a valid positive integer.', null, 422);
}

$categoryId = (int)$idInput;

try {
    $stmt = $pdo->prepare(
        "SELECT
            id,
            name,
            slug,
            description,
            image,
            sort_order,
            status,
            created_at,
            updated_at
         FROM categories
         WHERE id = :id
         LIMIT 1"
    );

    $stmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
    $stmt->execute();

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        sendResponse(false, 'Category not found.', null, 404);
    }

    sendResponse(true, 'Category retrieved successfully.', [
        'category' => [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'],
            'image' => $category['image'],
            'sort_order' => (int)$category['sort_order'],
            'status' => $category['status'],
            'created_at' => $category['created_at'],
            'updated_at' => $category['updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve category.',
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