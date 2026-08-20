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
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(
        false,
        'Only POST method is allowed.',
        null,
        405
    );
}

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
| INPUT VALUES
|--------------------------------------------------------------------------
*/

$addressType = isset($data['address_type'])
    ? strtolower(trim((string)$data['address_type']))
    : 'home';

$doorNo = isset($data['door_no'])
    ? trim((string)$data['door_no'])
    : '';

$street = isset($data['street'])
    ? trim((string)$data['street'])
    : '';

$area = isset($data['area'])
    ? trim((string)$data['area'])
    : '';

$city = isset($data['city'])
    ? trim((string)$data['city'])
    : '';

$district = isset($data['district'])
    ? trim((string)$data['district'])
    : '';

$state = isset($data['state'])
    ? trim((string)$data['state'])
    : '';

$pincode = isset($data['pincode'])
    ? trim((string)$data['pincode'])
    : '';

$landmark = isset($data['landmark'])
    ? trim((string)$data['landmark'])
    : '';

$isDefaultInput = $data['is_default'] ?? 0;

/*
|--------------------------------------------------------------------------
| ADDRESS TYPE VALIDATION
|--------------------------------------------------------------------------
*/

$allowedAddressTypes = [
    'home',
    'work',
    'other'
];

if (
    !in_array(
        $addressType,
        $allowedAddressTypes,
        true
    )
) {
    sendResponse(
        false,
        'Invalid address type. Allowed values are home, work, other.',
        [
            'allowed_address_types' => $allowedAddressTypes
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| REQUIRED FIELD VALIDATION
|--------------------------------------------------------------------------
*/

if ($doorNo === '') {
    sendResponse(
        false,
        'Door number is required.',
        null,
        422
    );
}

if ($street === '') {
    sendResponse(
        false,
        'Street is required.',
        null,
        422
    );
}

if ($area === '') {
    sendResponse(
        false,
        'Area is required.',
        null,
        422
    );
}

if ($city === '') {
    sendResponse(
        false,
        'City is required.',
        null,
        422
    );
}

if ($state === '') {
    sendResponse(
        false,
        'State is required.',
        null,
        422
    );
}

if ($pincode === '') {
    sendResponse(
        false,
        'Pincode is required.',
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
    mb_strlen($doorNo) < 1 ||
    mb_strlen($doorNo) > 100
) {
    sendResponse(
        false,
        'Door number must be between 1 and 100 characters.',
        null,
        422
    );
}

if (
    mb_strlen($street) < 2 ||
    mb_strlen($street) > 150
) {
    sendResponse(
        false,
        'Street must be between 2 and 150 characters.',
        null,
        422
    );
}

if (
    mb_strlen($area) < 2 ||
    mb_strlen($area) > 150
) {
    sendResponse(
        false,
        'Area must be between 2 and 150 characters.',
        null,
        422
    );
}

if (
    mb_strlen($city) < 2 ||
    mb_strlen($city) > 100
) {
    sendResponse(
        false,
        'City must be between 2 and 100 characters.',
        null,
        422
    );
}

if (
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
    mb_strlen($state) < 2 ||
    mb_strlen($state) > 100
) {
    sendResponse(
        false,
        'State must be between 2 and 100 characters.',
        null,
        422
    );
}

if (
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
|
| Indian pincode:
|
| - Exactly 6 digits
| - Cannot start with 0
|
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[1-9][0-9]{5}$/',
        $pincode
    )
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
| TEXT FIELD VALIDATION
|--------------------------------------------------------------------------
|
| Prevent obvious invalid control characters.
|
|--------------------------------------------------------------------------
*/

$textFields = [
    'door_no' => $doorNo,
    'street' => $street,
    'area' => $area,
    'city' => $city,
    'district' => $district,
    'state' => $state,
    'landmark' => $landmark
];

foreach ($textFields as $field => $value) {

    if ($value === '') {
        continue;
    }

    if (
        preg_match(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
            $value
        )
    ) {
        sendResponse(
            false,
            $field . ' contains invalid characters.',
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| IS_DEFAULT VALIDATION
|--------------------------------------------------------------------------
|
| Accepted:
|
| 0
| 1
| "0"
| "1"
| false
| true
|
|--------------------------------------------------------------------------
*/

if (
    $isDefaultInput === true ||
    $isDefaultInput === 1 ||
    $isDefaultInput === '1'
) {

    $isDefault = 1;

} elseif (
    $isDefaultInput === false ||
    $isDefaultInput === 0 ||
    $isDefaultInput === '0'
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

/*
|--------------------------------------------------------------------------
| OPTIONAL NULL VALUES
|--------------------------------------------------------------------------
*/

$districtValue =
    $district !== ''
        ? $district
        : null;

$landmarkValue =
    $landmark !== ''
        ? $landmark
        : null;

/*
|--------------------------------------------------------------------------
| CREATE ADDRESS
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    |
    | Transaction is important because if new address is default,
    | old default must be removed before inserting the new address.
    |
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | CHECK EXISTING ADDRESS COUNT
    |--------------------------------------------------------------------------
    |
    | If this is user's very first address,
    | automatically make it default.
    |
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM user_addresses
         WHERE user_id = :user_id"
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $existingAddressCount =
        (int)$stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | FIRST ADDRESS AUTO DEFAULT
    |--------------------------------------------------------------------------
    */

    if ($existingAddressCount === 0) {
        $isDefault = 1;
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE OLD DEFAULT
    |--------------------------------------------------------------------------
    |
    | A user should have only one default address.
    |
    |--------------------------------------------------------------------------
    */

    if ($isDefault === 1) {

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
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT NEW ADDRESS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "INSERT INTO user_addresses
        (
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
            status
        )
        VALUES
        (
            :user_id,
            :address_type,
            :door_no,
            :street,
            :area,
            :city,
            :district,
            :state,
            :pincode,
            :landmark,
            :is_default,
            'active'
        )"
    );

    $stmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':address_type',
        $addressType,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':door_no',
        $doorNo,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':street',
        $street,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':area',
        $area,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':city',
        $city,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':district',
        $districtValue,
        $districtValue === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':state',
        $state,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':pincode',
        $pincode,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':landmark',
        $landmarkValue,
        $landmarkValue === null
            ? PDO::PARAM_NULL
            : PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':is_default',
        $isDefault,
        PDO::PARAM_INT
    );

    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | NEW ADDRESS ID
    |--------------------------------------------------------------------------
    */

    $addressId =
        (int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | FETCH CREATED ADDRESS
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

    $address =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {

        $pdo->rollBack();

        sendResponse(
            false,
            'Address created but details could not be retrieved.',
            null,
            500
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Address created successfully.',
        [
            'address' => [
                'id' =>
                    (int)$address['id'],

                'user_id' =>
                    (int)$address['user_id'],

                'address_type' =>
                    $address['address_type'],

                'door_no' =>
                    $address['door_no'],

                'street' =>
                    $address['street'],

                'area' =>
                    $address['area'],

                'city' =>
                    $address['city'],

                'district' =>
                    $address['district'],

                'state' =>
                    $address['state'],

                'pincode' =>
                    $address['pincode'],

                'landmark' =>
                    $address['landmark'],

                'is_default' =>
                    (int)$address['is_default'],

                'status' =>
                    $address['status'],

                'created_at' =>
                    $address['created_at'],

                'updated_at' =>
                    $address['updated_at']
            ]
        ],
        201
    );

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to create address.',
        APP_ENV === 'development'
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'An unexpected error occurred.',
        APP_ENV === 'development'
            ? [
                'error' => $e->getMessage()
            ]
            : null,
        500
    );
}