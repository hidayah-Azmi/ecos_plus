<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'] ?? 0;
$comment = $_POST['comment'] ?? '';

if ($post_id && $comment) {
    $result = addComment($post_id, $user_id, $comment);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
}
?>