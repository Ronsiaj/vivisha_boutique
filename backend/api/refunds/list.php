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

if(getAuthenticatedType($decoded)!=='admin'){
    sendResponse(false,'Admin access only.',null,403);
}

$adminAuth=authenticateAdmin();
checkAdminRole($adminAuth,['admin']);

$adminId=getAuthenticatedId($adminAuth);

if($adminId<=0){
    sendResponse(false,'Invalid authenticated admin.',null,401);
}

function refundListPositiveInt(
    mixed $value,
    string $field
):?int{
    if($value===null||$value===''){
        return null;
    }

    if(is_array($value)){
        sendResponse(false,"{$field} must be a single value.",null,422);
    }

    $value=trim((string)$value);

    if(!preg_match('/^[1-9][0-9]*$/',$value)){
        sendResponse(
            false,
            "{$field} must be a valid positive integer.",
            null,
            422
        );
    }

    return (int)$value;
}

function refundListAmount(
    mixed $value,
    string $field
):?string{
    if($value===null||$value===''){
        return null;
    }

    if(is_array($value)){
        sendResponse(false,"{$field} must be a single value.",null,422);
    }

    $value=trim((string)$value);

    if(
        !preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/',$value)
    ){
        sendResponse(
            false,
            "{$field} must be a valid non-negative amount with maximum 2 decimal places.",
            null,
            422
        );
    }

    return number_format((float)$value,2,'.','');
}

function refundListDate(
    mixed $value,
    string $field
):?string{
    if($value===null||$value===''){
        return null;
    }

    if(is_array($value)){
        sendResponse(false,"{$field} must be a single value.",null,422);
    }

    $value=trim((string)$value);

    $date=DateTime::createFromFormat('Y-m-d',$value);
    $errors=DateTime::getLastErrors();

    if(
        !$date||
        (
            $errors!==false&&
            (
                $errors['warning_count']>0||
                $errors['error_count']>0
            )
        )||
        $date->format('Y-m-d')!==$value
    ){
        sendResponse(
            false,
            "{$field} must be in YYYY-MM-DD format.",
            null,
            422
        );
    }

    return $value;
}

$allowedParams=[
    'q',
    'id',
    'refund_id',
    'order_id',
    'payment_id',
    'user_id',
    'refund_type',
    'refund_method',
    'contact_status',
    'status',
    'created_by_admin_id',
    'handled_by_admin_id',
    'min_amount',
    'max_amount',
    'created_from',
    'created_to',
    'refunded_from',
    'refunded_to',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach(array_keys($_GET) as $key){
    if(!in_array($key,$allowedParams,true)){
        sendResponse(false,"Invalid query parameter: {$key}.",[
            'allowed_parameters'=>$allowedParams
        ],422);
    }
}

foreach($_GET as $key=>$value){
    if(is_array($value)){
        sendResponse(false,"{$key} must be a single value.",null,422);
    }
}

$q=trim((string)($_GET['q']??''));

if(mb_strlen($q)>200){
    sendResponse(false,'q must not exceed 200 characters.',null,422);
}

$id=refundListPositiveInt($_GET['id']??null,'id');
$refundId=refundListPositiveInt($_GET['refund_id']??null,'refund_id');

if(
    $id!==null&&
    $refundId!==null&&
    $id!==$refundId
){
    sendResponse(
        false,
        'id and refund_id cannot contain different values.',
        null,
        422
    );
}

$selectedRefundId=$refundId??$id;

$orderId=refundListPositiveInt(
    $_GET['order_id']??null,
    'order_id'
);

$paymentId=refundListPositiveInt(
    $_GET['payment_id']??null,
    'payment_id'
);

$userId=refundListPositiveInt(
    $_GET['user_id']??null,
    'user_id'
);

$createdByAdminId=refundListPositiveInt(
    $_GET['created_by_admin_id']??null,
    'created_by_admin_id'
);

$handledByAdminId=refundListPositiveInt(
    $_GET['handled_by_admin_id']??null,
    'handled_by_admin_id'
);

$refundType=strtolower(
    trim((string)($_GET['refund_type']??''))
);

$allowedRefundTypes=[
    'full',
    'partial'
];

if(
    $refundType!==''&&
    !in_array($refundType,$allowedRefundTypes,true)
){
    sendResponse(false,'Invalid refund_type.',[
        'allowed_values'=>$allowedRefundTypes
    ],422);
}

$refundMethod=strtolower(
    trim((string)($_GET['refund_method']??''))
);

$allowedRefundMethods=[
    'upi',
    'bank_transfer',
    'cash',
    'razorpay',
    'other'
];

if(
    $refundMethod!==''&&
    !in_array($refundMethod,$allowedRefundMethods,true)
){
    sendResponse(false,'Invalid refund_method.',[
        'allowed_values'=>$allowedRefundMethods
    ],422);
}

$contactStatus=strtolower(
    trim((string)($_GET['contact_status']??''))
);

$allowedContactStatuses=[
    'not_contacted',
    'contacted',
    'no_response',
    'confirmed'
];

if(
    $contactStatus!==''&&
    !in_array($contactStatus,$allowedContactStatuses,true)
){
    sendResponse(false,'Invalid contact_status.',[
        'allowed_values'=>$allowedContactStatuses
    ],422);
}

$status=strtolower(
    trim((string)($_GET['status']??''))
);

$allowedStatuses=[
    'pending',
    'processing',
    'refunded',
    'rejected',
    'cancelled'
];

if(
    $status!==''&&
    !in_array($status,$allowedStatuses,true)
){
    sendResponse(false,'Invalid status.',[
        'allowed_values'=>$allowedStatuses
    ],422);
}

$minAmount=refundListAmount(
    $_GET['min_amount']??null,
    'min_amount'
);

$maxAmount=refundListAmount(
    $_GET['max_amount']??null,
    'max_amount'
);

if(
    $minAmount!==null&&
    $maxAmount!==null&&
    (float)$minAmount>(float)$maxAmount
){
    sendResponse(
        false,
        'min_amount cannot be greater than max_amount.',
        null,
        422
    );
}

$createdFrom=refundListDate(
    $_GET['created_from']??null,
    'created_from'
);

$createdTo=refundListDate(
    $_GET['created_to']??null,
    'created_to'
);

$refundedFrom=refundListDate(
    $_GET['refunded_from']??null,
    'refunded_from'
);

$refundedTo=refundListDate(
    $_GET['refunded_to']??null,
    'refunded_to'
);

if(
    $createdFrom!==null&&
    $createdTo!==null&&
    $createdFrom>$createdTo
){
    sendResponse(
        false,
        'created_from cannot be greater than created_to.',
        null,
        422
    );
}

if(
    $refundedFrom!==null&&
    $refundedTo!==null&&
    $refundedFrom>$refundedTo
){
    sendResponse(
        false,
        'refunded_from cannot be greater than refunded_to.',
        null,
        422
    );
}

$pageRaw=trim((string)($_GET['page']??'1'));
$limitRaw=trim((string)($_GET['limit']??'20'));

if(!preg_match('/^[1-9][0-9]*$/',$pageRaw)){
    sendResponse(false,'page must be a positive integer.',null,422);
}

if(!preg_match('/^[1-9][0-9]*$/',$limitRaw)){
    sendResponse(false,'limit must be a positive integer.',null,422);
}

$page=(int)$pageRaw;
$limit=(int)$limitRaw;

if($limit>100){
    sendResponse(false,'limit must not exceed 100.',null,422);
}

$offset=($page-1)*$limit;

$sortColumns=[
    'id'=>'mr.id',
    'order_id'=>'mr.order_id',
    'payment_id'=>'mr.payment_id',
    'user_id'=>'mr.user_id',
    'refund_amount'=>'mr.refund_amount',
    'refund_type'=>'mr.refund_type',
    'refund_method'=>'mr.refund_method',
    'status'=>'mr.status',
    'contact_status'=>'mr.contact_status',
    'contacted_at'=>'mr.contacted_at',
    'refunded_at'=>'mr.refunded_at',
    'created_at'=>'mr.created_at',
    'updated_at'=>'mr.updated_at'
];

$sortBy=trim(
    (string)($_GET['sort_by']??'created_at')
);

$sortOrder=strtolower(
    trim((string)($_GET['sort_order']??'desc'))
);

if(!array_key_exists($sortBy,$sortColumns)){
    sendResponse(false,'Invalid sort_by.',[
        'allowed_values'=>array_keys($sortColumns)
    ],422);
}

if(!in_array($sortOrder,['asc','desc'],true)){
    sendResponse(
        false,
        'sort_order must be asc or desc.',
        null,
        422
    );
}

$sqlSortColumn=$sortColumns[$sortBy];
$sqlSortOrder=strtoupper($sortOrder);

try{

    $where=[];
    $params=[];

    if($selectedRefundId!==null){
        $where[]='mr.id=:refund_id';
        $params[':refund_id']=$selectedRefundId;
    }

    if($orderId!==null){
        $where[]='mr.order_id=:order_id';
        $params[':order_id']=$orderId;
    }

    if($paymentId!==null){
        $where[]='mr.payment_id=:payment_id';
        $params[':payment_id']=$paymentId;
    }

    if($userId!==null){
        $where[]='mr.user_id=:user_id';
        $params[':user_id']=$userId;
    }

    if($refundType!==''){
        $where[]='mr.refund_type=:refund_type';
        $params[':refund_type']=$refundType;
    }

    if($refundMethod!==''){
        $where[]='mr.refund_method=:refund_method';
        $params[':refund_method']=$refundMethod;
    }

    if($contactStatus!==''){
        $where[]='mr.contact_status=:contact_status';
        $params[':contact_status']=$contactStatus;
    }

    if($status!==''){
        $where[]='mr.status=:status';
        $params[':status']=$status;
    }

    if($createdByAdminId!==null){
        $where[]='mr.created_by_admin_id=:created_by_admin_id';
        $params[':created_by_admin_id']=$createdByAdminId;
    }

    if($handledByAdminId!==null){
        $where[]='mr.handled_by_admin_id=:handled_by_admin_id';
        $params[':handled_by_admin_id']=$handledByAdminId;
    }

    if($minAmount!==null){
        $where[]='mr.refund_amount>=:min_amount';
        $params[':min_amount']=$minAmount;
    }

    if($maxAmount!==null){
        $where[]='mr.refund_amount<=:max_amount';
        $params[':max_amount']=$maxAmount;
    }

    if($createdFrom!==null){
        $where[]='mr.created_at>=:created_from';
        $params[':created_from']=$createdFrom.' 00:00:00';
    }

    if($createdTo!==null){
        $where[]='mr.created_at<=:created_to';
        $params[':created_to']=$createdTo.' 23:59:59';
    }

    if($refundedFrom!==null){
        $where[]='mr.refunded_at>=:refunded_from';
        $params[':refunded_from']=$refundedFrom.' 00:00:00';
    }

    if($refundedTo!==null){
        $where[]='mr.refunded_at<=:refunded_to';
        $params[':refunded_to']=$refundedTo.' 23:59:59';
    }

    if($q!==''){
        $where[]="
            CONCAT_WS(
                ' ',
                mr.id,
                mr.order_id,
                mr.payment_id,
                mr.user_id,
                mr.refund_type,
                mr.refund_amount,
                mr.refund_method,
                mr.refund_reference,
                mr.reason,
                mr.admin_note,
                mr.customer_mobile,
                mr.contact_status,
                mr.status,
                o.order_number,
                o.payment_method,
                o.payment_status,
                o.order_status,
                p.provider,
                p.payment_method,
                p.razorpay_order_id,
                p.razorpay_payment_id,
                u.name,
                u.mobile,
                u.email
            ) LIKE :search_q
        ";

        $params[':search_q']='%'.$q.'%';
    }

    $whereSql=$where
        ?' WHERE '.implode(' AND ',$where)
        :'';

    $baseFrom="
        FROM manual_refunds mr

        INNER JOIN orders o
            ON o.id=mr.order_id

        INNER JOIN users u
            ON u.id=mr.user_id

        LEFT JOIN payments p
            ON p.id=mr.payment_id
    ";

    /*
    |--------------------------------------------------------------------------
    | TOTAL RECORD COUNT
    |--------------------------------------------------------------------------
    */

    $countStmt=$pdo->prepare(
        "SELECT COUNT(*)
         {$baseFrom}
         {$whereSql}"
    );

    foreach($params as $key=>$value){
        $countStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $countStmt->execute();

    $totalRecords=(int)$countStmt->fetchColumn();

    $totalPages=$totalRecords>0
        ?(int)ceil($totalRecords/$limit)
        :0;

    if(
        $totalPages>0&&
        $page>$totalPages
    ){
        sendResponse(
            false,
            'Requested page exceeds available pages.',
            [
                'requested_page'=>$page,
                'total_pages'=>$totalPages
            ],
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    $summaryStmt=$pdo->prepare(
        "SELECT
            COUNT(*) AS total_refund_records,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='refunded'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS refunded_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pending_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='processing'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS processing_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='rejected'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS rejected_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='cancelled'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS cancelled_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.refund_type='full'
                         AND mr.status='refunded'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS full_refund_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.refund_type='partial'
                         AND mr.status='refunded'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS partial_refund_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='refunded'
                        THEN mr.refund_amount
                        ELSE 0
                    END
                ),
                0
            ) AS total_refunded_amount,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='pending'
                        THEN mr.refund_amount
                        ELSE 0
                    END
                ),
                0
            ) AS pending_refund_amount,

            COALESCE(
                SUM(
                    CASE
                        WHEN mr.status='processing'
                        THEN mr.refund_amount
                        ELSE 0
                    END
                ),
                0
            ) AS processing_refund_amount,

            COALESCE(
                AVG(
                    CASE
                        WHEN mr.status='refunded'
                        THEN mr.refund_amount
                        ELSE NULL
                    END
                ),
                0
            ) AS average_refund_amount

         {$baseFrom}
         {$whereSql}"
    );

    foreach($params as $key=>$value){
        $summaryStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();

    $summary=$summaryStmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | REFUND LIST
    |--------------------------------------------------------------------------
    */

    $listStmt=$pdo->prepare(
        "SELECT
            mr.id,
            mr.order_id,
            mr.payment_id,
            mr.user_id,

            mr.refund_type,
            mr.refund_amount,
            mr.refund_method,
            mr.refund_reference,

            mr.reason,
            mr.admin_note,
            mr.customer_mobile,

            mr.contact_status,
            mr.status,

            mr.created_by_admin_id,
            mr.handled_by_admin_id,

            mr.contacted_at,
            mr.processing_at,
            mr.refunded_at,
            mr.rejected_at,
            mr.cancelled_at,

            mr.created_at,
            mr.updated_at,

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
            o.order_status,
            o.placed_at,
            o.confirmed_at,
            o.delivered_at,
            o.cancelled_at AS order_cancelled_at,

            p.provider AS payment_provider,
            p.attempt_no AS payment_attempt_no,
            p.razorpay_order_id,
            p.razorpay_payment_id,
            p.amount AS payment_amount,
            p.amount_paise,
            p.currency,
            p.payment_method AS payment_method,
            p.status AS payment_status,
            p.gateway_status,
            p.paid_at,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email,
            u.status AS user_status

         {$baseFrom}
         {$whereSql}

         ORDER BY
            {$sqlSortColumn} {$sqlSortOrder},
            mr.id DESC

         LIMIT :limit
         OFFSET :offset"
    );

    foreach($params as $key=>$value){
        $listStmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $listStmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $listStmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $listStmt->execute();

    $rows=$listStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | ORDER-WISE REFUND TOTALS
    |--------------------------------------------------------------------------
    |
    | This allows each row to show:
    |
    | original payment amount
    | current refund
    | total refunded for order/payment
    | remaining refundable amount
    |--------------------------------------------------------------------------
    */

    $orderPaymentPairs=[];

    foreach($rows as $row){
        if($row['payment_id']===null){
            continue;
        }

        $key=
            (int)$row['order_id'].'_'.
            (int)$row['payment_id'];

        $orderPaymentPairs[$key]=[
            'order_id'=>(int)$row['order_id'],
            'payment_id'=>(int)$row['payment_id']
        ];
    }

    $refundTotals=[];

    foreach($orderPaymentPairs as $key=>$pair){

        $totalStmt=$pdo->prepare(
            "SELECT
                COALESCE(
                    SUM(refund_amount),
                    0
                ) AS total_refunded_amount,

                COUNT(*) AS completed_refund_count

             FROM manual_refunds

             WHERE order_id=:order_id
               AND payment_id=:payment_id
               AND status='refunded'"
        );

        $totalStmt->bindValue(
            ':order_id',
            $pair['order_id'],
            PDO::PARAM_INT
        );

        $totalStmt->bindValue(
            ':payment_id',
            $pair['payment_id'],
            PDO::PARAM_INT
        );

        $totalStmt->execute();

        $refundTotals[$key]=$totalStmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT RESPONSE
    |--------------------------------------------------------------------------
    */

    $refunds=[];

    foreach($rows as $row){

        $paymentAmount=
            $row['payment_amount']!==null
                ?(float)$row['payment_amount']
                :0.00;

        $totalRefundedAmount=0.00;
        $completedRefundCount=0;

        if($row['payment_id']!==null){
            $pairKey=
                (int)$row['order_id'].'_'.
                (int)$row['payment_id'];

            if(isset($refundTotals[$pairKey])){
                $totalRefundedAmount=
                    (float)$refundTotals[$pairKey]['total_refunded_amount'];

                $completedRefundCount=
                    (int)$refundTotals[$pairKey]['completed_refund_count'];
            }
        }

        $remainingRefundable=max(
            0,
            $paymentAmount-$totalRefundedAmount
        );

        $refunds[]=[
            'id'=>(int)$row['id'],

            'refund'=>[
                'type'=>$row['refund_type'],
                'amount'=>$row['refund_amount'],
                'method'=>$row['refund_method'],
                'reference'=>$row['refund_reference'],
                'reason'=>$row['reason'],
                'admin_note'=>$row['admin_note'],
                'customer_mobile'=>$row['customer_mobile'],
                'contact_status'=>$row['contact_status'],
                'status'=>$row['status']
            ],

            'refund_calculation'=>[
                'original_payment_amount'=>
                    number_format(
                        $paymentAmount,
                        2,
                        '.',
                        ''
                    ),

                'current_refund_amount'=>
                    $row['refund_amount'],

                'total_refunded_amount'=>
                    number_format(
                        $totalRefundedAmount,
                        2,
                        '.',
                        ''
                    ),

                'remaining_refundable_amount'=>
                    number_format(
                        $remainingRefundable,
                        2,
                        '.',
                        ''
                    ),

                'completed_refund_count'=>
                    $completedRefundCount,

                'fully_refunded'=>
                    $paymentAmount>0&&
                    $remainingRefundable<=0
            ],

            'order'=>[
                'id'=>(int)$row['order_id'],
                'order_number'=>$row['order_number'],

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
                        $row['tax_amount'],

                    'grand_total'=>
                        $row['grand_total']
                ],

                'payment_method'=>
                    $row['order_payment_method'],

                'payment_status'=>
                    $row['order_payment_status'],

                'order_status'=>
                    $row['order_status'],

                'timeline'=>[
                    'placed_at'=>
                        $row['placed_at'],

                    'confirmed_at'=>
                        $row['confirmed_at'],

                    'delivered_at'=>
                        $row['delivered_at'],

                    'cancelled_at'=>
                        $row['order_cancelled_at']
                ]
            ],

            'payment'=>$row['payment_id']!==null?[
                'id'=>(int)$row['payment_id'],

                'provider'=>
                    $row['payment_provider'],

                'attempt_no'=>
                    $row['payment_attempt_no']!==null
                        ?(int)$row['payment_attempt_no']
                        :null,

                'razorpay_order_id'=>
                    $row['razorpay_order_id'],

                'razorpay_payment_id'=>
                    $row['razorpay_payment_id'],

                'amount'=>
                    $row['payment_amount'],

                'amount_paise'=>
                    $row['amount_paise']!==null
                        ?(int)$row['amount_paise']
                        :null,

                'currency'=>
                    $row['currency'],

                'payment_method'=>
                    $row['payment_method'],

                'status'=>
                    $row['payment_status'],

                'gateway_status'=>
                    $row['gateway_status'],

                'paid_at'=>
                    $row['paid_at']

            ]:null,

            'customer'=>[
                'id'=>(int)$row['user_id'],
                'name'=>$row['user_name'],
                'mobile'=>$row['user_mobile'],
                'email'=>$row['user_email'],
                'status'=>$row['user_status']
            ],

            'admin'=>[
                'created_by_admin_id'=>
                    (int)$row['created_by_admin_id'],

                'handled_by_admin_id'=>
                    $row['handled_by_admin_id']!==null
                        ?(int)$row['handled_by_admin_id']
                        :null
            ],

            'timeline'=>[
                'contacted_at'=>$row['contacted_at'],
                'processing_at'=>$row['processing_at'],
                'refunded_at'=>$row['refunded_at'],
                'rejected_at'=>$row['rejected_at'],
                'cancelled_at'=>$row['cancelled_at'],
                'created_at'=>$row['created_at'],
                'updated_at'=>$row['updated_at']
            ]
        ];
    }

    sendResponse(
        true,
        'Refunds retrieved successfully.',
        [
            'summary'=>[
                'total_refund_records'=>
                    (int)$summary['total_refund_records'],

                'completed_refunds'=>
                    (int)$summary['refunded_count'],

                'full_refunds'=>
                    (int)$summary['full_refund_count'],

                'partial_refunds'=>
                    (int)$summary['partial_refund_count'],

                'pending_refunds'=>
                    (int)$summary['pending_count'],

                'processing_refunds'=>
                    (int)$summary['processing_count'],

                'rejected_refunds'=>
                    (int)$summary['rejected_count'],

                'cancelled_refunds'=>
                    (int)$summary['cancelled_count'],

                'total_refunded_amount'=>
                    number_format(
                        (float)$summary['total_refunded_amount'],
                        2,
                        '.',
                        ''
                    ),

                'pending_refund_amount'=>
                    number_format(
                        (float)$summary['pending_refund_amount'],
                        2,
                        '.',
                        ''
                    ),

                'processing_refund_amount'=>
                    number_format(
                        (float)$summary['processing_refund_amount'],
                        2,
                        '.',
                        ''
                    ),

                'average_refund_amount'=>
                    number_format(
                        (float)$summary['average_refund_amount'],
                        2,
                        '.',
                        ''
                    )
            ],

            'refunds'=>$refunds,

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

                'refund_id'=>
                    $selectedRefundId,

                'order_id'=>
                    $orderId,

                'payment_id'=>
                    $paymentId,

                'user_id'=>
                    $userId,

                'refund_type'=>
                    $refundType!==''?$refundType:null,

                'refund_method'=>
                    $refundMethod!==''?$refundMethod:null,

                'contact_status'=>
                    $contactStatus!==''?$contactStatus:null,

                'status'=>
                    $status!==''?$status:null,

                'created_by_admin_id'=>
                    $createdByAdminId,

                'handled_by_admin_id'=>
                    $handledByAdminId,

                'min_amount'=>
                    $minAmount,

                'max_amount'=>
                    $maxAmount,

                'created_from'=>
                    $createdFrom,

                'created_to'=>
                    $createdTo,

                'refunded_from'=>
                    $refundedFrom,

                'refunded_to'=>
                    $refundedTo
            ],

            'sorting'=>[
                'sort_by'=>$sortBy,
                'sort_order'=>$sortOrder
            ],

            'access'=>[
                'account_type'=>'admin',
                'admin_id'=>$adminId
            ]
        ],
        200
    );

}catch(PDOException $e){

    sendResponse(
        false,
        'Unable to retrieve refunds.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );

}catch(Throwable $e){

    sendResponse(
        false,
        'An unexpected error occurred.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}