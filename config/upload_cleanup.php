<?php

function normalizeUploadPath($path) {
    if (!is_string($path) || trim($path) === '') {
        return null;
    }

    $path = trim($path);
    $urlPath = parse_url($path, PHP_URL_PATH);
    if (is_string($urlPath) && $urlPath !== '') {
        $path = $urlPath;
    }

    if (defined('APP_BASE') && APP_BASE !== '' && strpos($path, APP_BASE . '/uploads/') === 0) {
        $path = substr($path, strlen(APP_BASE));
    }

    $path = rawurldecode(str_replace('\\', '/', $path));

    if (!preg_match('#^/uploads/[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+$#', $path)) {
        return null;
    }

    return $path;
}

function uploadPathToFile($path) {
    $path = normalizeUploadPath($path);
    if (!$path) {
        return null;
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $uploadsRoot = realpath($projectRoot . '/uploads');
    if (!$projectRoot || !$uploadsRoot) {
        return null;
    }

    $relative = ltrim(substr($path, strlen('/uploads/')), '/');
    $filePath = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $dirReal = realpath(dirname($filePath));
    if (!$dirReal || strpos($dirReal, $uploadsRoot) !== 0) {
        return null;
    }

    return $filePath;
}

function extractUploadPathsFromHtml($html) {
    if (!is_string($html) || $html === '') {
        return [];
    }

    preg_match_all('#<img\b[^>]*\bsrc=["\']([^"\']+)["\']#i', $html, $matches);
    $paths = [];
    foreach ($matches[1] ?? [] as $src) {
        $path = normalizeUploadPath($src);
        if ($path) {
            $paths[$path] = true;
        }
    }

    return array_keys($paths);
}

function isUploadPathReferenced($conn, $path) {
    $path = normalizeUploadPath($path);
    if (!$path) {
        return true;
    }

    $exactChecks = [
        ['users', 'avatar'],
        ['users', 'profile_bg'],
        ['forms', 'cover_image'],
        ['clubs', 'cover_image'],
    ];

    foreach ($exactChecks as $check) {
        [$table, $column] = $check;
        $sql = "SELECT 1 FROM `$table` WHERE `$column` = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 's', $path);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_fetch_assoc($res)) {
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
    }

    $likeChecks = [
        ['forms', 'description'],
        ['form_comments', 'content'],
    ];
    $like = '%' . $path . '%';

    foreach ($likeChecks as $check) {
        [$table, $column] = $check;
        $sql = "SELECT 1 FROM `$table` WHERE `$column` LIKE ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 's', $like);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_fetch_assoc($res)) {
            mysqli_stmt_close($stmt);
            return true;
        }
        mysqli_stmt_close($stmt);
    }

    return false;
}

function deleteUploadIfUnreferenced($conn, $path) {
    $path = normalizeUploadPath($path);
    if (!$path || isUploadPathReferenced($conn, $path)) {
        return false;
    }

    $filePath = uploadPathToFile($path);
    if (!$filePath || !is_file($filePath)) {
        return false;
    }

    return @unlink($filePath);
}

function cleanupReplacedUploads($conn, array $oldPaths, array $newPaths = []) {
    $keep = [];
    foreach ($newPaths as $path) {
        $normalized = normalizeUploadPath($path);
        if ($normalized) {
            $keep[$normalized] = true;
        }
    }

    foreach ($oldPaths as $path) {
        $normalized = normalizeUploadPath($path);
        if ($normalized && empty($keep[$normalized])) {
            deleteUploadIfUnreferenced($conn, $normalized);
        }
    }
}
