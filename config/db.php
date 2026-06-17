<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root123456');
define('DB_NAME', 'group_13');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

function addColumnIfNotExists($conn, $table, $column, $definition) {
    $db = DB_NAME;
    $res = mysqli_query($conn, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'");
    if (!mysqli_fetch_row($res)) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

addColumnIfNotExists($conn, 'forms', 'response_scope', "ENUM('all_members','club_members') NOT NULL DEFAULT 'all_members' AFTER `show_on_index`");
addColumnIfNotExists($conn, 'forms', 'updated_at', "TIMESTAMP NULL DEFAULT NULL AFTER `created_at`");
