<?php
$pageTitle = '首頁';
require_once 'config/session.php';
require_once 'config/db.php';

// 取得已發布的公開表單（無填答期限限制或在期限內）
$now = date('Y-m-d H:i:s');
$sql = "SELECT f.id, f.title, f.description, f.start_date, f.end_date, f.allow_multiple,
               u.username AS author, g.name AS group_name,
               COUNT(fr.id) AS response_count
        FROM forms f
        JOIN users u ON f.user_id = u.id
        LEFT JOIN `groups` g ON f.target_group = g.id
        LEFT JOIN form_responses fr ON f.id = fr.form_id
        WHERE f.is_published = 1
          AND (f.target_group IS NULL)
          AND (f.end_date IS NULL OR f.end_date >= '$now')
        GROUP BY f.id
        ORDER BY f.created_at DESC";

$forms = mysqli_query($conn, $sql);

require_once 'config/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-list-ul"></i> 公開表單列表</h4>
        <p class="text-muted">以下為目前開放填答的公開表單</p>
    </div>
    <?php if (isMember()): ?>
    <div class="col-auto">
        <a href="/member/create_form.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> 新增表單
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (mysqli_num_rows($forms) === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
        <p class="mt-3">目前沒有公開表單</p>
        <?php if (!isLoggedIn()): ?>
            <a href="/login.php" class="btn btn-outline-primary">登入後新增表單</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php while ($form = mysqli_fetch_assoc($forms)): ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><?= htmlspecialchars($form['title']) ?></h5>
                    <?php if ($form['description']): ?>
                        <p class="card-text text-muted small"><?= nl2br(htmlspecialchars(mb_substr($form['description'], 0, 80))) ?><?= mb_strlen($form['description']) > 80 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-person"></i> <?= htmlspecialchars($form['author']) ?>
                            &nbsp;|&nbsp;
                            <i class="bi bi-pencil-square"></i> <?= $form['response_count'] ?> 人填答
                        </small>
                    </div>
                    <?php if ($form['end_date']): ?>
                        <small class="text-warning">
                            <i class="bi bi-clock"></i> 截止：<?= date('Y/m/d', strtotime($form['end_date'])) ?>
                        </small>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/form_view.php?id=<?= $form['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-pencil"></i> 填寫表單
                    </a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<?php require_once 'config/footer.php'; ?>
