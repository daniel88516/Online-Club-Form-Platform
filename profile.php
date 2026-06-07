<?php
$pageTitle = '個人頁面';
require_once 'config/session.php';
require_once 'config/db.php';

$profile_uid = intval($_GET['id'] ?? 0);
if (!$profile_uid) {
    header('Location: ' . APP_BASE . '/index.php');
    exit();
}

addColumnIfNotExists($conn, 'users', 'profile_bg_ratio', 'TINYINT NOT NULL DEFAULT 7');

// 取用戶資料
$stmt = mysqli_prepare($conn, "SELECT id, username, avatar, bio, created_at, profile_bg, profile_bg_ratio FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $profile_uid);
mysqli_stmt_execute($stmt);
$pu = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$pu) {
    header('Location: ' . APP_BASE . '/index.php');
    exit();
}

$cur_uid = isLoggedIn() ? $_SESSION['user_id'] : 0;
$now     = date('Y-m-d H:i:s');
$has_form_clubs = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'form_clubs'")) > 0;

function profileFormFillBlockReason($conn, $form, $user_id, $now, $has_form_clubs) {
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
            while ($row = mysqli_fetch_assoc($res)) {
                $club_ids[] = (int)$row['club_id'];
            }
        }
        if (!empty($form['club_id'])) {
            $club_ids[] = (int)$form['club_id'];
        }
        $club_ids = array_values(array_unique(array_filter($club_ids)));
        if (empty($club_ids)) {
            return '僅限所屬社團成員填答';
        }
        $list = implode(',', array_map('intval', $club_ids));
        $chk = mysqli_query($conn, "SELECT id FROM club_members WHERE user_id = $user_id AND status = 'active' AND club_id IN ($list) LIMIT 1");
        if (!mysqli_fetch_assoc($chk)) {
            return '僅限所屬社團成員填答';
        }
    }
    return '';
}

// 取已發布表單
$stmtF = mysqli_prepare($conn, "
    SELECT f.id, f.title, f.description, f.start_date, f.end_date, f.allow_multiple, f.response_scope, f.club_id, f.created_at,
           COUNT(DISTINCT fr.id) AS response_count,
           COUNT(DISTINCT fl.id) AS like_count,
           COUNT(DISTINCT fb.id) AS bookmark_count
    FROM forms f
    LEFT JOIN form_responses fr ON f.id = fr.form_id
    LEFT JOIN form_likes     fl ON f.id = fl.form_id
    LEFT JOIN form_bookmarks fb ON f.id = fb.form_id
    WHERE f.user_id = ? AND f.is_published = 1
    GROUP BY f.id
    ORDER BY f.created_at DESC
");
mysqli_stmt_bind_param($stmtF, 'i', $profile_uid);
mysqli_stmt_execute($stmtF);
$forms_result = mysqli_stmt_get_result($stmtF);
$forms_count  = mysqli_num_rows($forms_result);

require_once 'config/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7">

<!-- 個人資訊卡 -->
<?php $has_bg = !empty($pu['profile_bg']); ?>
<?php $bg_ratio = intval($pu['profile_bg_ratio'] ?? 7); ?>
<div class="card mb-3" style="overflow:hidden;position:relative;aspect-ratio:<?= $bg_ratio ?>/3;">
    <!-- 背景圖（填滿整張卡片） -->
    <div style="position:absolute;inset:0;">
        <?php if ($has_bg): ?>
            <img src="<?= htmlspecialchars(assetUrl($pu['profile_bg'])) ?>"
                 style="width:100%;height:100%;object-fit:cover;" alt="背景">
            <!-- 底部漸層遮罩，提升文字可讀性 -->
            <div style="position:absolute;inset:0;
                        background:linear-gradient(to bottom, transparent 35%, rgba(0,0,0,0.72) 100%);"></div>
        <?php else: ?>
            <div style="width:100%;height:100%;background:var(--bs-secondary-bg);"></div>
        <?php endif; ?>
    </div>

    <!-- 內容貼齊底部 -->
    <div style="position:absolute;inset:0;z-index:1;
                display:flex;flex-direction:column;justify-content:flex-end;padding:20px 24px;">
        <!-- 頭像 -->
        <div class="mb-2" style="display:inline-block;border-radius:50%;align-self:flex-start;
                                  border:3px solid <?= $has_bg ? 'rgba(255,255,255,0.6)' : 'var(--bs-border-color)' ?>;">
            <?= renderAvatar($pu['username'], $pu['avatar'], 64) ?>
        </div>
        <!-- 名字 -->
        <h4 class="fw-bold mb-1" style="color:<?= $has_bg ? '#fff' : 'inherit' ?>;
            text-shadow:<?= $has_bg ? '0 1px 6px rgba(0,0,0,0.6)' : 'none' ?>;">
            <?= htmlspecialchars($pu['username']) ?>
        </h4>
        <!-- 簡介 -->
        <?php if (!empty($pu['bio'])): ?>
            <p class="mb-1" style="white-space:pre-line;font-size:.9rem;
               color:<?= $has_bg ? 'rgba(255,255,255,0.85)' : 'var(--bs-secondary-color)' ?>"><?= htmlspecialchars(trim($pu['bio'])) ?></p>
        <?php endif; ?>
        <!-- 加入時間 -->
        <small style="color:<?= $has_bg ? 'rgba(255,255,255,0.6)' : 'var(--bs-secondary-color)' ?>;">
            <i class="bi bi-calendar3 me-1"></i>
            <?= date('Y 年 m 月加入', strtotime($pu['created_at'])) ?>
        </small>
    </div>
</div>

<!-- 操作按鈕（卡片外） -->
<div class="mb-4 d-flex justify-content-center gap-2">
    <?php if ($cur_uid && $cur_uid != $profile_uid): ?>
        <button class="btn btn-glow-primary btn-sm"
                onclick="openChatWith(<?= $profile_uid ?>, '<?= addslashes(htmlspecialchars($pu['username'])) ?>', '<?= addslashes(assetUrl($pu['avatar'] ?? '')) ?>')">
            <i class="bi bi-chat me-1"></i> 聊天
        </button>
    <?php elseif ($cur_uid == $profile_uid): ?>
        <a href="<?= REL_BASE ?>member/settings.php" class="btn btn-glow-primary">
            <i class="bi bi-pencil me-1"></i> 編輯個人資料
        </a>
    <?php endif; ?>
</div>

<!-- 統計 -->
<div class="d-flex justify-content-center gap-4 mb-4 text-center">
    <div>
        <div class="fw-bold fs-5"><?= $forms_count ?></div>
        <div class="text-muted small">發布表單</div>
    </div>
</div>

<!-- 發布的表單 -->
<h5 class="fw-bold mb-3"><i class="bi bi-journal-text me-1"></i> 發布的表單</h5>

<?php if ($forms_count === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
        <p class="mt-2">尚未發布任何表單</p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php while ($form = mysqli_fetch_assoc($forms_result)): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($form['title']) ?></h6>
                    <?php $desc = trim(strip_tags($form['description'] ?? '')); ?>
                    <p class="text-muted small mb-2 text-truncate" style="min-height:1.2em;">
                        <?= $desc ? htmlspecialchars($desc) : '&nbsp;' ?>
                    </p>
                    <div class="text-muted small">
                        <i class="bi bi-people"></i> <?= $form['response_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-heart"></i> <?= $form['like_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-bookmark"></i> <?= $form['bookmark_count'] ?>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <?php $fillBlockReason = profileFormFillBlockReason($conn, $form, $cur_uid, $now, $has_form_clubs); ?>
                    <?php if (!$fillBlockReason): ?>
                        <a href="<?= REL_BASE ?>form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary btn-sm w-100">
                            <i class="bi bi-pencil"></i> 填寫表單
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary btn-sm w-100 form-fill-disabled" disabled>
                            <i class="bi bi-lock-fill me-1"></i> <?= htmlspecialchars($fillBlockReason) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

</div>
</div>

<?php require_once 'config/footer.php'; ?>
