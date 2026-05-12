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
    $group_id = 1; // 預設加入「一般」群組

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
                        <a href="<?= REL_BASE ?>login.php" class="alert-link">點此登入</a>
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
                        <input type="text" name="email" id="email" class="form-control"
                               placeholder="example@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        <div id="email-feedback" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="至少 6 字元" required minlength="6">
                        <div id="password-feedback" class="form-text"></div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">確認密碼 <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                               placeholder="再輸入一次密碼" required>
                        <div id="confirm-feedback" class="form-text"></div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-person-check"></i> 註冊
                    </button>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                已有帳號？<a href="<?= REL_BASE ?>login.php">立即登入</a>
            </div>
        </div>
    </div>
</div>

<script>
// 輔助函式
function setValid($input, $feedback, msg) {
    $input.removeClass('is-invalid').addClass('is-valid');
    $feedback.text(msg).removeClass('text-danger').addClass('text-success');
}
function setInvalid($input, $feedback, msg) {
    $input.removeClass('is-valid').addClass('is-invalid');
    $feedback.text(msg).removeClass('text-success').addClass('text-danger');
}
function clearState($input, $feedback) {
    $input.removeClass('is-valid is-invalid');
    $feedback.text('').removeClass('text-success text-danger');
}

$(document).ready(function () {

    // 1. 帳號 - focus 清除 / blur 驗證 + Ajax 檢查
    $('#username').on('focus', function () {
        clearState($(this), $('#username-feedback'));
    }).on('blur', function () {
        const val = $(this).val().trim();
        const $el = $(this);
        if (val.length < 3 || val.length > 20) {
            setInvalid($el, $('#username-feedback'), '帳號長度需為 3-20 字元');
            return;
        }
        $.ajax({
            url: '<?= REL_BASE ?>api/check_username.php',
            type: 'POST',
            data: { username: val },
            success: function (res) {
                if (res.exists) {
                    setInvalid($el, $('#username-feedback'), '此帳號已被使用');
                } else {
                    setValid($el, $('#username-feedback'), '帳號可使用');
                }
            },
            error: function () {
                setInvalid($el, $('#username-feedback'), '檢查失敗，請稍後再試');
            }
        });
    });

    // 2. Email - focus 清除 / blur 驗證 + Ajax 檢查
    $('#email').on('focus', function () {
        clearState($(this), $('#email-feedback'));
    }).on('blur', function () {
        const val = $(this).val().trim();
        const $el = $(this);
        if (val.length === 0) {
            clearState($el, $('#email-feedback'));
            return;
        }
        $.ajax({
            url: '<?= REL_BASE ?>api/check_email.php',
            type: 'POST',
            data: { email: val },
            success: function (res) {
                if (!res.valid) {
                    setInvalid($el, $('#email-feedback'), 'Email 格式不正確');
                } else if (res.exists) {
                    setInvalid($el, $('#email-feedback'), '此 Email 已被使用');
                } else {
                    setValid($el, $('#email-feedback'), 'Email 可使用');
                }
            },
            error: function () {
                setInvalid($el, $('#email-feedback'), '檢查失敗，請稍後再試');
            }
        });
    });

    // 3. 密碼 - focus 清除 / input 即時長度驗證 (is-valid/is-invalid)
    $('#password').on('focus', function () {
        clearState($(this), $('#password-feedback'));
    }).on('input', function () {
        const val = $(this).val();
        const $el = $(this);
        if (val.length === 0) {
            clearState($el, $('#password-feedback'));
        } else if (val.length < 6) {
            setInvalid($el, $('#password-feedback'), '密碼至少需要 6 字元');
        } else {
            setValid($el, $('#password-feedback'), '密碼長度符合');
        }
        if ($('#confirm_password').val().length > 0) {
            $('#confirm_password').trigger('input');
        }
    });

    // 4. 確認密碼 - focus 清除 / input 即時比對 (is-valid/is-invalid)
    $('#confirm_password').on('focus', function () {
        clearState($(this), $('#confirm-feedback'));
    }).on('input', function () {
        const $el = $(this);
        if ($('#password').val().length < 6) {
            setInvalid($el, $('#confirm-feedback'), '請先設定好密碼（至少 6 字元）');
        } else if ($(this).val() !== $('#password').val()) {
            setInvalid($el, $('#confirm-feedback'), '兩次密碼不一致');
        } else {
            setValid($el, $('#confirm-feedback'), '密碼一致');
        }
    });

    // 5. 送出前完整驗證
    $('#registerForm').on('submit', function (e) {
        let valid = true;
        const username = $('#username').val().trim();
        const email    = $('#email').val().trim();
        const password = $('#password').val();
        const confirm  = $('#confirm_password').val();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (username.length < 3 || username.length > 20) {
            setInvalid($('#username'), $('#username-feedback'), '帳號長度需為 3-20 字元');
            valid = false;
        }
        if (!emailRegex.test(email)) {
            setInvalid($('#email'), $('#email-feedback'), 'Email 格式不正確');
            valid = false;
        }
        if (password.length < 6) {
            setInvalid($('#password'), $('#password-feedback'), '密碼至少需要 6 字元');
            valid = false;
        }
        if (password !== confirm) {
            setInvalid($('#confirm_password'), $('#confirm-feedback'), '兩次密碼不一致');
            valid = false;
        }
        if (!valid) e.preventDefault();
    });

});
</script>

<?php require_once 'config/footer.php'; ?>
