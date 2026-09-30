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

if ($accountType !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
}

$allowedParams = ['id'];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$idInput = $_GET['id'] ?? null;

if ($idInput === null || $idInput === '') {
    sendResponse(false, 'id is required.', null, 422);
}

if (
    filter_var($idInput, FILTER_VALIDATE_INT) === false ||
    (int)$idInput <= 0
) {
    sendResponse(false, 'id must be a valid positive integer.', null, 422);
}

$cartItemId = (int)$idInput;

try {
    $stmt = $pdo->prepare(
        "SELECT
            ci.id AS cart_item_id,
            ci.cart_id,
            ci.product_id,
            ci.variant_id,
            ci.quantity,
            ci.unit_price,
            ci.created_at AS cart_item_created_at,
            ci.updated_at AS cart_item_updated_at,

            ca.user_id,
            ca.status AS cart_status,
            ca.created_at AS cart_created_at,
            ca.updated_at AS cart_updated_at,

            p.category_id,
            p.hsn_profile_id,
            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.is_new_arrival,
            p.is_featured,
            p.is_best_seller,
            p.status AS product_status,

            c.name AS category_name,
            c.slug AS category_slug,
            c.description AS category_description,
            c.image AS category_image,
            c.sort_order AS category_sort_order,
            c.status AS category_status,

            hp.name AS hsn_profile_name,
            hp.hsn_code,
            hp.description AS hsn_description,
            hp.status AS hsn_profile_status,

            pv.size_id,
            pv.color_id,
            pv.sku,
            pv.variant_name,
            pv.original_price,
            pv.discount_type,
            pv.discount_value,
            pv.selling_price,
            pv.gst_rate,
            pv.gst_amount,
            pv.price_with_tax,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,
            pv.created_at AS variant_created_at,
            pv.updated_at AS variant_updated_at,

            s.name AS size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,

            co.name AS color_name,
            co.hex_code,
            co.status AS color_status

         FROM cart_items ci

         INNER JOIN carts ca
            ON ca.id = ci.cart_id

         INNER JOIN products p
            ON p.id = ci.product_id

         INNER JOIN product_variants pv
            ON pv.id = ci.variant_id
            AND pv.product_id = ci.product_id

         INNER JOIN categories c
            ON c.id = p.category_id

         LEFT JOIN hsn_profiles hp
            ON hp.id = p.hsn_profile_id

         LEFT JOIN sizes s
            ON s.id = pv.size_id

         LEFT JOIN colors co
            ON co.id = pv.color_id

         WHERE ci.id = :cart_item_id
           AND ca.user_id = :user_id

         LIMIT 1"
    );

    $stmt->bindValue(':cart_item_id', $cartItemId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse(false, 'Cart item not found.', null, 404);
    }

    $imageStmt = $pdo->prepare(
        "SELECT
            id,
            image,
            alt_text,
            is_primary,
            sort_order,
            status,
            created_at,
            updated_at
         FROM product_variant_images
         WHERE variant_id = :variant_id
           AND status = 'active'
         ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC"
    );

    $imageStmt->bindValue(
        ':variant_id',
        (int)$row['variant_id'],
        PDO::PARAM_INT
    );

    $imageStmt->execute();

    $images = array_map(
        static function (array $image): array {
            return [
                'id' => (int)$image['id'],
                'image' => $image['image'],
                'alt_text' => $image['alt_text'],
                'is_primary' => (int)$image['is_primary'],
                'sort_order' => (int)$image['sort_order'],
                'status' => $image['status'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at']
            ];
        },
        $imageStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    $primaryImage = null;

    foreach ($images as $image) {
        if ($image['is_primary'] === 1) {
            $primaryImage = $image;
            break;
        }
    }

    if ($primaryImage === null && !empty($images)) {
        $primaryImage = $images[0];
    }

    $stockQuantity = (int)$row['stock_quantity'];
    $reservedQuantity = (int)$row['reserved_quantity'];
    $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

    if ($availableQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($availableQuantity <= (int)$row['low_stock_limit']) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $quantity = (int)$row['quantity'];
    $cartUnitPrice = (float)$row['unit_price'];
    $currentPriceWithTax = (float)$row['price_with_tax'];

    $lineTotal = $cartUnitPrice * $quantity;
    $currentLineTotal = $currentPriceWithTax * $quantity;

    $priceChanged = round($cartUnitPrice, 2) !== round($currentPriceWithTax, 2);

    $canCheckout =
        $row['cart_status'] === 'active' &&
        $row['product_status'] === 'active' &&
        (int)$row['is_available'] === 1 &&
        $availableQuantity >= $quantity;

    sendResponse(true, 'Cart item retrieved successfully.', [
        'cart_item' => [
            'id' => (int)$row['cart_item_id'],
            'cart_id' => (int)$row['cart_id'],
            'product_id' => (int)$row['product_id'],
            'variant_id' => (int)$row['variant_id'],

            'cart' => [
                'id' => (int)$row['cart_id'],
                'status' => $row['cart_status'],
                'created_at' => $row['cart_created_at'],
                'updated_at' => $row['cart_updated_at']
            ],

            'product' => [
                'id' => (int)$row['product_id'],
                'category_id' => (int)$row['category_id'],
                'hsn_profile_id' => $row['hsn_profile_id'] !== null
                    ? (int)$row['hsn_profile_id']
                    : null,
                'name' => $row['product_name'],
                'slug' => $row['product_slug'],
                'description' => $row['product_description'],
                'is_new_arrival' => (int)$row['is_new_arrival'],
                'is_featured' => (int)$row['is_featured'],
                'is_best_seller' => (int)$row['is_best_seller'],
                'status' => $row['product_status']
            ],

            'category' => [
                'id' => (int)$row['category_id'],
                'name' => $row['category_name'],
                'slug' => $row['category_slug'],
                'description' => $row['category_description'],
                'image' => $row['category_image'],
                'sort_order' => (int)$row['category_sort_order'],
                'status' => $row['category_status']
            ],

            'hsn_profile' => $row['hsn_profile_id'] !== null
                ? [
                    'id' => (int)$row['hsn_profile_id'],
                    'name' => $row['hsn_profile_name'],
                    'hsn_code' => $row['hsn_code'],
                    'description' => $row['hsn_description'],
                    'status' => $row['hsn_profile_status']
                ]
                : null,

            'variant' => [
                'id' => (int)$row['variant_id'],
                'sku' => $row['sku'],
                'variant_name' => $row['variant_name'],

                'size' => $row['size_id'] !== null
                    ? [
                        'id' => (int)$row['size_id'],
                        'name' => $row['size_name'],
                        'sort_order' => (int)$row['size_sort_order'],
                        'status' => $row['size_status']
                    ]
                    : null,

                'color' => $row['color_id'] !== null
                    ? [
                        'id' => (int)$row['color_id'],
                        'name' => $row['color_name'],
                        'hex_code' => $row['hex_code'],
                        'status' => $row['color_status']
                    ]
                    : null,

                'pricing' => [
                    'original_price' => $row['original_price'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value'],
                    'selling_price' => $row['selling_price'],
                    'gst_rate' => $row['gst_rate'],
                    'gst_amount' => $row['gst_amount'],
                    'price_with_tax' => $row['price_with_tax'],
                    'cart_unit_price' => number_format(
                        $cartUnitPrice,
                        2,
                        '.',
                        ''
                    ),
                    'price_changed' => $priceChanged
                ],

                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'available_quantity' => $availableQuantity,
                    'low_stock_limit' => (int)$row['low_stock_limit'],
                    'stock_status' => $stockStatus,
                    'requested_quantity_available' =>
                        $availableQuantity >= $quantity
                ],

                'is_available' => (int)$row['is_available'],
                'primary_image' => $primaryImage,
                'images' => $images,
                'created_at' => $row['variant_created_at'],
                'updated_at' => $row['variant_updated_at']
            ],

            'quantity' => $quantity,

            'pricing' => [
                'unit_price' => number_format(
                    $cartUnitPrice,
                    2,
                    '.',
                    ''
                ),
                'line_total' => number_format(
                    $lineTotal,
                    2,
                    '.',
                    ''
                ),
                'current_unit_price' => number_format(
                    $currentPriceWithTax,
                    2,
                    '.',
                    ''
                ),
                'current_line_total' => number_format(
                    $currentLineTotal,
                    2,
                    '.',
                    ''
                ),
                'price_changed' => $priceChanged
            ],

            'can_checkout' => $canCheckout,
            'created_at' => $row['cart_item_created_at'],
            'updated_at' => $row['cart_item_updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve cart item.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
} catch (Throwable $e) {
    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}