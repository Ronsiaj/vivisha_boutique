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

$allowedFields = ['items','customer_note'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

if (!isset($data['items']) || !is_array($data['items']) || !$data['items']) {
    sendResponse(false, 'items must be a non-empty array.', null, 422);
}

if (count($data['items']) > 100) {
    sendResponse(false, 'Maximum 100 items are allowed per order.', null, 422);
}

$customerNote = isset($data['customer_note'])
    ? trim((string)$data['customer_note'])
    : null;

if ($customerNote === '') $customerNote = null;

if ($customerNote !== null && mb_strlen($customerNote) > 500) {
    sendResponse(false, 'customer_note must not exceed 500 characters.', null, 422);
}

$inputItems = [];
$seenItems = [];

foreach ($data['items'] as $index => $item) {
    $position = $index + 1;

    if (!is_array($item)) {
        sendResponse(false, "Item {$position} must be an object.", null, 422);
    }

    $allowedItemFields = ['product_id','variant_id','quantity'];

    foreach (array_keys($item) as $field) {
        if (!in_array($field, $allowedItemFields, true)) {
            sendResponse(false, "Invalid field '{$field}' in item {$position}.", [
                'allowed_item_fields' => $allowedItemFields
            ], 422);
        }
    }

    $productIdInput = trim((string)($item['product_id'] ?? ''));
    $variantIdInput = trim((string)($item['variant_id'] ?? ''));
    $quantityInput = trim((string)($item['quantity'] ?? ''));

    if (!preg_match('/^[1-9][0-9]*$/', $productIdInput)) {
        sendResponse(false, "Item {$position}: product_id must be a valid positive integer.", null, 422);
    }

    if (!preg_match('/^[1-9][0-9]*$/', $variantIdInput)) {
        sendResponse(false, "Item {$position}: variant_id must be a valid positive integer.", null, 422);
    }

    if (!preg_match('/^[1-9][0-9]*$/', $quantityInput)) {
        sendResponse(false, "Item {$position}: quantity must be a valid positive integer.", null, 422);
    }

    $productId = (int)$productIdInput;
    $variantId = (int)$variantIdInput;
    $quantity = (int)$quantityInput;
    $key = $productId . ':' . $variantId;

    if (isset($seenItems[$key])) {
        sendResponse(false, "Duplicate product variant found in item {$position}.", [
            'product_id' => $productId,
            'variant_id' => $variantId
        ], 409);
    }

    $seenItems[$key] = true;

    $inputItems[] = [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'quantity' => $quantity
    ];
}

function createOrderFail(PDO $pdo, string $message, mixed $data = null, int $code = 422): never
{
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, $message, $data, $code);
    exit;
}

try {
    $pdo->beginTransaction();

    $variantStmt = $pdo->prepare(
        "SELECT
            pv.id AS variant_id,pv.product_id,pv.size_id,pv.color_id,
            pv.sku,pv.variant_name,pv.original_price,pv.gst_rate,
            pv.gst_amount,pv.price_with_tax,pv.discount_type,
            pv.discount_value,pv.selling_price,pv.stock_quantity,
            pv.reserved_quantity,pv.is_available,
            p.name AS product_name,p.status AS product_status,
            p.hsn_profile_id,c.status AS category_status,
            hp.hsn_code,hp.status AS hsn_status,
            s.name AS size_name,s.status AS size_status,
            co.name AS color_name,co.status AS color_status
         FROM product_variants pv
         INNER JOIN products p ON p.id=pv.product_id
         INNER JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors co ON co.id=pv.color_id
         WHERE pv.id=:variant_id
           AND pv.product_id=:product_id
         LIMIT 1
         FOR UPDATE"
    );

    $preparedItems = [];
    $subtotal = 0.00;
    $taxAmount = 0.00;
    $productDiscountAmount = 0.00;

    foreach ($inputItems as $input) {
        $variantStmt->bindValue(':variant_id', $input['variant_id'], PDO::PARAM_INT);
        $variantStmt->bindValue(':product_id', $input['product_id'], PDO::PARAM_INT);
        $variantStmt->execute();

        $item = $variantStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            createOrderFail($pdo, 'Product variant not found.', [
                'product_id' => $input['product_id'],
                'variant_id' => $input['variant_id']
            ], 404);
        }

        if ($item['product_status'] !== 'active') {
            createOrderFail($pdo, 'Product is inactive.', [
                'product_id' => $input['product_id'],
                'product_name' => $item['product_name']
            ], 409);
        }

        if ($item['category_status'] !== 'active') {
            createOrderFail($pdo, 'Product category is inactive.', [
                'product_id' => $input['product_id']
            ], 409);
        }

        if (
            $item['hsn_profile_id'] !== null &&
            $item['hsn_status'] !== 'active'
        ) {
            createOrderFail($pdo, 'Product HSN profile is inactive.', [
                'product_id' => $input['product_id']
            ], 409);
        }

        if ((int)$item['is_available'] !== 1) {
            createOrderFail($pdo, 'Product variant is unavailable.', [
                'variant_id' => $input['variant_id'],
                'sku' => $item['sku']
            ], 409);
        }

        if (
            $item['size_id'] !== null &&
            $item['size_status'] !== 'active'
        ) {
            createOrderFail($pdo, 'Selected size is inactive.', [
                'variant_id' => $input['variant_id']
            ], 409);
        }

        if (
            $item['color_id'] !== null &&
            $item['color_status'] !== 'active'
        ) {
            createOrderFail($pdo, 'Selected color is inactive.', [
                'variant_id' => $input['variant_id']
            ], 409);
        }

        $stockQuantity = (int)$item['stock_quantity'];
        $reservedQuantity = (int)$item['reserved_quantity'];
        $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

        if ($availableQuantity < $input['quantity']) {
            createOrderFail($pdo, 'Insufficient stock.', [
                'product_id' => $input['product_id'],
                'variant_id' => $input['variant_id'],
                'sku' => $item['sku'],
                'requested_quantity' => $input['quantity'],
                'available_quantity' => $availableQuantity
            ], 409);
        }

        $originalPrice = round((float)$item['original_price'], 2);
        $gstRate = round((float)$item['gst_rate'], 2);
        $discountType = $item['discount_type'];
        $discountValue = round((float)$item['discount_value'], 2);

        if ($originalPrice <= 0 || $originalPrice > 99999999.99) {
            createOrderFail($pdo, 'Invalid original price.', [
                'variant_id' => $input['variant_id']
            ], 422);
        }

        if ($gstRate < 0 || $gstRate > 100) {
            createOrderFail($pdo, 'Invalid GST rate.', [
                'variant_id' => $input['variant_id']
            ], 422);
        }

        if (!in_array($discountType, ['none','percentage','flat'], true)) {
            createOrderFail($pdo, 'Invalid discount type.', [
                'variant_id' => $input['variant_id']
            ], 422);
        }

        if ($discountValue < 0) {
            createOrderFail($pdo, 'Invalid discount value.', [
                'variant_id' => $input['variant_id']
            ], 422);
        }

        $gstAmount = round(($originalPrice * $gstRate) / 100, 2);
        $priceWithTax = round($originalPrice + $gstAmount, 2);
        $unitDiscountAmount = 0.00;

        if ($discountType === 'none') {
            if ($discountValue != 0) {
                createOrderFail($pdo, 'discount_value must be 0 when discount_type is none.', [
                    'variant_id' => $input['variant_id']
                ], 422);
            }
        } elseif ($discountType === 'percentage') {
            if ($discountValue > 100) {
                createOrderFail($pdo, 'Percentage discount cannot exceed 100.', [
                    'variant_id' => $input['variant_id']
                ], 422);
            }

            $unitDiscountAmount = round(
                ($priceWithTax * $discountValue) / 100,
                2
            );
        } else {
            if ($discountValue > $priceWithTax) {
                createOrderFail($pdo, 'Flat discount cannot exceed price with tax.', [
                    'variant_id' => $input['variant_id']
                ], 422);
            }

            $unitDiscountAmount = $discountValue;
        }

        $sellingPrice = round(
            max(0, $priceWithTax - $unitDiscountAmount),
            2
        );

        $quantity = $input['quantity'];
        $lineSubtotal = round($originalPrice * $quantity, 2);
        $lineTaxAmount = round($gstAmount * $quantity, 2);
        $lineDiscountAmount = round($unitDiscountAmount * $quantity, 2);
        $lineTotal = round($sellingPrice * $quantity, 2);

        $subtotal = round($subtotal + $lineSubtotal, 2);
        $taxAmount = round($taxAmount + $lineTaxAmount, 2);
        $productDiscountAmount = round(
            $productDiscountAmount + $lineDiscountAmount,
            2
        );

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
            'gst_rate' => $gstRate,
            'gst_amount' => $gstAmount,
            'price_with_tax' => $priceWithTax,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'unit_discount_amount' => $unitDiscountAmount,
            'selling_price' => $sellingPrice,
            'quantity' => $quantity,
            'product_discount_amount' => $lineDiscountAmount,
            'line_subtotal' => $lineSubtotal,
            'taxable_amount' => $lineSubtotal,
            'tax_amount' => $lineTaxAmount,
            'line_total' => $lineTotal
        ];
    }

    $couponDiscountAmount = 0.00;
    $shippingCharge = 0.00;
    $codCharge = 0.00;

    $grandTotal = round(
        $subtotal +
        $taxAmount -
        $productDiscountAmount -
        $couponDiscountAmount +
        $shippingCharge +
        $codCharge,
        2
    );

    if ($grandTotal < 0) {
        createOrderFail($pdo, 'Invalid order grand total.', null, 422);
    }

    $temporaryOrderNumber =
        'TMP' . strtoupper(bin2hex(random_bytes(10)));

    $orderStmt = $pdo->prepare(
        "INSERT INTO orders (
            order_number,user_id,cart_id,subtotal,
            product_discount_amount,coupon_discount_amount,
            shipping_charge,cod_charge,tax_amount,grand_total,
            payment_method,payment_status,order_status,customer_note
         ) VALUES (
            :order_number,:user_id,NULL,:subtotal,
            :product_discount_amount,:coupon_discount_amount,
            :shipping_charge,:cod_charge,:tax_amount,:grand_total,
            NULL,'pending','pending',:customer_note
         )"
    );

    $orderStmt->bindValue(':order_number', $temporaryOrderNumber, PDO::PARAM_STR);
    $orderStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $orderStmt->bindValue(':subtotal', number_format($subtotal, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(':product_discount_amount', number_format($productDiscountAmount, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(':coupon_discount_amount', number_format($couponDiscountAmount, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(':shipping_charge', number_format($shippingCharge, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(':cod_charge', number_format($codCharge, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(':tax_amount', number_format($taxAmount, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(':grand_total', number_format($grandTotal, 2, '.', ''), PDO::PARAM_STR);
    $orderStmt->bindValue(
        ':customer_note',
        $customerNote,
        $customerNote === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );

    $orderStmt->execute();

    $orderId = (int)$pdo->lastInsertId();

    $orderNumber =
        'ORD' .
        date('ymd') .
        str_pad((string)$orderId, 5, '0', STR_PAD_LEFT);

    $orderNumberStmt = $pdo->prepare(
        "UPDATE orders
         SET order_number=:order_number
         WHERE id=:id"
    );

    $orderNumberStmt->bindValue(':order_number', $orderNumber, PDO::PARAM_STR);
    $orderNumberStmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $orderNumberStmt->execute();

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
            createOrderFail($pdo, 'Stock changed while creating order.', [
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
            'pricing' => [
                'original_price' => number_format($item['original_price'], 2, '.', ''),
                'gst_rate' => number_format($item['gst_rate'], 2, '.', ''),
                'gst_amount' => number_format($item['gst_amount'], 2, '.', ''),
                'price_with_tax' => number_format($item['price_with_tax'], 2, '.', ''),
                'discount_type' => $item['discount_type'],
                'discount_value' => number_format($item['discount_value'], 2, '.', ''),
                'unit_discount_amount' => number_format($item['unit_discount_amount'], 2, '.', ''),
                'selling_price' => number_format($item['selling_price'], 2, '.', ''),
                'line_subtotal' => number_format($item['line_subtotal'], 2, '.', ''),
                'tax_amount' => number_format($item['tax_amount'], 2, '.', ''),
                'product_discount_amount' => number_format($item['product_discount_amount'], 2, '.', ''),
                'line_total' => number_format($item['line_total'], 2, '.', '')
            ],
            'tax_split' => [
                'cgst_rate' => '0.00',
                'cgst_amount' => '0.00',
                'sgst_rate' => '0.00',
                'sgst_amount' => '0.00',
                'igst_rate' => '0.00',
                'igst_amount' => '0.00'
            ],
            'item_status' => 'active'
        ];
    }

    $pdo->commit();

    sendResponse(true, 'Order created successfully.', [
        'order' => [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'cart_id' => null,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'product_discount_amount' => number_format($productDiscountAmount, 2, '.', ''),
            'coupon_discount_amount' => number_format($couponDiscountAmount, 2, '.', ''),
            'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
            'cod_charge' => number_format($codCharge, 2, '.', ''),
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'grand_total' => number_format($grandTotal, 2, '.', ''),
            'payment_method' => null,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'customer_note' => $customerNote,
            'address_selected' => false,
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