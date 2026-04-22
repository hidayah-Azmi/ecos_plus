<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

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

// Get badges
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ecos+</title>
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
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .mobile-nav a i { width: 24px; }
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } .user-trigger { padding: 6px 12px; } .navbar-container { padding: 0 15px; } }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .welcome-banner { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 25px; border-radius: 20px; margin-bottom: 25px; position: relative; overflow: hidden; cursor: pointer; transition: transform 0.3s; }
        .welcome-banner:hover { transform: translateY(-3px); }
        .welcome-banner::after { content: '♻️'; position: absolute; right: 20px; bottom: 10px; font-size: 80px; opacity: 0.1; }
        .stats-card { background: white; border-radius: 20px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s; cursor: pointer; }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-number { font-size: 32px; font-weight: 700; color: #4CAF50; }
        .stats-icon { font-size: 40px; margin-bottom: 10px; color: #4CAF50; }
        .activity-item { border-left: 3px solid #4CAF50; padding: 12px 15px; background: #f8f9fa; margin-bottom: 10px; border-radius: 12px; transition: all 0.3s; }
        .activity-item:hover { background: #e8f5e9; }
        .badge-item { text-align: center; padding: 10px; background: #f8f9fa; border-radius: 12px; margin-bottom: 10px; transition: all 0.3s; }
        .badge-item:hover { transform: scale(1.05); background: #e8f5e9; }
        .leaderboard-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; transition: all 0.3s; }
        .leaderboard-item:hover { background: #f8f9fa; padding-left: 15px; }
        .rank-1 { color: #ffd700; font-weight: bold; }
        .rank-2 { color: #c0c0c0; font-weight: bold; }
        .rank-3 { color: #cd7f32; font-weight: bold; }
        .card-custom { background: white; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 20px; overflow: hidden; }
        .card-header-custom { background: white; border-bottom: 2px solid #4CAF50; padding: 15px 20px; font-weight: 600; }
        .quick-action { text-align: center; padding: 15px; background: white; border-radius: 12px; text-decoration: none; display: block; color: #333; border: 1px solid #e0e0e0; transition: all 0.3s; }
        .quick-action:hover { transform: translateY(-5px); border-color: #4CAF50; color: #4CAF50; }
        .quick-action i { font-size: 32px; color: #4CAF50; margin-bottom: 10px; }
        .impact-card { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 20px; padding: 20px; text-align: center; transition: all 0.3s; cursor: pointer; }
        .impact-card:hover { transform: translateY(-3px); }
        .impact-number { font-size: 28px; font-weight: 700; color: #4CAF50; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        /* Floating Camera Button */
        .camera-floating-btn { 
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
            cursor: pointer; 
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .camera-floating-btn:hover { 
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        /* Modal Styles */
        #video { 
            width: 100%; 
            border-radius: 12px; 
            background: #000;
            max-height: 400px;
            object-fit: cover;
        }
        #canvas { display: none; }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            .stats-number { font-size: 24px; } 
            .impact-number { font-size: 20px; }
            .camera-floating-btn { bottom: 15px; right: 15px; width: 50px; height: 50px; }
            .camera-floating-btn i { font-size: 20px; }
        }
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
                <li><a href="dashboard.php" class="nav-link-custom active"><i class="fas fa-home"></i> Dashboard</a></li>
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
        <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="activity.php"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
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
    <div class="welcome-banner" onclick="location.href='activity.php'">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3><i class="fas fa-user-circle"></i> Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h3>
                <p class="mb-0">Keep up the great work! Every recycling activity makes a difference for our planet.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-globe-asia" style="font-size: 60px; opacity: 0.8;"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='rewards.php'">
                <div class="stats-icon"><i class="fas fa-star"></i></div>
                <div class="stats-number"><?php echo number_format($userPoints['points']); ?></div>
                <div>Total Points</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='activity.php'">
                <div class="stats-icon"><i class="fas fa-recycle"></i></div>
                <div class="stats-number"><?php echo $userStats['total_activities'] ?? 0; ?></div>
                <div>Total Activities</div>
                <small><?php echo $userStats['approved_count'] ?? 0; ?> approved</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='rewards.php#badges'">
                <div class="stats-icon"><i class="fas fa-trophy"></i></div>
                <div class="stats-number"><?php echo $userBadges->num_rows; ?></div>
                <div>Badges Earned</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='leaderboard.php'">
                <div class="stats-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stats-number">#<?php echo $userRank['rank'] ?? 1; ?></div>
                <div>Your Rank</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="impact-card" onclick="location.href='activity.php'">
                <i class="fas fa-cloud" style="font-size: 32px;"></i>
                <div class="impact-number"><?php echo round($co2Saved); ?> kg</div>
                <div>CO₂ Saved</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="impact-card" onclick="location.href='activity.php'">
                <i class="fas fa-tree" style="font-size: 32px;"></i>
                <div class="impact-number"><?php echo round($co2Saved / 21); ?></div>
                <div>Trees Saved Equivalent</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="impact-card" onclick="location.href='activity.php'">
                <i class="fas fa-bottle-water" style="font-size: 32px;"></i>
                <div class="impact-number"><?php echo ($userStats['approved_count'] ?? 0) * 50; ?></div>
                <div>Plastic Bottles Saved</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card-custom">
                <div class="card-header-custom"><i class="fas fa-clock"></i> Recent Activities</div>
                <div class="p-3">
                    <?php if ($recentActivities->num_rows > 0): ?>
                        <?php while($activity = $recentActivities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($activity['activity_type']); ?></strong>
                                        <p class="mb-0 text-muted small"><?php echo htmlspecialchars(substr($activity['description'], 0, 60)); ?>...</p>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-<?php echo $activity['status']; ?>">
                                            <?php echo ucfirst($activity['status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></small>
                                        <?php if ($activity['points_earned'] > 0): ?>
                                            <br>
                                            <small class="text-success">+<?php echo $activity['points_earned']; ?> pts</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box-open" style="font-size: 48px; color: #ccc;"></i>
                            <p class="mt-3 text-muted">No activities yet. Start recycling!</p>
                            <a href="activity.php" class="btn btn-success btn-sm">Log Your First Activity</a>
                        </div>
                    <?php endif; ?>
                    <a href="activity.php" class="btn btn-outline-success btn-sm mt-2">View All Activities</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-custom">
                <div class="card-header-custom"><i class="fas fa-bolt"></i> Quick Actions</div>
                <div class="p-3">
                    <div class="row">
                        <div class="col-6 mb-3"><a href="activity.php" class="quick-action"><i class="fas fa-recycle"></i><div>Log Activity</div><small>+ points</small></a></div>
                        <div class="col-6 mb-3"><a href="map.php" class="quick-action"><i class="fas fa-map-marker-alt"></i><div>Find Recycling</div><small>Nearby centers</small></a></div>
                        <div class="col-6 mb-3"><a href="ai-insights.php" class="quick-action"><i class="fas fa-robot"></i><div>AI Tips</div><small>Get insights</small></a></div>
                        <div class="col-6 mb-3"><a href="rewards.php" class="quick-action"><i class="fas fa-gift"></i><div>Redeem Points</div><small>Shop rewards</small></a></div>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-header-custom"><i class="fas fa-medal"></i> Your Badges</div>
                <div class="p-3">
                    <div class="row">
                        <?php if ($userBadges->num_rows > 0): ?>
                            <?php while($badge = $userBadges->fetch_assoc()): ?>
                                <div class="col-6">
                                    <div class="badge-item">
                                        <div style="font-size: 32px;"><?php echo $badge['icon']; ?></div>
                                        <small><?php echo htmlspecialchars($badge['name']); ?></small>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-3">
                                <i class="fas fa-award" style="font-size: 48px; color: #ccc;"></i>
                                <p class="text-muted mt-2">Complete activities to earn badges!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="rewards.php#badges" class="btn btn-outline-success btn-sm w-100 mt-2">View All Badges</a>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-header-custom"><i class="fas fa-chart-line"></i> Top Recyclers</div>
                <div class="p-3">
                    <?php 
                    if ($leaderboard && $leaderboard->num_rows > 0):
                        $rank = 1;
                        while($userData = $leaderboard->fetch_assoc()): 
                    ?>
                        <div class="leaderboard-item">
                            <div><span class="rank-<?php echo $rank; ?>">#<?php echo $rank; ?></span> <?php echo htmlspecialchars($userData['full_name']); ?></div>
                            <strong><?php echo number_format($userData['points']); ?> pts</strong>
                        </div>
                    <?php 
                        $rank++;
                        endwhile;
                    else:
                    ?>
                        <div class="text-center py-3"><p class="text-muted">No users yet. Be the first!</p></div>
                    <?php endif; ?>
                    <a href="leaderboard.php" class="btn btn-outline-success btn-sm w-100 mt-2">View Full Leaderboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Camera Button -->
<button class="camera-floating-btn" data-bs-toggle="modal" data-bs-target="#cameraModal">
    <i class="fas fa-camera fa-2x"></i>
</button>

<!-- Camera Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header bg-success text-white" style="border-radius: 20px 20px 0 0;">
                <h5 class="modal-title" id="cameraModalLabel">
                    <i class="fas fa-camera"></i> Quick Recycle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <p class="text-muted">Take a photo of your recyclable item</p>
                <video id="video" autoplay playsinline style="width: 100%; border-radius: 12px; background: #000;"></video>
                <canvas id="canvas" style="display: none;"></canvas>
                <div id="loading" class="mt-3" style="display:none;">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Processing...</p>
                </div>
                <div id="result" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="switchCamBtn"><i class="fas fa-sync-alt"></i> Switch Camera</button>
                <button class="btn btn-success" id="captureBtn"><i class="fas fa-camera"></i> Capture & Continue</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Mobile menu toggle
    const mobileToggleBtn = document.getElementById('mobileToggleBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenu.classList.toggle('show');
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileToggleBtn && mobileMenu.classList.contains('show') && 
            !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });
    
    // Camera functionality
    let currentFacing = 'environment';
    let stream = null;
    let video = document.getElementById('video');
    let canvas = document.getElementById('canvas');
    let ctx = canvas.getContext('2d');
    
    async function startCamera() {
        if (stream) stream.getTracks().forEach(track => track.stop());
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: { exact: currentFacing } } 
            });
            video.srcObject = stream;
        } catch(e) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
            } catch(err) {
                alert('Cannot access camera. Please check permissions.');
            }
        }
    }
    
    document.getElementById('cameraModal').addEventListener('shown.bs.modal', () => startCamera());
    document.getElementById('cameraModal').addEventListener('hidden.bs.modal', () => {
        if (stream) stream.getTracks().forEach(track => track.stop());
        document.getElementById('result').innerHTML = '';
        document.getElementById('loading').style.display = 'none';
    });
    
    document.getElementById('switchCamBtn').onclick = () => {
        currentFacing = currentFacing === 'environment' ? 'user' : 'environment';
        startCamera();
    };
    
    document.getElementById('captureBtn').onclick = function() {
        if (!video.videoWidth || !video.videoHeight) {
            alert('Please wait for camera to initialize');
            return;
        }
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        
        document.getElementById('loading').style.display = 'block';
        document.getElementById('result').innerHTML = '';
        
        // Convert canvas to blob and save to localStorage
        canvas.toBlob(function(blob) {
            let reader = new FileReader();
            reader.onloadend = function() {
                // Store the captured image in localStorage
                localStorage.setItem('captured_image', reader.result);
                localStorage.setItem('captured_image_time', Date.now().toString());
                
                // Show success message
                document.getElementById('loading').style.display = 'none';
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Photo captured successfully!
                        <br><small>Redirecting to activity form...</small>
                    </div>
                `;
                
                // Redirect to activity.php after short delay
                setTimeout(() => {
                    window.location.href = 'activity.php?from_camera=1';
                }, 1500);
            };
            reader.readAsDataURL(blob);
        }, 'image/jpeg', 0.9);
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>