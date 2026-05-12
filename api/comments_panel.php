<?php
require_once '../config/session.php';
require_once '../config/db.php';

$fid        = intval($_GET['form_id'] ?? 0);
if (!$fid) exit();
$cur_user   = isLoggedIn() ? $_SESSION['user_id'] : 0;
$isLoggedIn = isLoggedIn();

/* ── 取留言 ── */
$all_comments = [];
$clist = mysqli_query($conn, "
    SELECT fc.id, fc.parent_id, fc.content, fc.created_at,
           u.username, u.id AS user_id, u.avatar,
           COUNT(DISTINCT cl.id) AS like_count,
           MAX(CASE WHEN cl.user_id = {$cur_user} THEN 1 ELSE 0 END) AS user_liked
    FROM form_comments fc
    JOIN users u ON fc.user_id = u.id
    LEFT JOIN comment_likes cl ON fc.id = cl.comment_id
    WHERE fc.form_id = {$fid}
    GROUP BY fc.id
    ORDER BY fc.created_at ASC
");
while ($row = mysqli_fetch_assoc($clist)) {
    $all_comments[] = $row;
}

/* ── 建樹 ── */
function panelBuildTree($comments, $parentId = null) {
    $tree = [];
    foreach ($comments as $c) {
        if ($c['parent_id'] == $parentId) {
            $c['children'] = panelBuildTree($comments, $c['id']);
            $tree[] = $c;
        }
    }
    return $tree;
}

/* ── 渲染留言樹 ── */
function panelRender($comments, $isLoggedIn, $cur_user, $fid) {
    foreach ($comments as $c):
        $timeStr    = date('Y/m/d H:i', strtotime($c['created_at']));
        $childCount = count($c['children']);
?>
<div class="comment-node" id="comment-<?= $c['id'] ?>" style="margin-bottom:8px;">
    <div class="d-flex gap-2">
        <div class="d-flex flex-column align-items-center" style="flex-shrink:0;">
            <?= renderAvatarDropdown($c['username'], $c['avatar'] ?? null, $c['user_id'], 32, $cur_user) ?>
            <?php if ($childCount > 0): ?>
            <div class="thread-line btn-toggle-replies"
                 data-comment-id="<?= $c['id'] ?>"
                 style="width:2px;background:#dee2e6;flex-grow:1;margin-top:4px;cursor:pointer;min-height:20px;"></div>
            <?php endif; ?>
        </div>
        <div class="flex-grow-1 pb-2">
            <div class="bg-light rounded px-3 py-2 mb-1 d-flex justify-content-between align-items-start gap-1">
                <div class="flex-grow-1">
                    <span class="fw-semibold small"><?= htmlspecialchars($c['username']) ?></span>
                    <span class="text-muted small ms-2"><?= $timeStr ?></span>
                    <div class="small mt-1 ql-content"><?= autolink($c['content']) ?></div>
                </div>
                <div class="dropdown" style="flex-shrink:0;">
                    <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                <span class="text-muted small">
                    <i class="bi bi-heart"></i><?= $c['like_count'] ? ' '.$c['like_count'] : '' ?>
                </span>
                <?php endif; ?>
                <?php if ($childCount > 0): ?>
                <button class="btn btn-sm px-2 text-muted btn-toggle-replies"
                        data-comment-id="<?= $c['id'] ?>">
                    <small><i class="bi bi-arrow-return-right"></i> <?= $childCount ?> 則回覆</small>
                </button>
                <?php endif; ?>
            </div>
            <?php if ($isLoggedIn): ?>
            <div class="reply-box mt-2" id="reply-box-<?= $c['id'] ?>" style="display:none;">
                <div id="quill-reply-<?= $c['id'] ?>" class="quill-comment-editor mb-1"></div>
                <div class="d-flex justify-content-end gap-1 mt-1">
                    <button class="btn btn-primary btn-sm btn-submit-reply"
                            data-form-id="<?= $fid ?>"
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
            <?php if ($isLoggedIn && $c['user_id'] == $cur_user): ?>
            <div class="edit-box mt-2" id="edit-box-<?= $c['id'] ?>" style="display:none;">
                <div id="quill-edit-<?= $c['id'] ?>" class="quill-comment-editor mb-1"></div>
                <div class="d-flex justify-content-end gap-1 mt-1">
                    <button class="btn btn-primary btn-sm btn-submit-edit" data-comment-id="<?= $c['id'] ?>">
                        <i class="bi bi-check-lg"></i> 儲存
                    </button>
                    <button class="btn btn-outline-secondary btn-sm btn-cancel-edit" data-comment-id="<?= $c['id'] ?>">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($childCount > 0): ?>
            <div class="replies-container mt-2" id="replies-<?= $c['id'] ?>" style="display:none;">
                <?php panelRender($c['children'], $isLoggedIn, $cur_user, $fid); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    endforeach;
}

$commentTree = panelBuildTree($all_comments);
$username    = $_SESSION['username'] ?? 'U';

// 從 DB 取最新頭像（session 不一定即時更新）
$_cur_avatar_panel = null;
if ($cur_user) {
    $__avp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT avatar FROM users WHERE id = $cur_user"));
    $_cur_avatar_panel = $__avp['avatar'] ?? null;
}
?>

<!-- ── 留言輸入框 ── -->
<?php if ($isLoggedIn): ?>
<div class="d-flex gap-2 mb-3 pt-2">
    <?= renderAvatar($username, $_cur_avatar_panel, 32) ?>
    <div class="flex-grow-1">
        <div id="top-reply-collapsed-f<?= $fid ?>">
            <input type="text" id="top-reply-trigger-f<?= $fid ?>"
                   class="form-control form-control-sm top-reply-trigger-input"
                   placeholder="回覆這則表單..." readonly style="cursor:pointer;">
        </div>
        <form id="comment-form-f<?= $fid ?>" style="display:none;">
            <div id="quill-main-f<?= $fid ?>" class="quill-comment-editor mb-2"></div>
            <div class="d-flex justify-content-end gap-2 mt-1">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        id="btn-cancel-top-f<?= $fid ?>">取消</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-send"></i> 送出
                </button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="text-center mb-3 pt-2">
    <a href="<?= APP_BASE ?>/login.php" class="btn btn-outline-primary btn-sm">登入後回覆</a>
</div>
<?php endif; ?>

<!-- ── 留言列表 ── -->
<div id="comments-list-f<?= $fid ?>">
    <?php if (empty($commentTree)): ?>
        <p class="text-muted small text-center py-2" id="no-comment-hint-f<?= $fid ?>">尚無回覆，成為第一個！</p>
    <?php else: ?>
        <?php panelRender($commentTree, $isLoggedIn, $cur_user, $fid); ?>
    <?php endif; ?>
</div>

<script>
/* ── 每個面板用 IIFE 隔離，$root 作為事件代理根，避免多面板衝突 ── */
(function () {
    const FORM_ID = <?= $fid ?>;
    const $root   = $('#feed-comments-' + FORM_ID);
    let   quillMain      = null;
    const quillInstances = {};
    const quillEditInstances = {};

    /* 上傳圖片並插入 Quill */
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
                url: (window.REL_BASE||'') + 'api/upload.php', type: 'POST',
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

    /* 延遲初始化回覆框 Quill */
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
        }
        return quillInstances[cid];
    }

    /* 初始化主留言 Quill */
    if ($('#quill-main-f' + FORM_ID).length) {
        quillMain = new Quill('#quill-main-f' + FORM_ID, {
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
    }

    /* 展開主留言框 */
    $root.on('click', '#top-reply-trigger-f' + FORM_ID, function () {
        $('#top-reply-collapsed-f' + FORM_ID).hide();
        $('#comment-form-f' + FORM_ID).show();
        setTimeout(() => quillMain && quillMain.focus(), 50);
    });

    $root.on('click', '#btn-cancel-top-f' + FORM_ID, function () {
        $('#comment-form-f' + FORM_ID).hide();
        $('#top-reply-collapsed-f' + FORM_ID).show();
        if (quillMain) quillMain.setContents([]);
    });

    /* 送出頂層留言 */
    $root.on('submit', '#comment-form-f' + FORM_ID, function (e) {
        e.preventDefault();
        if (!quillMain || quillMain.getText().trim() === '') return;
        const content = quillMain.root.innerHTML;

        $.post((window.REL_BASE||'') + 'api/comment.php', { form_id: FORM_ID, content: content }, function (res) {
            if (!res.success) return;
            $('#no-comment-hint-f' + FORM_ID).remove();
            $('#comments-list-f' + FORM_ID).append(makeCommentHTML(res.comment));
            quillMain.setContents([]);
            $('#comment-form-f' + FORM_ID).hide();
            $('#top-reply-collapsed-f' + FORM_ID).show();
            updateFeedCount(1);
        }, 'json');
    });

    /* 展開/收合子回覆 */
    $root.on('click', '.btn-toggle-replies', function () {
        const cid       = $(this).data('comment-id');
        const container = $(`#replies-${cid}`);
        const isOpen    = container.is(':visible');
        container.slideToggle(200);
        const $btn  = $root.find(`.btn-toggle-replies[data-comment-id="${cid}"]`).filter('button');
        const count = container.find('.comment-node').length;
        $btn.find('small').html(
            isOpen
            ? `<i class="bi bi-arrow-return-right"></i> ${count} 則回覆`
            : `<i class="bi bi-arrow-return-right"></i> 收起回覆`
        );
    });

    /* 顯示/隱藏回覆框 */
    $root.on('click', '.btn-reply', function () {
        const cid      = $(this).data('comment-id');
        const username = $(this).data('username');
        const box      = $(`#reply-box-${cid}`);
        $root.find('.reply-box').not(box).hide();
        const wasVisible = box.is(':visible');
        box.toggle(!wasVisible);
        if (!wasVisible) {
            getOrInitReplyQuill(cid, username);
            setTimeout(() => quillInstances[cid] && quillInstances[cid].focus(), 80);
        }
    });

    $root.on('click', '.btn-cancel-reply', function () {
        const cid = $(this).data('comment-id');
        $(`#reply-box-${cid}`).hide();
        if (quillInstances[cid]) quillInstances[cid].setContents([]);
    });

    /* 送出子回覆 */
    $root.on('click', '.btn-submit-reply', function () {
        const parentId = $(this).data('parent-id');
        const quill    = quillInstances[parentId];
        if (!quill || quill.getText().trim() === '') return;
        const content  = quill.root.innerHTML;

        $.post((window.REL_BASE||'') + 'api/comment.php',
            { form_id: FORM_ID, content: content, parent_id: parentId },
            function (res) {
                if (!res.success) return;

                let container = $(`#replies-${parentId}`);
                if (!container.length) {
                    $(`#comment-${parentId} > .d-flex > .flex-grow-1`).append(
                        `<div class="replies-container mt-2" id="replies-${parentId}"></div>`
                    );
                    container = $(`#replies-${parentId}`);
                    $root.find(`#comment-${parentId} .btn-reply`).after(
                        `<button class="btn btn-sm p-0 text-muted btn-toggle-replies" data-comment-id="${parentId}">
                            <small><i class="bi bi-arrow-return-right"></i> 1 則回覆</small>
                        </button>`
                    );
                }

                container.append(makeCommentHTML(res.comment)).show();
                quill.setContents([]);
                $(`#reply-box-${parentId}`).hide();
                updateFeedCount(1);
                $root.find(`.btn-toggle-replies[data-comment-id="${parentId}"]`).filter('button')
                     .find('small').html(`<i class="bi bi-arrow-return-right"></i> 收起回覆`);
            }, 'json');
    });

    /* 刪除留言 */
    $root.on('click', '.btn-delete-comment', function () {
        const commentId = $(this).data('comment-id');
        window.cuteConfirm({ icon: '💬', msg: '確定要刪除這則留言嗎？', sub: '此操作無法復原。' }, function (ok) {
            if (!ok) return;
            $.post((window.REL_BASE||'') + 'api/comment_delete.php', { comment_id: commentId }, function (res) {
                if (!res.success) { alert(res.message); return; }
                const $node   = $(`#comment-${commentId}`);
                const deleted = $node.find('.comment-node').length + 1;
                $node.fadeOut(300, function () { $(this).remove(); });
                updateFeedCount(-deleted);
            }, 'json');
        });
    });

    /* 編輯留言 */
    $root.on('click', '.btn-edit-comment', function () {
        const cid = $(this).data('comment-id');
        const box = $(`#edit-box-${cid}`);
        $root.find('.reply-box').hide();
        $root.find('.edit-box').not(box).hide();
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
        }
        const currentHTML = $(`#comment-${cid} .ql-content`).first().html();
        quillEditInstances[cid].clipboard.dangerouslyPasteHTML(currentHTML);
        setTimeout(() => quillEditInstances[cid].focus(), 80);
    });

    $root.on('click', '.btn-cancel-edit', function () {
        $(`#edit-box-${$(this).data('comment-id')}`).hide();
    });

    $root.on('click', '.btn-submit-edit', function () {
        const cid   = $(this).data('comment-id');
        const quill = quillEditInstances[cid];
        if (!quill || quill.getText().trim() === '') return;
        const content = quill.root.innerHTML;
        $.post((window.REL_BASE||'') + 'api/comment_edit.php', { comment_id: cid, content: content }, function (res) {
            if (!res.success) { alert(res.message); return; }
            $(`#comment-${cid} .ql-content`).first().html(res.content);
            $(`#edit-box-${cid}`).hide();
        }, 'json').fail(function () {
            alert('網路錯誤，請稍後再試');
        });
    });

    /* 留言按讚 */
    $root.on('click', '.btn-comment-like', function () {
        const btn       = $(this);
        const commentId = btn.data('comment-id');
        $.post((window.REL_BASE||'') + 'api/comment_like.php', { comment_id: commentId }, function (res) {
            if (!res.success) return;
            btn.find('.comment-like-count').text(res.count || '');
            btn.toggleClass('text-muted', !res.liked).toggleClass('text-body-emphasis', res.liked);
            btn.find('i').toggleClass('bi-heart', !res.liked).toggleClass('bi-heart-fill', res.liked);
        }, 'json');
    });

    /* 同步更新首頁 card 上的留言計數 */
    function updateFeedCount(delta) {
        const $span = $('.feed-comment-count-' + FORM_ID);
        $span.text(Math.max(0, parseInt($span.text() || 0) + delta));
    }

    /* 新留言 HTML 模板 */
    function makeCommentHTML(c) {
        const s32 = 'width:32px;height:32px;border-radius:50%;flex-shrink:0;';
        let avatarHtml;
        if (c.avatar) {
            avatarHtml = `<img src="${c.avatar}" class="site-avatar" style="${s32}object-fit:cover;" alt="">`;
        } else {
            const initial = (c.username.charAt(0) || '?').toUpperCase();
            avatarHtml = `<span class="site-avatar" style="${s32}background:var(--bs-primary,#0d6efd);color:var(--bs-primary-text,#fff);display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;">${initial}</span>`;
        }
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
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown">
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
                            <i class="bi bi-heart"></i>
                            <span class="comment-like-count small"></span>
                        </button>
                        <button class="btn btn-sm px-2 text-muted btn-reply"
                                data-comment-id="${c.id}" data-username="${c.username}">
                            <small>回覆</small>
                        </button>
                    </div>
                    <div class="reply-box mt-2" id="reply-box-${c.id}" style="display:none;">
                        <div id="quill-reply-${c.id}" class="quill-comment-editor mb-1"></div>
                        <div class="d-flex justify-content-end gap-1 mt-1">
                            <button class="btn btn-primary btn-sm btn-submit-reply"
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
                            <button class="btn btn-primary btn-sm btn-submit-edit" data-comment-id="${c.id}">
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
})();
</script>
