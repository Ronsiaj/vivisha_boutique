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
| OPTIONS
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
| AUTHENTICATE USER
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
| READ JSON BODY
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

if ($addressIdInput === '') {
    sendResponse(
        false,
        'Address ID is required.',
        null,
        422
    );
}

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
| CHECK ADDRESS OWNERSHIP
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
         WHERE id = :id
         AND user_id = :user_id
         LIMIT 1"
    );

    $stmt->bindValue(':id', $addressId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

    $stmt->execute();

    $existingAddress = $stmt->fetch(PDO::FETCH_ASSOC);

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

if (!$existingAddress) {
    sendResponse(
        false,
        'Address not found.',
        null,
        404
    );
}

/*
|--------------------------------------------------------------------------
| ALLOWED UPDATE FIELDS
|--------------------------------------------------------------------------
*/

$allowedFields = [
    'address_type',
    'door_no',
    'street',
    'area',
    'city',
    'district',
    'state',
    'pincode',
    'landmark',
    'is_default',
    'status'
];

/*
|--------------------------------------------------------------------------
| CHECK UNKNOWN FIELDS
|--------------------------------------------------------------------------
*/

foreach ($data as $key => $value) {

    if ($key === 'address_id') {
        continue;
    }

    if (!in_array($key, $allowedFields, true)) {
        sendResponse(
            false,
            'Invalid field: ' . $key,
            [
                'allowed_fields' => $allowedFields
            ],
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE AT LEAST ONE FIELD TO UPDATE
|--------------------------------------------------------------------------
*/

$hasUpdateField = false;

foreach ($allowedFields as $field) {
    if (array_key_exists($field, $data)) {
        $hasUpdateField = true;
        break;
    }
}

if (!$hasUpdateField) {
    sendResponse(
        false,
        'At least one field is required to update the address.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| VALUES
|--------------------------------------------------------------------------
*/

$addressType = array_key_exists('address_type', $data)
    ? strtolower(trim((string)$data['address_type']))
    : null;

$doorNo = array_key_exists('door_no', $data)
    ? trim((string)$data['door_no'])
    : null;

$street = array_key_exists('street', $data)
    ? trim((string)$data['street'])
    : null;

$area = array_key_exists('area', $data)
    ? trim((string)$data['area'])
    : null;

$city = array_key_exists('city', $data)
    ? trim((string)$data['city'])
    : null;

$district = array_key_exists('district', $data)
    ? trim((string)$data['district'])
    : null;

$state = array_key_exists('state', $data)
    ? trim((string)$data['state'])
    : null;

$pincode = array_key_exists('pincode', $data)
    ? trim((string)$data['pincode'])
    : null;

$landmark = array_key_exists('landmark', $data)
    ? trim((string)$data['landmark'])
    : null;

$status = array_key_exists('status', $data)
    ? strtolower(trim((string)$data['status']))
    : null;

$isDefault = null;

/*
|--------------------------------------------------------------------------
| ADDRESS TYPE VALIDATION
|--------------------------------------------------------------------------
*/

if ($addressType !== null) {

    $allowedAddressTypes = [
        'home',
        'work',
        'other'
    ];

    if (!in_array($addressType, $allowedAddressTypes, true)) {
        sendResponse(
            false,
            'Invalid address type. Allowed values are home, work, other.',
            [
                'allowed_address_types' => $allowedAddressTypes
            ],
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRED TEXT FIELDS IF PROVIDED
|--------------------------------------------------------------------------
*/

if ($doorNo !== null && $doorNo === '') {
    sendResponse(
        false,
        'Door number cannot be empty.',
        null,
        422
    );
}

if ($street !== null && $street === '') {
    sendResponse(
        false,
        'Street cannot be empty.',
        null,
        422
    );
}

if ($area !== null && $area === '') {
    sendResponse(
        false,
        'Area cannot be empty.',
        null,
        422
    );
}

if ($city !== null && $city === '') {
    sendResponse(
        false,
        'City cannot be empty.',
        null,
        422
    );
}

if ($state !== null && $state === '') {
    sendResponse(
        false,
        'State cannot be empty.',
        null,
        422
    );
}

if ($pincode !== null && $pincode === '') {
    sendResponse(
        false,
        'Pincode cannot be empty.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| LENGTH VALIDATIONS
|--------------------------------------------------------------------------
*/

if (
    $doorNo !== null &&
    mb_strlen($doorNo) > 100
) {
    sendResponse(
        false,
        'Door number must not exceed 100 characters.',
        null,
        422
    );
}

if (
    $street !== null &&
    (mb_strlen($street) < 2 || mb_strlen($street) > 150)
) {
    sendResponse(
        false,
        'Street must be between 2 and 150 characters.',
        null,
        422
    );
}

if (
    $area !== null &&
    (mb_strlen($area) < 2 || mb_strlen($area) > 150)
) {
    sendResponse(
        false,
        'Area must be between 2 and 150 characters.',
        null,
        422
    );
}

if (
    $city !== null &&
    (mb_strlen($city) < 2 || mb_strlen($city) > 100)
) {
    sendResponse(
        false,
        'City must be between 2 and 100 characters.',
        null,
        422
    );
}

if (
    $district !== null &&
    $district !== '' &&
    mb_strlen($district) > 100
) {
    sendResponse(
        false,
        'District must not exceed 100 characters.',
        null,
        422
    );
}

if (
    $state !== null &&
    (mb_strlen($state) < 2 || mb_strlen($state) > 100)
) {
    sendResponse(
        false,
        'State must be between 2 and 100 characters.',
        null,
        422
    );
}

if (
    $landmark !== null &&
    $landmark !== '' &&
    mb_strlen($landmark) > 150
) {
    sendResponse(
        false,
        'Landmark must not exceed 150 characters.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| PINCODE VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $pincode !== null &&
    !preg_match('/^[1-9][0-9]{5}$/', $pincode)
) {
    sendResponse(
        false,
        'Please enter a valid 6-digit Indian pincode.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| STATUS VALIDATION
|--------------------------------------------------------------------------
*/

if ($status !== null) {

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        sendResponse(
            false,
            'Invalid status. Allowed values are active or inactive.',
            [
                'allowed_statuses' => $allowedStatuses
            ],
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| IS DEFAULT VALIDATION
|--------------------------------------------------------------------------
*/

if (array_key_exists('is_default', $data)) {

    $input = $data['is_default'];

    if (
        $input === true ||
        $input === 1 ||
        $input === '1'
    ) {

        $isDefault = 1;

    } elseif (
        $input === false ||
        $input === 0 ||
        $input === '0'
    ) {

        $isDefault = 0;

    } else {

        sendResponse(
            false,
            'is_default must be 0 or 1.',
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT DEFAULT CANNOT BE DIRECTLY UNSET
|--------------------------------------------------------------------------
|
| User should select another address as default instead.
|
|--------------------------------------------------------------------------
*/

if (
    (int)$existingAddress['is_default'] === 1 &&
    $isDefault === 0
) {
    sendResponse(
        false,
        'The current default address cannot be unset directly. Select another address as default instead.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| INACTIVE ADDRESS CANNOT BECOME DEFAULT
|--------------------------------------------------------------------------
*/

$finalStatus = $status !== null
    ? $status
    : $existingAddress['status'];

if (
    $isDefault === 1 &&
    $finalStatus !== 'active'
) {
    sendResponse(
        false,
        'An inactive address cannot be selected as default.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| DEFAULT ADDRESS CANNOT BE MADE INACTIVE
|--------------------------------------------------------------------------
*/

if (
    (int)$existingAddress['is_default'] === 1 &&
    $status === 'inactive'
) {
    sendResponse(
        false,
        'Default address cannot be made inactive. Select another default address first.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| NORMALIZE NULLABLE FIELDS
|--------------------------------------------------------------------------
*/

if ($district !== null && $district === '') {
    $district = null;
}

if ($landmark !== null && $landmark === '') {
    $landmark = null;
}

/*
|--------------------------------------------------------------------------
| BUILD UPDATE
|--------------------------------------------------------------------------
*/

$updates = [];
$params = [
    ':id' => $addressId,
    ':user_id' => $userId
];

if ($addressType !== null) {
    $updates[] = 'address_type = :address_type';
    $params[':address_type'] = $addressType;
}

if ($doorNo !== null) {
    $updates[] = 'door_no = :door_no';
    $params[':door_no'] = $doorNo;
}

if ($street !== null) {
    $updates[] = 'street = :street';
    $params[':street'] = $street;
}

if ($area !== null) {
    $updates[] = 'area = :area';
    $params[':area'] = $area;
}

if ($city !== null) {
    $updates[] = 'city = :city';
    $params[':city'] = $city;
}

if (array_key_exists('district', $data)) {
    $updates[] = 'district = :district';
    $params[':district'] = $district;
}

if ($state !== null) {
    $updates[] = 'state = :state';
    $params[':state'] = $state;
}

if ($pincode !== null) {
    $updates[] = 'pincode = :pincode';
    $params[':pincode'] = $pincode;
}

if (array_key_exists('landmark', $data)) {
    $updates[] = 'landmark = :landmark';
    $params[':landmark'] = $landmark;
}

if ($status !== null) {
    $updates[] = 'status = :status';
    $params[':status'] = $status;
}

if ($isDefault !== null) {
    $updates[] = 'is_default = :is_default';
    $params[':is_default'] = $isDefault;
}

$updates[] = 'updated_at = CURRENT_TIMESTAMP';

/*
|--------------------------------------------------------------------------
| UPDATE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | IF NEW DEFAULT, REMOVE OLD DEFAULT
    |--------------------------------------------------------------------------
    */

    if ($isDefault === 1) {

        $stmt = $pdo->prepare(
            "UPDATE user_addresses
             SET
                is_default = 0,
                updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id
             AND id <> :address_id
             AND is_default = 1"
        );

        $stmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':address_id',
            $addressId,
            PDO::PARAM_INT
        );

        $stmt->execute();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE SELECTED ADDRESS
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE user_addresses
        SET " . implode(', ', $updates) . "
        WHERE id = :id
        AND user_id = :user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {

        if ($value === null) {

            $stmt->bindValue(
                $key,
                null,
                PDO::PARAM_NULL
            );

        } elseif (is_int($value)) {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_INT
            );

        } else {

            $stmt->bindValue(
                $key,
                $value,
                PDO::PARAM_STR
            );
        }
    }

    $stmt->execute();

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
         WHERE id = :id
         AND user_id = :user_id
         LIMIT 1"
    );

    $stmt->bindValue(
        ':id',
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
            'Address updated but details could not be retrieved.',
            null,
            500
        );
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Address updated successfully.',
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
            ]
        ],
        200
    );

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to update address.',
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