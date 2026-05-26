<?php
require_once '../config/session.php';
require_once '../config/db.php';

$token  = trim($_GET['token'] ?? '');
$action = $_GET['action'] ?? '';

if (!$token || $action !== 'delete') {
    http_response_code(400);
    die('無效的請求');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM reports WHERE delete_token = ? AND status = 'pending'");
mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$report) {
    die('
    <div style="font-family:Arial;text-align:center;padding:60px;">
        <h2 style="color:#6c757d;">此連結已失效或已處理過</h2>
        <p style="color:#adb5bd;">該檢舉可能已被處理，或連結已過期。</p>
    </div>');
}

$type_label = $report['type'] === 'comment' ? '留言' : '表單';

if ($report['type'] === 'comment') {
    $d = mysqli_prepare($conn, "DELETE FROM form_comments WHERE id = ?");
    mysqli_stmt_bind_param($d, 'i', $report['target_id']);
    mysqli_stmt_execute($d);
} else {
    $finfo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, title FROM forms WHERE id = {$report['target_id']}"));
    $d = mysqli_prepare($conn, "DELETE FROM forms WHERE id = ?");
    mysqli_stmt_bind_param($d, 'i', $report['target_id']);
    mysqli_stmt_execute($d);

    if ($finfo) {
        $sys_id  = getSystemUserId($conn);
        $content = "【表單刪除通知】\n您的表單「{$finfo['title']}」已因檢舉被刪除。\n檢舉原因：{$report['reason']}";
        $ins = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $sys_id, $finfo['user_id'], $content);
        mysqli_stmt_execute($ins);
    }
}

$u = mysqli_prepare($conn, "UPDATE reports SET status = 'resolved', delete_token = NULL WHERE id = ?");
mysqli_stmt_bind_param($u, 'i', $report['id']);
mysqli_stmt_execute($u);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>處理完成</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center" style="min-height:80vh;">
    <div class="card shadow text-center p-5" style="max-width:420px;width:100%;">
        <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
        <h3 class="mt-3 fw-bold">刪除成功</h3>
        <p class="text-muted">該<?= $type_label ?>已被成功刪除。</p>
        <a href="<?= REL_BASE ?>index.php" class="btn btn-primary mt-2">返回首頁</a>
    </div>
</div>
</body>
</html>
