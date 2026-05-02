<?php
$page_title = 'Manage Users';
$current_page = 'admin';
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

$message = '';
$messageType = '';

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Don't allow admin to delete themselves
    if ($user_id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account!";
        $messageType = "danger";
    } else {
        // Check if user exists
        $checkSql = "SELECT id FROM users WHERE id = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if ($exists) {
            // Delete user (cascade will delete related records)
            $deleteSql = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($deleteSql);
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $message = "User deleted successfully!";
                $messageType = "success";
            } else {
                $message = "Failed to delete user.";
                $messageType = "danger";
            }
            $stmt->close();
        }
    }
}

// Handle user status toggle (activate/deactivate)
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);
    
    // Don't allow admin to deactivate themselves
    if ($user_id != $_SESSION['user_id']) {
        $statusSql = "SELECT is_active FROM users WHERE id = ?";
        $stmt = $conn->prepare($statusSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $newStatus = $current['is_active'] ? 0 : 1;
        $updateSql = "UPDATE users SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ii", $newStatus, $user_id);
        if ($stmt->execute()) {
            $message = $newStatus ? "User activated!" : "User deactivated!";
            $messageType = "success";
        }
        $stmt->close();
    }
}

// Handle make admin / remove admin
if (isset($_GET['make_admin']) && is_numeric($_GET['make_admin'])) {
    $user_id = intval($_GET['make_admin']);
    
    $updateSql = "UPDATE users SET role = 'admin' WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = "User promoted to Admin!";
        $messageType = "success";
    }
    $stmt->close();
}

if (isset($_GET['remove_admin']) && is_numeric($_GET['remove_admin'])) {
    $user_id = intval($_GET['remove_admin']);
    
    // Don't allow removing own admin
    if ($user_id == $_SESSION['user_id']) {
        $message = "You cannot remove your own admin role!";
        $messageType = "danger";
    } else {
        $updateSql = "UPDATE users SET role = 'user' WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = "Admin role removed!";
            $messageType = "success";
        }
        $stmt->close();
    }
}

// Handle reset password
if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    $user_id = intval($_GET['reset_password']);
    
    $newPassword = 'password123';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updateSql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("si", $hashedPassword, $user_id);
    if ($stmt->execute()) {
        $message = "Password reset to 'password123' for this user!";
        $messageType = "success";
    }
    $stmt->close();
}

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$sql = "SELECT id, username, email, full_name, student_id, points, role, is_active, created_at, last_login 
        FROM users WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
}
if ($roleFilter != 'all') {
    $sql .= " AND role = '$roleFilter'";
}
if ($statusFilter != 'all') {
    $sql .= " AND is_active = " . ($statusFilter == 'active' ? 1 : 0);
}

$sql .= " ORDER BY created_at DESC";
$users = $conn->query($sql);

// Get statistics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$totalAdmins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin | Ecos+</title>
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

        /* Users Page Styles */
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

        .table-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        .search-filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        .role-admin {
            background: #cfe2ff;
            color: #084298;
        }
        .role-user {
            background: #e2e3e5;
            color: #383d41;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 5px 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }
        .action-btn:hover {
            background: #f0f2f5;
        }
        .action-btn.text-danger:hover {
            background: #f8d7da;
        }
        .action-btn.text-success:hover {
            background: #d4edda;
        }

        .table th, .table td {
            vertical-align: middle;
            padding: 12px 15px;
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
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php" class="nav-link-custom active"><i class="fas fa-users"></i> Users</a></li>
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
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
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
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-users"></i></div>
                <div class="stats-number"><?php echo $totalUsers; ?></div>
                <div class="stats-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stats-number"><?php echo $totalAdmins; ?></div>
                <div class="stats-label">Administrators</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-user-check"></i></div>
                <div class="stats-number"><?php echo $activeUsers; ?></div>
                <div class="stats-label">Active Users</div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="search-filter-bar">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold small"><i class="fas fa-search"></i> Search</label>
                <input type="text" class="form-control" name="search" placeholder="Name, username or email..." 
                       value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 40px;">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small"><i class="fas fa-filter"></i> Role</label>
                <select class="form-select" name="role" style="border-radius: 40px;">
                    <option value="all" <?php echo $roleFilter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="user" <?php echo $roleFilter == 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $roleFilter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small"><i class="fas fa-circle"></i> Status</label>
                <select class="form-select" name="status" style="border-radius: 40px;">
                    <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100" style="border-radius: 40px;">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="table-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h5 class="mb-2 mb-md-0"><i class="fas fa-users"></i> User Management</h5>
            <small class="text-muted">Total: <?php echo $users->num_rows; ?> users found</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Student ID</th>
                        <th>Points</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): 
                            $isCurrentUser = ($user['id'] == $_SESSION['user_id']);
                        ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['student_id']) ?: '-'; ?></td>
                                <td>
                                    <span class="fw-bold text-success"><?php echo number_format($user['points']); ?></span>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if (!$isCurrentUser): ?>
                                            <!-- Toggle Status -->
                                            <a href="?toggle_status=<?php echo $user['id']; ?>&<?php echo http_build_query(array_merge($_GET, ['toggle_status' => null])); ?>" 
                                               class="action-btn text-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                               title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                            </a>
                                            
                                            <!-- Make/Remove Admin -->
                                            <?php if ($user['role'] == 'user'): ?>
                                                <a href="?make_admin=<?php echo $user['id']; ?>&<?php echo http_build_query(array_merge($_GET, ['make_admin' => null])); ?>" 
                                                   class="action-btn text-info" title="Make Admin" onclick="return confirm('Make this user an admin?')">
                                                    <i class="fas fa-user-shield"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?remove_admin=<?php echo $user['id']; ?>&<?php echo http_build_query(array_merge($_GET, ['remove_admin' => null])); ?>" 
                                                   class="action-btn text-warning" title="Remove Admin" onclick="return confirm('Remove admin role from this user?')">
                                                    <i class="fas fa-user-minus"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Reset Password -->
                                            <a href="?reset_password=<?php echo $user['id']; ?>&<?php echo http_build_query(array_merge($_GET, ['reset_password' => null])); ?>" 
                                               class="action-btn text-primary" title="Reset Password" onclick="return confirm('Reset password to \'password123\'?')">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            
                                            <!-- Delete User -->
                                            <a href="?delete=<?php echo $user['id']; ?>&<?php echo http_build_query(array_merge($_GET, ['delete' => null])); ?>" 
                                               class="action-btn text-danger" title="Delete User" onclick="return confirm('Delete this user? All their activities will be removed.')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Current</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-users" style="font-size: 48px; color: #ccc;"></i>
                                <p class="mt-3 text-muted">No users found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>