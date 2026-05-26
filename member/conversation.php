<?php
$pageTitle = '對話';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$me      = $_SESSION['user_id'];
$with_id = intval($_GET['with'] ?? 0);

if (!$with_id || $with_id === $me) {
    header('Location: ' . APP_BASE . '/member/messages.php');
    exit();
}

// 取得對方資訊
$stmt = mysqli_prepare($conn, "SELECT id, username, avatar FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $with_id);
mysqli_stmt_execute($stmt);
$other = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$other) {
    header('Location: ' . APP_BASE . '/member/messages.php');
    exit();
}

$pageTitle = '與 ' . $other['username'] . ' 的對話';

// 載入歷史訊息並標記已讀
$history = mysqli_query($conn, "
    SELECT id, sender_id, content,
           DATE_FORMAT(created_at, '%Y/%m/%d %H:%i') AS created_at
    FROM messages
    WHERE (sender_id = $me AND receiver_id = $with_id)
       OR (sender_id = $with_id AND receiver_id = $me)
    ORDER BY id ASC
");
mysqli_query($conn, "UPDATE messages SET is_read = 1
    WHERE sender_id = $with_id AND receiver_id = $me AND is_read = 0");

require_once '../config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <!-- 頂部標題列 -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="<?= REL_BASE ?>member/messages.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <?= renderAvatar($other['username'], $other['avatar'], 36) ?>
            <h6 class="fw-bold mb-0"><?= htmlspecialchars($other['username']) ?></h6>
        </div>

        <!-- 訊息區 -->
        <div class="card mb-3">
            <div class="card-body p-3" id="msg-container"
                 style="height:480px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;">
                <?php
                $last_id = 0;
                while ($msg = mysqli_fetch_assoc($history)):
                    $is_me = ($msg['sender_id'] == $me);
                    $last_id = $msg['id'];
                ?>
                <div class="d-flex <?= $is_me ? 'justify-content-end' : 'justify-content-start' ?>"
                     data-msg-id="<?= $msg['id'] ?>">
                    <div class="px-3 py-2 rounded-3 small"
                         style="max-width:70%;
                                background:<?= $is_me ? 'var(--bs-primary,#0d6efd)' : 'var(--bs-secondary-bg)' ?>;
                                color:<?= $is_me ? '#fff' : 'inherit' ?>;
                                word-break:break-word;">
                        <?= nl2br(htmlspecialchars($msg['content'])) ?>
                        <div class="mt-1" style="font-size:0.7rem;opacity:0.7;text-align:<?= $is_me ? 'right' : 'left' ?>;">
                            <?= $msg['created_at'] ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>

                <?php if ($last_id === 0): ?>
                <div class="text-center text-muted small py-4" id="empty-hint">
                    開始和 <?= htmlspecialchars($other['username']) ?> 對話吧！
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 輸入框 -->
        <div class="input-group">
            <textarea id="msg-input" class="form-control" rows="2"
                      placeholder="輸入訊息… (Enter 送出，Shift+Enter 換行)"
                      style="resize:none;"></textarea>
            <button class="btn btn-glow-primary px-4" id="btn-send">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>

    </div>
</div>

<script>
const WITH_ID  = <?= $with_id ?>;
let lastId     = <?= $last_id ?>;
const $container = $('#msg-container');
const me       = <?= $me ?>;
const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || '#0d6efd';

// 捲到最底
function scrollBottom() {
    $container.scrollTop($container[0].scrollHeight);
}
scrollBottom();

// 建立訊息泡泡 HTML
function buildBubble(msg) {
    const isMe = (parseInt(msg.sender_id) === me);
    return `<div class="d-flex ${isMe ? 'justify-content-end' : 'justify-content-start'}" data-msg-id="${msg.id}">
        <div class="px-3 py-2 rounded-3 small"
             style="max-width:70%;background:${isMe ? primaryColor : 'var(--bs-secondary-bg)'};color:${isMe ? '#fff' : 'inherit'};word-break:break-word;">
            ${$('<span>').text(msg.content).html().replace(/\n/g,'<br>')}
            <div class="mt-1" style="font-size:0.7rem;opacity:0.7;text-align:${isMe ? 'right' : 'left'};">${msg.created_at}</div>
        </div>
    </div>`;
}

// 送出訊息
function sendMessage() {
    const content = $('#msg-input').val().trim();
    if (!content) return;
    $('#msg-input').val('').focus();
    $('#empty-hint').remove();

    $.post('<?= REL_BASE ?>api/send_message.php', { receiver_id: WITH_ID, content: content }, function (res) {
        if (res.success) {
            const bubble = buildBubble({ id: res.message_id, sender_id: me, content: content, created_at: res.created_at });
            $container.append(bubble);
            lastId = res.message_id;
            scrollBottom();
        }
    }, 'json');
}

$('#btn-send').on('click', sendMessage);
$('#msg-input').on('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// 輪詢新訊息（每 3 秒）
setInterval(function () {
    $.get('<?= REL_BASE ?>api/get_messages.php', { with: WITH_ID, last_id: lastId }, function (res) {
        if (!res.success || res.messages.length === 0) return;
        $('#empty-hint').remove();
        res.messages.forEach(function (msg) {
            if (!$('[data-msg-id="' + msg.id + '"]').length) {
                $container.append(buildBubble(msg));
                lastId = msg.id;
            }
        });
        scrollBottom();
    }, 'json');
}, 3000);
</script>

<?php require_once '../config/footer.php'; ?>
