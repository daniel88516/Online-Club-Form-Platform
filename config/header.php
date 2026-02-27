<?php
require_once __DIR__ . '/session.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>線上表單系統</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">
            <i class="bi bi-file-earmark-text"></i> 線上表單系統
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php"><i class="bi bi-house"></i> 首頁</a>
                </li>
                <?php if (isMember()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/member/my_forms.php"><i class="bi bi-journal-text"></i> 我的表單</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/member/create_form.php"><i class="bi bi-plus-circle"></i> 新增表單</a>
                </li>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/dashboard.php"><i class="bi bi-speedometer2"></i> 管理後台</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> 登出</a></li>
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
<div class="container mt-4">
