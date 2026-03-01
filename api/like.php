<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$form_id = intval($_POST['form_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$form_id) {
    echo json_encode(['success' => false, 'message' => '無效的表單']);
    exit();
}

// 檢查是否已按讚
$check = mysqli_prepare($conn, "SELECT id FROM form_likes WHERE form_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check, 'ii', $form_id, $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    // 已按讚 → 取消
    $del = mysqli_prepare($conn, "DELETE FROM form_likes WHERE form_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($del, 'ii', $form_id, $user_id);
    mysqli_stmt_execute($del);
    $liked = false;
} else {
    // 尚未按讚 → 新增
    $ins = mysqli_prepare($conn, "INSERT INTO form_likes (form_id, user_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($ins, 'ii', $form_id, $user_id);
    mysqli_stmt_execute($ins);
    $liked = true;
}

// 取得最新按讚數
$cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_likes WHERE form_id = $form_id"))['cnt'];

echo json_encode(['success' => true, 'liked' => $liked, 'count' => $cnt]);
