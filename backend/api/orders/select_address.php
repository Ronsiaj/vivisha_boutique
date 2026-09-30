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

if (getAuthenticatedType($decoded) !== 'user') {
    sendResponse(false, 'User access only.', null, 403);
}

$userAuth = authenticateUser();
$userId = getAuthenticatedId($userAuth);

if ($userId <= 0) {
    sendResponse(false, 'Invalid authenticated user.', null, 401);
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    sendResponse(false, 'Content-Type must be application/json.', null, 415);
}

$data = json_decode(file_get_contents('php://input') ?: '', true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    sendResponse(false, 'Invalid JSON body.', null, 400);
}

$allowedFields = ['order_id','address_id'];

foreach (array_keys($data) as $field) {
    if (!in_array($field, $allowedFields, true)) {
        sendResponse(false, "Invalid field: {$field}.", [
            'allowed_fields' => $allowedFields
        ], 422);
    }
}

$orderIdInput = trim((string)($data['order_id'] ?? ''));
$addressIdInput = trim((string)($data['address_id'] ?? ''));

if (!preg_match('/^[1-9][0-9]*$/', $orderIdInput)) {
    sendResponse(false, 'order_id must be a valid positive integer.', null, 422);
}

if (!preg_match('/^[1-9][0-9]*$/', $addressIdInput)) {
    sendResponse(false, 'address_id must be a valid positive integer.', null, 422);
}

$orderId = (int)$orderIdInput;
$addressId = (int)$addressIdInput;
$sellerState = 'Tamil Nadu';

function addressFail(PDO $pdo, string $message, mixed $data = null, int $code = 422): never
{
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false, $message, $data, $code);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id,order_number,user_id,cart_id,tax_amount,grand_total,
                payment_status,order_status
         FROM orders
         WHERE id=:order_id AND user_id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        addressFail($pdo, 'Order not found.', null, 404);
    }

    if (in_array($order['order_status'], ['cancelled','delivered'], true)) {
        addressFail($pdo, 'Address cannot be changed for this order.', [
            'order_status' => $order['order_status']
        ], 409);
    }

    $stmt = $pdo->prepare(
        "SELECT
            id,user_id,address_type,door_no,street,area,city,
            district,state,pincode,landmark,is_default,status
         FROM user_addresses
         WHERE id=:address_id AND user_id=:user_id
         LIMIT 1"
    );

    $stmt->bindValue(':address_id', $addressId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        addressFail($pdo, 'Address not found.', null, 404);
    }

    if ($address['status'] !== 'active') {
        addressFail($pdo, 'Selected address is inactive.', null, 422);
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM order_addresses
         WHERE order_id=:order_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $existingAddressId = $stmt->fetchColumn();

    if ($existingAddressId) {
        $stmt = $pdo->prepare(
            "UPDATE order_addresses SET
                user_address_id=:user_address_id,
                address_type=:address_type,
                door_no=:door_no,
                street=:street,
                area=:area,
                city=:city,
                district=:district,
                state=:state,
                pincode=:pincode,
                landmark=:landmark
             WHERE id=:id AND order_id=:order_id"
        );

        $stmt->bindValue(':id', (int)$existingAddressId, PDO::PARAM_INT);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO order_addresses (
                order_id,user_address_id,address_type,door_no,street,
                area,city,district,state,pincode,landmark
             ) VALUES (
                :order_id,:user_address_id,:address_type,:door_no,:street,
                :area,:city,:district,:state,:pincode,:landmark
             )"
        );

        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    }

    $stmt->bindValue(':user_address_id', $addressId, PDO::PARAM_INT);
    $stmt->bindValue(':address_type', $address['address_type'], PDO::PARAM_STR);
    $stmt->bindValue(':door_no', $address['door_no'], PDO::PARAM_STR);
    $stmt->bindValue(':street', $address['street'], PDO::PARAM_STR);
    $stmt->bindValue(':area', $address['area'], PDO::PARAM_STR);
    $stmt->bindValue(':city', $address['city'], PDO::PARAM_STR);
    $stmt->bindValue(
        ':district',
        $address['district'],
        $address['district'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );
    $stmt->bindValue(':state', $address['state'], PDO::PARAM_STR);
    $stmt->bindValue(':pincode', $address['pincode'], PDO::PARAM_STR);
    $stmt->bindValue(
        ':landmark',
        $address['landmark'],
        $address['landmark'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
    );

    $stmt->execute();

    $orderAddressId = $existingAddressId
        ? (int)$existingAddressId
        : (int)$pdo->lastInsertId();

    $sameState =
        strcasecmp(
            trim((string)$address['state']),
            trim($sellerState)
        ) === 0;

    $stmt = $pdo->prepare(
        "SELECT id,gst_rate,tax_amount
         FROM order_items
         WHERE order_id=:order_id
         FOR UPDATE"
    );

    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        addressFail($pdo, 'No order items found.', null, 404);
    }

    $updateTaxStmt = $pdo->prepare(
        "UPDATE order_items SET
            cgst_rate=:cgst_rate,
            cgst_amount=:cgst_amount,
            sgst_rate=:sgst_rate,
            sgst_amount=:sgst_amount,
            igst_rate=:igst_rate,
            igst_amount=:igst_amount
         WHERE id=:id AND order_id=:order_id"
    );

    $taxDetails = [];
    $totalCgst = 0.00;
    $totalSgst = 0.00;
    $totalIgst = 0.00;

    foreach ($items as $item) {
        $gstRate = round((float)$item['gst_rate'], 2);
        $itemTaxAmount = round((float)$item['tax_amount'], 2);

        $cgstRate = 0.00;
        $cgstAmount = 0.00;
        $sgstRate = 0.00;
        $sgstAmount = 0.00;
        $igstRate = 0.00;
        $igstAmount = 0.00;

        if ($sameState) {
            $cgstRate = round($gstRate / 2, 2);
            $sgstRate = round($gstRate - $cgstRate, 2);

            $cgstAmount = round($itemTaxAmount / 2, 2);
            $sgstAmount = round(
                $itemTaxAmount - $cgstAmount,
                2
            );
        } else {
            $igstRate = $gstRate;
            $igstAmount = $itemTaxAmount;
        }

        $updateTaxStmt->bindValue(
            ':cgst_rate',
            number_format($cgstRate, 2, '.', ''),
            PDO::PARAM_STR
        );

        $updateTaxStmt->bindValue(
            ':cgst_amount',
            number_format($cgstAmount, 2, '.', ''),
            PDO::PARAM_STR
        );

        $updateTaxStmt->bindValue(
            ':sgst_rate',
            number_format($sgstRate, 2, '.', ''),
            PDO::PARAM_STR
        );

        $updateTaxStmt->bindValue(
            ':sgst_amount',
            number_format($sgstAmount, 2, '.', ''),
            PDO::PARAM_STR
        );

        $updateTaxStmt->bindValue(
            ':igst_rate',
            number_format($igstRate, 2, '.', ''),
            PDO::PARAM_STR
        );

        $updateTaxStmt->bindValue(
            ':igst_amount',
            number_format($igstAmount, 2, '.', ''),
            PDO::PARAM_STR
        );

        $updateTaxStmt->bindValue(
            ':id',
            (int)$item['id'],
            PDO::PARAM_INT
        );

        $updateTaxStmt->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $updateTaxStmt->execute();

        $totalCgst = round($totalCgst + $cgstAmount, 2);
        $totalSgst = round($totalSgst + $sgstAmount, 2);
        $totalIgst = round($totalIgst + $igstAmount, 2);

        $taxDetails[] = [
            'order_item_id' => (int)$item['id'],
            'gst_rate' => number_format($gstRate, 2, '.', ''),
            'tax_amount' => number_format($itemTaxAmount, 2, '.', ''),
            'cgst_rate' => number_format($cgstRate, 2, '.', ''),
            'cgst_amount' => number_format($cgstAmount, 2, '.', ''),
            'sgst_rate' => number_format($sgstRate, 2, '.', ''),
            'sgst_amount' => number_format($sgstAmount, 2, '.', ''),
            'igst_rate' => number_format($igstRate, 2, '.', ''),
            'igst_amount' => number_format($igstAmount, 2, '.', '')
        ];
    }

    $pdo->commit();

    sendResponse(true, 'Order address selected successfully.', [
        'order' => [
            'id' => (int)$order['id'],
            'order_number' => $order['order_number'],
            'tax_type' => $sameState ? 'CGST_SGST' : 'IGST',
            'seller_state' => $sellerState,
            'delivery_state' => $address['state'],
            'tax_amount' => $order['tax_amount'],
            'grand_total' => $order['grand_total']
        ],
        'address' => [
            'id' => $orderAddressId,
            'user_address_id' => $addressId,
            'address_type' => $address['address_type'],
            'door_no' => $address['door_no'],
            'street' => $address['street'],
            'area' => $address['area'],
            'city' => $address['city'],
            'district' => $address['district'],
            'state' => $address['state'],
            'pincode' => $address['pincode'],
            'landmark' => $address['landmark']
        ],
        'gst_summary' => [
            'total_tax' => number_format(
                $totalCgst + $totalSgst + $totalIgst,
                2,
                '.',
                ''
            ),
            'cgst_amount' => number_format($totalCgst, 2, '.', ''),
            'sgst_amount' => number_format($totalSgst, 2, '.', ''),
            'igst_amount' => number_format($totalIgst, 2, '.', '')
        ],
        'items_tax' => $taxDetails
    ], 200);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    sendResponse(
        false,
        'Unable to select order address.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV') && APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}