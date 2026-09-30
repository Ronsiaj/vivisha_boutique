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
    'q','festival_name','discount_type',
    'page','limit','sort_by','sort_order'
];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$festivalName = trim((string)($_GET['festival_name'] ?? ''));
$discountType = strtolower(trim((string)($_GET['discount_type'] ?? '')));

if ($q !== '' && mb_strlen($q) > 150) {
    sendResponse(false, 'q must not exceed 150 characters.', null, 422);
}

if ($festivalName !== '' && mb_strlen($festivalName) > 100) {
    sendResponse(false, 'festival_name must not exceed 100 characters.', null, 422);
}

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
    'id' => 'fc.id',
    'festival_name' => 'fc.festival_name',
    'coupon_code' => 'fc.coupon_code',
    'title' => 'fc.title',
    'discount_value' => 'fc.discount_value',
    'min_order_amount' => 'fc.min_order_amount',
    'start_at' => 'fc.start_at',
    'end_at' => 'fc.end_at',
    'created_at' => 'fc.created_at'
];

$sortBy = trim((string)($_GET['sort_by'] ?? 'start_at'));
$sortOrder = strtolower(trim((string)($_GET['sort_order'] ?? 'asc')));

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
    $where = [
        "fc.status='active'",
        "fc.start_at<=NOW()",
        "fc.end_at>=NOW()",
        "(fc.usage_limit IS NULL OR COALESCE(tu.total_used,0)<fc.usage_limit)",
        "COALESCE(uu.user_used,0)<fc.usage_limit_per_user"
    ];

    $params = [
        ':usage_user_id' => $userId
    ];

    if ($festivalName !== '') {
        $where[] = 'fc.festival_name=:festival_name';
        $params[':festival_name'] = $festivalName;
    }

    if ($discountType !== '') {
        $where[] = 'fc.discount_type=:discount_type';
        $params[':discount_type'] = $discountType;
    }

    if ($q !== '') {
        $where[] = "CONCAT_WS(' ',
            fc.festival_name,
            fc.coupon_code,
            fc.title,
            fc.description,
            fc.discount_type,
            fc.discount_value,
            fc.min_order_amount,
            fc.max_discount_amount
        ) LIKE :q";

        $params[':q'] = '%' . $q . '%';
    }

    $usageJoin = "
        LEFT JOIN (
            SELECT festival_coupon_id,COUNT(*) AS total_used
            FROM festival_coupon_usage
            GROUP BY festival_coupon_id
        ) tu ON tu.festival_coupon_id=fc.id
        LEFT JOIN (
            SELECT festival_coupon_id,COUNT(*) AS user_used
            FROM festival_coupon_usage
            WHERE user_id=:usage_user_id
            GROUP BY festival_coupon_id
        ) uu ON uu.festival_coupon_id=fc.id
    ";

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM festival_coupons fc
         {$usageJoin}
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
            fc.id,
            fc.festival_name,
            fc.coupon_code,
            fc.title,
            fc.description,
            fc.discount_type,
            fc.discount_value,
            fc.min_order_amount,
            fc.max_discount_amount,
            fc.usage_limit,
            fc.usage_limit_per_user,
            fc.start_at,
            fc.end_at,
            fc.status,
            fc.created_at,
            fc.updated_at,
            COALESCE(tu.total_used,0) AS total_used,
            COALESCE(uu.user_used,0) AS user_used
         FROM festival_coupons fc
         {$usageJoin}
         {$whereSql}
         ORDER BY {$sortColumn} {$sqlSortOrder},fc.id DESC
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
    $coupons = [];

    foreach ($rows as $coupon) {
        $totalUsed = (int)$coupon['total_used'];
        $userUsed = (int)$coupon['user_used'];

        $globalRemaining = $coupon['usage_limit'] !== null
            ? max(0, (int)$coupon['usage_limit'] - $totalUsed)
            : null;

        $userRemaining = max(
            0,
            (int)$coupon['usage_limit_per_user'] - $userUsed
        );

        $coupons[] = [
            'id' => (int)$coupon['id'],
            'coupon_type' => 'festival',
            'festival_name' => $coupon['festival_name'],
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
                'end_at' => $coupon['end_at'],
                'is_currently_active' => true
            ],
            'usage' => [
                'global_limit' => $coupon['usage_limit'] !== null
                    ? (int)$coupon['usage_limit']
                    : null,
                'global_used' => $totalUsed,
                'global_remaining' => $globalRemaining,
                'limit_per_user' => (int)$coupon['usage_limit_per_user'],
                'user_used' => $userUsed,
                'user_remaining' => $userRemaining
            ],
            'status' => $coupon['status'],
            'created_at' => $coupon['created_at'],
            'updated_at' => $coupon['updated_at']
        ];
    }

    sendResponse(true, 'Festival coupons retrieved successfully.', [
        'eligible' => count($coupons) > 0,
        'reason' => count($coupons) > 0
            ? 'ACTIVE_FESTIVAL_COUPONS_AVAILABLE'
            : 'NO_ACTIVE_FESTIVAL_COUPONS',
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
            'festival_name' => $festivalName !== ''
                ? $festivalName
                : null,
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
        'Unable to retrieve festival coupons.',
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