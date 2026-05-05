<?php
$page_title = 'Rewards & Badges';
$current_page = 'rewards';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';
$unreadCount = getUnreadCount($user_id);

// Get user points
$userQuery = "SELECT points FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userPoints = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get available rewards
$rewardsQuery = "SELECT * FROM rewards WHERE is_active = 1 AND stock > 0 ORDER BY points_cost ASC";
$rewards = $conn->query($rewardsQuery);

// Get user's badges
$badgesQuery = "SELECT b.* FROM badges b INNER JOIN user_badges ub ON b.id = ub.badge_id WHERE ub.user_id = ? ORDER BY ub.earned_at DESC";
$stmt = $conn->prepare($badgesQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userBadges = $stmt->get_result();
$stmt->close();

// Get all badges for progress
$allBadgesQuery = "SELECT * FROM badges ORDER BY points_required, activities_required";
$allBadges = $conn->query($allBadgesQuery);
$totalBadges = $allBadges->num_rows;

// Handle reward redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward'])) {
    $reward_id = intval($_POST['reward_id']);
    
    $rewardQuery = "SELECT * FROM rewards WHERE id = ? AND is_active = 1 AND stock > 0";
    $stmt = $conn->prepare($rewardQuery);
    $stmt->bind_param("i", $reward_id);
    $stmt->execute();
    $reward = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reward && $userPoints['points'] >= $reward['points_cost']) {
        $updatePoints = "UPDATE users SET points = points - ? WHERE id = ?";
        $stmt = $conn->prepare($updatePoints);
        $stmt->bind_param("ii", $reward['points_cost'], $user_id);
        $stmt->execute();
        $stmt->close();
        
        $updateStock = "UPDATE rewards SET stock = stock - 1 WHERE id = ?";
        $stmt = $conn->prepare($updateStock);
        $stmt->bind_param("i", $reward_id);
        $stmt->execute();
        $stmt->close();
        
        $insertRedemption = "INSERT INTO user_rewards (user_id, reward_id, points_spent, status) VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($insertRedemption);
        $stmt->bind_param("iii", $user_id, $reward_id, $reward['points_cost']);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['user_points'] = $userPoints['points'] - $reward['points_cost'];
        $message = "🎉 Reward redeemed successfully! You spent {$reward['points_cost']} points.";
        $messageType = "success";
        $userPoints['points'] -= $reward['points_cost'];
    } else {
        $message = "❌ You don't have enough points to redeem this reward.";
        $messageType = "danger";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Rewards & Badges - Ecos+</title>
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

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Points Card */
        .points-card { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 32px; padding: 35px; margin-bottom: 30px; text-align: center; position: relative; overflow: hidden; border: 1px solid rgba(107,142,35,0.3); }
        .points-card::before { content: '♻️'; position: absolute; right: 20px; bottom: 10px; font-size: 100px; opacity: 0.05; }
        .points-number { font-size: 64px; font-weight: 800; background: linear-gradient(135deg, #FFD700, #FFA500); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .points-label { font-size: 18px; opacity: 0.8; color: white; }
        
        /* Progress Section */
        .progress-section { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; padding: 25px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.2); }
        .progress { height: 10px; border-radius: 10px; background: rgba(0,0,0,0.1); }
        .progress-bar { background: linear-gradient(90deg, #6B8E23, #8BC34A); border-radius: 10px; }
        
        /* Tabs */
        .nav-tabs { border-bottom: none; gap: 12px; margin-bottom: 30px; }
        .nav-tabs .nav-link { border: none; padding: 12px 32px; border-radius: 40px; font-weight: 600; color: #666; transition: all 0.3s; background: rgba(255,255,255,0.9); backdrop-filter: blur(5px); }
        .nav-tabs .nav-link:hover { color: #6B8E23; background: rgba(107,142,35,0.1); }
        .nav-tabs .nav-link.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 6px 15px rgba(76,175,80,0.3); }
        
        /* Reward Card */
        .reward-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 24px; padding: 25px; text-align: center; transition: all 0.3s; margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.2); }
        .reward-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
        .reward-icon { font-size: 65px; margin-bottom: 15px; display: inline-block; }
        .reward-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #1a1a2e; }
        .reward-description { font-size: 13px; color: #666; margin-bottom: 15px; }
        .reward-points { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; padding: 6px 18px; border-radius: 30px; font-size: 14px; font-weight: 600; display: inline-block; margin: 10px 0; }
        .stock-badge { font-size: 11px; color: #888; margin-bottom: 8px; }
        .btn-redeem { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; padding: 10px 28px; border-radius: 40px; font-weight: 600; transition: all 0.3s; }
        .btn-redeem:hover { transform: scale(1.05); box-shadow: 0 6px 15px rgba(107,142,35,0.4); }
        .btn-redeem:disabled { background: #ccc; transform: none; cursor: not-allowed; box-shadow: none; }
        
        /* Badge Card - JELAS BEZA YANG DAH DAPAT DAN BELUM */
        .badge-card { 
            background: rgba(30,30,40,0.7); 
            backdrop-filter: blur(10px);
            border-radius: 20px; 
            padding: 20px; 
            text-align: center; 
            transition: all 0.3s; 
            margin-bottom: 20px; 
            border: 1px solid rgba(255,255,255,0.1);
        }
        /* Badge yang sudah diperolehi - WARLA TERANG/HIJAU */
        .badge-card.earned { 
            background: linear-gradient(135deg, rgba(76,175,80,0.3), rgba(107,142,35,0.3));
            border: 2px solid #4CAF50;
            box-shadow: 0 5px 20px rgba(76,175,80,0.2);
        }
        .badge-card.earned .badge-icon { filter: drop-shadow(0 0 10px rgba(76,175,80,0.5)); }
        .badge-card.earned .badge-name { color: #4CAF50; }
        
        /* Badge yang belum diperolehi - KELABU/SUram */
        .badge-card.locked { 
            background: rgba(20,20,30,0.5);
            filter: grayscale(0.3);
            opacity: 0.7;
        }
        .badge-card.locked .badge-icon { filter: grayscale(0.5); opacity: 0.5; }
        .badge-card.locked .badge-name { color: #888; }
        
        .badge-card:hover { transform: translateY(-5px); }
        .badge-icon { font-size: 52px; margin-bottom: 12px; display: inline-block; }
        .badge-name { font-size: 15px; font-weight: 700; margin-bottom: 5px; color: rgba(255,255,255,0.9); }
        .badge-desc { font-size: 11px; color: rgba(255,255,255,0.6); margin-bottom: 10px; line-height: 1.4; }
        .badge-status { font-size: 11px; font-weight: 600; margin-top: 8px; padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .badge-status.earned { background: rgba(76,175,80,0.2); color: #4CAF50; border: 1px solid #4CAF50; }
        .badge-status.locked { background: rgba(100,100,100,0.2); color: #999; border: 1px solid #666; }
        
        /* Alert */
        .alert { border-radius: 20px; padding: 15px 20px; margin-bottom: 25px; border: none; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-danger { background: #fee2e2; color: #dc2626; border-left: 4px solid #ef4444; }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            .points-number { font-size: 48px; }
            .nav-tabs .nav-link { padding: 8px 20px; font-size: 13px; }
            .reward-card { padding: 18px; }
            .badge-card { padding: 15px; }
        }
        @media (max-width: 480px) {
            .points-number { font-size: 36px; }
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
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Tips</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php" class="active"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <!-- Points Card -->
    <div class="points-card">
        <i class="fas fa-star fa-2x mb-2" style="color: #FFD700;"></i>
        <div class="points-number"><?php echo number_format($userPoints['points']); ?></div>
        <div class="points-label">Your Total Eco-Points</div>
        <small style="color: white; opacity: 0.6;">♻️ Keep recycling to earn more points and unlock amazing rewards!</small>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Progress Section -->
    <div class="progress-section">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h5 class="mb-1"><i class="fas fa-chart-line" style="color: #6B8E23;"></i> Badge Collection Progress</h5>
                <p class="text-muted small mb-0">You've earned <strong><?php echo $userBadges->num_rows; ?></strong> out of <strong><?php echo $totalBadges; ?></strong> badges</p>
            </div>
            <div class="col-md-5">
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo ($userBadges->num_rows / max($totalBadges, 1)) * 100; ?>%"></div>
                </div>
                <div class="text-end mt-1">
                    <small class="text-muted"><?php echo round(($userBadges->num_rows / max($totalBadges, 1)) * 100); ?>% Complete</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="rewardTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rewards">
                <i class="fas fa-gift"></i> Rewards Store
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#badges">
                <i class="fas fa-medal"></i> My Badges
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Rewards Store Tab -->
        <div class="tab-pane fade show active" id="rewards">
            <div class="row">
                <?php if ($rewards->num_rows > 0): ?>
                    <?php while($reward = $rewards->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="reward-card">
                                <div class="reward-icon"><?php echo $reward['icon']; ?></div>
                                <h5 class="reward-title"><?php echo htmlspecialchars($reward['name']); ?></h5>
                                <p class="reward-description"><?php echo htmlspecialchars($reward['description']); ?></p>
                                <div class="reward-points">
                                    <i class="fas fa-star"></i> <?php echo number_format($reward['points_cost']); ?> points
                                </div>
                                <div class="stock-badge">
                                    <i class="fas fa-box"></i> Stock: <?php echo $reward['stock']; ?> left
                                </div>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                    <button type="submit" name="redeem_reward" class="btn-redeem" 
                                        <?php echo ($userPoints['points'] < $reward['points_cost'] || $reward['stock'] <= 0) ? 'disabled' : ''; ?>>
                                        <?php if ($userPoints['points'] < $reward['points_cost']): ?>
                                            <i class="fas fa-lock"></i> Need <?php echo number_format($reward['points_cost'] - $userPoints['points']); ?> more
                                        <?php elseif ($reward['stock'] <= 0): ?>
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        <?php else: ?>
                                            <i class="fas fa-gift"></i> Redeem Now
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-box-open" style="font-size: 64px; color: #ccc;"></i>
                        <p class="mt-3 text-muted">No rewards available at the moment.</p>
                        <p class="small text-muted">Check back soon for new rewards!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Badges Tab - JELAS BEZA YANG DAH DAPAT DAN BELUM -->
        <div class="tab-pane fade" id="badges">
            <div class="row">
                <?php 
                $earnedBadges = [];
                $userBadges->data_seek(0);
                while($badge = $userBadges->fetch_assoc()) {
                    $earnedBadges[$badge['id']] = true;
                }
                $allBadges->data_seek(0);
                while($badge = $allBadges->fetch_assoc()): 
                    $isEarned = isset($earnedBadges[$badge['id']]);
                ?>
                    <div class="col-lg-3 col-md-4 col-6">
                        <div class="badge-card <?php echo $isEarned ? 'earned' : 'locked'; ?>">
                            <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div>
                            <div class="badge-status <?php echo $isEarned ? 'earned' : 'locked'; ?>">
                                <?php if ($isEarned): ?>
                                    <i class="fas fa-check-circle"></i> Earned!
                                <?php elseif ($badge['points_required'] > 0): ?>
                                    <i class="fas fa-star"></i> Need <?php echo number_format($badge['points_required']); ?> points
                                <?php elseif ($badge['activities_required'] > 0): ?>
                                    <i class="fas fa-recycle"></i> Need <?php echo $badge['activities_required']; ?> activities
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>