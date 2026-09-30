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

    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        sendResponse(false, 'HSN profile not found.', null, 404);
    }

    sendResponse(true, 'HSN profile retrieved successfully.', [
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
        'Unable to retrieve HSN profile.',
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