<?php
$page_title = 'Community';
$current_page = 'community';
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Get user's privacy setting
$privacyQuery = "SELECT default_privacy FROM users WHERE id = ?";
$stmt = $conn->prepare($privacyQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_privacy = $stmt->get_result()->fetch_assoc()['default_privacy'] ?? 'public';
$stmt->close();

// Handle follow/unfollow
if (isset($_GET['follow']) && is_numeric($_GET['follow'])) {
    $target_id = intval($_GET['follow']);
    if ($target_id != $user_id) {
        $checkSql = "SELECT id FROM followers WHERE follower_id = ? AND following_id = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("ii", $user_id, $target_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if (!$exists) {
            $insertSql = "INSERT INTO followers (follower_id, following_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("ii", $user_id, $target_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// Handle unfollow
if (isset($_GET['unfollow']) && is_numeric($_GET['unfollow'])) {
    $target_id = intval($_GET['unfollow']);
    $deleteSql = "DELETE FROM followers WHERE follower_id = ? AND following_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("ii", $user_id, $target_id);
    $stmt->execute();
    $stmt->close();
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// Handle edit post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_post'])) {
    $post_id = intval($_POST['post_id']);
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $privacy = $_POST['privacy'];
    
    $checkSql = "SELECT user_id, image_path FROM community_posts WHERE id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($post && $post['user_id'] == $user_id) {
        $image_path = $post['image_path'];
        
        if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (in_array($_FILES['edit_image']['type'], $allowed_types)) {
                if ($image_path && file_exists($image_path)) {
                    unlink($image_path);
                }
                $upload_dir = 'assets/uploads/community/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = 'post_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
                $image_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['edit_image']['tmp_name'], $image_path);
            }
        }
        
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
            $image_path = null;
        }
        
        $updateSql = "UPDATE community_posts SET title = ?, content = ?, privacy = ?, image_path = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ssssi", $title, $content, $privacy, $image_path, $post_id);
        $stmt->execute();
        $stmt->close();
        $success = "Post updated successfully!";
    } else {
        $error = "You can only edit your own posts.";
    }
}

// Handle delete post
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
        $success = "Post deleted successfully!";
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// Handle create new post
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

// Handle update privacy setting
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

// Handle like/unlike
if (isset($_GET['like']) && is_numeric($_GET['like'])) {
    $post_id = intval($_GET['like']);
    $checkSql = "SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if (!$exists) {
        $insertSql = "INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $updateSql = "UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// Handle unlike
if (isset($_GET['unlike']) && is_numeric($_GET['unlike'])) {
    $post_id = intval($_GET['unlike']);
    $deleteSql = "DELETE FROM post_likes WHERE post_id = ? AND user_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $updateSql = "UPDATE community_posts SET likes_count = likes_count - 1 WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// Handle add comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $post_id = intval($_POST['post_id']);
    $comment = trim($_POST['comment']);
    
    if (!empty($comment)) {
        $sql = "INSERT INTO community_comments (post_id, user_id, content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $post_id, $user_id, $comment);
        $stmt->execute();
        $stmt->close();
        
        $updateSql = "UPDATE community_posts SET comments_count = comments_count + 1 WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: community.php?feed=' . ($_GET['feed'] ?? 'all'));
    exit();
}

// Get feed based on user preference
$feedType = $_GET['feed'] ?? 'all';

if ($feedType == 'following') {
    $postsQuery = "SELECT p.*, u.username, u.full_name, u.points, u.profile_image,
                   (SELECT COUNT(*) FROM followers WHERE follower_id = ? AND following_id = p.user_id) as is_following
                   FROM community_posts p 
                   INNER JOIN users u ON p.user_id = u.id 
                   WHERE p.is_hidden = 0 
                   AND (p.privacy = 'public' OR (p.privacy = 'followers' AND EXISTS (SELECT 1 FROM followers WHERE follower_id = ? AND following_id = p.user_id)) OR p.user_id = ?)
                   AND (p.user_id IN (SELECT following_id FROM followers WHERE follower_id = ?) OR p.user_id = ?)
                   ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($postsQuery);
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $posts = $stmt->get_result();
    $stmt->close();
} elseif ($feedType == 'my') {
    $postsQuery = "SELECT p.*, u.username, u.full_name, u.points, u.profile_image,
                   1 as is_following
                   FROM community_posts p 
                   INNER JOIN users u ON p.user_id = u.id 
                   WHERE p.is_hidden = 0 AND p.user_id = ?
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
                   WHERE p.is_hidden = 0 
                   AND (p.privacy = 'public' OR (p.privacy = 'followers' AND EXISTS (SELECT 1 FROM followers WHERE follower_id = ? AND following_id = p.user_id)) OR p.user_id = ?)
                   ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($postsQuery);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $posts = $stmt->get_result();
    $stmt->close();
}

// Get user's liked posts
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

// Get user's stats for sidebar
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

// Get suggested users to follow
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Poppins', sans-serif; }

        .navbar-custom {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #4CAF50, #8BC34A); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { font-size: 20px; color: white; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #4CAF50; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom i { font-size: 16px; }
        .nav-link-custom:hover { background: rgba(76, 175, 80, 0.15); color: #4CAF50; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        .user-dropdown { position: relative; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(255,255,255,0.08); border-radius: 40px; transition: all 0.3s ease; }
        .user-trigger:hover { background: rgba(255,255,255,0.15); }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 15px; color: white; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-arrow { color: rgba(255,255,255,0.6); font-size: 12px; transition: transform 0.3s; }
        .user-dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        .dropdown-menu-custom { position: absolute; top: 55px; right: 0; width: 220px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
        .user-dropdown:hover .dropdown-menu-custom { opacity: 1; visibility: visible; top: 60px; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; transition: all 0.2s; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:last-child { border-bottom: none; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #4CAF50; }
        .dropdown-item-custom i { width: 20px; color: #4CAF50; }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #1a1a2e; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .mobile-nav a i { width: 24px; }
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } .user-trigger { padding: 6px 12px; } .navbar-container { padding: 0 15px; } }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1000px; margin: 0 auto; padding: 25px; }
        .feed-tabs { display: flex; gap: 10px; background: white; border-radius: 15px; padding: 5px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .feed-tab { flex: 1; padding: 10px; border: none; background: none; border-radius: 12px; font-weight: 500; color: #666; transition: all 0.3s; }
        .feed-tab:hover { background: #f0f2f5; }
        .feed-tab.active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
        .sidebar-right { position: sticky; top: 80px; }
        .profile-card { background: white; border-radius: 24px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .profile-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
        .profile-card-bg { background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); height: 100px; }
        .profile-card-content { padding: 0 24px 24px 24px; text-align: center; position: relative; }
        .profile-avatar-wrapper { position: relative; display: inline-block; margin-top: -40px; margin-bottom: 12px; }
        .profile-avatar-large { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 600; color: white; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow: hidden; }
        .profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
        .profile-level-badge { position: absolute; bottom: -5px; right: -10px; background: linear-gradient(135deg, #FFD700, #FFA000); color: #333; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border: 2px solid white; }
        .profile-name { font-size: 20px; font-weight: 700; margin-bottom: 4px; color: #1a1a2e; }
        .profile-username { font-size: 13px; color: #666; margin-bottom: 16px; }
        .profile-stats-row { display: flex; justify-content: center; align-items: center; gap: 16px; padding: 16px 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; margin-bottom: 16px; }
        .profile-stat { text-align: center; }
        .profile-stat-value { font-size: 22px; font-weight: 700; color: #4CAF50; }
        .profile-stat-label { font-size: 11px; color: #999; }
        .profile-stat-divider { width: 1px; height: 30px; background: #f0f0f0; }
        .points-progress { margin-bottom: 20px; }
        .points-progress-header { display: flex; justify-content: space-between; font-size: 12px; color: #666; margin-bottom: 8px; }
        .progress-bar-custom { height: 6px; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); border-radius: 10px; transition: width 0.5s ease; }
        .points-next { font-size: 10px; color: #999; text-align: right; }
        .profile-btn { display: block; width: 100%; padding: 10px; background: #f8f9fa; border-radius: 40px; text-align: center; color: #4CAF50; text-decoration: none; font-weight: 500; font-size: 13px; transition: all 0.3s; }
        .profile-btn:hover { background: #4CAF50; color: white; }
        .privacy-card { background: white; border-radius: 24px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .privacy-card-header { padding: 16px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 14px; }
        .privacy-card-body { padding: 16px 20px; }
        .privacy-option { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; cursor: pointer; transition: all 0.3s; margin-bottom: 8px; }
        .privacy-option:hover { background: #f8f9fa; }
        .privacy-option-icon { font-size: 24px; width: 40px; text-align: center; }
        .privacy-option-info { flex: 1; }
        .privacy-option-title { font-weight: 600; font-size: 14px; color: #333; }
        .privacy-option-desc { font-size: 11px; color: #999; }
        .privacy-option-radio input { accent-color: #4CAF50; width: 18px; height: 18px; cursor: pointer; }
        .privacy-save-btn { width: 100%; padding: 10px; background: #4CAF50; color: white; border: none; border-radius: 40px; font-weight: 500; font-size: 13px; margin-top: 12px; transition: all 0.3s; }
        .suggested-card { background: white; border-radius: 24px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .suggested-card-header { padding: 16px 20px; background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; font-weight: 600; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
        .refresh-suggest { cursor: pointer; opacity: 0.8; transition: all 0.3s; }
        .refresh-suggest:hover { opacity: 1; transform: rotate(180deg); }
        .suggested-card-body { padding: 8px 16px; }
        .suggested-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .suggested-item:last-child { border-bottom: none; }
        .suggested-item-left { display: flex; align-items: center; gap: 12px; }
        .suggested-avatar { width: 44px; height: 44px; min-width: 44px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 600; color: white; overflow: hidden; flex-shrink: 0; }
        .suggested-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .suggested-name { font-weight: 600; font-size: 14px; color: #333; }
        .suggested-stats { font-size: 11px; color: #999; }
        .suggested-follow-btn { background: #4CAF50; color: white; border: none; padding: 6px 16px; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.3s; }
        .eco-card { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 24px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .eco-card-header { padding: 16px 20px; background: rgba(76, 175, 80, 0.2); font-weight: 600; font-size: 14px; color: #2E7D32; }
        .eco-card-body { padding: 16px 20px; }
        .eco-stat { display: flex; align-items: center; gap: 12px; padding: 10px 0; }
        .eco-stat-icon { font-size: 28px; width: 45px; text-align: center; }
        .eco-stat-info { flex: 1; }
        .eco-stat-value { font-size: 16px; font-weight: 700; color: #2E7D32; }
        .eco-stat-label { font-size: 11px; color: #555; }
        .create-post-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .create-post-header { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .create-post-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: white; }
        .create-post-input { flex: 1; background: #f0f2f5; border: none; border-radius: 30px; padding: 12px 20px; font-size: 14px; cursor: pointer; }
        .post-card { background: white; border-radius: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; transition: all 0.3s ease; }
        .post-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        .post-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; }
        .post-user { display: flex; align-items: center; gap: 12px; }
        .post-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: white; }
        .post-user-name { font-weight: 600; font-size: 15px; color: #1a1a2e; }
        .post-time { font-size: 11px; color: #65676b; }
        .privacy-badge { font-size: 10px; background: #f0f2f5; padding: 2px 8px; border-radius: 20px; margin-left: 8px; }
        .following-badge { background: #e8f5e9; color: #4CAF50; padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #4CAF50; }
        .btn-follow { background: #4CAF50; color: white; border: none; padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .post-menu-btn { background: none; border: none; padding: 8px; border-radius: 50%; cursor: pointer; color: #666; }
        .post-menu-btn:hover { background: #f0f2f5; }
        .post-content { padding: 0 20px 15px 20px; }
        .post-title { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
        .post-text { font-size: 14px; color: #333; line-height: 1.5; }
        .post-image { width: 100%; max-height: 400px; object-fit: cover; border-radius: 12px; margin-top: 10px; cursor: pointer; }
        .activity-stats { display: flex; gap: 12px; margin: 12px 0; flex-wrap: wrap; }
        .stat-badge { background: #f8f9fa; padding: 6px 14px; border-radius: 30px; display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 500; color: #1a1a2e; border: 1px solid #e0e0e0; }
        .stat-icon { font-size: 14px; }
        .stat-value { font-weight: 600; color: #4CAF50; }
        .post-stats { display: flex; gap: 20px; padding: 10px 20px; border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; color: #65676b; font-size: 13px; }
        .post-actions { display: flex; padding: 5px 10px; }
        .action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px; border-radius: 10px; cursor: pointer; color: #65676b; font-weight: 500; font-size: 14px; background: none; border: none; transition: all 0.2s ease; }
        .action-btn:hover { background: #f0f2f5; }
        .action-btn.liked { color: #e74c3c; }
        .comment-section { padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #e0e0e0; display: none; }
        .comment-item { display: flex; gap: 10px; margin-bottom: 12px; }
        .comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: #4CAF50; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: white; }
        .comment-bubble { flex: 1; background: white; padding: 8px 12px; border-radius: 18px; font-size: 13px; }
        .comment-name { font-weight: 600; font-size: 12px; margin-bottom: 3px; }
        .comment-text { color: #333; }
        .comment-time { font-size: 10px; color: #999; margin-top: 3px; }
        .comment-input { display: flex; gap: 10px; margin-top: 12px; }
        .comment-input-field { flex: 1; background: white; border: 1px solid #e0e0e0; border-radius: 30px; padding: 10px 15px; font-size: 13px; }
        .comment-submit { background: #4CAF50; color: white; border: none; border-radius: 30px; padding: 8px 18px; font-size: 13px; font-weight: 500; }
        @media (max-width: 768px) { .container-custom { padding: 15px; } .sidebar-right { display: none; } }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom"><div class="logo-icon">
                    <img src="assets/images/umpsa.png" alt="Logo" style="height:25px; object-fit:cover;">
                </div><div class="logo-text">Ecos<span>+</span></div></a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Insights</a></li>
                <li><a href="community.php" class="nav-link-custom active"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-dropdown">
                <div class="user-trigger"><div class="user-avatar"><?php echo $navbar_initial; ?></div><div class="user-info"><span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></span><span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span></div><i class="fas fa-chevron-down dropdown-arrow"></i></div>
                <div class="dropdown-menu-custom"><a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a><a href="rewards.php" class="dropdown-item-custom"><i class="fas fa-gift"></i> My Rewards</a><div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div><a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
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
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Insights</a></li>
        <li><a href="community.php" class="active"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <?php if (isAdmin()): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <div class="row">
        <div class="col-lg-8">
            <div class="feed-tabs">
                <button class="feed-tab <?php echo ($feedType == 'all') ? 'active' : ''; ?>" onclick="location.href='?feed=all'">🌍 All</button>
                <button class="feed-tab <?php echo ($feedType == 'following') ? 'active' : ''; ?>" onclick="location.href='?feed=following'">👥 Following</button>
                <button class="feed-tab <?php echo ($feedType == 'my') ? 'active' : ''; ?>" onclick="location.href='?feed=my'">📝 My Posts</button>
            </div>

            <div class="create-post-card">
                <div class="create-post-header">
                    <div class="create-post-avatar"><?php echo $navbar_initial; ?></div>
                    <div class="create-post-input" data-bs-toggle="modal" data-bs-target="#createPostModal">What's on your mind, <?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?>?</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-success flex-1" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-camera"></i> Photo</button>
                    <button class="btn btn-sm btn-outline-success flex-1" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-recycle"></i> Recycling Tip</button>
                    <button class="btn btn-sm btn-outline-success flex-1" data-bs-toggle="modal" data-bs-target="#createPostModal"><i class="fas fa-award"></i> Achievement</button>
                </div>
            </div>

            <?php if ($posts->num_rows > 0): ?>
                <?php while($post = $posts->fetch_assoc()): 
                    $isLiked = in_array($post['id'], $likedPosts);
                    $isFollowing = $post['is_following'] ?? 0;
                    $privacyIcon = $post['privacy'] == 'public' ? '🌍' : ($post['privacy'] == 'followers' ? '👥' : '🔒');
                    $isOwner = ($post['user_id'] == $user_id);
                    preg_match_all('/(\d+)/', $post['content'], $numbers);
                    $itemCount = !empty($numbers[1]) ? $numbers[1][0] : rand(1, 15);
                ?>
                    <div class="post-card">
                        <div class="post-header">
                            <div class="post-user"><div class="post-avatar"><?php echo strtoupper(substr($post['full_name'], 0, 1)); ?></div><div><div class="post-user-name"><?php echo htmlspecialchars($post['full_name']); ?><span class="privacy-badge"><?php echo $privacyIcon; ?> <?php echo ucfirst($post['privacy']); ?></span></div><div class="post-time"><?php echo date('M d, Y h:i A', strtotime($post['created_at'])); ?></div></div></div>
                            <div>
                                <?php if ($post['user_id'] != $user_id): ?>
                                    <?php if ($isFollowing): ?>
                                        <span class="following-badge"><i class="fas fa-user-check"></i> Following</span>
                                    <?php else: ?>
                                        <button class="btn-follow" onclick="location.href='?follow=<?php echo $post['user_id']; ?>&feed=<?php echo $feedType; ?>'">+ Follow</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isOwner || isAdmin()): ?>
                                    <div class="dropdown d-inline-block ms-2">
                                        <button class="post-menu-btn" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($isOwner): ?>
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editPostModal" onclick="populateEditModal(<?php echo $post['id']; ?>, '<?php echo addslashes($post['title']); ?>', '<?php echo addslashes($post['content']); ?>', '<?php echo $post['privacy']; ?>', '<?php echo addslashes($post['image_path']); ?>')"><i class="fas fa-edit"></i> Edit Post</a></li>
                                            <?php endif; ?>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="if(confirm('Delete this post?')) location.href='?delete=<?php echo $post['id']; ?>&feed=<?php echo $feedType; ?>'"><i class="fas fa-trash-alt"></i> Delete Post</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="post-content">
                            <?php if ($post['title']): ?><div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div><?php endif; ?>
                            <div class="post-text"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                            <div class="activity-stats"><div class="stat-badge"><span class="stat-icon">♻️</span><span class="stat-value"><?php echo $itemCount; ?> items</span></div><div class="stat-badge"><span class="stat-icon">🌍</span><span class="stat-value"><?php echo round($itemCount * 0.5); ?> kg CO₂</span></div><div class="stat-badge"><span class="stat-icon">⭐</span><span class="stat-value">+<?php echo $post['points']; ?> pts</span></div></div>
                            <?php if ($post['image_path'] && file_exists($post['image_path'])): ?><img src="<?php echo htmlspecialchars($post['image_path']); ?>" class="post-image" alt="Post image" onclick="openImageModal(this.src)"><?php endif; ?>
                        </div>
                        <div class="post-stats"><span><i class="fas fa-heart" style="color: #e74c3c;"></i> <?php echo $post['likes_count']; ?> likes</span><span><i class="fas fa-comment"></i> <?php echo $post['comments_count']; ?> comments</span></div>
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
                            <form method="POST" class="comment-input"><input type="hidden" name="post_id" value="<?php echo $post['id']; ?>"><input type="text" class="comment-input-field" name="comment" placeholder="Write a comment..." required><button type="submit" name="add_comment" class="comment-submit">Post</button></form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-3" style="border-radius: 20px; padding: 50px;"><i class="fas fa-newspaper" style="font-size: 64px; color: #ccc;"></i><p class="mt-3 text-muted">No posts to show.</p><?php if ($feedType == 'following'): ?><p class="small text-muted">Follow some users to see their posts here!</p><?php else: ?><button class="btn btn-success mt-2" data-bs-toggle="modal" data-bs-target="#createPostModal">Create First Post</button><?php endif; ?></div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="sidebar-right">
                <div class="profile-card"><div class="profile-card-bg"></div><div class="profile-card-content"><div class="profile-avatar-wrapper"><div class="profile-avatar-large"><?php if (!empty($navbar_user['profile_image']) && file_exists($navbar_user['profile_image'])): ?><img src="<?php echo $navbar_user['profile_image']; ?>" alt="Profile"><?php else: ?><?php echo $navbar_initial; ?><?php endif; ?></div><div class="profile-level-badge"><?php $level = floor(($navbar_user['points'] ?? 0) / 100) + 1; echo "Lv.$level"; ?></div></div><h4 class="profile-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></h4><div class="profile-username">@<?php echo htmlspecialchars($navbar_user['username'] ?? 'user'); ?></div><div class="profile-stats-row"><div class="profile-stat"><div class="profile-stat-value"><?php echo $totalActivities; ?></div><div class="profile-stat-label">Activities</div></div><div class="profile-stat-divider"></div><div class="profile-stat"><div class="profile-stat-value"><?php echo $followersCount; ?></div><div class="profile-stat-label">Followers</div></div><div class="profile-stat-divider"></div><div class="profile-stat"><div class="profile-stat-value"><?php echo $followingCount; ?></div><div class="profile-stat-label">Following</div></div></div><div class="points-progress"><div class="points-progress-header"><span><i class="fas fa-star"></i> Total Points</span><span><?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span></div><div class="progress-bar-custom"><div class="progress-fill" style="width: <?php echo min(100, (($navbar_user['points'] ?? 0) % 100) / 100 * 100); ?>%"></div></div><div class="points-next"><?php echo 100 - (($navbar_user['points'] ?? 0) % 100); ?> points to next level</div></div><a href="profile.php" class="profile-btn">View Full Profile <i class="fas fa-arrow-right"></i></a></div></div>

                <div class="privacy-card"><div class="privacy-card-header"><i class="fas fa-shield-alt"></i> Privacy Mode</div><div class="privacy-card-body"><form method="POST" id="privacyForm"><div class="privacy-option" onclick="document.getElementById('privacy_public').click()"><div class="privacy-option-icon">🌍</div><div class="privacy-option-info"><div class="privacy-option-title">Public</div><div class="privacy-option-desc">Everyone can see your posts</div></div><div class="privacy-option-radio"><input type="radio" name="default_privacy" value="public" id="privacy_public" <?php echo ($user_privacy == 'public') ? 'checked' : ''; ?>></div></div><div class="privacy-option" onclick="document.getElementById('privacy_followers').click()"><div class="privacy-option-icon">👥</div><div class="privacy-option-info"><div class="privacy-option-title">Followers Only</div><div class="privacy-option-desc">Only your followers can see</div></div><div class="privacy-option-radio"><input type="radio" name="default_privacy" value="followers" id="privacy_followers" <?php echo ($user_privacy == 'followers') ? 'checked' : ''; ?>></div></div><div class="privacy-option" onclick="document.getElementById('privacy_private').click()"><div class="privacy-option-icon">🔒</div><div class="privacy-option-info"><div class="privacy-option-title">Only Me</div><div class="privacy-option-desc">Only you can see your posts</div></div><div class="privacy-option-radio"><input type="radio" name="default_privacy" value="private" id="privacy_private" <?php echo ($user_privacy == 'private') ? 'checked' : ''; ?>></div></div><button type="submit" name="update_privacy" class="privacy-save-btn">Save Privacy Setting</button></form></div></div>

                <?php if ($suggestedUsers->num_rows > 0): ?>
                <div class="suggested-card"><div class="suggested-card-header"><i class="fas fa-user-plus"></i> Suggested for You<span class="refresh-suggest" onclick="location.reload()"><i class="fas fa-sync-alt"></i></span></div><div class="suggested-card-body"><?php while($suggest = $suggestedUsers->fetch_assoc()): $suggestInitial = strtoupper(substr($suggest['full_name'], 0, 1)); $suggestAvatar = !empty($suggest['profile_image']) && file_exists($suggest['profile_image']) ? $suggest['profile_image'] : null; ?><div class="suggested-item"><div class="suggested-item-left"><div class="suggested-avatar"><?php if ($suggestAvatar): ?><img src="<?php echo $suggestAvatar; ?>" alt="<?php echo htmlspecialchars($suggest['full_name']); ?>"><?php else: ?><?php echo $suggestInitial; ?><?php endif; ?></div><div class="suggested-info"><div class="suggested-name"><?php echo htmlspecialchars($suggest['full_name']); ?></div><div class="suggested-stats"><i class="fas fa-star" style="color: #FFD700; font-size: 10px;"></i> <?php echo number_format($suggest['points']); ?> pts</div></div></div><button class="suggested-follow-btn" onclick="location.href='?follow=<?php echo $suggest['id']; ?>&feed=<?php echo $feedType; ?>'"><i class="fas fa-user-plus"></i> Follow</button></div><?php endwhile; ?></div></div>
                <?php endif; ?>

                <div class="eco-card"><div class="eco-card-header"><i class="fas fa-leaf"></i> Your Eco Impact</div><div class="eco-card-body"><div class="eco-stat"><div class="eco-stat-icon">🌍</div><div class="eco-stat-info"><div class="eco-stat-value"><?php echo round($totalActivities * 2.5, 1); ?> kg</div><div class="eco-stat-label">CO₂ Saved</div></div></div><div class="eco-stat"><div class="eco-stat-icon">🌳</div><div class="eco-stat-info"><div class="eco-stat-value"><?php echo round($totalActivities * 2.5 / 21); ?></div><div class="eco-stat-label">Trees Equivalent</div></div></div><div class="eco-stat"><div class="eco-stat-icon">🥤</div><div class="eco-stat-info"><div class="eco-stat-value"><?php echo $totalActivities * 50; ?></div><div class="eco-stat-label">Plastic Bottles Saved</div></div></div></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-pen-alt"></i> Create Post</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Who can see this?</label><select name="privacy" class="form-select form-select-sm"><option value="public" <?php echo ($user_privacy == 'public') ? 'selected' : ''; ?>>🌍 Public - Everyone</option><option value="followers" <?php echo ($user_privacy == 'followers') ? 'selected' : ''; ?>>👥 Followers Only</option><option value="private">🔒 Only Me</option></select></div>
                    <div class="mb-3"><input type="text" class="form-control" name="title" placeholder="Title (optional)"></div>
                    <div class="mb-3"><textarea class="form-control" name="content" rows="4" placeholder="Share your recycling achievement, tips, or ask questions..." required></textarea></div>
                    <div class="mb-3"><label class="form-label"><i class="fas fa-image"></i> Add Photo</label><input type="file" class="form-control" name="post_image" accept="image/*" onchange="previewImage(this)"><div id="imagePreview" class="mt-2"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="create_post" class="btn btn-success">Post</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Post Modal -->
<div class="modal fade" id="editPostModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fas fa-edit"></i> Edit Post</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="post_id" id="edit_post_id">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Who can see this?</label><select name="privacy" id="edit_privacy" class="form-select form-select-sm"><option value="public">🌍 Public - Everyone</option><option value="followers">👥 Followers Only</option><option value="private">🔒 Only Me</option></select></div>
                    <div class="mb-3"><input type="text" class="form-control" name="title" id="edit_title" placeholder="Title (optional)"></div>
                    <div class="mb-3"><textarea class="form-control" name="content" id="edit_content" rows="4" required></textarea></div>
                    <div class="mb-3"><label class="form-label"><i class="fas fa-image"></i> Change Photo</label><input type="file" class="form-control" name="edit_image" accept="image/*" onchange="previewEditImage(this)"><div id="editImagePreview" class="mt-2"></div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage"><label class="form-check-label text-danger" for="removeImage">Remove current image</label></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_post" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content"><div class="modal-body text-center p-0"><img id="modalImage" src="" style="max-width: 100%; border-radius: 10px;"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div>
    </div>
</div>

<script>
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

    function toggleComments(postId) {
        const el = document.getElementById(`comments-${postId}`);
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
        if (el.style.display === 'block' && !document.getElementById(`comments-list-${postId}`).innerHTML) {
            loadComments(postId);
        }
    }
    
    function loadComments(postId) {
        const container = document.getElementById(`comments-list-${postId}`);
        container.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-success"></div></div>';
        fetch(`get_comments.php?post_id=${postId}`)
            .then(r => r.json())
            .then(data => {
                if (data.comments && data.comments.length) {
                    let html = '';
                    data.comments.forEach(c => {
                        html += `<div class="comment-item"><div class="comment-avatar">${c.initial}</div><div class="comment-bubble"><strong class="small">${escapeHtml(c.full_name)}</strong><p class="mb-0 small">${escapeHtml(c.content)}</p><small class="text-muted">${c.time}</small></div></div>`;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="text-muted text-center py-2">No comments yet.</div>';
                }
            });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function sharePost() {
        navigator.clipboard.writeText(window.location.href);
        alert('Link copied! Share with your friends.');
    }
    
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        preview.innerHTML = '';
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '100%';
                img.style.borderRadius = '10px';
                img.style.marginTop = '10px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function previewEditImage(input) {
        const preview = document.getElementById('editImagePreview');
        preview.innerHTML = '';
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '100%';
                img.style.borderRadius = '10px';
                img.style.marginTop = '10px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function populateEditModal(id, title, content, privacy, imagePath) {
        document.getElementById('edit_post_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_content').value = content;
        document.getElementById('edit_privacy').value = privacy;
        document.getElementById('editImagePreview').innerHTML = '';
        document.getElementById('removeImage').checked = false;
        if (imagePath && imagePath !== 'null' && imagePath !== '') {
            const img = document.createElement('img');
            img.src = imagePath;
            img.style.maxWidth = '100%';
            img.style.borderRadius = '10px';
            img.style.marginTop = '10px';
            document.getElementById('editImagePreview').appendChild(img);
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