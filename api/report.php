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

$type      = $_POST['type'] ?? '';
$target_id = intval($_POST['target_id'] ?? 0);
$reason    = $_POST['reason'] ?? '';

$allowed_reasons = ['騷擾內容', '詐騙'];
if (!in_array($type, ['comment', 'form']) || !$target_id || !in_array($reason, $allowed_reasons)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit();
}

$reporter_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "INSERT INTO reports (type, target_id, reporter_id, reason) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '系統錯誤：' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_bind_param($stmt, 'siis', $type, $target_id, $reporter_id, $reason);
if (!mysqli_stmt_execute($stmt)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '檢舉失敗，請稍後再試']);
    exit();
}

// ── 系統通知：傳訊息給所有管理員 ──
$report_id = (int)mysqli_insert_id($conn);
$type_zh   = $type === 'form' ? '表單' : '留言';
$sys_id    = getSystemUserId($conn);
$content   = "【檢舉通知】\n類型：{$type_zh}\n原因：{$reason}\n目標 ID：{$target_id}\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:{$report_id}]";
$admins    = mysqli_query($conn, "SELECT id FROM users WHERE role='admin'");
$ins       = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
while ($admin = mysqli_fetch_assoc($admins)) {
    mysqli_stmt_bind_param($ins, 'iis', $sys_id, $admin['id'], $content);
    mysqli_stmt_execute($ins);
}

ob_end_clean();
echo json_encode(['success' => true, 'message' => '檢舉已送出，感謝您的回報！管理員將盡快處理。']);
