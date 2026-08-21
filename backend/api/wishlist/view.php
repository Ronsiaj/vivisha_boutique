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

$wishlistIdInput = isset($_GET['id'])
    ? trim((string)$_GET['id'])
    : '';

if ($wishlistIdInput === '') {
    sendResponse(false, 'Wishlist ID is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $wishlistIdInput)) {
    sendResponse(false, 'Wishlist ID must be a valid positive integer.', null, 422);
}

$wishlistId = (int)$wishlistIdInput;

try {
    $stmt = $pdo->prepare(
        "SELECT
            w.id AS wishlist_id,
            w.user_id,
            w.product_id,
            w.variant_id,
            w.created_at AS wishlist_created_at,
            w.updated_at AS wishlist_updated_at,

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

         FROM wishlists w

         INNER JOIN products p
            ON p.id = w.product_id

         INNER JOIN product_variants pv
            ON pv.id = w.variant_id
            AND pv.product_id = w.product_id

         INNER JOIN categories c
            ON c.id = p.category_id

         LEFT JOIN sizes s
            ON s.id = pv.size_id

         LEFT JOIN colors co
            ON co.id = pv.color_id

         WHERE w.id = :wishlist_id
         AND w.user_id = :user_id

         LIMIT 1"
    );

    $stmt->bindValue(':wishlist_id', $wishlistId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wishlist) {
        $existsStmt = $pdo->prepare(
            "SELECT id, user_id
             FROM wishlists
             WHERE id = :wishlist_id
             LIMIT 1"
        );

        $existsStmt->bindValue(':wishlist_id', $wishlistId, PDO::PARAM_INT);
        $existsStmt->execute();

        $existingWishlist = $existsStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingWishlist) {
            sendResponse(
                false,
                'You are not allowed to view this wishlist item.',
                null,
                403
            );
        }

        sendResponse(false, 'Wishlist item not found.', null, 404);
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
        (int)$wishlist['variant_id'],
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

    $stockQuantity = (int)$wishlist['stock_quantity'];
    $reservedQuantity = (int)$wishlist['reserved_quantity'];

    $availableQuantity = $stockQuantity - $reservedQuantity;

    if ($availableQuantity < 0) {
        $availableQuantity = 0;
    }

    if ($availableQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif (
        $availableQuantity <=
        (int)$wishlist['low_stock_limit']
    ) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $isPurchasable =
        $wishlist['product_status'] === 'active' &&
        $wishlist['category_status'] === 'active' &&
        (int)$wishlist['is_available'] === 1 &&
        $availableQuantity > 0 &&
        (
            $wishlist['size_id'] === null ||
            $wishlist['size_status'] === 'active'
        ) &&
        (
            $wishlist['color_id'] === null ||
            $wishlist['color_status'] === 'active'
        );

    $hasDiscount =
        $wishlist['discount_type'] !== 'none' &&
        (float)$wishlist['discount_value'] > 0 &&
        (float)$wishlist['selling_price'] <
        (float)$wishlist['original_price'];

    sendResponse(true, 'Wishlist item retrieved successfully.', [
        'wishlist' => [
            'id' => (int)$wishlist['wishlist_id'],
            'product_id' => (int)$wishlist['product_id'],
            'variant_id' => (int)$wishlist['variant_id'],

            'product' => [
                'id' => (int)$wishlist['product_id'],
                'category_id' => (int)$wishlist['category_id'],
                'name' => $wishlist['product_name'],
                'slug' => $wishlist['product_slug'],
                'description' => $wishlist['product_description'],
                'is_new_arrival' => (int)$wishlist['is_new_arrival'],
                'is_featured' => (int)$wishlist['is_featured'],
                'is_best_seller' => (int)$wishlist['is_best_seller'],
                'status' => $wishlist['product_status'],
                'created_at' => $wishlist['product_created_at'],
                'updated_at' => $wishlist['product_updated_at']
            ],

            'category' => [
                'id' => (int)$wishlist['category_id'],
                'name' => $wishlist['category_name'],
                'slug' => $wishlist['category_slug'],
                'description' => $wishlist['category_description'],
                'image' => $wishlist['category_image'],
                'sort_order' => (int)$wishlist['category_sort_order'],
                'status' => $wishlist['category_status'],
                'created_at' => $wishlist['category_created_at'],
                'updated_at' => $wishlist['category_updated_at']
            ],

            'variant' => [
                'id' => (int)$wishlist['variant_id'],
                'sku' => $wishlist['sku'],
                'variant_name' => $wishlist['variant_name'],

                'size' => $wishlist['size_id'] !== null
                    ? [
                        'id' => (int)$wishlist['size_id'],
                        'name' => $wishlist['size_name'],
                        'sort_order' => (int)$wishlist['size_sort_order'],
                        'status' => $wishlist['size_status'],
                        'created_at' => $wishlist['size_created_at'],
                        'updated_at' => $wishlist['size_updated_at']
                    ]
                    : null,

                'color' => $wishlist['color_id'] !== null
                    ? [
                        'id' => (int)$wishlist['color_id'],
                        'name' => $wishlist['color_name'],
                        'hex_code' => $wishlist['hex_code'],
                        'status' => $wishlist['color_status'],
                        'created_at' => $wishlist['color_created_at'],
                        'updated_at' => $wishlist['color_updated_at']
                    ]
                    : null,

                'pricing' => [
                    'original_price' => $wishlist['original_price'],
                    'discount_type' => $wishlist['discount_type'],
                    'discount_value' => $wishlist['discount_value'],
                    'selling_price' => $wishlist['selling_price'],
                    'has_discount' => $hasDiscount
                ],

                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'available_quantity' => $availableQuantity,
                    'low_stock_limit' => (int)$wishlist['low_stock_limit'],
                    'stock_status' => $stockStatus,
                    'is_in_stock' => $availableQuantity > 0,
                    'is_low_stock' =>
                        $availableQuantity > 0 &&
                        $availableQuantity <= (int)$wishlist['low_stock_limit']
                ],

                'is_available' => (int)$wishlist['is_available'],
                'is_purchasable' => $isPurchasable,

                'primary_image' => $primaryImage,
                'images' => $formattedImages,
                'image_count' => count($formattedImages),

                'created_at' => $wishlist['variant_created_at'],
                'updated_at' => $wishlist['variant_updated_at']
            ],

            'created_at' => $wishlist['wishlist_created_at'],
            'updated_at' => $wishlist['wishlist_updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve wishlist item.',
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