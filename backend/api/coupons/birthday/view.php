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

$idInput = isset($_GET['id']) ? trim((string)$_GET['id']) : '';

if ($idInput === '') {
    sendResponse(false, 'Birthday coupon id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'Birthday coupon id must be a valid positive integer.', null, 422);
}

$birthdayCouponId = (int)$idInput;

try {
    $adminStmt = $pdo->prepare("
        SELECT id,name,role,status
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
        sendResponse(false, 'Only admin can view birthday coupon details.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $stmt = $pdo->prepare("
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
            a.role AS created_by_admin_role,
            a.status AS created_by_admin_status,
            bc.created_at,
            bc.updated_at
        FROM birthday_coupons bc
        LEFT JOIN admins a ON a.id=bc.created_by_admin_id
        WHERE bc.id=:id
        LIMIT 1
    ");

    $stmt->bindValue(':id', $birthdayCouponId, PDO::PARAM_INT);
    $stmt->execute();

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        sendResponse(false, 'Birthday coupon not found.', null, 404);
    }

    $usageSummaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_usage,
            COUNT(DISTINCT user_id) AS total_users,
            COUNT(DISTINCT order_id) AS total_orders,
            COALESCE(SUM(discount_amount),0) AS total_discount_given,
            COALESCE(SUM(order_amount_before_discount),0) AS total_order_amount_before_discount,
            COALESCE(SUM(final_order_amount),0) AS total_final_order_amount,
            MIN(used_at) AS first_used_at,
            MAX(used_at) AS last_used_at
        FROM birthday_coupon_usage
        WHERE birthday_coupon_id=:birthday_coupon_id
    ");

    $usageSummaryStmt->bindValue(
        ':birthday_coupon_id',
        $birthdayCouponId,
        PDO::PARAM_INT
    );

    $usageSummaryStmt->execute();

    $usageSummary = $usageSummaryStmt->fetch(PDO::FETCH_ASSOC);

    $currentYearStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS current_year_usage,
            COUNT(DISTINCT user_id) AS current_year_users,
            COALESCE(SUM(discount_amount),0) AS current_year_discount
        FROM birthday_coupon_usage
        WHERE birthday_coupon_id=:birthday_coupon_id
        AND YEAR(used_at)=YEAR(CURDATE())
    ");

    $currentYearStmt->bindValue(
        ':birthday_coupon_id',
        $birthdayCouponId,
        PDO::PARAM_INT
    );

    $currentYearStmt->execute();

    $currentYearUsage = $currentYearStmt->fetch(PDO::FETCH_ASSOC);

    $usageStmt = $pdo->prepare("
        SELECT
            id,
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
            final_order_amount,
            used_at,
            created_at
        FROM birthday_coupon_usage
        WHERE birthday_coupon_id=:birthday_coupon_id
        ORDER BY used_at DESC,id DESC
    ");

    $usageStmt->bindValue(
        ':birthday_coupon_id',
        $birthdayCouponId,
        PDO::PARAM_INT
    );

    $usageStmt->execute();

    $usageRows = $usageStmt->fetchAll(PDO::FETCH_ASSOC);
    $usageHistory = [];

    foreach ($usageRows as $usage) {
        $usageHistory[] = [
            'id' => (int)$usage['id'],
            'birthday_coupon_id' => (int)$usage['birthday_coupon_id'],
            'user_id' => (int)$usage['user_id'],
            'order_id' => (int)$usage['order_id'],
            'coupon_code' => $usage['coupon_code'],
            'birthday_month' => (int)$usage['birthday_month'],
            'birthday_day' => (int)$usage['birthday_day'],
            'birthday_date' => sprintf(
                '%02d-%02d',
                (int)$usage['birthday_month'],
                (int)$usage['birthday_day']
            ),
            'discount_type' => $usage['discount_type'],
            'discount_value' => (float)$usage['discount_value'],
            'order_amount_before_discount' => (float)$usage['order_amount_before_discount'],
            'discount_amount' => (float)$usage['discount_amount'],
            'final_order_amount' => (float)$usage['final_order_amount'],
            'used_at' => $usage['used_at'],
            'created_at' => $usage['created_at']
        ];
    }

    $formattedCoupon = [
        'id' => (int)$coupon['id'],
        'coupon_code' => $coupon['coupon_code'],
        'title' => $coupon['title'],
        'description' => $coupon['description'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'min_order_amount' => (float)$coupon['min_order_amount'],
        'max_discount_amount' => $coupon['max_discount_amount'] !== null
            ? (float)$coupon['max_discount_amount']
            : null,
        'valid_before_days' => (int)$coupon['valid_before_days'],
        'valid_after_days' => (int)$coupon['valid_after_days'],
        'usage_limit_per_birthday' => (int)$coupon['usage_limit_per_birthday'],
        'status' => $coupon['status'],
        'created_by' => [
            'admin_id' => $coupon['created_by_admin_id'] !== null
                ? (int)$coupon['created_by_admin_id']
                : null,
            'admin_name' => $coupon['created_by_admin_name'],
            'admin_role' => $coupon['created_by_admin_role'],
            'admin_status' => $coupon['created_by_admin_status']
        ],
        'usage_summary' => [
            'total_usage' => (int)$usageSummary['total_usage'],
            'total_users' => (int)$usageSummary['total_users'],
            'total_orders' => (int)$usageSummary['total_orders'],
            'total_discount_given' => (float)$usageSummary['total_discount_given'],
            'total_order_amount_before_discount' => (float)$usageSummary['total_order_amount_before_discount'],
            'total_final_order_amount' => (float)$usageSummary['total_final_order_amount'],
            'first_used_at' => $usageSummary['first_used_at'],
            'last_used_at' => $usageSummary['last_used_at'],
            'current_year_usage' => (int)$currentYearUsage['current_year_usage'],
            'current_year_users' => (int)$currentYearUsage['current_year_users'],
            'current_year_discount' => (float)$currentYearUsage['current_year_discount']
        ],
        'usage_history' => $usageHistory,
        'created_at' => $coupon['created_at'],
        'updated_at' => $coupon['updated_at']
    ];

    sendResponse(
        true,
        'Birthday coupon details retrieved successfully.',
        [
            'birthday_coupon' => $formattedCoupon
        ],
        200
    );

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve birthday coupon details.',
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