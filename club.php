<?php
define('FAB_CUSTOM', true);
require_once 'config/session.php';
require_once 'config/db.php';

$club_id = intval($_GET['id'] ?? 0);
if (!$club_id) { header('Location: ' . APP_BASE . '/clubs.php'); exit(); }

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;

// 取社團資訊
$stmt = mysqli_prepare($conn, "
    SELECT c.*, u.username AS owner_name, u.avatar AS owner_avatar,
           (SELECT COUNT(*) FROM club_members cm2 WHERE cm2.club_id = c.id AND cm2.status = 'active') AS member_count,
           (SELECT role   FROM club_members cm3 WHERE cm3.club_id = c.id AND cm3.user_id = ?) AS my_role,
           (SELECT status FROM club_members cm4 WHERE cm4.club_id = c.id AND cm4.user_id = ?) AS my_status
    FROM clubs c
    JOIN users u ON c.owner_id = u.id
    WHERE c.id = ?
");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $user_id, $club_id);
mysqli_stmt_execute($stmt);
$club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$club) { header('Location: ' . APP_BASE . '/clubs.php'); exit(); }

// 私人社團：非成員無法進入
if (!$club['is_public'] && ($club['my_role'] === null || $club['my_status'] === 'pending')) {
    header('Location: ' . APP_BASE . '/clubs.php?err=private'); exit();
}

$pageTitle = $club['name'];
$showNavSearch = true;

// 取社團表單
$now = date('Y-m-d H:i:s');
$has_form_clubs = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'form_clubs'")) > 0;
$form_clubs_join = $has_form_clubs ? "LEFT JOIN form_clubs fcl ON fcl.form_id = f.id" : "";
$club_filter = $has_form_clubs ? "(fcl.club_id = $club_id OR f.club_id = $club_id)" : "f.club_id = $club_id";
$sql = "SELECT f.id, f.user_id, f.title, f.description, f.cover_image, f.start_date, f.end_date, f.allow_multiple, f.created_at, f.show_stats, f.anonymous_responses, f.response_scope, f.club_id,
               u.username AS author, u.avatar AS author_avatar,
               COUNT(DISTINCT fr.id) AS response_count,
               COUNT(DISTINCT fl.id) AS like_count,
               COUNT(DISTINCT fb.id) AS bookmark_count,
               COUNT(DISTINCT fc.id) AS comment_count,
               MAX(CASE WHEN fl.user_id = $user_id THEN 1 ELSE 0 END) AS user_liked,
               MAX(CASE WHEN fb.user_id = $user_id THEN 1 ELSE 0 END) AS user_bookmarked
        FROM forms f
        JOIN users u ON f.user_id = u.id
        LEFT JOIN form_responses fr ON f.id = fr.form_id
        LEFT JOIN form_likes fl ON f.id = fl.form_id
        LEFT JOIN form_bookmarks fb ON f.id = fb.form_id
        LEFT JOIN form_comments fc ON f.id = fc.form_id
        $form_clubs_join
        WHERE f.is_published = 1
          AND $club_filter
        GROUP BY f.id
        ORDER BY f.created_at DESC";
$forms = mysqli_query($conn, $sql);

// 取社團成員（最多顯示 12）
$members = mysqli_query($conn, "
    SELECT u.id, u.username, u.avatar, cm.role
    FROM club_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.club_id = $club_id AND cm.status = 'active'
    ORDER BY FIELD(cm.role,'owner','member'), cm.joined_at ASC
    LIMIT 12
");

// 待審請求（owner 才載入）
$pending_requests = null;
if ($club['my_role'] === 'owner') {
    $pending_requests = mysqli_query($conn, "
        SELECT u.id, u.username, u.avatar
        FROM club_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.club_id = $club_id AND cm.status = 'pending'
        ORDER BY cm.joined_at ASC
    ");
}

function clubFormFillBlockReason($conn, $form, $user_id, $now, $has_form_clubs) {
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

require_once 'config/header.php';
?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css"><?php
?>

<div class="row justify-content-center">
    <div class="col-lg-11">

        <div class="card mb-4" style="position:relative;">
            <a href="<?= REL_BASE ?>clubs.php" class="btn btn-glow-primary btn-sm" style="position:absolute;top:10px;left:calc(100% + 12px);z-index:2;white-space:nowrap;">
                <i class="bi bi-arrow-left"></i> 返回社團
            </a>
            <?php if ($club['my_role'] === 'owner'): ?>
            <div style="position:relative;cursor:pointer;" id="cover-upload-area" title="點擊更換封面">
                <?php if ($club['cover_image']): ?>
                    <img id="club-cover-img" src="<?= htmlspecialchars(assetUrl($club['cover_image'])) ?>" class="card-img-top" style="aspect-ratio:8/3;object-fit:cover;width:100%;">
                <?php else: ?>
                    <div id="club-cover-placeholder" class="d-flex align-items-center justify-content-center" style="aspect-ratio:8/3;background:rgba(var(--bs-primary-rgb),.12);">
                        <i class="bi bi-people" style="font-size:3.5rem;opacity:.3;"></i>
                    </div>
                <?php endif; ?>
                <div style="position:absolute;bottom:8px;right:10px;background:rgba(0,0,0,.55);border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-camera-fill text-white" style="font-size:.95rem;"></i>
                </div>
                <input type="file" id="club-cover-file" accept="image/*" style="display:none;">
            </div>
            <?php else: ?>
                <?php if ($club['cover_image']): ?>
                    <img src="<?= htmlspecialchars(assetUrl($club['cover_image'])) ?>" class="card-img-top" style="aspect-ratio:8/3;object-fit:cover;width:100%;">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center" style="aspect-ratio:8/3;background:rgba(var(--bs-primary-rgb),.12);">
                        <i class="bi bi-people" style="font-size:3.5rem;opacity:.3;"></i>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($club['name']) ?></h4>
                        <?php if ($club['description']): ?>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($club['description']) ?></p>
                        <?php endif; ?>
                        <span class="small text-muted d-flex align-items-center gap-1 flex-wrap">
                            admin：
                            <?= renderAvatarDropdown($club['owner_name'], $club['owner_avatar'] ?? null, $club['owner_id'], 24, $user_id) ?>
                            <span><?= htmlspecialchars($club['owner_name']) ?></span>
                        </span>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <?php if (!$user_id): ?>
                            <!-- 未登入不顯示按鈕 -->
                        <?php elseif (!$club['my_role']): ?>
                            <?php if ($club['is_public']): ?>
                            <button class="btn btn-glow-cyan btn-sm" id="btn-join">
                                <i class="bi bi-person-plus"></i> 加入
                            </button>
                            <?php else: ?>
                            <button class="btn btn-outline-primary btn-sm" id="btn-apply">
                                <i class="bi bi-send"></i> 申請加入
                            </button>
                            <?php endif; ?>
                        <?php elseif ($club['my_status'] === 'pending'): ?>
                            <span class="btn btn-outline-secondary btn-sm disabled"><i class="bi bi-hourglass-split"></i> 申請中</span>
                        <?php elseif ($club['my_status'] === 'invited'): ?>
                            <button class="btn btn-glow-green btn-sm" id="btn-accept-invite"><i class="bi bi-check-lg"></i> 接受邀請</button>
                            <button class="btn btn-glow-red btn-sm" id="btn-decline-invite" title="拒絕邀請"><i class="bi bi-x-lg"></i></button>
                        <?php elseif ($club['my_role'] === 'member'): ?>
                            <button class="btn btn-glow-red btn-sm" id="btn-leave">
                                <i class="bi bi-person-dash"></i> 離開
                            </button>
                        <?php elseif ($club['my_role'] === 'owner'): ?>
                            <button class="btn btn-glow-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inviteModal">
                                <i class="bi bi-person-plus-fill"></i> 邀請
                            </button>
                            <button class="btn btn-sm <?= $club['is_public'] ? 'btn-glow-green' : 'btn-glow-amber' ?>"
                                    id="btn-toggle-public" title="切換公開/私人">
                                <i class="bi <?= $club['is_public'] ? 'bi-unlock' : 'bi-lock' ?>"></i>
                                <?= $club['is_public'] ? '公開' : '私人' ?>
                            </button>
                            <button class="btn btn-glow-green btn-sm btn-owner-options"
                                    data-id="<?= $club['id'] ?>"
                                    data-name="<?= htmlspecialchars($club['name']) ?>">
                                <i class="bi bi-shield-fill"></i> 管理員選項
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 查看成員按鈕 -->
                <?php if (in_array($club['my_role'], ['owner','member'])): ?>
                <div class="mt-3">
                    <button class="btn btn-glow-dark btn-sm" data-bs-toggle="modal" data-bs-target="#memberListModal" style="font-size:0.9rem;padding:0.28rem 0.7rem;">
                        <i class="bi bi-people-fill"></i> 查看成員（<?= $club['member_count'] ?>）
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <!-- 待審請求（owner 才看得到）-->
        <?php if ($club['my_role'] === 'owner' && $pending_requests && mysqli_num_rows($pending_requests) > 0): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header fw-semibold">
                <i class="bi bi-hourglass-split text-warning"></i> 待審請求
                <span class="badge bg-warning text-dark ms-1"><?= mysqli_num_rows($pending_requests) ?></span>
            </div>
            <div class="card-body p-2">
                <?php while ($p = mysqli_fetch_assoc($pending_requests)): ?>
                <div class="d-flex align-items-center gap-2 py-2 px-2 border-bottom" id="pending-row-<?= $p['id'] ?>">
                    <?= renderAvatarDropdown($p['username'], $p['avatar'] ?? null, $p['id'], 36, $user_id) ?>
                    <span class="flex-grow-1 fw-semibold"><?= htmlspecialchars($p['username']) ?></span>
                    <button class="btn btn-glow-green btn-sm btn-approve-member" data-uid="<?= $p['id'] ?>"><i class="bi bi-check-lg"></i> 批准</button>
                    <button class="btn btn-glow-red btn-sm btn-reject-member" data-uid="<?= $p['id'] ?>"><i class="bi bi-x-lg"></i> 拒絕</button>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 社團表單列表 -->
        <div class="mb-3 d-flex align-items-center justify-content-between">
            <div class="dropdown">
                <button class="btn ps-2 fw-bold dropdown-toggle d-inline-flex align-items-center gap-1 lh-1" style="font-size:1.05rem;background:transparent;border:none;color:inherit;"
                        data-bs-toggle="dropdown">
                    <i class="bi bi-journal-text"></i> <span id="sort-label-text">最新動態</span>
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

        <?php if (mysqli_num_rows($forms) === 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox" style="font-size:3rem;"></i>
                <p class="mt-3">此社團還沒有表單</p>
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
                        <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($form['user_id'] == $user_id): ?>
                            <li><a class="dropdown-item" href="<?= REL_BASE ?>member/edit_form.php?id=<?= $form['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>"><i class="bi bi-pencil text-purple me-2"></i> 編輯表單</a></li>
                            <?php endif; ?>
                            <?php if (isAdmin() || $form['user_id'] == $user_id || !empty($form['show_stats'])): ?>
                            <?php if ($form['user_id'] == $user_id): ?>
                            <?php if (isLoggedIn()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= REL_BASE ?>member/form_responses.php?id=<?= $form['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>"><i class="bi bi-bar-chart text-info me-2"></i> 查看統計</a></li>
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
                            <li><button class="dropdown-item btn-report-feed-form" data-form-id="<?= $form['id'] ?>"><i class="bi bi-flag text-report-orange me-2"></i> 檢舉表單</button></li>
                            <?php endif; ?>
                            <?php if (isAdmin() || $form['user_id'] == $user_id): ?>
                            <?php if (isLoggedIn()): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                            <li><button class="dropdown-item text-danger btn-delete-feed-form" data-form-id="<?= $form['id'] ?>"><i class="bi bi-trash text-danger me-2"></i> 刪除表單</button></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body pb-2">
                <h5 class="fw-bold mb-2 feed-title"><?= htmlspecialchars($form['title']) ?></h5>
                <?php if ($form['description']): ?>
                    <?php $plainDesc = strip_tags($form['description']); ?>
                    <p class="text-muted mb-2 feed-desc"><?= htmlspecialchars(mb_substr($plainDesc, 0, 120)) ?><?= mb_strlen($plainDesc) > 120 ? '...' : '' ?></p>
                <?php endif; ?>
                <?php if ($form['cover_image']): ?>
                    <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="rounded mb-3 w-100" style="max-height:250px;object-fit:cover;">
                <?php endif; ?>
                <div class="text-muted small mb-3"><i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答</div>
                <?php $fillBlockReason = clubFormFillBlockReason($conn, $form, $user_id, $now, $has_form_clubs); ?>
                <div class="d-grid mb-3">
                    <?php if ($fillBlockReason): ?>
                    <button class="btn btn-outline-secondary btn-sm w-100 form-fill-disabled" disabled><i class="bi bi-lock-fill me-1"></i> <?= htmlspecialchars($fillBlockReason) ?></button>
                    <?php else: ?>
                    <a href="<?= REL_BASE ?>form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary w-100"><i class="bi bi-pencil"></i> 填寫表單</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top d-flex justify-content-around py-2">
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-sm btn-like <?= $form['user_liked'] ? 'liked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
                    <i class="bi <?= $form['user_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    <span class="like-count"><?= $form['like_count'] ?></span>
                </button>
                <?php else: ?>
                <span class="btn btn-sm text-muted"><i class="bi bi-heart"></i> <?= $form['like_count'] ?></span>
                <?php endif; ?>
                <button class="btn btn-sm text-muted btn-toggle-feed-comments" data-id="<?= $form['id'] ?>">
                    <i class="bi bi-chat"></i>
                    <span class="feed-comment-count-<?= $form['id'] ?>"><?= $form['comment_count'] ?></span>
                </button>
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-sm btn-bookmark <?= $form['user_bookmarked'] ? 'bookmarked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
                    <i class="bi <?= $form['user_bookmarked'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                    <span class="bookmark-count"><?= $form['bookmark_count'] ?></span>
                </button>
                <?php else: ?>
                <span class="btn btn-sm text-muted"><i class="bi bi-bookmark"></i> <?= $form['bookmark_count'] ?></span>
                <?php endif; ?>
            </div>
            <div class="feed-comments-panel px-3 pb-2" id="feed-comments-<?= $form['id'] ?>" style="display:none;"></div>
        </div>
        <?php endwhile; ?>
        </div>
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
const CLUB_ID = <?= $club_id ?>;

$('#btn-join').on('click', function () {
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'join', club_id: CLUB_ID }, function (res) {
        if (res.success) location.reload();
        else alert(res.message);
    });
});
$('#btn-apply').on('click', function () {
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'join', club_id: CLUB_ID }, function (res) {
        if (res.success) location.reload();
        else alert(res.message);
    });
});
$('#btn-accept-invite').on('click', function () {
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'accept_invite', club_id: CLUB_ID }, function (res) {
        if (res.success) location.reload();
    });
});
$('#btn-decline-invite').on('click', function () {
    cuteConfirm({ msg: '確定拒絕邀請？', sub: '', icon: '🚫', okText: '拒絕' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'decline_invite', club_id: CLUB_ID }, function (res) {
            if (res.success) location.reload();
        });
    });
});
$('#btn-leave').on('click', function () {
    cuteConfirm({ msg: '確定要離開此社團？', sub: '離開後可重新申請加入。', icon: '👋', okText: '離開' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'leave', club_id: CLUB_ID }, function (res) {
            if (res.success) location.href = REL_BASE + 'clubs.php';
            else alert(res.message);
        });
    });
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

// 換封面圖
$('#cover-upload-area').on('click', function () { $('#club-cover-file').click(); });
$('#club-cover-file').on('click', function (e) { e.stopPropagation(); });
$('#club-cover-file').on('change', function () {
    const file = this.files[0];
    if (!file) return;
    this.value = '';
    const reader = new FileReader();
    reader.onload = function (e) {
        $('#club-page-cover-crop-img').attr('src', e.target.result);
        $('#clubPageCoverCropModal').modal('show');
    };
    reader.readAsDataURL(file);
});

// 切換公開/私人
$('#btn-toggle-public').on('click', function () {
    const $btn = $(this);
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'toggle_public', club_id: CLUB_ID }, function (res) {
        if (res.success) {
            if (res.is_public) {
                $btn.removeClass('btn-glow-amber').addClass('btn-glow-green')
                    .html('<i class="bi bi-unlock"></i> 公開');
            } else {
                $btn.removeClass('btn-glow-green').addClass('btn-glow-amber')
                    .html('<i class="bi bi-lock"></i> 私人');
            }
        }
    });
});

// 批准 / 拒絕申請
$(document).on('click', '.btn-approve-member', function () {
    const uid = $(this).data('uid');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'approve', club_id: CLUB_ID, target_uid: uid }, function (res) {
        if (res.success) location.reload();
        else alert(res.message);
    });
});
$(document).on('click', '.btn-reject-member', function () {
    const uid = $(this).data('uid');
    cuteConfirm({ msg: '確定拒絕此申請？', sub: '', icon: '🚫', okText: '拒絕' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'reject', club_id: CLUB_ID, target_uid: uid }, function (res) {
            if (res.success) location.reload();
            else alert(res.message);
        });
    });
});

// 邀請搜尋
let _inviteTimer;
$(document).on('input', '#invite-search-input', function () {
    clearTimeout(_inviteTimer);
    const q = $(this).val().trim();
    if (!q) { $('#invite-search-results').empty(); return; }
    _inviteTimer = setTimeout(function () {
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'search_users', q: q, club_id: CLUB_ID }, function (res) {
            const $r = $('#invite-search-results').empty();
            if (!res.users || !res.users.length) {
                $r.html('<p class="text-muted small text-center mt-2">找不到用戶</p>');
                return;
            }
            res.users.forEach(function (u) {
                const av = u.avatar
                    ? `<img src="${assetUrl(u.avatar)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">`
                    : `<span style="width:32px;height:32px;border-radius:50%;background:var(--bs-secondary-bg);display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${u.username.charAt(0).toUpperCase()}</span>`;
                const safeName = $('<span>').text(u.username).html();
                let action;
                if (u.member_status === 'active') {
                    action = '<span class="text-muted small"><i class="bi bi-person-check"></i> 已是成員</span>';
                } else if (u.member_status === 'invited') {
                    action = `<button class="btn btn-glow-amber btn-sm btn-cancel-invite" data-uid="${u.id}"><i class="bi bi-x-lg"></i> 取消邀請</button>`;
                } else if (u.member_status === 'pending') {
                    action = `<button class="btn btn-glow-red btn-sm btn-reject-apply" data-uid="${u.id}"><i class="bi bi-x-lg"></i> 拒絕申請</button>`;
                } else {
                    action = `<button class="btn btn-glow-cyan btn-sm btn-do-invite" data-uid="${u.id}"><i class="bi bi-send"></i> 邀請</button>`;
                }
                $r.append(`
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                        ${av}
                        <span class="flex-grow-1">${safeName}</span>
                        ${action}
                    </div>
                `);
            });
        });
    }, 300);
});

$(document).on('click', '.btn-do-invite', function () {
    const $btn = $(this);
    const uid  = $btn.data('uid');
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass"></i>');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'invite', club_id: CLUB_ID, target_uid: uid }, function (res) {
        if (res.success) {
            $btn.replaceWith(`<button class="btn btn-glow-amber btn-sm btn-cancel-invite" data-uid="${uid}"><i class="bi bi-x-lg"></i> 取消邀請</button>`);
        } else {
            alert(res.message);
            $btn.prop('disabled', false).html('<i class="bi bi-send"></i> 邀請');
        }
    });
});

$(document).on('click', '.btn-cancel-invite', function () {
    const $btn = $(this);
    const uid  = $btn.data('uid');
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass"></i>');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'cancel_invite', club_id: CLUB_ID, target_uid: uid }, function (res) {
        if (res.success) {
            $btn.replaceWith(`<button class="btn btn-glow-cyan btn-sm btn-do-invite" data-uid="${uid}"><i class="bi bi-send"></i> 邀請</button>`);
        } else {
            alert(res.message);
            $btn.prop('disabled', false).html('<i class="bi bi-x-lg"></i> 取消邀請');
        }
    });
});

$(document).on('click', '.btn-reject-apply', function () {
    const $btn = $(this);
    const uid  = $btn.data('uid');
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass"></i>');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'reject', club_id: CLUB_ID, target_uid: uid }, function (res) {
        if (res.success) {
            $btn.replaceWith(`<button class="btn btn-glow-cyan btn-sm btn-do-invite" data-uid="${uid}"><i class="bi bi-send"></i> 邀請</button>`);
        } else {
            alert(res.message);
            $btn.prop('disabled', false).html('<i class="bi bi-x-lg"></i> 拒絕申請');
        }
    });
});

// 查看成員 Modal
const IS_OWNER = <?= $club['my_role'] === 'owner' ? 'true' : 'false' ?>;
const CUR_UID  = <?= $user_id ?>;

function loadMemberList(q) {
    q = q || '';
    $('#member-list-results').html('<p class="text-muted small text-center mt-2">載入中...</p>');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'list_members', club_id: CLUB_ID, q: q }, function (res) {
        const $r = $('#member-list-results').empty();
        if (!res.members || !res.members.length) {
            $r.html('<p class="text-muted small text-center mt-2">找不到成員</p>'); return;
        }
        res.members.forEach(function (m) {
            const av = m.avatar
                ? `<img src="${assetUrl(m.avatar)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
                : `<span style="width:36px;height:36px;border-radius:50%;background:var(--bs-secondary-bg);display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${m.username.charAt(0).toUpperCase()}</span>`;
            const badge = m.role === 'owner' ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">管理員</span>' : '';
            const kickBtn = (IS_OWNER && m.role !== 'owner')
                ? `<button class="btn btn-glow-red btn-sm btn-kick-member" data-uid="${m.id}"><i class="bi bi-person-dash"></i> 踢除</button>`
                : '';
            $r.append(`
                <div class="d-flex align-items-center gap-2 py-2 border-bottom" id="member-row-${m.id}">
                    ${av}
                    <span class="flex-grow-1 fw-semibold">${$('<span>').text(m.username).html()}${badge}</span>
                    ${kickBtn}
                </div>
            `);
        });
    }, 'json');
}

$(document).on('show.bs.modal', '#memberListModal', function () {
    $('#member-list-search').val('');
    loadMemberList('');
});

let _memberSearchTimer;
$(document).on('input', '#member-list-search', function () {
    clearTimeout(_memberSearchTimer);
    const q = $(this).val().trim();
    _memberSearchTimer = setTimeout(function () { loadMemberList(q); }, 300);
});

$(document).on('click', '.btn-kick-member', function () {
    const $btn = $(this);
    const uid  = $btn.data('uid');
    cuteConfirm({ msg: '確定踢除此成員？', sub: '', icon: '🚫', okText: '踢除' }, function (ok) {
        if (!ok) return;
        $btn.prop('disabled', true);
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'kick', club_id: CLUB_ID, target_uid: uid }, function (res) {
            if (res.success) $('#member-row-' + uid).fadeOut(300, function () { $(this).remove(); });
            else { alert(res.message); $btn.prop('disabled', false); }
        }, 'json');
    });
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

// 留言面板
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
    if ($panel.data('loaded')) { $panel.slideDown(200); return; }
    $panel.html('<div class="text-center py-3 border-top"><span class="spinner-border spinner-border-sm text-muted"></span></div>');
    $panel.slideDown(200);
    $.get('<?= REL_BASE ?>api/comments_panel.php', { form_id: formId }, function (html) {
        $panel.html(html);
        $panel.data('loaded', true);
    });
});

// 刪除表單
$(document).on('click', '.btn-delete-feed-form', function () {
    const formId = $(this).data('form-id');
    const $card  = $(this).closest('.card.mb-4');
    window.cuteConfirm({ icon: '📋', msg: '確定要刪除這個表單嗎？', sub: '此操作無法復原。' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/delete_form.php', { form_id: formId }, function (res) {
            if (res.success) $card.fadeOut(300, function () { $(this).remove(); });
            else alert(res.message || '刪除失敗');
        }, 'json');
    });
});

// 檢舉
$(document).on('click', '.btn-report-feed-form', function () {
    window.cuteReport('form', $(this).data('form-id'), '檢舉表單');
});
$(document).on('click', '.btn-report-comment', function () {
    window.cuteReport('comment', $(this).data('comment-id'), '檢舉留言');
});

// ── Navbar 搜尋框 顯示/隱藏 ──
$('#nav-search-toggle').on('click', function () {
    $('#nav-search-box').removeClass('d-none');
    $('#nav-search-input').focus();
});
$('#nav-search-close').on('click', function () {
    $('#nav-search-input').val('').trigger('input');
    $('#nav-search-box').addClass('d-none');
});

// ── 搜尋 ──
function escapeReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function highlightText($el, q) {
    const orig = $el.data('orig-text') ?? $el.text();
    $el.data('orig-text', orig);
    if (!q) { $el.html(escapeHtml(orig)); return; }
    const re = new RegExp('(' + escapeReg(q) + ')', 'gi');
    $el.html(escapeHtml(orig).replace(re, '<mark class="search-hl">$1</mark>'));
}
$(document).on('input', '#nav-search-input', function () {
    const q = $(this).val().toLowerCase().trim();
    let visible = 0;
    $('.feed-card-item').each(function () {
        const title = $(this).data('title') || '';
        const desc  = $(this).data('desc')  || '';
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

// ── 排序 ──
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
    const mode  = $(this).data('mode');
    const label = $(this).text().trim();
    $('.sort-opt').removeClass('active');
    $(this).addClass('active');
    $('#sort-label-text').text(label);
    feedSort(mode);
});
</script>

<?php if ($club['my_role'] === 'owner'): ?>
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill"></i> 邀請成員</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="invite-search-input" class="form-control mb-3" placeholder="搜尋用戶名稱...">
                <div id="invite-search-results"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (in_array($club['my_role'], ['owner','member'])): ?>
<div class="modal fade" id="memberListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-people-fill"></i> 社團成員</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="member-list-search" class="form-control mb-3" placeholder="搜尋成員名稱...">
                <div id="member-list-results" style="max-height:260px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($club['my_role'] === 'owner'): ?>
<style>
.oom-member-item:hover { background: rgba(var(--bs-primary-rgb), .12); border-radius: 6px; }
</style>

<!-- 管理員選項 Modal -->
<div class="modal fade" id="ownerOptionsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-fill"></i> 管理員選項 — <span id="oom-club-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom">
                    <div class="fw-semibold mb-2"><i class="bi bi-person-fill-gear me-1 text-warning"></i> 更換管理員</div>
                    <p class="small text-muted mb-2">從目前社團成員中選擇新的管理員，移交後你將成為普通成員。</p>
                    <button type="button" class="btn btn-outline-secondary w-100 d-flex align-items-center gap-2 text-start mb-2" id="oom-member-trigger" style="min-height:42px;">
                        <span id="oom-selected-avatar"></span>
                        <span id="oom-selected-name" class="flex-grow-1 text-truncate">— 請選擇成員 —</span>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </button>
                    <input type="hidden" id="oom-new-owner" value="">
                    <button class="btn btn-glow-primary w-100" id="btn-transfer-owner">
                        <i class="bi bi-arrow-left-right"></i> 確認移交管理員
                    </button>
                </div>
                <div class="p-3 border-bottom">
                    <div class="fw-semibold mb-2"><i class="bi bi-trash-fill me-1 text-danger"></i> 刪除社團</div>
                    <p class="small text-muted mb-2">此操作<strong>無法復原</strong>，社團及所有成員資料將永久刪除。</p>
                    <button class="btn btn-glow-red w-100" id="btn-delete-club">
                        <i class="bi bi-trash"></i> 刪除這個社團
                    </button>
                </div>
                <div class="p-3">
                    <div class="fw-semibold mb-2"><i class="bi bi-box-arrow-right me-1 text-danger"></i> 離開社團</div>
                    <p class="small text-muted mb-2">離開後將自動由最早加入的成員遞補為管理員。若社團內無其他成員，社團將被刪除。</p>
                    <button class="btn btn-glow-red w-100" id="btn-owner-leave">
                        <i class="bi bi-person-dash"></i> 離開社團
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 成員選擇 Modal -->
<div class="modal fade" id="oomMemberPickerModal" tabindex="-1" style="z-index:1065;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-people me-1"></i> 選擇新管理員</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow-y:auto;max-height:320px;">
                <div id="oom-member-list" class="p-2"></div>
            </div>
        </div>
    </div>
</div>

<script>
let _oomClubId = <?= $club['id'] ?>;

function oomAvatarHtml(avatar, size) {
    size = size || 28;
    if (avatar) return `<img src="${assetUrl(avatar)}" class="rounded-circle" style="width:${size}px;height:${size}px;object-fit:cover;">`;
    return `<span class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:${size}px;height:${size}px;font-size:${Math.round(size*0.45)}px;"><i class="bi bi-person-fill text-white"></i></span>`;
}

$(document).on('click', '.btn-owner-options', function () {
    $('#oom-club-name').text($(this).data('name'));
    $('#oom-new-owner').val('');
    $('#oom-selected-name').text('— 請選擇成員 —');
    $('#oom-selected-avatar').html('');
    $('#oom-member-list').html('<div class="p-2 text-muted small">載入中...</div>');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'get_members', club_id: _oomClubId }, function (res) {
        if (!res.success || res.members.length === 0) {
            $('#oom-member-list').html('<div class="p-2 text-muted small">無其他成員可選</div>'); return;
        }
        $('#oom-member-list').html(res.members.map(m => `
            <div class="oom-member-item d-flex align-items-center gap-2 px-2 py-2 rounded" style="cursor:pointer;"
                 data-id="${m.id}" data-name="${m.username}" data-avatar="${m.avatar||''}">
                ${oomAvatarHtml(m.avatar, 32)}
                <span>${m.username}</span>
            </div>
        `).join(''));
    }, 'json');
    new bootstrap.Modal('#ownerOptionsModal').show();
});

$('#oom-member-trigger').on('click', function () {
    new bootstrap.Modal('#oomMemberPickerModal').show();
});

$(document).on('click', '.oom-member-item', function () {
    $('#oom-new-owner').val($(this).data('id'));
    $('#oom-selected-name').text($(this).data('name'));
    $('#oom-selected-avatar').html(oomAvatarHtml($(this).data('avatar'), 26));
    bootstrap.Modal.getInstance('#oomMemberPickerModal').hide();
});

$('#btn-transfer-owner').on('click', function () {
    const newOwner = $('#oom-new-owner').val();
    if (!newOwner) { cuteToast({ type: 'warning', msg: '請先選擇要移交的成員' }); return; }
    const name = $('#oom-selected-name').text();
    cuteConfirm({ msg: `確定將管理員移交給「${name}」？`, sub: '移交後你將成為普通成員，此操作無法自動復原。', icon: '⚡', okText: '確認移交' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'transfer_owner', club_id: _oomClubId, new_owner_id: newOwner }, function (res) {
            if (res.success) location.reload();
            else cuteToast({ type: 'error', msg: res.message });
        }, 'json');
    });
});

$('#btn-delete-club').on('click', function () {
    cuteConfirm({ msg: `確定刪除「<?= htmlspecialchars($club['name'], ENT_QUOTES) ?>」？`, sub: '此操作無法復原，社團及所有成員資料將永久刪除。', icon: '🗑️', okText: '永久刪除' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'delete_club', club_id: _oomClubId }, function (res) {
            if (res.success) location.href = REL_BASE + 'clubs.php';
            else cuteToast({ type: 'error', msg: res.message });
        }, 'json');
    });
});

$('#btn-owner-leave').on('click', function () {
    cuteConfirm({ msg: '確定要離開這個社團？', sub: '你的管理員身份將自動移交給最早加入的成員。若無其他成員，社團將被刪除。', icon: '👋', okText: '離開' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/club_action.php', { action: 'owner_leave', club_id: _oomClubId }, function (res) {
            if (res.success) location.href = REL_BASE + 'clubs.php';
            else cuteToast({ type: 'error', msg: res.message });
        }, 'json');
    });
});
</script>
<?php endif; ?>

<!-- 社團封面裁切 Modal -->
<div class="modal fade" id="clubPageCoverCropModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-crop me-2"></i>裁剪封面圖</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" style="background:#111;">
                <div style="max-height:380px;overflow:hidden;">
                    <img id="club-page-cover-crop-img" src="" style="display:block;max-width:100%;" alt="">
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>拖曳調整範圍，滾輪縮放</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-glow-primary" id="btn-club-page-cover-confirm">
                    <i class="bi bi-check-lg me-1"></i>確認裁剪
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
(function () {
    let cropper = null;
    $(document).on('shown.bs.modal', '#clubPageCoverCropModal', function () {
        cropper = new Cropper(document.getElementById('club-page-cover-crop-img'), {
            aspectRatio: 8 / 3,
            viewMode: 2, dragMode: 'move', autoCropArea: 0.9,
            restore: false, guides: true, center: true, highlight: false,
            cropBoxMovable: true, cropBoxResizable: true, toggleDragModeOnDblclick: false,
        });
    });
    $(document).on('hidden.bs.modal', '#clubPageCoverCropModal', function () {
        if (cropper) { cropper.destroy(); cropper = null; }
    });
    $(document).on('click', '#btn-club-page-cover-confirm', function () {
        if (!cropper) return;
        cropper.getCroppedCanvas({ maxWidth: 2400, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' })
        .toBlob(function (blob) {
            $('#clubPageCoverCropModal').modal('hide');
            const fd = new FormData();
            fd.append('image', blob, 'cover.jpg');
            fd.append('type', 'clubs');
            $.ajax({
                url: '<?= REL_BASE ?>api/upload.php', type: 'POST',
                data: fd, contentType: false, processData: false,
                success: function (res) {
                    if (!res.success) { alert(res.message || '上傳失敗'); return; }
                    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'update_cover', club_id: CLUB_ID, cover_url: res.path }, function (r) {
                        if (!r.success) { alert(r.message); return; }
                        if ($('#club-cover-img').length) {
                            $('#club-cover-img').attr('src', assetUrl(res.path));
                        } else {
                            $('#club-cover-placeholder').replaceWith(
                                `<img id="club-cover-img" src="${assetUrl(res.path)}" class="card-img-top" style="aspect-ratio:8/3;object-fit:cover;width:100%;">`
                            );
                        }
                    });
                }
            });
        }, 'image/jpeg', 0.92);
    });
})();
</script>
<?php if ($club['my_role']): ?>
<a href="<?= REL_BASE ?>member/create_form.php?club_id=<?= $club_id ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
   class="fab-btn"
   title="新增表單">
    <i class="bi bi-plus-lg"></i>
</a>
<?php endif; ?>

<?php require_once 'config/footer.php'; ?>
