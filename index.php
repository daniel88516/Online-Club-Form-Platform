<?php
$pageTitle = '首頁';
require_once 'config/session.php';
require_once 'config/db.php';

$now = date('Y-m-d H:i:s');
$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;

$sql = "SELECT f.id, f.user_id, f.title, f.description, f.cover_image, f.start_date, f.end_date, f.allow_multiple, f.created_at, f.show_stats, f.anonymous_responses,
               u.username AS author, u.avatar AS author_avatar,
               g.name AS group_name,
               COUNT(DISTINCT fr.id) AS response_count,
               COUNT(DISTINCT fl.id) AS like_count,
               COUNT(DISTINCT fb.id) AS bookmark_count,
               COUNT(DISTINCT fc.id) AS comment_count,
               MAX(CASE WHEN fl.user_id = $user_id THEN 1 ELSE 0 END) AS user_liked,
               MAX(CASE WHEN fb.user_id = $user_id THEN 1 ELSE 0 END) AS user_bookmarked
        FROM forms f
        JOIN users u ON f.user_id = u.id
        LEFT JOIN `groups` g ON f.target_group = g.id
        LEFT JOIN form_responses fr ON f.id = fr.form_id
        LEFT JOIN form_likes fl ON f.id = fl.form_id
        LEFT JOIN form_bookmarks fb ON f.id = fb.form_id
        LEFT JOIN form_comments fc ON f.id = fc.form_id
        WHERE f.is_published = 1
          AND (f.target_group IS NULL)
        GROUP BY f.id
        ORDER BY f.created_at DESC";

$forms = mysqli_query($conn, $sql);
require_once 'config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <!-- 頂部標題列 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0"><i class="bi bi-house-door"></i> 最新動態</h5>
        </div>

        <?php if (mysqli_num_rows($forms) === 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:3rem;"></i>
                <p class="mt-3">目前沒有公開表單</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="/login.php" class="btn btn-outline-primary">登入後新增表單</a>
                <?php endif; ?>
            </div>
        <?php else: ?>

        <?php while ($form = mysqli_fetch_assoc($forms)): ?>
        <div class="card mb-4" id="form-card-<?= $form['id'] ?>">
            <!-- 作者資訊 header -->
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <?= renderAvatarDropdown($form['author'], $form['author_avatar'], $form['user_id'], 40, $user_id) ?>
                <div>
                    <a href="/profile.php?id=<?= $form['user_id'] ?>" class="fw-bold text-decoration-none link-body-emphasis">
                        <?= htmlspecialchars($form['author']) ?>
                    </a>
                    <div class="text-muted small">
                        <?php
                        $diff = time() - strtotime($form['created_at']);
                        if ($diff < 60) echo '剛剛';
                        elseif ($diff < 3600) echo floor($diff/60) . ' 分鐘前';
                        elseif ($diff < 86400) echo floor($diff/3600) . ' 小時前';
                        else echo date('Y/m/d', strtotime($form['created_at']));
                        ?>
                    </div>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                        <?php if ($form['end_date']): ?>
                        <?php $expired = $form['end_date'] < $now; ?>
                        <span class="deadline-tag <?= $expired ? 'deadline-expired' : '' ?>">
                            <i class="bi bi-clock"></i>
                            <?= $expired ? '已截止' : ('截止 ' . date('m/d', strtotime($form['end_date']))) ?>
                        </span>
                    <?php endif; ?>
                        <?php if (isLoggedIn()): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($form['user_id'] == $user_id): ?>
                                <li>
                                    <a class="dropdown-item" href="/member/edit_form.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-pencil me-2"></i> 編輯表單
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if (isAdmin() || $form['user_id'] == $user_id || !empty($form['show_stats'])): ?>
                                <li>
                                    <a class="dropdown-item" href="/member/form_responses.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                        <?php if (empty($form['show_stats']) && (isAdmin() || $form['user_id'] == $user_id)): ?>
                                            <i class="bi bi-lock-fill text-secondary ms-1 small" title="統計未公開"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if (isAdmin() || $form['user_id'] == $user_id): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger btn-delete-feed-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-trash me-2"></i> 刪除表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>

                                <li>
                                    <button class="dropdown-item btn-report-feed-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-flag me-2"></i> 檢舉表單
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
            </div>

            <!-- 標題與內容 -->
            <div class="card-body pb-2">
                <h5 class="fw-bold mb-2"><?= htmlspecialchars($form['title']) ?></h5>
                <?php if ($form['description']): ?>
                    <?php $plainDesc = strip_tags($form['description']); ?>
                    <p class="text-muted mb-2"><?= htmlspecialchars(mb_substr($plainDesc, 0, 120)) ?><?= mb_strlen($plainDesc) > 120 ? '...' : '' ?></p>
                <?php endif; ?>
                <?php if ($form['cover_image']): ?>
                    <img src="<?= htmlspecialchars($form['cover_image']) ?>" class="rounded mb-3 w-100"
                         style="max-height:250px;object-fit:cover;">
                <?php endif; ?>

                <!-- 填答人數 -->
                <div class="text-muted small mb-3">
                    <i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答
                </div>

                <!-- 填寫按鈕 -->
                <?php $expired = $form['end_date'] && $form['end_date'] < $now; ?>
                <?php if ($expired): ?>
                <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled>
                    <i class="bi bi-lock"></i> 已截止，無法填寫
                </button>
                <?php else: ?>
                <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn-glow-dark w-100 mb-3">
                    <i class="bi bi-pencil"></i> 填寫表單
                </a>
                <?php endif; ?>
            </div>

            <!-- 按讚 / 留言 / 收藏 -->
            <div class="card-footer bg-transparent border-top d-flex justify-content-around py-2">
                <!-- 按讚 -->
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-sm btn-like <?= $form['user_liked'] ? 'liked' : 'text-muted' ?>"
                        data-id="<?= $form['id'] ?>">
                    <i class="bi <?= $form['user_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    <span class="like-count"><?= $form['like_count'] ?></span>
                </button>
                <?php else: ?>
                <span class="btn btn-sm text-muted" title="登入後才能按讚">
                    <i class="bi bi-heart"></i> <?= $form['like_count'] ?>
                </span>
                <?php endif; ?>

                <!-- 留言 -->
                <button class="btn btn-sm text-muted btn-toggle-feed-comments" data-id="<?= $form['id'] ?>">
                    <i class="bi bi-chat"></i>
                    <span class="feed-comment-count-<?= $form['id'] ?>"><?= $form['comment_count'] ?></span>
                </button>

                <!-- 收藏 -->
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-sm btn-bookmark <?= $form['user_bookmarked'] ? 'bookmarked' : 'text-muted' ?>"
                        data-id="<?= $form['id'] ?>">
                    <i class="bi <?= $form['user_bookmarked'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                    <span class="bookmark-count"><?= $form['bookmark_count'] ?></span>
                </button>
                <?php else: ?>
                <span class="btn btn-sm text-muted" title="登入後才能收藏">
                    <i class="bi bi-bookmark"></i> <?= $form['bookmark_count'] ?>
                </span>
                <?php endif; ?>
            </div>
            <!-- 留言展開面板（lazy load） -->
            <div class="feed-comments-panel px-3 pb-2"
                 id="feed-comments-<?= $form['id'] ?>" style="display:none;"></div>
        </div>
        <?php endwhile; ?>

        <?php endif; ?>
    </div>
</div>

<!-- 檢舉 Modal（首頁共用） -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="reportModalLabel"><i class="bi bi-flag"></i> 檢舉</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-3">
                <p class="text-muted small mb-3">請選擇檢舉原因：</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm text-start btn-report-reason" data-reason="騷擾內容">
                        <i class="bi bi-person-x me-1"></i> 騷擾內容
                    </button>
                    <button class="btn btn-outline-secondary btn-sm text-start btn-report-reason" data-reason="詐騙">
                        <i class="bi bi-exclamation-triangle me-1"></i> 詐騙
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 按讚
$(document).on('click', '.btn-like', function () {
    const btn = $(this);
    const formId = btn.data('id');
    $.post('/api/like.php', { form_id: formId }, function (res) {
        if (res.success) {
            btn.find('.like-count').text(res.count);
            if (res.liked) {
                btn.removeClass('text-muted').addClass('liked');
                btn.find('i').removeClass('bi-heart').addClass('bi-heart-fill');
            } else {
                btn.removeClass('liked').addClass('text-muted');
                btn.find('i').removeClass('bi-heart-fill').addClass('bi-heart');
            }
        }
    }, 'json');
});

// 收藏
$(document).on('click', '.btn-bookmark', function () {
    const btn = $(this);
    const formId = btn.data('id');
    $.post('/api/bookmark.php', { form_id: formId }, function (res) {
        if (res.success) {
            btn.find('.bookmark-count').text(res.count);
            if (res.bookmarked) {
                btn.removeClass('text-muted').addClass('bookmarked');
                btn.find('i').removeClass('bi-bookmark').addClass('bi-bookmark-fill');
            } else {
                btn.removeClass('bookmarked').addClass('text-muted');
                btn.find('i').removeClass('bi-bookmark-fill').addClass('bi-bookmark');
            }
        }
    }, 'json');
});

// 首頁留言面板 toggle + lazy load
$(document).on('click', '.btn-toggle-feed-comments', function () {
    const formId = $(this).data('id');
    const $panel = $('#feed-comments-' + formId);
    const $btn   = $(this);

    if ($panel.is(':visible')) {
        $panel.slideUp(200);
        $btn.find('i').removeClass('bi-chat-fill').addClass('bi-chat');
        return;
    }

    $btn.find('i').removeClass('bi-chat').addClass('bi-chat-fill');

    if ($panel.data('loaded')) {
        $panel.slideDown(200);
        return;
    }

    // 首次載入：顯示 loading
    $panel.html('<div class="text-center py-3 border-top"><span class="spinner-border spinner-border-sm text-muted"></span></div>');
    $panel.slideDown(200);

    $.get('/api/comments_panel.php', { form_id: formId }, function (html) {
        $panel.html(html);
        $panel.data('loaded', true);
    });
});

// 首頁刪除表單
$(document).on('click', '.btn-delete-feed-form', function () {
    const formId = $(this).data('form-id');
    const $card  = $(this).closest('.card.mb-4');
    window.cuteConfirm({ icon: '📋', msg: '確定要刪除這個表單嗎？', sub: '此操作無法復原。' }, function (ok) {
        if (!ok) return;
        $.post('/api/delete_form.php', { form_id: formId }, function (res) {
            if (res.success) {
                $card.fadeOut(300, function () { $(this).remove(); });
            } else {
                alert(res.message || '刪除失敗');
            }
        }, 'json');
    });
});

// 首頁表單檢舉：開啟 Modal
$(document).on('click', '.btn-report-feed-form', function () {
    const formId = $(this).data('form-id');
    $('#reportModal').data('report-type', 'form').data('report-id', formId);
    $('#reportModalLabel').html('<i class="bi bi-flag"></i> 檢舉表單');
    new bootstrap.Modal(document.getElementById('reportModal')).show();
});

// 首頁留言檢舉：開啟 Modal（由 comments_panel 觸發）
$(document).on('click', '.btn-report-comment', function () {
    const commentId = $(this).data('comment-id');
    $('#reportModal').data('report-type', 'comment').data('report-id', commentId);
    $('#reportModalLabel').html('<i class="bi bi-flag"></i> 檢舉留言');
    new bootstrap.Modal(document.getElementById('reportModal')).show();
});

// 送出檢舉（共用）
$(document).on('click', '.btn-report-reason', function () {
    const reason = $(this).data('reason');
    const type   = $('#reportModal').data('report-type');
    const id     = $('#reportModal').data('report-id');
    const modal  = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
    $.post('/api/report.php', { type: type, target_id: id, reason: reason }, function (res) {
        if (modal) modal.hide();
        alert(res.message);
    }, 'json').fail(function () {
        if (modal) modal.hide();
        alert('網路錯誤，請稍後再試');
    });
});

</script>

<?php if (isMember()): ?>
<!-- 新增表單 FAB -->
<a href="/member/create_form.php"
   class="fab-btn"
   title="新增表單">
    <i class="bi bi-plus-lg"></i>
</a>

<?php endif; ?>

<?php require_once 'config/footer.php'; ?>
