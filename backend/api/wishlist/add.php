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

if ($accountType !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') === false) {
    sendResponse(false, 'Content-Type must be application/json.', null, 415);
}

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    sendResponse(false, 'Request body is required.', null, 400);
}

$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, 'Invalid JSON request body.', null, 400);
}

$allowedFields = ['variant_id'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$variantIdInput = $data['variant_id'] ?? null;

if ($variantIdInput === null || $variantIdInput === '') {
    sendResponse(false, 'variant_id is required.', null, 422);
}

if (
    filter_var($variantIdInput, FILTER_VALIDATE_INT) === false ||
    (int)$variantIdInput <= 0
) {
    sendResponse(
        false,
        'variant_id must be a valid positive integer.',
        null,
        422
    );
}

$variantId = (int)$variantIdInput;

try {
    $pdo->beginTransaction();

    $variantStmt = $pdo->prepare(
        "SELECT
            pv.id,
            pv.product_id,
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

            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.category_id,
            p.status AS product_status,

            c.name AS category_name,
            c.status AS category_status,

            s.name AS size_name,
            s.status AS size_status,

            co.name AS color_name,
            co.hex_code,
            co.status AS color_status

         FROM product_variants pv

         INNER JOIN products p
            ON p.id = pv.product_id

         INNER JOIN categories c
            ON c.id = p.category_id

         LEFT JOIN sizes s
            ON s.id = pv.size_id

         LEFT JOIN colors co
            ON co.id = pv.color_id

         WHERE pv.id = :variant_id

         LIMIT 1"
    );

    $variantStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $variantStmt->execute();

    $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        $pdo->rollBack();

        sendResponse(
            false,
            'Product variant not found.',
            null,
            404
        );
    }

    if ($variant['product_status'] !== 'active') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This product is currently unavailable.',
            null,
            422
        );
    }

    if ($variant['category_status'] !== 'active') {
        $pdo->rollBack();

        sendResponse(
            false,
            'This product category is currently unavailable.',
            null,
            422
        );
    }

    if ((int)$variant['is_available'] !== 1) {
        $pdo->rollBack();

        sendResponse(
            false,
            'This product variant is currently unavailable.',
            null,
            422
        );
    }

    if (
        $variant['size_id'] !== null &&
        $variant['size_status'] !== 'active'
    ) {
        $pdo->rollBack();

        sendResponse(
            false,
            'Selected product size is currently unavailable.',
            null,
            422
        );
    }

    if (
        $variant['color_id'] !== null &&
        $variant['color_status'] !== 'active'
    ) {
        $pdo->rollBack();

        sendResponse(
            false,
            'Selected product color is currently unavailable.',
            null,
            422
        );
    }

    $productId = (int)$variant['product_id'];

    $duplicateStmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            product_id,
            variant_id,
            created_at,
            updated_at
         FROM wishlists
         WHERE user_id = :user_id
         AND variant_id = :variant_id
         LIMIT 1
         FOR UPDATE"
    );

    $duplicateStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $duplicateStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $duplicateStmt->execute();

    $existingWishlist = $duplicateStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingWishlist) {
        $pdo->rollBack();

        sendResponse(
            false,
            'This product variant is already in your wishlist.',
            [
                'wishlist_id' => (int)$existingWishlist['id'],
                'product_id' => (int)$existingWishlist['product_id'],
                'variant_id' => (int)$existingWishlist['variant_id']
            ],
            409
        );
    }

    $insertStmt = $pdo->prepare(
        "INSERT INTO wishlists (
            user_id,
            product_id,
            variant_id
        ) VALUES (
            :user_id,
            :product_id,
            :variant_id
        )"
    );

    $insertStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':product_id',
        $productId,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $insertStmt->execute();

    $wishlistId = (int)$pdo->lastInsertId();

    $wishlistStmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            product_id,
            variant_id,
            created_at,
            updated_at
         FROM wishlists
         WHERE id = :wishlist_id
         AND user_id = :user_id
         LIMIT 1"
    );

    $wishlistStmt->bindValue(
        ':wishlist_id',
        $wishlistId,
        PDO::PARAM_INT
    );

    $wishlistStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $wishlistStmt->execute();

    $wishlist = $wishlistStmt->fetch(PDO::FETCH_ASSOC);

    if (!$wishlist) {
        throw new RuntimeException(
            'Unable to retrieve created wishlist item.'
        );
    }

    $imageStmt = $pdo->prepare(
        "SELECT
            id,
            variant_id,
            image,
            alt_text,
            is_primary,
            sort_order,
            status
         FROM product_variant_images
         WHERE variant_id = :variant_id
         AND status = 'active'
         ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC
         LIMIT 1"
    );

    $imageStmt->bindValue(
        ':variant_id',
        $variantId,
        PDO::PARAM_INT
    );

    $imageStmt->execute();

    $primaryImage = $imageStmt->fetch(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM wishlists
         WHERE user_id = :user_id"
    );

    $countStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $countStmt->execute();

    $wishlistCount = (int)$countStmt->fetchColumn();

    $pdo->commit();

    $stockQuantity = (int)$variant['stock_quantity'];
    $reservedQuantity = (int)$variant['reserved_quantity'];

    $availableQuantity =
        $stockQuantity - $reservedQuantity;

    if ($availableQuantity < 0) {
        $availableQuantity = 0;
    }

    if ($availableQuantity <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif (
        $availableQuantity <=
        (int)$variant['low_stock_limit']
    ) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    sendResponse(
        true,
        'Product added to wishlist successfully.',
        [
            'wishlist' => [
                'id' => (int)$wishlist['id'],
                'user_id' => (int)$wishlist['user_id'],
                'product_id' => (int)$wishlist['product_id'],
                'variant_id' => (int)$wishlist['variant_id'],

                'product' => [
                    'id' => $productId,
                    'category_id' => (int)$variant['category_id'],
                    'name' => $variant['product_name'],
                    'slug' => $variant['product_slug'],
                    'description' => $variant['product_description'],
                    'status' => $variant['product_status']
                ],

                'category' => [
                    'id' => (int)$variant['category_id'],
                    'name' => $variant['category_name'],
                    'status' => $variant['category_status']
                ],

                'variant' => [
                    'id' => $variantId,
                    'sku' => $variant['sku'],
                    'variant_name' => $variant['variant_name'],

                    'size' => $variant['size_id'] !== null
                        ? [
                            'id' => (int)$variant['size_id'],
                            'name' => $variant['size_name'],
                            'status' => $variant['size_status']
                        ]
                        : null,

                    'color' => $variant['color_id'] !== null
                        ? [
                            'id' => (int)$variant['color_id'],
                            'name' => $variant['color_name'],
                            'hex_code' => $variant['hex_code'],
                            'status' => $variant['color_status']
                        ]
                        : null,

                    'pricing' => [
                        'original_price' => $variant['original_price'],
                        'discount_type' => $variant['discount_type'],
                        'discount_value' => $variant['discount_value'],
                        'selling_price' => $variant['selling_price']
                    ],

                    'stock' => [
                        'stock_quantity' => $stockQuantity,
                        'reserved_quantity' => $reservedQuantity,
                        'available_quantity' => $availableQuantity,
                        'low_stock_limit' => (int)$variant['low_stock_limit'],
                        'stock_status' => $stockStatus
                    ],

                    'is_available' => (int)$variant['is_available']
                ],

                'primary_image' => $primaryImage
                    ? [
                        'id' => (int)$primaryImage['id'],
                        'variant_id' => (int)$primaryImage['variant_id'],
                        'image' => $primaryImage['image'],
                        'alt_text' => $primaryImage['alt_text'],
                        'is_primary' => (int)$primaryImage['is_primary'],
                        'sort_order' => (int)$primaryImage['sort_order'],
                        'status' => $primaryImage['status']
                    ]
                    : null,

                'created_at' => $wishlist['created_at'],
                'updated_at' => $wishlist['updated_at']
            ],

            'wishlist_count' => $wishlistCount
        ],
        201
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $e->getCode() === '23000' &&
        str_contains(
            strtolower($e->getMessage()),
            'uq_wishlist_user_variant'
        )
    ) {
        sendResponse(
            false,
            'This product variant is already in your wishlist.',
            null,
            409
        );
    }

    sendResponse(
        false,
        'Unable to add product to wishlist.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
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