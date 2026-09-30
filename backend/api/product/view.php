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

$allowedParams = ['id'];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$idInput = $_GET['id'] ?? null;

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

try {
    $stmt = $pdo->prepare(
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

    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    sendResponse(true, 'Product retrieved successfully.', [
        'product' => [
            'id' => (int)$product['id'],

            'category' => [
                'id' => (int)$product['category_id'],
                'name' => $product['category_name'],
                'status' => $product['category_status']
            ],

            'hsn_profile' => $product['hsn_profile_id'] !== null
                ? [
                    'id' => (int)$product['hsn_profile_id'],
                    'name' => $product['hsn_profile_name'],
                    'hsn_code' => $product['hsn_code'],
                    'description' => $product['hsn_description'],
                    'status' => $product['hsn_profile_status']
                ]
                : null,

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