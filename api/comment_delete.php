<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/upload_cleanup.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$comment_id = intval($_POST['comment_id'] ?? 0);
$user_id    = $_SESSION['user_id'];

if (!$comment_id) {
    echo json_encode(['success' => false, 'message' => '缺少留言 ID']);
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT user_id FROM form_comments WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $comment_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) {
    echo json_encode(['success' => false, 'message' => '留言不存在']);
    exit();
}

if (!isAdmin() && (int)$row['user_id'] !== (int)$user_id) {
    echo json_encode(['success' => false, 'message' => '沒有刪除權限']);
    exit();
}

function collectSubtreeContents($conn, $root_id) {
    $contents = [];
    $queue    = [$root_id];

    while (!empty($queue)) {
        $cid = array_shift($queue);

        $s = mysqli_prepare($conn, "SELECT content FROM form_comments WHERE id = ?");
        mysqli_stmt_bind_param($s, 'i', $cid);
        mysqli_stmt_execute($s);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        if ($r) {
            $contents[] = $r['content'];
        }

        $s2 = mysqli_prepare($conn, "SELECT id FROM form_comments WHERE parent_id = ?");
        mysqli_stmt_bind_param($s2, 'i', $cid);
        mysqli_stmt_execute($s2);
        $res = mysqli_stmt_get_result($s2);
        while ($child = mysqli_fetch_assoc($res)) {
            $queue[] = (int)$child['id'];
        }
    }

    return $contents;
}

$cleanup_paths = [];
foreach (collectSubtreeContents($conn, $comment_id) as $content) {
    $cleanup_paths = array_merge($cleanup_paths, extractUploadPathsFromHtml($content));
}

$del = mysqli_prepare($conn, "DELETE FROM form_comments WHERE id = ?");
mysqli_stmt_bind_param($del, 'i', $comment_id);
$ok = mysqli_stmt_execute($del);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => '刪除失敗']);
    exit();
}

cleanupReplacedUploads($conn, $cleanup_paths, []);

echo json_encode(['success' => true]);
