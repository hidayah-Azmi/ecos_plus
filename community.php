<?php
$page_title = 'Community';
$current_page = 'community';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';
$unreadCount = getUnreadCount($user_id);

// Get user's privacy setting
$privacyQuery = "SELECT default_privacy FROM users WHERE id = ?";
$stmt = $conn->prepare($privacyQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_privacy = $stmt->get_result()->fetch_assoc()['default_privacy'] ?? 'public';
$stmt->close();

// ========== FOLLOW/UNFOLLOW ==========
if (isset($_GET['follow']) && is_numeric($_GET['follow'])) {
    $target_id = intval($_GET['follow']);
    if ($target_id != $user_id) {
        $result = followUser($user_id, $target_id);
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

if (isset($_GET['unfollow']) && is_numeric($_GET['unfollow'])) {
    $target_id = intval($_GET['unfollow']);
    $result = unfollowUser($user_id, $target_id);
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// ========== DELETE POST ==========
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $post_id = intval($_GET['delete']);
    $checkSql = "SELECT user_id, image_path FROM community_posts WHERE id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($post && ($post['user_id'] == $user_id || isAdmin())) {
        if ($post['image_path'] && file_exists($post['image_path'])) {
            unlink($post['image_path']);
        }
        $conn->query("DELETE FROM community_comments WHERE post_id = $post_id");
        $conn->query("DELETE FROM post_likes WHERE post_id = $post_id");
        $conn->query("DELETE FROM community_posts WHERE id = $post_id");
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// ========== CREATE NEW POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $content = trim($_POST['content']);
    $title = trim($_POST['title'] ?? '');
    $privacy = $_POST['privacy'] ?? 'public';
    
    if (empty($content)) {
        $error = 'Please enter some content for your post';
    } else {
        $image_path = null;
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (in_array($_FILES['post_image']['type'], $allowed_types)) {
                $upload_dir = 'assets/uploads/community/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = 'post_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
                $image_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['post_image']['tmp_name'], $image_path);
            } else {
                $error = 'Only JPG, PNG, and WEBP images are allowed.';
            }
        }
        
        if (empty($error)) {
            $sql = "INSERT INTO community_posts (user_id, title, content, image_path, privacy) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $user_id, $title, $content, $image_path, $privacy);
            if ($stmt->execute()) {
                $success = "Post shared successfully!";
                $_POST = array();
            } else {
                $error = "Failed to share post.";
            }
            $stmt->close();
        }
    }
}

// ========== UPDATE PRIVACY ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_privacy'])) {
    $default_privacy = $_POST['default_privacy'];
    $updateSql = "UPDATE users SET default_privacy = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si", $default_privacy, $user_id);
    $stmt->execute();
    $stmt->close();
    $user_privacy = $default_privacy;
    $success = "Privacy setting updated!";
}

// ========== LIKE/UNLIKE ==========
if (isset($_GET['like']) && is_numeric($_GET['like'])) {
    $post_id = intval($_GET['like']);
    likePost($post_id, $user_id);
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

if (isset($_GET['unlike']) && is_numeric($_GET['unlike'])) {
    $post_id = intval($_GET['unlike']);
    unlikePost($post_id, $user_id);
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// ========== ADD COMMENT ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $post_id = intval($_POST['post_id']);
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        addComment($post_id, $user_id, $comment);
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// ========== GET FEED ==========
$feedType = $_GET['feed'] ?? 'all';

if ($feedType == 'following') {
    $postsQuery = "SELECT p.*, u.username, u.full_name, u.points, u.profile_image,
                   (SELECT COUNT(*) FROM followers WHERE follower_id = ? AND following_id = p.user_id) as is_following
                   FROM community_posts p 
                   INNER JOIN users u ON p.user_id = u.id 
                   WHERE (p.privacy = 'public' OR (p.privacy = 'followers' AND EXISTS (SELECT 1 FROM followers WHERE follower_id = ? AND following_id = p.user_id)) OR p.user_id = ?)
                   AND p.user_id IN (SELECT following_id FROM followers WHERE follower_id = ?) 
                   ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($postsQuery);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $posts = $stmt->get_result();
    $stmt->close();
} elseif ($feedType == 'my') {
    $postsQuery = "SELECT p.*, u.username, u.full_name, u.points, u.profile_image, 1 as is_following
                   FROM community_posts p 
                   INNER JOIN users u ON p.user_id = u.id 
                   WHERE p.user_id = ? 
                   ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($postsQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $posts = $stmt->get_result();
    $stmt->close();
} else {
    $postsQuery = "SELECT p.*, u.username, u.full_name, u.points, u.profile_image,
                   (SELECT COUNT(*) FROM followers WHERE follower_id = ? AND following_id = p.user_id) as is_following
                   FROM community_posts p 
                   INNER JOIN users u ON p.user_id = u.id 
                   WHERE p.privacy = 'public' OR (p.privacy = 'followers' AND EXISTS (SELECT 1 FROM followers WHERE follower_id = ? AND following_id = p.user_id)) OR p.user_id = ?
                   ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($postsQuery);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $posts = $stmt->get_result();
    $stmt->close();
}

// ========== GET LIKED POSTS ==========
$likedQuery = "SELECT post_id FROM post_likes WHERE user_id = ?";
$stmt = $conn->prepare($likedQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$likedResult = $stmt->get_result();
$likedPosts = [];
while($row = $likedResult->fetch_assoc()) {
    $likedPosts[] = $row['post_id'];
}
$stmt->close();

// ========== GET USER STATS ==========
$statsQuery = "SELECT COUNT(*) as total FROM activities WHERE user_id = ? AND status = 'approved'";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalActivities = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$followersQuery = "SELECT COUNT(*) as count FROM followers WHERE following_id = ?";
$stmt = $conn->prepare($followersQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$followersCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$followingQuery = "SELECT COUNT(*) as count FROM followers WHERE follower_id = ?";
$stmt = $conn->prepare($followingQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$followingCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// ========== GET SUGGESTED USERS ==========
$suggestQuery = "SELECT id, username, full_name, points, profile_image 
                 FROM users 
                 WHERE role = 'user' AND id != ? 
                 AND id NOT IN (SELECT following_id FROM followers WHERE follower_id = ?)
                 ORDER BY points DESC LIMIT 5";
$stmt = $conn->prepare($suggestQuery);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$suggestedUsers = $stmt->get_result();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Community - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0a2e1a 0%, #1a4a2a 50%, #0d3b1f 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }
        
        /* Animated Leaves */
        @keyframes leafFloat1 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(-20px,-30px) rotate(10deg); } }
        @keyframes leafFloat2 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(20px,-20px) rotate(-10deg); } }
        
        .leaf-bg { position: fixed; font-size: 45px; opacity: 0.06; pointer-events: none; z-index: 0; }
        .leaf-bg:nth-child(1) { top: 10%; left: 5%; animation: leafFloat1 12s ease-in-out infinite; }
        .leaf-bg:nth-child(2) { top: 70%; right: 8%; animation: leafFloat2 15s ease-in-out infinite; font-size: 60px; }
        .leaf-bg:nth-child(3) { top: 40%; left: 85%; animation: leafFloat1 18s ease-in-out infinite; }
        .leaf-bg:nth-child(4) { bottom: 15%; left: 10%; animation: leafFloat2 14s ease-in-out infinite; }
        
        /* Navbar */
        .navbar-custom { background: rgba(10, 46, 26, 0.95); backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); padding: 0; position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(76,175,80,0.3); }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #6B8E23, #4CAF50); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .logo-icon img { width: 30px; height: 30px; object-fit: cover; border-radius: 8px; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #8BC34A; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom:hover { background: rgba(107, 142, 35, 0.3); color: #8BC34A; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        
        .notification-wrapper { position: relative; margin-right: 10px; }
        .notification-bell { background: rgba(255,255,255,0.1); border: none; color: white; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; transition: all 0.3s; font-size: 18px; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #f44336; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 50%; min-width: 18px; text-align: center; }
        
        .user-dropdown { position: relative; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 40px; transition: all 0.3s ease; }
        .user-trigger:hover { background: rgba(107, 142, 35, 0.4); }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 15px; color: white; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-arrow { color: rgba(255,255,255,0.6); font-size: 12px; transition: transform 0.3s; }
        .user-dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        .dropdown-menu-custom { position: absolute; top: 55px; right: 0; width: 220px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
        .user-dropdown:hover .dropdown-menu-custom { opacity: 1; visibility: visible; top: 60px; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:last-child { border-bottom: none; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #6B8E23; }
        .dropdown-item-custom i { width: 20px; color: #6B8E23; }
        
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #0a2e1a; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(107, 142, 35, 0.3); color: #8BC34A; }
        .mobile-nav a i { width: 24px; }
        
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } .navbar-container { padding: 0 15px; } }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1000px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Feed Tabs */
        .feed-tabs { display: flex; gap: 10px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 8px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .feed-tab { flex: 1; padding: 12px; border: none; background: none; border-radius: 14px; font-weight: 600; color: #666; transition: all 0.3s; font-size: 14px; }
        .feed-tab:hover { background: rgba(107,142,35,0.1); }
        .feed-tab.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        
        /* Create Post Card */
        .create-post-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; padding: 20px; margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.2); }
        .create-post-header { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .create-post-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: white; }
        .create-post-input { flex: 1; background: rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); border-radius: 30px; padding: 12px 20px; font-size: 14px; cursor: pointer; transition: all 0.3s; }
        .create-post-input:hover { background: rgba(0,0,0,0.08); border-color: #6B8E23; }
        .btn-outline-success-custom { border: 1.5px solid #6B8E23; background: transparent; border-radius: 40px; padding: 10px 12px; font-size: 13px; font-weight: 500; color: #6B8E23; transition: all 0.3s; flex: 1; }
        .btn-outline-success-custom:hover { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; transform: translateY(-2px); }
        
        /* Post Card */
        .post-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; margin-bottom: 25px; overflow: hidden; transition: all 0.3s; border: 1px solid rgba(255,255,255,0.2); }
        .post-card:hover { transform: translateY(-4px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
        .post-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; background: rgba(255,255,255,0.5); }
        .post-user { display: flex; align-items: center; gap: 12px; }
        .post-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: white; overflow: hidden; }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .post-user-name { font-weight: 700; font-size: 15px; color: #1a1a2e; }
        .post-time { font-size: 11px; color: #888; margin-top: 2px; }
        .privacy-badge { font-size: 10px; background: rgba(0,0,0,0.05); padding: 2px 8px; border-radius: 20px; margin-left: 8px; }
        .following-badge { background: #e8f5e9; color: #6B8E23; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 500; }
        .btn-follow { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .btn-follow:hover { transform: scale(1.05); box-shadow: 0 2px 8px rgba(107,142,35,0.4); }
        .post-content { padding: 0 22px 15px 22px; }
        .post-title { font-size: 18px; font-weight: 700; margin-bottom: 10px; color: #1a1a2e; }
        .post-text { font-size: 14px; color: #444; line-height: 1.6; }
        .post-image { width: 100%; max-height: 400px; object-fit: cover; border-radius: 16px; margin-top: 12px; cursor: pointer; transition: all 0.3s; }
        .post-image:hover { opacity: 0.95; }
        .post-stats { display: flex; gap: 25px; padding: 12px 22px; border-top: 1px solid rgba(0,0,0,0.05); border-bottom: 1px solid rgba(0,0,0,0.05); color: #666; font-size: 13px; background: rgba(0,0,0,0.02); }
        .post-actions { display: flex; padding: 8px 15px; background: rgba(255,255,255,0.3); }
        .action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 10px; border-radius: 12px; cursor: pointer; color: #666; font-weight: 500; font-size: 14px; background: none; border: none; transition: all 0.2s; }
        .action-btn:hover { background: rgba(107,142,35,0.1); }
        .action-btn.liked { color: #e74c3c; }
        .comment-section { padding: 15px 22px; background: rgba(0,0,0,0.02); border-top: 1px solid rgba(0,0,0,0.05); display: none; border-radius: 0 0 24px 24px; }
        .comment-item { display: flex; gap: 12px; margin-bottom: 15px; }
        .comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: white; flex-shrink: 0; }
        .comment-bubble { flex: 1; background: white; padding: 10px 14px; border-radius: 18px; font-size: 13px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .comment-text { color: #333; font-size: 12px; margin-top: 3px; }
        .comment-time { font-size: 10px; color: #999; margin-top: 3px; }
        .comment-input { display: flex; gap: 10px; margin-top: 15px; }
        .comment-input-field { flex: 1; background: white; border: 1px solid #e0e0e0; border-radius: 30px; padding: 10px 16px; font-size: 13px; transition: all 0.3s; }
        .comment-input-field:focus { outline: none; border-color: #6B8E23; }
        .comment-submit { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; border-radius: 30px; padding: 8px 20px; font-size: 13px; font-weight: 500; transition: all 0.3s; }
        .comment-submit:hover { transform: scale(1.02); }
        
        /* Sidebar */
        .sidebar-right { position: sticky; top: 80px; }
        .profile-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; overflow: hidden; margin-bottom: 24px; border: 1px solid rgba(255,255,255,0.2); }
        .profile-card-bg { background: linear-gradient(135deg, #6B8E23 0%, #4CAF50 100%); height: 100px; }
        .profile-card-content { padding: 0 24px 24px 24px; text-align: center; position: relative; }
        .profile-avatar-wrapper { position: relative; display: inline-block; margin-top: -40px; margin-bottom: 12px; }
        .profile-avatar-large { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; color: white; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow: hidden; }
        .profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
        .profile-level-badge { position: absolute; bottom: -5px; right: -10px; background: linear-gradient(135deg, #FFD700, #FFA000); color: #333; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; border: 2px solid white; }
        .profile-name { font-size: 20px; font-weight: 700; margin-bottom: 4px; color: #1a1a2e; }
        .profile-username { font-size: 13px; color: #666; margin-bottom: 16px; }
        .profile-stats-row { display: flex; justify-content: center; align-items: center; gap: 16px; padding: 16px 0; border-top: 1px solid rgba(0,0,0,0.05); border-bottom: 1px solid rgba(0,0,0,0.05); margin-bottom: 16px; }
        .profile-stat-value { font-size: 22px; font-weight: 700; color: #6B8E23; }
        .profile-stat-label { font-size: 11px; color: #999; }
        .points-progress { margin-bottom: 20px; }
        .progress-bar-custom { height: 6px; background: rgba(0,0,0,0.05); border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #6B8E23, #8BC34A); border-radius: 10px; transition: width 0.5s ease; }
        .profile-btn { display: block; width: 100%; padding: 10px; background: rgba(107,142,35,0.1); border-radius: 40px; text-align: center; color: #6B8E23; text-decoration: none; font-weight: 500; font-size: 13px; transition: all 0.3s; }
        .profile-btn:hover { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; }
        
        .privacy-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; margin-bottom: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.2); }
        .privacy-card-header { padding: 16px 20px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; font-weight: 600; font-size: 14px; }
        .privacy-card-body { padding: 16px 20px; }
        .privacy-option { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; cursor: pointer; transition: all 0.3s; margin-bottom: 8px; }
        .privacy-option:hover { background: rgba(107,142,35,0.1); }
        .privacy-option-icon { font-size: 24px; width: 40px; text-align: center; }
        .privacy-option-info { flex: 1; }
        .privacy-option-title { font-weight: 600; font-size: 14px; color: #333; }
        .privacy-option-desc { font-size: 11px; color: #999; }
        .privacy-save-btn { width: 100%; padding: 10px; background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; border-radius: 40px; font-weight: 500; font-size: 13px; margin-top: 12px; transition: all 0.3s; }
        .privacy-save-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(107,142,35,0.3); }
        
        /* Suggested Users - Fixed Layout */
        .suggested-card { 
            background: rgba(255,255,255,0.95); 
            backdrop-filter: blur(10px); 
            border-radius: 24px; 
            margin-bottom: 24px; 
            overflow: hidden; 
            border: 1px solid rgba(255,255,255,0.2); 
        }
        .suggested-card-header { 
            padding: 16px 20px; 
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); 
            color: white; 
            font-weight: 600; 
            font-size: 14px; 
        }
        .suggested-card-body { 
            padding: 8px 16px; 
        }
        .suggested-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 12px 0; 
            border-bottom: 1px solid rgba(0,0,0,0.05); 
        }
        .suggested-item:last-child { 
            border-bottom: none; 
        }
        .suggested-item-left { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            flex: 1;
        }
        .suggested-avatar { 
            width: 44px; 
            height: 44px; 
            min-width: 44px; 
            border-radius: 50%; 
            background: linear-gradient(135deg, #6B8E23, #8BC34A); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 16px; 
            font-weight: 600; 
            color: white; 
            overflow: hidden; 
        }
        .suggested-avatar img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        .suggested-info { 
            flex: 1; 
        }
        .suggested-name { 
            font-weight: 600; 
            font-size: 14px; 
            color: #333; 
            margin-bottom: 2px;
        }
        .suggested-stats { 
            font-size: 11px; 
            color: #999; 
        }
        .suggested-follow-btn { 
            background: linear-gradient(135deg, #6B8E23, #4CAF50); 
            color: white; 
            border: none; 
            padding: 6px 18px; 
            border-radius: 30px; 
            font-size: 12px; 
            font-weight: 500; 
            cursor: pointer; 
            transition: all 0.3s; 
            white-space: nowrap;
        }
        .suggested-follow-btn:hover { 
            transform: scale(1.05); 
        }
        
        .eco-card { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 24px; overflow: hidden; margin-bottom: 24px; }
        .eco-card-header { padding: 16px 20px; background: rgba(107,142,35,0.2); font-weight: 600; font-size: 14px; color: #2E7D32; }
        .eco-stat { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .eco-stat:last-child { border-bottom: none; }
        .eco-stat-icon { font-size: 28px; width: 45px; text-align: center; }
        .eco-stat-value { font-size: 16px; font-weight: 700; color: #2E7D32; }
        .eco-stat-label { font-size: 11px; color: #555; }
        
        /* Modal */
        .modal-content { border-radius: 24px; background: rgba(255,255,255,0.98); }
        .modal-header { border-radius: 24px 24px 0 0; }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            .sidebar-right { display: none; }
            .feed-tab { font-size: 12px; padding: 8px; }
            .post-header { padding: 12px; }
            .post-avatar { width: 40px; height: 40px; font-size: 14px; }
            .post-content { padding: 0 15px 12px 15px; }
            .post-stats { padding: 10px 15px; }
            .comment-section { padding: 12px 15px; }
        }
    </style>
</head>
<body>

<!-- Animated Leaves -->
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-seedling"></i></div>
<div class="leaf-bg"><i class="fas fa-tree"></i></div>
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>

<!-- Navigation Bar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon"><img src="assets/logo/12.png" alt="Logo"></div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI</a></li>
                <li><a href="community.php" class="nav-link-custom active"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div style="display: flex; align-items: center; gap: 10px;">

                <div class="user-dropdown">
                    <div class="user-trigger">
                        <div class="user-avatar"><?php echo $navbar_initial; ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></span>
                            <span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu-custom">
                        <a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a>
                        <a href="rewards.php" class="dropdown-item-custom"><i class="fas fa-gift"></i> My Rewards</a>
                        <a href="history.php" class="dropdown-item-custom"><i class="fas fa-history"></i> Activity History</a>
                        <div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div>
                        <a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
            <button class="mobile-toggle" id="mobileToggleBtn"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="activity.php"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI</a></li>
        <li><a href="community.php" class="active"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle"></i> <?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Feed Tabs -->
            <div class="feed-tabs">
                <button class="feed-tab <?php echo ($feedType == 'all') ? 'active' : ''; ?>" onclick="location.href='?feed=all'"><i class="fas fa-globe"></i> All Posts</button>
                <button class="feed-tab <?php echo ($feedType == 'following') ? 'active' : ''; ?>" onclick="location.href='?feed=following'"><i class="fas fa-user-friends"></i> Following</button>
                <button class="feed-tab <?php echo ($feedType == 'my') ? 'active' : ''; ?>" onclick="location.href='?feed=my'"><i class="fas fa-user"></i> My Posts</button>
            </div>

            <!-- Create Post Card -->
            <div class="create-post-card">
                <div class="create-post-header">
                    <div class="create-post-avatar"><?php echo $navbar_initial; ?></div>
                    <div class="create-post-input" data-bs-toggle="modal" data-bs-target="#createPostModal">What's on your mind, <?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?>?</div>
                </div>
                <div class="row g-2">
                    <div class="col-4"><button class="btn-outline-success-custom w-100" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-camera"></i> Photo</button></div>
                    <div class="col-4"><button class="btn-outline-success-custom w-100" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-recycle"></i> Recycle</button></div>
                    <div class="col-4"><button class="btn-outline-success-custom w-100" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-award"></i> Achievement</button></div>
                </div>
            </div>

            <!-- Posts Feed -->
            <?php if ($posts->num_rows > 0): ?>
                <?php while($post = $posts->fetch_assoc()): 
                    $isLiked = in_array($post['id'], $likedPosts);
                    $isFollowing = $post['is_following'] ?? 0;
                    $privacyIcon = $post['privacy'] == 'public' ? '🌍' : ($post['privacy'] == 'followers' ? '👥' : '🔒');
                    $isOwner = ($post['user_id'] == $user_id);
                    $userAvatar = !empty($post['profile_image']) && file_exists($post['profile_image']) ? $post['profile_image'] : null;
                ?>
                    <div class="post-card" id="post-<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <div class="post-user">
                                <div class="post-avatar">
                                    <?php if ($userAvatar): ?>
                                        <img src="<?php echo $userAvatar; ?>" alt="<?php echo htmlspecialchars($post['full_name']); ?>">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($post['full_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="post-user-name"><?php echo htmlspecialchars($post['full_name']); ?> <span class="privacy-badge"><?php echo $privacyIcon; ?> <?php echo ucfirst($post['privacy']); ?></span></div>
                                    <div class="post-time"><i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></div>
                                </div>
                            </div>
                            <div>
                                <?php if ($post['user_id'] != $user_id): ?>
                                    <?php if ($isFollowing): ?>
                                        <span class="following-badge"><i class="fas fa-user-check"></i> Following</span>
                                    <?php else: ?>
                                        <button class="btn-follow" onclick="location.href='?follow=<?php echo $post['user_id']; ?>&feed=<?php echo $feedType; ?>'"><i class="fas fa-user-plus"></i> Follow</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isOwner || isAdmin()): ?>
                                    <div class="dropdown d-inline-block ms-2">
                                        <button class="btn btn-sm btn-light post-menu-btn" data-bs-toggle="dropdown" style="border-radius: 50%; width: 32px; height: 32px;"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item text-danger" href="#" onclick="if(confirm('Delete this post?')) location.href='?delete=<?php echo $post['id']; ?>&feed=<?php echo $feedType; ?>'"><i class="fas fa-trash-alt"></i> Delete Post</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php if ($post['title']): ?><div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div><?php endif; ?>
                            <div class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                            <?php if ($post['image_path'] && file_exists($post['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($post['image_path']); ?>" class="post-image" alt="Post image" onclick="openImageModal(this.src)">
                            <?php endif; ?>
                        </div>
                        <div class="post-stats">
                            <span><i class="fas fa-heart" style="color: #e74c3c;"></i> <?php echo $post['likes_count']; ?> likes</span>
                            <span><i class="fas fa-comment"></i> <?php echo $post['comments_count']; ?> comments</span>
                            <span><i class="fas fa-share-alt"></i> Share</span>
                        </div>
                        <div class="post-actions">
                            <?php if ($isLiked): ?>
                                <button class="action-btn liked" onclick="location.href='?unlike=<?php echo $post['id']; ?>&feed=<?php echo $feedType; ?>'"><i class="fas fa-heart"></i> Liked</button>
                            <?php else: ?>
                                <button class="action-btn" onclick="location.href='?like=<?php echo $post['id']; ?>&feed=<?php echo $feedType; ?>'"><i class="far fa-heart"></i> Like</button>
                            <?php endif; ?>
                            <button class="action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)"><i class="far fa-comment"></i> Comment</button>
                            <button class="action-btn" onclick="sharePost()"><i class="fas fa-share-alt"></i> Share</button>
                        </div>
                        <div class="comment-section" id="comments-<?php echo $post['id']; ?>">
                            <div id="comments-list-<?php echo $post['id']; ?>"></div>
                            <form method="POST" class="comment-input">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <input type="text" class="comment-input-field" name="comment" placeholder="Write a comment..." required>
                                <button type="submit" name="add_comment" class="comment-submit">Post</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-3" style="border-radius: 20px; padding: 50px; background: rgba(255,255,255,0.95);">
                    <i class="fas fa-newspaper" style="font-size: 64px; color: #ccc;"></i>
                    <p class="mt-3 text-muted">No posts to show.</p>
                    <?php if ($feedType == 'following'): ?>
                        <p class="small text-muted">Follow some users to see their posts here!</p>
                    <?php else: ?>
                        <button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-plus"></i> Create First Post</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="sidebar-right">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-card-bg"></div>
                    <div class="profile-card-content">
                        <div class="profile-avatar-wrapper">
                            <div class="profile-avatar-large">
                                <?php if (!empty($navbar_user['profile_image']) && file_exists($navbar_user['profile_image'])): ?>
                                    <img src="<?php echo $navbar_user['profile_image']; ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo $navbar_initial; ?>
                                <?php endif; ?>
                            </div>
                            <div class="profile-level-badge">Lv.<?php echo floor(($navbar_user['points'] ?? 0) / 100) + 1; ?></div>
                        </div>
                        <h4 class="profile-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></h4>
                        <div class="profile-username">@<?php echo htmlspecialchars($navbar_user['username'] ?? 'user'); ?></div>
                        <div class="profile-stats-row">
                            <div class="profile-stat text-center"><div class="profile-stat-value"><?php echo $totalActivities; ?></div><div class="profile-stat-label">Activities</div></div>
                            <div class="profile-stat text-center"><div class="profile-stat-value"><?php echo $followersCount; ?></div><div class="profile-stat-label">Followers</div></div>
                            <div class="profile-stat text-center"><div class="profile-stat-value"><?php echo $followingCount; ?></div><div class="profile-stat-label">Following</div></div>
                        </div>
                        <div class="points-progress">
                            <div class="d-flex justify-content-between small mb-1"><span><i class="fas fa-star"></i> Total Points</span><span><?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span></div>
                            <div class="progress-bar-custom"><div class="progress-fill" style="width: <?php echo min(100, (($navbar_user['points'] ?? 0) % 100) / 100 * 100); ?>%"></div></div>
                            <div class="small text-muted mt-1"><?php echo 100 - (($navbar_user['points'] ?? 0) % 100); ?> points to next level</div>
                        </div>
                        <a href="profile.php" class="profile-btn">View Full Profile <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <!-- Privacy Card -->
                <div class="privacy-card">
                    <div class="privacy-card-header"><i class="fas fa-shield-alt"></i> Privacy Mode</div>
                    <div class="privacy-card-body">
                        <form method="POST" id="privacyForm">
                            <div class="privacy-option" onclick="document.getElementById('privacy_public').click()">
                                <div class="privacy-option-icon">🌍</div>
                                <div class="privacy-option-info"><div class="privacy-option-title">Public</div><div class="privacy-option-desc">Everyone can see your posts</div></div>
                                <div class="privacy-option-radio"><input type="radio" name="default_privacy" value="public" id="privacy_public" <?php echo ($user_privacy == 'public') ? 'checked' : ''; ?>></div>
                            </div>
                            <div class="privacy-option" onclick="document.getElementById('privacy_followers').click()">
                                <div class="privacy-option-icon">👥</div>
                                <div class="privacy-option-info"><div class="privacy-option-title">Followers Only</div><div class="privacy-option-desc">Only your followers can see</div></div>
                                <div class="privacy-option-radio"><input type="radio" name="default_privacy" value="followers" id="privacy_followers" <?php echo ($user_privacy == 'followers') ? 'checked' : ''; ?>></div>
                            </div>
                            <div class="privacy-option" onclick="document.getElementById('privacy_private').click()">
                                <div class="privacy-option-icon">🔒</div>
                                <div class="privacy-option-info"><div class="privacy-option-title">Only Me</div><div class="privacy-option-desc">Only you can see your posts</div></div>
                                <div class="privacy-option-radio"><input type="radio" name="default_privacy" value="private" id="privacy_private" <?php echo ($user_privacy == 'private') ? 'checked' : ''; ?>></div>
                            </div>
                            <button type="submit" name="update_privacy" class="privacy-save-btn">Save Privacy Setting</button>
                        </form>
                    </div>
                </div>

                <!-- Suggested Users - FIXED LAYOUT -->
                <?php if ($suggestedUsers->num_rows > 0): ?>
                <div class="suggested-card">
                    <div class="suggested-card-header"><i class="fas fa-user-plus"></i> Suggested for You</div>
                    <div class="suggested-card-body">
                        <?php while($suggest = $suggestedUsers->fetch_assoc()): 
                            $suggestInitial = strtoupper(substr($suggest['full_name'], 0, 1));
                            $suggestAvatar = !empty($suggest['profile_image']) && file_exists($suggest['profile_image']) ? $suggest['profile_image'] : null;
                        ?>
                        <div class="suggested-item">
                            <div class="suggested-item-left">
                                <div class="suggested-avatar">
                                    <?php if ($suggestAvatar): ?>
                                        <img src="<?php echo $suggestAvatar; ?>" alt="<?php echo htmlspecialchars($suggest['full_name']); ?>">
                                    <?php else: ?>
                                        <?php echo $suggestInitial; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="suggested-info">
                                    <div class="suggested-name"><?php echo htmlspecialchars($suggest['full_name']); ?></div>
                                    <div class="suggested-stats"><i class="fas fa-star" style="color: #FFD700;"></i> <?php echo number_format($suggest['points']); ?> pts</div>
                                </div>
                            </div>
                            <button class="suggested-follow-btn" onclick="location.href='?follow=<?php echo $suggest['id']; ?>&feed=<?php echo $feedType; ?>'"><i class="fas fa-user-plus"></i> Follow</button>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Eco Impact Card -->
                <div class="eco-card">
                    <div class="eco-card-header"><i class="fas fa-leaf"></i> Your Eco Impact</div>
                    <div class="eco-card-body">
                        <div class="eco-stat"><div class="eco-stat-icon">🌍</div><div class="eco-stat-value"><?php echo round($totalActivities * 2.5, 1); ?> kg</div><div class="eco-stat-label">CO₂ Saved</div></div>
                        <div class="eco-stat"><div class="eco-stat-icon">🌳</div><div class="eco-stat-value"><?php echo round($totalActivities * 2.5 / 21); ?></div><div class="eco-stat-label">Trees Equivalent</div></div>
                        <div class="eco-stat"><div class="eco-stat-icon">🥤</div><div class="eco-stat-value"><?php echo $totalActivities * 50; ?></div><div class="eco-stat-label">Plastic Bottles Saved</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 24px; background: rgba(255,255,255,0.98);">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; border-radius: 24px 24px 0 0;">
                <h5 class="modal-title"><i class="fas fa-pen-alt"></i> Create Post</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Who can see this?</label><select name="privacy" class="form-select" style="border-radius: 12px;"><option value="public" <?php echo ($user_privacy == 'public') ? 'selected' : ''; ?>>🌍 Public - Everyone</option><option value="followers" <?php echo ($user_privacy == 'followers') ? 'selected' : ''; ?>>👥 Followers Only</option><option value="private">🔒 Only Me</option></select></div>
                    <div class="mb-3"><input type="text" class="form-control" name="title" placeholder="Title (optional)" style="border-radius: 12px;"></div>
                    <div class="mb-3"><textarea class="form-control" name="content" rows="4" placeholder="Share your recycling achievement, tips, or ask questions..." required style="border-radius: 12px;"></textarea></div>
                    <div class="mb-3"><label class="form-label"><i class="fas fa-image"></i> Add Photo</label><input type="file" class="form-control" name="post_image" accept="image/*" onchange="previewImage(this)" style="border-radius: 12px;"><div id="imagePreview" class="mt-2"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 30px;">Cancel</button><button type="submit" name="create_post" class="btn" style="background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border-radius: 30px;">Post</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: transparent; border: none;">
            <div class="text-end mb-2"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <img id="modalImage" src="" style="width: 100%; border-radius: 20px;">
        </div>
    </div>
</div>

<script>
    // Mobile menu toggle
    var mobileToggleBtn = document.getElementById('mobileToggleBtn');
    var mobileMenu = document.getElementById('mobileMenu');
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function() { mobileMenu.classList.toggle('show'); });
    }
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileToggleBtn && !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });

    // Notification count
    const notificationCount = document.getElementById('notificationCount');
    function updateBadgeCount(count) { 
        if (notificationCount) { 
            notificationCount.textContent = count; 
            notificationCount.style.display = count > 0 ? 'inline-block' : 'none'; 
        } 
    }
    fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {});

    // Load comments
    function toggleComments(postId) {
        const el = document.getElementById(`comments-${postId}`);
        if (!el) return;
        if (el.style.display === 'none' || !el.style.display) {
            el.style.display = 'block';
            const commentsList = document.getElementById(`comments-list-${postId}`);
            if (commentsList && !commentsList.innerHTML) {
                loadComments(postId);
            }
        } else {
            el.style.display = 'none';
        }
    }
    
    function loadComments(postId) {
        const container = document.getElementById(`comments-list-${postId}`);
        if (!container) return;
        container.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-success"></div></div>';
        
        fetch(`get_comments.php?post_id=${postId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.comments && data.comments.length > 0) {
                    let html = '';
                    data.comments.forEach(comment => {
                        html += `
                            <div class="comment-item">
                                <div class="comment-avatar">${comment.initial}</div>
                                <div class="comment-bubble">
                                    <strong class="small">${escapeHtml(comment.full_name)}</strong>
                                    <p class="mb-0 small">${escapeHtml(comment.content)}</p>
                                    <small class="text-muted">${comment.time}</small>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-muted text-center py-2">No comments yet. Be the first!</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="text-muted text-center py-2">Failed to load comments</div>';
            });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function sharePost() { 
        navigator.clipboard.writeText(window.location.href); 
        alert('🔗 Link copied! Share with your friends.'); 
    }
    
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        if (!preview) return;
        preview.innerHTML = '';
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '100%';
                img.style.borderRadius = '12px';
                img.style.marginTop = '10px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        new bootstrap.Modal(document.getElementById('imageModal')).show();
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>