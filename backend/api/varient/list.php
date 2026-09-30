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

$bearerToken = getBearerToken();
$isAdminRequest = false;

if ($bearerToken !== null) {
    $decoded = authenticate();
    validateJWTData($decoded);

    $accountType = getAuthenticatedType($decoded);

    if (!in_array($accountType, ['admin', 'user'], true)) {
        sendResponse(false, 'Invalid account type in token.', null, 401);
    }

    $isAdminRequest = $accountType === 'admin';
}

function validatePositiveIdFilter(string $value, string $field): ?int
{
    if ($value === '') return null;

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }

    return (int)$value;
}

function validateBooleanFilter(string $value, string $field): ?int
{
    if ($value === '') return null;

    $value = strtolower(trim($value));

    if (in_array($value, ['1','true'], true)) return 1;
    if (in_array($value, ['0','false'], true)) return 0;

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return null;
}

function validatePriceFilter(string $value, string $field): ?float
{
    if ($value === '') return null;

    if (!is_numeric($value)) {
        sendResponse(false, "{$field} must be a valid number.", null, 422);
    }

    $price = round((float)$value, 2);

    if ($price < 0 || $price > 99999999.99) {
        sendResponse(false, "{$field} must be between 0 and 99999999.99.", null, 422);
    }

    return $price;
}

function validatePercentageFilter(string $value, string $field): ?float
{
    if ($value === '') return null;

    if (!is_numeric($value)) {
        sendResponse(false, "{$field} must be a valid number.", null, 422);
    }

    $value = round((float)$value, 2);

    if ($value < 0 || $value > 100) {
        sendResponse(false, "{$field} must be between 0 and 100.", null, 422);
    }

    return $value;
}

function validateDateFilter(string $value, string $field): void
{
    if ($value === '') return;

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

$allowedParams = [
    'q',
    'variant_id',
    'product_id',
    'category_id',
    'hsn_profile_id',
    'hsn_code',
    'size_id',
    'color_id',
    'gst_rate',
    'discount_type',
    'is_new_arrival',
    'is_featured',
    'is_best_seller',
    'min_price',
    'max_price',
    'min_original_price',
    'max_original_price',
    'min_price_with_tax',
    'max_price_with_tax',
    'stock_status',
    'created_from',
    'created_to',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
}

$variantId = validatePositiveIdFilter(
    trim((string)($_GET['variant_id'] ?? '')),
    'variant_id'
);

$productId = validatePositiveIdFilter(
    trim((string)($_GET['product_id'] ?? '')),
    'product_id'
);

$categoryId = validatePositiveIdFilter(
    trim((string)($_GET['category_id'] ?? '')),
    'category_id'
);

$hsnProfileId = validatePositiveIdFilter(
    trim((string)($_GET['hsn_profile_id'] ?? '')),
    'hsn_profile_id'
);

$sizeId = validatePositiveIdFilter(
    trim((string)($_GET['size_id'] ?? '')),
    'size_id'
);

$colorId = validatePositiveIdFilter(
    trim((string)($_GET['color_id'] ?? '')),
    'color_id'
);

$hsnCode = trim((string)($_GET['hsn_code'] ?? ''));

if ($hsnCode !== '' && !preg_match('/^(?:\d{4}|\d{6}|\d{8})$/', $hsnCode)) {
    sendResponse(false, 'hsn_code must contain exactly 4, 6 or 8 digits.', null, 422);
}

$gstRate = validatePercentageFilter(
    trim((string)($_GET['gst_rate'] ?? '')),
    'gst_rate'
);

$discountType = strtolower(
    trim((string)($_GET['discount_type'] ?? ''))
);

$allowedDiscountTypes = ['none','percentage','flat'];

if (
    $discountType !== '' &&
    !in_array($discountType, $allowedDiscountTypes, true)
) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_discount_types' => $allowedDiscountTypes
    ], 422);
}

$isNewArrival = validateBooleanFilter(
    trim((string)($_GET['is_new_arrival'] ?? '')),
    'is_new_arrival'
);

$isFeatured = validateBooleanFilter(
    trim((string)($_GET['is_featured'] ?? '')),
    'is_featured'
);

$isBestSeller = validateBooleanFilter(
    trim((string)($_GET['is_best_seller'] ?? '')),
    'is_best_seller'
);

$minPrice = validatePriceFilter(
    trim((string)($_GET['min_price'] ?? '')),
    'min_price'
);

$maxPrice = validatePriceFilter(
    trim((string)($_GET['max_price'] ?? '')),
    'max_price'
);

$minOriginalPrice = validatePriceFilter(
    trim((string)($_GET['min_original_price'] ?? '')),
    'min_original_price'
);

$maxOriginalPrice = validatePriceFilter(
    trim((string)($_GET['max_original_price'] ?? '')),
    'max_original_price'
);

$minPriceWithTax = validatePriceFilter(
    trim((string)($_GET['min_price_with_tax'] ?? '')),
    'min_price_with_tax'
);

$maxPriceWithTax = validatePriceFilter(
    trim((string)($_GET['max_price_with_tax'] ?? '')),
    'max_price_with_tax'
);

if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    sendResponse(false, 'min_price cannot be greater than max_price.', null, 422);
}

if (
    $minOriginalPrice !== null &&
    $maxOriginalPrice !== null &&
    $minOriginalPrice > $maxOriginalPrice
) {
    sendResponse(false, 'min_original_price cannot be greater than max_original_price.', null, 422);
}

if (
    $minPriceWithTax !== null &&
    $maxPriceWithTax !== null &&
    $minPriceWithTax > $maxPriceWithTax
) {
    sendResponse(false, 'min_price_with_tax cannot be greater than max_price_with_tax.', null, 422);
}

$stockStatus = strtolower(
    trim((string)($_GET['stock_status'] ?? ''))
);

$allowedStockStatuses = [
    'in_stock',
    'low_stock',
    'out_of_stock'
];

if (
    $stockStatus !== '' &&
    !in_array($stockStatus, $allowedStockStatuses, true)
) {
    sendResponse(false, 'Invalid stock_status.', [
        'allowed_stock_statuses' => $allowedStockStatuses
    ], 422);
}

$createdFrom = trim((string)($_GET['created_from'] ?? ''));
$createdTo = trim((string)($_GET['created_to'] ?? ''));

validateDateFilter($createdFrom, 'created_from');
validateDateFilter($createdTo, 'created_to');

if (
    $createdFrom !== '' &&
    $createdTo !== '' &&
    $createdFrom > $createdTo
) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (
    filter_var($pageInput, FILTER_VALIDATE_INT) === false ||
    (int)$pageInput <= 0
) {
    sendResponse(false, 'page must be a valid positive integer.', null, 422);
}

if (
    filter_var($limitInput, FILTER_VALIDATE_INT) === false ||
    (int)$limitInput <= 0 ||
    (int)$limitInput > 100
) {
    sendResponse(false, 'limit must be between 1 and 100.', null, 422);
}

$page = (int)$pageInput;
$limit = (int)$limitInput;
$offset = ($page - 1) * $limit;

$allowedSortColumns = [
    'id' => 'pv.id',
    'product_id' => 'pv.product_id',
    'sku' => 'pv.sku',
    'variant_name' => 'pv.variant_name',
    'original_price' => 'pv.original_price',
    'gst_rate' => 'pv.gst_rate',
    'gst_amount' => 'pv.gst_amount',
    'price_with_tax' => 'pv.price_with_tax',
    'discount_value' => 'pv.discount_value',
    'selling_price' => 'pv.selling_price',
    'stock_quantity' => 'pv.stock_quantity',
    'reserved_quantity' => 'pv.reserved_quantity',
    'low_stock_limit' => 'pv.low_stock_limit',
    'created_at' => 'pv.created_at',
    'updated_at' => 'pv.updated_at',
    'product_name' => 'p.name',
    'product_slug' => 'p.slug',
    'category_name' => 'c.name',
    'hsn_profile_name' => 'hp.name',
    'hsn_code' => 'hp.hsn_code',
    'size_name' => 's.name',
    'color_name' => 'co.name'
];

$sortBy = trim((string)($_GET['sort_by'] ?? 'created_at'));
$sortOrder = strtolower(
    trim((string)($_GET['sort_order'] ?? 'desc'))
);

if (!array_key_exists($sortBy, $allowedSortColumns)) {
    sendResponse(false, 'Invalid sort_by value.', [
        'allowed_values' => array_keys($allowedSortColumns)
    ], 422);
}

if (!in_array($sortOrder, ['asc','desc'], true)) {
    sendResponse(false, 'sort_order must be asc or desc.', null, 422);
}

$sortColumn = $allowedSortColumns[$sortBy];
$sqlSortOrder = strtoupper($sortOrder);

try {
    $where = [
        "p.status = 'active'"
    ];

    if (!$isAdminRequest) {
        $where[] = "c.status = 'active'";
        $where[] = "pv.is_available = 1";
    }

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

    if ($hsnProfileId !== null) {
        $where[] = 'p.hsn_profile_id = :hsn_profile_id';
        $params[':hsn_profile_id'] = $hsnProfileId;
    }

    if ($hsnCode !== '') {
        $where[] = 'hp.hsn_code = :hsn_code';
        $params[':hsn_code'] = $hsnCode;
    }

    if ($sizeId !== null) {
        $where[] = 'pv.size_id = :size_id';
        $params[':size_id'] = $sizeId;
    }

    if ($colorId !== null) {
        $where[] = 'pv.color_id = :color_id';
        $params[':color_id'] = $colorId;
    }

    if ($gstRate !== null) {
        $where[] = 'pv.gst_rate = :gst_rate';
        $params[':gst_rate'] = number_format($gstRate, 2, '.', '');
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

    if ($minPriceWithTax !== null) {
        $where[] = 'pv.price_with_tax >= :min_price_with_tax';
        $params[':min_price_with_tax'] = number_format($minPriceWithTax, 2, '.', '');
    }

    if ($maxPriceWithTax !== null) {
        $where[] = 'pv.price_with_tax <= :max_price_with_tax';
        $params[':max_price_with_tax'] = number_format($maxPriceWithTax, 2, '.', '');
    }

    if ($stockStatus === 'in_stock') {
        $where[] = "
            (pv.stock_quantity - pv.reserved_quantity) > pv.low_stock_limit
        ";
    } elseif ($stockStatus === 'low_stock') {
        $where[] = "
            (pv.stock_quantity - pv.reserved_quantity) > 0
            AND
            (pv.stock_quantity - pv.reserved_quantity) <= pv.low_stock_limit
        ";
    } elseif ($stockStatus === 'out_of_stock') {
        $where[] = '
            (pv.stock_quantity - pv.reserved_quantity) <= 0
        ';
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
        $search = '%' . $q . '%';

        $where[] = "(
            CAST(pv.id AS CHAR) LIKE :q_variant_id
            OR CAST(pv.product_id AS CHAR) LIKE :q_product_id
            OR CAST(p.category_id AS CHAR) LIKE :q_category_id
            OR CAST(p.hsn_profile_id AS CHAR) LIKE :q_hsn_profile_id
            OR CAST(pv.size_id AS CHAR) LIKE :q_size_id
            OR CAST(pv.color_id AS CHAR) LIKE :q_color_id
            OR pv.sku LIKE :q_sku
            OR pv.variant_name LIKE :q_variant_name
            OR p.name LIKE :q_product_name
            OR p.slug LIKE :q_product_slug
            OR p.description LIKE :q_product_description
            OR c.name LIKE :q_category_name
            OR c.slug LIKE :q_category_slug
            OR hp.name LIKE :q_hsn_name
            OR hp.hsn_code LIKE :q_hsn_code
            OR hp.description LIKE :q_hsn_description
            OR s.name LIKE :q_size_name
            OR co.name LIKE :q_color_name
            OR co.hex_code LIKE :q_hex_code
            OR pv.discount_type LIKE :q_discount_type
            OR CAST(pv.original_price AS CHAR) LIKE :q_original_price
            OR CAST(pv.gst_rate AS CHAR) LIKE :q_gst_rate
            OR CAST(pv.gst_amount AS CHAR) LIKE :q_gst_amount
            OR CAST(pv.price_with_tax AS CHAR) LIKE :q_price_with_tax
            OR CAST(pv.discount_value AS CHAR) LIKE :q_discount_value
            OR CAST(pv.selling_price AS CHAR) LIKE :q_selling_price
            OR CAST(pv.stock_quantity AS CHAR) LIKE :q_stock_quantity
            OR CAST(pv.reserved_quantity AS CHAR) LIKE :q_reserved_quantity
            OR CAST(pv.low_stock_limit AS CHAR) LIKE :q_low_stock_limit
            OR DATE_FORMAT(pv.created_at, '%Y-%m-%d %H:%i:%s') LIKE :q_created_at
            OR DATE_FORMAT(pv.updated_at, '%Y-%m-%d %H:%i:%s') LIKE :q_updated_at
        )";

        $params[':q_variant_id'] = $search;
        $params[':q_product_id'] = $search;
        $params[':q_category_id'] = $search;
        $params[':q_hsn_profile_id'] = $search;
        $params[':q_size_id'] = $search;
        $params[':q_color_id'] = $search;
        $params[':q_sku'] = $search;
        $params[':q_variant_name'] = $search;
        $params[':q_product_name'] = $search;
        $params[':q_product_slug'] = $search;
        $params[':q_product_description'] = $search;
        $params[':q_category_name'] = $search;
        $params[':q_category_slug'] = $search;
        $params[':q_hsn_name'] = $search;
        $params[':q_hsn_code'] = $search;
        $params[':q_hsn_description'] = $search;
        $params[':q_size_name'] = $search;
        $params[':q_color_name'] = $search;
        $params[':q_hex_code'] = $search;
        $params[':q_discount_type'] = $search;
        $params[':q_original_price'] = $search;
        $params[':q_gst_rate'] = $search;
        $params[':q_gst_amount'] = $search;
        $params[':q_price_with_tax'] = $search;
        $params[':q_discount_value'] = $search;
        $params[':q_selling_price'] = $search;
        $params[':q_stock_quantity'] = $search;
        $params[':q_reserved_quantity'] = $search;
        $params[':q_low_stock_limit'] = $search;
        $params[':q_created_at'] = $search;
        $params[':q_updated_at'] = $search;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $baseFrom = "
        FROM product_variants pv
        INNER JOIN products p ON p.id = pv.product_id
        INNER JOIN categories c ON c.id = p.category_id
        LEFT JOIN hsn_profiles hp ON hp.id = p.hsn_profile_id
        LEFT JOIN sizes s ON s.id = pv.size_id
        LEFT JOIN colors co ON co.id = pv.color_id
    ";

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         {$baseFrom}
         {$whereSql}"
    );

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

    $stmt = $pdo->prepare(
        "SELECT
            pv.id,
            pv.product_id,
            pv.size_id,
            pv.color_id,
            pv.sku,
            pv.variant_name,
            pv.original_price,
            pv.gst_rate,
            pv.gst_amount,
            pv.price_with_tax,
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
            p.hsn_profile_id,
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

            hp.name AS hsn_profile_name,
            hp.hsn_code,
            hp.description AS hsn_description,
            hp.status AS hsn_profile_status,
            hp.created_at AS hsn_created_at,
            hp.updated_at AS hsn_updated_at,

            s.name AS size_name,
            s.sort_order AS size_sort_order,
            s.status AS size_status,

            co.name AS color_name,
            co.hex_code AS color_hex_code,
            co.status AS color_status

         {$baseFrom}
         {$whereSql}

         ORDER BY {$sortColumn} {$sqlSortOrder}, pv.id DESC
         LIMIT :limit OFFSET :offset"
    );

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
    $imagesByVariant = [];

    if ($variants) {
        $variantIds = array_map(
            static fn(array $variant): int => (int)$variant['id'],
            $variants
        );

        $placeholders = [];

        foreach ($variantIds as $index => $id) {
            $placeholders[] = ':variant_' . $index;
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
             WHERE variant_id IN (" . implode(',', $placeholders) . ")
               AND status = 'active'
             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach ($variantIds as $index => $id) {
            $imageStmt->bindValue(
                ':variant_' . $index,
                $id,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
            $id = (int)$image['variant_id'];

            $imagesByVariant[$id][] = [
                'id' => (int)$image['id'],
                'variant_id' => $id,
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

    $formattedVariants = [];

    foreach ($variants as $variant) {
        $id = (int)$variant['id'];

        $stockQuantity = (int)$variant['stock_quantity'];
        $reservedQuantity = (int)$variant['reserved_quantity'];
        $availableQuantity = max(
            0,
            $stockQuantity - $reservedQuantity
        );

        if ($availableQuantity <= 0) {
            $calculatedStockStatus = 'out_of_stock';
        } elseif ($availableQuantity <= (int)$variant['low_stock_limit']) {
            $calculatedStockStatus = 'low_stock';
        } else {
            $calculatedStockStatus = 'in_stock';
        }

        $images = $imagesByVariant[$id] ?? [];
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

        $discountAmount = max(
            0,
            round(
                (float)$variant['price_with_tax'] -
                (float)$variant['selling_price'],
                2
            )
        );

        $formattedVariants[] = [
            'id' => $id,
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

            'hsn_profile' => $variant['hsn_profile_id'] !== null
                ? [
                    'id' => (int)$variant['hsn_profile_id'],
                    'name' => $variant['hsn_profile_name'],
                    'hsn_code' => $variant['hsn_code'],
                    'description' => $variant['hsn_description'],
                    'status' => $variant['hsn_profile_status'],
                    'created_at' => $variant['hsn_created_at'],
                    'updated_at' => $variant['hsn_updated_at']
                ]
                : null,

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
                'gst_rate' => $variant['gst_rate'],
                'gst_amount' => $variant['gst_amount'],
                'price_with_tax' => $variant['price_with_tax'],
                'discount_type' => $variant['discount_type'],
                'discount_value' => $variant['discount_value'],
                'discount_amount' => number_format(
                    $discountAmount,
                    2,
                    '.',
                    ''
                ),
                'selling_price' => $variant['selling_price']
            ],

            'stock' => [
                'stock_quantity' => $stockQuantity,
                'reserved_quantity' => $reservedQuantity,
                'available_quantity' => $availableQuantity,
                'low_stock_limit' => (int)$variant['low_stock_limit'],
                'stock_status' => $calculatedStockStatus
            ],

            'is_available' => (int)$variant['is_available'],
            'primary_image' => $primaryImage,
            'images' => $images,
            'image_count' => count($images),
            'created_at' => $variant['created_at'],
            'updated_at' => $variant['updated_at']
        ];
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
            'hsn_profile_id' => $hsnProfileId,
            'hsn_code' => $hsnCode !== '' ? $hsnCode : null,
            'size_id' => $sizeId,
            'color_id' => $colorId,
            'gst_rate' => $gstRate,
            'discount_type' => $discountType !== '' ? $discountType : null,
            'is_new_arrival' => $isNewArrival,
            'is_featured' => $isFeatured,
            'is_best_seller' => $isBestSeller,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'min_original_price' => $minOriginalPrice,
            'max_original_price' => $maxOriginalPrice,
            'min_price_with_tax' => $minPriceWithTax,
            'max_price_with_tax' => $maxPriceWithTax,
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