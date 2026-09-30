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

if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')===false){
    sendResponse(false,'Content-Type must be application/json.',null,415);
}

$data=json_decode(file_get_contents('php://input')?:'',true);

if(json_last_error()!==JSON_ERROR_NONE||!is_array($data)){
    sendResponse(false,'Invalid JSON body.',null,400);
}

$allowedFields=[
    'order_id',
    'refund_amount',
    'refund_method',
    'refund_reference',
    'reason',
    'admin_note',
    'customer_mobile'
];

foreach(array_keys($data) as $field){
    if(!in_array($field,$allowedFields,true)){
        sendResponse(false,"Invalid field: {$field}.",[
            'allowed_fields'=>$allowedFields
        ],422);
    }
}

$orderIdInput=trim((string)($data['order_id']??''));
$refundAmountInput=trim((string)($data['refund_amount']??''));
$refundMethod=strtolower(trim((string)($data['refund_method']??'')));
$refundReference=trim((string)($data['refund_reference']??''));
$reason=trim((string)($data['reason']??''));
$adminNote=trim((string)($data['admin_note']??''));
$customerMobile=trim((string)($data['customer_mobile']??''));

if(!preg_match('/^[1-9][0-9]*$/',$orderIdInput)){
    sendResponse(false,'order_id must be a valid positive integer.',null,422);
}

$orderId=(int)$orderIdInput;

if(
    $refundAmountInput===''||
    !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/',$refundAmountInput)
){
    sendResponse(
        false,
        'refund_amount must be a valid amount with maximum 2 decimal places.',
        null,
        422
    );
}

$refundAmount=(float)$refundAmountInput;

if($refundAmount<=0){
    sendResponse(false,'refund_amount must be greater than 0.',null,422);
}

if($refundAmount>9999999999.99){
    sendResponse(false,'refund_amount exceeds allowed limit.',null,422);
}

$allowedRefundMethods=[
    'upi',
    'bank_transfer',
    'cash',
    'razorpay',
    'other'
];

if(!in_array($refundMethod,$allowedRefundMethods,true)){
    sendResponse(false,'Invalid refund_method.',[
        'allowed_values'=>$allowedRefundMethods
    ],422);
}

if($reason===''){
    sendResponse(false,'reason is required.',null,422);
}

if(mb_strlen($reason)>500){
    sendResponse(false,'reason must not exceed 500 characters.',null,422);
}

if($adminNote!==''&&mb_strlen($adminNote)>1000){
    sendResponse(false,'admin_note must not exceed 1000 characters.',null,422);
}

if($refundReference!==''&&mb_strlen($refundReference)>150){
    sendResponse(false,'refund_reference must not exceed 150 characters.',null,422);
}

if(
    in_array(
        $refundMethod,
        ['upi','bank_transfer','razorpay'],
        true
    )&&
    $refundReference===''
){
    sendResponse(
        false,
        'refund_reference is required for this refund method.',
        null,
        422
    );
}

if(
    $customerMobile!==''&&
    !preg_match('/^[0-9+\-\s]{6,20}$/',$customerMobile)
){
    sendResponse(false,'Invalid customer_mobile.',null,422);
}

function refundFail(
    PDO $pdo,
    string $message,
    mixed $data=null,
    int $statusCode=422
):never{
    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        $message,
        $data,
        $statusCode
    );

    exit;
}

function amountToPaise(string|float|int $amount):int{
    $value=number_format((float)$amount,2,'.','');
    [$rupees,$paise]=explode('.',$value);

    return ((int)$rupees*100)+(int)$paise;
}

function paiseToAmount(int $paise):string{
    return number_format(
        $paise/100,
        2,
        '.',
        ''
    );
}

date_default_timezone_set('Asia/Kolkata');

try{

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    $orderStmt=$pdo->prepare(
        "SELECT
            o.id,
            o.order_number,
            o.user_id,
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
            o.placed_at,
            o.confirmed_at,
            o.delivered_at,
            o.cancelled_at,

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
        refundFail(
            $pdo,
            'Order not found.',
            null,
            404
        );
    }

    $userId=(int)$order['user_id'];

    /*
    |--------------------------------------------------------------------------
    | ORDER VALIDATION
    |--------------------------------------------------------------------------
    */

    if($order['payment_status']==='refunded'){
        refundFail(
            $pdo,
            'This order has already been fully refunded.',
            [
                'order_id'=>$orderId,
                'payment_status'=>$order['payment_status'],
                'order_status'=>$order['order_status']
            ],
            409
        );
    }

    if($order['order_status']==='refunded'){
        refundFail(
            $pdo,
            'This order is already marked as refunded.',
            [
                'order_id'=>$orderId,
                'order_status'=>'refunded'
            ],
            409
        );
    }

    if(!in_array(
        $order['payment_status'],
        ['paid','partially_refunded'],
        true
    )){
        refundFail(
            $pdo,
            'Refund can only be processed for a paid or partially refunded order.',
            [
                'payment_status'=>$order['payment_status'],
                'order_status'=>$order['order_status']
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET SUCCESSFUL PAYMENT
    |--------------------------------------------------------------------------
    */

    $paymentStmt=$pdo->prepare(
        "SELECT
            id,
            order_id,
            user_id,
            provider,
            attempt_no,
            razorpay_order_id,
            razorpay_payment_id,
            amount,
            amount_paise,
            currency,
            payment_method,
            status,
            gateway_status,
            paid_at

         FROM payments

         WHERE order_id=:order_id
           AND user_id=:user_id
           AND status IN(
                'paid',
                'partially_refunded'
           )

         ORDER BY paid_at DESC,id DESC

         LIMIT 1
         FOR UPDATE"
    );

    $paymentStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $paymentStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $paymentStmt->execute();

    $payment=$paymentStmt->fetch(PDO::FETCH_ASSOC);

    if(!$payment){
        refundFail(
            $pdo,
            'No refundable successful payment found for this order.',
            null,
            404
        );
    }

    $paymentId=(int)$payment['id'];

    /*
    |--------------------------------------------------------------------------
    | PENDING REFUND CHECK
    |--------------------------------------------------------------------------
    */

    $pendingStmt=$pdo->prepare(
        "SELECT
            id,
            refund_amount,
            refund_method,
            status

         FROM manual_refunds

         WHERE order_id=:order_id
           AND status IN(
                'pending',
                'processing'
           )

         ORDER BY id DESC

         LIMIT 1
         FOR UPDATE"
    );

    $pendingStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $pendingStmt->execute();

    $pendingRefund=$pendingStmt->fetch(PDO::FETCH_ASSOC);

    if($pendingRefund){
        refundFail(
            $pdo,
            'Another refund is already pending or processing for this order.',
            [
                'refund_id'=>
                    (int)$pendingRefund['id'],

                'refund_amount'=>
                    $pendingRefund['refund_amount'],

                'refund_method'=>
                    $pendingRefund['refund_method'],

                'status'=>
                    $pendingRefund['status']
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE REFERENCE
    |--------------------------------------------------------------------------
    */

    if($refundReference!==''){

        $referenceStmt=$pdo->prepare(
            "SELECT
                id,
                order_id,
                refund_amount,
                status

             FROM manual_refunds

             WHERE refund_reference=:refund_reference

             LIMIT 1"
        );

        $referenceStmt->bindValue(
            ':refund_reference',
            $refundReference,
            PDO::PARAM_STR
        );

        $referenceStmt->execute();

        $duplicate=$referenceStmt->fetch(PDO::FETCH_ASSOC);

        if($duplicate){
            refundFail(
                $pdo,
                'This refund reference has already been used.',
                [
                    'existing_refund_id'=>
                        (int)$duplicate['id'],

                    'existing_order_id'=>
                        (int)$duplicate['order_id'],

                    'existing_refund_amount'=>
                        $duplicate['refund_amount'],

                    'existing_status'=>
                        $duplicate['status']
                ],
                409
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIOUS SUCCESSFUL REFUNDS
    |--------------------------------------------------------------------------
    */

    $sumStmt=$pdo->prepare(
        "SELECT
            COALESCE(
                SUM(refund_amount),
                0
            ) AS total_refunded_amount,

            COUNT(*) AS total_refunds

         FROM manual_refunds

         WHERE order_id=:order_id
           AND payment_id=:payment_id
           AND status='refunded'"
    );

    $sumStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $sumStmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $sumStmt->execute();

    $refundSummary=$sumStmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | ORIGINAL PAYMENT
    |--------------------------------------------------------------------------
    */

    $paymentAmountPaise=
        (int)$payment['amount_paise'];

    if($paymentAmountPaise<=0){
        $paymentAmountPaise=amountToPaise(
            $payment['amount']
        );
    }

    if($paymentAmountPaise<=0){
        refundFail(
            $pdo,
            'Invalid original payment amount.',
            null,
            422
        );
    }

    $orderGrandTotalPaise=
        amountToPaise(
            $order['grand_total']
        );

    if(
        $orderGrandTotalPaise!==
        $paymentAmountPaise
    ){
        refundFail(
            $pdo,
            'Order grand total and successful payment amount do not match.',
            [
                'order_grand_total'=>
                    paiseToAmount(
                        $orderGrandTotalPaise
                    ),

                'payment_amount'=>
                    paiseToAmount(
                        $paymentAmountPaise
                    )
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REFUND CALCULATION
    |--------------------------------------------------------------------------
    */

    $previousRefundedPaise=
        amountToPaise(
            $refundSummary['total_refunded_amount']??0
        );

    if($previousRefundedPaise<0){
        $previousRefundedPaise=0;
    }

    if(
        $previousRefundedPaise>
        $paymentAmountPaise
    ){
        refundFail(
            $pdo,
            'Existing refunded amount exceeds original payment amount.',
            null,
            409
        );
    }

    $remainingBeforeRefundPaise=
        $paymentAmountPaise-
        $previousRefundedPaise;

    if($remainingBeforeRefundPaise<=0){
        refundFail(
            $pdo,
            'No refundable balance remains for this payment.',
            [
                'payment_amount'=>
                    paiseToAmount(
                        $paymentAmountPaise
                    ),

                'already_refunded_amount'=>
                    paiseToAmount(
                        $previousRefundedPaise
                    ),

                'remaining_refundable_amount'=>
                    '0.00'
            ],
            409
        );
    }

    $currentRefundPaise=
        amountToPaise(
            $refundAmount
        );

    if($currentRefundPaise<=0){
        refundFail(
            $pdo,
            'refund_amount must be greater than zero.',
            null,
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OVER REFUND BLOCK
    |--------------------------------------------------------------------------
    */

    if(
        $currentRefundPaise>
        $remainingBeforeRefundPaise
    ){
        refundFail(
            $pdo,
            'Refund amount exceeds the remaining refundable amount.',
            [
                'payment_amount'=>
                    paiseToAmount(
                        $paymentAmountPaise
                    ),

                'already_refunded_amount'=>
                    paiseToAmount(
                        $previousRefundedPaise
                    ),

                'remaining_refundable_amount'=>
                    paiseToAmount(
                        $remainingBeforeRefundPaise
                    ),

                'requested_refund_amount'=>
                    paiseToAmount(
                        $currentRefundPaise
                    )
            ],
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL CALCULATION
    |--------------------------------------------------------------------------
    */

    $totalRefundedPaise=
        $previousRefundedPaise+
        $currentRefundPaise;

    $remainingAfterRefundPaise=
        $paymentAmountPaise-
        $totalRefundedPaise;

    $isFullyRefunded=
        $totalRefundedPaise===
        $paymentAmountPaise;

    /*
     * Current individual refund.
     */
    $refundType=
        $currentRefundPaise===
        $remainingBeforeRefundPaise
            ?'full'
            :'partial';

    /*
     * Overall payment/order status.
     */
    $newPaymentStatus=
        $isFullyRefunded
            ?'refunded'
            :'partially_refunded';

    $newOrderStatus=
        $isFullyRefunded
            ?'refunded'
            :'partially_refunded';

    /*
    |--------------------------------------------------------------------------
    | MOBILE
    |--------------------------------------------------------------------------
    */

    if($customerMobile===''){
        $customerMobile=trim(
            (string)($order['user_mobile']??'')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT REFUND
    |--------------------------------------------------------------------------
    |
    | This API means manual refund has already been completed by admin.
    |--------------------------------------------------------------------------
    */

    $insertRefund=$pdo->prepare(
        "INSERT INTO manual_refunds(
            order_id,
            payment_id,
            user_id,
            refund_type,
            refund_amount,
            refund_method,
            refund_reference,
            reason,
            admin_note,
            customer_mobile,
            contact_status,
            status,
            created_by_admin_id,
            handled_by_admin_id,
            contacted_at,
            refunded_at

         )VALUES(
            :order_id,
            :payment_id,
            :user_id,
            :refund_type,
            :refund_amount,
            :refund_method,
            :refund_reference,
            :reason,
            :admin_note,
            :customer_mobile,
            'confirmed',
            'refunded',
            :created_by_admin_id,
            :handled_by_admin_id,
            NOW(),
            NOW()
         )"
    );

    $insertRefund->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $insertRefund->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $insertRefund->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $insertRefund->bindValue(
        ':refund_type',
        $refundType,
        PDO::PARAM_STR
    );

    $insertRefund->bindValue(
        ':refund_amount',
        paiseToAmount(
            $currentRefundPaise
        ),
        PDO::PARAM_STR
    );

    $insertRefund->bindValue(
        ':refund_method',
        $refundMethod,
        PDO::PARAM_STR
    );

    if($refundReference===''){
        $insertRefund->bindValue(
            ':refund_reference',
            null,
            PDO::PARAM_NULL
        );
    }else{
        $insertRefund->bindValue(
            ':refund_reference',
            $refundReference,
            PDO::PARAM_STR
        );
    }

    $insertRefund->bindValue(
        ':reason',
        $reason,
        PDO::PARAM_STR
    );

    if($adminNote===''){
        $insertRefund->bindValue(
            ':admin_note',
            null,
            PDO::PARAM_NULL
        );
    }else{
        $insertRefund->bindValue(
            ':admin_note',
            $adminNote,
            PDO::PARAM_STR
        );
    }

    if($customerMobile===''){
        $insertRefund->bindValue(
            ':customer_mobile',
            null,
            PDO::PARAM_NULL
        );
    }else{
        $insertRefund->bindValue(
            ':customer_mobile',
            $customerMobile,
            PDO::PARAM_STR
        );
    }

    $insertRefund->bindValue(
        ':created_by_admin_id',
        $adminId,
        PDO::PARAM_INT
    );

    $insertRefund->bindValue(
        ':handled_by_admin_id',
        $adminId,
        PDO::PARAM_INT
    );

    $insertRefund->execute();

    $refundId=(int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT
    |--------------------------------------------------------------------------
    */

    $oldPaymentStatus=(string)$payment['status'];

    $paymentUpdate=$pdo->prepare(
        "UPDATE payments SET
            status=:new_status

         WHERE id=:payment_id
           AND order_id=:order_id
           AND user_id=:user_id
           AND status IN(
                'paid',
                'partially_refunded'
           )"
    );

    $paymentUpdate->bindValue(
        ':new_status',
        $newPaymentStatus,
        PDO::PARAM_STR
    );

    $paymentUpdate->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $paymentUpdate->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $paymentUpdate->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $paymentUpdate->execute();

    if($paymentUpdate->rowCount()!==1){
        refundFail(
            $pdo,
            'Unable to update payment refund status.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER
    |--------------------------------------------------------------------------
    |
    | BOTH:
    |
    | payment_status
    | order_status
    |
    | are updated.
    |--------------------------------------------------------------------------
    */

    $oldOrderPaymentStatus=
        (string)$order['payment_status'];

    $oldOrderStatus=
        (string)$order['order_status'];

    $orderUpdate=$pdo->prepare(
        "UPDATE orders SET
            payment_status=:payment_status,
            order_status=:order_status

         WHERE id=:order_id
           AND user_id=:user_id
           AND payment_status IN(
                'paid',
                'partially_refunded'
           )
           AND order_status<>'refunded'"
    );

    $orderUpdate->bindValue(
        ':payment_status',
        $newPaymentStatus,
        PDO::PARAM_STR
    );

    $orderUpdate->bindValue(
        ':order_status',
        $newOrderStatus,
        PDO::PARAM_STR
    );

    $orderUpdate->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $orderUpdate->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $orderUpdate->execute();

    if($orderUpdate->rowCount()!==1){
        refundFail(
            $pdo,
            'Unable to update order refund status.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS HISTORY
    |--------------------------------------------------------------------------
    */

    $historyStmt=$pdo->prepare(
        "INSERT INTO payment_status_history(
            payment_id,
            order_id,
            from_status,
            to_status,
            source,
            message

         )VALUES(
            :payment_id,
            :order_id,
            :from_status,
            :to_status,
            'admin',
            :message
         )"
    );

    $historyMessage=
        'Manual refund completed by admin. '.
        'Refund amount: ₹'.
        paiseToAmount($currentRefundPaise).
        '. Total refunded: ₹'.
        paiseToAmount($totalRefundedPaise).
        '. Remaining refundable: ₹'.
        paiseToAmount($remainingAfterRefundPaise).
        '. Order status: '.
        $newOrderStatus.
        '.';

    $historyStmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $historyStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $historyStmt->bindValue(
        ':from_status',
        $oldPaymentStatus,
        PDO::PARAM_STR
    );

    $historyStmt->bindValue(
        ':to_status',
        $newPaymentStatus,
        PDO::PARAM_STR
    );

    $historyStmt->bindValue(
        ':message',
        mb_substr(
            $historyMessage,
            0,
            500
        ),
        PDO::PARAM_STR
    );

    $historyStmt->execute();

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    sendResponse(
        true,
        $isFullyRefunded
            ?'Full refund completed successfully.'
            :'Partial refund completed successfully.',
        [
            'refund'=>[
                'id'=>$refundId,

                'refund_type'=>
                    $refundType,

                'status'=>'refunded',

                'refund_method'=>
                    $refundMethod,

                'refund_reference'=>
                    $refundReference!==''?$refundReference:null,

                'reason'=>
                    $reason,

                'admin_note'=>
                    $adminNote!==''?$adminNote:null,

                'customer_mobile'=>
                    $customerMobile!==''?$customerMobile:null,

                'refund_amount'=>
                    paiseToAmount(
                        $currentRefundPaise
                    ),

                'contact_status'=>'confirmed',

                'created_by_admin_id'=>
                    $adminId,

                'handled_by_admin_id'=>
                    $adminId
            ],

            'refund_calculation'=>[
                'original_payment_amount'=>
                    paiseToAmount(
                        $paymentAmountPaise
                    ),

                'previously_refunded_amount'=>
                    paiseToAmount(
                        $previousRefundedPaise
                    ),

                'current_refund_amount'=>
                    paiseToAmount(
                        $currentRefundPaise
                    ),

                'total_refunded_amount'=>
                    paiseToAmount(
                        $totalRefundedPaise
                    ),

                'remaining_refundable_amount'=>
                    paiseToAmount(
                        $remainingAfterRefundPaise
                    ),

                'fully_refunded'=>
                    $isFullyRefunded
            ],

            'payment'=>[
                'id'=>$paymentId,

                'order_id'=>$orderId,

                'provider'=>
                    $payment['provider'],

                'payment_method'=>
                    $payment['payment_method'],

                'razorpay_order_id'=>
                    $payment['razorpay_order_id'],

                'razorpay_payment_id'=>
                    $payment['razorpay_payment_id'],

                'amount'=>
                    paiseToAmount(
                        $paymentAmountPaise
                    ),

                'previous_status'=>
                    $oldPaymentStatus,

                'status'=>
                    $newPaymentStatus
            ],

            'order'=>[
                'id'=>$orderId,

                'order_number'=>
                    $order['order_number'],

                'user_id'=>$userId,

                'grand_total'=>
                    $order['grand_total'],

                'payment_method'=>
                    $order['payment_method'],

                'previous_payment_status'=>
                    $oldOrderPaymentStatus,

                'payment_status'=>
                    $newPaymentStatus,

                'previous_order_status'=>
                    $oldOrderStatus,

                'order_status'=>
                    $newOrderStatus
            ],

            'customer'=>[
                'name'=>$order['user_name'],
                'mobile'=>$order['user_mobile'],
                'email'=>$order['user_email']
            ]
        ],
        201
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to process refund.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );

}catch(Throwable $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'An unexpected error occurred while processing refund.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );
}