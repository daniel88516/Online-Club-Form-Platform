<?php
ob_start();
require_once '../config/session.php';
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    ob_end_clean();
    echo json_encode(['success' => false]);
    exit();
}

$me = $_SESSION['user_id'];

$result = mysqli_query($conn, "
    SELECT
        t.other_id,
        u.username AS other_name,
        u.avatar   AS other_avatar,
        m.content  AS last_content,
        DATE_FORMAT(m.created_at, '%m/%d %H:%i') AS last_time,
        t.unread_count
    FROM (
        SELECT
            CASE WHEN sender_id = $me THEN receiver_id ELSE sender_id END AS other_id,
            MAX(id) AS last_msg_id,
            SUM(CASE WHEN receiver_id = $me AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
        FROM messages
        WHERE sender_id = $me OR receiver_id = $me
        GROUP BY other_id
    ) t
    JOIN users u    ON u.id = t.other_id
    JOIN messages m ON m.id = t.last_msg_id
    ORDER BY m.created_at DESC
");

$convs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['other_avatar'] = assetUrl($row['other_avatar'] ?? '');
    $convs[] = $row;
}

ob_end_clean();
echo json_encode(['success' => true, 'conversations' => $convs]);
