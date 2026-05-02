<?php
$page_title = 'Manage Recycling Locations';
$current_page = 'admin';
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

$message = '';
$messageType = '';
$editLocation = null;

// Handle add location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $category = $_POST['category'];
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $operating_hours = trim($_POST['operating_hours']);
    $description = trim($_POST['description']);
    $contact_phone = trim($_POST['contact_phone']);
    
    if (empty($name) || empty($address)) {
        $message = "Please fill in required fields (Name and Address).";
        $messageType = "danger";
    } else {
        $sql = "INSERT INTO recycling_locations (name, address, category, latitude, longitude, operating_hours, description, contact_phone, is_active, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssddssssi", $name, $address, $category, $latitude, $longitude, $operating_hours, $description, $contact_phone, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $message = "Recycling location added successfully!";
            $messageType = "success";
        } else {
            $message = "Failed to add location.";
            $messageType = "danger";
        }
        $stmt->close();
    }
}

// Handle edit location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_location'])) {
    $id = intval($_POST['location_id']);
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $category = $_POST['category'];
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $operating_hours = trim($_POST['operating_hours']);
    $description = trim($_POST['description']);
    $contact_phone = trim($_POST['contact_phone']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $sql = "UPDATE recycling_locations SET 
            name = ?, address = ?, category = ?, latitude = ?, longitude = ?, 
            operating_hours = ?, description = ?, contact_phone = ?, is_active = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssddssssii", $name, $address, $category, $latitude, $longitude, $operating_hours, $description, $contact_phone, $is_active, $id);
    
    if ($stmt->execute()) {
        $message = "Location updated successfully!";
        $messageType = "success";
    } else {
        $message = "Failed to update location.";
        $messageType = "danger";
    }
    $stmt->close();
}

// Handle delete location
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $sql = "DELETE FROM recycling_locations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Location deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Failed to delete location.";
        $messageType = "danger";
    }
    $stmt->close();
}

// Handle toggle status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    
    $sql = "UPDATE recycling_locations SET is_active = NOT is_active WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Location status toggled!";
        $messageType = "success";
    } else {
        $message = "Failed to toggle status.";
        $messageType = "danger";
    }
    $stmt->close();
}

// Get location for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $sql = "SELECT * FROM recycling_locations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editLocation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get all locations
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';

$sql = "SELECT * FROM recycling_locations WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (name LIKE '%$search%' OR address LIKE '%$search%')";
}
if ($categoryFilter != 'all') {
    $sql .= " AND category = '$categoryFilter'";
}
$sql .= " ORDER BY is_active DESC, name ASC";
$locations = $conn->query($sql);

// Get categories for filter
$categories = ['mixed', 'plastic_paper', 'ewaste', 'paper', 'organic'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Locations - Admin | Ecos+</title>
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

        /* Locations Page Styles */
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

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .location-card {
            transition: all 0.3s;
        }
        .location-card:hover {
            transform: translateY(-3px);
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

        .category-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 500;
        }
        .category-mixed { background: #4CAF50; color: white; }
        .category-plastic_paper { background: #2196F3; color: white; }
        .category-ewaste { background: #FF9800; color: white; }
        .category-paper { background: #9C27B0; color: white; }
        .category-organic { background: #8BC34A; color: white; }

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
                <li><a href="users.php" class="nav-link-custom"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="activities.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Activities</a></li>
                <li><a href="locations.php" class="nav-link-custom active"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
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
        <li><a href="activities.php"><i class="fas fa-recycle"></i> Activities</a></li>
        <li><a href="locations.php" class="active"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
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

    <div class="row">
        <!-- Add/Edit Location Form -->
        <div class="col-lg-4">
            <div class="form-card">
                <h5 class="mb-3">
                    <i class="fas <?php echo $editLocation ? 'fa-edit' : 'fa-plus-circle'; ?> text-success"></i>
                    <?php echo $editLocation ? 'Edit Location' : 'Add New Location'; ?>
                </h5>
                <hr>
                <form method="POST">
                    <?php if ($editLocation): ?>
                        <input type="hidden" name="location_id" value="<?php echo $editLocation['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Location Name *</label>
                        <input type="text" class="form-control" name="name" required
                               value="<?php echo $editLocation ? htmlspecialchars($editLocation['name']) : ''; ?>"
                               placeholder="e.g., Main Campus Recycling Center">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Address *</label>
                        <textarea class="form-control" name="address" rows="2" required
                                  placeholder="Full address of the location"><?php echo $editLocation ? htmlspecialchars($editLocation['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Category</label>
                        <select class="form-select" name="category">
                            <option value="mixed" <?php echo ($editLocation && $editLocation['category'] == 'mixed') ? 'selected' : ''; ?>>Mixed (All types)</option>
                            <option value="plastic_paper" <?php echo ($editLocation && $editLocation['category'] == 'plastic_paper') ? 'selected' : ''; ?>>Plastic & Paper</option>
                            <option value="ewaste" <?php echo ($editLocation && $editLocation['category'] == 'ewaste') ? 'selected' : ''; ?>>E-Waste Only</option>
                            <option value="paper" <?php echo ($editLocation && $editLocation['category'] == 'paper') ? 'selected' : ''; ?>>Paper Only</option>
                            <option value="organic" <?php echo ($editLocation && $editLocation['category'] == 'organic') ? 'selected' : ''; ?>>Organic Waste</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Latitude</label>
                            <input type="number" class="form-control" name="latitude" step="any" 
                                   value="<?php echo $editLocation ? $editLocation['latitude'] : '3.5462'; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Longitude</label>
                            <input type="number" class="form-control" name="longitude" step="any" 
                                   value="<?php echo $editLocation ? $editLocation['longitude'] : '103.4264'; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3 mt-2">
                        <label class="form-label fw-bold small">Operating Hours</label>
                        <input type="text" class="form-control" name="operating_hours" 
                               value="<?php echo $editLocation ? htmlspecialchars($editLocation['operating_hours']) : ''; ?>"
                               placeholder="e.g., Mon-Fri: 8am-6pm, Sat: 9am-1pm">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Contact Phone</label>
                        <input type="text" class="form-control" name="contact_phone" 
                               value="<?php echo $editLocation ? htmlspecialchars($editLocation['contact_phone']) : ''; ?>"
                               placeholder="e.g., +609-1234567">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Description</label>
                        <textarea class="form-control" name="description" rows="2" 
                                  placeholder="Additional information about this location"><?php echo $editLocation ? htmlspecialchars($editLocation['description']) : ''; ?></textarea>
                    </div>
                    
                    <?php if ($editLocation): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="is_active" <?php echo $editLocation['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active (visible to users)</label>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="<?php echo $editLocation ? 'edit_location' : 'add_location'; ?>" class="btn btn-success w-100">
                        <i class="fas <?php echo $editLocation ? 'fa-save' : 'fa-plus'; ?>"></i>
                        <?php echo $editLocation ? ' Update Location' : ' Add Location'; ?>
                    </button>
                    
                    <?php if ($editLocation): ?>
                        <a href="locations.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-times"></i> Cancel Edit
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Locations List -->
        <div class="col-lg-8">
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small"><i class="fas fa-search"></i> Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Location name or address..." 
                               value="<?php echo htmlspecialchars($search); ?>" style="border-radius: 40px;">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small"><i class="fas fa-filter"></i> Category</label>
                        <select class="form-select" name="category" style="border-radius: 40px;">
                            <option value="all" <?php echo $categoryFilter == 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo $categoryFilter == $cat ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $cat)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100" style="border-radius: 40px;">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Locations Table -->
            <div class="table-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <h5 class="mb-2 mb-md-0"><i class="fas fa-map-marker-alt"></i> Recycling Locations</h5>
                    <small class="text-muted">Total: <?php echo $locations->num_rows; ?> locations</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th>ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Category</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($locations->num_rows > 0): ?>
                                <?php while($loc = $locations->fetch_assoc()): ?>
                                    <tr class="location-card">
                                        <td><?php echo $loc['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($loc['name']); ?></strong>
                                            <?php if ($loc['contact_phone']): ?>
                                                <br>
                                                <small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($loc['contact_phone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(substr($loc['address'], 0, 50)); ?>...
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-map-pin"></i> <?php echo $loc['latitude']; ?>, <?php echo $loc['longitude']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="category-badge category-<?php echo $loc['category']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $loc['category'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars(substr($loc['operating_hours'], 0, 30)); ?>...</small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $loc['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $loc['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="?edit=<?php echo $loc['id']; ?>" class="action-btn text-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?toggle=<?php echo $loc['id']; ?>" class="action-btn text-warning" title="<?php echo $loc['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="return confirm('Toggle location status?')">
                                                    <i class="fas fa-<?php echo $loc['is_active'] ? 'ban' : 'check-circle'; ?>"></i>
                                                </a>
                                                <a href="?delete=<?php echo $loc['id']; ?>" class="action-btn text-danger" title="Delete" onclick="return confirm('Delete this location? This action cannot be undone.')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #ccc;"></i>
                                        <p class="mt-3 text-muted">No recycling locations found.</p>
                                        <p class="small text-muted">Click "Add New Location" to create one.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>