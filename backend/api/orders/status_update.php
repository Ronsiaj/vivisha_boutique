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
| ADMIN AUTH
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
    'order_id',
    'status',
    'order_status',
    'cancel_reason'
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
| ORDER ID
|--------------------------------------------------------------------------
*/

$idInput=trim(
    (string)($data['id']??'')
);

$orderIdInput=trim(
    (string)($data['order_id']??'')
);

if($idInput===''&&$orderIdInput===''){
    sendResponse(
        false,
        'id or order_id is required.',
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
    $orderIdInput!==''&&
    !preg_match('/^[1-9][0-9]*$/',$orderIdInput)
){
    sendResponse(
        false,
        'order_id must be a valid positive integer.',
        null,
        422
    );
}

if(
    $idInput!==''&&
    $orderIdInput!==''&&
    (int)$idInput!==(int)$orderIdInput
){
    sendResponse(
        false,
        'id and order_id cannot contain different values.',
        null,
        422
    );
}

$orderId=(int)(
    $orderIdInput!==''?$orderIdInput:$idInput
);

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$statusInput=trim(
    (string)($data['status']??'')
);

$orderStatusInput=trim(
    (string)($data['order_status']??'')
);

if($statusInput===''&&$orderStatusInput===''){
    sendResponse(
        false,
        'status or order_status is required.',
        null,
        422
    );
}

if(
    $statusInput!==''&&
    $orderStatusInput!==''&&
    strtolower($statusInput)!==strtolower($orderStatusInput)
){
    sendResponse(
        false,
        'status and order_status cannot contain different values.',
        null,
        422
    );
}

$newStatus=strtolower(
    $orderStatusInput!==''?
        $orderStatusInput:
        $statusInput
);

$allOrderStatuses=[
    'pending',
    'confirmed',
    'processing',
    'packed',
    'shipped',
    'out_for_delivery',
    'delivered',
    'cancelled',
    'partially_refunded',
    'refunded'
];

if(!in_array($newStatus,$allOrderStatuses,true)){
    sendResponse(
        false,
        'Invalid order status.',
        [
            'allowed_values'=>$allOrderStatuses
        ],
        422
    );
}

/*
 * Refund states must only come through refund APIs.
 */
if(
    in_array(
        $newStatus,
        ['partially_refunded','refunded'],
        true
    )
){
    sendResponse(
        false,
        'Refund statuses cannot be updated manually from the order status API. Use the refund API.',
        [
            'restricted_statuses'=>[
                'partially_refunded',
                'refunded'
            ]
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| CANCEL REASON
|--------------------------------------------------------------------------
*/

$cancelReason=trim(
    (string)($data['cancel_reason']??'')
);

if(mb_strlen($cancelReason)>500){
    sendResponse(
        false,
        'cancel_reason must not exceed 500 characters.',
        null,
        422
    );
}

if($newStatus==='cancelled'&&$cancelReason===''){
    sendResponse(
        false,
        'cancel_reason is required when cancelling an order.',
        null,
        422
    );
}

if(
    $newStatus!=='cancelled'&&
    array_key_exists('cancel_reason',$data)
){
    sendResponse(
        false,
        'cancel_reason can only be provided when status is cancelled.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function orderStatusFail(
    PDO $pdo,
    string $message,
    mixed $data=null,
    int $code=422
):never{

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        $message,
        $data,
        $code
    );

    exit;
}

date_default_timezone_set('Asia/Kolkata');

try{

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | LOCK ORDER
    |--------------------------------------------------------------------------
    */

    $orderStmt=$pdo->prepare(
        "SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.cart_id,

            o.subtotal,
            o.product_discount_amount,
            o.coupon_discount_amount,
            o.shipping_charge,
            o.cod_charge,
            o.tax_amount,
            o.grand_total,

            o.payment_method,
            o.payment_status,
            o.order_status,

            o.customer_note,
            o.cancel_reason,

            o.placed_at,
            o.confirmed_at,
            o.delivered_at,
            o.cancelled_at,
            o.created_at,
            o.updated_at,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email,
            u.status AS user_status

         FROM orders o

         INNER JOIN users u
            ON u.id=o.user_id

         WHERE o.id=:order_id

         LIMIT 1
         FOR UPDATE"
    );

    $orderStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $orderStmt->execute();

    $order=$orderStmt->fetch(PDO::FETCH_ASSOC);

    if(!$order){
        orderStatusFail(
            $pdo,
            'Order not found.',
            [
                'order_id'=>$orderId
            ],
            404
        );
    }

    $currentStatus=(string)$order['order_status'];
    $paymentStatus=(string)$order['payment_status'];
    $paymentMethod=$order['payment_method']!==null
        ?strtolower((string)$order['payment_method'])
        :null;

    /*
    |--------------------------------------------------------------------------
    | ALREADY SAME STATUS - IDEMPOTENCY
    |--------------------------------------------------------------------------
    |
    | Very important for delivered/cancelled because inventory
    | must never be adjusted twice.
    |--------------------------------------------------------------------------
    */

    if($currentStatus===$newStatus){

        $pdo->commit();

        sendResponse(
            true,
            'Order is already in the requested status.',
            [
                'order'=>[
                    'id'=>
                        (int)$order['id'],

                    'order_number'=>
                        $order['order_number'],

                    'payment_method'=>
                        $order['payment_method'],

                    'payment_status'=>
                        $paymentStatus,

                    'order_status'=>
                        $currentStatus,

                    'cancel_reason'=>
                        $order['cancel_reason'],

                    'confirmed_at'=>
                        $order['confirmed_at'],

                    'delivered_at'=>
                        $order['delivered_at'],

                    'cancelled_at'=>
                        $order['cancelled_at']
                ],

                'inventory_updated'=>false,
                'changed'=>false
            ],
            200
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TERMINAL / REFUND STATUS PROTECTION
    |--------------------------------------------------------------------------
    */

    if(
        in_array(
            $currentStatus,
            [
                'delivered',
                'cancelled',
                'partially_refunded',
                'refunded'
            ],
            true
        )
    ){
        orderStatusFail(
            $pdo,
            "Order status cannot be changed from {$currentStatus}.",
            [
                'current_status'=>$currentStatus,
                'requested_status'=>$newStatus
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VALID STATUS TRANSITIONS
    |--------------------------------------------------------------------------
    */

    $allowedTransitions=[
        'pending'=>[
            'confirmed',
            'cancelled'
        ],

        'confirmed'=>[
            'processing',
            'cancelled'
        ],

        'processing'=>[
            'packed',
            'cancelled'
        ],

        'packed'=>[
            'shipped',
            'cancelled'
        ],

        'shipped'=>[
            'out_for_delivery'
        ],

        'out_for_delivery'=>[
            'delivered'
        ]
    ];

    if(
        !isset($allowedTransitions[$currentStatus])||
        !in_array(
            $newStatus,
            $allowedTransitions[$currentStatus],
            true
        )
    ){
        orderStatusFail(
            $pdo,
            "Invalid order status transition from {$currentStatus} to {$newStatus}.",
            [
                'current_status'=>$currentStatus,

                'requested_status'=>$newStatus,

                'allowed_next_statuses'=>
                    $allowedTransitions[$currentStatus]??[]
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT VALIDATION FOR ORDER PROCESSING
    |--------------------------------------------------------------------------
    |
    | Online:
    | payment must be paid before order confirmation/process.
    |
    | COD:
    | payment can remain pending until cash is collected.
    |--------------------------------------------------------------------------
    */

    $fulfilmentStatuses=[
        'confirmed',
        'processing',
        'packed',
        'shipped',
        'out_for_delivery',
        'delivered'
    ];

    if(in_array($newStatus,$fulfilmentStatuses,true)){

        if($paymentMethod===null||$paymentMethod===''){

            orderStatusFail(
                $pdo,
                'Payment method has not been selected for this order.',
                [
                    'payment_method'=>null,
                    'payment_status'=>$paymentStatus
                ],
                409
            );
        }

        if($paymentMethod==='cod'){

            if(
                !in_array(
                    $paymentStatus,
                    ['pending','paid'],
                    true
                )
            ){
                orderStatusFail(
                    $pdo,
                    'COD order cannot proceed with the current payment status.',
                    [
                        'payment_method'=>$paymentMethod,
                        'payment_status'=>$paymentStatus
                    ],
                    409
                );
            }

        }else{

            if($paymentStatus!=='paid'){

                orderStatusFail(
                    $pdo,
                    'Online payment must be paid before this order can proceed.',
                    [
                        'payment_method'=>$paymentMethod,
                        'payment_status'=>$paymentStatus
                    ],
                    409
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD ORDER ITEMS
    |--------------------------------------------------------------------------
    |
    | Aggregate by variant to prevent duplicate variant lines from
    | causing incorrect stock updates.
    |--------------------------------------------------------------------------
    */

    $itemsStmt=$pdo->prepare(
        "SELECT
            variant_id,
            SUM(quantity) AS ordered_quantity

         FROM order_items

         WHERE order_id=:order_id
           AND variant_id IS NOT NULL

         GROUP BY variant_id

         ORDER BY variant_id ASC"
    );

    $itemsStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $itemsStmt->execute();

    $orderItems=$itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if(!$orderItems){

        orderStatusFail(
            $pdo,
            'No valid product variants were found for this order.',
            null,
            409
        );
    }

    $variantQuantityMap=[];

    foreach($orderItems as $item){

        $variantId=(int)$item['variant_id'];
        $quantity=(int)$item['ordered_quantity'];

        if($variantId<=0||$quantity<=0){
            orderStatusFail(
                $pdo,
                'Invalid order item quantity or variant.',
                [
                    'variant_id'=>$variantId,
                    'quantity'=>$quantity
                ],
                409
            );
        }

        $variantQuantityMap[$variantId]=$quantity;
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTORY PROCESSING
    |--------------------------------------------------------------------------
    |
    | Inventory changes only when entering:
    |
    | delivered
    | cancelled
    |--------------------------------------------------------------------------
    */

    $inventoryUpdated=false;
    $inventoryChanges=[];

    if(
        in_array(
            $newStatus,
            ['delivered','cancelled'],
            true
        )
    ){

        $variantIds=array_keys(
            $variantQuantityMap
        );

        $placeholders=[];

        foreach($variantIds as $index=>$variantId){
            $placeholders[]=':variant_'.$index;
        }

        $inSql=implode(',',$placeholders);

        /*
        |--------------------------------------------------------------------------
        | LOCK ALL VARIANTS
        |--------------------------------------------------------------------------
        */

        $variantStmt=$pdo->prepare(
            "SELECT
                id,
                product_id,
                sku,
                variant_name,
                stock_quantity,
                reserved_quantity,
                low_stock_limit,
                is_available

             FROM product_variants

             WHERE id IN ({$inSql})

             ORDER BY id ASC

             FOR UPDATE"
        );

        foreach($variantIds as $index=>$variantId){

            $variantStmt->bindValue(
                ':variant_'.$index,
                $variantId,
                PDO::PARAM_INT
            );
        }

        $variantStmt->execute();

        $variants=
            $variantStmt->fetchAll(PDO::FETCH_ASSOC);

        if(count($variants)!==count($variantIds)){

            $foundIds=array_map(
                static fn(array $variant):int=>
                    (int)$variant['id'],
                $variants
            );

            $missingIds=array_values(
                array_diff(
                    $variantIds,
                    $foundIds
                )
            );

            orderStatusFail(
                $pdo,
                'One or more product variants no longer exist.',
                [
                    'missing_variant_ids'=>$missingIds
                ],
                409
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE ALL STOCK FIRST
        |--------------------------------------------------------------------------
        |
        | Nothing gets changed until every variant passes validation.
        |--------------------------------------------------------------------------
        */

        foreach($variants as $variant){

            $variantId=(int)$variant['id'];

            $orderedQuantity=
                $variantQuantityMap[$variantId];

            $stockQuantity=
                (int)$variant['stock_quantity'];

            $reservedQuantity=
                (int)$variant['reserved_quantity'];

            /*
             * Both delivered and cancelled require reservation
             * to still exist.
             */
            if(
                $reservedQuantity<
                $orderedQuantity
            ){
                orderStatusFail(
                    $pdo,
                    'Reserved stock is lower than the ordered quantity.',
                    [
                        'variant_id'=>$variantId,
                        'sku'=>$variant['sku'],
                        'ordered_quantity'=>$orderedQuantity,
                        'stock_quantity'=>$stockQuantity,
                        'reserved_quantity'=>$reservedQuantity
                    ],
                    409
                );
            }

            /*
             * Delivery physically consumes stock.
             */
            if(
                $newStatus==='delivered'&&
                $stockQuantity<$orderedQuantity
            ){
                orderStatusFail(
                    $pdo,
                    'Insufficient stock quantity to complete delivery.',
                    [
                        'variant_id'=>$variantId,
                        'sku'=>$variant['sku'],
                        'ordered_quantity'=>$orderedQuantity,
                        'stock_quantity'=>$stockQuantity,
                        'reserved_quantity'=>$reservedQuantity
                    ],
                    409
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | APPLY INVENTORY CHANGES
        |--------------------------------------------------------------------------
        */

        foreach($variants as $variant){

            $variantId=(int)$variant['id'];

            $orderedQuantity=
                $variantQuantityMap[$variantId];

            $beforeStock=
                (int)$variant['stock_quantity'];

            $beforeReserved=
                (int)$variant['reserved_quantity'];

            /*
            |--------------------------------------------------------------------------
            | DELIVERED
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | stock = 20
            | reserved = 3
            | order qty = 2
            |
            | after delivery:
            | stock = 18
            | reserved = 1
            |--------------------------------------------------------------------------
            */

            if($newStatus==='delivered'){

                $stockUpdate=$pdo->prepare(
                    "UPDATE product_variants SET

                        stock_quantity=
                            stock_quantity-:stock_qty,

                        reserved_quantity=
                            reserved_quantity-:reserved_qty

                     WHERE id=:variant_id

                       AND stock_quantity>=:check_stock_qty

                       AND reserved_quantity>=:check_reserved_qty"
                );

                $stockUpdate->bindValue(
                    ':stock_qty',
                    $orderedQuantity,
                    PDO::PARAM_INT
                );

                $stockUpdate->bindValue(
                    ':reserved_qty',
                    $orderedQuantity,
                    PDO::PARAM_INT
                );

                $stockUpdate->bindValue(
                    ':variant_id',
                    $variantId,
                    PDO::PARAM_INT
                );

                $stockUpdate->bindValue(
                    ':check_stock_qty',
                    $orderedQuantity,
                    PDO::PARAM_INT
                );

                $stockUpdate->bindValue(
                    ':check_reserved_qty',
                    $orderedQuantity,
                    PDO::PARAM_INT
                );

                $stockUpdate->execute();

                if($stockUpdate->rowCount()!==1){

                    orderStatusFail(
                        $pdo,
                        'Unable to update stock for delivered order.',
                        [
                            'variant_id'=>$variantId
                        ],
                        409
                    );
                }

                $afterStock=
                    $beforeStock-$orderedQuantity;

                $afterReserved=
                    $beforeReserved-$orderedQuantity;
            }

            /*
            |--------------------------------------------------------------------------
            | CANCELLED
            |--------------------------------------------------------------------------
            |
            | Reservation is released.
            |
            | IMPORTANT:
            | stock_quantity is NOT increased because order reservation
            | did not reduce physical stock_quantity.
            |
            | Example:
            |
            | stock = 20
            | reserved = 3
            | cancelled qty = 2
            |
            | after cancel:
            | stock = 20
            | reserved = 1
            |--------------------------------------------------------------------------
            */

            else{

                $stockUpdate=$pdo->prepare(
                    "UPDATE product_variants SET

                        reserved_quantity=
                            reserved_quantity-:reserved_qty

                     WHERE id=:variant_id

                       AND reserved_quantity>=:check_reserved_qty"
                );

                $stockUpdate->bindValue(
                    ':reserved_qty',
                    $orderedQuantity,
                    PDO::PARAM_INT
                );

                $stockUpdate->bindValue(
                    ':variant_id',
                    $variantId,
                    PDO::PARAM_INT
                );

                $stockUpdate->bindValue(
                    ':check_reserved_qty',
                    $orderedQuantity,
                    PDO::PARAM_INT
                );

                $stockUpdate->execute();

                if($stockUpdate->rowCount()!==1){

                    orderStatusFail(
                        $pdo,
                        'Unable to release reserved stock for cancelled order.',
                        [
                            'variant_id'=>$variantId
                        ],
                        409
                    );
                }

                $afterStock=
                    $beforeStock;

                $afterReserved=
                    $beforeReserved-$orderedQuantity;
            }

            $availableBefore=max(
                0,
                $beforeStock-$beforeReserved
            );

            $availableAfter=max(
                0,
                $afterStock-$afterReserved
            );

            $lowStockLimit=
                (int)$variant['low_stock_limit'];

            if($availableAfter<=0){

                $stockStatus='out_of_stock';

            }elseif(
                $availableAfter<=$lowStockLimit
            ){

                $stockStatus='low_stock';

            }else{

                $stockStatus='in_stock';
            }

            $inventoryChanges[]=[
                'variant_id'=>$variantId,

                'product_id'=>
                    (int)$variant['product_id'],

                'sku'=>
                    $variant['sku'],

                'variant_name'=>
                    $variant['variant_name'],

                'ordered_quantity'=>
                    $orderedQuantity,

                'before'=>[
                    'stock_quantity'=>
                        $beforeStock,

                    'reserved_quantity'=>
                        $beforeReserved,

                    'available_quantity'=>
                        $availableBefore
                ],

                'after'=>[
                    'stock_quantity'=>
                        $afterStock,

                    'reserved_quantity'=>
                        $afterReserved,

                    'available_quantity'=>
                        $availableAfter,

                    'low_stock_limit'=>
                        $lowStockLimit,

                    'stock_status'=>
                        $stockStatus
                ]
            ];
        }

        $inventoryUpdated=true;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER STATUS
    |--------------------------------------------------------------------------
    */

    if($newStatus==='confirmed'){

        $updateOrder=$pdo->prepare(
            "UPDATE orders SET
                order_status='confirmed',
                confirmed_at=COALESCE(confirmed_at,NOW())

             WHERE id=:order_id
               AND order_status=:current_status"
        );

        $updateOrder->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $updateOrder->bindValue(
            ':current_status',
            $currentStatus,
            PDO::PARAM_STR
        );

    }elseif($newStatus==='delivered'){

        $updateOrder=$pdo->prepare(
            "UPDATE orders SET
                order_status='delivered',
                delivered_at=COALESCE(delivered_at,NOW())

             WHERE id=:order_id
               AND order_status=:current_status"
        );

        $updateOrder->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $updateOrder->bindValue(
            ':current_status',
            $currentStatus,
            PDO::PARAM_STR
        );

    }elseif($newStatus==='cancelled'){

        $updateOrder=$pdo->prepare(
            "UPDATE orders SET
                order_status='cancelled',
                cancel_reason=:cancel_reason,
                cancelled_at=COALESCE(cancelled_at,NOW())

             WHERE id=:order_id
               AND order_status=:current_status"
        );

        $updateOrder->bindValue(
            ':cancel_reason',
            $cancelReason,
            PDO::PARAM_STR
        );

        $updateOrder->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $updateOrder->bindValue(
            ':current_status',
            $currentStatus,
            PDO::PARAM_STR
        );

    }else{

        $updateOrder=$pdo->prepare(
            "UPDATE orders SET
                order_status=:new_status

             WHERE id=:order_id
               AND order_status=:current_status"
        );

        $updateOrder->bindValue(
            ':new_status',
            $newStatus,
            PDO::PARAM_STR
        );

        $updateOrder->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $updateOrder->bindValue(
            ':current_status',
            $currentStatus,
            PDO::PARAM_STR
        );
    }

    $updateOrder->execute();

    if($updateOrder->rowCount()!==1){

        orderStatusFail(
            $pdo,
            'Unable to update order status. The order may have been modified by another request.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH UPDATED ORDER
    |--------------------------------------------------------------------------
    */

    $updatedStmt=$pdo->prepare(
        "SELECT
            id,
            order_number,
            user_id,
            cart_id,

            subtotal,
            product_discount_amount,
            coupon_discount_amount,
            shipping_charge,
            cod_charge,
            tax_amount,
            grand_total,

            payment_method,
            payment_status,
            order_status,

            customer_note,
            cancel_reason,

            placed_at,
            confirmed_at,
            delivered_at,
            cancelled_at,
            created_at,
            updated_at

         FROM orders

         WHERE id=:order_id

         LIMIT 1"
    );

    $updatedStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $updatedStmt->execute();

    $updatedOrder=
        $updatedStmt->fetch(PDO::FETCH_ASSOC);

    if(!$updatedOrder){

        orderStatusFail(
            $pdo,
            'Unable to retrieve updated order.',
            null,
            500
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | REFUND REQUIRED FLAG
    |--------------------------------------------------------------------------
    |
    | If a paid order is cancelled, payment remains paid until
    | manual refund is actually completed.
    |--------------------------------------------------------------------------
    */

    $refundRequired=
        $newStatus==='cancelled'&&
        $paymentStatus==='paid';

    sendResponse(
        true,
        'Order status updated successfully.',
        [
            'order'=>[
                'id'=>
                    (int)$updatedOrder['id'],

                'order_number'=>
                    $updatedOrder['order_number'],

                'user_id'=>
                    (int)$updatedOrder['user_id'],

                'cart_id'=>
                    $updatedOrder['cart_id']!==null
                        ?(int)$updatedOrder['cart_id']
                        :null,

                'amounts'=>[
                    'subtotal'=>
                        $updatedOrder['subtotal'],

                    'product_discount_amount'=>
                        $updatedOrder['product_discount_amount'],

                    'coupon_discount_amount'=>
                        $updatedOrder['coupon_discount_amount'],

                    'shipping_charge'=>
                        $updatedOrder['shipping_charge'],

                    'cod_charge'=>
                        $updatedOrder['cod_charge'],

                    'tax_amount'=>
                        $updatedOrder['tax_amount'],

                    'grand_total'=>
                        $updatedOrder['grand_total']
                ],

                'payment_method'=>
                    $updatedOrder['payment_method'],

                'payment_status'=>
                    $updatedOrder['payment_status'],

                'previous_order_status'=>
                    $currentStatus,

                'order_status'=>
                    $updatedOrder['order_status'],

                'customer_note'=>
                    $updatedOrder['customer_note'],

                'cancel_reason'=>
                    $updatedOrder['cancel_reason'],

                'timeline'=>[
                    'placed_at'=>
                        $updatedOrder['placed_at'],

                    'confirmed_at'=>
                        $updatedOrder['confirmed_at'],

                    'delivered_at'=>
                        $updatedOrder['delivered_at'],

                    'cancelled_at'=>
                        $updatedOrder['cancelled_at'],

                    'created_at'=>
                        $updatedOrder['created_at'],

                    'updated_at'=>
                        $updatedOrder['updated_at']
                ]
            ],

            'inventory'=>[
                'updated'=>$inventoryUpdated,

                'action'=>
                    $newStatus==='delivered'
                        ?'stock_consumed_and_reservation_released'
                        :(
                            $newStatus==='cancelled'
                                ?'reservation_released'
                                :'none'
                        ),

                'variants'=>$inventoryChanges
            ],

            'refund'=>[
                'required'=>$refundRequired,

                'message'=>$refundRequired
                    ?'This cancelled order is already paid. Complete the refund through the refund API.'
                    :null
            ],

            'updated_by_admin_id'=>
                $adminId
        ],
        200
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to update order status.',
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
        'An unexpected error occurred while updating order status.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}