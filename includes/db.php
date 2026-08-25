<?php

date_default_timezone_set(getenv('KIN_CAFE_TIMEZONE') ?: 'Asia/Manila');

require_once __DIR__ . '/app_config.php';

$databaseDefaults = getProjectDatabaseDefaults();
$host = trim((string) (getenv('KIN_CAFE_DB_HOST') ?: $databaseDefaults['host']));
$port = (int) (getenv('KIN_CAFE_DB_PORT') ?: $databaseDefaults['port']);
$db = trim((string) (getenv('KIN_CAFE_DB_NAME') ?: $databaseDefaults['name']));
$user = trim((string) (getenv('KIN_CAFE_DB_USER') ?: $databaseDefaults['username']));
$pass = (string) (getenv('KIN_CAFE_DB_PASSWORD') ?: $databaseDefaults['password']);

if ($host === '') {
    $host = '127.0.0.1';
}
if ($port <= 0) {
    $port = 3306;
}
if ($db === '') {
    $db = 'kin_cafe';
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    require_once __DIR__ . '/functions.php';
    ensureSystemSchema($pdo);
    authBootstrap($pdo);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
