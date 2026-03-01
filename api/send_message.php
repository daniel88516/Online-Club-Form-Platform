<?php
ob_start();
require_once '../config/session.php';
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$receiver_id = intval($_POST['receiver_id'] ?? 0);
$content     = trim($_POST['content'] ?? '');

if (!$receiver_id || $content === '') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit();
}

$sender_id = $_SESSION['user_id'];

if ($sender_id === $receiver_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '不能傳訊息給自己']);
    exit();
}

// 確認收件人存在
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
mysqli_stmt_bind_param($check, 'i', $receiver_id);
mysqli_stmt_execute($check);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '找不到該用戶']);
    exit();
}

$stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iis', $sender_id, $receiver_id, $content);
mysqli_stmt_execute($stmt);
$new_id = mysqli_insert_id($conn);

ob_end_clean();
echo json_encode([
    'success'    => true,
    'message_id' => $new_id,
    'created_at' => date('Y/m/d H:i'),
]);
