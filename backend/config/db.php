<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'vivisha_boutique');
define('DB_USER', 'root');
define('DB_PASS', '');

/*
|--------------------------------------------------------------------------
| Application Configuration
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'Vivisha Boutique');
define('APP_ENV', 'development');

/*
|--------------------------------------------------------------------------
| JWT Configuration
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Change this secret key in production.
| Use a long random string.
|
*/

define(
    'JWT_SECRET',
    'VivishaBoutique_2026_Secure_JWT_Secret_Key_Change_This_Immediately'
);

define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 86400); // 24 hours


/*
|--------------------------------------------------------------------------
| CORS / API Headers
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

try {

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode(
        [
            'status'  => false,
            'message' => 'Database connection failed.',
            'error'   => APP_ENV === 'development'
                ? $e->getMessage()
                : null
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Common API Response
|--------------------------------------------------------------------------
*/

function sendResponse(
    bool $status,
    string $message,
    $data = null,
    int $httpCode = 200
): void {

    http_response_code($httpCode);

    $response = [
        'status'  => $status,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get JSON Request Body
|--------------------------------------------------------------------------
*/

function getJsonInput(): array
{
    $input = file_get_contents('php://input');

    if ($input === false || trim($input) === '') {
        sendResponse(
            false,
            'Request body is required.',
            null,
            400
        );
    }

    $data = json_decode($input, true);

    if (
        json_last_error() !== JSON_ERROR_NONE ||
        !is_array($data)
    ) {
        sendResponse(
            false,
            'Invalid JSON request body.',
            null,
            400
        );
    }

    return $data;
}