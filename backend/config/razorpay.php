<?php
declare(strict_types=1);

use Razorpay\Api\Api;

$projectRoot=dirname(__DIR__);
$autoloadFile=$projectRoot.'/vendor/autoload.php';
$envFile=$projectRoot.'/.env';

if(!is_file($autoloadFile)){
    throw new RuntimeException(
        'Composer autoload file not found. Run: composer require razorpay/razorpay'
    );
}

require_once $autoloadFile;

if(!class_exists(Api::class)){
    throw new RuntimeException(
        'Razorpay PHP SDK is not installed. Run: composer require razorpay/razorpay'
    );
}

function getRazorpayEnvValue(
    string $key,
    array $fileEnv=[]
):string{
    $value=getenv($key);

    if($value!==false&&trim((string)$value)!==''){
        return trim((string)$value);
    }

    if(isset($_ENV[$key])&&trim((string)$_ENV[$key])!==''){
        return trim((string)$_ENV[$key]);
    }

    if(isset($_SERVER[$key])&&trim((string)$_SERVER[$key])!==''){
        return trim((string)$_SERVER[$key]);
    }

    if(isset($fileEnv[$key])){
        return trim((string)$fileEnv[$key]);
    }

    return '';
}

$fileEnv=[];

if(is_file($envFile)){
    $parsedEnv=parse_ini_file(
        $envFile,
        false,
        INI_SCANNER_RAW
    );

    if($parsedEnv===false){
        throw new RuntimeException(
            'Unable to read .env file.'
        );
    }

    $fileEnv=$parsedEnv;
}

$razorpayKeyId=getRazorpayEnvValue(
    'RAZORPAY_KEY_ID',
    $fileEnv
);

$razorpayKeySecret=getRazorpayEnvValue(
    'RAZORPAY_KEY_SECRET',
    $fileEnv
);

$razorpayWebhookSecret=getRazorpayEnvValue(
    'RAZORPAY_WEBHOOK_SECRET',
    $fileEnv
);

if($razorpayKeyId===''){
    throw new RuntimeException(
        'RAZORPAY_KEY_ID is not configured.'
    );
}

if($razorpayKeySecret===''){
    throw new RuntimeException(
        'RAZORPAY_KEY_SECRET is not configured.'
    );
}

if(
    !str_starts_with($razorpayKeyId,'rzp_test_')&&
    !str_starts_with($razorpayKeyId,'rzp_live_')
){
    throw new RuntimeException(
        'Invalid RAZORPAY_KEY_ID format.'
    );
}

if(mb_strlen($razorpayKeyId)>100){
    throw new RuntimeException(
        'Invalid RAZORPAY_KEY_ID length.'
    );
}

if(
    mb_strlen($razorpayKeySecret)<8||
    mb_strlen($razorpayKeySecret)>255
){
    throw new RuntimeException(
        'Invalid RAZORPAY_KEY_SECRET configuration.'
    );
}

try{
    $razorpayApi=new Api(
        $razorpayKeyId,
        $razorpayKeySecret
    );
}catch(Throwable $e){
    throw new RuntimeException(
        'Unable to initialize Razorpay API client.',
        0,
        $e
    );
}