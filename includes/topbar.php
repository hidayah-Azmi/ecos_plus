<?php
// Get user data for topbar
$topbar_user = getCurrentUser();
$topbar_initial = $topbar_user ? strtoupper(substr($topbar_user['full_name'], 0, 1)) : 'U';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Ecos+'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        /* ============================================
           TOP BAR STYLES - MODERN & PROFESSIONAL
        ============================================ */
        .topbar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .topbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 25px;
        }

        /* Logo Section */
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }
        .logo-text span {
            color: #4CAF50;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .nav-item {
            position: relative;
        }
        .nav-link {
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
        .nav-link i {
            font-size: 16px;
        }
        .nav-link:hover {
            background: rgba(76, 175, 80, 0.15);
            color: #4CAF50;
            transform: translateY(-2px);
        }
        .nav-link.active {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 4px 10px rgba(76,175,80,0.3);
        }
        .nav-link.active i {
            color: white;
        }

        /* User Dropdown */
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
        .user-trigger:hover {
            background: rgba(255,255,255,0.15);
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            color: white;
        }
        .user-info {
            display: flex;
            flex-direction: column;
        }
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: white;
        }
        .user-points {
            font-size: 11px;
            color: #FFD700;
        }
        .user-points i {
            font-size: 10px;
            margin-right: 3px;
        }
        .dropdown-arrow {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
            transition: transform 0.3s;
        }
        .user-dropdown:hover .dropdown-arrow {
            transform: rotate(180deg);
        }
        .dropdown-menu-custom {
            position: absolute;
            top: 55px;
            right: 0;
            width: 220px;
            background: white;
            border-radius: 16px;
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
        .dropdown-item-custom:last-child {
            border-bottom: none;
        }
        .dropdown-item-custom:hover {
            background: #f8f9fa;
            color: #4CAF50;
        }
        .dropdown-item-custom i {
            width: 20px;
            color: #4CAF50;
        }
        .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
        }

        /* Mobile Menu */
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
        .mobile-menu.show {
            transform: translateX(0);
        }
        .mobile-nav {
            list-style: none;
            padding: 0;
        }
        .mobile-nav li {
            margin-bottom: 5px;
        }
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
        .mobile-nav a i {
            width: 24px;
        }

        /* Content Area */
        .content-area {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .nav-menu {
                display: none;
            }
            .mobile-toggle {
                display: block;
            }
            .user-info {
                display: none;
            }
            .user-trigger {
                padding: 6px 12px;
            }
            .topbar-container {
                padding: 0 15px;
            }
        }

        @media (max-width: 576px) {
            .logo-text {
                display: none;
            }
            .content-area {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
    <div class="topbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
            <!-- Logo -->
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>

            <!-- Desktop Navigation -->
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="nav-item"><a href="activity.php" class="nav-link <?php echo ($current_page == 'activity') ? 'active' : ''; ?>"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li class="nav-item"><a href="map.php" class="nav-link <?php echo ($current_page == 'map') ? 'active' : ''; ?>"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li class="nav-item"><a href="rewards.php" class="nav-link <?php echo ($current_page == 'rewards') ? 'active' : ''; ?>"><i class="fas fa-gift"></i> Rewards</a></li>
                <li class="nav-item"><a href="leaderboard.php" class="nav-link <?php echo ($current_page == 'leaderboard') ? 'active' : ''; ?>"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li class="nav-item"><a href="ai-insights.php" class="nav-link <?php echo ($current_page == 'ai-insights') ? 'active' : ''; ?>"><i class="fas fa-robot"></i> AI Insights</a></li>
                <li class="nav-item"><a href="community.php" class="nav-link <?php echo ($current_page == 'community') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Community</a></li>
                <?php if (isAdmin()): ?>
                <li class="nav-item"><a href="admin/dashboard.php" class="nav-link <?php echo ($current_page == 'admin') ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>

            <!-- User Dropdown -->
            <div class="user-dropdown">
                <div class="user-trigger">
                    <div class="user-avatar">
                        <?php echo $topbar_initial; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($topbar_user['full_name'] ?? 'User'); ?></span>
                        <span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($topbar_user['points'] ?? 0); ?> pts</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu-custom">
                    <a href="profile.php" class="dropdown-item-custom">
                        <i class="fas fa-user-circle"></i> My Profile
                    </a>
                    <a href="rewards.php" class="dropdown-item-custom">
                        <i class="fas fa-gift"></i> My Rewards
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item-custom">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Mobile Toggle -->
            <button class="mobile-toggle" id="mobileToggleBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>
</div>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="activity.php" class="<?php echo ($current_page == 'activity') ? 'active' : ''; ?>"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php" class="<?php echo ($current_page == 'map') ? 'active' : ''; ?>"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="rewards.php" class="<?php echo ($current_page == 'rewards') ? 'active' : ''; ?>"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="leaderboard.php" class="<?php echo ($current_page == 'leaderboard') ? 'active' : ''; ?>"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php" class="<?php echo ($current_page == 'ai-insights') ? 'active' : ''; ?>"><i class="fas fa-robot"></i> AI Insights</a></li>
        <li><a href="community.php" class="<?php echo ($current_page == 'community') ? 'active' : ''; ?>"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <?php if (isAdmin()): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="content-area">