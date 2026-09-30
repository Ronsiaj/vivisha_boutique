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

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === false) {
    sendResponse(false, 'Content-Type must be multipart/form-data.', null, 415);
}

$allowedFields = [
    'id','product_id','size_id','color_id','variant_name',
    'original_price','gst_rate','discount_type','discount_value',
    'stock_quantity','reserved_quantity','low_stock_limit','is_available',
    'existing_images','alt_text','is_primary','sort_order','image_status'
];

foreach (array_keys($_POST) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", ['allowed_fields' => $allowedFields], 422);
    }
}

foreach (array_keys($_FILES) as $field) {
    if ($field !== 'images') {
        sendResponse(false, "Invalid file field: {$field}.", ['allowed_file_field' => 'images'], 422);
    }
}

function positiveId(mixed $value): bool
{
    return preg_match('/^[1-9][0-9]*$/', trim((string)$value)) === 1;
}

function parseBooleanField(mixed $value, string $field): int
{
    $value = strtolower(trim((string)$value));
    if (in_array($value, ['1','true'], true)) return 1;
    if (in_array($value, ['0','false'], true)) return 0;
    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return 0;
}

function validAmount(mixed $value): bool
{
    return is_numeric($value) &&
        (float)$value >= 0 &&
        (float)$value <= 99999999.99;
}

function deleteFiles(array $files): void
{
    foreach ($files as $file) {
        if (is_string($file) && $file !== '' && is_file($file)) @unlink($file);
    }
}

$variantIdInput = trim((string)($_POST['id'] ?? ''));

if ($variantIdInput === '') {
    sendResponse(false, 'Variant ID is required.', null, 422);
}
if (!positiveId($variantIdInput)) {
    sendResponse(false, 'Variant ID must be a valid positive integer.', null, 422);
}

$variantId = (int)$variantIdInput;

$hasProductId = array_key_exists('product_id', $_POST);
$hasSizeId = array_key_exists('size_id', $_POST);
$hasColorId = array_key_exists('color_id', $_POST);
$hasVariantName = array_key_exists('variant_name', $_POST);
$hasOriginalPrice = array_key_exists('original_price', $_POST);
$hasGstRate = array_key_exists('gst_rate', $_POST);
$hasDiscountType = array_key_exists('discount_type', $_POST);
$hasDiscountValue = array_key_exists('discount_value', $_POST);
$hasStockQuantity = array_key_exists('stock_quantity', $_POST);
$hasReservedQuantity = array_key_exists('reserved_quantity', $_POST);
$hasLowStockLimit = array_key_exists('low_stock_limit', $_POST);
$hasIsAvailable = array_key_exists('is_available', $_POST);
$hasExistingImages = array_key_exists('existing_images', $_POST);
$hasNewImages = isset($_FILES['images']);

if (
    !$hasProductId && !$hasSizeId && !$hasColorId && !$hasVariantName &&
    !$hasOriginalPrice && !$hasGstRate && !$hasDiscountType &&
    !$hasDiscountValue && !$hasStockQuantity && !$hasReservedQuantity &&
    !$hasLowStockLimit && !$hasIsAvailable && !$hasExistingImages &&
    !$hasNewImages
) {
    sendResponse(false, 'At least one field must be provided for update.', null, 422);
}

$newUploadedFiles = [];
$oldFilesToDelete = [];

try {
    $stmt = $pdo->prepare(
        "SELECT id,product_id,size_id,color_id,sku,variant_name,
                original_price,gst_rate,gst_amount,price_with_tax,
                discount_type,discount_value,selling_price,
                stock_quantity,reserved_quantity,low_stock_limit,is_available
         FROM product_variants
         WHERE id=:id LIMIT 1"
    );
    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        sendResponse(false, 'Product variant not found.', null, 404);
    }

    $productId = (int)$existing['product_id'];
    $sizeId = $existing['size_id'] !== null ? (int)$existing['size_id'] : null;
    $colorId = $existing['color_id'] !== null ? (int)$existing['color_id'] : null;
    $sku = $existing['sku'];
    $variantName = $existing['variant_name'];
    $originalPrice = (float)$existing['original_price'];
    $gstRate = (float)$existing['gst_rate'];
    $gstAmount = (float)$existing['gst_amount'];
    $priceWithTax = (float)$existing['price_with_tax'];
    $discountType = $existing['discount_type'];
    $discountValue = (float)$existing['discount_value'];
    $sellingPrice = (float)$existing['selling_price'];
    $stockQuantity = (int)$existing['stock_quantity'];
    $reservedQuantity = (int)$existing['reserved_quantity'];
    $lowStockLimit = (int)$existing['low_stock_limit'];
    $isAvailable = (int)$existing['is_available'];

    if ($hasProductId) {
        $value = trim((string)$_POST['product_id']);
        if (!positiveId($value)) {
            sendResponse(false, 'product_id must be a valid positive integer.', null, 422);
        }
        $productId = (int)$value;
    }

    $stmt = $pdo->prepare(
        "SELECT id,name,status FROM products WHERE id=:id LIMIT 1"
    );
    $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    if ($hasSizeId) {
        $value = trim((string)$_POST['size_id']);
        if ($value === '') {
            $sizeId = null;
        } else {
            if (!positiveId($value)) {
                sendResponse(false, 'size_id must be a valid positive integer or empty.', null, 422);
            }
            $sizeId = (int)$value;
        }
    }

    if ($sizeId !== null) {
        $stmt = $pdo->prepare(
            "SELECT id,name,status FROM sizes WHERE id=:id LIMIT 1"
        );
        $stmt->bindValue(':id', $sizeId, PDO::PARAM_INT);
        $stmt->execute();

        $size = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$size) sendResponse(false, 'Size not found.', null, 404);
        if ($size['status'] !== 'active') {
            sendResponse(false, 'Selected size is inactive.', null, 422);
        }
    }

    if ($hasColorId) {
        $value = trim((string)$_POST['color_id']);
        if ($value === '') {
            $colorId = null;
        } else {
            if (!positiveId($value)) {
                sendResponse(false, 'color_id must be a valid positive integer or empty.', null, 422);
            }
            $colorId = (int)$value;
        }
    }

    if ($colorId !== null) {
        $stmt = $pdo->prepare(
            "SELECT id,name,hex_code,status FROM colors WHERE id=:id LIMIT 1"
        );
        $stmt->bindValue(':id', $colorId, PDO::PARAM_INT);
        $stmt->execute();

        $color = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$color) sendResponse(false, 'Color not found.', null, 404);
        if ($color['status'] !== 'active') {
            sendResponse(false, 'Selected color is inactive.', null, 422);
        }
    }

    if ($sizeId === null && $colorId === null) {
        sendResponse(false, 'At least size_id or color_id must be available.', null, 422);
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM product_variants
         WHERE LOWER(sku)=LOWER(:sku) AND id!=:id LIMIT 1"
    );
    $stmt->bindValue(':sku', $sku, PDO::PARAM_STR);
    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->fetch()) {
        sendResponse(false, 'SKU already exists.', ['sku' => $sku], 409);
    }

    if ($hasVariantName) {
        $variantName = trim((string)$_POST['variant_name']);

        if ($variantName === '') {
            $variantName = null;
        } else {
            if (mb_strlen($variantName) > 150) {
                sendResponse(false, 'variant_name must not exceed 150 characters.', null, 422);
            }
            if (!preg_match('/^[\p{L}\p{N}\s\-\&\'\/().,+]+$/u', $variantName)) {
                sendResponse(false, 'variant_name contains invalid characters.', null, 422);
            }
        }
    }

    if ($hasOriginalPrice) {
        $value = trim((string)$_POST['original_price']);

        if (!validAmount($value) || (float)$value <= 0) {
            sendResponse(false, 'original_price must be greater than 0 and within allowed range.', null, 422);
        }

        $originalPrice = round((float)$value, 2);
    }

    if ($hasGstRate) {
        $value = trim((string)$_POST['gst_rate']);

        if ($value === '' || !is_numeric($value)) {
            sendResponse(false, 'gst_rate must be a valid percentage.', null, 422);
        }

        $gstRate = round((float)$value, 2);

        if ($gstRate < 0 || $gstRate > 100) {
            sendResponse(false, 'gst_rate must be between 0 and 100.', null, 422);
        }
    }

    if ($hasDiscountType) {
        $discountType = strtolower(trim((string)$_POST['discount_type']));

        if (!in_array($discountType, ['none','percentage','flat'], true)) {
            sendResponse(false, 'Invalid discount_type.', [
                'allowed_discount_types' => ['none','percentage','flat']
            ], 422);
        }

        if ($discountType === 'none' && !$hasDiscountValue) {
            $discountValue = 0.00;
        }
    }

    if ($hasDiscountValue) {
        $value = trim((string)$_POST['discount_value']);

        if (!validAmount($value)) {
            sendResponse(false, 'discount_value must be a valid non-negative amount.', null, 422);
        }

        $discountValue = round((float)$value, 2);
    }

    $pricingChanged =
        $hasOriginalPrice ||
        $hasGstRate ||
        $hasDiscountType ||
        $hasDiscountValue;

    if ($pricingChanged) {
        $gstAmount = round(($originalPrice * $gstRate) / 100, 2);
        $priceWithTax = round($originalPrice + $gstAmount, 2);

        if ($priceWithTax > 99999999.99) {
            sendResponse(false, 'price_with_tax exceeds maximum allowed value.', null, 422);
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

            $discountAmount = round(
                ($priceWithTax * $discountValue) / 100,
                2
            );
        } else {
            if ($discountValue > $priceWithTax) {
                sendResponse(false, 'Flat discount cannot exceed price_with_tax.', [
                    'price_with_tax' => number_format($priceWithTax, 2, '.', '')
                ], 422);
            }

            $discountAmount = $discountValue;
        }

        $sellingPrice = round(
            max(0, $priceWithTax - $discountAmount),
            2
        );
    }

    if ($hasStockQuantity) {
        $value = trim((string)$_POST['stock_quantity']);

        if (
            filter_var($value, FILTER_VALIDATE_INT) === false ||
            (int)$value < 0
        ) {
            sendResponse(false, 'stock_quantity must be a non-negative integer.', null, 422);
        }

        $stockQuantity = (int)$value;
    }

    if ($hasReservedQuantity) {
        $value = trim((string)$_POST['reserved_quantity']);

        if (
            filter_var($value, FILTER_VALIDATE_INT) === false ||
            (int)$value < 0
        ) {
            sendResponse(false, 'reserved_quantity must be a non-negative integer.', null, 422);
        }

        $reservedQuantity = (int)$value;
    }

    if ($reservedQuantity > $stockQuantity) {
        sendResponse(false, 'reserved_quantity cannot exceed stock_quantity.', null, 422);
    }

    if ($hasLowStockLimit) {
        $value = trim((string)$_POST['low_stock_limit']);

        if (
            filter_var($value, FILTER_VALIDATE_INT) === false ||
            (int)$value < 0
        ) {
            sendResponse(false, 'low_stock_limit must be a non-negative integer.', null, 422);
        }

        $lowStockLimit = (int)$value;
    }

    if ($hasIsAvailable) {
        $isAvailable = parseBooleanField(
            $_POST['is_available'],
            'is_available'
        );
    }

    if ($sizeId !== null && $colorId !== null) {
        $sql = "SELECT id FROM product_variants
                WHERE product_id=:product_id
                AND size_id=:size_id
                AND color_id=:color_id
                AND id!=:id LIMIT 1";
    } elseif ($sizeId !== null) {
        $sql = "SELECT id FROM product_variants
                WHERE product_id=:product_id
                AND size_id=:size_id
                AND color_id IS NULL
                AND id!=:id LIMIT 1";
    } else {
        $sql = "SELECT id FROM product_variants
                WHERE product_id=:product_id
                AND size_id IS NULL
                AND color_id=:color_id
                AND id!=:id LIMIT 1";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);

    if ($sizeId !== null) {
        $stmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
    }

    if ($colorId !== null) {
        $stmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
    }

    $stmt->execute();

    if ($stmt->fetch()) {
        sendResponse(
            false,
            'This size and color combination already exists for the product.',
            null,
            409
        );
    }

    $stmt = $pdo->prepare(
        "SELECT id,image,alt_text,is_primary,sort_order,status
         FROM product_variant_images
         WHERE variant_id=:variant_id"
    );

    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $imageMap = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
        $imageMap[(int)$image['id']] = [
            'id' => (int)$image['id'],
            'image' => $image['image'],
            'alt_text' => $image['alt_text'],
            'is_primary' => (int)$image['is_primary'],
            'sort_order' => (int)$image['sort_order'],
            'status' => $image['status'],
            'remove' => false
        ];
    }

    if ($hasExistingImages) {
        $json = trim((string)$_POST['existing_images']);

        if ($json === '') {
            sendResponse(false, 'existing_images cannot be empty when provided.', null, 422);
        }

        $items = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
            sendResponse(false, 'existing_images must be valid JSON array.', null, 422);
        }

        foreach ($items as $index => $item) {
            $number = $index + 1;

            if (!is_array($item)) {
                sendResponse(false, "existing_images item {$number} must be an object.", null, 422);
            }

            if (!isset($item['id']) || !positiveId($item['id'])) {
                sendResponse(false, "existing_images item {$number}: valid id is required.", null, 422);
            }

            $imageId = (int)$item['id'];

            if (!isset($imageMap[$imageId])) {
                sendResponse(false, "Image ID {$imageId} does not belong to this variant.", null, 404);
            }

            $allowedImageFields = [
                'id','alt_text','is_primary','sort_order','status','remove'
            ];

            foreach (array_keys($item) as $field) {
                if (!in_array($field, $allowedImageFields, true)) {
                    sendResponse(false, "Invalid existing image field: {$field}.", null, 422);
                }
            }

            if (array_key_exists('alt_text', $item)) {
                if ($item['alt_text'] !== null && !is_string($item['alt_text'])) {
                    sendResponse(false, "Image {$imageId}: alt_text must be string or null.", null, 422);
                }

                $alt = $item['alt_text'] === null
                    ? null
                    : trim($item['alt_text']);

                if ($alt !== null && mb_strlen($alt) > 255) {
                    sendResponse(false, "Image {$imageId}: alt_text must not exceed 255 characters.", null, 422);
                }

                $imageMap[$imageId]['alt_text'] =
                    $alt === '' ? null : $alt;
            }

            if (array_key_exists('is_primary', $item)) {
                $imageMap[$imageId]['is_primary'] =
                    parseBooleanField($item['is_primary'], 'is_primary');
            }

            if (array_key_exists('sort_order', $item)) {
                if (
                    filter_var($item['sort_order'], FILTER_VALIDATE_INT) === false ||
                    (int)$item['sort_order'] < 0
                ) {
                    sendResponse(false, "Image {$imageId}: sort_order must be non-negative integer.", null, 422);
                }

                $imageMap[$imageId]['sort_order'] =
                    (int)$item['sort_order'];
            }

            if (array_key_exists('status', $item)) {
                $status = strtolower(trim((string)$item['status']));

                if (!in_array($status, ['active','inactive'], true)) {
                    sendResponse(false, "Image {$imageId}: invalid status.", null, 422);
                }

                $imageMap[$imageId]['status'] = $status;
            }

            if (array_key_exists('remove', $item)) {
                $imageMap[$imageId]['remove'] =
                    parseBooleanField($item['remove'], 'remove') === 1;
            }
        }
    }

    $altTexts = $_POST['alt_text'] ?? [];
    $primaryValues = $_POST['is_primary'] ?? [];
    $sortOrders = $_POST['sort_order'] ?? [];
    $statuses = $_POST['image_status'] ?? [];

    $altTexts = is_array($altTexts) ? $altTexts : [$altTexts];
    $primaryValues = is_array($primaryValues) ? $primaryValues : [$primaryValues];
    $sortOrders = is_array($sortOrders) ? $sortOrders : [$sortOrders];
    $statuses = is_array($statuses) ? $statuses : [$statuses];

    $preparedImages = [];

    if ($hasNewImages) {
        $files = $_FILES['images'];

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        $validCount = 0;

        foreach ($errors as $error) {
            if ($error !== UPLOAD_ERR_NO_FILE) $validCount++;
        }

        if ($validCount > 10) {
            sendResponse(false, 'Maximum 10 new images are allowed.', null, 422);
        }

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
                sendResponse(false, 'Only JPG, JPEG, PNG and WEBP images are allowed.', null, 422);
            }

            $info = @getimagesize($tmp);

            if (
                !$info ||
                $info[0] <= 0 ||
                $info[1] <= 0 ||
                $info[0] > 8000 ||
                $info[1] > 8000
            ) {
                sendResponse(false, 'Invalid image dimensions.', null, 422);
            }

            $alt = trim((string)($altTexts[$i] ?? ''));

            if (mb_strlen($alt) > 255) {
                sendResponse(false, 'Image alt_text must not exceed 255 characters.', null, 422);
            }

            $primary = parseBooleanField(
                $primaryValues[$i] ?? '0',
                'is_primary'
            );

            $sort = $sortOrders[$i] ?? ($i + 1);

            if (
                filter_var($sort, FILTER_VALIDATE_INT) === false ||
                (int)$sort < 0
            ) {
                sendResponse(false, 'Image sort_order must be a non-negative integer.', null, 422);
            }

            $status = strtolower(trim((string)($statuses[$i] ?? 'active')));

            if (!in_array($status, ['active','inactive'], true)) {
                sendResponse(false, 'Invalid image status.', null, 422);
            }

            $preparedImages[] = [
                'tmp_name' => $tmp,
                'extension' => $allowedTypes[$mime],
                'alt_text' => $alt === '' ? null : $alt,
                'is_primary' => $primary,
                'sort_order' => (int)$sort,
                'status' => $status
            ];
        }
    }

    $primaryCount = 0;

    foreach ($imageMap as $image) {
        if (!$image['remove'] && $image['is_primary'] === 1) {
            $primaryCount++;
        }
    }

    foreach ($preparedImages as $image) {
        if ($image['is_primary'] === 1) {
            $primaryCount++;
        }
    }

    if ($primaryCount > 1) {
        sendResponse(false, 'Only one primary image is allowed for a variant.', null, 422);
    }

    $uploadDirectory = __DIR__ . '/../../uploads/products/';

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

    $stmt = $pdo->prepare(
        "UPDATE product_variants SET
            product_id=:product_id,
            size_id=:size_id,
            color_id=:color_id,
            variant_name=:variant_name,
            original_price=:original_price,
            gst_rate=:gst_rate,
            gst_amount=:gst_amount,
            price_with_tax=:price_with_tax,
            discount_type=:discount_type,
            discount_value=:discount_value,
            selling_price=:selling_price,
            stock_quantity=:stock_quantity,
            reserved_quantity=:reserved_quantity,
            low_stock_limit=:low_stock_limit,
            is_available=:is_available
         WHERE id=:id"
    );

    $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':size_id', $sizeId, $sizeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':color_id', $colorId, $colorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':variant_name', $variantName, $variantName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    foreach ($imageMap as $image) {
        if ($image['remove']) {
            $stmt = $pdo->prepare(
                "DELETE FROM product_variant_images
                 WHERE id=:id AND variant_id=:variant_id"
            );

            $stmt->bindValue(':id', $image['id'], PDO::PARAM_INT);
            $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
            $stmt->execute();

            if (!empty($image['image'])) {
                $oldFilesToDelete[] =
                    __DIR__ . '/../../' . ltrim($image['image'], '/');
            }

            continue;
        }

        $stmt = $pdo->prepare(
            "UPDATE product_variant_images SET
                alt_text=:alt_text,
                is_primary=:is_primary,
                sort_order=:sort_order,
                status=:status
             WHERE id=:id AND variant_id=:variant_id"
        );

        $stmt->bindValue(':alt_text', $image['alt_text'], $image['alt_text'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_primary', $image['is_primary'], PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', $image['sort_order'], PDO::PARAM_INT);
        $stmt->bindValue(':status', $image['status'], PDO::PARAM_STR);
        $stmt->bindValue(':id', $image['id'], PDO::PARAM_INT);
        $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $stmt->execute();
    }

    foreach ($preparedImages as $image) {
        $fileName =
            'product_' . $productId .
            '_variant_' . $variantId .
            '_' . bin2hex(random_bytes(12)) .
            '.' . $image['extension'];

        $fullPath = $uploadDirectory . $fileName;

        if (!move_uploaded_file($image['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to save new variant image.');
        }

        $newUploadedFiles[] = $fullPath;
        $relativePath = 'uploads/products/' . $fileName;

        $stmt = $pdo->prepare(
            "INSERT INTO product_variant_images (
                variant_id,image,alt_text,is_primary,sort_order,status
             ) VALUES (
                :variant_id,:image,:alt_text,:is_primary,:sort_order,:status
             )"
        );

        $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $stmt->bindValue(':image', $relativePath, PDO::PARAM_STR);
        $stmt->bindValue(':alt_text', $image['alt_text'], $image['alt_text'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':is_primary', $image['is_primary'], PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', $image['sort_order'], PDO::PARAM_INT);
        $stmt->bindValue(':status', $image['status'], PDO::PARAM_STR);
        $stmt->execute();
    }

    $stmt = $pdo->prepare(
        "SELECT
            pv.id,pv.product_id,p.name AS product_name,
            pv.size_id,s.name AS size_name,
            pv.color_id,c.name AS color_name,c.hex_code,
            pv.sku,pv.variant_name,pv.original_price,
            pv.gst_rate,pv.gst_amount,pv.price_with_tax,
            pv.discount_type,pv.discount_value,pv.selling_price,
            pv.stock_quantity,pv.reserved_quantity,pv.low_stock_limit,
            pv.is_available,pv.created_at,pv.updated_at
         FROM product_variants pv
         INNER JOIN products p ON p.id=pv.product_id
         LEFT JOIN sizes s ON s.id=pv.size_id
         LEFT JOIN colors c ON c.id=pv.color_id
         WHERE pv.id=:id LIMIT 1"
    );

    $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new RuntimeException('Unable to retrieve updated variant.');
    }

    $stmt = $pdo->prepare(
        "SELECT id,variant_id,image,alt_text,is_primary,sort_order,status,created_at,updated_at
         FROM product_variant_images
         WHERE variant_id=:variant_id
         ORDER BY is_primary DESC,sort_order ASC,id ASC"
    );

    $stmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $stmt->execute();

    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();
    deleteFiles($oldFilesToDelete);

    $formattedImages = array_map(static fn(array $image): array => [
        'id' => (int)$image['id'],
        'variant_id' => (int)$image['variant_id'],
        'image' => $image['image'],
        'alt_text' => $image['alt_text'],
        'is_primary' => (int)$image['is_primary'],
        'sort_order' => (int)$image['sort_order'],
        'status' => $image['status'],
        'created_at' => $image['created_at'],
        'updated_at' => $image['updated_at']
    ], $images);

    sendResponse(true, 'Product variant updated successfully.', [
        'variant' => [
            'id' => (int)$variant['id'],
            'product_id' => (int)$variant['product_id'],
            'product_name' => $variant['product_name'],
            'size' => $variant['size_id'] !== null ? [
                'id' => (int)$variant['size_id'],
                'name' => $variant['size_name']
            ] : null,
            'color' => $variant['color_id'] !== null ? [
                'id' => (int)$variant['color_id'],
                'name' => $variant['color_name'],
                'hex_code' => $variant['hex_code']
            ] : null,
            'sku' => $variant['sku'],
            'variant_name' => $variant['variant_name'],
            'pricing' => [
                'original_price' => $variant['original_price'],
                'gst_rate' => $variant['gst_rate'],
                'gst_amount' => $variant['gst_amount'],
                'price_with_tax' => $variant['price_with_tax'],
                'discount_type' => $variant['discount_type'],
                'discount_value' => $variant['discount_value'],
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
            'images' => $formattedImages,
            'created_at' => $variant['created_at'],
            'updated_at' => $variant['updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    deleteFiles($newUploadedFiles);

    sendResponse(
        false,
        'Unable to update product variant.',
        APP_ENV === 'development' ? ['error' => $e->getMessage()] : null,
        500
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    deleteFiles($newUploadedFiles);

    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development' ? ['error' => $e->getMessage()] : null,
        500
    );
}