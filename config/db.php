<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root123456');
define('DB_NAME', 'group_13');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('資料庫連線失敗：' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

// 相容舊版 MySQL 的欄位遷移輔助函式
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $db = DB_NAME;
    $res = mysqli_query($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'");
    if ($res && mysqli_num_rows($res) === 0) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

addColumnIfNotExists($conn, 'users', 'bio', 'TEXT DEFAULT NULL');

// 社團功能遷移
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255) DEFAULT NULL,
    owner_id INT NOT NULL,
    is_public TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS club_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner','member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_club_user (club_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

addColumnIfNotExists($conn, 'forms', 'club_id', 'INT DEFAULT NULL');
addColumnIfNotExists($conn, 'club_members', 'status', "ENUM('active','pending','invited') DEFAULT 'active'");
mysqli_query($conn, "UPDATE club_members SET status = 'active' WHERE status IS NULL");
