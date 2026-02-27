<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root123456');
define('DB_NAME', 'group_01');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('資料庫連線失敗：' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
