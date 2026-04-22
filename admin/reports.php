<?php
$page_title = 'System Reports';
$current_page = 'admin';
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Get date filter
$yearFilter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$monthFilter = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$exportType = isset($_GET['export']) ? $_GET['export'] : '';
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Available years for filter
$years = [];
for($i = date('Y') - 2; $i <= date('Y'); $i++) {
    $years[] = $i;
}

// ============================================
// GET ALL STATISTICS
// ============================================

// Total users
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$totalAdmins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];

// Total activities
$totalActivities = $conn->query("SELECT COUNT(*) as count FROM activities")->fetch_assoc()['count'];
$approvedActivities = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'approved'")->fetch_assoc()['count'];
$pendingActivities = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'pending'")->fetch_assoc()['count'];
$rejectedActivities = $conn->query("SELECT COUNT(*) as count FROM activities WHERE status = 'rejected'")->fetch_assoc()['count'];

// Total points
$totalPoints = $conn->query("SELECT SUM(points) as total FROM users")->fetch_assoc()['total'] ?? 0;
$totalPointsAwarded = $conn->query("SELECT SUM(points_earned) as total FROM activities WHERE status = 'approved'")->fetch_assoc()['total'] ?? 0;

// Total locations and events
$totalLocations = $conn->query("SELECT COUNT(*) as count FROM recycling_locations WHERE is_active = 1")->fetch_assoc()['count'];
$totalEvents = $conn->query("SELECT COUNT(*) as count FROM campus_events WHERE is_active = 1")->fetch_assoc()['count'];
$totalEventParticipants = $conn->query("SELECT COUNT(*) as count FROM event_participants")->fetch_assoc()['count'];

// Total community posts
$totalPosts = $conn->query("SELECT COUNT(*) as count FROM community_posts")->fetch_assoc()['count'];
$totalLikes = $conn->query("SELECT COUNT(*) as count FROM post_likes")->fetch_assoc()['count'];
$totalComments = $conn->query("SELECT COUNT(*) as count FROM community_comments")->fetch_assoc()['count'];

// Monthly activity data
$monthlyData = [];
$monthlyExportData = [];
$monthlyLabels = [];
$monthlyCounts = [];
for($m = 1; $m <= 12; $m++) {
    $monthName = date('F', mktime(0, 0, 0, $m, 1));
    $monthlyLabels[] = substr($monthName, 0, 3);
    $sql = "SELECT COUNT(*) as count, SUM(points_earned) as points 
            FROM activities 
            WHERE YEAR(created_at) = $yearFilter AND MONTH(created_at) = $m AND status = 'approved'";
    $result = $conn->query($sql);
    $data = $result->fetch_assoc();
    $monthlyData[$monthName] = [
        'count' => $data['count'] ?? 0,
        'points' => $data['points'] ?? 0
    ];
    $monthlyCounts[] = $data['count'] ?? 0;
    $monthlyExportData[] = [
        'month' => $monthName,
        'activities' => $data['count'] ?? 0,
        'points' => $data['points'] ?? 0
    ];
}

// Activity by type
$typeExportData = [];
$typeLabels = [];
$typeCounts = [];
$typeQuery = "SELECT activity_type, COUNT(*) as count, SUM(points_earned) as points 
              FROM activities WHERE status = 'approved' 
              GROUP BY activity_type ORDER BY count DESC";
$typeResult = $conn->query($typeQuery);
$activityTypes = $conn->query($typeQuery);
while($row = $typeResult->fetch_assoc()) {
    $typeExportData[] = $row;
    $typeLabels[] = $row['activity_type'];
    $typeCounts[] = $row['count'];
}

// Top users
$topUsersExport = [];
$topUsersQuery = "SELECT id, username, full_name, points, 
                  (SELECT COUNT(*) FROM activities WHERE user_id = users.id AND status = 'approved') as activities_count
                  FROM users WHERE role = 'user' ORDER BY points DESC LIMIT 10";
$topUsersResult = $conn->query($topUsersQuery);
$topUsers = $conn->query($topUsersQuery);
while($row = $topUsersResult->fetch_assoc()) {
    $topUsersExport[] = $row;
}

// Recent activities
$recentActivitiesQuery = "SELECT a.*, u.username, u.full_name 
                          FROM activities a 
                          INNER JOIN users u ON a.user_id = u.id 
                          ORDER BY a.created_at DESC LIMIT 20";
$recentActivities = $conn->query($recentActivitiesQuery);

// Daily activity
$dailyData = [];
$dailyLabels = [];
$dailyCounts = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayName = date('D', strtotime($date));
    $dailyLabels[] = $dayName;
    $sql = "SELECT COUNT(*) as count FROM activities WHERE DATE(created_at) = '$date' AND status = 'approved'";
    $result = $conn->query($sql);
    $count = $result->fetch_assoc()['count'] ?? 0;
    $dailyData[$dayName] = $count;
    $dailyCounts[] = $count;
}

// ============================================
// GET STUDENT DATA (for individual report)
// ============================================
$allStudents = [];
$studentData = null;
$studentMaterialBreakdown = [];
$studentMonthlyData = [];
$studentRecentActivities = [];

$studentsQuery = "SELECT id, username, full_name, email, points, created_at,
                  (SELECT COUNT(*) FROM activities WHERE user_id = users.id AND status = 'approved') as total_activities
                  FROM users WHERE role = 'user' ORDER BY full_name ASC";
$allStudents = $conn->query($studentsQuery)->fetch_all(MYSQLI_ASSOC);

if ($studentId > 0) {
    // Get student info
    $studentSql = "SELECT id, username, full_name, email, points, created_at 
                   FROM users WHERE id = ? AND role = 'user'";
    $stmt = $conn->prepare($studentSql);
    $stmt->bind_param("i", $studentId);
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
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentMaterialBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get monthly activity
        for($m = 1; $m <= 12; $m++) {
            $sql = "SELECT COUNT(*) as count 
                    FROM activities 
                    WHERE user_id = $studentId AND status = 'approved' 
                    AND MONTH(created_at) = $m AND YEAR(created_at) = YEAR(NOW())";
            $result = $conn->query($sql);
            $data = $result->fetch_assoc();
            $studentMonthlyData[] = [
                'month' => date('M', mktime(0,0,0,$m,1)),
                'activities' => $data['count'] ?? 0
            ];
        }
        
        // Get recent activities
        $recentSql = "SELECT activity_type, description, points_earned, created_at, status 
                      FROM activities 
                      WHERE user_id = ? AND status = 'approved'
                      ORDER BY created_at DESC LIMIT 10";
        $stmt = $conn->prepare($recentSql);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $studentRecentActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// ============================================
// HANDLE PDF EXPORT (Student Report)
// ============================================

if ($exportType == 'student_pdf' && $studentId > 0 && $studentData) {
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
            .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
            .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; border-radius: 8px; }
            .stat-number { font-size: 28px; font-weight: bold; color: #4CAF50; }
            .section { margin-bottom: 30px; page-break-inside: avoid; }
            .section-title { background: #4CAF50; color: white; padding: 8px 12px; margin-bottom: 15px; font-size: 14px; font-weight: bold; border-radius: 5px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f5f5f5; }
            .chart-container { margin: 20px 0; text-align: center; }
            canvas { max-width: 100%; height: auto; }
            .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 10px; }
            .print-btn { text-align: center; margin-bottom: 20px; }
            .print-btn button { background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <div class="print-btn"><button onclick="window.print()">Print / Save as PDF</button></div>
        <div class="header"><h1>🌿 Ecos+ Student Activity Report</h1><p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p></div>
        <div class="student-info"><table width="100%"><tr><td width="30%"><strong>Student Name:</strong></td><td><?php echo htmlspecialchars($studentData['full_name']); ?></td><td width="30%"><strong>Username:</strong></td><td>@<?php echo htmlspecialchars($studentData['username']); ?></td></tr><tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($studentData['email']); ?></td><td><strong>Member Since:</strong></td><td><?php echo date('F j, Y', strtotime($studentData['created_at'])); ?></td></tr></table></div>
        <div class="stats-grid"><div class="stat-box"><div class="stat-number"><?php echo array_sum(array_column($studentMonthlyData, 'activities')); ?></div><div>Total Activities</div></div><div class="stat-box"><div class="stat-number"><?php echo $studentData['points']; ?></div><div>Total Points</div></div><div class="stat-box"><div class="stat-number"><?php echo count($studentMaterialBreakdown); ?></div><div>Material Types</div></div></div>
        <div class="section"><div class="section-title">📊 Monthly Activity Trend</div><div class="chart-container"><canvas id="monthlyChart" width="700" height="250"></canvas></div></div>
        <div class="section"><div class="section-title">♻️ Material Breakdown</div><div class="chart-container"><canvas id="materialChart" width="400" height="300"></canvas></div><table><thead><th>Material Type</th><th>Count</th><th>Points Earned</th></thead><tbody><?php foreach($studentMaterialBreakdown as $m): ?><tr><td><?php echo $m['activity_type']; ?></td><td style="text-align:center"><?php echo $m['count']; ?></td><td style="text-align:right"><?php echo $m['points']; ?></td></tr><?php endforeach; ?><?php if(empty($studentMaterialBreakdown)): ?><tr><td colspan="3" style="text-align:center">No activities yet</td></tr><?php endif; ?></tbody></table></div>
        <div class="section"><div class="section-title">📋 Recent Activities</div><table><thead><th>Date</th><th>Type</th><th>Description</th><th>Points</th></thead><tbody><?php foreach($studentRecentActivities as $act): ?><tr><td><?php echo date('M d, Y', strtotime($act['created_at'])); ?></td><td><?php echo $act['activity_type']; ?></td><td><?php echo substr($act['description'], 0, 40); ?>...</td><td style="text-align:right">+<?php echo $act['points_earned']; ?></td></tr><?php endforeach; ?><?php if(empty($studentRecentActivities)): ?><tr><td colspan="4" style="text-align:center">No activities yet</td></tr><?php endif; ?></tbody></table></div>
        <div class="footer"><p>Ecos+ Green Lifestyle & Recycling Tracker | Universiti Malaysia Pahang Al-Sultan Abdullah</p></div>
        <script>
            new Chart(document.getElementById('monthlyChart'), { type: 'bar', data: { labels: <?php echo json_encode(array_column($studentMonthlyData, 'month')); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode(array_column($studentMonthlyData, 'activities')); ?>, backgroundColor: 'rgba(76, 175, 80, 0.7)', borderColor: '#4CAF50', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
            new Chart(document.getElementById('materialChart'), { type: 'doughnut', data: { labels: <?php echo json_encode(array_column($studentMaterialBreakdown, 'activity_type')); ?>, datasets: [{ data: <?php echo json_encode(array_column($studentMaterialBreakdown, 'count')); ?>, backgroundColor: ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#F44336', '#8BC34A', '#FFC107', '#607D8B'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } } });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// HANDLE PDF EXPORT (Main Report)
// ============================================

if ($exportType == 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Ecos+ System Report - <?php echo date('F Y', mktime(0,0,0,$monthFilter,1,$yearFilter)); ?></title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Segoe UI', Arial, sans-serif; padding: 30px; font-size: 12px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4CAF50; padding-bottom: 15px; }
            .header h1 { color: #4CAF50; font-size: 24px; }
            .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
            .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; border-radius: 8px; }
            .stat-number { font-size: 28px; font-weight: bold; color: #4CAF50; }
            .section { margin-bottom: 30px; page-break-inside: avoid; }
            .section-title { background: #4CAF50; color: white; padding: 8px 12px; margin-bottom: 15px; font-size: 14px; font-weight: bold; border-radius: 5px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #f5f5f5; }
            .chart-container { margin: 20px 0; text-align: center; }
            canvas { max-width: 100%; height: auto; }
            .two-columns { display: flex; gap: 20px; margin-bottom: 30px; }
            .column { flex: 1; }
            .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 10px; }
            .print-btn { text-align: center; margin-bottom: 20px; }
            .print-btn button { background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <div class="print-btn"><button onclick="window.print()">Print / Save as PDF</button></div>
        <div class="header"><h1>🌿 Ecos+ System Report</h1><p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p><p>Period: <?php echo date('F Y', mktime(0,0,0,$monthFilter,1,$yearFilter)); ?></p></div>
        <div class="stats-grid"><div class="stat-box"><div class="stat-number"><?php echo $totalUsers; ?></div><div>Total Users</div></div><div class="stat-box"><div class="stat-number"><?php echo $totalActivities; ?></div><div>Total Activities</div></div><div class="stat-box"><div class="stat-number"><?php echo number_format($totalPointsAwarded); ?></div><div>Points Awarded</div></div><div class="stat-box"><div class="stat-number"><?php echo $totalLocations; ?></div><div>Recycling Locations</div></div></div>
        <div class="section"><div class="section-title">📊 Monthly Activities - <?php echo $yearFilter; ?></div><div class="chart-container"><canvas id="monthlyChart" width="800" height="300"></canvas></div><table><thead><th>Month</th><th>Activities</th><th>Points Earned</th></thead><tbody><?php foreach($monthlyExportData as $data): ?><tr><td><?php echo $data['month']; ?></td><td style="text-align:center"><?php echo $data['activities']; ?></td><td style="text-align:right"><?php echo number_format($data['points']); ?></td></tr><?php endforeach; ?></tbody></table></div>
        <div class="two-columns"><div class="column"><div class="section"><div class="section-title">📈 Activity Status</div><div class="chart-container"><canvas id="statusChart" width="300" height="250"></canvas></div><div style="text-align:center; margin-top:10px;"><span style="background:#28a745; color:white; padding:2px 8px; border-radius:10px;">Approved: <?php echo $approvedActivities; ?></span> <span style="background:#ffc107; color:#333; padding:2px 8px; border-radius:10px;">Pending: <?php echo $pendingActivities; ?></span> <span style="background:#dc3545; color:white; padding:2px 8px; border-radius:10px;">Rejected: <?php echo $rejectedActivities; ?></span></div></div></div><div class="column"><div class="section"><div class="section-title">♻️ Activities by Type</div><div class="chart-container"><canvas id="typeChart" width="300" height="250"></canvas></div></div></div></div>
        <div class="section"><div class="section-title">📅 Daily Activity (Last 7 Days)</div><div class="chart-container"><canvas id="dailyChart" width="800" height="250"></canvas></div></div>
        <div class="section"><div class="section-title">🏆 Top 10 Users by Points</div><table><thead><th>Rank</th><th>Full Name</th><th>Username</th><th>Points</th><th>Activities</th></thead><tbody><?php $rank=1; foreach($topUsersExport as $user): ?><tr><td style="text-align:center">#<?php echo $rank; ?></td><td><?php echo htmlspecialchars($user['full_name']); ?></td><td><?php echo htmlspecialchars($user['username']); ?></td><td style="text-align:right"><?php echo number_format($user['points']); ?></td><td style="text-align:center"><?php echo $user['activities_count']; ?></td></tr><?php $rank++; endforeach; ?></tbody></table></div>
        <div class="footer"><p>Ecos+ Green Lifestyle & Recycling Tracker | Universiti Malaysia Pahang Al-Sultan Abdullah</p></div>
        <script>
            new Chart(document.getElementById('monthlyChart'), { type: 'bar', data: { labels: <?php echo json_encode($monthlyLabels); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode($monthlyCounts); ?>, backgroundColor: 'rgba(76, 175, 80, 0.7)', borderColor: '#4CAF50', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
            new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: ['Approved', 'Pending', 'Rejected'], datasets: [{ data: [<?php echo $approvedActivities; ?>, <?php echo $pendingActivities; ?>, <?php echo $rejectedActivities; ?>], backgroundColor: ['#28a745', '#ffc107', '#dc3545'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } } });
            new Chart(document.getElementById('typeChart'), { type: 'bar', data: { labels: <?php echo json_encode($typeLabels); ?>, datasets: [{ label: 'Number of Activities', data: <?php echo json_encode($typeCounts); ?>, backgroundColor: 'rgba(33, 150, 243, 0.7)', borderColor: '#2196F3', borderWidth: 1 }] }, options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
            new Chart(document.getElementById('dailyChart'), { type: 'line', data: { labels: <?php echo json_encode($dailyLabels); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode($dailyCounts); ?>, backgroundColor: 'rgba(76, 175, 80, 0.2)', borderColor: '#4CAF50', borderWidth: 2, tension: 0.3, fill: true, pointBackgroundColor: '#4CAF50', pointRadius: 5 }] }, options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// HANDLE EXPORT - CSV
// ============================================

if ($exportType == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ecos_report_' . $yearFilter . '_' . $monthFilter . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ECOS+ SYSTEM REPORT']);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . date('F Y', mktime(0,0,0,$monthFilter,1,$yearFilter))]);
    fputcsv($output, []);
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Total Users', $totalUsers]);
    fputcsv($output, ['Total Activities', $totalActivities]);
    fputcsv($output, ['Approved Activities', $approvedActivities]);
    fputcsv($output, ['Pending Activities', $pendingActivities]);
    fputcsv($output, ['Rejected Activities', $rejectedActivities]);
    fputcsv($output, ['Total Points Awarded', $totalPointsAwarded]);
    fputcsv($output, ['Active Recycling Locations', $totalLocations]);
    fputcsv($output, ['Active Events', $totalEvents]);
    fputcsv($output, ['Event Participants', $totalEventParticipants]);
    fputcsv($output, ['Community Posts', $totalPosts]);
    fputcsv($output, []);
    fputcsv($output, ['MONTHLY ACTIVITIES - ' . $yearFilter]);
    fputcsv($output, ['Month', 'Activities', 'Points Earned']);
    foreach($monthlyExportData as $data) { fputcsv($output, [$data['month'], $data['activities'], $data['points']]); }
    fputcsv($output, []);
    fputcsv($output, ['ACTIVITIES BY MATERIAL TYPE']);
    fputcsv($output, ['Material Type', 'Count', 'Points Earned']);
    foreach($typeExportData as $data) { fputcsv($output, [$data['activity_type'], $data['count'], $data['points']]); }
    fputcsv($output, []);
    fputcsv($output, ['TOP 10 USERS BY POINTS']);
    fputcsv($output, ['Rank', 'Full Name', 'Username', 'Points', 'Activities']);
    $rank = 1;
    foreach($topUsersExport as $user) { fputcsv($output, [$rank, $user['full_name'], $user['username'], $user['points'], $user['activities_count']]); $rank++; }
    fclose($output);
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Admin | Ecos+</title>
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

        .container-custom { max-width: 1600px; margin: 0 auto; padding: 25px; }
        .stats-card { background: white; border-radius: 20px; padding: 20px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s; height: 100%; cursor: pointer; }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-number { font-size: 32px; font-weight: 700; color: #4CAF50; }
        .stats-icon { font-size: 35px; margin-bottom: 10px; color: #4CAF50; }
        .chart-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .chart-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #4CAF50; }
        .filter-bar { background: white; border-radius: 20px; padding: 15px 20px; margin-bottom: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .table-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        .export-buttons { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        .btn-export-excel { background: #28a745; color: white; border-radius: 40px; padding: 8px 20px; font-size: 13px; border: none; }
        .btn-export-pdf { background: #dc3545; color: white; border-radius: 40px; padding: 8px 20px; font-size: 13px; border: none; }
        .student-list { max-height: 500px; overflow-y: auto; }
        .student-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: all 0.3s; }
        .student-item:hover { background: #e8f5e9; transform: translateX(5px); }
        .student-item.active { background: #4CAF50; color: white; border-radius: 12px; }
        .student-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; color: white; margin-right: 12px; }
        .btn-export-student { background: #17a2b8; color: white; border-radius: 40px; padding: 5px 15px; font-size: 12px; border: none; }
        .btn-export-student:hover { background: #138496; }
        .nav-tabs-custom { display: flex; gap: 5px; background: white; border-radius: 15px; padding: 5px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .tab-btn { flex: 1; padding: 12px; border: none; background: none; border-radius: 12px; font-weight: 500; color: #666; transition: all 0.3s; }
        .tab-btn:hover { background: #f0f2f5; }
        .tab-btn.active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        @media (max-width: 768px) { .container-custom { padding: 15px; } .stats-number { font-size: 24px; } }
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
                <li><a href="reports.php" class="nav-link-custom active"><i class="fas fa-chart-line"></i> Reports</a></li>
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
        <li><a href="reports.php" class="active"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="../profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
        <li><a href="../dashboard.php"><i class="fas fa-globe"></i> Back to Site</a></li>
        <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <!-- Tabs -->
    <div class="nav-tabs-custom">
        <button class="tab-btn active" onclick="showTab('system')"><i class="fas fa-chart-line"></i> System Report</button>
        <button class="tab-btn" onclick="showTab('student')"><i class="fas fa-user-graduate"></i> Student Report</button>
    </div>

    <!-- System Report Tab -->
    <div id="tab-system" class="tab-content active">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div class="alert alert-success mb-2 mb-md-0" style="border-radius: 20px;"><i class="fas fa-chart-line"></i> System Reports & Analytics</div>
            <div class="export-buttons">
                <a href="?year=<?php echo $yearFilter; ?>&month=<?php echo $monthFilter; ?>&export=excel" class="btn-export-excel"><i class="fas fa-file-excel"></i> Export to Excel</a>
                <a href="?year=<?php echo $yearFilter; ?>&month=<?php echo $monthFilter; ?>&export=pdf" class="btn-export-pdf"><i class="fas fa-file-pdf"></i> Export to PDF</a>
            </div>
        </div>

        <!-- Statistics Row 1 -->
        <div class="row mb-4">
            <div class="col-md-3"><div class="stats-card"><div class="stats-icon"><i class="fas fa-users"></i></div><div class="stats-number"><?php echo $totalUsers; ?></div><div class="stats-label">Total Users</div><small class="text-muted"><?php echo $activeUsers; ?> active</small></div></div>
            <div class="col-md-3"><div class="stats-card"><div class="stats-icon"><i class="fas fa-recycle"></i></div><div class="stats-number"><?php echo $totalActivities; ?></div><div class="stats-label">Total Activities</div><small class="text-muted"><?php echo $approvedActivities; ?> approved</small></div></div>
            <div class="col-md-3"><div class="stats-card"><div class="stats-icon"><i class="fas fa-star"></i></div><div class="stats-number"><?php echo number_format($totalPoints); ?></div><div class="stats-label">Total Points</div><small class="text-muted"><?php echo number_format($totalPointsAwarded); ?> awarded</small></div></div>
            <div class="col-md-3"><div class="stats-card"><div class="stats-icon"><i class="fas fa-map-marker-alt"></i></div><div class="stats-number"><?php echo $totalLocations; ?></div><div class="stats-label">Recycling Locations</div></div></div>
        </div>

        <!-- Statistics Row 2 -->
        <div class="row mb-4">
            <div class="col-md-4"><div class="stats-card"><div class="stats-icon"><i class="fas fa-calendar"></i></div><div class="stats-number"><?php echo $totalEvents; ?></div><div class="stats-label">Active Events</div><small class="text-muted"><?php echo $totalEventParticipants; ?> participants</small></div></div>
            <div class="col-md-4"><div class="stats-card"><div class="stats-icon"><i class="fas fa-users"></i></div><div class="stats-number"><?php echo $totalPosts; ?></div><div class="stats-label">Community Posts</div><small class="text-muted"><?php echo $totalLikes; ?> likes, <?php echo $totalComments; ?> comments</small></div></div>
            <div class="col-md-4"><div class="stats-card"><div class="stats-icon"><i class="fas fa-chart-pie"></i></div><div class="stats-number"><?php echo round(($approvedActivities / max($totalActivities, 1)) * 100); ?>%</div><div class="stats-label">Approval Rate</div><small class="text-muted">activities approved</small></div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label fw-bold small"><i class="fas fa-calendar"></i> Year</label><select class="form-select" name="year" style="border-radius: 40px;"><?php foreach($years as $y): ?><option value="<?php echo $y; ?>" <?php echo $yearFilter == $y ? 'selected' : ''; ?>><?php echo $y; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label fw-bold small"><i class="fas fa-chart-line"></i> Month</label><select class="form-select" name="month" style="border-radius: 40px;"><?php for($m=1;$m<=12;$m++): ?><option value="<?php echo $m; ?>" <?php echo $monthFilter == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option><?php endfor; ?></select></div>
                <div class="col-md-3"><button type="submit" class="btn btn-success w-100" style="border-radius: 40px;"><i class="fas fa-filter"></i> Apply Filter</button></div>
                <div class="col-md-3"><a href="reports.php" class="btn btn-outline-secondary w-100" style="border-radius: 40px;"><i class="fas fa-sync-alt"></i> Reset</a></div>
            </form>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-lg-8"><div class="chart-card"><div class="chart-title"><i class="fas fa-chart-line"></i> Monthly Activities - <?php echo $yearFilter; ?></div><canvas id="monthlyChart" style="max-height: 350px;"></canvas></div></div>
            <div class="col-lg-4"><div class="chart-card"><div class="chart-title"><i class="fas fa-chart-pie"></i> Activity Status</div><canvas id="statusChart" style="max-height: 250px;"></canvas><div class="mt-3 text-center"><span class="badge bg-success">Approved: <?php echo $approvedActivities; ?></span> <span class="badge bg-warning">Pending: <?php echo $pendingActivities; ?></span> <span class="badge bg-danger">Rejected: <?php echo $rejectedActivities; ?></span></div></div></div>
        </div>

        <!-- Activity by Type & Daily Activity -->
        <div class="row">
            <div class="col-lg-6"><div class="chart-card"><div class="chart-title"><i class="fas fa-chart-bar"></i> Activities by Type</div><canvas id="typeChart" style="max-height: 300px;"></canvas></div></div>
            <div class="col-lg-6"><div class="chart-card"><div class="chart-title"><i class="fas fa-chart-line"></i> Daily Activity (Last 7 Days)</div><canvas id="dailyChart" style="max-height: 300px;"></canvas></div></div>
        </div>

        <!-- Top Users -->
        <div class="chart-card"><div class="chart-title"><i class="fas fa-trophy"></i> Top 10 Users by Points</div><div class="table-responsive"><table class="table table-hover"><thead><tr style="background: #f8f9fa;"><th>Rank</th><th>User</th><th>Username</th><th>Points</th><th>Activities</th></tr></thead><tbody><?php $rank = 1; while($user = $topUsers->fetch_assoc()): ?><tr><td style="text-align:center"><strong>#<?php echo $rank; ?></strong></td><td><div class="d-flex align-items-center"><div class="user-avatar-small"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div><?php echo htmlspecialchars($user['full_name']); ?></div></td><td>@<?php echo htmlspecialchars($user['username']); ?></td><td class="text-success fw-bold text-end"><?php echo number_format($user['points']); ?></td><td style="text-align:center"><?php echo $user['activities_count']; ?></td></tr><?php $rank++; endwhile; ?></tbody></table></div></div>

        <!-- Recent Activities -->
        <div class="table-card"><div class="chart-title"><i class="fas fa-history"></i> Recent Activities</div><div class="table-responsive"><table class="table table-hover"><thead><tr style="background: #f8f9fa;"><th>ID</th><th>User</th><th>Type</th><th>Description</th><th>Points</th><th>Status</th><th>Date</th></tr></thead><tbody><?php while($activity = $recentActivities->fetch_assoc()): ?><tr><td style="text-align:center"><?php echo $activity['id']; ?></td><td><strong><?php echo htmlspecialchars($activity['full_name']); ?></strong><br><small class="text-muted">@<?php echo htmlspecialchars($activity['username']); ?></small></td><td style="text-align:center"><?php echo htmlspecialchars($activity['activity_type']); ?></td><td><?php echo htmlspecialchars(substr($activity['description'], 0, 40)); ?>...</td><td class="text-success text-end">+<?php echo $activity['points_earned']; ?></td><td style="text-align:center"><span class="status-badge status-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span></td><td style="text-align:center"><small><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></small></td></tr><?php endwhile; ?></tbody></table></div></div>
    </div>

    <!-- Student Report Tab -->
    <div id="tab-student" class="tab-content">
        <div class="row">
            <!-- Student List Column -->
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5 class="mb-3"><i class="fas fa-users text-success"></i> Student List</h5>
                    <div class="student-list">
                        <?php foreach($allStudents as $student): ?>
                            <div class="student-item <?php echo ($studentId == $student['id']) ? 'active' : ''; ?>" onclick="location.href='?student_id=<?php echo $student['id']; ?>#student-tab'">
                                <div class="d-flex align-items-center">
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
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Student Report Column -->
            <div class="col-lg-8">
                <?php if ($studentId > 0 && $studentData): ?>
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                            <h5><i class="fas fa-chart-line text-success"></i> Report: <?php echo htmlspecialchars($studentData['full_name']); ?></h5>
                            <a href="?student_id=<?php echo $studentId; ?>&export=student_pdf" class="btn-export-student"><i class="fas fa-file-pdf"></i> Export PDF</a>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="p-2"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($studentData['email']); ?></div>
                                <div class="p-2"><i class="fas fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($studentData['created_at'])); ?></div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <div class="p-2 bg-success text-white rounded-pill d-inline-block px-3"><i class="fas fa-star"></i> Total Points: <?php echo $studentData['points']; ?></div>
                                <div class="p-2"><i class="fas fa-recycle"></i> Activities: <?php echo array_sum(array_column($studentMonthlyData, 'activities')); ?></div>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <h6><i class="fas fa-chart-bar"></i> Monthly Activity Trend</h6>
                            <canvas id="studentMonthlyChart" style="max-height: 300px;"></canvas>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6><i class="fas fa-chart-pie"></i> Material Breakdown</h6>
                                <canvas id="studentMaterialChart" style="max-height: 250px;"></canvas>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-table"></i> Material Summary</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Material</th><th>Count</th><th>Points</th></tr></thead>
                                        <tbody>
                                            <?php foreach($studentMaterialBreakdown as $m): ?>
                                                <tr><td><?php echo $m['activity_type']; ?></td><td><?php echo $m['count']; ?></td><td><?php echo $m['points']; ?></td></tr>
                                            <?php endforeach; ?>
                                            <?php if(empty($studentMaterialBreakdown)): ?>
                                                <tr><td colspan="3" class="text-center">No activities yet</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6><i class="fas fa-history"></i> Recent Activities</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Points</th></tr></thead>
                                    <tbody>
                                        <?php foreach($studentRecentActivities as $act): ?>
                                            <tr><td><?php echo date('M d, Y', strtotime($act['created_at'])); ?></td><td><?php echo $act['activity_type']; ?></td><td><?php echo substr($act['description'], 0, 30); ?>...</td><td>+<?php echo $act['points_earned']; ?></td></tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($studentRecentActivities)): ?>
                                            <tr><td colspan="4" class="text-center">No activities yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        new Chart(document.getElementById('studentMonthlyChart'), {
                            type: 'bar',
                            data: { labels: <?php echo json_encode(array_column($studentMonthlyData, 'month')); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode(array_column($studentMonthlyData, 'activities')); ?>, backgroundColor: 'rgba(76, 175, 80, 0.7)', borderColor: '#4CAF50', borderWidth: 1 }] },
                            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                        });
                        new Chart(document.getElementById('studentMaterialChart'), {
                            type: 'doughnut',
                            data: { labels: <?php echo json_encode(array_column($studentMaterialBreakdown, 'activity_type')); ?>, datasets: [{ data: <?php echo json_encode(array_column($studentMaterialBreakdown, 'count')); ?>, backgroundColor: ['#2196F3', '#4CAF50', '#FF9800', '#9C27B0', '#F44336', '#8BC34A', '#FFC107', '#607D8B'], borderWidth: 0 }] },
                            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
                        });
                    </script>
                    
                <?php else: ?>
                    <div class="chart-card text-center py-5">
                        <i class="fas fa-user-graduate" style="font-size: 64px; color: #ccc;"></i>
                        <h5 class="mt-3 text-muted">Select a student from the list</h5>
                        <p class="text-muted">Click on any student to view their detailed activity report with material breakdown chart</p>
                    </div>
                <?php endif; ?>
            </div>
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

    function showTab(tab) {
        document.getElementById('tab-system').classList.remove('active');
        document.getElementById('tab-student').classList.remove('active');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        
        document.getElementById('tab-' + tab).classList.add('active');
        event.target.classList.add('active');
    }
    
    // Check URL hash for tab selection
    if (window.location.hash === '#student-tab') {
        showTab('student');
    }

    // Monthly Chart
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($monthlyLabels); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode($monthlyCounts); ?>, backgroundColor: 'rgba(76, 175, 80, 0.7)', borderColor: '#4CAF50', borderWidth: 1, borderRadius: 8 }] },
        options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'top' } } }
    });

    // Status Chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: ['Approved', 'Pending', 'Rejected'], datasets: [{ data: [<?php echo $approvedActivities; ?>, <?php echo $pendingActivities; ?>, <?php echo $rejectedActivities; ?>], backgroundColor: ['#28a745', '#ffc107', '#dc3545'], borderWidth: 0, hoverOffset: 10 }] },
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, usePointStyle: true } } } }
    });

    // Type Chart
    new Chart(document.getElementById('typeChart'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($typeLabels); ?>, datasets: [{ label: 'Number of Activities', data: <?php echo json_encode($typeCounts); ?>, backgroundColor: 'rgba(33, 150, 243, 0.7)', borderColor: '#2196F3', borderWidth: 1, borderRadius: 8 }] },
        options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'top' } } }
    });

    // Daily Chart
    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: { labels: <?php echo json_encode($dailyLabels); ?>, datasets: [{ label: 'Activities', data: <?php echo json_encode($dailyCounts); ?>, backgroundColor: 'rgba(76, 175, 80, 0.2)', borderColor: '#4CAF50', borderWidth: 2, tension: 0.3, fill: true, pointBackgroundColor: '#4CAF50', pointRadius: 5 }] },
        options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { position: 'top' } } }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>