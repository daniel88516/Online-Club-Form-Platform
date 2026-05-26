<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$comment_id = intval($_POST['comment_id'] ?? 0);
$content    = trim($_POST['content'] ?? '');

if (!$comment_id || $content === '') {
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT user_id FROM form_comments WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $comment_id);
mysqli_stmt_execute($stmt);
$comment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$comment) {
    echo json_encode(['success' => false, 'message' => '留言不存在']);
    exit();
}

if ($comment['user_id'] !== $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => '無編輯權限']);
    exit();
}

$stmt = mysqli_prepare($conn, "UPDATE form_comments SET content = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $content, $comment_id);
mysqli_stmt_execute($stmt);

echo json_encode(['success' => true, 'content' => assetHtml($content)]);
