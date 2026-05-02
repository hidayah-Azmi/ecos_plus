<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'] ?? 0;
$action = $_POST['action'] ?? '';

if ($action == 'like') {
    $result = likePost($post_id, $user_id);
    echo json_encode($result);
} elseif ($action == 'unlike') {
    $result = unlikePost($post_id, $user_id);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>