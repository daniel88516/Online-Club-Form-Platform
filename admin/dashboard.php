<?php
$pageTitle = '管理後台';
require_once '../config/session.php';
require_once '../config/db.php';
requireAdmin();

// 統計
$total_users          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users"))['cnt'];
$total_forms          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM forms"))['cnt'];
$total_resp           = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_responses"))['cnt'];
$total_groups         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM `groups`"))['cnt'];
$total_clubs          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM clubs"))['cnt'];
$total_comments       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_comments"))['cnt'];
$pending_reports      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM reports WHERE status = 'pending'"))['cnt'];

require_once '../config/header.php';
?>

<h4 class="fw-bold mb-4"><i class="bi bi-speedometer2"></i> 管理後台</h4>

<!-- 統計卡片 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-blue">
            <div class="card-body py-5">
                <h2 class="fw-bold"><?= $total_users ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-people"></i> 會員數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-green">
            <div class="card-body py-5">
                <h2 class="fw-bold"><?= $total_forms ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-file-earmark-text"></i> 表單數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-cyan">
            <div class="card-body py-5">
                <h2 class="fw-bold"><?= $total_resp ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-pencil-square"></i> 填答數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-amber">
            <div class="card-body py-5">
                <h2 class="fw-bold"><?= $total_groups ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-diagram-3"></i> 群組數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-cyan">
            <div class="card-body py-5">
                <h2 class="fw-bold"><?= $total_clubs ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-people-fill"></i> 社團數</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-blue">
            <div class="card-body py-5">
                <h2 class="fw-bold"><?= $total_comments ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-chat-left-text"></i> 留言數</p>
            </div>
        </div>
    </div>
</div>

<!-- 快速連結 -->
<div class="row g-3 mb-5">
    <div class="col-6">
        <div class="card h-100">
            <div class="card-body py-4 px-4">
                <h5 class="fw-bold"><i class="bi bi-people text-primary"></i> 會員管理</h5>
                <p class="text-muted small">查看、修改、刪除會員帳號</p>
                <a href="/admin/members.php" class="btn btn-glow-primary btn-sm">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card h-100">
            <div class="card-body py-4 px-4">
                <h5 class="fw-bold"><i class="bi bi-file-earmark-text text-success"></i> 表單管理</h5>
                <p class="text-muted small">查看、刪除所有表單</p>
                <a href="/admin/forms.php" class="btn btn-glow-green btn-sm">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card h-100">
            <div class="card-body py-4 px-4">
                <h5 class="fw-bold"><i class="bi bi-diagram-3 text-warning"></i> 群組管理</h5>
                <p class="text-muted small">新增、修改、刪除群組</p>
                <a href="/admin/groups.php" class="btn btn-glow-primary btn-sm" style="--glow-rgb:255,180,0;">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card h-100">
            <div class="card-body py-4 px-4">
                <h5 class="fw-bold"><i class="bi bi-people-fill text-info"></i> 社團管理</h5>
                <p class="text-muted small">查看、刪除所有社團</p>
                <a href="/admin/clubs.php" class="btn btn-glow-primary btn-sm" style="--glow-rgb:13,202,240;">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card h-100">
            <div class="card-body py-4 px-4">
                <h5 class="fw-bold">
                    <i class="bi bi-flag text-danger"></i> 檢舉管理
                    <?php if ($pending_reports > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $pending_reports ?></span>
                    <?php endif; ?>
                </h5>
                <p class="text-muted small">查看並處理會員的檢舉記錄</p>
                <a href="/admin/reports.php" class="btn btn-glow-primary btn-sm" style="--glow-rgb:220,53,69;">進入管理</a>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card h-100 border-0 overflow-hidden" style="background: linear-gradient(135deg, #1a0025 0%, #2d0040 50%, #1a0010 100%);">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center" style="gap:0.5rem;">
                <div style="font-size:2.2rem; line-height:1; filter: drop-shadow(0 0 8px #ff2d78);">🍸</div>
                <div class="fw-bold" style="font-size:1.15rem; letter-spacing:0.12em; color:#ff2d78; text-shadow:0 0 12px #ff2d78, 0 0 24px #ff2d7855;">OPEN</div>
                <div style="font-size:0.7rem; letter-spacing:0.2em; color:rgba(255,100,180,0.5);">NEON BAR</div>
            </div>
        </div>
    </div>
</div>

<div style="height:8rem;"></div>

<?php require_once '../config/footer.php'; ?>
