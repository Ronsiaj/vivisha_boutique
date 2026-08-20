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

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST'], true)) {
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

$idInput = isset($_POST['id']) ? trim((string)$_POST['id']) : '';

if ($idInput === '') {
    sendResponse(false, 'Category ID is required.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $idInput)) {
    sendResponse(false, 'Category ID must be a valid positive integer.', null, 422);
}

$categoryId = (int)$idInput;

$hasName = array_key_exists('name', $_POST);
$hasSlug = array_key_exists('slug', $_POST);
$hasDescription = array_key_exists('description', $_POST);
$hasSortOrder = array_key_exists('sort_order', $_POST);
$hasStatus = array_key_exists('status', $_POST);
$hasImage = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
$hasRemoveImage = array_key_exists('remove_image', $_POST);

if (
    !$hasName &&
    !$hasSlug &&
    !$hasDescription &&
    !$hasSortOrder &&
    !$hasStatus &&
    !$hasImage &&
    !$hasRemoveImage
) {
    sendResponse(false, 'At least one field must be provided for update.', [
        'updatable_fields' => [
            'name',
            'slug',
            'description',
            'image',
            'remove_image',
            'sort_order',
            'status'
        ]
    ], 422);
}

$name = null;
$slug = null;
$description = null;
$sortOrder = null;
$status = null;
$removeImage = false;

function createCategorySlug(string $value): string
{
    $value = trim($value);

    if (function_exists('transliterator_transliterate')) {
        $converted = transliterator_transliterate(
            'Any-Latin; Latin-ASCII;',
            $value
        );

        if ($converted !== false) {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return $value;
}

if ($hasName) {
    $name = trim((string)$_POST['name']);

    if ($name === '') {
        sendResponse(false, 'Category name cannot be empty.', null, 422);
    }

    if (mb_strlen($name) < 2) {
        sendResponse(false, 'Category name must contain at least 2 characters.', null, 422);
    }

    if (mb_strlen($name) > 150) {
        sendResponse(false, 'Category name must not exceed 150 characters.', null, 422);
    }

    if (!preg_match('/^[\p{L}\p{N}\s\-\&\'\/().]+$/u', $name)) {
        sendResponse(false, 'Category name contains invalid characters.', null, 422);
    }
}

if ($hasSlug) {
    $slugInput = trim((string)$_POST['slug']);

    if ($slugInput === '') {
        sendResponse(false, 'Slug cannot be empty.', null, 422);
    }

    $slug = createCategorySlug($slugInput);

    if ($slug === '') {
        sendResponse(false, 'Invalid category slug.', null, 422);
    }

    if (mb_strlen($slug) > 180) {
        sendResponse(false, 'Slug must not exceed 180 characters.', null, 422);
    }
}

if ($hasDescription) {
    $description = trim((string)$_POST['description']);

    if ($description !== '' && mb_strlen($description) > 5000) {
        sendResponse(false, 'Description must not exceed 5000 characters.', null, 422);
    }
}

if ($hasSortOrder) {
    $sortOrderInput = trim((string)$_POST['sort_order']);

    if (
        filter_var($sortOrderInput, FILTER_VALIDATE_INT) === false ||
        (int)$sortOrderInput < 0
    ) {
        sendResponse(false, 'sort_order must be a valid non-negative integer.', null, 422);
    }

    $sortOrder = (int)$sortOrderInput;

    if ($sortOrder > 999999) {
        sendResponse(false, 'sort_order value is too large.', null, 422);
    }
}

if ($hasStatus) {
    $status = strtolower(trim((string)$_POST['status']));

    $allowedStatuses = ['active', 'inactive'];

    if (!in_array($status, $allowedStatuses, true)) {
        sendResponse(false, 'Invalid status.', [
            'allowed_statuses' => $allowedStatuses
        ], 422);
    }
}

if ($hasRemoveImage) {
    $removeImageInput = strtolower(trim((string)$_POST['remove_image']));

    if (!in_array($removeImageInput, ['0', '1', 'true', 'false'], true)) {
        sendResponse(false, 'remove_image must be 0, 1, true or false.', null, 422);
    }

    $removeImage = in_array($removeImageInput, ['1', 'true'], true);
}

if ($hasImage && $removeImage) {
    sendResponse(false, 'Cannot upload image and remove image at the same time.', null, 422);
}

$newImagePath = null;
$newImageFullPath = null;

if ($hasImage) {
    $image = $_FILES['image'];

    if ($image['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Uploaded image exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'Uploaded image exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'Image was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Unable to write uploaded image.',
            UPLOAD_ERR_EXTENSION => 'Image upload was stopped by a server extension.'
        ];

        sendResponse(
            false,
            $uploadErrors[$image['error']] ?? 'Image upload failed.',
            null,
            422
        );
    }

    if (!isset($image['tmp_name']) || !is_uploaded_file($image['tmp_name'])) {
        sendResponse(false, 'Invalid uploaded image.', null, 422);
    }

    if ((int)$image['size'] <= 0) {
        sendResponse(false, 'Uploaded image is empty.', null, 422);
    }

    if ((int)$image['size'] > 5 * 1024 * 1024) {
        sendResponse(false, 'Image size must not exceed 5 MB.', null, 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($image['tmp_name']);

    $allowedImageTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedImageTypes[$mimeType])) {
        sendResponse(false, 'Only JPG, JPEG, PNG and WEBP images are allowed.', null, 422);
    }

    $imageInfo = @getimagesize($image['tmp_name']);

    if ($imageInfo === false) {
        sendResponse(false, 'Uploaded file is not a valid image.', null, 422);
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];

    if ($width <= 0 || $height <= 0) {
        sendResponse(false, 'Invalid image dimensions.', null, 422);
    }

    if ($width > 8000 || $height > 8000) {
        sendResponse(false, 'Image dimensions must not exceed 8000x8000 pixels.', null, 422);
    }

    $extension = $allowedImageTypes[$mimeType];
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    $uploadDirectory = __DIR__ . '/../../uploads/categories/';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            sendResponse(false, 'Unable to create category image directory.', null, 500);
        }
    }

    if (!is_writable($uploadDirectory)) {
        sendResponse(false, 'Category image directory is not writable.', null, 500);
    }

    $newImageFullPath = $uploadDirectory . $fileName;

    if (!move_uploaded_file($image['tmp_name'], $newImageFullPath)) {
        sendResponse(false, 'Unable to save category image.', null, 500);
    }

    $newImagePath = 'uploads/categories/' . $fileName;
}

try {
    $checkStmt = $pdo->prepare(
        "SELECT
            id,
            name,
            slug,
            description,
            image,
            sort_order,
            status,
            created_at,
            updated_at
         FROM categories
         WHERE id = :id
         LIMIT 1"
    );

    $checkStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
    $checkStmt->execute();

    $existingCategory = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingCategory) {
        if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
            @unlink($newImageFullPath);
        }

        sendResponse(false, 'Category not found.', null, 404);
    }

    if ($hasName) {
        $duplicateNameStmt = $pdo->prepare(
            "SELECT id
             FROM categories
             WHERE LOWER(name) = LOWER(:name)
             AND id != :id
             LIMIT 1"
        );

        $duplicateNameStmt->bindValue(':name', $name, PDO::PARAM_STR);
        $duplicateNameStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
        $duplicateNameStmt->execute();

        if ($duplicateNameStmt->fetch()) {
            if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
                @unlink($newImageFullPath);
            }

            sendResponse(false, 'Category name already exists.', null, 409);
        }
    }

    if ($hasSlug) {
        $duplicateSlugStmt = $pdo->prepare(
            "SELECT id
             FROM categories
             WHERE slug = :slug
             AND id != :id
             LIMIT 1"
        );

        $duplicateSlugStmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $duplicateSlugStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
        $duplicateSlugStmt->execute();

        if ($duplicateSlugStmt->fetch()) {
            if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
                @unlink($newImageFullPath);
            }

            sendResponse(false, 'Category slug already exists.', null, 409);
        }
    }

    $updateFields = [];
    $params = [
        ':id' => $categoryId
    ];

    if ($hasName) {
        $updateFields[] = 'name = :name';
        $params[':name'] = $name;

        if (!$hasSlug) {
            $generatedSlug = createCategorySlug($name);

            if ($generatedSlug === '') {
                if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
                    @unlink($newImageFullPath);
                }

                sendResponse(false, 'Unable to generate slug from category name.', null, 422);
            }

            $duplicateAutoSlugStmt = $pdo->prepare(
                "SELECT id
                 FROM categories
                 WHERE slug = :slug
                 AND id != :id
                 LIMIT 1"
            );

            $duplicateAutoSlugStmt->bindValue(':slug', $generatedSlug, PDO::PARAM_STR);
            $duplicateAutoSlugStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
            $duplicateAutoSlugStmt->execute();

            if ($duplicateAutoSlugStmt->fetch()) {
                if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
                    @unlink($newImageFullPath);
                }

                sendResponse(false, 'Generated category slug already exists.', null, 409);
            }

            $updateFields[] = 'slug = :auto_slug';
            $params[':auto_slug'] = $generatedSlug;
        }
    }

    if ($hasSlug) {
        $updateFields[] = 'slug = :slug';
        $params[':slug'] = $slug;
    }

    if ($hasDescription) {
        $updateFields[] = 'description = :description';
        $params[':description'] = $description === '' ? null : $description;
    }

    if ($hasSortOrder) {
        $updateFields[] = 'sort_order = :sort_order';
        $params[':sort_order'] = $sortOrder;
    }

    if ($hasStatus) {
        $updateFields[] = 'status = :status';
        $params[':status'] = $status;
    }

    if ($hasImage) {
        $updateFields[] = 'image = :image';
        $params[':image'] = $newImagePath;
    } elseif ($removeImage) {
        $updateFields[] = 'image = NULL';
    }

    if (empty($updateFields)) {
        if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
            @unlink($newImageFullPath);
        }

        sendResponse(false, 'No valid fields provided for update.', null, 422);
    }

    $pdo->beginTransaction();

    $sql = "UPDATE categories
            SET " . implode(', ', $updateFields) . "
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if ($key === ':id' || $key === ':sort_order') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } elseif ($value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();

    $fetchStmt = $pdo->prepare(
        "SELECT
            id,
            name,
            slug,
            description,
            image,
            sort_order,
            status,
            created_at,
            updated_at
         FROM categories
         WHERE id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
    $fetchStmt->execute();

    $category = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new RuntimeException('Unable to retrieve updated category.');
    }

    $pdo->commit();

    if (
        ($hasImage || $removeImage) &&
        !empty($existingCategory['image'])
    ) {
        $oldImageFullPath = __DIR__ . '/../../' . ltrim($existingCategory['image'], '/');

        if (
            file_exists($oldImageFullPath) &&
            is_file($oldImageFullPath)
        ) {
            @unlink($oldImageFullPath);
        }
    }

    sendResponse(true, 'Category updated successfully.', [
        'category' => [
            'id' => (int)$category['id'],
            'name' => $category['name'],
            'slug' => $category['slug'],
            'description' => $category['description'],
            'image' => $category['image'],
            'sort_order' => (int)$category['sort_order'],
            'status' => $category['status'],
            'created_at' => $category['created_at'],
            'updated_at' => $category['updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
        @unlink($newImageFullPath);
    }

    sendResponse(
        false,
        'Unable to update category.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($newImageFullPath !== null && file_exists($newImageFullPath)) {
        @unlink($newImageFullPath);
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