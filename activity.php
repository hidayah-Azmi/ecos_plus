<?php
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user for navbar
$user = getCurrentUser();
$user_initial = $user ? strtoupper(substr($user['full_name'], 0, 1)) : 'U';

// YOUR WORKING API KEY
require_once 'config/api_config.php';

$apiKeys = GEMINI_API_KEYS;
$response = null;

foreach ($apiKeys as $key) {

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=" . $key;

    // call API
    $result = file_get_contents($url);

    if ($result !== false) {
        $response = $result;
        break; // stop if success
    }
}

// Activity types with points and colors
$activity_types = [
    'Plastic' => ['points' => 10, 'icon' => '🥤', 'color' => '#2196F3', 'bg' => '#E3F2FD', 'description' => 'Plastic bottles, containers, bags'],
    'Paper' => ['points' => 5, 'icon' => '📄', 'color' => '#9C27B0', 'bg' => '#F3E5F5', 'description' => 'Newspapers, magazines, cardboard'],
    'Glass' => ['points' => 15, 'icon' => '🥃', 'color' => '#4CAF50', 'bg' => '#E8F5E9', 'description' => 'Glass bottles, jars'],
    'E-Waste' => ['points' => 25, 'icon' => '💻', 'color' => '#FF9800', 'bg' => '#FFF3E0', 'description' => 'Electronics, batteries, cables'],
    'Organic' => ['points' => 8, 'icon' => '🍎', 'color' => '#8BC34A', 'bg' => '#F1F8E9', 'description' => 'Food waste, garden waste'],
    'Metal' => ['points' => 20, 'icon' => '🥫', 'color' => '#607D8B', 'bg' => '#ECEFF1', 'description' => 'Aluminum cans, metal scraps'],
    'Cardboard' => ['points' => 5, 'icon' => '📦', 'color' => '#795548', 'bg' => '#EFEBE9', 'description' => 'Cardboard boxes, cartons'],
    'Textile' => ['points' => 12, 'icon' => '👕', 'color' => '#E91E63', 'bg' => '#FCE4EC', 'description' => 'Clothes, fabrics, shoes']
];

// Gemini detection function - FIXED MODEL
function detectWithGemini($image_base64) {
    $api_key = GEMINI_API_KEY;
    
    // FIXED: Using gemini-1.5-flash (working model)
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
    
    if ($http_code == 429) {
        return ['error' => 'limit_reached', 'message' => 'API limit reached. Please select category manually.'];
    }
    
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

// Handle AI detection and save from camera
if (isset($_POST['ai_detect'])) {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'No image from camera']);
        exit;
    }
    
    $image_data = file_get_contents($_FILES['image']['tmp_name']);
    $image_base64 = base64_encode($image_data);
    
    // Save camera image to file
    $upload_dir = 'assets/uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = 'camera_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.jpg';
    $image_path = $upload_dir . $filename;
    file_put_contents($image_path, $image_data);
    
    $result = detectWithGemini($image_base64);
    
    if (is_array($result) && isset($result['error'])) {
        echo json_encode([
            'success' => false, 
            'error' => $result['message'],
            'limit_reached' => true,
            'image_path' => $image_path
        ]);
        exit;
    }
    
    $category = $result;
    
    if ($category && isset($activity_types[$category])) {
        echo json_encode([
            'success' => true,
            'category' => $category,
            'description' => $activity_types[$category]['description'],
            'points' => $activity_types[$category]['points'],
            'icon' => $activity_types[$category]['icon'],
            'color' => $activity_types[$category]['color'],
            'image_path' => $image_path
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot identify item. Please select manually.',
            'image_path' => $image_path
        ]);
    }
    exit;
}

// Handle final form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_activity'])) {
    $activity_type = $_POST['activity_type'];
    $description = trim($_POST['description']);
    $quantity = intval($_POST['quantity']);
    $weight = floatval($_POST['weight']);
    $location = trim($_POST['location']);
    $image_path = $_POST['image_path'] ?? null;
    
    if (empty($activity_type)) {
        $error = 'Please select an activity type';
    } elseif (empty($image_path) || !file_exists($image_path)) {
        $error = 'Please take a photo first using the camera';
    } else {
        $base_points = $activity_types[$activity_type]['points'];
        $total_points = $base_points + ($quantity * 2) + ($weight * 5);
        
        $sql = "INSERT INTO activities (user_id, activity_type, description, points_earned, image_path, location, quantity, weight, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ississii", $user_id, $activity_type, $description, $total_points, $image_path, $location, $quantity, $weight);
        
        if ($stmt->execute()) {
            $success = "Activity submitted! You earned $total_points points (pending approval)";
            $_POST = array();
        } else {
            $error = "Submission failed";
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
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Recycle Activity - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%); font-family: 'Poppins', sans-serif; min-height: 100vh; }

        /* Premium Navbar */
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
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; align-items: center; justify-content: center; flex: 1; }
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
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .mobile-nav a i { width: 24px; }
        
        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .mobile-toggle { display: block; } 
            .user-info { display: none; } 
            .user-trigger { padding: 6px 12px; } 
            .navbar-container { padding: 0 15px; }
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        /* Main Container */
        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; }
        
        /* Hero Section */
        .hero-section { background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); border-radius: 25px; padding: 35px; margin-bottom: 30px; color: white; position: relative; overflow: hidden; }
        .hero-section::before { content: '♻️'; position: absolute; right: 20px; bottom: 10px; font-size: 100px; opacity: 0.1; pointer-events: none; }
        .hero-badge { background: rgba(255,255,255,0.2); display: inline-block; padding: 5px 15px; border-radius: 30px; font-size: 12px; margin-bottom: 15px; }
        .hero-title { font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .hero-subtitle { font-size: 14px; opacity: 0.9; margin-bottom: 0; }
        
        /* Cards */
        .form-card, .history-card { background: white; border-radius: 24px; margin-bottom: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); transition: transform 0.3s, box-shadow 0.3s; }
        .form-card:hover, .history-card:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(0,0,0,0.12); }
        
        /* Activity Options */
        .activity-option { cursor: pointer; border-radius: 16px; padding: 15px 10px; text-align: center; margin-bottom: 12px; transition: all 0.3s ease; background: #f8f9fa; border: 2px solid transparent; }
        .activity-option:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .activity-option.selected { border-color: #4CAF50; background: linear-gradient(135deg, #e8f5e9, #c8e6c9); }
        .activity-icon { font-size: 36px; margin-bottom: 8px; display: block; }
        .activity-name { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 4px; }
        .activity-points { font-size: 11px; color: #4CAF50; font-weight: 500; }
        
        /* AI Camera Button */
        .btn-ai-camera { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 16px; width: 100%; border-radius: 60px; font-weight: 600; font-size: 16px; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-ai-camera:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(102,126,234,0.4); color: white; }
        
        /* Floating Camera Button */
        .camera-floating-btn { position: fixed; bottom: 25px; right: 25px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.25); cursor: pointer; z-index: 1000; transition: all 0.3s; display: flex; align-items: center; justify-content: center; }
        .camera-floating-btn:hover { transform: scale(1.1); box-shadow: 0 8px 30px rgba(102,126,234,0.5); }
        
        /* Point Calculator */
        .point-calculator { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 20px; padding: 20px; margin-top: 20px; }
        .point-value { font-size: 36px; font-weight: 800; color: #4CAF50; }
        .point-label { font-size: 12px; color: #666; margin-top: 5px; }
        
        /* Form Inputs */
        .form-control-modern { border: 2px solid #e0e0e0; border-radius: 14px; padding: 12px 16px; font-size: 14px; transition: all 0.3s; }
        .form-control-modern:focus { border-color: #4CAF50; box-shadow: 0 0 0 3px rgba(76,175,80,0.1); outline: none; }
        
        /* Submit Button */
        .btn-submit { background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); color: white; border: none; padding: 14px; border-radius: 60px; font-weight: 600; font-size: 16px; transition: all 0.3s; width: 100%; margin-top: 20px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(76,175,80,0.3); }
        
        /* Activity Items */
        .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0; transition: all 0.3s; }
        .activity-item:hover { background: #f8f9fa; padding-left: 10px; border-radius: 12px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        /* Modal Styles */
        #video { width: 100%; border-radius: 16px; background: #000; max-height: 400px; object-fit: cover; }
        #canvas { display: none; }
        
        /* Animations */
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            .hero-title { font-size: 22px; }
            .activity-icon { font-size: 28px; }
            .camera-floating-btn { width: 50px; height: 50px; bottom: 20px; right: 20px; }
        }
    </style>
</head>
<body>

<!-- Premium Navbar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon">
                    <img src="assets/images/umpsa.png" alt="Logo" style="height:25px; object-fit:cover;">
                </div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom active"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Insights</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isset($user) && $user['role'] === 'admin'): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-dropdown">
                <div class="user-trigger">
                    <div class="user-avatar"><?php echo $user_initial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></span>
                        <span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($user['points'] ?? 0); ?> pts</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu-custom">
                    <a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a>
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
        <li><a href="activity.php" class="active"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Insights</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <?php if (isset($user) && $user['role'] === 'admin'): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="hero-badge"><i class="fas fa-robot"></i> AI-Powered Recycling</div>
        <div class="hero-title">Make a Difference Today</div>
        <div class="hero-subtitle">Snap a photo, and our AI will identify the recyclable item automatically. Every action earns you points and helps save our planet! 🌍</div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="form-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h4 class="mb-0"><i class="fas fa-magic text-success"></i> AI Recycling Assistant</h4>
                    <span class="badge bg-success"><i class="fas fa-bolt"></i> Real-time Detection</span>
                </div>
                <p class="text-muted small">Powered by Google Gemini AI - Just take a photo and we'll handle the rest</p>
                <hr>
                
                <button class="btn-ai-camera" data-bs-toggle="modal" data-bs-target="#cameraModal">
                    <i class="fas fa-camera fa-lg"></i> <i class="fas fa-magic"></i> Take Photo & AI Detect
                </button>
                
                <div id="capturedImagePreview" class="mt-4" style="display:none;">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-check-circle text-success fa-2x"></i>
                        <div><strong class="text-success">Photo Captured!</strong><br><small class="text-muted">AI is analyzing your item...</small></div>
                    </div>
                    <img id="previewImg" class="captured-preview mt-2">
                </div>
                
                <form method="POST" id="activityForm">
                    <input type="hidden" name="image_path" id="image_path">
                    
                    <div class="mt-4">
                        <label class="fw-bold mb-2"><i class="fas fa-tag"></i> Select Recycling Category</label>
                        <div class="row g-2" id="activityTypes">
                            <?php foreach ($activity_types as $type => $data): ?>
                            <div class="col-4 col-md-3">
                                <div class="activity-option" data-type="<?php echo $type; ?>" data-points="<?php echo $data['points']; ?>" style="background: <?php echo $data['bg']; ?>;">
                                    <span class="activity-icon"><?php echo $data['icon']; ?></span>
                                    <div class="activity-name"><?php echo $type; ?></div>
                                    <div class="activity-points">+<?php echo $data['points']; ?> pts</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="activity_type" id="selected_type" required>
                    </div>
                    
                    <div class="row mt-3 g-3">
                        <div class="col-md-6">
                            <label class="fw-bold"><i class="fas fa-box"></i> Quantity (items)</label>
                            <input type="number" class="form-control form-control-modern" name="quantity" id="quantity" min="1" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold"><i class="fas fa-weight-hanging"></i> Weight (kg)</label>
                            <input type="number" class="form-control form-control-modern" name="weight" id="weight" step="0.1" value="0">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="fw-bold"><i class="fas fa-map-marker-alt"></i> Location</label>
                        <input type="text" class="form-control form-control-modern" name="location" id="location" placeholder="e.g., Main Campus Recycling Center">
                    </div>
                    
                    <div class="mt-3">
                        <label class="fw-bold"><i class="fas fa-pen"></i> Description</label>
                        <textarea class="form-control form-control-modern" name="description" id="description" rows="2" placeholder="Describe your recycling activity..." required></textarea>
                    </div>
                    
                    <div class="point-calculator">
                        <div class="row align-items-center text-center">
                            <div class="col-4">
                                <div class="point-value" id="basePoints">0</div>
                                <div class="point-label">Base Points</div>
                            </div>
                            <div class="col-4">
                                <div class="point-value text-warning" id="quantityPoints">0</div>
                                <div class="point-label">+ Quantity Bonus</div>
                            </div>
                            <div class="col-4">
                                <div class="point-value text-info" id="weightPoints">0</div>
                                <div class="point-label">+ Weight Bonus</div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row align-items-center text-center">
                            <div class="col-12">
                                <div class="point-value text-success" id="totalPoints">0</div>
                                <div class="point-label">Total Points to Earn!</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_activity" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Activity
                    </button>
                </form>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success mt-3"><?php echo $success; ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-5">
            <div class="history-card">
                <h5><i class="fas fa-history text-success"></i> Recent Submissions</h5>
                <hr>
                <?php if ($recentActivities->num_rows > 0): ?>
                    <?php while($activity = $recentActivities->fetch_assoc()): ?>
                        <div class="activity-item">
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
                    <div class="text-center py-5">
                        <i class="fas fa-box-open" style="font-size: 64px; color: #ccc;"></i>
                        <p class="mt-3 text-muted">No submissions yet</p>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#cameraModal">
                            <i class="fas fa-camera"></i> Start Recycling
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card mt-3" style="border-radius: 24px; background: linear-gradient(135deg, #fff, #f8f9fa); padding: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
                <h6><i class="fas fa-lightbulb text-warning"></i> Pro Recycling Tips</h6>
                <hr>
                <div class="small">
                    <p><i class="fas fa-check-circle text-success"></i> <strong>Rinse containers</strong> before recycling to avoid contamination</p>
                    <p><i class="fas fa-check-circle text-success"></i> <strong>Remove labels</strong> from plastic bottles for better processing</p>
                    <p><i class="fas fa-check-circle text-success"></i> <strong>E-waste</strong> should be recycled separately at designated centers</p>
                    <p><i class="fas fa-check-circle text-success"></i> <strong>Flatten cardboard boxes</strong> to save space and energy</p>
                    <p><i class="fas fa-check-circle text-success"></i> <strong>Crush aluminum cans</strong> to save storage space</p>
                </div>
                <div class="mt-3 p-2 bg-light rounded text-center">
                    <small class="text-muted"><i class="fas fa-chart-line"></i> You've earned <?php echo number_format($user['points'] ?? 0); ?> total points!</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Camera Modal -->
<button class="camera-floating-btn" data-bs-toggle="modal" data-bs-target="#cameraModal">
    <i class="fas fa-camera fa-2x"></i>
</button>

<div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-header bg-success text-white" style="border-radius: 0;">
                <h5 class="modal-title" id="cameraModalLabel"><i class="fas fa-robot"></i> AI Camera Detection</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <video id="video" autoplay playsinline style="width: 100%; border-radius: 16px; background: #000;"></video>
                <canvas id="canvas" style="display: none;"></canvas>
                <div id="loading" class="mt-3" style="display:none;">
                    <div class="spinner-border text-success" role="status"></div>
                    <p class="mt-2">🤖 Gemini AI is analyzing your item...</p>
                </div>
                <div id="result" class="mt-3"></div>
            </div>
            <div class="modal-footer justify-content-center gap-2">
                <button class="btn btn-secondary" id="switchCamBtn"><i class="fas fa-sync-alt"></i> Switch Camera</button>
                <button class="btn btn-success" id="captureBtn"><i class="fas fa-camera"></i> Capture & Detect</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    let capturedImagePath = null;
    
    // Mobile menu toggle
    const mobileToggleBtn = document.getElementById('mobileToggleBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('show');
        });
    }
    
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileToggleBtn && mobileMenu.classList.contains('show') && 
            !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });
    
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
        if (!selectedType) {
            document.getElementById('basePoints').innerText = '0';
            document.getElementById('quantityPoints').innerText = '0';
            document.getElementById('weightPoints').innerText = '0';
            document.getElementById('totalPoints').innerText = '0';
            return;
        }
        let qty = parseInt(document.getElementById('quantity').value) || 0;
        let wt = parseFloat(document.getElementById('weight').value) || 0;
        let points = parseInt(document.querySelector('.activity-option.selected').dataset.points);
        let quantityPoints = qty * 2;
        let weightPoints = wt * 5;
        let total = points + quantityPoints + weightPoints;
        
        document.getElementById('basePoints').innerText = points;
        document.getElementById('quantityPoints').innerText = quantityPoints;
        document.getElementById('weightPoints').innerText = weightPoints;
        document.getElementById('totalPoints').innerText = total;
    }
    
    document.getElementById('quantity').oninput = updatePoints;
    document.getElementById('weight').oninput = updatePoints;
    
    async function startCamera() {
        if (stream) stream.getTracks().forEach(track => track.stop());
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: { exact: currentFacing } } 
            });
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
        if (stream) stream.getTracks().forEach(track => track.stop());
        document.getElementById('result').innerHTML = '';
        document.getElementById('loading').style.display = 'none';
    });
    
    document.getElementById('switchCamBtn').onclick = () => {
        currentFacing = currentFacing === 'environment' ? 'user' : 'environment';
        startCamera();
    };
    
    document.getElementById('captureBtn').onclick = function() {
        if (!video.videoWidth || !video.videoHeight) {
            alert('Please wait for camera to initialize');
            return;
        }
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        
        canvas.toBlob(async function(blob) {
            let formData = new FormData();
            formData.append('image', blob, 'photo.jpg');
            formData.append('ai_detect', '1');
            
            document.getElementById('loading').style.display = 'block';
            document.getElementById('result').innerHTML = '';
            
            try {
                let response = await fetch('activity.php', { method: 'POST', body: formData });
                let result = await response.json();
                
                document.getElementById('loading').style.display = 'none';
                
                if (result.success) {
                    capturedImagePath = result.image_path;
                    document.getElementById('image_path').value = result.image_path;
                    document.getElementById('previewImg').src = result.image_path + '?t=' + new Date().getTime();
                    document.getElementById('capturedImagePreview').style.display = 'block';
                    
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-success">
                            <div style="font-size: 40px;">${result.icon}</div>
                            <strong>AI Detected: ${result.category}</strong><br>
                            ${result.description}<br>
                            <strong class="text-success">+${result.points} points</strong><br>
                            <small class="text-muted">✨ Points will be added after admin approval</small>
                            <hr>
                            <button class="btn btn-success btn-sm" onclick="selectCategory('${result.category}')">Use This Category</button>
                            <button class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    `;
                    
                    selectCategory(result.category);
                    document.getElementById('description').value = `Recycled ${result.category.toLowerCase()} items detected by AI`;
                    
                } else {
                    if (result.image_path) {
                        document.getElementById('image_path').value = result.image_path;
                        document.getElementById('previewImg').src = result.image_path + '?t=' + new Date().getTime();
                        document.getElementById('capturedImagePreview').style.display = 'block';
                    }
                    document.getElementById('result').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> ${result.error}<br>
                            <small>Please select the category manually below.</small>
                            <hr>
                            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                    `;
                }
            } catch(err) {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
            }
        }, 'image/jpeg', 0.8);
    };
    
    function selectCategory(cat) {
        let options = document.querySelectorAll('.activity-option');
        for (let opt of options) {
            let textElem = opt.querySelector('.activity-name');
            if (textElem && textElem.innerText === cat) {
                opt.click();
                break;
            }
        }
    }
    
    // Form validation
    document.getElementById('activityForm').addEventListener('submit', function(e) {
        if (!document.getElementById('selected_type').value) {
            e.preventDefault();
            alert('Please select or let AI detect a recycling category');
            return false;
        }
        if (!document.getElementById('image_path').value) {
            e.preventDefault();
            alert('Please take a photo using the AI camera first');
            return false;
        }
        if (!document.getElementById('description').value.trim()) {
            e.preventDefault();
            alert('Please add a description');
            return false;
        }
    });
    
    updatePoints();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>