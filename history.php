<?php
$page_title = 'Activity History';
$current_page = 'history';
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Build query
$query = "SELECT * FROM activities WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($type_filter !== 'all') {
    $query .= " AND activity_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$activities = $stmt->get_result();

// Get unique activity types for filter
$typeQuery = "SELECT DISTINCT activity_type FROM activities WHERE user_id = ?";
$typeStmt = $conn->prepare($typeQuery);
$typeStmt->bind_param("i", $user_id);
$typeStmt->execute();
$activityTypes = $typeStmt->get_result();

// Get statistics summary
$summaryQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(points_earned) as total_points
                FROM activities WHERE user_id = ?";
$summaryStmt = $conn->prepare($summaryQuery);
$summaryStmt->bind_param("i", $user_id);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Activity History - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }
        
        /* Floating Elements */
        .floating-leaf {
            position: fixed;
            opacity: 0.05;
            pointer-events: none;
            z-index: 0;
            font-size: 40px;
            animation: float 8s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        .floating-leaf:nth-child(1) { top: 10%; left: 5%; animation-delay: 0s; }
        .floating-leaf:nth-child(2) { top: 70%; right: 8%; animation-delay: 2s; }
        .floating-leaf:nth-child(3) { top: 40%; left: 85%; animation-delay: 4s; }
        .floating-leaf:nth-child(4) { bottom: 15%; left: 15%; animation-delay: 6s; }

        /* Navbar Styles (same as dashboard) */
        .navbar-custom {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(76,175,80,0.2);
        }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #4CAF50, #8BC34A); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #4CAF50; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom:hover { background: rgba(76, 175, 80, 0.15); color: #4CAF50; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
        .user-dropdown { position: relative; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(255,255,255,0.08); border-radius: 40px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-menu-custom { position: absolute; top: 55px; right: 0; width: 220px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
        .user-dropdown:hover .dropdown-menu-custom { opacity: 1; visibility: visible; top: 60px; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #4CAF50; }
        .dropdown-item-custom i { width: 20px; color: #4CAF50; }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #1a1a2e; z-index: 999; padding: 20px; transform: translateX(100%); transition: transform 0.3s ease; overflow-y: auto; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        
        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .mobile-toggle { display: block; } 
            .user-info { display: none; } 
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Summary Cards */
        .summary-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .summary-number { font-size: 28px; font-weight: 700; color: #4CAF50; }
        
        /* Filter Bar */
        .filter-bar {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 25px;
            border: 1px solid #ddd;
            background: white;
            transition: all 0.3s;
            margin: 3px;
        }
        .filter-btn.active, .filter-btn:hover {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        /* Activity Card */
        .activity-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
        }
        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .activity-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .activity-content { padding: 15px; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        
        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }
        .image-modal.show { display: flex; }
        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }
        .image-modal .close-btn {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
        
        /* Gallery Grid View */
        .gallery-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .gallery-item {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        .gallery-item:hover { transform: scale(1.03); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .gallery-info { padding: 10px; text-align: center; }
        .gallery-info small { font-size: 10px; }
        
        .view-toggle {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 15px;
        }
        .view-toggle button {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .view-toggle button.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        @media (max-width: 768px) {
            .container-custom { padding: 15px; }
            .summary-number { font-size: 20px; }
            .gallery-view { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
            .activity-image { height: 180px; }
        }
        
        @media (max-width: 480px) {
            .filter-bar .btn-group { flex-direction: column; }
            .filter-btn { width: 100%; margin: 3px 0; }
            .gallery-view { grid-template-columns: repeat(2, 1fr); }
            .activity-image { height: 150px; }
        }
    </style>
</head>
<body>

<div class="floating-leaf"><i class="fas fa-leaf"></i></div>
<div class="floating-leaf"><i class="fas fa-recycle"></i></div>
<div class="floating-leaf"><i class="fas fa-seedling"></i></div>
<div class="floating-leaf"><i class="fas fa-tree"></i></div>

<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon"><img src="assets/logo/12.png" alt="Logo" style="height:30px;"></div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom active"><i class="fas fa-home"></i> Dashboard</a></li>
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
            <div class="user-dropdown">
                <div class="user-trigger">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($user['points']); ?> pts</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu-custom">
                    <a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a>
                    <a href="history.php" class="dropdown-item-custom"><i class="fas fa-history"></i> Activity History</a>
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
        <li><a href="history.php" class="active"><i class="fas fa-history"></i> History</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h2 class="text-white mb-1"><i class="fas fa-history"></i> Activity History</h2>
            <p class="text-white-50">View all your recycling activities and achievements</p>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="summary-card">
                <i class="fas fa-recycle fa-2x text-success"></i>
                <div class="summary-number"><?php echo $summary['total'] ?? 0; ?></div>
                <div>Total Activities</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card">
                <i class="fas fa-check-circle fa-2x text-success"></i>
                <div class="summary-number text-success"><?php echo $summary['approved'] ?? 0; ?></div>
                <div>Approved</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card">
                <i class="fas fa-clock fa-2x text-warning"></i>
                <div class="summary-number text-warning"><?php echo $summary['pending'] ?? 0; ?></div>
                <div>Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="summary-card">
                <i class="fas fa-star fa-2x text-info"></i>
                <div class="summary-number text-info"><?php echo $summary['total_points'] ?? 0; ?></div>
                <div>Total Points</div>
            </div>
        </div>
    </div>
    
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="row align-items-center">
            <div class="col-md-3 mb-2 mb-md-0">
                <strong><i class="fas fa-filter"></i> Filter by Status:</strong>
            </div>
            <div class="col-md-9">
                <div class="btn-group flex-wrap">
                    <a href="?status=all&type=<?php echo $type_filter; ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?status=approved&type=<?php echo $type_filter; ?>" class="filter-btn <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
                    <a href="?status=pending&type=<?php echo $type_filter; ?>" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?status=rejected&type=<?php echo $type_filter; ?>" class="filter-btn <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>">Rejected</a>
                </div>
            </div>
        </div>
        <?php if($activityTypes->num_rows > 0): ?>
        <div class="row mt-3 align-items-center">
            <div class="col-md-3 mb-2 mb-md-0">
                <strong><i class="fas fa-tag"></i> Filter by Type:</strong>
            </div>
            <div class="col-md-9">
                <div class="btn-group flex-wrap">
                    <a href="?status=<?php echo $status_filter; ?>&type=all" class="filter-btn <?php echo $type_filter == 'all' ? 'active' : ''; ?>">All Types</a>
                    <?php while($type = $activityTypes->fetch_assoc()): ?>
                    <a href="?status=<?php echo $status_filter; ?>&type=<?php echo urlencode($type['activity_type']); ?>" class="filter-btn <?php echo $type_filter == $type['activity_type'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($type['activity_type']); ?>
                    </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- View Toggle -->
    <div class="view-toggle">
        <button id="listViewBtn" class="active"><i class="fas fa-list"></i> List View</button>
        <button id="galleryViewBtn"><i class="fas fa-th"></i> Gallery View</button>
    </div>
    
    <!-- Activities List View -->
    <div id="listView">
        <?php if ($activities->num_rows > 0): ?>
            <?php while($activity = $activities->fetch_assoc()): ?>
                <div class="activity-card" onclick="openImageModal('<?php echo $activity['image_path']; ?>')">
                    <div class="row g-0">
                        <?php if($activity['image_path'] && file_exists($activity['image_path'])): ?>
                        <div class="col-md-3">
                            <img src="<?php echo $activity['image_path']; ?>" class="activity-image" alt="Activity image">
                        </div>
                        <div class="col-md-9">
                            <div class="activity-content">
                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($activity['activity_type']); ?></h5>
                                    <span class="status-badge status-<?php echo $activity['status']; ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                    <?php if($activity['points_earned'] > 0): ?>
                                        <small class="text-success">
                                            <i class="fas fa-star"></i> +<?php echo $activity['points_earned']; ?> points
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="col-12">
                            <div class="activity-content">
                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($activity['activity_type']); ?></h5>
                                    <span class="status-badge status-<?php echo $activity['status']; ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($activity['created_at'])); ?>
                                    </small>
                                    <?php if($activity['points_earned'] > 0): ?>
                                        <small class="text-success">
                                            <i class="fas fa-star"></i> +<?php echo $activity['points_earned']; ?> points
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5 bg-white rounded-4">
                <i class="fas fa-inbox" style="font-size: 64px; color: #ccc;"></i>
                <h4 class="mt-3 text-muted">No activities found</h4>
                <p class="text-muted">Start recycling to see your history here!</p>
                <a href="activity.php" class="btn btn-success mt-2">Log Your First Activity</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Gallery View -->
    <div id="galleryView" style="display: none;">
        <?php 
        $activities->data_seek(0);
        $hasImages = false;
        ?>
        <div class="gallery-view">
            <?php while($activity = $activities->fetch_assoc()): ?>
                <?php if($activity['image_path'] && file_exists($activity['image_path'])): 
                    $hasImages = true;
                ?>
                    <div class="gallery-item" onclick="openImageModal('<?php echo $activity['image_path']; ?>')">
                        <img src="<?php echo $activity['image_path']; ?>" alt="Activity image">
                        <div class="gallery-info">
                            <strong><?php echo htmlspecialchars($activity['activity_type']); ?></strong><br>
                            <span class="status-badge status-<?php echo $activity['status']; ?>">
                                <?php echo ucfirst($activity['status']); ?>
                            </span><br>
                            <small class="text-muted"><?php echo date('d M Y', strtotime($activity['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
        <?php if(!$hasImages): ?>
            <div class="text-center py-5 bg-white rounded-4">
                <i class="fas fa-images" style="font-size: 64px; color: #ccc;"></i>
                <h4 class="mt-3 text-muted">No images found</h4>
                <p class="text-muted">Upload photos with your recycling activities!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal" onclick="closeImageModal()">
    <span class="close-btn">&times;</span>
    <img id="modalImage" src="" alt="Full size image">
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
    
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileToggleBtn && mobileMenu.classList.contains('show') && 
            !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });
    
    // View Toggle
    const listView = document.getElementById('listView');
    const galleryView = document.getElementById('galleryView');
    const listViewBtn = document.getElementById('listViewBtn');
    const galleryViewBtn = document.getElementById('galleryViewBtn');
    
    listViewBtn.addEventListener('click', function() {
        listView.style.display = 'block';
        galleryView.style.display = 'none';
        listViewBtn.classList.add('active');
        galleryViewBtn.classList.remove('active');
        localStorage.setItem('historyView', 'list');
    });
    
    galleryViewBtn.addEventListener('click', function() {
        listView.style.display = 'none';
        galleryView.style.display = 'block';
        galleryViewBtn.classList.add('active');
        listViewBtn.classList.remove('active');
        localStorage.setItem('historyView', 'gallery');
    });
    
    // Load saved view preference
    const savedView = localStorage.getItem('historyView');
    if (savedView === 'gallery') {
        galleryViewBtn.click();
    }
    
    // Image Modal
    function openImageModal(imagePath) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        if (imagePath && imagePath !== '') {
            modalImg.src = imagePath;
            modal.classList.add('show');
        }
    }
    
    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.classList.remove('show');
        document.getElementById('modalImage').src = '';
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>