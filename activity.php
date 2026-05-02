<?php
$page_title = 'Recycle Activity';
$current_page = 'activity';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/notifications.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';
$unreadCount = getUnreadCount($user_id);
$notifications = getUserNotifications($user_id, 10);

// YOUR WORKING API KEY
define('GEMINI_API_KEY', 'AIzaSyDocphUtJI-RX0iYep1hIk0R5IoOU5s9vI');

// Activity types with points
$activity_types = [
    'Plastic' => ['points' => 10, 'icon' => '🥤', 'description' => 'Plastic bottles, containers, bags'],
    'Paper' => ['points' => 5, 'icon' => '📄', 'description' => 'Newspapers, magazines, cardboard'],
    'Glass' => ['points' => 15, 'icon' => '🥃', 'description' => 'Glass bottles, jars'],
    'E-Waste' => ['points' => 25, 'icon' => '💻', 'description' => 'Electronics, batteries, cables'],
    'Organic' => ['points' => 8, 'icon' => '🍎', 'description' => 'Food waste, garden waste'],
    'Metal' => ['points' => 20, 'icon' => '🥫', 'description' => 'Aluminum cans, metal scraps'],
    'Cardboard' => ['points' => 5, 'icon' => '📦', 'description' => 'Cardboard boxes, cartons'],
    'Textile' => ['points' => 12, 'icon' => '👕', 'description' => 'Clothes, fabrics, shoes']
];

// Gemini detection function
function detectWithGemini($image_base64) {
    $api_key = GEMINI_API_KEY;
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;
    
    $prompt = "What type of recyclable material is in this image? Answer with ONE WORD from: Plastic, Paper, Glass, E-Waste, Organic, Metal, Cardboard, Textile. Only answer the category name.";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => 'image/jpeg',
                            'data' => $image_base64
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $category = trim($result['candidates'][0]['content']['parts'][0]['text']);
            
            $categories = ['Plastic', 'Paper', 'Glass', 'E-Waste', 'Organic', 'Metal', 'Cardboard', 'Textile'];
            foreach ($categories as $cat) {
                if (stripos($category, $cat) !== false) {
                    return $cat;
                }
            }
        }
    }
    
    return null;
}

// Handle AI detection (AJAX) - Keeping your Gemini logic
if (isset($_POST['ai_detect'])) {
    header('Content-Type: application/json');
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'No image from camera']);
        exit;
    }
    
    $image_data = file_get_contents($_FILES['image']['tmp_name']);
    $image_base64 = base64_encode($image_data);
    
    $upload_dir = 'assets/uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = 'activity_' . $user_id . '_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.jpg';
    $image_path = $upload_dir . $filename;
    file_put_contents($image_path, $image_data);
    
    $category = detectWithGemini($image_base64);
    
    if ($category && isset($activity_types[$category])) {
        echo json_encode([
            'success' => true,
            'category' => $category,
            'description' => $activity_types[$category]['description'],
            'points' => $activity_types[$category]['points'],
            'icon' => $activity_types[$category]['icon'],
            'image_path' => $image_path
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not identify item', 'image_path' => $image_path]);
    }
    exit;
}

// Handle Form Submission - FIXING THE INSERT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_activity'])) {
    $activity_type = $_POST['activity_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $weight = floatval($_POST['weight'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $image_path = $_POST['image_path'] ?? ''; 
    
    if (empty($activity_type)) {
        $error = 'Please select an activity type';
    } elseif (empty($image_path)) {
        $error = 'Please take a photo first';
    } else {
        // Calculate Points
        $base_points = $activity_types[$activity_type]['points'] ?? 0;
        $total_points = $base_points + ($quantity * 2) + ($weight * 5);
        
        // Use 'd' for weight if it is a decimal in your DB
        $sql = "INSERT INTO activities (user_id, activity_type, description, points_earned, image_path, location, quantity, weight, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        
        // TYPES: i (int), s (string), s (string), i (int), s (string), s (string), i (int), d (double/float)
        $stmt->bind_param("ississid", $user_id, $activity_type, $description, $total_points, $image_path, $location, $quantity, $weight);
        
        if ($stmt->execute()) {
            $success = "Activity submitted! You earned $total_points points.";
            // Clear path so it doesn't resubmit same image
            $image_path = ""; 
        } else {
            // Detailed error reporting to help you find the problem
            $error = "DB Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get recent activities
$recentQuery = "SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recentQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentActivities = $stmt->get_result();
$stmt->close();

// DO NOT CLOSE CONNECTION HERE
// $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Activity - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0a2e1a 0%, #1a4a2a 50%, #0d3b1f 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            position: relative;
        }
        
        /* Nature Background Pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="rgba(76,175,80,0.03)" d="M20,30 Q25,25 30,30 Q35,25 40,30"/><circle cx="50" cy="20" r="2" fill="rgba(76,175,80,0.05)"/><circle cx="80" cy="50" r="3" fill="rgba(76,175,80,0.05)"/><circle cx="15" cy="70" r="2" fill="rgba(76,175,80,0.05)"/></svg>');
            background-repeat: repeat;
            pointer-events: none;
            z-index: 0;
        }
        
        /* Animated Leaves */
        @keyframes leafFloat1 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(-20px,-30px) rotate(10deg); } }
        @keyframes leafFloat2 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(20px,-20px) rotate(-10deg); } }
        @keyframes leafFloat3 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(-15px,25px) rotate(15deg); } }
        
        .leaf-bg { position: fixed; font-size: 45px; opacity: 0.06; pointer-events: none; z-index: 0; }
        .leaf-bg:nth-child(1) { top: 10%; left: 5%; animation: leafFloat1 12s ease-in-out infinite; }
        .leaf-bg:nth-child(2) { top: 70%; right: 8%; animation: leafFloat2 15s ease-in-out infinite; font-size: 60px; }
        .leaf-bg:nth-child(3) { top: 40%; left: 85%; animation: leafFloat3 18s ease-in-out infinite; }
        .leaf-bg:nth-child(4) { bottom: 15%; left: 10%; animation: leafFloat1 14s ease-in-out infinite; }
        .leaf-bg:nth-child(5) { top: 85%; right: 20%; animation: leafFloat2 11s ease-in-out infinite; }
        
        /* Navbar - SAME AS DASHBOARD */
        .navbar-custom { background: rgba(10, 46, 26, 0.95); backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); padding: 0; position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(76,175,80,0.3); }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #6B8E23, #4CAF50); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .logo-icon img { width: 30px; height: 30px; object-fit: cover; border-radius: 8px; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #8BC34A; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom:hover { background: rgba(107, 142, 35, 0.3); color: #8BC34A; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        
        /* Notification Styles */
        .notification-wrapper { position: relative; margin-right: 10px; }
        .notification-bell { background: rgba(255,255,255,0.1); border: none; color: white; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; transition: all 0.3s; font-size: 18px; }
        .notification-bell:hover { background: rgba(107, 142, 35, 0.5); transform: scale(1.05); }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #f44336; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 50%; min-width: 18px; text-align: center; }
        .notification-popup { position: fixed; bottom: 100px; right: 25px; width: 360px; max-width: calc(100vw - 40px); background: white; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); z-index: 1001; display: none; overflow: hidden; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .notification-popup.show { display: block; }
        .notification-popup-header { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; }
        .notification-popup-list { max-height: 400px; overflow-y: auto; }
        .notification-popup-item { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; display: flex; gap: 12px; cursor: pointer; transition: background 0.2s; }
        .notification-popup-item.unread { background: #e8f5e9; }
        .notification-popup-item:hover { background: #f5f5f5; }
        .notification-popup-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notification-popup-icon.approved { background: #d4edda; color: #155724; }
        .notification-popup-icon.rejected { background: #f8d7da; color: #721c24; }
        .notification-popup-icon.follower { background: #d1ecf1; color: #0c5460; }
        .notification-popup-content { flex: 1; }
        .notification-popup-title { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
        .notification-popup-message { font-size: 11px; color: #666; margin-bottom: 2px; }
        .notification-popup-time { font-size: 10px; color: #999; }
        .empty-notification { text-align: center; padding: 30px; color: #999; }
        
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
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; transition: all 0.2s; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:last-child { border-bottom: none; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #6B8E23; }
        .dropdown-item-custom i { width: 20px; color: #6B8E23; }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #0a2e1a; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(107, 142, 35, 0.3); color: #8BC34A; }
        .mobile-nav a i { width: 24px; }
        
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } .user-trigger { padding: 6px 12px; } .navbar-container { padding: 0 15px; } }
        @media (max-width: 576px) { .logo-text { display: none; } .notification-popup { right: 10px; left: 10px; width: auto; bottom: 80px; } }

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        /* Card Styles */
        .form-card, .history-card, .guide-card {
            background: rgba(255,255,255,0.95);
            border-radius: 24px;
            margin-bottom: 25px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .activity-option {
            cursor: pointer;
            border: 2px solid #e0e0e0;
            border-radius: 16px;
            padding: 12px 8px;
            text-align: center;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .activity-option:hover {
            border-color: #6B8E23;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(107,142,35,0.2);
        }
        .activity-option.selected {
            border-color: #6B8E23;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        }
        .activity-icon { font-size: 32px; margin-bottom: 5px; }
        .activity-name { font-size: 12px; font-weight: 600; }
        .activity-points { font-size: 10px; color: #6B8E23; margin-top: 3px; }
        
        .btn-camera {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px;
            width: 100%;
            border-radius: 60px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-camera:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); }
        .btn-submit {
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            border: none;
            padding: 14px;
            border-radius: 60px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(107,142,35,0.4); }
        
        .guide-tip {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 12px 15px;
            margin-bottom: 12px;
            border-left: 4px solid #6B8E23;
            transition: all 0.3s;
        }
        .guide-tip:hover { background: #e8f5e9; transform: translateX(5px); }
        .guide-tip i { font-size: 20px; margin-right: 12px; color: #6B8E23; }
        .guide-tip .tip-title { font-weight: 600; font-size: 14px; }
        .guide-tip .tip-desc { font-size: 12px; color: #666; margin-left: 32px; }
        
        .captured-preview {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 15px;
            text-align: center;
            margin: 15px 0;
        }
        .captured-preview img { max-width: 100%; max-height: 200px; border-radius: 12px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .total-points { font-size: 28px; font-weight: 700; color: #6B8E23; }
        
        #video { width: 100%; border-radius: 20px; background: #000; border: 3px solid #6B8E23; }
        #canvas { display: none; }
        .camera-trigger-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            color: white;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
            font-size: 28px;
        }
        .camera-trigger-btn:hover { transform: scale(1.1); background: linear-gradient(135deg, #8BC34A, #6B8E23); }
        
        @media (max-width: 768px) {
            .form-card, .history-card, .guide-card { padding: 18px; }
            .camera-trigger-btn { width: 55px; height: 55px; font-size: 24px; }
            .activity-icon { font-size: 26px; }
        }
    </style>
</head>
<body>

<!-- Animated Leaves Background -->
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-seedling"></i></div>
<div class="leaf-bg"><i class="fas fa-tree"></i></div>
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-recycle"></i></div>

<!-- Navigation Bar - SAME AS DASHBOARD -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon"><img src="assets/logo/12.png" alt="Logo"></div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom active"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div style="display: flex; align-items: center; gap: 10px;">

                <div class="user-dropdown">
                    <div class="user-trigger"><div class="user-avatar"><?php echo $navbar_initial; ?></div><div class="user-info"><span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></span><span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span></div><i class="fas fa-chevron-down dropdown-arrow"></i></div>
                    <div class="dropdown-menu-custom"><a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a><a href="rewards.php" class="dropdown-item-custom"><i class="fas fa-gift"></i> My Rewards</a><a href="history.php" class="dropdown-item-custom"><i class="fas fa-history"></i> Activity History</a><div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div><a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
                </div>
            </div>
            <button class="mobile-toggle" id="mobileToggleBtn"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="activity.php" class="active"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<!-- Notification Popup -->
<div class="notification-popup" id="notificationPopup">
    <div class="notification-popup-header"><span><i class="fas fa-bell"></i> Notifications</span><button id="markAllReadBtn" style="background: none; border: none; color: white; font-size: 12px;">Mark all read</button></div>
    <div class="notification-popup-list" id="notificationList"><div class="empty-notification">Loading...</div></div>
</div>

<div class="container-custom">
    <div class="row">
        <!-- Left Column - Form -->
        <div class="col-lg-7">
            <div class="form-card">
                <h4 class="mb-2"><i class="fas fa-robot" style="color: #6B8E23;"></i> AI Recycling Assistant</h4>
                <p class="text-muted small">📸 Take a photo, AI will detect automatically</p>
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <button class="btn-camera" data-bs-toggle="modal" data-bs-target="#cameraModal">
                    <i class="fas fa-camera"></i> <i class="fas fa-magic"></i> Open Camera & Take Photo
                </button>

                <div id="capturedPreview" class="captured-preview" style="display: none;">
                    <label class="fw-bold">📸 Captured Photo:</label>
                    <img id="previewImg" src="" alt="Captured">
                    <p class="text-success small mt-2">✓ Photo saved successfully</p>
                </div>

                <form method="POST" id="activityForm">
                    <input type="hidden" name="image_path" id="image_path">

                    <div class="mt-3">
                        <label class="fw-bold">Select Material Type</label>
                        <div class="row" id="activityTypes">
                            <?php foreach ($activity_types as $type => $data): ?>
                            <div class="col-4 col-md-3">
                                <div class="activity-option" data-type="<?php echo $type; ?>" data-points="<?php echo $data['points']; ?>">
                                    <div class="activity-icon"><?php echo $data['icon']; ?></div>
                                    <div class="activity-name"><?php echo $type; ?></div>
                                    <div class="activity-points">+<?php echo $data['points']; ?> pts</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="activity_type" id="selected_type" required>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="fw-bold">Quantity (items)</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" min="1" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold">Weight (kg)</label>
                            <input type="number" class="form-control" name="weight" id="weight" step="0.1" value="0">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="fw-bold"><i class="fas fa-map-marker-alt"></i> Location</label>
                        <input type="text" class="form-control" name="location" id="location" placeholder="e.g., Main Campus Recycling Center">
                    </div>

                    <div class="mt-3">
                        <label class="fw-bold">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="2" required></textarea>
                    </div>

                    <div class="mt-3 text-center">
                        <h4>Total Points: <span id="totalPoints" class="total-points">0</span></h4>
                    </div>

                    <button type="submit" name="submit_activity" class="btn-submit mt-2">
                        <i class="fas fa-paper-plane"></i> Submit Activity
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column - Recent Activities & Guide -->
        <div class="col-lg-5">
            <!-- Guide Section -->
            <div class="guide-card">
                <h5><i class="fas fa-recycle" style="color: #6B8E23;"></i> How to Recycle Correctly</h5>
                <hr>
                
                <div class="guide-tip">
                    <i class="fas fa-bottle-water"></i>
                    <span class="tip-title">🥤 Plastic</span>
                    <div class="tip-desc">Rinse bottles, remove caps, flatten to save space</div>
                </div>
                <div class="guide-tip">
                    <i class="fas fa-newspaper"></i>
                    <span class="tip-title">📄 Paper</span>
                    <div class="tip-desc">Remove plastic windows, keep dry and clean</div>
                </div>
                <div class="guide-tip">
                    <i class="fas fa-wine-bottle"></i>
                    <span class="tip-title">🥃 Glass</span>
                    <div class="tip-desc">Remove lids, rinse well, sort by color if required</div>
                </div>
                <div class="guide-tip">
                    <i class="fas fa-laptop"></i>
                    <span class="tip-title">💻 E-Waste</span>
                    <div class="tip-desc">Remove batteries, take to special collection points</div>
                </div>
                <div class="guide-tip">
                    <i class="fas fa-apple-alt"></i>
                    <span class="tip-title">🍎 Organic</span>
                    <div class="tip-desc">Remove plastic stickers, compost at home if possible</div>
                </div>
                <div class="guide-tip">
                    <i class="fas fa-box"></i>
                    <span class="tip-title">📦 Cardboard</span>
                    <div class="tip-desc">Flatten boxes, remove tape and labels</div>
                </div>
                
                <hr>
                <div class="text-center">
                    <i class="fas fa-info-circle text-success"></i>
                    <p class="small text-muted mt-2">♻️ Proper recycling helps save our planet!<br>Wash items before recycling to prevent contamination.</p>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="history-card">
                <h5><i class="fas fa-history" style="color: #6B8E23;"></i> Recent Activities</h5>
                <hr>
                <?php if ($recentActivities->num_rows > 0): ?>
                    <?php while($activity = $recentActivities->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($activity['activity_type']); ?></strong>
                                <p class="mb-0 text-muted small"><?php echo htmlspecialchars(substr($activity['description'], 0, 40)); ?>...</p>
                                <small class="text-success"><i class="fas fa-star"></i> +<?php echo $activity['points_earned']; ?> pts</small>
                            </div>
                            <div class="text-end">
                                <span class="status-badge status-<?php echo $activity['status']; ?>">
                                    <?php echo ucfirst($activity['status']); ?>
                                </span>
                                <br>
                                <small class="text-muted"><?php echo date('M d', strtotime($activity['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-box-open" style="font-size: 40px; color: #ccc;"></i>
                        <p class="mt-2 text-muted">No activities yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Camera Trigger Button -->
<button class="camera-trigger-btn" data-bs-toggle="modal" data-bs-target="#cameraModal">
    <i class="fas fa-camera"></i>
</button>

<!-- Camera Modal -->
<div class="modal fade" id="cameraModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 30px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; border: none;">
                <h5 class="modal-title"><i class="fas fa-camera-retro"></i> Take a Photo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-3">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas"></canvas>
                <div id="loading" class="mt-3" style="display: none;">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">🤖 Gemini AI analyzing your item...</p>
                </div>
                <div id="result" class="mt-3"></div>
            </div>
            <div class="modal-footer d-flex justify-content-center gap-2">
                <button class="btn btn-secondary" id="switchCamBtn"><i class="fas fa-sync-alt"></i> Switch Camera</button>
                <button class="btn btn-success" id="captureBtn" style="background: linear-gradient(135deg, #6B8E23, #4CAF50); padding: 10px 25px; border-radius: 50px;"><i class="fas fa-camera"></i> Capture & Detect</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    let selectedType = null;
    let currentFacing = 'environment';
    let stream = null;
    let video = document.getElementById('video');
    let canvas = document.getElementById('canvas');
    let ctx = canvas.getContext('2d');

    // Activity selection
    document.querySelectorAll('.activity-option').forEach(opt => {
        opt.onclick = function() {
            document.querySelectorAll('.activity-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            selectedType = this.dataset.type;
            document.getElementById('selected_type').value = selectedType;
            updatePoints();
        }
    });

    function updatePoints() {
        if (!selectedType) return;
        let qty = document.getElementById('quantity').value || 0;
        let wt = document.getElementById('weight').value || 0;
        let points = parseInt(document.querySelector('.activity-option.selected').dataset.points);
        let total = points + (qty * 2) + (wt * 5);
        document.getElementById('totalPoints').innerText = total;
    }

    document.getElementById('quantity').oninput = updatePoints;
    document.getElementById('weight').oninput = updatePoints;

    // Camera functions
    async function startCamera() {
        if (stream) stream.getTracks().forEach(t => t.stop());
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { exact: currentFacing } } });
            video.srcObject = stream;
        } catch(e) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
            } catch(err) {
                alert('Cannot access camera. Please check permissions.');
            }
        }
    }

    document.getElementById('cameraModal').addEventListener('shown.bs.modal', () => startCamera());
    document.getElementById('cameraModal').addEventListener('hidden.bs.modal', () => {
        if (stream) stream.getTracks().forEach(t => t.stop());
        document.getElementById('result').innerHTML = '';
        document.getElementById('loading').style.display = 'none';
    });

    document.getElementById('switchCamBtn').onclick = () => {
        currentFacing = currentFacing === 'environment' ? 'user' : 'environment';
        startCamera();
    };

    document.getElementById('captureBtn').onclick = function() {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(async function(blob) {
            let formData = new FormData();
            formData.append('image', blob, 'photo.jpg');
            formData.append('ai_detect', '1');

            document.getElementById('loading').style.display = 'block';
            document.getElementById('result').innerHTML = '';

            let response = await fetch('activity.php', { method: 'POST', body: formData });
            let result = await response.json();

            document.getElementById('loading').style.display = 'none';

            if (result.success) {
                document.getElementById('image_path').value = result.image_path;
                let previewImg = document.getElementById('previewImg');
                previewImg.src = URL.createObjectURL(blob);
                document.getElementById('capturedPreview').style.display = 'block';
                
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-success text-center">
                        <div style="font-size: 40px;">${result.icon}</div>
                        <strong>✨ Gemini AI Detected: ${result.category} ✨</strong><br>
                        ${result.description}<br>
                        <strong class="text-success">+${result.points} points</strong><br>
                        <button class="btn btn-sm btn-success mt-2" onclick="selectCategory('${result.category}')">✅ Use This Category</button>
                        <button class="btn btn-sm btn-primary mt-2 ms-2" data-bs-dismiss="modal">📋 Close & Submit</button>
                    </div>
                `;
            } else {
                document.getElementById('result').innerHTML = `
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle"></i><br>
                        <strong>${result.error}</strong><br>
                        <button class="btn btn-sm btn-secondary mt-2" data-bs-dismiss="modal">Try Again</button>
                    </div>
                `;
            }
        }, 'image/jpeg');
    };

    function selectCategory(cat) {
        let options = document.querySelectorAll('.activity-option');
        for (let opt of options) {
            if (opt.querySelector('.activity-name').innerText === cat) {
                opt.click();
                break;
            }
        }
        bootstrap.Modal.getInstance(document.getElementById('cameraModal')).hide();
        document.getElementById('description').value = `Gemini AI detected: ${cat} - `;
    }

    updatePoints();

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

    // Notification System
    const notificationBell = document.getElementById('notificationBell');
    const notificationPopup = document.getElementById('notificationPopup');
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    let isPopupOpen = false;
    
    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            if (isPopupOpen) { notificationPopup.classList.remove('show'); isPopupOpen = false; }
            else { loadNotifications(); notificationPopup.classList.add('show'); isPopupOpen = true; }
        });
    }
    document.addEventListener('click', function(e) { if (notificationPopup && notificationBell && !notificationPopup.contains(e.target) && !notificationBell.contains(e.target)) { notificationPopup.classList.remove('show'); isPopupOpen = false; } });
    
    function loadNotifications() {
        fetch('ajax/get_notifications.php').then(r => r.json()).then(data => { if (data.success) { displayNotifications(data.notifications); updateBadgeCount(data.unread_count); } else { notificationList.innerHTML = '<div class="empty-notification">Failed to load</div>'; } }).catch(() => { notificationList.innerHTML = '<div class="empty-notification">Error loading</div>'; });
    }
    
    function displayNotifications(notifications) {
        if (!notifications || notifications.length === 0) { notificationList.innerHTML = '<div class="empty-notification"><i class="fas fa-bell-slash fa-2x mb-2"></i><p>No notifications yet</p></div>'; return; }
        let html = '';
        notifications.forEach(notif => {
            let iconClass = '', iconHtml = '';
            if (notif.type === 'activity_approved') { iconClass = 'approved'; iconHtml = '<i class="fas fa-check-circle"></i>'; }
            else if (notif.type === 'activity_rejected') { iconClass = 'rejected'; iconHtml = '<i class="fas fa-times-circle"></i>'; }
            else if (notif.type === 'new_follower') { iconClass = 'follower'; iconHtml = '<i class="fas fa-user-plus"></i>'; }
            else if (notif.type === 'post_like') { iconClass = 'approved'; iconHtml = '<i class="fas fa-heart"></i>'; }
            else if (notif.type === 'new_comment') { iconClass = 'follower'; iconHtml = '<i class="fas fa-comment"></i>'; }
            else { iconClass = 'approved'; iconHtml = '<i class="fas fa-info-circle"></i>'; }
            html += `<div class="notification-popup-item ${notif.is_read == 0 ? 'unread' : ''}" onclick="markNotificationRead(${notif.id})"><div class="notification-popup-icon ${iconClass}">${iconHtml}</div><div class="notification-popup-content"><div class="notification-popup-title">${escapeHtml(notif.title)}</div><div class="notification-popup-message">${escapeHtml(notif.message)}</div><div class="notification-popup-time">${notif.time_ago}</div></div></div>`;
        });
        notificationList.innerHTML = html;
    }
    
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    function updateBadgeCount(count) { if (notificationCount) { notificationCount.textContent = count; notificationCount.style.display = count > 0 ? 'inline-block' : 'none'; } }
    function markNotificationRead(id) { fetch('ajax/mark_notification_read.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + id }).then(() => loadNotifications()); }
    if (markAllReadBtn) { markAllReadBtn.onclick = () => { fetch('ajax/mark_all_read.php', { method: 'POST' }).then(() => loadNotifications()); }; }
    setInterval(() => { fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {}); }, 30000);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>