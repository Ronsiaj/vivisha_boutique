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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(204);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='GET'){
    sendResponse(false,'Only GET method is allowed.',null,405);
}

$decoded=authenticate();
validateJWTData($decoded);

$accountType=getAuthenticatedType($decoded);
$authenticatedId=getAuthenticatedId($decoded);

if($authenticatedId<=0){
    sendResponse(false,'Invalid authenticated account.',null,401);
}

if(!in_array($accountType,['admin','user'],true)){
    sendResponse(false,'Access denied.',null,403);
}

if($accountType==='admin'){
    $adminAuth=authenticateAdmin();
    checkAdminRole($adminAuth,['admin']);
}else{
    $userAuth=authenticateUser();
    $authenticatedId=getAuthenticatedId($userAuth);

    if($authenticatedId<=0){
        sendResponse(false,'Invalid authenticated user.',null,401);
    }
}

function paymentPositiveId(?string $value,string $field):?int{
    $value=trim((string)$value);

    if($value===''){
        return null;
    }

    if(!preg_match('/^[1-9][0-9]*$/',$value)){
        sendResponse(false,"{$field} must be a valid positive integer.",null,422);
    }

    return (int)$value;
}

function paymentAmount(?string $value,string $field):?float{
    $value=trim((string)$value);

    if($value===''){
        return null;
    }

    if(!is_numeric($value)||(float)$value<0){
        sendResponse(false,"{$field} must be a valid non-negative amount.",null,422);
    }

    return round((float)$value,2);
}

function paymentDate(?string $value,string $field):?string{
    $value=trim((string)$value);

    if($value===''){
        return null;
    }

    $date=DateTime::createFromFormat('Y-m-d',$value);
    $errors=DateTime::getLastErrors();

    if(
        !$date||
        ($errors!==false&&(
            $errors['warning_count']>0||
            $errors['error_count']>0
        ))||
        $date->format('Y-m-d')!==$value
    ){
        sendResponse(false,"{$field} must be in YYYY-MM-DD format.",null,422);
    }

    return $value;
}

$allowedParams=[
    'q',
    'id',
    'payment_id',
    'order_id',
    'user_id',
    'provider',
    'payment_method',
    'status',
    'gateway_status',
    'currency',
    'razorpay_order_id',
    'razorpay_payment_id',
    'receipt',
    'attempt_no',
    'min_amount',
    'max_amount',
    'created_from',
    'created_to',
    'paid_from',
    'paid_to',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach(array_keys($_GET) as $param){
    if(!in_array($param,$allowedParams,true)){
        sendResponse(false,"Invalid query parameter: {$param}.",[
            'allowed_parameters'=>$allowedParams
        ],422);
    }
}

$q=trim((string)($_GET['q']??''));

if($q!==''&&mb_strlen($q)>200){
    sendResponse(false,'q must not exceed 200 characters.',null,422);
}

$id=paymentPositiveId($_GET['id']??null,'id');
$paymentId=paymentPositiveId($_GET['payment_id']??null,'payment_id');

if($id!==null&&$paymentId!==null&&$id!==$paymentId){
    sendResponse(false,'id and payment_id cannot contain different values.',null,422);
}

$selectedPaymentId=$paymentId??$id;

$orderId=paymentPositiveId($_GET['order_id']??null,'order_id');
$userIdFilter=paymentPositiveId($_GET['user_id']??null,'user_id');
$attemptNo=paymentPositiveId($_GET['attempt_no']??null,'attempt_no');

if(
    $accountType==='user'&&
    $userIdFilter!==null&&
    $userIdFilter!==$authenticatedId
){
    sendResponse(false,'You can only view your own payments.',null,403);
}

$provider=strtolower(trim((string)($_GET['provider']??'')));

if($provider!==''&&!in_array($provider,['cod','razorpay'],true)){
    sendResponse(false,'Invalid provider.',[
        'allowed_values'=>['cod','razorpay']
    ],422);
}

$paymentMethod=strtolower(trim((string)($_GET['payment_method']??'')));

$allowedMethods=[
    'cod',
    'upi',
    'card',
    'netbanking',
    'wallet',
    'emi',
    'paylater',
    'other'
];

if(
    $paymentMethod!==''&&
    !in_array($paymentMethod,$allowedMethods,true)
){
    sendResponse(false,'Invalid payment_method.',[
        'allowed_values'=>$allowedMethods
    ],422);
}

$status=strtolower(trim((string)($_GET['status']??'')));

$allowedStatuses=[
    'pending',
    'initiated',
    'created',
    'authorized',
    'paid',
    'failed',
    'cancelled',
    'refunded',
    'partially_refunded'
];

if($status!==''&&!in_array($status,$allowedStatuses,true)){
    sendResponse(false,'Invalid status.',[
        'allowed_values'=>$allowedStatuses
    ],422);
}

$gatewayStatus=strtolower(trim((string)($_GET['gateway_status']??'')));

if(
    $gatewayStatus!==''&&
    (
        mb_strlen($gatewayStatus)>50||
        !preg_match('/^[a-z0-9_.-]+$/',$gatewayStatus)
    )
){
    sendResponse(false,'Invalid gateway_status.',null,422);
}

$currency=strtoupper(trim((string)($_GET['currency']??'')));

if(
    $currency!==''&&
    (
        mb_strlen($currency)>10||
        !preg_match('/^[A-Z]+$/',$currency)
    )
){
    sendResponse(false,'Invalid currency.',null,422);
}

$razorpayOrderId=trim((string)($_GET['razorpay_order_id']??''));
$razorpayPaymentId=trim((string)($_GET['razorpay_payment_id']??''));
$receipt=trim((string)($_GET['receipt']??''));

foreach([
    'razorpay_order_id'=>$razorpayOrderId,
    'razorpay_payment_id'=>$razorpayPaymentId,
    'receipt'=>$receipt
] as $field=>$value){
    if($value!==''&&mb_strlen($value)>150){
        sendResponse(false,"{$field} is too long.",null,422);
    }
}

$minAmount=paymentAmount($_GET['min_amount']??null,'min_amount');
$maxAmount=paymentAmount($_GET['max_amount']??null,'max_amount');

if(
    $minAmount!==null&&
    $maxAmount!==null&&
    $minAmount>$maxAmount
){
    sendResponse(false,'min_amount cannot be greater than max_amount.',null,422);
}

$createdFrom=paymentDate($_GET['created_from']??null,'created_from');
$createdTo=paymentDate($_GET['created_to']??null,'created_to');
$paidFrom=paymentDate($_GET['paid_from']??null,'paid_from');
$paidTo=paymentDate($_GET['paid_to']??null,'paid_to');

if(
    $createdFrom!==null&&
    $createdTo!==null&&
    $createdFrom>$createdTo
){
    sendResponse(false,'created_from cannot be greater than created_to.',null,422);
}

if(
    $paidFrom!==null&&
    $paidTo!==null&&
    $paidFrom>$paidTo
){
    sendResponse(false,'paid_from cannot be greater than paid_to.',null,422);
}

$pageInput=$_GET['page']??'1';
$limitInput=$_GET['limit']??'20';

if(
    filter_var($pageInput,FILTER_VALIDATE_INT)===false||
    (int)$pageInput<1
){
    sendResponse(false,'page must be a valid positive integer.',null,422);
}

if(
    filter_var($limitInput,FILTER_VALIDATE_INT)===false||
    (int)$limitInput<1||
    (int)$limitInput>100
){
    sendResponse(false,'limit must be between 1 and 100.',null,422);
}

$page=(int)$pageInput;
$limit=(int)$limitInput;
$offset=($page-1)*$limit;

$sortColumns=[
    'id'=>'p.id',
    'order_id'=>'p.order_id',
    'attempt_no'=>'p.attempt_no',
    'amount'=>'p.amount',
    'provider'=>'p.provider',
    'payment_method'=>'p.payment_method',
    'status'=>'p.status',
    'initiated_at'=>'p.initiated_at',
    'authorized_at'=>'p.authorized_at',
    'paid_at'=>'p.paid_at',
    'failed_at'=>'p.failed_at',
    'created_at'=>'p.created_at',
    'updated_at'=>'p.updated_at'
];

$sortBy=trim((string)($_GET['sort_by']??'created_at'));
$sortOrder=strtolower(trim((string)($_GET['sort_order']??'desc')));

if(!array_key_exists($sortBy,$sortColumns)){
    sendResponse(false,'Invalid sort_by.',[
        'allowed_values'=>array_keys($sortColumns)
    ],422);
}

if(!in_array($sortOrder,['asc','desc'],true)){
    sendResponse(false,'sort_order must be asc or desc.',null,422);
}

$sortColumn=$sortColumns[$sortBy];
$sqlSortOrder=strtoupper($sortOrder);

try{
    $where=[];
    $params=[];

    if($accountType==='user'){
        $where[]='p.user_id=:authenticated_user_id';
        $params[':authenticated_user_id']=$authenticatedId;
    }elseif($userIdFilter!==null){
        $where[]='p.user_id=:user_id';
        $params[':user_id']=$userIdFilter;
    }

    if($selectedPaymentId!==null){
        $where[]='p.id=:payment_id';
        $params[':payment_id']=$selectedPaymentId;
    }

    if($orderId!==null){
        $where[]='p.order_id=:order_id';
        $params[':order_id']=$orderId;
    }

    if($provider!==''){
        $where[]='p.provider=:provider';
        $params[':provider']=$provider;
    }

    if($paymentMethod!==''){
        $where[]='p.payment_method=:payment_method';
        $params[':payment_method']=$paymentMethod;
    }

    if($status!==''){
        $where[]='p.status=:status';
        $params[':status']=$status;
    }

    if($gatewayStatus!==''){
        $where[]='p.gateway_status=:gateway_status';
        $params[':gateway_status']=$gatewayStatus;
    }

    if($currency!==''){
        $where[]='p.currency=:currency';
        $params[':currency']=$currency;
    }

    if($razorpayOrderId!==''){
        $where[]='p.razorpay_order_id=:razorpay_order_id';
        $params[':razorpay_order_id']=$razorpayOrderId;
    }

    if($razorpayPaymentId!==''){
        $where[]='p.razorpay_payment_id=:razorpay_payment_id';
        $params[':razorpay_payment_id']=$razorpayPaymentId;
    }

    if($receipt!==''){
        $where[]='p.receipt=:receipt';
        $params[':receipt']=$receipt;
    }

    if($attemptNo!==null){
        $where[]='p.attempt_no=:attempt_no';
        $params[':attempt_no']=$attemptNo;
    }

    if($minAmount!==null){
        $where[]='p.amount>=:min_amount';
        $params[':min_amount']=number_format($minAmount,2,'.','');
    }

    if($maxAmount!==null){
        $where[]='p.amount<=:max_amount';
        $params[':max_amount']=number_format($maxAmount,2,'.','');
    }

    if($createdFrom!==null){
        $where[]='p.created_at>=:created_from';
        $params[':created_from']=$createdFrom.' 00:00:00';
    }

    if($createdTo!==null){
        $where[]='p.created_at<=:created_to';
        $params[':created_to']=$createdTo.' 23:59:59';
    }

    if($paidFrom!==null){
        $where[]='p.paid_at>=:paid_from';
        $params[':paid_from']=$paidFrom.' 00:00:00';
    }

    if($paidTo!==null){
        $where[]='p.paid_at<=:paid_to';
        $params[':paid_to']=$paidTo.' 23:59:59';
    }

    if($q!==''){
        $where[]="CONCAT_WS(' ',
            p.id,
            p.order_id,
            p.user_id,
            p.provider,
            p.attempt_no,
            p.razorpay_order_id,
            p.razorpay_payment_id,
            p.receipt,
            p.amount,
            p.amount_paise,
            p.currency,
            p.payment_method,
            p.status,
            p.gateway_status,
            p.bank,
            p.wallet,
            p.vpa,
            p.card_id,
            p.error_code,
            p.error_description,
            p.error_source,
            p.error_step,
            p.error_reason,
            o.order_number,
            o.coupon_type,
            o.coupon_code,
            o.payment_method,
            o.payment_status,
            o.order_status,
            u.name,
            u.mobile,
            u.email
        ) LIKE :q";

        $params[':q']='%'.$q.'%';
    }

    $whereSql=$where
        ?' WHERE '.implode(' AND ',$where)
        :'';

    $baseFrom="
        FROM payments p
        INNER JOIN orders o ON o.id=p.order_id
        INNER JOIN users u ON u.id=p.user_id
    ";

    $countStmt=$pdo->prepare(
        "SELECT COUNT(*)
         {$baseFrom}
         {$whereSql}"
    );

    foreach($params as $key=>$value){
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords=(int)$countStmt->fetchColumn();

    $totalPages=$totalRecords>0
        ?(int)ceil($totalRecords/$limit)
        :0;

    if($totalPages>0&&$page>$totalPages){
        sendResponse(false,'Requested page exceeds total available pages.',[
            'requested_page'=>$page,
            'total_pages'=>$totalPages
        ],422);
    }

    $summaryStmt=$pdo->prepare(
        "SELECT
            COUNT(*) AS total_payments,
            COALESCE(SUM(p.amount),0) AS total_amount,
            COALESCE(SUM(
                CASE WHEN p.status='paid' THEN p.amount ELSE 0 END
            ),0) AS paid_amount,
            COALESCE(SUM(
                CASE WHEN p.status='failed' THEN p.amount ELSE 0 END
            ),0) AS failed_amount,
            COALESCE(SUM(
                CASE WHEN p.provider='cod' THEN p.amount ELSE 0 END
            ),0) AS cod_amount,
            COALESCE(SUM(
                CASE WHEN p.provider='razorpay' THEN p.amount ELSE 0 END
            ),0) AS online_amount,
            COALESCE(SUM(p.fee),0) AS total_gateway_fee,
            COALESCE(SUM(p.tax),0) AS total_gateway_tax,
            SUM(CASE WHEN p.status='paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN p.status='failed' THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN p.status='pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN p.status='initiated' THEN 1 ELSE 0 END) AS initiated_count,
            SUM(CASE WHEN p.status='created' THEN 1 ELSE 0 END) AS created_count,
            SUM(CASE WHEN p.status='authorized' THEN 1 ELSE 0 END) AS authorized_count
         {$baseFrom}
         {$whereSql}"
    );

    foreach($params as $key=>$value){
        $summaryStmt->bindValue(
            $key,
            $value,
            is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();

    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC);

    $stmt=$pdo->prepare(
        "SELECT
            p.id,
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
            p.payment_method,
            p.status,
            p.gateway_status,
            p.bank,
            p.wallet,
            p.vpa,
            p.card_id,
            p.fee,
            p.tax,
            p.error_code,
            p.error_description,
            p.error_source,
            p.error_step,
            p.error_reason,
            p.notes,
            p.gateway_response,
            p.initiated_at,
            p.authorized_at,
            p.paid_at,
            p.failed_at,
            p.created_at,
            p.updated_at,

            o.order_number,
            o.cart_id,
            o.subtotal,
            o.product_discount_amount,
            o.coupon_type,
            o.coupon_id,
            o.coupon_code,
            o.coupon_discount_amount,
            o.shipping_charge,
            o.cod_charge,
            o.tax_amount AS order_tax_amount,
            o.grand_total,
            o.payment_method AS order_payment_method,
            o.payment_status AS order_payment_status,
            o.order_status,
            o.customer_note,
            o.cancel_reason,
            o.placed_at,
            o.confirmed_at,
            o.delivered_at,
            o.cancelled_at,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email,
            u.date_of_birth AS user_date_of_birth,
            u.status AS user_status

         {$baseFrom}
         {$whereSql}

         ORDER BY {$sortColumn} {$sqlSortOrder},p.id DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach($params as $key=>$value){
        $stmt->bindValue(
            $key,
            $value,
            is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
    $stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
    $stmt->execute();

    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

    if(!$rows){
        sendResponse(true,'Payments retrieved successfully.',[
            'payments'=>[],
            'summary'=>[
                'total_payments'=>0,
                'total_amount'=>'0.00',
                'paid_amount'=>'0.00',
                'failed_amount'=>'0.00',
                'cod_amount'=>'0.00',
                'online_amount'=>'0.00',
                'total_gateway_fee'=>'0.00',
                'total_gateway_tax'=>'0.00',
                'status_counts'=>[
                    'paid'=>0,
                    'failed'=>0,
                    'pending'=>0,
                    'initiated'=>0,
                    'created'=>0,
                    'authorized'=>0
                ]
            ],
            'pagination'=>[
                'page'=>$page,
                'limit'=>$limit,
                'total_records'=>0,
                'total_pages'=>0,
                'has_previous'=>false,
                'has_next'=>false
            ]
        ],200);
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS HISTORY
    |--------------------------------------------------------------------------
    */

    $paymentIds=array_map(
        static fn(array $row):int=>(int)$row['id'],
        $rows
    );

    $historyPlaceholders=[];

    foreach($paymentIds as $index=>$value){
        $historyPlaceholders[]=':history_payment_id_'.$index;
    }

    $historyInSql=implode(',',$historyPlaceholders);

    $historyStmt=$pdo->prepare(
        "SELECT
            id,
            payment_id,
            order_id,
            from_status,
            to_status,
            source,
            message,
            created_at
         FROM payment_status_history
         WHERE payment_id IN ({$historyInSql})
         ORDER BY payment_id ASC,id ASC"
    );

    foreach($paymentIds as $index=>$value){
        $historyStmt->bindValue(
            ':history_payment_id_'.$index,
            $value,
            PDO::PARAM_INT
        );
    }

    $historyStmt->execute();

    $historyMap=[];

    foreach($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $history){
        $pid=(int)$history['payment_id'];

        $historyMap[$pid][]=[
            'id'=>(int)$history['id'],
            'payment_id'=>$pid,
            'order_id'=>(int)$history['order_id'],
            'from_status'=>$history['from_status'],
            'to_status'=>$history['to_status'],
            'source'=>$history['source'],
            'message'=>$history['message'],
            'created_at'=>$history['created_at']
        ];
    }

    $payments=[];

    foreach($rows as $row){
        $pid=(int)$row['id'];

        $notes=null;

        if($row['notes']!==null&&$row['notes']!==''){
            $decodedNotes=json_decode((string)$row['notes'],true);

            $notes=json_last_error()===JSON_ERROR_NONE
                ?$decodedNotes
                :$row['notes'];
        }

        $gatewayResponse=null;

        if(
            $row['gateway_response']!==null&&
            $row['gateway_response']!==''
        ){
            $decodedGateway=json_decode(
                (string)$row['gateway_response'],
                true
            );

            $gatewayResponse=json_last_error()===JSON_ERROR_NONE
                ?$decodedGateway
                :$row['gateway_response'];
        }

        /*
         * Signature sensitive value.
         *
         * Admin response-க்கு மட்டும் raw signature.
         * User response-ல் expose பண்ண வேண்டாம்.
         */
        $razorpaySignature=$accountType==='admin'
            ?$row['razorpay_signature']
            :null;

        $payments[]=[
            'id'=>$pid,
            'order_id'=>(int)$row['order_id'],
            'user_id'=>(int)$row['user_id'],

            'payment'=>[
                'provider'=>$row['provider'],
                'attempt_no'=>(int)$row['attempt_no'],
                'payment_method'=>$row['payment_method'],
                'status'=>$row['status'],
                'gateway_status'=>$row['gateway_status'],
                'currency'=>$row['currency']
            ],

            'amounts'=>[
                'amount'=>$row['amount'],
                'amount_paise'=>(int)$row['amount_paise'],
                'gateway_fee'=>$row['fee'],
                'gateway_tax'=>$row['tax']
            ],

            'razorpay'=>[
                'order_id'=>$row['razorpay_order_id'],
                'payment_id'=>$row['razorpay_payment_id'],
                'signature'=>$razorpaySignature,
                'receipt'=>$row['receipt']
            ],

            'gateway_payment_details'=>[
                'bank'=>$row['bank'],
                'wallet'=>$row['wallet'],
                'vpa'=>$row['vpa'],
                'card_id'=>$row['card_id']
            ],

            'error'=>[
                'code'=>$row['error_code'],
                'description'=>$row['error_description'],
                'source'=>$row['error_source'],
                'step'=>$row['error_step'],
                'reason'=>$row['error_reason']
            ],

            'notes'=>$notes,

            /*
             * Admin can inspect full gateway response.
             * User-க்கு unnecessary gateway internals expose பண்ணவில்லை.
             */
            'gateway_response'=>$accountType==='admin'
                ?$gatewayResponse
                :null,

            'order'=>[
                'id'=>(int)$row['order_id'],
                'order_number'=>$row['order_number'],
                'cart_id'=>$row['cart_id']!==null
                    ?(int)$row['cart_id']
                    :null,

                'amounts'=>[
                    'subtotal'=>$row['subtotal'],
                    'product_discount_amount'=>
                        $row['product_discount_amount'],
                    'coupon_discount_amount'=>
                        $row['coupon_discount_amount'],
                    'shipping_charge'=>
                        $row['shipping_charge'],
                    'cod_charge'=>
                        $row['cod_charge'],
                    'tax_amount'=>
                        $row['order_tax_amount'],
                    'grand_total'=>
                        $row['grand_total']
                ],

                'coupon'=>[
                    'type'=>$row['coupon_type'],
                    'id'=>$row['coupon_id']!==null
                        ?(int)$row['coupon_id']
                        :null,
                    'code'=>$row['coupon_code'],
                    'discount_amount'=>
                        $row['coupon_discount_amount']
                ],

                'payment_method'=>
                    $row['order_payment_method'],

                'payment_status'=>
                    $row['order_payment_status'],

                'order_status'=>
                    $row['order_status'],

                'customer_note'=>
                    $row['customer_note'],

                'cancel_reason'=>
                    $row['cancel_reason'],

                'timeline'=>[
                    'placed_at'=>$row['placed_at'],
                    'confirmed_at'=>$row['confirmed_at'],
                    'delivered_at'=>$row['delivered_at'],
                    'cancelled_at'=>$row['cancelled_at']
                ]
            ],

            'user'=>[
                'id'=>(int)$row['user_id'],
                'name'=>$row['user_name'],
                'mobile'=>$row['user_mobile'],
                'email'=>$row['user_email'],
                'date_of_birth'=>$row['user_date_of_birth'],
                'status'=>$row['user_status']
            ],

            'status_history'=>$historyMap[$pid]??[],

            'timeline'=>[
                'initiated_at'=>$row['initiated_at'],
                'authorized_at'=>$row['authorized_at'],
                'paid_at'=>$row['paid_at'],
                'failed_at'=>$row['failed_at'],
                'created_at'=>$row['created_at'],
                'updated_at'=>$row['updated_at']
            ]
        ];
    }

    sendResponse(true,'Payments retrieved successfully.',[
        'payments'=>$payments,

        'summary'=>[
            'total_payments'=>
                (int)$summary['total_payments'],

            'total_amount'=>number_format(
                (float)$summary['total_amount'],
                2,
                '.',
                ''
            ),

            'paid_amount'=>number_format(
                (float)$summary['paid_amount'],
                2,
                '.',
                ''
            ),

            'failed_amount'=>number_format(
                (float)$summary['failed_amount'],
                2,
                '.',
                ''
            ),

            'cod_amount'=>number_format(
                (float)$summary['cod_amount'],
                2,
                '.',
                ''
            ),

            'online_amount'=>number_format(
                (float)$summary['online_amount'],
                2,
                '.',
                ''
            ),

            'total_gateway_fee'=>number_format(
                (float)$summary['total_gateway_fee'],
                2,
                '.',
                ''
            ),

            'total_gateway_tax'=>number_format(
                (float)$summary['total_gateway_tax'],
                2,
                '.',
                ''
            ),

            'status_counts'=>[
                'paid'=>(int)$summary['paid_count'],
                'failed'=>(int)$summary['failed_count'],
                'pending'=>(int)$summary['pending_count'],
                'initiated'=>(int)$summary['initiated_count'],
                'created'=>(int)$summary['created_count'],
                'authorized'=>(int)$summary['authorized_count']
            ]
        ],

        'pagination'=>[
            'page'=>$page,
            'limit'=>$limit,
            'total_records'=>$totalRecords,
            'total_pages'=>$totalPages,
            'has_previous'=>$page>1,
            'has_next'=>$page<$totalPages
        ],

        'filters'=>[
            'q'=>$q!==''?$q:null,
            'payment_id'=>$selectedPaymentId,
            'order_id'=>$orderId,
            'user_id'=>$accountType==='user'
                ?$authenticatedId
                :$userIdFilter,
            'provider'=>$provider!==''?$provider:null,
            'payment_method'=>$paymentMethod!==''?$paymentMethod:null,
            'status'=>$status!==''?$status:null,
            'gateway_status'=>$gatewayStatus!==''?$gatewayStatus:null,
            'currency'=>$currency!==''?$currency:null,
            'razorpay_order_id'=>$razorpayOrderId!==''?$razorpayOrderId:null,
            'razorpay_payment_id'=>$razorpayPaymentId!==''?$razorpayPaymentId:null,
            'receipt'=>$receipt!==''?$receipt:null,
            'attempt_no'=>$attemptNo,
            'min_amount'=>$minAmount,
            'max_amount'=>$maxAmount,
            'created_from'=>$createdFrom,
            'created_to'=>$createdTo,
            'paid_from'=>$paidFrom,
            'paid_to'=>$paidTo
        ],

        'sorting'=>[
            'sort_by'=>$sortBy,
            'sort_order'=>$sortOrder
        ],

        'access'=>[
            'account_type'=>$accountType,
            'scope'=>$accountType==='admin'
                ?'all_payments'
                :'own_payments_only'
        ]
    ],200);

}catch(PDOException $e){

    sendResponse(
        false,
        'Unable to retrieve payments.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );

}catch(Throwable $e){

    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?['error'=>$e->getMessage()]
            :null,
        500
    );
}