<?php
require_once '../config/session.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$form_id   = intval($_POST['form_id'] ?? 0);
$content   = trim($_POST['content'] ?? '');
$parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
$user_id   = $_SESSION['user_id'];

// content 為 Quill HTML，檢查純文字是否為空
if (!$form_id || empty(strip_tags($content))) {
    echo json_encode(['success' => false, 'message' => '留言不能為空']);
    exit();
}

// 確認表單存在且已發布
$check = mysqli_prepare($conn, "SELECT id FROM forms WHERE id = ? AND is_published = 1");
mysqli_stmt_bind_param($check, 'i', $form_id);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);
if (mysqli_stmt_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'message' => '表單不存在']);
    exit();
}

// 確認父留言存在
if ($parent_id) {
    $pcheck = mysqli_prepare($conn, "SELECT id FROM form_comments WHERE id = ? AND form_id = ?");
    mysqli_stmt_bind_param($pcheck, 'ii', $parent_id, $form_id);
    mysqli_stmt_execute($pcheck);
    mysqli_stmt_store_result($pcheck);
    if (mysqli_stmt_num_rows($pcheck) === 0) {
        echo json_encode(['success' => false, 'message' => '父留言不存在']);
        exit();
    }
}

// 新增留言（content 儲存 Quill HTML）
$ins = mysqli_prepare($conn, "INSERT INTO form_comments (form_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($ins, 'iisi', $form_id, $user_id, $content, $parent_id);
mysqli_stmt_execute($ins);
$comment_id = mysqli_insert_id($conn);

$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT fc.id, fc.content, fc.created_at, fc.parent_id, u.username, u.id AS user_id, u.avatar
    FROM form_comments fc
    JOIN users u ON fc.user_id = u.id
    WHERE fc.id = $comment_id
"));

echo json_encode([
    'success' => true,
    'comment' => [
        'id'         => $row['id'],
        'parent_id'  => $row['parent_id'],
        'username'   => $row['username'],
        'user_id'    => $row['user_id'],
        'avatar'     => $row['avatar'] ?? '',
        'content'    => $row['content'],
        'created_at' => date('Y/m/d H:i', strtotime($row['created_at'])),
        'like_count' => 0,
        'user_liked' => false,
    ]
]);
