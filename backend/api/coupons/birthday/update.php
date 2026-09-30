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
header('Access-Control-Allow-Methods: POST, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PATCH'], true)) {
    sendResponse(false, 'Only POST or PATCH method is allowed.', null, 405);
}

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);
$adminId = getAuthenticatedId($adminAuth);

if ($adminId <= 0) {
    sendResponse(false, 'Invalid authenticated admin.', null, 401);
}

$data = getJsonInput();
$idInput = $data['id'] ?? null;

if ($idInput === null || filter_var($idInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Valid birthday coupon id is required.', null, 422);
}

$birthdayCouponId = (int)$idInput;

if ($birthdayCouponId <= 0) {
    sendResponse(false, 'Birthday coupon id must be greater than 0.', null, 422);
}

$allowedFields = ['coupon_code', 'title', 'description', 'discount_type', 'discount_value', 'min_order_amount', 'max_discount_amount', 'valid_before_days', 'valid_after_days', 'usage_limit_per_birthday', 'status'];
$providedFields = [];

foreach ($allowedFields as $field) {
    if (array_key_exists($field, $data)) {
        $providedFields[] = $field;
    }
}

if (empty($providedFields)) {
    sendResponse(false, 'At least one field must be provided for update.', ['allowed_fields' => $allowedFields], 422);
}

try {
    $adminStmt = $pdo->prepare("SELECT id, role, status FROM admins WHERE id = :admin_id LIMIT 1");
    $adminStmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $adminStmt->execute();
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        sendResponse(false, 'Authenticated admin not found.', null, 401);
    }

    if ($admin['role'] !== 'admin') {
        sendResponse(false, 'Only admin can update birthday coupons.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $couponStmt = $pdo->prepare("SELECT id, coupon_code, title, description, discount_type, discount_value, min_order_amount, max_discount_amount, valid_before_days, valid_after_days, usage_limit_per_birthday, status, created_by_admin_id, created_at, updated_at FROM birthday_coupons WHERE id = :id LIMIT 1");
    $couponStmt->bindValue(':id', $birthdayCouponId, PDO::PARAM_INT);
    $couponStmt->execute();
    $existing = $couponStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(false, 'Birthday coupon not found.', null, 404);
    }

    $couponCode = $existing['coupon_code'];
    $title = $existing['title'];
    $description = $existing['description'];
    $discountType = $existing['discount_type'];
    $discountValue = (float)$existing['discount_value'];
    $minOrderAmount = (float)$existing['min_order_amount'];
    $maxDiscountAmount = $existing['max_discount_amount'] !== null ? (float)$existing['max_discount_amount'] : null;
    $validBeforeDays = (int)$existing['valid_before_days'];
    $validAfterDays = (int)$existing['valid_after_days'];
    $usageLimitPerBirthday = (int)$existing['usage_limit_per_birthday'];
    $status = $existing['status'];

    if (array_key_exists('coupon_code', $data)) {
        $couponCode = strtoupper(trim((string)$data['coupon_code']));

        if ($couponCode === '') {
            sendResponse(false, 'Coupon code cannot be empty.', null, 422);
        }

        if (strlen($couponCode) < 3 || strlen($couponCode) > 50) {
            sendResponse(false, 'Coupon code must be between 3 and 50 characters.', null, 422);
        }

        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $couponCode)) {
            sendResponse(false, 'Coupon code can contain only uppercase letters, numbers, hyphen and underscore.', null, 422);
        }
    }

    if (array_key_exists('title', $data)) {
        $title = trim((string)$data['title']);

        if ($title === '') {
            sendResponse(false, 'Title cannot be empty.', null, 422);
        }

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            sendResponse(false, 'Title must be between 3 and 150 characters.', null, 422);
        }
    }

    if (array_key_exists('description', $data)) {
        if ($data['description'] === null) {
            $description = null;
        } else {
            $description = trim((string)$data['description']);
            if ($description === '') {
                $description = null;
            }
        }

        if ($description !== null && mb_strlen($description) > 255) {
            sendResponse(false, 'Description must not exceed 255 characters.', null, 422);
        }
    }

    $discountTypeChanged = false;

    if (array_key_exists('discount_type', $data)) {
        $newDiscountType = strtolower(trim((string)$data['discount_type']));
        $allowedDiscountTypes = ['percentage', 'flat', 'free_shipping'];

        if (!in_array($newDiscountType, $allowedDiscountTypes, true)) {
            sendResponse(false, 'Invalid discount type.', ['allowed_values' => $allowedDiscountTypes], 422);
        }

        $discountTypeChanged = $newDiscountType !== $discountType;
        $discountType = $newDiscountType;
    }

    if ($discountTypeChanged && in_array($discountType, ['percentage', 'flat'], true) && !array_key_exists('discount_value', $data)) {
        sendResponse(false, 'discount_value is required when changing discount_type to percentage or flat.', null, 422);
    }

    if (array_key_exists('discount_value', $data)) {
        if ($data['discount_value'] === null || $data['discount_value'] === '') {
            sendResponse(false, 'Discount value cannot be empty.', null, 422);
        }

        if (!is_numeric($data['discount_value'])) {
            sendResponse(false, 'Discount value must be a valid number.', null, 422);
        }

        $discountValue = round((float)$data['discount_value'], 2);
    }

    if (array_key_exists('min_order_amount', $data)) {
        if ($data['min_order_amount'] === null || $data['min_order_amount'] === '') {
            sendResponse(false, 'Minimum order amount cannot be empty.', null, 422);
        }

        if (!is_numeric($data['min_order_amount'])) {
            sendResponse(false, 'Minimum order amount must be a valid number.', null, 422);
        }

        $minOrderAmount = round((float)$data['min_order_amount'], 2);

        if ($minOrderAmount < 0) {
            sendResponse(false, 'Minimum order amount cannot be negative.', null, 422);
        }

        if ($minOrderAmount > 99999999.99) {
            sendResponse(false, 'Minimum order amount exceeds the allowed limit.', null, 422);
        }
    }

    if (array_key_exists('max_discount_amount', $data)) {
        if ($data['max_discount_amount'] === null || $data['max_discount_amount'] === '') {
            $maxDiscountAmount = null;
        } else {
            if (!is_numeric($data['max_discount_amount'])) {
                sendResponse(false, 'Maximum discount amount must be a valid number or null.', null, 422);
            }

            $maxDiscountAmount = round((float)$data['max_discount_amount'], 2);

            if ($maxDiscountAmount <= 0) {
                sendResponse(false, 'Maximum discount amount must be greater than 0.', null, 422);
            }

            if ($maxDiscountAmount > 99999999.99) {
                sendResponse(false, 'Maximum discount amount exceeds the allowed limit.', null, 422);
            }
        }
    }

    if (array_key_exists('valid_before_days', $data)) {
        if (filter_var($data['valid_before_days'], FILTER_VALIDATE_INT) === false) {
            sendResponse(false, 'valid_before_days must be a valid integer.', null, 422);
        }

        $validBeforeDays = (int)$data['valid_before_days'];

        if ($validBeforeDays < 0 || $validBeforeDays > 30) {
            sendResponse(false, 'valid_before_days must be between 0 and 30.', null, 422);
        }
    }

    if (array_key_exists('valid_after_days', $data)) {
        if (filter_var($data['valid_after_days'], FILTER_VALIDATE_INT) === false) {
            sendResponse(false, 'valid_after_days must be a valid integer.', null, 422);
        }

        $validAfterDays = (int)$data['valid_after_days'];

        if ($validAfterDays < 0 || $validAfterDays > 30) {
            sendResponse(false, 'valid_after_days must be between 0 and 30.', null, 422);
        }
    }

    if (array_key_exists('usage_limit_per_birthday', $data)) {
        if (filter_var($data['usage_limit_per_birthday'], FILTER_VALIDATE_INT) === false) {
            sendResponse(false, 'usage_limit_per_birthday must be a valid integer.', null, 422);
        }

        $usageLimitPerBirthday = (int)$data['usage_limit_per_birthday'];

        if ($usageLimitPerBirthday < 1 || $usageLimitPerBirthday > 100) {
            sendResponse(false, 'usage_limit_per_birthday must be between 1 and 100.', null, 422);
        }
    }

    if (array_key_exists('status', $data)) {
        $status = strtolower(trim((string)$data['status']));
        $allowedStatuses = ['active', 'inactive'];

        if (!in_array($status, $allowedStatuses, true)) {
            sendResponse(false, 'Invalid status.', ['allowed_values' => $allowedStatuses], 422);
        }
    }

    if ($discountType === 'percentage') {
        if ($discountValue <= 0 || $discountValue > 100) {
            sendResponse(false, 'Percentage discount value must be greater than 0 and not exceed 100.', null, 422);
        }

        if ($maxDiscountAmount !== null && $maxDiscountAmount <= 0) {
            sendResponse(false, 'Maximum discount amount must be greater than 0.', null, 422);
        }
    }

    if ($discountType === 'flat') {
        if ($discountValue <= 0) {
            sendResponse(false, 'Flat discount value must be greater than 0.', null, 422);
        }

        if ($discountValue > 99999999.99) {
            sendResponse(false, 'Flat discount value exceeds the allowed limit.', null, 422);
        }

        if ($minOrderAmount > 0 && $discountValue > $minOrderAmount) {
            sendResponse(false, 'Flat discount value cannot be greater than minimum order amount.', null, 422);
        }

        $maxDiscountAmount = null;
    }

    if ($discountType === 'free_shipping') {
        $discountValue = 0.00;
        $maxDiscountAmount = null;
    }

    if (array_key_exists('coupon_code', $data)) {
        $duplicateStmt = $pdo->prepare("SELECT coupon_type FROM (SELECT 'birthday' AS coupon_type FROM birthday_coupons WHERE UPPER(coupon_code) = ? AND id <> ? UNION ALL SELECT 'festival' AS coupon_type FROM festival_coupons WHERE UPPER(coupon_code) = ? UNION ALL SELECT 'first_order' AS coupon_type FROM first_order_coupons WHERE UPPER(coupon_code) = ? UNION ALL SELECT 'referral' AS coupon_type FROM referral_coupons WHERE UPPER(referral_code) = ?) AS coupon_codes LIMIT 1");

        $duplicateStmt->execute([
            $couponCode,
            $birthdayCouponId,
            $couponCode,
            $couponCode,
            $couponCode
        ]);

        $duplicateCoupon = $duplicateStmt->fetch(PDO::FETCH_ASSOC);

        if ($duplicateCoupon) {
            sendResponse(false, 'Coupon code already exists.', [
                'coupon_code' => $couponCode,
                'existing_coupon_type' => $duplicateCoupon['coupon_type']
            ], 409);
        }
    }

    $updateFields = [];
    $params = [];

    if (array_key_exists('coupon_code', $data)) {
        $updateFields[] = 'coupon_code = :coupon_code';
        $params[':coupon_code'] = $couponCode;
    }

    if (array_key_exists('title', $data)) {
        $updateFields[] = 'title = :title';
        $params[':title'] = $title;
    }

    if (array_key_exists('description', $data)) {
        $updateFields[] = 'description = :description';
        $params[':description'] = $description;
    }

    if (array_key_exists('discount_type', $data)) {
        $updateFields[] = 'discount_type = :discount_type';
        $params[':discount_type'] = $discountType;
    }

    if (array_key_exists('discount_value', $data) || array_key_exists('discount_type', $data)) {
        $updateFields[] = 'discount_value = :discount_value';
        $params[':discount_value'] = number_format($discountValue, 2, '.', '');
    }

    if (array_key_exists('max_discount_amount', $data) || array_key_exists('discount_type', $data)) {
        $updateFields[] = 'max_discount_amount = :max_discount_amount';
        $params[':max_discount_amount'] = $maxDiscountAmount !== null ? number_format($maxDiscountAmount, 2, '.', '') : null;
    }

    if (array_key_exists('min_order_amount', $data)) {
        $updateFields[] = 'min_order_amount = :min_order_amount';
        $params[':min_order_amount'] = number_format($minOrderAmount, 2, '.', '');
    }

    if (array_key_exists('valid_before_days', $data)) {
        $updateFields[] = 'valid_before_days = :valid_before_days';
        $params[':valid_before_days'] = $validBeforeDays;
    }

    if (array_key_exists('valid_after_days', $data)) {
        $updateFields[] = 'valid_after_days = :valid_after_days';
        $params[':valid_after_days'] = $validAfterDays;
    }

    if (array_key_exists('usage_limit_per_birthday', $data)) {
        $updateFields[] = 'usage_limit_per_birthday = :usage_limit_per_birthday';
        $params[':usage_limit_per_birthday'] = $usageLimitPerBirthday;
    }

    if (array_key_exists('status', $data)) {
        $updateFields[] = 'status = :status';
        $params[':status'] = $status;
    }

    if (empty($updateFields)) {
        sendResponse(false, 'No valid fields found for update.', null, 422);
    }

    $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';

    $updateSql = "UPDATE birthday_coupons SET " . implode(', ', $updateFields) . " WHERE id = :id LIMIT 1";
    $updateStmt = $pdo->prepare($updateSql);

    foreach ($params as $key => $value) {
        if ($value === null) {
            $updateStmt->bindValue($key, null, PDO::PARAM_NULL);
        } elseif (is_int($value)) {
            $updateStmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $updateStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $updateStmt->bindValue(':id', $birthdayCouponId, PDO::PARAM_INT);
    $updateStmt->execute();

    $viewStmt = $pdo->prepare("SELECT bc.id, bc.coupon_code, bc.title, bc.description, bc.discount_type, bc.discount_value, bc.min_order_amount, bc.max_discount_amount, bc.valid_before_days, bc.valid_after_days, bc.usage_limit_per_birthday, bc.status, bc.created_by_admin_id, a.name AS created_by_admin_name, bc.created_at, bc.updated_at FROM birthday_coupons bc LEFT JOIN admins a ON a.id = bc.created_by_admin_id WHERE bc.id = :id LIMIT 1");

    $viewStmt->bindValue(':id', $birthdayCouponId, PDO::PARAM_INT);
    $viewStmt->execute();

    $updatedCoupon = $viewStmt->fetch(PDO::FETCH_ASSOC);

    if (!$updatedCoupon) {
        sendResponse(false, 'Birthday coupon updated but could not be retrieved.', null, 500);
    }

    $formattedCoupon = [
        'id' => (int)$updatedCoupon['id'],
        'coupon_code' => $updatedCoupon['coupon_code'],
        'title' => $updatedCoupon['title'],
        'description' => $updatedCoupon['description'],
        'discount_type' => $updatedCoupon['discount_type'],
        'discount_value' => (float)$updatedCoupon['discount_value'],
        'min_order_amount' => (float)$updatedCoupon['min_order_amount'],
        'max_discount_amount' => $updatedCoupon['max_discount_amount'] !== null ? (float)$updatedCoupon['max_discount_amount'] : null,
        'valid_before_days' => (int)$updatedCoupon['valid_before_days'],
        'valid_after_days' => (int)$updatedCoupon['valid_after_days'],
        'usage_limit_per_birthday' => (int)$updatedCoupon['usage_limit_per_birthday'],
        'status' => $updatedCoupon['status'],
        'created_by' => [
            'admin_id' => $updatedCoupon['created_by_admin_id'] !== null ? (int)$updatedCoupon['created_by_admin_id'] : null,
            'admin_name' => $updatedCoupon['created_by_admin_name']
        ],
        'created_at' => $updatedCoupon['created_at'],
        'updated_at' => $updatedCoupon['updated_at']
    ];

    sendResponse(true, 'Birthday coupon updated successfully.', ['birthday_coupon' => $formattedCoupon], 200);

} catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        sendResponse(false, 'Coupon code already exists.', null, 409);
    }

    sendResponse(
        false,
        'Unable to update birthday coupon.',
        defined('APP_ENV') && APP_ENV === 'development' ? ['error' => $e->getMessage()] : null,
        500
    );

} catch (Throwable $e) {
    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV') && APP_ENV === 'development' ? ['error' => $e->getMessage()] : null,
        500
    );
}