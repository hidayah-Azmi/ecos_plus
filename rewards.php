<?php
$page_title = 'Rewards & Badges';
$current_page = 'rewards';
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

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
        $message = "Reward redeemed successfully! You spent {$reward['points_cost']} points.";
        $messageType = "success";
        $userPoints['points'] -= $reward['points_cost'];
    } else {
        $message = "You don't have enough points to redeem this reward.";
        $messageType = "danger";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Badges - Ecos+</title>
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

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 25px; }
        .points-card { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; border-radius: 24px; padding: 30px; margin-bottom: 30px; text-align: center; position: relative; overflow: hidden; }
        .points-card::before { content: '♻️'; position: absolute; right: 20px; bottom: 10px; font-size: 80px; opacity: 0.1; }
        .points-number { font-size: 56px; font-weight: 700; background: linear-gradient(135deg, #FFD700, #FFA500); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .reward-card { background: white; border-radius: 20px; padding: 20px; text-align: center; transition: all 0.3s; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .reward-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .reward-icon { font-size: 60px; margin-bottom: 15px; }
        .reward-title { font-size: 18px; font-weight: 600; margin-bottom: 8px; color: #333; }
        .reward-description { font-size: 13px; color: #666; margin-bottom: 15px; }
        .reward-points { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 6px 15px; border-radius: 30px; font-size: 14px; font-weight: 600; display: inline-block; margin: 10px 0; }
        .btn-redeem { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; border: none; padding: 10px 25px; border-radius: 30px; font-weight: 600; transition: all 0.3s; }
        .btn-redeem:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(76,175,80,0.3); }
        .btn-redeem:disabled { background: #ccc; transform: none; cursor: not-allowed; }
        .badge-card { background: white; border-radius: 20px; padding: 20px; text-align: center; transition: all 0.3s; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .badge-card.earned { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4CAF50; }
        .badge-card.locked { opacity: 0.7; filter: grayscale(0.2); }
        .badge-icon { font-size: 50px; margin-bottom: 12px; }
        .badge-name { font-size: 15px; font-weight: 600; margin-bottom: 5px; color: #333; }
        .badge-desc { font-size: 11px; color: #666; margin-bottom: 10px; }
        .badge-status { font-size: 11px; font-weight: 600; }
        .badge-status.earned { color: #4CAF50; }
        .nav-tabs { border-bottom: none; gap: 10px; margin-bottom: 25px; }
        .nav-tabs .nav-link { border: none; padding: 12px 30px; border-radius: 40px; font-weight: 600; color: #666; transition: all 0.3s; }
        .nav-tabs .nav-link:hover { color: #4CAF50; background: #e8f5e9; }
        .nav-tabs .nav-link.active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        .progress-section { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .progress { height: 10px; border-radius: 10px; background: #e0e0e0; }
        .progress-bar { background: linear-gradient(90deg, #4CAF50, #8BC34A); border-radius: 10px; }
        @media (max-width: 768px) { .container-custom { padding: 15px; } .points-number { font-size: 40px; } }
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
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
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
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php" class="active"><i class="fas fa-gift"></i> Rewards</a></li>
        <?php if (isAdmin()): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <div class="points-card"><i class="fas fa-star fa-2x mb-2" style="color: #FFD700;"></i><div class="points-number"><?php echo number_format($userPoints['points']); ?></div><div class="points-label">Your Total Eco-Points</div><small style="opacity: 0.7;">Keep recycling to earn more points and unlock amazing rewards!</small></div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert"><i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="progress-section"><div class="row align-items-center"><div class="col-md-6"><h5 class="mb-1"><i class="fas fa-chart-line"></i> Badge Collection Progress</h5><p class="text-muted small">You've earned <?php echo $userBadges->num_rows; ?> out of <?php echo $totalBadges; ?> badges</p></div><div class="col-md-6"><div class="progress"><div class="progress-bar" style="width: <?php echo ($userBadges->num_rows / max($totalBadges, 1)) * 100; ?>%"></div></div><div class="text-end mt-1"><small class="text-muted"><?php echo round(($userBadges->num_rows / max($totalBadges, 1)) * 100); ?>% Complete</small></div></div></div></div>

    <ul class="nav nav-tabs" id="rewardTabs" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rewards"><i class="fas fa-store"></i> Rewards Store</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#badges"><i class="fas fa-medal"></i> My Badges Collection</button></li></ul>

    <div class="tab-content"><div class="tab-pane fade show active" id="rewards"><div class="row"><?php if ($rewards->num_rows > 0): ?><?php while($reward = $rewards->fetch_assoc()): ?><div class="col-md-4"><div class="reward-card"><div class="reward-icon"><?php echo $reward['icon']; ?></div><h5 class="reward-title"><?php echo htmlspecialchars($reward['name']); ?></h5><p class="reward-description"><?php echo htmlspecialchars($reward['description']); ?></p><div class="reward-points"><i class="fas fa-star"></i> <?php echo number_format($reward['points_cost']); ?> points</div><div class="stock-badge mb-2"><i class="fas fa-box"></i> Stock: <?php echo $reward['stock']; ?> left</div><form method="POST"><input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>"><button type="submit" name="redeem_reward" class="btn-redeem" <?php echo ($userPoints['points'] < $reward['points_cost'] || $reward['stock'] <= 0) ? 'disabled' : ''; ?>><?php if ($userPoints['points'] < $reward['points_cost']): ?><i class="fas fa-lock"></i> Need <?php echo number_format($reward['points_cost'] - $userPoints['points']); ?> more<?php elseif ($reward['stock'] <= 0): ?><i class="fas fa-times-circle"></i> Out of Stock<?php else: ?><i class="fas fa-gift"></i> Redeem Now<?php endif; ?></button></form></div></div><?php endwhile; ?><?php else: ?><div class="col-12 text-center py-5"><i class="fas fa-box-open" style="font-size: 64px; color: #ccc;"></i><p class="mt-3 text-muted">No rewards available at the moment.</p></div><?php endif; ?></div></div>

    <div class="tab-pane fade" id="badges"><div class="row"><?php $earnedBadges = []; $userBadges->data_seek(0); while($badge = $userBadges->fetch_assoc()) { $earnedBadges[$badge['id']] = true; } $allBadges->data_seek(0); while($badge = $allBadges->fetch_assoc()): $isEarned = isset($earnedBadges[$badge['id']]); ?><div class="col-lg-3 col-md-4 col-6"><div class="badge-card <?php echo $isEarned ? 'earned' : 'locked'; ?>"><div class="badge-icon"><?php echo $badge['icon']; ?></div><div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div><div class="badge-desc"><?php echo htmlspecialchars($badge['description']); ?></div><div class="badge-status <?php echo $isEarned ? 'earned' : 'locked'; ?>"><?php if ($isEarned): ?><i class="fas fa-check-circle"></i> Earned!<?php elseif ($badge['points_required'] > 0): ?><i class="fas fa-star"></i> Need <?php echo number_format($badge['points_required']); ?> points<?php elseif ($badge['activities_required'] > 0): ?><i class="fas fa-recycle"></i> Need <?php echo $badge['activities_required']; ?> activities<?php endif; ?></div></div></div><?php endwhile; ?></div></div></div>
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>