<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$username = trim($_POST['username'] ?? '');

if (empty($username)) {
    echo json_encode(['exists' => false]);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

echo json_encode(['exists' => mysqli_stmt_num_rows($stmt) > 0]);
