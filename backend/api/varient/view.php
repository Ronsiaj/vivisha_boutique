<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method is allowed.', null, 405);
}

$variantIdInput = isset($_GET['id'])
    ? trim((string)$_GET['id'])
    : '';

if ($variantIdInput === '') {
    sendResponse(false, 'Variant ID is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $variantIdInput)) {
    sendResponse(false, 'Variant ID must be a valid positive integer.', null, 422);
}

$variantId = (int)$variantIdInput;

try {
    $stmt = $pdo->prepare(
        "SELECT
            pv.id,
            pv.product_id,
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
            pv.created_at,
            pv.updated_at,

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

            s.name AS size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,
            s.created_at AS size_created_at,
            s.updated_at AS size_updated_at,

            co.name AS color_name,
            co.hex_code AS color_hex_code,
            co.status AS color_status,
            co.created_at AS color_created_at,
            co.updated_at AS color_updated_at

        FROM product_variants pv

        INNER JOIN products p
            ON p.id = pv.product_id

        INNER JOIN categories c
            ON c.id = p.category_id

        LEFT JOIN sizes s
            ON s.id = pv.size_id

        LEFT JOIN colors co
            ON co.id = pv.color_id

        WHERE pv.id = :id
        AND p.status = 'active'
        AND c.status = 'active'
        AND pv.is_available = 1

        LIMIT 1"
    );

    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        $existsStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE id = :id
             LIMIT 1"
        );

        $existsStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
        $existsStmt->execute();

        if ($existsStmt->fetch()) {
            sendResponse(
                false,
                'This product variant is not available.',
                null,
                404
            );
        }

        sendResponse(false, 'Product variant not found.', null, 404);
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
        $variantId,
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

    $stockQuantity = (int)$variant['stock_quantity'];
    $reservedQuantity = (int)$variant['reserved_quantity'];
    $availableQuantity = $stockQuantity - $reservedQuantity;

    if ($availableQuantity < 0) {
        $availableQuantity = 0;
    }

    if ($availableQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($availableQuantity <= (int)$variant['low_stock_limit']) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $productData = [
        'id' => (int)$variant['product_id'],
        'category_id' => (int)$variant['category_id'],
        'name' => $variant['product_name'],
        'slug' => $variant['product_slug'],
        'description' => $variant['product_description'],
        'is_new_arrival' => (int)$variant['is_new_arrival'],
        'is_featured' => (int)$variant['is_featured'],
        'is_best_seller' => (int)$variant['is_best_seller'],
        'status' => $variant['product_status'],
        'created_at' => $variant['product_created_at'],
        'updated_at' => $variant['product_updated_at']
    ];

    $categoryData = [
        'id' => (int)$variant['category_id'],
        'name' => $variant['category_name'],
        'slug' => $variant['category_slug'],
        'description' => $variant['category_description'],
        'image' => $variant['category_image'],
        'sort_order' => (int)$variant['category_sort_order'],
        'status' => $variant['category_status'],
        'created_at' => $variant['category_created_at'],
        'updated_at' => $variant['category_updated_at']
    ];

    $sizeData = null;

    if ($variant['size_id'] !== null) {
        $sizeData = [
            'id' => (int)$variant['size_id'],
            'name' => $variant['size_name'],
            'sort_order' => (int)$variant['size_sort_order'],
            'status' => $variant['size_status'],
            'created_at' => $variant['size_created_at'],
            'updated_at' => $variant['size_updated_at']
        ];
    }

    $colorData = null;

    if ($variant['color_id'] !== null) {
        $colorData = [
            'id' => (int)$variant['color_id'],
            'name' => $variant['color_name'],
            'hex_code' => $variant['color_hex_code'],
            'status' => $variant['color_status'],
            'created_at' => $variant['color_created_at'],
            'updated_at' => $variant['color_updated_at']
        ];
    }

    sendResponse(true, 'Product variant retrieved successfully.', [
        'variant' => [
            'id' => (int)$variant['id'],
            'product_id' => (int)$variant['product_id'],
            'size_id' => $variant['size_id'] !== null
                ? (int)$variant['size_id']
                : null,
            'color_id' => $variant['color_id'] !== null
                ? (int)$variant['color_id']
                : null,
            'sku' => $variant['sku'],
            'variant_name' => $variant['variant_name'],

            'product' => $productData,
            'category' => $categoryData,
            'size' => $sizeData,
            'color' => $colorData,

            'pricing' => [
                'original_price' => $variant['original_price'],
                'discount_type' => $variant['discount_type'],
                'discount_value' => $variant['discount_value'],
                'selling_price' => $variant['selling_price'],
                'has_discount' =>
                    $variant['discount_type'] !== 'none' &&
                    (float)$variant['discount_value'] > 0
            ],

            'stock' => [
                'stock_quantity' => $stockQuantity,
                'reserved_quantity' => $reservedQuantity,
                'available_quantity' => $availableQuantity,
                'low_stock_limit' => (int)$variant['low_stock_limit'],
                'stock_status' => $stockStatus,
                'is_in_stock' => $availableQuantity > 0,
                'is_low_stock' =>
                    $availableQuantity > 0 &&
                    $availableQuantity <= (int)$variant['low_stock_limit']
            ],

            'is_available' => (int)$variant['is_available'],

            'primary_image' => $primaryImage,
            'images' => $formattedImages,
            'image_count' => count($formattedImages),

            'created_at' => $variant['created_at'],
            'updated_at' => $variant['updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve product variant.',
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