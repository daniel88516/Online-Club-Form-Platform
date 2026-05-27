<?php
require_once '../config/session.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

// ── 設定 ──
define('MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXTS',  ['jpg', 'jpeg', 'png', 'gif', 'webp']);

$type = $_POST['type'] ?? 'comments'; // 'comments' 或 'forms'
$dir  = in_array($type, ['comments', 'forms']) ? $type : 'comments';
$uploadDir = __DIR__ . "/../uploads/$dir/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '請選擇圖片']);
    exit();
}

$file = $_FILES['image'];

// 檢查大小
if ($file['size'] > MAX_SIZE) {
    echo json_encode(['success' => false, 'message' => '圖片不能超過 5MB']);
    exit();
}

// 檢查 MIME 類型
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, ALLOWED_TYPES)) {
    echo json_encode(['success' => false, 'message' => '只允許 JPG / PNG / GIF / WebP']);
    exit();
}

// 檢查副檔名
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXTS)) {
    echo json_encode(['success' => false, 'message' => '不支援的檔案格式']);
    exit();
}

// 產生唯一檔名
$filename = uniqid('img_', true) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => '上傳失敗，請重試']);
    exit();
}

$path = "/uploads/$dir/$filename";
echo json_encode(['success' => true, 'path' => $path]);
