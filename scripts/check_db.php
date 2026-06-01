<?php
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: '3306';
$dbname   = getenv('DB_NAME') ?: 'rsgms_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$sslCa    = getenv('DB_SSL_CA') ?: '';

$sslOpt = [];
if ($sslCa) {
    $sslOpt = [
        PDO::MYSQL_ATTR_SSL_CA                => $sslCa,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
}

$mode = $argv[1] ?? 'ping';

try {
    if ($mode === 'ping') {
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        new PDO($dsn, $username, $password, $sslOpt);
        echo "ok";
    } elseif ($mode === 'check_seed') {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, $sslOpt);
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        echo $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    echo "0";
}
