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

function birthdayCouponFail(
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
        birthdayCouponFail($pdo, 'Order not found.', null, 404);
    }

    if ($order['order_status'] !== 'pending') {
        birthdayCouponFail($pdo, 'Coupon can only be applied to a pending order.', [
            'order_status' => $order['order_status']
        ], 409);
    }

    if ($order['payment_status'] !== 'pending') {
        birthdayCouponFail($pdo, 'Coupon cannot be applied after payment process has started.', [
            'payment_status' => $order['payment_status']
        ], 409);
    }

    $couponAlreadyUsed =
        $order['coupon_type'] !== null ||
        $order['coupon_id'] !== null ||
        $order['coupon_code'] !== null ||
        round((float)$order['coupon_discount_amount'], 2) > 0;

    if ($couponAlreadyUsed) {
        birthdayCouponFail($pdo, 'A coupon has already been applied to this order.', [
            'coupon_type' => $order['coupon_type'],
            'coupon_id' => $order['coupon_id'] !== null
                ? (int)$order['coupon_id']
                : null,
            'coupon_code' => $order['coupon_code'],
            'coupon_discount_amount' => $order['coupon_discount_amount']
        ], 409);
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM order_addresses
         WHERE order_id=:order_id
         LIMIT 1"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        birthdayCouponFail(
            $pdo,
            'Please select delivery address before applying coupon.',
            null,
            409
        );
    }

    /*
     * User row FOR UPDATE.
     * Same user simultaneous birthday coupon requests வந்தாலும்
     * இந்த lock duplicate yearly usage-ஐ avoid பண்ண உதவும்.
     */
    $stmt = $pdo->prepare(
        "SELECT id,date_of_birth,status
         FROM users
         WHERE id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        birthdayCouponFail($pdo, 'User not found.', null, 404);
    }

    if ($user['status'] !== 'active') {
        birthdayCouponFail(
            $pdo,
            'User account is not active.',
            null,
            403
        );
    }

    if (
        $user['date_of_birth'] === null ||
        trim((string)$user['date_of_birth']) === ''
    ) {
        birthdayCouponFail($pdo, 'Date of birth is required to use birthday coupon.', [
            'reason' => 'DATE_OF_BIRTH_NOT_AVAILABLE'
        ], 422);
    }

    $dobValue = trim((string)$user['date_of_birth']);

    $dob = DateTimeImmutable::createFromFormat(
        'Y-m-d',
        $dobValue
    );

    $dobErrors = DateTimeImmutable::getLastErrors();

    if (
        !$dob ||
        (
            $dobErrors !== false &&
            (
                $dobErrors['warning_count'] > 0 ||
                $dobErrors['error_count'] > 0
            )
        ) ||
        $dob->format('Y-m-d') !== $dobValue
    ) {
        birthdayCouponFail(
            $pdo,
            'Invalid date_of_birth stored for user.',
            null,
            422
        );
    }

    $today = new DateTimeImmutable('today');

    $currentMonth = (int)$today->format('n');
    $currentYear = (int)$today->format('Y');

    $birthMonth = (int)$dob->format('n');
    $birthDay = (int)$dob->format('j');

    /*
     * NEW RULE:
     * Exact birthday date தேவையில்லை.
     * Birthday month match ஆனால் போதும்.
     */
    if ($birthMonth !== $currentMonth) {
        birthdayCouponFail(
            $pdo,
            'Birthday coupon can only be used during your birthday month.',
            [
                'eligible' => false,
                'reason' => 'NOT_BIRTHDAY_MONTH',
                'date_of_birth' => $dob->format('Y-m-d'),
                'birthday_month' => $birthMonth,
                'current_month' => $currentMonth,
                'current_year' => $currentYear
            ],
            409
        );
    }

    /*
     * NEW RULE:
     * User can use ANY birthday coupon only ONCE per YEAR.
     *
     * coupon_id check கிடையாது.
     * Current year-ல் எந்த birthday coupon usage இருந்தாலும் reject.
     */
    $usageStmt = $pdo->prepare(
        "SELECT
            id,birthday_coupon_id,order_id,coupon_code,
            birthday_month,birthday_day,
            discount_type,discount_value,
            discount_amount,final_order_amount,used_at
         FROM birthday_coupon_usage
         WHERE user_id=:user_id
           AND YEAR(used_at)=:usage_year
         ORDER BY used_at DESC
         LIMIT 1
         FOR UPDATE"
    );

    $usageStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $usageStmt->bindValue(
        ':usage_year',
        $currentYear,
        PDO::PARAM_INT
    );

    $usageStmt->execute();

    $previousUsage = $usageStmt->fetch(PDO::FETCH_ASSOC);

    if ($previousUsage) {
        birthdayCouponFail(
            $pdo,
            'Birthday coupon has already been used for this year.',
            [
                'eligible' => false,
                'reason' => 'BIRTHDAY_COUPON_ALREADY_USED_THIS_YEAR',
                'current_year' => $currentYear,
                'previous_usage' => [
                    'id' => (int)$previousUsage['id'],
                    'birthday_coupon_id' =>
                        (int)$previousUsage['birthday_coupon_id'],
                    'order_id' =>
                        (int)$previousUsage['order_id'],
                    'coupon_code' =>
                        $previousUsage['coupon_code'],
                    'discount_type' =>
                        $previousUsage['discount_type'],
                    'discount_value' =>
                        $previousUsage['discount_value'],
                    'discount_amount' =>
                        $previousUsage['discount_amount'],
                    'final_order_amount' =>
                        $previousUsage['final_order_amount'],
                    'used_at' =>
                        $previousUsage['used_at']
                ]
            ],
            409
        );
    }

    $stmt = $pdo->prepare(
        "SELECT
            id,coupon_code,title,description,
            discount_type,discount_value,
            min_order_amount,max_discount_amount,
            valid_before_days,valid_after_days,
            usage_limit_per_birthday,status,
            created_at,updated_at
         FROM birthday_coupons
         WHERE id=:coupon_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        birthdayCouponFail(
            $pdo,
            'Birthday coupon not found.',
            null,
            404
        );
    }

    if ($coupon['status'] !== 'active') {
        birthdayCouponFail($pdo, 'This birthday coupon is inactive.', [
            'coupon_status' => $coupon['status']
        ], 409);
    }

    $discountType = $coupon['discount_type'];

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

    if (!in_array(
        $discountType,
        ['percentage','flat','free_shipping'],
        true
    )) {
        birthdayCouponFail(
            $pdo,
            'Invalid coupon discount type.',
            null,
            422
        );
    }

    if ($discountValue < 0) {
        birthdayCouponFail(
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
        birthdayCouponFail(
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
        birthdayCouponFail(
            $pdo,
            'Flat discount value must be greater than 0.',
            null,
            422
        );
    }

    if ($minOrderAmount < 0) {
        birthdayCouponFail(
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
        birthdayCouponFail(
            $pdo,
            'Invalid maximum discount amount.',
            null,
            422
        );
    }

    /*
     * Same order-க்கு birthday usage ஏற்கனவே இருக்கக்கூடாது.
     */
    $orderUsageStmt = $pdo->prepare(
        "SELECT id
         FROM birthday_coupon_usage
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
        birthdayCouponFail(
            $pdo,
            'Birthday coupon has already been used for this order.',
            null,
            409
        );
    }

    /*
     * Calculation logic unchanged.
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
        birthdayCouponFail(
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
        birthdayCouponFail(
            $pdo,
            'Invalid coupon eligible order amount.',
            null,
            422
        );
    }

    if ($couponEligibleAmount < $minOrderAmount) {
        birthdayCouponFail(
            $pdo,
            'Minimum order amount not reached for this coupon.',
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
            birthdayCouponFail(
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
        birthdayCouponFail(
            $pdo,
            'Coupon does not provide any discount for this order.',
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
     * orders update.
     * coupon_type always birthday.
     */
    $updateStmt = $pdo->prepare(
        "UPDATE orders SET
            coupon_type='birthday',
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

    $updateStmt->bindValue(
        ':coupon_id',
        $couponId,
        PDO::PARAM_INT
    );

    $updateStmt->bindValue(
        ':coupon_code',
        $coupon['coupon_code'],
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(
        ':coupon_discount_amount',
        number_format(
            $couponDiscount,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(
        ':grand_total',
        number_format(
            $grandTotal,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $updateStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $updateStmt->execute();

    if ($updateStmt->rowCount() !== 1) {
        birthdayCouponFail(
            $pdo,
            'Unable to apply coupon. Another coupon may already be applied.',
            null,
            409
        );
    }

    /*
     * Usage snapshot.
     *
     * birthday_month/day still store பண்ணுறோம்.
     * Eligibility மட்டும் entire birthday month.
     */
    $usageStmt = $pdo->prepare(
        "INSERT INTO birthday_coupon_usage (
            birthday_coupon_id,
            user_id,
            order_id,
            coupon_code,
            birthday_month,
            birthday_day,
            discount_type,
            discount_value,
            order_amount_before_discount,
            discount_amount,
            final_order_amount
         ) VALUES (
            :birthday_coupon_id,
            :user_id,
            :order_id,
            :coupon_code,
            :birthday_month,
            :birthday_day,
            :discount_type,
            :discount_value,
            :order_amount_before_discount,
            :discount_amount,
            :final_order_amount
         )"
    );

    $usageStmt->bindValue(
        ':birthday_coupon_id',
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
        ':birthday_month',
        $birthMonth,
        PDO::PARAM_INT
    );

    $usageStmt->bindValue(
        ':birthday_day',
        $birthDay,
        PDO::PARAM_INT
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

    sendResponse(
        true,
        'Birthday coupon applied successfully.',
        [
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
                'coupon_type' => 'birthday',
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
                'coupon_type' => 'birthday',
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
                )
            ],

            'birthday' => [
                'date_of_birth' => $dob->format('Y-m-d'),
                'birth_month' => $birthMonth,
                'birth_day' => $birthDay,
                'current_month' => $currentMonth,
                'current_year' => $currentYear,
                'valid_for_entire_birth_month' => true,
                'one_time_per_year' => true,
                'already_used_this_year_before' => false
            ],

            'usage' => [
                'id' => $usageId,
                'birthday_coupon_id' => $couponId,
                'user_id' => $userId,
                'order_id' => $orderId,
                'usage_year' => $currentYear
            ]
        ],
        200
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to apply birthday coupon.',
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