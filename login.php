<?php
$pageTitle = '登入';
require_once 'config/session.php';
require_once 'config/db.php';

if (isLoggedIn()) {
    header('Location: ' . APP_BASE . '/index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '請填寫帳號和密碼';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, role, avatar FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];

            header('Location: ' . APP_BASE . '/index.php');
            exit();
        } else {
            $error = '帳號或密碼錯誤';
        }
    }
}

require_once 'config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card mt-4">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> 會員登入</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">帳號</label>
                        <input type="text" name="username" class="form-control" placeholder="請輸入帳號"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">密碼</label>
                        <input type="password" name="password" class="form-control" placeholder="請輸入密碼" required>
                    </div>
                    <button type="submit" class="btn btn-glow-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> 登入
                    </button>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                還沒有帳號？<a href="<?= REL_BASE ?>register.php">立即註冊</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'config/footer.php'; ?>
