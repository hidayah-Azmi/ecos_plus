<?php
// Get user data for sidebar profile - WITHOUT closing connection
$conn_sidebar = getConnection();
$user_id_sidebar = $_SESSION['user_id'];
$user_query = "SELECT full_name, username, points, profile_image FROM users WHERE id = ?";
$stmt = $conn_sidebar->prepare($user_query);
$stmt->bind_param("i", $user_id_sidebar);
$stmt->execute();
$sidebar_user = $stmt->get_result()->fetch_assoc();
$stmt->close();
// DO NOT close connection here!

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }

        .sidebar.collapsed {
            left: -280px;
        }

        /* Profile Section in Sidebar */
        .sidebar-profile {
            text-align: center;
            padding: 30px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .sidebar-profile:hover {
            background: rgba(255,255,255,0.05);
        }

        .sidebar-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            overflow: hidden;
            transition: transform 0.3s;
        }
        .sidebar-avatar:hover {
            transform: scale(1.05);
        }
        .sidebar-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .sidebar-username {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 10px;
        }

        .sidebar-points {
            background: rgba(76, 175, 80, 0.2);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            border: 1px solid #4CAF50;
        }

        /* Navigation Menu */
        .sidebar-nav {
            list-style: none;
            padding: 0;
        }
        .sidebar-nav li {
            margin: 5px 0;
        }
        .sidebar-nav li a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            gap: 15px;
        }
        .sidebar-nav li a i {
            width: 25px;
            font-size: 18px;
        }
        .sidebar-nav li a:hover {
            background: rgba(76, 175, 80, 0.2);
            color: white;
            padding-left: 30px;
        }
        .sidebar-nav li.active a {
            background: linear-gradient(90deg, #4CAF50 0%, transparent 100%);
            color: white;
            border-left: 3px solid #4CAF50;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
            min-height: 100vh;
        }
        .main-content.expanded {
            margin-left: 0;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .toggle-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #4CAF50;
            transition: all 0.3s;
        }
        .toggle-btn:hover {
            transform: scale(1.1);
        }
        .page-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        /* Content Container */
        .content-container {
            padding: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 260px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.collapsed-mobile {
                left: -260px;
            }
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            .overlay.active {
                display: block;
            }
        }

        /* Scrollbar */
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
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Profile Section -->
    <div class="sidebar-profile" onclick="location.href='profile.php'">
        <div class="sidebar-avatar">
            <?php if ($sidebar_avatar): ?>
                <img src="<?php echo $sidebar_avatar; ?>" alt="Profile">
            <?php else: ?>
                <?php echo $sidebar_initial; ?>
            <?php endif; ?>
        </div>
        <div class="sidebar-name"><?php echo htmlspecialchars($sidebar_user['full_name']); ?></div>
        <div class="sidebar-username">@<?php echo htmlspecialchars($sidebar_user['username']); ?></div>
        <div class="sidebar-points">
            <i class="fas fa-star"></i> <?php echo number_format($sidebar_user['points']); ?> pts
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-nav">
        <li class="<?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        <li class="<?php echo ($current_page == 'activity') ? 'active' : ''; ?>">
            <a href="activity.php"><i class="fas fa-recycle"></i> Recycle</a>
        </li>
        <li class="<?php echo ($current_page == 'map') ? 'active' : ''; ?>">
            <a href="map.php"><i class="fas fa-map-marker-alt"></i> Recycling Map</a>
        </li>
        <li class="<?php echo ($current_page == 'rewards') ? 'active' : ''; ?>">
            <a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a>
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

<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="top-bar">
        <button class="toggle-btn" id="toggleSidebarBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-title"><?php echo $page_title ?? 'Ecos+'; ?></div>
        <div></div>
    </div>
    <div class="content-container">