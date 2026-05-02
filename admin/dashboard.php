<?php
$page_title = 'Admin Dashboard';
$current_page = 'admin';
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total activities
$result = $conn->query("SELECT COUNT(*) as count FROM activities");
$stats['total_activities'] = $result->fetch_assoc()['count'];

// Pending activities
$result = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'pending'");
$stats['pending_activities'] = $result->fetch_assoc()['count'];

// Approved activities
$result = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'approved'");
$stats['approved_activities'] = $result->fetch_assoc()['count'];

// Rejected activities
$result = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'rejected'");
$stats['rejected_activities'] = $result->fetch_assoc()['count'];

// Total points awarded
$result = $conn->query("SELECT SUM(points) as total FROM users");
$stats['total_points'] = $result->fetch_assoc()['total'] ?? 0;

// Total recycling locations
$result = $conn->query("SELECT COUNT(*) as count FROM recycling_locations WHERE is_active = 1");
$stats['total_locations'] = $result->fetch_assoc()['count'];

// Total events
$result = $conn->query("SELECT COUNT(*) as count FROM campus_events WHERE is_active = 1");
$stats['total_events'] = $result->fetch_assoc()['count'];

// Total community posts
$result = $conn->query("SELECT COUNT(*) as count FROM community_posts");
$stats['total_posts'] = $result->fetch_assoc()['count'];

// Recent activities for approval
$pendingQuery = "SELECT a.*, u.username, u.full_name 
                 FROM activities a 
                 INNER JOIN users u ON a.user_id = u.id 
                 WHERE a.status = 'pending' 
                 ORDER BY a.created_at DESC LIMIT 5";
$pendingActivities = $conn->query($pendingQuery);

// Monthly activity data for chart
$monthlyQuery = "SELECT 
                    DATE_FORMAT(created_at, '%M') as month,
                    COUNT(*) as count,
                    SUM(points_earned) as points
                 FROM activities 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY MONTH(created_at)
                 ORDER BY created_at ASC";
$monthlyData = $conn->query($monthlyQuery);

$months = [];
$activityCounts = [];
$pointsEarned = [];

while($row = $monthlyData->fetch_assoc()) {
    $months[] = substr($row['month'], 0, 3);
    $activityCounts[] = $row['count'];
    $pointsEarned[] = $row['points'];
}

// Top users by points
$topUsersQuery = "SELECT username, full_name, points FROM users WHERE role = 'user' ORDER BY points DESC LIMIT 5";
$topUsers = $conn->query($topUsersQuery);

// Recent users
$recentUsersQuery = "SELECT username, full_name, email, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5";
$recentUsers = $conn->query($recentUsersQuery);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f2f5;
            font-family: 'Poppins', sans-serif;
        }

        /* Navigation Bar Styles */
        .navbar-custom {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 25px;
        }
        .navbar-brand-custom {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            padding: 12px 0;
        }
        .logo-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-icon i { font-size: 20px; color: white; }
        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }
        .logo-text span { color: #4CAF50; }
        .nav-links {
            display: flex;
            gap: 5px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .nav-link-custom i { font-size: 16px; }
        .nav-link-custom:hover {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
            transform: translateY(-2px);
        }
        .nav-link-custom.active {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 4px 10px rgba(76,175,80,0.3);
        }
        .user-dropdown {
            position: relative;
            cursor: pointer;
        }
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.08);
            border-radius: 40px;
            transition: all 0.3s ease;
        }
        .user-trigger:hover { background: rgba(255,255,255,0.15); }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 15px;
            color: white;
        }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-arrow {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            transition: transform 0.3s;
        }
        .user-dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        .dropdown-menu-custom {
            position: absolute;
            top: 55px;
            right: 0;
            width: 220px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 100;
        }
        .user-dropdown:hover .dropdown-menu-custom {
            opacity: 1;
            visibility: visible;
            top: 60px;
        }
        .dropdown-item-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }
        .dropdown-item-custom:last-child { border-bottom: none; }
        .dropdown-item-custom:hover {
            background: #f8f9fa;
            color: #4CAF50;
        }
        .dropdown-item-custom i { width: 20px; color: #4CAF50; }
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
        }
        .mobile-menu {
            display: none;
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            height: calc(100vh - 70px);
            background: #1a1a2e;
            z-index: 999;
            padding: 20px;
            overflow-y: auto;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        .mobile-menu.show { transform: translateX(0); }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
        }
        .mobile-nav a:hover, .mobile-nav a.active {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        .mobile-nav a i { width: 24px; }
        @media (max-width: 992px) {
            .nav-links { display: none; }
            .mobile-toggle { display: block; }
            .user-info { display: none; }
            .user-trigger { padding: 6px 12px; }
            .navbar-container { padding: 0 15px; }
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        /* Admin Dashboard Styles */
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
        }

        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            cursor: pointer;
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-number {
            font-size: 32px;
            font-weight: 700;
            color: #4CAF50;
        }
        .stats-icon {
            font-size: 40px;
            margin-bottom: 10px;
            color: #4CAF50;
        }
        .stats-label {
            font-size: 14px;
            color: #666;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4CAF50;
        }

        .activity-item {
            border-left: 3px solid #FF9800;
            padding: 12px 15px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        .activity-item:hover {
            background: #fff3cd;
        }

        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .user-item:last-child {
            border-bottom: none;
        }

        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
        }
        .btn-approve:hover, .btn-reject:hover {
            opacity: 0.9;
            color: white;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .container-custom {
                padding: 15px;
            }
            .stats-number {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon">
                    <img src="assets/logo/12.png" alt="Logo" style="height:30px; object-fit:cover;">
                </div>
                <div class="logo-text">Ecos<span>+</span> Admin</div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php" class="nav-link-custom"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="activities.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Activities</a></li>
                <li><a href="locations.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <li><a href="reports.php" class="nav-link-custom"><i class="fas fa-chart-line"></i> Reports</a></li>
            </ul>
            <div class="user-dropdown">
                <div class="user-trigger">
                    <div class="user-avatar"><?php echo $navbar_initial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'Admin'); ?></span>
                        <span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu-custom">
                    <a href="../profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a>
                    <a href="../dashboard.php" class="dropdown-item-custom"><i class="fas fa-globe"></i> Back to Site</a>
                    <div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div>
                    <a href="../logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <button class="mobile-toggle" id="mobileToggleBtn"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="activities.php"><i class="fas fa-recycle"></i> Activities</a></li>
        <li><a href="locations.php"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="../profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="../dashboard.php"><i class="fas fa-globe"></i> Back to Site</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- Main Content -->
<div class="container-custom">
    <!-- Welcome Banner -->
    <div class="alert alert-success mb-4" style="border-radius: 20px;">
        <i class="fas fa-user-shield"></i> Welcome back, <strong><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'Admin'); ?></strong>! Here's your system overview.
    </div>

    <!-- Statistics Cards Row 1 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='users.php'">
                <div class="stats-icon"><i class="fas fa-users"></i></div>
                <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                <div class="stats-label">Total Users</div>
                <small class="text-muted">Click to manage</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='activities.php'">
                <div class="stats-icon"><i class="fas fa-recycle"></i></div>
                <div class="stats-number"><?php echo $stats['total_activities']; ?></div>
                <div class="stats-label">Total Activities</div>
                <small class="text-muted">All submissions</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='activities.php?filter=pending'">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-number"><?php echo $stats['pending_activities']; ?></div>
                <div class="stats-label">Pending Approval</div>
                <small class="text-muted">Needs action</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-star"></i></div>
                <div class="stats-number"><?php echo number_format($stats['total_points']); ?></div>
                <div class="stats-label">Total Points</div>
                <small class="text-muted">Awarded to users</small>
            </div>
        </div>
    </div>

    <!-- Statistics Cards Row 2 -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card" onclick="location.href='locations.php'">
                <div class="stats-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stats-number"><?php echo $stats['total_locations']; ?></div>
                <div class="stats-label">Recycling Locations</div>
                <small class="text-muted">Manage locations</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card" onclick="location.href='events.php'">
                <div class="stats-icon"><i class="fas fa-calendar"></i></div>
                <div class="stats-number"><?php echo $stats['total_events']; ?></div>
                <div class="stats-label">Active Events</div>
                <small class="text-muted">Manage events</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card" onclick="location.href='activities.php'">
                <div class="stats-icon"><i class="fas fa-comments"></i></div>
                <div class="stats-number"><?php echo $stats['total_posts']; ?></div>
                <div class="stats-label">Community Posts</div>
                <small class="text-muted">Total posts</small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-line"></i> Activity Overview (Last 6 Months)
                </div>
                <canvas id="activityChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie"></i> Activity Status
                </div>
                <canvas id="statusChart" style="max-height: 250px;"></canvas>
                <div class="mt-3 text-center">
                    <span class="badge bg-success">Approved: <?php echo $stats['approved_activities']; ?></span>
                    <span class="badge bg-warning">Pending: <?php echo $stats['pending_activities']; ?></span>
                    <span class="badge bg-danger">Rejected: <?php echo $stats['rejected_activities']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Activities & Top Users Row -->
    <div class="row mt-4">
        <div class="col-lg-7">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-clock"></i> Pending Activities for Approval
                    <a href="activities.php" class="float-end text-success small">View all →</a>
                </div>
                <?php if ($pendingActivities->num_rows > 0): ?>
                    <?php while($activity = $pendingActivities->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="flex-grow-1">
                                    <strong>
                                        <?php 
                                        $icons = ['Plastic' => '🥤', 'Paper' => '📄', 'Glass' => '🥃', 'E-Waste' => '💻', 'Organic' => '🍎', 'Metal' => '🥫', 'Cardboard' => '📦', 'Textile' => '👕'];
                                        echo $icons[$activity['activity_type']] ?? '♻️';
                                        ?> <?php echo htmlspecialchars($activity['activity_type']); ?>
                                    </strong>
                                    <p class="mb-0 text-muted small"><?php echo htmlspecialchars(substr($activity['description'], 0, 60)); ?>...</p>
                                    <small>
                                        <i class="fas fa-user"></i> By: <?php echo htmlspecialchars($activity['full_name']); ?> 
                                        (@<?php echo htmlspecialchars($activity['username']); ?>)
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                    <?php if ($activity['points_earned']): ?>
                                        <br>
                                        <small class="text-success"><i class="fas fa-star"></i> +<?php echo $activity['points_earned']; ?> points</small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end mt-2 mt-sm-0">
                                    <span class="status-badge status-pending">Pending</span>
                                    <br>
                                    <div class="mt-2">
                                        <a href="activities.php?approve=<?php echo $activity['id']; ?>" class="btn-approve btn-sm" onclick="return confirm('Approve this activity? Points will be awarded.')">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="activities.php?reject=<?php echo $activity['id']; ?>" class="btn-reject btn-sm" onclick="return confirm('Reject this activity?')">
                                            <i class="fas fa-times"></i> Reject
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #4CAF50;"></i>
                        <p class="mt-3 text-muted">No pending activities to review!</p>
                        <p class="small text-muted">All activities have been processed.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Top Users -->
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-trophy"></i> Top Recyclers
                    <a href="users.php" class="float-end text-success small">View all →</a>
                </div>
                <?php 
                $rank = 1;
                while($user = $topUsers->fetch_assoc()): 
                ?>
                    <div class="user-item">
                        <div>
                            <span class="fw-bold me-2">#<?php echo $rank; ?></span>
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($user['full_name']); ?>
                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                        </div>
                        <div>
                            <span class="fw-bold text-success"><?php echo number_format($user['points']); ?></span>
                            <small class="text-muted">pts</small>
                        </div>
                    </div>
                <?php 
                    $rank++;
                endwhile; 
                ?>
            </div>

            <!-- Recent Users -->
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-user-plus"></i> Newest Members
                    <a href="users.php" class="float-end text-success small">View all →</a>
                </div>
                <?php while($user = $recentUsers->fetch_assoc()): ?>
                    <div class="user-item">
                        <div>
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($user['full_name']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                        </div>
                        <div>
                            <small class="text-muted"><?php echo date('M d', strtotime($user['created_at'])); ?></small>
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
        mobileToggleBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('show');
        });
    }
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileToggleBtn && !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });

    // Activity Chart
    var ctx = document.getElementById('activityChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Activities',
                data: <?php echo json_encode($activityCounts); ?>,
                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                borderColor: '#4CAF50',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#4CAF50',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function(context) { return context.parsed.y + ' activities'; } } }
            },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Status Chart
    var ctx2 = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Pending', 'Rejected'],
            datasets: [{
                data: [<?php echo $stats['approved_activities']; ?>, <?php echo $stats['pending_activities']; ?>, <?php echo $stats['rejected_activities']; ?>],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, usePointStyle: true, boxWidth: 8 } }
            }
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>