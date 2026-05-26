<?php
// 社交功能區塊（按讚 / 收藏 / 留言樹）
$cid      = intval($id ?? 0);
$cur_user = isLoggedIn() ? $_SESSION['user_id'] : 0;

// 取目前登入者頭像（從DB，而非session，確保上傳後即時正確）
$_cur_avatar = null;
if ($cur_user) {
    $__av = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], "SELECT avatar FROM users WHERE id = $cur_user"));
    $_cur_avatar = $__av['avatar'] ?? null;
}

// 取得所有留言（含按讚數）
$all_comments = [];
$clist = mysqli_query($GLOBALS['conn'], "
    SELECT fc.id, fc.parent_id, fc.content, fc.created_at, u.username, u.id AS user_id, u.avatar,
           COUNT(DISTINCT cl.id) AS like_count,
           MAX(CASE WHEN cl.user_id = $cur_user THEN 1 ELSE 0 END) AS user_liked
    FROM form_comments fc
    JOIN users u ON fc.user_id = u.id
    LEFT JOIN comment_likes cl ON fc.id = cl.comment_id
    WHERE fc.form_id = $cid
    GROUP BY fc.id
    ORDER BY fc.created_at ASC
");
while ($row = mysqli_fetch_assoc($clist)) {
    $all_comments[] = $row;
}

// 建立留言樹
function buildCommentTree($comments, $parentId = null) {
    $tree = [];
    foreach ($comments as $c) {
        if ($c['parent_id'] == $parentId) {
            $c['children'] = buildCommentTree($comments, $c['id']);
            $tree[] = $c;
        }
    }
    return $tree;
}

// 遞迴渲染（每層預設收合）
function renderComments($comments, $depth = 0) {
    $isLoggedIn = isLoggedIn();
    $cur_user   = isLoggedIn() ? $_SESSION['user_id'] : 0;

    foreach ($comments as $c):
        $timeStr    = date('Y/m/d H:i', strtotime($c['created_at']));
        $childCount = count($c['children']);
?>
<div class="comment-node" id="comment-<?= $c['id'] ?>" style="margin-bottom:8px;">
    <div class="d-flex gap-2">
        <!-- 頭像 + 垂直線 -->
        <div class="d-flex flex-column align-items-center" style="flex-shrink:0;">
            <?= renderAvatarDropdown($c['username'], $c['avatar'] ?? null, $c['user_id'], 32, $cur_user) ?>
            <?php if ($childCount > 0): ?>
            <div class="thread-line btn-toggle-replies"
                 data-comment-id="<?= $c['id'] ?>"
                 style="width:2px;background:#dee2e6;flex-grow:1;margin-top:4px;cursor:pointer;min-height:20px;"></div>
            <?php endif; ?>
        </div>

        <div class="flex-grow-1 pb-2">
            <!-- 留言泡泡：content 為 Quill HTML，直接渲染 -->
            <div class="bg-light rounded px-3 py-2 mb-1 d-flex justify-content-between align-items-start gap-1">
                <div class="flex-grow-1">
                    <span class="fw-semibold small"><?= htmlspecialchars($c['username']) ?></span>
                    <span class="text-muted small ms-2"><?= $timeStr ?></span>
                    <div class="small mt-1 ql-content"><?= assetHtml(autolink($c['content'])) ?></div>
                </div>
                <div class="dropdown" style="flex-shrink:0;">
                    <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($isLoggedIn && $c['user_id'] == $cur_user): ?>
                        <li>
                            <button class="dropdown-item btn-edit-comment" data-comment-id="<?= $c['id'] ?>">
                                <i class="bi bi-pencil text-primary me-2"></i> 編輯留言
                            </button>
                        </li>
                        <?php endif; ?>
                        <?php if ($isLoggedIn && (isAdmin() || $c['user_id'] == $cur_user)): ?>
                        <li>
                            <button class="dropdown-item text-danger btn-delete-comment" data-comment-id="<?= $c['id'] ?>">
                                <i class="bi bi-trash me-2"></i> 刪除留言
                            </button>
                        </li>
                        <?php endif; ?>
                        <?php if ($isLoggedIn): ?>
                        <li>
                            <button class="dropdown-item btn-report-comment" data-comment-id="<?= $c['id'] ?>">
                                <i class="bi bi-flag me-2"></i> 檢舉留言
                            </button>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- 操作列 -->
            <div class="d-flex align-items-center gap-3 ps-1">
                <?php if ($isLoggedIn): ?>
                <button class="btn btn-sm px-2 btn-comment-like <?= $c['user_liked'] ? 'text-body-emphasis' : 'text-muted' ?>"
                        data-comment-id="<?= $c['id'] ?>">
                    <i class="bi <?= $c['user_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    <span class="comment-like-count small"><?= $c['like_count'] ?: '' ?></span>
                </button>
                <button class="btn btn-sm px-2 text-muted btn-reply"
                        data-comment-id="<?= $c['id'] ?>"
                        data-username="<?= htmlspecialchars($c['username']) ?>">
                    <small>回覆</small>
                </button>
                <?php else: ?>
                <span class="text-muted small"><i class="bi bi-heart"></i><?= $c['like_count'] ? ' '.$c['like_count'] : '' ?></span>
                <?php endif; ?>

                <!-- 展開子回覆按鈕 -->
                <?php if ($childCount > 0): ?>
                <button class="btn btn-sm px-2 text-muted btn-toggle-replies"
                        data-comment-id="<?= $c['id'] ?>">
                    <small><i class="bi bi-arrow-return-right"></i> <?= $childCount ?> 則回覆</small>
                </button>
                <?php endif; ?>
            </div>

            <!-- 回覆輸入框（Quill，預設隱藏） -->
            <?php if ($isLoggedIn): ?>
            <div class="reply-box mt-2" id="reply-box-<?= $c['id'] ?>" style="display:none;">
                <div id="quill-reply-<?= $c['id'] ?>" class="quill-comment-editor mb-1"></div>
                <div class="d-flex justify-content-end gap-1 mt-1">
                    <button class="btn btn-glow-primary btn-sm btn-submit-reply"
                            data-form-id="<?= $GLOBALS['id'] ?? 0 ?>"
                            data-parent-id="<?= $c['id'] ?>">
                        <i class="bi bi-send"></i> 送出
                    </button>
                    <button class="btn btn-outline-secondary btn-sm btn-cancel-reply"
                            data-comment-id="<?= $c['id'] ?>">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- 編輯輸入框（預設隱藏） -->
            <?php if ($isLoggedIn && $c['user_id'] == $cur_user): ?>
            <div class="edit-box mt-2" id="edit-box-<?= $c['id'] ?>" style="display:none;">
                <div id="quill-edit-<?= $c['id'] ?>" class="quill-comment-editor mb-1"></div>
                <div class="d-flex justify-content-end gap-1 mt-1">
                    <button class="btn btn-glow-primary btn-sm btn-submit-edit" data-comment-id="<?= $c['id'] ?>">
                        <i class="bi bi-check-lg"></i> 儲存
                    </button>
                    <button class="btn btn-outline-secondary btn-sm btn-cancel-edit" data-comment-id="<?= $c['id'] ?>">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- 子回覆（預設收合） -->
            <?php if ($childCount > 0): ?>
            <div class="replies-container mt-2" id="replies-<?= $c['id'] ?>" style="display:none;">
                <?php renderComments($c['children'], $depth + 1); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    endforeach;
}

$commentTree         = buildCommentTree($all_comments);
$comment_count_total = count($all_comments);
?>

<!-- 按讚 / 收藏 列 -->
<div class="card mt-3 mb-3">
    <div class="card-body d-flex justify-content-around py-2">
        <?php if (isLoggedIn()): ?>
        <button id="btn-like" class="btn btn-sm <?= ($user_liked ?? 0) ? 'text-danger' : 'text-muted' ?>">
            <i class="bi <?= ($user_liked ?? 0) ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
            <span id="like-count"><?= $like_count ?? 0 ?></span> 個讚
        </button>
        <?php else: ?>
        <span class="text-muted small"><i class="bi bi-heart"></i> <?= $like_count ?? 0 ?> 個讚</span>
        <?php endif; ?>
        <button id="btn-toggle-comments" class="btn btn-sm text-muted">
            <i class="bi bi-chat"></i>
            <span id="comment-count"><?= $comment_count_total ?></span> 則回覆
        </button>
        <?php if (isLoggedIn()): ?>
        <button id="btn-bookmark" class="btn btn-sm <?= ($user_bookmarked ?? 0) ? 'text-warning' : 'text-muted' ?>">
            <i class="bi <?= ($user_bookmarked ?? 0) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
            <span id="bookmark-count"><?= $bookmark_count ?? 0 ?></span> 收藏
        </button>
        <?php else: ?>
        <span class="text-muted small"><i class="bi bi-bookmark"></i> <?= $bookmark_count ?? 0 ?> 收藏</span>
        <?php endif; ?>
    </div>
</div>

<!-- 留言區（預設收合） -->
<div id="comments" class="card mb-4" style="display:none;">
    <div class="card-body">

        <!-- 頂層留言框 -->
        <?php if (isLoggedIn()): ?>
        <div class="d-flex gap-2 mb-3">
            <?= renderAvatar($_SESSION['username'], $_cur_avatar, 32) ?>
            <div class="flex-grow-1">
                <!-- 收合狀態 -->
                <div id="top-reply-collapsed">
                    <input type="text" id="top-reply-trigger" class="form-control form-control-sm"
                           placeholder="回覆這則表單..." readonly style="cursor:pointer;">
                </div>
                <!-- 展開狀態：Quill 編輯器（圖文混排） -->
                <form id="comment-form" style="display:none;">
                    <div id="quill-main" class="quill-comment-editor mb-2"></div>
                    <div class="d-flex justify-content-end gap-2 mt-1">
                        <button type="button" id="btn-cancel-top" class="btn btn-outline-secondary btn-sm">取消</button>
                        <button type="submit" class="btn btn-glow-primary btn-sm">
                            <i class="bi bi-send"></i> 送出
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center mb-3">
            <a href="<?= REL_BASE ?>login.php" class="btn btn-outline-primary btn-sm">登入後回覆</a>
        </div>
        <?php endif; ?>

        <!-- 留言列表 -->
        <div id="comments-list">
            <?php if (empty($commentTree)): ?>
                <p class="text-muted small text-center py-2" id="no-comment-hint">尚無回覆，成為第一個！</p>
            <?php else: ?>
                <?php renderComments($commentTree); ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
const FORM_ID      = <?= intval($id ?? 0) ?>;
const CUR_USER_ID  = <?= intval($cur_user) ?>;
const CUR_USERNAME = <?= json_encode($_SESSION['username'] ?? '') ?>;
const CUR_AVATAR   = <?= json_encode(assetUrl($_cur_avatar ?? '')) ?>;
const IS_ADMIN     = <?= isAdmin() ? 'true' : 'false' ?>;
let quillMain = null;
const quillInstances = {};
const quillEditInstances = {};

// ── 共用：上傳圖片並插入 Quill ──
function quillImageHandler(quill) {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();
    input.onchange = () => {
        const file = input.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('image', file);
        fd.append('type', 'comments');
        $.ajax({
            url: '<?= REL_BASE ?>api/upload.php', type: 'POST',
            data: fd, contentType: false, processData: false,
            success: res => {
                if (res.success) {
                    const range = quill.getSelection() || { index: quill.getLength() };
                    quill.insertEmbed(range.index, 'image', res.path);
                    quill.setSelection(range.index + 1);
                }
            }
        });
    };
}

// ── 延遲初始化回覆框 Quill（點擊時才建立） ──
function getOrInitReplyQuill(cid, username) {
    if (!quillInstances[cid]) {
        quillInstances[cid] = new Quill(`#quill-reply-${cid}`, {
            theme: 'snow',
            placeholder: `回覆 @${username}...`,
            modules: {
                toolbar: {
                    container: [['bold', 'italic', 'strike'], ['image'], ['clean']],
                    handlers: { image: function () { quillImageHandler(quillInstances[cid]); } }
                }
            }
        });
        attachAutoLink(quillInstances[cid]);
        attachPasteImageHandler(quillInstances[cid]);
    }
    return quillInstances[cid];
}

$(document).ready(function () {
    // ── 初始化主留言 Quill ──
    if ($('#quill-main').length) {
        quillMain = new Quill('#quill-main', {
            theme: 'snow',
            placeholder: '回覆這則表單...',
            modules: {
                toolbar: {
                    container: [['bold', 'italic', 'strike'], ['image'], ['clean']],
                    handlers: { image: function () { quillImageHandler(quillMain); } }
                }
            }
        });
        attachAutoLink(quillMain);
        attachPasteImageHandler(quillMain);
    }
});

// ── 表單按讚 ──
$(document).on('click', '#btn-like', function () {
    const btn = $(this);
    $.post('<?= REL_BASE ?>api/like.php', { form_id: FORM_ID }, function (res) {
        if (!res.success) return;
        $('#like-count').text(res.count);
        btn.toggleClass('text-muted', !res.liked).toggleClass('text-body-emphasis', res.liked);
        btn.find('i').toggleClass('bi-heart', !res.liked).toggleClass('bi-heart-fill', res.liked);
    }, 'json');
});

// ── 表單收藏 ──
$(document).on('click', '#btn-bookmark', function () {
    const btn = $(this);
    $.post('<?= REL_BASE ?>api/bookmark.php', { form_id: FORM_ID }, function (res) {
        if (!res.success) return;
        $('#bookmark-count').text(res.count);
        btn.toggleClass('text-muted', !res.bookmarked).toggleClass('text-warning', res.bookmarked);
        btn.find('i').toggleClass('bi-bookmark', !res.bookmarked).toggleClass('bi-bookmark-fill', res.bookmarked);
    }, 'json');
});

// ── 展開/收合留言區 ──
$(document).on('click', '#btn-toggle-comments', function () {
    const isOpen = $('#comments').is(':visible');
    $('#comments').slideToggle(200);
    $(this).find('i').toggleClass('bi-chat', isOpen).toggleClass('bi-chat-fill', !isOpen);
});

// ── 頂層回覆框：點擊展開 ──
$(document).on('click', '#top-reply-trigger', function () {
    $('#top-reply-collapsed').hide();
    $('#comment-form').show();
    setTimeout(() => quillMain && quillMain.focus(), 50);
});

$(document).on('click', '#btn-cancel-top', function () {
    $('#comment-form').hide();
    $('#top-reply-collapsed').show();
    if (quillMain) quillMain.setContents([]);
});

// ── 送出頂層留言 ──
$(document).on('submit', '#comment-form', function (e) {
    e.preventDefault();
    if (!quillMain || quillMain.getText().trim() === '') return;
    const content = quillMain.root.innerHTML;

    $.post('<?= REL_BASE ?>api/comment.php', { form_id: FORM_ID, content: content }, function (res) {
        if (!res.success) return;
        $('#no-comment-hint').remove();
        $('#comments-list').append(makeCommentHTML(res.comment));
        quillMain.setContents([]);
        $('#comment-form').hide();
        $('#top-reply-collapsed').show();
        $('#comment-count').text(parseInt($('#comment-count').text()) + 1);
        scrollToComment(`#comment-${res.comment.id}`);
    }, 'json');
});

// ── 展開/收合子回覆 ──
$(document).on('click', '.btn-toggle-replies', function () {
    const cid       = $(this).data('comment-id');
    const container = $(`#replies-${cid}`);
    const isOpen    = container.is(':visible');
    container.slideToggle(200);
    const btn   = $(`.btn-toggle-replies[data-comment-id="${cid}"]`).filter('button');
    const count = container.find('.comment-node').length;
    btn.find('small').html(
        isOpen
        ? `<i class="bi bi-arrow-return-right"></i> ${count} 則回覆`
        : `<i class="bi bi-arrow-return-right"></i> 收起回覆`
    );
});

// ── 顯示/隱藏回覆框 ──
$(document).on('click', '.btn-reply', function () {
    const cid      = $(this).data('comment-id');
    const username = $(this).data('username');
    const box      = $(`#reply-box-${cid}`);
    // 關閉其他開著的回覆框
    $('.reply-box').not(box).hide();
    const wasVisible = box.is(':visible');
    box.toggle(!wasVisible);
    if (!wasVisible) {
        getOrInitReplyQuill(cid, username);
        setTimeout(() => quillInstances[cid] && quillInstances[cid].focus(), 80);
    }
});

$(document).on('click', '.btn-cancel-reply', function () {
    const cid = $(this).data('comment-id');
    $(`#reply-box-${cid}`).hide();
    if (quillInstances[cid]) quillInstances[cid].setContents([]);
});

// ── 送出子回覆 ──
$(document).on('click', '.btn-submit-reply', function () {
    const parentId = $(this).data('parent-id');
    const quill    = quillInstances[parentId];
    if (!quill || quill.getText().trim() === '') return;
    const content  = quill.root.innerHTML;

    $.post('<?= REL_BASE ?>api/comment.php',
        { form_id: FORM_ID, content: content, parent_id: parentId },
        function (res) {
            if (!res.success) return;

            let container = $(`#replies-${parentId}`);
            if (!container.length) {
                $(`#comment-${parentId} > .d-flex > .flex-grow-1`).append(
                    `<div class="replies-container mt-2" id="replies-${parentId}"></div>`
                );
                container = $(`#replies-${parentId}`);
                $(`#comment-${parentId} .btn-reply`).after(
                    `<button class="btn btn-sm p-0 text-muted btn-toggle-replies" data-comment-id="${parentId}">
                        <small><i class="bi bi-arrow-return-right"></i> 1 則回覆</small>
                    </button>`
                );
            }

            container.append(makeCommentHTML(res.comment)).show();
            quill.setContents([]);
            $(`#reply-box-${parentId}`).hide();
            $('#comment-count').text(parseInt($('#comment-count').text()) + 1);
            $(`.btn-toggle-replies[data-comment-id="${parentId}"]`).filter('button')
              .find('small').html(`<i class="bi bi-arrow-return-right"></i> 收起回覆`);
            scrollToComment(`#comment-${res.comment.id}`);
        }, 'json');
});

// ── 刪除留言 ──
$(document).on('click', '.btn-delete-comment', function () {
    const commentId = $(this).data('comment-id');
    window.cuteConfirm({ icon: '💬', msg: '確定要刪除這則留言嗎？', sub: '此操作無法復原。' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/comment_delete.php', { comment_id: commentId }, function (res) {
            if (!res.success) { alert(res.message); return; }
            const node = $(`#comment-${commentId}`);
            const deletedCount = node.find('.comment-node').length + 1;
            node.fadeOut(300, function () { $(this).remove(); });
            const cur = parseInt($('#comment-count').text()) || 0;
            $('#comment-count').text(Math.max(0, cur - deletedCount));
        }, 'json');
    });
});

// ── 編輯留言 ──
$(document).on('click', '.btn-edit-comment', function () {
    const cid = $(this).data('comment-id');
    const box = $(`#edit-box-${cid}`);
    // 關閉其他回覆框 / 編輯框
    $('.reply-box').hide();
    $('.edit-box').not(box).hide();
    const wasVisible = box.is(':visible');
    if (wasVisible) { box.hide(); return; }
    box.show();
    if (!quillEditInstances[cid]) {
        quillEditInstances[cid] = new Quill(`#quill-edit-${cid}`, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [['bold', 'italic', 'strike'], ['image'], ['clean']],
                    handlers: { image: function () { quillImageHandler(quillEditInstances[cid]); } }
                }
            }
        });
        attachAutoLink(quillEditInstances[cid]);
        attachPasteImageHandler(quillEditInstances[cid]);
    }
    const currentHTML = $(`#comment-${cid} .ql-content`).first().html();
    quillEditInstances[cid].clipboard.dangerouslyPasteHTML(currentHTML);
    setTimeout(() => quillEditInstances[cid].focus(), 80);
});

$(document).on('click', '.btn-cancel-edit', function () {
    $(`#edit-box-${$(this).data('comment-id')}`).hide();
});

$(document).on('click', '.btn-submit-edit', function () {
    const cid   = $(this).data('comment-id');
    const quill = quillEditInstances[cid];
    if (!quill || quill.getText().trim() === '') return;
    const content = quill.root.innerHTML;
    $.post('<?= REL_BASE ?>api/comment_edit.php', { comment_id: cid, content: content }, function (res) {
        if (!res.success) { alert(res.message); return; }
        $(`#comment-${cid} .ql-content`).first().html(res.content);
        $(`#edit-box-${cid}`).hide();
    }, 'json').fail(function () {
        alert('網路錯誤，請稍後再試');
    });
});

// ── 留言按讚 ──
$(document).on('click', '.btn-comment-like', function () {
    const btn       = $(this);
    const commentId = btn.data('comment-id');
    $.post('<?= REL_BASE ?>api/comment_like.php', { comment_id: commentId }, function (res) {
        if (!res.success) return;
        btn.find('.comment-like-count').text(res.count || '');
        btn.toggleClass('text-muted', !res.liked).toggleClass('text-body-emphasis', res.liked);
        btn.find('i').toggleClass('bi-heart', !res.liked).toggleClass('bi-heart-fill', res.liked);
    }, 'json');
});

// ── 產生頭像 HTML（支援圖片或字母頭像，可帶互動wrapper） ──
function makeAvatarHTML(uid, username, avatarUrl, size, showInteract) {
    size = size || 32;
    const s = `width:${size}px;height:${size}px;border-radius:50%;flex-shrink:0;`;
    let avatarInner;
    if (avatarUrl) {
        avatarInner = `<img src="${assetUrl(avatarUrl)}" class="site-avatar" style="${s}object-fit:cover;" alt="">`;
    } else {
        const fs = Math.round(size * 0.44);
        const initial = (username.charAt(0) || '?').toUpperCase();
        avatarInner = `<span class="site-avatar" style="${s}background:var(--bs-primary,#0d6efd);color:var(--bs-primary-text,#fff);display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:${fs}px;">${initial}</span>`;
    }
    if (showInteract && uid && uid != CUR_USER_ID) {
        const showChat = uid != CUR_USER_ID ? 1 : 0;
        const uname = username.replace(/"/g, '&quot;');
        const av    = (assetUrl(avatarUrl || '')).replace(/"/g, '&quot;');
        return `<span class="avd-wrap" data-uid="${uid}" data-uname="${uname}" data-avatar="${av}" data-chat="${showChat}">${avatarInner}</span>`;
    }
    return avatarInner;
}

// ── 產生新留言 HTML（content 是 Quill HTML，直接插入） ──
function makeCommentHTML(c) {
    const avatarHtml = makeAvatarHTML(c.user_id || CUR_USER_ID, c.username, c.avatar || CUR_AVATAR, 32, true);
    return `
    <div class="comment-node" id="comment-${c.id}" style="margin-bottom:8px;">
        <div class="d-flex gap-2">
            <div class="d-flex flex-column align-items-center" style="flex-shrink:0;">
                ${avatarHtml}
            </div>
            <div class="flex-grow-1 pb-2">
                <div class="bg-light rounded px-3 py-2 mb-1 d-flex justify-content-between align-items-start gap-1">
                    <div class="flex-grow-1">
                        <span class="fw-semibold small">${c.username}</span>
                        <span class="text-muted small ms-2">${c.created_at}</span>
                        <div class="small mt-1 ql-content">${c.content}</div>
                    </div>
                    <div class="dropdown" style="flex-shrink:0;">
                        <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button class="dropdown-item btn-edit-comment" data-comment-id="${c.id}">
                                <i class="bi bi-pencil text-primary me-2"></i> 編輯留言
                            </button></li>
                            <li><button class="dropdown-item text-danger btn-delete-comment" data-comment-id="${c.id}">
                                <i class="bi bi-trash me-2"></i> 刪除留言
                            </button></li>
                            <li><button class="dropdown-item btn-report-comment" data-comment-id="${c.id}">
                                <i class="bi bi-flag me-2"></i> 檢舉留言
                            </button></li>
                        </ul>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 ps-1">
                    <button class="btn btn-sm px-2 text-muted btn-comment-like" data-comment-id="${c.id}">
                        <i class="bi bi-heart"></i> <span class="comment-like-count small"></span>
                    </button>
                    <button class="btn btn-sm px-2 text-muted btn-reply"
                            data-comment-id="${c.id}" data-username="${c.username}">
                        <small>回覆</small>
                    </button>
                </div>
                <div class="reply-box mt-2" id="reply-box-${c.id}" style="display:none;">
                    <div id="quill-reply-${c.id}" class="quill-comment-editor mb-1"></div>
                    <div class="d-flex justify-content-end gap-1 mt-1">
                        <button class="btn btn-glow-primary btn-sm btn-submit-reply"
                                data-form-id="${FORM_ID}" data-parent-id="${c.id}">
                            <i class="bi bi-send"></i> 送出
                        </button>
                        <button class="btn btn-outline-secondary btn-sm btn-cancel-reply"
                                data-comment-id="${c.id}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <div class="edit-box mt-2" id="edit-box-${c.id}" style="display:none;">
                    <div id="quill-edit-${c.id}" class="quill-comment-editor mb-1"></div>
                    <div class="d-flex justify-content-end gap-1 mt-1">
                        <button class="btn btn-glow-primary btn-sm btn-submit-edit" data-comment-id="${c.id}">
                            <i class="bi bi-check-lg"></i> 儲存
                        </button>
                        <button class="btn btn-outline-secondary btn-sm btn-cancel-edit"
                                data-comment-id="${c.id}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

// ── 工具：捲動到元素 ──
function scrollToComment(selector) {
    const el = $(selector);
    if (el.length) $('html, body').animate({ scrollTop: el.offset().top - 80 }, 400);
}

// ── 檢舉共用狀態 ──
let reportTarget = { type: '', id: 0 };

// 開啟留言檢舉
$(document).on('click', '.btn-report-comment', function () {
    window.cuteReport('comment', $(this).data('comment-id'), '檢舉留言');
});

// 開啟表單檢舉
$(document).on('click', '#btn-report-form', function () {
    window.cuteReport('form', FORM_ID, '檢舉表單');
});


// 刪除表單
$(document).on('click', '#btn-delete-form', function () {
    window.cuteConfirm({ icon: '📋', msg: '確定要刪除這個表單嗎？', sub: '此操作無法復原。' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/delete_form.php', { form_id: FORM_ID }, function (res) {
            if (res.success) {
                window.location.href = REL_BASE + 'index.php';
            } else {
                alert(res.message || '刪除失敗');
            }
        }, 'json').fail(function () {
            alert('網路錯誤，請稍後再試');
        });
    });
});
</script>
