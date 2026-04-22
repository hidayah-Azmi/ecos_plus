<?php
// Get user data for sidebar
$conn_sidebar = getConnection();
$user_id_sidebar = $_SESSION['user_id'];
$user_query = "SELECT full_name, username, points, profile_image FROM users WHERE id = ?";
$stmt = $conn_sidebar->prepare($user_query);
$stmt->bind_param("i", $user_id_sidebar);
$stmt->execute();
$sidebar_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$sidebar_initial = strtoupper(substr($sidebar_user['full_name'], 0, 1));
$sidebar_avatar = (!empty($sidebar_user['profile_image']) && file_exists($sidebar_user['profile_image'])) ? $sidebar_user['profile_image'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Ecos+'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f2f5;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        /* ============================================
           SIDEBAR STYLES - CANTIK & MODERN
        ============================================ */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: linear-gradient(180deg, #1a1a2e 0%, #0f0f23 100%);
            backdrop-filter: blur(10px);
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #4CAF50;
            border-radius: 5px;
        }

        /* Logo Section */
        .sidebar-logo {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 20px;
        }
        .sidebar-logo h3 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        .sidebar-logo p {
            font-size: 11px;
            opacity: 0.5;
            margin-top: 5px;
        }

        /* Profile Section */
        .sidebar-profile {
            text-align: center;
            padding: 0 20px 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .sidebar-profile:hover {
            background: rgba(255,255,255,0.03);
        }
        .profile-avatar {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            overflow: hidden;
            transition: all 0.3s;
            border: 3px solid rgba(255,255,255,0.2);
        }
        .profile-avatar:hover {
            transform: scale(1.05);
            border-color: #4CAF50;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .profile-username {
            font-size: 12px;
            opacity: 0.6;
            margin-bottom: 10px;
        }
        .profile-points {
            background: rgba(76, 175, 80, 0.15);
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .profile-points i {
            margin-right: 5px;
            color: #FFD700;
        }

        /* Navigation Menu */
        .sidebar-nav {
            list-style: none;
            padding: 0;
        }
        .sidebar-nav li {
            margin: 5px 12px;
        }
        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            gap: 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar-nav li a i {
            width: 24px;
            font-size: 18px;
            text-align: center;
        }
        .sidebar-nav li a:hover {
            background: rgba(76, 175, 80, 0.15);
            color: white;
            transform: translateX(5px);
        }
        .sidebar-nav li.active a {
            background: linear-gradient(90deg, #4CAF50, transparent);
            color: white;
            box-shadow: 0 2px 8px rgba(76,175,80,0.2);
        }
        .sidebar-nav li.active a i {
            color: #4CAF50;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
        }
        .toggle-btn {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #4CAF50;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-btn:hover {
            background: #f0f0f0;
            transform: scale(1.05);
        }
        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
        }
        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f5f7fa;
            padding: 8px 18px;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .user-badge:hover {
            background: #e8f5e9;
        }
        .user-badge .badge-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #4CAF50;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .user-badge .badge-name {
            font-weight: 500;
            color: #333;
        }
        .user-badge .badge-points {
            background: #4CAF50;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Content Container */
        .content-container {
            padding: 25px 30px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
                z-index: 1001;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .top-bar {
                padding: 12px 20px;
            }
            .content-container {
                padding: 15px;
            }
            .user-badge .badge-name {
                display: none;
            }
            .user-badge .badge-points {
                display: none;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <h3><i class="fas fa-leaf"></i> Ecos+</h3>
        <p>Green Lifestyle Tracker</p>
    </div>

    <!-- Profile Section -->
    <div class="sidebar-profile" onclick="location.href='profile.php'">
        <div class="profile-avatar">
            <?php if ($sidebar_avatar): ?>
                <img src="<?php echo $sidebar_avatar; ?>" alt="Profile">
            <?php else: ?>
                <?php echo $sidebar_initial; ?>
            <?php endif; ?>
        </div>
        <div class="profile-name"><?php echo htmlspecialchars($sidebar_user['full_name']); ?></div>
        <div class="profile-username">@<?php echo htmlspecialchars($sidebar_user['username']); ?></div>
        <div class="profile-points">
            <i class="fas fa-star"></i> <?php echo number_format($sidebar_user['points']); ?> points
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-nav">
        <li class="<?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        <li class="<?php echo ($current_page == 'activity') ? 'active' : ''; ?>">
            <a href="activity.php"><i class="fas fa-recycle"></i> Recycle Activity</a>
        </li>
        <li class="<?php echo ($current_page == 'map') ? 'active' : ''; ?>">
            <a href="map.php"><i class="fas fa-map-marker-alt"></i> Recycling Map</a>
        </li>
        <li class="<?php echo ($current_page == 'rewards') ? 'active' : ''; ?>">
            <a href="rewards.php"><i class="fas fa-gift"></i> Rewards & Badges</a>
        </li>
        <li class="<?php echo ($current_page == 'leaderboard') ? 'active' : ''; ?>">
            <a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        </li>
        <li class="<?php echo ($current_page == 'ai-insights') ? 'active' : ''; ?>">
            <a href="ai-insights.php"><i class="fas fa-robot"></i> AI Insights</a>
        </li>
        <li class="<?php echo ($current_page == 'community') ? 'active' : ''; ?>">
            <a href="community.php"><i class="fas fa-users"></i> Community</a>
        </li>
        <li class="<?php echo ($current_page == 'profile') ? 'active' : ''; ?>">
            <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
        </li>
        <?php if (isAdmin()): ?>
        <li class="<?php echo ($current_page == 'admin') ? 'active' : ''; ?>">
            <a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a>
        </li>
        <?php endif; ?>
        <li>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="top-bar">
        <button class="toggle-btn" id="toggleSidebarBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-title"><?php echo $page_title ?? 'Ecos+'; ?></div>
        <div class="top-bar-right">
            <div class="user-badge" onclick="location.href='profile.php'">
                <div class="badge-avatar">
                    <?php if ($sidebar_avatar): ?>
                        <img src="<?php echo $sidebar_avatar; ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <?php echo $sidebar_initial; ?>
                    <?php endif; ?>
                </div>
                <span class="badge-name"><?php echo htmlspecialchars($sidebar_user['full_name']); ?></span>
                <span class="badge-points"><i class="fas fa-star"></i> <?php echo number_format($sidebar_user['points']); ?></span>
            </div>
        </div>
    </div>
    <div class="content-container">