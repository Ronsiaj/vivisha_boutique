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

$productIdInput = isset($_POST['product_id']) ? trim((string)$_POST['product_id']) : '';
$sizeIdInput = isset($_POST['size_id']) ? trim((string)$_POST['size_id']) : '';
$colorIdInput = isset($_POST['color_id']) ? trim((string)$_POST['color_id']) : '';
$sku = isset($_POST['sku']) ? strtoupper(trim((string)$_POST['sku'])) : '';
$variantName = isset($_POST['variant_name']) ? trim((string)$_POST['variant_name']) : '';
$originalPriceInput = isset($_POST['original_price']) ? trim((string)$_POST['original_price']) : '';
$discountType = isset($_POST['discount_type']) ? strtolower(trim((string)$_POST['discount_type'])) : 'none';
$discountValueInput = isset($_POST['discount_value']) ? trim((string)$_POST['discount_value']) : '0';
$stockQuantityInput = isset($_POST['stock_quantity']) ? trim((string)$_POST['stock_quantity']) : '0';
$reservedQuantityInput = isset($_POST['reserved_quantity']) ? trim((string)$_POST['reserved_quantity']) : '0';
$lowStockLimitInput = isset($_POST['low_stock_limit']) ? trim((string)$_POST['low_stock_limit']) : '5';
$isAvailableInput = isset($_POST['is_available']) ? strtolower(trim((string)$_POST['is_available'])) : '1';

if ($productIdInput === '') {
    sendResponse(false, 'product_id is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $productIdInput)) {
    sendResponse(false, 'product_id must be a valid positive integer.', null, 422);
}

$productId = (int)$productIdInput;

$sizeId = null;

if ($sizeIdInput !== '') {
    if (!preg_match('/^[1-9][0-9]*$/', $sizeIdInput)) {
        sendResponse(false, 'size_id must be a valid positive integer.', null, 422);
    }

    $sizeId = (int)$sizeIdInput;
}

$colorId = null;

if ($colorIdInput !== '') {
    if (!preg_match('/^[1-9][0-9]*$/', $colorIdInput)) {
        sendResponse(false, 'color_id must be a valid positive integer.', null, 422);
    }

    $colorId = (int)$colorIdInput;
}

if ($sizeId === null && $colorId === null) {
    sendResponse(false, 'At least size_id or color_id must be provided.', null, 422);
}

if ($sku === '') {
    sendResponse(false, 'SKU is required.', null, 422);
}

if (mb_strlen($sku) < 2 || mb_strlen($sku) > 100) {
    sendResponse(false, 'SKU must contain between 2 and 100 characters.', null, 422);
}

if (!preg_match('/^[A-Z0-9\-_]+$/', $sku)) {
    sendResponse(false, 'SKU may contain only letters, numbers, hyphen and underscore.', null, 422);
}

if ($variantName !== '' && mb_strlen($variantName) > 150) {
    sendResponse(false, 'variant_name must not exceed 150 characters.', null, 422);
}

if ($originalPriceInput === '') {
    sendResponse(false, 'original_price is required.', null, 422);
}

if (!is_numeric($originalPriceInput)) {
    sendResponse(false, 'original_price must be a valid number.', null, 422);
}

$originalPrice = round((float)$originalPriceInput, 2);

if ($originalPrice < 0 || $originalPrice > 99999999.99) {
    sendResponse(false, 'Invalid original_price.', null, 422);
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

if (
    filter_var($stockQuantityInput, FILTER_VALIDATE_INT) === false ||
    (int)$stockQuantityInput < 0
) {
    sendResponse(false, 'stock_quantity must be a non-negative integer.', null, 422);
}

if (
    filter_var($reservedQuantityInput, FILTER_VALIDATE_INT) === false ||
    (int)$reservedQuantityInput < 0
) {
    sendResponse(false, 'reserved_quantity must be a non-negative integer.', null, 422);
}

if (
    filter_var($lowStockLimitInput, FILTER_VALIDATE_INT) === false ||
    (int)$lowStockLimitInput < 0
) {
    sendResponse(false, 'low_stock_limit must be a non-negative integer.', null, 422);
}

$stockQuantity = (int)$stockQuantityInput;
$reservedQuantity = (int)$reservedQuantityInput;
$lowStockLimit = (int)$lowStockLimitInput;

if ($reservedQuantity > $stockQuantity) {
    sendResponse(false, 'reserved_quantity cannot exceed stock_quantity.', null, 422);
}

if (!in_array($isAvailableInput, ['0', '1', 'true', 'false'], true)) {
    sendResponse(false, 'is_available must be 0, 1, true or false.', null, 422);
}

$isAvailable = in_array($isAvailableInput, ['1', 'true'], true) ? 1 : 0;

$altTexts = $_POST['alt_text'] ?? [];
$isPrimaryValues = $_POST['is_primary'] ?? [];
$sortOrders = $_POST['sort_order'] ?? [];
$imageStatuses = $_POST['image_status'] ?? [];

if (!is_array($altTexts)) {
    $altTexts = [$altTexts];
}

if (!is_array($isPrimaryValues)) {
    $isPrimaryValues = [$isPrimaryValues];
}

if (!is_array($sortOrders)) {
    $sortOrders = [$sortOrders];
}

if (!is_array($imageStatuses)) {
    $imageStatuses = [$imageStatuses];
}

$preparedImages = [];
$uploadedFiles = [];

if (isset($_FILES['images'])) {
    $files = $_FILES['images'];

    $names = is_array($files['name'])
        ? $files['name']
        : [$files['name']];

    $tmpNames = is_array($files['tmp_name'])
        ? $files['tmp_name']
        : [$files['tmp_name']];

    $errors = is_array($files['error'])
        ? $files['error']
        : [$files['error']];

    $fileSizes = is_array($files['size'])
        ? $files['size']
        : [$files['size']];

    $imageCount = count($names);

    if ($imageCount > 10) {
        sendResponse(false, 'Maximum 10 images are allowed per variant.', null, 422);
    }

    if (
        !empty($altTexts) &&
        count($altTexts) > $imageCount
    ) {
        sendResponse(false, 'alt_text count cannot exceed image count.', null, 422);
    }

    if (
        !empty($isPrimaryValues) &&
        count($isPrimaryValues) > $imageCount
    ) {
        sendResponse(false, 'is_primary count cannot exceed image count.', null, 422);
    }

    if (
        !empty($sortOrders) &&
        count($sortOrders) > $imageCount
    ) {
        sendResponse(false, 'sort_order count cannot exceed image count.', null, 422);
    }

    if (
        !empty($imageStatuses) &&
        count($imageStatuses) > $imageCount
    ) {
        sendResponse(false, 'image_status count cannot exceed image count.', null, 422);
    }

    $primaryCount = 0;

    foreach ($names as $index => $originalName) {
        $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            sendResponse(
                false,
                'Image ' . ($index + 1) . ' upload failed.',
                null,
                422
            );
        }

        $tmpName = $tmpNames[$index] ?? '';
        $fileSize = (int)($fileSizes[$index] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            sendResponse(
                false,
                'Image ' . ($index + 1) . ' is invalid.',
                null,
                422
            );
        }

        if ($fileSize <= 0) {
            sendResponse(
                false,
                'Image ' . ($index + 1) . ' is empty.',
                null,
                422
            );
        }

        if ($fileSize > 5 * 1024 * 1024) {
            sendResponse(
                false,
                'Each image must not exceed 5 MB.',
                null,
                422
            );
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
            sendResponse(
                false,
                'Image ' . ($index + 1) . ' is not a valid image.',
                null,
                422
            );
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];

        if (
            $width <= 0 ||
            $height <= 0 ||
            $width > 8000 ||
            $height > 8000
        ) {
            sendResponse(
                false,
                'Invalid image dimensions.',
                null,
                422
            );
        }

        $altText = isset($altTexts[$index])
            ? trim((string)$altTexts[$index])
            : '';

        if ($altText !== '' && mb_strlen($altText) > 255) {
            sendResponse(
                false,
                'Image alt_text must not exceed 255 characters.',
                null,
                422
            );
        }

        $primaryInput = isset($isPrimaryValues[$index])
            ? strtolower(trim((string)$isPrimaryValues[$index]))
            : '0';

        if (!in_array($primaryInput, ['0', '1', 'true', 'false'], true)) {
            sendResponse(
                false,
                'Image is_primary must be 0, 1, true or false.',
                null,
                422
            );
        }

        $isPrimary = in_array($primaryInput, ['1', 'true'], true)
            ? 1
            : 0;

        if ($isPrimary === 1) {
            $primaryCount++;
        }

        $sortOrderInput = isset($sortOrders[$index])
            ? trim((string)$sortOrders[$index])
            : (string)($index + 1);

        if (
            filter_var($sortOrderInput, FILTER_VALIDATE_INT) === false ||
            (int)$sortOrderInput < 0
        ) {
            sendResponse(
                false,
                'Image sort_order must be a non-negative integer.',
                null,
                422
            );
        }

        $imageStatus = isset($imageStatuses[$index])
            ? strtolower(trim((string)$imageStatuses[$index]))
            : 'active';

        if (!in_array($imageStatus, ['active', 'inactive'], true)) {
            sendResponse(
                false,
                'Invalid image status.',
                ['allowed_statuses' => ['active', 'inactive']],
                422
            );
        }

        $preparedImages[] = [
            'tmp_name' => $tmpName,
            'extension' => $allowedTypes[$mimeType],
            'alt_text' => $altText,
            'is_primary' => $isPrimary,
            'sort_order' => (int)$sortOrderInput,
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
        "SELECT id, name, status
         FROM products
         WHERE id = :id
         LIMIT 1"
    );

    $productStmt->bindValue(':id', $productId, PDO::PARAM_INT);
    $productStmt->execute();

    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        sendResponse(false, 'Product not found.', null, 404);
    }

    if ($sizeId !== null) {
        $sizeStmt = $pdo->prepare(
            "SELECT id, name, status
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

    if ($colorId !== null) {
        $colorStmt = $pdo->prepare(
            "SELECT id, name, hex_code, status
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

    $skuStmt = $pdo->prepare(
        "SELECT id
         FROM product_variants
         WHERE LOWER(sku) = LOWER(:sku)
         LIMIT 1"
    );

    $skuStmt->bindValue(':sku', $sku, PDO::PARAM_STR);
    $skuStmt->execute();

    if ($skuStmt->fetch()) {
        sendResponse(false, 'SKU already exists.', null, 409);
    }

    if ($sizeId !== null && $colorId !== null) {
        $combinationStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             AND size_id = :size_id
             AND color_id = :color_id
             LIMIT 1"
        );

        $combinationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
    } elseif ($sizeId !== null) {
        $combinationStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             AND size_id = :size_id
             AND color_id IS NULL
             LIMIT 1"
        );

        $combinationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
    } else {
        $combinationStmt = $pdo->prepare(
            "SELECT id
             FROM product_variants
             WHERE product_id = :product_id
             AND size_id IS NULL
             AND color_id = :color_id
             LIMIT 1"
        );

        $combinationStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $combinationStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
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

    $pdo->beginTransaction();

    $variantStmt = $pdo->prepare(
        "INSERT INTO product_variants (
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
        ) VALUES (
            :product_id,
            :size_id,
            :color_id,
            :sku,
            :variant_name,
            :original_price,
            :discount_type,
            :discount_value,
            :selling_price,
            :stock_quantity,
            :reserved_quantity,
            :low_stock_limit,
            :is_available
        )"
    );

    $variantStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);

    if ($sizeId === null) {
        $variantStmt->bindValue(':size_id', null, PDO::PARAM_NULL);
    } else {
        $variantStmt->bindValue(':size_id', $sizeId, PDO::PARAM_INT);
    }

    if ($colorId === null) {
        $variantStmt->bindValue(':color_id', null, PDO::PARAM_NULL);
    } else {
        $variantStmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
    }

    $variantStmt->bindValue(':sku', $sku, PDO::PARAM_STR);

    if ($variantName === '') {
        $variantStmt->bindValue(':variant_name', null, PDO::PARAM_NULL);
    } else {
        $variantStmt->bindValue(':variant_name', $variantName, PDO::PARAM_STR);
    }

    $variantStmt->bindValue(
        ':original_price',
        number_format($originalPrice, 2, '.', ''),
        PDO::PARAM_STR
    );

    $variantStmt->bindValue(':discount_type', $discountType, PDO::PARAM_STR);

    $variantStmt->bindValue(
        ':discount_value',
        number_format($discountValue, 2, '.', ''),
        PDO::PARAM_STR
    );

    $variantStmt->bindValue(
        ':selling_price',
        number_format($sellingPrice, 2, '.', ''),
        PDO::PARAM_STR
    );

    $variantStmt->bindValue(':stock_quantity', $stockQuantity, PDO::PARAM_INT);
    $variantStmt->bindValue(':reserved_quantity', $reservedQuantity, PDO::PARAM_INT);
    $variantStmt->bindValue(':low_stock_limit', $lowStockLimit, PDO::PARAM_INT);
    $variantStmt->bindValue(':is_available', $isAvailable, PDO::PARAM_INT);

    $variantStmt->execute();

    $variantId = (int)$pdo->lastInsertId();

    $createdImages = [];

    foreach ($preparedImages as $image) {
        $generatedName =
            'product_' .
            $productId .
            '_variant_' .
            $variantId .
            '_' .
            bin2hex(random_bytes(12)) .
            '.' .
            $image['extension'];

        $fullPath = $uploadDirectory . $generatedName;

        if (!move_uploaded_file($image['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to save variant image.');
        }

        $uploadedFiles[] = $fullPath;

        $relativePath = 'uploads/products/' . $generatedName;

        $imageStmt = $pdo->prepare(
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

        $imageStmt->bindValue(':variant_id', $variantId, PDO::PARAM_INT);
        $imageStmt->bindValue(':image', $relativePath, PDO::PARAM_STR);

        if ($image['alt_text'] === '') {
            $imageStmt->bindValue(':alt_text', null, PDO::PARAM_NULL);
        } else {
            $imageStmt->bindValue(':alt_text', $image['alt_text'], PDO::PARAM_STR);
        }

        $imageStmt->bindValue(':is_primary', $image['is_primary'], PDO::PARAM_INT);
        $imageStmt->bindValue(':sort_order', $image['sort_order'], PDO::PARAM_INT);
        $imageStmt->bindValue(':status', $image['status'], PDO::PARAM_STR);

        $imageStmt->execute();

        $createdImages[] = [
            'id' => (int)$pdo->lastInsertId(),
            'image' => $relativePath,
            'alt_text' => $image['alt_text'] !== '' ? $image['alt_text'] : null,
            'is_primary' => $image['is_primary'],
            'sort_order' => $image['sort_order'],
            'status' => $image['status']
        ];
    }

    $fetchStmt = $pdo->prepare(
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
        if (file_exists($file) && is_file($file)) {
            @unlink($file);
        }
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
        if (file_exists($file) && is_file($file)) {
            @unlink($file);
        }
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