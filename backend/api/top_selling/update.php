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

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| CONTENT TYPE
|--------------------------------------------------------------------------
*/

$contentType=$_SERVER['CONTENT_TYPE']??'';

if(stripos($contentType,'application/json')===false){
    sendResponse(
        false,
        'Content-Type must be application/json.',
        null,
        415
    );
}

/*
|--------------------------------------------------------------------------
| JSON BODY
|--------------------------------------------------------------------------
*/

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
    'top_selling_id',
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
| TOP SELLING ID
|--------------------------------------------------------------------------
*/

$idInput=trim(
    (string)($data['id']??'')
);

$topSellingIdInput=trim(
    (string)($data['top_selling_id']??'')
);

if($idInput===''&&$topSellingIdInput===''){
    sendResponse(
        false,
        'id or top_selling_id is required.',
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
    $topSellingIdInput!==''&&
    !preg_match('/^[1-9][0-9]*$/',$topSellingIdInput)
){
    sendResponse(
        false,
        'top_selling_id must be a valid positive integer.',
        null,
        422
    );
}

if(
    $idInput!==''&&
    $topSellingIdInput!==''&&
    (int)$idInput!==(int)$topSellingIdInput
){
    sendResponse(
        false,
        'id and top_selling_id cannot contain different values.',
        null,
        422
    );
}

$topSellingId=(int)(
    $topSellingIdInput!==''?
        $topSellingIdInput:
        $idInput
);

/*
|--------------------------------------------------------------------------
| REQUIRE UPDATE FIELD
|--------------------------------------------------------------------------
*/

$updateFields=array_diff(
    array_keys($data),
    ['id','top_selling_id']
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
        $data['product_variant_id']===null||
        is_array($data['product_variant_id'])||
        is_object($data['product_variant_id'])||
        is_bool($data['product_variant_id'])
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
        $data['sort_order']===null||
        is_array($data['sort_order'])||
        is_object($data['sort_order'])||
        is_bool($data['sort_order'])
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

    /*
     * INT UNSIGNED max = 4294967295
     */
    if(
        strlen($sortOrderInput)>10||
        (
            strlen($sortOrderInput)===10&&
            strcmp(
                $sortOrderInput,
                '4294967295'
            )>0
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
        $data['status']===null||
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

    $newStatus=strtolower(
        trim((string)$data['status'])
    );

    if(
        !in_array(
            $newStatus,
            ['active','inactive'],
            true
        )
    ){
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
    | LOCK CURRENT TOP SELLING RECORD
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

         FROM top_selling_variants

         WHERE id=:id

         LIMIT 1
         FOR UPDATE"
    );

    $currentStmt->bindValue(
        ':id',
        $topSellingId,
        PDO::PARAM_INT
    );

    $currentStmt->execute();

    $current=$currentStmt->fetch(PDO::FETCH_ASSOC);

    if(!$current){

        $pdo->rollBack();

        sendResponse(
            false,
            'Top selling record not found.',
            [
                'top_selling_id'=>$topSellingId
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
            pv.gst_rate,
            pv.gst_amount,
            pv.price_with_tax,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,
            pv.created_at,
            pv.updated_at,

            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.status AS product_status,

            s.name AS size_name,
            s.status AS size_status,

            c.name AS color_name,
            c.hex_code AS color_hex_code,
            c.status AS color_status

         FROM product_variants pv

         LEFT JOIN products p
            ON p.id=pv.product_id

         LEFT JOIN sizes s
            ON s.id=pv.size_id

         LEFT JOIN colors c
            ON c.id=pv.color_id

         WHERE pv.id=:product_variant_id

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
    | PRODUCT CHECK
    |--------------------------------------------------------------------------
    */

    if(
        $variant['product_id']===null||
        $variant['product_name']===null
    ){
        $pdo->rollBack();

        sendResponse(
            false,
            'Product linked to this variant was not found.',
            [
                'product_variant_id'=>
                    $finalProductVariantId
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE CHECK
    |--------------------------------------------------------------------------
    */

    $duplicateStmt=$pdo->prepare(
        "SELECT
            id,
            product_variant_id,
            sort_order,
            status,
            created_by_admin_id

         FROM top_selling_variants

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
        $topSellingId,
        PDO::PARAM_INT
    );

    $duplicateStmt->execute();

    $duplicate=$duplicateStmt->fetch(PDO::FETCH_ASSOC);

    if($duplicate){

        $pdo->rollBack();

        sendResponse(
            false,
            'This product variant is already added to another top selling record.',
            [
                'existing_top_selling'=>[
                    'id'=>
                        (int)$duplicate['id'],

                    'product_variant_id'=>
                        (int)$duplicate['product_variant_id'],

                    'sort_order'=>
                        (int)$duplicate['sort_order'],

                    'status'=>
                        $duplicate['status'],

                    'created_by_admin_id'=>
                        $duplicate['created_by_admin_id']!==null
                            ?(int)$duplicate['created_by_admin_id']
                            :null
                ]
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK CHANGES
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
    | DISCOUNT CALCULATION
    |--------------------------------------------------------------------------
    */

    $priceWithTax=(float)$variant['price_with_tax'];
    $sellingPrice=(float)$variant['selling_price'];

    $calculatedDiscountAmount=max(
        0,
        round(
            $priceWithTax-$sellingPrice,
            2
        )
    );

    $calculatedDiscountPercentage=0.00;

    if(
        $priceWithTax>0&&
        $calculatedDiscountAmount>0
    ){
        $calculatedDiscountPercentage=round(
            (
                $calculatedDiscountAmount/
                $priceWithTax
            )*100,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NO CHANGES
    |--------------------------------------------------------------------------
    */

    if(
        !$productVariantChanged&&
        !$sortOrderChanged&&
        !$statusChanged
    ){

        $pdo->commit();

        sendResponse(
            true,
            'No changes were required.',
            [
                'top_selling'=>[
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

                'product'=>[
                    'id'=>
                        (int)$variant['product_id'],

                    'name'=>
                        $variant['product_name'],

                    'slug'=>
                        $variant['product_slug'],

                    'status'=>
                        $variant['product_status']
                ],

                'product_variant'=>[
                    'id'=>
                        (int)$variant['id'],

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
        "UPDATE top_selling_variants SET
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
        $topSellingId,
        PDO::PARAM_INT
    );

    $updateStmt->execute();

    /*
    |--------------------------------------------------------------------------
    | FETCH UPDATED
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

         FROM top_selling_variants

         WHERE id=:id

         LIMIT 1"
    );

    $updatedStmt->bindValue(
        ':id',
        $topSellingId,
        PDO::PARAM_INT
    );

    $updatedStmt->execute();

    $updated=$updatedStmt->fetch(PDO::FETCH_ASSOC);

    if(!$updated){

        $pdo->rollBack();

        sendResponse(
            false,
            'Unable to retrieve updated top selling record.',
            null,
            500
        );
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Top selling product updated successfully.',
        [
            'top_selling'=>[
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

            'product'=>[
                'id'=>
                    (int)$variant['product_id'],

                'name'=>
                    $variant['product_name'],

                'slug'=>
                    $variant['product_slug'],

                'description'=>
                    $variant['product_description'],

                'status'=>
                    $variant['product_status']
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

                'size'=>
                    $variant['size_id']!==null
                        ?[
                            'id'=>
                                (int)$variant['size_id'],

                            'name'=>
                                $variant['size_name'],

                            'status'=>
                                $variant['size_status']
                        ]
                        :null,

                'color'=>
                    $variant['color_id']!==null
                        ?[
                            'id'=>
                                (int)$variant['color_id'],

                            'name'=>
                                $variant['color_name'],

                            'hex_code'=>
                                $variant['color_hex_code'],

                            'status'=>
                                $variant['color_status']
                        ]
                        :null,

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

                    'calculated_discount_amount'=>
                        number_format(
                            $calculatedDiscountAmount,
                            2,
                            '.',
                            ''
                        ),

                    'calculated_discount_percentage'=>
                        number_format(
                            $calculatedDiscountPercentage,
                            2,
                            '.',
                            ''
                        ),

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

    if((string)$e->getCode()==='23000'){

        sendResponse(
            false,
            'This product variant is already added to top selling.',
            [
                'product_variant_id'=>
                    $newProductVariantId
            ],
            409
        );
    }

    sendResponse(
        false,
        'Unable to update top selling product.',
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
        'An unexpected error occurred while updating top selling product.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}