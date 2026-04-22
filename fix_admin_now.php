<?php
// Direct database fix
$conn = new mysqli('localhost', 'root', '', 'ecos_plus');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Fixing Admin Account</h1>";

// First, let's see what's in the users table
$result = $conn->query("SELECT id, username, email, role FROM users");
echo "<h3>Current Users:</h3>";
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " - Username: " . $row['username'] . " - Email: " . $row['email'] . " - Role: " . $row['role'] . "<br>";
}

// Delete any existing admin
$conn->query("DELETE FROM users WHERE username = 'admin'");
echo "<p>✓ Deleted existing admin</p>";

// Create new admin with password 'admin123'
$password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, email, password, full_name, role, email_verified, points) 
        VALUES ('admin', 'admin@adab.umpsa.edu.my', '$password', 'System Administrator', 'admin', 1, 1000)";

if ($conn->query($sql)) {
    echo "<p style='color:green'>✓ Admin user created successfully!</p>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
} else {
    echo "<p style='color:red'>Error: " . $conn->error . "</p>";
}

// Verify the admin works
$verify = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($verify->num_rows > 0) {
    $admin = $verify->fetch_assoc();
    echo "<h3>Verification:</h3>";
    echo "Admin found!<br>";
    echo "Password hash: " . $admin['password'] . "<br>";
    
    // Test password verification
    if (password_verify('admin123', $admin['password'])) {
        echo "<p style='color:green'>✓ Password 'admin123' works correctly!</p>";
    } else {
        echo "<p style='color:red'>✗ Password verification failed!</p>";
    }
}

$conn->close();

echo "<br><a href='login.php' class='btn btn-success'>Go to Login</a>";
?>