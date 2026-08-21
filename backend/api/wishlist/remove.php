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
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) {
    sendResponse(false, 'Only DELETE or POST method is allowed.', null, 405);
}

$decoded = authenticate();
validateJWTData($decoded);

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if ($accountType !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
}

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

$allowedFields = ['wishlist_id'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$wishlistIdInput = $data['wishlist_id'] ?? null;

if ($wishlistIdInput === null || $wishlistIdInput === '') {
    sendResponse(false, 'wishlist_id is required.', null, 422);
}

if (
    filter_var($wishlistIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$wishlistIdInput <= 0
) {
    sendResponse(
        false,
        'wishlist_id must be a valid positive integer.',
        null,
        422
    );
}

$wishlistId = (int)$wishlistIdInput;

try {
    $pdo->beginTransaction();

    $wishlistStmt = $pdo->prepare(
        "SELECT
            w.id,
            w.user_id,
            w.product_id,
            w.variant_id,
            w.created_at,
            w.updated_at,

            p.name AS product_name,
            p.slug AS product_slug,

            pv.sku,
            pv.variant_name,

            s.name AS size_name,

            co.name AS color_name,
            co.hex_code

         FROM wishlists w

         INNER JOIN products p
            ON p.id = w.product_id

         INNER JOIN product_variants pv
            ON pv.id = w.variant_id
            AND pv.product_id = w.product_id

         LEFT JOIN sizes s
            ON s.id = pv.size_id

         LEFT JOIN colors co
            ON co.id = pv.color_id

         WHERE w.id = :wishlist_id

         LIMIT 1
         FOR UPDATE"
    );

    $wishlistStmt->bindValue(
        ':wishlist_id',
        $wishlistId,
        PDO::PARAM_INT
    );

    $wishlistStmt->execute();

    $wishlist = $wishlistStmt->fetch(PDO::FETCH_ASSOC);

    if (!$wishlist) {
        $pdo->rollBack();

        sendResponse(
            false,
            'Wishlist item not found.',
            null,
            404
        );
    }

    if ((int)$wishlist['user_id'] !== $userId) {
        $pdo->rollBack();

        sendResponse(
            false,
            'You are not allowed to remove this wishlist item.',
            null,
            403
        );
    }

    $deleteStmt = $pdo->prepare(
        "DELETE FROM wishlists
         WHERE id = :wishlist_id
         AND user_id = :user_id"
    );

    $deleteStmt->bindValue(
        ':wishlist_id',
        $wishlistId,
        PDO::PARAM_INT
    );

    $deleteStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $deleteStmt->execute();

    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Unable to remove wishlist item.'
        );
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM wishlists
         WHERE user_id = :user_id"
    );

    $countStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $countStmt->execute();

    $remainingWishlistCount =
        (int)$countStmt->fetchColumn();

    $pdo->commit();

    sendResponse(
        true,
        'Wishlist item removed successfully.',
        [
            'removed_wishlist' => [
                'id' => (int)$wishlist['id'],
                'product_id' => (int)$wishlist['product_id'],
                'variant_id' => (int)$wishlist['variant_id'],

                'product' => [
                    'id' => (int)$wishlist['product_id'],
                    'name' => $wishlist['product_name'],
                    'slug' => $wishlist['product_slug']
                ],

                'variant' => [
                    'id' => (int)$wishlist['variant_id'],
                    'sku' => $wishlist['sku'],
                    'variant_name' => $wishlist['variant_name'],

                    'size' => $wishlist['size_name'] !== null
                        ? [
                            'name' => $wishlist['size_name']
                        ]
                        : null,

                    'color' => $wishlist['color_name'] !== null
                        ? [
                            'name' => $wishlist['color_name'],
                            'hex_code' => $wishlist['hex_code']
                        ]
                        : null
                ]
            ],

            'wishlist_count' =>
                $remainingWishlistCount
        ],
        200
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to remove wishlist item.',
        APP_ENV === 'development'
            ? [
                'error' => $e->getMessage()
            ]
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
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );
}