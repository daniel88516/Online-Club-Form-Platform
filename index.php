<?php
$pageTitle = '首頁';
require_once 'config/session.php';
require_once 'config/db.php';

$now = date('Y-m-d H:i:s');
$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$has_form_clubs = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'form_clubs'")) > 0;
$has_show_on_index = mysqli_num_rows(mysqli_query($conn, "SHOW COLUMNS FROM forms LIKE 'show_on_index'")) > 0;
$index_filter = $has_show_on_index ? "AND f.show_on_index = 1" : "AND f.club_id IS NULL";

$sql = "SELECT f.id, f.user_id, f.title, f.description, f.cover_image, f.start_date, f.end_date, f.allow_multiple, f.created_at, f.show_stats, f.anonymous_responses, f.response_scope, f.club_id,
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
          $index_filter
        GROUP BY f.id
        ORDER BY f.created_at DESC";

$forms = mysqli_query($conn, $sql);
$showNavSearch = true;
require_once 'config/header.php';

function formFillBlockReason($conn, $form, $user_id, $now, $has_form_clubs) {
    if (!empty($form['start_date']) && $form['start_date'] > $now) {
        return '尚未開始，無法填寫';
    }
    if (!empty($form['end_date']) && $form['end_date'] < $now) {
        return '已截止，無法填寫';
    }
    if (!$user_id) {
        return '請先登入才能填寫';
    }
    if (($form['response_scope'] ?? 'all_members') === 'club_members') {
        $club_ids = [];
        if ($has_form_clubs) {
            $fid = (int)$form['id'];
            $res = mysqli_query($conn, "SELECT club_id FROM form_clubs WHERE form_id = $fid");
            while ($row = mysqli_fetch_assoc($res)) $club_ids[] = (int)$row['club_id'];
        }
        if (!empty($form['club_id'])) $club_ids[] = (int)$form['club_id'];
        $club_ids = array_values(array_unique(array_filter($club_ids)));
        if (empty($club_ids)) return '僅限所屬社團成員填答';
        $list = implode(',', array_map('intval', $club_ids));
        $chk = mysqli_query($conn, "SELECT id FROM club_members WHERE user_id = $user_id AND status = 'active' AND club_id IN ($list) LIMIT 1");
        if (!mysqli_fetch_assoc($chk)) return '僅限所屬社團成員填答';
    }
    return '';
}
?>
<style>
@media (min-width: 992px) {
    .index-sort-panel {
        position: absolute;
        right: calc(100% + 64px);
        top: 0;
        white-space: nowrap;
    }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-7" style="position:relative;">
        <div class="index-sort-panel">

        <!-- 標題 = 排序下拉選單 -->
        <div class="mb-3">
            <div class="dropdown">
                <button class="btn ps-2 fw-bold dropdown-toggle d-inline-flex align-items-center gap-1 lh-1" style="font-size:1.15rem;background:transparent;border:none;color:inherit;"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-house-door"></i> <span id="sort-label-text">最新動態</span>
                </button>
                <ul class="dropdown-menu">
                    <li><button class="dropdown-item sort-opt active" data-mode="date_desc"><i class="bi bi-clock-history me-2 text-primary"></i>最新動態</button></li>
                    <li><button class="dropdown-item sort-opt" data-mode="date_asc"><i class="bi bi-clock me-2 text-secondary"></i>最舊動態</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item sort-opt" data-mode="resp_desc"><i class="bi bi-people me-2 text-info"></i>回應最多</button></li>
                    <li><button class="dropdown-item sort-opt" data-mode="like_desc"><i class="bi bi-heart me-2 text-danger"></i>按讚最多</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item sort-opt" data-mode="title_asc"><i class="bi bi-sort-alpha-down me-2 text-success"></i>標題 A→Z</button></li>
                </ul>
            </div>
        </div>
        </div>

        <?php if (mysqli_num_rows($forms) === 0): ?>
            <div class="text-center py-5 text-muted" id="feed-empty-state">
                <i class="bi bi-inbox" style="font-size:3rem;"></i>
                <p class="mt-3">目前沒有公開表單</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="<?= REL_BASE ?>login.php" class="btn btn-glow-primary">登入後新增表單</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div id="feed-no-results" class="text-center py-4 text-muted d-none">
            <i class="bi bi-search" style="font-size:2rem;"></i>
            <p class="mt-2">找不到符合的表單</p>
        </div>

        <div id="feed-container">
        <?php while ($form = mysqli_fetch_assoc($forms)): ?>
        <div class="card mb-4 feed-card-item" id="form-card-<?= $form['id'] ?>"
             data-date="<?= strtotime($form['created_at']) ?>"
             data-resp="<?= $form['response_count'] ?>"
             data-like="<?= $form['like_count'] ?>"
             data-title="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>"
             data-desc="<?= htmlspecialchars(mb_strtolower(strip_tags($form['description'] ?? ''))) ?>">
            <!-- 作者資訊 header -->
            <div class="card-header d-flex align-items-center gap-2 py-2">
                <?= renderAvatarDropdown($form['author'], $form['author_avatar'], $form['user_id'], 40, $user_id) ?>
                <div>
                    <a href="<?= REL_BASE ?>profile.php?id=<?= $form['user_id'] ?>" class="fw-bold text-decoration-none link-body-emphasis">
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
                        <?php if (isLoggedIn() || !empty($form['show_stats']) || !empty($form['is_published'])): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($form['user_id'] == $user_id): ?>
                                <li>
                                    <a class="dropdown-item" href="<?= REL_BASE ?>member/edit_form.php?id=<?= $form['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                        <i class="bi bi-pencil text-purple me-2"></i> 編輯表單
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php if (isAdmin() || $form['user_id'] == $user_id || !empty($form['show_stats'])): ?>
                                <?php if ($form['user_id'] == $user_id): ?>
                                <?php if (isLoggedIn()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="<?= REL_BASE ?>member/form_responses.php?id=<?= $form['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                        <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                        <?php if (empty($form['show_stats']) && (isAdmin() || $form['user_id'] == $user_id)): ?>
                                            <i class="bi bi-lock-fill text-secondary ms-1 small" title="統計未公開"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <?php if (isLoggedIn()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                <?php endif; ?>

                                <li>
                                    <button class="dropdown-item btn-preview-form"
                                            data-form-id="<?= $form['id'] ?>"
                                            data-form-title="<?= htmlspecialchars($form['title']) ?>">
                                        <i class="bi bi-eye text-success me-2"></i> 預覽表單
                                    </button>
                                </li>
                                <?php if (isLoggedIn()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>

                                <?php if (isLoggedIn()): ?>
                                <li>
                                    <button class="dropdown-item btn-report-feed-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-flag text-report-orange me-2"></i> 檢舉表單
                                    </button>
                                </li>
                                <?php endif; ?>
                                <?php if (isAdmin() || $form['user_id'] == $user_id): ?>
                                <?php if (isLoggedIn()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                <li>
                                    <button class="dropdown-item text-danger btn-delete-feed-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-trash text-danger me-2"></i> 刪除表單
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
            </div>

            <!-- 標題與內容 -->
            <div class="card-body pb-2">
                <h5 class="fw-bold mb-2 feed-title"><?= htmlspecialchars($form['title']) ?></h5>
                <?php if ($form['description']): ?>
                    <?php $plainDesc = strip_tags($form['description']); ?>
                    <p class="text-muted mb-2 feed-desc"><?= htmlspecialchars(mb_substr($plainDesc, 0, 120)) ?><?= mb_strlen($plainDesc) > 120 ? '...' : '' ?></p>
                <?php endif; ?>
                <?php if ($form['cover_image']): ?>
                    <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="rounded mb-3 w-100"
                         style="max-height:250px;object-fit:cover;">
                <?php endif; ?>

                <!-- 填答人數 -->
                <div class="text-muted small mb-3">
                    <i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答
                </div>

                <!-- 填寫 / 預覽按鈕 -->
                <?php $fillBlockReason = formFillBlockReason($conn, $form, $user_id, $now, $has_form_clubs); ?>
                <div class="d-grid mb-3">
                    <?php if ($fillBlockReason): ?>
                    <button class="btn btn-outline-secondary btn-sm w-100 form-fill-disabled" disabled>
                        <i class="bi bi-lock-fill me-1"></i> <?= htmlspecialchars($fillBlockReason) ?>
                    </button>
                    <?php else: ?>
                    <a href="<?= REL_BASE ?>form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary w-100">
                        <i class="bi bi-pencil"></i> 填寫表單
                    </a>
                    <?php endif; ?>
                </div>
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
        </div><!-- /#feed-container -->

        <?php endif; ?>
    </div>
</div>

<!-- 預覽表單 Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold"><i class="bi bi-eye text-success me-1"></i> <span id="preview-form-title"></span></h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="preview-body">
                <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

// ── Navbar 搜尋框 顯示/隱藏 ──
$('#nav-search-toggle').on('click', function () {
    $('#nav-search-box').removeClass('d-none');
    $('#nav-search-input').focus();
});
$('#nav-search-close').on('click', function () {
    $('#nav-search-input').val('').trigger('input');
    $('#nav-search-box').addClass('d-none');
});

// ── 首頁搜尋 ──
function escapeReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function highlightText($el, q) {
    const orig = $el.data('orig-text') ?? $el.text();
    $el.data('orig-text', orig);
    if (!q) { $el.html(escapeHtml(orig)); return; }
    const re = new RegExp('(' + escapeReg(q) + ')', 'gi');
    $el.html(escapeHtml(orig).replace(re, '<mark class="search-hl">$1</mark>'));
}
function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
$('#nav-search-input').on('input', function () {
    const q = $(this).val().toLowerCase().trim();
    let visible = 0;
    $('.feed-card-item').each(function () {
        const title = String($(this).data('title') || '');
        const desc  = String($(this).data('desc')  || '');
        const match = !q || title.includes(q) || desc.includes(q);
        $(this).toggle(match);
        if (match) {
            highlightText($(this).find('.feed-title'), q);
            highlightText($(this).find('.feed-desc'), q);
            visible++;
        }
    });
    if (!q) {
        $('.feed-title, .feed-desc').each(function () {
            const orig = $(this).data('orig-text');
            if (orig !== undefined) $(this).html(escapeHtml(orig));
        });
    }
    $('#feed-no-results').toggleClass('d-none', visible > 0 || !q);
});

// ── 首頁排序 ──
function feedSort(mode) {
    const $container = $('#feed-container');
    const $items = $container.children('.feed-card-item').toArray();
    $items.sort(function (a, b) {
        const $a = $(a), $b = $(b);
        if (mode === 'date_desc') return $b.data('date') - $a.data('date');
        if (mode === 'date_asc')  return $a.data('date') - $b.data('date');
        if (mode === 'resp_desc') return $b.data('resp') - $a.data('resp');
        if (mode === 'like_desc') return $b.data('like') - $a.data('like');
        if (mode === 'title_asc') return $a.data('title').localeCompare($b.data('title'), 'zh-Hant');
        return 0;
    });
    $container.append($items);
}
$(document).on('click', '.sort-opt', function () {
    const mode = $(this).data('mode');
    const label = $(this).text().trim();
    $('.sort-opt').removeClass('active');
    $(this).addClass('active');
    $('#sort-label-text').text(label);
    feedSort(mode);
});

// 預覽表單
const _previewModalEl = document.getElementById('previewModal');
$(document).on('click', '.btn-preview-form', function () {
    $('#preview-form-title').text($(this).data('form-title'));
    $('#preview-body').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
    bootstrap.Modal.getOrCreateInstance(_previewModalEl).show();
    $.get('<?= REL_BASE ?>api/form_preview_content.php', { id: $(this).data('form-id') }, function (html) {
        $('#preview-body').html(html);
    }).fail(function () {
        $('#preview-body').html('<div class="alert alert-danger">載入失敗，請稍後再試</div>');
    });
});
_previewModalEl.addEventListener('hidden.bs.modal', function () {
    $('#preview-body').html('');
});

// 按讚
$(document).on('click', '.btn-like', function () {
    const btn = $(this);
    const formId = btn.data('id');
    $.post('<?= REL_BASE ?>api/like.php', { form_id: formId }, function (res) {
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
    $.post('<?= REL_BASE ?>api/bookmark.php', { form_id: formId }, function (res) {
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

    $.get('<?= REL_BASE ?>api/comments_panel.php', { form_id: formId }, function (html) {
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
        $.post('<?= REL_BASE ?>api/delete_form.php', { form_id: formId }, function (res) {
            if (res.success) {
                $card.fadeOut(300, function () { $(this).remove(); });
            } else {
                alert(res.message || '刪除失敗');
            }
        }, 'json');
    });
});

// 首頁表單檢舉
$(document).on('click', '.btn-report-feed-form', function () {
    window.cuteReport('form', $(this).data('form-id'), '檢舉表單');
});

// 首頁留言檢舉
$(document).on('click', '.btn-report-comment', function () {
    window.cuteReport('comment', $(this).data('comment-id'), '檢舉留言');
});

}); // end $(document).ready
</script>

<?php require_once 'config/footer.php'; ?>
