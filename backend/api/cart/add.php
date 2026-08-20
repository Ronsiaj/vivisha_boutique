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

$allowedFields = [
    'variant_id',
    'quantity'
];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$variantIdInput = $data['variant_id'] ?? null;
$quantityInput = $data['quantity'] ?? 1;

if ($variantIdInput === null || $variantIdInput === '') {
    sendResponse(false, 'variant_id is required.', null, 422);
}

if (
    filter_var($variantIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$variantIdInput <= 0
) {
    sendResponse(false, 'variant_id must be a valid positive integer.', null, 422);
}

$variantId = (int)$variantIdInput;

if (
    filter_var($quantityInput, FILTER_VALIDATE_INT) === false ||
    (int)$quantityInput <= 0
) {
    sendResponse(false, 'quantity must be a valid positive integer.', null, 422);
}

$quantity = (int)$quantityInput;

if ($quantity > 100) {
    sendResponse(false, 'Quantity cannot exceed 100 per cart item.', null, 422);
}

try {
    $pdo->beginTransaction();

    $variantStmt = $pdo->prepare(
        "SELECT
            pv.id,
            pv.product_id,
            pv.size_id,
            pv.color_id,
            pv.sku,
            pv.variant_name,
            pv.original_price,
            pv.discount_type,
            pv.discount_value,
            pv.selling_price,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,

            p.name AS product_name,
            p.slug AS product_slug,
            p.status AS product_status,
            p.category_id,

            c.name AS category_name,
            c.status AS category_status,

            s.name AS size_name,
            s.status AS size_status,

            co.name AS color_name,
            co.hex_code,
            co.status AS color_status

         FROM product_variants pv

         INNER JOIN products p
            ON p.id = pv.product_id

         INNER JOIN categories c
            ON c.id = p.category_id

         LEFT JOIN sizes s
            ON s.id = pv.size_id

         LEFT JOIN colors co
            ON co.id = pv.color_id

         WHERE pv.id = :variant_id
         LIMIT 1
         FOR UPDATE"
    );

    $variantStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $variantStmt->execute();

    $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        $pdo->rollBack();
        sendResponse(false, 'Product variant not found.', null, 404);
    }

    if ($variant['product_status'] !== 'active') {
        $pdo->rollBack();
        sendResponse(false, 'This product is currently unavailable.', null, 422);
    }

    if ($variant['category_status'] !== 'active') {
        $pdo->rollBack();
        sendResponse(false, 'This product category is currently unavailable.', null, 422);
    }

    if ((int)$variant['is_available'] !== 1) {
        $pdo->rollBack();
        sendResponse(false, 'This product variant is currently unavailable.', null, 422);
    }

    if (
        $variant['size_id'] !== null &&
        $variant['size_status'] !== 'active'
    ) {
        $pdo->rollBack();
        sendResponse(false, 'Selected product size is currently unavailable.', null, 422);
    }

    if (
        $variant['color_id'] !== null &&
        $variant['color_status'] !== 'active'
    ) {
        $pdo->rollBack();
        sendResponse(false, 'Selected product color is currently unavailable.', null, 422);
    }

    $stockQuantity = (int)$variant['stock_quantity'];
    $reservedQuantity = (int)$variant['reserved_quantity'];

    $availableQuantity = $stockQuantity - $reservedQuantity;

    if ($availableQuantity < 0) {
        $availableQuantity = 0;
    }

    if ($availableQuantity <= 0) {
        $pdo->rollBack();
        sendResponse(false, 'This product variant is out of stock.', [
            'available_quantity' => 0
        ], 422);
    }

    if ((float)$variant['selling_price'] < 0) {
        $pdo->rollBack();
        sendResponse(false, 'Invalid product selling price.', null, 422);
    }

    $cartStmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            status,
            created_at,
            updated_at
         FROM carts
         WHERE user_id = :user_id
         AND status = 'active'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $cartStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $cartStmt->execute();

    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        $createCartStmt = $pdo->prepare(
            "INSERT INTO carts (
                user_id,
                status
            ) VALUES (
                :user_id,
                'active'
            )"
        );

        $createCartStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $createCartStmt->execute();

        $cartId = (int)$pdo->lastInsertId();
    } else {
        $cartId = (int)$cart['id'];
    }

    $existingItemStmt = $pdo->prepare(
        "SELECT
            id,
            quantity,
            unit_price
         FROM cart_items
         WHERE cart_id = :cart_id
         AND variant_id = :variant_id
         LIMIT 1
         FOR UPDATE"
    );

    $existingItemStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $existingItemStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $existingItemStmt->execute();

    $existingItem = $existingItemStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingItem) {
        $existingQuantity = (int)$existingItem['quantity'];
        $newQuantity = $existingQuantity + $quantity;

        if ($newQuantity > 100) {
            $pdo->rollBack();

            sendResponse(false, 'Total cart quantity cannot exceed 100 for this item.', [
                'current_cart_quantity' => $existingQuantity,
                'requested_add_quantity' => $quantity,
                'maximum_quantity' => 100
            ], 422);
        }

        if ($newQuantity > $availableQuantity) {
            $pdo->rollBack();

            sendResponse(false, 'Requested quantity exceeds available stock.', [
                'current_cart_quantity' => $existingQuantity,
                'requested_add_quantity' => $quantity,
                'requested_total_quantity' => $newQuantity,
                'available_quantity' => $availableQuantity
            ], 422);
        }

        $updateItemStmt = $pdo->prepare(
            "UPDATE cart_items
             SET
                quantity = :quantity,
                unit_price = :unit_price
             WHERE id = :id"
        );

        $updateItemStmt->bindValue(':quantity', $newQuantity, PDO::PARAM_INT);
        $updateItemStmt->bindValue(
            ':unit_price',
            number_format((float)$variant['selling_price'], 2, '.', ''),
            PDO::PARAM_STR
        );
        $updateItemStmt->bindValue(
            ':id',
            (int)$existingItem['id'],
            PDO::PARAM_INT
        );

        $updateItemStmt->execute();

        $cartItemId = (int)$existingItem['id'];
        $finalQuantity = $newQuantity;
        $action = 'quantity_updated';
    } else {
        if ($quantity > $availableQuantity) {
            $pdo->rollBack();

            sendResponse(false, 'Requested quantity exceeds available stock.', [
                'requested_quantity' => $quantity,
                'available_quantity' => $availableQuantity
            ], 422);
        }

        $insertItemStmt = $pdo->prepare(
            "INSERT INTO cart_items (
                cart_id,
                product_id,
                variant_id,
                quantity,
                unit_price
            ) VALUES (
                :cart_id,
                :product_id,
                :variant_id,
                :quantity,
                :unit_price
            )"
        );

        $insertItemStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
        $insertItemStmt->bindValue(
            ':product_id',
            (int)$variant['product_id'],
            PDO::PARAM_INT
        );
        $insertItemStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $insertItemStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $insertItemStmt->bindValue(
            ':unit_price',
            number_format((float)$variant['selling_price'], 2, '.', ''),
            PDO::PARAM_STR
        );

        $insertItemStmt->execute();

        $cartItemId = (int)$pdo->lastInsertId();
        $finalQuantity = $quantity;
        $action = 'item_added';
    }

    $imageStmt = $pdo->prepare(
        "SELECT
            id,
            image,
            alt_text,
            is_primary,
            sort_order
         FROM product_variant_images
         WHERE variant_id = :variant_id
         AND status = 'active'
         ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC
         LIMIT 1"
    );

    $imageStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $imageStmt->execute();

    $primaryImage = $imageStmt->fetch(PDO::FETCH_ASSOC);

    $cartSummaryStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(quantity), 0) AS total_quantity,
            COALESCE(SUM(quantity * unit_price), 0) AS subtotal
         FROM cart_items
         WHERE cart_id = :cart_id"
    );

    $cartSummaryStmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $cartSummaryStmt->execute();

    $cartSummary = $cartSummaryStmt->fetch(PDO::FETCH_ASSOC);

    $cartFetchStmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            status,
            created_at,
            updated_at
         FROM carts
         WHERE id = :id
         LIMIT 1"
    );

    $cartFetchStmt->bindValue(':id', $cartId, PDO::PARAM_INT);
    $cartFetchStmt->execute();

    $cartData = $cartFetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$cartData) {
        throw new RuntimeException('Unable to retrieve cart.');
    }

    $pdo->commit();

    $unitPrice = (float)$variant['selling_price'];
    $lineTotal = $unitPrice * $finalQuantity;

    sendResponse(true, 'Product added to cart successfully.', [
        'action' => $action,

        'cart' => [
            'id' => (int)$cartData['id'],
            'user_id' => (int)$cartData['user_id'],
            'status' => $cartData['status'],

            'summary' => [
                'total_items' => (int)$cartSummary['total_items'],
                'total_quantity' => (int)$cartSummary['total_quantity'],
                'subtotal' => number_format(
                    (float)$cartSummary['subtotal'],
                    2,
                    '.',
                    ''
                )
            ],

            'created_at' => $cartData['created_at'],
            'updated_at' => $cartData['updated_at']
        ],

        'cart_item' => [
            'id' => $cartItemId,
            'cart_id' => $cartId,
            'product_id' => (int)$variant['product_id'],
            'variant_id' => (int)$variant['id'],
            'quantity' => $finalQuantity,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),

            'product' => [
                'id' => (int)$variant['product_id'],
                'name' => $variant['product_name'],
                'slug' => $variant['product_slug']
            ],

            'category' => [
                'id' => (int)$variant['category_id'],
                'name' => $variant['category_name']
            ],

            'variant' => [
                'id' => (int)$variant['id'],
                'sku' => $variant['sku'],
                'variant_name' => $variant['variant_name'],

                'size' => $variant['size_id'] !== null
                    ? [
                        'id' => (int)$variant['size_id'],
                        'name' => $variant['size_name']
                    ]
                    : null,

                'color' => $variant['color_id'] !== null
                    ? [
                        'id' => (int)$variant['color_id'],
                        'name' => $variant['color_name'],
                        'hex_code' => $variant['hex_code']
                    ]
                    : null,

                'pricing' => [
                    'original_price' => $variant['original_price'],
                    'discount_type' => $variant['discount_type'],
                    'discount_value' => $variant['discount_value'],
                    'selling_price' => $variant['selling_price']
                ],

                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'available_quantity' => $availableQuantity
                ],

                'is_available' => (int)$variant['is_available']
            ],

            'primary_image' => $primaryImage
                ? [
                    'id' => (int)$primaryImage['id'],
                    'image' => $primaryImage['image'],
                    'alt_text' => $primaryImage['alt_text'],
                    'is_primary' => (int)$primaryImage['is_primary'],
                    'sort_order' => (int)$primaryImage['sort_order']
                ]
                : null
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to add product to cart.',
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