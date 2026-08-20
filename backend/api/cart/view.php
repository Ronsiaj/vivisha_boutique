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

$cartItemIdInput = isset($_GET['id'])
    ? trim((string)$_GET['id'])
    : '';

if ($cartItemIdInput === '') {
    sendResponse(false, 'Cart item ID is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $cartItemIdInput)) {
    sendResponse(false, 'Cart item ID must be a valid positive integer.', null, 422);
}

$cartItemId = (int)$cartItemIdInput;

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
            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.is_new_arrival,
            p.is_featured,
            p.is_best_seller,
            p.status AS product_status,
            p.created_at AS product_created_at,
            p.updated_at AS product_updated_at,

            c.name AS category_name,
            c.slug AS category_slug,
            c.description AS category_description,
            c.image AS category_image,
            c.sort_order AS category_sort_order,
            c.status AS category_status,
            c.created_at AS category_created_at,
            c.updated_at AS category_updated_at,

            pv.size_id,
            pv.color_id,
            pv.sku,
            pv.variant_name,
            pv.original_price,
            pv.discount_type,
            pv.discount_value,
            pv.selling_price,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,
            pv.created_at AS variant_created_at,
            pv.updated_at AS variant_updated_at,

            s.name AS size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,
            s.created_at AS size_created_at,
            s.updated_at AS size_updated_at,

            co.name AS color_name,
            co.hex_code,
            co.status AS color_status,
            co.created_at AS color_created_at,
            co.updated_at AS color_updated_at

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

    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $existsStmt = $pdo->prepare(
            "SELECT ci.id
             FROM cart_items ci
             INNER JOIN carts ca
                ON ca.id = ci.cart_id
             WHERE ci.id = :cart_item_id
             LIMIT 1"
        );

        $existsStmt->bindValue(':cart_item_id', $cartItemId, PDO::PARAM_INT);
        $existsStmt->execute();

        if ($existsStmt->fetch()) {
            sendResponse(
                false,
                'You are not allowed to view this cart item.',
                null,
                403
            );
        }

        sendResponse(false, 'Cart item not found.', null, 404);
    }

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
         WHERE variant_id = :variant_id
         AND status = 'active'
         ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC"
    );

    $imageStmt->bindValue(
        ':variant_id',
        (int)$item['variant_id'],
        PDO::PARAM_INT
    );

    $imageStmt->execute();

    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedImages = [];
    $primaryImage = null;

    foreach ($images as $image) {
        $imageData = [
            'id' => (int)$image['id'],
            'variant_id' => (int)$image['variant_id'],
            'image' => $image['image'],
            'alt_text' => $image['alt_text'],
            'is_primary' => (int)$image['is_primary'],
            'sort_order' => (int)$image['sort_order'],
            'status' => $image['status'],
            'created_at' => $image['created_at'],
            'updated_at' => $image['updated_at']
        ];

        $formattedImages[] = $imageData;

        if (
            $primaryImage === null &&
            (int)$image['is_primary'] === 1
        ) {
            $primaryImage = $imageData;
        }
    }

    if ($primaryImage === null && !empty($formattedImages)) {
        $primaryImage = $formattedImages[0];
    }

    $stockQuantity = (int)$item['stock_quantity'];
    $reservedQuantity = (int)$item['reserved_quantity'];
    $availableQuantity = $stockQuantity - $reservedQuantity;

    if ($availableQuantity < 0) {
        $availableQuantity = 0;
    }

    if ($availableQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($availableQuantity <= (int)$item['low_stock_limit']) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $quantity = (int)$item['quantity'];
    $cartUnitPrice = (float)$item['unit_price'];
    $currentSellingPrice = (float)$item['selling_price'];
    $lineTotal = $quantity * $cartUnitPrice;

    $priceChanged = round($cartUnitPrice, 2) !== round($currentSellingPrice, 2);

    $isPurchasable =
        $item['cart_status'] === 'active' &&
        $item['product_status'] === 'active' &&
        $item['category_status'] === 'active' &&
        (int)$item['is_available'] === 1 &&
        $availableQuantity >= $quantity &&
        (
            $item['size_id'] === null ||
            $item['size_status'] === 'active'
        ) &&
        (
            $item['color_id'] === null ||
            $item['color_status'] === 'active'
        );

    sendResponse(true, 'Cart item retrieved successfully.', [
        'cart_item' => [
            'id' => (int)$item['cart_item_id'],
            'cart_id' => (int)$item['cart_id'],
            'product_id' => (int)$item['product_id'],
            'variant_id' => (int)$item['variant_id'],

            'cart' => [
                'id' => (int)$item['cart_id'],
                'status' => $item['cart_status'],
                'created_at' => $item['cart_created_at'],
                'updated_at' => $item['cart_updated_at']
            ],

            'product' => [
                'id' => (int)$item['product_id'],
                'category_id' => (int)$item['category_id'],
                'name' => $item['product_name'],
                'slug' => $item['product_slug'],
                'description' => $item['product_description'],
                'is_new_arrival' => (int)$item['is_new_arrival'],
                'is_featured' => (int)$item['is_featured'],
                'is_best_seller' => (int)$item['is_best_seller'],
                'status' => $item['product_status'],
                'created_at' => $item['product_created_at'],
                'updated_at' => $item['product_updated_at']
            ],

            'category' => [
                'id' => (int)$item['category_id'],
                'name' => $item['category_name'],
                'slug' => $item['category_slug'],
                'description' => $item['category_description'],
                'image' => $item['category_image'],
                'sort_order' => (int)$item['category_sort_order'],
                'status' => $item['category_status'],
                'created_at' => $item['category_created_at'],
                'updated_at' => $item['category_updated_at']
            ],

            'variant' => [
                'id' => (int)$item['variant_id'],
                'sku' => $item['sku'],
                'variant_name' => $item['variant_name'],

                'size' => $item['size_id'] !== null
                    ? [
                        'id' => (int)$item['size_id'],
                        'name' => $item['size_name'],
                        'sort_order' => (int)$item['size_sort_order'],
                        'status' => $item['size_status'],
                        'created_at' => $item['size_created_at'],
                        'updated_at' => $item['size_updated_at']
                    ]
                    : null,

                'color' => $item['color_id'] !== null
                    ? [
                        'id' => (int)$item['color_id'],
                        'name' => $item['color_name'],
                        'hex_code' => $item['hex_code'],
                        'status' => $item['color_status'],
                        'created_at' => $item['color_created_at'],
                        'updated_at' => $item['color_updated_at']
                    ]
                    : null,

                'pricing' => [
                    'original_price' => $item['original_price'],
                    'discount_type' => $item['discount_type'],
                    'discount_value' => $item['discount_value'],
                    'current_selling_price' => $item['selling_price'],
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
                    'low_stock_limit' => (int)$item['low_stock_limit'],
                    'stock_status' => $stockStatus,
                    'requested_cart_quantity_available' =>
                        $availableQuantity >= $quantity
                ],

                'is_available' => (int)$item['is_available'],
                'primary_image' => $primaryImage,
                'images' => $formattedImages,
                'image_count' => count($formattedImages),

                'created_at' => $item['variant_created_at'],
                'updated_at' => $item['variant_updated_at']
            ],

            'quantity' => $quantity,
            'unit_price' => number_format($cartUnitPrice, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
            'is_purchasable' => $isPurchasable,
            'created_at' => $item['cart_item_created_at'],
            'updated_at' => $item['cart_item_updated_at']
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