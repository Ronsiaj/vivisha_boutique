<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/jwt.php';

/*
|--------------------------------------------------------------------------
| RESPONSE HEADERS
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

/*
|--------------------------------------------------------------------------
| OPTIONS REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/*
|--------------------------------------------------------------------------
| METHOD VALIDATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(
        false,
        'Only PUT method is allowed.',
        null,
        405
    );
}

/*
|--------------------------------------------------------------------------
| USER AUTHENTICATION
|--------------------------------------------------------------------------
|
| authenticateUser() checks:
| - Bearer token
| - Token validity
| - User type
| - User exists
| - User status active
|
|--------------------------------------------------------------------------
*/

$authUser = authenticateUser();

$userId = getAuthenticatedId($authUser);

if ($userId <= 0) {
    sendResponse(
        false,
        'Invalid authenticated user.',
        null,
        401
    );
}

/*
|--------------------------------------------------------------------------
| READ JSON INPUT
|--------------------------------------------------------------------------
*/

$data = getJsonInput();

if (!is_array($data)) {
    sendResponse(
        false,
        'Invalid JSON request body.',
        null,
        400
    );
}

/*
|--------------------------------------------------------------------------
| ADDRESS ID
|--------------------------------------------------------------------------
*/

$addressIdInput = isset($data['address_id'])
    ? trim((string)$data['address_id'])
    : '';

/*
|--------------------------------------------------------------------------
| ADDRESS ID REQUIRED
|--------------------------------------------------------------------------
*/

if ($addressIdInput === '') {
    sendResponse(
        false,
        'Address ID is required.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| ADDRESS ID VALIDATION
|--------------------------------------------------------------------------
*/

if (!preg_match('/^[1-9][0-9]*$/', $addressIdInput)) {
    sendResponse(
        false,
        'Address ID must be a valid positive integer.',
        null,
        422
    );
}

$addressId = (int)$addressIdInput;

if ($addressId <= 0) {
    sendResponse(
        false,
        'Invalid address ID.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| VERIFY ADDRESS OWNERSHIP
|--------------------------------------------------------------------------
|
| Most important authorization check:
|
| address must belong to authenticated user.
|
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            address_type,
            door_no,
            street,
            area,
            city,
            district,
            state,
            pincode,
            landmark,
            is_default,
            status,
            created_at,
            updated_at
         FROM user_addresses
         WHERE id = :address_id
         AND user_id = :user_id
         LIMIT 1"
    );

    $stmt->bindValue(
        ':address_id',
        $addressId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $address = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    sendResponse(
        false,
        'Unable to verify address.',
        APP_ENV === 'development'
            ? ['error' => $e->getMessage()]
            : null,
        500
    );
}

/*
|--------------------------------------------------------------------------
| ADDRESS NOT FOUND / NOT OWNED
|--------------------------------------------------------------------------
*/

if (!$address) {
    sendResponse(
        false,
        'Address not found.',
        null,
        404
    );
}

/*
|--------------------------------------------------------------------------
| ADDRESS STATUS CHECK
|--------------------------------------------------------------------------
|
| Inactive address should not become default.
|
|--------------------------------------------------------------------------
*/

if ($address['status'] !== 'active') {
    sendResponse(
        false,
        'Only an active address can be selected as default.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| ALREADY DEFAULT
|--------------------------------------------------------------------------
*/

if ((int)$address['is_default'] === 1) {

    sendResponse(
        true,
        'This address is already your default address.',
        [
            'address' => [
                'id' => (int)$address['id'],
                'user_id' => (int)$address['user_id'],
                'address_type' => $address['address_type'],
                'door_no' => $address['door_no'],
                'street' => $address['street'],
                'area' => $address['area'],
                'city' => $address['city'],
                'district' => $address['district'],
                'state' => $address['state'],
                'pincode' => $address['pincode'],
                'landmark' => $address['landmark'],
                'is_default' => 1,
                'status' => $address['status'],
                'created_at' => $address['created_at'],
                'updated_at' => $address['updated_at']
            ],
            'changed' => false
        ],
        200
    );
}

/*
|--------------------------------------------------------------------------
| CHANGE DEFAULT ADDRESS
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | REMOVE CURRENT DEFAULT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "UPDATE user_addresses
         SET
            is_default = 0,
            updated_at = CURRENT_TIMESTAMP
         WHERE user_id = :user_id
         AND is_default = 1"
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | SET SELECTED ADDRESS AS DEFAULT
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "UPDATE user_addresses
         SET
            is_default = 1,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :address_id
         AND user_id = :user_id
         AND status = 'active'
         LIMIT 1"
    );

    $stmt->bindValue(
        ':address_id',
        $addressId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | VERIFY UPDATE
    |--------------------------------------------------------------------------
    */

    if ($stmt->rowCount() !== 1) {

        $pdo->rollBack();

        sendResponse(
            false,
            'Unable to set the selected address as default.',
            null,
            500
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH UPDATED ADDRESS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT
            id,
            user_id,
            address_type,
            door_no,
            street,
            area,
            city,
            district,
            state,
            pincode,
            landmark,
            is_default,
            status,
            created_at,
            updated_at
         FROM user_addresses
         WHERE id = :address_id
         AND user_id = :user_id
         LIMIT 1"
    );

    $stmt->bindValue(
        ':address_id',
        $addressId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $updatedAddress = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$updatedAddress) {

        $pdo->rollBack();

        sendResponse(
            false,
            'Default address updated but details could not be retrieved.',
            null,
            500
        );
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Default address updated successfully.',
        [
            'address' => [
                'id' => (int)$updatedAddress['id'],
                'user_id' => (int)$updatedAddress['user_id'],
                'address_type' => $updatedAddress['address_type'],
                'door_no' => $updatedAddress['door_no'],
                'street' => $updatedAddress['street'],
                'area' => $updatedAddress['area'],
                'city' => $updatedAddress['city'],
                'district' => $updatedAddress['district'],
                'state' => $updatedAddress['state'],
                'pincode' => $updatedAddress['pincode'],
                'landmark' => $updatedAddress['landmark'],
                'is_default' => (int)$updatedAddress['is_default'],
                'status' => $updatedAddress['status'],
                'created_at' => $updatedAddress['created_at'],
                'updated_at' => $updatedAddress['updated_at']
            ],
            'changed' => true
        ],
        200
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to update default address.',
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