<?php
$pageTitle = '編輯表單';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);

// 確認表單屬於此會員
$stmt = mysqli_prepare($conn, "SELECT * FROM forms WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) {
    header('Location: /member/my_forms.php');
    exit();
}

// 取得欄位
$fields_result = mysqli_query($conn, "SELECT * FROM form_fields WHERE form_id = $id ORDER BY order_num");
$existing_fields = [];
while ($f = mysqli_fetch_assoc($fields_result)) {
    $existing_fields[] = $f;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $target_group   = !empty($_POST['target_group']) ? intval($_POST['target_group']) : null;
    $start_date     = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date       = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $allow_multiple      = isset($_POST['allow_multiple']) ? 1 : 0;
    $is_published        = isset($_POST['is_published']) ? 1 : 0;
    $show_stats          = isset($_POST['show_stats']) ? 1 : 0;
    $anonymous_responses = isset($_POST['anonymous_responses']) ? 1 : 0;
    $fields         = $_POST['fields'] ?? [];

    // 檢查選擇類型欄位是否有填入選項
    $optionError = false;
    foreach ($fields as $field) {
        $type = $field['type'] ?? '';
        if (in_array($type, ['radio', 'checkbox', 'dropdown'])) {
            $opts = array_filter(array_map('trim', explode("\n", $field['options'] ?? '')));
            if (empty($opts)) { $optionError = true; break; }
        }
    }

    if (empty($title)) {
        $error = '請填寫表單標題';
    } elseif (empty($fields)) {
        $error = '請至少新增一個欄位';
    } elseif ($optionError) {
        $error = '單選題、核取方塊、下拉選單必須至少填入一個選項';
    } else {
        // 封面圖：由前端 AJAX 上傳後取得路徑，只需從 POST 讀取
        $raw_cover   = trim($_POST['cover_image'] ?? '');
        $cover_image = preg_match('#^/uploads/forms/[a-zA-Z0-9_.]+$#', $raw_cover)
                       ? $raw_cover
                       : $form['cover_image'];

        // 更新表單
        $stmt = mysqli_prepare($conn, "UPDATE forms SET title=?, description=?, cover_image=?, target_group=?, start_date=?, end_date=?, allow_multiple=?, is_published=?, show_stats=?, anonymous_responses=? WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'ssssssiiiiii',
            $title, $description, $cover_image, $target_group, $start_date, $end_date,
            $allow_multiple, $is_published, $show_stats, $anonymous_responses, $id, $_SESSION['user_id']
        );
        mysqli_stmt_execute($stmt);

        // 刪除舊欄位
        mysqli_query($conn, "DELETE FROM form_fields WHERE form_id = $id");

        // 插入新欄位
        foreach ($fields as $order => $field) {
            $type     = $field['type'] ?? 'short_text';
            $label    = trim($field['label'] ?? '');
            $required = isset($field['required']) ? 1 : 0;
            $options  = null;

            if (in_array($type, ['radio', 'checkbox', 'dropdown']) && !empty($field['options'])) {
                $opts = array_filter(array_map('trim', explode("\n", $field['options'])));
                $options = json_encode(array_values($opts), JSON_UNESCAPED_UNICODE);
            }

            if (!empty($label)) {
                $stmt2 = mysqli_prepare($conn, "INSERT INTO form_fields (form_id, field_type, label, is_required, options, order_num) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt2, 'issisi', $id, $type, $label, $required, $options, $order);
                mysqli_stmt_execute($stmt2);
            }
        }

        header('Location: /member/my_forms.php?msg=updated');
        exit();
    }
}

$groups = mysqli_query($conn, "SELECT id, name FROM `groups` ORDER BY id");
require_once '../config/header.php';
?>

<div class="row mb-3">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-pencil"></i> 編輯表單</h4>
    </div>
    <div class="col-auto">
        <a href="/member/my_forms.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="" id="formBuilder" enctype="multipart/form-data">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-gear"></i> 表單設定
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">表單標題 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?= htmlspecialchars($form['title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">說明</label>
                        <input type="hidden" name="description" id="description-hidden">
                        <div id="quill-description" class="quill-desc-editor"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">封面圖片</label>
                        <input type="hidden" name="cover_image" id="cover-image-url"
                               value="<?= htmlspecialchars($form['cover_image'] ?? '') ?>">
                        <input type="file" id="cover-image-input" class="form-control" accept="image/*">
                        <div id="cover-image-preview" class="mt-2"<?= $form['cover_image'] ? '' : ' style="display:none;"' ?>>
                            <img id="cover-image-thumb"
                                 src="<?= htmlspecialchars($form['cover_image'] ?? '') ?>"
                                 class="img-fluid rounded" style="max-height:150px;object-fit:cover;">
                            <small class="text-muted d-block mt-1">上傳新圖片將取代現有封面</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">填答對象</label>
                        <select name="target_group" class="form-select">
                            <option value="">所有人（公開）</option>
                            <?php while ($g = mysqli_fetch_assoc($groups)): ?>
                                <option value="<?= $g['id'] ?>" <?= $form['target_group'] == $g['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['name']) ?> 群組
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">開始時間</label>
                        <input type="datetime-local" name="start_date" class="form-control"
                               value="<?= $form['start_date'] ? date('Y-m-d\TH:i', strtotime($form['start_date'])) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">截止時間</label>
                        <input type="datetime-local" name="end_date" class="form-control"
                               value="<?= $form['end_date'] ? date('Y-m-d\TH:i', strtotime($form['end_date'])) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_multiple" id="allow_multiple"
                                   <?= $form['allow_multiple'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="allow_multiple">允許重複填答</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_published" id="is_published"
                                   <?= $form['is_published'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_published">發布表單</label>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_stats" id="show_stats"
                                   <?= ($form['show_stats'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_stats">
                                <i class="bi bi-bar-chart text-info"></i> 公開填答統計
                            </label>
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="anonymous_responses" id="anonymous_responses"
                                   <?= ($form['anonymous_responses'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="anonymous_responses">
                                <i class="bi bi-incognito text-secondary"></i> 匿名填寫
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <span><i class="bi bi-list-check"></i> 表單欄位</span>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm add-field" data-type="short_text"><i class="bi bi-input-cursor-text"></i> 簡答</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-field" data-type="long_text"><i class="bi bi-text-paragraph"></i> 詳答</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-field" data-type="radio"><i class="bi bi-ui-radios"></i> 單選題</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-field" data-type="checkbox"><i class="bi bi-ui-checks"></i> 核取方塊</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-field" data-type="dropdown"><i class="bi bi-menu-button-wide"></i> 下拉選單</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-field" data-type="date"><i class="bi bi-calendar"></i> 日期</button>
                    </div>
                    <div id="fields-container">
                        <div class="text-center text-muted py-4" id="empty-hint" style="<?= count($existing_fields) > 0 ? 'display:none' : '' ?>">
                            <i class="bi bi-arrow-up-circle" style="font-size:2rem;"></i>
                            <p class="mt-2">點擊上方按鈕新增欄位</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3 justify-content-end">
                <button type="submit" class="btn btn-success px-4">
                    <i class="bi bi-check-lg"></i> 儲存變更
                </button>
            </div>
        </div>
    </div>
</form>

<script>
let fieldCount = 0;
const fieldLabels = { short_text:'簡答題', long_text:'詳答題', radio:'單選題', checkbox:'核取方塊', dropdown:'下拉選單', date:'日期', time:'時間' };
const fieldIcons  = { short_text:'bi-input-cursor-text', long_text:'bi-text-paragraph', radio:'bi-ui-radios', checkbox:'bi-ui-checks', dropdown:'bi-menu-button-wide', date:'bi-calendar', time:'bi-clock' };

function hasOptions(type) { return ['radio','checkbox','dropdown'].includes(type); }

function addField(type, label='', required=false, options='') {
    $('#empty-hint').hide();
    const idx = fieldCount++;
    const optHTML = hasOptions(type) ? `
        <div class="mt-2">
            <label class="form-label small">選項（每行一個）</label>
            <textarea name="fields[${idx}][options]" class="form-control form-control-sm" rows="3">${options}</textarea>
        </div>` : '';
    const html = `
    <div class="field-card" id="field-${idx}">
        <input type="hidden" name="fields[${idx}][type]" value="${type}">
        <div class="d-flex align-items-start gap-2">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="${fieldIcons[type]} text-primary"></i>
                    <span class="badge bg-primary">${fieldLabels[type]}</span>
                    <input type="text" name="fields[${idx}][label]" class="form-control form-control-sm" value="${label}" placeholder="輸入問題標題" required>
                </div>
                ${optHTML}
                <div class="mt-2">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="fields[${idx}][required]" id="req-${idx}" ${required?'checked':''}>
                        <label class="form-check-label small" for="req-${idx}">必填</label>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-danger btn-sm remove-field" data-id="${idx}"><i class="bi bi-trash"></i></button>
        </div>
    </div>`;
    $('#fields-container').append(html);
}

$(document).ready(function () {
    // 載入現有欄位
    <?php foreach ($existing_fields as $f): ?>
    addField(
        '<?= $f['field_type'] ?>',
        '<?= addslashes(htmlspecialchars_decode($f['label'])) ?>',
        <?= $f['is_required'] ? 'true' : 'false' ?>,
        '<?= $f['options'] ? implode('\n', json_decode($f['options'], true)) : '' ?>'
    );
    <?php endforeach; ?>

    $('.add-field').on('click', function () { addField($(this).data('type')); });
    $(document).on('click', '.remove-field', function () {
        $(`#field-${$(this).data('id')}`).remove();
        if ($('.field-card').length === 0) $('#empty-hint').show();
    });
    $('#formBuilder').on('submit', function (e) {
        // 先把 Quill 說明內容填入 hidden field
        if (typeof quillDesc !== 'undefined') {
            $('#description-hidden').val(quillDesc.root.innerHTML);
        }
        if ($('.field-card').length === 0) { e.preventDefault(); alert('請至少新增一個欄位'); }
    });
});
</script>

<script>
// 封面圖：選取後立即 AJAX 上傳（與 Quill 圖片邏輯一致）
$('#cover-image-input').on('change', function () {
    const file = this.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('image', file);
    fd.append('type', 'forms');
    $.ajax({
        url: '/api/upload.php', type: 'POST',
        data: fd, contentType: false, processData: false,
        success: res => {
            if (res.success) {
                $('#cover-image-url').val(res.path);
                $('#cover-image-thumb').attr('src', res.path);
                $('#cover-image-preview').show();
            } else {
                alert(res.message || '封面圖上傳失敗');
            }
        }
    });
});
</script>

<script>
// ── 說明欄位：Quill 富文字編輯器（載入現有說明） ──
let quillDesc;
$(document).ready(function () {
    quillDesc = new Quill('#quill-description', {
        theme: 'snow',
        placeholder: '表單說明（選填）',
        modules: {
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'image', 'link'],
                    ['clean']
                ],
                handlers: {
                    image: function () {
                        const input = document.createElement('input');
                        input.setAttribute('type', 'file');
                        input.setAttribute('accept', 'image/*');
                        input.click();
                        input.onchange = () => {
                            const file = input.files[0];
                            if (!file) return;
                            const fd = new FormData();
                            fd.append('image', file);
                            fd.append('type', 'forms');
                            $.ajax({
                                url: '/api/upload.php', type: 'POST',
                                data: fd, contentType: false, processData: false,
                                success: res => {
                                    if (res.success) {
                                        const range = quillDesc.getSelection() || { index: quillDesc.getLength() };
                                        quillDesc.insertEmbed(range.index, 'image', res.path);
                                        quillDesc.setSelection(range.index + 1);
                                    }
                                }
                            });
                        };
                    }
                }
            }
        }
    });
    // 載入現有說明（HTML 格式）
    const existingDesc = <?= json_encode($form['description'] ?? '') ?>;
    if (existingDesc) quillDesc.clipboard.dangerouslyPasteHTML(existingDesc);
    attachAutoLink(quillDesc);
    attachPasteImageHandler(quillDesc, 'forms');
});
</script>
<?php require_once '../config/footer.php'; ?>
