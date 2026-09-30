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

function cartPositiveId(string $value, string $field): ?int
{
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }

    return (int)$value;
}

function cartBoolean(string $value, string $field): ?int
{
    if ($value === '') {
        return null;
    }

    $value = strtolower(trim($value));

    if (in_array($value, ['1', 'true'], true)) {
        return 1;
    }

    if (in_array($value, ['0', 'false'], true)) {
        return 0;
    }

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return null;
}

function cartDecimal(
    string $value,
    string $field,
    float $min = 0,
    float $max = 99999999.99
): ?float {
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        sendResponse(false, "{$field} must be a valid number.", null, 422);
    }

    $number = (float)$value;

    if ($number < $min || $number > $max) {
        sendResponse(false, "{$field} must be between {$min} and {$max}.", null, 422);
    }

    return round($number, 2);
}

function cartDate(string $value, string $field): void
{
    if ($value === '') {
        return;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        ($errors !== false && (
            $errors['warning_count'] > 0 ||
            $errors['error_count'] > 0
        )) ||
        $date->format('Y-m-d') !== $value
    ) {
        sendResponse(false, "{$field} must be in YYYY-MM-DD format.", null, 422);
    }
}

$allowedParams = [
    'q',
    'cart_id',
    'status',
    'product_id',
    'variant_id',
    'category_id',
    'hsn_profile_id',
    'hsn_code',
    'size_id',
    'color_id',
    'is_available',
    'gst_rate',
    'min_price',
    'max_price',
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

$cartId = cartPositiveId(
    isset($_GET['cart_id']) ? trim((string)$_GET['cart_id']) : '',
    'cart_id'
);

$productId = cartPositiveId(
    isset($_GET['product_id']) ? trim((string)$_GET['product_id']) : '',
    'product_id'
);

$variantId = cartPositiveId(
    isset($_GET['variant_id']) ? trim((string)$_GET['variant_id']) : '',
    'variant_id'
);

$categoryId = cartPositiveId(
    isset($_GET['category_id']) ? trim((string)$_GET['category_id']) : '',
    'category_id'
);

$hsnProfileId = cartPositiveId(
    isset($_GET['hsn_profile_id']) ? trim((string)$_GET['hsn_profile_id']) : '',
    'hsn_profile_id'
);

$sizeId = cartPositiveId(
    isset($_GET['size_id']) ? trim((string)$_GET['size_id']) : '',
    'size_id'
);

$colorId = cartPositiveId(
    isset($_GET['color_id']) ? trim((string)$_GET['color_id']) : '',
    'color_id'
);

$cartStatus = isset($_GET['status'])
    ? strtolower(trim((string)$_GET['status']))
    : '';

$allowedCartStatuses = ['active', 'converted', 'abandoned'];

if ($cartStatus !== '' && !in_array($cartStatus, $allowedCartStatuses, true)) {
    sendResponse(false, 'Invalid cart status.', [
        'allowed_statuses' => $allowedCartStatuses
    ], 422);
}

$hsnCode = isset($_GET['hsn_code'])
    ? trim((string)$_GET['hsn_code'])
    : '';

if ($hsnCode !== '' && !preg_match('/^(?:\d{4}|\d{6}|\d{8})$/', $hsnCode)) {
    sendResponse(false, 'hsn_code must contain exactly 4, 6 or 8 digits.', null, 422);
}

$isAvailable = cartBoolean(
    isset($_GET['is_available']) ? trim((string)$_GET['is_available']) : '',
    'is_available'
);

$gstRate = cartDecimal(
    isset($_GET['gst_rate']) ? trim((string)$_GET['gst_rate']) : '',
    'gst_rate',
    0,
    100
);

$minPrice = cartDecimal(
    isset($_GET['min_price']) ? trim((string)$_GET['min_price']) : '',
    'min_price'
);

$maxPrice = cartDecimal(
    isset($_GET['max_price']) ? trim((string)$_GET['max_price']) : '',
    'max_price'
);

if (
    $minPrice !== null &&
    $maxPrice !== null &&
    $minPrice > $maxPrice
) {
    sendResponse(false, 'min_price cannot be greater than max_price.', null, 422);
}

$stockStatus = isset($_GET['stock_status'])
    ? strtolower(trim((string)$_GET['stock_status']))
    : '';

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

$createdFrom = isset($_GET['created_from'])
    ? trim((string)$_GET['created_from'])
    : '';

$createdTo = isset($_GET['created_to'])
    ? trim((string)$_GET['created_to'])
    : '';

cartDate($createdFrom, 'created_from');
cartDate($createdTo, 'created_to');

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
    'id' => 'ci.id',
    'cart_id' => 'ci.cart_id',
    'product_id' => 'ci.product_id',
    'variant_id' => 'ci.variant_id',
    'product_name' => 'p.name',
    'category_name' => 'c.name',
    'hsn_profile_name' => 'hp.name',
    'hsn_code' => 'hp.hsn_code',
    'sku' => 'pv.sku',
    'variant_name' => 'pv.variant_name',
    'quantity' => 'ci.quantity',
    'unit_price' => 'ci.unit_price',
    'selling_price' => 'pv.selling_price',
    'gst_rate' => 'pv.gst_rate',
    'gst_amount' => 'pv.gst_amount',
    'price_with_tax' => 'pv.price_with_tax',
    'created_at' => 'ci.created_at',
    'updated_at' => 'ci.updated_at'
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
$sqlSortOrder = strtoupper($sortOrder);

try {
    $where = ['ca.user_id = :authenticated_user_id'];

    $params = [
        ':authenticated_user_id' => $userId
    ];

    if ($cartId !== null) {
        $where[] = 'ca.id = :cart_id';
        $params[':cart_id'] = $cartId;
    }

    if ($cartStatus !== '') {
        $where[] = 'ca.status = :cart_status';
        $params[':cart_status'] = $cartStatus;
    }

    if ($productId !== null) {
        $where[] = 'ci.product_id = :product_id';
        $params[':product_id'] = $productId;
    }

    if ($variantId !== null) {
        $where[] = 'ci.variant_id = :variant_id';
        $params[':variant_id'] = $variantId;
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

    if ($isAvailable !== null) {
        $where[] = 'pv.is_available = :is_available';
        $params[':is_available'] = $isAvailable;
    }

    if ($gstRate !== null) {
        $where[] = 'pv.gst_rate = :gst_rate';
        $params[':gst_rate'] = number_format($gstRate, 2, '.', '');
    }

    if ($minPrice !== null) {
        $where[] = 'ci.unit_price >= :min_price';
        $params[':min_price'] = number_format($minPrice, 2, '.', '');
    }

    if ($maxPrice !== null) {
        $where[] = 'ci.unit_price <= :max_price';
        $params[':max_price'] = number_format($maxPrice, 2, '.', '');
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
        $where[] = "
            (pv.stock_quantity - pv.reserved_quantity) <= 0
        ";
    }

    if ($createdFrom !== '') {
        $where[] = 'ci.created_at >= :created_from';
        $params[':created_from'] = $createdFrom . ' 00:00:00';
    }

    if ($createdTo !== '') {
        $where[] = 'ci.created_at <= :created_to';
        $params[':created_to'] = $createdTo . ' 23:59:59';
    }

    if ($q !== '') {
        $search = '%' . $q . '%';

        $where[] = "(
            CAST(ca.id AS CHAR) LIKE :q_cart_id
            OR CAST(ci.id AS CHAR) LIKE :q_cart_item_id
            OR CAST(ci.product_id AS CHAR) LIKE :q_product_id
            OR CAST(ci.variant_id AS CHAR) LIKE :q_variant_id
            OR p.name LIKE :q_product_name
            OR p.slug LIKE :q_product_slug
            OR p.description LIKE :q_product_description
            OR c.name LIKE :q_category_name
            OR hp.name LIKE :q_hsn_name
            OR hp.hsn_code LIKE :q_hsn_code
            OR hp.description LIKE :q_hsn_description
            OR pv.sku LIKE :q_sku
            OR pv.variant_name LIKE :q_variant_name
            OR s.name LIKE :q_size_name
            OR co.name LIKE :q_color_name
            OR co.hex_code LIKE :q_hex_code
            OR CAST(ci.quantity AS CHAR) LIKE :q_quantity
            OR CAST(ci.unit_price AS CHAR) LIKE :q_unit_price
            OR CAST(pv.original_price AS CHAR) LIKE :q_original_price
            OR CAST(pv.discount_value AS CHAR) LIKE :q_discount_value
            OR CAST(pv.selling_price AS CHAR) LIKE :q_selling_price
            OR CAST(pv.gst_rate AS CHAR) LIKE :q_gst_rate
            OR CAST(pv.gst_amount AS CHAR) LIKE :q_gst_amount
            OR CAST(pv.price_with_tax AS CHAR) LIKE :q_price_with_tax
            OR ca.status LIKE :q_cart_status
        )";

        $params[':q_cart_id'] = $search;
        $params[':q_cart_item_id'] = $search;
        $params[':q_product_id'] = $search;
        $params[':q_variant_id'] = $search;
        $params[':q_product_name'] = $search;
        $params[':q_product_slug'] = $search;
        $params[':q_product_description'] = $search;
        $params[':q_category_name'] = $search;
        $params[':q_hsn_name'] = $search;
        $params[':q_hsn_code'] = $search;
        $params[':q_hsn_description'] = $search;
        $params[':q_sku'] = $search;
        $params[':q_variant_name'] = $search;
        $params[':q_size_name'] = $search;
        $params[':q_color_name'] = $search;
        $params[':q_hex_code'] = $search;
        $params[':q_quantity'] = $search;
        $params[':q_unit_price'] = $search;
        $params[':q_original_price'] = $search;
        $params[':q_discount_value'] = $search;
        $params[':q_selling_price'] = $search;
        $params[':q_gst_rate'] = $search;
        $params[':q_gst_amount'] = $search;
        $params[':q_price_with_tax'] = $search;
        $params[':q_cart_status'] = $search;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $baseFrom = "
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
    ";

    $summaryStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_records,
            COUNT(DISTINCT ca.id) AS total_carts,
            COALESCE(SUM(ci.quantity), 0) AS total_quantity,
            COALESCE(SUM(ci.quantity * ci.unit_price), 0) AS subtotal
         {$baseFrom}
         {$whereSql}"
    );

    foreach ($params as $key => $value) {
        $summaryStmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    $totalRecords = (int)$summary['total_records'];
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
            c.image AS category_image,
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

         {$baseFrom}
         {$whereSql}

         ORDER BY {$sortColumn} {$sqlSortOrder}, ci.id DESC
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $primaryImagesByVariant = [];

    if ($rows) {
        $variantIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int)$row['variant_id'],
            $rows
        )));

        $placeholders = [];

        foreach ($variantIds as $index => $variantIdValue) {
            $placeholders[] = ':variant_' . $index;
        }

        $imageStmt = $pdo->prepare(
            "SELECT
                id,
                variant_id,
                image,
                alt_text,
                is_primary,
                sort_order
             FROM product_variant_images
             WHERE variant_id IN (" . implode(',', $placeholders) . ")
               AND status = 'active'
             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach ($variantIds as $index => $variantIdValue) {
            $imageStmt->bindValue(
                ':variant_' . $index,
                $variantIdValue,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
            $imageVariantId = (int)$image['variant_id'];

            if (!isset($primaryImagesByVariant[$imageVariantId])) {
                $primaryImagesByVariant[$imageVariantId] = [
                    'id' => (int)$image['id'],
                    'image' => $image['image'],
                    'alt_text' => $image['alt_text'],
                    'is_primary' => (int)$image['is_primary'],
                    'sort_order' => (int)$image['sort_order']
                ];
            }
        }
    }

    $cartItems = [];

    foreach ($rows as $row) {
        $stockQuantity = (int)$row['stock_quantity'];
        $reservedQuantity = (int)$row['reserved_quantity'];
        $availableQuantity = max(0, $stockQuantity - $reservedQuantity);

        if ($availableQuantity <= 0) {
            $calculatedStockStatus = 'out_of_stock';
        } elseif ($availableQuantity <= (int)$row['low_stock_limit']) {
            $calculatedStockStatus = 'low_stock';
        } else {
            $calculatedStockStatus = 'in_stock';
        }

        $quantity = (int)$row['quantity'];
        $unitPrice = (float)$row['unit_price'];
        $lineTotal = $quantity * $unitPrice;

        $cartItems[] = [
            'cart_item_id' => (int)$row['cart_item_id'],
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
                'image' => $row['category_image'],
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
                    'cart_unit_price' => number_format($unitPrice, 2, '.', '')
                ],

                'stock' => [
                    'stock_quantity' => $stockQuantity,
                    'reserved_quantity' => $reservedQuantity,
                    'available_quantity' => $availableQuantity,
                    'low_stock_limit' => (int)$row['low_stock_limit'],
                    'stock_status' => $calculatedStockStatus
                ],

                'is_available' => (int)$row['is_available'],
                'primary_image' =>
                    $primaryImagesByVariant[(int)$row['variant_id']] ?? null,
                'created_at' => $row['variant_created_at'],
                'updated_at' => $row['variant_updated_at']
            ],

            'quantity' => $quantity,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
            'created_at' => $row['cart_item_created_at'],
            'updated_at' => $row['cart_item_updated_at']
        ];
    }

    sendResponse(true, 'Cart items retrieved successfully.', [
        'cart_items' => $cartItems,

        'summary' => [
            'total_cart_records' => (int)$summary['total_carts'],
            'total_items' => $totalRecords,
            'total_quantity' => (int)$summary['total_quantity'],
            'subtotal' => number_format(
                (float)$summary['subtotal'],
                2,
                '.',
                ''
            )
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
            'cart_id' => $cartId,
            'status' => $cartStatus !== '' ? $cartStatus : null,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'category_id' => $categoryId,
            'hsn_profile_id' => $hsnProfileId,
            'hsn_code' => $hsnCode !== '' ? $hsnCode : null,
            'size_id' => $sizeId,
            'color_id' => $colorId,
            'is_available' => $isAvailable,
            'gst_rate' => $gstRate,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
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
        'Unable to retrieve cart items.',
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