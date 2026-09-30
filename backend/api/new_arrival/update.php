<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/jwt.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PATCH, PUT, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(204);
    exit;
}

if(!in_array($_SERVER['REQUEST_METHOD'],['PATCH','PUT','POST'],true)){
    sendResponse(
        false,
        'Only PATCH, PUT or POST method is allowed.',
        null,
        405
    );
}

$decoded=authenticate();
validateJWTData($decoded);

if(getAuthenticatedType($decoded)!=='admin'){
    sendResponse(
        false,
        'Admin access only.',
        null,
        403
    );
}

$adminAuth=authenticateAdmin();
checkAdminRole($adminAuth,['admin']);

$adminId=getAuthenticatedId($adminAuth);

if($adminId<=0){
    sendResponse(
        false,
        'Invalid authenticated admin.',
        null,
        401
    );
}

$contentType=$_SERVER['CONTENT_TYPE']??'';

if(stripos($contentType,'application/json')===false){
    sendResponse(
        false,
        'Content-Type must be application/json.',
        null,
        415
    );
}

$rawBody=file_get_contents('php://input');

if($rawBody===false||trim($rawBody)===''){
    sendResponse(
        false,
        'Request body is required.',
        null,
        400
    );
}

$data=json_decode($rawBody,true);

if(
    json_last_error()!==JSON_ERROR_NONE||
    !is_array($data)
){
    sendResponse(
        false,
        'Invalid JSON body.',
        null,
        400
    );
}

/*
|--------------------------------------------------------------------------
| ALLOWED FIELDS
|--------------------------------------------------------------------------
*/

$allowedFields=[
    'id',
    'new_arrival_id',
    'product_variant_id',
    'sort_order',
    'status'
];

foreach(array_keys($data) as $field){
    if(!in_array($field,$allowedFields,true)){
        sendResponse(
            false,
            "Invalid field: {$field}.",
            [
                'allowed_fields'=>$allowedFields
            ],
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| NEW ARRIVAL ID
|--------------------------------------------------------------------------
*/

$idInput=trim((string)($data['id']??''));
$newArrivalIdInput=trim(
    (string)($data['new_arrival_id']??'')
);

if($idInput===''&&$newArrivalIdInput===''){
    sendResponse(
        false,
        'id or new_arrival_id is required.',
        null,
        422
    );
}

if(
    $idInput!==''&&
    !preg_match('/^[1-9][0-9]*$/',$idInput)
){
    sendResponse(
        false,
        'id must be a valid positive integer.',
        null,
        422
    );
}

if(
    $newArrivalIdInput!==''&&
    !preg_match(
        '/^[1-9][0-9]*$/',
        $newArrivalIdInput
    )
){
    sendResponse(
        false,
        'new_arrival_id must be a valid positive integer.',
        null,
        422
    );
}

if(
    $idInput!==''&&
    $newArrivalIdInput!==''&&
    (int)$idInput!==(int)$newArrivalIdInput
){
    sendResponse(
        false,
        'id and new_arrival_id cannot contain different values.',
        null,
        422
    );
}

$newArrivalId=(int)(
    $newArrivalIdInput!==''?
        $newArrivalIdInput:
        $idInput
);

/*
|--------------------------------------------------------------------------
| REQUIRE AT LEAST ONE UPDATE FIELD
|--------------------------------------------------------------------------
*/

$updateFields=array_diff(
    array_keys($data),
    ['id','new_arrival_id']
);

if(!$updateFields){
    sendResponse(
        false,
        'At least one field must be provided for update.',
        [
            'updatable_fields'=>[
                'product_variant_id',
                'sort_order',
                'status'
            ]
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| PRODUCT VARIANT VALIDATION
|--------------------------------------------------------------------------
*/

$newProductVariantId=null;

if(array_key_exists('product_variant_id',$data)){

    if(
        is_array($data['product_variant_id'])||
        is_object($data['product_variant_id'])||
        is_bool($data['product_variant_id'])||
        $data['product_variant_id']===null
    ){
        sendResponse(
            false,
            'product_variant_id must be a valid positive integer.',
            null,
            422
        );
    }

    $variantInput=trim(
        (string)$data['product_variant_id']
    );

    if(
        $variantInput===''||
        !preg_match('/^[1-9][0-9]*$/',$variantInput)
    ){
        sendResponse(
            false,
            'product_variant_id must be a valid positive integer.',
            null,
            422
        );
    }

    $newProductVariantId=(int)$variantInput;
}

/*
|--------------------------------------------------------------------------
| SORT ORDER VALIDATION
|--------------------------------------------------------------------------
*/

$newSortOrder=null;

if(array_key_exists('sort_order',$data)){

    if(
        is_array($data['sort_order'])||
        is_object($data['sort_order'])||
        is_bool($data['sort_order'])||
        $data['sort_order']===null
    ){
        sendResponse(
            false,
            'sort_order must be a valid non-negative integer.',
            null,
            422
        );
    }

    $sortOrderInput=trim(
        (string)$data['sort_order']
    );

    if(
        $sortOrderInput===''||
        !preg_match('/^[0-9]+$/',$sortOrderInput)
    ){
        sendResponse(
            false,
            'sort_order must be a valid non-negative integer.',
            null,
            422
        );
    }

    if(
        strlen($sortOrderInput)>10||
        (
            strlen($sortOrderInput)===10&&
            strcmp($sortOrderInput,'4294967295')>0
        )
    ){
        sendResponse(
            false,
            'sort_order exceeds allowed limit.',
            null,
            422
        );
    }

    $newSortOrder=(int)$sortOrderInput;
}

/*
|--------------------------------------------------------------------------
| STATUS VALIDATION
|--------------------------------------------------------------------------
*/

$newStatus=null;

if(array_key_exists('status',$data)){

    if(
        is_array($data['status'])||
        is_object($data['status'])||
        $data['status']===null
    ){
        sendResponse(
            false,
            'Invalid status.',
            null,
            422
        );
    }

    $newStatus=strtolower(
        trim((string)$data['status'])
    );

    if(!in_array(
        $newStatus,
        ['active','inactive'],
        true
    )){
        sendResponse(
            false,
            'Invalid status.',
            [
                'allowed_values'=>[
                    'active',
                    'inactive'
                ]
            ],
            422
        );
    }
}

try{

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | LOCK CURRENT NEW ARRIVAL RECORD
    |--------------------------------------------------------------------------
    */

    $currentStmt=$pdo->prepare(
        "SELECT
            id,
            product_variant_id,
            sort_order,
            status,
            created_by_admin_id,
            created_at,
            updated_at

         FROM new_arrival_variants

         WHERE id=:id

         LIMIT 1
         FOR UPDATE"
    );

    $currentStmt->bindValue(
        ':id',
        $newArrivalId,
        PDO::PARAM_INT
    );

    $currentStmt->execute();

    $current=$currentStmt->fetch(PDO::FETCH_ASSOC);

    if(!$current){

        $pdo->rollBack();

        sendResponse(
            false,
            'New arrival record not found.',
            [
                'new_arrival_id'=>$newArrivalId
            ],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL VALUES
    |--------------------------------------------------------------------------
    */

    $finalProductVariantId=
        $newProductVariantId!==null
            ?$newProductVariantId
            :(int)$current['product_variant_id'];

    $finalSortOrder=
        $newSortOrder!==null
            ?$newSortOrder
            :(int)$current['sort_order'];

    $finalStatus=
        $newStatus!==null
            ?$newStatus
            :(string)$current['status'];

    /*
    |--------------------------------------------------------------------------
    | CHECK PRODUCT VARIANT EXISTS
    |--------------------------------------------------------------------------
    */

    $variantStmt=$pdo->prepare(
        "SELECT
            id,
            product_id,
            size_id,
            color_id,
            sku,
            variant_name,
            original_price,
            discount_type,
            discount_value,
            selling_price,
            gst_rate,
            gst_amount,
            price_with_tax,
            stock_quantity,
            reserved_quantity,
            low_stock_limit,
            is_available,
            created_at,
            updated_at

         FROM product_variants

         WHERE id=:product_variant_id

         LIMIT 1
         FOR UPDATE"
    );

    $variantStmt->bindValue(
        ':product_variant_id',
        $finalProductVariantId,
        PDO::PARAM_INT
    );

    $variantStmt->execute();

    $variant=$variantStmt->fetch(PDO::FETCH_ASSOC);

    if(!$variant){

        $pdo->rollBack();

        sendResponse(
            false,
            'Product variant not found.',
            [
                'product_variant_id'=>
                    $finalProductVariantId
            ],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE VARIANT
    |--------------------------------------------------------------------------
    |
    | Same variant cannot exist in another new-arrival record.
    |--------------------------------------------------------------------------
    */

    $duplicateStmt=$pdo->prepare(
        "SELECT
            id,
            product_variant_id,
            sort_order,
            status

         FROM new_arrival_variants

         WHERE product_variant_id=:product_variant_id
           AND id<>:current_id

         LIMIT 1
         FOR UPDATE"
    );

    $duplicateStmt->bindValue(
        ':product_variant_id',
        $finalProductVariantId,
        PDO::PARAM_INT
    );

    $duplicateStmt->bindValue(
        ':current_id',
        $newArrivalId,
        PDO::PARAM_INT
    );

    $duplicateStmt->execute();

    $duplicate=$duplicateStmt->fetch(PDO::FETCH_ASSOC);

    if($duplicate){

        $pdo->rollBack();

        sendResponse(
            false,
            'This product variant is already added to another new arrival record.',
            [
                'existing_new_arrival'=>[
                    'id'=>
                        (int)$duplicate['id'],

                    'product_variant_id'=>
                        (int)$duplicate['product_variant_id'],

                    'sort_order'=>
                        (int)$duplicate['sort_order'],

                    'status'=>
                        $duplicate['status']
                ]
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK WHETHER ANYTHING ACTUALLY CHANGED
    |--------------------------------------------------------------------------
    */

    $productVariantChanged=
        $finalProductVariantId!==
        (int)$current['product_variant_id'];

    $sortOrderChanged=
        $finalSortOrder!==
        (int)$current['sort_order'];

    $statusChanged=
        $finalStatus!==
        (string)$current['status'];

    if(
        !$productVariantChanged&&
        !$sortOrderChanged&&
        !$statusChanged
    ){

        $pdo->commit();

        $stockQuantity=
            (int)$variant['stock_quantity'];

        $reservedQuantity=
            (int)$variant['reserved_quantity'];

        $availableQuantity=max(
            0,
            $stockQuantity-$reservedQuantity
        );

        $lowStockLimit=
            (int)$variant['low_stock_limit'];

        if($availableQuantity<=0){
            $stockStatus='out_of_stock';
        }elseif(
            $availableQuantity<=$lowStockLimit
        ){
            $stockStatus='low_stock';
        }else{
            $stockStatus='in_stock';
        }

        sendResponse(
            true,
            'No changes were required.',
            [
                'new_arrival'=>[
                    'id'=>
                        (int)$current['id'],

                    'product_variant_id'=>
                        (int)$current['product_variant_id'],

                    'sort_order'=>
                        (int)$current['sort_order'],

                    'status'=>
                        $current['status'],

                    'created_by_admin_id'=>
                        $current['created_by_admin_id']!==null
                            ?(int)$current['created_by_admin_id']
                            :null,

                    'created_at'=>
                        $current['created_at'],

                    'updated_at'=>
                        $current['updated_at']
                ],

                'product_variant'=>[
                    'id'=>
                        (int)$variant['id'],

                    'product_id'=>
                        (int)$variant['product_id'],

                    'sku'=>
                        $variant['sku'],

                    'variant_name'=>
                        $variant['variant_name'],

                    'stock'=>[
                        'stock_quantity'=>
                            $stockQuantity,

                        'reserved_quantity'=>
                            $reservedQuantity,

                        'available_quantity'=>
                            $availableQuantity,

                        'low_stock_limit'=>
                            $lowStockLimit,

                        'stock_status'=>
                            $stockStatus
                    ],

                    'is_available'=>
                        (int)$variant['is_available']
                ],

                'changed'=>false
            ],
            200
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    $updateStmt=$pdo->prepare(
        "UPDATE new_arrival_variants SET
            product_variant_id=:product_variant_id,
            sort_order=:sort_order,
            status=:status

         WHERE id=:id"
    );

    $updateStmt->bindValue(
        ':product_variant_id',
        $finalProductVariantId,
        PDO::PARAM_INT
    );

    $updateStmt->bindValue(
        ':sort_order',
        $finalSortOrder,
        PDO::PARAM_INT
    );

    $updateStmt->bindValue(
        ':status',
        $finalStatus,
        PDO::PARAM_STR
    );

    $updateStmt->bindValue(
        ':id',
        $newArrivalId,
        PDO::PARAM_INT
    );

    $updateStmt->execute();

    /*
    |--------------------------------------------------------------------------
    | FETCH UPDATED RECORD
    |--------------------------------------------------------------------------
    */

    $updatedStmt=$pdo->prepare(
        "SELECT
            id,
            product_variant_id,
            sort_order,
            status,
            created_by_admin_id,
            created_at,
            updated_at

         FROM new_arrival_variants

         WHERE id=:id

         LIMIT 1"
    );

    $updatedStmt->bindValue(
        ':id',
        $newArrivalId,
        PDO::PARAM_INT
    );

    $updatedStmt->execute();

    $updated=$updatedStmt->fetch(PDO::FETCH_ASSOC);

    if(!$updated){
        $pdo->rollBack();

        sendResponse(
            false,
            'Unable to retrieve updated new arrival.',
            null,
            500
        );
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | STOCK CALCULATION
    |--------------------------------------------------------------------------
    */

    $stockQuantity=
        (int)$variant['stock_quantity'];

    $reservedQuantity=
        (int)$variant['reserved_quantity'];

    $availableQuantity=max(
        0,
        $stockQuantity-$reservedQuantity
    );

    $lowStockLimit=
        (int)$variant['low_stock_limit'];

    if($availableQuantity<=0){
        $stockStatus='out_of_stock';
    }elseif(
        $availableQuantity<=$lowStockLimit
    ){
        $stockStatus='low_stock';
    }else{
        $stockStatus='in_stock';
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'New arrival updated successfully.',
        [
            'new_arrival'=>[
                'id'=>
                    (int)$updated['id'],

                'product_variant_id'=>
                    (int)$updated['product_variant_id'],

                'sort_order'=>
                    (int)$updated['sort_order'],

                'status'=>
                    $updated['status'],

                'created_by_admin_id'=>
                    $updated['created_by_admin_id']!==null
                        ?(int)$updated['created_by_admin_id']
                        :null,

                'created_at'=>
                    $updated['created_at'],

                'updated_at'=>
                    $updated['updated_at']
            ],

            'changes'=>[
                'product_variant_id'=>[
                    'changed'=>
                        $productVariantChanged,

                    'from'=>
                        (int)$current['product_variant_id'],

                    'to'=>
                        $finalProductVariantId
                ],

                'sort_order'=>[
                    'changed'=>
                        $sortOrderChanged,

                    'from'=>
                        (int)$current['sort_order'],

                    'to'=>
                        $finalSortOrder
                ],

                'status'=>[
                    'changed'=>
                        $statusChanged,

                    'from'=>
                        $current['status'],

                    'to'=>
                        $finalStatus
                ]
            ],

            'product_variant'=>[
                'id'=>
                    (int)$variant['id'],

                'product_id'=>
                    (int)$variant['product_id'],

                'size_id'=>
                    $variant['size_id']!==null
                        ?(int)$variant['size_id']
                        :null,

                'color_id'=>
                    $variant['color_id']!==null
                        ?(int)$variant['color_id']
                        :null,

                'sku'=>
                    $variant['sku'],

                'variant_name'=>
                    $variant['variant_name'],

                'pricing'=>[
                    'original_price'=>
                        $variant['original_price'],

                    'gst_rate'=>
                        $variant['gst_rate'],

                    'gst_amount'=>
                        $variant['gst_amount'],

                    'price_with_tax'=>
                        $variant['price_with_tax'],

                    'discount_type'=>
                        $variant['discount_type'],

                    'discount_value'=>
                        $variant['discount_value'],

                    'selling_price'=>
                        $variant['selling_price']
                ],

                'stock'=>[
                    'stock_quantity'=>
                        $stockQuantity,

                    'reserved_quantity'=>
                        $reservedQuantity,

                    'available_quantity'=>
                        $availableQuantity,

                    'low_stock_limit'=>
                        $lowStockLimit,

                    'stock_status'=>
                        $stockStatus
                ],

                'is_available'=>
                    (int)$variant['is_available'],

                'created_at'=>
                    $variant['created_at'],

                'updated_at'=>
                    $variant['updated_at']
            ],

            'updated_by_admin_id'=>
                $adminId,

            'changed'=>true
        ],
        200
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    /*
     * Unique product_variant_id constraint.
     */
    if((string)$e->getCode()==='23000'){
        sendResponse(
            false,
            'This product variant is already added to new arrivals.',
            [
                'product_variant_id'=>
                    $newProductVariantId
            ],
            409
        );
    }

    sendResponse(
        false,
        'Unable to update new arrival.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );

}catch(Throwable $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'An unexpected error occurred while updating new arrival.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}