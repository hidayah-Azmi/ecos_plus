<?php
$page_title = 'Campus Events';
$current_page = 'events';
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Events - Ecos+</title>
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
        .filter-bar { background: white; border-radius: 20px; padding: 15px 20px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .event-card { background: white; border-radius: 20px; overflow: hidden; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s, box-shadow 0.3s; }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .event-banner { width: 100%; height: 180px; object-fit: cover; }
        .event-banner-placeholder { width: 100%; height: 180px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; }
        .event-content { padding: 20px; }
        .event-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: #1a1a2e; }
        .event-meta { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; font-size: 13px; color: #666; }
        .event-meta i { width: 18px; color: #4CAF50; }
        .event-description { font-size: 14px; color: #555; line-height: 1.5; margin-bottom: 15px; }
        .event-stats { display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid #f0f0f0; }
        .participant-count { font-size: 13px; color: #666; }
        .participant-count .count { font-weight: 700; color: #4CAF50; }
        .points-badge { background: #e8f5e9; padding: 5px 12px; border-radius: 30px; font-size: 13px; font-weight: 600; color: #4CAF50; }
        .btn-join { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; border: none; padding: 8px 20px; border-radius: 30px; font-weight: 600; font-size: 13px; transition: all 0.3s; }
        .btn-join:hover { transform: scale(1.02); box-shadow: 0 5px 15px rgba(76,175,80,0.3); }
        .btn-joined { background: #e0e0e0; color: #666; cursor: default; }
        .btn-full { background: #dc3545; color: white; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; margin-left: 10px; }
        .badge-upcoming { background: #d4edda; color: #155724; }
        .badge-ongoing { background: #cfe2ff; color: #084298; }
        .badge-ended { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; }
        @media (max-width: 768px) { .container-custom { padding: 15px; } .event-title { font-size: 18px; } }
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
                <li><a href="events.php" class="nav-link-custom active"><i class="fas fa-calendar"></i> Events</a></li>
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
        <li><a href="events.php" class="active"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <?php if (isAdmin()): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert"><i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo $message; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="text-center mb-4"><h3><i class="fas fa-calendar-alt text-success"></i> Campus Events</h3><p class="text-muted">Join events, earn points, and be part of our green community!</p></div>

    <div class="filter-bar"><div class="row g-3 align-items-center"><div class="col-md-4"><form method="GET" action="" class="d-flex gap-2"><input type="text" class="form-control" name="search" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 40px;"><button type="submit" class="btn btn-success" style="border-radius: 40px;"><i class="fas fa-search"></i></button></form></div><div class="col-md-4"><div class="btn-group w-100" role="group"><a href="?filter=upcoming" class="btn btn-outline-success <?php echo $filter == 'upcoming' ? 'active' : ''; ?>" style="border-radius: 40px 0 0 40px;"><i class="fas fa-calendar-week"></i> Upcoming</a><a href="?filter=past" class="btn btn-outline-success <?php echo $filter == 'past' ? 'active' : ''; ?>" style="border-radius: 0 40px 40px 0;"><i class="fas fa-history"></i> Past</a></div></div><div class="col-md-4 text-md-end"><small class="text-muted"><i class="fas fa-users"></i> Join events to earn eco-points!</small></div></div></div>

    <?php if ($events->num_rows > 0): ?>
        <?php while($event = $events->fetch_assoc()): $isJoined = $event['user_joined'] > 0; $isFull = $event['participant_count'] >= $event['max_participants']; $isEnded = strtotime($event['end_date']) < time(); $isOngoing = strtotime($event['start_date']) <= time() && strtotime($event['end_date']) >= time(); $statusClass = $isEnded ? 'badge-ended' : ($isOngoing ? 'badge-ongoing' : 'badge-upcoming'); $statusText = $isEnded ? 'Ended' : ($isOngoing ? 'Ongoing' : 'Upcoming'); ?>
            <div class="event-card"><?php if ($event['banner_image'] && file_exists($event['banner_image'])): ?><img src="<?php echo $event['banner_image']; ?>" class="event-banner" alt="<?php echo htmlspecialchars($event['title']); ?>"><?php else: ?><div class="event-banner-placeholder"><i class="fas fa-leaf"></i></div><?php endif; ?><div class="event-content"><div class="d-flex justify-content-between align-items-start flex-wrap"><h4 class="event-title"><?php echo htmlspecialchars($event['title']); ?><span class="badge-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></h4><div class="points-badge"><i class="fas fa-star"></i> +<?php echo $event['points_reward']; ?> pts</div></div><div class="event-meta"><span><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y', strtotime($event['start_date'])); ?></span><span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($event['start_date'])); ?> - <?php echo date('g:i A', strtotime($event['end_date'])); ?></span><?php if ($event['location']): ?><span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></span><?php endif; ?></div><div class="event-description"><?php echo nl2br(htmlspecialchars(substr($event['description'], 0, 150))); ?><?php if (strlen($event['description']) > 150): ?>...<?php endif; ?></div><div class="event-stats"><div class="participant-count"><i class="fas fa-users"></i> <span class="count"><?php echo $event['participant_count']; ?></span> / <?php echo $event['max_participants']; ?> participants</div><?php if ($isEnded): ?><button class="btn-join btn-joined" disabled><i class="fas fa-check-circle"></i> Event Ended</button><?php elseif ($isJoined): ?><button class="btn-join btn-joined" disabled><i class="fas fa-check"></i> Joined</button><?php elseif ($isFull): ?><button class="btn-join btn-full" disabled><i class="fas fa-ban"></i> Full</button><?php else: ?><a href="?join=<?php echo $event['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>" class="btn-join" onclick="return confirm('Join this event? You will earn <?php echo $event['points_reward']; ?> points!')"><i class="fas fa-calendar-plus"></i> Join Event</a><?php endif; ?></div></div></div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-calendar-times" style="font-size: 64px; color: #ccc;"></i><h5 class="mt-3 text-muted">No events found</h5><p class="text-muted">Check back later for exciting campus events!</p></div>
    <?php endif; ?>
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