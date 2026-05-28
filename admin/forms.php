<?php
$pageTitle = '表單管理';
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/_admin_search.php';
requireAdmin();

$success = '';
$search = trim($_GET['search'] ?? '');

// 刪除表單
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    mysqli_query($conn, "DELETE FROM forms WHERE id = $del_id");
    $success = '表單已刪除';
}

// 切換發布狀態
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggle_id = intval($_POST['toggle_id']);
    mysqli_query($conn, "UPDATE forms SET is_published = !is_published WHERE id = $toggle_id");
    header('Location: ' . APP_BASE . '/admin/forms.php' . ($search ? '?search=' . urlencode($search) : ''));
    exit();
}

// 搜尋
$where  = $search ? "WHERE f.title LIKE '%$search%' OR u.username LIKE '%$search%'" : '';

$forms = mysqli_query($conn, "
    SELECT f.id, f.title, f.is_published, f.created_at, f.user_id,
           u.username AS author, u.avatar AS author_avatar,
           COUNT(fr.id) AS response_count
    FROM forms f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN form_responses fr ON f.id = fr.form_id
    $where
    GROUP BY f.id
    ORDER BY f.created_at DESC
");

require_once '../config/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-file-earmark-text"></i> 表單管理</h4>
    </div>
    <div class="col-auto">
        <a href="<?= REL_BASE ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php renderAdminSearchBar('search', $search, '搜尋表單標題或建立者...', REL_BASE . 'admin/forms.php'); ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th class="sortable-th" data-col="id">#<span class="sort-icon"></span></th>
                    <th class="sortable-th" data-col="title">標題<span class="sort-icon"></span></th>
                    <th>建立者</th>
                    <th class="sortable-th" data-col="status">狀態<span class="sort-icon"></span></th>
                    <th class="sortable-th" data-col="resp">填答數<span class="sort-icon"></span></th>
                    <th class="sortable-th desc" data-col="date">建立時間<span class="sort-icon"></span></th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($forms) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">沒有表單</td></tr>
                <?php else: ?>
                    <?php while ($form = mysqli_fetch_assoc($forms)): ?>
                    <tr data-id="<?= $form['id'] ?>"
                        data-admin-search="<?= htmlspecialchars(mb_strtolower($form['title'] . ' ' . $form['author'])) ?>"
                        data-title="<?= htmlspecialchars(mb_strtolower($form['title'])) ?>"
                        data-status="<?= $form['is_published'] ?>"
                        data-resp="<?= $form['response_count'] ?>"
                        data-date="<?= strtotime($form['created_at']) ?>">
                        <td><?= $form['id'] ?></td>
                        <td class="fw-semibold admin-search-target"><?= adminSearchHighlight($form['title'], $search) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?= renderAvatar($form['author'], $form['author_avatar'], 28) ?>
                                <span class="admin-search-target"><?= adminSearchHighlight($form['author'], $search) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($form['is_published']): ?>
                                <span class="badge bg-success">已發布</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">草稿</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $form['response_count'] ?></td>
                        <td class="small text-muted"><?= date('Y/m/d', strtotime($form['created_at'])) ?></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($form['user_id'] == $_SESSION['user_id']): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?= REL_BASE ?>member/edit_form.php?id=<?= $form['id'] ?>">
                                            <i class="bi bi-pencil text-primary me-2"></i> 編輯表單
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($form['user_id'] == $_SESSION['user_id']): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <li>
                                        <a class="dropdown-item" href="<?= REL_BASE ?>member/form_responses.php?id=<?= $form['id'] ?>&return_to=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                            <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item btn-preview-form"
                                                data-form-id="<?= $form['id'] ?>"
                                                data-form-title="<?= htmlspecialchars($form['title']) ?>">
                                            <i class="bi bi-eye text-success me-2"></i> 預覽表單
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" class="d-block m-0">
                                            <input type="hidden" name="toggle_id" value="<?= $form['id'] ?>">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-<?= $form['is_published'] ? 'pause-circle text-warning' : 'play-circle text-warning' ?> me-2"></i>
                                                <?= $form['is_published'] ? '取消發布' : '發布表單' ?>
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" class="d-block m-0"
                                              data-cute-confirm="確定要刪除這個表單嗎？"
                                              data-cute-icon="📋">
                                            <input type="hidden" name="delete_id" value="<?= $form['id'] ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i> 刪除表單
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 預覽表單 Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="previewModalLabel">
                    <i class="bi bi-eye text-success me-1"></i> <span id="preview-form-title"></span>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="preview-body">
                <div class="text-center py-4">
                    <span class="spinner-border text-primary"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── 表格欄位排序 ──
(function () {
    const tbody = document.querySelector('table tbody');
    document.querySelectorAll('.sortable-th').forEach(function (th) {
        th.addEventListener('click', function () {
            const col  = th.dataset.col;
            const isDesc = th.classList.contains('desc');
            // 清除其他欄位狀態
            document.querySelectorAll('.sortable-th').forEach(function (t) {
                t.classList.remove('asc', 'desc');
            });
            th.classList.add(isDesc ? 'asc' : 'desc');
            const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
            rows.sort(function (a, b) {
                let va = a.dataset[col] || '';
                let vb = b.dataset[col] || '';
                // 數字欄位
                if (['id','status','resp','date'].includes(col)) {
                    va = parseFloat(va) || 0;
                    vb = parseFloat(vb) || 0;
                    return isDesc ? va - vb : vb - va;
                }
                // 文字欄位
                return isDesc
                    ? va.localeCompare(vb, 'zh-Hant')
                    : vb.localeCompare(va, 'zh-Hant');
            });
            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });
})();

const _previewModalEl = document.getElementById('previewModal');
$(document).on('click', '.btn-preview-form', function () {
    const formId    = $(this).data('form-id');
    const formTitle = $(this).data('form-title');
    $('#preview-form-title').text(formTitle);
    $('#preview-body').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
    bootstrap.Modal.getOrCreateInstance(_previewModalEl).show();
    $.get('<?= REL_BASE ?>api/form_preview_content.php', { id: formId }, function (html) {
        $('#preview-body').html(html);
    }).fail(function () {
        $('#preview-body').html('<div class="alert alert-danger">載入失敗，請稍後再試</div>');
    });
});
_previewModalEl.addEventListener('hidden.bs.modal', function () {
    $('#preview-body').html('');
});
</script>

<?php renderAdminSearchScript(); ?>
<?php require_once '../config/footer.php'; ?>
