<?php
// Simple script to create fresh admin
$conn = new mysqli('localhost', 'root', '', 'ecos_plus');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Delete all existing users (optional - be careful!)
// $conn->query("DELETE FROM users");

// Create fresh admin
$username = 'admin';
$email = 'admin@adab.umpsa.edu.my';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$full_name = 'System Administrator';
$role = 'admin';

$sql = "INSERT INTO users (username, email, password, full_name, role, email_verified) 
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
        password = VALUES(password),
        role = VALUES(role)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $username, $email, $password, $full_name, $role);

if ($stmt->execute()) {
    echo "<h2 style='color:green'>✓ Admin user created/updated successfully!</h2>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p><strong>Email:</strong> admin@adab.umpsa.edu.my</p>";
} else {
    echo "<h2 style='color:red'>Error: " . $stmt->error . "</h2>";
}

$stmt->close();
$conn->close();

echo "<br><a href='login.php' class='btn btn-primary'>Go to Login</a>";
echo "<br><a href='debug_login.php' class='btn btn-info'>Run Debug</a>";
?>