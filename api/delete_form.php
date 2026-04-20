<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$form_id = intval($_POST['form_id'] ?? 0);
if (!$form_id) {
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT user_id, title FROM forms WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $form_id);
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) {
    echo json_encode(['success' => false, 'message' => '表單不存在']);
    exit();
}

if (!isAdmin() && $form['user_id'] !== $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => '無刪除權限']);
    exit();
}

$del = mysqli_prepare($conn, "DELETE FROM forms WHERE id = ?");
mysqli_stmt_bind_param($del, 'i', $form_id);
mysqli_stmt_execute($del);

// 系統通知表單建立者（刪除者不是自己才通知）
if ($form['user_id'] !== $_SESSION['user_id']) {
    $sys_id  = getSystemUserId($conn);
    $content = "【表單刪除通知】\n您的表單「{$form['title']}」已被管理員刪除。";
    $ins = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($ins, 'iis', $sys_id, $form['user_id'], $content);
    mysqli_stmt_execute($ins);
}

echo json_encode(['success' => true]);
