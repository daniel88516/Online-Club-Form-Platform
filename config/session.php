<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('APP_BASE')) {
    // 自動偵測部署子目錄（根目錄部署＝''，子目錄部署＝'/group_13' 等）
    $docRoot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $projRoot = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..')), '/');
    define('APP_BASE', str_replace($docRoot, '', $projRoot));
}

if (!defined('REL_BASE')) {
    // HTML 資源相對前綴，根目錄頁面=''，member/admin 頁面='../'
    $scriptRel = str_replace(APP_BASE, '', str_replace('\\', '/', $_SERVER['SCRIPT_NAME']));
    $depth     = substr_count(trim($scriptRel, '/'), '/');
    define('REL_BASE', str_repeat('../', $depth));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isMember() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'member' || $_SESSION['role'] === 'admin');
}

function assetUrl($path) {
    if (empty($path)) {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $path) || preg_match('#^data:#i', $path)) {
        return $path;
    }

    if (strpos($path, '/uploads/') === 0) {
        return APP_BASE . $path;
    }

    return $path;
}

function assetHtml($html) {
    if ($html === null || $html === '') {
        return $html;
    }

    return preg_replace_callback(
        '#(<img\b[^>]*\bsrc=["\'])(/uploads/[^"\']+)(["\'])#i',
        function ($matches) {
            return $matches[1] . assetUrl($matches[2]) . $matches[3];
        },
        $html
    );
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_BASE . '/login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_BASE . '/login.php');
        exit();
    }
    if (!isAdmin()) {
        header('Location: ' . APP_BASE . '/login.php');
        exit();
    }
}

/**
 * 取得（或自動建立）系統通知帳號的 user_id
 */
function getSystemUserId($conn) {
    $res = mysqli_query($conn, "SELECT id FROM users WHERE username='系統' LIMIT 1");
    if ($row = mysqli_fetch_assoc($res)) return (int)$row['id'];
    // 首次執行：建立系統帳號（無法登入，密碼為空）
    mysqli_query($conn, "INSERT INTO users (username, password, email, role) VALUES ('系統', '', 'system@localhost', 'member')");
    return (int)mysqli_insert_id($conn);
}

/**
 * 渲染用戶頭像：有圖片顯示圖片，否則顯示姓名第一字母的圓形
 * $size 單位 px
 */
function renderAvatar($username, $avatar = null, $size = 36) {
    $s = "width:{$size}px;height:{$size}px;border-radius:50%;flex-shrink:0;";
    if (!empty($avatar)) {
        return '<img src="' . htmlspecialchars(assetUrl($avatar)) . '" class="site-avatar" style="' . $s . 'object-fit:cover;" alt="">';
    }
    $fs = round($size * 0.44);
    $initial = htmlspecialchars(mb_strtoupper(mb_substr($username, 0, 1)));
    return '<span class="site-avatar" style="' . $s . 'background:var(--bs-primary,#0d6efd);color:var(--bs-primary-text,#fff);display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:' . $fs . 'px;">' . $initial . '</span>';
}

/**
 * 渲染頭像（點擊後觸發全域彈出選單）
 * $uid      = 該頭像對應的 user id
 * $cur_uid  = 目前登入者的 user id（0 = 未登入）
 */
function renderAvatarDropdown($username, $avatar, $uid, $size = 36, $cur_uid = 0) {
    $avatar_html = renderAvatar($username, $avatar, $size);
    $uid_int     = intval($uid);
    $show_chat   = ($cur_uid && intval($cur_uid) !== $uid_int) ? 1 : 0;
    $uattr       = htmlspecialchars($username, ENT_QUOTES);

    $aattr = htmlspecialchars(assetUrl($avatar ?? ''), ENT_QUOTES);
    return '<span class="avd-wrap" data-uid="' . $uid_int . '" data-uname="' . $uattr . '" data-avatar="' . $aattr . '" data-chat="' . $show_chat . '">'
         . $avatar_html
         . '</span>';
}

/**
 * 把 HTML 中的裸 URL 自動轉成可點擊的 <a> 超連結
 * 已包在 <a> 裡的不會重複處理
 */
function autolink($html) {
    // 以現有 <a>...</a> 為分界切割，避免對已有連結重複包裹
    $parts = preg_split('/(<a\b[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => &$part) {
        if ($i % 2 === 0) {
            // 非 <a> 區段：將裸 URL 替換成超連結
            $part = preg_replace(
                '/(https?:\/\/[^\s<>"\']+)/i',
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
                $part
            );
        }
    }
    return implode('', $parts);
}
