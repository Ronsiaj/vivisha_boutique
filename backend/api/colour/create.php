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

$name = isset($data['name']) ? trim((string)$data['name']) : '';
$hexCode = isset($data['hex_code']) ? strtoupper(trim((string)$data['hex_code'])) : '';
$status = isset($data['status']) ? strtolower(trim((string)$data['status'])) : 'active';

if ($name === '') {
    sendResponse(false, 'Color name is required.', null, 422);
}

if (mb_strlen($name) < 2) {
    sendResponse(false, 'Color name must contain at least 2 characters.', null, 422);
}

if (mb_strlen($name) > 100) {
    sendResponse(false, 'Color name must not exceed 100 characters.', null, 422);
}

if (!preg_match('/^[\p{L}\p{N}\s\-\(\)\/&]+$/u', $name)) {
    sendResponse(false, 'Color name contains invalid characters.', null, 422);
}

if ($hexCode !== '') {
    if (!preg_match('/^#[0-9A-F]{6}$/', $hexCode)) {
        sendResponse(false, 'hex_code must be a valid 6-digit HEX color code.', [
            'example' => '#FF0000'
        ], 422);
    }
} else {
    $hexCode = null;
}

$allowedStatuses = ['active', 'inactive'];

if (!in_array($status, $allowedStatuses, true)) {
    sendResponse(false, 'Invalid status.', [
        'allowed_statuses' => $allowedStatuses
    ], 422);
}

try {
    $duplicateNameStmt = $pdo->prepare(
        "SELECT id
         FROM colors
         WHERE LOWER(name) = LOWER(:name)
         LIMIT 1"
    );

    $duplicateNameStmt->bindValue(':name', $name, PDO::PARAM_STR);
    $duplicateNameStmt->execute();

    if ($duplicateNameStmt->fetch()) {
        sendResponse(false, 'Color name already exists.', null, 409);
    }

    if ($hexCode !== null) {
        $duplicateHexStmt = $pdo->prepare(
            "SELECT id
             FROM colors
             WHERE UPPER(hex_code) = UPPER(:hex_code)
             LIMIT 1"
        );

        $duplicateHexStmt->bindValue(':hex_code', $hexCode, PDO::PARAM_STR);
        $duplicateHexStmt->execute();

        if ($duplicateHexStmt->fetch()) {
            sendResponse(false, 'Hex code already exists.', null, 409);
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO colors (
            name,
            hex_code,
            status
        ) VALUES (
            :name,
            :hex_code,
            :status
        )"
    );

    $stmt->bindValue(':name', $name, PDO::PARAM_STR);

    if ($hexCode === null) {
        $stmt->bindValue(':hex_code', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':hex_code', $hexCode, PDO::PARAM_STR);
    }

    $stmt->bindValue(':status', $status, PDO::PARAM_STR);

    $stmt->execute();

    $colorId = (int)$pdo->lastInsertId();

    $fetchStmt = $pdo->prepare(
        "SELECT
            id,
            name,
            hex_code,
            status,
            created_at,
            updated_at
         FROM colors
         WHERE id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $colorId, PDO::PARAM_INT);
    $fetchStmt->execute();

    $color = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$color) {
        sendResponse(false, 'Color created but unable to retrieve it.', null, 500);
    }

    sendResponse(true, 'Color created successfully.', [
        'color' => [
            'id' => (int)$color['id'],
            'name' => $color['name'],
            'hex_code' => $color['hex_code'],
            'status' => $color['status'],
            'created_at' => $color['created_at'],
            'updated_at' => $color['updated_at']
        ]
    ], 201);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to create color.',
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