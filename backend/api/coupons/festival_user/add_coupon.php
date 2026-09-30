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

date_default_timezone_set('Asia/Kolkata');

function festivalCouponFail(
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

    $userStmt = $pdo->prepare(
        "SELECT id,status
         FROM users
         WHERE id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $userStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        festivalCouponFail($pdo, 'User not found.', null, 404);
    }

    if ($user['status'] !== 'active') {
        festivalCouponFail($pdo, 'User account is not active.', null, 403);
    }

    $orderStmt = $pdo->prepare(
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

    $orderStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $orderStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $orderStmt->execute();

    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        festivalCouponFail($pdo, 'Order not found.', null, 404);
    }

    if ($order['order_status'] !== 'pending') {
        festivalCouponFail(
            $pdo,
            'Coupon can only be applied to a pending order.',
            ['order_status' => $order['order_status']],
            409
        );
    }

    if ($order['payment_status'] !== 'pending') {
        festivalCouponFail(
            $pdo,
            'Coupon cannot be applied after payment process has started.',
            ['payment_status' => $order['payment_status']],
            409
        );
    }

    $couponAlreadyApplied =
        $order['coupon_type'] !== null ||
        $order['coupon_id'] !== null ||
        $order['coupon_code'] !== null ||
        round((float)$order['coupon_discount_amount'], 2) > 0;

    if ($couponAlreadyApplied) {
        festivalCouponFail(
            $pdo,
            'A coupon has already been applied to this order.',
            [
                'coupon_type' => $order['coupon_type'],
                'coupon_id' => $order['coupon_id'] !== null
                    ? (int)$order['coupon_id']
                    : null,
                'coupon_code' => $order['coupon_code'],
                'coupon_discount_amount' => $order['coupon_discount_amount']
            ],
            409
        );
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
        festivalCouponFail(
            $pdo,
            'Please select delivery address before applying coupon.',
            null,
            409
        );
    }

    $couponStmt = $pdo->prepare(
        "SELECT
            id,festival_name,coupon_code,title,description,
            discount_type,discount_value,min_order_amount,
            max_discount_amount,usage_limit,usage_limit_per_user,
            start_at,end_at,status,created_at,updated_at
         FROM festival_coupons
         WHERE id=:coupon_id
         LIMIT 1
         FOR UPDATE"
    );

    $couponStmt->bindValue(':coupon_id', $couponId, PDO::PARAM_INT);
    $couponStmt->execute();

    $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        festivalCouponFail($pdo, 'Festival coupon not found.', null, 404);
    }

    if ($coupon['status'] !== 'active') {
        festivalCouponFail(
            $pdo,
            'This festival coupon is not active.',
            ['coupon_status' => $coupon['status']],
            409
        );
    }

    try {
        $startAt = new DateTimeImmutable(
            $coupon['start_at'],
            new DateTimeZone('Asia/Kolkata')
        );

        $endAt = new DateTimeImmutable(
            $coupon['end_at'],
            new DateTimeZone('Asia/Kolkata')
        );
    } catch (Throwable $e) {
        festivalCouponFail(
            $pdo,
            'Invalid festival coupon validity dates.',
            null,
            422
        );
    }

    if ($startAt > $endAt) {
        festivalCouponFail(
            $pdo,
            'Festival coupon start_at cannot be greater than end_at.',
            null,
            422
        );
    }

    $now = new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Kolkata')
    );

    if ($now < $startAt) {
        festivalCouponFail(
            $pdo,
            'This festival coupon is not active yet.',
            [
                'festival_name' => $coupon['festival_name'],
                'start_at' => $coupon['start_at'],
                'end_at' => $coupon['end_at']
            ],
            409
        );
    }

    if ($now > $endAt) {
        festivalCouponFail(
            $pdo,
            'This festival coupon has expired.',
            [
                'festival_name' => $coupon['festival_name'],
                'start_at' => $coupon['start_at'],
                'end_at' => $coupon['end_at']
            ],
            409
        );
    }

    $discountType = $coupon['discount_type'];
    $discountValue = round((float)$coupon['discount_value'], 2);
    $minOrderAmount = round((float)$coupon['min_order_amount'], 2);

    $maxDiscountAmount = $coupon['max_discount_amount'] !== null
        ? round((float)$coupon['max_discount_amount'], 2)
        : null;

    $usageLimit = $coupon['usage_limit'] !== null
        ? (int)$coupon['usage_limit']
        : null;

    $usageLimitPerUser = (int)$coupon['usage_limit_per_user'];

    if (!in_array(
        $discountType,
        ['percentage','flat','free_shipping'],
        true
    )) {
        festivalCouponFail(
            $pdo,
            'Invalid festival coupon discount type.',
            null,
            422
        );
    }

    if ($discountValue < 0) {
        festivalCouponFail(
            $pdo,
            'Invalid coupon discount value.',
            null,
            422
        );
    }

    if (
        $discountType === 'percentage' &&
        ($discountValue <= 0 || $discountValue > 100)
    ) {
        festivalCouponFail(
            $pdo,
            'Percentage discount must be greater than 0 and not exceed 100.',
            null,
            422
        );
    }

    if (
        $discountType === 'flat' &&
        $discountValue <= 0
    ) {
        festivalCouponFail(
            $pdo,
            'Flat discount value must be greater than 0.',
            null,
            422
        );
    }

    if ($minOrderAmount < 0) {
        festivalCouponFail(
            $pdo,
            'Invalid minimum order amount.',
            null,
            422
        );
    }

    if (
        $maxDiscountAmount !== null &&
        $maxDiscountAmount < 0
    ) {
        festivalCouponFail(
            $pdo,
            'Invalid maximum discount amount.',
            null,
            422
        );
    }

    if ($usageLimit !== null && $usageLimit <= 0) {
        festivalCouponFail(
            $pdo,
            'Invalid festival coupon usage_limit.',
            null,
            422
        );
    }

    if ($usageLimitPerUser <= 0) {
        festivalCouponFail(
            $pdo,
            'Invalid festival coupon usage_limit_per_user.',
            null,
            422
        );
    }

    $globalUsageStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM festival_coupon_usage
         WHERE festival_coupon_id=:coupon_id"
    );

    $globalUsageStmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $globalUsageStmt->execute();

    $globalUsedCount = (int)$globalUsageStmt->fetchColumn();

    if (
        $usageLimit !== null &&
        $globalUsedCount >= $usageLimit
    ) {
        festivalCouponFail(
            $pdo,
            'Festival coupon global usage limit has been reached.',
            [
                'festival_name' => $coupon['festival_name'],
                'usage_limit' => $usageLimit,
                'used_count' => $globalUsedCount
            ],
            409
        );
    }

    $userCouponUsageStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM festival_coupon_usage
         WHERE festival_coupon_id=:coupon_id
           AND user_id=:user_id"
    );

    $userCouponUsageStmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $userCouponUsageStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $userCouponUsageStmt->execute();

    $userCouponUsedCount = (int)$userCouponUsageStmt->fetchColumn();

    if ($userCouponUsedCount >= $usageLimitPerUser) {
        festivalCouponFail(
            $pdo,
            'Festival coupon usage limit for this user has been reached.',
            [
                'festival_name' => $coupon['festival_name'],
                'usage_limit_per_user' => $usageLimitPerUser,
                'used_count' => $userCouponUsedCount
            ],
            409
        );
    }

    $periodUsageStmt = $pdo->prepare(
        "SELECT
            fcu.id,
            fcu.festival_coupon_id,
            fcu.order_id,
            fcu.coupon_code,
            fcu.used_at,
            fc.festival_name
         FROM festival_coupon_usage fcu
         INNER JOIN festival_coupons fc
            ON fc.id=fcu.festival_coupon_id
         WHERE fcu.user_id=:user_id
           AND fcu.used_at>=:start_at
           AND fcu.used_at<=:end_at
         ORDER BY fcu.used_at DESC
         LIMIT 1"
    );

    $periodUsageStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $periodUsageStmt->bindValue(
        ':start_at',
        $startAt->format('Y-m-d H:i:s'),
        PDO::PARAM_STR
    );

    $periodUsageStmt->bindValue(
        ':end_at',
        $endAt->format('Y-m-d H:i:s'),
        PDO::PARAM_STR
    );

    $periodUsageStmt->execute();

    $periodUsage = $periodUsageStmt->fetch(PDO::FETCH_ASSOC);

    if ($periodUsage) {
        festivalCouponFail(
            $pdo,
            'You have already used a festival coupon during this festival period.',
            [
                'eligible' => false,
                'reason' => 'FESTIVAL_PERIOD_COUPON_ALREADY_USED',
                'current_festival' => $coupon['festival_name'],
                'start_at' => $coupon['start_at'],
                'end_at' => $coupon['end_at'],
                'previous_festival' => $periodUsage['festival_name'],
                'previous_coupon_code' => $periodUsage['coupon_code'],
                'previous_order_id' => (int)$periodUsage['order_id'],
                'used_at' => $periodUsage['used_at']
            ],
            409
        );
    }

    $orderUsageStmt = $pdo->prepare(
        "SELECT id
         FROM festival_coupon_usage
         WHERE order_id=:order_id
         LIMIT 1"
    );

    $orderUsageStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $orderUsageStmt->execute();

    if ($orderUsageStmt->fetchColumn()) {
        festivalCouponFail(
            $pdo,
            'A festival coupon has already been used for this order.',
            null,
            409
        );
    }

    $subtotal = round((float)$order['subtotal'], 2);
    $productDiscount = round(
        (float)$order['product_discount_amount'],
        2
    );
    $taxAmount = round((float)$order['tax_amount'], 2);
    $shippingCharge = round(
        (float)$order['shipping_charge'],
        2
    );
    $codCharge = round((float)$order['cod_charge'], 2);

    if (
        $subtotal < 0 ||
        $productDiscount < 0 ||
        $taxAmount < 0 ||
        $shippingCharge < 0 ||
        $codCharge < 0
    ) {
        festivalCouponFail(
            $pdo,
            'Invalid order amount data.',
            null,
            422
        );
    }

    $couponEligibleAmount = round(
        $subtotal +
        $taxAmount -
        $productDiscount,
        2
    );

    if ($couponEligibleAmount < 0) {
        festivalCouponFail(
            $pdo,
            'Invalid coupon eligible order amount.',
            null,
            422
        );
    }

    if ($couponEligibleAmount < $minOrderAmount) {
        festivalCouponFail(
            $pdo,
            'Minimum order amount not reached for this festival coupon.',
            [
                'minimum_order_amount' => number_format(
                    $minOrderAmount,
                    2,
                    '.',
                    ''
                ),
                'current_order_amount' => number_format(
                    $couponEligibleAmount,
                    2,
                    '.',
                    ''
                ),
                'required_more' => number_format(
                    $minOrderAmount - $couponEligibleAmount,
                    2,
                    '.',
                    ''
                )
            ],
            422
        );
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
            festivalCouponFail(
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

    $couponDiscount = round(
        $couponDiscount,
        2
    );

    if ($couponDiscount <= 0) {
        festivalCouponFail(
            $pdo,
            'Festival coupon does not provide any discount for this order.',
            null,
            422
        );
    }

    $grandTotal = round(
        $orderAmountBeforeCoupon -
        $couponDiscount,
        2
    );

    if ($grandTotal < 0) {
        $grandTotal = 0.00;
    }

    $updateOrderStmt = $pdo->prepare(
        "UPDATE orders SET
            coupon_type='festival',
            coupon_id=:coupon_id,
            coupon_code=:coupon_code,
            coupon_discount_amount=:coupon_discount_amount,
            grand_total=:grand_total
         WHERE id=:order_id
           AND user_id=:user_id
           AND coupon_type IS NULL
           AND coupon_id IS NULL
           AND coupon_code IS NULL
           AND coupon_discount_amount=0"
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
        number_format(
            $couponDiscount,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $updateOrderStmt->bindValue(
        ':grand_total',
        number_format(
            $grandTotal,
            2,
            '.',
            ''
        ),
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
        festivalCouponFail(
            $pdo,
            'Unable to apply coupon. Another coupon may already be applied.',
            null,
            409
        );
    }

    $usageStmt = $pdo->prepare(
        "INSERT INTO festival_coupon_usage (
            festival_coupon_id,
            user_id,
            order_id,
            coupon_code,
            discount_type,
            discount_value,
            order_amount_before_discount,
            discount_amount,
            final_order_amount
         ) VALUES (
            :festival_coupon_id,
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

    $usageStmt->bindValue(
        ':festival_coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $usageStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $usageStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $usageStmt->bindValue(
        ':coupon_code',
        $coupon['coupon_code'],
        PDO::PARAM_STR
    );

    $usageStmt->bindValue(
        ':discount_type',
        $discountType,
        PDO::PARAM_STR
    );

    $usageStmt->bindValue(
        ':discount_value',
        number_format(
            $discountValue,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $usageStmt->bindValue(
        ':order_amount_before_discount',
        number_format(
            $orderAmountBeforeCoupon,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $usageStmt->bindValue(
        ':discount_amount',
        number_format(
            $couponDiscount,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $usageStmt->bindValue(
        ':final_order_amount',
        number_format(
            $grandTotal,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $usageStmt->execute();

    $usageId = (int)$pdo->lastInsertId();

    $pdo->commit();

    sendResponse(true, 'Festival coupon applied successfully.', [
        'order' => [
            'id' => $orderId,
            'order_number' => $order['order_number'],
            'subtotal' => number_format(
                $subtotal,
                2,
                '.',
                ''
            ),
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
            'coupon_type' => 'festival',
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
            'coupon_type' => 'festival',
            'festival_name' => $coupon['festival_name'],
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
                ? number_format(
                    $maxDiscountAmount,
                    2,
                    '.',
                    ''
                )
                : null,
            'discount_amount' => number_format(
                $couponDiscount,
                2,
                '.',
                ''
            ),
            'start_at' => $coupon['start_at'],
            'end_at' => $coupon['end_at']
        ],
        'eligibility' => [
            'festival_name' => $coupon['festival_name'],
            'current_time' => $now->format('Y-m-d H:i:s'),
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'usage_limit' => $usageLimit,
            'global_used_count_before' => $globalUsedCount,
            'usage_limit_per_user' => $usageLimitPerUser,
            'user_coupon_used_count_before' => $userCouponUsedCount,
            'festival_period_used_before' => false
        ],
        'usage' => [
            'id' => $usageId,
            'festival_coupon_id' => $couponId,
            'user_id' => $userId,
            'order_id' => $orderId
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    if ((string)$e->getCode() === '23000') {
        sendResponse(
            false,
            'Festival coupon could not be applied because of duplicate usage.',
            defined('APP_ENV') && APP_ENV === 'development'
                ? ['error' => $e->getMessage()]
                : null,
            409
        );
    }

    sendResponse(
        false,
        'Unable to apply festival coupon.',
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