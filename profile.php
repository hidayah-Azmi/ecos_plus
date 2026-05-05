<?php
$page_title = 'My Profile';
$current_page = 'profile';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$password_error = '';
$password_success = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';
$unreadCount = getUnreadCount($user_id);

// Get user data
$userQuery = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $faculty = trim($_POST['faculty']);
    $year_of_study = intval($_POST['year_of_study']);
    $bio = trim($_POST['bio']);
    
    if (empty($full_name)) {
        $error = 'Full name is required';
    } else {
        $profile_image = $user['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (in_array($_FILES['profile_image']['type'], $allowed_types)) {
                $upload_dir = 'assets/uploads/profiles/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if ($profile_image && file_exists($profile_image)) {
                    unlink($profile_image);
                }
                
                $filename = 'profile_' . $user_id . '_' . time() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $profile_image = $upload_dir . $filename;
                move_uploaded_file($_FILES['profile_image']['tmp_name'], $profile_image);
            } else {
                $error = 'Only JPG, PNG, and WEBP images are allowed for profile picture.';
            }
        }
        
        if (empty($error)) {
            $sql = "UPDATE users SET full_name = ?, phone = ?, faculty = ?, year_of_study = ?, bio = ?, profile_image = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssissi", $full_name, $phone, $faculty, $year_of_study, $bio, $profile_image, $user_id);
            
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
                $_SESSION['username'] = $full_name;
                $user['full_name'] = $full_name;
                $user['phone'] = $phone;
                $user['faculty'] = $faculty;
                $user['year_of_study'] = $year_of_study;
                $user['bio'] = $bio;
                $user['profile_image'] = $profile_image;
            } else {
                $error = "Failed to update profile.";
            }
            $stmt->close();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $passQuery = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($passQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_hash = $stmt->get_result()->fetch_assoc()['password'];
    $stmt->close();
    
    if (!password_verify($current_password, $current_hash)) {
        $password_error = 'Current password is incorrect';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New passwords do not match';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $updateSql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $new_hash, $user_id);
        
        if ($stmt->execute()) {
            $password_success = "Password changed successfully!";
        } else {
            $password_error = "Failed to change password.";
        }
        $stmt->close();
    }
}

// Get user statistics
$statsQuery = "SELECT 
                COUNT(*) as total_activities,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(points_earned) as total_points_earned
               FROM activities WHERE user_id = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user badges count
$badgesQuery = "SELECT COUNT(*) as count FROM user_badges WHERE user_id = ?";
$stmt = $conn->prepare($badgesQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$badgesCount = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get user badges list
$badgesListQuery = "SELECT b.*, ub.earned_at FROM badges b 
                    INNER JOIN user_badges ub ON b.id = ub.badge_id 
                    WHERE ub.user_id = ? 
                    ORDER BY ub.earned_at DESC";
$stmt = $conn->prepare($badgesListQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userBadges = $stmt->get_result();
$stmt->close();

// Get recent activities
$recentQuery = "SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recentQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentActivities = $stmt->get_result();
$stmt->close();

// Calculate level
$currentPoints = $user['points'] ?? 0;
$level = floor($currentPoints / 100) + 1;
$nextLevelPoints = ceil($currentPoints / 100) * 100;
$pointsToNext = $nextLevelPoints - $currentPoints;
$levelProgress = ($currentPoints % 100) / 100 * 100;

$conn->close();

// Get user initial for avatar
$user_initial = strtoupper(substr($user['full_name'], 0, 1));
$profile_image = !empty($user['profile_image']) && file_exists($user['profile_image']) ? $user['profile_image'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Profile - Ecos+</title>
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
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(107, 142, 35, 0.3); color: #8BC34A; }
        .mobile-nav a i { width: 24px; }
        
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } .navbar-container { padding: 0 15px; } }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1000px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Profile Card */
        .profile-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 28px; overflow: hidden; margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.2); }
        .profile-card-inner { padding: 30px; }
        
        /* Profile Header with Avatar Next to Name */
        .profile-header { display: flex; align-items: center; gap: 25px; flex-wrap: wrap; margin-bottom: 25px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 42px; font-weight: 600; color: white; overflow: hidden; flex-shrink: 0; box-shadow: 0 8px 20px rgba(0,0,0,0.15); border: 3px solid white; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info-header { flex: 1; }
        .profile-name { font-size: 28px; font-weight: 700; color: #1a1a2e; margin-bottom: 5px; }
        .profile-username { color: #888; font-size: 14px; margin-bottom: 8px; }
        .profile-badges { display: flex; gap: 15px; flex-wrap: wrap; }
        .level-badge { background: linear-gradient(135deg, #FFD700, #FFA000); color: #333; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .member-badge { background: rgba(0,0,0,0.05); color: #666; padding: 4px 12px; border-radius: 20px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        
        /* Stats Row */
        .stats-row { display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap; }
        .stat-box { background: #f8f9fa; border-radius: 16px; padding: 12px 20px; text-align: center; flex: 1; min-width: 80px; transition: all 0.3s; }
        .stat-box:hover { transform: translateY(-3px); background: #e8f5e9; }
        .stat-number { font-size: 24px; font-weight: 800; color: #6B8E23; }
        .stat-label { font-size: 11px; color: #666; margin-top: 3px; }
        
        /* Level Progress */
        .level-progress { background: rgba(0,0,0,0.03); border-radius: 16px; padding: 15px; margin-top: 15px; }
        .progress-bar-custom { height: 8px; background: rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #6B8E23, #8BC34A); border-radius: 10px; transition: width 0.5s ease; }
        
        /* Bio Section */
        .bio-section { background: rgba(0,0,0,0.02); border-radius: 16px; padding: 15px; margin-top: 15px; border-left: 3px solid #6B8E23; }
        
        /* Tabs */
        .nav-tabs-custom { display: flex; gap: 8px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 8px; margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.2); }
        .tab-btn { flex: 1; padding: 12px; border: none; background: none; border-radius: 14px; font-weight: 600; color: #666; transition: all 0.3s; font-size: 13px; cursor: pointer; }
        .tab-btn:hover { background: rgba(107,142,35,0.1); }
        .tab-btn.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        
        /* Form Card */
        .form-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; padding: 25px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.2); }
        .form-label { font-weight: 600; font-size: 13px; color: #555; margin-bottom: 8px; }
        .form-control, .form-select { border-radius: 12px; border: 1px solid #e0e0e0; padding: 12px 15px; font-size: 14px; background: white; }
        .form-control:focus, .form-select:focus { border-color: #6B8E23; box-shadow: 0 0 0 3px rgba(107,142,35,0.1); outline: none; }
        .btn-save { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; padding: 12px; border-radius: 40px; font-weight: 600; width: 100%; transition: all 0.3s; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(107,142,35,0.3); }
        
        /* Badges Grid */
        .badges-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 15px; }
        .badge-item { background: white; border-radius: 16px; padding: 15px; text-align: center; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .badge-item:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .badge-icon { font-size: 42px; margin-bottom: 8px; }
        .badge-name { font-size: 13px; font-weight: 600; margin-bottom: 3px; color: #333; }
        .badge-date { font-size: 10px; color: #999; }
        
        /* Activity Item */
        .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .activity-item:last-child { border-bottom: none; }
        .activity-type { font-weight: 600; font-size: 14px; color: #333; }
        .activity-points { color: #6B8E23; font-weight: 600; font-size: 13px; }
        .activity-status { font-size: 10px; padding: 3px 10px; border-radius: 20px; font-weight: 500; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Alert */
        .alert { border-radius: 16px; padding: 12px 18px; font-size: 13px; border: none; }
        .alert-danger { background: #fee2e2; color: #dc2626; }
        .alert-success { background: #dcfce7; color: #166534; }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            .profile-card-inner { padding: 20px; }
            .profile-header { gap: 15px; }
            .profile-avatar { width: 75px; height: 75px; font-size: 32px; }
            .profile-name { font-size: 20px; }
            .stat-number { font-size: 18px; }
            .badges-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
            .tab-btn { font-size: 11px; padding: 8px; }
        }
        
        @media (max-width: 480px) {
            .profile-header { flex-direction: column; text-align: center; }
            .profile-badges { justify-content: center; }
            .stats-row { flex-wrap: wrap; }
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
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Tips</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
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
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Tips</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="profile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <!-- Profile Card -->
    <div class="profile-card">
        <div class="profile-card-inner">
            <!-- Profile Header with Avatar Next to Name -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if ($profile_image): ?>
                        <img src="<?php echo $profile_image; ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $user_initial; ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info-header">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                    <div class="profile-badges">
                        <span class="level-badge"><i class="fas fa-star"></i> Level <?php echo $level; ?></span>
                        <span class="member-badge"><i class="fas fa-calendar-alt"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                        <?php if ($user['faculty']): ?>
                            <span class="member-badge"><i class="fas fa-university"></i> <?php echo htmlspecialchars($user['faculty']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-box"><div class="stat-number"><?php echo $stats['total_activities'] ?? 0; ?></div><div class="stat-label">Activities</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $badgesCount; ?></div><div class="stat-label">Badges</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $stats['approved_count'] ?? 0; ?></div><div class="stat-label">Approved</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo number_format($user['points']); ?></div><div class="stat-label">Points</div></div>
            </div>
            
            <!-- Level Progress -->
            <div class="level-progress">
                <div class="d-flex justify-content-between small">
                    <span><i class="fas fa-chart-line"></i> Level <?php echo $level; ?> Progress</span>
                    <span><?php echo $pointsToNext; ?> points to Level <?php echo $level + 1; ?></span>
                </div>
                <div class="progress-bar-custom"><div class="progress-fill" style="width: <?php echo $levelProgress; ?>%"></div></div>
            </div>
            
            <!-- Bio -->
            <?php if ($user['bio']): ?>
                <div class="bio-section">
                    <i class="fas fa-quote-left text-success me-1"></i> 
                    <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nav-tabs-custom">
        <button class="tab-btn active" onclick="showTab('edit')"><i class="fas fa-user-edit"></i> Edit Profile</button>
        <button class="tab-btn" onclick="showTab('password')"><i class="fas fa-key"></i> Change Password</button>
        <button class="tab-btn" onclick="showTab('badges')"><i class="fas fa-medal"></i> My Badges</button>
        <button class="tab-btn" onclick="showTab('history')"><i class="fas fa-history"></i> Activity History</button>
    </div>

    <!-- Tab: Edit Profile -->
    <div id="tab-edit" class="tab-content">
        <div class="form-card">
            <h5 class="mb-3"><i class="fas fa-user-edit text-success"></i> Edit Profile Information</h5>
            <?php if ($error): ?><div class="alert alert-danger mt-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success mt-2"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3"><label class="form-label">Profile Picture</label><input type="file" class="form-control" name="profile_image" accept="image/*"><small class="text-muted">Leave empty to keep current picture</small></div>
                <div class="mb-3"><label class="form-label">Full Name *</label><input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                <div class="mb-3"><label class="form-label">Phone Number</label><input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>"></div>
                <div class="mb-3"><label class="form-label">Faculty</label><select class="form-select" name="faculty"><option value="">Select Faculty</option><option value="Faculty of Computing" <?php echo ($user['faculty'] == 'Faculty of Computing') ? 'selected' : ''; ?>>Faculty of Computing</option><option value="Faculty of Engineering" <?php echo ($user['faculty'] == 'Faculty of Engineering') ? 'selected' : ''; ?>>Faculty of Engineering</option><option value="Faculty of Industrial Management" <?php echo ($user['faculty'] == 'Faculty of Industrial Management') ? 'selected' : ''; ?>>Faculty of Industrial Management</option><option value="Faculty of Science and Technology" <?php echo ($user['faculty'] == 'Faculty of Science and Technology') ? 'selected' : ''; ?>>Faculty of Science and Technology</option><option value="Faculty of Civil Engineering" <?php echo ($user['faculty'] == 'Faculty of Civil Engineering') ? 'selected' : ''; ?>>Faculty of Civil Engineering</option><option value="Faculty of Mechanical Engineering" <?php echo ($user['faculty'] == 'Faculty of Mechanical Engineering') ? 'selected' : ''; ?>>Faculty of Mechanical Engineering</option><option value="Faculty of Electrical Engineering" <?php echo ($user['faculty'] == 'Faculty of Electrical Engineering') ? 'selected' : ''; ?>>Faculty of Electrical Engineering</option></select></div>
                <div class="mb-3"><label class="form-label">Year of Study</label><select class="form-select" name="year_of_study"><option value="0">Select Year</option><option value="1" <?php echo ($user['year_of_study'] == 1) ? 'selected' : ''; ?>>Year 1</option><option value="2" <?php echo ($user['year_of_study'] == 2) ? 'selected' : ''; ?>>Year 2</option><option value="3" <?php echo ($user['year_of_study'] == 3) ? 'selected' : ''; ?>>Year 3</option><option value="4" <?php echo ($user['year_of_study'] == 4) ? 'selected' : ''; ?>>Year 4</option><option value="Staff" <?php echo ($user['year_of_study'] == 'Staff') ? 'selected' : ''; ?>>Staff</option></select></div>
                <div class="mb-3"><label class="form-label">Bio</label><textarea class="form-control" name="bio" rows="3" placeholder="Tell us about your passion for sustainability..."><?php echo htmlspecialchars($user['bio']); ?></textarea></div>
                <button type="submit" name="update_profile" class="btn-save mt-2">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Tab: Change Password -->
    <div id="tab-password" class="tab-content" style="display: none;">
        <div class="form-card">
            <h5 class="mb-3"><i class="fas fa-key text-success"></i> Change Password</h5>
            <?php if ($password_error): ?><div class="alert alert-danger mt-2"><?php echo htmlspecialchars($password_error); ?></div><?php endif; ?>
            <?php if ($password_success): ?><div class="alert alert-success mt-2"><?php echo htmlspecialchars($password_success); ?></div><?php endif; ?>
            <form method="POST">
                <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" id="new_password" required><small class="text-muted">Minimum 6 characters</small></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" id="confirm_password" required><small id="passwordMatch" class="text-muted"></small></div>
                <button type="submit" name="change_password" class="btn-save mt-2">Change Password</button>
            </form>
        </div>
    </div>

    <!-- Tab: My Badges -->
    <div id="tab-badges" class="tab-content" style="display: none;">
        <div class="form-card">
            <h5 class="mb-3"><i class="fas fa-medal text-success"></i> My Badges Collection</h5>
            <?php if ($userBadges->num_rows > 0): ?>
                <div class="badges-grid"><?php while($badge = $userBadges->fetch_assoc()): ?><div class="badge-item"><div class="badge-icon"><?php echo $badge['icon']; ?></div><div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div><div class="badge-date">Earned: <?php echo date('M d, Y', strtotime($badge['earned_at'])); ?></div></div><?php endwhile; ?></div>
            <?php else: ?>
                <div class="text-center py-5"><i class="fas fa-award" style="font-size: 64px; color: #ccc;"></i><p class="mt-3 text-muted">No badges yet. Complete activities to earn badges!</p><a href="activity.php" class="btn btn-success">Start Recycling</a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Activity History -->
    <div id="tab-history" class="tab-content" style="display: none;">
        <div class="form-card">
            <h5 class="mb-3"><i class="fas fa-history text-success"></i> Your Activity History</h5>
            <?php if ($recentActivities->num_rows > 0): ?>
                <?php while($activity = $recentActivities->fetch_assoc()): ?>
                    <div class="activity-item">
                        <div><div class="activity-type"><?php echo htmlspecialchars($activity['activity_type']); ?></div><div class="text-muted small"><?php echo htmlspecialchars(substr($activity['description'], 0, 50)); ?>...</div><small class="text-muted"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></small></div>
                        <div class="text-end"><div class="activity-points">+<?php echo $activity['points_earned']; ?> pts</div><span class="activity-status status-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span></div>
                    </div>
                <?php endwhile; ?>
                <div class="text-center mt-3"><a href="history.php" class="btn btn-outline-success btn-sm rounded-pill">View Full History <i class="fas fa-arrow-right"></i></a></div>
            <?php else: ?>
                <div class="text-center py-5"><i class="fas fa-box-open" style="font-size: 64px; color: #ccc;"></i><p class="mt-3 text-muted">No activities yet. Start recycling!</p><a href="activity.php" class="btn btn-success">Log Your First Activity</a></div>
            <?php endif; ?>
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
    
    // Notification count update
    const notificationCount = document.getElementById('notificationCount');
    function updateBadgeCount(count) { 
        if (notificationCount) { 
            notificationCount.textContent = count; 
            notificationCount.style.display = count > 0 ? 'inline-block' : 'none'; 
        } 
    }
    fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {});

    // Tab switching
    function showTab(tab) {
        document.getElementById('tab-edit').style.display = 'none';
        document.getElementById('tab-password').style.display = 'none';
        document.getElementById('tab-badges').style.display = 'none';
        document.getElementById('tab-history').style.display = 'none';
        document.getElementById(`tab-${tab}`).style.display = 'block';
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }
    
    // Password match validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('passwordMatch');
    
    function validatePassword() {
        if (newPassword && confirmPassword) {
            if (newPassword.value !== confirmPassword.value && confirmPassword.value !== '') {
                matchMessage.innerHTML = '<span class="text-danger">✗ Passwords do not match</span>';
                return false;
            } else if (newPassword.value.length > 0 && confirmPassword.value.length > 0) {
                matchMessage.innerHTML = '<span class="text-success">✓ Passwords match</span>';
                return true;
            } else {
                matchMessage.innerHTML = '';
            }
        }
        return false;
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('keyup', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>