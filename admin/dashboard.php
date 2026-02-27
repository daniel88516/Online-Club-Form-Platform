<?php
$pageTitle = '管理後台';
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

// 統計
$total_users   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users"))['cnt'];
$total_forms   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM forms"))['cnt'];
$total_resp    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_responses"))['cnt'];
$total_groups  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM `groups`"))['cnt'];

require_once '../config/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-speedometer2"></i> 管理後台</h4>

<!-- 統計卡片 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-primary text-white">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= $total_users ?></h2>
                <p class="mb-0"><i class="bi bi-people"></i> 會員數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-success text-white">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= $total_forms ?></h2>
                <p class="mb-0"><i class="bi bi-file-earmark-text"></i> 表單數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-info text-white">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= $total_resp ?></h2>
                <p class="mb-0"><i class="bi bi-pencil-square"></i> 填答數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-warning text-white">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= $total_groups ?></h2>
                <p class="mb-0"><i class="bi bi-diagram-3"></i> 群組數</p>
            </div>
        </div>
    </div>
</div>

<!-- 快速連結 -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold"><i class="bi bi-people text-primary"></i> 會員管理</h5>
                <p class="text-muted small">查看、修改、刪除會員帳號</p>
                <a href="/admin/members.php" class="btn btn-outline-primary btn-sm">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold"><i class="bi bi-file-earmark-text text-success"></i> 表單管理</h5>
                <p class="text-muted small">查看、刪除所有表單</p>
                <a href="/admin/forms.php" class="btn btn-outline-success btn-sm">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold"><i class="bi bi-diagram-3 text-warning"></i> 群組管理</h5>
                <p class="text-muted small">新增、修改、刪除群組</p>
                <a href="/admin/groups.php" class="btn btn-outline-warning btn-sm">進入管理</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../config/footer.php'; ?>
