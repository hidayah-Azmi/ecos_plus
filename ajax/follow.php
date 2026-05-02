<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$target_id = $_POST['target_id'] ?? 0;

if ($action == 'follow') {
    $result = followUser($user_id, $target_id);
    echo json_encode($result);
} elseif ($action == 'unfollow') {
    $result = unfollowUser($user_id, $target_id);
    echo json_encode($result);
} elseif ($action == 'check') {
    $isFollowing = isFollowing($user_id, $target_id);
    echo json_encode(['following' => $isFollowing]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>