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

$allowedParams = [
    'q',
    'discount_type',
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

if ($q !== '' && mb_strlen($q) > 150) {
    sendResponse(false, 'q must not exceed 150 characters.', null, 422);
}

$discountType = strtolower(trim((string)($_GET['discount_type'] ?? '')));

if (
    $discountType !== '' &&
    !in_array($discountType, ['percentage','flat','free_shipping'], true)
) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_values' => ['percentage','flat','free_shipping']
    ], 422);
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
    'id' => 'id',
    'coupon_code' => 'coupon_code',
    'title' => 'title',
    'discount_value' => 'discount_value',
    'min_order_amount' => 'min_order_amount',
    'start_at' => 'start_at',
    'end_at' => 'end_at',
    'created_at' => 'created_at'
];

$sortBy = trim((string)($_GET['sort_by'] ?? 'created_at'));
$sortOrder = strtolower(trim((string)($_GET['sort_order'] ?? 'desc')));

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    sendResponse(false, 'Invalid sort_by.', [
        'allowed_values' => array_keys($allowedSortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc','desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$sortColumn = $allowedSortColumns[$sortBy];
$sqlSortOrder = strtoupper($sortOrder);

try {
    $orderStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM orders
         WHERE user_id=:user_id"
    );

    $orderStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $orderStmt->execute();

    $orderCount = (int)$orderStmt->fetchColumn();

    if ($orderCount > 0) {
        sendResponse(true, 'User is not eligible for first order coupons.', [
            'eligible' => false,
            'reason' => 'USER_ALREADY_HAS_ORDER',
            'order_count' => $orderCount,
            'coupons' => [],
            'pagination' => [
                'page' => 1,
                'limit' => $limit,
                'total_records' => 0,
                'total_pages' => 0,
                'has_previous' => false,
                'has_next' => false
            ]
        ], 200);
    }

    $usageStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM first_order_coupon_usage
         WHERE user_id=:user_id"
    );

    $usageStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $usageStmt->execute();

    $usageCount = (int)$usageStmt->fetchColumn();

    if ($usageCount > 0) {
        sendResponse(true, 'User has already used a first order coupon.', [
            'eligible' => false,
            'reason' => 'FIRST_ORDER_COUPON_ALREADY_USED',
            'usage_count' => $usageCount,
            'coupons' => [],
            'pagination' => [
                'page' => 1,
                'limit' => $limit,
                'total_records' => 0,
                'total_pages' => 0,
                'has_previous' => false,
                'has_next' => false
            ]
        ], 200);
    }

    $where = [
        "status='active'",
        "(start_at IS NULL OR start_at<=NOW())",
        "(end_at IS NULL OR end_at>=NOW())"
    ];

    $params = [];

    if ($discountType !== '') {
        $where[] = 'discount_type=:discount_type';
        $params[':discount_type'] = $discountType;
    }

    if ($q !== '') {
        $where[] = "CONCAT_WS(' ',
            coupon_code,
            title,
            description,
            discount_type,
            discount_value,
            min_order_amount,
            max_discount_amount
        ) LIKE :q";

        $params[':q'] = '%' . $q . '%';
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM first_order_coupons
         {$whereSql}"
    );

    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
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
            id,
            coupon_code,
            title,
            description,
            discount_type,
            discount_value,
            min_order_amount,
            max_discount_amount,
            start_at,
            end_at,
            status,
            created_at,
            updated_at
         FROM first_order_coupons
         {$whereSql}
         ORDER BY {$sortColumn} {$sqlSortOrder},id DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $coupons = [];

    foreach ($rows as $coupon) {
        $coupons[] = [
            'id' => (int)$coupon['id'],
            'coupon_code' => $coupon['coupon_code'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'discount' => [
                'type' => $coupon['discount_type'],
                'value' => $coupon['discount_value'],
                'max_discount_amount' => $coupon['max_discount_amount']
            ],
            'min_order_amount' => $coupon['min_order_amount'],
            'validity' => [
                'start_at' => $coupon['start_at'],
                'end_at' => $coupon['end_at']
            ],
            'status' => $coupon['status'],
            'created_at' => $coupon['created_at'],
            'updated_at' => $coupon['updated_at']
        ];
    }

    sendResponse(true, 'First order coupons retrieved successfully.', [
        'eligible' => true,
        'reason' => 'FIRST_ORDER_ELIGIBLE',
        'user_id' => $userId,
        'order_count' => 0,
        'coupons' => $coupons,
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
            'discount_type' => $discountType !== ''
                ? $discountType
                : null
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve first order coupons.',
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