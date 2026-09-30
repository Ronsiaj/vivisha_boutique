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

$data = json_decode(file_get_contents('php://input') ?: '', true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, 'Invalid JSON body.', null, 400);
}

foreach (array_keys($data) as $field) {
    if ($field !== 'cart_id') {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => ['cart_id']
        ], 422);
    }
}

$cartIdInput = trim((string)($data['cart_id'] ?? ''));

if (!preg_match('/^[1-9][0-9]*$/', $cartIdInput)) {
    sendResponse(false, 'cart_id must be a valid positive integer.', null, 422);
}

$cartId = (int)$cartIdInput;

function orderFail(PDO $pdo, string $message, mixed $data = null, int $code = 422): never
{
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, $message, $data, $code);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id,user_id,status,created_at,updated_at
         FROM carts
         WHERE id=:cart_id AND user_id=:user_id
         LIMIT 1 FOR UPDATE"
    );

    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cart) {
        orderFail($pdo, 'Cart not found.', null, 404);
    }

    if ($cart['status'] !== 'active') {
        orderFail($pdo, 'Only active cart can be converted to an order.', [
            'cart_status' => $cart['status']
        ], 409);
    }

    $stmt = $pdo->prepare(
        "SELECT id,order_number
         FROM orders
         WHERE cart_id=:cart_id
         LIMIT 1"
    );

    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->execute();

    $existingOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingOrder) {
        orderFail($pdo, 'This cart has already been converted to an order.', [
            'order_id' => (int)$existingOrder['id'],
            'order_number' => $existingOrder['order_number']
        ], 409);
    }

    $stmt = $pdo->prepare(
        "SELECT
            ci.id AS cart_item_id,ci.product_id,ci.variant_id,ci.quantity,
            p.name AS product_name,p.status AS product_status,
            p.hsn_profile_id,c.status AS category_status,
            hp.hsn_code,hp.status AS hsn_status,
            pv.size_id,pv.color_id,pv.sku,pv.variant_name,
            pv.original_price,pv.gst_rate,pv.gst_amount,pv.price_with_tax,
            pv.discount_type,pv.discount_value,pv.selling_price,
            pv.stock_quantity,pv.reserved_quantity,pv.is_available,
            s.name AS size_name,s.status AS size_status,
            co.name AS color_name,co.status AS color_status
         FROM cart_items ci
         INNER JOIN products p ON p.id=ci.product_id
         INNER JOIN product_variants pv
            ON pv.id=ci.variant_id AND pv.product_id=ci.product_id
         INNER JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors co ON co.id=pv.color_id
         WHERE ci.cart_id=:cart_id
         ORDER BY ci.id ASC
         FOR UPDATE"
    );

    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->execute();

    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$cartItems) {
        orderFail($pdo, 'Cart is empty.', null, 422);
    }

    $preparedItems = [];
    $subtotal = 0.00;
    $productDiscountAmount = 0.00;
    $taxAmount = 0.00;
    $grandTotal = 0.00;

    foreach ($cartItems as $item) {
        $quantity = (int)$item['quantity'];

        if ($quantity <= 0) {
            orderFail($pdo, 'Invalid cart item quantity.', [
                'cart_item_id' => (int)$item['cart_item_id']
            ], 422);
        }

        if ($item['product_status'] !== 'active') {
            orderFail($pdo, 'Product is inactive.', [
                'product_id' => (int)$item['product_id'],
                'product_name' => $item['product_name']
            ], 409);
        }

        if ($item['category_status'] !== 'active') {
            orderFail($pdo, 'Product category is inactive.', [
                'product_id' => (int)$item['product_id']
            ], 409);
        }

        if ($item['hsn_profile_id'] !== null && $item['hsn_status'] !== 'active') {
            orderFail($pdo, 'Product HSN profile is inactive.', [
                'product_id' => (int)$item['product_id']
            ], 409);
        }

        if ((int)$item['is_available'] !== 1) {
            orderFail($pdo, 'Product variant is unavailable.', [
                'variant_id' => (int)$item['variant_id'],
                'sku' => $item['sku']
            ], 409);
        }

        if ($item['size_id'] !== null && $item['size_status'] !== 'active') {
            orderFail($pdo, 'Selected size is inactive.', [
                'variant_id' => (int)$item['variant_id']
            ], 409);
        }

        if ($item['color_id'] !== null && $item['color_status'] !== 'active') {
            orderFail($pdo, 'Selected color is inactive.', [
                'variant_id' => (int)$item['variant_id']
            ], 409);
        }

        $stockQuantity = (int)$item['stock_quantity'];
        $reservedQuantity = (int)$item['reserved_quantity'];
        $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

        if ($availableQuantity < $quantity) {
            orderFail($pdo, 'Insufficient stock.', [
                'product_id' => (int)$item['product_id'],
                'variant_id' => (int)$item['variant_id'],
                'sku' => $item['sku'],
                'requested_quantity' => $quantity,
                'available_quantity' => $availableQuantity
            ], 409);
        }

        $originalPrice = round((float)$item['original_price'], 2);
        $gstRate = round((float)$item['gst_rate'], 2);
        $gstAmount = round((float)$item['gst_amount'], 2);
        $priceWithTax = round((float)$item['price_with_tax'], 2);
        $sellingPrice = round((float)$item['selling_price'], 2);

        if (
            $originalPrice < 0 ||
            $gstRate < 0 ||
            $gstAmount < 0 ||
            $priceWithTax < 0 ||
            $sellingPrice < 0 ||
            $sellingPrice > $priceWithTax
        ) {
            orderFail($pdo, 'Invalid variant pricing.', [
                'variant_id' => (int)$item['variant_id']
            ], 422);
        }

        $unitDiscount = round(max(0, $priceWithTax - $sellingPrice), 2);
        $lineSubtotal = round($originalPrice * $quantity, 2);
        $lineTax = round($gstAmount * $quantity, 2);
        $lineDiscount = round($unitDiscount * $quantity, 2);
        $lineTotal = round($sellingPrice * $quantity, 2);

        $subtotal = round($subtotal + $lineSubtotal, 2);
        $taxAmount = round($taxAmount + $lineTax, 2);
        $productDiscountAmount = round(
            $productDiscountAmount + $lineDiscount,
            2
        );
        $grandTotal = round($grandTotal + $lineTotal, 2);

        $preparedItems[] = [
            'product_id' => (int)$item['product_id'],
            'variant_id' => (int)$item['variant_id'],
            'product_name' => $item['product_name'],
            'variant_name' => $item['variant_name'],
            'sku' => $item['sku'],
            'hsn_code' => $item['hsn_code'],
            'size_name' => $item['size_name'],
            'color_name' => $item['color_name'],
            'original_price' => $originalPrice,
            'selling_price' => $sellingPrice,
            'quantity' => $quantity,
            'product_discount_amount' => $lineDiscount,
            'line_subtotal' => $lineSubtotal,
            'taxable_amount' => $lineSubtotal,
            'gst_rate' => $gstRate,
            'tax_amount' => $lineTax,
            'line_total' => $lineTotal
        ];
    }

    $temporaryOrderNumber = 'TMP' . strtoupper(bin2hex(random_bytes(10)));

    $stmt = $pdo->prepare(
        "INSERT INTO orders (
            order_number,user_id,cart_id,subtotal,
            product_discount_amount,coupon_discount_amount,
            shipping_charge,cod_charge,tax_amount,grand_total,
            payment_method,payment_status,order_status
         ) VALUES (
            :order_number,:user_id,:cart_id,:subtotal,
            :product_discount_amount,0,0,0,:tax_amount,:grand_total,
            NULL,'pending','pending'
         )"
    );

    $stmt->bindValue(':order_number', $temporaryOrderNumber, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->bindValue(':subtotal', number_format($subtotal, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':product_discount_amount', number_format($productDiscountAmount, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':tax_amount', number_format($taxAmount, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':grand_total', number_format($grandTotal, 2, '.', ''), PDO::PARAM_STR);
    $stmt->execute();

    $orderId = (int)$pdo->lastInsertId();

    $orderNumber =
        'ORD' .
        date('ymd') .
        str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare(
        "UPDATE orders
         SET order_number=:order_number
         WHERE id=:id"
    );

    $stmt->bindValue(':order_number', $orderNumber, PDO::PARAM_STR);
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $itemStmt = $pdo->prepare(
        "INSERT INTO order_items (
            order_id,product_id,variant_id,product_name,variant_name,
            sku,hsn_code,size_name,color_name,original_price,
            selling_price,quantity,product_discount_amount,line_subtotal,
            taxable_amount,gst_rate,cgst_rate,cgst_amount,
            sgst_rate,sgst_amount,igst_rate,igst_amount,
            tax_amount,line_total,item_status
         ) VALUES (
            :order_id,:product_id,:variant_id,:product_name,:variant_name,
            :sku,:hsn_code,:size_name,:color_name,:original_price,
            :selling_price,:quantity,:product_discount_amount,:line_subtotal,
            :taxable_amount,:gst_rate,0,0,0,0,0,0,
            :tax_amount,:line_total,'active'
         )"
    );

    $reserveStmt = $pdo->prepare(
        "UPDATE product_variants
         SET reserved_quantity=reserved_quantity+:quantity
         WHERE id=:variant_id
           AND is_available=1
           AND (stock_quantity-reserved_quantity)>=:quantity_check"
    );

    $responseItems = [];

    foreach ($preparedItems as $item) {
        $itemStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $itemStmt->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
        $itemStmt->bindValue(':variant_id', $item['variant_id'], PDO::PARAM_INT);
        $itemStmt->bindValue(':product_name', $item['product_name'], PDO::PARAM_STR);
        $itemStmt->bindValue(
            ':variant_name',
            $item['variant_name'],
            $item['variant_name'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $itemStmt->bindValue(
            ':sku',
            $item['sku'],
            $item['sku'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $itemStmt->bindValue(
            ':hsn_code',
            $item['hsn_code'],
            $item['hsn_code'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $itemStmt->bindValue(
            ':size_name',
            $item['size_name'],
            $item['size_name'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $itemStmt->bindValue(
            ':color_name',
            $item['color_name'],
            $item['color_name'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );

        foreach ([
            'original_price',
            'selling_price',
            'product_discount_amount',
            'line_subtotal',
            'taxable_amount',
            'gst_rate',
            'tax_amount',
            'line_total'
        ] as $field) {
            $itemStmt->bindValue(
                ':' . $field,
                number_format((float)$item[$field], 2, '.', ''),
                PDO::PARAM_STR
            );
        }

        $itemStmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
        $itemStmt->execute();

        $orderItemId = (int)$pdo->lastInsertId();

        $reserveStmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
        $reserveStmt->bindValue(':variant_id', $item['variant_id'], PDO::PARAM_INT);
        $reserveStmt->bindValue(':quantity_check', $item['quantity'], PDO::PARAM_INT);
        $reserveStmt->execute();

        if ($reserveStmt->rowCount() !== 1) {
            orderFail($pdo, 'Stock changed while creating order.', [
                'variant_id' => $item['variant_id'],
                'sku' => $item['sku']
            ], 409);
        }

        $responseItems[] = [
            'id' => $orderItemId,
            'product_id' => $item['product_id'],
            'variant_id' => $item['variant_id'],
            'product_name' => $item['product_name'],
            'variant_name' => $item['variant_name'],
            'sku' => $item['sku'],
            'hsn_code' => $item['hsn_code'],
            'size_name' => $item['size_name'],
            'color_name' => $item['color_name'],
            'quantity' => $item['quantity'],
            'original_price' => number_format($item['original_price'], 2, '.', ''),
            'gst_rate' => number_format($item['gst_rate'], 2, '.', ''),
            'tax_amount' => number_format($item['tax_amount'], 2, '.', ''),
            'product_discount_amount' => number_format($item['product_discount_amount'], 2, '.', ''),
            'selling_price' => number_format($item['selling_price'], 2, '.', ''),
            'line_total' => number_format($item['line_total'], 2, '.', '')
        ];
    }

    $stmt = $pdo->prepare(
        "UPDATE carts
         SET status='converted'
         WHERE id=:cart_id
           AND user_id=:user_id
           AND status='active'"
    );

    $stmt->bindValue(':cart_id', $cartId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        orderFail($pdo, 'Unable to convert cart.', null, 409);
    }

    $pdo->commit();

    sendResponse(true, 'Order created successfully.', [
        'order' => [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'cart_id' => $cartId,
            'user_id' => $userId,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'product_discount_amount' => number_format($productDiscountAmount, 2, '.', ''),
            'coupon_discount_amount' => '0.00',
            'shipping_charge' => '0.00',
            'cod_charge' => '0.00',
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'grand_total' => number_format($grandTotal, 2, '.', ''),
            'payment_method' => null,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'items' => $responseItems,
            'item_count' => count($responseItems)
        ]
    ], 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    sendResponse(
        false,
        'Unable to create order.',
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