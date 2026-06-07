<?php
require_once '../config/session.php';
require_once '../config/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); echo '參數錯誤'; exit(); }

if (isAdmin()) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM forms WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM forms WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
}
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) { http_response_code(404); echo '找不到表單或無查看權限'; exit(); }

if (!isAdmin() && (!isLoggedIn() || (int)$form['user_id'] !== (int)$_SESSION['user_id'])) {
    if (empty($form['is_published'])) {
        http_response_code(404);
        echo '找不到表單或無查看權限';
        exit();
    }

    $visible = !empty($form['show_on_index']);
    $club_ids = [];
    $has_form_clubs = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'form_clubs'")) > 0;
    if ($has_form_clubs) {
        $fc_result = mysqli_query($conn, "SELECT club_id FROM form_clubs WHERE form_id = $id");
        while ($fc = mysqli_fetch_assoc($fc_result)) $club_ids[] = (int)$fc['club_id'];
    }
    if (!empty($form['club_id'])) $club_ids[] = (int)$form['club_id'];
    $club_ids = array_values(array_unique(array_filter($club_ids)));

    if (!$visible && !empty($club_ids)) {
        $club_id_list = implode(',', array_map('intval', $club_ids));
        $public_club = mysqli_query($conn, "SELECT id FROM clubs WHERE is_public = 1 AND id IN ($club_id_list) LIMIT 1");
        $visible = (bool)mysqli_fetch_assoc($public_club);

        if (!$visible && isLoggedIn()) {
            $uid = (int)$_SESSION['user_id'];
            $member_check = mysqli_query($conn, "SELECT id FROM club_members WHERE user_id = $uid AND status = 'active' AND club_id IN ($club_id_list) LIMIT 1");
            $visible = (bool)mysqli_fetch_assoc($member_check);
        }
    }

    if (!$visible) {
        http_response_code(404);
        echo '找不到表單或無查看權限';
        exit();
    }
}

$fields_result = mysqli_query($conn, "SELECT * FROM form_fields WHERE form_id = $id ORDER BY order_num");
$fields = [];
while ($f = mysqli_fetch_assoc($fields_result)) {
    $f['options'] = $f['options'] ? json_decode($f['options'], true) : [];
    $fields[] = $f;
}
?>
<div class="card mb-3">
    <?php if ($form['cover_image']): ?>
        <img src="<?= htmlspecialchars(assetUrl($form['cover_image'])) ?>" class="card-img-top" style="max-height:250px;object-fit:cover;">
    <?php endif; ?>
    <div class="card-body">
        <h5 class="fw-bold mb-1"><?= htmlspecialchars($form['title']) ?></h5>
        <?php if ($form['description']): ?>
            <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars(strip_tags($form['description']))) ?></p>
        <?php endif; ?>
        <?php if ($form['end_date']): ?>
            <small class="text-warning"><i class="bi bi-clock"></i> 截止：<?= date('Y/m/d H:i', strtotime($form['end_date'])) ?></small>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($fields)): ?>
    <p class="text-muted text-center">此表單尚未新增任何題目</p>
<?php else: ?>
    <?php foreach ($fields as $field): ?>
    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold">
                <?= htmlspecialchars($field['label']) ?>
                <?php if ($field['is_required']): ?><span class="text-danger"> *</span><?php endif; ?>
            </label>

            <?php if ($field['field_type'] === 'short_text'): ?>
                <input type="text" class="form-control" disabled placeholder="簡答文字">

            <?php elseif ($field['field_type'] === 'long_text'): ?>
                <textarea class="form-control" rows="3" disabled placeholder="詳答文字"></textarea>

            <?php elseif ($field['field_type'] === 'radio'): ?>
                <?php foreach ($field['options'] as $opt): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" disabled>
                        <label class="form-check-label option-label"><?= htmlspecialchars($opt) ?></label>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($field['field_type'] === 'checkbox'): ?>
                <?php foreach ($field['options'] as $opt): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" disabled>
                        <label class="form-check-label option-label"><?= htmlspecialchars($opt) ?></label>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($field['field_type'] === 'dropdown'): ?>
                <select class="form-select" disabled>
                    <option>-- 請選擇 --</option>
                    <?php foreach ($field['options'] as $opt): ?>
                        <option><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($field['field_type'] === 'date'): ?>
                <input type="date" class="form-control" disabled>

            <?php elseif ($field['field_type'] === 'time'): ?>
                <input type="time" class="form-control" disabled>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="d-grid mb-2">
        <button class="btn btn-primary btn-lg" disabled>
            <i class="bi bi-lock"></i> 預覽模式停用
        </button>
    </div>
<?php endif; ?>
