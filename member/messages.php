<?php
$pageTitle = '訊息';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$me = $_SESSION['user_id'];

// 取得所有對話（每個對象只取最後一筆，並統計未讀）
$conversations = mysqli_query($conn, "
    SELECT
        other_id,
        u.username AS other_name,
        u.avatar AS other_avatar,
        last_content,
        last_time,
        SUM(unread) AS unread_count
    FROM (
        SELECT
            CASE WHEN sender_id = $me THEN receiver_id ELSE sender_id END AS other_id,
            content AS last_content,
            created_at AS last_time,
            CASE WHEN receiver_id = $me AND is_read = 0 THEN 1 ELSE 0 END AS unread
        FROM messages
        WHERE sender_id = $me OR receiver_id = $me
    ) t
    JOIN users u ON u.id = t.other_id
    GROUP BY other_id, u.username, u.avatar
    ORDER BY MAX(last_time) DESC
");

require_once '../config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="d-flex align-items-center gap-2 mb-4">
            <h5 class="fw-bold mb-0"><i class="bi bi-chat-dots"></i> 訊息</h5>
        </div>

        <?php if (mysqli_num_rows($conversations) === 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-chat-square-dots" style="font-size:3rem;"></i>
                <p class="mt-3">還沒有任何對話<br><small>點擊首頁表單作者名稱即可開始對話</small></p>
            </div>
        <?php else: ?>
            <div class="card">
                <ul class="list-group list-group-flush">
                    <?php while ($conv = mysqli_fetch_assoc($conversations)): ?>
                    <li class="list-group-item list-group-item-action p-0">
                        <a href="/member/conversation.php?with=<?= $conv['other_id'] ?>"
                           class="d-flex align-items-center gap-3 px-3 py-3 text-decoration-none text-reset">
                            <!-- 頭像 -->
                            <?= renderAvatar($conv['other_name'], $conv['other_avatar'], 44) ?>
                            <!-- 內容 -->
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold <?= $conv['unread_count'] > 0 ? 'text-primary' : '' ?>">
                                        <?= htmlspecialchars($conv['other_name']) ?>
                                    </span>
                                    <span class="small text-muted">
                                        <?= date('m/d H:i', strtotime($conv['last_time'])) ?>
                                    </span>
                                </div>
                                <div class="small text-muted text-truncate">
                                    <?= htmlspecialchars(mb_substr($conv['last_content'], 0, 40)) ?>
                                </div>
                            </div>
                            <!-- 未讀徽章 -->
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="badge bg-primary rounded-pill flex-shrink-0"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../config/footer.php'; ?>
