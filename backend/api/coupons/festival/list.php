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
$festivalName = isset($_GET['festival_name']) ? trim((string)$_GET['festival_name']) : '';
$minOrderFromInput = isset($_GET['min_order_amount_from']) ? trim((string)$_GET['min_order_amount_from']) : '';
$minOrderToInput = isset($_GET['min_order_amount_to']) ? trim((string)$_GET['min_order_amount_to']) : '';
$discountFromInput = isset($_GET['discount_value_from']) ? trim((string)$_GET['discount_value_from']) : '';
$discountToInput = isset($_GET['discount_value_to']) ? trim((string)$_GET['discount_value_to']) : '';
$startFrom = isset($_GET['start_from']) ? trim((string)$_GET['start_from']) : '';
$startTo = isset($_GET['start_to']) ? trim((string)$_GET['start_to']) : '';
$endFrom = isset($_GET['end_from']) ? trim((string)$_GET['end_from']) : '';
$endTo = isset($_GET['end_to']) ? trim((string)$_GET['end_to']) : '';
$createdFrom = isset($_GET['created_from']) ? trim((string)$_GET['created_from']) : '';
$createdTo = isset($_GET['created_to']) ? trim((string)$_GET['created_to']) : '';
$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';
$sortBy = isset($_GET['sort_by']) ? trim((string)$_GET['sort_by']) : 'created_at';
$sortOrder = isset($_GET['sort_order']) ? strtolower(trim((string)$_GET['sort_order'])) : 'desc';

if (filter_var($pageInput, FILTER_VALIDATE_INT) === false || (int)$pageInput < 1) {
    sendResponse(false, 'Page must be a valid positive integer.', null, 422);
}

$page = (int)$pageInput;

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

if ($festivalName !== '' && mb_strlen($festivalName) > 100) {
    sendResponse(false, 'festival_name must not exceed 100 characters.', null, 422);
}

$allowedStatuses = ['active', 'inactive', 'expired'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_values' => $allowedStatuses
    ], 422);
}

$allowedDiscountTypes = ['percentage', 'flat', 'free_shipping'];

if ($discountType !== '' && !in_array($discountType, $allowedDiscountTypes, true)) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_values' => $allowedDiscountTypes
    ], 422);
}

function validateFestivalListAmount(string $value, string $field): ?float
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

function validateFestivalListDate(string $value, string $field): void
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

$minOrderFrom = validateFestivalListAmount($minOrderFromInput, 'min_order_amount_from');
$minOrderTo = validateFestivalListAmount($minOrderToInput, 'min_order_amount_to');
$discountFrom = validateFestivalListAmount($discountFromInput, 'discount_value_from');
$discountTo = validateFestivalListAmount($discountToInput, 'discount_value_to');

if ($minOrderFrom !== null && $minOrderTo !== null && $minOrderFrom > $minOrderTo) {
    sendResponse(false, 'min_order_amount_from cannot be greater than min_order_amount_to.', null, 422);
}

if ($discountFrom !== null && $discountTo !== null && $discountFrom > $discountTo) {
    sendResponse(false, 'discount_value_from cannot be greater than discount_value_to.', null, 422);
}

validateFestivalListDate($startFrom, 'start_from');
validateFestivalListDate($startTo, 'start_to');
validateFestivalListDate($endFrom, 'end_from');
validateFestivalListDate($endTo, 'end_to');
validateFestivalListDate($createdFrom, 'created_from');
validateFestivalListDate($createdTo, 'created_to');

if ($startFrom !== '' && $startTo !== '' && $startFrom > $startTo) {
    sendResponse(false, 'start_from cannot be greater than start_to.', null, 422);
}

if ($endFrom !== '' && $endTo !== '' && $endFrom > $endTo) {
    sendResponse(false, 'end_from cannot be greater than end_to.', null, 422);
}

if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$allowedSortColumns = [
    'id',
    'festival_name',
    'coupon_code',
    'title',
    'discount_type',
    'discount_value',
    'min_order_amount',
    'max_discount_amount',
    'usage_limit',
    'usage_limit_per_user',
    'start_at',
    'end_at',
    'status',
    'created_at',
    'updated_at'
];

if (!in_array($sortBy, $allowedSortColumns, true)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_values' => $allowedSortColumns
    ], 422);
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
        sendResponse(false, 'Only admin can access festival coupons.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $where = ['1=1'];
    $params = [];

    if ($q !== '') {
        $where[] = "(
            CAST(fc.id AS CHAR) LIKE :q_id
            OR fc.festival_name LIKE :q_festival_name
            OR fc.coupon_code LIKE :q_coupon_code
            OR fc.title LIKE :q_title
            OR fc.description LIKE :q_description
            OR fc.discount_type LIKE :q_discount_type
            OR CAST(fc.discount_value AS CHAR) LIKE :q_discount_value
            OR CAST(fc.min_order_amount AS CHAR) LIKE :q_min_order
            OR CAST(fc.max_discount_amount AS CHAR) LIKE :q_max_discount
            OR CAST(fc.usage_limit AS CHAR) LIKE :q_usage_limit
            OR CAST(fc.usage_limit_per_user AS CHAR) LIKE :q_usage_per_user
            OR DATE_FORMAT(fc.start_at,'%Y-%m-%d %H:%i:%s') LIKE :q_start
            OR DATE_FORMAT(fc.end_at,'%Y-%m-%d %H:%i:%s') LIKE :q_end
            OR fc.status LIKE :q_status
            OR CAST(fc.created_by_admin_id AS CHAR) LIKE :q_admin_id
            OR a.name LIKE :q_admin_name
            OR DATE_FORMAT(fc.created_at,'%Y-%m-%d %H:%i:%s') LIKE :q_created
            OR DATE_FORMAT(fc.updated_at,'%Y-%m-%d %H:%i:%s') LIKE :q_updated
        )";

        $search = '%' . $q . '%';

        $params[':q_id'] = $search;
        $params[':q_festival_name'] = $search;
        $params[':q_coupon_code'] = $search;
        $params[':q_title'] = $search;
        $params[':q_description'] = $search;
        $params[':q_discount_type'] = $search;
        $params[':q_discount_value'] = $search;
        $params[':q_min_order'] = $search;
        $params[':q_max_discount'] = $search;
        $params[':q_usage_limit'] = $search;
        $params[':q_usage_per_user'] = $search;
        $params[':q_start'] = $search;
        $params[':q_end'] = $search;
        $params[':q_status'] = $search;
        $params[':q_admin_id'] = $search;
        $params[':q_admin_name'] = $search;
        $params[':q_created'] = $search;
        $params[':q_updated'] = $search;
    }

    if ($festivalName !== '') {
        $where[] = 'fc.festival_name = :festival_name';
        $params[':festival_name'] = $festivalName;
    }

    if ($status !== '') {
        $where[] = 'fc.status = :status';
        $params[':status'] = $status;
    }

    if ($discountType !== '') {
        $where[] = 'fc.discount_type = :discount_type';
        $params[':discount_type'] = $discountType;
    }

    if ($minOrderFrom !== null) {
        $where[] = 'fc.min_order_amount >= :min_order_from';
        $params[':min_order_from'] = number_format($minOrderFrom, 2, '.', '');
    }

    if ($minOrderTo !== null) {
        $where[] = 'fc.min_order_amount <= :min_order_to';
        $params[':min_order_to'] = number_format($minOrderTo, 2, '.', '');
    }

    if ($discountFrom !== null) {
        $where[] = 'fc.discount_value >= :discount_from';
        $params[':discount_from'] = number_format($discountFrom, 2, '.', '');
    }

    if ($discountTo !== null) {
        $where[] = 'fc.discount_value <= :discount_to';
        $params[':discount_to'] = number_format($discountTo, 2, '.', '');
    }

    if ($startFrom !== '') {
        $where[] = 'fc.start_at >= :start_from';
        $params[':start_from'] = $startFrom . ' 00:00:00';
    }

    if ($startTo !== '') {
        $where[] = 'fc.start_at <= :start_to';
        $params[':start_to'] = $startTo . ' 23:59:59';
    }

    if ($endFrom !== '') {
        $where[] = 'fc.end_at >= :end_from';
        $params[':end_from'] = $endFrom . ' 00:00:00';
    }

    if ($endTo !== '') {
        $where[] = 'fc.end_at <= :end_to';
        $params[':end_to'] = $endTo . ' 23:59:59';
    }

    if ($createdFrom !== '') {
        $where[] = 'fc.created_at >= :created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'fc.created_at <= :created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
        FROM festival_coupons fc
        LEFT JOIN admins a ON a.id=fc.created_by_admin_id
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
    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    if ($totalPages > 0 && $page > $totalPages) {
        sendResponse(false, 'Requested page exceeds total available pages.', [
            'requested_page' => $page,
            'total_pages' => $totalPages
        ], 422);
    }

    $sql = "
        SELECT
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
            fc.created_by_admin_id,
            a.name AS created_by_admin_name,
            a.role AS created_by_admin_role,
            fc.created_at,
            fc.updated_at
        FROM festival_coupons fc
        LEFT JOIN admins a ON a.id=fc.created_by_admin_id
        $whereSql
        ORDER BY fc.`$sortBy` $sortOrder,fc.id DESC
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
        $now = time();
        $startTimestamp = strtotime($row['start_at']);
        $endTimestamp = strtotime($row['end_at']);

        if ($row['status'] !== 'active') {
            $availability = $row['status'];
        } elseif ($now < $startTimestamp) {
            $availability = 'upcoming';
        } elseif ($now > $endTimestamp) {
            $availability = 'expired';
        } else {
            $availability = 'available';
        }

        $coupons[] = [
            'id' => (int)$row['id'],
            'festival_name' => $row['festival_name'],
            'coupon_code' => $row['coupon_code'],
            'title' => $row['title'],
            'description' => $row['description'],
            'discount_type' => $row['discount_type'],
            'discount_value' => (float)$row['discount_value'],
            'min_order_amount' => (float)$row['min_order_amount'],
            'max_discount_amount' => $row['max_discount_amount'] !== null
                ? (float)$row['max_discount_amount']
                : null,
            'usage_limit' => $row['usage_limit'] !== null
                ? (int)$row['usage_limit']
                : null,
            'usage_limit_per_user' => (int)$row['usage_limit_per_user'],
            'start_at' => $row['start_at'],
            'end_at' => $row['end_at'],
            'status' => $row['status'],
            'availability' => $availability,
            'created_by' => [
                'admin_id' => $row['created_by_admin_id'] !== null
                    ? (int)$row['created_by_admin_id']
                    : null,
                'admin_name' => $row['created_by_admin_name'],
                'admin_role' => $row['created_by_admin_role']
            ],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    sendResponse(true, 'Festival coupons retrieved successfully.', [
        'festival_coupons' => $coupons,
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
            'festival_name' => $festivalName !== '' ? $festivalName : null,
            'status' => $status !== '' ? $status : null,
            'discount_type' => $discountType !== '' ? $discountType : null,
            'min_order_amount_from' => $minOrderFrom,
            'min_order_amount_to' => $minOrderTo,
            'discount_value_from' => $discountFrom,
            'discount_value_to' => $discountTo,
            'start_from' => $startFrom !== '' ? $startFrom : null,
            'start_to' => $startTo !== '' ? $startTo : null,
            'end_from' => $endFrom !== '' ? $endFrom : null,
            'end_to' => $endTo !== '' ? $endTo : null,
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