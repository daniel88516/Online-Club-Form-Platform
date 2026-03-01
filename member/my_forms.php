<?php
$pageTitle = '我的表單';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$uid = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');
$tab = in_array($_GET['tab'] ?? 'created', ['created', 'bookmarks', 'likes'])
       ? ($_GET['tab'] ?? 'created') : 'created';

// 處理發布/取消發布（仍保留 form POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggle_id = intval($_POST['toggle_id']);
    $stmt = mysqli_prepare($conn, "UPDATE forms SET is_published = !is_published WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $toggle_id, $uid);
    mysqli_stmt_execute($stmt);
    header('Location: /member/my_forms.php?tab=created');
    exit();
}

// ── 建立的表單 ──────────────────────────────────────────────
$sqlC = "
    SELECT f.id, f.title, f.description, f.is_published, f.created_at, f.end_date,
           f.show_stats, f.anonymous_responses,
           COUNT(DISTINCT fr.id) AS response_count,
           COUNT(DISTINCT fl.id) AS like_count,
           COUNT(DISTINCT fb.id) AS bookmark_count
    FROM forms f
    LEFT JOIN form_responses fr  ON f.id = fr.form_id
    LEFT JOIN form_likes     fl  ON f.id = fl.form_id
    LEFT JOIN form_bookmarks fb  ON f.id = fb.form_id
    WHERE f.user_id = ?
    GROUP BY f.id
    ORDER BY f.created_at DESC";
$stmtC = mysqli_prepare($conn, $sqlC);
mysqli_stmt_bind_param($stmtC, 'i', $uid);
mysqli_stmt_execute($stmtC);
$created_forms = mysqli_stmt_get_result($stmtC);

// ── 收藏的表單 ──────────────────────────────────────────────
$stmtB = mysqli_prepare($conn, "
    SELECT f.id, f.user_id, f.title, f.description, f.end_date, f.is_published, f.show_stats,
           u.username AS author, u.avatar AS author_avatar,
           COUNT(DISTINCT fr.id)  AS response_count,
           COUNT(DISTINCT fl.id)  AS like_count,
           COUNT(DISTINCT fbc.id) AS bookmark_count
    FROM form_bookmarks fb
    JOIN forms f    ON fb.form_id  = f.id
    JOIN users u    ON f.user_id   = u.id
    LEFT JOIN form_responses fr  ON f.id = fr.form_id
    LEFT JOIN form_likes     fl  ON f.id = fl.form_id
    LEFT JOIN form_bookmarks fbc ON f.id = fbc.form_id
    WHERE fb.user_id = ?
    GROUP BY f.id
    ORDER BY fb.created_at DESC
");
mysqli_stmt_bind_param($stmtB, 'i', $uid);
mysqli_stmt_execute($stmtB);
$bookmarked_forms = mysqli_stmt_get_result($stmtB);

// ── 按讚的表單 ──────────────────────────────────────────────
$stmtL = mysqli_prepare($conn, "
    SELECT f.id, f.user_id, f.title, f.description, f.end_date, f.is_published, f.show_stats,
           u.username AS author, u.avatar AS author_avatar,
           COUNT(DISTINCT fr.id)  AS response_count,
           COUNT(DISTINCT flc.id) AS like_count,
           COUNT(DISTINCT fb.id)  AS bookmark_count
    FROM form_likes fli
    JOIN forms f    ON fli.form_id = f.id
    JOIN users u    ON f.user_id   = u.id
    LEFT JOIN form_responses fr  ON f.id = fr.form_id
    LEFT JOIN form_likes     flc ON f.id = flc.form_id
    LEFT JOIN form_bookmarks fb  ON f.id = fb.form_id
    WHERE fli.user_id = ?
    GROUP BY f.id
    ORDER BY f.created_at DESC
");
mysqli_stmt_bind_param($stmtL, 'i', $uid);
mysqli_stmt_execute($stmtL);
$liked_forms = mysqli_stmt_get_result($stmtL);

$msg = $_GET['msg'] ?? '';
require_once '../config/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text"></i> 我的表單</h4>
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

<!-- 即時搜尋 -->
<div class="mb-3 position-relative">
    <i class="bi bi-search position-absolute text-muted" style="left:.75rem;top:50%;transform:translateY(-50%);pointer-events:none;font-size:.9rem;"></i>
    <input type="text" id="search-input" class="form-control ps-4" placeholder="搜尋表單標題...">
</div>

<?php if (mysqli_num_rows($created_forms) === 0): ?>
    <div class="text-center py-5 text-muted" id="tab-empty">
        <i class="bi bi-journal-plus" style="font-size:3rem;"></i>
        <p class="mt-3">還沒有建立任何表單</p>
        <a href="/member/create_form.php" class="btn btn-primary btn-sm">建立第一個表單</a>
    </div>
<?php else: ?>
    <div class="row g-3" id="cards-container">
        <?php while ($form = mysqli_fetch_assoc($created_forms)): ?>
        <div class="col-md-6 form-card-item" id="cr-col-<?= $form['id'] ?>"
             data-search="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>">
            <div class="card h-100">
                <div class="card-body">
                    <!-- 狀態 + 三連點 -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($form['is_published']): ?>
                                <span class="badge bg-success">已發布</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">草稿</span>
                            <?php endif; ?>
                            <?php if ($form['end_date'] && $form['end_date'] < $now): ?>
                                <span class="badge bg-danger">已截止</span>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="/member/edit_form.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-pencil me-2"></i> 編輯表單
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/member/form_responses.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                        <?php if (!($form['show_stats'] ?? 1)): ?>
                                            <i class="bi bi-lock-fill text-secondary ms-1 small" title="統計未公開"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item btn-preview-form"
                                            data-form-id="<?= $form['id'] ?>"
                                            data-form-title="<?= htmlspecialchars($form['title']) ?>">
                                        <i class="bi bi-eye me-2"></i> 預覽表單
                                    </button>
                                </li>
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
                                <li>
                                    <button class="dropdown-item btn-delete-my-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-trash me-2"></i> 刪除表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item btn-report-my-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-flag me-2"></i> 檢舉表單
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($form['title']) ?></h6>
                    <?php if ($form['description']): ?>
                        <p class="text-muted small mb-2"><?= htmlspecialchars(mb_substr(strip_tags($form['description']), 0, 80)) ?><?= mb_strlen(strip_tags($form['description'])) > 80 ? '…' : '' ?></p>
                    <?php endif; ?>

                    <div class="text-muted small mt-2">
                        <i class="bi bi-people"></i> <?= $form['response_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-heart"></i> <?= $form['like_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-bookmark"></i> <?= $form['bookmark_count'] ?>
                    </div>
                </div>
                <?php if ($form['is_published'] && (!$form['end_date'] || $form['end_date'] >= $now)): ?>
                <div class="card-footer bg-transparent">
                    <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-pencil"></i> 填寫表單
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="text-center py-4 text-muted d-none" id="no-results">
        <i class="bi bi-search" style="font-size:2rem;"></i>
        <p class="mt-2">找不到符合的表單</p>
    </div>
<?php endif; ?>

<?php elseif ($tab === 'bookmarks'): ?>
<!-- ════════════════ 收藏的表單 ════════════════ -->

<!-- 即時搜尋 -->
<div class="mb-3 position-relative">
    <i class="bi bi-search position-absolute text-muted" style="left:.75rem;top:50%;transform:translateY(-50%);pointer-events:none;font-size:.9rem;"></i>
    <input type="text" id="search-input" class="form-control ps-4" placeholder="搜尋表單標題...">
</div>

<?php if (mysqli_num_rows($bookmarked_forms) === 0): ?>
    <div class="text-center py-5 text-muted" id="tab-empty">
        <i class="bi bi-bookmark" style="font-size:3rem;"></i>
        <p class="mt-3">尚未收藏任何表單</p>
        <a href="/index.php" class="btn btn-outline-primary btn-sm">去探索表單</a>
    </div>
<?php else: ?>
    <div class="row g-3" id="cards-container">
        <?php while ($form = mysqli_fetch_assoc($bookmarked_forms)): ?>
        <div class="col-md-6 form-card-item" id="bk-col-<?= $form['id'] ?>"
             data-search="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>">
            <div class="card h-100">
                <div class="card-body">
                    <!-- 作者 + 三連點 -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <?= renderAvatarDropdown($form['author'], $form['author_avatar'] ?? null, $form['user_id'], 28, $uid) ?>
                            <a href="/profile.php?id=<?= $form['user_id'] ?>" class="small text-muted text-decoration-none">
                                <?= htmlspecialchars($form['author']) ?>
                            </a>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($form['user_id'] == $uid): ?>
                                <li>
                                    <a class="dropdown-item" href="/member/edit_form.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-pencil me-2"></i> 編輯表單
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <?php if ($form['show_stats'] ?? 1): ?>
                                <li>
                                    <a class="dropdown-item" href="/member/form_responses.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <button class="dropdown-item btn-preview-form"
                                            data-form-id="<?= $form['id'] ?>"
                                            data-form-title="<?= htmlspecialchars($form['title']) ?>">
                                        <i class="bi bi-eye me-2"></i> 預覽表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item btn-report-my-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-flag me-2"></i> 檢舉表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-warning btn-remove-bookmark" data-id="<?= $form['id'] ?>">
                                        <i class="bi bi-bookmark-x me-2"></i> 取消收藏
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($form['title']) ?></h6>
                    <?php if ($form['description']): ?>
                        <p class="text-muted small mb-2"><?= htmlspecialchars(mb_substr(strip_tags($form['description']), 0, 80)) ?><?= mb_strlen(strip_tags($form['description'])) > 80 ? '…' : '' ?></p>
                    <?php endif; ?>

                    <div class="text-muted small mt-2">
                        <i class="bi bi-people"></i> <?= $form['response_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-heart"></i> <?= $form['like_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-bookmark"></i> <?= $form['bookmark_count'] ?>
                    </div>

                    <?php if (!$form['is_published']): ?>
                        <span class="badge bg-secondary mt-2">未發布</span>
                    <?php elseif ($form['end_date'] && $form['end_date'] < $now): ?>
                        <span class="badge bg-danger mt-2">已截止</span>
                    <?php endif; ?>
                </div>
                <?php if ($form['is_published'] && (!$form['end_date'] || $form['end_date'] >= $now)): ?>
                <div class="card-footer bg-transparent">
                    <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-pencil"></i> 填寫表單
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="text-center py-4 text-muted d-none" id="no-results">
        <i class="bi bi-search" style="font-size:2rem;"></i>
        <p class="mt-2">找不到符合的表單</p>
    </div>
<?php endif; ?>

<?php else: ?>
<!-- ════════════════ 按讚的表單 ════════════════ -->

<!-- 即時搜尋 -->
<div class="mb-3 position-relative">
    <i class="bi bi-search position-absolute text-muted" style="left:.75rem;top:50%;transform:translateY(-50%);pointer-events:none;font-size:.9rem;"></i>
    <input type="text" id="search-input" class="form-control ps-4" placeholder="搜尋表單標題...">
</div>

<?php if (mysqli_num_rows($liked_forms) === 0): ?>
    <div class="text-center py-5 text-muted" id="tab-empty">
        <i class="bi bi-heart" style="font-size:3rem;"></i>
        <p class="mt-3">尚未對任何表單按讚</p>
        <a href="/index.php" class="btn btn-outline-primary btn-sm">去探索表單</a>
    </div>
<?php else: ?>
    <div class="row g-3" id="cards-container">
        <?php while ($form = mysqli_fetch_assoc($liked_forms)): ?>
        <div class="col-md-6 form-card-item" id="lk-col-<?= $form['id'] ?>"
             data-search="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>">
            <div class="card h-100">
                <div class="card-body">
                    <!-- 作者 + 三連點 -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <?= renderAvatarDropdown($form['author'], $form['author_avatar'] ?? null, $form['user_id'], 28, $uid) ?>
                            <a href="/profile.php?id=<?= $form['user_id'] ?>" class="small text-muted text-decoration-none">
                                <?= htmlspecialchars($form['author']) ?>
                            </a>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($form['user_id'] == $uid): ?>
                                <li>
                                    <a class="dropdown-item" href="/member/edit_form.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-pencil me-2"></i> 編輯表單
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <?php if ($form['show_stats'] ?? 1): ?>
                                <li>
                                    <a class="dropdown-item" href="/member/form_responses.php?id=<?= $form['id'] ?>">
                                        <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <button class="dropdown-item btn-preview-form"
                                            data-form-id="<?= $form['id'] ?>"
                                            data-form-title="<?= htmlspecialchars($form['title']) ?>">
                                        <i class="bi bi-eye me-2"></i> 預覽表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item btn-report-my-form" data-form-id="<?= $form['id'] ?>">
                                        <i class="bi bi-flag me-2"></i> 檢舉表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger btn-unlike" data-id="<?= $form['id'] ?>">
                                        <i class="bi bi-heart-break me-2"></i> 取消按讚
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($form['title']) ?></h6>
                    <?php if ($form['description']): ?>
                        <p class="text-muted small mb-2"><?= htmlspecialchars(mb_substr(strip_tags($form['description']), 0, 80)) ?><?= mb_strlen(strip_tags($form['description'])) > 80 ? '…' : '' ?></p>
                    <?php endif; ?>

                    <div class="text-muted small mt-2">
                        <i class="bi bi-people"></i> <?= $form['response_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-heart"></i> <?= $form['like_count'] ?>
                        &nbsp;·&nbsp;
                        <i class="bi bi-bookmark"></i> <?= $form['bookmark_count'] ?>
                    </div>

                    <?php if (!$form['is_published']): ?>
                        <span class="badge bg-secondary mt-2">未發布</span>
                    <?php elseif ($form['end_date'] && $form['end_date'] < $now): ?>
                        <span class="badge bg-danger mt-2">已截止</span>
                    <?php endif; ?>
                </div>
                <?php if ($form['is_published'] && (!$form['end_date'] || $form['end_date'] >= $now)): ?>
                <div class="card-footer bg-transparent">
                    <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-pencil"></i> 填寫表單
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="text-center py-4 text-muted d-none" id="no-results">
        <i class="bi bi-search" style="font-size:2rem;"></i>
        <p class="mt-2">找不到符合的表單</p>
    </div>
<?php endif; ?>

<?php endif; ?>

<!-- 預覽表單 Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold">
                    <i class="bi bi-eye text-success me-1"></i> <span id="preview-form-title"></span>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="preview-body">
                <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
            </div>
        </div>
    </div>
</div>

<!-- 檢舉 Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-flag"></i> 檢舉表單</h6>
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
// ── 即時搜尋（客戶端過濾，不需按鈕）──
$('#search-input').on('input', function () {
    const q = $(this).val().toLowerCase().trim();
    let visible = 0;
    $('.form-card-item').each(function () {
        const match = !q || $(this).data('search').includes(q);
        $(this).toggle(match);
        if (match) visible++;
    });
    $('#no-results').toggleClass('d-none', visible > 0 || !q);
});

// ── 預覽表單 ──
const _previewModalEl = document.getElementById('previewModal');
$(document).on('click', '.btn-preview-form', function () {
    const formId    = $(this).data('form-id');
    const formTitle = $(this).data('form-title');
    $('#preview-form-title').text(formTitle);
    $('#preview-body').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
    bootstrap.Modal.getOrCreateInstance(_previewModalEl).show();
    $.get('/api/form_preview_content.php', { id: formId }, function (html) {
        $('#preview-body').html(html);
    }).fail(function () {
        $('#preview-body').html('<div class="alert alert-danger">載入失敗，請稍後再試</div>');
    });
});
_previewModalEl.addEventListener('hidden.bs.modal', function () {
    $('#preview-body').html('');
});

// ── 刪除表單（AJAX，與首頁一致）──
$(document).on('click', '.btn-delete-my-form', function () {
    const formId = $(this).data('form-id');
    window.cuteConfirm({ icon: '📋', msg: '確定要刪除這個表單嗎？', sub: '此操作無法復原。' }, function (ok) {
        if (!ok) return;
        $.post('/api/delete_form.php', { form_id: formId }, function (res) {
            if (res.success) {
                $('#cr-col-' + formId).fadeOut(300, function () { $(this).remove(); });
            } else {
                alert(res.message || '刪除失敗');
            }
        }, 'json');
    });
});

// ── 取消收藏 ──
$(document).on('click', '.btn-remove-bookmark', function () {
    const formId = $(this).data('id');
    $.post('/api/bookmark.php', { form_id: formId }, function (res) {
        if (res.success && !res.bookmarked) {
            $('#bk-col-' + formId).fadeOut(300, function () { $(this).remove(); });
        }
    }, 'json');
});

// ── 取消按讚 ──
$(document).on('click', '.btn-unlike', function () {
    const formId = $(this).data('id');
    $.post('/api/like.php', { form_id: formId }, function (res) {
        if (res.success && !res.liked) {
            $('#lk-col-' + formId).fadeOut(300, function () { $(this).remove(); });
        }
    }, 'json');
});

// ── 檢舉 ──
let reportFormId = 0;
$(document).on('click', '.btn-report-my-form', function () {
    reportFormId = $(this).data('form-id');
    new bootstrap.Modal(document.getElementById('reportModal')).show();
});
$(document).on('click', '.btn-report-reason', function () {
    const reason = $(this).data('reason');
    const modal  = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
    $.post('/api/report.php', { type: 'form', target_id: reportFormId, reason: reason }, function (res) {
        if (modal) modal.hide();
        alert(res.message);
    }, 'json').fail(function () {
        if (modal) modal.hide();
        alert('網路錯誤，請稍後再試');
    });
});
</script>

<!-- 新增表單 FAB -->
<a href="/member/create_form.php" class="fab-btn" title="新增表單">
    <i class="bi bi-plus-lg"></i>
</a>

<?php require_once '../config/footer.php'; ?>
