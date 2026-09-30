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

$data=json_decode(
    $rawBody?:'',
    true
);

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

$allowedFields=[
    'id',
    'refund_id',
    'refund_amount',
    'refund_method',
    'refund_reference',
    'reason',
    'admin_note',
    'customer_mobile',
    'contact_status',
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

$idInput=trim(
    (string)($data['id']??'')
);

$refundIdInput=trim(
    (string)($data['refund_id']??'')
);

if($idInput===''&&$refundIdInput===''){
    sendResponse(
        false,
        'id or refund_id is required.',
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
    $refundIdInput!==''&&
    !preg_match('/^[1-9][0-9]*$/',$refundIdInput)
){
    sendResponse(
        false,
        'refund_id must be a valid positive integer.',
        null,
        422
    );
}

if(
    $idInput!==''&&
    $refundIdInput!==''&&
    (int)$idInput!==(int)$refundIdInput
){
    sendResponse(
        false,
        'id and refund_id cannot contain different values.',
        null,
        422
    );
}

$refundId=(int)(
    $refundIdInput!==''?$refundIdInput:$idInput
);

$updateFields=array_diff(
    array_keys($data),
    ['id','refund_id']
);

if(!$updateFields){
    sendResponse(
        false,
        'At least one field must be provided for update.',
        null,
        422
    );
}

function refundUpdateFail(
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

function refundUpdateAmountToPaise(
    string|float|int $amount
):int{
    $formatted=number_format(
        (float)$amount,
        2,
        '.',
        ''
    );

    [$rupees,$paise]=explode('.',$formatted);

    return ((int)$rupees*100)+(int)$paise;
}

function refundUpdatePaiseToAmount(
    int $paise
):string{
    return number_format(
        $paise/100,
        2,
        '.',
        ''
    );
}

/*
|--------------------------------------------------------------------------
| VALIDATE PROVIDED FIELDS
|--------------------------------------------------------------------------
*/

if(array_key_exists('refund_amount',$data)){

    if(
        is_array($data['refund_amount'])||
        is_object($data['refund_amount'])
    ){
        sendResponse(
            false,
            'refund_amount must be a valid amount.',
            null,
            422
        );
    }

    $refundAmountInput=trim(
        (string)$data['refund_amount']
    );

    if(
        $refundAmountInput===''||
        !preg_match(
            '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/',
            $refundAmountInput
        )
    ){
        sendResponse(
            false,
            'refund_amount must be a valid amount with maximum 2 decimal places.',
            null,
            422
        );
    }

    if((float)$refundAmountInput<=0){
        sendResponse(
            false,
            'refund_amount must be greater than zero.',
            null,
            422
        );
    }

    if((float)$refundAmountInput>9999999999.99){
        sendResponse(
            false,
            'refund_amount exceeds allowed limit.',
            null,
            422
        );
    }
}

$allowedRefundMethods=[
    'upi',
    'bank_transfer',
    'cash',
    'razorpay',
    'other'
];

if(array_key_exists('refund_method',$data)){

    if(
        is_array($data['refund_method'])||
        is_object($data['refund_method'])
    ){
        sendResponse(
            false,
            'Invalid refund_method.',
            null,
            422
        );
    }

    $method=strtolower(
        trim((string)$data['refund_method'])
    );

    if(!in_array($method,$allowedRefundMethods,true)){
        sendResponse(
            false,
            'Invalid refund_method.',
            [
                'allowed_values'=>$allowedRefundMethods
            ],
            422
        );
    }
}

$allowedContactStatuses=[
    'not_contacted',
    'contacted',
    'no_response',
    'confirmed'
];

if(array_key_exists('contact_status',$data)){

    $contactStatusInput=strtolower(
        trim((string)$data['contact_status'])
    );

    if(
        !in_array(
            $contactStatusInput,
            $allowedContactStatuses,
            true
        )
    ){
        sendResponse(
            false,
            'Invalid contact_status.',
            [
                'allowed_values'=>$allowedContactStatuses
            ],
            422
        );
    }
}

$allowedStatuses=[
    'pending',
    'processing',
    'refunded',
    'rejected',
    'cancelled'
];

if(array_key_exists('status',$data)){

    $statusInput=strtolower(
        trim((string)$data['status'])
    );

    if(!in_array($statusInput,$allowedStatuses,true)){
        sendResponse(
            false,
            'Invalid status.',
            [
                'allowed_values'=>$allowedStatuses
            ],
            422
        );
    }
}

if(array_key_exists('refund_reference',$data)){

    if(
        is_array($data['refund_reference'])||
        is_object($data['refund_reference'])
    ){
        sendResponse(
            false,
            'Invalid refund_reference.',
            null,
            422
        );
    }

    if(
        mb_strlen(
            trim((string)$data['refund_reference'])
        )>150
    ){
        sendResponse(
            false,
            'refund_reference must not exceed 150 characters.',
            null,
            422
        );
    }
}

if(array_key_exists('reason',$data)){

    if(
        is_array($data['reason'])||
        is_object($data['reason'])
    ){
        sendResponse(
            false,
            'Invalid reason.',
            null,
            422
        );
    }

    $reasonInput=trim(
        (string)$data['reason']
    );

    if($reasonInput===''){
        sendResponse(
            false,
            'reason cannot be empty.',
            null,
            422
        );
    }

    if(mb_strlen($reasonInput)>500){
        sendResponse(
            false,
            'reason must not exceed 500 characters.',
            null,
            422
        );
    }
}

if(array_key_exists('admin_note',$data)){

    if(
        is_array($data['admin_note'])||
        is_object($data['admin_note'])
    ){
        sendResponse(
            false,
            'Invalid admin_note.',
            null,
            422
        );
    }

    if(
        mb_strlen(
            trim((string)$data['admin_note'])
        )>1000
    ){
        sendResponse(
            false,
            'admin_note must not exceed 1000 characters.',
            null,
            422
        );
    }
}

if(array_key_exists('customer_mobile',$data)){

    if(
        is_array($data['customer_mobile'])||
        is_object($data['customer_mobile'])
    ){
        sendResponse(
            false,
            'Invalid customer_mobile.',
            null,
            422
        );
    }

    $mobileInput=trim(
        (string)$data['customer_mobile']
    );

    if(
        $mobileInput!==''&&
        !preg_match(
            '/^[0-9+\-\s]{6,20}$/',
            $mobileInput
        )
    ){
        sendResponse(
            false,
            'Invalid customer_mobile.',
            null,
            422
        );
    }
}

date_default_timezone_set('Asia/Kolkata');

try{

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | LOCK REFUND
    |--------------------------------------------------------------------------
    */

    $refundStmt=$pdo->prepare(
        "SELECT
            id,
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
            processing_at,
            refunded_at,
            rejected_at,
            cancelled_at,
            created_at,
            updated_at

         FROM manual_refunds

         WHERE id=:refund_id

         LIMIT 1
         FOR UPDATE"
    );

    $refundStmt->bindValue(
        ':refund_id',
        $refundId,
        PDO::PARAM_INT
    );

    $refundStmt->execute();

    $refund=$refundStmt->fetch(PDO::FETCH_ASSOC);

    if(!$refund){
        refundUpdateFail(
            $pdo,
            'Refund not found.',
            null,
            404
        );
    }

    if($refund['payment_id']===null){
        refundUpdateFail(
            $pdo,
            'Refund is not linked to a payment.',
            null,
            409
        );
    }

    $orderId=(int)$refund['order_id'];
    $paymentId=(int)$refund['payment_id'];
    $userId=(int)$refund['user_id'];

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
            o.grand_total,
            o.payment_method,
            o.payment_status,
            o.order_status,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email

         FROM orders o

         INNER JOIN users u
            ON u.id=o.user_id

         WHERE o.id=:order_id
           AND o.user_id=:user_id

         LIMIT 1
         FOR UPDATE"
    );

    $orderStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $orderStmt->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $orderStmt->execute();

    $order=$orderStmt->fetch(PDO::FETCH_ASSOC);

    if(!$order){
        refundUpdateFail(
            $pdo,
            'Order linked to this refund was not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOCK PAYMENT
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

         WHERE id=:payment_id
           AND order_id=:order_id
           AND user_id=:user_id

         LIMIT 1
         FOR UPDATE"
    );

    $paymentStmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
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
        refundUpdateFail(
            $pdo,
            'Payment linked to this refund was not found.',
            null,
            404
        );
    }

    if(
        !in_array(
            $payment['status'],
            [
                'paid',
                'partially_refunded',
                'refunded'
            ],
            true
        )
    ){
        refundUpdateFail(
            $pdo,
            'This payment is not eligible for refund update.',
            [
                'payment_status'=>$payment['status']
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS TRANSITION
    |--------------------------------------------------------------------------
    */

    $oldRefundStatus=(string)$refund['status'];

    $newRefundStatus=array_key_exists('status',$data)
        ?strtolower(trim((string)$data['status']))
        :$oldRefundStatus;

    $allowedTransitions=[
        'pending'=>[
            'pending',
            'processing',
            'refunded',
            'rejected',
            'cancelled'
        ],
        'processing'=>[
            'processing',
            'refunded',
            'rejected',
            'cancelled'
        ],
        'refunded'=>[
            'refunded'
        ],
        'rejected'=>[
            'rejected'
        ],
        'cancelled'=>[
            'cancelled'
        ]
    ];

    if(
        !isset($allowedTransitions[$oldRefundStatus])||
        !in_array(
            $newRefundStatus,
            $allowedTransitions[$oldRefundStatus],
            true
        )
    ){
        refundUpdateFail(
            $pdo,
            "Invalid refund status transition from {$oldRefundStatus} to {$newRefundStatus}.",
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TERMINAL FINANCIAL FIELDS
    |--------------------------------------------------------------------------
    |
    | Once refunded/rejected/cancelled:
    | amount/method cannot be financially rewritten.
    |--------------------------------------------------------------------------
    */

    if(
        in_array(
            $oldRefundStatus,
            ['refunded','rejected','cancelled'],
            true
        )
    ){
        if(array_key_exists('refund_amount',$data)){

            $providedAmount=number_format(
                (float)$data['refund_amount'],
                2,
                '.',
                ''
            );

            $existingAmount=number_format(
                (float)$refund['refund_amount'],
                2,
                '.',
                ''
            );

            if($providedAmount!==$existingAmount){
                refundUpdateFail(
                    $pdo,
                    'refund_amount cannot be changed after the refund reaches a terminal status.',
                    null,
                    409
                );
            }
        }

        if(array_key_exists('refund_method',$data)){

            $providedMethod=strtolower(
                trim((string)$data['refund_method'])
            );

            if($providedMethod!==$refund['refund_method']){
                refundUpdateFail(
                    $pdo,
                    'refund_method cannot be changed after the refund reaches a terminal status.',
                    null,
                    409
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FINAL VALUES
    |--------------------------------------------------------------------------
    */

    $finalRefundAmount=array_key_exists(
        'refund_amount',
        $data
    )
        ?number_format(
            (float)$data['refund_amount'],
            2,
            '.',
            ''
        )
        :number_format(
            (float)$refund['refund_amount'],
            2,
            '.',
            ''
        );

    $finalRefundMethod=array_key_exists(
        'refund_method',
        $data
    )
        ?strtolower(
            trim((string)$data['refund_method'])
        )
        :(string)$refund['refund_method'];

    $finalRefundReference=array_key_exists(
        'refund_reference',
        $data
    )
        ?trim((string)$data['refund_reference'])
        :trim((string)($refund['refund_reference']??''));

    $finalReason=array_key_exists(
        'reason',
        $data
    )
        ?trim((string)$data['reason'])
        :(string)$refund['reason'];

    $finalAdminNote=array_key_exists(
        'admin_note',
        $data
    )
        ?trim((string)$data['admin_note'])
        :trim((string)($refund['admin_note']??''));

    $finalCustomerMobile=array_key_exists(
        'customer_mobile',
        $data
    )
        ?trim((string)$data['customer_mobile'])
        :trim((string)($refund['customer_mobile']??''));

    if($finalCustomerMobile===''){
        $finalCustomerMobile=trim(
            (string)($order['user_mobile']??'')
        );
    }

    $finalContactStatus=array_key_exists(
        'contact_status',
        $data
    )
        ?strtolower(
            trim((string)$data['contact_status'])
        )
        :(string)$refund['contact_status'];

    /*
     * Completed manual refund means customer contact confirmed.
     */
    if($newRefundStatus==='refunded'){
        $finalContactStatus='confirmed';
    }

    if($finalReason===''){
        refundUpdateFail(
            $pdo,
            'reason is required.',
            null,
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REFERENCE REQUIRED FOR FINAL REFUND
    |--------------------------------------------------------------------------
    */

    if(
        $newRefundStatus==='refunded'&&
        in_array(
            $finalRefundMethod,
            [
                'upi',
                'bank_transfer',
                'razorpay'
            ],
            true
        )&&
        $finalRefundReference===''
    ){
        refundUpdateFail(
            $pdo,
            'refund_reference is required before marking this refund as refunded.',
            null,
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE REFUND REFERENCE
    |--------------------------------------------------------------------------
    */

    if($finalRefundReference!==''){

        $duplicateStmt=$pdo->prepare(
            "SELECT
                id,
                order_id,
                refund_reference,
                status

             FROM manual_refunds

             WHERE refund_reference=:refund_reference
               AND id<>:refund_id

             LIMIT 1"
        );

        $duplicateStmt->bindValue(
            ':refund_reference',
            $finalRefundReference,
            PDO::PARAM_STR
        );

        $duplicateStmt->bindValue(
            ':refund_id',
            $refundId,
            PDO::PARAM_INT
        );

        $duplicateStmt->execute();

        $duplicate=$duplicateStmt->fetch(PDO::FETCH_ASSOC);

        if($duplicate){
            refundUpdateFail(
                $pdo,
                'This refund reference has already been used.',
                [
                    'existing_refund_id'=>
                        (int)$duplicate['id'],

                    'existing_order_id'=>
                        (int)$duplicate['order_id'],

                    'existing_status'=>
                        $duplicate['status']
                ],
                409
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT AMOUNT
    |--------------------------------------------------------------------------
    */

    $paymentAmountPaise=
        (int)$payment['amount_paise'];

    if($paymentAmountPaise<=0){
        $paymentAmountPaise=
            refundUpdateAmountToPaise(
                $payment['amount']
            );
    }

    if($paymentAmountPaise<=0){
        refundUpdateFail(
            $pdo,
            'Invalid original payment amount.',
            null,
            409
        );
    }

    $orderGrandTotalPaise=
        refundUpdateAmountToPaise(
            $order['grand_total']
        );

    if(
        $paymentAmountPaise!==
        $orderGrandTotalPaise
    ){
        refundUpdateFail(
            $pdo,
            'Order grand total and payment amount do not match.',
            [
                'order_grand_total'=>
                    refundUpdatePaiseToAmount(
                        $orderGrandTotalPaise
                    ),

                'payment_amount'=>
                    refundUpdatePaiseToAmount(
                        $paymentAmountPaise
                    )
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIOUS COMPLETED REFUNDS
    |--------------------------------------------------------------------------
    |
    | Current refund is excluded.
    |--------------------------------------------------------------------------
    */

    $previousStmt=$pdo->prepare(
        "SELECT
            COALESCE(
                SUM(refund_amount),
                0
            ) AS total_refunded_amount,

            COUNT(*) AS completed_refund_count

         FROM manual_refunds

         WHERE order_id=:order_id
           AND payment_id=:payment_id
           AND status='refunded'
           AND id<>:refund_id"
    );

    $previousStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $previousStmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $previousStmt->bindValue(
        ':refund_id',
        $refundId,
        PDO::PARAM_INT
    );

    $previousStmt->execute();

    $previous=$previousStmt->fetch(PDO::FETCH_ASSOC);

    $previousRefundedPaise=
        refundUpdateAmountToPaise(
            $previous['total_refunded_amount']??0
        );

    if(
        $previousRefundedPaise>
        $paymentAmountPaise
    ){
        refundUpdateFail(
            $pdo,
            'Existing refunded amount exceeds original payment amount.',
            null,
            409
        );
    }

    $remainingBeforeCurrent=
        $paymentAmountPaise-
        $previousRefundedPaise;

    if($remainingBeforeCurrent<=0){
        refundUpdateFail(
            $pdo,
            'No refundable balance remains for this payment.',
            [
                'payment_amount'=>
                    refundUpdatePaiseToAmount(
                        $paymentAmountPaise
                    ),

                'already_refunded_amount'=>
                    refundUpdatePaiseToAmount(
                        $previousRefundedPaise
                    ),

                'remaining_refundable_amount'=>'0.00'
            ],
            409
        );
    }

    $currentRefundPaise=
        refundUpdateAmountToPaise(
            $finalRefundAmount
        );

    if($currentRefundPaise<=0){
        refundUpdateFail(
            $pdo,
            'refund_amount must be greater than zero.',
            null,
            422
        );
    }

    if(
        $currentRefundPaise>
        $remainingBeforeCurrent
    ){
        refundUpdateFail(
            $pdo,
            'Refund amount exceeds the remaining refundable amount.',
            [
                'original_payment_amount'=>
                    refundUpdatePaiseToAmount(
                        $paymentAmountPaise
                    ),

                'previously_refunded_amount'=>
                    refundUpdatePaiseToAmount(
                        $previousRefundedPaise
                    ),

                'remaining_refundable_amount'=>
                    refundUpdatePaiseToAmount(
                        $remainingBeforeCurrent
                    ),

                'requested_refund_amount'=>
                    refundUpdatePaiseToAmount(
                        $currentRefundPaise
                    )
            ],
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REFUND TYPE
    |--------------------------------------------------------------------------
    |
    | If current refund completes all remaining balance:
    | full
    |
    | Otherwise:
    | partial
    |--------------------------------------------------------------------------
    */

    $refundType=
        $currentRefundPaise===$remainingBeforeCurrent
            ?'full'
            :'partial';

    /*
    |--------------------------------------------------------------------------
    | CALCULATE SUCCESSFUL TOTAL
    |--------------------------------------------------------------------------
    */

    if($newRefundStatus==='refunded'){

        $totalRefundedAfterPaise=
            $previousRefundedPaise+
            $currentRefundPaise;

    }else{

        $totalRefundedAfterPaise=
            $previousRefundedPaise;
    }

    if(
        $totalRefundedAfterPaise>
        $paymentAmountPaise
    ){
        refundUpdateFail(
            $pdo,
            'Calculated refunded amount exceeds original payment.',
            null,
            409
        );
    }

    $remainingAfterPaise=
        $paymentAmountPaise-
        $totalRefundedAfterPaise;

    $isFullyRefunded=
        $totalRefundedAfterPaise===
        $paymentAmountPaise;

    /*
    |--------------------------------------------------------------------------
    | TIMESTAMPS
    |--------------------------------------------------------------------------
    */

    $now=date('Y-m-d H:i:s');

    $contactedAt=$refund['contacted_at'];
    $processingAt=$refund['processing_at'];
    $refundedAt=$refund['refunded_at'];
    $rejectedAt=$refund['rejected_at'];
    $cancelledAt=$refund['cancelled_at'];

    if(
        $finalContactStatus!=='not_contacted'&&
        $contactedAt===null
    ){
        $contactedAt=$now;
    }

    if(
        $newRefundStatus==='processing'&&
        $processingAt===null
    ){
        $processingAt=$now;
    }

    if(
        $newRefundStatus==='refunded'&&
        $refundedAt===null
    ){
        $refundedAt=$now;
    }

    if(
        $newRefundStatus==='rejected'&&
        $rejectedAt===null
    ){
        $rejectedAt=$now;
    }

    if(
        $newRefundStatus==='cancelled'&&
        $cancelledAt===null
    ){
        $cancelledAt=$now;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE REFUND
    |--------------------------------------------------------------------------
    */

    $updateRefund=$pdo->prepare(
        "UPDATE manual_refunds SET
            refund_type=:refund_type,
            refund_amount=:refund_amount,
            refund_method=:refund_method,
            refund_reference=:refund_reference,
            reason=:reason,
            admin_note=:admin_note,
            customer_mobile=:customer_mobile,
            contact_status=:contact_status,
            status=:status,
            handled_by_admin_id=:handled_by_admin_id,
            contacted_at=:contacted_at,
            processing_at=:processing_at,
            refunded_at=:refunded_at,
            rejected_at=:rejected_at,
            cancelled_at=:cancelled_at

         WHERE id=:refund_id"
    );

    $updateRefund->bindValue(
        ':refund_type',
        $refundType,
        PDO::PARAM_STR
    );

    $updateRefund->bindValue(
        ':refund_amount',
        refundUpdatePaiseToAmount(
            $currentRefundPaise
        ),
        PDO::PARAM_STR
    );

    $updateRefund->bindValue(
        ':refund_method',
        $finalRefundMethod,
        PDO::PARAM_STR
    );

    $updateRefund->bindValue(
        ':refund_reference',
        $finalRefundReference!==''?$finalRefundReference:null,
        $finalRefundReference!==''?PDO::PARAM_STR:PDO::PARAM_NULL
    );

    $updateRefund->bindValue(
        ':reason',
        $finalReason,
        PDO::PARAM_STR
    );

    $updateRefund->bindValue(
        ':admin_note',
        $finalAdminNote!==''?$finalAdminNote:null,
        $finalAdminNote!==''?PDO::PARAM_STR:PDO::PARAM_NULL
    );

    $updateRefund->bindValue(
        ':customer_mobile',
        $finalCustomerMobile!==''?$finalCustomerMobile:null,
        $finalCustomerMobile!==''?PDO::PARAM_STR:PDO::PARAM_NULL
    );

    $updateRefund->bindValue(
        ':contact_status',
        $finalContactStatus,
        PDO::PARAM_STR
    );

    $updateRefund->bindValue(
        ':status',
        $newRefundStatus,
        PDO::PARAM_STR
    );

    $updateRefund->bindValue(
        ':handled_by_admin_id',
        $adminId,
        PDO::PARAM_INT
    );

    foreach([
        ':contacted_at'=>$contactedAt,
        ':processing_at'=>$processingAt,
        ':refunded_at'=>$refundedAt,
        ':rejected_at'=>$rejectedAt,
        ':cancelled_at'=>$cancelledAt
    ] as $placeholder=>$value){

        $updateRefund->bindValue(
            $placeholder,
            $value,
            $value===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );
    }

    $updateRefund->bindValue(
        ':refund_id',
        $refundId,
        PDO::PARAM_INT
    );

    $updateRefund->execute();

    /*
    |--------------------------------------------------------------------------
    | PAYMENT + ORDER STATUS
    |--------------------------------------------------------------------------
    |
    | Only successfully completed refund changes financial/order status.
    |--------------------------------------------------------------------------
    */

    $oldPaymentStatus=(string)$payment['status'];
    $oldOrderPaymentStatus=(string)$order['payment_status'];
    $oldOrderStatus=(string)$order['order_status'];

    $newPaymentStatus=$oldPaymentStatus;
    $newOrderPaymentStatus=$oldOrderPaymentStatus;
    $newOrderStatus=$oldOrderStatus;

    if($newRefundStatus==='refunded'){

        $newPaymentStatus=
            $isFullyRefunded
                ?'refunded'
                :'partially_refunded';

        $newOrderPaymentStatus=
            $isFullyRefunded
                ?'refunded'
                :'partially_refunded';

        $newOrderStatus=
            $isFullyRefunded
                ?'refunded'
                :'partially_refunded';

        /*
        |--------------------------------------------------------------------------
        | UPDATE PAYMENT
        |--------------------------------------------------------------------------
        */

        $paymentUpdate=$pdo->prepare(
            "UPDATE payments SET
                status=:status

             WHERE id=:payment_id
               AND order_id=:order_id
               AND user_id=:user_id"
        );

        $paymentUpdate->bindValue(
            ':status',
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

        /*
        |--------------------------------------------------------------------------
        | UPDATE ORDER
        |--------------------------------------------------------------------------
        */

        $orderUpdate=$pdo->prepare(
            "UPDATE orders SET
                payment_status=:payment_status,
                order_status=:order_status

             WHERE id=:order_id
               AND user_id=:user_id"
        );

        $orderUpdate->bindValue(
            ':payment_status',
            $newOrderPaymentStatus,
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

        /*
        |--------------------------------------------------------------------------
        | PAYMENT STATUS HISTORY
        |--------------------------------------------------------------------------
        */

        if($oldPaymentStatus!==$newPaymentStatus){

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
                'Manual refund updated by admin. '.
                'Current refund amount: ₹'.
                refundUpdatePaiseToAmount(
                    $currentRefundPaise
                ).
                '. Total refunded amount: ₹'.
                refundUpdatePaiseToAmount(
                    $totalRefundedAfterPaise
                ).
                '. Remaining refundable amount: ₹'.
                refundUpdatePaiseToAmount(
                    $remainingAfterPaise
                ).
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
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    sendResponse(
        true,
        'Refund updated successfully.',
        [
            'refund'=>[
                'id'=>$refundId,

                'order_id'=>$orderId,

                'payment_id'=>$paymentId,

                'user_id'=>$userId,

                'refund_type'=>$refundType,

                'refund_amount'=>
                    refundUpdatePaiseToAmount(
                        $currentRefundPaise
                    ),

                'refund_method'=>
                    $finalRefundMethod,

                'refund_reference'=>
                    $finalRefundReference!==''?$finalRefundReference:null,

                'reason'=>
                    $finalReason,

                'admin_note'=>
                    $finalAdminNote!==''?$finalAdminNote:null,

                'customer_mobile'=>
                    $finalCustomerMobile!==''?$finalCustomerMobile:null,

                'contact_status'=>
                    $finalContactStatus,

                'previous_status'=>
                    $oldRefundStatus,

                'status'=>
                    $newRefundStatus,

                'handled_by_admin_id'=>
                    $adminId,

                'timeline'=>[
                    'contacted_at'=>$contactedAt,
                    'processing_at'=>$processingAt,
                    'refunded_at'=>$refundedAt,
                    'rejected_at'=>$rejectedAt,
                    'cancelled_at'=>$cancelledAt
                ]
            ],

            'refund_calculation'=>[
                'original_payment_amount'=>
                    refundUpdatePaiseToAmount(
                        $paymentAmountPaise
                    ),

                'previously_refunded_amount'=>
                    refundUpdatePaiseToAmount(
                        $previousRefundedPaise
                    ),

                'current_refund_amount'=>
                    refundUpdatePaiseToAmount(
                        $currentRefundPaise
                    ),

                'total_refunded_amount'=>
                    refundUpdatePaiseToAmount(
                        $totalRefundedAfterPaise
                    ),

                'remaining_refundable_amount'=>
                    refundUpdatePaiseToAmount(
                        $remainingAfterPaise
                    ),

                'fully_refunded'=>
                    $isFullyRefunded,

                'current_refund_completed'=>
                    $newRefundStatus==='refunded'
            ],

            'payment'=>[
                'id'=>$paymentId,

                'provider'=>
                    $payment['provider'],

                'payment_method'=>
                    $payment['payment_method'],

                'razorpay_order_id'=>
                    $payment['razorpay_order_id'],

                'razorpay_payment_id'=>
                    $payment['razorpay_payment_id'],

                'amount'=>
                    refundUpdatePaiseToAmount(
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

                'grand_total'=>
                    $order['grand_total'],

                'previous_payment_status'=>
                    $oldOrderPaymentStatus,

                'payment_status'=>
                    $newOrderPaymentStatus,

                'previous_order_status'=>
                    $oldOrderStatus,

                'order_status'=>
                    $newOrderStatus
            ],

            'customer'=>[
                'id'=>$userId,
                'name'=>$order['user_name'],
                'mobile'=>$order['user_mobile'],
                'email'=>$order['user_email']
            ]
        ],
        200
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to update refund.',
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
        'An unexpected error occurred while updating refund.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}