<?php
$driver   = getenv('DB_DRIVER') ?: 'mysql';
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
$dbname   = getenv('DB_NAME') ?: ($driver === 'pgsql' ? 'rsgms_db' : 'rsgms_db');
$username = getenv('DB_USER') ?: ($driver === 'pgsql' ? 'postgres' : 'root');
$password = getenv('DB_PASSWORD') ?: '';

$mode = $argv[1] ?? 'ping';

try {
    if ($driver === 'pgsql') {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        $options = [];
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $options = [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false];
    }

    if ($mode === 'ping') {
        new PDO($dsn, $username, $password, $options);
        echo "ok";
    } elseif ($mode === 'check_seed') {
        $pdo = new PDO($dsn, $username, $password, $options);
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        echo $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    echo "0";
}
