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

$allowedFields = ['order_id','referral_code'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$orderIdInput = trim((string)($data['order_id'] ?? ''));
$referralCode = strtoupper(trim((string)($data['referral_code'] ?? '')));

if (!preg_match('/^[1-9][0-9]*$/', $orderIdInput)) {
    sendResponse(false, 'order_id must be a valid positive integer.', null, 422);
}

if ($referralCode === '') {
    sendResponse(false, 'referral_code is required.', null, 422);
}

if (mb_strlen($referralCode) > 50) {
    sendResponse(false, 'referral_code must not exceed 50 characters.', null, 422);
}

if (!preg_match('/^[A-Z0-9_-]+$/', $referralCode)) {
    sendResponse(false, 'referral_code contains invalid characters.', [
        'allowed' => 'A-Z, 0-9, underscore and hyphen'
    ], 422);
}

$orderId = (int)$orderIdInput;

date_default_timezone_set('Asia/Kolkata');

function referralCouponFail(
    PDO $pdo,
    string $message,
    mixed $data = null,
    int $code = 422
): never {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(false, $message, $data, $code);
    exit;
}

try {
    $pdo->beginTransaction();

    /*
     * Lock user.
     */
    $stmt = $pdo->prepare(
        "SELECT id,status
         FROM users
         WHERE id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        referralCouponFail($pdo, 'User not found.', null, 404);
    }

    if ($user['status'] !== 'active') {
        referralCouponFail(
            $pdo,
            'User account is not active.',
            null,
            403
        );
    }

    /*
     * User must own the order.
     */
    $stmt = $pdo->prepare(
        "SELECT
            id,
            order_number,
            user_id,
            cart_id,
            subtotal,
            product_discount_amount,
            coupon_type,
            coupon_id,
            coupon_code,
            coupon_discount_amount,
            shipping_charge,
            cod_charge,
            tax_amount,
            grand_total,
            payment_method,
            payment_status,
            order_status,
            placed_at
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
        referralCouponFail(
            $pdo,
            'Order not found.',
            null,
            404
        );
    }

    if ($order['order_status'] !== 'pending') {
        referralCouponFail(
            $pdo,
            'Coupon can only be applied to a pending order.',
            [
                'order_status' => $order['order_status']
            ],
            409
        );
    }

    if ($order['payment_status'] !== 'pending') {
        referralCouponFail(
            $pdo,
            'Coupon cannot be applied after payment process has started.',
            [
                'payment_status' => $order['payment_status']
            ],
            409
        );
    }

    /*
     * One order = only one coupon of any type.
     */
    $couponAlreadyApplied =
        $order['coupon_type'] !== null ||
        $order['coupon_id'] !== null ||
        $order['coupon_code'] !== null ||
        round((float)$order['coupon_discount_amount'], 2) > 0;

    if ($couponAlreadyApplied) {
        referralCouponFail(
            $pdo,
            'A coupon has already been applied to this order.',
            [
                'coupon_type' => $order['coupon_type'],
                'coupon_id' => $order['coupon_id'] !== null
                    ? (int)$order['coupon_id']
                    : null,
                'coupon_code' => $order['coupon_code'],
                'coupon_discount_amount' =>
                    $order['coupon_discount_amount']
            ],
            409
        );
    }

    /*
     * Address must already be selected.
     */
    $stmt = $pdo->prepare(
        "SELECT id
         FROM order_addresses
         WHERE order_id=:order_id
         LIMIT 1"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        referralCouponFail(
            $pdo,
            'Please select delivery address before applying coupon.',
            null,
            409
        );
    }

    /*
     * Referral code lookup.
     * User enters referral_code.
     */
    $stmt = $pdo->prepare(
        "SELECT
            id,
            referral_code,
            title,
            description,
            discount_type,
            discount_value,
            min_order_amount,
            max_discount_amount,
            usage_limit,
            usage_limit_per_user,
            start_at,
            end_at,
            status,
            created_at,
            updated_at
         FROM referral_coupons
         WHERE UPPER(referral_code)=:referral_code
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(
        ':referral_code',
        $referralCode,
        PDO::PARAM_STR
    );

    $stmt->execute();

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        referralCouponFail(
            $pdo,
            'Invalid referral code.',
            [
                'referral_code' => $referralCode
            ],
            404
        );
    }

    $couponId = (int)$coupon['id'];
    $storedReferralCode = (string)$coupon['referral_code'];

    if ($coupon['status'] !== 'active') {
        referralCouponFail(
            $pdo,
            'This referral coupon is not active.',
            [
                'referral_code' => $storedReferralCode,
                'status' => $coupon['status']
            ],
            409
        );
    }

    /*
     * Optional start/end validity.
     */
    $timezone = new DateTimeZone('Asia/Kolkata');
    $now = new DateTimeImmutable('now', $timezone);

    $startAt = null;
    $endAt = null;

    if ($coupon['start_at'] !== null) {
        try {
            $startAt = new DateTimeImmutable(
                (string)$coupon['start_at'],
                $timezone
            );
        } catch (Throwable $e) {
            referralCouponFail(
                $pdo,
                'Invalid referral coupon start_at.',
                null,
                422
            );
        }

        if ($now < $startAt) {
            referralCouponFail(
                $pdo,
                'This referral coupon is not active yet.',
                [
                    'start_at' => $coupon['start_at']
                ],
                409
            );
        }
    }

    if ($coupon['end_at'] !== null) {
        try {
            $endAt = new DateTimeImmutable(
                (string)$coupon['end_at'],
                $timezone
            );
        } catch (Throwable $e) {
            referralCouponFail(
                $pdo,
                'Invalid referral coupon end_at.',
                null,
                422
            );
        }

        if ($now > $endAt) {
            referralCouponFail(
                $pdo,
                'This referral coupon has expired.',
                [
                    'end_at' => $coupon['end_at']
                ],
                409
            );
        }
    }

    if (
        $startAt !== null &&
        $endAt !== null &&
        $startAt > $endAt
    ) {
        referralCouponFail(
            $pdo,
            'Invalid referral coupon validity period.',
            null,
            422
        );
    }

    $discountType = (string)$coupon['discount_type'];

    $discountValue = round(
        (float)$coupon['discount_value'],
        2
    );

    $minOrderAmount = round(
        (float)$coupon['min_order_amount'],
        2
    );

    $maxDiscountAmount = $coupon['max_discount_amount'] !== null
        ? round((float)$coupon['max_discount_amount'], 2)
        : null;

    $usageLimit = $coupon['usage_limit'] !== null
        ? (int)$coupon['usage_limit']
        : null;

    $usageLimitPerUser =
        (int)$coupon['usage_limit_per_user'];

    if (!in_array(
        $discountType,
        ['percentage','flat','free_shipping'],
        true
    )) {
        referralCouponFail(
            $pdo,
            'Invalid coupon discount type.',
            null,
            422
        );
    }

    if ($discountValue < 0) {
        referralCouponFail(
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
        referralCouponFail(
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
        referralCouponFail(
            $pdo,
            'Flat discount value must be greater than 0.',
            null,
            422
        );
    }

    if ($minOrderAmount < 0) {
        referralCouponFail(
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
        referralCouponFail(
            $pdo,
            'Invalid maximum discount amount.',
            null,
            422
        );
    }

    if (
        $usageLimit !== null &&
        $usageLimit <= 0
    ) {
        referralCouponFail(
            $pdo,
            'Invalid usage_limit.',
            null,
            422
        );
    }

    if ($usageLimitPerUser <= 0) {
        referralCouponFail(
            $pdo,
            'Invalid usage_limit_per_user.',
            null,
            422
        );
    }

    /*
     * GLOBAL USAGE LIMIT.
     */
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM referral_coupon_usage
         WHERE referral_coupon_id=:coupon_id"
    );

    $stmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $globalUsedCount = (int)$stmt->fetchColumn();

    if (
        $usageLimit !== null &&
        $globalUsedCount >= $usageLimit
    ) {
        referralCouponFail(
            $pdo,
            'Referral coupon usage limit has been reached.',
            [
                'usage_limit' => $usageLimit,
                'used_count' => $globalUsedCount
            ],
            409
        );
    }

    /*
     * IMPORTANT BUSINESS RULE:
     *
     * SAME referral coupon/code can be used by
     * SAME USER only ONCE.
     *
     * coupon_id=1 used today -> coupon_id=1 again not allowed.
     *
     * coupon_id=2 / different code next month -> allowed.
     */
    $stmt = $pdo->prepare(
        "SELECT
            id,
            referral_coupon_id,
            order_id,
            referral_code,
            used_at
         FROM referral_coupon_usage
         WHERE user_id=:user_id
           AND referral_coupon_id=:coupon_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $previousUsage = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($previousUsage) {
        referralCouponFail(
            $pdo,
            'You have already used this referral code.',
            [
                'eligible' => false,
                'reason' => 'REFERRAL_CODE_ALREADY_USED',
                'referral_coupon_id' =>
                    (int)$previousUsage['referral_coupon_id'],
                'referral_code' =>
                    $previousUsage['referral_code'],
                'previous_order_id' =>
                    (int)$previousUsage['order_id'],
                'used_at' =>
                    $previousUsage['used_at']
            ],
            409
        );
    }

    /*
     * usage_limit_per_user is also respected.
     * Your current business rule is stricter:
     * one code = one use/user.
     */
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM referral_coupon_usage
         WHERE user_id=:user_id
           AND referral_coupon_id=:coupon_id"
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $userUsedCount = (int)$stmt->fetchColumn();

    if ($userUsedCount >= $usageLimitPerUser) {
        referralCouponFail(
            $pdo,
            'Referral coupon usage limit for this user has been reached.',
            [
                'usage_limit_per_user' =>
                    $usageLimitPerUser,
                'used_count' => $userUsedCount
            ],
            409
        );
    }

    /*
     * Same order referral usage duplicate protection.
     */
    $stmt = $pdo->prepare(
        "SELECT id
         FROM referral_coupon_usage
         WHERE order_id=:order_id
         LIMIT 1"
    );

    $stmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    if ($stmt->fetchColumn()) {
        referralCouponFail(
            $pdo,
            'A referral coupon has already been used for this order.',
            null,
            409
        );
    }

    /*
     * Pricing calculation.
     * Same logic as birthday / first-order / festival.
     */
    $subtotal = round(
        (float)$order['subtotal'],
        2
    );

    $productDiscount = round(
        (float)$order['product_discount_amount'],
        2
    );

    $taxAmount = round(
        (float)$order['tax_amount'],
        2
    );

    $shippingCharge = round(
        (float)$order['shipping_charge'],
        2
    );

    $codCharge = round(
        (float)$order['cod_charge'],
        2
    );

    if (
        $subtotal < 0 ||
        $productDiscount < 0 ||
        $taxAmount < 0 ||
        $shippingCharge < 0 ||
        $codCharge < 0
    ) {
        referralCouponFail(
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
        referralCouponFail(
            $pdo,
            'Invalid coupon eligible order amount.',
            null,
            422
        );
    }

    if ($couponEligibleAmount < $minOrderAmount) {
        referralCouponFail(
            $pdo,
            'Minimum order amount not reached for this referral coupon.',
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
            referralCouponFail(
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
        referralCouponFail(
            $pdo,
            'Referral coupon does not provide any discount for this order.',
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

    /*
     * Update order.
     *
     * orders.coupon_type = referral
     * orders.coupon_id = referral_coupons.id
     * orders.coupon_code = referral_code
     */
    $stmt = $pdo->prepare(
        "UPDATE orders SET
            coupon_type='referral',
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

    $stmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':coupon_code',
        $storedReferralCode,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':coupon_discount_amount',
        number_format(
            $couponDiscount,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':grand_total',
        number_format(
            $grandTotal,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    if ($stmt->rowCount() !== 1) {
        referralCouponFail(
            $pdo,
            'Unable to apply coupon. Another coupon may already be applied.',
            null,
            409
        );
    }

    /*
     * Referral usage snapshot.
     */
    $stmt = $pdo->prepare(
        "INSERT INTO referral_coupon_usage (
            referral_coupon_id,
            user_id,
            order_id,
            referral_code,
            discount_type,
            discount_value,
            order_amount_before_discount,
            discount_amount,
            final_order_amount
         ) VALUES (
            :referral_coupon_id,
            :user_id,
            :order_id,
            :referral_code,
            :discount_type,
            :discount_value,
            :order_amount_before_discount,
            :discount_amount,
            :final_order_amount
         )"
    );

    $stmt->bindValue(
        ':referral_coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':referral_code',
        $storedReferralCode,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':discount_type',
        $discountType,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':discount_value',
        number_format(
            $discountValue,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':order_amount_before_discount',
        number_format(
            $orderAmountBeforeCoupon,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':discount_amount',
        number_format(
            $couponDiscount,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':final_order_amount',
        number_format(
            $grandTotal,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->execute();

    $usageId = (int)$pdo->lastInsertId();

    $pdo->commit();

    sendResponse(true, 'Referral coupon applied successfully.', [
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
            'coupon_type' => 'referral',
            'coupon_id' => $couponId,
            'coupon_code' => $storedReferralCode,
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
            'coupon_type' => 'referral',
            'referral_code' => $storedReferralCode,
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
            'max_discount_amount' =>
                $maxDiscountAmount !== null
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
            'end_at' => $coupon['end_at'],
            'status' => $coupon['status']
        ],

        'usage' => [
            'id' => $usageId,
            'referral_coupon_id' => $couponId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'referral_code' => $storedReferralCode,
            'one_use_per_code_per_user' => true
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to apply referral coupon.',
        defined('APP_ENV') &&
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
        defined('APP_ENV') &&
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}