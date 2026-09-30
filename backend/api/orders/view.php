<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

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

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if (!in_array($accountType, ['admin','user'], true)) {
    sendResponse(false, 'Access denied.', null, 403);
}

if ($accountType === 'admin') {
    $adminAuth = authenticateAdmin();
    checkAdminRole($adminAuth, ['admin']);
} else {
    $userAuth = authenticateUser();
    $authenticatedId = getAuthenticatedId($userAuth);

    if ($authenticatedId <= 0) {
        sendResponse(false, 'Invalid authenticated user.', null, 401);
    }
}

$allowedParams = ['id'];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$idInput = trim((string)($_GET['id'] ?? ''));

if ($idInput === '') {
    sendResponse(false, 'id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'id must be a valid positive integer.', null, 422);
}

$orderId = (int)$idInput;

try {
    $where = 'o.id=:order_id';

    if ($accountType === 'user') {
        $where .= ' AND o.user_id=:authenticated_user_id';
    }

    $stmt = $pdo->prepare(
        "SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.cart_id,
            o.subtotal,
            o.product_discount_amount,
            o.coupon_type,
            o.coupon_id,
            o.coupon_code,
            o.coupon_discount_amount,
            o.shipping_charge,
            o.cod_charge,
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
            o.cancelled_at,
            o.created_at,
            o.updated_at,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email,
            u.date_of_birth AS user_date_of_birth,
            u.status AS user_status,
            u.last_login AS user_last_login,
            u.created_at AS user_created_at,
            u.updated_at AS user_updated_at,

            bc.coupon_code AS birthday_coupon_code,
            bc.title AS birthday_title,
            bc.description AS birthday_description,
            bc.discount_type AS birthday_discount_type,
            bc.discount_value AS birthday_discount_value,
            bc.min_order_amount AS birthday_min_order_amount,
            bc.max_discount_amount AS birthday_max_discount_amount,
            bc.valid_before_days,
            bc.valid_after_days,
            bc.usage_limit_per_birthday,
            bc.status AS birthday_coupon_status,

            fc.festival_name,
            fc.coupon_code AS festival_coupon_code,
            fc.title AS festival_title,
            fc.description AS festival_description,
            fc.discount_type AS festival_discount_type,
            fc.discount_value AS festival_discount_value,
            fc.min_order_amount AS festival_min_order_amount,
            fc.max_discount_amount AS festival_max_discount_amount,
            fc.usage_limit AS festival_usage_limit,
            fc.usage_limit_per_user AS festival_usage_limit_per_user,
            fc.start_at AS festival_start_at,
            fc.end_at AS festival_end_at,
            fc.status AS festival_coupon_status,

            rc.referral_code,
            rc.title AS referral_title,
            rc.description AS referral_description,
            rc.discount_type AS referral_discount_type,
            rc.discount_value AS referral_discount_value,
            rc.min_order_amount AS referral_min_order_amount,
            rc.max_discount_amount AS referral_max_discount_amount,
            rc.usage_limit AS referral_usage_limit,
            rc.usage_limit_per_user AS referral_usage_limit_per_user,
            rc.start_at AS referral_start_at,
            rc.end_at AS referral_end_at,
            rc.status AS referral_coupon_status,

            foc.coupon_code AS first_order_coupon_code,
            foc.title AS first_order_title,
            foc.description AS first_order_description,
            foc.discount_type AS first_order_discount_type,
            foc.discount_value AS first_order_discount_value,
            foc.min_order_amount AS first_order_min_order_amount,
            foc.max_discount_amount AS first_order_max_discount_amount,
            foc.start_at AS first_order_start_at,
            foc.end_at AS first_order_end_at,
            foc.status AS first_order_coupon_status

         FROM orders o
         INNER JOIN users u ON u.id=o.user_id

         LEFT JOIN birthday_coupons bc
            ON o.coupon_type='birthday'
           AND bc.id=o.coupon_id

         LEFT JOIN festival_coupons fc
            ON o.coupon_type='festival'
           AND fc.id=o.coupon_id

         LEFT JOIN referral_coupons rc
            ON o.coupon_type='referral'
           AND rc.id=o.coupon_id

         LEFT JOIN first_order_coupons foc
            ON o.coupon_type='first_order'
           AND foc.id=o.coupon_id

         WHERE {$where}
         LIMIT 1"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);

    if ($accountType === 'user') {
        $stmt->bindValue(
            ':authenticated_user_id',
            $authenticatedId,
            PDO::PARAM_INT
        );
    }

    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        sendResponse(false, 'Order not found.', null, 404);
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER ADDRESSES
    |--------------------------------------------------------------------------
    */

    $addressStmt = $pdo->prepare(
        "SELECT
            id,
            order_id,
            user_address_id,
            address_type,
            door_no,
            street,
            area,
            city,
            district,
            state,
            pincode,
            landmark,
            created_at
         FROM order_addresses
         WHERE order_id=:order_id
         ORDER BY id ASC"
    );

    $addressStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $addressStmt->execute();

    $addressRows = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
    $addresses = [];

    foreach ($addressRows as $address) {
        $addresses[] = [
            'id' => (int)$address['id'],
            'order_id' => (int)$address['order_id'],
            'user_address_id' => $address['user_address_id'] !== null
                ? (int)$address['user_address_id']
                : null,
            'address_type' => $address['address_type'],
            'door_no' => $address['door_no'],
            'street' => $address['street'],
            'area' => $address['area'],
            'city' => $address['city'],
            'district' => $address['district'],
            'state' => $address['state'],
            'pincode' => $address['pincode'],
            'landmark' => $address['landmark'],
            'created_at' => $address['created_at']
        ];
    }

    $selectedAddress = $addresses[0] ?? null;

    /*
    |--------------------------------------------------------------------------
    | ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $itemStmt = $pdo->prepare(
        "SELECT
            oi.id,
            oi.order_id,
            oi.product_id,
            oi.variant_id,
            oi.product_name,
            oi.variant_name,
            oi.sku,
            oi.hsn_code,
            oi.size_name,
            oi.color_name,
            oi.original_price,
            oi.selling_price,
            oi.quantity,
            oi.product_discount_amount,
            oi.line_subtotal,
            oi.taxable_amount,
            oi.gst_rate,
            oi.cgst_rate,
            oi.cgst_amount,
            oi.sgst_rate,
            oi.sgst_amount,
            oi.igst_rate,
            oi.igst_amount,
            oi.tax_amount,
            oi.line_total,
            oi.item_status,
            oi.created_at,
            oi.updated_at,

            p.name AS current_product_name,
            p.slug AS current_product_slug,
            p.description AS current_product_description,
            p.category_id,
            p.hsn_profile_id,
            p.is_new_arrival,
            p.is_featured,
            p.is_best_seller,
            p.status AS current_product_status,
            p.created_at AS product_created_at,
            p.updated_at AS product_updated_at,

            c.name AS category_name,
            c.slug AS category_slug,
            c.description AS category_description,
            c.image AS category_image,
            c.sort_order AS category_sort_order,
            c.status AS category_status,

            hp.name AS hsn_profile_name,
            hp.hsn_code AS current_hsn_code,
            hp.description AS hsn_description,
            hp.status AS hsn_status,

            pv.size_id,
            pv.color_id,
            pv.sku AS current_sku,
            pv.variant_name AS current_variant_name,
            pv.original_price AS current_original_price,
            pv.discount_type AS current_discount_type,
            pv.discount_value AS current_discount_value,
            pv.selling_price AS current_selling_price,
            pv.gst_rate AS current_gst_rate,
            pv.gst_amount AS current_gst_amount,
            pv.price_with_tax AS current_price_with_tax,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,
            pv.created_at AS variant_created_at,
            pv.updated_at AS variant_updated_at,

            s.name AS current_size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,

            co.name AS current_color_name,
            co.hex_code,
            co.status AS color_status

         FROM order_items oi

         LEFT JOIN products p
            ON p.id=oi.product_id

         LEFT JOIN categories c
            ON c.id=p.category_id

         LEFT JOIN hsn_profiles hp
            ON hp.id=p.hsn_profile_id

         LEFT JOIN product_variants pv
            ON pv.id=oi.variant_id
           AND (
                oi.product_id IS NULL
                OR pv.product_id=oi.product_id
           )

         LEFT JOIN sizes s
            ON s.id=pv.size_id

         LEFT JOIN colors co
            ON co.id=pv.color_id

         WHERE oi.order_id=:order_id
         ORDER BY oi.id ASC"
    );

    $itemStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $itemStmt->execute();

    $itemRows = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | VARIANT IMAGES
    |--------------------------------------------------------------------------
    */

    $variantIds = [];

    foreach ($itemRows as $item) {
        if ($item['variant_id'] !== null) {
            $variantIds[(int)$item['variant_id']] = true;
        }
    }

    $imageMap = [];

    if ($variantIds) {
        $variantIds = array_keys($variantIds);
        $placeholders = [];

        foreach ($variantIds as $index => $variantId) {
            $placeholders[] = ':variant_id_' . $index;
        }

        $inSql = implode(',', $placeholders);

        $statusCondition = $accountType === 'user'
            ? " AND status='active'"
            : '';

        $imageStmt = $pdo->prepare(
            "SELECT
                id,
                variant_id,
                image,
                alt_text,
                is_primary,
                sort_order,
                status,
                created_at,
                updated_at
             FROM product_variant_images
             WHERE variant_id IN ({$inSql})
             {$statusCondition}
             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach ($variantIds as $index => $variantId) {
            $imageStmt->bindValue(
                ':variant_id_' . $index,
                $variantId,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
            $variantId = (int)$image['variant_id'];

            $imageMap[$variantId][] = [
                'id' => (int)$image['id'],
                'variant_id' => $variantId,
                'image' => $image['image'],
                'alt_text' => $image['alt_text'],
                'is_primary' => (int)$image['is_primary'],
                'sort_order' => (int)$image['sort_order'],
                'status' => $image['status'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at']
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ITEMS
    |--------------------------------------------------------------------------
    */

    $items = [];

    $totalQuantity = 0;
    $itemsSubtotal = 0.00;
    $itemsDiscount = 0.00;
    $itemsTax = 0.00;
    $itemsTotal = 0.00;

    foreach ($itemRows as $item) {
        $variantId = $item['variant_id'] !== null
            ? (int)$item['variant_id']
            : null;

        $images = $variantId !== null
            ? ($imageMap[$variantId] ?? [])
            : [];

        $primaryImage = null;

        foreach ($images as $image) {
            if ($image['is_primary'] === 1) {
                $primaryImage = $image;
                break;
            }
        }

        if ($primaryImage === null && $images) {
            $primaryImage = $images[0];
        }

        $stockQuantity = $item['stock_quantity'] !== null
            ? (int)$item['stock_quantity']
            : null;

        $reservedQuantity = $item['reserved_quantity'] !== null
            ? (int)$item['reserved_quantity']
            : null;

        $availableQuantity = null;
        $stockStatus = null;

        if (
            $stockQuantity !== null &&
            $reservedQuantity !== null
        ) {
            $availableQuantity = max(
                0,
                $stockQuantity - $reservedQuantity
            );

            if ($availableQuantity <= 0) {
                $stockStatus = 'out_of_stock';
            } elseif (
                $availableQuantity <=
                (int)$item['low_stock_limit']
            ) {
                $stockStatus = 'low_stock';
            } else {
                $stockStatus = 'in_stock';
            }
        }

        $quantity = (int)$item['quantity'];

        $totalQuantity += $quantity;
        $itemsSubtotal += (float)$item['line_subtotal'];
        $itemsDiscount += (float)$item['product_discount_amount'];
        $itemsTax += (float)$item['tax_amount'];
        $itemsTotal += (float)$item['line_total'];

        $items[] = [
            'id' => (int)$item['id'],
            'order_id' => (int)$item['order_id'],

            /*
            |--------------------------------------------------------------------------
            | HISTORICAL ORDER SNAPSHOT
            |--------------------------------------------------------------------------
            */

            'snapshot' => [
                'product_id' => $item['product_id'] !== null
                    ? (int)$item['product_id']
                    : null,

                'variant_id' => $variantId,

                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'],
                'sku' => $item['sku'],
                'hsn_code' => $item['hsn_code'],
                'size_name' => $item['size_name'],
                'color_name' => $item['color_name'],

                'pricing' => [
                    'original_price' =>
                        $item['original_price'],

                    'selling_price' =>
                        $item['selling_price'],

                    'quantity' =>
                        $quantity,

                    'product_discount_amount' =>
                        $item['product_discount_amount'],

                    'line_subtotal' =>
                        $item['line_subtotal'],

                    'taxable_amount' =>
                        $item['taxable_amount'],

                    'line_total' =>
                        $item['line_total']
                ],

                'tax' => [
                    'gst_rate' =>
                        $item['gst_rate'],

                    'cgst_rate' =>
                        $item['cgst_rate'],

                    'cgst_amount' =>
                        $item['cgst_amount'],

                    'sgst_rate' =>
                        $item['sgst_rate'],

                    'sgst_amount' =>
                        $item['sgst_amount'],

                    'igst_rate' =>
                        $item['igst_rate'],

                    'igst_amount' =>
                        $item['igst_amount'],

                    'tax_amount' =>
                        $item['tax_amount']
                ],

                'item_status' =>
                    $item['item_status']
            ],

            /*
            |--------------------------------------------------------------------------
            | CURRENT PRODUCT DATA
            |--------------------------------------------------------------------------
            */

            'current_product' => $item['product_id'] !== null ? [
                'id' =>
                    (int)$item['product_id'],

                'name' =>
                    $item['current_product_name'],

                'slug' =>
                    $item['current_product_slug'],

                'description' =>
                    $item['current_product_description'],

                'category_id' =>
                    $item['category_id'] !== null
                        ? (int)$item['category_id']
                        : null,

                'hsn_profile_id' =>
                    $item['hsn_profile_id'] !== null
                        ? (int)$item['hsn_profile_id']
                        : null,

                'is_new_arrival' =>
                    $item['is_new_arrival'] !== null
                        ? (int)$item['is_new_arrival']
                        : null,

                'is_featured' =>
                    $item['is_featured'] !== null
                        ? (int)$item['is_featured']
                        : null,

                'is_best_seller' =>
                    $item['is_best_seller'] !== null
                        ? (int)$item['is_best_seller']
                        : null,

                'status' =>
                    $item['current_product_status'],

                'created_at' =>
                    $item['product_created_at'],

                'updated_at' =>
                    $item['product_updated_at']
            ] : null,

            'category' => $item['category_id'] !== null ? [
                'id' =>
                    (int)$item['category_id'],

                'name' =>
                    $item['category_name'],

                'slug' =>
                    $item['category_slug'],

                'description' =>
                    $item['category_description'],

                'image' =>
                    $item['category_image'],

                'sort_order' =>
                    (int)$item['category_sort_order'],

                'status' =>
                    $item['category_status']
            ] : null,

            'hsn_profile' => $item['hsn_profile_id'] !== null ? [
                'id' =>
                    (int)$item['hsn_profile_id'],

                'name' =>
                    $item['hsn_profile_name'],

                'hsn_code' =>
                    $item['current_hsn_code'],

                'description' =>
                    $item['hsn_description'],

                'status' =>
                    $item['hsn_status']
            ] : null,

            /*
            |--------------------------------------------------------------------------
            | CURRENT VARIANT DATA
            |--------------------------------------------------------------------------
            */

            'current_variant' => $variantId !== null ? [
                'id' =>
                    $variantId,

                'sku' =>
                    $item['current_sku'],

                'variant_name' =>
                    $item['current_variant_name'],

                'size' => $item['size_id'] !== null ? [
                    'id' =>
                        (int)$item['size_id'],

                    'name' =>
                        $item['current_size_name'],

                    'sort_order' =>
                        (int)$item['size_sort_order'],

                    'status' =>
                        $item['size_status']
                ] : null,

                'color' => $item['color_id'] !== null ? [
                    'id' =>
                        (int)$item['color_id'],

                    'name' =>
                        $item['current_color_name'],

                    'hex_code' =>
                        $item['hex_code'],

                    'status' =>
                        $item['color_status']
                ] : null,

                'pricing' => [
                    'original_price' =>
                        $item['current_original_price'],

                    'gst_rate' =>
                        $item['current_gst_rate'],

                    'gst_amount' =>
                        $item['current_gst_amount'],

                    'price_with_tax' =>
                        $item['current_price_with_tax'],

                    'discount_type' =>
                        $item['current_discount_type'],

                    'discount_value' =>
                        $item['current_discount_value'],

                    'selling_price' =>
                        $item['current_selling_price']
                ],

                'stock' => [
                    'stock_quantity' =>
                        $stockQuantity,

                    'reserved_quantity' =>
                        $reservedQuantity,

                    'available_quantity' =>
                        $availableQuantity,

                    'low_stock_limit' =>
                        $item['low_stock_limit'] !== null
                            ? (int)$item['low_stock_limit']
                            : null,

                    'stock_status' =>
                        $stockStatus
                ],

                'is_available' =>
                    $item['is_available'] !== null
                        ? (int)$item['is_available']
                        : null,

                'primary_image' =>
                    $primaryImage,

                'images' =>
                    $images,

                'created_at' =>
                    $item['variant_created_at'],

                'updated_at' =>
                    $item['variant_updated_at']
            ] : null,

            'created_at' =>
                $item['created_at'],

            'updated_at' =>
                $item['updated_at']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COUPON MASTER DETAILS
    |--------------------------------------------------------------------------
    */

    $coupon = null;

    if ($order['coupon_type'] === 'birthday') {
        $coupon = [
            'type' => 'birthday',

            'id' => $order['coupon_id'] !== null
                ? (int)$order['coupon_id']
                : null,

            /*
             * Order time snapshot.
             */
            'order_snapshot_code' =>
                $order['coupon_code'],

            'discount_amount' =>
                $order['coupon_discount_amount'],

            /*
             * Current coupon master row.
             */
            'current_definition' =>
                $order['birthday_coupon_code'] !== null ? [
                    'coupon_code' =>
                        $order['birthday_coupon_code'],

                    'title' =>
                        $order['birthday_title'],

                    'description' =>
                        $order['birthday_description'],

                    'discount_type' =>
                        $order['birthday_discount_type'],

                    'discount_value' =>
                        $order['birthday_discount_value'],

                    'min_order_amount' =>
                        $order['birthday_min_order_amount'],

                    'max_discount_amount' =>
                        $order['birthday_max_discount_amount'],

                    'valid_before_days' =>
                        (int)$order['valid_before_days'],

                    'valid_after_days' =>
                        (int)$order['valid_after_days'],

                    'usage_limit_per_birthday' =>
                        (int)$order['usage_limit_per_birthday'],

                    'status' =>
                        $order['birthday_coupon_status']
                ] : null
        ];

    } elseif ($order['coupon_type'] === 'festival') {
        $coupon = [
            'type' => 'festival',

            'id' => $order['coupon_id'] !== null
                ? (int)$order['coupon_id']
                : null,

            'order_snapshot_code' =>
                $order['coupon_code'],

            'discount_amount' =>
                $order['coupon_discount_amount'],

            'current_definition' =>
                $order['festival_coupon_code'] !== null ? [
                    'festival_name' =>
                        $order['festival_name'],

                    'coupon_code' =>
                        $order['festival_coupon_code'],

                    'title' =>
                        $order['festival_title'],

                    'description' =>
                        $order['festival_description'],

                    'discount_type' =>
                        $order['festival_discount_type'],

                    'discount_value' =>
                        $order['festival_discount_value'],

                    'min_order_amount' =>
                        $order['festival_min_order_amount'],

                    'max_discount_amount' =>
                        $order['festival_max_discount_amount'],

                    'usage_limit' =>
                        $order['festival_usage_limit'] !== null
                            ? (int)$order['festival_usage_limit']
                            : null,

                    'usage_limit_per_user' =>
                        $order['festival_usage_limit_per_user'] !== null
                            ? (int)$order['festival_usage_limit_per_user']
                            : null,

                    'start_at' =>
                        $order['festival_start_at'],

                    'end_at' =>
                        $order['festival_end_at'],

                    'status' =>
                        $order['festival_coupon_status']
                ] : null
        ];

    } elseif ($order['coupon_type'] === 'referral') {
        $coupon = [
            'type' => 'referral',

            'id' => $order['coupon_id'] !== null
                ? (int)$order['coupon_id']
                : null,

            'order_snapshot_code' =>
                $order['coupon_code'],

            'discount_amount' =>
                $order['coupon_discount_amount'],

            'current_definition' =>
                $order['referral_code'] !== null ? [
                    'referral_code' =>
                        $order['referral_code'],

                    'title' =>
                        $order['referral_title'],

                    'description' =>
                        $order['referral_description'],

                    'discount_type' =>
                        $order['referral_discount_type'],

                    'discount_value' =>
                        $order['referral_discount_value'],

                    'min_order_amount' =>
                        $order['referral_min_order_amount'],

                    'max_discount_amount' =>
                        $order['referral_max_discount_amount'],

                    'usage_limit' =>
                        $order['referral_usage_limit'] !== null
                            ? (int)$order['referral_usage_limit']
                            : null,

                    'usage_limit_per_user' =>
                        $order['referral_usage_limit_per_user'] !== null
                            ? (int)$order['referral_usage_limit_per_user']
                            : null,

                    'start_at' =>
                        $order['referral_start_at'],

                    'end_at' =>
                        $order['referral_end_at'],

                    'status' =>
                        $order['referral_coupon_status']
                ] : null
        ];

    } elseif ($order['coupon_type'] === 'first_order') {
        $coupon = [
            'type' => 'first_order',

            'id' => $order['coupon_id'] !== null
                ? (int)$order['coupon_id']
                : null,

            'order_snapshot_code' =>
                $order['coupon_code'],

            'discount_amount' =>
                $order['coupon_discount_amount'],

            'current_definition' =>
                $order['first_order_coupon_code'] !== null ? [
                    'coupon_code' =>
                        $order['first_order_coupon_code'],

                    'title' =>
                        $order['first_order_title'],

                    'description' =>
                        $order['first_order_description'],

                    'discount_type' =>
                        $order['first_order_discount_type'],

                    'discount_value' =>
                        $order['first_order_discount_value'],

                    'min_order_amount' =>
                        $order['first_order_min_order_amount'],

                    'max_discount_amount' =>
                        $order['first_order_max_discount_amount'],

                    'start_at' =>
                        $order['first_order_start_at'],

                    'end_at' =>
                        $order['first_order_end_at'],

                    'status' =>
                        $order['first_order_coupon_status']
                ] : null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COUPON USAGE SNAPSHOT
    |--------------------------------------------------------------------------
    |
    | இது முக்கியம்.
    |
    | Example:
    | festival coupon id=1 Diwali use பண்ணினார்.
    | பின்னாடி admin same id=1 row-ஐ Pongal data-க்கு update பண்ணலாம்.
    |
    | Current master = Pongal
    | But usage snapshot = order time actual Diwali coupon values.
    |--------------------------------------------------------------------------
    */

    $couponUsage = null;

    if ($order['coupon_type'] === 'birthday') {
        $usageStmt = $pdo->prepare(
            "SELECT
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
             WHERE order_id=:order_id
             LIMIT 1"
        );

        $usageStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $usageStmt->execute();

        $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);

        if ($usage) {
            $couponUsage = [
                'id' => (int)$usage['id'],
                'coupon_type' => 'birthday',
                'birthday_coupon_id' =>
                    (int)$usage['birthday_coupon_id'],
                'user_id' =>
                    (int)$usage['user_id'],
                'order_id' =>
                    (int)$usage['order_id'],
                'coupon_code' =>
                    $usage['coupon_code'],
                'birthday_month' =>
                    (int)$usage['birthday_month'],
                'birthday_day' =>
                    (int)$usage['birthday_day'],
                'discount_type' =>
                    $usage['discount_type'],
                'discount_value' =>
                    $usage['discount_value'],
                'order_amount_before_discount' =>
                    $usage['order_amount_before_discount'],
                'discount_amount' =>
                    $usage['discount_amount'],
                'final_order_amount' =>
                    $usage['final_order_amount'],
                'used_at' =>
                    $usage['used_at'],
                'created_at' =>
                    $usage['created_at']
            ];
        }

    } elseif ($order['coupon_type'] === 'festival') {
        $usageStmt = $pdo->prepare(
            "SELECT
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
             WHERE order_id=:order_id
             LIMIT 1"
        );

        $usageStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $usageStmt->execute();

        $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);

        if ($usage) {
            $couponUsage = [
                'id' =>
                    (int)$usage['id'],
                'coupon_type' =>
                    'festival',
                'festival_coupon_id' =>
                    (int)$usage['festival_coupon_id'],
                'user_id' =>
                    (int)$usage['user_id'],
                'order_id' =>
                    (int)$usage['order_id'],
                'coupon_code' =>
                    $usage['coupon_code'],
                'discount_type' =>
                    $usage['discount_type'],
                'discount_value' =>
                    $usage['discount_value'],
                'order_amount_before_discount' =>
                    $usage['order_amount_before_discount'],
                'discount_amount' =>
                    $usage['discount_amount'],
                'final_order_amount' =>
                    $usage['final_order_amount'],
                'used_at' =>
                    $usage['used_at'],
                'created_at' =>
                    $usage['created_at']
            ];
        }

    } elseif ($order['coupon_type'] === 'referral') {
        $usageStmt = $pdo->prepare(
            "SELECT
                id,
                referral_coupon_id,
                user_id,
                order_id,
                referral_code,
                discount_type,
                discount_value,
                order_amount_before_discount,
                discount_amount,
                final_order_amount,
                used_at,
                created_at
             FROM referral_coupon_usage
             WHERE order_id=:order_id
             LIMIT 1"
        );

        $usageStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $usageStmt->execute();

        $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);

        if ($usage) {
            $couponUsage = [
                'id' =>
                    (int)$usage['id'],
                'coupon_type' =>
                    'referral',
                'referral_coupon_id' =>
                    (int)$usage['referral_coupon_id'],
                'user_id' =>
                    (int)$usage['user_id'],
                'order_id' =>
                    (int)$usage['order_id'],
                'referral_code' =>
                    $usage['referral_code'],
                'discount_type' =>
                    $usage['discount_type'],
                'discount_value' =>
                    $usage['discount_value'],
                'order_amount_before_discount' =>
                    $usage['order_amount_before_discount'],
                'discount_amount' =>
                    $usage['discount_amount'],
                'final_order_amount' =>
                    $usage['final_order_amount'],
                'used_at' =>
                    $usage['used_at'],
                'created_at' =>
                    $usage['created_at']
            ];
        }

    } elseif ($order['coupon_type'] === 'first_order') {
        $usageStmt = $pdo->prepare(
            "SELECT
                id,
                first_order_coupon_id,
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
             FROM first_order_coupon_usage
             WHERE order_id=:order_id
             LIMIT 1"
        );

        $usageStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $usageStmt->execute();

        $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);

        if ($usage) {
            $couponUsage = [
                'id' =>
                    (int)$usage['id'],
                'coupon_type' =>
                    'first_order',
                'first_order_coupon_id' =>
                    (int)$usage['first_order_coupon_id'],
                'user_id' =>
                    (int)$usage['user_id'],
                'order_id' =>
                    (int)$usage['order_id'],
                'coupon_code' =>
                    $usage['coupon_code'],
                'discount_type' =>
                    $usage['discount_type'],
                'discount_value' =>
                    $usage['discount_value'],
                'order_amount_before_discount' =>
                    $usage['order_amount_before_discount'],
                'discount_amount' =>
                    $usage['discount_amount'],
                'final_order_amount' =>
                    $usage['final_order_amount'],
                'used_at' =>
                    $usage['used_at'],
                'created_at' =>
                    $usage['created_at']
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    $response = [
        'id' =>
            (int)$order['id'],

        'order_number' =>
            $order['order_number'],

        'user_id' =>
            (int)$order['user_id'],

        'cart_id' =>
            $order['cart_id'] !== null
                ? (int)$order['cart_id']
                : null,

        'user' => [
            'id' =>
                (int)$order['user_id'],

            'name' =>
                $order['user_name'],

            'mobile' =>
                $order['user_mobile'],

            'email' =>
                $order['user_email'],

            'date_of_birth' =>
                $order['user_date_of_birth'],

            'status' =>
                $order['user_status'],

            'last_login' =>
                $order['user_last_login'],

            'created_at' =>
                $order['user_created_at'],

            'updated_at' =>
                $order['user_updated_at']
        ],

        'amounts' => [
            'subtotal' =>
                $order['subtotal'],

            'product_discount_amount' =>
                $order['product_discount_amount'],

            'coupon_discount_amount' =>
                $order['coupon_discount_amount'],

            'shipping_charge' =>
                $order['shipping_charge'],

            'cod_charge' =>
                $order['cod_charge'],

            'tax_amount' =>
                $order['tax_amount'],

            'grand_total' =>
                $order['grand_total']
        ],

        'coupon' =>
            $coupon,

        'coupon_usage_snapshot' =>
            $couponUsage,

        'payment' => [
            'method' =>
                $order['payment_method'],

            'status' =>
                $order['payment_status']
        ],

        'order_status' =>
            $order['order_status'],

        'customer_note' =>
            $order['customer_note'],

        'cancel_reason' =>
            $order['cancel_reason'],

        'address' =>
            $selectedAddress,

        'addresses' =>
            $addresses,

        'items' =>
            $items,

        'items_summary' => [
            'total_items' =>
                count($items),

            'total_quantity' =>
                $totalQuantity,

            'line_subtotal' =>
                number_format(
                    $itemsSubtotal,
                    2,
                    '.',
                    ''
                ),

            'product_discount_amount' =>
                number_format(
                    $itemsDiscount,
                    2,
                    '.',
                    ''
                ),

            'tax_amount' =>
                number_format(
                    $itemsTax,
                    2,
                    '.',
                    ''
                ),

            'line_total' =>
                number_format(
                    $itemsTotal,
                    2,
                    '.',
                    ''
                )
        ],

        'timeline' => [
            'placed_at' =>
                $order['placed_at'],

            'confirmed_at' =>
                $order['confirmed_at'],

            'delivered_at' =>
                $order['delivered_at'],

            'cancelled_at' =>
                $order['cancelled_at'],

            'created_at' =>
                $order['created_at'],

            'updated_at' =>
                $order['updated_at']
        ]
    ];

    sendResponse(
        true,
        'Order retrieved successfully.',
        [
            'order' => $response,

            'access' => [
                'account_type' =>
                    $accountType,

                'scope' =>
                    $accountType === 'admin'
                        ? 'all_orders'
                        : 'own_orders_only'
            ]
        ],
        200
    );

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve order.',
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