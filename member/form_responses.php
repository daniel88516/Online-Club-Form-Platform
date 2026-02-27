<?php
$pageTitle = '填答統計';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);

// 確認表單屬於此會員（或管理員）
if (isAdmin()) {
    $stmt = mysqli_prepare($conn, "SELECT f.*, u.username AS author FROM forms f JOIN users u ON f.user_id = u.id WHERE f.id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT f.*, u.username AS author FROM forms f JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
}
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) {
    header('Location: /member/my_forms.php');
    exit();
}

// 取得欄位
$fields_result = mysqli_query($conn, "SELECT * FROM form_fields WHERE form_id = $id ORDER BY order_num");
$fields = [];
while ($f = mysqli_fetch_assoc($fields_result)) {
    $f['options'] = $f['options'] ? json_decode($f['options'], true) : [];
    $fields[] = $f;
}

// 取得填答記錄總數
$total_result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_responses WHERE form_id = $id");
$total = mysqli_fetch_assoc($total_result)['cnt'];

// 每個欄位的答案統計
$field_stats = [];
foreach ($fields as $field) {
    $fid = $field['id'];
    $answers_result = mysqli_query($conn, "SELECT ra.answer, COUNT(*) AS cnt FROM response_answers ra WHERE ra.field_id = $fid AND ra.answer != '' GROUP BY ra.answer ORDER BY cnt DESC");
    $answers = [];
    while ($row = mysqli_fetch_assoc($answers_result)) {
        $answers[] = $row;
    }
    $field_stats[$fid] = $answers;
}

// 詳細填答列表
$responses_result = mysqli_query($conn, "
    SELECT fr.id, fr.submitted_at, u.username
    FROM form_responses fr
    LEFT JOIN users u ON fr.user_id = u.id
    WHERE fr.form_id = $id
    ORDER BY fr.submitted_at DESC
");

require_once '../config/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-bar-chart"></i> 填答統計：<?= htmlspecialchars($form['title']) ?></h4>
    </div>
    <div class="col-auto">
        <a href="/member/my_forms.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<!-- 總覽 -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <h2 class="fw-bold text-primary"><?= $total ?></h2>
                <p class="text-muted mb-0">填答人數</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <h2 class="fw-bold text-success"><?= count($fields) ?></h2>
                <p class="text-muted mb-0">題目數量</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <h2 class="fw-bold <?= $form['is_published'] ? 'text-success' : 'text-secondary' ?>">
                    <?= $form['is_published'] ? '已發布' : '草稿' ?>
                </h2>
                <p class="text-muted mb-0">狀態</p>
            </div>
        </div>
    </div>
</div>

<?php if ($total === 0): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> 目前尚無填答記錄</div>
<?php else: ?>

<!-- 各題統計 -->
<h5 class="fw-bold mb-3"><i class="bi bi-pie-chart"></i> 各題統計</h5>
<?php foreach ($fields as $field): ?>
<div class="card mb-3">
    <div class="card-header">
        <strong><?= htmlspecialchars($field['label']) ?></strong>
        <span class="badge bg-secondary ms-2"><?= ['short_text'=>'簡答','long_text'=>'詳答','radio'=>'單選','checkbox'=>'核取','dropdown'=>'下拉','date'=>'日期','time'=>'時間'][$field['field_type']] ?></span>
    </div>
    <div class="card-body">
        <?php $stats = $field_stats[$field['id']]; ?>
        <?php if (empty($stats)): ?>
            <p class="text-muted small">無填答</p>
        <?php elseif (in_array($field['field_type'], ['radio', 'checkbox', 'dropdown'])): ?>
            <!-- 長條圖統計 -->
            <?php foreach ($stats as $s): ?>
                <?php $pct = $total > 0 ? round($s['cnt'] / $total * 100) : 0; ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small"><?= htmlspecialchars($s['answer']) ?></span>
                        <span class="small text-muted"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="bg-light rounded" style="height:20px;">
                        <div class="stat-bar rounded" style="width:<?= $pct ?>%;height:100%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- 文字答案列表 -->
            <ul class="list-group list-group-flush">
                <?php foreach (array_slice($stats, 0, 10) as $s): ?>
                    <li class="list-group-item small py-1"><?= htmlspecialchars($s['answer']) ?></li>
                <?php endforeach; ?>
                <?php if (count($stats) > 10): ?>
                    <li class="list-group-item small text-muted py-1">...還有 <?= count($stats) - 10 ?> 筆</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- 詳細記錄 -->
<h5 class="fw-bold mb-3 mt-4"><i class="bi bi-list-ul"></i> 詳細填答記錄</h5>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>填答者</th>
                    <th>填答時間</th>
                    <th class="text-center">查看</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = mysqli_fetch_assoc($responses_result)): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= $r['username'] ? htmlspecialchars($r['username']) : '<span class="text-muted">匿名</span>' ?></td>
                    <td class="small text-muted"><?= date('Y/m/d H:i', strtotime($r['submitted_at'])) ?></td>
                    <td class="text-center">
                        <a href="/member/response_detail.php?id=<?= $r['id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../config/footer.php'; ?>
