<?php
$pageTitle = '群組管理';
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$error   = '';
$success = '';

// 新增群組
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_name'])) {
    $name = trim($_POST['add_name']);
    $desc = trim($_POST['add_desc'] ?? '');
    if (empty($name)) {
        $error = '請填寫群組名稱';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (name, description) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $name, $desc);
        mysqli_stmt_execute($stmt);
        $success = '群組已新增';
    }
}

// 刪除群組
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    mysqli_query($conn, "DELETE FROM `groups` WHERE id = $del_id");
    $success = '群組已刪除';
}

// 修改群組
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $eid  = intval($_POST['edit_id']);
    $name = trim($_POST['edit_name'] ?? '');
    $desc = trim($_POST['edit_desc'] ?? '');
    if (!empty($name)) {
        $stmt = mysqli_prepare($conn, "UPDATE `groups` SET name = ?, description = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $name, $desc, $eid);
        mysqli_stmt_execute($stmt);
        $success = '群組已更新';
    }
}

$groups = mysqli_query($conn, "SELECT g.*, COUNT(u.id) AS member_count FROM `groups` g LEFT JOIN users u ON g.id = u.group_id GROUP BY g.id ORDER BY g.id");

require_once '../config/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-diagram-3"></i> 群組管理</h4>
    </div>
    <div class="col-auto">
        <a href="/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- 新增群組 -->
<div class="card mb-4">
    <div class="card-header bg-success text-white"><i class="bi bi-plus-circle"></i> 新增群組</div>
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">群組名稱 <span class="text-danger">*</span></label>
                <input type="text" name="add_name" class="form-control" placeholder="輸入群組名稱" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">說明</label>
                <input type="text" name="add_desc" class="form-control" placeholder="群組說明（選填）">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-plus"></i> 新增
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 群組列表 -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>名稱</th>
                    <th>說明</th>
                    <th>會員數</th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($g = mysqli_fetch_assoc($groups)): ?>
                <tr>
                    <td><?= $g['id'] ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($g['name']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($g['description']) ?></td>
                    <td><span class="badge bg-primary"><?= $g['member_count'] ?></span></td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center gap-1">
                            <!-- 編輯 Modal 觸發 -->
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    data-id="<?= $g['id'] ?>"
                                    data-name="<?= htmlspecialchars($g['name']) ?>"
                                    data-desc="<?= htmlspecialchars($g['description']) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- 刪除 -->
                            <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此群組？')">
                                <input type="hidden" name="delete_id" value="<?= $g['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 編輯 Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">編輯群組</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">群組名稱</label>
                        <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">說明</label>
                        <input type="text" name="edit_desc" id="edit_desc" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">儲存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_id').value   = btn.dataset.id;
    document.getElementById('edit_name').value = btn.dataset.name;
    document.getElementById('edit_desc').value = btn.dataset.desc;
});
</script>

<?php require_once '../config/footer.php'; ?>
