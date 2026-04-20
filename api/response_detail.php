<?php
require_once '../config/session.php';
require_once '../config/db.php';
header('Content-Type: application/json');

$response_id = intval($_GET['id'] ?? 0);
if (!$response_id) {
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit();
}

// 取得填答記錄（只有表單擁有者或 admin 可查看）
$stmt = mysqli_prepare($conn, "
    SELECT fr.submitted_at, f.user_id AS form_owner, f.title AS form_title,
           f.anonymous_responses, f.show_stats,
           COALESCE(u.username, '匿名') AS respondent
    FROM form_responses fr
    JOIN forms f ON fr.form_id = f.id
    LEFT JOIN users u ON fr.user_id = u.id
    WHERE fr.id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $response_id);
mysqli_stmt_execute($stmt);
$response = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$canView = isAdmin()
    || $response['form_owner'] == ($_SESSION['user_id'] ?? 0)
    || !empty($response['show_stats']);
if (!$response || !$canView) {
    echo json_encode(['success' => false, 'message' => '無查看權限']);
    exit();
}

// 取得答案
$ans_stmt = mysqli_prepare($conn, "
    SELECT ff.label, ff.field_type, ra.answer
    FROM response_answers ra
    JOIN form_fields ff ON ra.field_id = ff.id
    WHERE ra.response_id = ?
    ORDER BY ff.order_num
");
mysqli_stmt_bind_param($ans_stmt, 'i', $response_id);
mysqli_stmt_execute($ans_stmt);
$ans_result = mysqli_stmt_get_result($ans_stmt);

$answers = [];
while ($row = mysqli_fetch_assoc($ans_result)) {
    $answers[] = [
        'label'      => $row['label'],
        'field_type' => $row['field_type'],
        'answer'     => $row['answer'],
    ];
}

echo json_encode([
    'success'      => true,
    'respondent'   => $response['anonymous_responses'] ? '匿名' : $response['respondent'],
    'submitted_at' => date('Y/m/d H:i', strtotime($response['submitted_at'])),
    'answers'      => $answers,
]);
