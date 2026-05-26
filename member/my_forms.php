<?php
$pageTitle = '我的表單';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$uid = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');
$tab = in_array($_GET['tab'] ?? 'created', ['created', 'bookmarks', 'likes'])
       ? ($_GET['tab'] ?? 'created') : 'created';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggle_id = intval($_POST['toggle_id']);
    $stmt = mysqli_prepare($conn, "UPDATE forms SET is_published = !is_published WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $toggle_id, $uid);
    mysqli_stmt_execute($stmt);
    header('Location: ' . APP_BASE . '/member/my_forms.php?tab=created');
    exit();
}

// ── 建立的表單 ──
$stmtC = mysqli_prepare($conn, "
    SELECT f.id, f.title, f.description, f.cover_image, f.is_published, f.created_at, f.end_date,
           f.show_stats, f.anonymous_responses,
           COUNT(DISTINCT fr.id)  AS response_count,
           COUNT(DISTINCT fl.id)  AS like_count,
           COUNT(DISTINCT fb.id)  AS bookmark_count,
           COUNT(DISTINCT fc.id)  AS comment_count,
           MAX(CASE WHEN fl.user_id = ? THEN 1 ELSE 0 END) AS user_liked,
           MAX(CASE WHEN fb.user_id = ? THEN 1 ELSE 0 END) AS user_bookmarked
    FROM forms f
    LEFT JOIN form_responses fr ON f.id = fr.form_id
    LEFT JOIN form_likes     fl ON f.id = fl.form_id
    LEFT JOIN form_bookmarks fb ON f.id = fb.form_id
    LEFT JOIN form_comments  fc ON f.id = fc.form_id
    WHERE f.user_id = ?
    GROUP BY f.id
    ORDER BY f.created_at DESC");
mysqli_stmt_bind_param($stmtC, 'iii', $uid, $uid, $uid);
mysqli_stmt_execute($stmtC);
$created_forms = mysqli_stmt_get_result($stmtC);

// ── 收藏的表單 ──
$stmtB = mysqli_prepare($conn, "
    SELECT f.id, f.user_id, f.title, f.description, f.cover_image, f.end_date, f.is_published, f.show_stats,
           f.created_at, fb_me.created_at AS bookmarked_at,
           u.username AS author, u.avatar AS author_avatar,
           COUNT(DISTINCT fr.id)  AS response_count,
           COUNT(DISTINCT fl.id)  AS like_count,
           COUNT(DISTINCT fb.id)  AS bookmark_count,
           COUNT(DISTINCT fc.id)  AS comment_count,
           MAX(CASE WHEN fl.user_id = ? THEN 1 ELSE 0 END) AS user_liked,
           MAX(CASE WHEN fb.user_id = ? THEN 1 ELSE 0 END) AS user_bookmarked
    FROM form_bookmarks fb_me
    JOIN forms f    ON fb_me.form_id = f.id
    JOIN users u    ON f.user_id     = u.id
    LEFT JOIN form_responses fr ON f.id = fr.form_id
    LEFT JOIN form_likes     fl ON f.id = fl.form_id
    LEFT JOIN form_bookmarks fb ON f.id = fb.form_id
    LEFT JOIN form_comments  fc ON f.id = fc.form_id
    WHERE fb_me.user_id = ?
    GROUP BY f.id
    ORDER BY fb_me.created_at DESC");
mysqli_stmt_bind_param($stmtB, 'iii', $uid, $uid, $uid);
mysqli_stmt_execute($stmtB);
$bookmarked_forms = mysqli_stmt_get_result($stmtB);

// ── 按讚的表單 ──
$stmtL = mysqli_prepare($conn, "
    SELECT f.id, f.user_id, f.title, f.description, f.cover_image, f.end_date, f.is_published, f.show_stats,
           f.created_at, fli.created_at AS liked_at,
           u.username AS author, u.avatar AS author_avatar,
           COUNT(DISTINCT fr.id)  AS response_count,
           COUNT(DISTINCT flc.id) AS like_count,
           COUNT(DISTINCT fb.id)  AS bookmark_count,
           COUNT(DISTINCT fc.id)  AS comment_count,
           MAX(CASE WHEN flc.user_id = ? THEN 1 ELSE 0 END) AS user_liked,
           MAX(CASE WHEN fb.user_id  = ? THEN 1 ELSE 0 END) AS user_bookmarked
    FROM form_likes fli
    JOIN forms f    ON fli.form_id = f.id
    JOIN users u    ON f.user_id   = u.id
    LEFT JOIN form_responses fr  ON f.id = fr.form_id
    LEFT JOIN form_likes     flc ON f.id = flc.form_id
    LEFT JOIN form_bookmarks fb  ON f.id = fb.form_id
    LEFT JOIN form_comments  fc  ON f.id = fc.form_id
    WHERE fli.user_id = ?
    GROUP BY f.id
    ORDER BY fli.created_at DESC");
mysqli_stmt_bind_param($stmtL, 'iii', $uid, $uid, $uid);
mysqli_stmt_execute($stmtL);
$liked_forms = mysqli_stmt_get_result($stmtL);

$msg        = $_GET['msg'] ?? '';
$me_name    = $_SESSION['username'] ?? '';
$me_avatar  = $_SESSION['avatar']   ?? null;
$showNavSearch = true;
require_once '../config/header.php';

function timeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60)     return '剛剛';
    if ($d < 3600)   return floor($d/60)   . ' 分鐘前';
    if ($d < 86400)  return floor($d/3600) . ' 小時前';
    if ($d < 604800) return floor($d/86400). ' 天前';
    return date('Y/m/d', strtotime($dt));
}
?>

<div class="d-flex align-items-center justify-content-between gap-2 mb-3">
    <div class="dropdown">
        <button class="btn ps-2 fw-bold dropdown-toggle d-inline-flex align-items-center gap-1 lh-1" style="font-size:1.15rem;background:transparent;border:none;color:inherit;"
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-journal-text"></i> <span id="sort-label-text"><?php
                if ($tab === 'bookmarks') echo '最新收藏';
                elseif ($tab === 'likes') echo '最新按讚';
                else echo '最新建立';
            ?></span>
        </button>
        <ul class="dropdown-menu">
            <?php if ($tab === 'created'): ?>
            <li><button class="dropdown-item sort-opt active" data-mode="date_desc"><i class="bi bi-clock-history me-2 text-primary"></i>最新建立</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="date_asc"><i class="bi bi-clock me-2 text-secondary"></i>最舊建立</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="resp_desc"><i class="bi bi-people me-2 text-info"></i>回應最多</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="like_desc"><i class="bi bi-heart me-2 text-danger"></i>按讚最多</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="title_asc"><i class="bi bi-sort-alpha-down me-2 text-success"></i>標題 A→Z</button></li>
            <?php elseif ($tab === 'bookmarks'): ?>
            <li><button class="dropdown-item sort-opt active" data-mode="bookmarked_desc"><i class="bi bi-bookmark-fill me-2 text-primary"></i>最新收藏</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="bookmarked_asc"><i class="bi bi-bookmark me-2 text-secondary"></i>最舊收藏</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="resp_desc"><i class="bi bi-people me-2 text-info"></i>回應最多</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="like_desc"><i class="bi bi-heart me-2 text-danger"></i>按讚最多</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="title_asc"><i class="bi bi-sort-alpha-down me-2 text-success"></i>標題 A→Z</button></li>
            <?php else: ?>
            <li><button class="dropdown-item sort-opt active" data-mode="liked_desc"><i class="bi bi-heart-fill me-2 text-danger"></i>最新按讚</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="liked_asc"><i class="bi bi-heart me-2 text-secondary"></i>最舊按讚</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="resp_desc"><i class="bi bi-people me-2 text-info"></i>回應最多</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="like_desc"><i class="bi bi-heart me-2 text-primary"></i>按讚最多</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="title_asc"><i class="bi bi-sort-alpha-down me-2 text-success"></i>標題 A→Z</button></li>
            <?php endif; ?>
        </ul>
    </div>
    <a href="<?= REL_BASE ?>index.php" class="btn btn-glow-primary btn-sm flex-shrink-0">
        <i class="bi bi-arrow-left"></i> 返回首頁
    </a>
</div>

<?php if ($msg === 'created'): ?>
    <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle"></i> 表單建立成功！</div>
<?php elseif ($msg === 'updated'): ?>
    <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle"></i> 表單已更新</div>
<?php elseif ($msg === 'deleted'): ?>
    <div class="alert alert-warning alert-dismissible"><i class="bi bi-trash"></i> 表單已刪除</div>
<?php endif; ?>

<!-- 分頁標籤 -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'created'   ? 'active' : '' ?>" href="?tab=created">
            <i class="bi bi-journal-text me-1"></i> 建立的表單
            <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= mysqli_num_rows($created_forms) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'bookmarks' ? 'active' : '' ?>" href="?tab=bookmarks">
            <i class="bi bi-bookmark-fill me-1"></i> 收藏的表單
            <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= mysqli_num_rows($bookmarked_forms) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'likes'     ? 'active' : '' ?>" href="?tab=likes">
            <i class="bi bi-heart-fill me-1"></i> 按讚的表單
            <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?= mysqli_num_rows($liked_forms) ?></span>
        </a>
    </li>
</ul>

<?php if ($tab === 'created'): ?>
<!-- ════════════════ 建立的表單 ════════════════ -->
<?php if (mysqli_num_rows($created_forms) === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-plus" style="font-size:3rem;"></i>
        <p class="mt-3">還沒有建立任何表單</p>
        <a href="<?= REL_BASE ?>member/create_form.php" class="btn btn-glow-primary btn-sm">建立第一個表單</a>
    </div>
<?php else: ?>
<div class="row justify-content-center"><div class="col-lg-7">
<div id="cards-container">
<?php while ($form = mysqli_fetch_assoc($created_forms)):
    $expired   = $form['end_date'] && $form['end_date'] < $now;
    $plainDesc = strip_tags($form['description'] ?? '');
?>
<div class="card mb-4 form-card-item" id="cr-col-<?= $form['id'] ?>"
     data-date="<?= strtotime($form['created_at']) ?>"
     data-resp="<?= $form['response_count'] ?>"
     data-like="<?= $form['like_count'] ?>"
     data-title="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>"
     data-desc="<?= htmlspecialchars(mb_strtolower($plainDesc)) ?>">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <?= renderAvatarDropdown($me_name, $me_avatar, $uid, 40, $uid) ?>
        <div>
            <span class="fw-bold"><?= htmlspecialchars($me_name) ?></span>
            <div class="text-muted small"><?= timeAgo($form['created_at']) ?></div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if (!$form['is_published']): ?>
                <span class="badge bg-secondary">草稿</span>
            <?php endif; ?>
            <?php if ($form['end_date']): ?>
                <span class="deadline-tag <?= $expired ? 'deadline-expired' : '' ?>">
                    <i class="bi bi-clock"></i>
                    <?= $expired ? '已截止' : ('截止 ' . date('m/d', strtotime($form['end_date']))) ?>
                </span>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= REL_BASE ?>member/edit_form.php?id=<?= $form['id'] ?>"><i class="bi bi-pencil me-2"></i> 編輯表單</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= REL_BASE ?>member/form_responses.php?id=<?= $form['id'] ?>"><i class="bi bi-bar-chart text-info me-2"></i> 查看統計<?php if (!($form['show_stats'] ?? 1)): ?><i class="bi bi-lock-fill text-secondary ms-1 small"></i><?php endif; ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item btn-preview-form" data-form-id="<?= $form['id'] ?>" data-form-title="<?= htmlspecialchars($form['title']) ?>"><i class="bi bi-eye me-2"></i> 預覽表單</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" class="d-block m-0">
                            <input type="hidden" name="toggle_id" value="<?= $form['id'] ?>">
                            <button type="submit" class="dropdown-item">
                                <i class="bi bi-<?= $form['is_published'] ? 'pause-circle text-warning' : 'play-circle text-success' ?> me-2"></i>
                                <?= $form['is_published'] ? '取消發布' : '發布表單' ?>
                            </button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item btn-delete-my-form" data-form-id="<?= $form['id'] ?>"><i class="bi bi-trash me-2"></i> 刪除表單</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item btn-report-my-form" data-form-id="<?= $form['id'] ?>"><i class="bi bi-flag me-2"></i> 檢舉表單</button></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body pb-2">
        <h5 class="fw-bold mb-2 myf-title"><?= htmlspecialchars($form['title']) ?></h5>
        <?php if ($plainDesc): ?>
            <p class="text-muted mb-2 myf-desc"><?= htmlspecialchars(mb_substr($plainDesc, 0, 120)) ?><?= mb_strlen($plainDesc) > 120 ? '...' : '' ?></p>
        <?php endif; ?>
        <?php if ($form['cover_image']): ?>
            <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="rounded mb-3 w-100" style="max-height:250px;object-fit:cover;">
        <?php endif; ?>
        <div class="text-muted small mb-3"><i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答</div>
        <?php if (!$form['is_published']): ?>
            <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled><i class="bi bi-lock"></i> 草稿，尚未發布</button>
        <?php elseif ($expired): ?>
            <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled><i class="bi bi-lock"></i> 已截止，無法填寫</button>
        <?php else: ?>
            <a href="<?= REL_BASE ?>form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary w-100 mb-3"><i class="bi bi-pencil"></i> 填寫表單</a>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-transparent border-top d-flex justify-content-around py-2">
        <button class="btn btn-sm btn-like <?= $form['user_liked'] ? 'liked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
            <i class="bi <?= $form['user_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
            <span class="like-count"><?= $form['like_count'] ?></span>
        </button>
        <button class="btn btn-sm text-muted btn-toggle-feed-comments" data-id="<?= $form['id'] ?>">
            <i class="bi bi-chat"></i> <span class="feed-comment-count-<?= $form['id'] ?>"><?= $form['comment_count'] ?></span>
        </button>
        <button class="btn btn-sm btn-bookmark <?= $form['user_bookmarked'] ? 'bookmarked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
            <i class="bi <?= $form['user_bookmarked'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
            <span class="bookmark-count"><?= $form['bookmark_count'] ?></span>
        </button>
    </div>
    <div class="feed-comments-panel px-3 pb-2" id="feed-comments-<?= $form['id'] ?>" style="display:none;"></div>
</div>
<?php endwhile; ?>
</div>
<div class="text-center py-4 text-muted d-none" id="no-results">
    <i class="bi bi-search" style="font-size:2rem;"></i><p class="mt-2">找不到符合的表單</p>
</div>
</div></div><!-- col-lg-7 / row -->
<?php endif; ?>

<?php elseif ($tab === 'bookmarks'): ?>
<!-- ════════════════ 收藏的表單 ════════════════ -->
<?php if (mysqli_num_rows($bookmarked_forms) === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-bookmark" style="font-size:3rem;"></i>
        <p class="mt-3">尚未收藏任何表單</p>
        <a href="<?= REL_BASE ?>index.php" class="btn btn-glow-primary btn-sm">去探索表單</a>
    </div>
<?php else: ?>
<div class="row justify-content-center"><div class="col-lg-7">
<div id="cards-container">
<?php while ($form = mysqli_fetch_assoc($bookmarked_forms)):
    $expired   = $form['end_date'] && $form['end_date'] < $now;
    $plainDesc = strip_tags($form['description'] ?? '');
?>
<div class="card mb-4 form-card-item" id="bk-col-<?= $form['id'] ?>"
     data-bookmarked="<?= strtotime($form['bookmarked_at']) ?>"
     data-date="<?= strtotime($form['created_at']) ?>"
     data-resp="<?= $form['response_count'] ?>"
     data-like="<?= $form['like_count'] ?>"
     data-title="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>"
     data-desc="<?= htmlspecialchars(mb_strtolower($plainDesc)) ?>">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <?= renderAvatarDropdown($form['author'], $form['author_avatar'] ?? null, $form['user_id'], 40, $uid) ?>
        <div>
            <a href="<?= REL_BASE ?>profile.php?id=<?= $form['user_id'] ?>" class="fw-bold text-decoration-none link-body-emphasis"><?= htmlspecialchars($form['author']) ?></a>
            <div class="text-muted small"><?= timeAgo($form['created_at']) ?></div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if ($form['end_date']): ?>
                <span class="deadline-tag <?= $expired ? 'deadline-expired' : '' ?>">
                    <i class="bi bi-clock"></i>
                    <?= $expired ? '已截止' : ('截止 ' . date('m/d', strtotime($form['end_date']))) ?>
                </span>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($form['user_id'] == $uid): ?>
                    <li><a class="dropdown-item" href="<?= REL_BASE ?>member/edit_form.php?id=<?= $form['id'] ?>"><i class="bi bi-pencil me-2"></i> 編輯表單</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <?php if ($form['show_stats'] ?? 1): ?>
                    <li><a class="dropdown-item" href="<?= REL_BASE ?>member/form_responses.php?id=<?= $form['id'] ?>"><i class="bi bi-bar-chart text-info me-2"></i> 查看統計</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><button class="dropdown-item btn-preview-form" data-form-id="<?= $form['id'] ?>" data-form-title="<?= htmlspecialchars($form['title']) ?>"><i class="bi bi-eye me-2"></i> 預覽表單</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item btn-report-my-form" data-form-id="<?= $form['id'] ?>"><i class="bi bi-flag me-2"></i> 檢舉表單</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-warning btn-remove-bookmark" data-id="<?= $form['id'] ?>"><i class="bi bi-bookmark-x me-2"></i> 取消收藏</button></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body pb-2">
        <h5 class="fw-bold mb-2 myf-title"><?= htmlspecialchars($form['title']) ?></h5>
        <?php if ($plainDesc): ?>
            <p class="text-muted mb-2 myf-desc"><?= htmlspecialchars(mb_substr($plainDesc, 0, 120)) ?><?= mb_strlen($plainDesc) > 120 ? '...' : '' ?></p>
        <?php endif; ?>
        <?php if ($form['cover_image']): ?>
            <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="rounded mb-3 w-100" style="max-height:250px;object-fit:cover;">
        <?php endif; ?>
        <div class="text-muted small mb-3"><i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答</div>
        <?php if (!$form['is_published']): ?>
            <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled><i class="bi bi-lock"></i> 尚未發布</button>
        <?php elseif ($expired): ?>
            <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled><i class="bi bi-lock"></i> 已截止，無法填寫</button>
        <?php else: ?>
            <a href="<?= REL_BASE ?>form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary w-100 mb-3"><i class="bi bi-pencil"></i> 填寫表單</a>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-transparent border-top d-flex justify-content-around py-2">
        <button class="btn btn-sm btn-like <?= $form['user_liked'] ? 'liked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
            <i class="bi <?= $form['user_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
            <span class="like-count"><?= $form['like_count'] ?></span>
        </button>
        <button class="btn btn-sm text-muted btn-toggle-feed-comments" data-id="<?= $form['id'] ?>">
            <i class="bi bi-chat"></i> <span class="feed-comment-count-<?= $form['id'] ?>"><?= $form['comment_count'] ?></span>
        </button>
        <button class="btn btn-sm btn-bookmark <?= $form['user_bookmarked'] ? 'bookmarked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
            <i class="bi <?= $form['user_bookmarked'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
            <span class="bookmark-count"><?= $form['bookmark_count'] ?></span>
        </button>
    </div>
    <div class="feed-comments-panel px-3 pb-2" id="feed-comments-<?= $form['id'] ?>" style="display:none;"></div>
</div>
<?php endwhile; ?>
</div>
<div class="text-center py-4 text-muted d-none" id="no-results">
    <i class="bi bi-search" style="font-size:2rem;"></i><p class="mt-2">找不到符合的表單</p>
</div>
</div></div><!-- col-lg-7 / row -->
<?php endif; ?>

<?php else: ?>
<!-- ════════════════ 按讚的表單 ════════════════ -->

<?php if (mysqli_num_rows($liked_forms) === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-heart" style="font-size:3rem;"></i>
        <p class="mt-3">尚未對任何表單按讚</p>
        <a href="<?= REL_BASE ?>index.php" class="btn btn-glow-primary btn-sm">去探索表單</a>
    </div>
<?php else: ?>
<div class="row justify-content-center"><div class="col-lg-7">
<div id="cards-container">
<?php while ($form = mysqli_fetch_assoc($liked_forms)):
    $expired   = $form['end_date'] && $form['end_date'] < $now;
    $plainDesc = strip_tags($form['description'] ?? '');
?>
<div class="card mb-4 form-card-item" id="lk-col-<?= $form['id'] ?>"
     data-liked="<?= strtotime($form['liked_at']) ?>"
     data-date="<?= strtotime($form['created_at']) ?>"
     data-resp="<?= $form['response_count'] ?>"
     data-like="<?= $form['like_count'] ?>"
     data-title="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>"
     data-desc="<?= htmlspecialchars(mb_strtolower($plainDesc)) ?>">
    <div class="card-header d-flex align-items-center gap-2 py-2">
        <?= renderAvatarDropdown($form['author'], $form['author_avatar'] ?? null, $form['user_id'], 40, $uid) ?>
        <div>
            <a href="<?= REL_BASE ?>profile.php?id=<?= $form['user_id'] ?>" class="fw-bold text-decoration-none link-body-emphasis"><?= htmlspecialchars($form['author']) ?></a>
            <div class="text-muted small"><?= timeAgo($form['created_at']) ?></div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if ($form['end_date']): ?>
                <span class="deadline-tag <?= $expired ? 'deadline-expired' : '' ?>">
                    <i class="bi bi-clock"></i>
                    <?= $expired ? '已截止' : ('截止 ' . date('m/d', strtotime($form['end_date']))) ?>
                </span>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($form['user_id'] == $uid): ?>
                    <li><a class="dropdown-item" href="<?= REL_BASE ?>member/edit_form.php?id=<?= $form['id'] ?>"><i class="bi bi-pencil me-2"></i> 編輯表單</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <?php if ($form['show_stats'] ?? 1): ?>
                    <li><a class="dropdown-item" href="<?= REL_BASE ?>member/form_responses.php?id=<?= $form['id'] ?>"><i class="bi bi-bar-chart text-info me-2"></i> 查看統計</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><button class="dropdown-item btn-preview-form" data-form-id="<?= $form['id'] ?>" data-form-title="<?= htmlspecialchars($form['title']) ?>"><i class="bi bi-eye me-2"></i> 預覽表單</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item btn-report-my-form" data-form-id="<?= $form['id'] ?>"><i class="bi bi-flag me-2"></i> 檢舉表單</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger btn-unlike" data-id="<?= $form['id'] ?>"><i class="bi bi-heartbreak me-2"></i> 取消按讚</button></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body pb-2">
        <h5 class="fw-bold mb-2 myf-title"><?= htmlspecialchars($form['title']) ?></h5>
        <?php if ($plainDesc): ?>
            <p class="text-muted mb-2 myf-desc"><?= htmlspecialchars(mb_substr($plainDesc, 0, 120)) ?><?= mb_strlen($plainDesc) > 120 ? '...' : '' ?></p>
        <?php endif; ?>
        <?php if ($form['cover_image']): ?>
            <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="rounded mb-3 w-100" style="max-height:250px;object-fit:cover;">
        <?php endif; ?>
        <div class="text-muted small mb-3"><i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答</div>
        <?php if (!$form['is_published']): ?>
            <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled><i class="bi bi-lock"></i> 尚未發布</button>
        <?php elseif ($expired): ?>
            <button class="btn btn-outline-secondary btn-sm w-100 mb-3" disabled><i class="bi bi-lock"></i> 已截止，無法填寫</button>
        <?php else: ?>
            <a href="<?= REL_BASE ?>form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary w-100 mb-3"><i class="bi bi-pencil"></i> 填寫表單</a>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-transparent border-top d-flex justify-content-around py-2">
        <button class="btn btn-sm btn-like <?= $form['user_liked'] ? 'liked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
            <i class="bi <?= $form['user_liked'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
            <span class="like-count"><?= $form['like_count'] ?></span>
        </button>
        <button class="btn btn-sm text-muted btn-toggle-feed-comments" data-id="<?= $form['id'] ?>">
            <i class="bi bi-chat"></i> <span class="feed-comment-count-<?= $form['id'] ?>"><?= $form['comment_count'] ?></span>
        </button>
        <button class="btn btn-sm btn-bookmark <?= $form['user_bookmarked'] ? 'bookmarked' : 'text-muted' ?>" data-id="<?= $form['id'] ?>">
            <i class="bi <?= $form['user_bookmarked'] ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
            <span class="bookmark-count"><?= $form['bookmark_count'] ?></span>
        </button>
    </div>
    <div class="feed-comments-panel px-3 pb-2" id="feed-comments-<?= $form['id'] ?>" style="display:none;"></div>
</div>
<?php endwhile; ?>
</div>
<div class="text-center py-4 text-muted d-none" id="no-results">
    <i class="bi bi-search" style="font-size:2rem;"></i><p class="mt-2">找不到符合的表單</p>
</div>
</div></div><!-- col-lg-7 / row -->
<?php endif; ?>

<?php endif; ?>

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

// ── Navbar 搜尋 ──
$('#nav-search-toggle').on('click', function () {
    $('#nav-search-box').removeClass('d-none');
    $('#nav-search-input').focus();
});
$('#nav-search-close').on('click', function () {
    $('#nav-search-input').val('').trigger('input');
    $('#nav-search-box').addClass('d-none');
});

function mfEscReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function mfEscHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function mfHighlight($el, q) {
    const orig = $el.data('orig') ?? $el.text();
    $el.data('orig', orig);
    if (!q) { $el.html(mfEscHtml(orig)); return; }
    $el.html(mfEscHtml(orig).replace(new RegExp('(' + mfEscReg(q) + ')', 'gi'), '<mark class="search-hl">$1</mark>'));
}
$('#nav-search-input').on('input', function () {
    const q = $(this).val().toLowerCase().trim();
    let visible = 0;
    $('.form-card-item').each(function () {
        const match = !q || String($(this).data('title')||'').includes(q) || String($(this).data('desc')||'').includes(q);
        $(this).toggle(match);
        if (match) {
            mfHighlight($(this).find('.myf-title'), q);
            mfHighlight($(this).find('.myf-desc'),  q);
            visible++;
        }
    });
    if (!q) $('.myf-title, .myf-desc').each(function () {
        const o = $(this).data('orig');
        if (o !== undefined) $(this).html(mfEscHtml(o));
    });
    $('#no-results').toggleClass('d-none', visible > 0 || !q);
});

// ── 排序 ──
$(document).on('click', '.sort-opt', function () {
    const mode  = $(this).data('mode');
    const label = $(this).text().trim();
    $('.sort-opt').removeClass('active');
    $(this).addClass('active');
    $('#sort-label-text').text(label);
    const $c = $('#cards-container');
    const $items = $c.children('.form-card-item').toArray();
    $items.sort(function (a, b) {
        const $a = $(a), $b = $(b);
        if (mode === 'date_desc')       return $b.data('date')       - $a.data('date');
        if (mode === 'date_asc')        return $a.data('date')       - $b.data('date');
        if (mode === 'resp_desc')       return $b.data('resp')       - $a.data('resp');
        if (mode === 'like_desc')       return $b.data('like')       - $a.data('like');
        if (mode === 'bookmarked_desc') return $b.data('bookmarked') - $a.data('bookmarked');
        if (mode === 'bookmarked_asc')  return $a.data('bookmarked') - $b.data('bookmarked');
        if (mode === 'liked_desc')      return $b.data('liked')      - $a.data('liked');
        if (mode === 'liked_asc')       return $a.data('liked')      - $b.data('liked');
        if (mode === 'title_asc')       return $a.data('title').localeCompare($b.data('title'), 'zh-Hant');
        return 0;
    });
    $c.append($items);
});

// ── 按讚 ──
$(document).on('click', '.btn-like', function () {
    const btn = $(this), formId = btn.data('id');
    $.post('<?= REL_BASE ?>api/like.php', { form_id: formId }, function (res) {
        if (res.success) {
            btn.find('.like-count').text(res.count);
            if (res.liked) { btn.removeClass('text-muted').addClass('liked'); btn.find('i').removeClass('bi-heart').addClass('bi-heart-fill'); }
            else           { btn.removeClass('liked').addClass('text-muted'); btn.find('i').removeClass('bi-heart-fill').addClass('bi-heart'); }
        }
    }, 'json');
});

// ── 收藏 ──
$(document).on('click', '.btn-bookmark', function () {
    const btn = $(this), formId = btn.data('id');
    $.post('<?= REL_BASE ?>api/bookmark.php', { form_id: formId }, function (res) {
        if (res.success) {
            btn.find('.bookmark-count').text(res.count);
            if (res.bookmarked) { btn.removeClass('text-muted').addClass('bookmarked'); btn.find('i').removeClass('bi-bookmark').addClass('bi-bookmark-fill'); }
            else                { btn.removeClass('bookmarked').addClass('text-muted'); btn.find('i').removeClass('bi-bookmark-fill').addClass('bi-bookmark'); }
        }
    }, 'json');
});

// ── 留言展開 ──
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
    $panel.html('<div class="text-center py-3 border-top"><span class="spinner-border spinner-border-sm text-muted"></span></div>').slideDown(200);
    $.get('<?= REL_BASE ?>api/comments_panel.php', { form_id: formId }, function (html) {
        $panel.html(html).data('loaded', true);
    });
});

// ── 留言檢舉 ──
$(document).on('click', '.btn-report-comment', function () {
    window.cuteReport('comment', $(this).data('comment-id'), '檢舉留言');
});

// ── 預覽 ──
const _previewModalEl = document.getElementById('previewModal');
$(document).on('click', '.btn-preview-form', function () {
    $('#preview-form-title').text($(this).data('form-title'));
    $('#preview-body').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
    bootstrap.Modal.getOrCreateInstance(_previewModalEl).show();
    $.get('<?= REL_BASE ?>api/form_preview_content.php', { id: $(this).data('form-id') }, function (html) {
        $('#preview-body').html(html);
    }).fail(function () { $('#preview-body').html('<div class="alert alert-danger">載入失敗</div>'); });
});
_previewModalEl.addEventListener('hidden.bs.modal', function () { $('#preview-body').html(''); });

// ── 刪除 ──
$(document).on('click', '.btn-delete-my-form', function () {
    const formId = $(this).data('form-id');
    window.cuteConfirm({ icon: '📋', msg: '確定要刪除這個表單嗎？', sub: '此操作無法復原。' }, function (ok) {
        if (!ok) return;
        $.post('<?= REL_BASE ?>api/delete_form.php', { form_id: formId }, function (res) {
            if (res.success) $('#cr-col-' + formId).fadeOut(300, function () { $(this).remove(); });
            else alert(res.message || '刪除失敗');
        }, 'json');
    });
});

// ── 取消收藏 ──
$(document).on('click', '.btn-remove-bookmark', function () {
    const formId = $(this).data('id');
    $.post('<?= REL_BASE ?>api/bookmark.php', { form_id: formId }, function (res) {
        if (res.success && !res.bookmarked) $('#bk-col-' + formId).fadeOut(300, function () { $(this).remove(); });
    }, 'json');
});

// ── 取消按讚 ──
$(document).on('click', '.btn-unlike', function () {
    const formId = $(this).data('id');
    $.post('<?= REL_BASE ?>api/like.php', { form_id: formId }, function (res) {
        if (res.success && !res.liked) $('#lk-col-' + formId).fadeOut(300, function () { $(this).remove(); });
    }, 'json');
});

// ── 檢舉 ──
$(document).on('click', '.btn-report-my-form', function () {
    window.cuteReport('form', $(this).data('form-id'), '檢舉表單');
});

}); // end $(document).ready
</script>

<?php require_once '../config/footer.php'; ?>
