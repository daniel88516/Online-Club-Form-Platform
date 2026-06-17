<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/upload_cleanup.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

define('MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXTS',  ['jpg', 'jpeg', 'png', 'gif', 'webp']);

$type = $_POST['type'] ?? 'comments';
$dir  = in_array($type, ['comments', 'forms', 'clubs'], true) ? $type : 'comments';
$uploadDir = __DIR__ . "/../uploads/$dir/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '請選擇圖片']);
    exit();
}

$file = $_FILES['image'];

if ($file['size'] > MAX_SIZE) {
    echo json_encode(['success' => false, 'message' => '圖片不可超過 5MB']);
    exit();
}

$mime = null;
if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}
if (!is_string($mime) || $mime === '') {
    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = is_array($imageInfo) && isset($imageInfo['mime']) ? $imageInfo['mime'] : null;
}
if (!in_array($mime, ALLOWED_TYPES, true)) {
    echo json_encode(['success' => false, 'message' => '僅允許 JPG / PNG / GIF / WebP']);
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXTS, true)) {
    echo json_encode(['success' => false, 'message' => '不支援的副檔名']);
    exit();
}

$filename = uniqid('img_', true) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => '上傳失敗，請稍後再試']);
    exit();
}

$path = "/uploads/$dir/$filename";
if (!empty($_POST['old_path'])) {
    cleanupReplacedUploads($conn, [$_POST['old_path']], [$path]);
}

echo json_encode(['success' => true, 'path' => $path]);
