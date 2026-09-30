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
    sendResponse(false, 'Festival coupon id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'Festival coupon id must be a valid positive integer.', null, 422);
}

$festivalCouponId = (int)$idInput;

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
        sendResponse(false, 'Only admin can view festival coupon details.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $stmt = $pdo->prepare("
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
            a.status AS created_by_admin_status,
            fc.created_at,
            fc.updated_at
        FROM festival_coupons fc
        LEFT JOIN admins a ON a.id=fc.created_by_admin_id
        WHERE fc.id=:id
        LIMIT 1
    ");

    $stmt->bindValue(':id', $festivalCouponId, PDO::PARAM_INT);
    $stmt->execute();

    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        sendResponse(false, 'Festival coupon not found.', null, 404);
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
        FROM festival_coupon_usage
        WHERE festival_coupon_id=:festival_coupon_id
    ");

    $usageSummaryStmt->bindValue(
        ':festival_coupon_id',
        $festivalCouponId,
        PDO::PARAM_INT
    );

    $usageSummaryStmt->execute();

    $usageSummary = $usageSummaryStmt->fetch(PDO::FETCH_ASSOC);

    $usageStmt = $pdo->prepare("
        SELECT
            id,
            festival_coupon_id,
            user_id,
            order_id,
            coupon_code,
            discount_type,
            discount_value,
            order_amount_before_discount,
            discount_amount,
            final_order_amount,
            used_at,
            created_at
        FROM festival_coupon_usage
        WHERE festival_coupon_id=:festival_coupon_id
        ORDER BY used_at DESC,id DESC
    ");

    $usageStmt->bindValue(
        ':festival_coupon_id',
        $festivalCouponId,
        PDO::PARAM_INT
    );

    $usageStmt->execute();

    $usageRows = $usageStmt->fetchAll(PDO::FETCH_ASSOC);
    $usageHistory = [];

    foreach ($usageRows as $usage) {
        $usageHistory[] = [
            'id' => (int)$usage['id'],
            'festival_coupon_id' => (int)$usage['festival_coupon_id'],
            'user_id' => (int)$usage['user_id'],
            'order_id' => (int)$usage['order_id'],
            'coupon_code' => $usage['coupon_code'],
            'discount_type' => $usage['discount_type'],
            'discount_value' => (float)$usage['discount_value'],
            'order_amount_before_discount' => (float)$usage['order_amount_before_discount'],
            'discount_amount' => (float)$usage['discount_amount'],
            'final_order_amount' => (float)$usage['final_order_amount'],
            'used_at' => $usage['used_at'],
            'created_at' => $usage['created_at']
        ];
    }

    $totalUsage = (int)$usageSummary['total_usage'];

    $remainingUsage = $coupon['usage_limit'] !== null
        ? max(0, (int)$coupon['usage_limit'] - $totalUsage)
        : null;

    $now = time();
    $startTimestamp = strtotime($coupon['start_at']);
    $endTimestamp = strtotime($coupon['end_at']);

    if ($coupon['status'] === 'inactive') {
        $availability = 'inactive';
    } elseif ($coupon['status'] === 'expired') {
        $availability = 'expired';
    } elseif ($now < $startTimestamp) {
        $availability = 'upcoming';
    } elseif ($now > $endTimestamp) {
        $availability = 'expired';
    } elseif (
        $coupon['usage_limit'] !== null &&
        $totalUsage >= (int)$coupon['usage_limit']
    ) {
        $availability = 'usage_limit_reached';
    } else {
        $availability = 'available';
    }

    $isCurrentlyValid = $availability === 'available';

    $formattedCoupon = [
        'id' => (int)$coupon['id'],
        'festival_name' => $coupon['festival_name'],
        'coupon_code' => $coupon['coupon_code'],
        'title' => $coupon['title'],
        'description' => $coupon['description'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'min_order_amount' => (float)$coupon['min_order_amount'],
        'max_discount_amount' => $coupon['max_discount_amount'] !== null
            ? (float)$coupon['max_discount_amount']
            : null,
        'usage_limit' => $coupon['usage_limit'] !== null
            ? (int)$coupon['usage_limit']
            : null,
        'usage_limit_per_user' => (int)$coupon['usage_limit_per_user'],
        'start_at' => $coupon['start_at'],
        'end_at' => $coupon['end_at'],
        'status' => $coupon['status'],
        'availability' => $availability,
        'is_currently_valid' => $isCurrentlyValid,
        'created_by' => [
            'admin_id' => $coupon['created_by_admin_id'] !== null
                ? (int)$coupon['created_by_admin_id']
                : null,
            'admin_name' => $coupon['created_by_admin_name'],
            'admin_role' => $coupon['created_by_admin_role'],
            'admin_status' => $coupon['created_by_admin_status']
        ],
        'usage_summary' => [
            'total_usage' => $totalUsage,
            'usage_limit' => $coupon['usage_limit'] !== null
                ? (int)$coupon['usage_limit']
                : null,
            'remaining_usage' => $remainingUsage,
            'usage_limit_per_user' => (int)$coupon['usage_limit_per_user'],
            'total_users' => (int)$usageSummary['total_users'],
            'total_orders' => (int)$usageSummary['total_orders'],
            'total_discount_given' => (float)$usageSummary['total_discount_given'],
            'total_order_amount_before_discount' => (float)$usageSummary['total_order_amount_before_discount'],
            'total_final_order_amount' => (float)$usageSummary['total_final_order_amount'],
            'first_used_at' => $usageSummary['first_used_at'],
            'last_used_at' => $usageSummary['last_used_at']
        ],
        'usage_history' => $usageHistory,
        'created_at' => $coupon['created_at'],
        'updated_at' => $coupon['updated_at']
    ];

    sendResponse(
        true,
        'Festival coupon details retrieved successfully.',
        [
            'festival_coupon' => $formattedCoupon
        ],
        200
    );

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve festival coupon details.',
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