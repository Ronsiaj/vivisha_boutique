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
    sendResponse(false, 'Referral coupon id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'Referral coupon id must be a valid positive integer.', null, 422);
}

$referralCouponId = (int)$idInput;

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
        sendResponse(false, 'Only admin can view referral coupon details.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $couponStmt = $pdo->prepare("
        SELECT
            rc.id,
            rc.referral_code,
            rc.title,
            rc.description,
            rc.discount_type,
            rc.discount_value,
            rc.min_order_amount,
            rc.max_discount_amount,
            rc.usage_limit,
            rc.usage_limit_per_user,
            rc.start_at,
            rc.end_at,
            rc.status,
            rc.created_by_admin_id,
            a.name AS created_by_admin_name,
            a.role AS created_by_admin_role,
            a.status AS created_by_admin_status,
            rc.created_at,
            rc.updated_at
        FROM referral_coupons rc
        LEFT JOIN admins a
            ON a.id=rc.created_by_admin_id
        WHERE rc.id=:id
        LIMIT 1
    ");

    $couponStmt->bindValue(':id', $referralCouponId, PDO::PARAM_INT);
    $couponStmt->execute();

    $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        sendResponse(false, 'Referral coupon not found.', null, 404);
    }

    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_usage,
            COUNT(DISTINCT user_id) AS total_users,
            COUNT(DISTINCT order_id) AS total_orders,
            COALESCE(SUM(discount_amount),0) AS total_discount_given,
            COALESCE(SUM(order_amount_before_discount),0) AS total_order_amount_before_discount,
            COALESCE(SUM(final_order_amount),0) AS total_final_order_amount,
            COALESCE(AVG(discount_amount),0) AS average_discount_amount,
            MIN(used_at) AS first_used_at,
            MAX(used_at) AS last_used_at
        FROM referral_coupon_usage
        WHERE referral_coupon_id=:referral_coupon_id
    ");

    $summaryStmt->bindValue(
        ':referral_coupon_id',
        $referralCouponId,
        PDO::PARAM_INT
    );

    $summaryStmt->execute();

    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $usageStmt = $pdo->prepare("
        SELECT
            rcu.id,
            rcu.referral_coupon_id,
            rcu.user_id,
            rcu.order_id,
            rcu.referral_code,
            rcu.discount_type,
            rcu.discount_value,
            rcu.order_amount_before_discount,
            rcu.discount_amount,
            rcu.final_order_amount,
            rcu.used_at,
            rcu.created_at,
            o.order_number,
            o.subtotal,
            o.product_discount_amount,
            o.coupon_discount_amount,
            o.shipping_charge,
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
            o.cancelled_at
        FROM referral_coupon_usage rcu
        LEFT JOIN orders o
            ON o.id=rcu.order_id
        WHERE rcu.referral_coupon_id=:referral_coupon_id
        ORDER BY rcu.used_at DESC,rcu.id DESC
    ");

    $usageStmt->bindValue(
        ':referral_coupon_id',
        $referralCouponId,
        PDO::PARAM_INT
    );

    $usageStmt->execute();

    $usageRows = $usageStmt->fetchAll(PDO::FETCH_ASSOC);
    $usageHistory = [];

    foreach ($usageRows as $usage) {
        $usageHistory[] = [
            'id' => (int)$usage['id'],
            'referral_coupon_id' => (int)$usage['referral_coupon_id'],
            'user_id' => (int)$usage['user_id'],
            'order_id' => (int)$usage['order_id'],
            'referral_code' => $usage['referral_code'],
            'discount_type' => $usage['discount_type'],
            'discount_value' => (float)$usage['discount_value'],
            'order_amount_before_discount' => (float)$usage['order_amount_before_discount'],
            'discount_amount' => (float)$usage['discount_amount'],
            'final_order_amount' => (float)$usage['final_order_amount'],
            'order' => [
                'order_number' => $usage['order_number'],
                'subtotal' => $usage['subtotal'] !== null
                    ? (float)$usage['subtotal']
                    : null,
                'product_discount_amount' => $usage['product_discount_amount'] !== null
                    ? (float)$usage['product_discount_amount']
                    : null,
                'coupon_discount_amount' => $usage['coupon_discount_amount'] !== null
                    ? (float)$usage['coupon_discount_amount']
                    : null,
                'shipping_charge' => $usage['shipping_charge'] !== null
                    ? (float)$usage['shipping_charge']
                    : null,
                'tax_amount' => $usage['tax_amount'] !== null
                    ? (float)$usage['tax_amount']
                    : null,
                'grand_total' => $usage['grand_total'] !== null
                    ? (float)$usage['grand_total']
                    : null,
                'payment_method' => $usage['payment_method'],
                'payment_status' => $usage['payment_status'],
                'order_status' => $usage['order_status'],
                'customer_note' => $usage['customer_note'],
                'cancel_reason' => $usage['cancel_reason'],
                'placed_at' => $usage['placed_at'],
                'confirmed_at' => $usage['confirmed_at'],
                'delivered_at' => $usage['delivered_at'],
                'cancelled_at' => $usage['cancelled_at']
            ],
            'used_at' => $usage['used_at'],
            'created_at' => $usage['created_at']
        ];
    }

    $totalUsage = (int)$summary['total_usage'];

    $usageLimit = $coupon['usage_limit'] !== null
        ? (int)$coupon['usage_limit']
        : null;

    $remainingUsage = $usageLimit !== null
        ? max(0, $usageLimit - $totalUsage)
        : null;

    $usagePercentage = null;

    if ($usageLimit !== null && $usageLimit > 0) {
        $usagePercentage = round(
            min(100, ($totalUsage / $usageLimit) * 100),
            2
        );
    }

    $now = time();

    $startTimestamp = $coupon['start_at'] !== null
        ? strtotime($coupon['start_at'])
        : null;

    $endTimestamp = $coupon['end_at'] !== null
        ? strtotime($coupon['end_at'])
        : null;

    if ($coupon['status'] === 'inactive') {
        $availability = 'inactive';
    } elseif ($coupon['status'] === 'expired') {
        $availability = 'expired';
    } elseif ($startTimestamp !== null && $now < $startTimestamp) {
        $availability = 'upcoming';
    } elseif ($endTimestamp !== null && $now > $endTimestamp) {
        $availability = 'expired';
    } elseif (
        $usageLimit !== null &&
        $totalUsage >= $usageLimit
    ) {
        $availability = 'usage_limit_reached';
    } else {
        $availability = 'available';
    }

    $couponData = [
        'id' => (int)$coupon['id'],
        'referral_code' => $coupon['referral_code'],
        'title' => $coupon['title'],
        'description' => $coupon['description'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'min_order_amount' => (float)$coupon['min_order_amount'],
        'max_discount_amount' => $coupon['max_discount_amount'] !== null
            ? (float)$coupon['max_discount_amount']
            : null,
        'usage_limit' => $usageLimit,
        'usage_limit_per_user' => (int)$coupon['usage_limit_per_user'],
        'start_at' => $coupon['start_at'],
        'end_at' => $coupon['end_at'],
        'status' => $coupon['status'],
        'availability' => $availability,
        'is_currently_valid' => $availability === 'available',
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
            'usage_limit' => $usageLimit,
            'remaining_usage' => $remainingUsage,
            'usage_percentage' => $usagePercentage,
            'usage_limit_per_user' => (int)$coupon['usage_limit_per_user'],
            'total_users' => (int)$summary['total_users'],
            'total_orders' => (int)$summary['total_orders'],
            'total_discount_given' => (float)$summary['total_discount_given'],
            'total_order_amount_before_discount' => (float)$summary['total_order_amount_before_discount'],
            'total_final_order_amount' => (float)$summary['total_final_order_amount'],
            'average_discount_amount' => (float)$summary['average_discount_amount'],
            'first_used_at' => $summary['first_used_at'],
            'last_used_at' => $summary['last_used_at']
        ],
        'usage_history' => $usageHistory,
        'created_at' => $coupon['created_at'],
        'updated_at' => $coupon['updated_at']
    ];

    sendResponse(
        true,
        'Referral coupon details retrieved successfully.',
        [
            'referral_coupon' => $couponData
        ],
        200
    );

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve referral coupon details.',
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