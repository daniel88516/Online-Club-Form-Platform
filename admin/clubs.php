<?php
$pageTitle = '社團管理';
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/_admin_search.php';
requireAdmin();

// 刪除社團
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_club_id'])) {
    $del_id = intval($_POST['delete_club_id']);
    mysqli_query($conn, "DELETE FROM club_members WHERE club_id = $del_id");
    mysqli_query($conn, "DELETE FROM clubs WHERE id = $del_id");
}

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';
$stmt = mysqli_prepare($conn, "
    SELECT c.id, c.name, c.description, c.is_public, c.created_at, c.cover_image,
           u.username AS owner_name, u.avatar AS owner_avatar, u.id AS owner_id,
           (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id AND cm.status = 'active') AS member_count,
           (SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id AND cm.status = 'pending') AS pending_count
    FROM clubs c
    JOIN users u ON c.owner_id = u.id
    WHERE c.name LIKE ? OR u.username LIKE ?
    ORDER BY c.created_at DESC
");
mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
mysqli_stmt_execute($stmt);
$clubs = mysqli_stmt_get_result($stmt);
$total = mysqli_num_rows($clubs);

require_once '../config/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people-fill"></i> 社團管理</h4>
    <a href="<?= REL_BASE ?>admin/dashboard.php" class="btn btn-glow-dark btn-sm"><i class="bi bi-arrow-left"></i> 返回後台</a>
</div>

<div class="card mb-4 border-0 admin-stat-card stat-blue">
    <div class="card-body py-3 text-center">
        <h2 class="fw-bold mb-0"><?= $total ?></h2>
        <p class="mb-0 text-muted small">社團總數</p>
    </div>
</div>

<?php renderAdminSearchBar('q', $q, '搜尋社團名稱或管理員...', REL_BASE . 'admin/clubs.php'); ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>社團名稱</th>
                    <th>管理員</th>
                    <th class="text-center">成員</th>
                    <th class="text-center">待審</th>
                    <th class="text-center">類型</th>
                    <th>建立時間</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($club = mysqli_fetch_assoc($clubs)): ?>
                <tr data-admin-search="<?= htmlspecialchars(mb_strtolower($club['name'] . ' ' . $club['owner_name'])) ?>">
                    <td>
                        <a href="<?= REL_BASE ?>club.php?id=<?= $club['id'] ?>" target="_blank" class="fw-semibold text-decoration-none link-body-emphasis admin-search-target">
                            <?= adminSearchHighlight($club['name'], $q) ?>
                        </a>
                        <?php if ($club['description']): ?>
                        <div class="text-muted small"><?= htmlspecialchars(mb_substr($club['description'], 0, 40)) ?><?= mb_strlen($club['description']) > 40 ? '...' : '' ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= renderAvatarDropdown($club['owner_name'], $club['owner_avatar'] ?? null, $club['owner_id'], 28, $_SESSION['user_id']) ?>
                            <span class="admin-search-target"><?= adminSearchHighlight($club['owner_name'], $q) ?></span>
                        </div>
                    </td>
                    <td class="text-center">
                        <div class="d-flex justify-content-center">
                            <button class="btn btn-glow-dark btn-sm btn-view-members" data-id="<?= $club['id'] ?>" data-count="<?= $club['member_count'] ?>" style="font-size:0.9rem;padding:0.28rem 0.7rem;">
                                <i class="bi bi-people-fill"></i> 查看成員（<?= $club['member_count'] ?>）
                            </button>
                        </div>
                    </td>
                    <td class="text-center">
                        <?php if ($club['pending_count'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $club['pending_count'] ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($club['is_public']): ?>
                            <span class="badge bg-success">公開</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">私人</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= date('Y/m/d', strtotime($club['created_at'])) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('確定刪除社團「<?= htmlspecialchars($club['name'], ENT_QUOTES) ?>」？此操作無法復原。')">
                            <input type="hidden" name="delete_club_id" value="<?= $club['id'] ?>">
                            <button class="btn btn-glow-red btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($total === 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">找不到社團</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 查看成員 Modal -->
<div class="modal fade" id="adminMemberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-people-fill"></i> 社團成員</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="admin-member-search" class="form-control mb-3" placeholder="搜尋成員名稱...">
                <div id="admin-member-results" style="max-height:260px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>
</div>

<script>
let _adminMemberClubId = 0;
let _adminMemberTimer;

function loadAdminMembers(q) {
    $('#admin-member-results').html('<p class="text-muted small text-center mt-2">載入中...</p>');
    $.post('<?= REL_BASE ?>api/club_action.php', { action: 'list_members', club_id: _adminMemberClubId, q: q || '' }, function (res) {
        const $r = $('#admin-member-results').empty();
        if (!res.members || !res.members.length) {
            $r.html('<p class="text-muted small text-center mt-2">找不到成員</p>'); return;
        }
        res.members.forEach(function (m) {
            const av = m.avatar
                ? `<img src="${assetUrl(m.avatar)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
                : `<span style="width:36px;height:36px;border-radius:50%;background:var(--bs-secondary-bg);display:inline-flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">${m.username.charAt(0).toUpperCase()}</span>`;
            const badge = m.role === 'owner' ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">管理員</span>' : '';
            $r.append(`
                <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                    ${av}
                    <span class="fw-semibold">${$('<span>').text(m.username).html()}${badge}</span>
                </div>
            `);
        });
    }, 'json');
}

$(document).on('click', '.btn-view-members', function () {
    _adminMemberClubId = $(this).data('id');
    $('#admin-member-search').val('');
    loadAdminMembers('');
    new bootstrap.Modal('#adminMemberModal').show();
});

$(document).on('input', '#admin-member-search', function () {
    clearTimeout(_adminMemberTimer);
    const q = $(this).val().trim();
    _adminMemberTimer = setTimeout(function () { loadAdminMembers(q); }, 300);
});
</script>

<?php renderAdminSearchScript(); ?>
<?php require_once '../config/footer.php'; ?>
