<?php
// Simple fix - no includes to avoid conflicts
$conn = new mysqli('localhost', 'root', '', 'ecos_plus');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Simple Admin Fix</h1>";

// Delete existing admin
$conn->query("DELETE FROM users WHERE username = 'admin'");
echo "<p>✓ Removed existing admin</p>";

// Create new admin
$password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, email, password, full_name, role, email_verified, points) 
        VALUES ('admin', 'admin@adab.umpsa.edu.my', '$password', 'System Administrator', 'admin', 1, 1000)";

if ($conn->query($sql)) {
    echo "<p style='color:green'>✓ Admin created successfully!</p>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
} else {
    echo "<p style='color:red'>Error: " . $conn->error . "</p>";
}

// Show all users
$result = $conn->query("SELECT id, username, email, role FROM users");
echo "<h3>Current Users:</h3>";
echo "<ul>";
while($row = $result->fetch_assoc()) {
    echo "<li>ID: " . $row['id'] . " - Username: " . $row['username'] . " - Role: " . $row['role'] . "</li>";
}
echo "</ul>";

$conn->close();

echo "<br><a href='login.php' class='btn btn-success'>Go to Login</a>";
?>