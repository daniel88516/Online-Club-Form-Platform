<?php
$pageTitle = '填答統計';
require_once '../config/session.php';
require_once '../config/db.php';

requireLogin();

$id = intval($_GET['id'] ?? 0);

// 統一以 id 查詢，不限制 user_id（存取控制交由 show_stats 決定）
$stmt = mysqli_prepare($conn, "SELECT f.*, u.username AS author FROM forms f JOIN users u ON f.user_id = u.id WHERE f.id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$form = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$form) {
    header('Location: /index.php');
    exit();
}

// 存取控制：show_stats=0 時只有擁有者與 admin 可查看；show_stats=1 則所有登入會員皆可
if (!isAdmin() && $form['user_id'] != $_SESSION['user_id'] && empty($form['show_stats'])) {
    header('Location: /index.php');
    exit();
}

// 取得欄位
$fields_result = mysqli_query($conn, "SELECT * FROM form_fields WHERE form_id = $id ORDER BY order_num");
$fields = [];
while ($f = mysqli_fetch_assoc($fields_result)) {
    $f['options'] = $f['options'] ? json_decode($f['options'], true) : [];
    $fields[] = $f;
}

// 填答總數
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM form_responses WHERE form_id = $id"))['cnt'];

// 每個欄位的答案統計
$text_types  = ['short_text', 'long_text'];
$field_stats = [];
foreach ($fields as $field) {
    $fid  = $field['id'];
    $type = $field['field_type'];
    if (in_array($type, $text_types)) {
        // 文字欄位：帶使用者資訊，不 GROUP BY
        $answers_result = mysqli_query($conn, "
            SELECT ra.answer, u.id AS user_id, u.username, u.avatar
            FROM response_answers ra
            JOIN form_responses fr ON ra.response_id = fr.id
            LEFT JOIN users u ON fr.user_id = u.id
            WHERE ra.field_id = $fid AND ra.answer != ''
            ORDER BY fr.submitted_at DESC
        ");
    } else {
        $answers_result = mysqli_query($conn, "SELECT ra.answer, COUNT(*) AS cnt FROM response_answers ra WHERE ra.field_id = $fid AND ra.answer != '' GROUP BY ra.answer ORDER BY cnt DESC");
    }
    $answers = [];
    while ($row = mysqli_fetch_assoc($answers_result)) {
        $answers[] = $row;
    }
    $field_stats[$fid] = $answers;
}

// 詳細填答列表
$responses_result = mysqli_query($conn, "
    SELECT fr.id, fr.submitted_at, u.id AS user_id, u.username, u.avatar
    FROM form_responses fr
    LEFT JOIN users u ON fr.user_id = u.id
    WHERE fr.form_id = $id
    ORDER BY fr.submitted_at DESC
");

// ── 按讚記錄 ──────────────────────────────────────────────
$stmtLk = mysqli_prepare($conn, "
    SELECT u.id, u.username, u.avatar, fl.created_at
    FROM form_likes fl
    JOIN users u ON fl.user_id = u.id
    WHERE fl.form_id = ?
    ORDER BY fl.created_at DESC
");
mysqli_stmt_bind_param($stmtLk, 'i', $id);
mysqli_stmt_execute($stmtLk);
$likes_users = mysqli_stmt_get_result($stmtLk);
$like_total  = mysqli_num_rows($likes_users);

// ── 收藏記錄 ──────────────────────────────────────────────
$stmtBk = mysqli_prepare($conn, "
    SELECT u.id, u.username, u.avatar, fb.created_at
    FROM form_bookmarks fb
    JOIN users u ON fb.user_id = u.id
    WHERE fb.form_id = ?
    ORDER BY fb.created_at DESC
");
mysqli_stmt_bind_param($stmtBk, 'i', $id);
mysqli_stmt_execute($stmtBk);
$bookmarks_users = mysqli_stmt_get_result($stmtBk);
$bookmark_total  = mysqli_num_rows($bookmarks_users);

require_once '../config/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── 全域：主題色 & 文字色，供各題圖表共用 ── */
var _themeColor = (function () {
    var c = localStorage.getItem('themeColor');
    if (c && /^#[0-9a-fA-F]{6}$/.test(c)) return c;
    return getComputedStyle(document.documentElement)
               .getPropertyValue('--bs-primary').trim() || '#0d6efd';
})();
var _chartText = getComputedStyle(document.documentElement)
                     .getPropertyValue('--bs-body-color').trim() || '#212529';
var _chartGrid = getComputedStyle(document.documentElement)
                     .getPropertyValue('--bs-border-color').trim() || '#dee2e6';
</script>

<div class="row mb-3 align-items-center">
    <div class="col">
        <h4 class="fw-bold"><i class="bi bi-bar-chart"></i> 填答統計：<?= htmlspecialchars($form['title']) ?></h4>
        <small class="text-muted">建立者：<?= htmlspecialchars($form['author']) ?></small>
    </div>
    <div class="col-auto">
        <a href="/member/my_forms.php" class="btn btn-glow-primary">
            <i class="bi bi-arrow-left"></i> 返回
        </a>
    </div>
</div>

<!-- 總覽 -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-blue">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= $total ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-people"></i> 填答人數</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-0 admin-stat-card stat-green">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= count($fields) ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-list-check"></i> 題目數量</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-0 admin-stat-card <?= $form['is_published'] ? 'stat-cyan' : 'stat-amber' ?>">
            <div class="card-body py-4">
                <h2 class="fw-bold"><?= $form['is_published'] ? '已發布' : '草稿' ?></h2>
                <p class="mb-0 text-muted"><i class="bi bi-toggle-on"></i> 狀態</p>
            </div>
        </div>
    </div>
</div>

<?php if ($total === 0): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle"></i> 目前尚無填答記錄</div>
<?php else: ?>

<!-- 各題統計 -->
<h5 class="fw-bold mb-3"><i class="bi bi-pie-chart"></i> 各題統計</h5>
<?php foreach ($fields as $field): ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <strong><?= htmlspecialchars($field['label']) ?></strong>
        <span class="badge bg-secondary"><?= ['short_text'=>'簡答','long_text'=>'詳答','radio'=>'單選','checkbox'=>'核取','dropdown'=>'下拉','date'=>'日期','time'=>'時間'][$field['field_type']] ?></span>
        <?php if ($field['is_required']): ?>
            <span class="badge bg-danger">必填</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php $stats = $field_stats[$field['id']]; ?>
        <?php if (empty($stats)): ?>
            <p class="text-muted small mb-0">無填答記錄</p>

        <?php elseif (in_array($field['field_type'], ['radio', 'dropdown'])): ?>
            <!-- 圓餅圖 -->
            <div class="row align-items-center">
                <div class="col-md-5">
                    <canvas id="chart_<?= $field['id'] ?>" height="200"></canvas>
                </div>
                <div class="col-md-7">
                    <?php foreach ($stats as $s): ?>
                        <?php $pct = $total > 0 ? round($s['cnt'] / $total * 100) : 0; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small"><?= htmlspecialchars($s['answer']) ?></span>
                                <span class="small text-muted"><?= $s['cnt'] ?> 票（<?= $pct ?>%）</span>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
            (function(){
            new Chart(document.getElementById('chart_<?= $field['id'] ?>'), {
                type: 'doughnut',
                data: {
                    labels: [<?= implode(',', array_map(fn($s) => "'" . addslashes($s['answer']) . "'", $stats)) ?>],
                    datasets: [{
                        data: [<?= implode(',', array_map(fn($s) => $s['cnt'], $stats)) ?>],
                        backgroundColor: [_themeColor,'#198754','#ffc107','#dc3545','#0dcaf0','#6f42c1','#fd7e14','#20c997'],
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: _chartText }
                        }
                    }
                }
            });
            })();
            </script>

        <?php elseif ($field['field_type'] === 'checkbox'): ?>
            <!-- 橫向長條圖 -->
            <canvas id="chart_<?= $field['id'] ?>" height="<?= min(count($stats) * 40 + 40, 250) ?>"></canvas>
            <script>
            (function(){
            new Chart(document.getElementById('chart_<?= $field['id'] ?>'), {
                type: 'bar',
                data: {
                    labels: [<?= implode(',', array_map(fn($s) => "'" . addslashes($s['answer']) . "'", $stats)) ?>],
                    datasets: [{
                        label: '勾選次數',
                        data: [<?= implode(',', array_map(fn($s) => $s['cnt'], $stats)) ?>],
                        backgroundColor: _themeColor,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: _chartText },
                            grid:  { color: _chartGrid }
                        },
                        y: {
                            ticks: { color: _chartText },
                            grid:  { color: _chartGrid }
                        }
                    },
                    plugins: { legend: { display: false } }
                }
            });
            })();
            </script>

        <?php elseif (in_array($field['field_type'], $text_types)): ?>
            <!-- 簡答/詳答：帶頭像、截斷、可點擊展開 -->
            <ul class="list-group list-group-flush">
                <?php foreach (array_slice($stats, 0, 20) as $s): ?>
                    <?php $isAnon = ($form['anonymous_responses'] ?? 0) || empty($s['username']); ?>
                    <li class="list-group-item py-2 text-answer-row" style="cursor:pointer;"
                        data-answer="<?= htmlspecialchars($s['answer'], ENT_QUOTES) ?>"
                        data-username="<?= $isAnon ? '' : htmlspecialchars($s['username'], ENT_QUOTES) ?>"
                        data-avatar="<?= $isAnon ? '' : htmlspecialchars($s['avatar'] ?? '', ENT_QUOTES) ?>"
                        data-uid="<?= $isAnon ? 0 : intval($s['user_id']) ?>">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($isAnon): ?>
                                <span class="text-muted"><i class="bi bi-incognito"></i></span>
                            <?php else: ?>
                                <?= renderAvatarDropdown($s['username'], $s['avatar'] ?? null, $s['user_id'], 24, $_SESSION['user_id'] ?? 0) ?>
                                <span class="small fw-semibold text-nowrap"><?= htmlspecialchars($s['username']) ?></span>
                            <?php endif; ?>
                            <span class="small answer-preview flex-grow-1" style="min-width:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (count($stats) > 20): ?>
                    <li class="list-group-item small text-muted py-2">...還有 <?= count($stats) - 20 ?> 筆答案</li>
                <?php endif; ?>
            </ul>
        <?php else: ?>
            <!-- 文字答案列表（日期/時間） -->
            <ul class="list-group list-group-flush">
                <?php foreach (array_slice($stats, 0, 10) as $s): ?>
                    <li class="list-group-item small py-2">
                        <?= htmlspecialchars($s['answer']) ?>
                        <?php if (!empty($s['cnt']) && $s['cnt'] > 1): ?>
                            <span class="badge bg-secondary ms-2"><?= $s['cnt'] ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (count($stats) > 10): ?>
                    <li class="list-group-item small text-muted py-2">...還有 <?= count($stats) - 10 ?> 筆答案</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- 文字答案展開 Modal -->
<div class="modal fade" id="textAnswerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2" id="tam-user">
                    <span class="text-muted"><i class="bi bi-incognito"></i> 匿名</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="tam-body" style="white-space:pre-wrap;word-break:break-word;"></div>
        </div>
    </div>
</div>
<script>
$(document).on('click', '.text-answer-row', function (e) {
    if ($(e.target).closest('.avd-wrap').length) return;
    const answer   = $(this).data('answer');
    const username = $(this).data('username');
    const avatar   = $(this).data('avatar');

    if (username) {
        const img = avatar
            ? '<img src="' + $('<span>').text(avatar).html() + '" class="rounded-circle" width="32" height="32" style="object-fit:cover;">'
            : '<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px;font-size:.85rem;">' + $('<span>').text(username.charAt(0).toUpperCase()).html() + '</div>';
        $('#tam-user').html('<div class="d-flex align-items-center gap-2">' + img + '<span class="fw-semibold">' + $('<span>').text(username).html() + '</span></div>');
    } else {
        $('#tam-user').html('<span class="text-muted"><i class="bi bi-incognito"></i> 匿名</span>');
    }
    $('#tam-body').text(answer);
    new bootstrap.Modal(document.getElementById('textAnswerModal')).show();
});

// 取第一行顯示，有更多內容時補主題色 ···
$(function () {
    $('.answer-preview').each(function () {
        const answer = $(this).closest('.text-answer-row').data('answer') || '';
        const firstLine = answer.split('\n')[0];
        const hasMore = answer.includes('\n') || answer.length > firstLine.length;
        $(this).text(firstLine);
        if (hasMore || this.scrollWidth > this.clientWidth + 2) {
            $(this).append('<span style="color:var(--bs-primary);font-weight:600;font-size:.8rem;">...展開以查看完整回答</span>');
        }
    });
});
</script>

<!-- 詳細填答記錄 -->
<h5 class="fw-bold mb-3 mt-4"><i class="bi bi-list-ul"></i> 詳細填答記錄</h5>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>填答者</th>
                    <th>填答時間</th>
                    <th class="text-center">查看</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = mysqli_fetch_assoc($responses_result)): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <?php if ($form['anonymous_responses'] ?? 0): ?>
                            <span class="text-muted fst-italic"><i class="bi bi-incognito"></i> 匿名</span>
                        <?php elseif ($r['username']): ?>
                            <div class="d-flex align-items-center gap-2">
                                <?= renderAvatarDropdown($r['username'], $r['avatar'] ?? null, $r['user_id'], 28, $_SESSION['user_id'] ?? 0) ?>
                                <span><?= htmlspecialchars($r['username']) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="text-muted fst-italic">匿名</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= date('Y/m/d H:i', strtotime($r['submitted_at'])) ?></td>
                    <td class="text-center">
                        <button class="btn btn-glow-primary btn-view-response" data-id="<?= $r['id'] ?>">
                            <i class="bi bi-eye"></i> 查看
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ── 按讚記錄 ── -->
<h5 class="fw-bold mb-3 mt-4">
    <i class="bi bi-heart-fill text-danger me-1"></i> 按讚記錄
    <span class="badge bg-secondary ms-1" style="font-size:.75rem;"><?= $like_total ?></span>
</h5>
<div class="card mb-4">
    <div class="card-body py-3">
        <?php if ($like_total === 0): ?>
            <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i> 尚無人按讚</p>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php while ($u = mysqli_fetch_assoc($likes_users)): ?>
                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1">
                    <?= renderAvatarDropdown($u['username'], $u['avatar'] ?? null, $u['id'], 26, $_SESSION['user_id']) ?>
                    <div>
                        <div class="small fw-semibold"><?= htmlspecialchars($u['username']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?= date('Y/m/d H:i', strtotime($u['created_at'])) ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── 收藏記錄 ── -->
<h5 class="fw-bold mb-3">
    <i class="bi bi-bookmark-fill text-warning me-1"></i> 收藏記錄
    <span class="badge bg-secondary ms-1" style="font-size:.75rem;"><?= $bookmark_total ?></span>
</h5>
<div class="card mb-4">
    <div class="card-body py-3">
        <?php if ($bookmark_total === 0): ?>
            <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i> 尚無人收藏</p>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php while ($u = mysqli_fetch_assoc($bookmarks_users)): ?>
                <div class="d-flex align-items-center gap-2 border rounded px-2 py-1">
                    <?= renderAvatarDropdown($u['username'], $u['avatar'] ?? null, $u['id'], 26, $_SESSION['user_id']) ?>
                    <div>
                        <div class="small fw-semibold"><?= htmlspecialchars($u['username']) ?></div>
                        <div class="text-muted" style="font-size:.72rem;"><?= date('Y/m/d H:i', strtotime($u['created_at'])) ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 填答詳情 Modal -->
<div class="modal fade" id="responseDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h6 class="modal-title fw-semibold mb-0" id="responseDetailTitle">
                        <i class="bi bi-person me-1"></i> <span id="rd-respondent"></span>
                    </h6>
                    <small class="text-muted" id="rd-time"></small>
                </div>
                <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rd-body">
                <div class="text-center py-4">
                    <span class="spinner-border text-primary"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).on('click', '.btn-view-response', function () {
    const id = $(this).data('id');
    $('#rd-respondent').text('');
    $('#rd-time').text('');
    $('#rd-body').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('responseDetailModal')).show();

    $.get('/api/response_detail.php', { id: id }, function (res) {
        if (!res.success) { $('#rd-body').html('<div class="alert alert-danger">' + res.message + '</div>'); return; }

        $('#rd-respondent').text(res.respondent);
        $('#rd-time').text(res.submitted_at);

        let html = '';
        if (res.answers.length === 0) {
            html = '<p class="text-muted text-center">無填答內容</p>';
        } else {
            res.answers.forEach(function (a) {
                const ans = a.answer !== ''
                    ? $('<span>').text(a.answer).html().replace(/\n/g, '<br>')
                    : '<span class="text-muted fst-italic">未填答</span>';
                html += '<div class="card mb-2"><div class="card-body py-2">'
                      + '<p class="fw-semibold mb-1 small">' + $('<span>').text(a.label).html() + '</p>'
                      + '<p class="mb-0 text-secondary">' + ans + '</p>'
                      + '</div></div>';
            });
        }
        $('#rd-body').html(html);
    }, 'json').fail(function () {
        $('#rd-body').html('<div class="alert alert-danger">載入失敗，請稍後再試</div>');
    });
});
</script>

<?php require_once '../config/footer.php'; ?>
