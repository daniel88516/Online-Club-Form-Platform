<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

$username = trim($_POST['username'] ?? '');
$exclude_current = !empty($_POST['exclude_current']) && isLoggedIn();

if (empty($username)) {
    echo json_encode(['exists' => false]);
    exit();
}

if ($exclude_current) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
    mysqli_stmt_bind_param($stmt, 'si', $username, $uid);
} else {
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

echo json_encode(['exists' => mysqli_stmt_num_rows($stmt) > 0]);
