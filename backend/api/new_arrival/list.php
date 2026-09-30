<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';

if(!function_exists('sendResponse')){
    function sendResponse(
        bool $success,
        string $message,
        mixed $data=null,
        int $statusCode=200
    ):never{
        http_response_code($statusCode);
        echo json_encode(
            [
                'success'=>$success,
                'message'=>$message,
                'data'=>$data
            ],
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(204);
    exit;
}

if($_SERVER['REQUEST_METHOD']!=='GET'){
    sendResponse(false,'Only GET method is allowed.',null,405);
}

function newArrivalPositiveInt(
    mixed $value,
    string $field
):?int{
    if($value===null||$value===''){
        return null;
    }

    if(is_array($value)||is_object($value)||is_bool($value)){
        sendResponse(
            false,
            "{$field} must be a valid positive integer.",
            null,
            422
        );
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

function newArrivalNonNegativeInt(
    mixed $value,
    string $field
):?int{
    if($value===null||$value===''){
        return null;
    }

    if(is_array($value)||is_object($value)||is_bool($value)){
        sendResponse(
            false,
            "{$field} must be a valid non-negative integer.",
            null,
            422
        );
    }

    $value=trim((string)$value);

    if(!preg_match('/^[0-9]+$/',$value)){
        sendResponse(
            false,
            "{$field} must be a valid non-negative integer.",
            null,
            422
        );
    }

    return (int)$value;
}

function newArrivalAmount(
    mixed $value,
    string $field
):?string{
    if($value===null||$value===''){
        return null;
    }

    if(is_array($value)||is_object($value)||is_bool($value)){
        sendResponse(
            false,
            "{$field} must be a valid amount.",
            null,
            422
        );
    }

    $value=trim((string)$value);

    if(
        !preg_match(
            '/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/',
            $value
        )
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

/*
|--------------------------------------------------------------------------
| ALLOWED QUERY PARAMETERS
|--------------------------------------------------------------------------
*/

$allowedParams=[
    'q',
    'id',
    'new_arrival_id',
    'product_variant_id',
    'product_id',
    'size_id',
    'color_id',
    'status',
    'is_available',
    'stock_status',
    'min_price',
    'max_price',
    'sort_order_value',
    'page',
    'limit',
    'sort_by',
    'sort_order'
];

foreach(array_keys($_GET) as $param){
    if(!in_array($param,$allowedParams,true)){
        sendResponse(
            false,
            "Invalid query parameter: {$param}.",
            [
                'allowed_parameters'=>$allowedParams
            ],
            422
        );
    }
}

foreach($_GET as $key=>$value){
    if(is_array($value)){
        sendResponse(
            false,
            "{$key} must be a single value.",
            null,
            422
        );
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$q=trim((string)($_GET['q']??''));

if(mb_strlen($q)>200){
    sendResponse(
        false,
        'q must not exceed 200 characters.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| IDS
|--------------------------------------------------------------------------
*/

$id=newArrivalPositiveInt(
    $_GET['id']??null,
    'id'
);

$newArrivalId=newArrivalPositiveInt(
    $_GET['new_arrival_id']??null,
    'new_arrival_id'
);

if(
    $id!==null&&
    $newArrivalId!==null&&
    $id!==$newArrivalId
){
    sendResponse(
        false,
        'id and new_arrival_id cannot contain different values.',
        null,
        422
    );
}

$selectedNewArrivalId=$newArrivalId??$id;

$productVariantId=newArrivalPositiveInt(
    $_GET['product_variant_id']??null,
    'product_variant_id'
);

$productId=newArrivalPositiveInt(
    $_GET['product_id']??null,
    'product_id'
);

$sizeId=newArrivalPositiveInt(
    $_GET['size_id']??null,
    'size_id'
);

$colorId=newArrivalPositiveInt(
    $_GET['color_id']??null,
    'color_id'
);

$sortOrderValue=newArrivalNonNegativeInt(
    $_GET['sort_order_value']??null,
    'sort_order_value'
);

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status=strtolower(
    trim((string)($_GET['status']??''))
);

if(
    $status!==''&&
    !in_array($status,['active','inactive'],true)
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

/*
 * Public API exposes active new arrivals only.
 */
if($status!==''&&$status!=='active'){
    sendResponse(
        false,
        'Public API supports status=active only.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| IS AVAILABLE
|--------------------------------------------------------------------------
*/

$isAvailable=null;

if(isset($_GET['is_available'])&&$_GET['is_available']!==''){

    $isAvailableInput=trim(
        (string)$_GET['is_available']
    );

    if(!in_array($isAvailableInput,['0','1'],true)){
        sendResponse(
            false,
            'is_available must be 0 or 1.',
            null,
            422
        );
    }

    $isAvailable=(int)$isAvailableInput;
}

/*
 * Public API exposes available variants only.
 */
if($isAvailable!==null&&$isAvailable!==1){
    sendResponse(
        false,
        'Public API supports is_available=1 only.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| STOCK STATUS
|--------------------------------------------------------------------------
*/

$stockStatus=strtolower(
    trim((string)($_GET['stock_status']??''))
);

$allowedStockStatuses=[
    'in_stock',
    'low_stock',
    'out_of_stock'
];

if(
    $stockStatus!==''&&
    !in_array(
        $stockStatus,
        $allowedStockStatuses,
        true
    )
){
    sendResponse(
        false,
        'Invalid stock_status.',
        [
            'allowed_values'=>$allowedStockStatuses
        ],
        422
    );
}

/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

$minPrice=newArrivalAmount(
    $_GET['min_price']??null,
    'min_price'
);

$maxPrice=newArrivalAmount(
    $_GET['max_price']??null,
    'max_price'
);

if(
    $minPrice!==null&&
    $maxPrice!==null&&
    (float)$minPrice>(float)$maxPrice
){
    sendResponse(
        false,
        'min_price cannot be greater than max_price.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$pageInput=trim(
    (string)($_GET['page']??'1')
);

$limitInput=trim(
    (string)($_GET['limit']??'20')
);

if(!preg_match('/^[1-9][0-9]*$/',$pageInput)){
    sendResponse(
        false,
        'page must be a valid positive integer.',
        null,
        422
    );
}

if(!preg_match('/^[1-9][0-9]*$/',$limitInput)){
    sendResponse(
        false,
        'limit must be a valid positive integer.',
        null,
        422
    );
}

$page=(int)$pageInput;
$limit=(int)$limitInput;

if($limit>100){
    sendResponse(
        false,
        'limit must be between 1 and 100.',
        null,
        422
    );
}

$offset=($page-1)*$limit;

/*
|--------------------------------------------------------------------------
| SORTING
|--------------------------------------------------------------------------
*/

$sortColumns=[
    'id'=>'nav.id',
    'sort_order'=>'nav.sort_order',
    'created_at'=>'nav.created_at',
    'updated_at'=>'nav.updated_at',
    'product_variant_id'=>'nav.product_variant_id',
    'product_id'=>'pv.product_id',
    'original_price'=>'pv.original_price',
    'selling_price'=>'pv.selling_price',
    'stock_quantity'=>'pv.stock_quantity',
    'variant_name'=>'pv.variant_name',
    'sku'=>'pv.sku'
];

$sortBy=trim(
    (string)($_GET['sort_by']??'sort_order')
);

$sortOrder=strtolower(
    trim((string)($_GET['sort_order']??'asc'))
);

if(!array_key_exists($sortBy,$sortColumns)){
    sendResponse(
        false,
        'Invalid sort_by.',
        [
            'allowed_values'=>array_keys($sortColumns)
        ],
        422
    );
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

    /*
    |--------------------------------------------------------------------------
    | WHERE
    |--------------------------------------------------------------------------
    */

    $where=[];
    $params=[];

    /*
     * Public scope:
     * active new arrivals + available variants only.
     */
    $where[]="nav.status='active'";
    $where[]='pv.is_available=1';

    if($selectedNewArrivalId!==null){
        $where[]='nav.id=:new_arrival_id';
        $params[':new_arrival_id']=$selectedNewArrivalId;
    }

    if($productVariantId!==null){
        $where[]='nav.product_variant_id=:product_variant_id';
        $params[':product_variant_id']=$productVariantId;
    }

    if($productId!==null){
        $where[]='pv.product_id=:product_id';
        $params[':product_id']=$productId;
    }

    if($sizeId!==null){
        $where[]='pv.size_id=:size_id';
        $params[':size_id']=$sizeId;
    }

    if($colorId!==null){
        $where[]='pv.color_id=:color_id';
        $params[':color_id']=$colorId;
    }

    if($sortOrderValue!==null){
        $where[]='nav.sort_order=:sort_order_value';
        $params[':sort_order_value']=$sortOrderValue;
    }

    if($minPrice!==null){
        $where[]='pv.selling_price>=:min_price';
        $params[':min_price']=$minPrice;
    }

    if($maxPrice!==null){
        $where[]='pv.selling_price<=:max_price';
        $params[':max_price']=$maxPrice;
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK STATUS FILTER
    |--------------------------------------------------------------------------
    |
    | available_quantity =
    | stock_quantity - reserved_quantity
    |--------------------------------------------------------------------------
    */

    if($stockStatus==='out_of_stock'){

        $where[]="
            (
                CASE
                    WHEN pv.stock_quantity>pv.reserved_quantity
                    THEN pv.stock_quantity-pv.reserved_quantity
                    ELSE 0
                END
            )<=0
        ";

    }elseif($stockStatus==='low_stock'){

        $where[]="
            (
                CASE
                    WHEN pv.stock_quantity>pv.reserved_quantity
                    THEN pv.stock_quantity-pv.reserved_quantity
                    ELSE 0
                END
            )>0
        ";

        $where[]="
            (
                CASE
                    WHEN pv.stock_quantity>pv.reserved_quantity
                    THEN pv.stock_quantity-pv.reserved_quantity
                    ELSE 0
                END
            )<=pv.low_stock_limit
        ";

    }elseif($stockStatus==='in_stock'){

        $where[]="
            (
                CASE
                    WHEN pv.stock_quantity>pv.reserved_quantity
                    THEN pv.stock_quantity-pv.reserved_quantity
                    ELSE 0
                END
            )>pv.low_stock_limit
        ";
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if($q!==''){

        $where[]="
            CONCAT_WS(
                ' ',
                nav.id,
                nav.product_variant_id,
                nav.sort_order,
                nav.status,
                pv.id,
                pv.product_id,
                pv.sku,
                pv.variant_name,
                pv.original_price,
                pv.selling_price,
                p.name,
                s.name,
                c.name
            ) LIKE :search_q
        ";

        $params[':search_q']='%'.$q.'%';
    }

    $whereSql=$where
        ?' WHERE '.implode(' AND ',$where)
        :'';

    /*
    |--------------------------------------------------------------------------
    | BASE JOIN
    |--------------------------------------------------------------------------
    */

    $baseFrom="
        FROM new_arrival_variants nav

        INNER JOIN product_variants pv
            ON pv.id=nav.product_variant_id

        LEFT JOIN products p
            ON p.id=pv.product_id

        LEFT JOIN sizes s
            ON s.id=pv.size_id

        LEFT JOIN colors c
            ON c.id=pv.color_id
    ";

    /*
    |--------------------------------------------------------------------------
    | COUNT
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
            'Requested page exceeds total available pages.',
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
            COUNT(*) AS total_records,

            SUM(
                CASE
                    WHEN nav.status='active'
                    THEN 1
                    ELSE 0
                END
            ) AS active_count,

            SUM(
                CASE
                    WHEN nav.status='inactive'
                    THEN 1
                    ELSE 0
                END
            ) AS inactive_count,

            SUM(
                CASE
                    WHEN pv.is_available=1
                    THEN 1
                    ELSE 0
                END
            ) AS available_count,

            SUM(
                CASE
                    WHEN pv.is_available=0
                    THEN 1
                    ELSE 0
                END
            ) AS unavailable_count

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
    | MAIN LIST
    |--------------------------------------------------------------------------
    */

    $stmt=$pdo->prepare(
        "SELECT
            nav.id AS new_arrival_id,
            nav.product_variant_id,
            nav.sort_order,
            nav.status AS new_arrival_status,
            nav.created_by_admin_id,
            nav.created_at AS new_arrival_created_at,
            nav.updated_at AS new_arrival_updated_at,

            pv.id AS variant_id,
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
            pv.created_at AS variant_created_at,
            pv.updated_at AS variant_updated_at,

            p.name AS product_name,
            p.slug AS product_slug,
            p.description AS product_description,
            p.status AS product_status,

            s.name AS size_name,
            s.status AS size_status,

            c.name AS color_name,
            c.hex_code AS color_hex_code,
            c.status AS color_status

         {$baseFrom}
         {$whereSql}

         ORDER BY
            {$sqlSortColumn} {$sqlSortOrder},
            nav.id DESC

         LIMIT :limit
         OFFSET :offset"
    );

    foreach($params as $key=>$value){
        $stmt->bindValue(
            $key,
            $value,
            is_int($value)
                ?PDO::PARAM_INT
                :PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | GET VARIANT IMAGES
    |--------------------------------------------------------------------------
    */

    $variantIds=[];

    foreach($rows as $row){
        $variantIds[(int)$row['variant_id']]=true;
    }

    $imageMap=[];

    if($variantIds){

        $variantIds=array_keys($variantIds);

        $placeholders=[];

        foreach($variantIds as $index=>$variantId){
            $placeholders[]=
                ':image_variant_id_'.$index;
        }

        $imageInSql=implode(',',$placeholders);

        /*
         * Public API exposes active variant images only.
         */
        $imageStatusSql=" AND status='active'";

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

             WHERE variant_id IN ({$imageInSql})
             {$imageStatusSql}

             ORDER BY
                variant_id ASC,
                is_primary DESC,
                sort_order ASC,
                id ASC"
        );

        foreach($variantIds as $index=>$variantId){
            $imageStmt->bindValue(
                ':image_variant_id_'.$index,
                $variantId,
                PDO::PARAM_INT
            );
        }

        $imageStmt->execute();

        $imageRows=$imageStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach($imageRows as $image){

            $variantId=(int)$image['variant_id'];

            $imageMap[$variantId][]=[
                'id'=>(int)$image['id'],

                'variant_id'=>
                    $variantId,

                'image'=>
                    $image['image'],

                'alt_text'=>
                    $image['alt_text'],

                'is_primary'=>
                    (int)$image['is_primary'],

                'sort_order'=>
                    (int)$image['sort_order'],

                'status'=>
                    $image['status'],

                'created_at'=>
                    $image['created_at'],

                'updated_at'=>
                    $image['updated_at']
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT RESPONSE
    |--------------------------------------------------------------------------
    */

    $newArrivals=[];

    foreach($rows as $row){

        $variantId=(int)$row['variant_id'];

        /*
        |--------------------------------------------------------------------------
        | STOCK CALCULATION
        |--------------------------------------------------------------------------
        */

        $stockQuantity=
            (int)$row['stock_quantity'];

        $reservedQuantity=
            (int)$row['reserved_quantity'];

        $availableQuantity=max(
            0,
            $stockQuantity-$reservedQuantity
        );

        $lowStockLimit=
            (int)$row['low_stock_limit'];

        if($availableQuantity<=0){

            $calculatedStockStatus=
                'out_of_stock';

        }elseif(
            $availableQuantity<=$lowStockLimit
        ){

            $calculatedStockStatus=
                'low_stock';

        }else{

            $calculatedStockStatus=
                'in_stock';
        }

        /*
        |--------------------------------------------------------------------------
        | DISCOUNT CALCULATION
        |--------------------------------------------------------------------------
        */

        $priceWithTax=(float)$row['price_with_tax'];
        $sellingPrice=(float)$row['selling_price'];

        $discountAmount=max(
            0,
            round(
                $priceWithTax-$sellingPrice,
                2
            )
        );

        $discountPercentage=0.00;

        if(
            $priceWithTax>0&&
            $discountAmount>0
        ){
            $discountPercentage=round(
                ($discountAmount/$priceWithTax)*100,
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | IMAGES
        |--------------------------------------------------------------------------
        */

        $images=$imageMap[$variantId]??[];

        $primaryImage=null;

        foreach($images as $image){

            if($image['is_primary']===1){
                $primaryImage=$image;
                break;
            }
        }

        if(
            $primaryImage===null&&
            !empty($images)
        ){
            $primaryImage=$images[0];
        }

        /*
        |--------------------------------------------------------------------------
        | RESULT
        |--------------------------------------------------------------------------
        */

        $newArrivals[]=[
            'id'=>
                (int)$row['new_arrival_id'],

            'product_variant_id'=>
                $variantId,

            'sort_order'=>
                (int)$row['sort_order'],

            'status'=>
                $row['new_arrival_status'],

            'created_by_admin_id'=>
                $row['created_by_admin_id']!==null
                    ?(int)$row['created_by_admin_id']
                    :null,

            'created_at'=>
                $row['new_arrival_created_at'],

            'updated_at'=>
                $row['new_arrival_updated_at'],

            /*
            |--------------------------------------------------------------------------
            | PRODUCT
            |--------------------------------------------------------------------------
            */

            'product'=>[
                'id'=>
                    (int)$row['product_id'],

                'name'=>
                    $row['product_name'],

                'slug'=>
                    $row['product_slug'],

                'description'=>
                    $row['product_description'],

                'status'=>
                    $row['product_status']
            ],

            /*
            |--------------------------------------------------------------------------
            | PRODUCT VARIANT
            |--------------------------------------------------------------------------
            */

            'product_variant'=>[
                'id'=>
                    $variantId,

                'product_id'=>
                    (int)$row['product_id'],

                'sku'=>
                    $row['sku'],

                'variant_name'=>
                    $row['variant_name'],

                /*
                |--------------------------------------------------------------------------
                | SIZE
                |--------------------------------------------------------------------------
                */

                'size'=>
                    $row['size_id']!==null
                        ?[
                            'id'=>
                                (int)$row['size_id'],

                            'name'=>
                                $row['size_name'],

                            'status'=>
                                $row['size_status']
                        ]
                        :null,

                /*
                |--------------------------------------------------------------------------
                | COLOR
                |--------------------------------------------------------------------------
                */

                'color'=>
                    $row['color_id']!==null
                        ?[
                            'id'=>
                                (int)$row['color_id'],

                            'name'=>
                                $row['color_name'],

                            'hex_code'=>
                                $row['color_hex_code'],

                            'status'=>
                                $row['color_status']
                        ]
                        :null,

                /*
                |--------------------------------------------------------------------------
                | PRICING
                |--------------------------------------------------------------------------
                */

                'pricing'=>[
                    'original_price'=>
                        $row['original_price'],

                    'gst_rate'=>
                        $row['gst_rate'],

                    'gst_amount'=>
                        $row['gst_amount'],

                    'price_with_tax'=>
                        $row['price_with_tax'],

                    'discount_type'=>
                        $row['discount_type'],

                    'discount_value'=>
                        $row['discount_value'],

                    'calculated_discount_amount'=>
                        number_format(
                            $discountAmount,
                            2,
                            '.',
                            ''
                        ),

                    'calculated_discount_percentage'=>
                        number_format(
                            $discountPercentage,
                            2,
                            '.',
                            ''
                        ),

                    'selling_price'=>
                        $row['selling_price']
                ],

                /*
                |--------------------------------------------------------------------------
                | STOCK
                |--------------------------------------------------------------------------
                */

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
                        $calculatedStockStatus
                ],

                'is_available'=>
                    (int)$row['is_available'],

                /*
                |--------------------------------------------------------------------------
                | IMAGES
                |--------------------------------------------------------------------------
                */

                'primary_image'=>
                    $primaryImage,

                'images'=>
                    $images,

                'created_at'=>
                    $row['variant_created_at'],

                'updated_at'=>
                    $row['variant_updated_at']
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'New arrivals retrieved successfully.',
        [
            'new_arrivals'=>$newArrivals,

            'summary'=>[
                'total_records'=>
                    (int)($summary['total_records']??0),

                'active_count'=>
                    (int)($summary['active_count']??0),

                'inactive_count'=>
                    (int)($summary['inactive_count']??0),

                'available_count'=>
                    (int)($summary['available_count']??0),

                'unavailable_count'=>
                    (int)($summary['unavailable_count']??0)
            ],

            'pagination'=>[
                'page'=>$page,

                'limit'=>$limit,

                'total_records'=>$totalRecords,

                'total_pages'=>$totalPages,

                'has_previous'=>
                    $page>1,

                'has_next'=>
                    $page<$totalPages
            ],

            'filters'=>[
                'q'=>
                    $q!==''?$q:null,

                'new_arrival_id'=>
                    $selectedNewArrivalId,

                'product_variant_id'=>
                    $productVariantId,

                'product_id'=>
                    $productId,

                'size_id'=>
                    $sizeId,

                'color_id'=>
                    $colorId,

                'status'=>'active',

                'is_available'=>1,

                'stock_status'=>
                    $stockStatus!==''?$stockStatus:null,

                'min_price'=>
                    $minPrice,

                'max_price'=>
                    $maxPrice,

                'sort_order_value'=>
                    $sortOrderValue
            ],

            'sorting'=>[
                'sort_by'=>$sortBy,
                'sort_order'=>$sortOrder
            ],

            'access'=>[
                'authentication'=>false,
                'scope'=>'public_active_available_new_arrivals_only'
            ]
        ],
        200
    );

}catch(PDOException $e){

    sendResponse(
        false,
        'Unable to retrieve new arrivals.',
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
        'An unexpected error occurred while retrieving new arrivals.',
        defined('APP_ENV')&&APP_ENV==='development'
            ?[
                'error'=>$e->getMessage()
            ]
            :null,
        500
    );
}