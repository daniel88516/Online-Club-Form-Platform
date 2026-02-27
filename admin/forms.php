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

// 搜尋
$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE f.title LIKE '%$search%'" : '';

$forms = mysqli_query($conn, "
    SELECT f.id, f.title, f.is_published, f.created_at,
           u.username AS author,
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
                        <td><?= htmlspecialchars($form['author']) ?></td>
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
                            <div class="d-flex justify-content-center gap-1">
                                <a href="/member/form_responses.php?id=<?= $form['id'] ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-bar-chart"></i>
                                </a>
                                <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-outline-success btn-sm" target="_blank">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此表單？')">
                                    <input type="hidden" name="delete_id" value="<?= $form['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
