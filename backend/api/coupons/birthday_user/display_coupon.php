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

$discountType = strtolower(
    trim((string)($_GET['discount_type'] ?? ''))
);

$allowedDiscountTypes = [
    'percentage',
    'flat',
    'free_shipping'
];

if (
    $discountType !== '' &&
    !in_array($discountType, $allowedDiscountTypes, true)
) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_values' => $allowedDiscountTypes
    ], 422);
}

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (
    filter_var($pageInput, FILTER_VALIDATE_INT) === false ||
    (int)$pageInput < 1
) {
    sendResponse(
        false,
        'page must be a valid positive integer.',
        null,
        422
    );
}

if (
    filter_var($limitInput, FILTER_VALIDATE_INT) === false ||
    (int)$limitInput < 1 ||
    (int)$limitInput > 100
) {
    sendResponse(
        false,
        'limit must be between 1 and 100.',
        null,
        422
    );
}

$page = (int)$pageInput;
$limit = (int)$limitInput;
$offset = ($page - 1) * $limit;

$allowedSortColumns = [
    'id' => 'bc.id',
    'coupon_code' => 'bc.coupon_code',
    'title' => 'bc.title',
    'discount_value' => 'bc.discount_value',
    'min_order_amount' => 'bc.min_order_amount',
    'max_discount_amount' => 'bc.max_discount_amount',
    'created_at' => 'bc.created_at'
];

$sortBy = trim(
    (string)($_GET['sort_by'] ?? 'created_at')
);

$sortOrder = strtolower(
    trim((string)($_GET['sort_order'] ?? 'desc'))
);

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    sendResponse(false, 'Invalid sort_by.', [
        'allowed_values' => array_keys($allowedSortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc','desc'], true)) {
    sendResponse(
        false,
        'sort_order must be asc or desc.',
        null,
        422
    );
}

$sortColumn = $allowedSortColumns[$sortBy];
$sqlSortOrder = strtoupper($sortOrder);

date_default_timezone_set('Asia/Kolkata');

$today = new DateTimeImmutable('today');
$currentMonth = (int)$today->format('n');
$currentYear = (int)$today->format('Y');

try {
    $userStmt = $pdo->prepare(
        "SELECT id,date_of_birth,status
         FROM users
         WHERE id=:user_id
         LIMIT 1"
    );

    $userStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $userStmt->execute();

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse(
            false,
            'User not found.',
            null,
            404
        );
    }

    if ($user['status'] !== 'active') {
        sendResponse(
            false,
            'User account is not active.',
            null,
            403
        );
    }

    if (
        $user['date_of_birth'] === null ||
        trim((string)$user['date_of_birth']) === ''
    ) {
        sendResponse(true, 'Birthday coupon is not available.', [
            'eligible' => false,
            'reason' => 'DATE_OF_BIRTH_NOT_AVAILABLE',
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

    $dobValue = trim(
        (string)$user['date_of_birth']
    );

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
        sendResponse(
            false,
            'Invalid date_of_birth stored for user.',
            null,
            422
        );
    }

    $birthMonth = (int)$dob->format('n');
    $birthDay = (int)$dob->format('j');

    if ($birthMonth !== $currentMonth) {
        sendResponse(
            true,
            'Birthday coupons are available only during your birthday month.',
            [
                'eligible' => false,
                'reason' => 'NOT_BIRTHDAY_MONTH',
                'date_of_birth' => $dob->format('Y-m-d'),
                'birthday_month' => $birthMonth,
                'current_month' => $currentMonth,
                'current_year' => $currentYear,
                'coupons' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ],
            200
        );
    }

    /*
     * Birthday coupon = ONE TIME PER YEAR.
     *
     * Any birthday coupon already used by this user
     * during current year means no more birthday coupons
     * should be displayed this year.
     */
    $usageStmt = $pdo->prepare(
        "SELECT
            id,
            birthday_coupon_id,
            order_id,
            coupon_code,
            birthday_month,
            birthday_day,
            discount_type,
            discount_value,
            discount_amount,
            final_order_amount,
            used_at
         FROM birthday_coupon_usage
         WHERE user_id=:user_id
           AND YEAR(used_at)=:usage_year
         ORDER BY used_at DESC
         LIMIT 1"
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

    $currentYearUsage = $usageStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($currentYearUsage) {
        sendResponse(
            true,
            'Birthday coupon has already been used for this year.',
            [
                'eligible' => false,
                'reason' => 'BIRTHDAY_COUPON_ALREADY_USED_THIS_YEAR',
                'date_of_birth' => $dob->format('Y-m-d'),
                'birthday_month' => $birthMonth,
                'current_year' => $currentYear,
                'previous_usage' => [
                    'id' => (int)$currentYearUsage['id'],
                    'birthday_coupon_id' =>
                        (int)$currentYearUsage['birthday_coupon_id'],
                    'order_id' =>
                        (int)$currentYearUsage['order_id'],
                    'coupon_code' =>
                        $currentYearUsage['coupon_code'],
                    'discount_type' =>
                        $currentYearUsage['discount_type'],
                    'discount_value' =>
                        $currentYearUsage['discount_value'],
                    'discount_amount' =>
                        $currentYearUsage['discount_amount'],
                    'final_order_amount' =>
                        $currentYearUsage['final_order_amount'],
                    'used_at' =>
                        $currentYearUsage['used_at']
                ],
                'coupons' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false
                ]
            ],
            200
        );
    }

    $where = [
        "bc.status='active'"
    ];

    $params = [];

    if ($discountType !== '') {
        $where[] = 'bc.discount_type=:discount_type';
        $params[':discount_type'] = $discountType;
    }

    if ($q !== '') {
        $where[] = "CONCAT_WS(' ',
            bc.coupon_code,
            bc.title,
            bc.description,
            bc.discount_type,
            bc.discount_value,
            bc.min_order_amount,
            bc.max_discount_amount
        ) LIKE :q";

        $params[':q'] = '%' . $q . '%';
    }

    $whereSql = ' WHERE ' . implode(
        ' AND ',
        $where
    );

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM birthday_coupons bc
         {$whereSql}"
    );

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
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
        sendResponse(
            false,
            'Requested page exceeds total available pages.',
            [
                'requested_page' => $page,
                'total_pages' => $totalPages
            ],
            422
        );
    }

    $stmt = $pdo->prepare(
        "SELECT
            bc.id,
            bc.coupon_code,
            bc.title,
            bc.description,
            bc.discount_type,
            bc.discount_value,
            bc.min_order_amount,
            bc.max_discount_amount,
            bc.valid_before_days,
            bc.valid_after_days,
            bc.usage_limit_per_birthday,
            bc.status,
            bc.created_at,
            bc.updated_at
         FROM birthday_coupons bc
         {$whereSql}
         ORDER BY {$sortColumn} {$sqlSortOrder},bc.id DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $coupons = [];

    foreach ($rows as $coupon) {
        $coupons[] = [
            'id' => (int)$coupon['id'],
            'coupon_type' => 'birthday',
            'coupon_code' => $coupon['coupon_code'],
            'title' => $coupon['title'],
            'description' => $coupon['description'],
            'discount' => [
                'type' => $coupon['discount_type'],
                'value' => $coupon['discount_value'],
                'max_discount_amount' =>
                    $coupon['max_discount_amount']
            ],
            'min_order_amount' =>
                $coupon['min_order_amount'],
            'eligibility' => [
                'birthday_month' => $birthMonth,
                'valid_for_entire_birth_month' => true,
                'one_time_per_year' => true,
                'current_year' => $currentYear,
                'already_used_this_year' => false
            ],
            'status' => $coupon['status'],
            'created_at' => $coupon['created_at'],
            'updated_at' => $coupon['updated_at']
        ];
    }

    sendResponse(
        true,
        'Birthday coupons retrieved successfully.',
        [
            'eligible' => true,
            'reason' => 'BIRTHDAY_MONTH_ELIGIBLE',
            'date_of_birth' => $dob->format('Y-m-d'),
            'birthday' => [
                'month' => $birthMonth,
                'day' => $birthDay
            ],
            'current' => [
                'date' => $today->format('Y-m-d'),
                'month' => $currentMonth,
                'year' => $currentYear
            ],
            'coupon_rule' => [
                'valid_for_entire_birth_month' => true,
                'one_time_per_year' => true
            ],
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
                'q' => $q !== ''
                    ? $q
                    : null,
                'discount_type' => $discountType !== ''
                    ? $discountType
                    : null
            ],
            'sorting' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ]
        ],
        200
    );

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve birthday coupons.',
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