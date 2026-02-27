<?php
$pageTitle = '填寫表單';
require_once 'config/session.php';
require_once 'config/db.php';

$id  = intval($_GET['id'] ?? 0);
$now = date('Y-m-d H:i:s');

// 取得表單
$stmt = mysqli_prepare($conn, "SELECT f.*, u.username AS author FROM forms f JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.is_published = 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) {
    require_once 'config/header.php';
    echo '<div class="alert alert-danger">表單不存在或尚未發布</div>';
    require_once 'config/footer.php';
    exit();
}

// 檢查截止時間
if ($form['end_date'] && $form['end_date'] < $now) {
    require_once 'config/header.php';
    echo '<div class="alert alert-warning"><i class="bi bi-clock"></i> 此表單已截止填答</div>';
    require_once 'config/footer.php';
    exit();
}

// 檢查是否重複填答
if (!$form['allow_multiple'] && isLoggedIn()) {
    $check = mysqli_prepare($conn, "SELECT id FROM form_responses WHERE form_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($check, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);
    if (mysqli_stmt_num_rows($check) > 0) {
        require_once 'config/header.php';
        echo '<div class="alert alert-info"><i class="bi bi-check-circle"></i> 您已填答過此表單</div>';
        require_once 'config/footer.php';
        exit();
    }
}

// 取得欄位
$fields_result = mysqli_query($conn, "SELECT * FROM form_fields WHERE form_id = $id ORDER BY order_num");
$fields = [];
while ($f = mysqli_fetch_assoc($fields_result)) {
    $f['options'] = $f['options'] ? json_decode($f['options'], true) : [];
    $fields[] = $f;
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $answers = $_POST['answers'] ?? [];

    // 檢查必填
    $missing = false;
    foreach ($fields as $field) {
        if ($field['is_required']) {
            $ans = $answers[$field['id']] ?? '';
            if (is_array($ans)) $ans = implode('', $ans);
            if (trim($ans) === '') { $missing = true; break; }
        }
    }

    if ($missing) {
        $error = '請填寫所有必填欄位';
    } else {
        // 插入填答記錄
        $stmt = mysqli_prepare($conn, "INSERT INTO form_responses (form_id, user_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $response_id = mysqli_insert_id($conn);

        // 插入答案
        foreach ($fields as $field) {
            $ans = $answers[$field['id']] ?? '';
            if (is_array($ans)) $ans = implode(', ', $ans);
            $field_id = $field['id'];
            $stmt2 = mysqli_prepare($conn, "INSERT INTO response_answers (response_id, field_id, answer) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, 'iis', $response_id, $field_id, $ans);
            mysqli_stmt_execute($stmt2);
        }
        $success = true;
    }
}

require_once 'config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <?php if ($success): ?>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-check-circle text-success" style="font-size:4rem;"></i>
                    <h4 class="mt-3 fw-bold">填答完成！</h4>
                    <p class="text-muted">感謝您的填答</p>
                    <a href="/index.php" class="btn btn-primary mt-2">返回首頁</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="fw-bold"><?= htmlspecialchars($form['title']) ?></h3>
                    <?php if ($form['description']): ?>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($form['description'])) ?></p>
                    <?php endif; ?>
                    <small class="text-muted">
                        <i class="bi bi-person"></i> 建立者：<?= htmlspecialchars($form['author']) ?>
                        <?php if ($form['end_date']): ?>
                            &nbsp;|&nbsp;<i class="bi bi-clock text-warning"></i> 截止：<?= date('Y/m/d H:i', strtotime($form['end_date'])) ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="fillForm">
                <?php foreach ($fields as $field): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label fw-semibold">
                            <?= htmlspecialchars($field['label']) ?>
                            <?php if ($field['is_required']): ?><span class="text-danger"> *</span><?php endif; ?>
                        </label>

                        <?php if ($field['field_type'] === 'short_text'): ?>
                            <input type="text" name="answers[<?= $field['id'] ?>]" class="form-control"
                                   <?= $field['is_required'] ? 'required' : '' ?>>

                        <?php elseif ($field['field_type'] === 'long_text'): ?>
                            <textarea name="answers[<?= $field['id'] ?>]" class="form-control" rows="4"
                                      <?= $field['is_required'] ? 'required' : '' ?>></textarea>

                        <?php elseif ($field['field_type'] === 'radio'): ?>
                            <?php foreach ($field['options'] as $opt): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                           name="answers[<?= $field['id'] ?>]"
                                           value="<?= htmlspecialchars($opt) ?>"
                                           <?= $field['is_required'] ? 'required' : '' ?>>
                                    <label class="form-check-label"><?= htmlspecialchars($opt) ?></label>
                                </div>
                            <?php endforeach; ?>

                        <?php elseif ($field['field_type'] === 'checkbox'): ?>
                            <?php foreach ($field['options'] as $opt): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="answers[<?= $field['id'] ?>][]"
                                           value="<?= htmlspecialchars($opt) ?>">
                                    <label class="form-check-label"><?= htmlspecialchars($opt) ?></label>
                                </div>
                            <?php endforeach; ?>

                        <?php elseif ($field['field_type'] === 'dropdown'): ?>
                            <select name="answers[<?= $field['id'] ?>]" class="form-select"
                                    <?= $field['is_required'] ? 'required' : '' ?>>
                                <option value="">-- 請選擇 --</option>
                                <?php foreach ($field['options'] as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($field['field_type'] === 'date'): ?>
                            <input type="date" name="answers[<?= $field['id'] ?>]" class="form-control"
                                   <?= $field['is_required'] ? 'required' : '' ?>>

                        <?php elseif ($field['field_type'] === 'time'): ?>
                            <input type="time" name="answers[<?= $field['id'] ?>]" class="form-control"
                                   <?= $field['is_required'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send"></i> 提交
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'config/footer.php'; ?>
