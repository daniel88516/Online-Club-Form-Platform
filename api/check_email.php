<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

$email = trim($_POST['email'] ?? '');
$exclude_current = !empty($_POST['exclude_current']) && isLoggedIn();

if (empty($email)) {
    echo json_encode(['exists' => false, 'valid' => false]);
    exit();
}

// 格式驗證
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'valid' => false]);
    exit();
}

if ($exclude_current) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
    mysqli_stmt_bind_param($stmt, 'si', $email, $uid);
} else {
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

echo json_encode([
    'valid'  => true,
    'exists' => mysqli_stmt_num_rows($stmt) > 0
]);
