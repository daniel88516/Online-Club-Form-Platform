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
    echo json_encode(['success' => false, 'message' => '缺少留言 ID']);
    exit();
}

// 取得留言擁有者
$stmt = mysqli_prepare($conn, "SELECT user_id FROM form_comments WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $comment_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) {
    echo json_encode(['success' => false, 'message' => '留言不存在']);
    exit();
}

// 權限：admin 可刪任何留言，member 只能刪自己的
if (!isAdmin() && $row['user_id'] !== $user_id) {
    echo json_encode(['success' => false, 'message' => '無權刪除此留言']);
    exit();
}

// ── BFS 收集整棵子樹的所有 content ──
// 目的：刪除 DB 之前，先把所有嵌入圖片的實體檔案清掉
function collectSubtreeContents($conn, $root_id) {
    $contents = [];
    $queue    = [$root_id];

    while (!empty($queue)) {
        $cid = array_shift($queue);

        // 取此節點 content
        $s = mysqli_prepare($conn, "SELECT content FROM form_comments WHERE id = ?");
        mysqli_stmt_bind_param($s, 'i', $cid);
        mysqli_stmt_execute($s);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
        if ($r) $contents[] = $r['content'];

        // 取子節點 id，加入佇列
        $s2 = mysqli_prepare($conn, "SELECT id FROM form_comments WHERE parent_id = ?");
        mysqli_stmt_bind_param($s2, 'i', $cid);
        mysqli_stmt_execute($s2);
        $res = mysqli_stmt_get_result($s2);
        while ($child = mysqli_fetch_assoc($res)) {
            $queue[] = $child['id'];
        }
    }

    return $contents;
}

// 從所有 content 中抽出圖片路徑並刪除實體檔案
$allContents = collectSubtreeContents($conn, $comment_id);
foreach ($allContents as $content) {
    preg_match_all('/<img[^>]+src="([^"]+)"/i', $content, $matches);
    foreach ($matches[1] as $imgUrl) {
        // 只刪本站 /uploads/ 下的檔案，避免誤刪外部圖片連結
        if (strpos($imgUrl, '/uploads/') === 0) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . $imgUrl;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}

// 刪除留言（子留言透過 FK ON DELETE CASCADE 自動一併刪除）
$del = mysqli_prepare($conn, "DELETE FROM form_comments WHERE id = ?");
mysqli_stmt_bind_param($del, 'i', $comment_id);
mysqli_stmt_execute($del);

echo json_encode(['success' => true]);
