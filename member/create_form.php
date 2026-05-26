<?php
$pageTitle = '新增表單';
define('FAB_CUSTOM', true);
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$error = '';
$success = '';

function normalizeFieldOptions($options) {
    if (is_array($options)) {
        return array_values(array_filter(array_map('trim', $options), function ($value) {
            return $value !== '';
        }));
    }

    return array_values(array_filter(array_map('trim', explode("\n", (string)$options)), function ($value) {
        return $value !== '';
    }));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $target_group = null;
    $start_date   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date     = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $club_id             = !empty($_POST['club_id']) ? intval($_POST['club_id']) : null;
    $allow_multiple      = isset($_POST['allow_multiple']) ? 1 : 0;
    $is_published        = isset($_POST['is_published']) ? 1 : 0;
    $show_stats          = isset($_POST['show_stats']) ? 1 : 0;
    $anonymous_responses = isset($_POST['anonymous_responses']) ? 1 : 0;
    $fields       = $_POST['fields'] ?? [];

    // 檢查選擇類型欄位是否有填入選項
    $optionError = false;
    foreach ($fields as $field) {
        $type = $field['type'] ?? '';
        if (in_array($type, ['radio', 'checkbox', 'dropdown'])) {
            $opts = normalizeFieldOptions($field['options'] ?? []);
            if (empty($opts)) { $optionError = true; break; }
        }
    }

    if (empty($title)) {
        $error = '請填寫表單標題';
    } elseif (empty($fields)) {
        $error = '請至少新增一個欄位';
    } elseif (!empty($start_date) && empty($end_date)) {
        $error = '已填開始時間，請一併填寫截止時間';
    } elseif (empty($start_date) && !empty($end_date)) {
        $error = '已填截止時間，請一併填寫開始時間';
    } elseif ($optionError) {
        $error = '單選題、核取方塊、下拉選單必須至少填入一個選項';
    } else {
        // 封面圖：由前端 AJAX 上傳後取得路徑，只需從 POST 讀取
        $raw_cover   = trim($_POST['cover_image'] ?? '');
        $cover_image = preg_match('#^/uploads/forms/[a-zA-Z0-9_.]+$#', $raw_cover)
                       ? $raw_cover
                       : null;

        // 插入表單
        $stmt = mysqli_prepare($conn, "INSERT INTO forms (user_id, title, description, cover_image, target_group, start_date, end_date, allow_multiple, is_published, show_stats, anonymous_responses, club_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issssissiiii',
            $_SESSION['user_id'], $title, $description, $cover_image, $target_group,
            $start_date, $end_date, $allow_multiple, $is_published, $show_stats, $anonymous_responses, $club_id
        );

        if (mysqli_stmt_execute($stmt)) {
            $form_id = mysqli_insert_id($conn);

            // 插入欄位
            foreach ($fields as $order => $field) {
                $type     = $field['type'] ?? 'short_text';
                $label    = trim($field['label'] ?? '');
                $required = isset($field['required']) ? 1 : 0;
                $options  = null;

                if (in_array($type, ['radio', 'checkbox', 'dropdown']) && !empty($field['options'])) {
                    $opts = normalizeFieldOptions($field['options']);
                    $options = json_encode(array_values($opts), JSON_UNESCAPED_UNICODE);
                }

                if (!empty($label)) {
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO form_fields (form_id, field_type, label, is_required, options, order_num) VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt2, 'issisi', $form_id, $type, $label, $required, $options, $order);
                    mysqli_stmt_execute($stmt2);
                }
            }

            header('Location: ' . APP_BASE . '/member/my_forms.php?msg=created');
            exit();
        } else {
            $error = '建立失敗，請稍後再試';
        }
    }
}

// 取得使用者所屬社團（供表單關聯選擇）
$my_clubs_stmt = mysqli_prepare($conn, "
    SELECT c.id, c.name FROM clubs c
    JOIN club_members cm ON cm.club_id = c.id AND cm.user_id = ?
    ORDER BY cm.joined_at DESC
");
mysqli_stmt_bind_param($my_clubs_stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($my_clubs_stmt);
$my_clubs_result = mysqli_stmt_get_result($my_clubs_stmt);
$my_clubs = [];
while ($club = mysqli_fetch_assoc($my_clubs_result)) {
    $my_clubs[] = $club;
}
$selected_club_id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? intval($_POST['club_id'] ?? 0)
    : intval($_GET['club_id'] ?? 0);

require_once '../config/header.php';
?>

<div class="row mb-3">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-plus-circle"></i> 新增表單</h4>
    </div>
    <div class="col-auto">
        <a href="<?= REL_BASE ?>member/my_forms.php" class="btn btn-glow-primary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="" id="formBuilder" enctype="multipart/form-data">
    <div class="row g-4">
        <!-- 左側：表單設定 -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-gear"></i> 表單設定
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">表單標題 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="輸入表單標題"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">說明</label>
                        <input type="hidden" name="description" id="description-hidden">
                        <div id="quill-description" class="quill-desc-editor"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">封面圖片</label>
                        <input type="hidden" name="cover_image" id="cover-image-url" value="">
                        <input type="file" id="cover-image-input" class="form-control" accept="image/*">
                        <div id="cover-image-preview" class="mt-2" style="display:none;">
                            <img id="cover-image-thumb" src="" class="img-fluid rounded" style="max-height:200px;object-fit:cover;">
                        </div>
                    </div>
                    <?php if (count($my_clubs) > 0): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">所屬社團</label>
                        <input type="hidden" name="club_id" id="club-id-input" value="<?= $selected_club_id ?: '' ?>">
                        <div class="dropdown">
                            <button class="btn btn-glow-primary dropdown-toggle w-100 d-flex align-items-center justify-content-between text-start"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span id="club-select-label">
                                    <?php
                                    $selected_club_name = '不屬於任何社團';
                                    foreach ($my_clubs as $c) {
                                        if ((int)$c['id'] === $selected_club_id) {
                                            $selected_club_name = $c['name'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($selected_club_name);
                                    ?>
                                </span>
                            </button>
                            <ul class="dropdown-menu w-100" style="<?= count($my_clubs) > 5 ? 'max-height: 240px; overflow-y: auto;' : '' ?>">
                                <li><button type="button" class="dropdown-item club-select-option" data-id="" data-name="不屬於任何社團">不屬於任何社團</button></li>
                                <?php foreach ($my_clubs as $c): ?>
                                    <li>
                                        <button type="button" class="dropdown-item club-select-option" data-id="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-semibold mb-0">開始時間</label>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input time-toggle" type="checkbox" id="start_time_toggle" data-target="start_date">
                                <label class="form-check-label small" for="start_time_toggle">精確到時間</label>
                            </div>
                        </div>
                        <div class="input-group">
                            <input type="date" name="start_date" id="start_date" class="form-control">
                            <button type="button" class="btn btn-outline-secondary native-dp-toggle" data-target="start_date"><i class="bi bi-calendar"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-semibold mb-0">截止時間</label>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input time-toggle" type="checkbox" id="end_time_toggle" data-target="end_date">
                                <label class="form-check-label small" for="end_time_toggle">精確到時間</label>
                            </div>
                        </div>
                        <div class="input-group">
                            <input type="date" name="end_date" id="end_date" class="form-control">
                            <button type="button" class="btn btn-outline-secondary native-dp-toggle" data-target="end_date"><i class="bi bi-calendar"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_multiple" id="allow_multiple">
                            <label class="form-check-label" for="allow_multiple">允許重複填答</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_published" id="is_published">
                            <label class="form-check-label" for="is_published">立即發布</label>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_stats" id="show_stats" checked>
                            <label class="form-check-label" for="show_stats">
                                <i class="bi bi-bar-chart text-info"></i> 公開填答統計
                            </label>
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="anonymous_responses" id="anonymous_responses">
                            <label class="form-check-label" for="anonymous_responses">
                                <i class="bi bi-incognito text-secondary"></i> 匿名填寫
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右側：欄位建立器 -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check"></i> 表單欄位</span>
                </div>
                <div class="card-body">
                    <!-- 新增欄位按鈕 -->
                    <div class="mb-3 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-glow-primary btn-sm add-field" data-type="short_text">
                            <i class="bi bi-input-cursor-text"></i> 簡答
                        </button>
                        <button type="button" class="btn btn-glow-primary btn-sm add-field" data-type="long_text">
                            <i class="bi bi-text-paragraph"></i> 詳答
                        </button>
                        <button type="button" class="btn btn-glow-primary btn-sm add-field" data-type="radio">
                            <i class="bi bi-ui-radios"></i> 單選題
                        </button>
                        <button type="button" class="btn btn-glow-primary btn-sm add-field" data-type="checkbox">
                            <i class="bi bi-ui-checks"></i> 核取方塊
                        </button>
                        <button type="button" class="btn btn-glow-primary btn-sm add-field" data-type="dropdown">
                            <i class="bi bi-menu-button-wide"></i> 下拉選單
                        </button>
                        <button type="button" class="btn btn-glow-primary btn-sm add-field" data-type="date">
                            <i class="bi bi-calendar"></i> 日期
                        </button>
                    </div>

                    <!-- 欄位容器 -->
                    <div id="fields-container">
                        <div class="text-center text-muted py-4" id="empty-hint">
                            <i class="bi bi-arrow-up-circle" style="font-size:2rem;"></i>
                            <p class="mt-2">點擊上方按鈕新增欄位</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3 justify-content-end">
                <button type="submit" class="btn btn-glow-green px-4">
                    <i class="bi bi-check-lg"></i> 儲存表單
                </button>
            </div>
        </div>
    </div>
</form>

<script>
let fieldCount = 0;

const fieldLabels = {
    short_text: '簡答題',
    long_text:  '詳答題',
    radio:      '單選題',
    checkbox:   '核取方塊',
    dropdown:   '下拉選單',
    date:       '日期',
    time:       '時間'
};

const fieldIcons = {
    short_text: 'bi-input-cursor-text',
    long_text:  'bi-text-paragraph',
    radio:      'bi-ui-radios',
    checkbox:   'bi-ui-checks',
    dropdown:   'bi-menu-button-wide',
    date:       'bi-calendar',
    time:       'bi-clock'
};

function hasOptions(type) {
    return ['radio', 'checkbox', 'dropdown'].includes(type);
}

function optionIcon(type) {
    if (type === 'radio') return '<i class="bi bi-circle option-kind-icon"></i>';
    if (type === 'checkbox') return '<i class="bi bi-square option-kind-icon"></i>';
    return '<i class="bi bi-list option-kind-icon"></i>';
}

function optionRowHTML(type, value = '') {
    const safeValue = $('<span>').text(value).html();
    return `
        <div class="option-row d-flex align-items-center gap-2 mb-2">
            <button type="button" class="btn btn-sm btn-link text-muted p-0 option-drag-handle" title="拖曳排序">
                <i class="bi bi-grip-vertical"></i>
            </button>
            <span class="option-kind">${optionIcon(type)}</span>
            <textarea name="" class="form-control form-control-sm option-input" rows="1" placeholder="選項文字">${safeValue}</textarea>
            <button type="button" class="btn btn-glow-red btn-sm remove-option" title="刪除選項">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
}

function autoResizeOption($el) {
    $el.css('height', 'auto');
    $el.css('height', Math.max(34, $el[0].scrollHeight) + 'px');
}

function syncOptionNames($card) {
    const fieldName = $card.find('input[name$="[type]"]').attr('name') || '';
    const match = fieldName.match(/^fields\[\d+\]/);
    if (!match) return;
    $card.find('.option-input').each(function () {
        $(this).attr('name', match[0] + '[options][]');
        autoResizeOption($(this));
    });
}

function initOptionSortable(el) {
    if (!window.Sortable || !el || el.dataset.sortableReady) return;
    Sortable.create(el, {
        handle: '.option-drag-handle',
        animation: 150,
        ghostClass: 'opacity-50',
        forceFallback: true,
        fallbackOnBody: true,
        fallbackTolerance: 3,
        onEnd: function () {
            syncOptionNames($(el).closest('.field-card'));
        }
    });
    el.dataset.sortableReady = '1';
}

function addField(type) {
    $('#empty-hint').hide();
    const idx = fieldCount++;
    const defaultLabel = (type === 'short_text' || type === 'long_text') ? '說說你的看法吧!!!' : '';
    const optionsHTML = hasOptions(type) ? `
        <div class="mt-2 option-builder" data-option-type="${type}">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label small mb-0">選項</label>
                <button type="button" class="btn btn-glow-primary btn-sm add-option">
                    <i class="bi bi-plus-lg"></i> 新增選項
                </button>
            </div>
            <div class="option-list">
                ${optionRowHTML(type, '選項 1')}
                ${optionRowHTML(type, '選項 2')}
            </div>
        </div>` : '';

    const html = `
    <div class="field-card" id="field-${idx}">
        <input type="hidden" name="fields[${idx}][type]" value="${type}">
        <div class="d-flex align-items-start gap-2">
            <div class="drag-handle text-muted" title="拖曳排序" style="cursor:grab;padding-top:4px;font-size:1.1rem;flex-shrink:0;user-select:none;-webkit-user-select:none;touch-action:none;">
                <i class="bi bi-grip-vertical"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="${fieldIcons[type]} text-primary"></i>
                    <span class="badge bg-primary">${fieldLabels[type]}</span>
                    <input type="text" name="fields[${idx}][label]" class="form-control form-control-sm"
                           value="${defaultLabel}" placeholder="輸入問題標題" required>
                </div>
                ${optionsHTML}
                <div class="mt-2">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="fields[${idx}][required]" id="req-${idx}">
                        <label class="form-check-label small" for="req-${idx}">必填</label>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-glow-red btn-sm remove-field" data-id="${idx}">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>`;

    $('#fields-container').append(html);
    const $card = $(`#field-${idx}`);
    syncOptionNames($card);
    initOptionSortable($card.find('.option-list')[0]);
}

$(document).ready(function () {
    // 新增欄位
    $('.add-field').on('click', function () {
        addField($(this).data('type'));
    });

    $(document).on('click', '.club-select-option', function () {
        $('#club-id-input').val($(this).data('id'));
        $('#club-select-label').text($(this).data('name'));
    });

    // 刪除欄位
    $(document).on('click', '.remove-field', function () {
        const id = $(this).data('id');
        $(`#field-${id}`).remove();
        if ($('.field-card').length === 0) {
            $('#empty-hint').show();
        }
    });

    $(document).on('click', '.add-option', function () {
        const $builder = $(this).closest('.option-builder');
        const type = $builder.data('option-type');
        const next = $builder.find('.option-row').length + 1;
        $builder.find('.option-list').append(optionRowHTML(type, '選項 ' + next));
        initOptionSortable($builder.find('.option-list')[0]);
        syncOptionNames($builder.closest('.field-card'));
        $builder.find('.option-input').last().focus().select();
    });

    $(document).on('click', '.remove-option', function () {
        const $builder = $(this).closest('.option-builder');
        if ($builder.find('.option-row').length <= 1) {
            $builder.find('.option-input').val('').focus();
        } else {
            $(this).closest('.option-row').remove();
        }
        syncOptionNames($builder.closest('.field-card'));
    });

    $(document).on('input', '.option-input', function () {
        $(this).removeClass('is-invalid');
        autoResizeOption($(this));
        syncOptionNames($(this).closest('.field-card'));
    });

    $(document).on('keydown', '.option-input', function (e) {
        if (e.key !== 'Enter' || !e.ctrlKey) return;
        e.preventDefault();
        $(this).closest('.option-builder').find('.add-option').trigger('click');
    });

    // 表單送出驗證
    $('#formBuilder').on('submit', function (e) {
        // 先把 Quill 說明內容填入 hidden field
        if (typeof quillDesc !== 'undefined') {
            $('#description-hidden').val(quillDesc.root.innerHTML);
        }

        if ($('.field-card').length === 0) {
            e.preventDefault();
            window.cuteToast({ type: 'error', msg: '請至少新增一個欄位' });
            return;
        }

        // 檢查開始時間與截止時間：只填其中一個才提示
        const startDate = $('#start_date').val();
        const endDate   = $('#end_date').val();
        if (startDate && !endDate) {
            e.preventDefault();
            window.cuteToast({ type: 'error', msg: '已填開始時間，請一併填寫截止時間' });
            $('#end_date').addClass('is-invalid').focus();
            return;
        }
        if (!startDate && endDate) {
            e.preventDefault();
            window.cuteToast({ type: 'error', msg: '已填截止時間，請一併填寫開始時間' });
            $('#start_date').addClass('is-invalid').focus();
            return;
        }
        $('#start_date, #end_date').removeClass('is-invalid');

        $('.field-card').each(function () {
            syncOptionNames($(this));
        });

        // 檢查選擇類型欄位是否有填入選項
        let hasError = false;
        $('.field-card').each(function () {
            const type = $(this).find('input[name$="[type]"]').val();
            if (['radio', 'checkbox', 'dropdown'].includes(type)) {
                const options = $(this).find('.option-input').filter(function () {
                    return $(this).val().trim() !== '';
                });
                if (options.length === 0) {
                    hasError = true;
                    $(this).find('.option-input').addClass('is-invalid');
                } else {
                    $(this).find('.option-input').removeClass('is-invalid');
                }
            }
        });
        if (hasError) {
            e.preventDefault();
            window.cuteToast({ type: 'error', msg: '單選題、核取方塊、下拉選單必須至少填入一個選項' });
            return;
        }
        // 依 DOM 順序重新排序欄位索引，確保拖曳後順序正確
        let newIdx = 0;
        $('#fields-container .field-card').each(function () {
            $(this).find('[name]').each(function () {
                const n = $(this).attr('name');
                if (n && /^fields\[/.test(n)) {
                    $(this).attr('name', n.replace(/^fields\[\d+\]/, 'fields[' + newIdx + ']'));
                }
            });
            syncOptionNames($(this));
            newIdx++;
        });
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
        url: '<?= REL_BASE ?>api/upload.php', type: 'POST',
        data: fd, contentType: false, processData: false,
        success: res => {
            if (res.success) {
                $('#cover-image-url').val(res.path);
                $('#cover-image-thumb').attr('src', assetUrl(res.path));
                $('#cover-image-preview').show();
            } else {
                window.cuteToast({ type: 'error', msg: res.message || '封面圖上傳失敗' });
            }
        }
    });
});
</script>

<script>
// ── 說明欄位：Quill 富文字編輯器 ──
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
                                url: '<?= REL_BASE ?>api/upload.php', type: 'POST',
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
    <?php if (!empty($_POST['description'])): ?>
    quillDesc.clipboard.dangerouslyPasteHTML(<?= json_encode($_POST['description']) ?>);
    <?php endif; ?>
    attachAutoLink(quillDesc);
    attachPasteImageHandler(quillDesc, 'forms');
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// 拖曳排序欄位
Sortable.create(document.getElementById('fields-container'), {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'opacity-50',
    forceFallback: true,
    fallbackOnBody: true,
    fallbackTolerance: 3
});
</script>
<script>
$('.time-toggle').on('change', function () {
    const input = document.getElementById($(this).data('target'));
    const val = input.value;
    input.type = this.checked ? 'datetime-local' : 'date';
    input.value = val.substring(0, this.checked ? 16 : 10);
});

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
$(document).on('blur', 'input[type="datetime-local"], input[type="date"]', function () {
    _dpOpen[this.id] = false;
});
</script>
<?php require_once '../config/footer.php'; ?>
