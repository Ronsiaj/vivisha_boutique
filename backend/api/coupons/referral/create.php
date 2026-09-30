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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);

$adminId = getAuthenticatedId($adminAuth);

if ($adminId <= 0) {
    sendResponse(false, 'Invalid authenticated admin.', null, 401);
}

$data = getJsonInput();

$referralCode = isset($data['referral_code'])
    ? strtoupper(trim((string)$data['referral_code']))
    : '';

$title = isset($data['title'])
    ? trim((string)$data['title'])
    : '';

$description = isset($data['description']) && $data['description'] !== null
    ? trim((string)$data['description'])
    : '';

$discountType = isset($data['discount_type'])
    ? strtolower(trim((string)$data['discount_type']))
    : '';

$discountValueInput = $data['discount_value'] ?? null;
$minOrderAmountInput = $data['min_order_amount'] ?? 0;
$maxDiscountAmountInput = $data['max_discount_amount'] ?? null;
$usageLimitInput = $data['usage_limit'] ?? null;
$usageLimitPerUserInput = $data['usage_limit_per_user'] ?? 1;

$startAtInput = isset($data['start_at']) && $data['start_at'] !== null
    ? trim((string)$data['start_at'])
    : '';

$endAtInput = isset($data['end_at']) && $data['end_at'] !== null
    ? trim((string)$data['end_at'])
    : '';

$status = isset($data['status'])
    ? strtolower(trim((string)$data['status']))
    : 'active';

if ($referralCode === '') {
    sendResponse(false, 'Referral code is required.', null, 422);
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

if ($title === '') {
    sendResponse(false, 'Title is required.', null, 422);
}

if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
    sendResponse(
        false,
        'Title must be between 3 and 150 characters.',
        null,
        422
    );
}

if ($description !== '' && mb_strlen($description) > 255) {
    sendResponse(
        false,
        'Description must not exceed 255 characters.',
        null,
        422
    );
}

$allowedDiscountTypes = [
    'percentage',
    'flat',
    'free_shipping'
];

if ($discountType === '') {
    sendResponse(false, 'Discount type is required.', null, 422);
}

if (!in_array($discountType, $allowedDiscountTypes, true)) {
    sendResponse(
        false,
        'Invalid discount_type.',
        [
            'allowed_values' => $allowedDiscountTypes
        ],
        422
    );
}

if (!is_numeric($minOrderAmountInput)) {
    sendResponse(
        false,
        'Minimum order amount must be a valid number.',
        null,
        422
    );
}

$minOrderAmount = round((float)$minOrderAmountInput, 2);

if ($minOrderAmount < 0 || $minOrderAmount > 99999999.99) {
    sendResponse(
        false,
        'Minimum order amount must be between 0 and 99999999.99.',
        null,
        422
    );
}

$discountValue = 0.00;
$maxDiscountAmount = null;

if ($discountType === 'percentage') {
    if ($discountValueInput === null || $discountValueInput === '') {
        sendResponse(
            false,
            'Discount value is required for percentage discount.',
            null,
            422
        );
    }

    if (!is_numeric($discountValueInput)) {
        sendResponse(
            false,
            'Discount value must be a valid number.',
            null,
            422
        );
    }

    $discountValue = round((float)$discountValueInput, 2);

    if ($discountValue <= 0 || $discountValue > 100) {
        sendResponse(
            false,
            'Percentage discount value must be greater than 0 and not exceed 100.',
            null,
            422
        );
    }

    if (
        $maxDiscountAmountInput !== null &&
        $maxDiscountAmountInput !== ''
    ) {
        if (!is_numeric($maxDiscountAmountInput)) {
            sendResponse(
                false,
                'Maximum discount amount must be a valid number.',
                null,
                422
            );
        }

        $maxDiscountAmount = round(
            (float)$maxDiscountAmountInput,
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

if ($discountType === 'flat') {
    if ($discountValueInput === null || $discountValueInput === '') {
        sendResponse(
            false,
            'Discount value is required for flat discount.',
            null,
            422
        );
    }

    if (!is_numeric($discountValueInput)) {
        sendResponse(
            false,
            'Discount value must be a valid number.',
            null,
            422
        );
    }

    $discountValue = round((float)$discountValueInput, 2);

    if ($discountValue <= 0 || $discountValue > 99999999.99) {
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

$usageLimit = null;

if (
    $usageLimitInput !== null &&
    $usageLimitInput !== ''
) {
    if (
        filter_var(
            $usageLimitInput,
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

    $usageLimit = (int)$usageLimitInput;

    if ($usageLimit < 1) {
        sendResponse(
            false,
            'usage_limit must be greater than 0.',
            null,
            422
        );
    }
}

if (
    filter_var(
        $usageLimitPerUserInput,
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

$usageLimitPerUser = (int)$usageLimitPerUserInput;

if ($usageLimitPerUser < 1) {
    sendResponse(
        false,
        'usage_limit_per_user must be greater than 0.',
        null,
        422
    );
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

function validateReferralDateTime(string $value, string $field): ?string
{
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

        if (
            $date &&
            !$hasErrors &&
            $date->format($format) === $value
        ) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    sendResponse(
        false,
        "{$field} must be a valid datetime.",
        [
            'accepted_formats' => [
                'YYYY-MM-DD HH:MM:SS',
                'YYYY-MM-DD HH:MM',
                'YYYY-MM-DDTHH:MM:SS',
                'YYYY-MM-DDTHH:MM'
            ]
        ],
        422
    );
}

$startAt = validateReferralDateTime(
    $startAtInput,
    'start_at'
);

$endAt = validateReferralDateTime(
    $endAtInput,
    'end_at'
);

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

$allowedStatuses = [
    'active',
    'inactive',
    'expired'
];

if (!in_array($status, $allowedStatuses, true)) {
    sendResponse(
        false,
        'Invalid status.',
        [
            'allowed_values' => $allowedStatuses
        ],
        422
    );
}

if ($status === 'expired' && $endAt === null) {
    sendResponse(
        false,
        'end_at is required when status is expired.',
        null,
        422
    );
}

try {
    $adminStmt = $pdo->prepare("
        SELECT id,role,status
        FROM admins
        WHERE id=:admin_id
        LIMIT 1
    ");

    $adminStmt->bindValue(
        ':admin_id',
        $adminId,
        PDO::PARAM_INT
    );

    $adminStmt->execute();

    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        sendResponse(
            false,
            'Authenticated admin not found.',
            null,
            401
        );
    }

    if ($admin['role'] !== 'admin') {
        sendResponse(
            false,
            'Only admin can create referral coupons.',
            null,
            403
        );
    }

    if ($admin['status'] !== 'active') {
        sendResponse(
            false,
            'Admin account is not active.',
            null,
            403
        );
    }

    $duplicateStmt = $pdo->prepare("
        SELECT coupon_type
        FROM (
            SELECT
                'referral' AS coupon_type
            FROM referral_coupons
            WHERE UPPER(referral_code)=?

            UNION ALL

            SELECT
                'birthday' AS coupon_type
            FROM birthday_coupons
            WHERE UPPER(coupon_code)=?

            UNION ALL

            SELECT
                'festival' AS coupon_type
            FROM festival_coupons
            WHERE UPPER(coupon_code)=?

            UNION ALL

            SELECT
                'first_order' AS coupon_type
            FROM first_order_coupons
            WHERE UPPER(coupon_code)=?
        ) AS coupon_codes
        LIMIT 1
    ");

    $duplicateStmt->execute([
        $referralCode,
        $referralCode,
        $referralCode,
        $referralCode
    ]);

    $existingCoupon = $duplicateStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($existingCoupon) {
        sendResponse(
            false,
            'Referral code already exists.',
            [
                'referral_code' => $referralCode,
                'existing_coupon_type' =>
                    $existingCoupon['coupon_type']
            ],
            409
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO referral_coupons (
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
            created_by_admin_id
        )
        VALUES (
            :referral_code,
            :title,
            :description,
            :discount_type,
            :discount_value,
            :min_order_amount,
            :max_discount_amount,
            :usage_limit,
            :usage_limit_per_user,
            :start_at,
            :end_at,
            :status,
            :created_by_admin_id
        )
    ");

    $stmt->bindValue(
        ':referral_code',
        $referralCode,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':title',
        $title,
        PDO::PARAM_STR
    );

    if ($description === '') {
        $stmt->bindValue(
            ':description',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':description',
            $description,
            PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':discount_type',
        $discountType,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':discount_value',
        number_format(
            $discountValue,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':min_order_amount',
        number_format(
            $minOrderAmount,
            2,
            '.',
            ''
        ),
        PDO::PARAM_STR
    );

    if ($maxDiscountAmount === null) {
        $stmt->bindValue(
            ':max_discount_amount',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':max_discount_amount',
            number_format(
                $maxDiscountAmount,
                2,
                '.',
                ''
            ),
            PDO::PARAM_STR
        );
    }

    if ($usageLimit === null) {
        $stmt->bindValue(
            ':usage_limit',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':usage_limit',
            $usageLimit,
            PDO::PARAM_INT
        );
    }

    $stmt->bindValue(
        ':usage_limit_per_user',
        $usageLimitPerUser,
        PDO::PARAM_INT
    );

    if ($startAt === null) {
        $stmt->bindValue(
            ':start_at',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':start_at',
            $startAt,
            PDO::PARAM_STR
        );
    }

    if ($endAt === null) {
        $stmt->bindValue(
            ':end_at',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $stmt->bindValue(
            ':end_at',
            $endAt,
            PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':status',
        $status,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':created_by_admin_id',
        $adminId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $referralCouponId = (int)$pdo->lastInsertId();

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
            'Referral coupon was created but could not be retrieved.',
            null,
            500
        );
    }

    sendResponse(
        true,
        'Referral coupon created successfully.',
        [
            'referral_coupon' => [
                'id' => (int)$coupon['id'],
                'referral_code' => $coupon['referral_code'],
                'title' => $coupon['title'],
                'description' => $coupon['description'],
                'discount_type' => $coupon['discount_type'],
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
                'start_at' => $coupon['start_at'],
                'end_at' => $coupon['end_at'],
                'status' => $coupon['status'],
                'created_by' => [
                    'admin_id' =>
                        $coupon['created_by_admin_id'] !== null
                            ? (int)$coupon['created_by_admin_id']
                            : null,
                    'admin_name' =>
                        $coupon['created_by_admin_name'],
                    'admin_role' =>
                        $coupon['created_by_admin_role']
                ],
                'created_at' => $coupon['created_at'],
                'updated_at' => $coupon['updated_at']
            ]
        ],
        201
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
        'Unable to create referral coupon.',
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