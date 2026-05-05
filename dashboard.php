<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Get unread notification count
$unreadCount = getUnreadCount($user_id);
$notifications = getUserNotifications($user_id, 10);

// Get user points
$pointsQuery = "SELECT points FROM users WHERE id = ?";
$stmt = $conn->prepare($pointsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pointsResult = $stmt->get_result();
$userPoints = $pointsResult->fetch_assoc();
$stmt->close();

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total_activities,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(points_earned) as total_points_earned
               FROM activities WHERE user_id = ?";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$statsResult = $stmt->get_result();
$userStats = $statsResult->fetch_assoc();
$stmt->close();

// Get recent activities
$activitiesQuery = "SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($activitiesQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentActivities = $stmt->get_result();
$stmt->close();

// Get badges - using actual badge icons
$badgesQuery = "SELECT b.* FROM badges b 
                INNER JOIN user_badges ub ON b.id = ub.badge_id 
                WHERE ub.user_id = ? LIMIT 4";
$stmt = $conn->prepare($badgesQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userBadges = $stmt->get_result();
$stmt->close();

// Get leaderboard
$leaderboardQuery = "SELECT full_name, points FROM users WHERE role = 'user' ORDER BY points DESC LIMIT 5";
$leaderboard = $conn->query($leaderboardQuery);

// Get user rank
$rankQuery = "SELECT COUNT(*) + 1 as rank FROM users WHERE points > (SELECT points FROM users WHERE id = ?) AND role = 'user'";
$stmt = $conn->prepare($rankQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rankResult = $stmt->get_result();
$userRank = $rankResult->fetch_assoc();
$stmt->close();

// Calculate CO2 saved
$co2Saved = ($userStats['approved_count'] ?? 0) * 2.5;
$treesSaved = max(1, round($co2Saved / 21));
$plasticBottlesSaved = ($userStats['approved_count'] ?? 0) * 50;

// Calculate next level
$currentPoints = $userPoints['points'] ?? 0;
$nextLevelPoints = ceil($currentPoints / 100) * 100;
$pointsToNext = $nextLevelPoints - $currentPoints;
$levelProgress = ($currentPoints % 100) / 100 * 100;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0a2e1a 0%, #1a4a2a 50%, #0d3b1f 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            position: relative;
        }
        
        /* Nature Background Pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="rgba(76,175,80,0.03)" d="M20,30 Q25,25 30,30 Q35,25 40,30"/><circle cx="50" cy="20" r="2" fill="rgba(76,175,80,0.05)"/><circle cx="80" cy="50" r="3" fill="rgba(76,175,80,0.05)"/><circle cx="15" cy="70" r="2" fill="rgba(76,175,80,0.05)"/></svg>');
            background-repeat: repeat;
            pointer-events: none;
            z-index: 0;
        }
        
        /* Animated Leaves */
        @keyframes leafFloat1 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(-20px,-30px) rotate(10deg); } }
        @keyframes leafFloat2 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(20px,-20px) rotate(-10deg); } }
        @keyframes leafFloat3 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(-15px,25px) rotate(15deg); } }
        
        .leaf-bg { position: fixed; font-size: 45px; opacity: 0.06; pointer-events: none; z-index: 0; }
        .leaf-bg:nth-child(1) { top: 10%; left: 5%; animation: leafFloat1 12s ease-in-out infinite; }
        .leaf-bg:nth-child(2) { top: 70%; right: 8%; animation: leafFloat2 15s ease-in-out infinite; font-size: 60px; }
        .leaf-bg:nth-child(3) { top: 40%; left: 85%; animation: leafFloat3 18s ease-in-out infinite; }
        .leaf-bg:nth-child(4) { bottom: 15%; left: 10%; animation: leafFloat1 14s ease-in-out infinite; }
        .leaf-bg:nth-child(5) { top: 85%; right: 20%; animation: leafFloat2 11s ease-in-out infinite; }
        
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
        
        /* Notification Styles */
        .notification-wrapper { position: relative; margin-right: 10px; }
        .notification-bell { background: rgba(255,255,255,0.1); border: none; color: white; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; transition: all 0.3s; font-size: 18px; }
        .notification-bell:hover { background: rgba(107, 142, 35, 0.5); transform: scale(1.05); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #f44336; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 50%; min-width: 18px; text-align: center; }
        .notification-popup { position: fixed; bottom: 100px; right: 25px; width: 360px; max-width: calc(100vw - 40px); background: white; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); z-index: 1001; display: none; overflow: hidden; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .notification-popup.show { display: block; }
        .notification-popup-header { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; }
        .notification-popup-list { max-height: 400px; overflow-y: auto; }
        .notification-popup-item { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 12px; cursor: pointer; transition: background 0.2s; }
        .notification-popup-item.unread { background: #e8f5e9; }
        .notification-popup-item:hover { background: #f5f5f5; }
        .notification-popup-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notification-popup-icon.approved { background: #d4edda; color: #155724; }
        .notification-popup-icon.rejected { background: #f8d7da; color: #721c24; }
        .notification-popup-icon.follower { background: #d1ecf1; color: #0c5460; }
        .notification-popup-content { flex: 1; }
        .notification-popup-title { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
        .notification-popup-message { font-size: 11px; color: #666; margin-bottom: 2px; }
        .notification-popup-time { font-size: 10px; color: #999; }
        .empty-notification { text-align: center; padding: 30px; color: #999; }
        
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
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; transition: all 0.2s; border-bottom: 1px solid #f0f0f0; }
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
        
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } .user-trigger { padding: 6px 12px; } .navbar-container { padding: 0 15px; } }
        @media (max-width: 576px) { .logo-text { display: none; } .notification-popup { right: 10px; left: 10px; width: auto; bottom: 80px; } }

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Hero Welcome Card */
        .hero-card { 
            background: linear-gradient(135deg, #1a5a2a 0%, #2d6a3d 50%, #1a5a2a 100%);
            border-radius: 30px;
            padding: 35px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .hero-card::before { content: '🌿'; position: absolute; top: -20px; right: -20px; font-size: 150px; opacity: 0.08; }
        .hero-card::after { content: '🍃'; position: absolute; bottom: -30px; left: -20px; font-size: 120px; opacity: 0.08; }
        .eco-badge { background: rgba(255,255,255,0.2); backdrop-filter: blur(5px); border-radius: 50px; padding: 5px 15px; display: inline-flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 15px; }
        .level-progress { background: rgba(0,0,0,0.3); border-radius: 20px; padding: 8px 15px; margin-top: 15px; }
        .level-bar { height: 6px; background: rgba(255,255,255,0.3); border-radius: 10px; overflow: hidden; margin-top: 8px; }
        .level-fill { width: <?php echo $levelProgress; ?>%; height: 100%; background: linear-gradient(90deg, #FFD700, #FFA500); border-radius: 10px; }
        
        /* Stats Grid - Eco Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .eco-stat-card { background: rgba(255,255,255,0.95); border-radius: 24px; padding: 20px; text-align: center; transition: all 0.3s; cursor: pointer; position: relative; overflow: hidden; border: 1px solid rgba(107,142,35,0.2); }
        .eco-stat-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
        .eco-stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(135deg, #6B8E23, #4CAF50); }
        .stat-icon { font-size: 38px; margin-bottom: 12px; display: inline-block; }
        .stat-value { font-size: 32px; font-weight: 800; color: #2d6a3d; line-height: 1.2; }
        .stat-label { font-size: 13px; color: #666; font-weight: 500; margin-top: 5px; }
        .stat-sub { font-size: 10px; color: #999; margin-top: 3px; display: block; }
        
        /* Impact Row - Sustainability Focus */
        .impact-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .impact-sustainability-card { 
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 24px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .impact-sustainability-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
        .impact-icon { font-size: 48px; margin-bottom: 15px; display: inline-block; }
        .impact-value { font-size: 36px; font-weight: 800; color: #1B5E20; }
        .impact-label { font-size: 14px; font-weight: 600; color: #2E7D32; margin-top: 8px; }
        .impact-description { font-size: 11px; color: #4CAF50; margin-top: 5px; }
        
        /* Tree Animation for CO2 */
        .tree-animation { display: inline-block; animation: sway 3s ease-in-out infinite; }
        @keyframes sway { 0%,100% { transform: rotate(0deg); } 50% { transform: rotate(5deg); } }
        
        /* Responsive */
        @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; } .impact-row { grid-template-columns: repeat(3, 1fr); gap: 15px; } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .impact-row { grid-template-columns: 1fr; } .container-custom { padding: 15px; } .hero-card { padding: 25px; } .stat-value { font-size: 26px; } .impact-value { font-size: 28px; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } .stat-value { font-size: 28px; } .impact-value { font-size: 24px; } }
        
        /* Other Components */
        .card-custom { background: rgba(255,255,255,0.95); border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 25px; overflow: hidden; }
        .card-header-custom { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #6B8E23; padding: 18px 22px; font-weight: 700; font-size: 16px; }
        .activity-item { border-left: 4px solid #6B8E23; padding: 15px; background: #f8f9fa; margin-bottom: 12px; border-radius: 16px; transition: all 0.3s; }
        .activity-item:hover { background: #e8f5e9; transform: translateX(8px); }
        .badge-item { text-align: center; padding: 12px; background: #f8f9fa; border-radius: 16px; transition: all 0.3s; }
        .badge-item:hover { transform: scale(1.05); background: linear-gradient(135deg, #e8f5e9, #c8e6c9); }
        .quick-action { text-align: center; padding: 15px; background: white; border-radius: 16px; text-decoration: none; display: block; color: #333; border: 2px solid #e8f5e9; transition: all 0.3s; }
        .quick-action:hover { transform: translateY(-5px); border-color: #6B8E23; color: #6B8E23; box-shadow: 0 10px 25px rgba(107,142,35,0.2); }
        .quick-action i { font-size: 32px; color: #6B8E23; margin-bottom: 10px; display: block; }
        .leaderboard-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #f0f0f0; transition: all 0.3s; border-radius: 12px; }
        .leaderboard-item:hover { background: #f8f9fa; padding-left: 18px; }
        .rank-1 { color: #FFD700; font-weight: bold; }
        .rank-2 { color: #C0C0C0; font-weight: bold; }
        .rank-3 { color: #CD7F32; font-weight: bold; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        /* Floating Camera Button */
        .camera-floating-btn { position: fixed; bottom: 25px; right: 25px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.3); cursor: pointer; z-index: 1000; transition: all 0.3s; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .camera-floating-btn:hover { transform: scale(1.1) rotate(10deg); background: linear-gradient(135deg, #8BC34A, #6B8E23); }
        
        #video { width: 100%; border-radius: 12px; background: #000; max-height: 400px; object-fit: cover; }
        #canvas { display: none; }
    </style>
</head>
<body>

<!-- Animated Leaves Background -->
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-seedling"></i></div>
<div class="leaf-bg"><i class="fas fa-tree"></i></div>
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-recycle"></i></div>

<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon"><img src="assets/logo/12.png" alt="Logo"></div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Tips</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="notification-wrapper">
                    <button class="notification-bell" id="notificationBell"><i class="fas fa-bell"></i><span class="notification-badge" id="notificationCount"><?php echo $unreadCount; ?></span></button>
                </div>
                <div class="user-dropdown">
                    <div class="user-trigger"><div class="user-avatar"><?php echo $navbar_initial; ?></div><div class="user-info"><span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></span><span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span></div><i class="fas fa-chevron-down dropdown-arrow"></i></div>
                    <div class="dropdown-menu-custom"><a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a><a href="rewards.php" class="dropdown-item-custom"><i class="fas fa-gift"></i> My Rewards</a><a href="history.php" class="dropdown-item-custom"><i class="fas fa-history"></i> Activity History</a><div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div><a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
                </div>
            </div>
            <button class="mobile-toggle" id="mobileToggleBtn"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="activity.php"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Tips</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- Notification Popup -->
<div class="notification-popup" id="notificationPopup">
    <div class="notification-popup-header"><span><i class="fas fa-bell"></i> Notifications</span><button id="markAllReadBtn" style="background: none; border: none; color: white; font-size: 12px;">Mark all read</button></div>
    <div class="notification-popup-list" id="notificationList"><div class="empty-notification">Loading...</div></div>
</div>

<div class="container-custom">
    <!-- Hero Welcome Card - Sustainable Design -->
    <div class="hero-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="eco-badge"><i class="fas fa-seedling"></i> <span>Eco-Warrior</span> <i class="fas fa-leaf ms-2"></i></div>
                <h2 class="text-white" style="font-weight: 700;">Welcome back, <span style="color: #FFD700;"><?php echo htmlspecialchars($user['full_name']); ?></span>!</h2>
                <p class="text-white-50 mt-2">You've saved <strong><?php echo round($co2Saved); ?> kg</strong> of CO₂ - equivalent to planting <strong><?php echo $treesSaved; ?> trees</strong>! 🌳</p>
                <div class="level-progress">
                    <div class="d-flex justify-content-between text-white small"><span><i class="fas fa-star"></i> Level <?php echo floor($currentPoints / 100) + 1; ?></span><span><?php echo $pointsToNext; ?> pts to next level</span></div>
                    <div class="level-bar"><div class="level-fill"></div></div>
                </div>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="fas fa-globe-asia" style="font-size: 80px; opacity: 0.5; color: white;"></i>
            </div>
        </div>
    </div>

    <!-- Stats Grid - 4 Cards -->
    <div class="stats-grid">
        <div class="eco-stat-card" onclick="location.href='rewards.php'"><div class="stat-icon"><i class="fas fa-star" style="color: #FFD700;"></i></div><div class="stat-value"><?php echo number_format($currentPoints); ?></div><div class="stat-label">Total Points</div><div class="stat-sub">Eco points earned</div></div>
        <div class="eco-stat-card" onclick="location.href='activity.php'"><div class="stat-icon"><i class="fas fa-recycle" style="color: #4CAF50;"></i></div><div class="stat-value"><?php echo $userStats['total_activities'] ?? 0; ?></div><div class="stat-label">Activities</div><div class="stat-sub"><span style="color:#28a745;">✓ <?php echo $userStats['approved_count'] ?? 0; ?> approved</span></div></div>
        <div class="eco-stat-card" onclick="location.href='rewards.php#badges'"><div class="stat-icon"><i class="fas fa-award" style="color: #FF9800;"></i></div><div class="stat-value"><?php echo $userBadges->num_rows; ?></div><div class="stat-label">Badges Earned</div><div class="stat-sub">Keep collecting!</div></div>
        <div class="eco-stat-card" onclick="location.href='leaderboard.php'"><div class="stat-icon"><i class="fas fa-chart-line" style="color: #2196F3;"></i></div><div class="stat-value">#<?php echo $userRank['rank'] ?? 1; ?></div><div class="stat-label">Global Rank</div><div class="stat-sub">Among all recyclers</div></div>
    </div>

    <!-- Impact Row - Sustainability Focus Cards -->
    <div class="impact-row">
        <div class="impact-sustainability-card" onclick="location.href='activity.php'">
            <div class="impact-icon"><i class="fas fa-cloud-upload-alt tree-animation"></i></div>
            <div class="impact-value"><?php echo round($co2Saved); ?> <span style="font-size: 16px;">kg</span></div>
            <div class="impact-label">CO₂ SAVED</div>
            <div class="impact-description"><i class="fas fa-car"></i> = <?php echo round($co2Saved / 2.3); ?> km driving</div>
        </div>
        <div class="impact-sustainability-card" onclick="location.href='activity.php'">
            <div class="impact-icon"><i class="fas fa-tree tree-animation" style="animation-duration: 4s;"></i></div>
            <div class="impact-value"><?php echo $treesSaved; ?></div>
            <div class="impact-label">TREES SAVED</div>
            <div class="impact-description"><i class="fas fa-leaf"></i> Oxygen for <?php echo $treesSaved * 4; ?> people/year</div>
        </div>
        <div class="impact-sustainability-card" onclick="location.href='activity.php'">
            <div class="impact-icon" style="font-size: 48px;">🥤</div>
            <div class="impact-value"><?php echo number_format($plasticBottlesSaved); ?></div>
            <div class="impact-label">PLASTIC BOTTLES</div>
            <div class="impact-description"><i class="fas fa-trash-alt"></i> Removed from ocean</div>
        </div>
    </div>

    <!-- Recent Activities & Sidebar -->
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-custom">
                <div class="card-header-custom"><i class="fas fa-clock me-2" style="color: #6B8E23;"></i> Recent Eco-Activities</div>
                <div class="p-3">
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <?php while($activity = $recentActivities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div class="flex-grow-1"><strong><i class="fas fa-recycle me-1" style="color: #6B8E23;"></i> <?php echo htmlspecialchars($activity['activity_type']); ?></strong><p class="mb-0 text-muted small mt-1"><?php echo htmlspecialchars(substr($activity['description'], 0, 60)); ?>...</p></div>
                                    <div class="text-end ms-2"><span class="status-badge status-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span><br><small class="text-muted"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($activity['created_at'])); ?></small><?php if ($activity['points_earned'] > 0): ?><br><small class="text-success"><i class="fas fa-star"></i> +<?php echo $activity['points_earned']; ?> pts</small><?php endif; ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5"><i class="fas fa-box-open" style="font-size: 64px; color: #ccc;"></i><p class="mt-3 text-muted">No activities yet. Start recycling!</p><a href="activity.php" class="btn btn-success rounded-pill px-4">Log Your First Activity</a></div>
                    <?php endif; ?>
                    <a href="history.php" class="btn btn-outline-success rounded-pill btn-sm mt-2 w-100">View All Activities <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card-custom"><div class="card-header-custom"><i class="fas fa-bolt me-2" style="color: #FF9800;"></i> Quick Actions</div><div class="p-3"><div class="row g-2"><div class="col-6"><a href="activity.php" class="quick-action"><i class="fas fa-recycle"></i><div>Log Activity</div><small>+ points</small></a></div><div class="col-6"><a href="map.php" class="quick-action"><i class="fas fa-map-marker-alt"></i><div>Find Centers</div><small>nearby</small></a></div><div class="col-6"><a href="community.php" class="quick-action"><i class="fas fa-users"></i><div>Community</div><small>share tips</small></a></div><div class="col-6"><a href="rewards.php" class="quick-action"><i class="fas fa-gift"></i><div>Redeem</div><small>rewards</small></a></div></div></div></div>

            <!-- Badges -->
            <div class="card-custom"><div class="card-header-custom"><i class="fas fa-medal me-2" style="color: #FF9800;"></i> Your Green Badges</div><div class="p-3"><div class="row g-2"><?php if ($userBadges->num_rows > 0): ?><?php while($badge = $userBadges->fetch_assoc()): ?><div class="col-6"><div class="badge-item"><div style="font-size: 36px;"><?php echo $badge['icon']; ?></div><small><?php echo htmlspecialchars($badge['name']); ?></small></div></div><?php endwhile; ?><?php else: ?><div class="col-12 text-center py-4"><i class="fas fa-award" style="font-size: 60px; color: #ccc;"></i><p class="text-muted mt-2">Complete activities to earn badges!</p></div><?php endif; ?></div><a href="rewards.php#badges" class="btn btn-outline-success rounded-pill btn-sm w-100 mt-2">View All Badges <i class="fas fa-arrow-right ms-1"></i></a></div></div>

            <!-- Leaderboard -->
            <div class="card-custom"><div class="card-header-custom"><i class="fas fa-chart-line me-2" style="color: #2196F3;"></i> Top Recyclers</div><div class="p-3"><?php if ($leaderboard && $leaderboard->num_rows > 0): $rank = 1; while($userData = $leaderboard->fetch_assoc()): ?><div class="leaderboard-item"><div><?php if ($rank == 1): ?><span class="rank-1"><i class="fas fa-crown"></i> #<?php echo $rank; ?></span><?php elseif ($rank == 2): ?><span class="rank-2">#<?php echo $rank; ?></span><?php elseif ($rank == 3): ?><span class="rank-3">#<?php echo $rank; ?></span><?php else: ?>#<?php echo $rank; ?><?php endif; ?> <strong><?php echo htmlspecialchars(substr($userData['full_name'], 0, 15)); ?></strong></div><div class="text-success"><i class="fas fa-star"></i> <?php echo number_format($userData['points']); ?></div></div><?php $rank++; endwhile; else: ?><div class="text-center py-4"><i class="fas fa-users" style="font-size: 48px; color: #ccc;"></i><p class="text-muted mt-2">No users yet. Be the first!</p></div><?php endif; ?><a href="leaderboard.php" class="btn btn-outline-success rounded-pill btn-sm w-100 mt-2">View Full Leaderboard <i class="fas fa-arrow-right ms-1"></i></a></div></div>
        </div>
    </div>
</div>

<!-- Floating Camera Button -->
<button class="camera-floating-btn" data-bs-toggle="modal" data-bs-target="#cameraModal"><i class="fas fa-camera"></i></button>

<!-- Camera Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content" style="border-radius: 24px;"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-camera"></i> Quick Recycle</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center p-3"><p class="text-muted">Take a photo of your recyclable item</p><video id="video" autoplay playsinline style="width: 100%; border-radius: 16px; background: #000;"></video><canvas id="canvas" style="display: none;"></canvas><div id="loading" class="mt-3" style="display:none;"><div class="spinner-border text-success"></div><p class="mt-2">Processing...</p></div><div id="result" class="mt-3"></div></div><div class="modal-footer"><button class="btn btn-secondary rounded-pill" id="switchCamBtn"><i class="fas fa-sync-alt"></i> Switch Camera</button><button class="btn btn-success rounded-pill" id="captureBtn"><i class="fas fa-camera"></i> Capture & Continue</button><button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button></div></div></div></div>

<script>
    // Mobile menu toggle
    var mobileToggleBtn = document.getElementById('mobileToggleBtn');
    var mobileMenu = document.getElementById('mobileMenu');
    if (mobileToggleBtn) { mobileToggleBtn.addEventListener('click', function() { mobileMenu.classList.toggle('show'); }); }
    document.addEventListener('click', function(event) { if (mobileMenu && mobileToggleBtn && !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) { mobileMenu.classList.remove('show'); } });
    
    // Notification System
    const notificationBell = document.getElementById('notificationBell');
    const notificationPopup = document.getElementById('notificationPopup');
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    let isPopupOpen = false;
    
    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            if (isPopupOpen) { notificationPopup.classList.remove('show'); isPopupOpen = false; }
            else { loadNotifications(); notificationPopup.classList.add('show'); isPopupOpen = true; }
        });
    }
    document.addEventListener('click', function(e) { if (notificationPopup && notificationBell && !notificationPopup.contains(e.target) && !notificationBell.contains(e.target)) { notificationPopup.classList.remove('show'); isPopupOpen = false; } });
    
    function loadNotifications() {
        fetch('ajax/get_notifications.php').then(r => r.json()).then(data => { if (data.success) { displayNotifications(data.notifications); updateBadgeCount(data.unread_count); } else { notificationList.innerHTML = '<div class="empty-notification">Failed to load</div>'; } }).catch(() => { notificationList.innerHTML = '<div class="empty-notification">Error loading</div>'; });
    }
    
    function displayNotifications(notifications) {
        if (!notifications || notifications.length === 0) { notificationList.innerHTML = '<div class="empty-notification"><i class="fas fa-bell-slash fa-2x mb-2"></i><p>No notifications yet</p></div>'; return; }
        let html = '';
        notifications.forEach(notif => {
            let iconClass = '', iconHtml = '';
            if (notif.type === 'activity_approved') { iconClass = 'approved'; iconHtml = '<i class="fas fa-check-circle"></i>'; }
            else if (notif.type === 'activity_rejected') { iconClass = 'rejected'; iconHtml = '<i class="fas fa-times-circle"></i>'; }
            else if (notif.type === 'new_follower') { iconClass = 'follower'; iconHtml = '<i class="fas fa-user-plus"></i>'; }
            else if (notif.type === 'post_like') { iconClass = 'approved'; iconHtml = '<i class="fas fa-heart"></i>'; }
            else if (notif.type === 'new_comment') { iconClass = 'follower'; iconHtml = '<i class="fas fa-comment"></i>'; }
            else { iconClass = 'approved'; iconHtml = '<i class="fas fa-info-circle"></i>'; }
            html += `<div class="notification-popup-item ${notif.is_read == 0 ? 'unread' : ''}" onclick="markNotificationRead(${notif.id})"><div class="notification-popup-icon ${iconClass}">${iconHtml}</div><div class="notification-popup-content"><div class="notification-popup-title">${escapeHtml(notif.title)}</div><div class="notification-popup-message">${escapeHtml(notif.message)}</div><div class="notification-popup-time">${notif.time_ago}</div></div></div>`;
        });
        notificationList.innerHTML = html;
    }
    
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    function updateBadgeCount(count) { if (notificationCount) { notificationCount.textContent = count; notificationCount.style.display = count > 0 ? 'inline-block' : 'none'; } }
    function markNotificationRead(id) { fetch('ajax/mark_notification_read.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + id }).then(() => loadNotifications()); }
    if (markAllReadBtn) { markAllReadBtn.onclick = () => { fetch('ajax/mark_all_read.php', { method: 'POST' }).then(() => loadNotifications()); }; }
    setInterval(() => { fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {}); }, 30000);
    
    // Camera functionality
    let currentFacing = 'environment', stream = null, video = document.getElementById('video'), canvas = document.getElementById('canvas'), ctx = canvas.getContext('2d');
    async function startCamera() { if (stream) stream.getTracks().forEach(track => track.stop()); try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { exact: currentFacing } } }); video.srcObject = stream; } catch(e) { try { stream = await navigator.mediaDevices.getUserMedia({ video: true }); video.srcObject = stream; } catch(err) { alert('Cannot access camera.'); } } }
    const cameraModal = document.getElementById('cameraModal');
    cameraModal.addEventListener('shown.bs.modal', () => startCamera());
    cameraModal.addEventListener('hidden.bs.modal', () => { if (stream) stream.getTracks().forEach(track => track.stop()); document.getElementById('result').innerHTML = ''; document.getElementById('loading').style.display = 'none'; });
    document.getElementById('switchCamBtn').onclick = () => { currentFacing = currentFacing === 'environment' ? 'user' : 'environment'; startCamera(); };
    document.getElementById('captureBtn').onclick = function() { if (!video.videoWidth || !video.videoHeight) { alert('Wait for camera'); return; } canvas.width = video.videoWidth; canvas.height = video.videoHeight; ctx.drawImage(video, 0, 0); document.getElementById('loading').style.display = 'block'; canvas.toBlob(function(blob) { let reader = new FileReader(); reader.onloadend = function() { localStorage.setItem('captured_image', reader.result); localStorage.setItem('captured_image_time', Date.now().toString()); document.getElementById('loading').style.display = 'none'; document.getElementById('result').innerHTML = `<div class="alert alert-success rounded-pill"><i class="fas fa-check-circle"></i> Photo captured!<br><small>Redirecting...</small></div>`; setTimeout(() => window.location.href = 'activity.php?from_camera=1', 1500); }; reader.readAsDataURL(blob); }, 'image/jpeg', 0.9); };
    
    fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>