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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method is allowed.', null, 405);
}

$decoded = authenticate();
validateJWTData($decoded);

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if (!in_array($accountType, ['admin','user'], true)) {
    sendResponse(false, 'Access denied.', null, 403);
}

if ($accountType === 'admin') {
    $adminAuth = authenticateAdmin();
    checkAdminRole($adminAuth, ['admin']);
} else {
    $userAuth = authenticateUser();
    $authenticatedId = getAuthenticatedId($userAuth);

    if ($authenticatedId <= 0) {
        sendResponse(false, 'Invalid authenticated user.', null, 401);
    }
}

function positiveId(?string $value, string $field): ?int
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }

    return (int)$value;
}

function validDate(?string $value, string $field): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        (
            $errors !== false &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        ) ||
        $date->format('Y-m-d') !== $value
    ) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }

    return $value;
}

function validAmount(?string $value, string $field): ?float
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (
        !is_numeric($value) ||
        (float)$value < 0 ||
        (float)$value > 9999999999.99
    ) {
        sendResponse(false, "{$field} must be a valid non-negative amount.", null, 422);
    }

    return round((float)$value, 2);
}

$allowedParams = [
    'q',
    'id',
    'order_id',
    'order_number',
    'user_id',
    'cart_id',
    'coupon_type',
    'coupon_id',
    'coupon_code',
    'payment_method',
    'payment_status',
    'order_status',
    'product_id',
    'variant_id',
    'sku',
    'hsn_code',
    'item_status',
    'city',
    'district',
    'state',
    'pincode',
    'has_address',
    'min_total',
    'max_total',
    'placed_from',
    'placed_to',
    'created_from',
    'created_to',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'q must not exceed 200 characters.', null, 422);
}

$id = positiveId($_GET['id'] ?? null, 'id');
$orderId = positiveId($_GET['order_id'] ?? null, 'order_id');

if ($id !== null && $orderId !== null && $id !== $orderId) {
    sendResponse(false, 'id and order_id cannot contain different values.', null, 422);
}

$selectedOrderId = $orderId ?? $id;
$userIdFilter = positiveId($_GET['user_id'] ?? null, 'user_id');
$cartId = positiveId($_GET['cart_id'] ?? null, 'cart_id');
$couponId = positiveId($_GET['coupon_id'] ?? null, 'coupon_id');
$productId = positiveId($_GET['product_id'] ?? null, 'product_id');
$variantId = positiveId($_GET['variant_id'] ?? null, 'variant_id');

if (
    $accountType === 'user' &&
    $userIdFilter !== null &&
    $userIdFilter !== $authenticatedId
) {
    sendResponse(false, 'You can only view your own orders.', null, 403);
}

$orderNumber = trim((string)($_GET['order_number'] ?? ''));
$couponCode = trim((string)($_GET['coupon_code'] ?? ''));
$sku = trim((string)($_GET['sku'] ?? ''));
$hsnCode = trim((string)($_GET['hsn_code'] ?? ''));
$city = trim((string)($_GET['city'] ?? ''));
$district = trim((string)($_GET['district'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
$pincode = trim((string)($_GET['pincode'] ?? ''));

foreach ([
    'order_number' => $orderNumber,
    'coupon_code' => $couponCode,
    'sku' => $sku,
    'hsn_code' => $hsnCode,
    'city' => $city,
    'district' => $district,
    'state' => $state,
    'pincode' => $pincode
] as $field => $value) {
    if ($value !== '' && mb_strlen($value) > 150) {
        sendResponse(false, "{$field} is too long.", null, 422);
    }
}

$couponType = strtolower(trim((string)($_GET['coupon_type'] ?? '')));

if (
    $couponType !== '' &&
    !in_array(
        $couponType,
        ['birthday','festival','referral','first_order'],
        true
    )
) {
    sendResponse(false, 'Invalid coupon_type.', [
        'allowed_values' => [
            'birthday',
            'festival',
            'referral',
            'first_order'
        ]
    ], 422);
}

$paymentMethod = strtolower(
    trim((string)($_GET['payment_method'] ?? ''))
);

if (
    $paymentMethod !== '' &&
    !in_array(
        $paymentMethod,
        ['cod','razorpay','upi','card','netbanking'],
        true
    )
) {
    sendResponse(false, 'Invalid payment_method.', [
        'allowed_values' => [
            'cod',
            'razorpay',
            'upi',
            'card',
            'netbanking'
        ]
    ], 422);
}

$paymentStatus = strtolower(
    trim((string)($_GET['payment_status'] ?? ''))
);

$orderStatus = strtolower(
    trim((string)($_GET['order_status'] ?? ''))
);

$itemStatus = strtolower(
    trim((string)($_GET['item_status'] ?? ''))
);

foreach ([
    'payment_status' => $paymentStatus,
    'order_status' => $orderStatus,
    'item_status' => $itemStatus
] as $field => $value) {
    if (
        $value !== '' &&
        !preg_match('/^[a-z0-9_]+$/', $value)
    ) {
        sendResponse(false, "Invalid {$field}.", null, 422);
    }
}

$hasAddressInput = trim((string)($_GET['has_address'] ?? ''));
$hasAddress = null;

if ($hasAddressInput !== '') {
    if (!in_array($hasAddressInput, ['0','1'], true)) {
        sendResponse(false, 'has_address must be 0 or 1.', null, 422);
    }

    $hasAddress = (int)$hasAddressInput;
}

$minTotal = validAmount($_GET['min_total'] ?? null, 'min_total');
$maxTotal = validAmount($_GET['max_total'] ?? null, 'max_total');

if (
    $minTotal !== null &&
    $maxTotal !== null &&
    $minTotal > $maxTotal
) {
    sendResponse(false, 'min_total cannot be greater than max_total.', null, 422);
}

$placedFrom = validDate($_GET['placed_from'] ?? null, 'placed_from');
$placedTo = validDate($_GET['placed_to'] ?? null, 'placed_to');
$createdFrom = validDate($_GET['created_from'] ?? null, 'created_from');
$createdTo = validDate($_GET['created_to'] ?? null, 'created_to');

if (
    $placedFrom !== null &&
    $placedTo !== null &&
    $placedFrom > $placedTo
) {
    sendResponse(false, 'placed_from cannot be greater than placed_to.', null, 422);
}

if (
    $createdFrom !== null &&
    $createdTo !== null &&
    $createdFrom > $createdTo
) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (
    filter_var($pageInput, FILTER_VALIDATE_INT) === false ||
    (int)$pageInput < 1
) {
    sendResponse(false, 'page must be a valid positive integer.', null, 422);
}

if (
    filter_var($limitInput, FILTER_VALIDATE_INT) === false ||
    (int)$limitInput < 1 ||
    (int)$limitInput > 100
) {
    sendResponse(false, 'limit must be between 1 and 100.', null, 422);
}

$page = (int)$pageInput;
$limit = (int)$limitInput;
$offset = ($page - 1) * $limit;

$sortColumns = [
    'id' => 'o.id',
    'order_number' => 'o.order_number',
    'subtotal' => 'o.subtotal',
    'product_discount_amount' => 'o.product_discount_amount',
    'coupon_discount_amount' => 'o.coupon_discount_amount',
    'tax_amount' => 'o.tax_amount',
    'grand_total' => 'o.grand_total',
    'payment_status' => 'o.payment_status',
    'order_status' => 'o.order_status',
    'placed_at' => 'o.placed_at',
    'created_at' => 'o.created_at',
    'updated_at' => 'o.updated_at'
];

$sortBy = trim((string)($_GET['sort_by'] ?? 'created_at'));
$sortOrder = strtolower(trim((string)($_GET['sort_order'] ?? 'desc')));

if (!array_key_exists($sortBy, $sortColumns)) {
    sendResponse(false, 'Invalid sort_by.', [
        'allowed_values' => array_keys($sortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc','desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$sortColumn = $sortColumns[$sortBy];
$sqlSortOrder = strtoupper($sortOrder);

try {
    $where = [];
    $params = [];

    if ($accountType === 'user') {
        $where[] = 'o.user_id=:authenticated_user_id';
        $params[':authenticated_user_id'] = $authenticatedId;
    } elseif ($userIdFilter !== null) {
        $where[] = 'o.user_id=:user_id';
        $params[':user_id'] = $userIdFilter;
    }

    if ($selectedOrderId !== null) {
        $where[] = 'o.id=:order_id';
        $params[':order_id'] = $selectedOrderId;
    }

    if ($orderNumber !== '') {
        $where[] = 'o.order_number=:order_number';
        $params[':order_number'] = $orderNumber;
    }

    if ($cartId !== null) {
        $where[] = 'o.cart_id=:cart_id';
        $params[':cart_id'] = $cartId;
    }

    if ($couponType !== '') {
        $where[] = 'o.coupon_type=:coupon_type';
        $params[':coupon_type'] = $couponType;
    }

    if ($couponId !== null) {
        $where[] = 'o.coupon_id=:coupon_id';
        $params[':coupon_id'] = $couponId;
    }

    if ($couponCode !== '') {
        $where[] = 'o.coupon_code=:coupon_code';
        $params[':coupon_code'] = $couponCode;
    }

    if ($paymentMethod !== '') {
        $where[] = 'o.payment_method=:payment_method';
        $params[':payment_method'] = $paymentMethod;
    }

    if ($paymentStatus !== '') {
        $where[] = 'o.payment_status=:payment_status';
        $params[':payment_status'] = $paymentStatus;
    }

    if ($orderStatus !== '') {
        $where[] = 'o.order_status=:order_status';
        $params[':order_status'] = $orderStatus;
    }

    if ($minTotal !== null) {
        $where[] = 'o.grand_total>=:min_total';
        $params[':min_total'] = number_format($minTotal, 2, '.', '');
    }

    if ($maxTotal !== null) {
        $where[] = 'o.grand_total<=:max_total';
        $params[':max_total'] = number_format($maxTotal, 2, '.', '');
    }

    if ($placedFrom !== null) {
        $where[] = 'o.placed_at>=:placed_from';
        $params[':placed_from'] = $placedFrom . ' 00:00:00';
    }

    if ($placedTo !== null) {
        $where[] = 'o.placed_at<=:placed_to';
        $params[':placed_to'] = $placedTo . ' 23:59:59';
    }

    if ($createdFrom !== null) {
        $where[] = 'o.created_at>=:created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== null) {
        $where[] = 'o.created_at<=:created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    if ($hasAddress === 1) {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_addresses oa_has
            WHERE oa_has.order_id=o.id
        )";
    }

    if ($hasAddress === 0) {
        $where[] = "NOT EXISTS(
            SELECT 1
            FROM order_addresses oa_has
            WHERE oa_has.order_id=o.id
        )";
    }

    if ($city !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_addresses oa_city
            WHERE oa_city.order_id=o.id
              AND oa_city.city=:city
        )";
        $params[':city'] = $city;
    }

    if ($district !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_addresses oa_district
            WHERE oa_district.order_id=o.id
              AND oa_district.district=:district
        )";
        $params[':district'] = $district;
    }

    if ($state !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_addresses oa_state
            WHERE oa_state.order_id=o.id
              AND oa_state.state=:state
        )";
        $params[':state'] = $state;
    }

    if ($pincode !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_addresses oa_pin
            WHERE oa_pin.order_id=o.id
              AND oa_pin.pincode=:pincode
        )";
        $params[':pincode'] = $pincode;
    }

    if ($productId !== null) {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_items oi_product
            WHERE oi_product.order_id=o.id
              AND oi_product.product_id=:product_id
        )";
        $params[':product_id'] = $productId;
    }

    if ($variantId !== null) {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_items oi_variant
            WHERE oi_variant.order_id=o.id
              AND oi_variant.variant_id=:variant_id
        )";
        $params[':variant_id'] = $variantId;
    }

    if ($sku !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_items oi_sku
            WHERE oi_sku.order_id=o.id
              AND oi_sku.sku=:sku
        )";
        $params[':sku'] = $sku;
    }

    if ($hsnCode !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_items oi_hsn
            WHERE oi_hsn.order_id=o.id
              AND oi_hsn.hsn_code=:hsn_code
        )";
        $params[':hsn_code'] = $hsnCode;
    }

    if ($itemStatus !== '') {
        $where[] = "EXISTS(
            SELECT 1
            FROM order_items oi_status
            WHERE oi_status.order_id=o.id
              AND oi_status.item_status=:item_status
        )";
        $params[':item_status'] = $itemStatus;
    }

    if ($q !== '') {
        $where[] = "(
            CONCAT_WS(' ',
                o.id,
                o.order_number,
                o.user_id,
                o.cart_id,
                o.coupon_type,
                o.coupon_id,
                o.coupon_code,
                o.payment_method,
                o.payment_status,
                o.order_status,
                o.customer_note,
                o.cancel_reason,
                u.name,
                u.mobile,
                u.email,
                bc.coupon_code,
                bc.title,
                fc.festival_name,
                fc.coupon_code,
                fc.title,
                rc.referral_code,
                rc.title,
                foc.coupon_code,
                foc.title
            ) LIKE :q_main

            OR EXISTS(
                SELECT 1
                FROM order_addresses oa_q
                WHERE oa_q.order_id=o.id
                  AND CONCAT_WS(' ',
                    oa_q.address_type,
                    oa_q.door_no,
                    oa_q.street,
                    oa_q.area,
                    oa_q.city,
                    oa_q.district,
                    oa_q.state,
                    oa_q.pincode,
                    oa_q.landmark
                  ) LIKE :q_address
            )

            OR EXISTS(
                SELECT 1
                FROM order_items oi_q
                WHERE oi_q.order_id=o.id
                  AND CONCAT_WS(' ',
                    oi_q.product_name,
                    oi_q.variant_name,
                    oi_q.sku,
                    oi_q.hsn_code,
                    oi_q.size_name,
                    oi_q.color_name,
                    oi_q.item_status
                  ) LIKE :q_item
            )
        )";

        $params[':q_main'] = '%' . $q . '%';
        $params[':q_address'] = '%' . $q . '%';
        $params[':q_item'] = '%' . $q . '%';
    }

    $whereSql = $where
        ? ' WHERE ' . implode(' AND ', $where)
        : '';

    $baseFrom = "
        FROM orders o
        INNER JOIN users u ON u.id=o.user_id
        LEFT JOIN birthday_coupons bc
            ON o.coupon_type='birthday'
           AND bc.id=o.coupon_id
        LEFT JOIN festival_coupons fc
            ON o.coupon_type='festival'
           AND fc.id=o.coupon_id
        LEFT JOIN referral_coupons rc
            ON o.coupon_type='referral'
           AND rc.id=o.coupon_id
        LEFT JOIN first_order_coupons foc
            ON o.coupon_type='first_order'
           AND foc.id=o.coupon_id
    ";

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         {$baseFrom}
         {$whereSql}"
    );

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    if (
        $totalPages > 0 &&
        $page > $totalPages
    ) {
        sendResponse(false, 'Requested page exceeds total available pages.', [
            'requested_page' => $page,
            'total_pages' => $totalPages
        ], 422);
    }

    $summaryStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(o.subtotal),0) AS subtotal,
            COALESCE(SUM(o.product_discount_amount),0) AS product_discount_amount,
            COALESCE(SUM(o.coupon_discount_amount),0) AS coupon_discount_amount,
            COALESCE(SUM(o.shipping_charge),0) AS shipping_charge,
            COALESCE(SUM(o.cod_charge),0) AS cod_charge,
            COALESCE(SUM(o.tax_amount),0) AS tax_amount,
            COALESCE(SUM(o.grand_total),0) AS grand_total
         {$baseFrom}
         {$whereSql}"
    );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        "SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.cart_id,
            o.subtotal,
            o.product_discount_amount,
            o.coupon_type,
            o.coupon_id,
            o.coupon_code,
            o.coupon_discount_amount,
            o.shipping_charge,
            o.cod_charge,
            o.tax_amount,
            o.grand_total,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.customer_note,
            o.cancel_reason,
            o.placed_at,
            o.confirmed_at,
            o.delivered_at,
            o.cancelled_at,
            o.created_at,
            o.updated_at,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email,
            u.date_of_birth AS user_date_of_birth,
            u.status AS user_status,

            bc.coupon_code AS birthday_coupon_code,
            bc.title AS birthday_title,
            bc.description AS birthday_description,
            bc.discount_type AS birthday_discount_type,
            bc.discount_value AS birthday_discount_value,
            bc.min_order_amount AS birthday_min_order_amount,
            bc.max_discount_amount AS birthday_max_discount_amount,
            bc.valid_before_days,
            bc.valid_after_days,
            bc.usage_limit_per_birthday,
            bc.status AS birthday_coupon_status,

            fc.festival_name,
            fc.coupon_code AS festival_coupon_code,
            fc.title AS festival_title,
            fc.description AS festival_description,
            fc.discount_type AS festival_discount_type,
            fc.discount_value AS festival_discount_value,
            fc.min_order_amount AS festival_min_order_amount,
            fc.max_discount_amount AS festival_max_discount_amount,
            fc.usage_limit AS festival_usage_limit,
            fc.usage_limit_per_user AS festival_usage_limit_per_user,
            fc.start_at AS festival_start_at,
            fc.end_at AS festival_end_at,
            fc.status AS festival_coupon_status,

            rc.referral_code,
            rc.title AS referral_title,
            rc.description AS referral_description,
            rc.discount_type AS referral_discount_type,
            rc.discount_value AS referral_discount_value,
            rc.min_order_amount AS referral_min_order_amount,
            rc.max_discount_amount AS referral_max_discount_amount,
            rc.usage_limit AS referral_usage_limit,
            rc.usage_limit_per_user AS referral_usage_limit_per_user,
            rc.start_at AS referral_start_at,
            rc.end_at AS referral_end_at,
            rc.status AS referral_coupon_status,

            foc.coupon_code AS first_order_coupon_code,
            foc.title AS first_order_title,
            foc.description AS first_order_description,
            foc.discount_type AS first_order_discount_type,
            foc.discount_value AS first_order_discount_value,
            foc.min_order_amount AS first_order_min_order_amount,
            foc.max_discount_amount AS first_order_max_discount_amount,
            foc.start_at AS first_order_start_at,
            foc.end_at AS first_order_end_at,
            foc.status AS first_order_coupon_status

         {$baseFrom}
         {$whereSql}

         ORDER BY {$sortColumn} {$sqlSortOrder},o.id DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $orderRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$orderRows) {
        sendResponse(true, 'Orders retrieved successfully.', [
            'orders' => [],
            'summary' => [
                'total_orders' => 0,
                'subtotal' => '0.00',
                'product_discount_amount' => '0.00',
                'coupon_discount_amount' => '0.00',
                'shipping_charge' => '0.00',
                'cod_charge' => '0.00',
                'tax_amount' => '0.00',
                'grand_total' => '0.00'
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_records' => 0,
                'total_pages' => 0,
                'has_previous' => false,
                'has_next' => false
            ]
        ], 200);
    }

    $orderIds = array_map(
        static fn(array $row): int => (int)$row['id'],
        $orderRows
    );

    $orderPlaceholders = [];

    foreach ($orderIds as $index => $value) {
        $orderPlaceholders[] = ':order_id_' . $index;
    }

    $orderInSql = implode(',', $orderPlaceholders);

    /*
     * ORDER ADDRESSES
     */
    $addressStmt = $pdo->prepare(
        "SELECT
            id,
            order_id,
            user_address_id,
            address_type,
            door_no,
            street,
            area,
            city,
            district,
            state,
            pincode,
            landmark,
            created_at
         FROM order_addresses
         WHERE order_id IN ({$orderInSql})
         ORDER BY id ASC"
    );

    foreach ($orderIds as $index => $value) {
        $addressStmt->bindValue(
            ':order_id_' . $index,
            $value,
            PDO::PARAM_INT
        );
    }

    $addressStmt->execute();

    $addressMap = [];

    foreach ($addressStmt->fetchAll(PDO::FETCH_ASSOC) as $address) {
        $oid = (int)$address['order_id'];

        $addressMap[$oid] = [
            'id' => (int)$address['id'],
            'order_id' => $oid,
            'user_address_id' => $address['user_address_id'] !== null
                ? (int)$address['user_address_id']
                : null,
            'address_type' => $address['address_type'],
            'door_no' => $address['door_no'],
            'street' => $address['street'],
            'area' => $address['area'],
            'city' => $address['city'],
            'district' => $address['district'],
            'state' => $address['state'],
            'pincode' => $address['pincode'],
            'landmark' => $address['landmark'],
            'created_at' => $address['created_at']
        ];
    }

    /*
     * ORDER ITEMS + CURRENT PRODUCT/VARIANT DATA
     */
    $itemPlaceholders = [];

    foreach ($orderIds as $index => $value) {
        $itemPlaceholders[] = ':item_order_id_' . $index;
    }

    $itemInSql = implode(',', $itemPlaceholders);

    $itemStmt = $pdo->prepare(
        "SELECT
            oi.id,
            oi.order_id,
            oi.product_id,
            oi.variant_id,
            oi.product_name,
            oi.variant_name,
            oi.sku,
            oi.hsn_code,
            oi.size_name,
            oi.color_name,
            oi.original_price,
            oi.selling_price,
            oi.quantity,
            oi.product_discount_amount,
            oi.line_subtotal,
            oi.taxable_amount,
            oi.gst_rate,
            oi.cgst_rate,
            oi.cgst_amount,
            oi.sgst_rate,
            oi.sgst_amount,
            oi.igst_rate,
            oi.igst_amount,
            oi.tax_amount,
            oi.line_total,
            oi.item_status,
            oi.created_at,
            oi.updated_at,

            p.name AS current_product_name,
            p.slug AS current_product_slug,
            p.description AS current_product_description,
            p.category_id,
            p.hsn_profile_id,
            p.is_new_arrival,
            p.is_featured,
            p.is_best_seller,
            p.status AS current_product_status,

            c.name AS category_name,
            c.slug AS category_slug,
            c.description AS category_description,
            c.image AS category_image,
            c.status AS category_status,

            hp.name AS hsn_profile_name,
            hp.hsn_code AS current_hsn_code,
            hp.description AS hsn_description,
            hp.status AS hsn_status,

            pv.size_id,
            pv.color_id,
            pv.sku AS current_sku,
            pv.variant_name AS current_variant_name,
            pv.original_price AS current_original_price,
            pv.discount_type AS current_discount_type,
            pv.discount_value AS current_discount_value,
            pv.selling_price AS current_selling_price,
            pv.gst_rate AS current_gst_rate,
            pv.gst_amount AS current_gst_amount,
            pv.price_with_tax AS current_price_with_tax,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,
            pv.created_at AS variant_created_at,
            pv.updated_at AS variant_updated_at,

            s.name AS current_size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,

            co.name AS current_color_name,
            co.hex_code,
            co.status AS color_status

         FROM order_items oi
         LEFT JOIN products p ON p.id=oi.product_id
         LEFT JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         LEFT JOIN product_variants pv
            ON pv.id=oi.variant_id
           AND (
                oi.product_id IS NULL
                OR pv.product_id=oi.product_id
           )
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors co ON co.id=pv.color_id
         WHERE oi.order_id IN ({$itemInSql})
         ORDER BY oi.order_id ASC,oi.id ASC"
    );

    foreach ($orderIds as $index => $value) {
        $itemStmt->bindValue(
            ':item_order_id_' . $index,
            $value,
            PDO::PARAM_INT
        );
    }

    $itemStmt->execute();

    $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $variantIds = [];

    foreach ($itemRows as $item) {
        if ($item['variant_id'] !== null) {
            $variantIds[(int)$item['variant_id']] = true;
        }
    }

    /*
     * VARIANT IMAGES
     */
    $imageMap = [];

    if ($variantIds) {
        $variantIds = array_keys($variantIds);
        $imagePlaceholders = [];

        foreach ($variantIds as $index => $value) {
            $imagePlaceholders[] = ':variant_image_id_' . $index;
        }

        $imageInSql = implode(',', $imagePlaceholders);

        $imageStatusSql = $accountType === 'user'
            ? " AND status='active'"
            : '';

        $imageStmt = $pdo->prepare(
            "SELECT
                id,
                variant_id,
                image,
                alt_text,
                is_primary,
                sort_order,
                status,
                created_at,
                updated_at
             FROM product_variant_images
             WHERE variant_id IN ({$imageInSql})
             {$imageStatusSql}
             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach ($variantIds as $index => $value) {
            $imageStmt->bindValue(
                ':variant_image_id_' . $index,
                $value,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
            $vid = (int)$image['variant_id'];

            $imageMap[$vid][] = [
                'id' => (int)$image['id'],
                'variant_id' => $vid,
                'image' => $image['image'],
                'alt_text' => $image['alt_text'],
                'is_primary' => (int)$image['is_primary'],
                'sort_order' => (int)$image['sort_order'],
                'status' => $image['status'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at']
            ];
        }
    }

    /*
     * FORMAT ORDER ITEMS
     */
    $itemsMap = [];

    foreach ($itemRows as $item) {
        $oid = (int)$item['order_id'];

        $stockQuantity = $item['stock_quantity'] !== null
            ? (int)$item['stock_quantity']
            : null;

        $reservedQuantity = $item['reserved_quantity'] !== null
            ? (int)$item['reserved_quantity']
            : null;

        $availableQuantity = null;
        $stockStatus = null;

        if (
            $stockQuantity !== null &&
            $reservedQuantity !== null
        ) {
            $availableQuantity = max(
                0,
                $stockQuantity - $reservedQuantity
            );

            if ($availableQuantity <= 0) {
                $stockStatus = 'out_of_stock';
            } elseif (
                $availableQuantity <=
                (int)$item['low_stock_limit']
            ) {
                $stockStatus = 'low_stock';
            } else {
                $stockStatus = 'in_stock';
            }
        }

        $variantIdValue = $item['variant_id'] !== null
            ? (int)$item['variant_id']
            : null;

        $images = $variantIdValue !== null
            ? ($imageMap[$variantIdValue] ?? [])
            : [];

        $primaryImage = null;

        foreach ($images as $image) {
            if ($image['is_primary'] === 1) {
                $primaryImage = $image;
                break;
            }
        }

        if ($primaryImage === null && $images) {
            $primaryImage = $images[0];
        }

        $itemsMap[$oid][] = [
            'id' => (int)$item['id'],
            'order_id' => $oid,

            /*
             * Historical snapshot.
             * Invoice/order history should use these values.
             */
            'snapshot' => [
                'product_id' => $item['product_id'] !== null
                    ? (int)$item['product_id']
                    : null,
                'variant_id' => $variantIdValue,
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'],
                'sku' => $item['sku'],
                'hsn_code' => $item['hsn_code'],
                'size_name' => $item['size_name'],
                'color_name' => $item['color_name'],

                'pricing' => [
                    'original_price' => $item['original_price'],
                    'selling_price' => $item['selling_price'],
                    'quantity' => (int)$item['quantity'],
                    'product_discount_amount' =>
                        $item['product_discount_amount'],
                    'line_subtotal' => $item['line_subtotal'],
                    'taxable_amount' => $item['taxable_amount'],
                    'line_total' => $item['line_total']
                ],

                'tax' => [
                    'gst_rate' => $item['gst_rate'],
                    'cgst_rate' => $item['cgst_rate'],
                    'cgst_amount' => $item['cgst_amount'],
                    'sgst_rate' => $item['sgst_rate'],
                    'sgst_amount' => $item['sgst_amount'],
                    'igst_rate' => $item['igst_rate'],
                    'igst_amount' => $item['igst_amount'],
                    'tax_amount' => $item['tax_amount']
                ],

                'item_status' => $item['item_status']
            ],

            /*
             * Current product data.
             * Product/variant later change ஆனாலும் snapshot மேலே safe.
             */
            'current_product' => $item['product_id'] !== null ? [
                'id' => (int)$item['product_id'],
                'name' => $item['current_product_name'],
                'slug' => $item['current_product_slug'],
                'description' =>
                    $item['current_product_description'],
                'category_id' => $item['category_id'] !== null
                    ? (int)$item['category_id']
                    : null,
                'hsn_profile_id' => $item['hsn_profile_id'] !== null
                    ? (int)$item['hsn_profile_id']
                    : null,
                'is_new_arrival' =>
                    $item['is_new_arrival'] !== null
                        ? (int)$item['is_new_arrival']
                        : null,
                'is_featured' =>
                    $item['is_featured'] !== null
                        ? (int)$item['is_featured']
                        : null,
                'is_best_seller' =>
                    $item['is_best_seller'] !== null
                        ? (int)$item['is_best_seller']
                        : null,
                'status' =>
                    $item['current_product_status']
            ] : null,

            'category' => $item['category_id'] !== null ? [
                'id' => (int)$item['category_id'],
                'name' => $item['category_name'],
                'slug' => $item['category_slug'],
                'description' =>
                    $item['category_description'],
                'image' => $item['category_image'],
                'status' => $item['category_status']
            ] : null,

            'hsn_profile' => $item['hsn_profile_id'] !== null ? [
                'id' => (int)$item['hsn_profile_id'],
                'name' => $item['hsn_profile_name'],
                'hsn_code' => $item['current_hsn_code'],
                'description' => $item['hsn_description'],
                'status' => $item['hsn_status']
            ] : null,

            'current_variant' => $variantIdValue !== null ? [
                'id' => $variantIdValue,
                'sku' => $item['current_sku'],
                'variant_name' =>
                    $item['current_variant_name'],

                'size' => $item['size_id'] !== null ? [
                    'id' => (int)$item['size_id'],
                    'name' => $item['current_size_name'],
                    'sort_order' =>
                        (int)$item['size_sort_order'],
                    'status' => $item['size_status']
                ] : null,

                'color' => $item['color_id'] !== null ? [
                    'id' => (int)$item['color_id'],
                    'name' => $item['current_color_name'],
                    'hex_code' => $item['hex_code'],
                    'status' => $item['color_status']
                ] : null,

                'pricing' => [
                    'original_price' =>
                        $item['current_original_price'],
                    'discount_type' =>
                        $item['current_discount_type'],
                    'discount_value' =>
                        $item['current_discount_value'],
                    'gst_rate' =>
                        $item['current_gst_rate'],
                    'gst_amount' =>
                        $item['current_gst_amount'],
                    'price_with_tax' =>
                        $item['current_price_with_tax'],
                    'selling_price' =>
                        $item['current_selling_price']
                ],

                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' =>
                        $reservedQuantity,
                    'available_quantity' =>
                        $availableQuantity,
                    'low_stock_limit' =>
                        $item['low_stock_limit'] !== null
                            ? (int)$item['low_stock_limit']
                            : null,
                    'stock_status' => $stockStatus
                ],

                'is_available' =>
                    $item['is_available'] !== null
                        ? (int)$item['is_available']
                        : null,

                'primary_image' => $primaryImage,
                'images' => $images,

                'created_at' =>
                    $item['variant_created_at'],
                'updated_at' =>
                    $item['variant_updated_at']
            ] : null,

            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at']
        ];
    }

    /*
     * FINAL ORDER RESPONSE
     */
    $orders = [];

    foreach ($orderRows as $row) {
        $oid = (int)$row['id'];

        $couponDetails = null;

        if ($row['coupon_type'] === 'birthday') {
            $couponDetails = [
                'type' => 'birthday',
                'id' => $row['coupon_id'] !== null
                    ? (int)$row['coupon_id']
                    : null,
                'order_snapshot_code' =>
                    $row['coupon_code'],
                'current_definition' =>
                    $row['birthday_coupon_code'] !== null ? [
                        'coupon_code' =>
                            $row['birthday_coupon_code'],
                        'title' =>
                            $row['birthday_title'],
                        'description' =>
                            $row['birthday_description'],
                        'discount_type' =>
                            $row['birthday_discount_type'],
                        'discount_value' =>
                            $row['birthday_discount_value'],
                        'min_order_amount' =>
                            $row['birthday_min_order_amount'],
                        'max_discount_amount' =>
                            $row['birthday_max_discount_amount'],
                        'valid_before_days' =>
                            (int)$row['valid_before_days'],
                        'valid_after_days' =>
                            (int)$row['valid_after_days'],
                        'usage_limit_per_birthday' =>
                            (int)$row['usage_limit_per_birthday'],
                        'status' =>
                            $row['birthday_coupon_status']
                    ] : null
            ];

        } elseif ($row['coupon_type'] === 'festival') {
            $couponDetails = [
                'type' => 'festival',
                'id' => $row['coupon_id'] !== null
                    ? (int)$row['coupon_id']
                    : null,
                'order_snapshot_code' =>
                    $row['coupon_code'],
                'current_definition' =>
                    $row['festival_coupon_code'] !== null ? [
                        'festival_name' =>
                            $row['festival_name'],
                        'coupon_code' =>
                            $row['festival_coupon_code'],
                        'title' =>
                            $row['festival_title'],
                        'description' =>
                            $row['festival_description'],
                        'discount_type' =>
                            $row['festival_discount_type'],
                        'discount_value' =>
                            $row['festival_discount_value'],
                        'min_order_amount' =>
                            $row['festival_min_order_amount'],
                        'max_discount_amount' =>
                            $row['festival_max_discount_amount'],
                        'usage_limit' =>
                            $row['festival_usage_limit'] !== null
                                ? (int)$row['festival_usage_limit']
                                : null,
                        'usage_limit_per_user' =>
                            $row['festival_usage_limit_per_user'] !== null
                                ? (int)$row['festival_usage_limit_per_user']
                                : null,
                        'start_at' =>
                            $row['festival_start_at'],
                        'end_at' =>
                            $row['festival_end_at'],
                        'status' =>
                            $row['festival_coupon_status']
                    ] : null
            ];

        } elseif ($row['coupon_type'] === 'referral') {
            $couponDetails = [
                'type' => 'referral',
                'id' => $row['coupon_id'] !== null
                    ? (int)$row['coupon_id']
                    : null,
                'order_snapshot_code' =>
                    $row['coupon_code'],
                'current_definition' =>
                    $row['referral_code'] !== null ? [
                        'referral_code' =>
                            $row['referral_code'],
                        'title' =>
                            $row['referral_title'],
                        'description' =>
                            $row['referral_description'],
                        'discount_type' =>
                            $row['referral_discount_type'],
                        'discount_value' =>
                            $row['referral_discount_value'],
                        'min_order_amount' =>
                            $row['referral_min_order_amount'],
                        'max_discount_amount' =>
                            $row['referral_max_discount_amount'],
                        'usage_limit' =>
                            $row['referral_usage_limit'] !== null
                                ? (int)$row['referral_usage_limit']
                                : null,
                        'usage_limit_per_user' =>
                            $row['referral_usage_limit_per_user'] !== null
                                ? (int)$row['referral_usage_limit_per_user']
                                : null,
                        'start_at' =>
                            $row['referral_start_at'],
                        'end_at' =>
                            $row['referral_end_at'],
                        'status' =>
                            $row['referral_coupon_status']
                    ] : null
            ];

        } elseif ($row['coupon_type'] === 'first_order') {
            $couponDetails = [
                'type' => 'first_order',
                'id' => $row['coupon_id'] !== null
                    ? (int)$row['coupon_id']
                    : null,
                'order_snapshot_code' =>
                    $row['coupon_code'],
                'current_definition' =>
                    $row['first_order_coupon_code'] !== null ? [
                        'coupon_code' =>
                            $row['first_order_coupon_code'],
                        'title' =>
                            $row['first_order_title'],
                        'description' =>
                            $row['first_order_description'],
                        'discount_type' =>
                            $row['first_order_discount_type'],
                        'discount_value' =>
                            $row['first_order_discount_value'],
                        'min_order_amount' =>
                            $row['first_order_min_order_amount'],
                        'max_discount_amount' =>
                            $row['first_order_max_discount_amount'],
                        'start_at' =>
                            $row['first_order_start_at'],
                        'end_at' =>
                            $row['first_order_end_at'],
                        'status' =>
                            $row['first_order_coupon_status']
                    ] : null
            ];
        }

        $orderItems = $itemsMap[$oid] ?? [];

        $totalQuantity = 0;

        foreach ($orderItems as $orderItem) {
            $totalQuantity +=
                (int)$orderItem['snapshot']['pricing']['quantity'];
        }

        $orders[] = [
            'id' => $oid,
            'order_number' => $row['order_number'],
            'user_id' => (int)$row['user_id'],
            'cart_id' => $row['cart_id'] !== null
                ? (int)$row['cart_id']
                : null,

            'user' => [
                'id' => (int)$row['user_id'],
                'name' => $row['user_name'],
                'mobile' => $row['user_mobile'],
                'email' => $row['user_email'],
                'date_of_birth' =>
                    $row['user_date_of_birth'],
                'status' =>
                    $row['user_status']
            ],

            'amounts' => [
                'subtotal' =>
                    $row['subtotal'],
                'product_discount_amount' =>
                    $row['product_discount_amount'],
                'coupon_discount_amount' =>
                    $row['coupon_discount_amount'],
                'shipping_charge' =>
                    $row['shipping_charge'],
                'cod_charge' =>
                    $row['cod_charge'],
                'tax_amount' =>
                    $row['tax_amount'],
                'grand_total' =>
                    $row['grand_total']
            ],

            'coupon' => $couponDetails,

            'payment' => [
                'method' =>
                    $row['payment_method'],
                'status' =>
                    $row['payment_status']
            ],

            'order_status' =>
                $row['order_status'],

            'customer_note' =>
                $row['customer_note'],

            'cancel_reason' =>
                $row['cancel_reason'],

            'address' =>
                $addressMap[$oid] ?? null,

            'items' => $orderItems,

            'items_summary' => [
                'total_items' =>
                    count($orderItems),
                'total_quantity' =>
                    $totalQuantity
            ],

            'timeline' => [
                'placed_at' =>
                    $row['placed_at'],
                'confirmed_at' =>
                    $row['confirmed_at'],
                'delivered_at' =>
                    $row['delivered_at'],
                'cancelled_at' =>
                    $row['cancelled_at'],
                'created_at' =>
                    $row['created_at'],
                'updated_at' =>
                    $row['updated_at']
            ]
        ];
    }

    sendResponse(true, 'Orders retrieved successfully.', [
        'orders' => $orders,

        'summary' => [
            'total_orders' =>
                (int)$summary['total_orders'],
            'subtotal' =>
                number_format((float)$summary['subtotal'], 2, '.', ''),
            'product_discount_amount' =>
                number_format(
                    (float)$summary['product_discount_amount'],
                    2,
                    '.',
                    ''
                ),
            'coupon_discount_amount' =>
                number_format(
                    (float)$summary['coupon_discount_amount'],
                    2,
                    '.',
                    ''
                ),
            'shipping_charge' =>
                number_format(
                    (float)$summary['shipping_charge'],
                    2,
                    '.',
                    ''
                ),
            'cod_charge' =>
                number_format(
                    (float)$summary['cod_charge'],
                    2,
                    '.',
                    ''
                ),
            'tax_amount' =>
                number_format(
                    (float)$summary['tax_amount'],
                    2,
                    '.',
                    ''
                ),
            'grand_total' =>
                number_format(
                    (float)$summary['grand_total'],
                    2,
                    '.',
                    ''
                )
        ],

        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ],

        'filters' => [
            'q' => $q !== '' ? $q : null,
            'order_id' => $selectedOrderId,
            'order_number' =>
                $orderNumber !== '' ? $orderNumber : null,
            'user_id' => $accountType === 'user'
                ? $authenticatedId
                : $userIdFilter,
            'cart_id' => $cartId,
            'coupon_type' =>
                $couponType !== '' ? $couponType : null,
            'coupon_id' => $couponId,
            'coupon_code' =>
                $couponCode !== '' ? $couponCode : null,
            'payment_method' =>
                $paymentMethod !== '' ? $paymentMethod : null,
            'payment_status' =>
                $paymentStatus !== '' ? $paymentStatus : null,
            'order_status' =>
                $orderStatus !== '' ? $orderStatus : null,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'sku' => $sku !== '' ? $sku : null,
            'hsn_code' =>
                $hsnCode !== '' ? $hsnCode : null,
            'item_status' =>
                $itemStatus !== '' ? $itemStatus : null,
            'city' =>
                $city !== '' ? $city : null,
            'district' =>
                $district !== '' ? $district : null,
            'state' =>
                $state !== '' ? $state : null,
            'pincode' =>
                $pincode !== '' ? $pincode : null,
            'has_address' => $hasAddress,
            'min_total' => $minTotal,
            'max_total' => $maxTotal,
            'placed_from' => $placedFrom,
            'placed_to' => $placedTo,
            'created_from' => $createdFrom,
            'created_to' => $createdTo
        ],

        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ],

        'access' => [
            'account_type' => $accountType,
            'scope' => $accountType === 'admin'
                ? 'all_orders'
                : 'own_orders_only'
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve orders.',
        defined('APP_ENV') &&
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
} catch (Throwable $e) {
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