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

// 每次 POST 操作後自動備份資料庫
function auto_backup_db() {
    $lock_file = __DIR__ . '/../.last_backup';

    // 同一分鐘內只備份一次
    if (file_exists($lock_file) && (time() - filemtime($lock_file)) < 60) return;

    $out = __DIR__ . '/../group_13.sql';
    $cmd = '"C:/xampp/mysql/bin/mysqldump.exe" -u ' . DB_USER . ' -p' . DB_PASS
         . ' --routines --triggers --single-transaction --databases ' . DB_NAME
         . ' > "' . $out . '" 2>NUL';
    shell_exec($cmd);

    if (file_exists($out) && filesize($out) > 0) touch($lock_file);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    auto_backup_db();
}

function addColumnIfNotExists($conn, $table, $column, $definition) {
    $db = DB_NAME;
    $res = mysqli_query($conn, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table' AND COLUMN_NAME='$column'");
    if (!mysqli_fetch_row($res)) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

