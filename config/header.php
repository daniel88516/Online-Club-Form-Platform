<?php
require_once __DIR__ . '/session.php';
?>
<!DOCTYPE html>
<html lang="zh-TW" id="html-root">
<head>
    <script>
        /* 在 CSS 載入前套用主題，避免畫面閃爍 */
        (function () {
            var t = localStorage.getItem('theme');
            var isDark;
            if (t === 'dark')       isDark = true;
            else if (t === 'light') isDark = false;
            else if (t === 'auto')  isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            else                    isDark = true; /* 預設深色 */

            document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
            /* 直接寫 inline style，確保背景立即生效，不依賴 CSS 載入時序 */
            if (isDark) {
                document.documentElement.style.background = '#000';
                document.documentElement.style.colorScheme = 'dark';
            }

            /* 提前套用主題顏色，讓 navbar（bg-primary）不會閃爍 */
            var c = localStorage.getItem('themeColor') || '#6610f2';
            if (c && /^#[0-9a-fA-F]{6}$/.test(c)) {
                var n = parseInt(c.slice(1), 16);
                var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
                document.documentElement.style.setProperty('--bs-primary', c);
                document.documentElement.style.setProperty('--bs-primary-rgb', r + ',' + g + ',' + b);
                /* 預先設定對比文字色，避免 navbar 文字閃白 */
                var bri = (r * 299 + g * 587 + b * 114) / 1000;
                document.documentElement.style.setProperty('--bs-primary-text', bri > 140 ? '#000000' : '#ffffff');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>線上表單系統</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="/js/theme-color.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/js/theme-color.js') ?>"></script>
    <script>(function(){ var c = localStorage.getItem('themeColor') || '#6610f2'; applyThemeColor(c); })();</script>
    <style>
        /* ── 導覽列主題切換三段式元件 ── */
        .nav-theme-seg {
            display: flex;
            background: rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 2px;
            gap: 2px;
        }
        .nav-theme-btn {
            flex: 1;
            border: none;
            border-radius: 6px;
            padding: 3px 4px;
            font-size: 0.74rem;
            font-weight: 600;
            cursor: pointer;
            background: transparent;
            color: var(--bs-body-color);
            transition: background .18s, box-shadow .18s;
            white-space: nowrap;
        }
        .nav-theme-btn.active {
            background: var(--bs-secondary-bg);
            box-shadow: 0 1px 4px rgba(0,0,0,.22);
        }
        .nav-theme-btn:not(.active):hover {
            background: rgba(255,255,255,0.12);
        }
        /* Quill 內容顯示（留言 / 說明） */
        .ql-content img { max-width:100%; border-radius:4px; margin:4px 0; cursor:pointer; display:block; }
        .ql-content p   { margin-bottom:0.2rem; }
        .ql-content p:last-child { margin-bottom:0; }
        .ql-content a   { color:var(--bs-primary, #0d6efd); }
        .ql-content ul, .ql-content ol { padding-left:1.2rem; margin-bottom:0.2rem; }
        /* 留言框編輯器最小高度 */
        .quill-comment-editor .ql-editor { min-height:70px; font-size:0.875rem; }
        /* 說明框編輯器最小高度 */
        .quill-desc-editor .ql-editor { min-height:120px; }
    </style>
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top" style="position:relative;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">
            <i class="bi bi-file-earmark-text"></i> 線上表單系統
        </a>
        <?php if (!empty($showNavSearch)): ?>
        <!-- 搜尋群組：絕對置中於 navbar -->
        <div style="position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:4px;">
            <button id="nav-search-toggle" class="btn btn-sm p-1"
                    title="搜尋" style="color:rgba(255,255,255,0.85);background:transparent;border:none;line-height:1;">
                <i class="bi bi-search" style="font-size:1rem;"></i>
            </button>
            <div id="nav-search-box" class="d-none d-flex align-items-center" style="gap:4px;">
                <input type="text" id="nav-search-input" placeholder="搜尋..."
                       autocomplete="off"
                       style="background:rgba(255,255,255,0.15);border:none;border-radius:6px;
                              padding:3px 10px;color:#fff;font-size:.875rem;width:260px;outline:none;
                              caret-color:#fff;">
                <button id="nav-search-close"
                        style="background:transparent;border:none;color:rgba(255,255,255,0.7);
                               cursor:pointer;font-size:.85rem;line-height:1;padding:2px 4px;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <span class="me-auto"></span>
        <?php else: ?>
        <span class="me-auto"></span>
        <?php endif; ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto"></ul>
            <ul class="navbar-nav align-items-center">
                <?php if (isLoggedIn()):
                    $uid = $_SESSION['user_id'];
                    $unread_msg = 0;
                    $nav_avatar = null;
                    if (isset($conn)) {
                        $unread_msg = mysqli_fetch_assoc(mysqli_query($conn,
                            "SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = $uid AND is_read = 0"
                        ))['cnt'] ?? 0;
                        $nav_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT avatar FROM users WHERE id = $uid"));
                        $nav_avatar = $nav_row['avatar'] ?? null;
                    }
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 py-2" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <?php if (!empty($nav_avatar)): ?>
                            <img src="<?= htmlspecialchars($nav_avatar) ?>" class="site-avatar"
                                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;" alt="">
                        <?php else: ?>
                            <span class="site-avatar" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.25);
                                         display:inline-flex;align-items:center;justify-content:center;
                                         font-weight:700;font-size:1rem;flex-shrink:0;">
                                <?= mb_strtoupper(mb_substr($_SESSION['username'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>
                        <span class="fw-semibold" style="font-size:1rem;"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (isMember()): ?>
                        <li><a class="dropdown-item" href="/profile.php?id=<?= $_SESSION['user_id'] ?>"><i class="bi bi-person me-2"></i> 個人頁面</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/member/my_forms.php"><i class="bi bi-journal-text me-2"></i> 我的表單</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/clubs.php"><i class="bi bi-people me-2"></i> 社團</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                        <li><a class="dropdown-item" href="/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i> 管理後台</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <!-- 主題切換 -->
                        <li>
                            <div style="padding: 4px 16px">
                                <div class="nav-theme-seg">
                                    <button type="button" class="nav-theme-btn" data-t="light"><i class="bi bi-sun"></i> 淺色</button>
                                    <button type="button" class="nav-theme-btn" data-t="auto"><i class="bi bi-display"></i> 系統</button>
                                    <button type="button" class="nav-theme-btn" data-t="dark"><i class="bi bi-moon"></i> 深色</button>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/member/settings.php"><i class="bi bi-gear me-2"></i> 設定</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i> 登出</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/login.php"><i class="bi bi-box-arrow-in-right"></i> 登入</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/register.php"><i class="bi bi-person-plus"></i> 註冊</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>


<script>
(function () {
    function getCur() { return localStorage.getItem('theme') || 'auto'; }

    function applyTheme(t) {
        var isDark;
        if (t === 'dark')       { localStorage.setItem('theme', 'dark');  isDark = true;  }
        else if (t === 'light') { localStorage.setItem('theme', 'light'); isDark = false; }
        else                    { localStorage.removeItem('theme');
                                  isDark = window.matchMedia('(prefers-color-scheme: dark)').matches; }

        var root = document.documentElement;
        root.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
        if (isDark) {
            root.style.background   = '#000';
            root.style.colorScheme  = 'dark';
        } else {
            root.style.background   = '';
            root.style.colorScheme  = '';
        }
        /* 更新所有頁面上的三段選鈕（可能有多個頁面實例） */
        syncButtons(t);
    }

    function syncButtons(t) {
        document.querySelectorAll('.nav-theme-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-t') === t);
        });
    }

    /* 初始化：標記當前作用中的按鈕 */
    syncButtons(getCur());

    /* 點擊切換 */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.nav-theme-btn');
        if (!btn) return;
        e.stopPropagation();
        applyTheme(btn.getAttribute('data-t'));
    });

    /* 監聽系統偏好變更（只在「系統」模式下有效） */
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (getCur() === 'auto') applyTheme('auto');
    });
})();
</script>
<div class="container mt-4">
