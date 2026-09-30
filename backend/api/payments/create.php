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

$allowedFields=['order_id','payment_method'];

foreach(array_keys($data) as $field){
    if(!in_array($field,$allowedFields,true)){
        sendResponse(false,"Invalid field: {$field}.",[
            'allowed_fields'=>$allowedFields
        ],422);
    }
}

$orderIdInput=trim((string)($data['order_id']??''));
$paymentMethod=strtolower(trim((string)($data['payment_method']??'')));

if(!preg_match('/^[1-9][0-9]*$/',$orderIdInput)){
    sendResponse(false,'order_id must be a valid positive integer.',null,422);
}

$allowedPaymentMethods=[
    'cod',
    'razorpay',
    'upi',
    'card',
    'netbanking',
    'wallet',
    'emi',
    'paylater'
];

if($paymentMethod===''){
    sendResponse(false,'payment_method is required.',[
        'allowed_values'=>$allowedPaymentMethods
    ],422);
}

if(!in_array($paymentMethod,$allowedPaymentMethods,true)){
    sendResponse(false,'Invalid payment_method.',[
        'allowed_values'=>$allowedPaymentMethods
    ],422);
}

$orderId=(int)$orderIdInput;
$codChargeFixed=100.00;

function paymentCreateFail(
    PDO $pdo,
    string $message,
    mixed $data=null,
    int $statusCode=422
):never{
    if($pdo->inTransaction()) $pdo->rollBack();
    sendResponse(false,$message,$data,$statusCode);
    exit;
}

try{
    $pdo->beginTransaction();

    $stmt=$pdo->prepare(
        "SELECT
            o.id,
            o.order_number,
            o.user_id,
            o.cart_id,
            o.subtotal,
            o.product_discount_amount,
            o.coupon_type,
            o.coupon_id,
            o.coupon_code,
            o.coupon_discount_amount,
            o.shipping_charge,
            o.cod_charge,
            o.tax_amount,
            o.grand_total,
            o.payment_method,
            o.payment_status,
            o.order_status,
            o.customer_note,
            o.placed_at,
            u.name AS user_name,
            u.email AS user_email,
            u.mobile AS user_mobile,
            u.status AS user_status
         FROM orders o
         INNER JOIN users u ON u.id=o.user_id
         WHERE o.id=:order_id
           AND o.user_id=:user_id
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $stmt->bindValue(':user_id',$userId,PDO::PARAM_INT);
    $stmt->execute();

    $order=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$order){
        paymentCreateFail($pdo,'Order not found.',null,404);
    }

    if($order['user_status']!=='active'){
        paymentCreateFail($pdo,'User account is not active.',null,403);
    }

    if($order['order_status']!=='pending'){
        paymentCreateFail($pdo,'Payment can only be selected for a pending order.',[
            'order_status'=>$order['order_status']
        ],409);
    }

    if($order['payment_status']==='paid'){
        paymentCreateFail($pdo,'This order is already paid.',[
            'order_id'=>$orderId,
            'payment_status'=>'paid'
        ],409);
    }

    $addressStmt=$pdo->prepare(
        "SELECT id
         FROM order_addresses
         WHERE order_id=:order_id
         LIMIT 1"
    );

    $addressStmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $addressStmt->execute();

    if(!$addressStmt->fetchColumn()){
        paymentCreateFail(
            $pdo,
            'Please select delivery address before selecting payment method.',
            null,
            409
        );
    }

    $itemStmt=$pdo->prepare(
        "SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(quantity),0) AS total_quantity
         FROM order_items
         WHERE order_id=:order_id
           AND item_status='active'"
    );

    $itemStmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $itemStmt->execute();

    $itemSummary=$itemStmt->fetch(PDO::FETCH_ASSOC);

    if(!$itemSummary||(int)$itemSummary['total_items']<=0){
        paymentCreateFail(
            $pdo,
            'Order does not contain any active items.',
            null,
            409
        );
    }

    $subtotal=round((float)$order['subtotal'],2);
    $productDiscount=round((float)$order['product_discount_amount'],2);
    $couponDiscount=round((float)$order['coupon_discount_amount'],2);
    $shippingCharge=round((float)$order['shipping_charge'],2);
    $taxAmount=round((float)$order['tax_amount'],2);

    if(
        $subtotal<0||
        $productDiscount<0||
        $couponDiscount<0||
        $shippingCharge<0||
        $taxAmount<0
    ){
        paymentCreateFail(
            $pdo,
            'Invalid order amount data.',
            null,
            422
        );
    }

    if($productDiscount>$subtotal){
        paymentCreateFail(
            $pdo,
            'Product discount cannot exceed subtotal.',
            null,
            422
        );
    }

    /*
     * IMPORTANT:
     *
     * Existing grand_total-ஐ வைத்து calculate பண்ணவில்லை.
     * அதனால் COD API multiple times call பண்ணினாலும்
     * ₹100 + ₹100 + ₹100 ஆகாது.
     */
    $amountAfterProductDiscount=round(
        $subtotal-$productDiscount,
        2
    );

    $amountWithTax=round(
        $amountAfterProductDiscount+$taxAmount,
        2
    );

    $amountBeforeCoupon=round(
        $amountWithTax+$shippingCharge,
        2
    );

    if($couponDiscount>$amountBeforeCoupon){
        paymentCreateFail(
            $pdo,
            'Coupon discount exceeds payable order amount.',
            null,
            422
        );
    }

    $basePayableAmount=round(
        $amountBeforeCoupon-$couponDiscount,
        2
    );

    if($basePayableAmount<=0){
        paymentCreateFail(
            $pdo,
            'Order payable amount must be greater than zero.',
            [
                'payable_amount'=>number_format(
                    $basePayableAmount,
                    2,
                    '.',
                    ''
                )
            ],
            422
        );
    }

    /*
     * Check successful payment separately.
     */
    $paidStmt=$pdo->prepare(
        "SELECT
            id,
            provider,
            payment_method,
            amount,
            status,
            razorpay_payment_id
         FROM payments
         WHERE order_id=:order_id
           AND status='paid'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $paidStmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $paidStmt->execute();

    $paidPayment=$paidStmt->fetch(PDO::FETCH_ASSOC);

    if($paidPayment){
        paymentCreateFail(
            $pdo,
            'This order already has a successful payment.',
            [
                'payment_id'=>(int)$paidPayment['id'],
                'status'=>'paid'
            ],
            409
        );
    }

    /*
     * Latest attempt.
     */
    $latestStmt=$pdo->prepare(
        "SELECT
            id,
            provider,
            attempt_no,
            razorpay_order_id,
            razorpay_payment_id,
            amount,
            amount_paise,
            payment_method,
            status,
            gateway_status
         FROM payments
         WHERE order_id=:order_id
         ORDER BY attempt_no DESC,id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $latestStmt->bindValue(':order_id',$orderId,PDO::PARAM_INT);
    $latestStmt->execute();

    $latestPayment=$latestStmt->fetch(PDO::FETCH_ASSOC);

    $attemptNo=$latestPayment
        ?((int)$latestPayment['attempt_no']+1)
        :1;

    /*
    |--------------------------------------------------------------------------
    | COD
    |--------------------------------------------------------------------------
    */

    if($paymentMethod==='cod'){

        /*
         * Already active Razorpay payment இருக்கும்போது
         * COD-க்கு switch செய்வதை block பண்ணுறோம்.
         *
         * பழைய Razorpay order customer மூலம் இன்னும் pay ஆக வாய்ப்பு
         * இருப்பதால் duplicate payment avoid ஆகும்.
         */
        if(
            $latestPayment&&
            $latestPayment['provider']==='razorpay'&&
            in_array(
                $latestPayment['status'],
                ['initiated','created','authorized'],
                true
            )
        ){
            paymentCreateFail(
                $pdo,
                'An online payment attempt is already active for this order.',
                [
                    'payment_id'=>(int)$latestPayment['id'],
                    'status'=>$latestPayment['status'],
                    'razorpay_order_id'=>$latestPayment['razorpay_order_id']
                ],
                409
            );
        }

        $codCharge=$codChargeFixed;

        $grandTotal=round(
            $basePayableAmount+$codCharge,
            2
        );

        /*
         * Existing pending COD record இருந்தா reuse.
         */
        if(
            $latestPayment&&
            $latestPayment['provider']==='cod'&&
            $latestPayment['status']==='pending'
        ){
            $paymentId=(int)$latestPayment['id'];

            $updatePayment=$pdo->prepare(
                "UPDATE payments SET
                    amount=:amount,
                    amount_paise=:amount_paise,
                    payment_method='cod'
                 WHERE id=:payment_id
                   AND user_id=:user_id"
            );

            $updatePayment->bindValue(
                ':amount',
                number_format($grandTotal,2,'.',''),
                PDO::PARAM_STR
            );

            $updatePayment->bindValue(
                ':amount_paise',
                (int)round($grandTotal*100),
                PDO::PARAM_INT
            );

            $updatePayment->bindValue(
                ':payment_id',
                $paymentId,
                PDO::PARAM_INT
            );

            $updatePayment->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $updatePayment->execute();

        }else{
            $insertPayment=$pdo->prepare(
                "INSERT INTO payments(
                    order_id,
                    user_id,
                    provider,
                    attempt_no,
                    amount,
                    amount_paise,
                    currency,
                    payment_method,
                    status,
                    initiated_at
                 )VALUES(
                    :order_id,
                    :user_id,
                    'cod',
                    :attempt_no,
                    :amount,
                    :amount_paise,
                    'INR',
                    'cod',
                    'pending',
                    NOW()
                 )"
            );

            $insertPayment->bindValue(
                ':order_id',
                $orderId,
                PDO::PARAM_INT
            );

            $insertPayment->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $insertPayment->bindValue(
                ':attempt_no',
                $attemptNo,
                PDO::PARAM_INT
            );

            $insertPayment->bindValue(
                ':amount',
                number_format($grandTotal,2,'.',''),
                PDO::PARAM_STR
            );

            $insertPayment->bindValue(
                ':amount_paise',
                (int)round($grandTotal*100),
                PDO::PARAM_INT
            );

            $insertPayment->execute();

            $paymentId=(int)$pdo->lastInsertId();

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
                    NULL,
                    'pending',
                    'create_api',
                    'Cash on delivery payment selected'
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

            $historyStmt->execute();
        }

        /*
         * COD ₹100 exactly once.
         */
        $orderUpdate=$pdo->prepare(
            "UPDATE orders SET
                cod_charge=:cod_charge,
                grand_total=:grand_total,
                payment_method='cod',
                payment_status='pending'
             WHERE id=:order_id
               AND user_id=:user_id
               AND payment_status<>'paid'"
        );

        $orderUpdate->bindValue(
            ':cod_charge',
            number_format($codCharge,2,'.',''),
            PDO::PARAM_STR
        );

        $orderUpdate->bindValue(
            ':grand_total',
            number_format($grandTotal,2,'.',''),
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

        $pdo->commit();

        sendResponse(
            true,
            'Cash on delivery selected successfully.',
            [
                'payment'=>[
                    'id'=>$paymentId,
                    'provider'=>'cod',
                    'selected_method'=>'cod',
                    'payment_method'=>'cod',
                    'status'=>'pending',
                    'online_payment'=>false
                ],

                'order'=>[
                    'id'=>$orderId,
                    'order_number'=>$order['order_number'],

                    'amount_breakdown'=>[
                        'subtotal'=>number_format(
                            $subtotal,
                            2,
                            '.',
                            ''
                        ),

                        'product_discount_amount'=>number_format(
                            $productDiscount,
                            2,
                            '.',
                            ''
                        ),

                        'tax_amount'=>number_format(
                            $taxAmount,
                            2,
                            '.',
                            ''
                        ),

                        'shipping_charge'=>number_format(
                            $shippingCharge,
                            2,
                            '.',
                            ''
                        ),

                        'coupon_discount_amount'=>number_format(
                            $couponDiscount,
                            2,
                            '.',
                            ''
                        ),

                        'amount_before_cod'=>number_format(
                            $basePayableAmount,
                            2,
                            '.',
                            ''
                        ),

                        'cod_charge'=>'100.00',

                        'grand_total'=>number_format(
                            $grandTotal,
                            2,
                            '.',
                            ''
                        )
                    ],

                    'payment_method'=>'cod',
                    'payment_status'=>'pending'
                ],

                'razorpay'=>null
            ],
            200
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ONLINE METHODS
    |--------------------------------------------------------------------------
    |
    | razorpay
    | upi
    | card
    | netbanking
    | wallet
    | emi
    | paylater
    |
    | All online payments use Razorpay as provider.
    |--------------------------------------------------------------------------
    */

    $onlineMethods=[
        'razorpay',
        'upi',
        'card',
        'netbanking',
        'wallet',
        'emi',
        'paylater'
    ];

    if(!in_array($paymentMethod,$onlineMethods,true)){
        paymentCreateFail(
            $pdo,
            'Unsupported online payment method.',
            null,
            422
        );
    }

    /*
     * Online payment-க்கு COD charge எப்போதும் ZERO.
     */
    $codCharge=0.00;

    $grandTotal=round(
        $basePayableAmount,
        2
    );

    $amountPaise=(int)round(
        $grandTotal*100
    );

    if($amountPaise<=0){
        paymentCreateFail(
            $pdo,
            'Invalid online payment amount.',
            null,
            422
        );
    }

    /*
     * payments.payment_method:
     *
     * Generic razorpay என்றால் NULL.
     * Actual method verify ஆன பிறகு update ஆகும்.
     *
     * User upi/card/etc explicitly select பண்ணினா
     * selected method temporarily store ஆகும்.
     */
    $selectedGatewayMethod=
        $paymentMethod==='razorpay'
            ?null
            :$paymentMethod;

    /*
     * Existing active Razorpay order இருந்தால் duplicate
     * gateway order create பண்ண வேண்டாம்.
     *
     * UPI -> Card போன்ற online method change ஆனாலும்
     * same Razorpay order amount reuse செய்யலாம்.
     */
    if(
        $latestPayment&&
        $latestPayment['provider']==='razorpay'&&
        in_array(
            $latestPayment['status'],
            ['initiated','created','authorized'],
            true
        )&&
        $latestPayment['razorpay_order_id']!==null&&
        round((float)$latestPayment['amount'],2)===$grandTotal
    ){
        $paymentId=(int)$latestPayment['id'];

        $paymentUpdate=$pdo->prepare(
            "UPDATE payments SET
                payment_method=:payment_method
             WHERE id=:payment_id
               AND user_id=:user_id
               AND status<>'paid'"
        );

        if($selectedGatewayMethod===null){
            $paymentUpdate->bindValue(
                ':payment_method',
                null,
                PDO::PARAM_NULL
            );
        }else{
            $paymentUpdate->bindValue(
                ':payment_method',
                $selectedGatewayMethod,
                PDO::PARAM_STR
            );
        }

        $paymentUpdate->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_INT
        );

        $paymentUpdate->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $paymentUpdate->execute();

        $orderUpdate=$pdo->prepare(
            "UPDATE orders SET
                cod_charge=0,
                grand_total=:grand_total,
                payment_method=:payment_method,
                payment_status='initiated'
             WHERE id=:order_id
               AND user_id=:user_id
               AND payment_status<>'paid'"
        );

        $orderUpdate->bindValue(
            ':grand_total',
            number_format($grandTotal,2,'.',''),
            PDO::PARAM_STR
        );

        $orderUpdate->bindValue(
            ':payment_method',
            $paymentMethod,
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

        $pdo->commit();

        sendResponse(
            true,
            'Existing Razorpay payment order retrieved successfully.',
            [
                'payment'=>[
                    'id'=>$paymentId,
                    'provider'=>'razorpay',
                    'selected_method'=>$paymentMethod,
                    'actual_payment_method'=>null,
                    'status'=>$latestPayment['status'],
                    'online_payment'=>true
                ],

                'razorpay'=>[
                    'key_id'=>$razorpayKeyId,
                    'order_id'=>$latestPayment['razorpay_order_id'],
                    'amount'=>$amountPaise,
                    'amount_rupees'=>number_format(
                        $grandTotal,
                        2,
                        '.',
                        ''
                    ),
                    'currency'=>'INR',
                    'preferred_method'=>$paymentMethod==='razorpay'
                        ?null
                        :$paymentMethod
                ],

                'order'=>[
                    'id'=>$orderId,
                    'order_number'=>$order['order_number'],
                    'cod_charge'=>'0.00',
                    'grand_total'=>number_format(
                        $grandTotal,
                        2,
                        '.',
                        ''
                    ),
                    'payment_method'=>$paymentMethod,
                    'payment_status'=>'initiated'
                ],

                'customer'=>[
                    'name'=>$order['user_name'],
                    'email'=>$order['user_email'],
                    'mobile'=>$order['user_mobile']
                ]
            ],
            200
        );
    }

    /*
     * Pending COD -> online switch.
     * COD has no gateway transaction, so safely cancel local COD attempt.
     */
    if(
        $latestPayment&&
        $latestPayment['provider']==='cod'&&
        $latestPayment['status']==='pending'
    ){
        $cancelCod=$pdo->prepare(
            "UPDATE payments SET
                status='cancelled'
             WHERE id=:payment_id
               AND user_id=:user_id
               AND status='pending'"
        );

        $cancelCod->bindValue(
            ':payment_id',
            (int)$latestPayment['id'],
            PDO::PARAM_INT
        );

        $cancelCod->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $cancelCod->execute();

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
                'pending',
                'cancelled',
                'create_api',
                'COD payment cancelled because user switched to online payment'
             )"
        );

        $historyStmt->bindValue(
            ':payment_id',
            (int)$latestPayment['id'],
            PDO::PARAM_INT
        );

        $historyStmt->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $historyStmt->execute();
    }

    /*
     * Local online payment attempt.
     */
    $receipt=substr(
        'VB_'.$order['order_number'].'_'.$attemptNo,
        0,
        40
    );

    $insertPayment=$pdo->prepare(
        "INSERT INTO payments(
            order_id,
            user_id,
            provider,
            attempt_no,
            receipt,
            amount,
            amount_paise,
            currency,
            payment_method,
            status,
            initiated_at
         )VALUES(
            :order_id,
            :user_id,
            'razorpay',
            :attempt_no,
            :receipt,
            :amount,
            :amount_paise,
            'INR',
            :payment_method,
            'initiated',
            NOW()
         )"
    );

    $insertPayment->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $insertPayment->bindValue(
        ':user_id',
        $userId,
        PDO::PARAM_INT
    );

    $insertPayment->bindValue(
        ':attempt_no',
        $attemptNo,
        PDO::PARAM_INT
    );

    $insertPayment->bindValue(
        ':receipt',
        $receipt,
        PDO::PARAM_STR
    );

    $insertPayment->bindValue(
        ':amount',
        number_format($grandTotal,2,'.',''),
        PDO::PARAM_STR
    );

    $insertPayment->bindValue(
        ':amount_paise',
        $amountPaise,
        PDO::PARAM_INT
    );

    if($selectedGatewayMethod===null){
        $insertPayment->bindValue(
            ':payment_method',
            null,
            PDO::PARAM_NULL
        );
    }else{
        $insertPayment->bindValue(
            ':payment_method',
            $selectedGatewayMethod,
            PDO::PARAM_STR
        );
    }

    $insertPayment->execute();

    $paymentId=(int)$pdo->lastInsertId();

    /*
     * Remove previous COD ₹100 when online selected.
     */
    $orderUpdate=$pdo->prepare(
        "UPDATE orders SET
            cod_charge=0,
            grand_total=:grand_total,
            payment_method=:payment_method,
            payment_status='initiated'
         WHERE id=:order_id
           AND user_id=:user_id
           AND payment_status<>'paid'"
    );

    $orderUpdate->bindValue(
        ':grand_total',
        number_format($grandTotal,2,'.',''),
        PDO::PARAM_STR
    );

    $orderUpdate->bindValue(
        ':payment_method',
        $paymentMethod,
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
            NULL,
            'initiated',
            'create_api',
            :message
         )"
    );

    $historyMessage=
        'Razorpay payment initiated. Selected method: '.
        $paymentMethod;

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
        ':message',
        $historyMessage,
        PDO::PARAM_STR
    );

    $historyStmt->execute();

    /*
     * Release DB transaction before external API request.
     */
    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | CREATE RAZORPAY ORDER
    |--------------------------------------------------------------------------
    */

    try{
        $razorpayOrder=$razorpayApi->order->create([
            'amount'=>$amountPaise,
            'currency'=>'INR',
            'receipt'=>$receipt,
            'partial_payment'=>false,
            'notes'=>[
                'local_order_id'=>(string)$orderId,
                'order_number'=>(string)$order['order_number'],
                'local_payment_id'=>(string)$paymentId,
                'selected_payment_method'=>$paymentMethod
            ]
        ]);

        $razorpayOrderId=(string)$razorpayOrder['id'];
        $gatewayStatus=(string)($razorpayOrder['status']??'created');

        $gatewayResponse=method_exists(
            $razorpayOrder,
            'toArray'
        )
            ?$razorpayOrder->toArray()
            :[];

        $paymentUpdate=$pdo->prepare(
            "UPDATE payments SET
                razorpay_order_id=:razorpay_order_id,
                status='created',
                gateway_status=:gateway_status,
                gateway_response=:gateway_response
             WHERE id=:payment_id
               AND user_id=:user_id"
        );

        $paymentUpdate->bindValue(
            ':razorpay_order_id',
            $razorpayOrderId,
            PDO::PARAM_STR
        );

        $paymentUpdate->bindValue(
            ':gateway_status',
            $gatewayStatus,
            PDO::PARAM_STR
        );

        $paymentUpdate->bindValue(
            ':gateway_response',
            json_encode(
                $gatewayResponse,
                JSON_UNESCAPED_UNICODE|
                JSON_UNESCAPED_SLASHES
            ),
            PDO::PARAM_STR
        );

        $paymentUpdate->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_INT
        );

        $paymentUpdate->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $paymentUpdate->execute();

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
                'initiated',
                'created',
                'create_api',
                'Razorpay order created successfully'
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

        $historyStmt->execute();

        sendResponse(
            true,
            'Online payment created successfully.',
            [
                'payment'=>[
                    'id'=>$paymentId,
                    'provider'=>'razorpay',
                    'attempt_no'=>$attemptNo,
                    'selected_method'=>$paymentMethod,
                    'actual_payment_method'=>null,
                    'status'=>'created',
                    'online_payment'=>true
                ],

                'razorpay'=>[
                    'key_id'=>$razorpayKeyId,
                    'order_id'=>$razorpayOrderId,
                    'amount'=>$amountPaise,
                    'amount_rupees'=>number_format(
                        $grandTotal,
                        2,
                        '.',
                        ''
                    ),
                    'currency'=>'INR',
                    'receipt'=>$receipt,

                    /*
                     * Frontend can use this to configure checkout UI.
                     */
                    'preferred_method'=>$paymentMethod==='razorpay'
                        ?null
                        :$paymentMethod
                ],

                'order'=>[
                    'id'=>$orderId,
                    'order_number'=>$order['order_number'],

                    'amount_breakdown'=>[
                        'subtotal'=>number_format(
                            $subtotal,
                            2,
                            '.',
                            ''
                        ),

                        'product_discount_amount'=>number_format(
                            $productDiscount,
                            2,
                            '.',
                            ''
                        ),

                        'tax_amount'=>number_format(
                            $taxAmount,
                            2,
                            '.',
                            ''
                        ),

                        'shipping_charge'=>number_format(
                            $shippingCharge,
                            2,
                            '.',
                            ''
                        ),

                        'coupon_discount_amount'=>number_format(
                            $couponDiscount,
                            2,
                            '.',
                            ''
                        ),

                        'cod_charge'=>'0.00',

                        'grand_total'=>number_format(
                            $grandTotal,
                            2,
                            '.',
                            ''
                        )
                    ],

                    'payment_method'=>$paymentMethod,
                    'payment_status'=>'initiated'
                ],

                'customer'=>[
                    'name'=>$order['user_name'],
                    'email'=>$order['user_email'],
                    'mobile'=>$order['user_mobile']
                ]
            ],
            201
        );

    }catch(Throwable $gatewayException){

        /*
         * Razorpay order create failed.
         */
        $failureStmt=$pdo->prepare(
            "UPDATE payments SET
                status='failed',
                gateway_status='failed',
                error_description=:error_description,
                failed_at=NOW()
             WHERE id=:payment_id
               AND status<>'paid'"
        );

        $failureStmt->bindValue(
            ':error_description',
            mb_substr(
                $gatewayException->getMessage(),
                0,
                500
            ),
            PDO::PARAM_STR
        );

        $failureStmt->bindValue(
            ':payment_id',
            $paymentId,
            PDO::PARAM_INT
        );

        $failureStmt->execute();

        $orderFailure=$pdo->prepare(
            "UPDATE orders SET
                payment_status='failed'
             WHERE id=:order_id
               AND user_id=:user_id
               AND payment_status<>'paid'"
        );

        $orderFailure->bindValue(
            ':order_id',
            $orderId,
            PDO::PARAM_INT
        );

        $orderFailure->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $orderFailure->execute();

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
                'initiated',
                'failed',
                'create_api',
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
            ':message',
            mb_substr(
                'Razorpay order creation failed: '.
                $gatewayException->getMessage(),
                0,
                500
            ),
            PDO::PARAM_STR
        );

        $historyStmt->execute();

        sendResponse(
            false,
            'Unable to create Razorpay payment order.',
            defined('APP_ENV')&&APP_ENV==='development'
                ?[
                    'payment_id'=>$paymentId,
                    'error'=>$gatewayException->getMessage()
                ]
                :[
                    'payment_id'=>$paymentId
                ],
            502
        );
    }

}catch(PDOException $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    sendResponse(
        false,
        'Unable to create payment.',
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
        'An unexpected error occurred.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );
}