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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(204);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    sendResponse(false,'Only POST method is allowed.',null,405);
}

$decoded=authenticate();
validateJWTData($decoded);

if(getAuthenticatedType($decoded)!=='admin'){
    sendResponse(false,'Admin access only.',null,403);
}

$adminAuth=authenticateAdmin();
checkAdminRole($adminAuth,['admin']);

$adminId=getAuthenticatedId($adminAuth);

if($adminId<=0){
    sendResponse(false,'Invalid authenticated admin.',null,401);
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
| ALLOWED INPUTS
|--------------------------------------------------------------------------
*/

$allowedFields=[
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
| PRODUCT VARIANT ID
|--------------------------------------------------------------------------
*/

if(!array_key_exists('product_variant_id',$data)){
    sendResponse(
        false,
        'product_variant_id is required.',
        null,
        422
    );
}

if(
    is_array($data['product_variant_id'])||
    is_object($data['product_variant_id'])
){
    sendResponse(
        false,
        'product_variant_id must be a valid positive integer.',
        null,
        422
    );
}

$productVariantIdInput=trim(
    (string)$data['product_variant_id']
);

if(
    $productVariantIdInput===''||
    !preg_match('/^[1-9][0-9]*$/',$productVariantIdInput)
){
    sendResponse(
        false,
        'product_variant_id must be a valid positive integer.',
        null,
        422
    );
}

$productVariantId=(int)$productVariantIdInput;

/*
|--------------------------------------------------------------------------
| SORT ORDER
|--------------------------------------------------------------------------
*/

$sortOrder=0;

if(array_key_exists('sort_order',$data)){

    if(
        is_array($data['sort_order'])||
        is_object($data['sort_order'])
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

    $sortOrder=(int)$sortOrderInput;

    if($sortOrder>4294967295){
        sendResponse(
            false,
            'sort_order exceeds allowed limit.',
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status='active';

if(array_key_exists('status',$data)){

    if(
        is_array($data['status'])||
        is_object($data['status'])
    ){
        sendResponse(
            false,
            'Invalid status.',
            null,
            422
        );
    }

    $status=strtolower(
        trim((string)$data['status'])
    );

    if(!in_array($status,['active','inactive'],true)){
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
        $productVariantId,
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
                'product_variant_id'=>$productVariantId
            ],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE
    |--------------------------------------------------------------------------
    */

    $duplicateStmt=$pdo->prepare(
        "SELECT
            id,
            product_variant_id,
            sort_order,
            status,
            created_by_admin_id,
            created_at,
            updated_at

         FROM new_arrival_variants

         WHERE product_variant_id=:product_variant_id

         LIMIT 1
         FOR UPDATE"
    );

    $duplicateStmt->bindValue(
        ':product_variant_id',
        $productVariantId,
        PDO::PARAM_INT
    );

    $duplicateStmt->execute();

    $existing=$duplicateStmt->fetch(PDO::FETCH_ASSOC);

    if($existing){

        $pdo->rollBack();

        sendResponse(
            false,
            'This product variant is already added to new arrivals.',
            [
                'existing_new_arrival'=>[
                    'id'=>(int)$existing['id'],
                    'product_variant_id'=>
                        (int)$existing['product_variant_id'],
                    'sort_order'=>
                        (int)$existing['sort_order'],
                    'status'=>
                        $existing['status'],
                    'created_by_admin_id'=>
                        $existing['created_by_admin_id']!==null
                            ?(int)$existing['created_by_admin_id']
                            :null,
                    'created_at'=>
                        $existing['created_at'],
                    'updated_at'=>
                        $existing['updated_at']
                ]
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT NEW ARRIVAL
    |--------------------------------------------------------------------------
    */

    $insertStmt=$pdo->prepare(
        "INSERT INTO new_arrival_variants(
            product_variant_id,
            sort_order,
            status,
            created_by_admin_id
         )VALUES(
            :product_variant_id,
            :sort_order,
            :status,
            :created_by_admin_id
         )"
    );

    $insertStmt->bindValue(
        ':product_variant_id',
        $productVariantId,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':sort_order',
        $sortOrder,
        PDO::PARAM_INT
    );

    $insertStmt->bindValue(
        ':status',
        $status,
        PDO::PARAM_STR
    );

    $insertStmt->bindValue(
        ':created_by_admin_id',
        $adminId,
        PDO::PARAM_INT
    );

    $insertStmt->execute();

    $newArrivalId=(int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | FETCH CREATED RECORD
    |--------------------------------------------------------------------------
    */

    $createdStmt=$pdo->prepare(
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

    $createdStmt->bindValue(
        ':id',
        $newArrivalId,
        PDO::PARAM_INT
    );

    $createdStmt->execute();

    $created=$createdStmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | STOCK CALCULATION
    |--------------------------------------------------------------------------
    */

    $stockQuantity=(int)$variant['stock_quantity'];
    $reservedQuantity=(int)$variant['reserved_quantity'];

    $availableQuantity=max(
        0,
        $stockQuantity-$reservedQuantity
    );

    $lowStockLimit=(int)$variant['low_stock_limit'];

    if($availableQuantity<=0){
        $stockStatus='out_of_stock';
    }elseif($availableQuantity<=$lowStockLimit){
        $stockStatus='low_stock';
    }else{
        $stockStatus='in_stock';
    }

    sendResponse(
        true,
        'Product variant added to new arrivals successfully.',
        [
            'new_arrival'=>[
                'id'=>(int)$created['id'],

                'product_variant_id'=>
                    (int)$created['product_variant_id'],

                'sort_order'=>
                    (int)$created['sort_order'],

                'status'=>
                    $created['status'],

                'created_by_admin_id'=>
                    $created['created_by_admin_id']!==null
                        ?(int)$created['created_by_admin_id']
                        :null,

                'created_at'=>
                    $created['created_at'],

                'updated_at'=>
                    $created['updated_at']
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
            ]
        ],
        201
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    /*
     * Unique product_variant_id protection.
     */
    if((string)$e->getCode()==='23000'){

        sendResponse(
            false,
            'This product variant is already added to new arrivals.',
            [
                'product_variant_id'=>
                    $productVariantId
            ],
            409
        );
    }

    sendResponse(
        false,
        'Unable to create new arrival.',
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
        'An unexpected error occurred while creating new arrival.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}