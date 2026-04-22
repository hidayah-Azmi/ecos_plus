<?php
$page_title = 'AI Insights';
$current_page = 'ai-insights';
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Get user's recent activities
$activitiesQuery = "SELECT id, activity_type, description, created_at, points_earned, image_path 
                    FROM activities 
                    WHERE user_id = ? AND status = 'approved' 
                    ORDER BY created_at DESC 
                    LIMIT 6";
$stmt = $conn->prepare($activitiesQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();
$stmt->close();

// Get user statistics
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(points_earned) as total_points,
                SUM(CASE WHEN activity_type = 'Plastic' THEN 1 ELSE 0 END) as plastic_count,
                SUM(CASE WHEN activity_type = 'Paper' THEN 1 ELSE 0 END) as paper_count,
                SUM(CASE WHEN activity_type = 'Glass' THEN 1 ELSE 0 END) as glass_count,
                SUM(CASE WHEN activity_type = 'E-Waste' THEN 1 ELSE 0 END) as ewaste_count,
                SUM(CASE WHEN activity_type = 'Metal' THEN 1 ELSE 0 END) as metal_count,
                SUM(CASE WHEN activity_type = 'Organic' THEN 1 ELSE 0 END) as organic_count
               FROM activities WHERE user_id = ? AND status = 'approved'";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get user points
$pointsQuery = "SELECT points FROM users WHERE id = ?";
$stmt = $conn->prepare($pointsQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userPoints = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

$co2Saved = ($stats['total'] ?? 0) * 2.5;

// Generate AI tips
function generateAITips($stats, $points, $co2Saved) {
    $totalActivities = $stats['total'] ?? 0;
    $tips = array();
    
    if ($totalActivities == 0) {
        $tips[] = array('icon' => '🌱', 'title' => 'Welcome to Ecos+!', 'message' => 'Start your green journey by recycling your first item!', 'color' => '#4CAF50');
        $tips[] = array('icon' => '💡', 'title' => 'Did You Know?', 'message' => 'Recycling one aluminum can saves enough energy to run a TV for 3 hours!', 'color' => '#2196F3');
        $tips[] = array('icon' => '🎯', 'title' => 'Your First Goal', 'message' => 'Complete 5 recycling activities to earn the "Green Starter" badge!', 'color' => '#FF9800');
        return $tips;
    }
    
    if ($totalActivities >= 50) {
        $tips[] = array('icon' => '🏆', 'title' => 'Amazing Achievement!', 'message' => "You've completed $totalActivities recycling activities! 🌟", 'color' => '#FFD700');
    } elseif ($totalActivities >= 20) {
        $tips[] = array('icon' => '🎉', 'title' => 'Great Progress!', 'message' => "You've completed $totalActivities activities. Keep going!", 'color' => '#4CAF50');
    }
    
    if (($stats['plastic_count'] ?? 0) > 0) {
        $tips[] = array('icon' => '🥤', 'title' => 'Plastic Recycling Hero', 'message' => "You've recycled " . ($stats['plastic_count'] ?? 0) . " plastic items!", 'color' => '#2196F3');
    }
    
    if (($stats['ewaste_count'] ?? 0) > 0) {
        $tips[] = array('icon' => '💻', 'title' => 'E-Waste Champion', 'message' => "Thank you for recycling " . ($stats['ewaste_count'] ?? 0) . " electronic items!", 'color' => '#FF9800');
    }
    
    if ($co2Saved > 0) {
        $tips[] = array('icon' => '🌍', 'title' => 'Your Carbon Footprint', 'message' => "You've saved " . number_format($co2Saved, 1) . " kg of CO₂! 🌲", 'color' => '#4CAF50');
    }
    
    $tips[] = array('icon' => '💪', 'title' => 'Keep Going!', 'message' => 'Every item you recycle makes a difference!', 'color' => '#4CAF50');
    
    return $tips;
}

$ai_tips = generateAITips($stats, $userPoints, $co2Saved);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Insights - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Poppins', sans-serif; }

        /* Navigation Bar */
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
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; align-items: center; justify-content: center; }
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
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .mobile-nav a i { width: 24px; }
        
        /* Center the navigation links on desktop */
        .navbar-container > div {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav-links {
            justify-content: center;
            flex: 1;
        }
        
        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .mobile-toggle { display: block; } 
            .user-info { display: none; } 
            .user-trigger { padding: 6px 12px; } 
            .navbar-container { padding: 0 15px; }
            .nav-links { justify-content: flex-start; }
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        /* Content Styles */
        .container-custom { max-width: 1200px; margin: 0 auto; padding: 25px; }
        .ai-hero-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 25px; padding: 35px; margin-bottom: 30px; color: white; position: relative; overflow: hidden; }
        .ai-hero-card::before { content: '♻️'; position: absolute; right: 20px; bottom: 10px; font-size: 100px; opacity: 0.1; }
        .ai-badge { background: rgba(255,255,255,0.2); display: inline-block; padding: 5px 15px; border-radius: 30px; font-size: 12px; margin-bottom: 15px; }
        .ai-title { font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .ai-subtitle { font-size: 14px; opacity: 0.9; margin-bottom: 0; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 35px; margin-bottom: 10px; }
        .stat-value { font-size: 28px; font-weight: 700; color: #4CAF50; }
        .stat-label { font-size: 13px; color: #666; margin-top: 5px; }

        .tips-section { background: white; border-radius: 20px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .tips-title { font-size: 20px; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #4CAF50; }
        .tip-card { background: #f8f9fa; border-radius: 15px; padding: 18px; margin-bottom: 15px; border-left: 4px solid; transition: all 0.3s; }
        .tip-card:hover { transform: translateX(8px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .tip-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .tip-icon { font-size: 28px; }
        .tip-title { font-size: 16px; font-weight: 600; margin: 0; }
        .tip-message { font-size: 14px; color: #555; margin-left: 40px; }

        .chart-container { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .chart-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; }
        canvas { max-height: 300px; }

        .activities-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .activities-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .activity-card { border-radius: 16px; padding: 0; overflow: hidden; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .activity-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .activity-card-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: rgba(255,255,255,0.7); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .activity-type-badge { display: flex; align-items: center; gap: 8px; }
        .activity-icon { font-size: 24px; }
        .activity-type { font-weight: 600; font-size: 14px; color: #333; }
        .activity-points-badge { background: #4CAF50; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .activity-card-body { display: flex; gap: 12px; padding: 15px; }
        .activity-image { width: 80px; height: 80px; flex-shrink: 0; border-radius: 12px; overflow: hidden; background: #f0f0f0; }
        .activity-image img { width: 100%; height: 100%; object-fit: cover; }
        .activity-image-placeholder { width: 80px; height: 80px; flex-shrink: 0; border-radius: 12px; background: #f0f0f0; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #999; font-size: 12px; }
        .activity-image-placeholder i { font-size: 24px; margin-bottom: 5px; }
        .activity-description { flex: 1; }
        .activity-description p { font-size: 13px; color: #555; margin: 0 0 8px 0; line-height: 1.4; }
        .activity-date { font-size: 11px; color: #999; }

        @media (max-width: 768px) {
            .container-custom { padding: 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .ai-title { font-size: 22px; }
            .activities-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon">
                    <img src="assets/images/umpsa.png" alt="Logo" style="height:25px; object-fit:cover;">
                </div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom active"><i class="fas fa-robot"></i> AI Insights</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
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
                    <div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div>
                    <a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
        <li><a href="ai-insights.php" class="active"><i class="fas fa-robot"></i> AI Insights</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> My Rewards</a></li>
        <?php if (isAdmin()): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- Main Content -->
<div class="container-custom">
    <div class="ai-hero-card">
        <div class="ai-badge"><i class="fas fa-robot"></i> AI-Powered Insights</div>
        <div class="ai-title">Your Personal Sustainability Assistant</div>
        <div class="ai-subtitle">Based on your <?php echo $stats['total'] ?? 0; ?> recycling activities</div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-recycle" style="color: #4CAF50;"></i></div><div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div><div class="stat-label">Total Activities</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-star" style="color: #FFD700;"></i></div><div class="stat-value"><?php echo number_format($userPoints['points']); ?></div><div class="stat-label">Total Points</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-leaf" style="color: #4CAF50;"></i></div><div class="stat-value"><?php echo number_format($co2Saved, 1); ?> kg</div><div class="stat-label">CO₂ Saved</div></div>
    </div>

    <div class="tips-section">
        <div class="tips-title"><i class="fas fa-lightbulb" style="color: #FFD700;"></i> AI-Generated Insights & Tips</div>
        <?php foreach($ai_tips as $tip): ?>
        <div class="tip-card" style="border-left-color: <?php echo $tip['color']; ?>">
            <div class="tip-header"><div class="tip-icon"><?php echo $tip['icon']; ?></div><div class="tip-title" style="color: <?php echo $tip['color']; ?>"><?php echo $tip['title']; ?></div></div>
            <div class="tip-message"><?php echo $tip['message']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="chart-container">
        <div class="chart-title"><i class="fas fa-chart-pie"></i> Your Recycling Breakdown</div>
        <canvas id="recyclingChart"></canvas>
    </div>

    <!-- Recent Activities Cards -->
    <div class="activities-card">
        <div class="chart-title"><i class="fas fa-history"></i> Recent Activities</div>
        <?php if ($activities->num_rows > 0): ?>
            <div class="activities-grid">
                <?php 
                $icons = array('Plastic' => '🥤', 'Paper' => '📄', 'Glass' => '🥃', 'E-Waste' => '💻', 'Organic' => '🍎', 'Metal' => '🥫', 'Cardboard' => '📦', 'Textile' => '👕');
                $bgColors = array('Plastic' => '#E3F2FD', 'Paper' => '#F3E5F5', 'Glass' => '#E8F5E9', 'E-Waste' => '#FFF3E0', 'Organic' => '#F1F8E9', 'Metal' => '#ECEFF1', 'Cardboard' => '#EFEBE9', 'Textile' => '#FCE4EC');
                while($activity = $activities->fetch_assoc()): 
                    $icon = isset($icons[$activity['activity_type']]) ? $icons[$activity['activity_type']] : '♻️';
                    $bgColor = isset($bgColors[$activity['activity_type']]) ? $bgColors[$activity['activity_type']] : '#f8f9fa';
                    
                    $imageUrl = '';
                    if (!empty($activity['image_path'])) {
                        if (file_exists($activity['image_path'])) {
                            $imageUrl = $activity['image_path'];
                        } elseif (file_exists('../' . $activity['image_path'])) {
                            $imageUrl = '../' . $activity['image_path'];
                        } elseif (file_exists('assets/uploads/' . basename($activity['image_path']))) {
                            $imageUrl = 'assets/uploads/' . basename($activity['image_path']);
                        } else {
                            $imageUrl = '';
                        }
                    }
                ?>
                    <div class="activity-card" style="background: <?php echo $bgColor; ?>;">
                        <div class="activity-card-header">
                            <div class="activity-type-badge"><span class="activity-icon"><?php echo $icon; ?></span><span class="activity-type"><?php echo htmlspecialchars($activity['activity_type']); ?></span></div>
                            <div class="activity-points-badge"><i class="fas fa-star"></i> +<?php echo $activity['points_earned']; ?></div>
                        </div>
                        <div class="activity-card-body">
                            <?php if (!empty($imageUrl)): ?>
                                <div class="activity-image"><img src="<?php echo $imageUrl; ?>?t=<?php echo time(); ?>" alt="Proof" onerror="this.parentElement.innerHTML='<div class=\'activity-image-placeholder\'><i class=\'fas fa-camera\'></i><span>No image</span></div>'"></div>
                            <?php else: ?>
                                <div class="activity-image-placeholder"><i class="fas fa-camera"></i><span>No image</span></div>
                            <?php endif; ?>
                            <div class="activity-description">
                                <p><?php echo htmlspecialchars(substr($activity['description'], 0, 80)); ?>...</p>
                                <div class="activity-date"><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($activity['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5"><i class="fas fa-box-open" style="font-size: 64px; color: #ccc;"></i><p class="mt-3 text-muted">No activities recorded yet.</p><a href="activity.php" class="btn btn-success mt-2">Log Your First Activity</a></div>
        <?php endif; ?>
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

    var ctx = document.getElementById('recyclingChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Plastic', 'Paper', 'Glass', 'E-Waste', 'Metal', 'Organic'],
            datasets: [{
                data: [<?php echo $stats['plastic_count'] ?? 0; ?>, <?php echo $stats['paper_count'] ?? 0; ?>, <?php echo $stats['glass_count'] ?? 0; ?>, <?php echo $stats['ewaste_count'] ?? 0; ?>, <?php echo $stats['metal_count'] ?? 0; ?>, <?php echo $stats['organic_count'] ?? 0; ?>],
                backgroundColor: ['#2196F3', '#9C27B0', '#4CAF50', '#FF9800', '#607D8B', '#8BC34A'],
                borderWidth: 0, hoverOffset: 10
            }]
        },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, usePointStyle: true, boxWidth: 10 } }, tooltip: { callbacks: { label: function(context) { return context.label + ': ' + context.raw + ' items'; } } } } }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>