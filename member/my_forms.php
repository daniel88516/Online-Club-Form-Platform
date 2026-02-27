<?php
$pageTitle = '我的表單';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

// 處理刪除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM forms WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $del_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    header('Location: /member/my_forms.php?msg=deleted');
    exit();
}

// 處理發布/取消發布
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $toggle_id = intval($_POST['toggle_id']);
    $stmt = mysqli_prepare($conn, "UPDATE forms SET is_published = !is_published WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $toggle_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    header('Location: /member/my_forms.php');
    exit();
}

// 搜尋與排序
$search  = trim($_GET['search'] ?? '');
$sort    = in_array($_GET['sort'] ?? '', ['title', 'created_at', 'response_count']) ? $_GET['sort'] : 'created_at';
$order   = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$next_order = $order === 'ASC' ? 'desc' : 'asc';

$where = "WHERE f.user_id = ?";
$params = [$_SESSION['user_id']];
$types = 'i';

if (!empty($search)) {
    $where .= " AND f.title LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

$sql = "SELECT f.id, f.title, f.is_published, f.created_at, f.end_date,
               COUNT(fr.id) AS response_count
        FROM forms f
        LEFT JOIN form_responses fr ON f.id = fr.form_id
        $where
        GROUP BY f.id
        ORDER BY $sort $order";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$forms = mysqli_stmt_get_result($stmt);

$msg = $_GET['msg'] ?? '';

require_once '../config/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-journal-text"></i> 我的表單</h4>
    </div>
    <div class="col-auto">
        <a href="/member/create_form.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> 新增表單
        </a>
    </div>
</div>

<?php if ($msg === 'created'): ?>
    <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle"></i> 表單建立成功！</div>
<?php elseif ($msg === 'deleted'): ?>
    <div class="alert alert-warning alert-dismissible"><i class="bi bi-trash"></i> 表單已刪除</div>
<?php endif; ?>

<!-- 搜尋 -->
<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="搜尋表單標題..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search): ?>
            <a href="/member/my_forms.php" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>
                        <a href="?sort=title&order=<?= $next_order ?>&search=<?= urlencode($search) ?>" class="text-decoration-none text-dark">
                            標題 <i class="bi bi-arrow-down-up text-muted small"></i>
                        </a>
                    </th>
                    <th>狀態</th>
                    <th>
                        <a href="?sort=response_count&order=<?= $next_order ?>&search=<?= urlencode($search) ?>" class="text-decoration-none text-dark">
                            填答數 <i class="bi bi-arrow-down-up text-muted small"></i>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=created_at&order=<?= $next_order ?>&search=<?= urlencode($search) ?>" class="text-decoration-none text-dark">
                            建立時間 <i class="bi bi-arrow-down-up text-muted small"></i>
                        </a>
                    </th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($forms) === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <?= $search ? '找不到符合的表單' : '還沒有建立任何表單' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($form = mysqli_fetch_assoc($forms)): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($form['title']) ?></td>
                        <td>
                            <?php if ($form['is_published']): ?>
                                <span class="badge bg-success">已發布</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">草稿</span>
                            <?php endif; ?>
                        </td>
                        <td><i class="bi bi-people"></i> <?= $form['response_count'] ?></td>
                        <td class="small text-muted"><?= date('Y/m/d H:i', strtotime($form['created_at'])) ?></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                <a href="/member/edit_form.php?id=<?= $form['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/member/form_responses.php?id=<?= $form['id'] ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-bar-chart"></i>
                                </a>
                                <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-outline-success btn-sm" target="_blank">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <!-- 發布/取消 -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="toggle_id" value="<?= $form['id'] ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-sm"
                                            title="<?= $form['is_published'] ? '取消發布' : '發布' ?>">
                                        <i class="bi bi-<?= $form['is_published'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                </form>
                                <!-- 刪除 -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('確定要刪除這個表單嗎？')">
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
