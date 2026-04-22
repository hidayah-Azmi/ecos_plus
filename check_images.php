<?php
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

echo "<h1>Image Path Debug</h1>";

// Get activities with images
$query = "SELECT id, activity_type, image_path, created_at FROM activities WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activities = $stmt->get_result();
$stmt->close();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #4CAF50; color: white;'>";
echo "<th>ID</th><th>Type</th><th>Image Path (Database)</th><th>Full Path Check</th><th>File Exists?</th><th>Preview</th>";
echo "</tr>";

while($activity = $activities->fetch_assoc()) {
    $db_path = $activity['image_path'];
    
    // Try different possible paths
    $paths_to_try = [
        '../' . $db_path,
        $db_path,
        '../assets/uploads/' . basename($db_path),
        'assets/uploads/' . basename($db_path)
    ];
    
    $found_path = '';
    $exists = false;
    
    foreach ($paths_to_try as $path) {
        if (file_exists($path)) {
            $found_path = $path;
            $exists = true;
            break;
        }
    }
    
    echo "<tr>";
    echo "<td>{$activity['id']}</td>";
    echo "<td>{$activity['activity_type']}</td>";
    echo "<td><code>" . htmlspecialchars($db_path) . "</code></td>";
    echo "<td><code>" . htmlspecialchars($found_path) . "</code></td>";
    echo "<td style='color: " . ($exists ? 'green' : 'red') . "'>" . ($exists ? 'YES' : 'NO') . "</td>";
    echo "<td>";
    if ($exists) {
        echo "<img src='" . $found_path . "' style='max-width: 100px; max-height: 100px; border-radius: 10px;'>";
    } else {
        echo "No image found";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Also check the uploads folder
echo "<h2>Files in assets/uploads/</h2>";
$upload_dir = 'assets/uploads/';
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>Folder assets/uploads/ does NOT exist!</p>";
}

$conn->close();
?>