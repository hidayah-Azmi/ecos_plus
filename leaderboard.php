<?php
$page_title = 'Leaderboard';
$current_page = 'leaderboard';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/notifications.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';
$unreadCount = getUnreadCount($user_id);

// Get leaderboard data with faculty
$leaderboardQuery = "SELECT id, username, full_name, points, faculty FROM users WHERE role = 'user' ORDER BY points DESC";
$leaderboard = $conn->query($leaderboardQuery);
$totalUsers = $leaderboard->num_rows;

// Get user rank
$rankQuery = "SELECT COUNT(*) + 1 as rank FROM users WHERE points > (SELECT points FROM users WHERE id = ?) AND role = 'user'";
$stmt = $conn->prepare($rankQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userRank = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get top 3 users for podium with faculty
$podiumQuery = "SELECT full_name, points, faculty FROM users WHERE role = 'user' ORDER BY points DESC LIMIT 3";
$podium = $conn->query($podiumQuery);
$top3 = [];
while($row = $podium->fetch_assoc()) {
    $top3[] = $row;
}

// Calculate statistics
$statsQuery = "SELECT AVG(points) as avg_points, SUM(points) as total_points, COUNT(*) as total_users FROM users WHERE role = 'user'";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get current user points and faculty
$userPoints = $navbar_user['points'] ?? 0;
$userFaculty = $navbar_user['faculty'] ?? '';

// Calculate points needed for next rank
$nextRankQuery = "SELECT points FROM users WHERE role = 'user' AND points > ? ORDER BY points ASC LIMIT 1";
$stmt = $conn->prepare($nextRankQuery);
$stmt->bind_param("i", $userPoints);
$stmt->execute();
$nextRank = $stmt->get_result()->fetch_assoc();
$pointsToNext = $nextRank ? $nextRank['points'] - $userPoints : 0;
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Leaderboard - Ecos+</title>
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
        .notification-bell { background: rgba(255,255,255,0.1); border: none; color: white; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #f44336; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 50%; }
        .user-dropdown { position: relative; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 40px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 15px; color: white; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-arrow { color: rgba(255,255,255,0.6); font-size: 12px; transition: transform 0.3s; }
        .user-dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        .dropdown-menu-custom { position: absolute; top: 55px; right: 0; width: 220px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
        .user-dropdown:hover .dropdown-menu-custom { opacity: 1; visibility: visible; top: 60px; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #6B8E23; }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #0a2e1a; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(107, 142, 35, 0.3); color: #8BC34A; }
        .mobile-nav a i { width: 24px; }
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; padding: 20px; text-align: center; transition: all 0.3s; border: 1px solid rgba(255,255,255,0.2); }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .stat-icon { font-size: 38px; margin-bottom: 12px; display: inline-block; }
        .stat-value { font-size: 32px; font-weight: 800; background: linear-gradient(135deg, #6B8E23, #4CAF50); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-label { font-size: 13px; color: #555; margin-top: 8px; font-weight: 500; }
        
        /* Premium Podium Section */
        .podium-section { 
            background: rgba(255,255,255,0.95); 
            border-radius: 40px; 
            padding: 40px 30px; 
            margin-bottom: 30px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.3);
        }
        .section-badge {
            display: inline-block;
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            color: white;
            padding: 5px 20px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 32px;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .section-subtitle {
            font-size: 14px;
            color: #666;
        }
        .podium-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 40px;
        }
        .podium-card {
            position: relative;
            text-align: center;
            padding: 25px 20px;
            border-radius: 30px;
            min-width: 180px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }
        .podium-card:hover { transform: translateY(-12px); }
        .podium-gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            box-shadow: 0 20px 40px rgba(255, 215, 0, 0.3);
            transform: scale(1.05);
            margin-bottom: 10px;
            padding: 35px 20px;
        }
        .podium-silver {
            background: linear-gradient(135deg, #E0E0E0, #BDBDBD);
            box-shadow: 0 15px 30px rgba(192, 192, 192, 0.3);
        }
        .podium-bronze {
            background: linear-gradient(135deg, #E8B87D, #D49A5A);
            box-shadow: 0 15px 30px rgba(205, 127, 50, 0.3);
        }
        .crown-icon {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 35px;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.2));
        }
        .podium-medal { font-size: 45px; margin-bottom: 10px; }
        .podium-number {
            font-size: 48px;
            font-weight: 800;
            font-family: 'Poppins', sans-serif;
            background: rgba(255,255,255,0.3);
            display: inline-block;
            width: 60px;
            height: 60px;
            line-height: 60px;
            border-radius: 50%;
            margin-bottom: 15px;
            color: #333;
        }
        .podium-gold .podium-number {
            background: rgba(255,255,255,0.4);
            width: 70px;
            height: 70px;
            line-height: 70px;
            font-size: 55px;
        }
        .podium-avatar { font-size: 45px; margin: 15px 0; }
        .champion-avatar { font-size: 60px; animation: bounce 2s ease-in-out infinite; }
        @keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        .podium-name { font-size: 16px; font-weight: 700; color: #333; margin-bottom: 5px; }
        .champion-name { font-size: 18px; color: #2E7D32; }
        .podium-faculty { font-size: 11px; color: rgba(0,0,0,0.6); margin-bottom: 10px; }
        .champion-faculty { color: #1B5E20; }
        .podium-points { margin-top: 10px; }
        .points-value { font-size: 20px; font-weight: 800; color: #2E7D32; }
        .points-label { font-size: 11px; font-weight: 500; color: rgba(0,0,0,0.6); margin-left: 3px; }
        .champion-points .points-value { font-size: 28px; color: #1B5E20; }
        .podium-glow {
            position: absolute;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 80px;
            background: radial-gradient(ellipse at center, rgba(255,255,255,0.4) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        .podium-card:hover .podium-glow { opacity: 1; }
        
        /* User Rank Card */
        .user-rank-card { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 28px; padding: 28px; margin-bottom: 30px; border: 1px solid rgba(107,142,35,0.3); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .user-rank-info { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .user-rank-left { display: flex; align-items: center; gap: 20px; }
        .user-rank-badge { width: 80px; height: 80px; background: rgba(255,215,0,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; border: 2px solid #FFD700; color: #FFD700; }
        .user-rank-text h4 { font-size: 20px; margin-bottom: 5px; color: white; }
        .user-rank-text p { color: rgba(255,255,255,0.7); font-size: 12px; }
        .user-rank-text i { color: #FFD700; margin-right: 5px; }
        .user-rank-number { font-size: 36px; font-weight: 800; color: #FFD700; }
        .progress { height: 10px; border-radius: 10px; background: rgba(255,255,255,0.15); }
        .progress-bar { background: linear-gradient(90deg, #6B8E23, #8BC34A); border-radius: 10px; }
        
        /* Leaderboard Table */
        .leaderboard-card { background: rgba(255,255,255,0.95); border-radius: 28px; overflow: hidden; border: 1px solid rgba(255,255,255,0.2); }
        .leaderboard-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 30px; text-align: center; color: white; }
        .leaderboard-header i { font-size: 45px; margin-bottom: 10px; color: #FFD700; }
        .leaderboard-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid rgba(0,0,0,0.05); transition: all 0.3s; }
        .leaderboard-item:hover { background: linear-gradient(90deg, #f8f9fa, #fff); transform: translateX(8px); }
        .leaderboard-item.current-user { background: linear-gradient(90deg, #e8f5e9, #c8e6c9); border-left: 4px solid #6B8E23; }
        .rank-badge { width: 45px; height: 45px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; margin-right: 15px; font-size: 16px; }
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #333; box-shadow: 0 2px 8px rgba(255,215,0,0.3); }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #333; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #e0a878); color: #333; }
        .rank-other { background: #f0f0f0; color: #666; }
        .user-avatar-small { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #4CAF50); display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: 700; margin-right: 15px; font-size: 16px; }
        .faculty-name { font-size: 10px; color: #888; margin-top: 3px; display: flex; align-items: center; gap: 5px; }
        .points-glow { font-weight: 800; background: linear-gradient(135deg, #6B8E23, #4CAF50); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 20px; }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .podium-card { min-width: 140px; padding: 18px 12px; }
            .podium-gold { padding: 25px 12px; }
            .podium-number { font-size: 32px; width: 45px; height: 45px; line-height: 45px; }
            .podium-gold .podium-number { width: 55px; height: 55px; line-height: 55px; font-size: 40px; }
            .podium-avatar { font-size: 35px; }
            .champion-avatar { font-size: 45px; }
            .points-value { font-size: 16px; }
            .champion-points .points-value { font-size: 22px; }
            .leaderboard-item { padding: 12px 15px; }
            .rank-badge { width: 35px; height: 35px; font-size: 12px; }
            .user-avatar-small { width: 32px; height: 32px; font-size: 12px; }
        }
        @media (max-width: 480px) { 
            .stats-grid { grid-template-columns: 1fr; } 
            .user-rank-left { flex-direction: column; text-align: center; } 
            .user-rank-info { justify-content: center; text-align: center; }
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
                <li><a href="leaderboard.php" class="nav-link-custom active"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
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
        <li><a href="leaderboard.php" class="active"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users" style="color: #6B8E23;"></i></div>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Active Recyclers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-star" style="color: #FFD700;"></i></div>
            <div class="stat-value"><?php echo number_format($stats['avg_points'] ?? 0); ?></div>
            <div class="stat-label">Average Points</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-leaf" style="color: #6B8E23;"></i></div>
            <div class="stat-value"><?php echo number_format($stats['total_points'] ?? 0); ?></div>
            <div class="stat-label">Total Points Awarded</div>
        </div>
    </div>

    <!-- Premium Podium Section -->
    <?php if (count($top3) >= 3): ?>
    <div class="podium-section">
        <div class="text-center">
            <div class="section-badge">🏆 CHAMPIONS</div>
            <h3 class="section-title">Top Recyclers</h3>
            <p class="section-subtitle">Our sustainability champions</p>
        </div>
        <div class="podium-container">
            <!-- 2nd Place -->
            <div class="podium-card podium-silver">
                <div class="podium-medal">🥈</div>
                <div class="podium-number">2</div>
                <div class="podium-avatar">👤</div>
                <div class="podium-name"><?php echo htmlspecialchars(substr($top3[1]['full_name'], 0, 20)); ?></div>
                <div class="podium-faculty"><i class="fas fa-university"></i> <?php echo htmlspecialchars(substr($top3[1]['faculty'] ?? 'Member', 0, 25)); ?></div>
                <div class="podium-points">
                    <span class="points-value"><?php echo number_format($top3[1]['points']); ?></span>
                    <span class="points-label">pts</span>
                </div>
                <div class="podium-glow"></div>
            </div>
            
            <!-- 1st Place -->
            <div class="podium-card podium-gold">
                <div class="crown-icon">👑</div>
                <div class="podium-medal">🥇</div>
                <div class="podium-number">1</div>
                <div class="podium-avatar champion-avatar">⭐</div>
                <div class="podium-name champion-name"><?php echo htmlspecialchars(substr($top3[0]['full_name'], 0, 20)); ?></div>
                <div class="podium-faculty champion-faculty"><i class="fas fa-university"></i> <?php echo htmlspecialchars(substr($top3[0]['faculty'] ?? 'Member', 0, 25)); ?></div>
                <div class="podium-points champion-points">
                    <span class="points-value"><?php echo number_format($top3[0]['points']); ?></span>
                    <span class="points-label">pts</span>
                </div>
                <div class="podium-glow"></div>
            </div>
            
            <!-- 3rd Place -->
            <div class="podium-card podium-bronze">
                <div class="podium-medal">🥉</div>
                <div class="podium-number">3</div>
                <div class="podium-avatar">👤</div>
                <div class="podium-name"><?php echo htmlspecialchars(substr($top3[2]['full_name'], 0, 20)); ?></div>
                <div class="podium-faculty"><i class="fas fa-university"></i> <?php echo htmlspecialchars(substr($top3[2]['faculty'] ?? 'Member', 0, 25)); ?></div>
                <div class="podium-points">
                    <span class="points-value"><?php echo number_format($top3[2]['points']); ?></span>
                    <span class="points-label">pts</span>
                </div>
                <div class="podium-glow"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- User Rank Card -->
    <div class="user-rank-card">
        <div class="user-rank-info">
            <div class="user-rank-left">
                <div class="user-rank-badge">#<?php echo $userRank['rank'] ?? 'N/A'; ?></div>
                <div class="user-rank-text">
                    <h4><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($navbar_user['full_name'] ?? 'You'); ?></h4>
                    <p><i class="fas fa-university"></i> <?php echo htmlspecialchars($userFaculty ?: 'Member'); ?></p>
                </div>
            </div>
            <div class="text-end">
                <div class="user-rank-number"><?php echo number_format($userPoints); ?></div>
                <small><i class="fas fa-star"></i> Eco Points</small>
            </div>
        </div>
        <?php if ($pointsToNext > 0): ?>
        <div class="progress-to-next mt-3">
            <div class="d-flex justify-content-between small mb-2">
                <span><i class="fas fa-chart-line"></i> Progress to next rank</span>
                <span class="text-warning">🎯 <?php echo number_format($pointsToNext); ?> points needed</span>
            </div>
            <div class="progress"><div class="progress-bar" style="width: <?php echo min(100, ($userPoints / max(1, $userPoints + $pointsToNext)) * 100); ?>%"></div></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Full Leaderboard -->
    <div class="leaderboard-card">
        <div class="leaderboard-header">
            <i class="fas fa-trophy"></i>
            <h3 class="mt-2">🏆 Full Leaderboard 🏆</h3>
            <p class="mb-0 opacity-75">Top recyclers in the Ecos+ community</p>
        </div>
        <div class="leaderboard-list">
            <?php 
            $leaderboard->data_seek(0); 
            $rank = 1; 
            while($user = $leaderboard->fetch_assoc()): 
                $isCurrentUser = ($user['id'] == $_SESSION['user_id']); 
                $rankClass = $rank <= 3 ? "rank-$rank" : "rank-other"; 
                $userInitial = strtoupper(substr($user['full_name'], 0, 1));
                $userFaculty = $user['faculty'] ?? '';
            ?>
            <div class="leaderboard-item <?php echo $isCurrentUser ? 'current-user' : ''; ?>">
                <div class="d-flex align-items-center">
                    <div class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                    <div class="user-avatar-small"><?php echo $userInitial; ?></div>
                    <div>
                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                        <div class="text-muted small">@<?php echo htmlspecialchars($user['username']); ?></div>
                        <?php if (!empty($userFaculty)): ?>
                            <div class="faculty-name"><i class="fas fa-university"></i> <?php echo htmlspecialchars($userFaculty); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <div class="points-glow"><?php echo number_format($user['points']); ?></div>
                    <small class="text-muted">points</small>
                    <?php if ($isCurrentUser): ?>
                        <div class="badge bg-success mt-1">You</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php 
                $rank++;
            endwhile; 
            ?>
        </div>
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
    
    const notificationCount = document.getElementById('notificationCount');
    function updateBadgeCount(count) { 
        if (notificationCount) { 
            notificationCount.textContent = count; 
            notificationCount.style.display = count > 0 ? 'inline-block' : 'none'; 
        } 
    }
    fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>