<?php
$pageTitle = '填寫表單';
require_once 'config/session.php';
require_once 'config/db.php';

$id      = intval($_GET['id'] ?? 0);
$preview = isset($_GET['preview']);
$now     = date('Y-m-d H:i:s');

$form_back_url = REL_BASE . 'index.php';
$back_source = $_POST['return_to'] ?? $_GET['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($back_source !== '') {
    $parts = parse_url($back_source);
    $current_host = $_SERVER['HTTP_HOST'] ?? '';
    $is_explicit_return = isset($_POST['return_to']) || isset($_GET['return_to']);
    $is_current_form = !$is_explicit_return && (($parts['path'] ?? '') === ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($parts !== false && !$is_current_form) {
        $host_ok = !isset($parts['host']) || strcasecmp($parts['host'], $current_host) === 0;
        $scheme_ok = !isset($parts['scheme']) || in_array(strtolower($parts['scheme']), ['http', 'https'], true);
        if ($host_ok && $scheme_ok) {
            $form_back_url = $back_source;
        }
    }
}
$form_back_attr = htmlspecialchars($form_back_url, ENT_QUOTES);
$form_back_param = urlencode($form_back_url);

// 取得表單（預覽模式允許表單擁有者查看草稿）
if ($preview && isLoggedIn()) {
    $stmt = mysqli_prepare($conn, "SELECT f.*, u.username AS author, u.avatar AS author_avatar FROM forms f JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
} else {
    $stmt = mysqli_prepare($conn, "SELECT f.*, u.username AS author, u.avatar AS author_avatar FROM forms f JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.is_published = 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
}
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) {
    require_once 'config/header.php';
    echo '<div class="alert alert-danger">表單不存在或尚未發布</div>';
    require_once 'config/footer.php';
    exit();
}

// 檢查截止時間（預覽模式跳過）
if (!$preview && $form['end_date'] && $form['end_date'] < $now) {
    require_once 'config/header.php';
    echo '<div class="alert alert-warning"><i class="bi bi-clock"></i> 此表單已截止填答</div>';
    require_once 'config/footer.php';
    exit();
}

// 檢查是否重複填答（預覽模式跳過）
if (!$preview && !$form['allow_multiple'] && isLoggedIn()) {
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
$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;

// 修改模式：預載舊答案
$edit_response_id = intval($_GET['edit_response'] ?? 0);
$prefill = [];
if ($edit_response_id && $user_id) {
    $chkEdit = mysqli_prepare($conn, "SELECT id FROM form_responses WHERE id = ? AND form_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($chkEdit, 'iii', $edit_response_id, $id, $user_id);
    mysqli_stmt_execute($chkEdit);
    if (!mysqli_stmt_get_result($chkEdit)->fetch_assoc()) $edit_response_id = 0;
}
if ($edit_response_id) {
    $paResult = mysqli_query($conn, "SELECT field_id, answer FROM response_answers WHERE response_id = $edit_response_id");
    while ($row = mysqli_fetch_assoc($paResult)) $prefill[$row['field_id']] = $row['answer'];
}

// 社交功能：按讚 / 收藏狀態
$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$like_count     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_likes WHERE form_id = $id"))['cnt'];
$bookmark_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_bookmarks WHERE form_id = $id"))['cnt'];
$comment_count  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_comments WHERE form_id = $id"))['cnt'];
$user_liked     = 0;
$user_bookmarked = 0;
if ($user_id) {
    $lk = mysqli_prepare($conn, "SELECT id FROM form_likes WHERE form_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($lk, 'ii', $id, $user_id);
    mysqli_stmt_execute($lk); mysqli_stmt_store_result($lk);
    $user_liked = mysqli_stmt_num_rows($lk) > 0 ? 1 : 0;

    $bk = mysqli_prepare($conn, "SELECT id FROM form_bookmarks WHERE form_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($bk, 'ii', $id, $user_id);
    mysqli_stmt_execute($bk); mysqli_stmt_store_result($bk);
    $user_bookmarked = mysqli_stmt_num_rows($bk) > 0 ? 1 : 0;
}

// 留言列表
$comments_result = mysqli_query($conn, "
    SELECT fc.id, fc.content, fc.created_at, u.username
    FROM form_comments fc
    JOIN users u ON fc.user_id = u.id
    WHERE fc.form_id = $id
    ORDER BY fc.created_at ASC
");

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
        $edit_id = intval($_POST['edit_response_id'] ?? 0);

        if ($edit_id) {
            // 修改模式：確認這筆 response 屬於此表單與此使用者
            $chk = mysqli_prepare($conn, "SELECT id FROM form_responses WHERE id = ? AND form_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($chk, 'iii', $edit_id, $id, $user_id);
            mysqli_stmt_execute($chk);
            $valid = mysqli_stmt_get_result($chk)->fetch_assoc();
            if ($valid) {
                $response_id = $edit_id;
                $del = mysqli_prepare($conn, "DELETE FROM response_answers WHERE response_id = ?");
                mysqli_stmt_bind_param($del, 'i', $response_id);
                mysqli_stmt_execute($del);
                $upd = mysqli_prepare($conn, "UPDATE form_responses SET submitted_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'i', $response_id);
                mysqli_stmt_execute($upd);
            } else {
                $edit_id = 0;
            }
        }

        if (!$edit_id) {
            $stmt = mysqli_prepare($conn, "INSERT INTO form_responses (form_id, user_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
            mysqli_stmt_execute($stmt);
            $response_id = mysqli_insert_id($conn);
        }

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
?>
<?php if ($preview): ?>
<!DOCTYPE html>
<html lang="zh-TW" id="html-root">
<head>
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            var isDark = t === 'dark' ? true : t === 'light' ? false : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
            if (isDark) { document.documentElement.style.background = '#000'; document.documentElement.style.colorScheme = 'dark'; }
            var c = localStorage.getItem('themeColor');
            if (c && /^#[0-9a-fA-F]{6}$/.test(c)) {
                var n = parseInt(c.slice(1), 16);
                var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
                document.documentElement.style.setProperty('--bs-primary', c);
                document.documentElement.style.setProperty('--bs-primary-rgb', r + ',' + g + ',' + b);
                var bri = (r * 299 + g * 587 + b * 114) / 1000;
                document.documentElement.style.setProperty('--bs-primary-text', bri > 140 ? '#000000' : '#ffffff');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= REL_BASE ?>css/style.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        window.REL_BASE = '<?= REL_BASE ?>';
        window.APP_BASE = '<?= APP_BASE ?>';
        window.assetUrl = function (path) {
            if (!path) return '';
            if (/^(https?:)?\/\//i.test(path) || /^data:/i.test(path)) return path;
            if (path.indexOf('/uploads/') === 0) return window.APP_BASE + path;
            return path;
        };
    </script>
    <script src="<?= REL_BASE ?>js/theme-color.js"></script>
    <script>(function(){ var c = localStorage.getItem('themeColor'); if (c) applyThemeColor(c); })();</script>
</head>
<body class="bg-light">
<div class="container py-3">
<?php else: require_once 'config/header.php'; endif; ?>

<style>
.form-back-shell { position: relative; }
.form-back-float {
    position: absolute;
    top: 10px;
    left: calc(100% + 76px);
    z-index: 2;
    white-space: nowrap;
}
@media (max-width: 991.98px) {
    .form-back-mobile-row {
        display: flex;
        justify-content: flex-end;
    }
    .form-back-float {
        position: static;
        margin-bottom: .75rem;
    }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <?php if ($preview): ?>
        <div class="alert alert-warning py-2 mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-eye-fill"></i>
            <span class="small">預覽模式 — 此頁面為草稿預覽，表單提交功能已停用</span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="form-back-shell">
            <div class="form-back-mobile-row">
                <a href="<?= $form_back_attr ?>" class="btn btn-glow-primary btn-sm form-back-float">
                    <i class="bi bi-arrow-left"></i> &#36820;&#22238;
                </a>
            </div>
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-check-circle text-success" style="font-size:4rem;"></i>
                    <h4 class="mt-3 fw-bold">填答完成！</h4>
                    <p class="text-muted">感謝您的填答</p>
                    <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
                        <a href="<?= REL_BASE ?>form_view.php?id=<?= $id ?>&edit_response=<?= $response_id ?>&return_to=<?= $form_back_param ?>" class="btn btn-glow-red">
                            <i class="bi bi-pencil-square me-1"></i> 修改填答
                        </a>
                        <?php if (!empty($form['show_stats']) || $form['user_id'] == $user_id): ?>
                        <a href="<?= REL_BASE ?>member/form_responses.php?id=<?= $id ?>" class="btn btn-glow-cyan">
                            <i class="bi bi-bar-chart me-1"></i> 查看統計
                        </a>
                        <?php endif; ?>
                        <a href="<?= REL_BASE ?>index.php" class="btn btn-glow-green">
                            <i class="bi bi-house me-1"></i> 回到首頁
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="form-back-shell">
            <div class="form-back-mobile-row">
                <a href="<?= $form_back_attr ?>" class="btn btn-glow-primary btn-sm form-back-float">
                    <i class="bi bi-arrow-left"></i> &#36820;&#22238;
                </a>
            </div>
            <div class="card mb-3">
                <?php if ($form['cover_image']): ?>
                    <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="card-img-top"
                         style="max-height:300px;object-fit:cover;">
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h3 class="fw-bold mb-0"><?= htmlspecialchars($form['title']) ?></h3>
                        <?php if (isLoggedIn()): ?>
                        <div class="dropdown ms-2">
                            <button class="btn btn-sm p-0 text-muted" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (isAdmin() || $form['user_id'] == $user_id): ?>
                                <li>
                                    <button class="dropdown-item text-danger" id="btn-delete-form">
                                        <i class="bi bi-trash"></i> 刪除表單
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <button class="dropdown-item" id="btn-report-form">
                                        <i class="bi bi-flag"></i> 檢舉表單
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($form['description']): ?>
                        <div class="text-muted mb-2 ql-content"><?= assetHtml(autolink($form['description'])) ?></div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <?= renderAvatar($form['author'], $form['author_avatar'] ?? null, 28) ?>
                            <small class="text-muted fw-semibold"><?= htmlspecialchars($form['author']) ?></small>
                        </div>
                        <?php if ($form['end_date']): ?>
                            <small class="text-muted">&nbsp;|&nbsp;<i class="bi bi-clock text-warning"></i> 截止：<?= date('Y/m/d H:i', strtotime($form['end_date'])) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="fillForm">
                <input type="hidden" name="return_to" value="<?= $form_back_attr ?>">
                <?php if ($edit_response_id): ?>
                <input type="hidden" name="edit_response_id" value="<?= $edit_response_id ?>">
                <?php endif; ?>
                <?php foreach ($fields as $field): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label fw-semibold">
                            <?= htmlspecialchars($field['label']) ?>
                            <?php if ($field['is_required']): ?><span class="text-danger"> *</span><?php endif; ?>
                        </label>

                        <?php
                            $fid = $field['id'];
                            $pre = htmlspecialchars($prefill[$fid] ?? '', ENT_QUOTES);
                            $preChecked = array_map('trim', explode(', ', $prefill[$fid] ?? ''));
                        ?>
                        <?php if ($field['field_type'] === 'short_text'): ?>
                            <input type="text" name="answers[<?= $fid ?>]" class="form-control"
                                   value="<?= $pre ?>" <?= $field['is_required'] ? 'required' : '' ?>>

                        <?php elseif ($field['field_type'] === 'long_text'): ?>
                            <textarea name="answers[<?= $fid ?>]" class="form-control" rows="4"
                                      <?= $field['is_required'] ? 'required' : '' ?>><?= $pre ?></textarea>

                        <?php elseif ($field['field_type'] === 'radio'): ?>
                            <?php foreach ($field['options'] as $opt): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                           name="answers[<?= $fid ?>]"
                                           value="<?= htmlspecialchars($opt) ?>"
                                           <?= ($prefill[$fid] ?? '') === $opt ? 'checked' : '' ?>
                                           <?= $field['is_required'] ? 'required' : '' ?>>
                                    <label class="form-check-label option-label"><?= htmlspecialchars($opt) ?></label>
                                </div>
                            <?php endforeach; ?>

                        <?php elseif ($field['field_type'] === 'checkbox'): ?>
                            <?php foreach ($field['options'] as $opt): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="answers[<?= $fid ?>][]"
                                           value="<?= htmlspecialchars($opt) ?>"
                                           <?= in_array($opt, $preChecked) ? 'checked' : '' ?>>
                                    <label class="form-check-label option-label"><?= htmlspecialchars($opt) ?></label>
                                </div>
                            <?php endforeach; ?>

                        <?php elseif ($field['field_type'] === 'dropdown'): ?>
                            <select name="answers[<?= $fid ?>]" class="form-select"
                                    <?= $field['is_required'] ? 'required' : '' ?>>
                                <option value="">-- 請選擇 --</option>
                                <?php foreach ($field['options'] as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>" <?= ($prefill[$fid] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($field['field_type'] === 'date'): ?>
                            <div class="input-group">
                                <input type="date" name="answers[<?= $fid ?>]" id="dp_<?= $fid ?>" class="form-control" value="<?= $pre ?>" <?= $field['is_required'] ? 'required' : '' ?>>
                                <button type="button" class="btn btn-outline-secondary native-dp-toggle" data-target="dp_<?= $fid ?>"><i class="bi bi-calendar"></i></button>
                            </div>

                        <?php elseif ($field['field_type'] === 'time'): ?>
                            <input type="time" name="answers[<?= $fid ?>]" class="form-control"
                                   value="<?= $pre ?>" <?= $field['is_required'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-glow-primary w-100" <?= $preview ? 'disabled title="預覽模式，無法提交"' : '' ?>>
                        <i class="bi bi-send"></i> 提交
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <!-- 社交功能區（按讚 / 收藏 / 留言） -->
        <?php if (!$preview) include __DIR__ . '/config/_social_section.php'; ?>

    </div>
</div>

<!-- 所有社交功能 script 統一在 _social_section.php 管理 -->
<script>
const _dpOpen = {};
$(document).on('click', '.native-dp-toggle', function () {
    const id = $(this).data('target');
    const input = document.getElementById(id);
    if (_dpOpen[id]) {
        input.blur();
        _dpOpen[id] = false;
    } else {
        input.showPicker();
        _dpOpen[id] = true;
    }
});
$(document).on('blur', 'input[type="date"]', function () {
    _dpOpen[this.id] = false;
});
</script>

<?php if ($preview): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div></body></html>
<?php else: require_once 'config/footer.php'; endif; ?>
