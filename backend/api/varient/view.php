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

$variantId = (int)$idInput;

try {
    $stmt = $pdo->prepare(
        "SELECT
            pv.id,pv.product_id,pv.size_id,pv.color_id,pv.sku,pv.variant_name,
            pv.original_price,pv.gst_rate,pv.gst_amount,pv.price_with_tax,
            pv.discount_type,pv.discount_value,pv.selling_price,
            pv.stock_quantity,pv.reserved_quantity,pv.low_stock_limit,
            pv.is_available,pv.created_at,pv.updated_at,
            p.category_id,p.hsn_profile_id,p.name AS product_name,
            p.slug AS product_slug,p.description AS product_description,
            p.is_new_arrival,p.is_featured,p.is_best_seller,
            p.status AS product_status,p.created_at AS product_created_at,
            p.updated_at AS product_updated_at,
            c.name AS category_name,c.slug AS category_slug,
            c.description AS category_description,c.image AS category_image,
            c.sort_order AS category_sort_order,c.status AS category_status,
            hp.name AS hsn_profile_name,hp.hsn_code,
            hp.description AS hsn_description,hp.status AS hsn_profile_status,
            hp.created_at AS hsn_created_at,hp.updated_at AS hsn_updated_at,
            s.name AS size_name,s.sort_order AS size_sort_order,
            s.status AS size_status,
            co.name AS color_name,co.hex_code AS color_hex_code,
            co.status AS color_status
         FROM product_variants pv
         INNER JOIN products p ON p.id=pv.product_id
         INNER JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors co ON co.id=pv.color_id
         WHERE pv.id=:id
           AND pv.is_available=1
           AND p.status='active'
           AND c.status='active'
         LIMIT 1"
    );

    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        sendResponse(false, 'Product variant not found or unavailable.', null, 404);
    }

    $stmt = $pdo->prepare(
        "SELECT id,variant_id,image,alt_text,is_primary,sort_order,status,created_at,updated_at
         FROM product_variant_images
         WHERE variant_id=:variant_id AND status='active'
         ORDER BY is_primary DESC,sort_order ASC,id ASC"
    );

    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $images = array_map(static fn(array $image): array => [
        'id' => (int)$image['id'],
        'variant_id' => (int)$image['variant_id'],
        'image' => $image['image'],
        'alt_text' => $image['alt_text'],
        'is_primary' => (int)$image['is_primary'],
        'sort_order' => (int)$image['sort_order'],
        'status' => $image['status'],
        'created_at' => $image['created_at'],
        'updated_at' => $image['updated_at']
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

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

    $stockQuantity = (int)$variant['stock_quantity'];
    $reservedQuantity = (int)$variant['reserved_quantity'];
    $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

    if ($availableQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($availableQuantity <= (int)$variant['low_stock_limit']) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $originalPrice = (float)$variant['original_price'];
    $gstRate = (float)$variant['gst_rate'];
    $gstAmount = (float)$variant['gst_amount'];
    $priceWithTax = (float)$variant['price_with_tax'];
    $discountValue = (float)$variant['discount_value'];
    $sellingPrice = (float)$variant['selling_price'];

    $discountAmount = max(
        0,
        round($priceWithTax - $sellingPrice, 2)
    );

    $discountPercentage = 0.00;

    if ($variant['discount_type'] === 'percentage') {
        $discountPercentage = $discountValue;
    } elseif (
        $variant['discount_type'] === 'flat' &&
        $priceWithTax > 0
    ) {
        $discountPercentage = round(
            ($discountAmount / $priceWithTax) * 100,
            2
        );
    }

    sendResponse(true, 'Product variant retrieved successfully.', [
        'variant' => [
            'id' => (int)$variant['id'],
            'sku' => $variant['sku'],
            'variant_name' => $variant['variant_name'],
            'product' => [
                'id' => (int)$variant['product_id'],
                'category_id' => (int)$variant['category_id'],
                'hsn_profile_id' => $variant['hsn_profile_id'] !== null
                    ? (int)$variant['hsn_profile_id']
                    : null,
                'name' => $variant['product_name'],
                'slug' => $variant['product_slug'],
                'description' => $variant['product_description'],
                'is_new_arrival' => (int)$variant['is_new_arrival'],
                'is_featured' => (int)$variant['is_featured'],
                'is_best_seller' => (int)$variant['is_best_seller'],
                'status' => $variant['product_status'],
                'created_at' => $variant['product_created_at'],
                'updated_at' => $variant['product_updated_at']
            ],
            'category' => [
                'id' => (int)$variant['category_id'],
                'name' => $variant['category_name'],
                'slug' => $variant['category_slug'],
                'description' => $variant['category_description'],
                'image' => $variant['category_image'],
                'sort_order' => (int)$variant['category_sort_order'],
                'status' => $variant['category_status']
            ],
            'hsn_profile' => $variant['hsn_profile_id'] !== null ? [
                'id' => (int)$variant['hsn_profile_id'],
                'name' => $variant['hsn_profile_name'],
                'hsn_code' => $variant['hsn_code'],
                'description' => $variant['hsn_description'],
                'status' => $variant['hsn_profile_status'],
                'created_at' => $variant['hsn_created_at'],
                'updated_at' => $variant['hsn_updated_at']
            ] : null,
            'size' => $variant['size_id'] !== null ? [
                'id' => (int)$variant['size_id'],
                'name' => $variant['size_name'],
                'sort_order' => (int)$variant['size_sort_order'],
                'status' => $variant['size_status']
            ] : null,
            'color' => $variant['color_id'] !== null ? [
                'id' => (int)$variant['color_id'],
                'name' => $variant['color_name'],
                'hex_code' => $variant['color_hex_code'],
                'status' => $variant['color_status']
            ] : null,
            'pricing' => [
                'original_price' => number_format($originalPrice, 2, '.', ''),
                'gst_rate' => number_format($gstRate, 2, '.', ''),
                'gst_amount' => number_format($gstAmount, 2, '.', ''),
                'price_with_tax' => number_format($priceWithTax, 2, '.', ''),
                'discount_type' => $variant['discount_type'],
                'discount_value' => number_format($discountValue, 2, '.', ''),
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'effective_discount_percentage' => number_format(
                    $discountPercentage,
                    2,
                    '.',
                    ''
                ),
                'selling_price' => number_format($sellingPrice, 2, '.', '')
            ],
            'stock' => [
                'stock_quantity' => $stockQuantity,
                'reserved_quantity' => $reservedQuantity,
                'available_quantity' => $availableQuantity,
                'low_stock_limit' => (int)$variant['low_stock_limit'],
                'stock_status' => $stockStatus
            ],
            'is_available' => (int)$variant['is_available'],
            'primary_image' => $primaryImage,
            'images' => $images,
            'image_count' => count($images),
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