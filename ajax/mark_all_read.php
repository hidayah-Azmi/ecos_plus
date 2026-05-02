<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$result = markAllNotificationsAsRead($user_id);

echo json_encode(['success' => $result]);
?>