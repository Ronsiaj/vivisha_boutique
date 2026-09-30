<?php

declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'vivisha_boutique');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Vivisha Boutique');
define('APP_ENV', 'development');

define(
    'JWT_SECRET',
    'VivishaBoutique_2026_Secure_JWT_Secret_Key_Change_This_Immediately'
);

define(
    'JWT_ALGORITHM',
    'HS256'
);

define(
    'JWT_EXPIRY',
    86400
);

$allowedOrigins = [

    'http://localhost:5173',
    'http://127.0.0.1:5173',

    'http://localhost:3000',
    'http://127.0.0.1:3000',

];

$requestOrigin =
    isset($_SERVER['HTTP_ORIGIN'])
        ? trim(
            (string)$_SERVER['HTTP_ORIGIN']
        )
        : '';

function applyCorsHeaders(
    array $allowedOrigins,
    string $requestOrigin
): void {

    header_remove(
        'Access-Control-Allow-Origin'
    );

    header_remove(
        'Access-Control-Allow-Credentials'
    );

    header_remove(
        'Access-Control-Allow-Methods'
    );

    header_remove(
        'Access-Control-Allow-Headers'
    );

    header_remove(
        'Access-Control-Max-Age'
    );

    if (
        $requestOrigin !== '' &&
        in_array(
            $requestOrigin,
            $allowedOrigins,
            true
        )
    ) {

        header(
            'Access-Control-Allow-Origin: '
            . $requestOrigin
        );

        header(
            'Access-Control-Allow-Credentials: true'
        );

        header(
            'Vary: Origin',
            false
        );
    }

    header(
        'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS'
    );

    header(
        'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'
    );

    header(
        'Access-Control-Max-Age: 86400'
    );
}

applyCorsHeaders(
    $allowedOrigins,
    $requestOrigin
);

register_shutdown_function(
    function () use (
        $allowedOrigins,
        $requestOrigin
    ): void {

        if (!headers_sent()) {

            applyCorsHeaders(
                $allowedOrigins,
                $requestOrigin
            );
        }
    }
);

header(
    'Content-Type: application/json; charset=UTF-8'
);

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'OPTIONS'
) {

    applyCorsHeaders(
        $allowedOrigins,
        $requestOrigin
    );

    http_response_code(204);

    exit;
}

try {

    $dsn =
        'mysql:host='
        . DB_HOST
        . ';dbname='
        . DB_NAME
        . ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false
        ]
    );

} catch (PDOException $e) {

    http_response_code(500);

    $response = [

        'status' =>
            false,

        'message' =>
            'Database connection failed.'
    ];

    if (
        defined('APP_ENV') &&
        APP_ENV === 'development'
    ) {

        $response['error'] =
            $e->getMessage();
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function sendResponse(
    bool $status,
    string $message,
    $data = null,
    int $httpCode = 200
): void {

    http_response_code(
        $httpCode
    );

    $response = [

        'status' =>
            $status,

        'message' =>
            $message
    ];

    if ($data !== null) {

        $response['data'] =
            $data;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function getJsonInput(): array
{

    $input =
        file_get_contents(
            'php://input'
        );

    if (
        $input === false ||
        trim($input) === ''
    ) {

        sendResponse(
            false,
            'Request body is required.',
            null,
            400
        );
    }

    $data =
        json_decode(
            $input,
            true
        );

    if (
        json_last_error()
            !== JSON_ERROR_NONE ||
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
