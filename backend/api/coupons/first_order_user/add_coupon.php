<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/jwt.php';

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

$allowedFields = ['order_id','coupon_id'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$orderIdInput = trim((string)($data['order_id'] ?? ''));
$couponIdInput = trim((string)($data['coupon_id'] ?? ''));

if (!preg_match('/^[1-9][0-9]*$/', $orderIdInput)) {
    sendResponse(false, 'order_id must be a valid positive integer.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $couponIdInput)) {
    sendResponse(false, 'coupon_id must be a valid positive integer.', null, 422);
}

$orderId = (int)$orderIdInput;
$couponId = (int)$couponIdInput;

function firstOrderCouponFail(
    PDO $pdo,
    string $message,
    mixed $data = null,
    int $code = 422
): never {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, $message, $data, $code);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            id,order_number,user_id,cart_id,subtotal,
            product_discount_amount,coupon_type,coupon_id,coupon_code,
            coupon_discount_amount,shipping_charge,cod_charge,
            tax_amount,grand_total,payment_method,payment_status,
            order_status,placed_at
         FROM orders
         WHERE id=:order_id
           AND user_id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        firstOrderCouponFail($pdo, 'Order not found.', null, 404);
    }

    if ($order['order_status'] !== 'pending') {
        firstOrderCouponFail($pdo, 'Coupon can only be applied to a pending order.', [
            'order_status' => $order['order_status']
        ], 409);
    }

    if ($order['payment_status'] !== 'pending') {
        firstOrderCouponFail($pdo, 'Coupon cannot be applied after payment process has started.', [
            'payment_status' => $order['payment_status']
        ], 409);
    }

    $couponAlreadyApplied =
        $order['coupon_type'] !== null ||
        $order['coupon_id'] !== null ||
        $order['coupon_code'] !== null ||
        (float)$order['coupon_discount_amount'] > 0;

    if ($couponAlreadyApplied) {
        firstOrderCouponFail($pdo, 'A coupon has already been applied to this order.', [
            'coupon_type' => $order['coupon_type'],
            'coupon_id' => $order['coupon_id'] !== null
                ? (int)$order['coupon_id']
                : null,
            'coupon_code' => $order['coupon_code'],
            'coupon_discount_amount' => $order['coupon_discount_amount']
        ], 409);
    }

    $addressStmt = $pdo->prepare(
        "SELECT id
         FROM order_addresses
         WHERE order_id=:order_id
         LIMIT 1"
    );

    $addressStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $addressStmt->execute();

    if (!$addressStmt->fetchColumn()) {
        firstOrderCouponFail(
            $pdo,
            'Please select delivery address before applying coupon.',
            null,
            409
        );
    }

    $firstOrderStmt = $pdo->prepare(
        "SELECT id,order_number
         FROM orders
         WHERE user_id=:user_id
         ORDER BY id ASC
         LIMIT 1
         FOR UPDATE"
    );

    $firstOrderStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $firstOrderStmt->execute();

    $firstOrder = $firstOrderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$firstOrder || (int)$firstOrder['id'] !== $orderId) {
        firstOrderCouponFail($pdo, 'First order coupon is only valid for your first order.', [
            'eligible' => false,
            'reason' => 'NOT_FIRST_ORDER'
        ], 409);
    }

    $usageStmt = $pdo->prepare(
        "SELECT id,first_order_coupon_id,order_id,coupon_code,used_at
         FROM first_order_coupon_usage
         WHERE user_id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $usageStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $usageStmt->execute();

    $previousUsage = $usageStmt->fetch(PDO::FETCH_ASSOC);

    if ($previousUsage) {
        firstOrderCouponFail(
            $pdo,
            'First order coupon has already been used by this user.',
            [
                'eligible' => false,
                'reason' => 'FIRST_ORDER_COUPON_ALREADY_USED',
                'used_order_id' => (int)$previousUsage['order_id'],
                'coupon_code' => $previousUsage['coupon_code'],
                'used_at' => $previousUsage['used_at']
            ],
            409
        );
    }

    $couponStmt = $pdo->prepare(
        "SELECT
            id,coupon_code,title,description,discount_type,
            discount_value,min_order_amount,max_discount_amount,
            start_at,end_at,status,created_at,updated_at
         FROM first_order_coupons
         WHERE id=:coupon_id
         LIMIT 1
         FOR UPDATE"
    );

    $couponStmt->bindValue(':coupon_id', $couponId, PDO::PARAM_INT);
    $couponStmt->execute();

    $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        firstOrderCouponFail($pdo, 'First order coupon not found.', null, 404);
    }

    if ($coupon['status'] !== 'active') {
        firstOrderCouponFail($pdo, 'This coupon is not active.', [
            'coupon_status' => $coupon['status']
        ], 409);
    }

    $now = new DateTimeImmutable();

    if (
        $coupon['start_at'] !== null &&
        $now < new DateTimeImmutable($coupon['start_at'])
    ) {
        firstOrderCouponFail($pdo, 'This coupon is not active yet.', [
            'start_at' => $coupon['start_at']
        ], 409);
    }

    if (
        $coupon['end_at'] !== null &&
        $now > new DateTimeImmutable($coupon['end_at'])
    ) {
        firstOrderCouponFail($pdo, 'This coupon has expired.', [
            'end_at' => $coupon['end_at']
        ], 409);
    }

    $discountType = $coupon['discount_type'];
    $discountValue = round((float)$coupon['discount_value'], 2);
    $minOrderAmount = round((float)$coupon['min_order_amount'], 2);

    $maxDiscountAmount = $coupon['max_discount_amount'] !== null
        ? round((float)$coupon['max_discount_amount'], 2)
        : null;

    if (!in_array(
        $discountType,
        ['percentage','flat','free_shipping'],
        true
    )) {
        firstOrderCouponFail($pdo, 'Invalid coupon discount type.', null, 422);
    }

    if ($discountValue < 0) {
        firstOrderCouponFail($pdo, 'Invalid coupon discount value.', null, 422);
    }

    if (
        $discountType === 'percentage' &&
        ($discountValue <= 0 || $discountValue > 100)
    ) {
        firstOrderCouponFail(
            $pdo,
            'Percentage coupon value must be between 0 and 100.',
            null,
            422
        );
    }

    if ($discountType === 'flat' && $discountValue <= 0) {
        firstOrderCouponFail(
            $pdo,
            'Flat coupon discount value must be greater than 0.',
            null,
            422
        );
    }

    if ($minOrderAmount < 0) {
        firstOrderCouponFail($pdo, 'Invalid minimum order amount.', null, 422);
    }

    if ($maxDiscountAmount !== null && $maxDiscountAmount < 0) {
        firstOrderCouponFail($pdo, 'Invalid maximum discount amount.', null, 422);
    }

    $subtotal = round((float)$order['subtotal'], 2);
    $productDiscount = round((float)$order['product_discount_amount'], 2);
    $taxAmount = round((float)$order['tax_amount'], 2);
    $shippingCharge = round((float)$order['shipping_charge'], 2);
    $codCharge = round((float)$order['cod_charge'], 2);

    if (
        $subtotal < 0 ||
        $productDiscount < 0 ||
        $taxAmount < 0 ||
        $shippingCharge < 0 ||
        $codCharge < 0
    ) {
        firstOrderCouponFail($pdo, 'Invalid order amount data.', null, 422);
    }

    $couponEligibleAmount = round(
        $subtotal + $taxAmount - $productDiscount,
        2
    );

    if ($couponEligibleAmount < 0) {
        firstOrderCouponFail($pdo, 'Invalid coupon eligible order amount.', null, 422);
    }

    if ($couponEligibleAmount < $minOrderAmount) {
        firstOrderCouponFail($pdo, 'Minimum order amount not reached for this coupon.', [
            'minimum_order_amount' => number_format($minOrderAmount, 2, '.', ''),
            'current_order_amount' => number_format($couponEligibleAmount, 2, '.', ''),
            'required_more' => number_format(
                $minOrderAmount - $couponEligibleAmount,
                2,
                '.',
                ''
            )
        ], 422);
    }

    $orderAmountBeforeCoupon = round(
        $couponEligibleAmount +
        $shippingCharge +
        $codCharge,
        2
    );

    $couponDiscount = 0.00;

    if ($discountType === 'percentage') {
        $couponDiscount = round(
            ($couponEligibleAmount * $discountValue) / 100,
            2
        );

        if (
            $maxDiscountAmount !== null &&
            $couponDiscount > $maxDiscountAmount
        ) {
            $couponDiscount = $maxDiscountAmount;
        }

        $couponDiscount = min(
            $couponDiscount,
            $couponEligibleAmount
        );

    } elseif ($discountType === 'flat') {
        $couponDiscount = min(
            $discountValue,
            $couponEligibleAmount
        );

        if (
            $maxDiscountAmount !== null &&
            $couponDiscount > $maxDiscountAmount
        ) {
            $couponDiscount = $maxDiscountAmount;
        }

    } else {
        if ($shippingCharge <= 0) {
            firstOrderCouponFail(
                $pdo,
                'Free shipping coupon cannot be applied because shipping charge is 0.',
                [
                    'shipping_charge' => number_format(
                        $shippingCharge,
                        2,
                        '.',
                        ''
                    )
                ],
                422
            );
        }

        $couponDiscount = $shippingCharge;

        if (
            $maxDiscountAmount !== null &&
            $couponDiscount > $maxDiscountAmount
        ) {
            $couponDiscount = $maxDiscountAmount;
        }
    }

    $couponDiscount = round($couponDiscount, 2);

    if ($couponDiscount <= 0) {
        firstOrderCouponFail(
            $pdo,
            'Coupon does not provide any discount for this order.',
            null,
            422
        );
    }

    $grandTotal = round(
        $orderAmountBeforeCoupon - $couponDiscount,
        2
    );

    if ($grandTotal < 0) {
        $grandTotal = 0.00;
    }

    $updateOrderStmt = $pdo->prepare(
        "UPDATE orders SET
            coupon_type='first_order',
            coupon_id=:coupon_id,
            coupon_code=:coupon_code,
            coupon_discount_amount=:coupon_discount_amount,
            grand_total=:grand_total
         WHERE id=:order_id
           AND user_id=:user_id
           AND coupon_discount_amount=0
           AND coupon_type IS NULL
           AND coupon_id IS NULL"
    );

    $updateOrderStmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $updateOrderStmt->bindValue(
        ':coupon_code',
        $coupon['coupon_code'],
        PDO::PARAM_STR
    );

    $updateOrderStmt->bindValue(
        ':coupon_discount_amount',
        number_format($couponDiscount, 2, '.', ''),
        PDO::PARAM_STR
    );

    $updateOrderStmt->bindValue(
        ':grand_total',
        number_format($grandTotal, 2, '.', ''),
        PDO::PARAM_STR
    );

    $updateOrderStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $updateOrderStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $updateOrderStmt->execute();

    if ($updateOrderStmt->rowCount() !== 1) {
        firstOrderCouponFail(
            $pdo,
            'Unable to apply coupon. Another coupon may already be applied.',
            null,
            409
        );
    }

    $usageInsertStmt = $pdo->prepare(
        "INSERT INTO first_order_coupon_usage (
            first_order_coupon_id,
            user_id,
            order_id,
            coupon_code,
            discount_type,
            discount_value,
            order_amount_before_discount,
            discount_amount,
            final_order_amount
         ) VALUES (
            :first_order_coupon_id,
            :user_id,
            :order_id,
            :coupon_code,
            :discount_type,
            :discount_value,
            :order_amount_before_discount,
            :discount_amount,
            :final_order_amount
         )"
    );

    $usageInsertStmt->bindValue(
        ':first_order_coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $usageInsertStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $usageInsertStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $usageInsertStmt->bindValue(
        ':coupon_code',
        $coupon['coupon_code'],
        PDO::PARAM_STR
    );

    $usageInsertStmt->bindValue(
        ':discount_type',
        $discountType,
        PDO::PARAM_STR
    );

    $usageInsertStmt->bindValue(
        ':discount_value',
        number_format($discountValue, 2, '.', ''),
        PDO::PARAM_STR
    );

    $usageInsertStmt->bindValue(
        ':order_amount_before_discount',
        number_format($orderAmountBeforeCoupon, 2, '.', ''),
        PDO::PARAM_STR
    );

    $usageInsertStmt->bindValue(
        ':discount_amount',
        number_format($couponDiscount, 2, '.', ''),
        PDO::PARAM_STR
    );

    $usageInsertStmt->bindValue(
        ':final_order_amount',
        number_format($grandTotal, 2, '.', ''),
        PDO::PARAM_STR
    );

    $usageInsertStmt->execute();

    $usageId = (int)$pdo->lastInsertId();

    $pdo->commit();

    sendResponse(true, 'First order coupon applied successfully.', [
        'order' => [
            'id' => $orderId,
            'order_number' => $order['order_number'],
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'product_discount_amount' => number_format(
                $productDiscount,
                2,
                '.',
                ''
            ),
            'tax_amount' => number_format(
                $taxAmount,
                2,
                '.',
                ''
            ),
            'shipping_charge' => number_format(
                $shippingCharge,
                2,
                '.',
                ''
            ),
            'cod_charge' => number_format(
                $codCharge,
                2,
                '.',
                ''
            ),
            'order_amount_before_coupon' => number_format(
                $orderAmountBeforeCoupon,
                2,
                '.',
                ''
            ),
            'coupon_type' => 'first_order',
            'coupon_id' => $couponId,
            'coupon_code' => $coupon['coupon_code'],
            'coupon_discount_amount' => number_format(
                $couponDiscount,
                2,
                '.',
                ''
            ),
            'grand_total' => number_format(
                $grandTotal,
                2,
                '.',
                ''
            )
        ],
        'coupon' => [
            'id' => $couponId,
            'coupon_code' => $coupon['coupon_code'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'discount_type' => $discountType,
            'discount_value' => number_format(
                $discountValue,
                2,
                '.',
                ''
            ),
            'min_order_amount' => number_format(
                $minOrderAmount,
                2,
                '.',
                ''
            ),
            'max_discount_amount' => $maxDiscountAmount !== null
                ? number_format($maxDiscountAmount, 2, '.', '')
                : null,
            'discount_amount' => number_format(
                $couponDiscount,
                2,
                '.',
                ''
            )
        ],
        'usage' => [
            'id' => $usageId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'coupon_type' => 'first_order'
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    if ((string)$e->getCode() === '23000') {
        sendResponse(
            false,
            'First order coupon has already been used.',
            defined('APP_ENV') && APP_ENV === 'development'
                ? ['error' => $e->getMessage()]
                : null,
            409
        );
    }

    sendResponse(
        false,
        'Unable to apply first order coupon.',
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