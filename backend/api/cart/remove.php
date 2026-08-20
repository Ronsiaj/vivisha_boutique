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

$allowedFields = ['cart_item_id'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$cartItemIdInput = $data['cart_item_id'] ?? null;

if ($cartItemIdInput === null || $cartItemIdInput === '') {
    sendResponse(false, 'cart_item_id is required.', null, 422);
}

if (
    filter_var($cartItemIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$cartItemIdInput <= 0
) {
    sendResponse(false, 'cart_item_id must be a valid positive integer.', null, 422);
}

$cartItemId = (int)$cartItemIdInput;

try {
    $pdo->beginTransaction();

    $itemStmt = $pdo->prepare(
        "SELECT
            ci.id,
            ci.cart_id,
            ci.product_id,
            ci.variant_id,
            ci.quantity,
            ci.unit_price,
            ca.user_id,
            ca.status AS cart_status
         FROM cart_items ci
         INNER JOIN carts ca
            ON ca.id = ci.cart_id
         WHERE ci.id = :cart_item_id
         LIMIT 1
         FOR UPDATE"
    );

    $itemStmt->bindValue(':cart_item_id', $cartItemId, PDO::PARAM_INT);
    $itemStmt->execute();

    $cartItem = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cartItem) {
        $pdo->rollBack();
        sendResponse(false, 'Cart item not found.', null, 404);
    }

    if ((int)$cartItem['user_id'] !== $userId) {
        $pdo->rollBack();

        sendResponse(
            false,
            'You are not allowed to remove this cart item.',
            null,
            403
        );
    }

    if ($cartItem['cart_status'] !== 'active') {
        $pdo->rollBack();

        sendResponse(
            false,
            'Only items from an active cart can be removed.',
            [
                'cart_status' => $cartItem['cart_status']
            ],
            422
        );
    }

    $cartId = (int)$cartItem['cart_id'];

    $deleteItemStmt = $pdo->prepare(
        "DELETE FROM cart_items
         WHERE id = :cart_item_id
         AND cart_id = :cart_id"
    );

    $deleteItemStmt->bindValue(
        ':cart_item_id',
        $cartItemId,
        PDO::PARAM_INT
    );

    $deleteItemStmt->bindValue(
        ':cart_id',
        $cartId,
        PDO::PARAM_INT
    );

    $deleteItemStmt->execute();

    if ($deleteItemStmt->rowCount() !== 1) {
        throw new RuntimeException('Unable to remove cart item.');
    }

    $remainingStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM cart_items
         WHERE cart_id = :cart_id"
    );

    $remainingStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $remainingStmt->execute();

    $remainingItems = (int)$remainingStmt->fetchColumn();

    $cartDeleted = false;

    if ($remainingItems === 0) {
        $deleteCartStmt = $pdo->prepare(
            "DELETE FROM carts
             WHERE id = :cart_id
             AND user_id = :user_id
             AND status = 'active'"
        );

        $deleteCartStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
        $deleteCartStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $deleteCartStmt->execute();

        if ($deleteCartStmt->rowCount() !== 1) {
            throw new RuntimeException('Unable to remove empty cart.');
        }

        $cartDeleted = true;
    }

    $summary = [
        'total_items' => 0,
        'total_quantity' => 0,
        'subtotal' => '0.00'
    ];

    if (!$cartDeleted) {
        $summaryStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_items,
                COALESCE(SUM(quantity), 0) AS total_quantity,
                COALESCE(SUM(quantity * unit_price), 0) AS subtotal
             FROM cart_items
             WHERE cart_id = :cart_id"
        );

        $summaryStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
        $summaryStmt->execute();

        $summaryData = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        $summary = [
            'total_items' => (int)$summaryData['total_items'],
            'total_quantity' => (int)$summaryData['total_quantity'],
            'subtotal' => number_format(
                (float)$summaryData['subtotal'],
                2,
                '.',
                ''
            )
        ];
    }

    $pdo->commit();

    sendResponse(true, 'Cart item removed successfully.', [
        'removed_item' => [
            'cart_item_id' => $cartItemId,
            'cart_id' => $cartId,
            'product_id' => (int)$cartItem['product_id'],
            'variant_id' => (int)$cartItem['variant_id'],
            'quantity' => (int)$cartItem['quantity'],
            'unit_price' => $cartItem['unit_price']
        ],
        'cart' => [
            'id' => $cartDeleted ? null : $cartId,
            'deleted' => $cartDeleted,
            'remaining_items' => $remainingItems,
            'summary' => $summary
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to remove cart item.',
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