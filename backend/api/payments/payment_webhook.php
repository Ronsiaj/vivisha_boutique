<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/razorpay.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function webhookResponse(
    bool $status,
    string $message,
    mixed $data=null,
    int $code=200
):never{
    http_response_code($code);
    echo json_encode([
        'status'=>$status,
        'message'=>$message,
        'data'=>$data
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST'){
    webhookResponse(false,'Only POST method is allowed.',null,405);
}

$rawBody=file_get_contents('php://input');

if($rawBody===false||$rawBody===''){
    webhookResponse(false,'Empty webhook payload.',null,400);
}

$signature=trim(
    (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'')
);

$eventId=trim(
    (string)($_SERVER['HTTP_X_RAZORPAY_EVENT_ID']??'')
);

if($signature===''){
    webhookResponse(
        false,
        'X-Razorpay-Signature header is required.',
        null,
        400
    );
}

if($razorpayWebhookSecret===''){
    webhookResponse(
        false,
        'Razorpay webhook secret is not configured.',
        null,
        500
    );
}

/*
|--------------------------------------------------------------------------
| VERIFY WEBHOOK SIGNATURE
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Raw request body must be used.
|
*/

try{
    $razorpayApi->utility->verifyWebhookSignature(
        $rawBody,
        $signature,
        $razorpayWebhookSecret
    );
}catch(Throwable $e){
    webhookResponse(
        false,
        'Invalid Razorpay webhook signature.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        400
    );
}

$payload=json_decode($rawBody,true);

if(
    json_last_error()!==JSON_ERROR_NONE||
    !is_array($payload)
){
    webhookResponse(
        false,
        'Invalid webhook JSON payload.',
        null,
        400
    );
}

$eventType=trim(
    (string)($payload['event']??'')
);

if($eventType===''){
    webhookResponse(
        false,
        'Webhook event type is missing.',
        null,
        400
    );
}

/*
 * Razorpay normally sends x-razorpay-event-id.
 * Fallback keeps local idempotency protection.
 */
if($eventId===''){
    $eventId=hash('sha256',$rawBody);
}

if(mb_strlen($eventId)>255){
    webhookResponse(
        false,
        'Invalid webhook event ID.',
        null,
        400
    );
}

$paymentEntity=
    $payload['payload']['payment']['entity']??null;

$razorpayOrderId=null;
$razorpayPaymentId=null;

if(is_array($paymentEntity)){
    $razorpayOrderId=
        isset($paymentEntity['order_id'])
            ?trim((string)$paymentEntity['order_id'])
            :null;

    $razorpayPaymentId=
        isset($paymentEntity['id'])
            ?trim((string)$paymentEntity['id'])
            :null;
}

/*
|--------------------------------------------------------------------------
| STORE / DEDUPLICATE WEBHOOK EVENT
|--------------------------------------------------------------------------
*/

try{
    $eventStmt=$pdo->prepare(
        "SELECT
            id,
            process_status
         FROM payment_webhook_events
         WHERE event_id=:event_id
         LIMIT 1"
    );

    $eventStmt->bindValue(
        ':event_id',
        $eventId,
        PDO::PARAM_STR
    );

    $eventStmt->execute();

    $existingEvent=$eventStmt->fetch(PDO::FETCH_ASSOC);

    if($existingEvent){
        $webhookEventId=(int)$existingEvent['id'];

        /*
         * Already completed duplicate webhook.
         */
        if(in_array(
            $existingEvent['process_status'],
            ['processed','ignored'],
            true
        )){
            webhookResponse(
                true,
                'Webhook event already processed.',
                [
                    'event_id'=>$eventId,
                    'event_type'=>$eventType
                ],
                200
            );
        }

        /*
         * Another request may already be processing same event.
         */
        if($existingEvent['process_status']==='pending'){
            webhookResponse(
                true,
                'Webhook event is already being processed.',
                [
                    'event_id'=>$eventId
                ],
                200
            );
        }

        /*
         * Previous failed delivery retry.
         */
        $retryStmt=$pdo->prepare(
            "UPDATE payment_webhook_events SET
                event_type=:event_type,
                razorpay_order_id=:razorpay_order_id,
                razorpay_payment_id=:razorpay_payment_id,
                payload=:payload,
                process_status='pending',
                error_message=NULL,
                received_at=NOW(),
                processed_at=NULL
             WHERE id=:id"
        );

        $retryStmt->bindValue(
            ':event_type',
            $eventType,
            PDO::PARAM_STR
        );

        $retryStmt->bindValue(
            ':razorpay_order_id',
            $razorpayOrderId,
            $razorpayOrderId===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $retryStmt->bindValue(
            ':razorpay_payment_id',
            $razorpayPaymentId,
            $razorpayPaymentId===null
                ?PDO::PARAM_NULL
                :PDO::PARAM_STR
        );

        $retryStmt->bindValue(
            ':payload',
            $rawBody,
            PDO::PARAM_STR
        );

        $retryStmt->bindValue(
            ':id',
            $webhookEventId,
            PDO::PARAM_INT
        );

        $retryStmt->execute();

    }else{
        try{
            $insertEvent=$pdo->prepare(
                "INSERT INTO payment_webhook_events(
                    event_id,
                    event_type,
                    razorpay_order_id,
                    razorpay_payment_id,
                    payload,
                    process_status,
                    received_at
                 )VALUES(
                    :event_id,
                    :event_type,
                    :razorpay_order_id,
                    :razorpay_payment_id,
                    :payload,
                    'pending',
                    NOW()
                 )"
            );

            $insertEvent->bindValue(
                ':event_id',
                $eventId,
                PDO::PARAM_STR
            );

            $insertEvent->bindValue(
                ':event_type',
                $eventType,
                PDO::PARAM_STR
            );

            $insertEvent->bindValue(
                ':razorpay_order_id',
                $razorpayOrderId,
                $razorpayOrderId===null
                    ?PDO::PARAM_NULL
                    :PDO::PARAM_STR
            );

            $insertEvent->bindValue(
                ':razorpay_payment_id',
                $razorpayPaymentId,
                $razorpayPaymentId===null
                    ?PDO::PARAM_NULL
                    :PDO::PARAM_STR
            );

            $insertEvent->bindValue(
                ':payload',
                $rawBody,
                PDO::PARAM_STR
            );

            $insertEvent->execute();

            $webhookEventId=(int)$pdo->lastInsertId();

        }catch(PDOException $e){
            /*
             * Same event arrived simultaneously.
             */
            if((string)$e->getCode()==='23000'){
                webhookResponse(
                    true,
                    'Duplicate webhook event received.',
                    [
                        'event_id'=>$eventId
                    ],
                    200
                );
            }

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IGNORE EVENTS NOT USED BY THIS PAYMENT FLOW
    |--------------------------------------------------------------------------
    */

    $supportedEvents=[
        'payment.authorized',
        'payment.captured',
        'payment.failed'
    ];

    if(!in_array($eventType,$supportedEvents,true)){
        $ignoreStmt=$pdo->prepare(
            "UPDATE payment_webhook_events SET
                process_status='ignored',
                processed_at=NOW()
             WHERE id=:id"
        );

        $ignoreStmt->bindValue(
            ':id',
            $webhookEventId,
            PDO::PARAM_INT
        );

        $ignoreStmt->execute();

        webhookResponse(
            true,
            'Webhook event ignored.',
            [
                'event_id'=>$eventId,
                'event_type'=>$eventType
            ],
            200
        );
    }

    if(!is_array($paymentEntity)){
        throw new RuntimeException(
            'Payment entity missing from webhook payload.'
        );
    }

    if(
        $razorpayOrderId===null||
        $razorpayOrderId===''||
        $razorpayPaymentId===null||
        $razorpayPaymentId===''
    ){
        throw new RuntimeException(
            'Razorpay order ID or payment ID missing.'
        );
    }

    if(
        !preg_match('/^order_[A-Za-z0-9]+$/',$razorpayOrderId)||
        !preg_match('/^pay_[A-Za-z0-9]+$/',$razorpayPaymentId)
    ){
        throw new RuntimeException(
            'Invalid Razorpay payment identifiers.'
        );
    }

    $gatewayAmount=(int)($paymentEntity['amount']??0);
    $gatewayCurrency=strtoupper(
        trim((string)($paymentEntity['currency']??''))
    );

    $gatewayStatus=strtolower(
        trim((string)($paymentEntity['status']??''))
    );

    $gatewayMethod=strtolower(
        trim((string)($paymentEntity['method']??''))
    );

    $gatewayCaptured=(bool)(
        $paymentEntity['captured']??false
    );

    if($gatewayAmount<=0){
        throw new RuntimeException(
            'Invalid payment amount in webhook.'
        );
    }

    if($gatewayCurrency!=='INR'){
        throw new RuntimeException(
            'Invalid payment currency in webhook.'
        );
    }

    $supportedMethods=[
        'upi',
        'card',
        'netbanking',
        'wallet',
        'emi',
        'paylater'
    ];

    $actualPaymentMethod=in_array(
        $gatewayMethod,
        $supportedMethods,
        true
    )
        ?$gatewayMethod
        :'other';

    /*
     * orders.payment_method enum has:
     * cod, razorpay, upi, card, netbanking, wallet, emi, paylater
     *
     * Unknown gateway method -> razorpay.
     */
    $orderPaymentMethod=
        $actualPaymentMethod==='other'
            ?'razorpay'
            :$actualPaymentMethod;

    $gatewayResponse=json_encode(
        $paymentEntity,
        JSON_UNESCAPED_UNICODE|
        JSON_UNESCAPED_SLASHES
    );

    if($gatewayResponse===false){
        $gatewayResponse='{}';
    }

    /*
    |--------------------------------------------------------------------------
    | BEGIN BUSINESS TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
     * Find local Razorpay payment.
     */
    $paymentStmt=$pdo->prepare(
        "SELECT
            p.id,
            p.order_id,
            p.user_id,
            p.provider,
            p.attempt_no,
            p.razorpay_order_id,
            p.razorpay_payment_id,
            p.amount,
            p.amount_paise,
            p.currency,
            p.payment_method,
            p.status,
            p.gateway_status,

            o.order_number,
            o.payment_method AS order_payment_method,
            o.payment_status AS order_payment_status,
            o.order_status,
            o.grand_total

         FROM payments p
         INNER JOIN orders o
            ON o.id=p.order_id

         WHERE p.provider='razorpay'
           AND p.razorpay_order_id=:razorpay_order_id

         LIMIT 1
         FOR UPDATE"
    );

    $paymentStmt->bindValue(
        ':razorpay_order_id',
        $razorpayOrderId,
        PDO::PARAM_STR
    );

    $paymentStmt->execute();

    $payment=$paymentStmt->fetch(PDO::FETCH_ASSOC);

    if(!$payment){
        throw new RuntimeException(
            'Local Razorpay payment record not found.'
        );
    }

    $paymentId=(int)$payment['id'];
    $orderId=(int)$payment['order_id'];
    $userId=(int)$payment['user_id'];
    $oldStatus=(string)$payment['status'];

    /*
    |--------------------------------------------------------------------------
    | VERIFY AMOUNT + CURRENCY
    |--------------------------------------------------------------------------
    */

    if(
        $gatewayAmount!==
        (int)$payment['amount_paise']
    ){
        throw new RuntimeException(
            'Webhook payment amount mismatch.'
        );
    }

    if(
        strtoupper((string)$payment['currency'])!==
        $gatewayCurrency
    ){
        throw new RuntimeException(
            'Webhook payment currency mismatch.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PREVENT SAME RAZORPAY PAYMENT ID USED ELSEWHERE
    |--------------------------------------------------------------------------
    */

    $duplicateStmt=$pdo->prepare(
        "SELECT id,order_id
         FROM payments
         WHERE razorpay_payment_id=:razorpay_payment_id
           AND id<>:payment_id
         LIMIT 1
         FOR UPDATE"
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
        throw new RuntimeException(
            'Razorpay payment ID is already linked to another local payment.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT.AUTHORIZED
    |--------------------------------------------------------------------------
    */

    if($eventType==='payment.authorized'){

        /*
         * Out-of-order protection:
         * captured/paid வந்த பிறகு authorized webhook late வந்தால்
         * paid -> authorized downgrade பண்ணக்கூடாது.
         */
        if($oldStatus==='paid'){
            $finishStmt=$pdo->prepare(
                "UPDATE payment_webhook_events SET
                    process_status='processed',
                    processed_at=NOW()
                 WHERE id=:id"
            );

            $finishStmt->bindValue(
                ':id',
                $webhookEventId,
                PDO::PARAM_INT
            );

            $finishStmt->execute();

            $pdo->commit();

            webhookResponse(
                true,
                'Authorized webhook received after payment was already paid.',
                [
                    'event_id'=>$eventId,
                    'payment_id'=>$paymentId,
                    'order_id'=>$orderId,
                    'status'=>'paid'
                ],
                200
            );
        }

        $bank=$paymentEntity['bank']??null;
        $wallet=$paymentEntity['wallet']??null;
        $vpa=$paymentEntity['vpa']??null;
        $cardId=$paymentEntity['card_id']??null;

        $updatePayment=$pdo->prepare(
            "UPDATE payments SET
                razorpay_payment_id=:razorpay_payment_id,
                payment_method=:payment_method,
                status='authorized',
                gateway_status=:gateway_status,
                bank=:bank,
                wallet=:wallet,
                vpa=:vpa,
                card_id=:card_id,
                gateway_response=:gateway_response,
                authorized_at=COALESCE(authorized_at,NOW())
             WHERE id=:payment_id
               AND status<>'paid'"
        );

        $updatePayment->bindValue(
            ':razorpay_payment_id',
            $razorpayPaymentId,
            PDO::PARAM_STR
        );

        $updatePayment->bindValue(
            ':payment_method',
            $actualPaymentMethod,
            PDO::PARAM_STR
        );

        $updatePayment->bindValue(
            ':gateway_status',
            $gatewayStatus!==''
                ?$gatewayStatus
                :'authorized',
            PDO::PARAM_STR
        );

        foreach([
            ':bank'=>$bank,
            ':wallet'=>$wallet,
            ':vpa'=>$vpa,
            ':card_id'=>$cardId
        ] as $key=>$value){
            $updatePayment->bindValue(
                $key,
                $value,
                $value===null
                    ?PDO::PARAM_NULL
                    :PDO::PARAM_STR
            );
        }

        $updatePayment->bindValue(
            ':gateway_response',
            $gatewayResponse,
            PDO::PARAM_STR
        );

        $updatePayment->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_INT
        );

        $updatePayment->execute();

        /*
         * Order payment remains initiated.
         * Authorized != fully paid/captured yet.
         */
        $updateOrder=$pdo->prepare(
            "UPDATE orders SET
                cod_charge=0,
                payment_method=:payment_method,
                payment_status='initiated'
             WHERE id=:order_id
               AND payment_status<>'paid'"
        );

        $updateOrder->bindValue(
            ':payment_method',
            $orderPaymentMethod,
            PDO::PARAM_STR
        );

        $updateOrder->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $updateOrder->execute();

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
                    'webhook',
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

            $historyStmt->bindValue(
                ':message',
                'Razorpay payment authorized webhook received.',
                PDO::PARAM_STR
            );

            $historyStmt->execute();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT.CAPTURED
    |--------------------------------------------------------------------------
    */

    elseif($eventType==='payment.captured'){

        /*
         * Captured webhook should represent a captured payment.
         */
        if(
            $gatewayStatus!=='captured'&&
            $gatewayCaptured!==true
        ){
            throw new RuntimeException(
                'payment.captured webhook does not contain a captured payment.'
            );
        }

        /*
         * Already paid -> do not duplicate history or downgrade anything.
         */
        if($oldStatus!=='paid'){
            $bank=$paymentEntity['bank']??null;
            $wallet=$paymentEntity['wallet']??null;
            $vpa=$paymentEntity['vpa']??null;
            $cardId=$paymentEntity['card_id']??null;

            $feePaise=(int)($paymentEntity['fee']??0);
            $taxPaise=(int)($paymentEntity['tax']??0);

            $gatewayFee=round(
                $feePaise/100,
                2
            );

            $gatewayTax=round(
                $taxPaise/100,
                2
            );

            $updatePayment=$pdo->prepare(
                "UPDATE payments SET
                    razorpay_payment_id=:razorpay_payment_id,
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
                   AND status<>'paid'"
            );

            $updatePayment->bindValue(
                ':razorpay_payment_id',
                $razorpayPaymentId,
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
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
                $updatePayment->bindValue(
                    $key,
                    $value,
                    $value===null
                        ?PDO::PARAM_NULL
                        :PDO::PARAM_STR
                );
            }

            $updatePayment->bindValue(
                ':fee',
                number_format(
                    $gatewayFee,
                    2,
                    '.',
                    ''
                ),
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
                ':tax',
                number_format(
                    $gatewayTax,
                    2,
                    '.',
                    ''
                ),
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
                ':gateway_response',
                $gatewayResponse,
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
                ':payment_id',
                $paymentId,
                PDO::PARAM_INT
            );

            $updatePayment->execute();

            /*
             * Final order payment success.
             */
            $updateOrder=$pdo->prepare(
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
                   AND payment_status<>'paid'"
            );

            $updateOrder->bindValue(
                ':payment_method',
                $orderPaymentMethod,
                PDO::PARAM_STR
            );

            $updateOrder->bindValue(
                ':order_id',
                $orderId,
                PDO::PARAM_INT
            );

            $updateOrder->execute();

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
                    'webhook',
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

            $historyStmt->bindValue(
                ':message',
                mb_substr(
                    'Razorpay payment captured webhook received. Method: '.
                    $actualPaymentMethod,
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
    | PAYMENT.FAILED
    |--------------------------------------------------------------------------
    */

    elseif($eventType==='payment.failed'){

        /*
         * Never downgrade an already-paid payment/order.
         *
         * Webhooks can arrive out of order.
         */
        if($oldStatus!=='paid'){
            $errorCode=$paymentEntity['error_code']??null;
            $errorDescription=
                $paymentEntity['error_description']??null;
            $errorSource=$paymentEntity['error_source']??null;
            $errorStep=$paymentEntity['error_step']??null;
            $errorReason=$paymentEntity['error_reason']??null;

            $updatePayment=$pdo->prepare(
                "UPDATE payments SET
                    razorpay_payment_id=:razorpay_payment_id,
                    payment_method=:payment_method,
                    status='failed',
                    gateway_status='failed',
                    error_code=:error_code,
                    error_description=:error_description,
                    error_source=:error_source,
                    error_step=:error_step,
                    error_reason=:error_reason,
                    gateway_response=:gateway_response,
                    failed_at=COALESCE(failed_at,NOW())
                 WHERE id=:payment_id
                   AND status<>'paid'"
            );

            $updatePayment->bindValue(
                ':razorpay_payment_id',
                $razorpayPaymentId,
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
                ':payment_method',
                $actualPaymentMethod,
                PDO::PARAM_STR
            );

            foreach([
                ':error_code'=>$errorCode,
                ':error_description'=>$errorDescription,
                ':error_source'=>$errorSource,
                ':error_step'=>$errorStep,
                ':error_reason'=>$errorReason
            ] as $key=>$value){
                $updatePayment->bindValue(
                    $key,
                    $value!==null
                        ?mb_substr((string)$value,0,500)
                        :null,
                    $value===null
                        ?PDO::PARAM_NULL
                        :PDO::PARAM_STR
                );
            }

            $updatePayment->bindValue(
                ':gateway_response',
                $gatewayResponse,
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
                ':payment_id',
                $paymentId,
                PDO::PARAM_INT
            );

            $updatePayment->execute();

            $updateOrder=$pdo->prepare(
                "UPDATE orders SET
                    cod_charge=0,
                    payment_method=:payment_method,
                    payment_status='failed'
                 WHERE id=:order_id
                   AND payment_status<>'paid'"
            );

            $updateOrder->bindValue(
                ':payment_method',
                $orderPaymentMethod,
                PDO::PARAM_STR
            );

            $updateOrder->bindValue(
                ':order_id',
                $orderId,
                PDO::PARAM_INT
            );

            $updateOrder->execute();

            if($oldStatus!=='failed'){
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
                        'webhook',
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

                $message=$errorDescription
                    ?mb_substr(
                        (string)$errorDescription,
                        0,
                        500
                    )
                    :'Razorpay payment failed webhook received.';

                $historyStmt->bindValue(
                    ':message',
                    $message,
                    PDO::PARAM_STR
                );

                $historyStmt->execute();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MARK WEBHOOK AS PROCESSED
    |--------------------------------------------------------------------------
    */

    $finishStmt=$pdo->prepare(
        "UPDATE payment_webhook_events SET
            process_status='processed',
            processed_at=NOW(),
            error_message=NULL
         WHERE id=:id"
    );

    $finishStmt->bindValue(
        ':id',
        $webhookEventId,
        PDO::PARAM_INT
    );

    $finishStmt->execute();

    $pdo->commit();

    webhookResponse(
        true,
        'Webhook processed successfully.',
        [
            'event_id'=>$eventId,
            'event_type'=>$eventType,
            'local_payment_id'=>$paymentId,
            'order_id'=>$orderId,
            'razorpay_order_id'=>$razorpayOrderId,
            'razorpay_payment_id'=>$razorpayPaymentId
        ],
        200
    );

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    if(isset($webhookEventId)){
        try{
            $failedStmt=$pdo->prepare(
                "UPDATE payment_webhook_events SET
                    process_status='failed',
                    error_message=:error_message,
                    processed_at=NOW()
                 WHERE id=:id"
            );

            $failedStmt->bindValue(
                ':error_message',
                mb_substr($e->getMessage(),0,500),
                PDO::PARAM_STR
            );

            $failedStmt->bindValue(
                ':id',
                $webhookEventId,
                PDO::PARAM_INT
            );

            $failedStmt->execute();
        }catch(Throwable $ignored){}
    }

    webhookResponse(
        false,
        'Unable to process payment webhook.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );

}catch(Throwable $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    if(isset($webhookEventId)){
        try{
            $failedStmt=$pdo->prepare(
                "UPDATE payment_webhook_events SET
                    process_status='failed',
                    error_message=:error_message,
                    processed_at=NOW()
                 WHERE id=:id"
            );

            $failedStmt->bindValue(
                ':error_message',
                mb_substr($e->getMessage(),0,500),
                PDO::PARAM_STR
            );

            $failedStmt->bindValue(
                ':id',
                $webhookEventId,
                PDO::PARAM_INT
            );

            $failedStmt->execute();
        }catch(Throwable $ignored){}
    }

    webhookResponse(
        false,
        'Payment webhook processing failed.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );
}