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

$allowedParams=['id','payment_id'];

foreach(array_keys($_GET) as $param){
    if(!in_array($param,$allowedParams,true)){
        sendResponse(false,"Invalid query parameter: {$param}.",[
            'allowed_parameters'=>$allowedParams
        ],422);
    }
}

$idInput=trim((string)($_GET['id']??''));
$paymentIdInput=trim((string)($_GET['payment_id']??''));

if($idInput===''&&$paymentIdInput===''){
    sendResponse(false,'id or payment_id is required.',null,422);
}

if(
    $idInput!==''&&
    !preg_match('/^[1-9][0-9]*$/',$idInput)
){
    sendResponse(false,'id must be a valid positive integer.',null,422);
}

if(
    $paymentIdInput!==''&&
    !preg_match('/^[1-9][0-9]*$/',$paymentIdInput)
){
    sendResponse(false,'payment_id must be a valid positive integer.',null,422);
}

if(
    $idInput!==''&&
    $paymentIdInput!==''&&
    (int)$idInput!==(int)$paymentIdInput
){
    sendResponse(
        false,
        'id and payment_id cannot contain different values.',
        null,
        422
    );
}

$paymentId=(int)(
    $paymentIdInput!==''?$paymentIdInput:$idInput
);

try{

    /*
    |--------------------------------------------------------------------------
    | PAYMENT + ORDER + USER
    |--------------------------------------------------------------------------
    */

    $where="
        p.id=:payment_id
    ";

    if($accountType==='user'){
        $where.="
            AND p.user_id=:authenticated_user_id
            AND o.user_id=:authenticated_order_user_id
        ";
    }

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
            o.created_at AS order_created_at,
            o.updated_at AS order_updated_at,

            u.name AS user_name,
            u.mobile AS user_mobile,
            u.email AS user_email,
            u.date_of_birth AS user_date_of_birth,
            u.status AS user_status,
            u.last_login AS user_last_login,
            u.created_at AS user_created_at,
            u.updated_at AS user_updated_at,

            bc.coupon_code AS birthday_coupon_code,
            bc.title AS birthday_title,
            bc.description AS birthday_description,
            bc.discount_type AS birthday_discount_type,
            bc.discount_value AS birthday_discount_value,
            bc.min_order_amount AS birthday_min_order_amount,
            bc.max_discount_amount AS birthday_max_discount_amount,
            bc.status AS birthday_coupon_status,

            fc.festival_name,
            fc.coupon_code AS festival_coupon_code,
            fc.title AS festival_title,
            fc.description AS festival_description,
            fc.discount_type AS festival_discount_type,
            fc.discount_value AS festival_discount_value,
            fc.min_order_amount AS festival_min_order_amount,
            fc.max_discount_amount AS festival_max_discount_amount,
            fc.start_at AS festival_start_at,
            fc.end_at AS festival_end_at,
            fc.status AS festival_coupon_status,

            rc.referral_code,
            rc.title AS referral_title,
            rc.description AS referral_description,
            rc.discount_type AS referral_discount_type,
            rc.discount_value AS referral_discount_value,
            rc.min_order_amount AS referral_min_order_amount,
            rc.max_discount_amount AS referral_max_discount_amount,
            rc.start_at AS referral_start_at,
            rc.end_at AS referral_end_at,
            rc.status AS referral_coupon_status,

            foc.coupon_code AS first_order_coupon_code,
            foc.title AS first_order_title,
            foc.description AS first_order_description,
            foc.discount_type AS first_order_discount_type,
            foc.discount_value AS first_order_discount_value,
            foc.min_order_amount AS first_order_min_order_amount,
            foc.max_discount_amount AS first_order_max_discount_amount,
            foc.start_at AS first_order_start_at,
            foc.end_at AS first_order_end_at,
            foc.status AS first_order_coupon_status

         FROM payments p

         INNER JOIN orders o
            ON o.id=p.order_id

         INNER JOIN users u
            ON u.id=p.user_id

         LEFT JOIN birthday_coupons bc
            ON o.coupon_type='birthday'
           AND bc.id=o.coupon_id

         LEFT JOIN festival_coupons fc
            ON o.coupon_type='festival'
           AND fc.id=o.coupon_id

         LEFT JOIN referral_coupons rc
            ON o.coupon_type='referral'
           AND rc.id=o.coupon_id

         LEFT JOIN first_order_coupons foc
            ON o.coupon_type='first_order'
           AND foc.id=o.coupon_id

         WHERE {$where}

         LIMIT 1"
    );

    $stmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    if($accountType==='user'){
        $stmt->bindValue(
            ':authenticated_user_id',
            $authenticatedId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':authenticated_order_user_id',
            $authenticatedId,
            PDO::PARAM_INT
        );
    }

    $stmt->execute();

    $row=$stmt->fetch(PDO::FETCH_ASSOC);

    if(!$row){
        sendResponse(
            false,
            'Payment not found.',
            null,
            404
        );
    }

    $orderId=(int)$row['order_id'];

    /*
    |--------------------------------------------------------------------------
    | ORDER ADDRESS
    |--------------------------------------------------------------------------
    */

    $addressStmt=$pdo->prepare(
        "SELECT
            id,
            order_id,
            user_address_id,
            address_type,
            door_no,
            street,
            area,
            city,
            district,
            state,
            pincode,
            landmark,
            created_at
         FROM order_addresses
         WHERE order_id=:order_id
         ORDER BY id ASC"
    );

    $addressStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $addressStmt->execute();

    $addressRows=$addressStmt->fetchAll(PDO::FETCH_ASSOC);

    $addresses=[];

    foreach($addressRows as $address){
        $addresses[]=[
            'id'=>(int)$address['id'],
            'order_id'=>(int)$address['order_id'],
            'user_address_id'=>$address['user_address_id']!==null
                ?(int)$address['user_address_id']
                :null,

            'address_type'=>$address['address_type'],
            'door_no'=>$address['door_no'],
            'street'=>$address['street'],
            'area'=>$address['area'],
            'city'=>$address['city'],
            'district'=>$address['district'],
            'state'=>$address['state'],
            'pincode'=>$address['pincode'],
            'landmark'=>$address['landmark'],
            'created_at'=>$address['created_at']
        ];
    }

    $selectedAddress=$addresses[0]??null;

    /*
    |--------------------------------------------------------------------------
    | ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $itemStmt=$pdo->prepare(
        "SELECT
            oi.id,
            oi.order_id,
            oi.product_id,
            oi.variant_id,

            oi.product_name,
            oi.variant_name,
            oi.sku,
            oi.hsn_code,
            oi.size_name,
            oi.color_name,

            oi.original_price,
            oi.selling_price,
            oi.quantity,

            oi.product_discount_amount,
            oi.line_subtotal,
            oi.taxable_amount,

            oi.gst_rate,
            oi.cgst_rate,
            oi.cgst_amount,
            oi.sgst_rate,
            oi.sgst_amount,
            oi.igst_rate,
            oi.igst_amount,
            oi.tax_amount,

            oi.line_total,
            oi.item_status,
            oi.created_at,
            oi.updated_at,

            p.name AS current_product_name,
            p.slug AS current_product_slug,
            p.description AS current_product_description,
            p.category_id,
            p.hsn_profile_id,
            p.status AS current_product_status,

            c.name AS category_name,
            c.slug AS category_slug,
            c.image AS category_image,
            c.status AS category_status,

            hp.name AS hsn_profile_name,
            hp.hsn_code AS current_hsn_code,
            hp.status AS hsn_status,

            pv.size_id,
            pv.color_id,
            pv.sku AS current_sku,
            pv.variant_name AS current_variant_name,
            pv.original_price AS current_original_price,
            pv.discount_type AS current_discount_type,
            pv.discount_value AS current_discount_value,
            pv.gst_rate AS current_gst_rate,
            pv.gst_amount AS current_gst_amount,
            pv.price_with_tax AS current_price_with_tax,
            pv.selling_price AS current_selling_price,
            pv.stock_quantity,
            pv.reserved_quantity,
            pv.low_stock_limit,
            pv.is_available,

            s.name AS current_size_name,
            s.status AS size_status,

            co.name AS current_color_name,
            co.hex_code,
            co.status AS color_status

         FROM order_items oi

         LEFT JOIN products p
            ON p.id=oi.product_id

         LEFT JOIN categories c
            ON c.id=p.category_id

         LEFT JOIN hsn_profiles hp
            ON hp.id=p.hsn_profile_id

         LEFT JOIN product_variants pv
            ON pv.id=oi.variant_id
           AND (
                oi.product_id IS NULL
                OR pv.product_id=oi.product_id
           )

         LEFT JOIN sizes s
            ON s.id=pv.size_id

         LEFT JOIN colors co
            ON co.id=pv.color_id

         WHERE oi.order_id=:order_id

         ORDER BY oi.id ASC"
    );

    $itemStmt->bindValue(
        ':order_id',
        $orderId,
        PDO::PARAM_INT
    );

    $itemStmt->execute();

    $itemRows=$itemStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | VARIANT IMAGES
    |--------------------------------------------------------------------------
    */

    $variantIds=[];

    foreach($itemRows as $item){
        if($item['variant_id']!==null){
            $variantIds[(int)$item['variant_id']]=true;
        }
    }

    $imageMap=[];

    if($variantIds){
        $variantIds=array_keys($variantIds);

        $placeholders=[];

        foreach($variantIds as $index=>$variantId){
            $placeholders[]=':variant_id_'.$index;
        }

        $inSql=implode(',',$placeholders);

        $imageStatusCondition=$accountType==='user'
            ?" AND status='active'"
            :'';

        $imageStmt=$pdo->prepare(
            "SELECT
                id,
                variant_id,
                image,
                alt_text,
                is_primary,
                sort_order,
                status,
                created_at,
                updated_at
             FROM product_variant_images
             WHERE variant_id IN ({$inSql})
             {$imageStatusCondition}
             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach($variantIds as $index=>$variantId){
            $imageStmt->bindValue(
                ':variant_id_'.$index,
                $variantId,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        foreach($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $image){
            $variantId=(int)$image['variant_id'];

            $imageMap[$variantId][]=[
                'id'=>(int)$image['id'],
                'variant_id'=>$variantId,
                'image'=>$image['image'],
                'alt_text'=>$image['alt_text'],
                'is_primary'=>(int)$image['is_primary'],
                'sort_order'=>(int)$image['sort_order'],
                'status'=>$image['status'],
                'created_at'=>$image['created_at'],
                'updated_at'=>$image['updated_at']
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ORDER ITEMS
    |--------------------------------------------------------------------------
    */

    $items=[];
    $totalQuantity=0;
    $totalLineSubtotal=0.00;
    $totalProductDiscount=0.00;
    $totalTax=0.00;
    $totalLineTotal=0.00;

    foreach($itemRows as $item){

        $variantId=$item['variant_id']!==null
            ?(int)$item['variant_id']
            :null;

        $images=$variantId!==null
            ?($imageMap[$variantId]??[])
            :[];

        $primaryImage=null;

        foreach($images as $image){
            if($image['is_primary']===1){
                $primaryImage=$image;
                break;
            }
        }

        if($primaryImage===null&&$images){
            $primaryImage=$images[0];
        }

        $stockQuantity=$item['stock_quantity']!==null
            ?(int)$item['stock_quantity']
            :null;

        $reservedQuantity=$item['reserved_quantity']!==null
            ?(int)$item['reserved_quantity']
            :null;

        $availableQuantity=null;
        $stockStatus=null;

        if(
            $stockQuantity!==null&&
            $reservedQuantity!==null
        ){
            $availableQuantity=max(
                0,
                $stockQuantity-$reservedQuantity
            );

            $lowStockLimit=(int)($item['low_stock_limit']??0);

            if($availableQuantity<=0){
                $stockStatus='out_of_stock';
            }elseif($availableQuantity<=$lowStockLimit){
                $stockStatus='low_stock';
            }else{
                $stockStatus='in_stock';
            }
        }

        $quantity=(int)$item['quantity'];

        $totalQuantity+=$quantity;
        $totalLineSubtotal+=(float)$item['line_subtotal'];
        $totalProductDiscount+=(float)$item['product_discount_amount'];
        $totalTax+=(float)$item['tax_amount'];
        $totalLineTotal+=(float)$item['line_total'];

        $items[]=[
            'id'=>(int)$item['id'],
            'order_id'=>(int)$item['order_id'],

            'snapshot'=>[
                'product_id'=>$item['product_id']!==null
                    ?(int)$item['product_id']
                    :null,

                'variant_id'=>$variantId,

                'product_name'=>$item['product_name'],
                'variant_name'=>$item['variant_name'],
                'sku'=>$item['sku'],
                'hsn_code'=>$item['hsn_code'],
                'size_name'=>$item['size_name'],
                'color_name'=>$item['color_name'],

                'pricing'=>[
                    'original_price'=>$item['original_price'],
                    'selling_price'=>$item['selling_price'],
                    'quantity'=>$quantity,
                    'product_discount_amount'=>
                        $item['product_discount_amount'],
                    'line_subtotal'=>$item['line_subtotal'],
                    'taxable_amount'=>$item['taxable_amount'],
                    'line_total'=>$item['line_total']
                ],

                'tax'=>[
                    'gst_rate'=>$item['gst_rate'],
                    'cgst_rate'=>$item['cgst_rate'],
                    'cgst_amount'=>$item['cgst_amount'],
                    'sgst_rate'=>$item['sgst_rate'],
                    'sgst_amount'=>$item['sgst_amount'],
                    'igst_rate'=>$item['igst_rate'],
                    'igst_amount'=>$item['igst_amount'],
                    'tax_amount'=>$item['tax_amount']
                ],

                'item_status'=>$item['item_status']
            ],

            'current_product'=>$item['product_id']!==null?[
                'id'=>(int)$item['product_id'],
                'name'=>$item['current_product_name'],
                'slug'=>$item['current_product_slug'],
                'description'=>$item['current_product_description'],
                'status'=>$item['current_product_status']
            ]:null,

            'category'=>$item['category_id']!==null?[
                'id'=>(int)$item['category_id'],
                'name'=>$item['category_name'],
                'slug'=>$item['category_slug'],
                'image'=>$item['category_image'],
                'status'=>$item['category_status']
            ]:null,

            'hsn_profile'=>$item['hsn_profile_id']!==null?[
                'id'=>(int)$item['hsn_profile_id'],
                'name'=>$item['hsn_profile_name'],
                'hsn_code'=>$item['current_hsn_code'],
                'status'=>$item['hsn_status']
            ]:null,

            'current_variant'=>$variantId!==null?[
                'id'=>$variantId,

                'sku'=>$item['current_sku'],

                'variant_name'=>
                    $item['current_variant_name'],

                'size'=>$item['size_id']!==null?[
                    'id'=>(int)$item['size_id'],
                    'name'=>$item['current_size_name'],
                    'status'=>$item['size_status']
                ]:null,

                'color'=>$item['color_id']!==null?[
                    'id'=>(int)$item['color_id'],
                    'name'=>$item['current_color_name'],
                    'hex_code'=>$item['hex_code'],
                    'status'=>$item['color_status']
                ]:null,

                'pricing'=>[
                    'original_price'=>
                        $item['current_original_price'],

                    'gst_rate'=>
                        $item['current_gst_rate'],

                    'gst_amount'=>
                        $item['current_gst_amount'],

                    'price_with_tax'=>
                        $item['current_price_with_tax'],

                    'discount_type'=>
                        $item['current_discount_type'],

                    'discount_value'=>
                        $item['current_discount_value'],

                    'selling_price'=>
                        $item['current_selling_price']
                ],

                'stock'=>[
                    'stock_quantity'=>$stockQuantity,
                    'reserved_quantity'=>$reservedQuantity,
                    'available_quantity'=>$availableQuantity,
                    'low_stock_limit'=>$item['low_stock_limit']!==null
                        ?(int)$item['low_stock_limit']
                        :null,
                    'stock_status'=>$stockStatus
                ],

                'is_available'=>$item['is_available']!==null
                    ?(int)$item['is_available']
                    :null,

                'primary_image'=>$primaryImage,
                'images'=>$images

            ]:null,

            'created_at'=>$item['created_at'],
            'updated_at'=>$item['updated_at']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | COUPON DETAILS
    |--------------------------------------------------------------------------
    */

    $coupon=null;

    if($row['coupon_type']==='birthday'){
        $coupon=[
            'type'=>'birthday',

            'id'=>$row['coupon_id']!==null
                ?(int)$row['coupon_id']
                :null,

            'order_snapshot_code'=>$row['coupon_code'],

            'discount_amount'=>
                $row['coupon_discount_amount'],

            'current_definition'=>
                $row['birthday_coupon_code']!==null?[
                    'coupon_code'=>
                        $row['birthday_coupon_code'],

                    'title'=>$row['birthday_title'],

                    'description'=>
                        $row['birthday_description'],

                    'discount_type'=>
                        $row['birthday_discount_type'],

                    'discount_value'=>
                        $row['birthday_discount_value'],

                    'min_order_amount'=>
                        $row['birthday_min_order_amount'],

                    'max_discount_amount'=>
                        $row['birthday_max_discount_amount'],

                    'status'=>
                        $row['birthday_coupon_status']
                ]:null
        ];

    }elseif($row['coupon_type']==='festival'){

        $coupon=[
            'type'=>'festival',

            'id'=>$row['coupon_id']!==null
                ?(int)$row['coupon_id']
                :null,

            'order_snapshot_code'=>$row['coupon_code'],

            'discount_amount'=>
                $row['coupon_discount_amount'],

            'current_definition'=>
                $row['festival_coupon_code']!==null?[
                    'festival_name'=>
                        $row['festival_name'],

                    'coupon_code'=>
                        $row['festival_coupon_code'],

                    'title'=>$row['festival_title'],

                    'description'=>
                        $row['festival_description'],

                    'discount_type'=>
                        $row['festival_discount_type'],

                    'discount_value'=>
                        $row['festival_discount_value'],

                    'min_order_amount'=>
                        $row['festival_min_order_amount'],

                    'max_discount_amount'=>
                        $row['festival_max_discount_amount'],

                    'start_at'=>
                        $row['festival_start_at'],

                    'end_at'=>
                        $row['festival_end_at'],

                    'status'=>
                        $row['festival_coupon_status']
                ]:null
        ];

    }elseif($row['coupon_type']==='referral'){

        $coupon=[
            'type'=>'referral',

            'id'=>$row['coupon_id']!==null
                ?(int)$row['coupon_id']
                :null,

            'order_snapshot_code'=>$row['coupon_code'],

            'discount_amount'=>
                $row['coupon_discount_amount'],

            'current_definition'=>
                $row['referral_code']!==null?[
                    'referral_code'=>
                        $row['referral_code'],

                    'title'=>$row['referral_title'],

                    'description'=>
                        $row['referral_description'],

                    'discount_type'=>
                        $row['referral_discount_type'],

                    'discount_value'=>
                        $row['referral_discount_value'],

                    'min_order_amount'=>
                        $row['referral_min_order_amount'],

                    'max_discount_amount'=>
                        $row['referral_max_discount_amount'],

                    'start_at'=>
                        $row['referral_start_at'],

                    'end_at'=>
                        $row['referral_end_at'],

                    'status'=>
                        $row['referral_coupon_status']
                ]:null
        ];

    }elseif($row['coupon_type']==='first_order'){

        $coupon=[
            'type'=>'first_order',

            'id'=>$row['coupon_id']!==null
                ?(int)$row['coupon_id']
                :null,

            'order_snapshot_code'=>$row['coupon_code'],

            'discount_amount'=>
                $row['coupon_discount_amount'],

            'current_definition'=>
                $row['first_order_coupon_code']!==null?[
                    'coupon_code'=>
                        $row['first_order_coupon_code'],

                    'title'=>$row['first_order_title'],

                    'description'=>
                        $row['first_order_description'],

                    'discount_type'=>
                        $row['first_order_discount_type'],

                    'discount_value'=>
                        $row['first_order_discount_value'],

                    'min_order_amount'=>
                        $row['first_order_min_order_amount'],

                    'max_discount_amount'=>
                        $row['first_order_max_discount_amount'],

                    'start_at'=>
                        $row['first_order_start_at'],

                    'end_at'=>
                        $row['first_order_end_at'],

                    'status'=>
                        $row['first_order_coupon_status']
                ]:null
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS HISTORY
    |--------------------------------------------------------------------------
    */

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
         WHERE payment_id=:payment_id
         ORDER BY id ASC"
    );

    $historyStmt->bindValue(
        ':payment_id',
        $paymentId,
        PDO::PARAM_INT
    );

    $historyStmt->execute();

    $history=[];

    foreach($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $item){
        $history[]=[
            'id'=>(int)$item['id'],
            'payment_id'=>(int)$item['payment_id'],
            'order_id'=>(int)$item['order_id'],
            'from_status'=>$item['from_status'],
            'to_status'=>$item['to_status'],
            'source'=>$item['source'],
            'message'=>$item['message'],
            'created_at'=>$item['created_at']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | WEBHOOK EVENTS
    |--------------------------------------------------------------------------
    */

    $webhooks=[];

    if(
        $row['provider']==='razorpay'&&
        $row['razorpay_order_id']!==null
    ){
        $webhookStmt=$pdo->prepare(
            "SELECT
                id,
                event_id,
                event_type,
                razorpay_order_id,
                razorpay_payment_id,
                process_status,
                error_message,
                received_at,
                processed_at,
                payload
             FROM payment_webhook_events
             WHERE razorpay_order_id=:razorpay_order_id
             ORDER BY id ASC"
        );

        $webhookStmt->bindValue(
            ':razorpay_order_id',
            $row['razorpay_order_id'],
            PDO::PARAM_STR
        );

        $webhookStmt->execute();

        foreach(
            $webhookStmt->fetchAll(PDO::FETCH_ASSOC)
            as $webhook
        ){
            $payload=null;

            if($accountType==='admin'){
                $decodedPayload=json_decode(
                    (string)$webhook['payload'],
                    true
                );

                $payload=json_last_error()===JSON_ERROR_NONE
                    ?$decodedPayload
                    :$webhook['payload'];
            }

            $webhooks[]=[
                'id'=>(int)$webhook['id'],
                'event_id'=>$webhook['event_id'],
                'event_type'=>$webhook['event_type'],
                'razorpay_order_id'=>
                    $webhook['razorpay_order_id'],

                'razorpay_payment_id'=>
                    $webhook['razorpay_payment_id'],

                'process_status'=>
                    $webhook['process_status'],

                'error_message'=>
                    $webhook['error_message'],

                'received_at'=>
                    $webhook['received_at'],

                'processed_at'=>
                    $webhook['processed_at'],

                'payload'=>$payload
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | JSON FIELDS
    |--------------------------------------------------------------------------
    */

    $notes=null;

    if($row['notes']!==null&&$row['notes']!==''){
        $decodedNotes=json_decode(
            (string)$row['notes'],
            true
        );

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
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */

    $response=[
        'id'=>(int)$row['id'],
        'order_id'=>$orderId,
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

        /*
         * Signature only admin can view.
         */
        'razorpay'=>[
            'order_id'=>$row['razorpay_order_id'],
            'payment_id'=>$row['razorpay_payment_id'],
            'signature'=>$accountType==='admin'
                ?$row['razorpay_signature']
                :null,
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
         * Full Razorpay gateway response admin only.
         */
        'gateway_response'=>$accountType==='admin'
            ?$gatewayResponse
            :null,

        'user'=>[
            'id'=>(int)$row['user_id'],
            'name'=>$row['user_name'],
            'mobile'=>$row['user_mobile'],
            'email'=>$row['user_email'],
            'date_of_birth'=>$row['user_date_of_birth'],
            'status'=>$row['user_status'],
            'last_login'=>$row['user_last_login'],
            'created_at'=>$row['user_created_at'],
            'updated_at'=>$row['user_updated_at']
        ],

        'order'=>[
            'id'=>$orderId,
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

            'coupon'=>$coupon,

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

            'address'=>$selectedAddress,

            'addresses'=>$addresses,

            'items'=>$items,

            'items_summary'=>[
                'total_items'=>count($items),

                'total_quantity'=>$totalQuantity,

                'line_subtotal'=>number_format(
                    $totalLineSubtotal,
                    2,
                    '.',
                    ''
                ),

                'product_discount_amount'=>number_format(
                    $totalProductDiscount,
                    2,
                    '.',
                    ''
                ),

                'tax_amount'=>number_format(
                    $totalTax,
                    2,
                    '.',
                    ''
                ),

                'line_total'=>number_format(
                    $totalLineTotal,
                    2,
                    '.',
                    ''
                )
            ],

            'timeline'=>[
                'placed_at'=>$row['placed_at'],
                'confirmed_at'=>$row['confirmed_at'],
                'delivered_at'=>$row['delivered_at'],
                'cancelled_at'=>$row['cancelled_at'],
                'created_at'=>$row['order_created_at'],
                'updated_at'=>$row['order_updated_at']
            ]
        ],

        'status_history'=>$history,

        'webhook_events'=>$webhooks,

        'timeline'=>[
            'initiated_at'=>$row['initiated_at'],
            'authorized_at'=>$row['authorized_at'],
            'paid_at'=>$row['paid_at'],
            'failed_at'=>$row['failed_at'],
            'created_at'=>$row['created_at'],
            'updated_at'=>$row['updated_at']
        ]
    ];

    sendResponse(
        true,
        'Payment retrieved successfully.',
        [
            'payment'=>$response,

            'access'=>[
                'account_type'=>$accountType,

                'scope'=>$accountType==='admin'
                    ?'all_payments'
                    :'own_payments_only'
            ]
        ],
        200
    );

}catch(PDOException $e){

    sendResponse(
        false,
        'Unable to retrieve payment.',
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