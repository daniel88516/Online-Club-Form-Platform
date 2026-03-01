<?php
$pageTitle = '表單管理';
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$success = '';

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
    header('Location: /admin/forms.php' . ($search ? '?search=' . urlencode($search) : ''));
    exit();
}

// 搜尋
$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE f.title LIKE '%$search%'" : '';

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
        <a href="/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="搜尋表單標題..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search): ?>
            <a href="/admin/forms.php" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>標題</th>
                    <th>建立者</th>
                    <th>狀態</th>
                    <th>填答數</th>
                    <th>建立時間</th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($forms) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">沒有表單</td></tr>
                <?php else: ?>
                    <?php while ($form = mysqli_fetch_assoc($forms)): ?>
                    <tr>
                        <td><?= $form['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($form['title']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?= renderAvatar($form['author'], $form['author_avatar'], 28) ?>
                                <?= htmlspecialchars($form['author']) ?>
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
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if ($form['user_id'] == $_SESSION['user_id']): ?>
                                    <li>
                                        <a class="dropdown-item" href="/member/edit_form.php?id=<?= $form['id'] ?>">
                                            <i class="bi bi-pencil text-primary me-2"></i> 編輯表單
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <a class="dropdown-item" href="/member/form_responses.php?id=<?= $form['id'] ?>">
                                            <i class="bi bi-bar-chart text-info me-2"></i> 查看統計
                                        </a>
                                    </li>
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
</script>

<?php require_once '../config/footer.php'; ?>
