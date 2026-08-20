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

$cartIdInput = isset($_GET['cart_id']) ? trim((string)$_GET['cart_id']) : '';
$productIdInput = isset($_GET['product_id']) ? trim((string)$_GET['product_id']) : '';
$variantIdInput = isset($_GET['variant_id']) ? trim((string)$_GET['variant_id']) : '';
$categoryIdInput = isset($_GET['category_id']) ? trim((string)$_GET['category_id']) : '';
$sizeIdInput = isset($_GET['size_id']) ? trim((string)$_GET['size_id']) : '';
$colorIdInput = isset($_GET['color_id']) ? trim((string)$_GET['color_id']) : '';

$cartId = validatePositiveIdFilter($cartIdInput, 'cart_id');
$productId = validatePositiveIdFilter($productIdInput, 'product_id');
$variantId = validatePositiveIdFilter($variantIdInput, 'variant_id');
$categoryId = validatePositiveIdFilter($categoryIdInput, 'category_id');
$sizeId = validatePositiveIdFilter($sizeIdInput, 'size_id');
$colorId = validatePositiveIdFilter($colorIdInput, 'color_id');

$cartStatus = isset($_GET['status'])
    ? strtolower(trim((string)$_GET['status']))
    : '';

$allowedCartStatuses = ['active', 'converted', 'abandoned'];

if (
    $cartStatus !== '' &&
    !in_array($cartStatus, $allowedCartStatuses, true)
) {
    sendResponse(false, 'Invalid cart status.', [
        'allowed_statuses' => $allowedCartStatuses
    ], 422);
}

$isAvailableInput = isset($_GET['is_available'])
    ? trim((string)$_GET['is_available'])
    : '';

$isAvailable = validateBooleanFilter(
    $isAvailableInput,
    'is_available'
);

$minPriceInput = isset($_GET['min_price'])
    ? trim((string)$_GET['min_price'])
    : '';

$maxPriceInput = isset($_GET['max_price'])
    ? trim((string)$_GET['max_price'])
    : '';

$minPrice = validatePriceFilter($minPriceInput, 'min_price');
$maxPrice = validatePriceFilter($maxPriceInput, 'max_price');

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
    'id' => 'ci.id',
    'cart_id' => 'ci.cart_id',
    'product_id' => 'ci.product_id',
    'variant_id' => 'ci.variant_id',
    'quantity' => 'ci.quantity',
    'unit_price' => 'ci.unit_price',
    'created_at' => 'ci.created_at',
    'updated_at' => 'ci.updated_at',
    'product_name' => 'p.name',
    'variant_name' => 'pv.variant_name',
    'selling_price' => 'pv.selling_price'
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
        'ca.user_id = :authenticated_user_id'
    ];

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

    if ($minPrice !== null) {
        $where[] = 'ci.unit_price >= :min_price';
        $params[':min_price'] = number_format($minPrice, 2, '.', '');
    }

    if ($maxPrice !== null) {
        $where[] = 'ci.unit_price <= :max_price';
        $params[':max_price'] = number_format($maxPrice, 2, '.', '');
    }

    if ($stockStatus === 'in_stock') {
        $where[] = '(pv.stock_quantity - pv.reserved_quantity) > 0';
    } elseif ($stockStatus === 'low_stock') {
        $where[] = "
            (pv.stock_quantity - pv.reserved_quantity) > 0
            AND
            (pv.stock_quantity - pv.reserved_quantity) <= pv.low_stock_limit
        ";
    } elseif ($stockStatus === 'out_of_stock') {
        $where[] = '(pv.stock_quantity - pv.reserved_quantity) <= 0';
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
        $where[] = "(
            CAST(ca.id AS CHAR) LIKE :q_cart_id
            OR CAST(ci.id AS CHAR) LIKE :q_cart_item_id
            OR CAST(ci.product_id AS CHAR) LIKE :q_product_id
            OR CAST(ci.variant_id AS CHAR) LIKE :q_variant_id
            OR p.name LIKE :q_product_name
            OR p.slug LIKE :q_product_slug
            OR p.description LIKE :q_product_description
            OR pv.sku LIKE :q_sku
            OR pv.variant_name LIKE :q_variant_name
            OR c.name LIKE :q_category_name
            OR s.name LIKE :q_size_name
            OR co.name LIKE :q_color_name
            OR co.hex_code LIKE :q_hex_code
            OR CAST(ci.quantity AS CHAR) LIKE :q_quantity
            OR CAST(ci.unit_price AS CHAR) LIKE :q_unit_price
            OR CAST(pv.original_price AS CHAR) LIKE :q_original_price
            OR CAST(pv.discount_value AS CHAR) LIKE :q_discount_value
            OR CAST(pv.selling_price AS CHAR) LIKE :q_selling_price
            OR ca.status LIKE :q_cart_status
            OR DATE_FORMAT(ci.created_at, '%Y-%m-%d %H:%i:%s') LIKE :q_created_at
            OR DATE_FORMAT(ci.updated_at, '%Y-%m-%d %H:%i:%s') LIKE :q_updated_at
        )";

        $searchValue = '%' . $q . '%';

        $params[':q_cart_id'] = $searchValue;
        $params[':q_cart_item_id'] = $searchValue;
        $params[':q_product_id'] = $searchValue;
        $params[':q_variant_id'] = $searchValue;
        $params[':q_product_name'] = $searchValue;
        $params[':q_product_slug'] = $searchValue;
        $params[':q_product_description'] = $searchValue;
        $params[':q_sku'] = $searchValue;
        $params[':q_variant_name'] = $searchValue;
        $params[':q_category_name'] = $searchValue;
        $params[':q_size_name'] = $searchValue;
        $params[':q_color_name'] = $searchValue;
        $params[':q_hex_code'] = $searchValue;
        $params[':q_quantity'] = $searchValue;
        $params[':q_unit_price'] = $searchValue;
        $params[':q_original_price'] = $searchValue;
        $params[':q_discount_value'] = $searchValue;
        $params[':q_selling_price'] = $searchValue;
        $params[':q_cart_status'] = $searchValue;
        $params[':q_created_at'] = $searchValue;
        $params[':q_updated_at'] = $searchValue;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
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
        $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $countStmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
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

            c.name AS category_name,
            c.slug AS category_slug,
            c.image AS category_image,
            c.status AS category_status,

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

        LEFT JOIN sizes s
            ON s.id = pv.size_id

        LEFT JOIN colors co
            ON co.id = pv.color_id

        $whereSql

        ORDER BY $sortColumn $sortOrder, ci.id DESC

        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedItems = [];
    $cartIds = [];

    foreach ($rows as $row) {
        $cartIds[(int)$row['cart_id']] = true;
    }

    $primaryImagesByVariant = [];

    if (!empty($rows)) {
        $variantIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int)$row['variant_id'],
            $rows
        )));

        $placeholders = [];

        foreach ($variantIds as $index => $id) {
            $placeholders[] = ':variant_id_' . $index;
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

        foreach ($variantIds as $index => $id) {
            $imageStmt->bindValue(
                ':variant_id_' . $index,
                $id,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as $image) {
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

    $subtotal = 0.0;
    $totalQuantity = 0;

    foreach ($rows as $row) {
        $stockQuantity = (int)$row['stock_quantity'];
        $reservedQuantity = (int)$row['reserved_quantity'];

        $availableQuantity = $stockQuantity - $reservedQuantity;

        if ($availableQuantity < 0) {
            $availableQuantity = 0;
        }

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

        $subtotal += $lineTotal;
        $totalQuantity += $quantity;

        $formattedItems[] = [
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
                    'current_selling_price' => $row['selling_price'],
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
        'cart_items' => $formattedItems,

        'summary' => [
            'total_cart_records' => count($cartIds),
            'total_items' => count($formattedItems),
            'total_quantity' => $totalQuantity,
            'subtotal' => number_format($subtotal, 2, '.', '')
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
            'size_id' => $sizeId,
            'color_id' => $colorId,
            'is_available' => $isAvailable,
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