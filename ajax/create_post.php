<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$content = $_POST['content'] ?? '';

if ($content) {
    $result = createPost($user_id, $content);
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Content cannot be empty']);
}
?>