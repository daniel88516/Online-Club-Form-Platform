<?php
$pageTitle = '社團';
$showNavSearch = true;
require_once 'config/session.php';
require_once 'config/db.php';
require_once 'config/header.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : 0;
$tab     = $_GET['tab'] ?? 'explore';
$q       = trim($_GET['q'] ?? '');
?>
<style>
.oom-member-item:hover { background: rgba(var(--bs-primary-rgb), .12); border-radius: 6px; }
</style>

<!-- 標題排序 + 建立按鈕 -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="dropdown">
        <button class="btn ps-2 fw-bold dropdown-toggle d-inline-flex align-items-center gap-1 lh-1" style="font-size:1.15rem;background:transparent;border:none;color:inherit;"
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-people"></i> <span id="sort-label-text">探索社團</span>
        </button>
        <ul class="dropdown-menu">
            <li><button class="dropdown-item sort-opt active" data-mode="members_desc"><i class="bi bi-people me-2 text-info"></i>成員最多</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="date_desc"><i class="bi bi-clock-history me-2 text-primary"></i>最新建立</button></li>
            <li><button class="dropdown-item sort-opt" data-mode="date_asc"><i class="bi bi-clock me-2 text-secondary"></i>最早建立</button></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item sort-opt" data-mode="name_asc"><i class="bi bi-sort-alpha-down me-2 text-success"></i>名稱 A→Z</button></li>
        </ul>
    </div>
    <?php if ($user_id): ?>
    <button class="btn btn-glow-primary" data-bs-toggle="modal" data-bs-target="#createClubModal">
        <i class="bi bi-plus-lg"></i> 建立社團
    </button>
    <?php endif; ?>
</div>

<!-- tab 切換 -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'explore' ? 'active' : '' ?>" href="?tab=explore">
            <i class="bi bi-compass"></i> 探索社團
        </a>
    </li>
    <?php if ($user_id): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'mine' ? 'active' : '' ?>" href="?tab=mine">
            <i class="bi bi-bookmark-heart"></i> 我的社團
        </a>
    </li>
    <?php
    $pending_count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM club_members WHERE user_id = ? AND status IN ('pending','invited')");
    mysqli_stmt_bind_param($pending_count_stmt, 'i', $user_id);
    mysqli_stmt_execute($pending_count_stmt);
    $pending_count = mysqli_fetch_row(mysqli_stmt_get_result($pending_count_stmt))[0];
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>" href="?tab=pending">
            <i class="bi bi-hourglass-split"></i> 待處理社團
            <?php if ($pending_count > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endif; ?>
</ul>

<!-- 社團列表 -->
    <?php
    if ($tab === 'pending' && $user_id) {
        $stmt = mysqli_prepare($conn, "
            SELECT c.*, u.username AS owner_name, u.avatar AS owner_avatar,
                   (SELECT COUNT(*) FROM club_members cm2 WHERE cm2.club_id = c.id AND cm2.status = 'active') AS member_count,
                   cm.role AS my_role, cm.status AS my_status,
                   (SELECT COUNT(*) FROM club_members cm5 WHERE cm5.club_id = c.id AND cm5.status = 'pending') AS pending_count
            FROM clubs c
            JOIN club_members cm ON cm.club_id = c.id AND cm.user_id = ?
            JOIN users u ON c.owner_id = u.id
            WHERE cm.status IN ('pending','invited')
               OR (cm.role = 'owner' AND EXISTS (SELECT 1 FROM club_members WHERE club_id = c.id AND status = 'pending'))
            ORDER BY cm.joined_at DESC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $clubs = mysqli_stmt_get_result($stmt);
    } elseif ($tab === 'mine' && $user_id) {
        $stmt = mysqli_prepare($conn, "
            SELECT c.*, u.username AS owner_name, u.avatar AS owner_avatar,
                   (SELECT COUNT(*) FROM club_members cm2 WHERE cm2.club_id = c.id AND cm2.status = 'active') AS member_count,
                   cm.role AS my_role, cm.status AS my_status
            FROM clubs c
            JOIN club_members cm ON cm.club_id = c.id AND cm.user_id = ? AND cm.status = 'active'
            JOIN users u ON c.owner_id = u.id
            ORDER BY cm.joined_at DESC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $clubs = mysqli_stmt_get_result($stmt);
    } else {
        $like = '%' . $q . '%';
        $stmt = mysqli_prepare($conn, "
            SELECT c.*, u.username AS owner_name, u.avatar AS owner_avatar,
                   (SELECT COUNT(*) FROM club_members cm2 WHERE cm2.club_id = c.id AND cm2.status = 'active') AS member_count,
                   (SELECT role   FROM club_members cm3 WHERE cm3.club_id = c.id AND cm3.user_id = ?) AS my_role,
                   (SELECT status FROM club_members cm4 WHERE cm4.club_id = c.id AND cm4.user_id = ?) AS my_status
            FROM clubs c
            JOIN users u ON c.owner_id = u.id
            WHERE c.name LIKE ?
            ORDER BY member_count DESC, c.created_at DESC
        ");
        mysqli_stmt_bind_param($stmt, 'iis', $user_id, $user_id, $like);
        mysqli_stmt_execute($stmt);
        $clubs = mysqli_stmt_get_result($stmt);
    }
    ?>

<div id="clubs-no-results" class="text-center py-4 text-muted d-none">
    <i class="bi bi-search" style="font-size:2rem;"></i>
    <p class="mt-2">找不到符合的社團</p>
</div>

<div class="row justify-content-center"><div class="col-lg-7">
<div id="clubs-container">
        <?php $count = 0; while ($club = mysqli_fetch_assoc($clubs)): $count++; ?>
        <div class="club-card-item mb-4"
             data-name="<?= htmlspecialchars(mb_strtolower($club['name'])) ?>"
             data-desc="<?= htmlspecialchars(mb_strtolower($club['description'] ?? '')) ?>"
             data-members="<?= $club['member_count'] ?>"
             data-date="<?= strtotime($club['created_at']) ?>">
            <div class="card h-100">
                <?php if ($club['cover_image']): ?>
                    <img src="<?= htmlspecialchars($club['cover_image']) ?>" class="card-img-top" style="aspect-ratio:8/3;object-fit:cover;width:100%;">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center" style="aspect-ratio:8/3;background:rgba(var(--bs-primary-rgb),.15);">
                        <i class="bi bi-people" style="font-size:3rem;opacity:.4;"></i>
                    </div>
                <?php endif; ?>
                <div class="card-body">
                    <h6 class="fw-bold mb-1">
                        <?= htmlspecialchars($club['name']) ?>
                        <?php if (!$club['is_public']): ?>
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;"><i class="bi bi-lock-fill"></i> 私人</span>
                        <?php endif; ?>
                    </h6>
                    <p class="small text-muted mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                        <?= htmlspecialchars($club['description'] ?: '尚無介紹') ?>
                    </p>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="small text-muted"><i class="bi bi-people"></i> <?= $club['member_count'] ?> 位成員</span>
                        <span class="small text-muted d-flex align-items-center gap-1">
                            admin：<?= renderAvatarDropdown($club['owner_name'], $club['owner_avatar'] ?? null, $club['owner_id'], 22, $user_id) ?>
                            <?= htmlspecialchars($club['owner_name']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="/club.php?id=<?= $club['id'] ?>" class="btn btn-glow-primary btn-sm flex-grow-1">
                        <i class="bi bi-door-open"></i> 進入
                        <?php if ($tab === 'pending' && ($club['pending_count'] ?? 0) > 0 && $club['my_role'] === 'owner'): ?>
                        <span class="badge bg-warning text-dark ms-1"><?= $club['pending_count'] ?> 待審</span>
                        <?php endif; ?>
                    </a>
                    <?php if (!$user_id): ?>
                        <!-- 未登入 -->
                    <?php elseif (!$club['my_role']): ?>
                        <?php if ($club['is_public']): ?>
                        <button class="btn btn-glow-cyan btn-sm btn-join-club" data-id="<?= $club['id'] ?>">
                            <i class="bi bi-person-plus"></i> 加入
                        </button>
                        <?php else: ?>
                        <button class="btn btn-glow-primary btn-sm btn-apply-club" data-id="<?= $club['id'] ?>">
                            <i class="bi bi-send"></i> 申請
                        </button>
                        <?php endif; ?>
                    <?php elseif ($club['my_status'] === 'pending'): ?>
                        <button class="btn btn-glow-red btn-sm btn-cancel-apply-club" data-id="<?= $club['id'] ?>"><i class="bi bi-x-lg"></i> 取消申請</button>
                    <?php elseif ($club['my_status'] === 'invited'): ?>
                        <button class="btn btn-glow-green btn-sm btn-accept-invite-club" data-id="<?= $club['id'] ?>">
                            <i class="bi bi-check-lg"></i> 接受邀請
                        </button>
                        <button class="btn btn-glow-red btn-sm btn-decline-invite-club" data-id="<?= $club['id'] ?>"><i class="bi bi-x-lg"></i> 拒絕邀約</button>
                    <?php elseif ($club['my_role'] === 'member'): ?>
                        <button class="btn btn-glow-red btn-sm btn-leave-club" data-id="<?= $club['id'] ?>">
                            <i class="bi bi-person-dash"></i> 離開
                        </button>
                    <?php elseif ($club['my_role'] === 'owner'): ?>
                        <button class="btn btn-sm btn-glow-green btn-owner-options"
                                data-id="<?= $club['id'] ?>"
                                data-name="<?= htmlspecialchars($club['name']) ?>">
                            <i class="bi bi-shield-fill"></i> 管理員選項
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        <?php if ($count === 0): ?>
        <div class="col-12 text-center text-muted py-5">
            <i class="bi bi-people" style="font-size:3rem;opacity:.3;"></i>
            <p class="mt-2"><?= $tab === 'mine' ? '你還沒有加入任何社團' : '找不到符合的社團' ?></p>
        </div>
        <?php endif; ?>
    </div>
</div></div><!-- col-lg-7 / row -->

<!-- 管理員選項 Modal -->
<div class="modal fade" id="ownerOptionsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-fill"></i> 管理員選項 — <span id="oom-club-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">

                <!-- 更換管理員 -->
                <div class="p-3 border-bottom">
                    <div class="fw-semibold mb-2"><i class="bi bi-person-fill-gear me-1 text-warning"></i> 更換管理員</div>
                    <p class="small text-muted mb-2">從目前社團成員中選擇新的管理員，移交後你將成為普通成員。</p>
                    <!-- 成員選擇觸發按鈕 -->
                    <button type="button" class="btn btn-outline-secondary w-100 d-flex align-items-center gap-2 text-start mb-2" id="oom-member-trigger" style="min-height:42px;">
                        <span id="oom-selected-avatar"></span>
                        <span id="oom-selected-name" class="flex-grow-1 text-truncate">— 請選擇成員 —</span>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </button>
                    <input type="hidden" id="oom-new-owner" value="">
                    <button class="btn btn-glow-primary w-100" id="btn-transfer-owner">
                        <i class="bi bi-arrow-left-right"></i> 確認移交管理員
                    </button>
                </div>

                <!-- 刪除社團 -->
                <div class="p-3 border-bottom">
                    <div class="fw-semibold mb-2"><i class="bi bi-trash-fill me-1 text-danger"></i> 刪除社團</div>
                    <p class="small text-muted mb-2">此操作<strong>無法復原</strong>，社團及所有成員資料將永久刪除。</p>
                    <button class="btn btn-glow-red w-100" id="btn-delete-club">
                        <i class="bi bi-trash"></i> 刪除這個社團
                    </button>
                </div>

                <!-- 離開社團 -->
                <div class="p-3">
                    <div class="fw-semibold mb-2"><i class="bi bi-box-arrow-right me-1 text-danger"></i> 離開社團</div>
                    <p class="small text-muted mb-2">離開後將自動由最早加入的成員遞補為管理員。若社團內無其他成員，社團將被刪除。</p>
                    <button class="btn btn-glow-red w-100" id="btn-owner-leave">
                        <i class="bi bi-person-dash"></i> 離開社團
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- 成員選擇 Modal（疊在管理員選項上）-->
<div class="modal fade" id="oomMemberPickerModal" tabindex="-1" style="z-index:1065;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-people me-1"></i> 選擇新管理員</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow-y:auto;max-height:320px;">
                <div id="oom-member-list" class="p-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- 社團封面裁切 Modal -->
<div class="modal fade" id="clubCoverCropModal" tabindex="-1" data-bs-backdrop="static" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-crop me-2"></i>裁剪封面圖</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" style="background:#111;">
                <div style="max-height:380px;overflow:hidden;">
                    <img id="club-cover-crop-img" src="" style="display:block;max-width:100%;" alt="">
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>拖曳調整範圍，滾輪縮放</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-glow-primary" id="btn-club-cover-crop-confirm">
                    <i class="bi bi-check-lg me-1"></i>確認裁剪
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 建立社團 Modal -->
<div class="modal fade" id="createClubModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-people"></i> 建立社團</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">社團名稱 <span class="text-danger">*</span></label>
                    <input type="text" id="club-name" class="form-control" placeholder="輸入社團名稱">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">社團介紹</label>
                    <textarea id="club-desc" class="form-control" rows="3" placeholder="介紹你的社團..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">封面圖片</label>
                    <input type="file" id="club-cover-input" class="form-control" accept="image/*">
                    <div id="club-cover-preview" class="mt-2 d-none">
                        <img id="club-cover-thumb" src="" class="img-fluid rounded" style="max-height:150px;object-fit:cover;width:100%;">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="club-public" checked>
                    <label class="form-check-label" for="club-public">公開社團（任何人可加入）</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-glow-primary" id="btn-create-club">建立</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
let _clubCoverUrl = '';
let _clubCoverCropModal = null, _clubCoverCropper = null;

$('#club-cover-input').on('change', function () {
    const file = this.files[0];
    if (!file) return;
    this.value = '';
    const reader = new FileReader();
    reader.onload = function (e) {
        document.getElementById('club-cover-crop-img').src = e.target.result;
        if (_clubCoverCropper) { _clubCoverCropper.destroy(); _clubCoverCropper = null; }
        if (!_clubCoverCropModal) _clubCoverCropModal = new bootstrap.Modal(document.getElementById('clubCoverCropModal'));
        _clubCoverCropModal.show();
    };
    reader.readAsDataURL(file);
});

$(document).on('shown.bs.modal', '#clubCoverCropModal', function () {
    _clubCoverCropper = new Cropper(document.getElementById('club-cover-crop-img'), {
        aspectRatio: 8 / 3,
        viewMode: 2,
        dragMode: 'move',
        autoCropArea: 0.9,
        restore: false,
        guides: true,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
    });
});

$(document).on('hidden.bs.modal', '#clubCoverCropModal', function () {
    if (_clubCoverCropper) { _clubCoverCropper.destroy(); _clubCoverCropper = null; }
});

$(document).on('click', '#btn-club-cover-crop-confirm', function () {
    if (!_clubCoverCropper) return;
    _clubCoverCropper.getCroppedCanvas({ maxWidth: 2400,
        imageSmoothingEnabled: true, imageSmoothingQuality: 'high' })
    .toBlob(function (blob) {
        _clubCoverCropModal.hide();
        const fd = new FormData();
        fd.append('image', blob, 'cover.jpg');
        fd.append('type', 'clubs');
        $.ajax({
            url: '/api/upload.php', type: 'POST',
            data: fd, contentType: false, processData: false,
            success: function (res) {
                if (res.success) {
                    _clubCoverUrl = res.path;
                    $('#club-cover-thumb').attr('src', res.path);
                    $('#club-cover-preview').removeClass('d-none');
                } else { alert(res.message || '封面圖上傳失敗'); }
            }
        });
    }, 'image/jpeg', 0.92);
});

// 關閉 modal 時重置封面
$('#createClubModal').on('hidden.bs.modal', function () {
    _clubCoverUrl = '';
    $('#club-cover-input').val('');
    $('#club-cover-preview').addClass('d-none');
    $('#club-cover-thumb').attr('src', '');
});

window.addEventListener('load', function () {
    if (new URLSearchParams(location.search).get('err') === 'private') {
        cuteToast({ type: 'error', icon: '🔒', msg: '此社團為私人社團，需成為成員才能進入。' });
    }
});

$('#btn-create-club').on('click', function () {
    const name = $('#club-name').val().trim();
    if (!name) { alert('請輸入社團名稱'); return; }
    $.post('/api/club_action.php', {
        action: 'create',
        name: name,
        description: $('#club-desc').val().trim(),
        is_public: $('#club-public').is(':checked') ? 1 : 0,
        cover_image: _clubCoverUrl
    }, function (res) {
        if (res.success) location.href = '/club.php?id=' + res.club_id;
        else alert(res.message);
    });
});

// ── 管理員選項 ──
let _oomClubId = 0;

function oomAvatarHtml(avatar, size) {
    size = size || 28;
    if (avatar) return `<img src="${avatar}" class="rounded-circle" style="width:${size}px;height:${size}px;object-fit:cover;">`;
    return `<span class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:${size}px;height:${size}px;font-size:${Math.round(size*0.45)}px;"><i class="bi bi-person-fill text-white"></i></span>`;
}

$(document).on('click', '.btn-owner-options', function () {
    _oomClubId = $(this).data('id');
    $('#oom-club-name').text($(this).data('name'));
    // 重置選擇器
    $('#oom-new-owner').val('');
    $('#oom-selected-name').text('— 請選擇成員 —');
    $('#oom-selected-avatar').html('');
    $('#oom-member-panel').addClass('d-none');
    $('#oom-member-list').html('<div class="p-2 text-muted small">載入中...</div>');
    $.post('/api/club_action.php', { action: 'get_members', club_id: _oomClubId }, function (res) {
        if (!res.success || res.members.length === 0) {
            $('#oom-member-list').html('<div class="p-2 text-muted small">無其他成員可選</div>');
            return;
        }
        $('#oom-member-list').html(res.members.map(m => `
            <div class="oom-member-item d-flex align-items-center gap-2 px-2 py-2 rounded" style="cursor:pointer;"
                 data-id="${m.id}" data-name="${m.username}" data-avatar="${m.avatar||''}">
                ${oomAvatarHtml(m.avatar, 32)}
                <span>${m.username}</span>
            </div>
        `).join(''));
    }, 'json');
    new bootstrap.Modal('#ownerOptionsModal').show();
});

// 開啟成員選擇浮動視窗
$('#oom-member-trigger').on('click', function () {
    new bootstrap.Modal('#oomMemberPickerModal').show();
});
// 點選成員
$(document).on('click', '.oom-member-item', function () {
    const id     = $(this).data('id');
    const name   = $(this).data('name');
    const avatar = $(this).data('avatar');
    $('#oom-new-owner').val(id);
    $('#oom-selected-name').text(name);
    $('#oom-selected-avatar').html(oomAvatarHtml(avatar, 26));
    bootstrap.Modal.getInstance('#oomMemberPickerModal').hide();
});

$('#btn-transfer-owner').on('click', function () {
    const newOwner = $('#oom-new-owner').val();
    if (!newOwner) { cuteToast({ type: 'warning', msg: '請先選擇要移交的成員' }); return; }
    const name = $('#oom-new-owner option:selected').text();
    cuteConfirm({ msg: `確定將管理員移交給「${name}」？`, sub: '移交後你將成為普通成員，此操作無法自動復原。', icon: '⚡', okText: '確認移交' }, function (ok) {
        if (!ok) return;
        $.post('/api/club_action.php', { action: 'transfer_owner', club_id: _oomClubId, new_owner_id: newOwner }, function (res) {
            if (res.success) { bootstrap.Modal.getInstance('#ownerOptionsModal').hide(); location.reload(); }
            else cuteToast({ type: 'error', msg: res.message });
        }, 'json');
    });
});

$('#btn-delete-club').on('click', function () {
    const clubName = $('#oom-club-name').text();
    cuteConfirm({ msg: `確定刪除「${clubName}」？`, sub: '此操作無法復原，社團及所有成員資料將永久刪除。', icon: '🗑️', okText: '永久刪除' }, function (ok) {
        if (!ok) return;
        $.post('/api/club_action.php', { action: 'delete_club', club_id: _oomClubId }, function (res) {
            if (res.success) location.reload();
            else cuteToast({ type: 'error', msg: res.message });
        }, 'json');
    });
});

$('#btn-owner-leave').on('click', function () {
    cuteConfirm({ msg: '確定要離開這個社團？', sub: '你的管理員身份將自動移交給最早加入的成員。若無其他成員，社團將被刪除。', icon: '👋', okText: '離開' }, function (ok) {
        if (!ok) return;
        $.post('/api/club_action.php', { action: 'owner_leave', club_id: _oomClubId }, function (res) {
            if (res.success) { bootstrap.Modal.getInstance('#ownerOptionsModal').hide(); location.reload(); }
            else cuteToast({ type: 'error', msg: res.message });
        }, 'json');
    });
});

$(document).on('click', '.btn-join-club', function () {
    const id = $(this).data('id');
    $.post('/api/club_action.php', { action: 'join', club_id: id }, function (res) {
        if (res.success) location.reload();
        else alert(res.message);
    });
});

$(document).on('click', '.btn-apply-club', function () {
    const $btn = $(this);
    const id = $btn.data('id');
    $.post('/api/club_action.php', { action: 'join', club_id: id }, function (res) {
        if (res.success && res.pending) {
            $btn.removeClass('btn-glow-primary btn-apply-club').addClass('btn-glow-red btn-cancel-apply-club')
                .html('<i class="bi bi-x-lg"></i> 取消申請');
        } else if (res.success) {
            location.reload();
        } else {
            alert(res.message);
        }
    });
});

$(document).on('click', '.btn-cancel-apply-club', function () {
    const $btn = $(this);
    const id = $btn.data('id');
    $.post('/api/club_action.php', { action: 'leave', club_id: id }, function (res) {
        if (res.success) {
            $btn.removeClass('btn-glow-red btn-cancel-apply-club').addClass('btn-glow-primary btn-apply-club')
                .html('<i class="bi bi-send"></i> 申請');
        } else {
            alert(res.message);
        }
    });
});

$(document).on('click', '.btn-accept-invite-club', function () {
    const id = $(this).data('id');
    $.post('/api/club_action.php', { action: 'accept_invite', club_id: id }, function (res) {
        if (res.success) location.href = '/club.php?id=' + id;
        else alert(res.message);
    });
});

$(document).on('click', '.btn-decline-invite-club', function () {
    const id = $(this).data('id');
    $.post('/api/club_action.php', { action: 'decline_invite', club_id: id }, function (res) {
        if (res.success) location.reload();
        else alert(res.message);
    });
});

$(document).on('click', '.btn-leave-club', function () {
    const id = $(this).data('id');
    cuteConfirm({ msg: '確定要離開此社團？', sub: '離開後可重新申請加入。', icon: '👋', okText: '離開' }, function (ok) {
        if (!ok) return;
        $.post('/api/club_action.php', { action: 'leave', club_id: id }, function (res) {
            if (res.success) location.reload();
            else alert(res.message);
        });
    });
});


// ── Navbar 搜尋 ──
$('#nav-search-toggle').on('click', function () {
    $('#nav-search-box').removeClass('d-none');
    $('#nav-search-input').focus();
});
$('#nav-search-close').on('click', function () {
    $('#nav-search-input').val('').trigger('input');
    $('#nav-search-box').addClass('d-none');
});

$('#nav-search-input').on('input', function () {
    const q = $(this).val().toLowerCase().trim();
    let visible = 0;
    $('.club-card-item').each(function () {
        const match = !q || $(this).data('name').includes(q) || $(this).data('desc').includes(q);
        $(this).toggle(match);
        if (match) visible++;
    });
    $('#clubs-no-results').toggleClass('d-none', visible > 0 || !q);
});

// ── 排序 ──
function clubsSort(mode) {
    const $container = $('#clubs-container');
    const $items = $container.children('.club-card-item').toArray();
    $items.sort(function (a, b) {
        const $a = $(a), $b = $(b);
        if (mode === 'members_desc') return $b.data('members') - $a.data('members');
        if (mode === 'date_desc')    return $b.data('date') - $a.data('date');
        if (mode === 'date_asc')     return $a.data('date') - $b.data('date');
        if (mode === 'name_asc')     return $a.data('name').localeCompare($b.data('name'), 'zh-Hant');
        return 0;
    });
    $container.append($items);
}
$(document).on('click', '.sort-opt', function () {
    const mode  = $(this).data('mode');
    const label = $(this).text().trim();
    $('.sort-opt').removeClass('active');
    $(this).addClass('active');
    $('#sort-label-text').text(label);
    clubsSort(mode);
});

</script>

<?php require_once 'config/footer.php'; ?>
