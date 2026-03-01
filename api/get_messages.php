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

$with    = intval($_GET['with'] ?? 0);
$last_id = intval($_GET['last_id'] ?? 0);
$me      = $_SESSION['user_id'];

if (!$with) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit();
}

// 取得新訊息（id > last_id）
$stmt = mysqli_prepare($conn, "
    SELECT m.id, m.sender_id, m.content, m.is_read,
           DATE_FORMAT(m.created_at, '%Y/%m/%d %H:%i') AS created_at
    FROM messages m
    WHERE ((m.sender_id = ? AND m.receiver_id = ?)
        OR (m.sender_id = ? AND m.receiver_id = ?))
      AND m.id > ?
    ORDER BY m.id ASC
");
mysqli_stmt_bind_param($stmt, 'iiiii', $me, $with, $with, $me, $last_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

// 標記對方傳來的訊息為已讀
mysqli_query($conn, "UPDATE messages SET is_read = 1
    WHERE sender_id = $with AND receiver_id = $me AND is_read = 0");

// 取得我發給對方的最大已讀訊息 ID（用於已讀顯示）
$ackStmt = mysqli_prepare($conn, "SELECT COALESCE(MAX(id), 0) AS max_read_id FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 1");
mysqli_stmt_bind_param($ackStmt, 'ii', $me, $with);
mysqli_stmt_execute($ackStmt);
$ackRow    = mysqli_fetch_assoc(mysqli_stmt_get_result($ackStmt));
$maxReadId = (int)($ackRow['max_read_id'] ?? 0);

ob_end_clean();
echo json_encode(['success' => true, 'messages' => $messages, 'max_read_id' => $maxReadId]);
