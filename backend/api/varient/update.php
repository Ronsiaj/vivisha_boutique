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

$accountType = getAuthenticatedType($decoded);
$authenticatedId = getAuthenticatedId($decoded);

if ($authenticatedId <= 0) {
    sendResponse(false, 'Invalid authenticated account.', null, 401);
}

if ($accountType !== 'admin') {
    sendResponse(false, 'Admin access only.', null, 403);
}

$adminAuth = authenticateAdmin();
checkAdminRole($adminAuth, ['admin']);

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'multipart/form-data') === false) {
    sendResponse(false, 'Content-Type must be multipart/form-data.', null, 415);
}

function positiveId(mixed $value): bool
{
    return preg_match('/^[1-9][0-9]*$/', trim((string)$value)) === 1;
}

function parseBooleanField(mixed $value, string $field): int
{
    $value = strtolower(trim((string)$value));

    if (in_array($value, ['1', 'true'], true)) {
        return 1;
    }

    if (in_array($value, ['0', 'false'], true)) {
        return 0;
    }

    sendResponse(false, "{$field} must be 0, 1, true or false.", null, 422);
    return 0;
}

function validAmount(mixed $value): bool
{
    if (!is_numeric($value)) {
        return false;
    }

    $amount = (float)$value;

    return $amount >= 0 && $amount <= 99999999.99;
}

function deleteFiles(array $files): void
{
    foreach ($files as $file) {
        if (
            is_string($file) &&
            $file !== '' &&
            file_exists($file) &&
            is_file($file)
        ) {
            @unlink($file);
        }
    }
}

$variantIdInput = isset($_POST['id'])
    ? trim((string)$_POST['id'])
    : '';

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
$hasSku = array_key_exists('sku', $_POST);
$hasVariantName = array_key_exists('variant_name', $_POST);
$hasOriginalPrice = array_key_exists('original_price', $_POST);
$hasDiscountType = array_key_exists('discount_type', $_POST);
$hasDiscountValue = array_key_exists('discount_value', $_POST);
$hasStockQuantity = array_key_exists('stock_quantity', $_POST);
$hasReservedQuantity = array_key_exists('reserved_quantity', $_POST);
$hasLowStockLimit = array_key_exists('low_stock_limit', $_POST);
$hasIsAvailable = array_key_exists('is_available', $_POST);
$hasExistingImages = array_key_exists('existing_images', $_POST);
$hasNewImages = isset($_FILES['images']);

if (
    !$hasProductId &&
    !$hasSizeId &&
    !$hasColorId &&
    !$hasSku &&
    !$hasVariantName &&
    !$hasOriginalPrice &&
    !$hasDiscountType &&
    !$hasDiscountValue &&
    !$hasStockQuantity &&
    !$hasReservedQuantity &&
    !$hasLowStockLimit &&
    !$hasIsAvailable &&
    !$hasExistingImages &&
    !$hasNewImages
) {
    sendResponse(false, 'At least one field must be provided for update.', null, 422);
}

try {
    $existingStmt = $pdo->prepare(
        "SELECT
            id,
            product_id,
            size_id,
            color_id,
            sku,
            variant_name,
            original_price,
            discount_type,
            discount_value,
            selling_price,
            stock_quantity,
            reserved_quantity,
            low_stock_limit,
            is_available
         FROM product_variants
         WHERE id = :id
         LIMIT 1"
    );

    $existingStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $existingStmt->execute();

    $existingVariant = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingVariant) {
        sendResponse(false, 'Product variant not found.', null, 404);
    }

    $productId = (int)$existingVariant['product_id'];
    $sizeId = $existingVariant['size_id'] !== null
        ? (int)$existingVariant['size_id']
        : null;
    $colorId = $existingVariant['color_id'] !== null
        ? (int)$existingVariant['color_id']
        : null;

    $sku = $existingVariant['sku'];
    $variantName = $existingVariant['variant_name'];
    $originalPrice = (float)$existingVariant['original_price'];
    $discountType = $existingVariant['discount_type'];
    $discountValue = (float)$existingVariant['discount_value'];
    $stockQuantity = (int)$existingVariant['stock_quantity'];
    $reservedQuantity = (int)$existingVariant['reserved_quantity'];
    $lowStockLimit = (int)$existingVariant['low_stock_limit'];
    $isAvailable = (int)$existingVariant['is_available'];

    if ($hasProductId) {
        $value = trim((string)$_POST['product_id']);

        if (!positiveId($value)) {
            sendResponse(false, 'product_id must be a valid positive integer.', null, 422);
        }

        $productId = (int)$value;

        $productStmt = $pdo->prepare(
            "SELECT id
             FROM products
             WHERE id = :id
             LIMIT 1"
        );

        $productStmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $productStmt->execute();

        if (!$productStmt->fetch()) {
            sendResponse(false, 'Product not found.', null, 404);
        }
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

            $sizeStmt = $pdo->prepare(
                "SELECT id, status
                 FROM sizes
                 WHERE id = :id
                 LIMIT 1"
            );

            $sizeStmt->bindValue(':id', $sizeId, PDO::PARAM_INT);
            $sizeStmt->execute();

            $size = $sizeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$size) {
                sendResponse(false, 'Size not found.', null, 404);
            }

            if ($size['status'] !== 'active') {
                sendResponse(false, 'Selected size is inactive.', null, 422);
            }
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

            $colorStmt = $pdo->prepare(
                "SELECT id, status
                 FROM colors
                 WHERE id = :id
                 LIMIT 1"
            );

            $colorStmt->bindValue(':id', $colorId, PDO::PARAM_INT);
            $colorStmt->execute();

            $color = $colorStmt->fetch(PDO::FETCH_ASSOC);

            if (!$color) {
                sendResponse(false, 'Color not found.', null, 404);
            }

            if ($color['status'] !== 'active') {
                sendResponse(false, 'Selected color is inactive.', null, 422);
            }
        }
    }

    if ($sizeId === null && $colorId === null) {
        sendResponse(false, 'At least size_id or color_id must be available.', null, 422);
    }

    if ($hasSku) {
        $sku = strtoupper(trim((string)$_POST['sku']));

        if ($sku === '') {
            sendResponse(false, 'SKU cannot be empty.', null, 422);
        }

        if (mb_strlen($sku) > 100) {
            sendResponse(false, 'SKU must not exceed 100 characters.', null, 422);
        }

        if (!preg_match('/^[A-Z0-9\-_]+$/', $sku)) {
            sendResponse(false, 'SKU may contain only letters, numbers, hyphen and underscore.', null, 422);
        }
    }

    $skuStmt = $pdo->prepare(
        "SELECT id
         FROM product_variants
         WHERE LOWER(sku) = LOWER(:sku)
         AND id != :id
         LIMIT 1"
    );

    $skuStmt->bindValue(':sku', $sku, PDO::PARAM_STR);
    $skuStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $skuStmt->execute();

    if ($skuStmt->fetch()) {
        sendResponse(false, 'SKU already exists.', null, 409);
    }

    if ($hasVariantName) {
        $variantName = trim((string)$_POST['variant_name']);

        if ($variantName === '') {
            $variantName = null;
        } elseif (mb_strlen($variantName) > 150) {
            sendResponse(false, 'variant_name must not exceed 150 characters.', null, 422);
        }
    }

    if ($hasOriginalPrice) {
        $value = trim((string)$_POST['original_price']);

        if (!validAmount($value)) {
            sendResponse(false, 'original_price must be a valid non-negative amount.', null, 422);
        }

        $originalPrice = round((float)$value, 2);
    }

    if ($hasDiscountType) {
        $discountType = strtolower(trim((string)$_POST['discount_type']));

        if (!in_array($discountType, ['none', 'percentage', 'flat'], true)) {
            sendResponse(false, 'Invalid discount_type.', [
                'allowed_discount_types' => ['none', 'percentage', 'flat']
            ], 422);
        }
    }

    if ($hasDiscountValue) {
        $value = trim((string)$_POST['discount_value']);

        if (!validAmount($value)) {
            sendResponse(false, 'discount_value must be a valid non-negative amount.', null, 422);
        }

        $discountValue = round((float)$value, 2);
    }

    if ($discountType === 'none') {
        if ($discountValue != 0) {
            sendResponse(false, 'discount_value must be 0 when discount_type is none.', null, 422);
        }

        $sellingPrice = $originalPrice;

    } elseif ($discountType === 'percentage') {
        if ($discountValue > 100) {
            sendResponse(false, 'Percentage discount cannot exceed 100.', null, 422);
        }

        $sellingPrice = $originalPrice - (($originalPrice * $discountValue) / 100);

    } else {
        if ($discountValue > $originalPrice) {
            sendResponse(false, 'Flat discount cannot exceed original_price.', null, 422);
        }

        $sellingPrice = $originalPrice - $discountValue;
    }

    $sellingPrice = round(max(0, $sellingPrice), 2);

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
        $combinationStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             AND size_id = :size_id
             AND color_id = :color_id
             AND id != :id
             LIMIT 1"
        );

        $combinationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':id', $variantId, PDO::PARAM_INT);

    } elseif ($sizeId !== null) {
        $combinationStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             AND size_id = :size_id
             AND color_id IS NULL
             AND id != :id
             LIMIT 1"
        );

        $combinationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':id', $variantId, PDO::PARAM_INT);

    } else {
        $combinationStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             AND size_id IS NULL
             AND color_id = :color_id
             AND id != :id
             LIMIT 1"
        );

        $combinationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    }

    $combinationStmt->execute();

    if ($combinationStmt->fetch()) {
        sendResponse(
            false,
            'This size and color combination already exists for the product.',
            null,
            409
        );
    }

    $currentImagesStmt = $pdo->prepare(
        "SELECT
            id,
            image,
            alt_text,
            is_primary,
            sort_order,
            status
         FROM product_variant_images
         WHERE variant_id = :variant_id"
    );

    $currentImagesStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $currentImagesStmt->execute();

    $currentImages = $currentImagesStmt->fetchAll(PDO::FETCH_ASSOC);

    $imageMap = [];

    foreach ($currentImages as $image) {
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
        $existingImagesJson = trim((string)$_POST['existing_images']);

        if ($existingImagesJson === '') {
            sendResponse(false, 'existing_images cannot be empty when provided.', null, 422);
        }

        $existingImagesInput = json_decode($existingImagesJson, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            !is_array($existingImagesInput)
        ) {
            sendResponse(false, 'existing_images must be valid JSON array.', null, 422);
        }

        foreach ($existingImagesInput as $index => $imageInput) {
            $number = $index + 1;

            if (!is_array($imageInput)) {
                sendResponse(false, "existing_images item {$number} must be an object.", null, 422);
            }

            if (!isset($imageInput['id']) || !positiveId($imageInput['id'])) {
                sendResponse(false, "existing_images item {$number}: valid id is required.", null, 422);
            }

            $imageId = (int)$imageInput['id'];

            if (!isset($imageMap[$imageId])) {
                sendResponse(
                    false,
                    "Image ID {$imageId} does not belong to this variant.",
                    null,
                    404
                );
            }

            if (array_key_exists('alt_text', $imageInput)) {
                if (
                    $imageInput['alt_text'] !== null &&
                    !is_string($imageInput['alt_text'])
                ) {
                    sendResponse(false, "Image {$imageId}: alt_text must be string or null.", null, 422);
                }

                $altText = $imageInput['alt_text'] === null
                    ? null
                    : trim($imageInput['alt_text']);

                if ($altText !== null && mb_strlen($altText) > 255) {
                    sendResponse(false, "Image {$imageId}: alt_text must not exceed 255 characters.", null, 422);
                }

                $imageMap[$imageId]['alt_text'] =
                    ($altText === '') ? null : $altText;
            }

            if (array_key_exists('is_primary', $imageInput)) {
                $imageMap[$imageId]['is_primary'] = parseBooleanField(
                    $imageInput['is_primary'],
                    'is_primary'
                );
            }

            if (array_key_exists('sort_order', $imageInput)) {
                if (
                    filter_var($imageInput['sort_order'], FILTER_VALIDATE_INT) === false ||
                    (int)$imageInput['sort_order'] < 0
                ) {
                    sendResponse(false, "Image {$imageId}: sort_order must be non-negative integer.", null, 422);
                }

                $imageMap[$imageId]['sort_order'] =
                    (int)$imageInput['sort_order'];
            }

            if (array_key_exists('status', $imageInput)) {
                $imageStatus = strtolower(trim((string)$imageInput['status']));

                if (!in_array($imageStatus, ['active', 'inactive'], true)) {
                    sendResponse(false, "Image {$imageId}: invalid status.", null, 422);
                }

                $imageMap[$imageId]['status'] = $imageStatus;
            }

            if (array_key_exists('remove', $imageInput)) {
                $imageMap[$imageId]['remove'] = parseBooleanField(
                    $imageInput['remove'],
                    'remove'
                ) === 1;
            }
        }
    }

    $newAltTexts = $_POST['alt_text'] ?? [];
    $newPrimaryValues = $_POST['is_primary'] ?? [];
    $newSortOrders = $_POST['sort_order'] ?? [];
    $newStatuses = $_POST['image_status'] ?? [];

    if (!is_array($newAltTexts)) {
        $newAltTexts = [$newAltTexts];
    }

    if (!is_array($newPrimaryValues)) {
        $newPrimaryValues = [$newPrimaryValues];
    }

    if (!is_array($newSortOrders)) {
        $newSortOrders = [$newSortOrders];
    }

    if (!is_array($newStatuses)) {
        $newStatuses = [$newStatuses];
    }

    $preparedImages = [];

    if ($hasNewImages) {
        $files = $_FILES['images'];

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
        $fileSizes = is_array($files['size']) ? $files['size'] : [$files['size']];

        $validCount = 0;

        foreach ($errors as $error) {
            if ($error !== UPLOAD_ERR_NO_FILE) {
                $validCount++;
            }
        }

        if ($validCount > 10) {
            sendResponse(false, 'Maximum 10 new images are allowed.', null, 422);
        }

        foreach ($names as $index => $originalName) {
            $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                sendResponse(false, 'Image ' . ($index + 1) . ' upload failed.', null, 422);
            }

            $tmpName = $tmpNames[$index] ?? '';
            $fileSize = (int)($fileSizes[$index] ?? 0);

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                sendResponse(false, 'Invalid uploaded image.', null, 422);
            }

            if ($fileSize <= 0) {
                sendResponse(false, 'Uploaded image is empty.', null, 422);
            }

            if ($fileSize > 5 * 1024 * 1024) {
                sendResponse(false, 'Each image must not exceed 5 MB.', null, 422);
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowedTypes[$mimeType])) {
                sendResponse(
                    false,
                    'Only JPG, JPEG, PNG and WEBP images are allowed.',
                    null,
                    422
                );
            }

            $imageInfo = @getimagesize($tmpName);

            if ($imageInfo === false) {
                sendResponse(false, 'Uploaded file is not a valid image.', null, 422);
            }

            if (
                (int)$imageInfo[0] <= 0 ||
                (int)$imageInfo[1] <= 0 ||
                (int)$imageInfo[0] > 8000 ||
                (int)$imageInfo[1] > 8000
            ) {
                sendResponse(false, 'Invalid image dimensions.', null, 422);
            }

            $altText = isset($newAltTexts[$index])
                ? trim((string)$newAltTexts[$index])
                : '';

            if ($altText !== '' && mb_strlen($altText) > 255) {
                sendResponse(false, 'Image alt_text must not exceed 255 characters.', null, 422);
            }

            $primaryInput = $newPrimaryValues[$index] ?? '0';

            $isPrimary = parseBooleanField(
                $primaryInput,
                'is_primary'
            );

            $sortOrderInput = $newSortOrders[$index] ?? ($index + 1);

            if (
                filter_var($sortOrderInput, FILTER_VALIDATE_INT) === false ||
                (int)$sortOrderInput < 0
            ) {
                sendResponse(false, 'Image sort_order must be a non-negative integer.', null, 422);
            }

            $imageStatus = isset($newStatuses[$index])
                ? strtolower(trim((string)$newStatuses[$index]))
                : 'active';

            if (!in_array($imageStatus, ['active', 'inactive'], true)) {
                sendResponse(false, 'Invalid image status.', null, 422);
            }

            $preparedImages[] = [
                'tmp_name' => $tmpName,
                'extension' => $allowedTypes[$mimeType],
                'alt_text' => $altText === '' ? null : $altText,
                'is_primary' => $isPrimary,
                'sort_order' => (int)$sortOrderInput,
                'status' => $imageStatus
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
        sendResponse(
            false,
            'Only one primary image is allowed for a variant.',
            null,
            422
        );
    }

    $uploadDirectory = __DIR__ . '/../../uploads/products/';

    if (!empty($preparedImages)) {
        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                sendResponse(false, 'Unable to create product image directory.', null, 500);
            }
        }

        if (!is_writable($uploadDirectory)) {
            sendResponse(false, 'Product image directory is not writable.', null, 500);
        }
    }

    $newUploadedFiles = [];
    $oldFilesToDelete = [];

    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare(
        "UPDATE product_variants
         SET
            product_id = :product_id,
            size_id = :size_id,
            color_id = :color_id,
            sku = :sku,
            variant_name = :variant_name,
            original_price = :original_price,
            discount_type = :discount_type,
            discount_value = :discount_value,
            selling_price = :selling_price,
            stock_quantity = :stock_quantity,
            reserved_quantity = :reserved_quantity,
            low_stock_limit = :low_stock_limit,
            is_available = :is_available
         WHERE id = :id"
    );

    $updateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);

    if ($sizeId === null) {
        $updateStmt->bindValue(':size_id', null, PDO::PARAM_NULL);
    } else {
        $updateStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
    }

    if ($colorId === null) {
        $updateStmt->bindValue(':color_id', null, PDO::PARAM_NULL);
    } else {
        $updateStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
    }

    $updateStmt->bindValue(':sku', $sku, PDO::PARAM_STR);

    if ($variantName === null || $variantName === '') {
        $updateStmt->bindValue(':variant_name', null, PDO::PARAM_NULL);
    } else {
        $updateStmt->bindValue(':variant_name', $variantName, PDO::PARAM_STR);
    }

    $updateStmt->bindValue(
        ':original_price',
        number_format($originalPrice, 2, '.', ''),
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(':discount_type', $discountType, PDO::PARAM_STR);

    $updateStmt->bindValue(
        ':discount_value',
        number_format($discountValue, 2, '.', ''),
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(
        ':selling_price',
        number_format($sellingPrice, 2, '.', ''),
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(':stock_quantity', $stockQuantity, PDO::PARAM_INT);
    $updateStmt->bindValue(':reserved_quantity', $reservedQuantity, PDO::PARAM_INT);
    $updateStmt->bindValue(':low_stock_limit', $lowStockLimit, PDO::PARAM_INT);
    $updateStmt->bindValue(':is_available', $isAvailable, PDO::PARAM_INT);
    $updateStmt->bindValue(':id', $variantId, PDO::PARAM_INT);

    $updateStmt->execute();

    foreach ($imageMap as $image) {
        if ($image['remove']) {
            $deleteImageStmt = $pdo->prepare(
                "DELETE FROM product_variant_images
                 WHERE id = :id
                 AND variant_id = :variant_id"
            );

            $deleteImageStmt->bindValue(':id', $image['id'], PDO::PARAM_INT);
            $deleteImageStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
            $deleteImageStmt->execute();

            if (!empty($image['image'])) {
                $oldFilesToDelete[] =
                    __DIR__ . '/../../' . ltrim($image['image'], '/');
            }

            continue;
        }

        $imageUpdateStmt = $pdo->prepare(
            "UPDATE product_variant_images
             SET
                alt_text = :alt_text,
                is_primary = :is_primary,
                sort_order = :sort_order,
                status = :status
             WHERE id = :id
             AND variant_id = :variant_id"
        );

        if ($image['alt_text'] === null) {
            $imageUpdateStmt->bindValue(':alt_text', null, PDO::PARAM_NULL);
        } else {
            $imageUpdateStmt->bindValue(':alt_text', $image['alt_text'], PDO::PARAM_STR);
        }

        $imageUpdateStmt->bindValue(':is_primary', $image['is_primary'], PDO::PARAM_INT);
        $imageUpdateStmt->bindValue(':sort_order', $image['sort_order'], PDO::PARAM_INT);
        $imageUpdateStmt->bindValue(':status', $image['status'], PDO::PARAM_STR);
        $imageUpdateStmt->bindValue(':id', $image['id'], PDO::PARAM_INT);
        $imageUpdateStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);

        $imageUpdateStmt->execute();
    }

    foreach ($preparedImages as $image) {
        $fileName =
            'product_' .
            $productId .
            '_variant_' .
            $variantId .
            '_' .
            bin2hex(random_bytes(12)) .
            '.' .
            $image['extension'];

        $fullPath = $uploadDirectory . $fileName;

        if (!move_uploaded_file($image['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to save new variant image.');
        }

        $newUploadedFiles[] = $fullPath;

        $relativePath = 'uploads/products/' . $fileName;

        $newImageStmt = $pdo->prepare(
            "INSERT INTO product_variant_images (
                variant_id,
                image,
                alt_text,
                is_primary,
                sort_order,
                status
            ) VALUES (
                :variant_id,
                :image,
                :alt_text,
                :is_primary,
                :sort_order,
                :status
            )"
        );

        $newImageStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $newImageStmt->bindValue(':image', $relativePath, PDO::PARAM_STR);

        if ($image['alt_text'] === null) {
            $newImageStmt->bindValue(':alt_text', null, PDO::PARAM_NULL);
        } else {
            $newImageStmt->bindValue(':alt_text', $image['alt_text'], PDO::PARAM_STR);
        }

        $newImageStmt->bindValue(':is_primary', $image['is_primary'], PDO::PARAM_INT);
        $newImageStmt->bindValue(':sort_order', $image['sort_order'], PDO::PARAM_INT);
        $newImageStmt->bindValue(':status', $image['status'], PDO::PARAM_STR);

        $newImageStmt->execute();
    }

    $fetchVariantStmt = $pdo->prepare(
        "SELECT
            pv.id,
            pv.product_id,
            p.name AS product_name,
            pv.size_id,
            s.name AS size_name,
            pv.color_id,
            c.name AS color_name,
            c.hex_code,
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
            pv.updated_at
         FROM product_variants pv
         INNER JOIN products p
            ON p.id = pv.product_id
         LEFT JOIN sizes s
            ON s.id = pv.size_id
         LEFT JOIN colors c
            ON c.id = pv.color_id
         WHERE pv.id = :id
         LIMIT 1"
    );

    $fetchVariantStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
    $fetchVariantStmt->execute();

    $variant = $fetchVariantStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new RuntimeException('Unable to retrieve updated variant.');
    }

    $fetchImagesStmt = $pdo->prepare(
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
         ORDER BY is_primary DESC, sort_order ASC, id ASC"
    );

    $fetchImagesStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
    $fetchImagesStmt->execute();

    $images = $fetchImagesStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    deleteFiles($oldFilesToDelete);

    $formattedImages = [];

    foreach ($images as $image) {
        $formattedImages[] = [
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
    }

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
            'original_price' => $variant['original_price'],
            'discount_type' => $variant['discount_type'],
            'discount_value' => $variant['discount_value'],
            'selling_price' => $variant['selling_price'],
            'stock_quantity' => (int)$variant['stock_quantity'],
            'reserved_quantity' => (int)$variant['reserved_quantity'],
            'low_stock_limit' => (int)$variant['low_stock_limit'],
            'is_available' => (int)$variant['is_available'],
            'images' => $formattedImages,
            'created_at' => $variant['created_at'],
            'updated_at' => $variant['updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (isset($newUploadedFiles)) {
        deleteFiles($newUploadedFiles);
    }

    sendResponse(
        false,
        'Unable to update product variant.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (isset($newUploadedFiles)) {
        deleteFiles($newUploadedFiles);
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