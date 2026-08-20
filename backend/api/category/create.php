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

$name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$slugInput = isset($_POST['slug']) ? trim((string)$_POST['slug']) : '';
$description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
$sortOrderInput = isset($_POST['sort_order']) ? trim((string)$_POST['sort_order']) : '0';
$status = isset($_POST['status']) ? strtolower(trim((string)$_POST['status'])) : 'active';

if ($name === '') {
    sendResponse(false, 'Category name is required.', null, 422);
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

if ($description !== '' && mb_strlen($description) > 5000) {
    sendResponse(false, 'Description must not exceed 5000 characters.', null, 422);
}

if (
    filter_var($sortOrderInput, FILTER_VALIDATE_INT) === false ||
    (int)$sortOrderInput < 0
) {
    sendResponse(false, 'sort_order must be a valid non-negative integer.', null, 422);
}

$sortOrder = (int)$sortOrderInput;

$allowedStatuses = ['active', 'inactive'];

if (!in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_statuses' => $allowedStatuses
    ], 422);
}

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

$slug = $slugInput !== ''
    ? createCategorySlug($slugInput)
    : createCategorySlug($name);

if ($slug === '') {
    sendResponse(false, 'Unable to generate a valid category slug.', null, 422);
}

if (mb_strlen($slug) > 180) {
    sendResponse(false, 'Slug must not exceed 180 characters.', null, 422);
}

$imagePath = null;
$uploadedFullPath = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $image = $_FILES['image'];

    if ($image['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'Image upload failed.', null, 422);
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

    $uploadedFullPath = $uploadDirectory . $fileName;

    if (!move_uploaded_file($image['tmp_name'], $uploadedFullPath)) {
        sendResponse(false, 'Unable to save category image.', null, 500);
    }

    $imagePath = 'uploads/categories/' . $fileName;
}

try {
    $duplicateNameStmt = $pdo->prepare(
        "SELECT id
         FROM categories
         WHERE LOWER(name) = LOWER(:name)
         LIMIT 1"
    );

    $duplicateNameStmt->bindValue(':name', $name, PDO::PARAM_STR);
    $duplicateNameStmt->execute();

    if ($duplicateNameStmt->fetch()) {
        if ($uploadedFullPath !== null && file_exists($uploadedFullPath)) {
            @unlink($uploadedFullPath);
        }

        sendResponse(false, 'Category name already exists.', null, 409);
    }

    $duplicateSlugStmt = $pdo->prepare(
        "SELECT id
         FROM categories
         WHERE slug = :slug
         LIMIT 1"
    );

    $duplicateSlugStmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    $duplicateSlugStmt->execute();

    if ($duplicateSlugStmt->fetch()) {
        if ($uploadedFullPath !== null && file_exists($uploadedFullPath)) {
            @unlink($uploadedFullPath);
        }

        sendResponse(false, 'Category slug already exists.', null, 409);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO categories (
            name,
            slug,
            description,
            image,
            sort_order,
            status
        ) VALUES (
            :name,
            :slug,
            :description,
            :image,
            :sort_order,
            :status
        )"
    );

    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);

    if ($description === '') {
        $stmt->bindValue(':description', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
    }

    if ($imagePath === null) {
        $stmt->bindValue(':image', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':image', $imagePath, PDO::PARAM_STR);
    }

    $stmt->bindValue(':sort_order', $sortOrder, PDO::PARAM_INT);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->execute();

    $categoryId = (int)$pdo->lastInsertId();

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

    sendResponse(true, 'Category created successfully.', [
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
    ], 201);

} catch (PDOException $e) {
    if ($uploadedFullPath !== null && file_exists($uploadedFullPath)) {
        @unlink($uploadedFullPath);
    }

    sendResponse(
        false,
        'Unable to create category.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($uploadedFullPath !== null && file_exists($uploadedFullPath)) {
        @unlink($uploadedFullPath);
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