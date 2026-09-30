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

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);

$adminId = getAuthenticatedId($adminAuth);

if ($adminId <= 0) {
    sendResponse(false, 'Invalid authenticated admin.', null, 401);
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$discountType = isset($_GET['discount_type']) ? strtolower(trim((string)$_GET['discount_type'])) : '';
$validBeforeDaysInput = isset($_GET['valid_before_days']) ? trim((string)$_GET['valid_before_days']) : '';
$validAfterDaysInput = isset($_GET['valid_after_days']) ? trim((string)$_GET['valid_after_days']) : '';
$minOrderAmountFromInput = isset($_GET['min_order_amount_from']) ? trim((string)$_GET['min_order_amount_from']) : '';
$minOrderAmountToInput = isset($_GET['min_order_amount_to']) ? trim((string)$_GET['min_order_amount_to']) : '';
$discountValueFromInput = isset($_GET['discount_value_from']) ? trim((string)$_GET['discount_value_from']) : '';
$discountValueToInput = isset($_GET['discount_value_to']) ? trim((string)$_GET['discount_value_to']) : '';
$createdFrom = isset($_GET['created_from']) ? trim((string)$_GET['created_from']) : '';
$createdTo = isset($_GET['created_to']) ? trim((string)$_GET['created_to']) : '';
$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';
$sortBy = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'created_at';
$sortOrder = isset($_GET['sort_order']) ? strtolower(trim((string)$_GET['sort_order'])) : 'desc';

if (filter_var($pageInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Page must be a valid integer.', null, 422);
}

$page = (int)$pageInput;

if ($page < 1) {
    sendResponse(false, 'Page must be greater than 0.', null, 422);
}

if (filter_var($limitInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Limit must be a valid integer.', null, 422);
}

$limit = (int)$limitInput;

if ($limit < 1 || $limit > 100) {
    sendResponse(false, 'Limit must be between 1 and 100.', null, 422);
}

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
}

$allowedStatuses = ['active', 'inactive'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', ['allowed_values' => $allowedStatuses], 422);
}

$allowedDiscountTypes = ['percentage', 'flat', 'free_shipping'];

if ($discountType !== '' && !in_array($discountType, $allowedDiscountTypes, true)) {
    sendResponse(false, 'Invalid discount_type.', ['allowed_values' => $allowedDiscountTypes], 422);
}

$validBeforeDays = null;

if ($validBeforeDaysInput !== '') {
    if (!preg_match('/^\d+$/', $validBeforeDaysInput)) {
        sendResponse(false, 'valid_before_days must be a valid non-negative integer.', null, 422);
    }

    $validBeforeDays = (int)$validBeforeDaysInput;

    if ($validBeforeDays > 30) {
        sendResponse(false, 'valid_before_days must not exceed 30.', null, 422);
    }
}

$validAfterDays = null;

if ($validAfterDaysInput !== '') {
    if (!preg_match('/^\d+$/', $validAfterDaysInput)) {
        sendResponse(false, 'valid_after_days must be a valid non-negative integer.', null, 422);
    }

    $validAfterDays = (int)$validAfterDaysInput;

    if ($validAfterDays > 30) {
        sendResponse(false, 'valid_after_days must not exceed 30.', null, 422);
    }
}

function validateBirthdayCouponAmount(string $value, string $field): ?float
{
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        sendResponse(false, "{$field} must be a valid number.", null, 422);
    }

    $amount = round((float)$value, 2);

    if ($amount < 0 || $amount > 99999999.99) {
        sendResponse(false, "{$field} must be between 0 and 99999999.99.", null, 422);
    }

    return $amount;
}

$minOrderAmountFrom = validateBirthdayCouponAmount($minOrderAmountFromInput, 'min_order_amount_from');
$minOrderAmountTo = validateBirthdayCouponAmount($minOrderAmountToInput, 'min_order_amount_to');
$discountValueFrom = validateBirthdayCouponAmount($discountValueFromInput, 'discount_value_from');
$discountValueTo = validateBirthdayCouponAmount($discountValueToInput, 'discount_value_to');

if ($minOrderAmountFrom !== null && $minOrderAmountTo !== null && $minOrderAmountFrom > $minOrderAmountTo) {
    sendResponse(false, 'min_order_amount_from cannot be greater than min_order_amount_to.', null, 422);
}

if ($discountValueFrom !== null && $discountValueTo !== null && $discountValueFrom > $discountValueTo) {
    sendResponse(false, 'discount_value_from cannot be greater than discount_value_to.', null, 422);
}

function validateBirthdayCouponDate(string $value, string $field): void
{
    if ($value === '') {
        return;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    $hasErrors = $errors !== false && (
        $errors['warning_count'] > 0 ||
        $errors['error_count'] > 0
    );

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

validateBirthdayCouponDate($createdFrom, 'created_from');
validateBirthdayCouponDate($createdTo, 'created_to');

if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$allowedSortColumns = [
    'id',
    'coupon_code',
    'title',
    'discount_type',
    'discount_value',
    'min_order_amount',
    'max_discount_amount',
    'valid_before_days',
    'valid_after_days',
    'usage_limit_per_birthday',
    'status',
    'created_at',
    'updated_at'
];

if (!in_array($sortBy, $allowedSortColumns, true)) {
    sendResponse(false, 'Invalid sort_by value.', ['allowed_values' => $allowedSortColumns], 422);
}

if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$offset = ($page - 1) * $limit;

try {
    $adminStmt = $pdo->prepare("
        SELECT id,role,status
        FROM admins
        WHERE id=:admin_id
        LIMIT 1
    ");
    $adminStmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $adminStmt->execute();

    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        sendResponse(false, 'Authenticated admin not found.', null, 401);
    }

    if ($admin['role'] !== 'admin') {
        sendResponse(false, 'Only admin can access birthday coupons.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $where = ['1=1'];
    $params = [];

    if ($q !== '') {
        $where[] = "(
            CAST(bc.id AS CHAR) LIKE :q_id
            OR bc.coupon_code LIKE :q_code
            OR bc.title LIKE :q_title
            OR bc.description LIKE :q_description
            OR bc.discount_type LIKE :q_discount_type
            OR CAST(bc.discount_value AS CHAR) LIKE :q_discount_value
            OR CAST(bc.min_order_amount AS CHAR) LIKE :q_min_order
            OR CAST(bc.max_discount_amount AS CHAR) LIKE :q_max_discount
            OR CAST(bc.valid_before_days AS CHAR) LIKE :q_before_days
            OR CAST(bc.valid_after_days AS CHAR) LIKE :q_after_days
            OR CAST(bc.usage_limit_per_birthday AS CHAR) LIKE :q_usage_limit
            OR bc.status LIKE :q_status
            OR CAST(bc.created_by_admin_id AS CHAR) LIKE :q_admin_id
            OR a.name LIKE :q_admin_name
            OR DATE_FORMAT(bc.created_at,'%Y-%m-%d %H:%i:%s') LIKE :q_created
            OR DATE_FORMAT(bc.updated_at,'%Y-%m-%d %H:%i:%s') LIKE :q_updated
        )";

        $searchValue = '%' . $q . '%';

        $params[':q_id'] = $searchValue;
        $params[':q_code'] = $searchValue;
        $params[':q_title'] = $searchValue;
        $params[':q_description'] = $searchValue;
        $params[':q_discount_type'] = $searchValue;
        $params[':q_discount_value'] = $searchValue;
        $params[':q_min_order'] = $searchValue;
        $params[':q_max_discount'] = $searchValue;
        $params[':q_before_days'] = $searchValue;
        $params[':q_after_days'] = $searchValue;
        $params[':q_usage_limit'] = $searchValue;
        $params[':q_status'] = $searchValue;
        $params[':q_admin_id'] = $searchValue;
        $params[':q_admin_name'] = $searchValue;
        $params[':q_created'] = $searchValue;
        $params[':q_updated'] = $searchValue;
    }

    if ($status !== '') {
        $where[] = 'bc.status = :status';
        $params[':status'] = $status;
    }

    if ($discountType !== '') {
        $where[] = 'bc.discount_type = :discount_type';
        $params[':discount_type'] = $discountType;
    }

    if ($validBeforeDays !== null) {
        $where[] = 'bc.valid_before_days = :valid_before_days';
        $params[':valid_before_days'] = $validBeforeDays;
    }

    if ($validAfterDays !== null) {
        $where[] = 'bc.valid_after_days = :valid_after_days';
        $params[':valid_after_days'] = $validAfterDays;
    }

    if ($minOrderAmountFrom !== null) {
        $where[] = 'bc.min_order_amount >= :min_order_amount_from';
        $params[':min_order_amount_from'] = number_format($minOrderAmountFrom, 2, '.', '');
    }

    if ($minOrderAmountTo !== null) {
        $where[] = 'bc.min_order_amount <= :min_order_amount_to';
        $params[':min_order_amount_to'] = number_format($minOrderAmountTo, 2, '.', '');
    }

    if ($discountValueFrom !== null) {
        $where[] = 'bc.discount_value >= :discount_value_from';
        $params[':discount_value_from'] = number_format($discountValueFrom, 2, '.', '');
    }

    if ($discountValueTo !== null) {
        $where[] = 'bc.discount_value <= :discount_value_to';
        $params[':discount_value_to'] = number_format($discountValueTo, 2, '.', '');
    }

    if ($createdFrom !== '') {
        $where[] = 'bc.created_at >= :created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'bc.created_at <= :created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
        FROM birthday_coupons bc
        LEFT JOIN admins a ON a.id=bc.created_by_admin_id
        $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 0;

    if ($totalPages > 0 && $page > $totalPages) {
        sendResponse(false, 'Requested page exceeds total available pages.', [
            'requested_page' => $page,
            'total_pages' => $totalPages
        ], 422);
    }

    $sql = "
        SELECT
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
            bc.created_by_admin_id,
            a.name AS created_by_admin_name,
            bc.created_at,
            bc.updated_at
        FROM birthday_coupons bc
        LEFT JOIN admins a ON a.id=bc.created_by_admin_id
        $whereSql
        ORDER BY bc.`$sortBy` $sortOrder,bc.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

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

    foreach ($rows as $row) {
        $coupons[] = [
            'id' => (int)$row['id'],
            'coupon_code' => $row['coupon_code'],
            'title' => $row['title'],
            'description' => $row['description'],
            'discount_type' => $row['discount_type'],
            'discount_value' => (float)$row['discount_value'],
            'min_order_amount' => (float)$row['min_order_amount'],
            'max_discount_amount' => $row['max_discount_amount'] !== null ? (float)$row['max_discount_amount'] : null,
            'valid_before_days' => (int)$row['valid_before_days'],
            'valid_after_days' => (int)$row['valid_after_days'],
            'usage_limit_per_birthday' => (int)$row['usage_limit_per_birthday'],
            'status' => $row['status'],
            'created_by' => [
                'admin_id' => $row['created_by_admin_id'] !== null ? (int)$row['created_by_admin_id'] : null,
                'admin_name' => $row['created_by_admin_name']
            ],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    sendResponse(true, 'Birthday coupons retrieved successfully.', [
        'birthday_coupons' => $coupons,
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
            'status' => $status !== '' ? $status : null,
            'discount_type' => $discountType !== '' ? $discountType : null,
            'valid_before_days' => $validBeforeDays,
            'valid_after_days' => $validAfterDays,
            'min_order_amount_from' => $minOrderAmountFrom,
            'min_order_amount_to' => $minOrderAmountTo,
            'discount_value_from' => $discountValueFrom,
            'discount_value_to' => $discountValueTo,
            'created_from' => $createdFrom !== '' ? $createdFrom : null,
            'created_to' => $createdTo !== '' ? $createdTo : null
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve birthday coupons.',
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