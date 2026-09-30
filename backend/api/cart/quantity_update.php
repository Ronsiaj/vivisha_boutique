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

if (getAuthenticatedType($decoded) !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
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

$allowedFields = ['cart_item_id','quantity'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$cartItemIdInput = $data['cart_item_id'] ?? null;
$quantityInput = $data['quantity'] ?? null;

if ($cartItemIdInput === null || $cartItemIdInput === '') {
    sendResponse(false, 'cart_item_id is required.', null, 422);
}

if (
    filter_var($cartItemIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$cartItemIdInput <= 0
) {
    sendResponse(false, 'cart_item_id must be a valid positive integer.', null, 422);
}

if ($quantityInput === null || $quantityInput === '') {
    sendResponse(false, 'quantity is required.', null, 422);
}

if (
    filter_var($quantityInput, FILTER_VALIDATE_INT) === false ||
    (int)$quantityInput <= 0
) {
    sendResponse(false, 'quantity must be a valid positive integer.', null, 422);
}

$cartItemId = (int)$cartItemIdInput;
$quantity = (int)$quantityInput;

if ($quantity > 100) {
    sendResponse(false, 'Quantity cannot exceed 100 per cart item.', null, 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            ci.id AS cart_item_id,ci.cart_id,ci.product_id,ci.variant_id,
            ci.quantity AS old_quantity,ci.unit_price AS old_unit_price,
            ca.user_id,ca.status AS cart_status,
            p.name AS product_name,p.slug AS product_slug,
            p.category_id,p.hsn_profile_id,p.status AS product_status,
            c.name AS category_name,c.status AS category_status,
            hp.name AS hsn_name,hp.hsn_code,hp.status AS hsn_status,
            pv.size_id,pv.color_id,pv.sku,pv.variant_name,
            pv.original_price,pv.gst_rate,pv.gst_amount,pv.price_with_tax,
            pv.discount_type,pv.discount_value,pv.selling_price,
            pv.stock_quantity,pv.reserved_quantity,pv.low_stock_limit,
            pv.is_available,
            s.name AS size_name,s.status AS size_status,
            co.name AS color_name,co.hex_code,co.status AS color_status
         FROM cart_items ci
         INNER JOIN carts ca ON ca.id=ci.cart_id
         INNER JOIN products p ON p.id=ci.product_id
         INNER JOIN product_variants pv
            ON pv.id=ci.variant_id AND pv.product_id=ci.product_id
         INNER JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors co ON co.id=pv.color_id
         WHERE ci.id=:cart_item_id
           AND ca.user_id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':cart_item_id', $cartItemId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $pdo->rollBack();
        sendResponse(false, 'Cart item not found.', null, 404);
    }

    if ($item['cart_status'] !== 'active') {
        $pdo->rollBack();
        sendResponse(false, 'Only active cart items can be updated.', [
            'cart_status' => $item['cart_status']
        ], 409);
    }

    if ($item['product_status'] !== 'active') {
        $pdo->rollBack();
        sendResponse(false, 'This product is currently unavailable.', null, 409);
    }

    if ($item['category_status'] !== 'active') {
        $pdo->rollBack();
        sendResponse(false, 'This product category is currently unavailable.', null, 409);
    }

    if (
        $item['hsn_profile_id'] !== null &&
        $item['hsn_status'] !== 'active'
    ) {
        $pdo->rollBack();
        sendResponse(false, 'Product HSN profile is currently inactive.', null, 409);
    }

    if ((int)$item['is_available'] !== 1) {
        $pdo->rollBack();
        sendResponse(false, 'This product variant is currently unavailable.', null, 409);
    }

    if (
        $item['size_id'] !== null &&
        $item['size_status'] !== 'active'
    ) {
        $pdo->rollBack();
        sendResponse(false, 'Selected product size is currently unavailable.', null, 409);
    }

    if (
        $item['color_id'] !== null &&
        $item['color_status'] !== 'active'
    ) {
        $pdo->rollBack();
        sendResponse(false, 'Selected product color is currently unavailable.', null, 409);
    }

    $stockQuantity = (int)$item['stock_quantity'];
    $reservedQuantity = (int)$item['reserved_quantity'];
    $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

    if ($availableQuantity <= 0) {
        $pdo->rollBack();
        sendResponse(false, 'This product variant is out of stock.', [
            'available_quantity' => 0
        ], 409);
    }

    if ($quantity > $availableQuantity) {
        $pdo->rollBack();
        sendResponse(false, 'Requested quantity exceeds available stock.', [
            'requested_quantity' => $quantity,
            'available_quantity' => $availableQuantity,
            'maximum_cart_quantity' => min(100, $availableQuantity)
        ], 422);
    }

    $originalPrice = round((float)$item['original_price'], 2);
    $gstRate = round((float)$item['gst_rate'], 2);
    $gstAmount = round((float)$item['gst_amount'], 2);
    $priceWithTax = round((float)$item['price_with_tax'], 2);
    $discountValue = round((float)$item['discount_value'], 2);
    $sellingPrice = round((float)$item['selling_price'], 2);

    if (
        $originalPrice < 0 ||
        $gstRate < 0 ||
        $gstRate > 100 ||
        $gstAmount < 0 ||
        $priceWithTax < 0 ||
        $sellingPrice < 0 ||
        $sellingPrice > $priceWithTax
    ) {
        $pdo->rollBack();
        sendResponse(false, 'Invalid product pricing.', null, 422);
    }

    if (!in_array(
        $item['discount_type'],
        ['none','percentage','flat'],
        true
    )) {
        $pdo->rollBack();
        sendResponse(false, 'Invalid product discount type.', null, 422);
    }

    $discountAmount = round(
        max(0, $priceWithTax - $sellingPrice),
        2
    );

    $oldUnitPrice = round((float)$item['old_unit_price'], 2);
    $priceChanged = $oldUnitPrice !== $sellingPrice;

    $stmt = $pdo->prepare(
        "UPDATE cart_items
         SET quantity=:quantity,
             unit_price=:unit_price
         WHERE id=:cart_item_id
           AND cart_id=:cart_id"
    );

    $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindValue(
        ':unit_price',
        number_format($sellingPrice, 2, '.', ''),
        PDO::PARAM_STR
    );
    $stmt->bindValue(':cart_item_id', $cartItemId, PDO::PARAM_INT);
    $stmt->bindValue(':cart_id', (int)$item['cart_id'], PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare(
        "SELECT id,image,alt_text,is_primary,sort_order,status
         FROM product_variant_images
         WHERE variant_id=:variant_id
           AND status='active'
         ORDER BY is_primary DESC,sort_order ASC,id ASC
         LIMIT 1"
    );

    $stmt->bindValue(':variant_id', (int)$item['variant_id'], PDO::PARAM_INT);
    $stmt->execute();

    $primaryImage = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(quantity),0) AS total_quantity,
            COALESCE(SUM(quantity*unit_price),0) AS subtotal
         FROM cart_items
         WHERE cart_id=:cart_id"
    );

    $stmt->bindValue(':cart_id', (int)$item['cart_id'], PDO::PARAM_INT);
    $stmt->execute();

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT id,user_id,status,created_at,updated_at
         FROM carts
         WHERE id=:cart_id
           AND user_id=:user_id
         LIMIT 1"
    );

    $stmt->bindValue(':cart_id', (int)$item['cart_id'], PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        throw new RuntimeException('Unable to retrieve updated cart.');
    }

    $pdo->commit();

    $lineTotal = round($sellingPrice * $quantity, 2);

    sendResponse(true, 'Cart item updated successfully.', [
        'cart' => [
            'id' => (int)$cart['id'],
            'user_id' => (int)$cart['user_id'],
            'status' => $cart['status'],
            'summary' => [
                'total_items' => (int)$summary['total_items'],
                'total_quantity' => (int)$summary['total_quantity'],
                'subtotal' => number_format(
                    (float)$summary['subtotal'],
                    2,
                    '.',
                    ''
                )
            ],
            'created_at' => $cart['created_at'],
            'updated_at' => $cart['updated_at']
        ],
        'cart_item' => [
            'id' => $cartItemId,
            'cart_id' => (int)$item['cart_id'],
            'product_id' => (int)$item['product_id'],
            'variant_id' => (int)$item['variant_id'],
            'old_quantity' => (int)$item['old_quantity'],
            'quantity' => $quantity,
            'old_unit_price' => number_format($oldUnitPrice, 2, '.', ''),
            'unit_price' => number_format($sellingPrice, 2, '.', ''),
            'price_changed' => $priceChanged,
            'line_total' => number_format($lineTotal, 2, '.', ''),
            'product' => [
                'id' => (int)$item['product_id'],
                'name' => $item['product_name'],
                'slug' => $item['product_slug']
            ],
            'category' => [
                'id' => (int)$item['category_id'],
                'name' => $item['category_name']
            ],
            'hsn_profile' => $item['hsn_profile_id'] !== null ? [
                'id' => (int)$item['hsn_profile_id'],
                'name' => $item['hsn_name'],
                'hsn_code' => $item['hsn_code'],
                'status' => $item['hsn_status']
            ] : null,
            'variant' => [
                'id' => (int)$item['variant_id'],
                'sku' => $item['sku'],
                'variant_name' => $item['variant_name'],
                'size' => $item['size_id'] !== null ? [
                    'id' => (int)$item['size_id'],
                    'name' => $item['size_name']
                ] : null,
                'color' => $item['color_id'] !== null ? [
                    'id' => (int)$item['color_id'],
                    'name' => $item['color_name'],
                    'hex_code' => $item['hex_code']
                ] : null,
                'pricing' => [
                    'original_price' => number_format($originalPrice, 2, '.', ''),
                    'gst_rate' => number_format($gstRate, 2, '.', ''),
                    'gst_amount' => number_format($gstAmount, 2, '.', ''),
                    'price_with_tax' => number_format($priceWithTax, 2, '.', ''),
                    'discount_type' => $item['discount_type'],
                    'discount_value' => number_format($discountValue, 2, '.', ''),
                    'discount_amount' => number_format($discountAmount, 2, '.', ''),
                    'selling_price' => number_format($sellingPrice, 2, '.', '')
                ],
                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'available_quantity' => $availableQuantity,
                    'low_stock_limit' => (int)$item['low_stock_limit']
                ],
                'is_available' => (int)$item['is_available']
            ],
            'primary_image' => $primaryImage ? [
                'id' => (int)$primaryImage['id'],
                'image' => $primaryImage['image'],
                'alt_text' => $primaryImage['alt_text'],
                'is_primary' => (int)$primaryImage['is_primary'],
                'sort_order' => (int)$primaryImage['sort_order'],
                'status' => $primaryImage['status']
            ] : null
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    sendResponse(
        false,
        'Unable to update cart item.',
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