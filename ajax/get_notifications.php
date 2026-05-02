<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

$notificationsArray = getUserNotifications($user_id, 20);

$notifications = [];
foreach ($notificationsArray as $row) {
    $time_diff = time() - strtotime($row['created_at']);
    if ($time_diff < 60) {
        $time_ago = 'Just now';
    } elseif ($time_diff < 3600) {
        $time_ago = floor($time_diff / 60) . ' minutes ago';
    } elseif ($time_diff < 86400) {
        $time_ago = floor($time_diff / 3600) . ' hours ago';
    } else {
        $time_ago = floor($time_diff / 86400) . ' days ago';
    }
    
    // Set icon based on type
    $icon = 'fas fa-bell';
    if ($row['type'] == 'activity_approved') $icon = 'fas fa-check-circle';
    elseif ($row['type'] == 'activity_rejected') $icon = 'fas fa-times-circle';
    elseif ($row['type'] == 'new_follower') $icon = 'fas fa-user-plus';
    elseif ($row['type'] == 'new_comment') $icon = 'fas fa-comment';
    elseif ($row['type'] == 'post_like') $icon = 'fas fa-heart';
    
    $notifications[] = [
        'id' => $row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'message' => $row['message'],
        'is_read' => $row['is_read'],
        'time_ago' => $time_ago,
        'icon' => $icon,
        'actor_name' => $row['actor_name'] ?? null
    ];
}

$unreadCount = getUnreadCount($user_id);

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);
?>