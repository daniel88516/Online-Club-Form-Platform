<?php
/**
 * PHPMailer 郵件設定
 * 使用 Gmail SMTP 發信
 *
 * Gmail 設定步驟：
 * 1. 登入 Gmail → 帳戶設定 → 安全性
 * 2. 開啟「兩步驟驗證」
 * 3. 搜尋「應用程式密碼」→ 新增 → 選「郵件」→ 複製 16 碼密碼
 * 4. 將該密碼填入 MAIL_PASS 欄位
 */

define('MAIL_HOST',     'smtp.gmail.com');   // SMTP 伺服器
define('MAIL_PORT',     587);                // TLS port
define('MAIL_USERNAME', 'your@gmail.com');   // ← 換成你的 Gmail
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx'); // ← 換成應用程式密碼（16碼）
define('MAIL_FROM',     'your@gmail.com');   // 寄件人地址
define('MAIL_FROM_NAME', '表單系統通知');    // 寄件人名稱
