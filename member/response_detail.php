<?php
$pageTitle = '填答詳情';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$response_id = intval($_GET['id'] ?? 0);

// 取得填答記錄
$stmt = mysqli_prepare($conn, "
    SELECT fr.*, f.title AS form_title, f.user_id AS form_owner, u.username AS respondent
    FROM form_responses fr
    JOIN forms f ON fr.form_id = f.id
    LEFT JOIN users u ON fr.user_id = u.id
    WHERE fr.id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $response_id);
mysqli_stmt_execute($stmt);
$response = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$response || (!isAdmin() && $response['form_owner'] != $_SESSION['user_id'])) {
    header('Location: /member/my_forms.php');
    exit();
}

// 取得答案
$answers_result = mysqli_query($conn, "
    SELECT ff.label, ff.field_type, ra.answer
    FROM response_answers ra
    JOIN form_fields ff ON ra.field_id = ff.id
    WHERE ra.response_id = $response_id
    ORDER BY ff.order_num
");

require_once '../config/header.php';
?>

<div class="row mb-3">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-file-text"></i> 填答詳情</h4>
        <p class="text-muted">表單：<?= htmlspecialchars($response['form_title']) ?></p>
    </div>
    <div class="col-auto">
        <a href="/member/form_responses.php?id=<?= $response['form_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> 返回統計
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-person"></i>
        填答者：<?= $response['respondent'] ? htmlspecialchars($response['respondent']) : '匿名' ?>
        &nbsp;|&nbsp;
        <i class="bi bi-clock"></i>
        <?= date('Y/m/d H:i', strtotime($response['submitted_at'])) ?>
    </div>
</div>

<?php while ($ans = mysqli_fetch_assoc($answers_result)): ?>
<div class="card mb-3">
    <div class="card-body">
        <p class="fw-semibold mb-1"><?= htmlspecialchars($ans['label']) ?></p>
        <p class="mb-0 text-secondary"><?= $ans['answer'] !== '' ? nl2br(htmlspecialchars($ans['answer'])) : '<span class="text-muted fst-italic">未填答</span>' ?></p>
    </div>
</div>
<?php endwhile; ?>

<?php require_once '../config/footer.php'; ?>
