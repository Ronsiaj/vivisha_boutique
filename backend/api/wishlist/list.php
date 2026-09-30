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

if (getAuthenticatedId($decoded) <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}
if (getAuthenticatedType($decoded) !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
}

function wishlistPositiveId(string $value, string $field): ?int
{
    if ($value === '') return null;
    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }
    return (int)$value;
}

function wishlistBoolean(string $value, string $field): ?int
{
    if ($value === '') return null;
    $value = strtolower(trim($value));
    if (in_array($value, ['1','true'], true)) return 1;
    if (in_array($value, ['0','false'], true)) return 0;
    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return null;
}

function wishlistPrice(string $value, string $field): ?float
{
    if ($value === '') return null;
    if (!is_numeric($value)) {
        sendResponse(false, "{$field} must be a valid number.", null, 422);
    }
    $value = round((float)$value, 2);
    if ($value < 0 || $value > 99999999.99) {
        sendResponse(false, "{$field} must be between 0 and 99999999.99.", null, 422);
    }
    return $value;
}

function wishlistPercentage(string $value, string $field): ?float
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

function wishlistDate(string $value, string $field): void
{
    if ($value === '') return;
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    if (
        !$date ||
        ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
        $date->format('Y-m-d') !== $value
    ) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

$allowedParams = [
    'q','wishlist_id','product_id','variant_id','category_id','hsn_profile_id',
    'hsn_code','size_id','color_id','is_available','is_new_arrival',
    'is_featured','is_best_seller','gst_rate','discount_type','product_status',
    'min_price','max_price','min_original_price','max_original_price',
    'min_price_with_tax','max_price_with_tax','stock_status','created_from',
    'created_to','page','limit','sort_by','sort_order'
];

foreach (array_keys($_GET) as $param) {
    if (!in_array($param, $allowedParams, true)) {
        sendResponse(false, "Invalid query parameter: {$param}.", [
            'allowed_parameters' => $allowedParams
        ], 422);
    }
}

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '' && mb_strlen($q) > 200) {
    sendResponse(false, 'Search query must not exceed 200 characters.', null, 422);
}

$wishlistId = wishlistPositiveId(trim((string)($_GET['wishlist_id'] ?? '')), 'wishlist_id');
$productId = wishlistPositiveId(trim((string)($_GET['product_id'] ?? '')), 'product_id');
$variantId = wishlistPositiveId(trim((string)($_GET['variant_id'] ?? '')), 'variant_id');
$categoryId = wishlistPositiveId(trim((string)($_GET['category_id'] ?? '')), 'category_id');
$hsnProfileId = wishlistPositiveId(trim((string)($_GET['hsn_profile_id'] ?? '')), 'hsn_profile_id');
$sizeId = wishlistPositiveId(trim((string)($_GET['size_id'] ?? '')), 'size_id');
$colorId = wishlistPositiveId(trim((string)($_GET['color_id'] ?? '')), 'color_id');

$hsnCode = trim((string)($_GET['hsn_code'] ?? ''));

if ($hsnCode !== '' && !preg_match('/^(?:\d{4}|\d{6}|\d{8})$/', $hsnCode)) {
    sendResponse(false, 'hsn_code must contain exactly 4, 6 or 8 digits.', null, 422);
}

$isAvailable = wishlistBoolean(trim((string)($_GET['is_available'] ?? '')), 'is_available');
$isNewArrival = wishlistBoolean(trim((string)($_GET['is_new_arrival'] ?? '')), 'is_new_arrival');
$isFeatured = wishlistBoolean(trim((string)($_GET['is_featured'] ?? '')), 'is_featured');
$isBestSeller = wishlistBoolean(trim((string)($_GET['is_best_seller'] ?? '')), 'is_best_seller');
$gstRate = wishlistPercentage(trim((string)($_GET['gst_rate'] ?? '')), 'gst_rate');

$discountType = strtolower(trim((string)($_GET['discount_type'] ?? '')));
$allowedDiscountTypes = ['none','percentage','flat'];

if ($discountType !== '' && !in_array($discountType, $allowedDiscountTypes, true)) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_discount_types' => $allowedDiscountTypes
    ], 422);
}

$productStatus = strtolower(trim((string)($_GET['product_status'] ?? '')));

if ($productStatus !== '' && !in_array($productStatus, ['active','inactive'], true)) {
    sendResponse(false, 'Invalid product_status.', [
        'allowed_statuses' => ['active','inactive']
    ], 422);
}

$minPrice = wishlistPrice(trim((string)($_GET['min_price'] ?? '')), 'min_price');
$maxPrice = wishlistPrice(trim((string)($_GET['max_price'] ?? '')), 'max_price');
$minOriginalPrice = wishlistPrice(trim((string)($_GET['min_original_price'] ?? '')), 'min_original_price');
$maxOriginalPrice = wishlistPrice(trim((string)($_GET['max_original_price'] ?? '')), 'max_original_price');
$minPriceWithTax = wishlistPrice(trim((string)($_GET['min_price_with_tax'] ?? '')), 'min_price_with_tax');
$maxPriceWithTax = wishlistPrice(trim((string)($_GET['max_price_with_tax'] ?? '')), 'max_price_with_tax');

if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    sendResponse(false, 'min_price cannot be greater than max_price.', null, 422);
}
if ($minOriginalPrice !== null && $maxOriginalPrice !== null && $minOriginalPrice > $maxOriginalPrice) {
    sendResponse(false, 'min_original_price cannot be greater than max_original_price.', null, 422);
}
if ($minPriceWithTax !== null && $maxPriceWithTax !== null && $minPriceWithTax > $maxPriceWithTax) {
    sendResponse(false, 'min_price_with_tax cannot be greater than max_price_with_tax.', null, 422);
}

$stockStatus = strtolower(trim((string)($_GET['stock_status'] ?? '')));
$allowedStockStatuses = ['in_stock','low_stock','out_of_stock'];

if ($stockStatus !== '' && !in_array($stockStatus, $allowedStockStatuses, true)) {
    sendResponse(false, 'Invalid stock_status.', [
        'allowed_stock_statuses' => $allowedStockStatuses
    ], 422);
}

$createdFrom = trim((string)($_GET['created_from'] ?? ''));
$createdTo = trim((string)($_GET['created_to'] ?? ''));

wishlistDate($createdFrom, 'created_from');
wishlistDate($createdTo, 'created_to');

if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
    sendResponse(false, 'created_from cannot be greater than created_to.', null, 422);
}

$pageInput = $_GET['page'] ?? '1';
$limitInput = $_GET['limit'] ?? '20';

if (filter_var($pageInput, FILTER_VALIDATE_INT) === false || (int)$pageInput <= 0) {
    sendResponse(false, 'page must be a valid positive integer.', null, 422);
}

if (
    filter_var($limitInput, FILTER_VALIDATE_INT) === false ||
    (int)$limitInput < 1 ||
    (int)$limitInput > 100
) {
    sendResponse(false, 'limit must be between 1 and 100.', null, 422);
}

$page = (int)$pageInput;
$limit = (int)$limitInput;
$offset = ($page - 1) * $limit;

$allowedSortColumns = [
    'id' => 'w.id',
    'product_id' => 'w.product_id',
    'variant_id' => 'w.variant_id',
    'created_at' => 'w.created_at',
    'updated_at' => 'w.updated_at',
    'product_name' => 'p.name',
    'product_slug' => 'p.slug',
    'category_name' => 'c.name',
    'hsn_profile_name' => 'hp.name',
    'hsn_code' => 'hp.hsn_code',
    'variant_name' => 'pv.variant_name',
    'original_price' => 'pv.original_price',
    'gst_rate' => 'pv.gst_rate',
    'gst_amount' => 'pv.gst_amount',
    'price_with_tax' => 'pv.price_with_tax',
    'discount_value' => 'pv.discount_value',
    'selling_price' => 'pv.selling_price',
    'stock_quantity' => 'pv.stock_quantity',
    'size_name' => 's.name',
    'color_name' => 'co.name'
];

$sortBy = trim((string)($_GET['sort_by'] ?? 'created_at'));
$sortOrder = strtolower(trim((string)($_GET['sort_order'] ?? 'desc')));

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
    $where = ['w.user_id=:user_id'];
    $params = [':user_id' => $userId];

    if ($wishlistId !== null) {
        $where[] = 'w.id=:wishlist_id';
        $params[':wishlist_id'] = $wishlistId;
    }
    if ($productId !== null) {
        $where[] = 'w.product_id=:product_id';
        $params[':product_id'] = $productId;
    }
    if ($variantId !== null) {
        $where[] = 'w.variant_id=:variant_id';
        $params[':variant_id'] = $variantId;
    }
    if ($categoryId !== null) {
        $where[] = 'p.category_id=:category_id';
        $params[':category_id'] = $categoryId;
    }
    if ($hsnProfileId !== null) {
        $where[] = 'p.hsn_profile_id=:hsn_profile_id';
        $params[':hsn_profile_id'] = $hsnProfileId;
    }
    if ($hsnCode !== '') {
        $where[] = 'hp.hsn_code=:hsn_code';
        $params[':hsn_code'] = $hsnCode;
    }
    if ($sizeId !== null) {
        $where[] = 'pv.size_id=:size_id';
        $params[':size_id'] = $sizeId;
    }
    if ($colorId !== null) {
        $where[] = 'pv.color_id=:color_id';
        $params[':color_id'] = $colorId;
    }
    if ($isAvailable !== null) {
        $where[] = 'pv.is_available=:is_available';
        $params[':is_available'] = $isAvailable;
    }
    if ($isNewArrival !== null) {
        $where[] = 'p.is_new_arrival=:is_new_arrival';
        $params[':is_new_arrival'] = $isNewArrival;
    }
    if ($isFeatured !== null) {
        $where[] = 'p.is_featured=:is_featured';
        $params[':is_featured'] = $isFeatured;
    }
    if ($isBestSeller !== null) {
        $where[] = 'p.is_best_seller=:is_best_seller';
        $params[':is_best_seller'] = $isBestSeller;
    }
    if ($gstRate !== null) {
        $where[] = 'pv.gst_rate=:gst_rate';
        $params[':gst_rate'] = number_format($gstRate, 2, '.', '');
    }
    if ($discountType !== '') {
        $where[] = 'pv.discount_type=:discount_type';
        $params[':discount_type'] = $discountType;
    }
    if ($productStatus !== '') {
        $where[] = 'p.status=:product_status';
        $params[':product_status'] = $productStatus;
    }
    if ($minPrice !== null) {
        $where[] = 'pv.selling_price>=:min_price';
        $params[':min_price'] = number_format($minPrice, 2, '.', '');
    }
    if ($maxPrice !== null) {
        $where[] = 'pv.selling_price<=:max_price';
        $params[':max_price'] = number_format($maxPrice, 2, '.', '');
    }
    if ($minOriginalPrice !== null) {
        $where[] = 'pv.original_price>=:min_original_price';
        $params[':min_original_price'] = number_format($minOriginalPrice, 2, '.', '');
    }
    if ($maxOriginalPrice !== null) {
        $where[] = 'pv.original_price<=:max_original_price';
        $params[':max_original_price'] = number_format($maxOriginalPrice, 2, '.', '');
    }
    if ($minPriceWithTax !== null) {
        $where[] = 'pv.price_with_tax>=:min_price_with_tax';
        $params[':min_price_with_tax'] = number_format($minPriceWithTax, 2, '.', '');
    }
    if ($maxPriceWithTax !== null) {
        $where[] = 'pv.price_with_tax<=:max_price_with_tax';
        $params[':max_price_with_tax'] = number_format($maxPriceWithTax, 2, '.', '');
    }

    if ($stockStatus === 'in_stock') {
        $where[] = '(pv.stock_quantity-pv.reserved_quantity)>pv.low_stock_limit';
    } elseif ($stockStatus === 'low_stock') {
        $where[] = '(pv.stock_quantity-pv.reserved_quantity)>0
                    AND (pv.stock_quantity-pv.reserved_quantity)<=pv.low_stock_limit';
    } elseif ($stockStatus === 'out_of_stock') {
        $where[] = '(pv.stock_quantity-pv.reserved_quantity)<=0';
    }

    if ($createdFrom !== '') {
        $where[] = 'w.created_at>=:created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }
    if ($createdTo !== '') {
        $where[] = 'w.created_at<=:created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    if ($q !== '') {
        $search = '%' . $q . '%';

        $where[] = "(
            CAST(w.id AS CHAR) LIKE :q_wishlist_id OR
            CAST(w.product_id AS CHAR) LIKE :q_product_id OR
            CAST(w.variant_id AS CHAR) LIKE :q_variant_id OR
            CAST(p.category_id AS CHAR) LIKE :q_category_id OR
            CAST(p.hsn_profile_id AS CHAR) LIKE :q_hsn_profile_id OR
            CAST(pv.size_id AS CHAR) LIKE :q_size_id OR
            CAST(pv.color_id AS CHAR) LIKE :q_color_id OR
            p.name LIKE :q_product_name OR
            p.slug LIKE :q_product_slug OR
            p.description LIKE :q_product_description OR
            c.name LIKE :q_category_name OR
            c.slug LIKE :q_category_slug OR
            hp.name LIKE :q_hsn_name OR
            hp.hsn_code LIKE :q_hsn_code OR
            hp.description LIKE :q_hsn_description OR
            pv.sku LIKE :q_sku OR
            pv.variant_name LIKE :q_variant_name OR
            s.name LIKE :q_size_name OR
            co.name LIKE :q_color_name OR
            co.hex_code LIKE :q_hex_code OR
            pv.discount_type LIKE :q_discount_type OR
            CAST(pv.original_price AS CHAR) LIKE :q_original_price OR
            CAST(pv.gst_rate AS CHAR) LIKE :q_gst_rate OR
            CAST(pv.gst_amount AS CHAR) LIKE :q_gst_amount OR
            CAST(pv.price_with_tax AS CHAR) LIKE :q_price_with_tax OR
            CAST(pv.discount_value AS CHAR) LIKE :q_discount_value OR
            CAST(pv.selling_price AS CHAR) LIKE :q_selling_price OR
            CAST(pv.stock_quantity AS CHAR) LIKE :q_stock_quantity OR
            CAST(pv.reserved_quantity AS CHAR) LIKE :q_reserved_quantity OR
            CAST(pv.low_stock_limit AS CHAR) LIKE :q_low_stock_limit OR
            p.status LIKE :q_product_status
        )";

        foreach ([
            ':q_wishlist_id',':q_product_id',':q_variant_id',':q_category_id',
            ':q_hsn_profile_id',':q_size_id',':q_color_id',':q_product_name',
            ':q_product_slug',':q_product_description',':q_category_name',
            ':q_category_slug',':q_hsn_name',':q_hsn_code',':q_hsn_description',
            ':q_sku',':q_variant_name',':q_size_name',':q_color_name',
            ':q_hex_code',':q_discount_type',':q_original_price',':q_gst_rate',
            ':q_gst_amount',':q_price_with_tax',':q_discount_value',
            ':q_selling_price',':q_stock_quantity',':q_reserved_quantity',
            ':q_low_stock_limit',':q_product_status'
        ] as $key) {
            $params[$key] = $search;
        }
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $baseFrom = "
        FROM wishlists w
        INNER JOIN products p ON p.id=w.product_id
        INNER JOIN product_variants pv ON pv.id=w.variant_id AND pv.product_id=w.product_id
        INNER JOIN categories c ON c.id=p.category_id
        LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
        LEFT JOIN sizes s ON s.id=pv.size_id
        LEFT JOIN colors co ON co.id=pv.color_id
    ";

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) {$baseFrom} {$whereSql}"
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
            w.id AS wishlist_id,w.user_id,w.product_id,w.variant_id,
            w.created_at AS wishlist_created_at,w.updated_at AS wishlist_updated_at,
            p.category_id,p.hsn_profile_id,p.name AS product_name,p.slug AS product_slug,
            p.description AS product_description,p.is_new_arrival,p.is_featured,
            p.is_best_seller,p.status AS product_status,
            p.created_at AS product_created_at,p.updated_at AS product_updated_at,
            c.name AS category_name,c.slug AS category_slug,
            c.description AS category_description,c.image AS category_image,
            c.sort_order AS category_sort_order,c.status AS category_status,
            hp.name AS hsn_profile_name,hp.hsn_code,
            hp.description AS hsn_description,hp.status AS hsn_profile_status,
            pv.size_id,pv.color_id,pv.sku,pv.variant_name,pv.original_price,
            pv.gst_rate,pv.gst_amount,pv.price_with_tax,pv.discount_type,
            pv.discount_value,pv.selling_price,pv.stock_quantity,
            pv.reserved_quantity,pv.low_stock_limit,pv.is_available,
            pv.created_at AS variant_created_at,pv.updated_at AS variant_updated_at,
            s.name AS size_name,s.sort_order AS size_sort_order,s.status AS size_status,
            co.name AS color_name,co.hex_code,co.status AS color_status
         {$baseFrom}
         {$whereSql}
         ORDER BY {$sortColumn} {$sqlSortOrder},w.id DESC
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

    $wishlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $imagesByVariant = [];

    if ($wishlists) {
        $variantIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int)$row['variant_id'],
            $wishlists
        )));

        $placeholders = [];

        foreach ($variantIds as $index => $id) {
            $placeholders[] = ':variant_' . $index;
        }

        $imageStmt = $pdo->prepare(
            "SELECT id,variant_id,image,alt_text,is_primary,sort_order,status,created_at,updated_at
             FROM product_variant_images
             WHERE variant_id IN (" . implode(',', $placeholders) . ")
               AND status='active'
             ORDER BY variant_id ASC,is_primary DESC,sort_order ASC,id ASC"
        );

        foreach ($variantIds as $index => $id) {
            $imageStmt->bindValue(':variant_' . $index, $id, PDO::PARAM_INT);
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

    $formattedWishlists = [];

    foreach ($wishlists as $wishlist) {
        $currentVariantId = (int)$wishlist['variant_id'];
        $stockQuantity = (int)$wishlist['stock_quantity'];
        $reservedQuantity = (int)$wishlist['reserved_quantity'];
        $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

        if ($availableQuantity <= 0) {
            $stockStatusValue = 'out_of_stock';
        } elseif ($availableQuantity <= (int)$wishlist['low_stock_limit']) {
            $stockStatusValue = 'low_stock';
        } else {
            $stockStatusValue = 'in_stock';
        }

        $images = $imagesByVariant[$currentVariantId] ?? [];
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

        $priceWithTax = (float)$wishlist['price_with_tax'];
        $sellingPrice = (float)$wishlist['selling_price'];
        $discountAmount = max(0, round($priceWithTax - $sellingPrice, 2));
        $effectiveDiscountPercentage = 0.00;

        if ($wishlist['discount_type'] === 'percentage') {
            $effectiveDiscountPercentage = (float)$wishlist['discount_value'];
        } elseif ($wishlist['discount_type'] === 'flat' && $priceWithTax > 0) {
            $effectiveDiscountPercentage = round(
                ($discountAmount / $priceWithTax) * 100,
                2
            );
        }

        $isPurchasable =
            $wishlist['product_status'] === 'active' &&
            $wishlist['category_status'] === 'active' &&
            (int)$wishlist['is_available'] === 1 &&
            $availableQuantity > 0 &&
            ($wishlist['size_id'] === null || $wishlist['size_status'] === 'active') &&
            ($wishlist['color_id'] === null || $wishlist['color_status'] === 'active');

        $formattedWishlists[] = [
            'id' => (int)$wishlist['wishlist_id'],
            'product_id' => (int)$wishlist['product_id'],
            'variant_id' => $currentVariantId,
            'product' => [
                'id' => (int)$wishlist['product_id'],
                'category_id' => (int)$wishlist['category_id'],
                'hsn_profile_id' => $wishlist['hsn_profile_id'] !== null
                    ? (int)$wishlist['hsn_profile_id']
                    : null,
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
                'status' => $wishlist['category_status']
            ],
            'hsn_profile' => $wishlist['hsn_profile_id'] !== null ? [
                'id' => (int)$wishlist['hsn_profile_id'],
                'name' => $wishlist['hsn_profile_name'],
                'hsn_code' => $wishlist['hsn_code'],
                'description' => $wishlist['hsn_description'],
                'status' => $wishlist['hsn_profile_status']
            ] : null,
            'variant' => [
                'id' => $currentVariantId,
                'sku' => $wishlist['sku'],
                'variant_name' => $wishlist['variant_name'],
                'size' => $wishlist['size_id'] !== null ? [
                    'id' => (int)$wishlist['size_id'],
                    'name' => $wishlist['size_name'],
                    'sort_order' => (int)$wishlist['size_sort_order'],
                    'status' => $wishlist['size_status']
                ] : null,
                'color' => $wishlist['color_id'] !== null ? [
                    'id' => (int)$wishlist['color_id'],
                    'name' => $wishlist['color_name'],
                    'hex_code' => $wishlist['hex_code'],
                    'status' => $wishlist['color_status']
                ] : null,
                'pricing' => [
                    'original_price' => $wishlist['original_price'],
                    'gst_rate' => $wishlist['gst_rate'],
                    'gst_amount' => $wishlist['gst_amount'],
                    'price_with_tax' => $wishlist['price_with_tax'],
                    'discount_type' => $wishlist['discount_type'],
                    'discount_value' => $wishlist['discount_value'],
                    'discount_amount' => number_format($discountAmount, 2, '.', ''),
                    'effective_discount_percentage' => number_format(
                        $effectiveDiscountPercentage,
                        2,
                        '.',
                        ''
                    ),
                    'selling_price' => $wishlist['selling_price'],
                    'has_discount' =>
                        $wishlist['discount_type'] !== 'none' &&
                        (float)$wishlist['discount_value'] > 0
                ],
                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'available_quantity' => $availableQuantity,
                    'low_stock_limit' => (int)$wishlist['low_stock_limit'],
                    'stock_status' => $stockStatusValue
                ],
                'is_available' => (int)$wishlist['is_available'],
                'is_purchasable' => $isPurchasable,
                'primary_image' => $primaryImage,
                'images' => $images,
                'image_count' => count($images),
                'created_at' => $wishlist['variant_created_at'],
                'updated_at' => $wishlist['variant_updated_at']
            ],
            'created_at' => $wishlist['wishlist_created_at'],
            'updated_at' => $wishlist['wishlist_updated_at']
        ];
    }

    sendResponse(true, 'Wishlist retrieved successfully.', [
        'wishlists' => $formattedWishlists,
        'summary' => [
            'wishlist_count' => $totalRecords,
            'current_page_count' => count($formattedWishlists)
        ],
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
            'wishlist_id' => $wishlistId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'category_id' => $categoryId,
            'hsn_profile_id' => $hsnProfileId,
            'hsn_code' => $hsnCode !== '' ? $hsnCode : null,
            'size_id' => $sizeId,
            'color_id' => $colorId,
            'is_available' => $isAvailable,
            'is_new_arrival' => $isNewArrival,
            'is_featured' => $isFeatured,
            'is_best_seller' => $isBestSeller,
            'gst_rate' => $gstRate,
            'discount_type' => $discountType !== '' ? $discountType : null,
            'product_status' => $productStatus !== '' ? $productStatus : null,
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
        'Unable to retrieve wishlist.',
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