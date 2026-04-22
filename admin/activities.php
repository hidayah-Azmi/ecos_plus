<?php
$page_title = 'Manage Activities';
$current_page = 'admin';
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

$message = '';
$messageType = '';

// Handle approve activity
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $activity_id = intval($_GET['approve']);
    
    // Get activity details
    $activityQuery = "SELECT user_id, points_earned FROM activities WHERE id = ?";
    $stmt = $conn->prepare($activityQuery);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $activity = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($activity) {
        // Update activity status
        $updateSql = "UPDATE activities SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ii", $_SESSION['user_id'], $activity_id);
        
        if ($stmt->execute()) {
            // Add points to user
            $pointsSql = "UPDATE users SET points = points + ? WHERE id = ?";
            $pointsStmt = $conn->prepare($pointsSql);
            $pointsStmt->bind_param("ii", $activity['points_earned'], $activity['user_id']);
            $pointsStmt->execute();
            $pointsStmt->close();
            
            // Check and award badges
            require_once '../includes/auth.php';
            checkAndAwardBadges($activity['user_id']);
            
            $message = "Activity approved and " . $activity['points_earned'] . " points awarded!";
            $messageType = "success";
        } else {
            $message = "Failed to approve activity.";
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Handle reject activity
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $activity_id = intval($_GET['reject']);
    
    $updateSql = "UPDATE activities SET status = 'rejected', approved_at = NOW(), approved_by = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ii", $_SESSION['user_id'], $activity_id);
    
    if ($stmt->execute()) {
        $message = "Activity rejected.";
        $messageType = "warning";
    } else {
        $message = "Failed to reject activity.";
        $messageType = "danger";
    }
    $stmt->close();
}

// Handle bulk approve
if (isset($_POST['bulk_approve']) && isset($_POST['selected_ids'])) {
    $selected_ids = $_POST['selected_ids'];
    $approved_count = 0;
    
    foreach ($selected_ids as $activity_id) {
        $activityQuery = "SELECT user_id, points_earned FROM activities WHERE id = ?";
        $stmt = $conn->prepare($activityQuery);
        $stmt->bind_param("i", $activity_id);
        $stmt->execute();
        $activity = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($activity) {
            $updateSql = "UPDATE activities SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("ii", $_SESSION['user_id'], $activity_id);
            
            if ($stmt->execute()) {
                $pointsSql = "UPDATE users SET points = points + ? WHERE id = ?";
                $pointsStmt = $conn->prepare($pointsSql);
                $pointsStmt->bind_param("ii", $activity['points_earned'], $activity['user_id']);
                $pointsStmt->execute();
                $pointsStmt->close();
                $approved_count++;
            }
            $stmt->close();
        }
    }
    
    if ($approved_count > 0) {
        $message = "$approved_count activities approved successfully!";
        $messageType = "success";
    } else {
        $message = "No activities were approved.";
        $messageType = "warning";
    }
}

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT a.*, u.username, u.full_name, u.email, u.points as user_points,
        (SELECT full_name FROM users WHERE id = a.approved_by) as approved_by_name
        FROM activities a 
        INNER JOIN users u ON a.user_id = u.id 
        WHERE 1=1";

if ($statusFilter != 'all') {
    $sql .= " AND a.status = '$statusFilter'";
}
if ($typeFilter != 'all') {
    $sql .= " AND a.activity_type = '$typeFilter'";
}
if (!empty($search)) {
    $sql .= " AND (u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR a.description LIKE '%$search%')";
}

$sql .= " ORDER BY a.created_at DESC";
$activities = $conn->query($sql);

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM activities")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['approved'] = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'approved'")->fetch_assoc()['count'];
$stats['rejected'] = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'rejected'")->fetch_assoc()['count'];
$stats['total_points'] = $conn->query("SELECT SUM(points_earned) as total FROM activities WHERE status = 'approved'")->fetch_assoc()['total'] ?? 0;

// Get activity types for filter
$typesResult = $conn->query("SELECT DISTINCT activity_type FROM activities ORDER BY activity_type");
$activityTypes = [];
while($row = $typesResult->fetch_assoc()) {
    $activityTypes[] = $row['activity_type'];
}

$conn->close();

// Icons for activity types
$activityIcons = [
    'Plastic' => '🥤', 'Paper' => '📄', 'Glass' => '🥃', 
    'E-Waste' => '💻', 'Organic' => '🍎', 'Metal' => '🥫', 
    'Cardboard' => '📦', 'Textile' => '👕'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Activities - Admin | Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        /* Activities Page Styles */
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
            height: 100%;
            cursor: pointer;
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
            font-size: 35px;
            margin-bottom: 10px;
            color: #4CAF50;
        }

        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .table-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve:hover, .btn-reject:hover {
            opacity: 0.9;
            color: white;
        }

        .activity-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
        }

        .table th, .table td {
            vertical-align: middle;
            padding: 12px 15px;
        }

        .bulk-bar {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: none;
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
                    <img src="assets/images/umpsa.png" alt="Logo" style="height:25px; object-fit:cover;">
                </div>
                <div class="logo-text">Ecos<span>+</span> Admin</div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php" class="nav-link-custom"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="activities.php" class="nav-link-custom active"><i class="fas fa-recycle"></i> Activities</a></li>
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
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="activities.php" class="active"><i class="fas fa-recycle"></i> Activities</a></li>
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
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='?status=all'">
                <div class="stats-icon"><i class="fas fa-recycle"></i></div>
                <div class="stats-number"><?php echo $stats['total']; ?></div>
                <div class="stats-label">Total Activities</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='?status=pending'" style="border-left: 3px solid #ffc107;">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
                <div class="stats-label">Pending Approval</div>
                <small class="text-warning">Needs action</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card" onclick="location.href='?status=approved'">
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stats-number"><?php echo $stats['approved']; ?></div>
                <div class="stats-label">Approved</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-star"></i></div>
                <div class="stats-number"><?php echo number_format($stats['total_points']); ?></div>
                <div class="stats-label">Total Points Awarded</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold small"><i class="fas fa-search"></i> Search</label>
                <input type="text" class="form-control" name="search" placeholder="User or description..." 
                       value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 40px;">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small"><i class="fas fa-filter"></i> Status</label>
                <select class="form-select" name="status" style="border-radius: 40px;">
                    <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $statusFilter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $statusFilter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small"><i class="fas fa-tag"></i> Activity Type</label>
                <select class="form-select" name="type" style="border-radius: 40px;">
                    <option value="all" <?php echo $typeFilter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($activityTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $typeFilter == $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100" style="border-radius: 40px;">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Bulk Action Bar -->
    <div class="bulk-bar" id="bulkBar">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span id="selectedCount">0</span> activities selected
            </div>
            <div>
                <button class="btn btn-sm btn-success" id="bulkApproveBtn" onclick="submitBulkApprove()">
                    <i class="fas fa-check"></i> Approve Selected
                </button>
                <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
    </div>

    <!-- Activities Table -->
    <div class="table-card">
        <form method="POST" id="bulkForm">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th width="40">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                            </th>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Points</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($activities->num_rows > 0): ?>
                            <?php while($activity = $activities->fetch_assoc()): 
                                $icon = $activityIcons[$activity['activity_type']] ?? '♻️';
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($activity['status'] == 'pending'): ?>
                                            <input type="checkbox" class="activity-checkbox" name="selected_ids[]" value="<?php echo $activity['id']; ?>" onchange="updateBulkBar()">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $activity['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($activity['username']); ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold"><?php echo $icon; ?> <?php echo htmlspecialchars($activity['activity_type']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($activity['description'], 0, 50)); ?>...
                                        <br>
                                        <?php if ($activity['location']): ?>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($activity['location']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-success fw-bold">+<?php echo $activity['points_earned']; ?></td>
                                    <td>
                                        <?php if ($activity['image_path'] && file_exists('../' . $activity['image_path'])): ?>
                                            <img src="../<?php echo $activity['image_path']; ?>" class="activity-image" alt="Proof" onclick="openImageModal('../<?php echo $activity['image_path']; ?>')">
                                        <?php else: ?>
                                            <span class="text-muted">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $activity['status']; ?>">
                                            <?php echo ucfirst($activity['status']); ?>
                                        </span>
                                        <?php if ($activity['status'] != 'pending' && $activity['approved_by_name']): ?>
                                            <br>
                                            <small class="text-muted">by <?php echo htmlspecialchars($activity['approved_by_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($activity['status'] == 'pending'): ?>
                                            <a href="?approve=<?php echo $activity['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn-approve btn-sm" onclick="return confirm('Approve this activity? Points will be awarded.')">
                                                <i class="fas fa-check"></i> Approve
                                            </a>
                                            <br>
                                            <a href="?reject=<?php echo $activity['id']; ?>&<?php echo http_build_query($_GET); ?>" class="btn-reject btn-sm mt-1" onclick="return confirm('Reject this activity?')">
                                                <i class="fas fa-times"></i> Reject
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <i class="fas fa-box-open" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="mt-3 text-muted">No activities found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: transparent; border: none;">
            <div class="text-end mb-2">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <img id="modalImage" src="" style="width: 100%; border-radius: 20px;">
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

    // Image modal
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        new bootstrap.Modal(document.getElementById('imageModal')).show();
    }

    // Bulk actions
    var checkboxes = document.querySelectorAll('.activity-checkbox');
    var bulkBar = document.getElementById('bulkBar');
    var selectAllCheckbox = document.getElementById('selectAll');

    function updateBulkBar() {
        var checked = document.querySelectorAll('.activity-checkbox:checked');
        var count = checked.length;
        
        if (count > 0) {
            bulkBar.style.display = 'block';
            document.getElementById('selectedCount').innerText = count;
        } else {
            bulkBar.style.display = 'none';
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
        }
    }

    function toggleSelectAll(source) {
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
        updateBulkBar();
    }

    function clearSelection() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateBulkBar();
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
    }

    function submitBulkApprove() {
        var checked = document.querySelectorAll('.activity-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one activity to approve.');
            return;
        }
        
        if (confirm('Approve ' + checked.length + ' selected activities? Points will be awarded to users.')) {
            document.getElementById('bulkForm').submit();
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>