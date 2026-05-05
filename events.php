<?php
$page_title = 'Campus Events';
$current_page = 'events';
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

// Handle join event
if (isset($_GET['join']) && is_numeric($_GET['join'])) {
    $event_id = intval($_GET['join']);
    
    $checkSql = "SELECT id FROM event_participants WHERE event_id = ? AND user_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $alreadyJoined = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if (!$alreadyJoined) {
        $eventSql = "SELECT max_participants, points_reward, (SELECT COUNT(*) FROM event_participants WHERE event_id = ?) as current_participants FROM campus_events WHERE id = ? AND is_active = 1 AND end_date > NOW()";
        $stmt = $conn->prepare($eventSql);
        $stmt->bind_param("ii", $event_id, $event_id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($event && $event['current_participants'] < $event['max_participants']) {
            $joinSql = "INSERT INTO event_participants (event_id, user_id) VALUES (?, ?)";
            $stmt = $conn->prepare($joinSql);
            $stmt->bind_param("ii", $event_id, $user_id);
            if ($stmt->execute()) {
                $pointsSql = "UPDATE users SET points = points + ? WHERE id = ?";
                $pointsStmt = $conn->prepare($pointsSql);
                $pointsStmt->bind_param("ii", $event['points_reward'], $user_id);
                $pointsStmt->execute();
                $pointsStmt->close();
                $_SESSION['user_points'] = ($_SESSION['user_points'] ?? 0) + $event['points_reward'];
                $message = "Successfully joined the event! You earned " . $event['points_reward'] . " points! 🎉";
                $messageType = "success";
            }
            $stmt->close();
        } else {
            $message = "Sorry, this event is full!";
            $messageType = "danger";
        }
    } else {
        $message = "You have already joined this event!";
        $messageType = "warning";
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT e.*, (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) as participant_count, (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id AND user_id = $user_id) as user_joined FROM campus_events e WHERE e.is_active = 1";
if ($filter == 'upcoming') { $sql .= " AND e.end_date > NOW()"; } elseif ($filter == 'past') { $sql .= " AND e.end_date < NOW()"; }
if (!empty($search)) { $sql .= " AND (e.title LIKE '%$search%' OR e.location LIKE '%$search%')"; }
$sql .= " ORDER BY e.start_date ASC";
$events = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Campus Events - Ecos+</title>
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
        
        /* Navbar - SAME AS DASHBOARD */
        .navbar-custom {
            background: rgba(10, 46, 26, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(76,175,80,0.3);
        }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-wrapper { display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 30px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; flex-shrink: 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #6B8E23, #4CAF50); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .logo-icon img { width: 30px; height: 30px; object-fit: cover; border-radius: 8px; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #8BC34A; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; flex: 1; justify-content: center; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom i { font-size: 16px; }
        .nav-link-custom:hover { background: rgba(107, 142, 35, 0.3); color: #8BC34A; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        
        /* Notification Bell */
        .notification-wrapper { position: relative; margin-right: 10px; }
        .notification-bell { background: rgba(255,255,255,0.1); border: none; color: white; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; transition: all 0.3s; font-size: 18px; }
        .notification-bell:hover { background: rgba(107, 142, 35, 0.5); transform: scale(1.05); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #f44336; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 50%; min-width: 18px; text-align: center; }
        
        /* User Dropdown */
        .navbar-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
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
        
        /* Mobile */
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #0a2e1a; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(107, 142, 35, 0.3); color: #8BC34A; }
        .mobile-nav a i { width: 24px; }
        
        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .mobile-toggle { display: block; } 
            .navbar-right { margin-left: auto; } 
            .user-info { display: none; } 
            .navbar-container { padding: 0 15px; }
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        /* Container */
        .container-custom { max-width: 1200px; margin: 0 auto; padding: 30px 20px; position: relative; z-index: 1; }
        
        /* Page Header */
        .page-header { text-align: center; margin-bottom: 30px; }
        .page-header h2 { font-size: 28px; font-weight: 700; color: #8BC34A; margin-bottom: 8px; }
        .page-header p { color: rgba(255,255,255,0.7); font-size: 14px; }
        
        /* Filter Bar */
        .filter-bar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .search-input {
            border-radius: 12px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
        }
        .search-input:focus {
            border-color: #6B8E23;
            outline: none;
            box-shadow: 0 0 0 3px rgba(107,142,35,0.1);
        }
        .filter-buttons { display: flex; gap: 10px; }
        .filter-btn {
            padding: 8px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #6B8E23;
            background: white;
            color: #6B8E23;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            border-color: #6B8E23;
            color: white;
        }
        .filter-btn:hover:not(.active) {
            background: #e8f5e9;
            transform: translateY(-2px);
        }
        
        /* Events Grid - Professional List View */
        .events-grid { display: flex; flex-direction: column; gap: 25px; }
        .event-item {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            flex-wrap: wrap;
        }
        .event-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .event-image {
            width: 300px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            position: relative;
            overflow: hidden;
        }
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .event-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: white;
        }
        .event-details {
            flex: 1;
            padding: 25px;
        }
        .event-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 10px;
        }
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #666;
        }
        .event-meta i {
            width: 18px;
            color: #6B8E23;
            margin-right: 5px;
        }
        .event-description {
            font-size: 14px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .event-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        .event-stats {
            display: flex;
            gap: 20px;
        }
        .stat {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }
        .stat i { color: #6B8E23; }
        .stat .number { font-weight: 700; color: #1a1a2e; }
        .points-badge {
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            color: white;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-join {
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            color: white;
            border: none;
            padding: 8px 28px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-join:hover { transform: scale(1.02); box-shadow: 0 5px 15px rgba(107,142,35,0.4); }
        .btn-joined { background: #ccc; color: #666; cursor: default; transform: none; }
        .btn-full { background: #dc3545; color: white; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status-upcoming { background: #d4edda; color: #155724; }
        .status-ongoing { background: #cfe2ff; color: #084298; }
        .status-ended { background: #f8d7da; color: #721c24; }
        
        /* Empty State */
        .empty-state {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 60px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .empty-state i { font-size: 64px; color: #ccc; margin-bottom: 20px; }
        
        /* Alert */
        .alert { border-radius: 16px; padding: 15px 20px; margin-bottom: 25px; border: none; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-danger { background: #fee2e2; color: #dc2626; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        
        @media (max-width: 768px) {
            .container-custom { padding: 20px 15px; }
            .event-image { width: 100%; height: 180px; }
            .event-details { padding: 18px; }
            .event-title { font-size: 18px; }
            .event-meta { gap: 12px; font-size: 12px; }
            .filter-buttons { margin-top: 10px; }
            .filter-btn { padding: 6px 16px; font-size: 12px; }
        }
    </style>
</head>
<body>

<!-- Animated Leaves -->
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-seedling"></i></div>
<div class="leaf-bg"><i class="fas fa-tree"></i></div>
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>

<!-- Navigation Bar - SAME AS DASHBOARD -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div class="navbar-wrapper">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon"><img src="assets/logo/12.png" alt="Logo"></div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Tips</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom active"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div class="navbar-right">

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
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php" class="active"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-calendar-alt"></i> Campus Events</h2>
        <p>Join sustainability events, earn points, and make a difference</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="row align-items-center g-3">
            <div class="col-md-5">
                <form method="GET" action="" class="d-flex gap-2">
                    <input type="text" class="form-control search-input" name="search" placeholder="🔍 Search events..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-success" style="background: linear-gradient(135deg, #6B8E23, #4CAF50); border: none; border-radius: 12px; padding: 10px 20px;"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="col-md-4">
                <div class="filter-buttons">
                    <a href="?filter=upcoming" class="filter-btn <?php echo $filter == 'upcoming' ? 'active' : ''; ?>"><i class="fas fa-calendar-week"></i> Upcoming</a>
                    <a href="?filter=past" class="filter-btn <?php echo $filter == 'past' ? 'active' : ''; ?>"><i class="fas fa-history"></i> Past Events</a>
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <small class="text-white-50"><i class="fas fa-gift"></i> Join events = earn points!</small>
            </div>
        </div>
    </div>

    <!-- Events List -->
    <?php if ($events->num_rows > 0): ?>
        <div class="events-grid">
            <?php while($event = $events->fetch_assoc()): 
                $isJoined = $event['user_joined'] > 0;
                $isFull = $event['participant_count'] >= $event['max_participants'];
                $isEnded = strtotime($event['end_date']) < time();
                $isOngoing = strtotime($event['start_date']) <= time() && strtotime($event['end_date']) >= time();
                $statusClass = $isEnded ? 'status-ended' : ($isOngoing ? 'status-ongoing' : 'status-upcoming');
                $statusText = $isEnded ? 'Ended' : ($isOngoing ? 'Ongoing' : 'Upcoming');
            ?>
                <div class="event-item">
                    <div class="event-image">
                        <?php if ($event['banner_image'] && file_exists($event['banner_image'])): ?>
                            <img src="<?php echo $event['banner_image']; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                        <?php else: ?>
                            <div class="event-image-placeholder"><i class="fas fa-leaf"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="event-details">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="points-badge"><i class="fas fa-star"></i> +<?php echo $event['points_reward']; ?> points</div>
                        </div>
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y', strtotime($event['start_date'])); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($event['start_date'])); ?></span>
                            <?php if ($event['location']): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                            <?php endif; ?>
                            <span><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></span>
                        </div>
                        <div class="event-description">
                            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                        </div>
                        <div class="event-footer">
                            <div class="event-stats">
                                <div class="stat"><i class="fas fa-users"></i> <span class="number"><?php echo $event['participant_count']; ?></span> / <?php echo $event['max_participants']; ?> joined</div>
                            </div>
                            <?php if ($isEnded): ?>
                                <button class="btn-join btn-joined" disabled><i class="fas fa-check-circle"></i> Ended</button>
                            <?php elseif ($isJoined): ?>
                                <button class="btn-join btn-joined" disabled><i class="fas fa-check"></i> Joined</button>
                            <?php elseif ($isFull): ?>
                                <button class="btn-join btn-full" disabled><i class="fas fa-ban"></i> Full</button>
                            <?php else: ?>
                                <a href="?join=<?php echo $event['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-join" onclick="return confirm('Join this event? You will earn <?php echo $event['points_reward']; ?> points! 🌟')">
                                    <i class="fas fa-calendar-plus"></i> Join Event
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h5>No events found</h5>
            <p class="text-muted mb-3">Check back later for exciting campus events!</p>
            <a href="?filter=upcoming" class="btn btn-success" style="background: linear-gradient(135deg, #6B8E23, #4CAF50); border: none; border-radius: 30px; padding: 10px 30px;">View Upcoming Events</a>
        </div>
    <?php endif; ?>
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