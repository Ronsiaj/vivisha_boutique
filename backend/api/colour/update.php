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
header('Access-Control-Allow-Methods: PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
    sendResponse(false, 'Only PUT or PATCH method is allowed.', null, 405);
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

$idInput = $data['id'] ?? null;

if ($idInput === null || $idInput === '') {
    sendResponse(false, 'Color ID is required.', null, 422);
}

if (filter_var($idInput, FILTER_VALIDATE_INT) === false) {
    sendResponse(false, 'Color ID must be a valid integer.', null, 422);
}

$colorId = (int)$idInput;

if ($colorId <= 0) {
    sendResponse(false, 'Color ID must be greater than 0.', null, 422);
}

$allowedFields = ['id', 'name', 'hex_code', 'status'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$hasName = array_key_exists('name', $data);
$hasHexCode = array_key_exists('hex_code', $data);
$hasStatus = array_key_exists('status', $data);

if (!$hasName && !$hasHexCode && !$hasStatus) {
    sendResponse(false, 'At least one field must be provided for update.', [
        'updatable_fields' => ['name', 'hex_code', 'status']
    ], 422);
}

$name = null;
$hexCode = null;
$status = null;

if ($hasName) {
    if (!is_string($data['name'])) {
        sendResponse(false, 'Color name must be a string.', null, 422);
    }

    $name = trim($data['name']);

    if ($name === '') {
        sendResponse(false, 'Color name cannot be empty.', null, 422);
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
}

if ($hasHexCode) {
    if ($data['hex_code'] === null || trim((string)$data['hex_code']) === '') {
        $hexCode = null;
    } else {
        if (!is_string($data['hex_code'])) {
            sendResponse(false, 'hex_code must be a string or null.', null, 422);
        }

        $hexCode = strtoupper(trim($data['hex_code']));

        if (!preg_match('/^#[0-9A-F]{6}$/', $hexCode)) {
            sendResponse(false, 'hex_code must be a valid 6-digit HEX color code.', [
                'example' => '#FF0000'
            ], 422);
        }
    }
}

if ($hasStatus) {
    if (!is_string($data['status'])) {
        sendResponse(false, 'Status must be a string.', null, 422);
    }

    $status = strtolower(trim($data['status']));

    $allowedStatuses = ['active', 'inactive'];

    if (!in_array($status, $allowedStatuses, true)) {
        sendResponse(false, 'Invalid status.', [
            'allowed_statuses' => $allowedStatuses
        ], 422);
    }
}

try {
    $checkStmt = $pdo->prepare(
        "SELECT id, name, hex_code, status, created_at, updated_at
         FROM colors
         WHERE id = :id
         LIMIT 1"
    );

    $checkStmt->bindValue(':id', $colorId, PDO::PARAM_INT);
    $checkStmt->execute();

    $existingColor = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingColor) {
        sendResponse(false, 'Color not found.', null, 404);
    }

    if ($hasName) {
        $duplicateNameStmt = $pdo->prepare(
            "SELECT id
             FROM colors
             WHERE LOWER(name) = LOWER(:name)
             AND id != :id
             LIMIT 1"
        );

        $duplicateNameStmt->bindValue(':name', $name, PDO::PARAM_STR);
        $duplicateNameStmt->bindValue(':id', $colorId, PDO::PARAM_INT);
        $duplicateNameStmt->execute();

        if ($duplicateNameStmt->fetch()) {
            sendResponse(false, 'Color name already exists.', null, 409);
        }
    }

    if ($hasHexCode && $hexCode !== null) {
        $duplicateHexStmt = $pdo->prepare(
            "SELECT id
             FROM colors
             WHERE UPPER(hex_code) = UPPER(:hex_code)
             AND id != :id
             LIMIT 1"
        );

        $duplicateHexStmt->bindValue(':hex_code', $hexCode, PDO::PARAM_STR);
        $duplicateHexStmt->bindValue(':id', $colorId, PDO::PARAM_INT);
        $duplicateHexStmt->execute();

        if ($duplicateHexStmt->fetch()) {
            sendResponse(false, 'Hex code already exists.', null, 409);
        }
    }

    $updateFields = [];
    $params = [':id' => $colorId];

    if ($hasName) {
        $updateFields[] = 'name = :name';
        $params[':name'] = $name;
    }

    if ($hasHexCode) {
        $updateFields[] = 'hex_code = :hex_code';
        $params[':hex_code'] = $hexCode;
    }

    if ($hasStatus) {
        $updateFields[] = 'status = :status';
        $params[':status'] = $status;
    }

    $sql = "UPDATE colors
            SET " . implode(', ', $updateFields) . "
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        if ($key === ':id') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } elseif ($value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();

    $fetchStmt = $pdo->prepare(
        "SELECT id, name, hex_code, status, created_at, updated_at
         FROM colors
         WHERE id = :id
         LIMIT 1"
    );

    $fetchStmt->bindValue(':id', $colorId, PDO::PARAM_INT);
    $fetchStmt->execute();

    $color = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    sendResponse(true, 'Color updated successfully.', [
        'color' => [
            'id' => (int)$color['id'],
            'name' => $color['name'],
            'hex_code' => $color['hex_code'],
            'status' => $color['status'],
            'created_at' => $color['created_at'],
            'updated_at' => $color['updated_at']
        ]
    ], 200);

} catch (PDOException $e) {
    sendResponse(
        false,
        'Unable to update color.',
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