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

function orderAddressPositiveId(string $value, string $field): ?int
{
    if ($value === '') return null;

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }

    return (int)$value;
}

function orderAddressDate(string $value, string $field): void
{
    if ($value === '') return;

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        ($errors !== false && (
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0
        )) ||
        $date->format('Y-m-d') !== $value
    ) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

$allowedParams = [
    'q',
    'id',
    'order_id',
    'user_id',
    'user_address_id',
    'address_type',
    'city',
    'district',
    'state',
    'pincode',
    'order_status',
    'payment_status',
    'payment_method',
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
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
}

$id = orderAddressPositiveId(
    trim((string)($_GET['id'] ?? '')),
    'id'
);

$orderId = orderAddressPositiveId(
    trim((string)($_GET['order_id'] ?? '')),
    'order_id'
);

$userId = orderAddressPositiveId(
    trim((string)($_GET['user_id'] ?? '')),
    'user_id'
);

$userAddressId = orderAddressPositiveId(
    trim((string)($_GET['user_address_id'] ?? '')),
    'user_address_id'
);

if (
    $accountType === 'user' &&
    $userId !== null &&
    $userId !== $authenticatedId
) {
    sendResponse(false, 'You can only view your own order addresses.', null, 403);
}

$addressType = strtolower(trim((string)($_GET['address_type'] ?? '')));

if (
    $addressType !== '' &&
    !in_array($addressType, ['home','work','other'], true)
) {
    sendResponse(false, 'Invalid address_type.', [
        'allowed_values' => ['home','work','other']
    ], 422);
}

$city = trim((string)($_GET['city'] ?? ''));
$district = trim((string)($_GET['district'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
$pincode = trim((string)($_GET['pincode'] ?? ''));

foreach ([
    'city' => $city,
    'district' => $district,
    'state' => $state
] as $field => $value) {
    if ($value !== '' && mb_strlen($value) > 100) {
        sendResponse(false, "{$field} must not exceed 100 characters.", null, 422);
    }
}

if (
    $pincode !== '' &&
    (
        mb_strlen($pincode) > 10 ||
        !preg_match('/^[A-Za-z0-9\-\s]+$/', $pincode)
    )
) {
    sendResponse(false, 'Invalid pincode.', null, 422);
}

$orderStatus = strtolower(trim((string)($_GET['order_status'] ?? '')));

$allowedOrderStatuses = [
    'pending',
    'confirmed',
    'processing',
    'packed',
    'shipped',
    'delivered',
    'cancelled'
];

if (
    $orderStatus !== '' &&
    !in_array($orderStatus, $allowedOrderStatuses, true)
) {
    sendResponse(false, 'Invalid order_status.', [
        'allowed_values' => $allowedOrderStatuses
    ], 422);
}

$paymentStatus = strtolower(trim((string)($_GET['payment_status'] ?? '')));

$allowedPaymentStatuses = [
    'pending',
    'initiated',
    'paid',
    'failed'
];

if (
    $paymentStatus !== '' &&
    !in_array($paymentStatus, $allowedPaymentStatuses, true)
) {
    sendResponse(false, 'Invalid payment_status.', [
        'allowed_values' => $allowedPaymentStatuses
    ], 422);
}

$paymentMethod = strtolower(trim((string)($_GET['payment_method'] ?? '')));

$allowedPaymentMethods = [
    'cod',
    'razorpay',
    'upi',
    'card',
    'netbanking'
];

if (
    $paymentMethod !== '' &&
    !in_array($paymentMethod, $allowedPaymentMethods, true)
) {
    sendResponse(false, 'Invalid payment_method.', [
        'allowed_values' => $allowedPaymentMethods
    ], 422);
}

$createdFrom = trim((string)($_GET['created_from'] ?? ''));
$createdTo = trim((string)($_GET['created_to'] ?? ''));

orderAddressDate($createdFrom, 'created_from');
orderAddressDate($createdTo, 'created_to');

if (
    $createdFrom !== '' &&
    $createdTo !== '' &&
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

$allowedSortColumns = [
    'id' => 'oa.id',
    'order_id' => 'oa.order_id',
    'user_id' => 'o.user_id',
    'user_address_id' => 'oa.user_address_id',
    'address_type' => 'oa.address_type',
    'city' => 'oa.city',
    'district' => 'oa.district',
    'state' => 'oa.state',
    'pincode' => 'oa.pincode',
    'order_number' => 'o.order_number',
    'grand_total' => 'o.grand_total',
    'order_status' => 'o.order_status',
    'payment_status' => 'o.payment_status',
    'created_at' => 'oa.created_at'
];

$sortBy = trim((string)($_GET['sort_by'] ?? 'created_at'));
$sortOrder = strtolower(trim((string)($_GET['sort_order'] ?? 'desc')));

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_values' => array_keys($allowedSortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc','desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$sortColumn = $allowedSortColumns[$sortBy];
$sqlSortOrder = strtoupper($sortOrder);

try {
    $where = [];
    $params = [];

    if ($accountType === 'user') {
        $where[] = 'o.user_id=:authenticated_user_id';
        $params[':authenticated_user_id'] = $authenticatedId;
    }

    if ($id !== null) {
        $where[] = 'oa.id=:id';
        $params[':id'] = $id;
    }

    if ($orderId !== null) {
        $where[] = 'oa.order_id=:order_id';
        $params[':order_id'] = $orderId;
    }

    if ($accountType === 'admin' && $userId !== null) {
        $where[] = 'o.user_id=:user_id';
        $params[':user_id'] = $userId;
    }

    if ($userAddressId !== null) {
        $where[] = 'oa.user_address_id=:user_address_id';
        $params[':user_address_id'] = $userAddressId;
    }

    if ($addressType !== '') {
        $where[] = 'oa.address_type=:address_type';
        $params[':address_type'] = $addressType;
    }

    if ($city !== '') {
        $where[] = 'oa.city=:city';
        $params[':city'] = $city;
    }

    if ($district !== '') {
        $where[] = 'oa.district=:district';
        $params[':district'] = $district;
    }

    if ($state !== '') {
        $where[] = 'oa.state=:state';
        $params[':state'] = $state;
    }

    if ($pincode !== '') {
        $where[] = 'oa.pincode=:pincode';
        $params[':pincode'] = $pincode;
    }

    if ($orderStatus !== '') {
        $where[] = 'o.order_status=:order_status';
        $params[':order_status'] = $orderStatus;
    }

    if ($paymentStatus !== '') {
        $where[] = 'o.payment_status=:payment_status';
        $params[':payment_status'] = $paymentStatus;
    }

    if ($paymentMethod !== '') {
        $where[] = 'o.payment_method=:payment_method';
        $params[':payment_method'] = $paymentMethod;
    }

    if ($createdFrom !== '') {
        $where[] = 'oa.created_at>=:created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'oa.created_at<=:created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    if ($q !== '') {
        $where[] = "CONCAT_WS(' ',
            oa.id,
            oa.order_id,
            oa.user_address_id,
            oa.address_type,
            oa.door_no,
            oa.street,
            oa.area,
            oa.city,
            oa.district,
            oa.state,
            oa.pincode,
            oa.landmark,
            o.id,
            o.order_number,
            o.user_id,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.grand_total
        ) LIKE :q";

        $params[':q'] = '%' . $q . '%';
    }

    $whereSql = $where
        ? ' WHERE ' . implode(' AND ', $where)
        : '';

    $baseFrom = "
        FROM order_addresses oa
        INNER JOIN orders o ON o.id=oa.order_id
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

    if ($totalPages > 0 && $page > $totalPages) {
        sendResponse(false, 'Requested page exceeds total available pages.', [
            'requested_page' => $page,
            'total_pages' => $totalPages
        ], 422);
    }

    $stmt = $pdo->prepare(
        "SELECT
            oa.id,
            oa.order_id,
            oa.user_address_id,
            oa.address_type,
            oa.door_no,
            oa.street,
            oa.area,
            oa.city,
            oa.district,
            oa.state,
            oa.pincode,
            oa.landmark,
            oa.created_at,

            o.order_number,
            o.user_id,
            o.cart_id,
            o.subtotal,
            o.product_discount_amount,
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
            o.created_at AS order_created_at,
            o.updated_at AS order_updated_at

         {$baseFrom}
         {$whereSql}

         ORDER BY {$sortColumn} {$sqlSortOrder},oa.id DESC
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $addresses = [];

    foreach ($rows as $row) {
        $addresses[] = [
            'id' => (int)$row['id'],
            'order_id' => (int)$row['order_id'],
            'user_address_id' => $row['user_address_id'] !== null
                ? (int)$row['user_address_id']
                : null,

            'address' => [
                'address_type' => $row['address_type'],
                'door_no' => $row['door_no'],
                'street' => $row['street'],
                'area' => $row['area'],
                'city' => $row['city'],
                'district' => $row['district'],
                'state' => $row['state'],
                'pincode' => $row['pincode'],
                'landmark' => $row['landmark']
            ],

            'order' => [
                'id' => (int)$row['order_id'],
                'order_number' => $row['order_number'],
                'user_id' => (int)$row['user_id'],
                'cart_id' => $row['cart_id'] !== null
                    ? (int)$row['cart_id']
                    : null,

                'amounts' => [
                    'subtotal' => $row['subtotal'],
                    'product_discount_amount' => $row['product_discount_amount'],
                    'coupon_discount_amount' => $row['coupon_discount_amount'],
                    'shipping_charge' => $row['shipping_charge'],
                    'cod_charge' => $row['cod_charge'],
                    'tax_amount' => $row['tax_amount'],
                    'grand_total' => $row['grand_total']
                ],

                'payment_method' => $row['payment_method'],
                'payment_status' => $row['payment_status'],
                'order_status' => $row['order_status'],
                'customer_note' => $row['customer_note'],
                'cancel_reason' => $row['cancel_reason'],
                'placed_at' => $row['placed_at'],
                'confirmed_at' => $row['confirmed_at'],
                'delivered_at' => $row['delivered_at'],
                'cancelled_at' => $row['cancelled_at'],
                'created_at' => $row['order_created_at'],
                'updated_at' => $row['order_updated_at']
            ],

            'created_at' => $row['created_at']
        ];
    }

    sendResponse(true, 'Order addresses retrieved successfully.', [
        'order_addresses' => $addresses,

        'summary' => [
            'total_order_addresses' => $totalRecords,
            'current_page_count' => count($addresses)
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
            'id' => $id,
            'order_id' => $orderId,
            'user_id' => $accountType === 'admin'
                ? $userId
                : $authenticatedId,
            'user_address_id' => $userAddressId,
            'address_type' => $addressType !== '' ? $addressType : null,
            'city' => $city !== '' ? $city : null,
            'district' => $district !== '' ? $district : null,
            'state' => $state !== '' ? $state : null,
            'pincode' => $pincode !== '' ? $pincode : null,
            'order_status' => $orderStatus !== '' ? $orderStatus : null,
            'payment_status' => $paymentStatus !== '' ? $paymentStatus : null,
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
            'created_from' => $createdFrom !== '' ? $createdFrom : null,
            'created_to' => $createdTo !== '' ? $createdTo : null
        ],

        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ],

        'access' => [
            'account_type' => $accountType,
            'scope' => $accountType === 'admin'
                ? 'all_order_addresses'
                : 'own_order_addresses_only'
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve order addresses.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
} catch (Throwable $e) {
    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}