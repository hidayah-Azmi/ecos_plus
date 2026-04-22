<?php
// Simple debug script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Debug</h1>";

// Connect directly without using config file
$conn = new mysqli('localhost', 'root', '', 'ecos_plus');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Connected to database successfully!</h2>";

// Check all users
$result = $conn->query("SELECT id, username, email, password, role FROM users");
echo "<h3>All users in database:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Password Hash</th><th>Role</th></tr>";

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . substr($row['password'], 0, 30) . "...</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No users found!</td></tr>";
}
echo "</table>";

// Check if admin exists
$adminCheck = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($adminCheck->num_rows > 0) {
    $admin = $adminCheck->fetch_assoc();
    echo "<h3>Admin user found:</h3>";
    echo "Username: " . $admin['username'] . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    
    // Test password
    $testPassword = 'admin123';
    if (password_verify($testPassword, $admin['password'])) {
        echo "<span style='color:green'>✓ Password 'admin123' is CORRECT!</span><br>";
    } else {
        echo "<span style='color:red'>✗ Password 'admin123' is INCORRECT!</span><br>";
        
        // Fix the password
        $newHash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$newHash' WHERE username = 'admin'");
        echo "<span style='color:green'>✓ Password has been reset to 'admin123'</span><br>";
    }
} else {
    echo "<h3 style='color:red'>No admin user found! Creating one...</h3>";
    
    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, full_name, role, email_verified) 
            VALUES ('admin', 'admin@adab.umpsa.edu.my', '$newHash', 'System Administrator', 'admin', 1)";
    
    if ($conn->query($sql)) {
        echo "<span style='color:green'>✓ Admin user created with password 'admin123'</span><br>";
    } else {
        echo "<span style='color:red'>Error: " . $conn->error . "</span><br>";
    }
}

$conn->close();

echo "<br><a href='login.php' class='btn btn-primary'>Go to Login</a>";
echo "<br><br><a href='test_login.php' class='btn btn-success'>Test Login Directly</a>";
?>