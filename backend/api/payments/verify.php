<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/jwt.php';
require_once __DIR__.'/../../config/razorpay.php';

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

if(getAuthenticatedId($decoded)<=0){
    sendResponse(false,'Invalid authenticated account.',null,401);
}

if(getAuthenticatedType($decoded)!=='user'){
    sendResponse(false,'User access only.',null,403);
}

$userAuth=authenticateUser();
$userId=getAuthenticatedId($userAuth);

if($userId<=0){
    sendResponse(false,'Invalid authenticated user.',null,401);
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
    'payment_id',
    'razorpay_order_id',
    'razorpay_payment_id',
    'razorpay_signature'
];

foreach(array_keys($data) as $field){
    if(!in_array($field,$allowedFields,true)){
        sendResponse(false,"Invalid field: {$field}.",[
            'allowed_fields'=>$allowedFields
        ],422);
    }
}

$orderIdInput=trim((string)($data['order_id']??''));
$paymentIdInput=trim((string)($data['payment_id']??''));
$clientRazorpayOrderId=trim((string)($data['razorpay_order_id']??''));
$razorpayPaymentId=trim((string)($data['razorpay_payment_id']??''));
$razorpaySignature=trim((string)($data['razorpay_signature']??''));

if(!preg_match('/^[1-9][0-9]*$/',$orderIdInput)){
    sendResponse(false,'order_id must be a valid positive integer.',null,422);
}

if(!preg_match('/^[1-9][0-9]*$/',$paymentIdInput)){
    sendResponse(false,'payment_id must be a valid positive integer.',null,422);
}

if($clientRazorpayOrderId===''){
    sendResponse(false,'razorpay_order_id is required.',null,422);
}

if(
    mb_strlen($clientRazorpayOrderId)>100||
    !preg_match('/^order_[A-Za-z0-9]+$/',$clientRazorpayOrderId)
){
    sendResponse(false,'Invalid razorpay_order_id.',null,422);
}

if($razorpayPaymentId===''){
    sendResponse(false,'razorpay_payment_id is required.',null,422);
}

if(
    mb_strlen($razorpayPaymentId)>100||
    !preg_match('/^pay_[A-Za-z0-9]+$/',$razorpayPaymentId)
){
    sendResponse(false,'Invalid razorpay_payment_id.',null,422);
}

if($razorpaySignature===''){
    sendResponse(false,'razorpay_signature is required.',null,422);
}

if(!preg_match('/^[a-fA-F0-9]{64}$/',$razorpaySignature)){
    sendResponse(false,'Invalid razorpay_signature format.',null,422);
}

$orderId=(int)$orderIdInput;
$paymentId=(int)$paymentIdInput;

function paymentVerifyFail(
    PDO $pdo,
    string $message,
    mixed $data=null,
    int $statusCode=422
):never{
    if($pdo->inTransaction()){
        $pdo->rollBack();
    }
    sendResponse(false,$message,$data,$statusCode);
    exit;
}

try{
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | GET PAYMENT + ORDER
    |--------------------------------------------------------------------------
    */

    $stmt=$pdo->prepare(
        "SELECT
            p.id AS payment_id,
            p.order_id,
            p.user_id,
            p.provider,
            p.attempt_no,
            p.razorpay_order_id,
            p.razorpay_payment_id,
            p.razorpay_signature,
            p.receipt,
            p.amount,
            p.amount_paise,
            p.currency,
            p.payment_method AS stored_payment_method,
            p.status AS payment_status,
            p.gateway_status,
            p.created_at AS payment_created_at,

            o.order_number,
            o.subtotal,
            o.product_discount_amount,
            o.coupon_discount_amount,
            o.shipping_charge,
            o.cod_charge,
            o.tax_amount,
            o.grand_total,
            o.payment_method AS order_payment_method,
            o.payment_status AS order_payment_status,
            o.order_status

         FROM payments p
         INNER JOIN orders o ON o.id=p.order_id

         WHERE p.id=:payment_id
           AND p.order_id=:order_id
           AND p.user_id=:user_id
           AND o.user_id=:order_user_id

         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':payment_id',$paymentId,PDO::PARAM_INT);
    $stmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $stmt->bindValue(':user_id',$userId,PDO::PARAM_INT);
    $stmt->bindValue(':order_user_id',$userId,PDO::PARAM_INT);
    $stmt->execute();

    $payment=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$payment){
        paymentVerifyFail(
            $pdo,
            'Payment record not found.',
            null,
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COD DOES NOT USE RAZORPAY VERIFY
    |--------------------------------------------------------------------------
    */

    if($payment['provider']==='cod'){
        paymentVerifyFail(
            $pdo,
            'Cash on delivery payment does not require Razorpay verification.',
            [
                'payment_id'=>$paymentId,
                'payment_method'=>'cod',
                'payment_status'=>$payment['payment_status']
            ],
            409
        );
    }

    if($payment['provider']!=='razorpay'){
        paymentVerifyFail(
            $pdo,
            'Invalid payment provider.',
            [
                'provider'=>$payment['provider']
            ],
            409
        );
    }

    if($payment['razorpay_order_id']===null||$payment['razorpay_order_id']===''){
        paymentVerifyFail(
            $pdo,
            'Razorpay order has not been created for this payment.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CLIENT ORDER ID MUST MATCH DATABASE ORDER ID
    |--------------------------------------------------------------------------
    */

    if(!hash_equals(
        (string)$payment['razorpay_order_id'],
        $clientRazorpayOrderId
    )){
        paymentVerifyFail(
            $pdo,
            'Razorpay order ID mismatch.',
            null,
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IDEMPOTENT SUCCESS
    |--------------------------------------------------------------------------
    */

    if($payment['payment_status']==='paid'){
        if(
            $payment['razorpay_payment_id']!==null&&
            $payment['razorpay_payment_id']!==$razorpayPaymentId
        ){
            paymentVerifyFail(
                $pdo,
                'This payment has already been completed with another Razorpay payment ID.',
                null,
                409
            );
        }

        $pdo->commit();

        sendResponse(true,'Payment already verified successfully.',[
            'payment'=>[
                'id'=>$paymentId,
                'order_id'=>$orderId,
                'provider'=>'razorpay',
                'razorpay_order_id'=>$payment['razorpay_order_id'],
                'razorpay_payment_id'=>$payment['razorpay_payment_id'],
                'payment_method'=>$payment['stored_payment_method'],
                'status'=>'paid'
            ],
            'order'=>[
                'id'=>$orderId,
                'order_number'=>$payment['order_number'],
                'payment_method'=>$payment['order_payment_method'],
                'payment_status'=>$payment['order_payment_status'],
                'order_status'=>$payment['order_status'],
                'grand_total'=>$payment['grand_total']
            ],
            'already_verified'=>true
        ],200);
    }

    if(in_array(
        $payment['payment_status'],
        ['cancelled','refunded','partially_refunded'],
        true
    )){
        paymentVerifyFail(
            $pdo,
            'This payment cannot be verified in its current status.',
            [
                'payment_status'=>$payment['payment_status']
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK ANOTHER SUCCESSFUL PAYMENT
    |--------------------------------------------------------------------------
    */

    $paidStmt=$pdo->prepare(
        "SELECT
            id,
            razorpay_payment_id,
            payment_method,
            status
         FROM payments
         WHERE order_id=:order_id
           AND status='paid'
           AND id<>:payment_id
         LIMIT 1
         FOR UPDATE"
    );

    $paidStmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $paidStmt->bindValue(':payment_id',$paymentId,PDO::PARAM_INT);
    $paidStmt->execute();

    $otherPaidPayment=$paidStmt->fetch(PDO::FETCH_ASSOC);

    if($otherPaidPayment){
        paymentVerifyFail(
            $pdo,
            'This order already has another successful payment.',
            [
                'payment_id'=>(int)$otherPaidPayment['id'],
                'payment_status'=>'paid'
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY RAZORPAY SIGNATURE
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Database razorpay_order_id தான் signature verification-க்கு use ஆகுது.
    |--------------------------------------------------------------------------
    */

    try{
        $razorpayApi->utility->verifyPaymentSignature([
            'razorpay_order_id'=>(string)$payment['razorpay_order_id'],
            'razorpay_payment_id'=>$razorpayPaymentId,
            'razorpay_signature'=>$razorpaySignature
        ]);
    }catch(Throwable $e){
        paymentVerifyFail(
            $pdo,
            'Invalid Razorpay payment signature.',
            defined('APP_ENV')&&APP_ENV==='development'
                ?['error'=>$e->getMessage()]
                :null,
            400
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH PAYMENT FROM RAZORPAY
    |--------------------------------------------------------------------------
    */

    try{
        $gatewayPayment=$razorpayApi->payment->fetch(
            $razorpayPaymentId
        );
    }catch(Throwable $e){
        paymentVerifyFail(
            $pdo,
            'Unable to fetch payment from Razorpay.',
            defined('APP_ENV')&&APP_ENV==='development'
                ?['error'=>$e->getMessage()]
                :null,
            502
        );
    }

    $gatewayPaymentId=(string)($gatewayPayment['id']??'');
    $gatewayOrderId=(string)($gatewayPayment['order_id']??'');
    $gatewayAmount=(int)($gatewayPayment['amount']??0);
    $gatewayCurrency=strtoupper(
        (string)($gatewayPayment['currency']??'')
    );
    $gatewayStatus=strtolower(
        (string)($gatewayPayment['status']??'')
    );
    $gatewayMethod=strtolower(
        (string)($gatewayPayment['method']??'')
    );
    $gatewayCaptured=(bool)($gatewayPayment['captured']??false);

    /*
    |--------------------------------------------------------------------------
    | GATEWAY DATA VALIDATIONS
    |--------------------------------------------------------------------------
    */

    if($gatewayPaymentId!==$razorpayPaymentId){
        paymentVerifyFail(
            $pdo,
            'Razorpay payment ID mismatch.',
            null,
            409
        );
    }

    if($gatewayOrderId!==(string)$payment['razorpay_order_id']){
        paymentVerifyFail(
            $pdo,
            'Razorpay payment does not belong to this order.',
            null,
            409
        );
    }

    if($gatewayAmount!==(int)$payment['amount_paise']){
        paymentVerifyFail(
            $pdo,
            'Razorpay payment amount mismatch.',
            [
                'expected_amount_paise'=>
                    (int)$payment['amount_paise'],
                'received_amount_paise'=>
                    $gatewayAmount
            ],
            409
        );
    }

    if($gatewayCurrency!=='INR'){
        paymentVerifyFail(
            $pdo,
            'Razorpay payment currency mismatch.',
            [
                'expected_currency'=>'INR',
                'received_currency'=>$gatewayCurrency
            ],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PREVENT SAME RAZORPAY PAYMENT ID ON ANOTHER LOCAL RECORD
    |--------------------------------------------------------------------------
    */

    $duplicateStmt=$pdo->prepare(
        "SELECT
            id,
            order_id
         FROM payments
         WHERE razorpay_payment_id=:razorpay_payment_id
           AND id<>:payment_id
         LIMIT 1"
    );

    $duplicateStmt->bindValue(
        ':razorpay_payment_id',
        $razorpayPaymentId,
        PDO::PARAM_STR
    );

    $duplicateStmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $duplicateStmt->execute();

    if($duplicateStmt->fetch(PDO::FETCH_ASSOC)){
        paymentVerifyFail(
            $pdo,
            'This Razorpay payment ID is already linked to another payment.',
            null,
            409
        );
    }

    $gatewayResponse=method_exists(
        $gatewayPayment,
        'toArray'
    )
        ?$gatewayPayment->toArray()
        :[];

    $gatewayResponseJson=json_encode(
        $gatewayResponse,
        JSON_UNESCAPED_UNICODE|
        JSON_UNESCAPED_SLASHES
    );

    if($gatewayResponseJson===false){
        $gatewayResponseJson='{}';
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD
    |--------------------------------------------------------------------------
    |
    | Razorpay commonly returns:
    | card / netbanking / wallet / emi / upi
    |
    | Unknown gateway method:
    | payments.payment_method = other
    | orders.payment_method   = razorpay
    |--------------------------------------------------------------------------
    */

    $supportedActualMethods=[
        'upi',
        'card',
        'netbanking',
        'wallet',
        'emi',
        'paylater'
    ];

    $actualPaymentMethod=in_array(
        $gatewayMethod,
        $supportedActualMethods,
        true
    )
        ?$gatewayMethod
        :'other';

    $orderPaymentMethod=$actualPaymentMethod==='other'
        ?'razorpay'
        :$actualPaymentMethod;

    /*
    |--------------------------------------------------------------------------
    | PAYMENT FAILED
    |--------------------------------------------------------------------------
    */

    if($gatewayStatus==='failed'){
        $errorCode=$gatewayPayment['error_code']??null;
        $errorDescription=$gatewayPayment['error_description']??null;
        $errorSource=$gatewayPayment['error_source']??null;
        $errorStep=$gatewayPayment['error_step']??null;
        $errorReason=$gatewayPayment['error_reason']??null;

        $oldStatus=(string)$payment['payment_status'];

        $updateStmt=$pdo->prepare(
            "UPDATE payments SET
                razorpay_payment_id=:razorpay_payment_id,
                razorpay_signature=:razorpay_signature,
                payment_method=:payment_method,
                status='failed',
                gateway_status=:gateway_status,
                error_code=:error_code,
                error_description=:error_description,
                error_source=:error_source,
                error_step=:error_step,
                error_reason=:error_reason,
                gateway_response=:gateway_response,
                failed_at=NOW()
             WHERE id=:payment_id
               AND user_id=:user_id
               AND status<>'paid'"
        );

        $updateStmt->bindValue(
            ':razorpay_payment_id',
            $razorpayPaymentId,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':razorpay_signature',
            $razorpaySignature,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':payment_method',
            $actualPaymentMethod,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':gateway_status',
            $gatewayStatus,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':error_code',
            $errorCode,
            $errorCode===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':error_description',
            $errorDescription,
            $errorDescription===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':error_source',
            $errorSource,
            $errorSource===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':error_step',
            $errorStep,
            $errorStep===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':error_reason',
            $errorReason,
            $errorReason===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':gateway_response',
            $gatewayResponseJson,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_INT
        );

        $updateStmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $updateStmt->execute();

        $orderUpdate=$pdo->prepare(
            "UPDATE orders SET
                payment_method=:payment_method,
                payment_status='failed'
             WHERE id=:order_id
               AND user_id=:user_id
               AND payment_status<>'paid'"
        );

        $orderUpdate->bindValue(
            ':payment_method',
            $orderPaymentMethod,
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
                'failed',
                'verify_api',
                :message
             )"
        );

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
            $oldStatus,
            PDO::PARAM_STR
        );

        $failureMessage=$errorDescription
            ?mb_substr((string)$errorDescription,0,500)
            :'Razorpay payment failed';

        $historyStmt->bindValue(
            ':message',
            $failureMessage,
            PDO::PARAM_STR
        );

        $historyStmt->execute();

        $pdo->commit();

        sendResponse(false,'Payment failed.',[
            'payment'=>[
                'id'=>$paymentId,
                'order_id'=>$orderId,
                'razorpay_order_id'=>
                    $payment['razorpay_order_id'],
                'razorpay_payment_id'=>
                    $razorpayPaymentId,
                'payment_method'=>
                    $actualPaymentMethod,
                'gateway_status'=>'failed',
                'status'=>'failed'
            ],
            'error'=>[
                'code'=>$errorCode,
                'description'=>$errorDescription,
                'source'=>$errorSource,
                'step'=>$errorStep,
                'reason'=>$errorReason
            ]
        ],409);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHORIZED BUT NOT CAPTURED
    |--------------------------------------------------------------------------
    */

    if(
        $gatewayStatus==='authorized'&&
        !$gatewayCaptured
    ){
        $oldStatus=(string)$payment['payment_status'];

        $updateStmt=$pdo->prepare(
            "UPDATE payments SET
                razorpay_payment_id=:razorpay_payment_id,
                razorpay_signature=:razorpay_signature,
                payment_method=:payment_method,
                status='authorized',
                gateway_status='authorized',
                bank=:bank,
                wallet=:wallet,
                vpa=:vpa,
                card_id=:card_id,
                gateway_response=:gateway_response,
                authorized_at=COALESCE(authorized_at,NOW())
             WHERE id=:payment_id
               AND user_id=:user_id
               AND status<>'paid'"
        );

        $updateStmt->bindValue(
            ':razorpay_payment_id',
            $razorpayPaymentId,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':razorpay_signature',
            $razorpaySignature,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':payment_method',
            $actualPaymentMethod,
            PDO::PARAM_STR
        );

        foreach([
            ':bank'=>$gatewayPayment['bank']??null,
            ':wallet'=>$gatewayPayment['wallet']??null,
            ':vpa'=>$gatewayPayment['vpa']??null,
            ':card_id'=>$gatewayPayment['card_id']??null
        ] as $key=>$value){
            $updateStmt->bindValue(
                $key,
                $value,
                $value===null
                    ?PDO::PARAM_NULL
                    :PDO::PARAM_STR
            );
        }

        $updateStmt->bindValue(
            ':gateway_response',
            $gatewayResponseJson,
            PDO::PARAM_STR
        );

        $updateStmt->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_INT
        );

        $updateStmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $updateStmt->execute();

        $orderUpdate=$pdo->prepare(
            "UPDATE orders SET
                payment_method=:payment_method,
                payment_status='initiated'
             WHERE id=:order_id
               AND user_id=:user_id
               AND payment_status<>'paid'"
        );

        $orderUpdate->bindValue(
            ':payment_method',
            $orderPaymentMethod,
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

        if($oldStatus!=='authorized'){
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
                    'authorized',
                    'verify_api',
                    'Razorpay payment authorized but not captured yet'
                 )"
            );

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
                $oldStatus,
                PDO::PARAM_STR
            );

            $historyStmt->execute();
        }

        $pdo->commit();

        sendResponse(false,'Payment is authorized but not captured yet.',[
            'payment'=>[
                'id'=>$paymentId,
                'order_id'=>$orderId,
                'razorpay_order_id'=>
                    $payment['razorpay_order_id'],
                'razorpay_payment_id'=>
                    $razorpayPaymentId,
                'payment_method'=>
                    $actualPaymentMethod,
                'gateway_status'=>'authorized',
                'status'=>'authorized'
            ],
            'order'=>[
                'payment_status'=>'initiated'
            ]
        ],409);
    }

    /*
    |--------------------------------------------------------------------------
    | MUST BE CAPTURED
    |--------------------------------------------------------------------------
    */

    if(
        $gatewayStatus!=='captured'||
        $gatewayCaptured!==true
    ){
        $pdo->commit();

        sendResponse(false,'Payment is not captured yet.',[
            'payment'=>[
                'id'=>$paymentId,
                'gateway_status'=>$gatewayStatus,
                'captured'=>$gatewayCaptured
            ]
        ],409);
    }

    /*
    |--------------------------------------------------------------------------
    | CAPTURED PAYMENT
    |--------------------------------------------------------------------------
    */

    $feePaise=(int)($gatewayPayment['fee']??0);
    $taxPaise=(int)($gatewayPayment['tax']??0);

    $gatewayFee=round(
        $feePaise/100,
        2
    );

    $gatewayTax=round(
        $taxPaise/100,
        2
    );

    $bank=$gatewayPayment['bank']??null;
    $wallet=$gatewayPayment['wallet']??null;
    $vpa=$gatewayPayment['vpa']??null;
    $cardId=$gatewayPayment['card_id']??null;

    $oldPaymentStatus=(string)$payment['payment_status'];

    $paymentUpdate=$pdo->prepare(
        "UPDATE payments SET
            razorpay_payment_id=:razorpay_payment_id,
            razorpay_signature=:razorpay_signature,
            payment_method=:payment_method,
            status='paid',
            gateway_status='captured',
            bank=:bank,
            wallet=:wallet,
            vpa=:vpa,
            card_id=:card_id,
            fee=:fee,
            tax=:tax,
            error_code=NULL,
            error_description=NULL,
            error_source=NULL,
            error_step=NULL,
            error_reason=NULL,
            gateway_response=:gateway_response,
            paid_at=COALESCE(paid_at,NOW())
         WHERE id=:payment_id
           AND order_id=:order_id
           AND user_id=:user_id
           AND status<>'paid'"
    );

    $paymentUpdate->bindValue(
        ':razorpay_payment_id',
        $razorpayPaymentId,
        PDO::PARAM_STR
    );

    $paymentUpdate->bindValue(
        ':razorpay_signature',
        $razorpaySignature,
        PDO::PARAM_STR
    );

    $paymentUpdate->bindValue(
        ':payment_method',
        $actualPaymentMethod,
        PDO::PARAM_STR
    );

    foreach([
        ':bank'=>$bank,
        ':wallet'=>$wallet,
        ':vpa'=>$vpa,
        ':card_id'=>$cardId
    ] as $key=>$value){
        $paymentUpdate->bindValue(
            $key,
            $value,
            $value===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );
    }

    $paymentUpdate->bindValue(
        ':fee',
        number_format($gatewayFee,2,'.',''),
        PDO::PARAM_STR
    );

    $paymentUpdate->bindValue(
        ':tax',
        number_format($gatewayTax,2,'.',''),
        PDO::PARAM_STR
    );

    $paymentUpdate->bindValue(
        ':gateway_response',
        $gatewayResponseJson,
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
    |
    | Actual gateway method store ஆகும்:
    |
    | UPI         -> upi
    | Card        -> card
    | Netbanking  -> netbanking
    | Wallet      -> wallet
    | EMI         -> emi
    |
    | Unknown     -> razorpay
    |--------------------------------------------------------------------------
    */

    $orderUpdate=$pdo->prepare(
        "UPDATE orders SET
            cod_charge=0,
            payment_method=:payment_method,
            payment_status='paid',
            order_status=CASE
                WHEN order_status='pending'
                THEN 'confirmed'
                ELSE order_status
            END,
            confirmed_at=CASE
                WHEN confirmed_at IS NULL
                THEN NOW()
                ELSE confirmed_at
            END
         WHERE id=:order_id
           AND user_id=:user_id
           AND payment_status<>'paid'"
    );

    $orderUpdate->bindValue(
        ':payment_method',
        $orderPaymentMethod,
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
    | STATUS HISTORY
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
            'paid',
            'verify_api',
            :message
         )"
    );

    $historyMessage=
        'Razorpay payment verified and captured successfully. Method: '.
        $actualPaymentMethod;

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
        ':message',
        mb_substr($historyMessage,0,500),
        PDO::PARAM_STR
    );

    $historyStmt->execute();

    $pdo->commit();

    sendResponse(true,'Payment verified successfully.',[
        'payment'=>[
            'id'=>$paymentId,
            'order_id'=>$orderId,
            'provider'=>'razorpay',
            'attempt_no'=>(int)$payment['attempt_no'],

            'razorpay_order_id'=>
                $payment['razorpay_order_id'],

            'razorpay_payment_id'=>
                $razorpayPaymentId,

            'payment_method'=>
                $actualPaymentMethod,

            'gateway_status'=>'captured',
            'status'=>'paid',

            'amount'=>number_format(
                (float)$payment['amount'],
                2,
                '.',
                ''
            ),

            'amount_paise'=>
                (int)$payment['amount_paise'],

            'currency'=>'INR',

            'gateway_fee'=>number_format(
                $gatewayFee,
                2,
                '.',
                ''
            ),

            'gateway_tax'=>number_format(
                $gatewayTax,
                2,
                '.',
                ''
            ),

            'bank'=>$bank,
            'wallet'=>$wallet,
            'vpa'=>$vpa,
            'card_id'=>$cardId
        ],

        'order'=>[
            'id'=>$orderId,
            'order_number'=>$payment['order_number'],

            'amounts'=>[
                'subtotal'=>
                    $payment['subtotal'],

                'product_discount_amount'=>
                    $payment['product_discount_amount'],

                'coupon_discount_amount'=>
                    $payment['coupon_discount_amount'],

                'shipping_charge'=>
                    $payment['shipping_charge'],

                'cod_charge'=>'0.00',

                'tax_amount'=>
                    $payment['tax_amount'],

                'grand_total'=>
                    $payment['grand_total']
            ],

            'payment_method'=>
                $orderPaymentMethod,

            'payment_status'=>'paid',

            'order_status'=>
                $payment['order_status']==='pending'
                    ?'confirmed'
                    :$payment['order_status']
        ],

        'verification'=>[
            'signature_verified'=>true,
            'order_id_verified'=>true,
            'payment_id_verified'=>true,
            'amount_verified'=>true,
            'currency_verified'=>true,
            'captured_verified'=>true
        ]
    ],200);

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    if((string)$e->getCode()==='23000'){
        sendResponse(
            false,
            'Payment verification conflict. This Razorpay payment may already be linked to another transaction.',
            defined('APP_ENV')&&APP_ENV==='development'
                ?['error'=>$e->getMessage()]
                :null,
            409
        );
    }

    sendResponse(
        false,
        'Unable to verify payment.',
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
        'An unexpected error occurred while verifying payment.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );
}