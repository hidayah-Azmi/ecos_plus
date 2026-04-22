<?php
$page_title = 'Student Report';
$current_page = 'admin';
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Get student ID from URL
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$exportType = isset($_GET['export']) ? $_GET['export'] : '';

// Get all students for dropdown
$studentsQuery = "SELECT id, username, full_name, email, points, 
                  (SELECT COUNT(*) FROM activities WHERE user_id = users.id AND status = 'approved') as total_activities
                  FROM users WHERE role = 'user' ORDER BY full_name ASC";
$students = $conn->query($studentsQuery);

// Get selected student data
$studentData = null;
$studentActivities = [];
$materialBreakdown = [];
$monthlyStudentData = [];
$recentActivities = [];

if ($student_id > 0) {
    // Get student info
    $studentSql = "SELECT id, username, full_name, email, points, created_at 
                   FROM users WHERE id = ? AND role = 'user'";
    $stmt = $conn->prepare($studentSql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $studentData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($studentData) {
        // Get material breakdown
        $materialSql = "SELECT activity_type, COUNT(*) as count, SUM(points_earned) as points 
                        FROM activities 
                        WHERE user_id = ? AND status = 'approved' 
                        GROUP BY activity_type 
                        ORDER BY count DESC";
        $stmt = $conn->prepare($materialSql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $materialBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get monthly activity data
        for($m = 1; $m <= 12; $m++) {
            $monthName = date('F', mktime(0, 0, 0, $m, 1));
            $sql = "SELECT COUNT(*) as count, SUM(points_earned) as points 
                    FROM activities 
                    WHERE user_id = $student_id AND status = 'approved' 
                    AND MONTH(created_at) = $m AND YEAR(created_at) = YEAR(NOW())";
            $result = $conn->query($sql);
            $data = $result->fetch_assoc();
            $monthlyStudentData[] = [
                'month' => substr($monthName, 0, 3),
                'activities' => $data['count'] ?? 0,
                'points' => $data['points'] ?? 0
            ];
        }
        
        // Get recent activities
        $recentSql = "SELECT activity_type, description, points_earned, created_at, status 
                      FROM activities 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC LIMIT 10";
        $stmt = $conn->prepare($recentSql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $recentActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Handle PDF Export
if ($exportType == 'pdf' && $student_id > 0 && $studentData) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo $studentData['full_name']; ?> - Activity Report</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Arial, sans-serif; padding: 30px; font-size: 12px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4CAF50; padding-bottom: 15px; }
            .header h1 { color: #4CAF50; font-size: 24px; }
            .student-info { background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .student-info table { width: 100%; }
            .section { margin-bottom: 30px; page-break-inside: avoid; }
            .section-title { background: #4CAF50; color: white; padding: 8px 12px; margin-bottom: 15px; font-size: 14px; font-weight: bold; border-radius: 5px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f5f5f5; }
            .chart-container { margin: 20px 0; text-align: center; }
            canvas { max-width: 100%; height: auto; margin: 0 auto; }
            .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
            .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; border-radius: 8px; background: #f9f9f9; }
            .stat-number { font-size: 28px; font-weight: bold; color: #4CAF50; }
            .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 10px; }
            .print-btn { text-align: center; margin-bottom: 20px; }
            .print-btn button { background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <div class="print-btn">
            <button onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>
        </div>
        
        <div class="header">
            <h1>🌿 Ecos+ Student Activity Report</h1>
            <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
        </div>
        
        <div class="student-info">
            <table>
                <tr><td width="30%"><strong>Student Name:</strong></td><td><?php echo htmlspecialchars($studentData['full_name']); ?></td></tr>
                <tr><td><strong>Username:</strong></td><td>@<?php echo htmlspecialchars($studentData['username']); ?></td></tr>
                <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($studentData['email']); ?></td></tr>
                <tr><td><strong>Member Since:</strong></td><td><?php echo date('F j, Y', strtotime($studentData['created_at'])); ?></td></tr>
            </table>
        </div>
        
        <div class="stats-grid">
            <div class="stat-box"><div class="stat-number"><?php echo array_sum(array_column($monthlyStudentData, 'activities')); ?></div><div>Total Activities</div></div>
            <div class="stat-box"><div class="stat-number"><?php echo $studentData['points']; ?></div><div>Total Points</div></div>
            <div class="stat-box"><div class="stat-number"><?php echo count($materialBreakdown); ?></div><div>Material Types</div></div>
        </div>
        
        <div class="section">
            <div class="section-title">📊 Monthly Activity Trend</div>
            <div class="chart-container">
                <canvas id="monthlyChart" width="700" height="250"></canvas>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">♻️ Material Breakdown</div>
            <div class="chart-container">
                <canvas id="materialChart" width="400" height="300"></canvas>
            </div>
            <table>
                <thead><th>Material Type</th><th>Count</th><th>Points Earned</th></thead>
                <tbody><?php foreach($materialBreakdown as $m): ?>
                    <tr><td><?php echo $m['activity_type']; ?></td><td style="text-align:center"><?php echo $m['count']; ?></td><td style="text-align:right"><?php echo $m['points']; ?></td></tr>
                <?php endforeach; ?>
                <?php if(empty($materialBreakdown)): ?><tr><td colspan="3" style="text-align:center">No activities yet</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">📋 Recent Activities</div>
            <table>
                <thead><th>Date</th><th>Type</th><th>Description</th><th>Points</th><th>Status</th></thead>
                <tbody><?php foreach($recentActivities as $act): ?>
                    <tr><td><?php echo date('M d, Y', strtotime($act['created_at'])); ?></td><td><?php echo $act['activity_type']; ?></td><td><?php echo substr($act['description'], 0, 40); ?>...</td><td style="text-align:right">+<?php echo $act['points_earned']; ?></td><td><?php echo ucfirst($act['status']); ?></td></tr>
                <?php endforeach; ?>
                <?php if(empty($recentActivities)): ?><tr><td colspan="5" style="text-align:center">No activities yet</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Ecos+ Green Lifestyle & Recycling Tracker | Universiti Malaysia Pahang Al-Sultan Abdullah</p>
        </div>
        
        <script>
            new Chart(document.getElementById('monthlyChart'), {
                type: 'bar',
                data: { labels: <?php echo json_encode(array_column($monthlyStudentData, 'month')); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode(array_column($monthlyStudentData, 'activities')); ?>, backgroundColor: 'rgba(76, 175, 80, 0.7)', borderColor: '#4CAF50', borderWidth: 1 }] },
                options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
            
            new Chart(document.getElementById('materialChart'), {
                type: 'doughnut',
                data: { labels: <?php echo json_encode(array_column($materialBreakdown, 'activity_type')); ?>, datasets: [{ data: <?php echo json_encode(array_column($materialBreakdown, 'count')); ?>, backgroundColor: ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#F44336', '#8BC34A', '#FFC107', '#607D8B'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report - Admin | Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; }
        .card-custom { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .student-list { max-height: 500px; overflow-y: auto; }
        .student-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: all 0.3s; }
        .student-item:hover { background: #e8f5e9; transform: translateX(5px); }
        .student-item.active { background: #4CAF50; color: white; }
        .student-item.active .text-muted { color: rgba(255,255,255,0.8) !important; }
        .student-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: white; }
        .chart-container { margin: 20px 0; }
        .stat-badge { display: inline-block; padding: 8px 16px; background: #f8f9fa; border-radius: 30px; margin: 5px; }
        .material-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .btn-export { background: #dc3545; color: white; border-radius: 40px; padding: 8px 20px; border: none; }
        .btn-export:hover { background: #c82333; }
        @media (max-width: 768px) { .container-custom { padding: 15px; } }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom"><div class="logo-icon">
                    <img src="assets/images/umpsa.png" alt="Logo" style="height:25px; object-fit:cover;">
                </div><div class="logo-text">Ecos<span>+</span> Admin</div></a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="users.php" class="nav-link-custom"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="activities.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Activities</a></li>
                <li><a href="locations.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <li><a href="reports.php" class="nav-link-custom"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li><a href="student_report.php" class="nav-link-custom active"><i class="fas fa-user-graduate"></i> Student Report</a></li>
            </ul>
            <div class="user-dropdown">
                <div class="user-trigger"><div class="user-avatar"><?php echo $navbar_initial; ?></div><div class="user-info"><span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'Admin'); ?></span><span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span></div><i class="fas fa-chevron-down dropdown-arrow"></i></div>
                <div class="dropdown-menu-custom"><a href="../profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a><a href="../dashboard.php" class="dropdown-item-custom"><i class="fas fa-globe"></i> Back to Site</a><div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div><a href="../logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
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
        <li><a href="locations.php"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="student_report.php" class="active"><i class="fas fa-user-graduate"></i> Student Report</a></li>
        <li><a href="../profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="../dashboard.php"><i class="fas fa-globe"></i> Back to Site</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <div class="row">
        <!-- Student List Column -->
        <div class="col-lg-4">
            <div class="card-custom">
                <h5 class="mb-3"><i class="fas fa-users text-success"></i> Student List</h5>
                <div class="student-list">
                    <?php while($student = $students->fetch_assoc()): ?>
                        <div class="student-item <?php echo ($student_id == $student['id']) ? 'active' : ''; ?>" onclick="location.href='?student_id=<?php echo $student['id']; ?>'">
                            <div class="d-flex align-items-center gap-3">
                                <div class="student-avatar"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></div>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                    <div class="text-muted small">@<?php echo htmlspecialchars($student['username']); ?></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success"><?php echo $student['points']; ?> pts</div>
                                <div class="text-muted small"><?php echo $student['total_activities']; ?> activities</div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Student Report Column -->
        <div class="col-lg-8">
            <?php if ($student_id > 0 && $studentData): ?>
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                        <h5><i class="fas fa-chart-line text-success"></i> Activity Report: <?php echo htmlspecialchars($studentData['full_name']); ?></h5>
                        <a href="?student_id=<?php echo $student_id; ?>&export=pdf" class="btn-export"><i class="fas fa-file-pdf"></i> Export PDF</a>
                    </div>
                    
                    <!-- Student Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="stat-badge"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($studentData['email']); ?></div>
                            <div class="stat-badge"><i class="fas fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($studentData['created_at'])); ?></div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="stat-badge bg-success text-white"><i class="fas fa-star"></i> Total Points: <?php echo $studentData['points']; ?></div>
                            <div class="stat-badge"><i class="fas fa-recycle"></i> Activities: <?php echo array_sum(array_column($monthlyStudentData, 'activities')); ?></div>
                        </div>
                    </div>
                    
                    <!-- Monthly Activity Chart -->
                    <div class="chart-container">
                        <h6><i class="fas fa-chart-bar"></i> Monthly Activity Trend</h6>
                        <canvas id="monthlyChart" style="max-height: 300px;"></canvas>
                    </div>
                    
                    <!-- Material Breakdown Chart -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6><i class="fas fa-chart-pie"></i> Material Breakdown</h6>
                            <canvas id="materialChart" style="max-height: 250px;"></canvas>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-table"></i> Material Summary</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th>Material</th><th>Count</th><th>Points</th></tr></thead>
                                    <tbody>
                                        <?php foreach($materialBreakdown as $m): ?>
                                            <tr><td><?php echo $m['activity_type']; ?></td><td><?php echo $m['count']; ?></td><td><?php echo $m['points']; ?></td></tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($materialBreakdown)): ?>
                                            <tr><td colspan="3" class="text-center">No activities yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="mt-4">
                        <h6><i class="fas fa-history"></i> Recent Activities</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Points</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach($recentActivities as $act): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($act['created_at'])); ?></td>
                                            <td><?php echo $act['activity_type']; ?></td>
                                            <td><?php echo substr($act['description'], 0, 30); ?>...</td>
                                            <td>+<?php echo $act['points_earned']; ?></td>
                                            <td><span class="badge bg-<?php echo $act['status'] == 'approved' ? 'success' : 'warning'; ?>"><?php echo ucfirst($act['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($recentActivities)): ?>
                                        <tr><td colspan="5" class="text-center">No activities yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <script>
                    // Monthly Chart
                    new Chart(document.getElementById('monthlyChart'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode(array_column($monthlyStudentData, 'month')); ?>,
                            datasets: [{
                                label: 'Activities',
                                data: <?php echo json_encode(array_column($monthlyStudentData, 'activities')); ?>,
                                backgroundColor: 'rgba(76, 175, 80, 0.7)',
                                borderColor: '#4CAF50',
                                borderWidth: 1,
                                borderRadius: 8
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'top' } } }
                    });
                    
                    // Material Chart
                    new Chart(document.getElementById('materialChart'), {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_column($materialBreakdown, 'activity_type')); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_column($materialBreakdown, 'count')); ?>,
                                backgroundColor: ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#F44336', '#8BC34A', '#FFC107', '#607D8B'],
                                borderWidth: 0
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
                    });
                </script>
                
            <?php else: ?>
                <div class="card-custom text-center py-5">
                    <i class="fas fa-user-graduate" style="font-size: 64px; color: #ccc;"></i>
                    <h5 class="mt-3 text-muted">Select a student from the list</h5>
                    <p class="text-muted">Click on any student to view their detailed activity report</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
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