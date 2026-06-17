<?php
ob_start();
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/upload_cleanup.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '請選擇圖片']);
    exit();
}

$file = $_FILES['avatar'];

if ($file['size'] > 2 * 1024 * 1024) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '圖片不能超過 2MB']);
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
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '只允許 JPG / PNG / WebP']);
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '不支援的檔案格式']);
    exit();
}

$user_id  = $_SESSION['user_id'];
$filename = 'avatar_' . $user_id . '_' . uniqid() . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '上傳失敗，請重試']);
    exit();
}

$path = '/uploads/avatars/' . $filename;
$old_path = null;
$old_stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$old_stmt->bind_param('i', $user_id);
$old_stmt->execute();
$old_row = $old_stmt->get_result()->fetch_assoc();
$old_path = $old_row['avatar'] ?? null;
$old_stmt->close();

$stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->bind_param('si', $path, $user_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    @unlink($dest);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '銝憭望?嚗??岫']);
    exit();
}

cleanupReplacedUploads($conn, [$old_path], [$path]);

ob_end_clean();
echo json_encode(['success' => true, 'path' => $path]);
