<?php
$pageTitle = '註冊';
require_once 'config/session.php';
require_once 'config/db.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $group_id = intval($_POST['group_id'] ?? 1);

    // 驗證
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $error = '請填寫所有欄位';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '帳號長度需為 3-20 字元';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email 格式不正確';
    } elseif (strlen($password) < 6) {
        $error = '密碼長度至少 6 字元';
    } elseif ($password !== $confirm) {
        $error = '兩次密碼不一致';
    } else {
        // 檢查帳號是否存在
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = '帳號或 Email 已被使用';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role, group_id) VALUES (?, ?, ?, 'member', ?)");
            mysqli_stmt_bind_param($stmt2, 'sssi', $username, $email, $hashed, $group_id);

            if (mysqli_stmt_execute($stmt2)) {
                $success = '註冊成功！請登入';
            } else {
                $error = '註冊失敗，請稍後再試';
            }
        }
    }
}

// 取得群組列表
$groups = mysqli_query($conn, "SELECT id, name FROM `groups` ORDER BY id");

require_once 'config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card mt-4">
            <div class="card-header bg-success text-white text-center py-3">
                <h4 class="mb-0"><i class="bi bi-person-plus"></i> 會員註冊</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <a href="/login.php" class="alert-link">點此登入</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">帳號 <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="username" class="form-control"
                               placeholder="3-20 字元" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required minlength="3" maxlength="20">
                        <div id="username-feedback" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control"
                               placeholder="example@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">群組</label>
                        <select name="group_id" class="form-select">
                            <?php while ($g = mysqli_fetch_assoc($groups)): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="至少 6 字元" required minlength="6">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">確認密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                               placeholder="再輸入一次密碼" required>
                        <div id="confirm-feedback" class="form-text"></div>
                    </div>
                    <button type="submit" class="btn btn-glow-primary w-100">
                        <i class="bi bi-person-check"></i> 註冊
                    </button>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                已有帳號？<a href="/login.php">立即登入</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    // Ajax 即時檢查帳號
    let usernameTimer;
    $('#username').on('input', function () {
        clearTimeout(usernameTimer);
        const val = $(this).val().trim();
        if (val.length < 3) {
            $('#username-feedback').text('').removeClass('text-success text-danger');
            return;
        }
        usernameTimer = setTimeout(function () {
            $.ajax({
                url: '/api/check_username.php',
                type: 'POST',
                data: { username: val },
                success: function (res) {
                    if (res.exists) {
                        $('#username-feedback').text('此帳號已被使用').removeClass('text-success').addClass('text-danger');
                    } else {
                        $('#username-feedback').text('帳號可使用').removeClass('text-danger').addClass('text-success');
                    }
                }
            });
        }, 500);
    });

    // 即時確認密碼比對
    $('#confirm_password').on('input', function () {
        if ($(this).val() !== $('#password').val()) {
            $('#confirm-feedback').text('兩次密碼不一致').addClass('text-danger').removeClass('text-success');
        } else {
            $('#confirm-feedback').text('密碼一致').removeClass('text-danger').addClass('text-success');
        }
    });

    // 表單送出前驗證
    $('#registerForm').on('submit', function (e) {
        if ($('#confirm_password').val() !== $('#password').val()) {
            e.preventDefault();
            alert('兩次密碼不一致，請重新確認');
        }
    });
});
</script>

<?php require_once 'config/footer.php'; ?>
