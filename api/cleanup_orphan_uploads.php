<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/upload_cleanup.php';
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => '沒有權限']);
    exit();
}

$uploadsRoot = realpath(__DIR__ . '/../uploads');
if (!$uploadsRoot) {
    echo json_encode(['success' => true, 'deleted_count' => 0, 'deleted_size_mb' => 0, 'failed_count' => 0]);
    exit();
}

$deleted = [];
$failed = [];
$deletedSize = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $fullPath = str_replace('\\', '/', $file->getPathname());
    $rootPath = str_replace('\\', '/', $uploadsRoot);
    $uploadPath = '/uploads' . substr($fullPath, strlen($rootPath));
    $uploadPath = str_replace('\\', '/', $uploadPath);

    if (isUploadPathReferenced($conn, $uploadPath)) {
        continue;
    }

    $size = $file->getSize();
    if (deleteUploadIfUnreferenced($conn, $uploadPath)) {
        $deleted[] = $uploadPath;
        $deletedSize += $size;
    } else {
        $failed[] = $uploadPath;
    }
}

echo json_encode([
    'success' => true,
    'deleted_count' => count($deleted),
    'deleted_size_mb' => round($deletedSize / 1048576, 2),
    'failed_count' => count($failed),
    'failed' => $failed,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
