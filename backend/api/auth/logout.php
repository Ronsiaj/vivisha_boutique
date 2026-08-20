<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method is allowed.', null, 405);
}

$token = getBearerToken();

if (!$token) {
    sendResponse(false, 'Authorization token is required.', null, 401);
}

try {
    $decoded = decodeJWT($token);
    validateJWTData($decoded);

    $userId = (int)$decoded->data->id;
    $userType = (string)$decoded->data->type;

    if ($userId <= 0) {
        sendResponse(false, 'Invalid account ID.', null, 401);
    }

    if (!in_array($userType, ['admin', 'user'], true)) {
        sendResponse(false, 'Invalid account type.', null, 401);
    }

    sendResponse(
        true,
        ucfirst($userType) . ' logged out successfully.',
        [
            'account_type' => $userType,
            'account_id' => $userId
        ],
        200
    );

} catch (Exception $e) {
    sendResponse(false, 'Invalid or expired token.', null, 401);
}