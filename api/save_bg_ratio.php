<?php
require_once '../config/session.php';
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success' => false]); exit(); }

$ratio = intval($_POST['ratio'] ?? 7);
if (!in_array($ratio, [6, 7, 8])) { echo json_encode(['success' => false, 'message' => '無效的比例']); exit(); }

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("UPDATE users SET profile_bg_ratio = ? WHERE id = ?");
$stmt->bind_param('ii', $ratio, $user_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
