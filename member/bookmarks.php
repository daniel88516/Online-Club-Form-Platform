<?php
$pageTitle = '我的收藏';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

$stmt = mysqli_prepare($conn, "
    SELECT f.id, f.title, f.description, f.end_date, f.is_published,
           u.username AS author,
           COUNT(DISTINCT fr.id) AS response_count,
           COUNT(DISTINCT fl.id) AS like_count
    FROM form_bookmarks fb
    JOIN forms f ON fb.form_id = f.id
    JOIN users u ON f.user_id = u.id
    LEFT JOIN form_responses fr ON f.id = fr.form_id
    LEFT JOIN form_likes fl ON f.id = fl.form_id
    WHERE fb.user_id = ?
    GROUP BY f.id
    ORDER BY fb.created_at DESC
");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$forms = mysqli_stmt_get_result($stmt);

require_once '../config/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bookmark-fill text-warning"></i> 我的收藏</h4>
    <a href="/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> 返回首頁
    </a>
</div>

<?php if (mysqli_num_rows($forms) === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-bookmark" style="font-size:3rem;"></i>
        <p class="mt-3">尚未收藏任何表單</p>
        <a href="/index.php" class="btn btn-glow-primary btn-sm">去探索表單</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php while ($form = mysqli_fetch_assoc($forms)): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="fw-bold"><?= htmlspecialchars($form['title']) ?></h5>
                    <?php if ($form['description']): ?>
                        <p class="text-muted small"><?= htmlspecialchars(mb_substr($form['description'], 0, 80)) ?><?= mb_strlen($form['description']) > 80 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <div class="text-muted small">
                        <i class="bi bi-person"></i> <?= htmlspecialchars($form['author']) ?>
                        &nbsp;|&nbsp;
                        <i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 填答
                        &nbsp;|&nbsp;
                        <i class="bi bi-heart"></i> <?= $form['like_count'] ?>
                    </div>
                    <?php if (!$form['is_published']): ?>
                        <span class="badge bg-secondary mt-2">未發布</span>
                    <?php elseif ($form['end_date'] && $form['end_date'] < $now): ?>
                        <span class="badge bg-danger mt-2">已截止</span>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <?php if ($form['is_published'] && (!$form['end_date'] || $form['end_date'] >= $now)): ?>
                    <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-glow-primary btn-sm flex-grow-1">
                        <i class="bi bi-pencil"></i> 填寫
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-outline-warning btn-sm btn-remove-bookmark" data-id="<?= $form['id'] ?>">
                        <i class="bi bi-bookmark-x"></i> 取消收藏
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<script>
$('.btn-remove-bookmark').on('click', function () {
    const btn = $(this);
    const formId = btn.data('id');
    $.post('/api/bookmark.php', { form_id: formId }, function (res) {
        if (res.success && !res.bookmarked) {
            btn.closest('.col-md-6').fadeOut(300, function () { $(this).remove(); });
        }
    }, 'json');
});
</script>

<?php require_once '../config/footer.php'; ?>
