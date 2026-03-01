<?php
$pageTitle = '會員管理';
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$error = '';
$success = '';

// 刪除會員
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);
    if ($del_id == $_SESSION['user_id']) {
        $error = '不能刪除自己的帳號';
    } else {
        mysqli_query($conn, "DELETE FROM users WHERE id = $del_id");
        $success = '會員已刪除';
    }
}

// 修改角色
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role_id'])) {
    $uid  = intval($_POST['change_role_id']);
    $role = $_POST['role'] === 'admin' ? 'admin' : 'member';
    if ($uid == $_SESSION['user_id']) {
        $error = '不能修改自己的角色';
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $role, $uid);
        mysqli_stmt_execute($stmt);
        $success = '角色已更新';
    }
}

// 修改群組
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_group_id'])) {
    $uid      = intval($_POST['change_group_id']);
    $group_id = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;
    $stmt = mysqli_prepare($conn, "UPDATE users SET group_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $group_id, $uid);
    mysqli_stmt_execute($stmt);
    $success = '群組已更新';
}

// 搜尋
$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE username LIKE '%$search%' OR email LIKE '%$search%'" : '';
$users  = mysqli_query($conn, "SELECT u.*, g.name AS group_name FROM users u LEFT JOIN `groups` g ON u.group_id = g.id $where ORDER BY u.id");
$groups = mysqli_query($conn, "SELECT id, name FROM `groups` ORDER BY id");
$groups_arr = [];
while ($g = mysqli_fetch_assoc($groups)) $groups_arr[] = $g;

require_once '../config/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-people"></i> 會員管理</h4>
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

<form method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="搜尋帳號或 Email..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search): ?>
            <a href="/admin/members.php" class="btn btn-outline-danger"><i class="bi bi-x"></i></a>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>帳號</th>
                    <th>Email</th>
                    <th>群組</th>
                    <th>角色</th>
                    <th>建立時間</th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = mysqli_fetch_assoc($users)): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= renderAvatar($user['username'], $user['avatar'] ?? null, 28) ?>
                            <span class="fw-semibold"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="change_group_id" value="<?= $user['id'] ?>">
                            <select name="group_id" class="form-select form-select-sm" style="min-width:90px;">
                                <option value="">無</option>
                                <?php foreach ($groups_arr as $g): ?>
                                    <option value="<?= $g['id'] ?>" <?= $user['group_id'] == $g['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($g['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check"></i></button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="change_role_id" value="<?= $user['id'] ?>">
                            <select name="role" class="form-select form-select-sm" style="min-width:90px;">
                                <option value="member" <?= $user['role'] === 'member' ? 'selected' : '' ?>>會員</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>管理者</option>
                            </select>
                            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check"></i></button>
                        </form>
                    </td>
                    <td class="small text-muted"><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                    <td class="text-center">
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" class="d-inline"
                              data-cute-confirm="確定刪除此會員？"
                              data-cute-icon="👤">
                            <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted small">（自己）</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
