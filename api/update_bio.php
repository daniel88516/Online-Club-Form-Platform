<?php
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();
header('Content-Type: application/json');

$bio = mb_substr(trim($_POST['bio'] ?? ''), 0, 200);
$uid = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "UPDATE users SET bio = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $bio, $uid);
$ok = mysqli_stmt_execute($stmt);

echo json_encode(['success' => $ok]);
