<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Admin Login - Ecos+</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 800px; margin: 50px auto; }
        .card { border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .card-header { background: #4CAF50; color: white; border-radius: 15px 15px 0 0; }
        .btn-fix { background: #4CAF50; color: white; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
<div class='container'>
    <div class='card'>
        <div class='card-header'>
            <h4><i class='fas fa-key'></i> Admin Password Fix Tool</h4>
        </div>
        <div class='card-body'>";

$conn = getConnection();

// Check if admin exists
$checkAdmin = $conn->query("SELECT id, username, email, password FROM users WHERE username = 'admin' OR email = 'admin@adab.umpsa.edu.my'");

if ($checkAdmin->num_rows > 0) {
    $admin = $checkAdmin->fetch_assoc();
    echo "<div class='alert alert-info'>";
    echo "<strong>Admin user found:</strong><br>";
    echo "Username: " . htmlspecialchars($admin['username']) . "<br>";
    echo "Email: " . htmlspecialchars($admin['email']) . "<br>";
    echo "User ID: " . $admin['id'] . "<br>";
    echo "</div>";
    
    // Test current password
    $testPassword = 'admin123';
    if (password_verify($testPassword, $admin['password'])) {
        echo "<div class='alert alert-success'>✓ Password 'admin123' is already correct!</div>";
        echo "<div class='alert alert-info'>You can login with:<br>Username: admin<br>Password: admin123</div>";
    } else {
        echo "<div class='alert alert-warning'>✗ Current password is NOT 'admin123'</div>";
        
        // Update password
        $newHash = password_hash('admin123', PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $newHash, $admin['id']);
        
        if ($update->execute()) {
            echo "<div class='alert alert-success'>✓ Admin password has been reset to: <strong>admin123</strong></div>";
            
            // Verify new password
            $verify = $conn->query("SELECT password FROM users WHERE id = " . $admin['id']);
            $newData = $verify->fetch_assoc();
            if (password_verify('admin123', $newData['password'])) {
                echo "<div class='alert alert-success'>✓ Password verification passed!</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>✗ Failed to update password</div>";
        }
        $update->close();
    }
} else {
    echo "<div class='alert alert-warning'>No admin user found! Creating one...</div>";
    
    // Create admin user
    $username = 'admin';
    $email = 'admin@adab.umpsa.edu.my';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $role = 'admin';
    
    $insert = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, email_verified) VALUES (?, ?, ?, ?, ?, 1)");
    $insert->bind_param("sssss", $username, $email, $password, $full_name, $role);
    
    if ($insert->execute()) {
        echo "<div class='alert alert-success'>✓ Admin user created successfully!</div>";
        echo "<div class='alert alert-success'>Username: admin<br>Password: admin123</div>";
    } else {
        echo "<div class='alert alert-danger'>✗ Failed to create admin: " . $conn->error . "</div>";
    }
    $insert->close();
}

// Show all users for debugging
echo "<hr><h5>All Users in Database:</h5>";
$allUsers = $conn->query("SELECT id, username, email, role FROM users ORDER BY id");
if ($allUsers->num_rows > 0) {
    echo "<table class='table table-bordered table-sm'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    while($user = $allUsers->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();

echo "<hr>
<div class='text-center'>
    <a href='login.php' class='btn btn-success'>Go to Login Page</a>
    <a href='dashboard.php' class='btn btn-primary'>Go to Dashboard</a>
</div>
</div></div></div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>