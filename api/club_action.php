<?php
require_once '../config/session.php';
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

$action  = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// ── 建立社團 ──
if ($action === 'create') {
    $name      = trim($_POST['name'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $is_public = intval($_POST['is_public'] ?? 1);
    $raw_cover = trim($_POST['cover_image'] ?? '');
    $cover_image = preg_match('#^/uploads/[a-zA-Z0-9_/.\-]+$#', $raw_cover) ? $raw_cover : null;

    if (!$name) {
        echo json_encode(['success' => false, 'message' => '社團名稱不能為空']);
        exit();
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO clubs (name, description, owner_id, is_public, cover_image) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssiis', $name, $desc, $user_id, $is_public, $cover_image);
    mysqli_stmt_execute($stmt);
    $club_id = mysqli_insert_id($conn);

    $m = mysqli_prepare($conn, "INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')");
    mysqli_stmt_bind_param($m, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($m);

    echo json_encode(['success' => true, 'club_id' => $club_id]);
    exit();
}

// ── 加入（公開）或申請（私人）──
if ($action === 'join') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT id, is_public, name, owner_id FROM clubs WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $club_id);
    mysqli_stmt_execute($stmt);
    $club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$club) {
        echo json_encode(['success' => false, 'message' => '社團不存在']);
        exit();
    }

    // 已存在紀錄則不重複
    $chk = mysqli_prepare($conn, "SELECT status FROM club_members WHERE club_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($chk, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($chk);
    $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    if ($existing) {
        echo json_encode(['success' => false, 'message' => '已是成員或申請中']);
        exit();
    }

    if ($club['is_public']) {
        $ins = mysqli_prepare($conn, "INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'member', 'active')");
        mysqli_stmt_bind_param($ins, 'ii', $club_id, $user_id);
        mysqli_stmt_execute($ins);
        echo json_encode(['success' => true]);
    } else {
        $ins = mysqli_prepare($conn, "INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'member', 'pending')");
        mysqli_stmt_bind_param($ins, 'ii', $club_id, $user_id);
        mysqli_stmt_execute($ins);
        // 通知 owner
        $sys_id  = getSystemUserId($conn);
        $content = "【社團申請通知】\n用戶「{$_SESSION['username']}」申請加入您的社團「{$club['name']}」，請前往社團審核。[CLUB:{$club_id}]";
        $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($n, 'iis', $sys_id, $club['owner_id'], $content);
        mysqli_stmt_execute($n);
        echo json_encode(['success' => true, 'pending' => true]);
    }
    exit();
}

// ── 離開社團（含取消申請）──
if ($action === 'leave') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $club = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, name, owner_id FROM clubs WHERE id = $club_id"));
    if (!$club) { echo json_encode(['success' => false, 'message' => '社團不存在']); exit(); }
    if ($club['owner_id'] == $user_id) {
        echo json_encode(['success' => false, 'message' => '社團建立者無法離開社團']);
        exit();
    }
    // 檢查是否為取消申請
    $chk = mysqli_prepare($conn, "SELECT status FROM club_members WHERE club_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($chk, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($chk);
    $cm = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));

    $del = mysqli_prepare($conn, "DELETE FROM club_members WHERE club_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($del, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($del);

    // 若是取消申請，刪掉給 owner 的通知
    if ($cm && $cm['status'] === 'pending') {
        $sys_id = getSystemUserId($conn);
        $uname  = $_SESSION['username'];
        $like_pattern = "%{$uname}%申請加入您的社團「{$club['name']}」%";
        $del_msg = mysqli_prepare($conn, "DELETE FROM messages WHERE sender_id = ? AND receiver_id = ? AND content LIKE ? ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($del_msg, 'iis', $sys_id, $club['owner_id'], $like_pattern);
        mysqli_stmt_execute($del_msg);
    }
    echo json_encode(['success' => true]);
    exit();
}

// ── 批准申請（owner）──
if ($action === 'approve') {
    $club_id    = intval($_POST['club_id'] ?? 0);
    $target_uid = intval($_POST['target_uid'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    $u = mysqli_prepare($conn, "UPDATE club_members SET status = 'active' WHERE club_id = ? AND user_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($u, 'ii', $club_id, $target_uid);
    mysqli_stmt_execute($u);

    $sys_id  = getSystemUserId($conn);
    $content = "【社團申請通過】\n您申請加入社團「{$owner['name']}」已通過審核！[CLUB:{$club_id}]";
    $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($n, 'iis', $sys_id, $target_uid, $content);
    mysqli_stmt_execute($n);
    echo json_encode(['success' => true]);
    exit();
}

// ── 拒絕申請（owner）──
if ($action === 'reject') {
    $club_id    = intval($_POST['club_id'] ?? 0);
    $target_uid = intval($_POST['target_uid'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    $d = mysqli_prepare($conn, "DELETE FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($d, 'ii', $club_id, $target_uid);
    mysqli_stmt_execute($d);

    $sys_id  = getSystemUserId($conn);
    $content = "【社團申請未通過】\n您申請加入社團「{$owner['name']}」未通過審核。[CLUBS_EXPLORE]";
    $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($n, 'iis', $sys_id, $target_uid, $content);
    mysqli_stmt_execute($n);
    echo json_encode(['success' => true]);
    exit();
}

// ── 邀請用戶（owner）──
if ($action === 'invite') {
    $club_id    = intval($_POST['club_id'] ?? 0);
    $target_uid = intval($_POST['target_uid'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }
    if ($target_uid === $user_id) { echo json_encode(['success' => false, 'message' => '不能邀請自己']); exit(); }

    $chk = mysqli_prepare($conn, "SELECT status FROM club_members WHERE club_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($chk, 'ii', $club_id, $target_uid);
    mysqli_stmt_execute($chk);
    $ex = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    if ($ex) {
        $msg = $ex['status'] === 'active' ? '該用戶已是成員' : ($ex['status'] === 'invited' ? '已發送邀請' : '該用戶已申請加入');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit();
    }

    $ins = mysqli_prepare($conn, "INSERT INTO club_members (club_id, user_id, role, status) VALUES (?, ?, 'member', 'invited')");
    mysqli_stmt_bind_param($ins, 'ii', $club_id, $target_uid);
    mysqli_stmt_execute($ins);

    $sys_id  = getSystemUserId($conn);
    $content = "【社團邀請】\n您被邀請加入社團「{$owner['name']}」，請前往待處理社團確認邀請。[CLUBS_PENDING]";
    $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($n, 'iis', $sys_id, $target_uid, $content);
    mysqli_stmt_execute($n);
    echo json_encode(['success' => true]);
    exit();
}

// ── 搜尋用戶（邀請用，owner 限定）──
if ($action === 'search_users') {
    $club_id = intval($_POST['club_id'] ?? 0);
    // 確認是 owner
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    $q    = trim($_POST['q'] ?? '');
    $like = '%' . $q . '%';
    $stmt = mysqli_prepare($conn, "
        SELECT u.id, u.username, u.avatar,
               cm.status AS member_status
        FROM users u
        LEFT JOIN club_members cm ON cm.user_id = u.id AND cm.club_id = ?
        WHERE u.username LIKE ? AND u.id != ?
        LIMIT 10
    ");
    mysqli_stmt_bind_param($stmt, 'isi', $club_id, $like, $user_id);
    mysqli_stmt_execute($stmt);
    $res   = mysqli_stmt_get_result($stmt);
    $users = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = ['id' => $row['id'], 'username' => $row['username'], 'avatar' => $row['avatar'], 'member_status' => $row['member_status']];
    }
    echo json_encode(['success' => true, 'users' => $users]);
    exit();
}

// ── 取消邀請（owner 撤回已發出的邀請）──
if ($action === 'cancel_invite') {
    $club_id    = intval($_POST['club_id'] ?? 0);
    $target_uid = intval($_POST['target_uid'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }
    $d = mysqli_prepare($conn, "DELETE FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'invited'");
    mysqli_stmt_bind_param($d, 'ii', $club_id, $target_uid);
    mysqli_stmt_execute($d);
    // 刪掉原本發給被邀者的通知
    $sys_id = getSystemUserId($conn);
    $like_pattern = "%社團「{$owner['name']}」%[CLUBS_PENDING]%";
    $del_msg = mysqli_prepare($conn, "DELETE FROM messages WHERE sender_id = ? AND receiver_id = ? AND content LIKE ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($del_msg, 'iis', $sys_id, $target_uid, $like_pattern);
    mysqli_stmt_execute($del_msg);
    echo json_encode(['success' => true]);
    exit();
}

// ── 接受邀請 ──
if ($action === 'accept_invite') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $u = mysqli_prepare($conn, "UPDATE club_members SET status = 'active' WHERE club_id = ? AND user_id = ? AND status = 'invited'");
    mysqli_stmt_bind_param($u, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($u);
    echo json_encode(['success' => true]);
    exit();
}

// ── 拒絕邀請 ──
if ($action === 'decline_invite') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $d = mysqli_prepare($conn, "DELETE FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'invited'");
    mysqli_stmt_bind_param($d, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($d);
    echo json_encode(['success' => true]);
    exit();
}

// ── 更新封面圖片（owner）──
if ($action === 'update_cover') {
    $club_id   = intval($_POST['club_id'] ?? 0);
    $cover_url = trim($_POST['cover_url'] ?? '');
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }
    if (!preg_match('#^/uploads/[a-zA-Z0-9_/.\-]+$#', $cover_url)) {
        echo json_encode(['success' => false, 'message' => '無效的圖片路徑']); exit();
    }
    $u = mysqli_prepare($conn, "UPDATE clubs SET cover_image = ? WHERE id = ?");
    mysqli_stmt_bind_param($u, 'si', $cover_url, $club_id);
    mysqli_stmt_execute($u);
    echo json_encode(['success' => true]);
    exit();
}

// ── 切換公開/私人（owner）──
if ($action === 'toggle_public') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, is_public FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    $new_val = $owner['is_public'] ? 0 : 1;
    $u = mysqli_prepare($conn, "UPDATE clubs SET is_public = ? WHERE id = ?");
    mysqli_stmt_bind_param($u, 'ii', $new_val, $club_id);
    mysqli_stmt_execute($u);
    echo json_encode(['success' => true, 'is_public' => $new_val]);
    exit();
}

// ── 取得社團成員列表（owner 用，排除自己）──
if ($action === 'get_members') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    $stmt = mysqli_prepare($conn, "
        SELECT u.id, u.username, u.avatar FROM club_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.club_id = ? AND cm.status = 'active' AND cm.user_id != ?
        ORDER BY cm.joined_at ASC
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $club_id, $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $members = [];
    while ($r = mysqli_fetch_assoc($res)) $members[] = $r;
    echo json_encode(['success' => true, 'members' => $members]);
    exit();
}

// ── 查看社團成員（所有成員可用，支援搜尋）──
if ($action === 'list_members') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $q       = trim($_POST['q'] ?? '');
    $like    = '%' . $q . '%';
    $stmt = mysqli_prepare($conn, "
        SELECT u.id, u.username, u.avatar, cm.role
        FROM club_members cm
        JOIN users u ON cm.user_id = u.id
        WHERE cm.club_id = ? AND cm.status = 'active' AND u.username LIKE ?
        ORDER BY cm.role = 'owner' DESC, cm.joined_at ASC
        LIMIT 50
    ");
    mysqli_stmt_bind_param($stmt, 'is', $club_id, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $members = [];
    while ($r = mysqli_fetch_assoc($res)) $members[] = $r;
    echo json_encode(['success' => true, 'members' => $members]);
    exit();
}

// ── 踢除成員（owner）──
if ($action === 'kick') {
    $club_id    = intval($_POST['club_id'] ?? 0);
    $target_uid = intval($_POST['target_uid'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }
    if ($target_uid === $user_id) { echo json_encode(['success' => false, 'message' => '不能踢除自己']); exit(); }
    $d = mysqli_prepare($conn, "DELETE FROM club_members WHERE club_id = ? AND user_id = ? AND role != 'owner'");
    mysqli_stmt_bind_param($d, 'ii', $club_id, $target_uid);
    mysqli_stmt_execute($d);
    $sys_id  = getSystemUserId($conn);
    $content = "【社團通知】\n你已被移出社團「{$owner['name']}」。[CLUBS_EXPLORE]";
    $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($n, 'iis', $sys_id, $target_uid, $content);
    mysqli_stmt_execute($n);
    echo json_encode(['success' => true]);
    exit();
}

// ── 移交管理員（owner）──
if ($action === 'transfer_owner') {
    $club_id     = intval($_POST['club_id'] ?? 0);
    $new_owner_id = intval($_POST['new_owner_id'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    // 確認新 owner 是現有成員
    $chk = mysqli_prepare($conn, "SELECT id FROM club_members WHERE club_id = ? AND user_id = ? AND status = 'active'");
    mysqli_stmt_bind_param($chk, 'ii', $club_id, $new_owner_id);
    mysqli_stmt_execute($chk);
    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($chk))) {
        echo json_encode(['success' => false, 'message' => '該成員不在社團內']); exit();
    }

    mysqli_query($conn, "UPDATE clubs SET owner_id = $new_owner_id WHERE id = $club_id");
    mysqli_query($conn, "UPDATE club_members SET role = 'owner' WHERE club_id = $club_id AND user_id = $new_owner_id");
    mysqli_query($conn, "UPDATE club_members SET role = 'member' WHERE club_id = $club_id AND user_id = $user_id");

    $sys_id  = getSystemUserId($conn);
    $content = "【社團管理員變更】\n社團「{$owner['name']}」的管理員已移交給你。[CLUB:{$club_id}]";
    $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($n, 'iis', $sys_id, $new_owner_id, $content);
    mysqli_stmt_execute($n);
    echo json_encode(['success' => true]);
    exit();
}

// ── 刪除社團（owner）──
if ($action === 'delete_club') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    mysqli_query($conn, "DELETE FROM club_members WHERE club_id = $club_id");
    mysqli_query($conn, "DELETE FROM clubs WHERE id = $club_id");
    echo json_encode(['success' => true]);
    exit();
}

// ── 管理員離開（自動遞補）──
if ($action === 'owner_leave') {
    $club_id = intval($_POST['club_id'] ?? 0);
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM clubs WHERE id = $club_id AND owner_id = $user_id"));
    if (!$owner) { echo json_encode(['success' => false, 'message' => '無權限']); exit(); }

    // 找第二早加入的成員（排除自己）
    $next = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT user_id FROM club_members WHERE club_id = $club_id AND status = 'active' AND user_id != $user_id ORDER BY joined_at ASC LIMIT 1"
    ));

    if ($next) {
        $new_owner_id = $next['user_id'];
        mysqli_query($conn, "UPDATE clubs SET owner_id = $new_owner_id WHERE id = $club_id");
        mysqli_query($conn, "UPDATE club_members SET role = 'owner' WHERE club_id = $club_id AND user_id = $new_owner_id");
        mysqli_query($conn, "DELETE FROM club_members WHERE club_id = $club_id AND user_id = $user_id");

        $sys_id  = getSystemUserId($conn);
        $content = "【社團管理員變更】\n社團「{$owner['name']}」的原管理員已離開，你已自動成為新管理員。[CLUB:{$club_id}]";
        $n = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($n, 'iis', $sys_id, $new_owner_id, $content);
        mysqli_stmt_execute($n);
    } else {
        // 無其他成員 → 刪除社團
        mysqli_query($conn, "DELETE FROM club_members WHERE club_id = $club_id");
        mysqli_query($conn, "DELETE FROM clubs WHERE id = $club_id");
    }

    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => '未知操作']);
