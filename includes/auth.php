<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================
// DATABASE CONNECTION
// =============================================
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $host = 'localhost';
        $user = 'root';
        $password = '';
        $database = 'ecos_plus';
        
        $conn = new mysqli($host, $user, $password, $database);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
    return $conn;
}

// =============================================
// DOMAIN VALIDATION - ONLY @adab.umpsa.edu.my
// =============================================

function validateEmailDomain($email) {
    $allowed_domain = '@adab.umpsa.edu.my';
    $domain = substr(strrchr($email, "@"), 0);
    return $domain === $allowed_domain;
}

// =============================================
// AUTHENTICATION FUNCTIONS
// =============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getConnection();
    $user_id = $_SESSION['user_id'];
    
    $sql = "SELECT id, email, username, full_name, phone, points, role, profile_image, bio, faculty, year_of_study, student_id, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    
    return $user;
}

function login($email, $password) {
    $conn = getConnection();
    
    $sql = "SELECT id, email, username, full_name, password, points, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_points'] = $user['points'];
            
            $stmt->close();
            return true;
        }
    }
    
    $stmt->close();
    return false;
}

function register($full_name, $email, $password, $phone = null, $student_id = null, $faculty = null, $year_of_study = null) {
    $conn = getConnection();
    
    // Validate email domain - ONLY @adab.umpsa.edu.my
    if (!validateEmailDomain($email)) {
        return ['success' => false, 'message' => 'Only @adab.umpsa.edu.my email addresses are allowed'];
    }
    
    // Check if email already exists
    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        return ['success' => false, 'message' => 'Email already exists'];
    }
    $checkStmt->close();
    
    // Generate username from email (remove @adab.umpsa.edu.my)
    $username = strstr($email, '@', true);
    // Clean username - remove special characters, only allow letters, numbers, underscore
    $username = preg_replace('/[^a-zA-Z0-9_]/', '_', $username);
    
    // Make sure username is unique
    $baseUsername = $username;
    $counter = 1;
    while (true) {
        $checkUsernameSql = "SELECT id FROM users WHERE username = ?";
        $checkUsernameStmt = $conn->prepare($checkUsernameSql);
        $checkUsernameStmt->bind_param("s", $username);
        $checkUsernameStmt->execute();
        $checkUsernameResult = $checkUsernameStmt->get_result();
        if ($checkUsernameResult->num_rows === 0) {
            $checkUsernameStmt->close();
            break;
        }
        $checkUsernameStmt->close();
        $username = $baseUsername . $counter;
        $counter++;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = 'user';
    
    // Prepare bio (default empty)
    $bio = '';
    $profile_image = null;
    
    // SQL includes all columns from your database structure
    $sql = "INSERT INTO users (username, email, password, full_name, bio, profile_image, phone, faculty, year_of_study, student_id, role, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $username, $email, $hashedPassword, $full_name, $bio, $profile_image, $phone, $faculty, $year_of_study, $student_id, $role);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Registration successful! You can now login.'];
    }
    
    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'message' => 'Registration failed: ' . $error];
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// =============================================
// POINTS & BADGES FUNCTIONS
// =============================================

function getUserPoints($user_id) {
    $conn = getConnection();
    $sql = "SELECT points FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? $user['points'] : 0;
}

function addUserPoints($user_id, $points, $activity_id = null) {
    $conn = getConnection();
    
    $sql = "UPDATE users SET points = points + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $points, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($activity_id && $result) {
        $updateActivity = "UPDATE activities SET points_earned = ? WHERE id = ?";
        $stmt2 = $conn->prepare($updateActivity);
        $stmt2->bind_param("ii", $points, $activity_id);
        $stmt2->execute();
        $stmt2->close();
        
        checkAndAwardBadges($user_id);
    }
    
    // Update session points
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
        $_SESSION['user_points'] = ($_SESSION['user_points'] ?? 0) + $points;
    }
    
    return $result;
}

function checkAndAwardBadges($user_id) {
    $conn = getConnection();
    
    $userSql = "SELECT points FROM users WHERE id = ?";
    $stmt = $conn->prepare($userSql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $activitySql = "SELECT COUNT(*) as total FROM activities WHERE user_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($activitySql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $activities = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Check if badges table exists
    $checkTableSql = "SHOW TABLES LIKE 'badges'";
    $tableCheck = $conn->query($checkTableSql);
    if ($tableCheck->num_rows === 0) {
        return; // No badges table
    }
    
    $badgeSql = "SELECT * FROM badges WHERE id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = ?)";
    $stmt = $conn->prepare($badgeSql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $availableBadges = $stmt->get_result();
    
    while ($badge = $availableBadges->fetch_assoc()) {
        $earnBadge = false;
        
        if ($badge['points_required'] > 0 && $user['points'] >= $badge['points_required']) {
            $earnBadge = true;
        }
        
        if ($badge['activities_required'] > 0 && $activities['total'] >= $badge['activities_required']) {
            $earnBadge = true;
        }
        
        if ($earnBadge) {
            $awardSql = "INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)";
            $awardStmt = $conn->prepare($awardSql);
            $awardStmt->bind_param("ii", $user_id, $badge['id']);
            $awardStmt->execute();
            $awardStmt->close();
        }
    }
    
    $stmt->close();
}

// =============================================
// USER RANK & LEADERBOARD
// =============================================

function getUserRank($user_id) {
    $conn = getConnection();
    $sql = "SELECT COUNT(*) + 1 as rank FROM users WHERE points > (SELECT points FROM users WHERE id = ?) AND role = 'user'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rank = $result->fetch_assoc();
    $stmt->close();
    return $rank['rank'] ?? 1;
}

function getLeaderboard($limit = 10) {
    $conn = getConnection();
    $sql = "SELECT id, full_name, email, points FROM users WHERE role = 'user' ORDER BY points DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $leaderboard = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $leaderboard;
}

// =============================================
// USER STATISTICS
// =============================================

function getUserStatistics($user_id) {
    $conn = getConnection();
    
    $stats = [
        'total_activities' => 0,
        'approved_activities' => 0,
        'pending_activities' => 0,
        'total_points' => 0,
        'co2_saved' => 0
    ];
    
    // Check if activities table exists
    $checkTableSql = "SHOW TABLES LIKE 'activities'";
    $tableCheck = $conn->query($checkTableSql);
    if ($tableCheck->num_rows === 0) {
        return $stats;
    }
    
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                COALESCE(SUM(points_earned), 0) as points
            FROM activities WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        $stats['total_activities'] = $data['total'] ?? 0;
        $stats['approved_activities'] = $data['approved'] ?? 0;
        $stats['pending_activities'] = $data['pending'] ?? 0;
        $stats['total_points'] = $data['points'] ?? 0;
        $stats['co2_saved'] = ($data['approved'] ?? 0) * 2.5;
    }
    $stmt->close();
    
    return $stats;
}
?>