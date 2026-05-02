<?php
// includes/notifications.php

function getNotifConnection() {
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $database = 'ecos_plus';
    
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Create notification - WITHOUT actor_id
function createNotification($user_id, $type, $title, $message, $related_id = null) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssi", $user_id, $type, $title, $message, $related_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

function getUserNotifications($user_id, $limit = 20) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $notifications;
}

function getUnreadCount($user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();
    $conn->close();
    
    return $count;
}

function markNotificationAsRead($notification_id, $user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

function markAllNotificationsAsRead($user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

// ========== FOLLOW FUNCTIONS ==========
function followUser($follower_id, $following_id) {
    $conn = getNotifConnection();
    
    // Check if already following
    $checkStmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
    $checkStmt->bind_param("ii", $follower_id, $following_id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Already following'];
    }
    $checkStmt->close();
    
    $stmt = $conn->prepare("INSERT INTO followers (follower_id, following_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $follower_id, $following_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        // Get follower name
        $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
        $userStmt->bind_param("i", $follower_id);
        $userStmt->execute();
        $follower = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        
        // Create notification for the person being followed
        createNotification(
            $following_id,
            'new_follower',
            'New Follower! 🫂',
            $follower['full_name'] . ' started following you',
            $follower_id
        );
    }
    
    $conn->close();
    return ['success' => $result, 'message' => $result ? 'Followed successfully' : 'Failed to follow'];
}

function unfollowUser($follower_id, $following_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("DELETE FROM followers WHERE follower_id = ? AND following_id = ?");
    $stmt->bind_param("ii", $follower_id, $following_id);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return ['success' => $result, 'message' => $result ? 'Unfollowed successfully' : 'Failed to unfollow'];
}

function isFollowing($follower_id, $following_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT id FROM followers WHERE follower_id = ? AND following_id = ?");
    $stmt->bind_param("ii", $follower_id, $following_id);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    $conn->close();
    
    return $result;
}

function getFollowersCount($user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE following_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    $conn->close();
    
    return $count;
}

function getFollowingCount($user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM followers WHERE follower_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    $conn->close();
    
    return $count;
}

// ========== LIKE FUNCTIONS WITH NOTIFICATION ==========
function likePost($post_id, $user_id) {
    $conn = getNotifConnection();
    
    // Check if already liked
    $checkStmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $post_id, $user_id);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        $conn->close();
        return ['success' => false, 'message' => 'Already liked'];
    }
    $checkStmt->close();
    
    // Get post owner
    $postStmt = $conn->prepare("SELECT user_id FROM community_posts WHERE id = ?");
    $postStmt->bind_param("i", $post_id);
    $postStmt->execute();
    $post = $postStmt->get_result()->fetch_assoc();
    $postStmt->close();
    
    // Add like
    $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $post_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result && $post && $post['user_id'] != $user_id) {
        // Get liker name
        $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $liker = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        
        // Update likes count in posts table
        $updateStmt = $conn->prepare("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?");
        $updateStmt->bind_param("i", $post_id);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Create notification for post owner
        createNotification(
            $post['user_id'],
            'post_like',
            'Post Liked! ❤️',
            $liker['full_name'] . ' liked your post',
            $post_id
        );
    }
    
    $conn->close();
    return ['success' => $result];
}

function unlikePost($post_id, $user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Update likes count in posts table
    $updateStmt = $conn->prepare("UPDATE community_posts SET likes_count = likes_count - 1 WHERE id = ?");
    $updateStmt->bind_param("i", $post_id);
    $updateStmt->execute();
    $updateStmt->close();
    
    $conn->close();
    return ['success' => $result];
}

function hasUserLikedPost($post_id, $user_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    $conn->close();
    
    return $result;
}

// ========== COMMENT FUNCTIONS WITH NOTIFICATION ==========
function addComment($post_id, $user_id, $comment) {
    $conn = getNotifConnection();
    
    // Get post owner
    $postStmt = $conn->prepare("SELECT user_id FROM community_posts WHERE id = ?");
    $postStmt->bind_param("i", $post_id);
    $postStmt->execute();
    $post = $postStmt->get_result()->fetch_assoc();
    $postStmt->close();
    
    // Add comment
    $stmt = $conn->prepare("INSERT INTO community_comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $post_id, $user_id, $comment);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result && $post && $post['user_id'] != $user_id) {
        // Get commenter name
        $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $commenter = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        
        // Update comments count in posts table
        $updateStmt = $conn->prepare("UPDATE community_posts SET comments_count = comments_count + 1 WHERE id = ?");
        $updateStmt->bind_param("i", $post_id);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Create notification for post owner
        createNotification(
            $post['user_id'],
            'new_comment',
            'New Comment 💬',
            $commenter['full_name'] . ' commented: "' . substr($comment, 0, 50) . '"',
            $post_id
        );
    }
    
    $conn->close();
    return ['success' => $result];
}

function getComments($post_id) {
    $conn = getNotifConnection();
    
    $stmt = $conn->prepare("SELECT c.*, u.full_name, u.username 
                            FROM community_comments c 
                            JOIN users u ON c.user_id = u.id 
                            WHERE c.post_id = ? 
                            ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();
    
    return $comments;
}

// Activity approval notification
function notifyActivityStatusChange($activity_id, $user_id, $status, $points = 0) {
    if ($status == 'approved') {
        $title = 'Activity Approved! 🎉';
        $message = "Your recycling activity has been approved! You earned $points points.";
        $type = 'activity_approved';
    } else {
        $title = 'Activity Needs Revision 😢';
        $message = "Your recycling activity was not approved. Please check the guidelines and try again.";
        $type = 'activity_rejected';
    }
    
    return createNotification($user_id, $type, $title, $message, $activity_id);
}
?>