<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$comment_id = intval($_POST['comment_id'] ?? 0);
$user_id    = $_SESSION['user_id'];

if (!$comment_id) {
    echo json_encode(['success' => false, 'message' => '無效的留言']);
    exit();
}

// 檢查是否已按讚
$check = mysqli_prepare($conn, "SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check, 'ii', $comment_id, $user_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    $del = mysqli_prepare($conn, "DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($del, 'ii', $comment_id, $user_id);
    mysqli_stmt_execute($del);
    $liked = false;
} else {
    $ins = mysqli_prepare($conn, "INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($ins, 'ii', $comment_id, $user_id);
    mysqli_stmt_execute($ins);
    $liked = true;
}

$cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM comment_likes WHERE comment_id = $comment_id"))['cnt'];

echo json_encode(['success' => true, 'liked' => $liked, 'count' => $cnt]);
