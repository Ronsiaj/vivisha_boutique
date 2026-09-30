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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$decoded = authenticate();
validateJWTData($decoded);

if (getAuthenticatedId($decoded) <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if (getAuthenticatedType($decoded) !== 'admin') {
    sendResponse(false, 'Admin access only.', null, 403);
}

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'multipart/form-data') === false) {
    sendResponse(false, 'Content-Type must be multipart/form-data.', null, 415);
}

$allowedFields = [
    'product_id','size_id','color_id','variant_name',
    'original_price','gst_rate','discount_type','discount_value',
    'stock_quantity','reserved_quantity','low_stock_limit','is_available',
    'alt_text','is_primary','sort_order','image_status'
];

foreach (array_keys($_POST) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

foreach (array_keys($_FILES) as $field) {
    if ($field !== 'images') {
        sendResponse(false, "Invalid file field: {$field}.", null, 422);
    }
}

function positiveId(string $value, string $field): ?int
{
    if ($value === '') return null;

    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        sendResponse(false, "{$field} must be a valid positive integer.", null, 422);
    }

    return (int)$value;
}

function nonNegativeInt(string $value, string $field): int
{
    if (
        filter_var($value, FILTER_VALIDATE_INT) === false ||
        (int)$value < 0
    ) {
        sendResponse(false, "{$field} must be a non-negative integer.", null, 422);
    }

    return (int)$value;
}

$productIdInput = trim((string)($_POST['product_id'] ?? ''));
$sizeIdInput = trim((string)($_POST['size_id'] ?? ''));
$colorIdInput = trim((string)($_POST['color_id'] ?? ''));
$variantName = trim((string)($_POST['variant_name'] ?? ''));
$originalPriceInput = trim((string)($_POST['original_price'] ?? ''));
$gstRateInput = trim((string)($_POST['gst_rate'] ?? ''));
$discountType = strtolower(trim((string)($_POST['discount_type'] ?? 'none')));
$discountValueInput = trim((string)($_POST['discount_value'] ?? '0'));
$stockQuantityInput = trim((string)($_POST['stock_quantity'] ?? '0'));
$reservedQuantityInput = trim((string)($_POST['reserved_quantity'] ?? '0'));
$lowStockLimitInput = trim((string)($_POST['low_stock_limit'] ?? '5'));
$isAvailableInput = strtolower(trim((string)($_POST['is_available'] ?? '1')));

if ($productIdInput === '') {
    sendResponse(false, 'product_id is required.', null, 422);
}

$productId = positiveId($productIdInput, 'product_id');
$sizeId = positiveId($sizeIdInput, 'size_id');
$colorId = positiveId($colorIdInput, 'color_id');

if ($sizeId === null && $colorId === null) {
    sendResponse(false, 'At least size_id or color_id must be provided.', null, 422);
}

if ($variantName !== '' && mb_strlen($variantName) > 150) {
    sendResponse(false, 'variant_name must not exceed 150 characters.', null, 422);
}

if (
    $variantName !== '' &&
    !preg_match('/^[\p{L}\p{N}\s\-\&\'\/().,+]+$/u', $variantName)
) {
    sendResponse(false, 'variant_name contains invalid characters.', null, 422);
}

if ($originalPriceInput === '' || !is_numeric($originalPriceInput)) {
    sendResponse(false, 'original_price must be a valid number.', null, 422);
}

$originalPrice = round((float)$originalPriceInput, 2);

if ($originalPrice <= 0 || $originalPrice > 99999999.99) {
    sendResponse(false, 'Invalid original_price.', null, 422);
}

if ($gstRateInput === '' || !is_numeric($gstRateInput)) {
    sendResponse(false, 'gst_rate must be a valid percentage.', null, 422);
}

$gstRate = round((float)$gstRateInput, 2);

if ($gstRate < 0 || $gstRate > 100) {
    sendResponse(false, 'gst_rate must be between 0 and 100.', null, 422);
}

$gstAmount = round(($originalPrice * $gstRate) / 100, 2);
$priceWithTax = round($originalPrice + $gstAmount, 2);

if ($priceWithTax > 99999999.99) {
    sendResponse(false, 'price_with_tax exceeds maximum allowed value.', null, 422);
}

$allowedDiscountTypes = ['none', 'percentage', 'flat'];

if (!in_array($discountType, $allowedDiscountTypes, true)) {
    sendResponse(false, 'Invalid discount_type.', [
        'allowed_discount_types' => $allowedDiscountTypes
    ], 422);
}

if (!is_numeric($discountValueInput)) {
    sendResponse(false, 'discount_value must be a valid number.', null, 422);
}

$discountValue = round((float)$discountValueInput, 2);

if ($discountValue < 0) {
    sendResponse(false, 'discount_value cannot be negative.', null, 422);
}

$discountAmount = 0.00;

if ($discountType === 'none') {
    if ($discountValue != 0) {
        sendResponse(false, 'discount_value must be 0 when discount_type is none.', null, 422);
    }
} elseif ($discountType === 'percentage') {
    if ($discountValue > 100) {
        sendResponse(false, 'Percentage discount cannot exceed 100.', null, 422);
    }

    $discountAmount = round(($priceWithTax * $discountValue) / 100, 2);
} else {
    if ($discountValue > $priceWithTax) {
        sendResponse(false, 'Flat discount cannot exceed price_with_tax.', null, 422);
    }

    $discountAmount = $discountValue;
}

$sellingPrice = round(max(0, $priceWithTax - $discountAmount), 2);

$stockQuantity = nonNegativeInt($stockQuantityInput, 'stock_quantity');
$reservedQuantity = nonNegativeInt($reservedQuantityInput, 'reserved_quantity');
$lowStockLimit = nonNegativeInt($lowStockLimitInput, 'low_stock_limit');

if ($reservedQuantity > $stockQuantity) {
    sendResponse(false, 'reserved_quantity cannot exceed stock_quantity.', null, 422);
}

if (!in_array($isAvailableInput, ['0','1','true','false'], true)) {
    sendResponse(false, 'is_available must be 0, 1, true or false.', null, 422);
}

$isAvailable = in_array($isAvailableInput, ['1','true'], true) ? 1 : 0;

$altTexts = $_POST['alt_text'] ?? [];
$isPrimaryValues = $_POST['is_primary'] ?? [];
$sortOrders = $_POST['sort_order'] ?? [];
$imageStatuses = $_POST['image_status'] ?? [];

$altTexts = is_array($altTexts) ? $altTexts : [$altTexts];
$isPrimaryValues = is_array($isPrimaryValues) ? $isPrimaryValues : [$isPrimaryValues];
$sortOrders = is_array($sortOrders) ? $sortOrders : [$sortOrders];
$imageStatuses = is_array($imageStatuses) ? $imageStatuses : [$imageStatuses];

$preparedImages = [];
$uploadedFiles = [];

if (isset($_FILES['images'])) {
    $files = $_FILES['images'];

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

    $imageCount = count($names);

    if ($imageCount > 10) {
        sendResponse(false, 'Maximum 10 images are allowed per variant.', null, 422);
    }

    foreach ([
        'alt_text' => $altTexts,
        'is_primary' => $isPrimaryValues,
        'sort_order' => $sortOrders,
        'image_status' => $imageStatuses
    ] as $field => $values) {
        if (count($values) > $imageCount) {
            sendResponse(false, "{$field} count cannot exceed image count.", null, 422);
        }
    }

    $primaryCount = 0;

    foreach ($names as $i => $name) {
        $error = $errors[$i] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) continue;

        if ($error !== UPLOAD_ERR_OK) {
            sendResponse(false, 'Image ' . ($i + 1) . ' upload failed.', null, 422);
        }

        $tmp = $tmpNames[$i] ?? '';
        $fileSize = (int)($sizes[$i] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            sendResponse(false, 'Invalid uploaded image.', null, 422);
        }

        if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
            sendResponse(false, 'Each image must be between 1 byte and 5 MB.', null, 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedTypes[$mime])) {
            sendResponse(false, 'Only JPG, PNG and WEBP images are allowed.', null, 422);
        }

        $info = @getimagesize($tmp);

        if (!$info || $info[0] <= 0 || $info[1] <= 0 || $info[0] > 8000 || $info[1] > 8000) {
            sendResponse(false, 'Invalid image dimensions.', null, 422);
        }

        $alt = trim((string)($altTexts[$i] ?? ''));

        if (mb_strlen($alt) > 255) {
            sendResponse(false, 'alt_text must not exceed 255 characters.', null, 422);
        }

        $primaryInput = strtolower(trim((string)($isPrimaryValues[$i] ?? '0')));

        if (!in_array($primaryInput, ['0','1','true','false'], true)) {
            sendResponse(false, 'is_primary must be 0, 1, true or false.', null, 422);
        }

        $primary = in_array($primaryInput, ['1','true'], true) ? 1 : 0;
        $primaryCount += $primary;

        $sortInput = trim((string)($sortOrders[$i] ?? ($i + 1)));

        if (
            filter_var($sortInput, FILTER_VALIDATE_INT) === false ||
            (int)$sortInput < 0
        ) {
            sendResponse(false, 'sort_order must be a non-negative integer.', null, 422);
        }

        $imageStatus = strtolower(trim((string)($imageStatuses[$i] ?? 'active')));

        if (!in_array($imageStatus, ['active','inactive'], true)) {
            sendResponse(false, 'Invalid image status.', null, 422);
        }

        $preparedImages[] = [
            'tmp_name' => $tmp,
            'extension' => $allowedTypes[$mime],
            'alt_text' => $alt,
            'is_primary' => $primary,
            'sort_order' => (int)$sortInput,
            'status' => $imageStatus
        ];
    }

    if ($primaryCount > 1) {
        sendResponse(false, 'Only one image can be primary.', null, 422);
    }
}

$uploadDirectory = __DIR__ . '/../../uploads/products/';

try {
    $productStmt = $pdo->prepare(
        "SELECT p.id,p.name,p.slug,p.category_id,p.hsn_profile_id,p.status,
                c.name AS category_name,
                hp.name AS hsn_profile_name,hp.hsn_code
         FROM products p
         INNER JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         WHERE p.id=:id
         LIMIT 1"
    );

    $productStmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $productStmt->execute();

    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    $size = null;

    if ($sizeId !== null) {
        $stmt = $pdo->prepare(
            "SELECT id,name,sort_order,status
             FROM sizes
             WHERE id=:id
             LIMIT 1"
        );

        $stmt->bindValue(':id', $sizeId, PDO::PARAM_INT);
        $stmt->execute();

        $size = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$size) sendResponse(false, 'Size not found.', null, 404);
        if ($size['status'] !== 'active') sendResponse(false, 'Selected size is inactive.', null, 422);
    }

    $color = null;

    if ($colorId !== null) {
        $stmt = $pdo->prepare(
            "SELECT id,name,hex_code,status
             FROM colors
             WHERE id=:id
             LIMIT 1"
        );

        $stmt->bindValue(':id', $colorId, PDO::PARAM_INT);
        $stmt->execute();

        $color = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$color) sendResponse(false, 'Color not found.', null, 404);
        if ($color['status'] !== 'active') sendResponse(false, 'Selected color is inactive.', null, 422);
    }

    if ($sizeId !== null && $colorId !== null) {
        $sql = "SELECT id FROM product_variants
                WHERE product_id=:product_id
                AND size_id=:size_id
                AND color_id=:color_id
                LIMIT 1";
    } elseif ($sizeId !== null) {
        $sql = "SELECT id FROM product_variants
                WHERE product_id=:product_id
                AND size_id=:size_id
                AND color_id IS NULL
                LIMIT 1";
    } else {
        $sql = "SELECT id FROM product_variants
                WHERE product_id=:product_id
                AND size_id IS NULL
                AND color_id=:color_id
                LIMIT 1";
    }

    $comboStmt = $pdo->prepare($sql);
    $comboStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);

    if ($sizeId !== null) {
        $comboStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
    }

    if ($colorId !== null) {
        $comboStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
    }

    $comboStmt->execute();

    if ($comboStmt->fetch()) {
        sendResponse(
            false,
            'This size and color combination already exists for the product.',
            null,
            409
        );
    }

    if ($preparedImages) {
        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            sendResponse(false, 'Unable to create product image directory.', null, 500);
        }

        if (!is_writable($uploadDirectory)) {
            sendResponse(false, 'Product image directory is not writable.', null, 500);
        }
    }

    $pdo->beginTransaction();

    $temporarySku = 'TMP' . strtoupper(bin2hex(random_bytes(12)));

    $stmt = $pdo->prepare(
        "INSERT INTO product_variants (
            product_id,size_id,color_id,sku,variant_name,
            original_price,gst_rate,gst_amount,price_with_tax,
            discount_type,discount_value,selling_price,
            stock_quantity,reserved_quantity,low_stock_limit,is_available
        ) VALUES (
            :product_id,:size_id,:color_id,:sku,:variant_name,
            :original_price,:gst_rate,:gst_amount,:price_with_tax,
            :discount_type,:discount_value,:selling_price,
            :stock_quantity,:reserved_quantity,:low_stock_limit,:is_available
        )"
    );

    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':size_id', $sizeId, $sizeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':color_id', $colorId, $colorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':sku', $temporarySku, PDO::PARAM_STR);
    $stmt->bindValue(':variant_name', $variantName !== '' ? $variantName : null, $variantName !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':original_price', number_format($originalPrice, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':gst_rate', number_format($gstRate, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':gst_amount', number_format($gstAmount, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':price_with_tax', number_format($priceWithTax, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':discount_type', $discountType, PDO::PARAM_STR);
    $stmt->bindValue(':discount_value', number_format($discountValue, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':selling_price', number_format($sellingPrice, 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':stock_quantity', $stockQuantity, PDO::PARAM_INT);
    $stmt->bindValue(':reserved_quantity', $reservedQuantity, PDO::PARAM_INT);
    $stmt->bindValue(':low_stock_limit', $lowStockLimit, PDO::PARAM_INT);
    $stmt->bindValue(':is_available', $isAvailable, PDO::PARAM_INT);
    $stmt->execute();

    $variantId = (int)$pdo->lastInsertId();

    $sku = 'VIV' . date('Y') . str_pad(
    (string)$variantId,
    3,
    '0',
    STR_PAD_LEFT
);

    $skuCheck = $pdo->prepare(
        "SELECT id
         FROM product_variants
         WHERE sku=:sku AND id!=:id
         LIMIT 1"
    );

    $skuCheck->bindValue(':sku', $sku, PDO::PARAM_STR);
    $skuCheck->bindValue(':id', $variantId, PDO::PARAM_INT);
    $skuCheck->execute();

    if ($skuCheck->fetch()) {
        throw new RuntimeException('Generated SKU already exists.');
    }

    $skuStmt = $pdo->prepare(
        "UPDATE product_variants
         SET sku=:sku
         WHERE id=:id"
    );

    $skuStmt->bindValue(':sku', $sku, PDO::PARAM_STR);
    $skuStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $skuStmt->execute();

    $createdImages = [];

    foreach ($preparedImages as $image) {
        $fileName =
            'product_' . $productId .
            '_variant_' . $variantId .
            '_' . bin2hex(random_bytes(12)) .
            '.' . $image['extension'];

        $fullPath = $uploadDirectory . $fileName;

        if (!move_uploaded_file($image['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to save variant image.');
        }

        $uploadedFiles[] = $fullPath;
        $relativePath = 'uploads/products/' . $fileName;

        $imageStmt = $pdo->prepare(
            "INSERT INTO product_variant_images (
                variant_id,image,alt_text,is_primary,sort_order,status
            ) VALUES (
                :variant_id,:image,:alt_text,:is_primary,:sort_order,:status
            )"
        );

        $imageStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $imageStmt->bindValue(':image', $relativePath, PDO::PARAM_STR);
        $imageStmt->bindValue(
            ':alt_text',
            $image['alt_text'] !== '' ? $image['alt_text'] : null,
            $image['alt_text'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
        );
        $imageStmt->bindValue(':is_primary', $image['is_primary'], PDO::PARAM_INT);
        $imageStmt->bindValue(':sort_order', $image['sort_order'], PDO::PARAM_INT);
        $imageStmt->bindValue(':status', $image['status'], PDO::PARAM_STR);
        $imageStmt->execute();

        $createdImages[] = [
            'id' => (int)$pdo->lastInsertId(),
            'image' => $relativePath,
            'alt_text' => $image['alt_text'] !== '' ? $image['alt_text'] : null,
            'is_primary' => (int)$image['is_primary'],
            'sort_order' => (int)$image['sort_order'],
            'status' => $image['status']
        ];
    }

    $fetchStmt = $pdo->prepare(
        "SELECT
            pv.id,pv.product_id,p.name AS product_name,p.slug AS product_slug,
            p.category_id,c.name AS category_name,p.hsn_profile_id,
            hp.name AS hsn_profile_name,hp.hsn_code,
            pv.size_id,s.name AS size_name,
            pv.color_id,co.name AS color_name,co.hex_code,
            pv.sku,pv.variant_name,pv.original_price,
            pv.gst_rate,pv.gst_amount,pv.price_with_tax,
            pv.discount_type,pv.discount_value,pv.selling_price,
            pv.stock_quantity,pv.reserved_quantity,pv.low_stock_limit,
            pv.is_available,pv.created_at,pv.updated_at
         FROM product_variants pv
         INNER JOIN products p ON p.id=pv.product_id
         INNER JOIN categories c ON c.id=p.category_id
         LEFT JOIN hsn_profiles hp ON hp.id=p.hsn_profile_id
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors co ON co.id=pv.color_id
         WHERE pv.id=:id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $fetchStmt->execute();

    $variant = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new RuntimeException('Unable to retrieve created variant.');
    }

    $pdo->commit();

    sendResponse(true, 'Product variant created successfully.', [
        'variant' => [
            'id' => (int)$variant['id'],
            'product' => [
                'id' => (int)$variant['product_id'],
                'name' => $variant['product_name'],
                'slug' => $variant['product_slug']
            ],
            'category' => [
                'id' => (int)$variant['category_id'],
                'name' => $variant['category_name']
            ],
            'hsn_profile' => $variant['hsn_profile_id'] !== null ? [
                'id' => (int)$variant['hsn_profile_id'],
                'name' => $variant['hsn_profile_name'],
                'hsn_code' => $variant['hsn_code']
            ] : null,
            'sku' => $variant['sku'],
            'variant_name' => $variant['variant_name'],
            'size' => $variant['size_id'] !== null ? [
                'id' => (int)$variant['size_id'],
                'name' => $variant['size_name']
            ] : null,
            'color' => $variant['color_id'] !== null ? [
                'id' => (int)$variant['color_id'],
                'name' => $variant['color_name'],
                'hex_code' => $variant['hex_code']
            ] : null,
            'pricing' => [
                'original_price' => $variant['original_price'],
                'gst_rate' => $variant['gst_rate'],
                'gst_amount' => $variant['gst_amount'],
                'price_with_tax' => $variant['price_with_tax'],
                'discount_type' => $variant['discount_type'],
                'discount_value' => $variant['discount_value'],
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'selling_price' => $variant['selling_price']
            ],
            'stock' => [
                'stock_quantity' => (int)$variant['stock_quantity'],
                'reserved_quantity' => (int)$variant['reserved_quantity'],
                'available_quantity' => max(
                    0,
                    (int)$variant['stock_quantity'] -
                    (int)$variant['reserved_quantity']
                ),
                'low_stock_limit' => (int)$variant['low_stock_limit']
            ],
            'is_available' => (int)$variant['is_available'],
            'images' => $createdImages,
            'created_at' => $variant['created_at'],
            'updated_at' => $variant['updated_at']
        ]
    ], 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($uploadedFiles as $file) {
        if (is_file($file)) @unlink($file);
    }

    sendResponse(
        false,
        'Unable to create product variant.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($uploadedFiles as $file) {
        if (is_file($file)) @unlink($file);
    }

    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}