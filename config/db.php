<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'group_13');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('資料庫連線失敗：' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

// 確保 users 有 bio 欄位（一次性遷移，IF NOT EXISTS 讓它冪等）
mysqli_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL");
