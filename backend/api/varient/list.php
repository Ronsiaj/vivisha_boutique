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

function validatePositiveIdFilter(string $value, string $field): ?int
{
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }

    return (int)$value;
}

function validateBooleanFilter(string $value, string $field): ?int
{
    if ($value === '') {
        return null;
    }

    $value = strtolower($value);

    if (in_array($value, ['1', 'true'], true)) {
        return 1;
    }

    if (in_array($value, ['0', 'false'], true)) {
        return 0;
    }

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);

    return null;
}

function validatePriceFilter(string $value, string $field): ?float
{
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        sendResponse(false, "{$field} must be a valid number.", null, 422);
    }

    $price = (float)$value;

    if ($price < 0 || $price > 99999999.99) {
        sendResponse(false, "{$field} must be between 0 and 99999999.99.", null, 422);
    }

    return round($price, 2);
}

function validateDateFilter(string $value, string $field): void
{
    if ($value === '') {
        return;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    $hasErrors = $errors !== false && (
        $errors['warning_count'] > 0 ||
        $errors['error_count'] > 0
    );

    if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
}

$variantId = validatePositiveIdFilter(
    isset($_GET['variant_id']) ? trim((string)$_GET['variant_id']) : '',
    'variant_id'
);

$productId = validatePositiveIdFilter(
    isset($_GET['product_id']) ? trim((string)$_GET['product_id']) : '',
    'product_id'
);

$categoryId = validatePositiveIdFilter(
    isset($_GET['category_id']) ? trim((string)$_GET['category_id']) : '',
    'category_id'
);

$sizeId = validatePositiveIdFilter(
    isset($_GET['size_id']) ? trim((string)$_GET['size_id']) : '',
    'size_id'
);

$colorId = validatePositiveIdFilter(
    isset($_GET['color_id']) ? trim((string)$_GET['color_id']) : '',
    'color_id'
);

$discountType = isset($_GET['discount_type'])
    ? strtolower(trim((string)$_GET['discount_type']))
    : '';

$allowedDiscountTypes = ['none', 'percentage', 'flat'];

if ($discountType !== '' && !in_array($discountType, $allowedDiscountTypes, true)) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_discount_types' => $allowedDiscountTypes
    ], 422);
}

$isNewArrival = validateBooleanFilter(
    isset($_GET['is_new_arrival']) ? trim((string)$_GET['is_new_arrival']) : '',
    'is_new_arrival'
);

$isFeatured = validateBooleanFilter(
    isset($_GET['is_featured']) ? trim((string)$_GET['is_featured']) : '',
    'is_featured'
);

$isBestSeller = validateBooleanFilter(
    isset($_GET['is_best_seller']) ? trim((string)$_GET['is_best_seller']) : '',
    'is_best_seller'
);

$minPrice = validatePriceFilter(
    isset($_GET['min_price']) ? trim((string)$_GET['min_price']) : '',
    'min_price'
);

$maxPrice = validatePriceFilter(
    isset($_GET['max_price']) ? trim((string)$_GET['max_price']) : '',
    'max_price'
);

$minOriginalPrice = validatePriceFilter(
    isset($_GET['min_original_price']) ? trim((string)$_GET['min_original_price']) : '',
    'min_original_price'
);

$maxOriginalPrice = validatePriceFilter(
    isset($_GET['max_original_price']) ? trim((string)$_GET['max_original_price']) : '',
    'max_original_price'
);

if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    sendResponse(false, 'min_price cannot be greater than max_price.', null, 422);
}

if (
    $minOriginalPrice !== null &&
    $maxOriginalPrice !== null &&
    $minOriginalPrice > $maxOriginalPrice
) {
    sendResponse(
        false,
        'min_original_price cannot be greater than max_original_price.',
        null,
        422
    );
}

$stockStatus = isset($_GET['stock_status'])
    ? strtolower(trim((string)$_GET['stock_status']))
    : '';

$allowedStockStatuses = [
    'in_stock',
    'out_of_stock',
    'low_stock'
];

if ($stockStatus !== '' && !in_array($stockStatus, $allowedStockStatuses, true)) {
    sendResponse(false, 'Invalid stock_status.', [
        'allowed_stock_statuses' => $allowedStockStatuses
    ], 422);
}

$createdFrom = isset($_GET['created_from'])
    ? trim((string)$_GET['created_from'])
    : '';

$createdTo = isset($_GET['created_to'])
    ? trim((string)$_GET['created_to'])
    : '';

validateDateFilter($createdFrom, 'created_from');
validateDateFilter($createdTo, 'created_to');

if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (filter_var($pageInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Page must be a valid integer.', null, 422);
}

$page = (int)$pageInput;

if ($page < 1) {
    sendResponse(false, 'Page must be greater than 0.', null, 422);
}

if (filter_var($limitInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Limit must be a valid integer.', null, 422);
}

$limit = (int)$limitInput;

if ($limit < 1 || $limit > 100) {
    sendResponse(false, 'Limit must be between 1 and 100.', null, 422);
}

$offset = ($page - 1) * $limit;

$allowedSortColumns = [
    'id' => 'pv.id',
    'product_id' => 'pv.product_id',
    'sku' => 'pv.sku',
    'variant_name' => 'pv.variant_name',
    'original_price' => 'pv.original_price',
    'discount_value' => 'pv.discount_value',
    'selling_price' => 'pv.selling_price',
    'stock_quantity' => 'pv.stock_quantity',
    'reserved_quantity' => 'pv.reserved_quantity',
    'low_stock_limit' => 'pv.low_stock_limit',
    'created_at' => 'pv.created_at',
    'updated_at' => 'pv.updated_at',
    'product_name' => 'p.name',
    'category_name' => 'c.name',
    'size_name' => 's.name',
    'color_name' => 'co.name'
];

$sortBy = isset($_GET['sort_by'])
    ? trim((string)$_GET['sort_by'])
    : 'created_at';

$sortOrder = isset($_GET['sort_order'])
    ? strtolower(trim((string)$_GET['sort_order']))
    : 'desc';

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_values' => array_keys($allowedSortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$sortColumn = $allowedSortColumns[$sortBy];

try {
    $where = [
        "p.status = 'active'",
        "c.status = 'active'",
        "pv.is_available = 1"
    ];

    $params = [];

    if ($variantId !== null) {
        $where[] = 'pv.id = :variant_id';
        $params[':variant_id'] = $variantId;
    }

    if ($productId !== null) {
        $where[] = 'pv.product_id = :product_id';
        $params[':product_id'] = $productId;
    }

    if ($categoryId !== null) {
        $where[] = 'p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    if ($sizeId !== null) {
        $where[] = 'pv.size_id = :size_id';
        $params[':size_id'] = $sizeId;
    }

    if ($colorId !== null) {
        $where[] = 'pv.color_id = :color_id';
        $params[':color_id'] = $colorId;
    }

    if ($discountType !== '') {
        $where[] = 'pv.discount_type = :discount_type';
        $params[':discount_type'] = $discountType;
    }

    if ($isNewArrival !== null) {
        $where[] = 'p.is_new_arrival = :is_new_arrival';
        $params[':is_new_arrival'] = $isNewArrival;
    }

    if ($isFeatured !== null) {
        $where[] = 'p.is_featured = :is_featured';
        $params[':is_featured'] = $isFeatured;
    }

    if ($isBestSeller !== null) {
        $where[] = 'p.is_best_seller = :is_best_seller';
        $params[':is_best_seller'] = $isBestSeller;
    }

    if ($minPrice !== null) {
        $where[] = 'pv.selling_price >= :min_price';
        $params[':min_price'] = number_format($minPrice, 2, '.', '');
    }

    if ($maxPrice !== null) {
        $where[] = 'pv.selling_price <= :max_price';
        $params[':max_price'] = number_format($maxPrice, 2, '.', '');
    }

    if ($minOriginalPrice !== null) {
        $where[] = 'pv.original_price >= :min_original_price';
        $params[':min_original_price'] = number_format($minOriginalPrice, 2, '.', '');
    }

    if ($maxOriginalPrice !== null) {
        $where[] = 'pv.original_price <= :max_original_price';
        $params[':max_original_price'] = number_format($maxOriginalPrice, 2, '.', '');
    }

    if ($stockStatus === 'in_stock') {
        $where[] = '(pv.stock_quantity - pv.reserved_quantity) > 0';
    } elseif ($stockStatus === 'out_of_stock') {
        $where[] = '(pv.stock_quantity - pv.reserved_quantity) <= 0';
    } elseif ($stockStatus === 'low_stock') {
        $where[] = "
            (pv.stock_quantity - pv.reserved_quantity) > 0
            AND
            (pv.stock_quantity - pv.reserved_quantity) <= pv.low_stock_limit
        ";
    }

    if ($createdFrom !== '') {
        $where[] = 'pv.created_at >= :created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'pv.created_at <= :created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    if ($q !== '') {
        $where[] = "(
            CAST(pv.id AS CHAR) LIKE :q_variant_id
            OR CAST(pv.product_id AS CHAR) LIKE :q_product_id
            OR CAST(p.category_id AS CHAR) LIKE :q_category_id
            OR CAST(pv.size_id AS CHAR) LIKE :q_size_id
            OR CAST(pv.color_id AS CHAR) LIKE :q_color_id
            OR pv.sku LIKE :q_sku
            OR pv.variant_name LIKE :q_variant_name
            OR p.name LIKE :q_product_name
            OR p.slug LIKE :q_product_slug
            OR p.description LIKE :q_product_description
            OR c.name LIKE :q_category_name
            OR c.slug LIKE :q_category_slug
            OR s.name LIKE :q_size_name
            OR co.name LIKE :q_color_name
            OR co.hex_code LIKE :q_hex_code
            OR pv.discount_type LIKE :q_discount_type
            OR CAST(pv.original_price AS CHAR) LIKE :q_original_price
            OR CAST(pv.discount_value AS CHAR) LIKE :q_discount_value
            OR CAST(pv.selling_price AS CHAR) LIKE :q_selling_price
            OR CAST(pv.stock_quantity AS CHAR) LIKE :q_stock_quantity
            OR CAST(pv.reserved_quantity AS CHAR) LIKE :q_reserved_quantity
            OR CAST(pv.low_stock_limit AS CHAR) LIKE :q_low_stock_limit
            OR DATE_FORMAT(pv.created_at, '%Y-%m-%d %H:%i:%s') LIKE :q_created_at
            OR DATE_FORMAT(pv.updated_at, '%Y-%m-%d %H:%i:%s') LIKE :q_updated_at
        )";

        $searchValue = '%' . $q . '%';

        $params[':q_variant_id'] = $searchValue;
        $params[':q_product_id'] = $searchValue;
        $params[':q_category_id'] = $searchValue;
        $params[':q_size_id'] = $searchValue;
        $params[':q_color_id'] = $searchValue;
        $params[':q_sku'] = $searchValue;
        $params[':q_variant_name'] = $searchValue;
        $params[':q_product_name'] = $searchValue;
        $params[':q_product_slug'] = $searchValue;
        $params[':q_product_description'] = $searchValue;
        $params[':q_category_name'] = $searchValue;
        $params[':q_category_slug'] = $searchValue;
        $params[':q_size_name'] = $searchValue;
        $params[':q_color_name'] = $searchValue;
        $params[':q_hex_code'] = $searchValue;
        $params[':q_discount_type'] = $searchValue;
        $params[':q_original_price'] = $searchValue;
        $params[':q_discount_value'] = $searchValue;
        $params[':q_selling_price'] = $searchValue;
        $params[':q_stock_quantity'] = $searchValue;
        $params[':q_reserved_quantity'] = $searchValue;
        $params[':q_low_stock_limit'] = $searchValue;
        $params[':q_created_at'] = $searchValue;
        $params[':q_updated_at'] = $searchValue;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        INNER JOIN categories c ON c.id = p.category_id
        LEFT JOIN sizes s ON s.id = pv.size_id
        LEFT JOIN colors co ON co.id = pv.color_id
        $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = $totalRecords > 0
        ? (int)ceil($totalRecords / $limit)
        : 0;

    if ($totalPages > 0 && $page > $totalPages) {
        sendResponse(false, 'Requested page exceeds total available pages.', [
            'requested_page' => $page,
            'total_pages' => $totalPages
        ], 422);
    }

    $sql = "
        SELECT
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
            s.name AS size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,
            co.name AS color_name,
            co.hex_code AS color_hex_code,
            co.status AS color_status
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        INNER JOIN categories c ON c.id = p.category_id
        LEFT JOIN sizes s ON s.id = pv.size_id
        LEFT JOIN colors co ON co.id = pv.color_id
        $whereSql
        ORDER BY $sortColumn $sortOrder, pv.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $formattedVariants = [];

    if (!empty($variants)) {
        $variantIds = array_map(
            static fn(array $variant): int => (int)$variant['id'],
            $variants
        );

        $placeholders = [];

        foreach ($variantIds as $index => $id) {
            $placeholders[] = ':variant_image_id_' . $index;
        }

        $imageSql = "
            SELECT
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
            WHERE variant_id IN (" . implode(',', $placeholders) . ")
            AND status = 'active'
            ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC
        ";

        $imageStmt = $pdo->prepare($imageSql);

        foreach ($variantIds as $index => $id) {
            $imageStmt->bindValue(
                ':variant_image_id_' . $index,
                $id,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        $allImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
        $imagesByVariant = [];

        foreach ($allImages as $image) {
            $imageVariantId = (int)$image['variant_id'];

            if (!isset($imagesByVariant[$imageVariantId])) {
                $imagesByVariant[$imageVariantId] = [];
            }

            $imagesByVariant[$imageVariantId][] = [
                'id' => (int)$image['id'],
                'variant_id' => $imageVariantId,
                'image' => $image['image'],
                'alt_text' => $image['alt_text'],
                'is_primary' => (int)$image['is_primary'],
                'sort_order' => (int)$image['sort_order'],
                'status' => $image['status'],
                'created_at' => $image['created_at'],
                'updated_at' => $image['updated_at']
            ];
        }

        foreach ($variants as $variant) {
            $variantIdValue = (int)$variant['id'];

            $availableStock =
                (int)$variant['stock_quantity'] -
                (int)$variant['reserved_quantity'];

            if ($availableStock < 0) {
                $availableStock = 0;
            }

            if ($availableStock <= 0) {
                $calculatedStockStatus = 'out_of_stock';
            } elseif ($availableStock <= (int)$variant['low_stock_limit']) {
                $calculatedStockStatus = 'low_stock';
            } else {
                $calculatedStockStatus = 'in_stock';
            }

            $variantImages = $imagesByVariant[$variantIdValue] ?? [];
            $primaryImage = null;

            foreach ($variantImages as $image) {
                if ($image['is_primary'] === 1) {
                    $primaryImage = $image;
                    break;
                }
            }

            if ($primaryImage === null && !empty($variantImages)) {
                $primaryImage = $variantImages[0];
            }

            $formattedVariants[] = [
                'id' => $variantIdValue,
                'sku' => $variant['sku'],
                'variant_name' => $variant['variant_name'],
                'product' => [
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
                'size' => $variant['size_id'] !== null
                    ? [
                        'id' => (int)$variant['size_id'],
                        'name' => $variant['size_name'],
                        'sort_order' => (int)$variant['size_sort_order'],
                        'status' => $variant['size_status']
                    ]
                    : null,
                'color' => $variant['color_id'] !== null
                    ? [
                        'id' => (int)$variant['color_id'],
                        'name' => $variant['color_name'],
                        'hex_code' => $variant['color_hex_code'],
                        'status' => $variant['color_status']
                    ]
                    : null,
                'pricing' => [
                    'original_price' => $variant['original_price'],
                    'discount_type' => $variant['discount_type'],
                    'discount_value' => $variant['discount_value'],
                    'selling_price' => $variant['selling_price']
                ],
                'stock' => [
                    'stock_quantity' => (int)$variant['stock_quantity'],
                    'reserved_quantity' => (int)$variant['reserved_quantity'],
                    'available_quantity' => $availableStock,
                    'low_stock_limit' => (int)$variant['low_stock_limit'],
                    'stock_status' => $calculatedStockStatus
                ],
                'is_available' => (int)$variant['is_available'],
                'primary_image' => $primaryImage,
                'images' => $variantImages,
                'image_count' => count($variantImages),
                'created_at' => $variant['created_at'],
                'updated_at' => $variant['updated_at']
            ];
        }
    }

    sendResponse(true, 'Product variants retrieved successfully.', [
        'variants' => $formattedVariants,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ],
        'filters' => [
            'q' => $q !== '' ? $q : null,
            'variant_id' => $variantId,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'size_id' => $sizeId,
            'color_id' => $colorId,
            'discount_type' => $discountType !== '' ? $discountType : null,
            'is_new_arrival' => $isNewArrival,
            'is_featured' => $isFeatured,
            'is_best_seller' => $isBestSeller,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'min_original_price' => $minOriginalPrice,
            'max_original_price' => $maxOriginalPrice,
            'stock_status' => $stockStatus !== '' ? $stockStatus : null,
            'created_from' => $createdFrom !== '' ? $createdFrom : null,
            'created_to' => $createdTo !== '' ? $createdTo : null
        ],
        'sorting' => [
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to retrieve product variants.',
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