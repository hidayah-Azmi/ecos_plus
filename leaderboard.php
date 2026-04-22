<?php
$page_title = 'Leaderboard';
$current_page = 'leaderboard';
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Get leaderboard data
$leaderboardQuery = "SELECT id, username, full_name, points FROM users WHERE role = 'user' ORDER BY points DESC";
$leaderboard = $conn->query($leaderboardQuery);
$totalUsers = $leaderboard->num_rows;

// Get user rank
$rankQuery = "SELECT COUNT(*) + 1 as rank FROM users WHERE points > (SELECT points FROM users WHERE id = ?) AND role = 'user'";
$stmt = $conn->prepare($rankQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userRank = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get top 3 users for podium
$podiumQuery = "SELECT full_name, points FROM users WHERE role = 'user' ORDER BY points DESC LIMIT 3";
$podium = $conn->query($podiumQuery);
$top3 = [];
while($row = $podium->fetch_assoc()) {
    $top3[] = $row;
}

// Calculate statistics
$statsQuery = "SELECT AVG(points) as avg_points, SUM(points) as total_points, COUNT(*) as total_users FROM users WHERE role = 'user'";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get current user points
$userPoints = $navbar_user['points'] ?? 0;

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Ecos+</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 35px; margin-bottom: 10px; }
        .stat-value { font-size: 28px; font-weight: 700; color: #4CAF50; }
        .stat-label { font-size: 13px; color: #666; margin-top: 5px; }
        .podium-section { background: white; border-radius: 20px; padding: 30px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .podium-title { text-align: center; margin-bottom: 25px; }
        .podium { display: flex; justify-content: center; align-items: flex-end; gap: 20px; flex-wrap: wrap; }
        .podium-card { text-align: center; padding: 20px; border-radius: 20px; transition: transform 0.3s; flex: 1; min-width: 140px; }
        .podium-card:hover { transform: translateY(-5px); }
        .podium-card.first { background: linear-gradient(135deg, #ffd700, #ffed4e); order: 2; }
        .podium-card.second { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); order: 1; }
        .podium-card.third { background: linear-gradient(135deg, #cd7f32, #e0a878); order: 3; }
        .podium-rank { font-size: 36px; font-weight: 700; margin-bottom: 10px; }
        .podium-icon { font-size: 45px; margin-bottom: 10px; }
        .podium-name { font-weight: 600; font-size: 15px; margin-bottom: 5px; }
        .podium-points { font-size: 18px; font-weight: 700; }
        .user-rank-card { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 20px; padding: 25px; margin-bottom: 30px; color: white; }
        .user-rank-info { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .user-rank-left { display: flex; align-items: center; gap: 20px; }
        .user-rank-badge { width: 70px; height: 70px; background: rgba(255,255,255,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; border: 2px solid #FFD700; }
        .user-rank-number { font-size: 32px; font-weight: 700; color: #FFD700; }
        .progress-to-next { margin-top: 15px; }
        .progress { height: 8px; border-radius: 10px; background: rgba(255,255,255,0.2); }
        .progress-bar { background: #4CAF50; border-radius: 10px; }
        .leaderboard-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .leaderboard-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 25px; text-align: center; }
        .leaderboard-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #f0f0f0; transition: all 0.3s; }
        .leaderboard-item:hover { background: #f8f9fa; transform: translateX(5px); }
        .leaderboard-item.current-user { background: #e8f5e9; border-left: 4px solid #4CAF50; }
        .rank-badge { width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #333; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #333; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #e0a878); color: #333; }
        .rank-other { background: #f0f0f0; color: #666; }
        .user-avatar-small { width: 35px; height: 35px; border-radius: 50%; background: #4CAF50; display: inline-flex; align-items: center; justify-content: center; color: white; font-weight: 600; margin-right: 12px; }
        @media (max-width: 768px) { .container-custom { padding: 15px; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .podium-card { padding: 12px; min-width: 100px; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

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
                <li><a href="leaderboard.php" class="nav-link-custom active"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Insights</a></li>
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
        <li><a href="leaderboard.php" class="active"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Insights</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
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
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-users" style="color: #4CAF50;"></i></div><div class="stat-value"><?php echo $totalUsers; ?></div><div class="stat-label">Active Recyclers</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-star" style="color: #FFD700;"></i></div><div class="stat-value"><?php echo number_format($stats['avg_points'] ?? 0); ?></div><div class="stat-label">Average Points</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line" style="color: #4CAF50;"></i></div><div class="stat-value"><?php echo number_format($stats['total_points'] ?? 0); ?></div><div class="stat-label">Total Points Awarded</div></div>
    </div>

    <?php if (!empty($top3)): ?>
    <div class="podium-section">
        <div class="podium-title"><h4><i class="fas fa-crown" style="color: #FFD700;"></i> Top Recyclers</h4><p class="text-muted small">Our champions of sustainability</p></div>
        <div class="podium"><?php $medals = ['🥇', '🥈', '🥉']; $ranks = ['first', 'second', 'third']; for($i = 0; $i < count($top3) && $i < 3; $i++): ?><div class="podium-card <?php echo $ranks[$i]; ?>"><div class="podium-rank">#<?php echo $i+1; ?></div><div class="podium-icon"><?php echo $medals[$i]; ?></div><div class="podium-name"><?php echo htmlspecialchars(substr($top3[$i]['full_name'], 0, 20)); ?></div><div class="podium-points"><?php echo number_format($top3[$i]['points']); ?> pts</div></div><?php endfor; ?></div>
    </div>
    <?php endif; ?>

    <div class="user-rank-card"><div class="user-rank-info"><div class="user-rank-left"><div class="user-rank-badge">#<?php echo $userRank['rank'] ?? 'N/A'; ?></div><div class="user-rank-text"><h4><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($navbar_user['full_name'] ?? 'You'); ?></h4><p class="mb-0 small opacity-75">Your current standing</p></div></div><div class="text-end"><div class="user-rank-number"><?php echo number_format($userPoints); ?></div><small><i class="fas fa-star"></i> Total Points</small></div></div><?php if ($pointsToNext > 0): ?><div class="progress-to-next"><div class="d-flex justify-content-between small mb-1"><span><i class="fas fa-chart-line"></i> Progress to next rank</span><span><?php echo number_format($pointsToNext); ?> points needed</span></div><div class="progress"><div class="progress-bar" style="width: <?php echo min(100, ($userPoints / max(1, $userPoints + $pointsToNext)) * 100); ?>%"></div></div></div><?php endif; ?></div>

    <div class="leaderboard-card"><div class="leaderboard-header"><i class="fas fa-trophy fa-2x"></i><h3 class="mt-2">Full Leaderboard</h3><p class="mb-0 opacity-75">Top recyclers in the Ecos+ community</p></div><div class="leaderboard-list"><?php $leaderboard->data_seek(0); $rank = 1; while($user = $leaderboard->fetch_assoc()): $isCurrentUser = ($user['id'] == $_SESSION['user_id']); $rankClass = $rank <= 3 ? "rank-$rank" : "rank-other"; $userInitial = strtoupper(substr($user['full_name'], 0, 1)); ?><div class="leaderboard-item <?php echo $isCurrentUser ? 'current-user' : ''; ?>"><div class="d-flex align-items-center"><div class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></div><div class="user-avatar-small"><?php echo $userInitial; ?></div><div><strong><?php echo htmlspecialchars($user['full_name']); ?></strong><div class="text-muted small">@<?php echo htmlspecialchars($user['username']); ?></div></div></div><div class="text-end"><h5 class="mb-0 text-success"><?php echo number_format($user['points']); ?></h5><small class="text-muted">points</small><?php if ($isCurrentUser): ?><span class="badge bg-success ms-2">You</span><?php endif; ?></div></div><?php $rank++; endwhile; ?></div></div>
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