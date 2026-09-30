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

if (
    $idInput === null ||
    filter_var($idInput, FILTER_VALIDATE_INT) === false ||
    (int)$idInput <= 0
) {
    sendResponse(false, 'Valid referral coupon id is required.', null, 422);
}

$referralCouponId = (int)$idInput;

$allowedFields = [
    'referral_code',
    'title',
    'description',
    'discount_type',
    'discount_value',
    'min_order_amount',
    'max_discount_amount',
    'usage_limit',
    'usage_limit_per_user',
    'start_at',
    'end_at',
    'status'
];

$providedFields = [];

foreach ($allowedFields as $field) {
    if (array_key_exists($field, $data)) {
        $providedFields[] = $field;
    }
}

if (empty($providedFields)) {
    sendResponse(false, 'At least one field must be provided for update.', [
        'allowed_fields' => $allowedFields
    ], 422);
}

$parseDateTime = static function ($value, string $field): ?string {
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        sendResponse(false, "{$field} must be a valid datetime or null.", null, 422);
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i'
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();

        $hasErrors = $errors !== false && (
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0
        );

        if ($date && !$hasErrors && $date->format($format) === $value) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    sendResponse(false, "{$field} must be a valid datetime.", [
        'accepted_formats' => [
            'YYYY-MM-DD HH:MM:SS',
            'YYYY-MM-DD HH:MM',
            'YYYY-MM-DDTHH:MM:SS',
            'YYYY-MM-DDTHH:MM'
        ]
    ], 422);
};

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
        sendResponse(false, 'Only admin can update referral coupons.', null, 403);
    }

    if ($admin['status'] !== 'active') {
        sendResponse(false, 'Admin account is not active.', null, 403);
    }

    $existingStmt = $pdo->prepare("
        SELECT
            id,
            referral_code,
            title,
            description,
            discount_type,
            discount_value,
            min_order_amount,
            max_discount_amount,
            usage_limit,
            usage_limit_per_user,
            start_at,
            end_at,
            status,
            created_by_admin_id,
            created_at,
            updated_at
        FROM referral_coupons
        WHERE id=:id
        LIMIT 1
    ");

    $existingStmt->bindValue(':id', $referralCouponId, PDO::PARAM_INT);
    $existingStmt->execute();

    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(false, 'Referral coupon not found.', null, 404);
    }

    $referralCode = $existing['referral_code'];
    $title = $existing['title'];
    $description = $existing['description'];
    $discountType = $existing['discount_type'];
    $discountValue = (float)$existing['discount_value'];
    $minOrderAmount = (float)$existing['min_order_amount'];

    $maxDiscountAmount = $existing['max_discount_amount'] !== null
        ? (float)$existing['max_discount_amount']
        : null;

    $usageLimit = $existing['usage_limit'] !== null
        ? (int)$existing['usage_limit']
        : null;

    $usageLimitPerUser = (int)$existing['usage_limit_per_user'];
    $startAt = $existing['start_at'];
    $endAt = $existing['end_at'];
    $status = $existing['status'];

    if (array_key_exists('referral_code', $data)) {
        $referralCode = strtoupper(trim((string)$data['referral_code']));

        if ($referralCode === '') {
            sendResponse(false, 'Referral code cannot be empty.', null, 422);
        }

        if (strlen($referralCode) < 3 || strlen($referralCode) > 50) {
            sendResponse(
                false,
                'Referral code must be between 3 and 50 characters.',
                null,
                422
            );
        }

        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $referralCode)) {
            sendResponse(
                false,
                'Referral code can contain only uppercase letters, numbers, hyphen and underscore.',
                null,
                422
            );
        }
    }

    if (array_key_exists('title', $data)) {
        $title = trim((string)$data['title']);

        if ($title === '') {
            sendResponse(false, 'Title cannot be empty.', null, 422);
        }

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            sendResponse(
                false,
                'Title must be between 3 and 150 characters.',
                null,
                422
            );
        }
    }

    if (array_key_exists('description', $data)) {
        if (
            $data['description'] === null ||
            trim((string)$data['description']) === ''
        ) {
            $description = null;
        } else {
            $description = trim((string)$data['description']);

            if (mb_strlen($description) > 255) {
                sendResponse(
                    false,
                    'Description must not exceed 255 characters.',
                    null,
                    422
                );
            }
        }
    }

    $discountTypeChanged = false;

    if (array_key_exists('discount_type', $data)) {
        $newDiscountType = strtolower(trim((string)$data['discount_type']));

        $allowedDiscountTypes = [
            'percentage',
            'flat',
            'free_shipping'
        ];

        if (!in_array($newDiscountType, $allowedDiscountTypes, true)) {
            sendResponse(false, 'Invalid discount_type.', [
                'allowed_values' => $allowedDiscountTypes
            ], 422);
        }

        $discountTypeChanged = $newDiscountType !== $discountType;
        $discountType = $newDiscountType;
    }

    if (
        $discountTypeChanged &&
        in_array($discountType, ['percentage', 'flat'], true) &&
        !array_key_exists('discount_value', $data)
    ) {
        sendResponse(
            false,
            'discount_value is required when changing discount_type to percentage or flat.',
            null,
            422
        );
    }

    if (array_key_exists('discount_value', $data)) {
        if (
            $data['discount_value'] === null ||
            $data['discount_value'] === ''
        ) {
            sendResponse(false, 'Discount value cannot be empty.', null, 422);
        }

        if (!is_numeric($data['discount_value'])) {
            sendResponse(
                false,
                'Discount value must be a valid number.',
                null,
                422
            );
        }

        $discountValue = round((float)$data['discount_value'], 2);
    }

    if (array_key_exists('min_order_amount', $data)) {
        if (
            $data['min_order_amount'] === null ||
            $data['min_order_amount'] === ''
        ) {
            sendResponse(
                false,
                'Minimum order amount cannot be empty.',
                null,
                422
            );
        }

        if (!is_numeric($data['min_order_amount'])) {
            sendResponse(
                false,
                'Minimum order amount must be a valid number.',
                null,
                422
            );
        }

        $minOrderAmount = round((float)$data['min_order_amount'], 2);

        if (
            $minOrderAmount < 0 ||
            $minOrderAmount > 99999999.99
        ) {
            sendResponse(
                false,
                'Minimum order amount must be between 0 and 99999999.99.',
                null,
                422
            );
        }
    }

    if (array_key_exists('max_discount_amount', $data)) {
        if (
            $data['max_discount_amount'] === null ||
            $data['max_discount_amount'] === ''
        ) {
            $maxDiscountAmount = null;
        } else {
            if (!is_numeric($data['max_discount_amount'])) {
                sendResponse(
                    false,
                    'Maximum discount amount must be a valid number or null.',
                    null,
                    422
                );
            }

            $maxDiscountAmount = round(
                (float)$data['max_discount_amount'],
                2
            );

            if (
                $maxDiscountAmount <= 0 ||
                $maxDiscountAmount > 99999999.99
            ) {
                sendResponse(
                    false,
                    'Maximum discount amount must be greater than 0 and not exceed 99999999.99.',
                    null,
                    422
                );
            }
        }
    }

    if (array_key_exists('usage_limit', $data)) {
        if (
            $data['usage_limit'] === null ||
            $data['usage_limit'] === ''
        ) {
            $usageLimit = null;
        } else {
            if (
                filter_var(
                    $data['usage_limit'],
                    FILTER_VALIDATE_INT
                ) === false
            ) {
                sendResponse(
                    false,
                    'usage_limit must be a valid integer or null.',
                    null,
                    422
                );
            }

            $usageLimit = (int)$data['usage_limit'];

            if ($usageLimit < 1) {
                sendResponse(
                    false,
                    'usage_limit must be greater than 0.',
                    null,
                    422
                );
            }
        }
    }

    if (array_key_exists('usage_limit_per_user', $data)) {
        if (
            filter_var(
                $data['usage_limit_per_user'],
                FILTER_VALIDATE_INT
            ) === false
        ) {
            sendResponse(
                false,
                'usage_limit_per_user must be a valid integer.',
                null,
                422
            );
        }

        $usageLimitPerUser = (int)$data['usage_limit_per_user'];

        if ($usageLimitPerUser < 1) {
            sendResponse(
                false,
                'usage_limit_per_user must be greater than 0.',
                null,
                422
            );
        }
    }

    if (array_key_exists('start_at', $data)) {
        $startAt = $parseDateTime(
            $data['start_at'],
            'start_at'
        );
    }

    if (array_key_exists('end_at', $data)) {
        $endAt = $parseDateTime(
            $data['end_at'],
            'end_at'
        );
    }

    if (
        $startAt !== null &&
        $endAt !== null &&
        strtotime($endAt) <= strtotime($startAt)
    ) {
        sendResponse(
            false,
            'end_at must be greater than start_at.',
            null,
            422
        );
    }

    if (array_key_exists('status', $data)) {
        $status = strtolower(trim((string)$data['status']));

        $allowedStatuses = [
            'active',
            'inactive',
            'expired'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            sendResponse(false, 'Invalid status.', [
                'allowed_values' => $allowedStatuses
            ], 422);
        }
    }

    if ($status === 'expired' && $endAt === null) {
        sendResponse(
            false,
            'end_at is required when status is expired.',
            null,
            422
        );
    }

    if ($discountType === 'percentage') {
        if (
            $discountValue <= 0 ||
            $discountValue > 100
        ) {
            sendResponse(
                false,
                'Percentage discount value must be greater than 0 and not exceed 100.',
                null,
                422
            );
        }

        if (
            $maxDiscountAmount !== null &&
            (
                $maxDiscountAmount <= 0 ||
                $maxDiscountAmount > 99999999.99
            )
        ) {
            sendResponse(
                false,
                'Maximum discount amount must be greater than 0 and not exceed 99999999.99.',
                null,
                422
            );
        }
    }

    if ($discountType === 'flat') {
        if (
            $discountValue <= 0 ||
            $discountValue > 99999999.99
        ) {
            sendResponse(
                false,
                'Flat discount value must be greater than 0 and not exceed 99999999.99.',
                null,
                422
            );
        }

        if (
            $minOrderAmount > 0 &&
            $discountValue > $minOrderAmount
        ) {
            sendResponse(
                false,
                'Flat discount value cannot be greater than minimum order amount.',
                null,
                422
            );
        }

        $maxDiscountAmount = null;
    }

    if ($discountType === 'free_shipping') {
        $discountValue = 0.00;
        $maxDiscountAmount = null;
    }

    if (
        $usageLimit !== null &&
        $usageLimitPerUser > $usageLimit
    ) {
        sendResponse(
            false,
            'usage_limit_per_user cannot be greater than total usage_limit.',
            null,
            422
        );
    }

    if (array_key_exists('referral_code', $data)) {
        $duplicateStmt = $pdo->prepare("
            SELECT coupon_type
            FROM (
                SELECT 'referral' AS coupon_type
                FROM referral_coupons
                WHERE UPPER(referral_code)=?
                AND id<>?

                UNION ALL

                SELECT 'birthday' AS coupon_type
                FROM birthday_coupons
                WHERE UPPER(coupon_code)=?

                UNION ALL

                SELECT 'festival' AS coupon_type
                FROM festival_coupons
                WHERE UPPER(coupon_code)=?

                UNION ALL

                SELECT 'first_order' AS coupon_type
                FROM first_order_coupons
                WHERE UPPER(coupon_code)=?
            ) AS coupon_codes
            LIMIT 1
        ");

        $duplicateStmt->execute([
            $referralCode,
            $referralCouponId,
            $referralCode,
            $referralCode,
            $referralCode
        ]);

        $duplicateCoupon = $duplicateStmt->fetch(PDO::FETCH_ASSOC);

        if ($duplicateCoupon) {
            sendResponse(
                false,
                'Referral code already exists.',
                [
                    'referral_code' => $referralCode,
                    'existing_coupon_type' =>
                        $duplicateCoupon['coupon_type']
                ],
                409
            );
        }
    }

    $updateFields = [];
    $params = [];

    if (array_key_exists('referral_code', $data)) {
        $updateFields[] = 'referral_code=:referral_code';
        $params[':referral_code'] = $referralCode;
    }

    if (array_key_exists('title', $data)) {
        $updateFields[] = 'title=:title';
        $params[':title'] = $title;
    }

    if (array_key_exists('description', $data)) {
        $updateFields[] = 'description=:description';
        $params[':description'] = $description;
    }

    if (array_key_exists('discount_type', $data)) {
        $updateFields[] = 'discount_type=:discount_type';
        $params[':discount_type'] = $discountType;
    }

    if (
        array_key_exists('discount_value', $data) ||
        array_key_exists('discount_type', $data)
    ) {
        $updateFields[] = 'discount_value=:discount_value';

        $params[':discount_value'] = number_format(
            $discountValue,
            2,
            '.',
            ''
        );
    }

    if (array_key_exists('min_order_amount', $data)) {
        $updateFields[] = 'min_order_amount=:min_order_amount';

        $params[':min_order_amount'] = number_format(
            $minOrderAmount,
            2,
            '.',
            ''
        );
    }

    if (
        array_key_exists('max_discount_amount', $data) ||
        array_key_exists('discount_type', $data)
    ) {
        $updateFields[] =
            'max_discount_amount=:max_discount_amount';

        $params[':max_discount_amount'] =
            $maxDiscountAmount !== null
                ? number_format(
                    $maxDiscountAmount,
                    2,
                    '.',
                    ''
                )
                : null;
    }

    if (array_key_exists('usage_limit', $data)) {
        $updateFields[] = 'usage_limit=:usage_limit';
        $params[':usage_limit'] = $usageLimit;
    }

    if (array_key_exists('usage_limit_per_user', $data)) {
        $updateFields[] =
            'usage_limit_per_user=:usage_limit_per_user';

        $params[':usage_limit_per_user'] =
            $usageLimitPerUser;
    }

    if (array_key_exists('start_at', $data)) {
        $updateFields[] = 'start_at=:start_at';
        $params[':start_at'] = $startAt;
    }

    if (array_key_exists('end_at', $data)) {
        $updateFields[] = 'end_at=:end_at';
        $params[':end_at'] = $endAt;
    }

    if (array_key_exists('status', $data)) {
        $updateFields[] = 'status=:status';
        $params[':status'] = $status;
    }

    if (empty($updateFields)) {
        sendResponse(
            false,
            'No valid fields found for update.',
            null,
            422
        );
    }

    $updateFields[] = 'updated_at=CURRENT_TIMESTAMP';

    $updateSql = "
        UPDATE referral_coupons
        SET " . implode(',', $updateFields) . "
        WHERE id=:id
        LIMIT 1
    ";

    $updateStmt = $pdo->prepare($updateSql);

    foreach ($params as $key => $value) {
        if ($value === null) {
            $updateStmt->bindValue(
                $key,
                null,
                PDO::PARAM_NULL
            );
        } elseif (is_int($value)) {
            $updateStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );
        } else {
            $updateStmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }

    $updateStmt->bindValue(
        ':id',
        $referralCouponId,
        PDO::PARAM_INT
    );

    $updateStmt->execute();

    $viewStmt = $pdo->prepare("
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

    $viewStmt->bindValue(
        ':id',
        $referralCouponId,
        PDO::PARAM_INT
    );

    $viewStmt->execute();

    $coupon = $viewStmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        sendResponse(
            false,
            'Referral coupon updated but could not be retrieved.',
            null,
            500
        );
    }

    sendResponse(
        true,
        'Referral coupon updated successfully.',
        [
            'referral_coupon' => [
                'id' => (int)$coupon['id'],
                'referral_code' =>
                    $coupon['referral_code'],
                'title' =>
                    $coupon['title'],
                'description' =>
                    $coupon['description'],
                'discount_type' =>
                    $coupon['discount_type'],
                'discount_value' =>
                    (float)$coupon['discount_value'],
                'min_order_amount' =>
                    (float)$coupon['min_order_amount'],
                'max_discount_amount' =>
                    $coupon['max_discount_amount'] !== null
                        ? (float)$coupon['max_discount_amount']
                        : null,
                'usage_limit' =>
                    $coupon['usage_limit'] !== null
                        ? (int)$coupon['usage_limit']
                        : null,
                'usage_limit_per_user' =>
                    (int)$coupon['usage_limit_per_user'],
                'start_at' =>
                    $coupon['start_at'],
                'end_at' =>
                    $coupon['end_at'],
                'status' =>
                    $coupon['status'],
                'created_by' => [
                    'admin_id' =>
                        $coupon['created_by_admin_id'] !== null
                            ? (int)$coupon['created_by_admin_id']
                            : null,
                    'admin_name' =>
                        $coupon['created_by_admin_name'],
                    'admin_role' =>
                        $coupon['created_by_admin_role'],
                    'admin_status' =>
                        $coupon['created_by_admin_status']
                ],
                'created_at' =>
                    $coupon['created_at'],
                'updated_at' =>
                    $coupon['updated_at']
            ]
        ],
        200
    );

} catch (PDOException $e) {
    if (
        isset($e->errorInfo[1]) &&
        (int)$e->errorInfo[1] === 1062
    ) {
        sendResponse(
            false,
            'Referral code already exists.',
            null,
            409
        );
    }

    sendResponse(
        false,
        'Unable to update referral coupon.',
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