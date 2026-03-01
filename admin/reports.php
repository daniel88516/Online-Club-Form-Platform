<?php
$pageTitle = '檢舉管理';
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

$success = '';

// 標記為已處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_id'])) {
    $rid = intval($_POST['resolve_id']);
    mysqli_query($conn, "UPDATE reports SET status = 'resolved' WHERE id = $rid");
    $success = '已標記為處理完成';
}

// 刪除被檢舉內容並標記已處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_target_id'], $_POST['delete_target_type'], $_POST['delete_report_id'])) {
    $rtype = $_POST['delete_target_type'] === 'comment' ? 'comment' : 'form';
    $tid   = intval($_POST['delete_target_id']);
    $rid   = intval($_POST['delete_report_id']);

    if ($rtype === 'comment') {
        mysqli_query($conn, "DELETE FROM form_comments WHERE id = $tid");
    } else {
        mysqli_query($conn, "DELETE FROM forms WHERE id = $tid");
    }
    mysqli_query($conn, "UPDATE reports SET status = 'resolved' WHERE id = $rid");
    $success = '已刪除內容並標記為處理完成';
}

// 篩選狀態
$filter = $_GET['filter'] ?? 'pending';
$where  = $filter === 'all' ? '' : "WHERE r.status = '$filter'";

$reports = mysqli_query($conn, "
    SELECT r.id, r.type, r.target_id, r.reason, r.status, r.created_at,
           reporter.username AS reporter_name,
           CASE
               WHEN r.type = 'comment' THEN fc.content
               WHEN r.type = 'form'    THEN f.title
               ELSE NULL
           END AS target_content,
           CASE
               WHEN r.type = 'form'    THEN f.title
               WHEN r.type = 'comment' THEN cf.title
               ELSE NULL
           END AS form_title,
           CASE
               WHEN r.type = 'form'    THEN r.target_id
               WHEN r.type = 'comment' THEN fc.form_id
               ELSE NULL
           END AS form_id
    FROM reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    LEFT JOIN form_comments fc ON r.type = 'comment' AND r.target_id = fc.id
    LEFT JOIN forms f ON r.type = 'form' AND r.target_id = f.id
    LEFT JOIN forms cf ON r.type = 'comment' AND fc.form_id = cf.id
    $where
    ORDER BY r.created_at DESC
");

$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM reports WHERE status = 'pending'"))['cnt'];

require_once '../config/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold">
            <i class="bi bi-flag"></i> 檢舉管理
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-danger ms-1"><?= $pending_count ?></span>
            <?php endif; ?>
        </h4>
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

<!-- 篩選標籤 -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'pending' ? 'active' : '' ?>" href="?filter=pending">
            待處理
            <?php if ($pending_count > 0): ?>
                <span class="badge bg-danger ms-1"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'resolved' ? 'active' : '' ?>" href="?filter=resolved">已處理</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">全部</a>
    </li>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>類型</th>
                    <th>被檢舉內容</th>
                    <th>所屬表單</th>
                    <th>原因</th>
                    <th>檢舉者</th>
                    <th>時間</th>
                    <th>狀態</th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($reports) === 0): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">沒有檢舉記錄</td></tr>
                <?php else: ?>
                    <?php while ($r = mysqli_fetch_assoc($reports)): ?>
                    <tr id="report-row-<?= $r['id'] ?>" class="<?= $r['status'] === 'resolved' ? 'opacity-50' : '' ?>">
                        <td><?= $r['id'] ?></td>
                        <td>
                            <?php if ($r['type'] === 'form'): ?>
                                <span class="badge bg-primary"><i class="bi bi-file-earmark-text"></i> 表單</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-chat"></i> 留言</span>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:200px;">
                            <?php if ($r['target_content']): ?>
                                <span class="text-truncate d-inline-block" style="max-width:180px;">
                                    <?= htmlspecialchars(mb_substr(strip_tags($r['target_content']), 0, 35)) ?>…
                                </span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">（內容已刪除）</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars($r['form_title'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-danger-subtle text-danger"><?= htmlspecialchars($r['reason']) ?></span>
                        </td>
                        <td class="small"><?= htmlspecialchars($r['reporter_name'] ?? '匿名') ?></td>
                        <td class="small text-muted"><?= date('Y/m/d H:i', strtotime($r['created_at'])) ?></td>
                        <td>
                            <?php if ($r['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">待處理</span>
                            <?php else: ?>
                                <span class="badge bg-success">已處理</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">

                                    <?php if ($r['target_content'] && $r['form_id']): ?>
                                    <li>
                                        <?php if ($r['type'] === 'form'): ?>
                                            <!-- 預覽表單（Modal） -->
                                            <button class="dropdown-item btn-preview-report-form"
                                                    data-form-id="<?= $r['form_id'] ?>"
                                                    data-form-title="<?= htmlspecialchars($r['form_title'] ?? '') ?>">
                                                <i class="bi bi-eye text-info me-2"></i> 預覽表單
                                            </button>
                                        <?php else: ?>
                                            <!-- 查看留言（Modal） -->
                                            <button class="dropdown-item btn-preview-comment"
                                                    data-content="<?= htmlspecialchars($r['target_content']) ?>"
                                                    data-form-id="<?= $r['form_id'] ?>"
                                                    data-form-title="<?= htmlspecialchars($r['form_title'] ?? '') ?>">
                                                <i class="bi bi-eye text-info me-2"></i> 查看留言
                                            </button>
                                        <?php endif; ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>

                                    <?php if ($r['status'] === 'pending'): ?>
                                    <li>
                                        <form method="POST" class="d-block m-0">
                                            <input type="hidden" name="resolve_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="dropdown-item text-success">
                                                <i class="bi bi-check-circle me-2"></i> 標記已處理
                                            </button>
                                        </form>
                                    </li>
                                    <?php if ($r['target_content']): ?>
                                    <li>
                                        <form method="POST" class="d-block m-0"
                                              data-cute-confirm="確定要刪除這個<?= $r['type'] === 'form' ? '表單' : '留言' ?>嗎？"
                                              data-cute-icon="<?= $r['type'] === 'form' ? '📋' : '💬' ?>">
                                            <input type="hidden" name="delete_target_id"   value="<?= $r['target_id'] ?>">
                                            <input type="hidden" name="delete_target_type" value="<?= $r['type'] ?>">
                                            <input type="hidden" name="delete_report_id"   value="<?= $r['id'] ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i> 刪除<?= $r['type'] === 'form' ? '表單' : '留言' ?>
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <?php endif; ?>

                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 預覽表單 Modal -->
<div class="modal fade" id="previewReportFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold">
                    <i class="bi bi-eye text-info me-1"></i> <span id="preview-report-form-title"></span>
                </h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="preview-report-form-body">
                <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
            </div>
        </div>
    </div>
</div>

<!-- 查看留言 Modal -->
<div class="modal fade" id="commentPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold"><i class="bi bi-chat me-1"></i> 被檢舉留言內容</h6>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="preview-comment-body" class="border rounded p-3 bg-light small"></div>
            </div>
            <div class="modal-footer py-2">
                <a id="preview-comment-form-link" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> 前往所屬表單
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">關閉</button>
            </div>
        </div>
    </div>
</div>

<style>
.report-highlighted td { background-color: #fff8e1 !important; }
[data-bs-theme="dark"] .report-highlighted td { background-color: #2a2200 !important; }
</style>

<script>
// Highlight 指定檢舉列
(function () {
    const hlId = <?= intval($_GET['highlight'] ?? 0) ?>;
    if (!hlId) return;
    const row = document.getElementById('report-row-' + hlId);
    if (!row) return;
    row.classList.add('report-highlighted');
    setTimeout(function () { row.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 200);
    // 任何操作（點擊）立即移除 highlight
    document.addEventListener('click', function () {
        row.classList.remove('report-highlighted');
    }, { once: true });
})();

// 預覽表單（同 admin/forms.php 做法）
const _previewReportModalEl = document.getElementById('previewReportFormModal');
$(document).on('click', '.btn-preview-report-form', function () {
    const formId    = $(this).data('form-id');
    const formTitle = $(this).data('form-title');
    $('#preview-report-form-title').text(formTitle);
    $('#preview-report-form-body').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
    bootstrap.Modal.getOrCreateInstance(_previewReportModalEl).show();
    $.get('/api/form_preview_content.php', { id: formId }, function (html) {
        $('#preview-report-form-body').html(html);
    }).fail(function () {
        $('#preview-report-form-body').html('<div class="alert alert-danger">載入失敗，請稍後再試</div>');
    });
});
_previewReportModalEl.addEventListener('hidden.bs.modal', function () {
    $('#preview-report-form-body').html('');
});

// 查看留言（Modal）
$(document).on('click', '.btn-preview-comment', function () {
    const content  = $(this).data('content');
    const formId   = $(this).data('form-id');
    $('#preview-comment-body').html(content || '<span class="text-muted">（無內容）</span>');
    $('#preview-comment-form-link').attr('href', formId ? '/form_view.php?id=' + formId : '#');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('commentPreviewModal')).show();
});
</script>

<?php require_once '../config/footer.php'; ?>
