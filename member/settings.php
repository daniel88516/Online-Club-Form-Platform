<?php
$pageTitle = '設定';
require_once '../config/session.php';
require_once '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];

addColumnIfNotExists($conn, 'users', 'profile_bg_ratio', 'TINYINT NOT NULL DEFAULT 7');

$stmt = $conn->prepare("SELECT username, email, role, created_at, avatar, bio, profile_bg, profile_bg_ratio FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$bg_ratio = intval($user['profile_bg_ratio'] ?? 7);

require_once '../config/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">

<div class="row justify-content-center">
    <div class="col-lg-6">

        <h5 class="fw-bold mb-4"><i class="bi bi-gear"></i> 設定</h5>

        <!-- 頭像 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person-circle me-1"></i> 頭像
            </div>
            <div class="card-body p-0" id="bg-preview-card" style="position:relative;aspect-ratio:<?= $bg_ratio ?>/3;overflow:hidden;transition:aspect-ratio .3s;">
                <!-- 背景圖（填滿整個 card-body） -->
                <div style="position:absolute;inset:0;background:var(--bs-secondary-bg);">
                    <?php if (!empty($user['profile_bg'])): ?>
                        <img id="profile-bg-img" src="<?= htmlspecialchars($user['profile_bg']) ?>"
                             style="width:100%;height:100%;object-fit:cover;" alt="背景">
                    <?php else: ?>
                        <img id="profile-bg-img" src="" style="width:100%;height:100%;object-fit:cover;display:none;" alt="背景">
                    <?php endif; ?>
                    <!-- 底部漸層 -->
                    <div style="position:absolute;inset:0;
                                background:linear-gradient(to bottom,transparent 30%,rgba(0,0,0,0.65) 100%);"></div>
                </div>

                <!-- 相機按鈕（右下，換背景） -->
                <div onclick="document.getElementById('bg-file-input').click()"
                     style="position:absolute;bottom:14px;right:14px;width:36px;height:36px;border-radius:50%;
                            background:rgba(0,0,0,0.45);color:#fff;border:2px solid rgba(255,255,255,0.7);
                            display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:2;">
                    <i class="bi bi-camera-fill" style="font-size:0.85rem;"></i>
                </div>

                <!-- 頭像（左下，換頭像） -->
                <div style="position:absolute;bottom:14px;left:16px;z-index:2;">
                    <div class="position-relative" style="cursor:pointer;display:inline-block;"
                         onclick="document.getElementById('avatar-file-input').click()">
                        <div id="avatar-preview" style="width:80px;height:80px;border-radius:50%;overflow:hidden;
                             background:var(--bs-primary,#0d6efd);display:flex;align-items:center;justify-content:center;
                             font-size:2rem;font-weight:bold;color:var(--bs-primary-text,#fff);
                             border:3px solid rgba(255,255,255,0.7);transition:opacity .2s;"
                             onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar']) ?>" id="avatar-img"
                                     style="width:100%;height:100%;object-fit:cover;" alt="頭像">
                            <?php else: ?>
                                <span><?= mb_strtoupper(mb_substr($user['username'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="position:absolute;bottom:1px;right:1px;width:22px;height:22px;
                                    border-radius:50%;background:var(--bs-primary,#0d6efd);color:var(--bs-primary-text,#fff);
                                    display:flex;align-items:center;justify-content:center;
                                    border:2px solid rgba(255,255,255,0.8);pointer-events:none;">
                            <i class="bi bi-pencil-fill" style="font-size:0.55rem;"></i>
                        </div>
                    </div>
                </div>

                <!-- hidden inputs & status -->
                <input type="file" id="bg-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                <div id="avatar-status" style="position:absolute;bottom:6px;left:110px;z-index:2;"></div>
                <div id="bg-status" style="position:absolute;bottom:6px;left:110px;z-index:2;"></div>
            </div>
        </div>

        <!-- 背景比例 -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-aspect-ratio me-1"></i> 個人頁背景比例</div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <?php foreach ([6, 7, 8] as $r): ?>
                    <button class="btn btn-sm flex-grow-1 btn-ratio <?= $bg_ratio === $r ? 'btn-glow-primary' : 'btn-outline-secondary' ?>"
                            data-ratio="<?= $r ?>">
                        <?= $r ?>:3
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 個人簡介 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person-lines-fill me-1"></i> 個人簡介
            </div>
            <div class="card-body">
                <textarea id="bio-input" class="form-control mb-2" rows="3"
                          placeholder="介紹一下自己…" maxlength="200"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted"><span id="bio-len"><?= mb_strlen($user['bio'] ?? '') ?></span> / 200</small>
                    <button class="btn btn-glow-primary btn-sm" id="btn-save-bio">
                        <i class="bi bi-check-lg me-1"></i> 儲存簡介
                    </button>
                </div>
                <div id="bio-status" class="mt-2"></div>
            </div>
        </div>

        <!-- 外觀設定 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-palette me-1"></i> 外觀
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">選擇你喜歡的介面主題</p>
                <div class="d-flex gap-3">

                    <label class="theme-option flex-fill text-center p-3 rounded border" data-theme="light">
                        <div class="theme-preview light-preview mx-auto mb-2"></div>
                        <div class="fw-semibold"><i class="bi bi-sun"></i> 淺色</div>
                        <small class="text-muted">Light</small>
                    </label>

                    <label class="theme-option flex-fill text-center p-3 rounded border" data-theme="dark">
                        <div class="theme-preview dark-preview mx-auto mb-2"></div>
                        <div class="fw-semibold"><i class="bi bi-moon-stars"></i> 深色</div>
                        <small class="text-muted">Dark</small>
                    </label>

                    <label class="theme-option flex-fill text-center p-3 rounded border" data-theme="auto">
                        <div class="theme-preview auto-preview mx-auto mb-2"></div>
                        <div class="fw-semibold"><i class="bi bi-circle-half"></i> 跟隨系統</div>
                        <small class="text-muted">Auto</small>
                    </label>

                </div>
            </div>
        </div>

        <!-- 主題顏色 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-palette2 me-1"></i> 主題顏色
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">影響按鈕、連結、導覽列等介面元素</p>
                <!-- 預設色票 -->
                <div class="d-flex flex-wrap gap-2 mb-4" id="color-presets"></div>
                <!-- 自訂顏色 -->
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">自訂</span>
                    <input type="color" id="theme-color-picker"
                           style="width:44px;height:36px;padding:2px;cursor:pointer;
                                  border-radius:8px;border:1px solid var(--bs-border-color);">
                    <code id="color-hex-val" class="small"></code>
                    <button class="btn btn-sm btn-outline-secondary ms-auto" id="btn-reset-color">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> 重置預設
                    </button>
                </div>
            </div>
        </div>

        <!-- 按鈕位置 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-arrows-move me-1"></i> 按鈕位置
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">在預覽區內拖曳按鈕調整位置，也可以直接在各頁面拖曳</p>

                <!-- 迷你預覽區 -->
                <div id="pos-preview" style="position:relative;width:100%;height:200px;
                     background:var(--bs-secondary-bg);border-radius:10px;overflow:hidden;
                     border:1px solid var(--bs-border-color);">
                    <!-- 模擬 Navbar -->
                    <div style="height:30px;background:var(--bs-primary,#0d6efd);width:100%;"></div>
                    <!-- 模擬內容 -->
                    <div style="padding:10px 14px;">
                        <div style="height:9px;background:var(--bs-border-color);border-radius:4px;width:55%;margin-bottom:7px;opacity:.5;"></div>
                        <div style="height:7px;background:var(--bs-border-color);border-radius:4px;width:75%;margin-bottom:7px;opacity:.4;"></div>
                        <div style="height:7px;background:var(--bs-border-color);border-radius:4px;width:40%;opacity:.3;"></div>
                    </div>
                    <!-- 新增表單預覽按鈕 -->
                    <div id="preview-fab" title="新增表單"
                         style="position:absolute;width:36px;height:36px;border-radius:50%;
                                background:var(--bs-primary,#0d6efd);color:var(--bs-primary-text,#fff);display:flex;align-items:center;
                                justify-content:center;cursor:grab;z-index:2;user-select:none;
                                box-shadow:0 2px 8px rgba(var(--bs-primary-rgb,13,110,253),.45);font-size:1.1rem;">
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <!-- 聊天預覽按鈕 -->
                    <div id="preview-chat" title="訊息"
                         style="position:absolute;width:36px;height:36px;border-radius:50%;
                                background:var(--bs-primary,#0d6efd);color:var(--bs-primary-text,#fff);display:flex;align-items:center;
                                justify-content:center;cursor:grab;z-index:2;user-select:none;
                                box-shadow:0 2px 8px rgba(var(--bs-primary-rgb,13,110,253),.45);font-size:1rem;">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                </div>

                <div class="d-flex gap-3 mt-2 small text-muted">
                    <span><i class="bi bi-plus-circle-fill text-primary me-1"></i>新增表單</span>
                    <span><i class="bi bi-chat-dots-fill text-primary me-1"></i>聊天</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>位置存於本機，不同裝置各自獨立</small>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-reset-pos">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> 重置預設
                    </button>
                </div>
            </div>
        </div>

        <!-- 卡片動畫效果 -->
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-stars me-1"></i> 卡片動畫效果</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="anim-enabled" role="switch">
                    <label class="form-check-label small text-muted" for="anim-enabled">開啟</label>
                </div>
            </div>
            <div class="card-body" id="anim-body">
                <p class="text-muted small mb-3">首頁卡片 hover 時的彈跳與粒子噴發效果</p>

                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between mb-1">
                        <span><i class="bi bi-arrows-collapse-vertical me-1"></i> 震動幅度</span>
                        <span class="fw-semibold text-primary" id="anim-amp-val">3</span>
                    </label>
                    <input type="range" class="form-range" id="anim-amp" min="1" max="10" step="1">
                    <div class="d-flex justify-content-between text-muted" style="font-size:.7rem;">
                        <span>輕微</span><span>強烈</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label d-flex justify-content-between mb-1">
                        <span><i class="bi bi-lightning-charge me-1"></i> 震動速度</span>
                        <span class="fw-semibold text-primary" id="anim-spd-val">3</span>
                    </label>
                    <input type="range" class="form-range" id="anim-spd" min="1" max="10" step="1">
                    <div class="d-flex justify-content-between text-muted" style="font-size:.7rem;">
                        <span>慢</span><span>快</span>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between py-2 border-top">
                    <span class="small"><i class="bi bi-stars me-1"></i> 粒子噴發</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="anim-ptcl" role="switch">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>設定存於本機，不同裝置各自獨立</small>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-reset-anim">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> 重置預設
                    </button>
                </div>
            </div>
        </div>

        <!-- 素材主題 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-stars me-1"></i> 素材主題
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">為整個網站加入全域環境動畫效果</p>
                <div class="d-flex flex-wrap gap-2" id="ambient-options">
                    <button class="btn btn-sm ambient-opt" data-theme="none">🚫 關閉</button>
                    <button class="btn btn-sm ambient-opt" data-theme="snow">❄️ 下雪</button>
                    <button class="btn btn-sm ambient-opt" data-theme="sakura">🌸 落花</button>
                    <button class="btn btn-sm ambient-opt" data-theme="stars">⭐ 星空</button>
                    <button class="btn btn-sm ambient-opt" data-theme="bubbles">🫧 泡泡</button>
                </div>
                <small class="text-muted mt-3 d-block"><i class="bi bi-info-circle me-1"></i>設定存於本機，不同裝置各自獨立</small>
            </div>
        </div>

        <!-- 帳號資訊 -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person me-1"></i> 帳號資訊
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small">使用者名稱</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">電子郵件</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '—') ?>" readonly>
                </div>
                <div class="mb-0">
                    <label class="form-label text-muted small">身份</label>
                    <input type="text" class="form-control" value="<?= $user['role'] === 'admin' ? '管理員' : '一般會員' ?>" readonly>
                </div>
            </div>
        </div>

        <!-- 加入時間 -->
        <div class="text-center text-muted small">
            <i class="bi bi-calendar3 me-1"></i>
            加入時間：<?= date('Y 年 m 月 d 日', strtotime($user['created_at'])) ?>
        </div>

    </div>
</div>

<script>
window._bgRatio = <?= $bg_ratio ?>;

/* ── 背景比例選擇 ── */
$(document).on('click', '.btn-ratio', function () {
    window._bgRatio = parseInt($(this).data('ratio'));
    $('.btn-ratio').removeClass('btn-glow-primary').addClass('btn-outline-secondary');
    $(this).removeClass('btn-outline-secondary').addClass('btn-glow-primary');
    // 即時更新預覽
    document.getElementById('bg-preview-card').style.aspectRatio = window._bgRatio + '/3';
    $.post('<?= REL_BASE ?>api/save_bg_ratio.php', { ratio: window._bgRatio }, null, 'json');
});

/* ── 背景圖片上傳（含裁切）── */
(function () {
    var bgCropModal = null, bgCropperInstance = null;

    document.getElementById('bg-file-input').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        this.value = '';
        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('bg-crop-img').src = e.target.result;
            if (bgCropperInstance) { bgCropperInstance.destroy(); bgCropperInstance = null; }
            if (!bgCropModal) bgCropModal = new bootstrap.Modal(document.getElementById('bgCropModal'));
            bgCropModal.show();
        };
        reader.readAsDataURL(file);
    });

    $(document).on('shown.bs.modal', '#bgCropModal', function () {
        bgCropperInstance = new Cropper(document.getElementById('bg-crop-img'), {
            aspectRatio: window._bgRatio / 3,
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

    $(document).on('hidden.bs.modal', '#bgCropModal', function () {
        if (bgCropperInstance) { bgCropperInstance.destroy(); bgCropperInstance = null; }
    });

    $(document).on('click', '#btn-bg-crop-confirm', function () {
        if (!bgCropperInstance) return;
        var $status = $('#bg-status');
        bgCropperInstance.getCroppedCanvas({ maxWidth: 2400,
            imageSmoothingEnabled: true, imageSmoothingQuality: 'high' })
        .toBlob(function (blob) {
            bgCropModal.hide();
            $status.html('<span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i>上傳中…</span>');
            var fd = new FormData();
            fd.append('profile_bg', blob, 'bg.jpg');
            $.ajax({
                url: '<?= REL_BASE ?>api/upload_profile_bg.php', type: 'POST',
                data: fd, contentType: false, processData: false,
                success: function (res) {
                    if (res.success) {
                        var img = document.getElementById('profile-bg-img');
                        img.src = res.path;
                        img.style.display = 'block';
                        $status.html('<span class="text-success small"><i class="bi bi-check-circle me-1"></i>背景已更新</span>');
                        setTimeout(function () { $status.html(''); }, 3000);
                    } else {
                        $status.html('<span class="text-danger small">' + res.message + '</span>');
                    }
                },
                error: function () {
                    $status.html('<span class="text-danger small">上傳失敗，請重試</span>');
                }
            });
        }, 'image/jpeg', 0.92);
    });
})();

/* ── 個人簡介 ── */
(function () {
    var $input  = $('#bio-input');
    var $len    = $('#bio-len');
    var $status = $('#bio-status');
    $input.on('input', function () { $len.text($(this).val().length); });
    $('#btn-save-bio').on('click', function () {
        $.post('<?= REL_BASE ?>api/update_bio.php', { bio: $input.val() }, function (res) {
            if (res.success) {
                $status.html('<span class="text-success small"><i class="bi bi-check-circle me-1"></i>已儲存</span>');
                setTimeout(function () { $status.html(''); }, 2500);
            }
        }, 'json');
    });
})();

/* ── 主題顏色 ── */
(function () {
    var DEFAULT = '#6610f2';
    var PRESETS = [
        { name: '靛藍',    color: '#6610f2' },
        { name: '純黑',    color: '#111111' },
        { name: '預設藍',  color: '#0d6efd' },
        { name: '紫色',    color: '#6f42c1' },
        { name: '粉紅',    color: '#d63384' },
        { name: '紅色',    color: '#dc3545' },
        { name: '橙色',    color: '#fd7e14' },
        { name: '綠色',    color: '#198754' },
        { name: '青綠',    color: '#0d9488' },
        { name: '深灰',    color: '#343a40' },
    ];

    var saved   = localStorage.getItem('themeColor') || DEFAULT;
    var picker  = document.getElementById('theme-color-picker');
    var hexVal  = document.getElementById('color-hex-val');
    var $presets = document.getElementById('color-presets');

    // hex → "R,G,B" 字串，供 rgba() 使用
    function hexToRgb(hex) {
        return parseInt(hex.slice(1,3),16)+','+parseInt(hex.slice(3,5),16)+','+parseInt(hex.slice(5,7),16);
    }

    // 渲染預設色票
    PRESETS.forEach(function (p) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.title = p.name;
        btn.dataset.color = p.color;
        var rgb = hexToRgb(p.color);
        btn.style.cssText = 'width:34px;height:34px;border-radius:50%;background:' + p.color +
            ';border:3px solid transparent;cursor:pointer;transition:transform .15s,box-shadow .15s;' +
            'box-shadow:0 0 7px rgba(' + rgb + ',0.45),0 0 14px rgba(' + rgb + ',0.20);';
        btn.addEventListener('click', function () { setColor(p.color); });
        $presets.appendChild(btn);
    });

    // 初始化
    picker.value = saved;
    hexVal.textContent = saved;
    updateActive(saved);

    picker.addEventListener('input', function () { setColor(this.value); });
    document.getElementById('btn-reset-color').addEventListener('click', function () {
        setColor(DEFAULT);
        localStorage.removeItem('themeColor');
    });

    function setColor(hex) {
        localStorage.setItem('themeColor', hex);
        applyThemeColor(hex);
        picker.value = hex;
        hexVal.textContent = hex;
        updateActive(hex);
    }

    function updateActive(hex) {
        $presets.querySelectorAll('button').forEach(function (btn) {
            var active = btn.dataset.color.toLowerCase() === hex.toLowerCase();
            var rgb = hexToRgb(btn.dataset.color);
            btn.style.transform   = active ? 'scale(1.22)' : 'scale(1)';
            btn.style.borderColor = active ? 'rgba(255,255,255,0.85)' : 'transparent';
            btn.style.boxShadow   = active
                ? '0 0 0 3px rgba('+rgb+',0.50),0 0 14px rgba('+rgb+',0.90),0 0 32px rgba('+rgb+',0.42)'
                : '0 0 7px rgba('+rgb+',0.45),0 0 14px rgba('+rgb+',0.20)';
        });
    }
})();
</script>

<!-- 背景圖裁切 Modal -->
<div class="modal fade" id="bgCropModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-crop me-2"></i>裁剪背景圖</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" style="background:#111;">
                <div style="max-height:380px;overflow:hidden;">
                    <img id="bg-crop-img" src="" style="display:block;max-width:100%;" alt="">
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>拖曳調整範圍，滾輪縮放</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-glow-primary" id="btn-bg-crop-confirm">
                    <i class="bi bi-check-lg me-1"></i>確認裁剪
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 頭像裁切 Modal -->
<div class="modal fade" id="cropModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-crop me-2"></i>裁剪頭像</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2" style="background:#111;">
                <div style="max-height:380px;overflow:hidden;">
                    <img id="crop-img" src="" style="display:block;max-width:100%;" alt="">
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="bi bi-info-circle me-1"></i>拖曳調整範圍，滾輪縮放</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-glow-primary" id="btn-crop-confirm">
                    <i class="bi bi-check-lg me-1"></i>確認裁剪
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
(function () {
    var cropModal = null;
    var cropperInstance = null;

    document.getElementById('avatar-file-input').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        this.value = ''; // 允許重複選同一檔案

        var reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('crop-img').src = e.target.result;
            if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
            if (!cropModal) cropModal = new bootstrap.Modal(document.getElementById('cropModal'));
            cropModal.show();
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('cropModal').addEventListener('shown.bs.modal', function () {
        cropperInstance = new Cropper(document.getElementById('crop-img'), {
            aspectRatio: 1,
            viewMode: 2,
            dragMode: 'move',
            autoCropArea: 0.85,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
        });
    });

    document.getElementById('cropModal').addEventListener('hidden.bs.modal', function () {
        if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
    });

    document.getElementById('btn-crop-confirm').addEventListener('click', function () {
        if (!cropperInstance) return;
        var $status = $('#avatar-status');
        cropperInstance.getCroppedCanvas({ width: 300, height: 300,
            imageSmoothingEnabled: true, imageSmoothingQuality: 'high' })
        .toBlob(function (blob) {
            cropModal.hide();
            // 預覽
            var url = URL.createObjectURL(blob);
            document.getElementById('avatar-preview').innerHTML =
                '<img src="' + url + '" style="width:100%;height:100%;object-fit:cover;" alt="頭像">';
            // 上傳
            $status.html('<span class="text-muted small"><i class="bi bi-arrow-repeat me-1"></i>上傳中…</span>');
            var fd = new FormData();
            fd.append('avatar', blob, 'avatar.jpg');
            $.ajax({
                url: '<?= REL_BASE ?>api/upload_avatar.php', type: 'POST',
                data: fd, contentType: false, processData: false,
                success: function (res) {
                    if (res.success) {
                        $status.html('<span class="text-success small"><i class="bi bi-check-circle me-1"></i>頭像已更新</span>');
                        setTimeout(function () { $status.html(''); }, 3000);
                        // 同步右上角 navbar 頭像
                        $('.site-avatar').each(function () {
                            var el = this;
                            if (el.tagName === 'IMG') {
                                el.src = res.path;
                            } else {
                                // 字母頭像 → 換成圖片
                                var img = document.createElement('img');
                                img.src = res.path;
                                img.className = 'site-avatar';
                                img.style.cssText = el.style.cssText + 'object-fit:cover;';
                                img.alt = '';
                                el.parentNode.replaceChild(img, el);
                            }
                        });
                    } else {
                        $status.html('<span class="text-danger small">' + res.message + '</span>');
                    }
                },
                error: function () {
                    $status.html('<span class="text-danger small">上傳失敗，請重試</span>');
                }
            });
        }, 'image/jpeg', 0.92);
    });
})();
</script>

<style>
.theme-option {
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
    user-select: none;
}
.theme-option:hover {
    border-color: var(--bs-primary, #0d6efd) !important;
}
.theme-option.active {
    border-color: var(--bs-primary, #0d6efd) !important;
    box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb, 13,110,253),.2);
}
.theme-preview {
    width: 56px;
    height: 40px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}
.light-preview {
    background: linear-gradient(135deg, #ffffff 50%, #e9ecef 50%);
}
.dark-preview {
    background: linear-gradient(135deg, #212529 50%, #343a40 50%);
}
.auto-preview {
    background: linear-gradient(135deg, #ffffff 50%, #212529 50%);
}

/* ── 動畫設定滑桿軌道：淺色模式下補深軌道色 ── */
#anim-body .form-range::-webkit-slider-runnable-track {
    background: rgba(0,0,0,.18);
    height: 6px;
    border-radius: 3px;
}
#anim-body .form-range::-moz-range-track {
    background: rgba(0,0,0,.18);
    height: 6px;
    border-radius: 3px;
}
[data-bs-theme="dark"] #anim-body .form-range::-webkit-slider-runnable-track {
    background: rgba(255,255,255,.22);
}
[data-bs-theme="dark"] #anim-body .form-range::-moz-range-track {
    background: rgba(255,255,255,.22);
}

/* ── 深色模式：未勾選 switch 補亮底色 ── */
[data-bs-theme="dark"] #anim-body .form-switch .form-check-input:not(:checked),
[data-bs-theme="dark"] .card-header    .form-switch .form-check-input:not(:checked) {
    background-color: rgba(255,255,255,.18);
    border-color:     rgba(255,255,255,.30);
    /* switch 圓點改白色，才看得清楚 */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3E%3Ccircle r='3' fill='rgba(255,255,255,.75)'/%3E%3C/Svg%3E");
}

/* ── 幅度 / 速度數值光影 ── */
#anim-amp-val,
#anim-spd-val {
    color: #fff !important;
    background: rgba(var(--bs-primary-rgb, 13,110,253), 0.20);
    border-radius: 4px;
    padding: 0 6px;
    font-size: .8rem;
    line-height: 1.6;
    box-shadow: 0 0 8px rgba(var(--bs-primary-rgb, 13,110,253), 0.55), 0 0 18px rgba(var(--bs-primary-rgb, 13,110,253), 0.22);
}
</style>

<script>
(function () {
    const options = document.querySelectorAll('.theme-option');
    const root    = document.getElementById('html-root');

    function applyTheme(t) {
        if (t === 'auto') {
            const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        } else {
            root.setAttribute('data-bs-theme', t);
        }
    }

    function setActive(t) {
        options.forEach(el => el.classList.toggle('active', el.dataset.theme === t));
    }

    // 初始化
    const saved = localStorage.getItem('theme') || 'auto';
    setActive(saved);

    options.forEach(el => {
        el.addEventListener('click', function () {
            const t = this.dataset.theme;
            localStorage.setItem('theme', t);
            applyTheme(t);
            setActive(t);
        });
    });
})();
</script>

<script>
// 設定頁：預覽區拖曳邏輯
window.addEventListener('load', function () {
    var preview  = document.getElementById('pos-preview');
    var prevFab  = document.getElementById('preview-fab');
    var prevChat = document.getElementById('preview-chat');
    var BTN = 36; // 預覽按鈕尺寸

    // 將 viewport 比例位置換算到預覽區座標
    function posToPreview(pos) {
        var pw = preview.offsetWidth, ph = preview.offsetHeight;
        return {
            left: Math.max(0, Math.min(pw - BTN, pos.x * pw)),
            top:  Math.max(0, Math.min(ph - BTN, pos.y * ph))
        };
    }

    // 將預覽區座標換算回 viewport 比例
    function previewToPos(left, top) {
        return {
            x: left / preview.offsetWidth,
            y: top  / preview.offsetHeight
        };
    }

    // 同步預覽區按鈕到已儲存位置
    function syncPreview() {
        var saved = window.FAB_DRAG.load();
        var fp = saved.fab  || window.FAB_DRAG.DEFAULTS.fab;
        var cp = saved.chat || window.FAB_DRAG.DEFAULTS.chat;
        var fPos = posToPreview(fp), cPos = posToPreview(cp);
        prevFab.style.left  = fPos.left + 'px'; prevFab.style.top  = fPos.top + 'px';
        prevChat.style.left = cPos.left + 'px'; prevChat.style.top = cPos.top + 'px';
    }

    // 讓預覽按鈕可拖曳，並同步更新實際按鈕
    function makePreviewDraggable(el, key, actualSelector) {
        var startX, startY, startL, startT;

        el.addEventListener('mousedown', function (e) {
            e.preventDefault();
            startX = e.clientX; startY = e.clientY;
            startL = el.offsetLeft; startT = el.offsetTop;
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', end);
        });
        el.addEventListener('touchstart', function (e) {
            e.preventDefault();
            var t = e.touches[0];
            startX = t.clientX; startY = t.clientY;
            startL = el.offsetLeft; startT = el.offsetTop;
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', end);
        }, { passive: false });

        function move(e) {
            if (e.cancelable) e.preventDefault();
            var p = e.touches ? e.touches[0] : e;
            var pw = preview.offsetWidth, ph = preview.offsetHeight;
            var newL = Math.max(0, Math.min(pw - BTN, startL + p.clientX - startX));
            var newT = Math.max(0, Math.min(ph - BTN, startT + p.clientY - startY));
            el.style.left = newL + 'px'; el.style.top = newT + 'px';

            // 即時更新實際按鈕
            var pos = previewToPos(newL, newT);
            var actual = document.querySelector(actualSelector);
            if (actual) window.FAB_DRAG.applyToEl(actual, pos.x, pos.y, 72);
        }

        function end(e) {
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup', end);
            document.removeEventListener('touchmove', move);
            document.removeEventListener('touchend', end);
            var pos = previewToPos(el.offsetLeft, el.offsetTop);
            var saved = window.FAB_DRAG.load();
            saved[key] = pos;
            window.FAB_DRAG.save(saved);
        }
    }

    syncPreview();
    makePreviewDraggable(prevFab,  'fab',  '.fab-btn');
    makePreviewDraggable(prevChat, 'chat', '#chat-widget');

    document.getElementById('btn-reset-pos').addEventListener('click', function () {
        window.FAB_DRAG.reset();
    });
});
</script>

<script>
/* ── 卡片動畫效果設定 ── */
(function () {
    var KEY     = 'cardAnim';
    var DEFAULT = { enabled: true, amplitude: 6, speed: 6, particles: true };

    function load() {
        try { return Object.assign({}, DEFAULT, JSON.parse(localStorage.getItem(KEY) || '{}')); }
        catch (e) { return Object.assign({}, DEFAULT); }
    }
    function save(cfg) { localStorage.setItem(KEY, JSON.stringify(cfg)); }

    var cfg      = load();
    var $enabled = document.getElementById('anim-enabled');
    var $body    = document.getElementById('anim-body');
    var $amp     = document.getElementById('anim-amp');
    var $ampVal  = document.getElementById('anim-amp-val');
    var $spd     = document.getElementById('anim-spd');
    var $spdVal  = document.getElementById('anim-spd-val');
    var $ptcl    = document.getElementById('anim-ptcl');

    function syncUI() {
        $enabled.checked    = cfg.enabled;
        $amp.value          = cfg.amplitude;
        $ampVal.textContent = cfg.amplitude;
        $spd.value          = cfg.speed;
        $spdVal.textContent = cfg.speed;
        $ptcl.checked       = cfg.particles;
        updateDisabled();
    }

    function updateDisabled() {
        var off = !cfg.enabled;
        $body.style.opacity = off ? '0.5' : '1';
        [$amp, $spd, $ptcl].forEach(function (el) { el.disabled = off; });
    }

    syncUI();

    $enabled.addEventListener('change', function () {
        cfg.enabled = this.checked; save(cfg); updateDisabled(); window.applyCardAnimCSS();
    });
    $amp.addEventListener('input', function () {
        cfg.amplitude = parseInt(this.value); $ampVal.textContent = cfg.amplitude; save(cfg); window.applyCardAnimCSS();
    });
    $spd.addEventListener('input', function () {
        cfg.speed = parseInt(this.value); $spdVal.textContent = cfg.speed; save(cfg); window.applyCardAnimCSS();
    });
    $ptcl.addEventListener('change', function () {
        cfg.particles = this.checked; save(cfg); window.applyCardAnimCSS();
    });
    document.getElementById('btn-reset-anim').addEventListener('click', function () {
        cfg = Object.assign({}, DEFAULT); save(cfg); syncUI(); window.applyCardAnimCSS();
    });
})();
</script>

<script>
/* ── 素材主題選擇器 ── */
(function () {
    var KEY   = 'ambientTheme';
    var saved = localStorage.getItem(KEY) || 'none';

    function updateActive(t) {
        document.querySelectorAll('.ambient-opt').forEach(function (btn) {
            var active = btn.dataset.theme === t;
            var rgb = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary-rgb').trim() || '13,110,253';
            btn.style.background  = active ? 'rgba(' + rgb + ',0.18)' : '';
            btn.style.color       = active ? '#fff' : '';
            btn.style.borderColor = active ? 'rgba(' + rgb + ',0.60)' : '';
            btn.style.boxShadow   = active ? '0 0 14px rgba(' + rgb + ',0.60),0 0 32px rgba(' + rgb + ',0.25)' : '';
        });
    }

    updateActive(saved);

    document.querySelectorAll('.ambient-opt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = this.dataset.theme;
            localStorage.setItem(KEY, t);
            updateActive(t);
            if (window.startAmbientTheme) window.startAmbientTheme(t);
        });
    });
})();
</script>

<?php require_once '../config/footer.php'; ?>
