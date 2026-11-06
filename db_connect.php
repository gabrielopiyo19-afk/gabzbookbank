<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$localConfig = __DIR__ . '/config.local.php';
$config = is_file($localConfig) ? require $localConfig : [];

$host = $config['host'] ?? getenv('BOOK_BANK_DB_HOST') ?: 'localhost';
$user = $config['user'] ?? getenv('BOOK_BANK_DB_USER') ?: 'book_bank_app';
$password = $config['password'] ?? getenv('BOOK_BANK_DB_PASSWORD') ?: '';
$database = $config['database'] ?? getenv('BOOK_BANK_DB_NAME') ?: 'book_bank_db';

try {
    $conn = new mysqli($host, $user, $password, $database);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Book Bank database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}
