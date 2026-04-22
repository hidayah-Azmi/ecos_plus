<?php
session_start();

echo "<h1>Direct Login Test</h1>";

$conn = new mysqli('localhost', 'root', '', 'ecos_plus');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Try to login with admin
$username = 'admin';
$password = 'admin123';

echo "<h2>Testing login with: $username / $password</h2>";

$sql = "SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "<p>User found: " . $user['username'] . "</p>";
    
    if (password_verify($password, $user['password'])) {
        echo "<p style='color:green'>✓ Password is CORRECT!</p>";
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        
        echo "<p style='color:green'>✓ Session created successfully!</p>";
        echo "<p>Session data: <pre>";
        print_r($_SESSION);
        echo "</pre></p>";
        
        echo "<a href='dashboard.php' class='btn btn-success'>Go to Dashboard</a>";
    } else {
        echo "<p style='color:red'>✗ Password is INCORRECT!</p>";
        echo "<p>Password hash in DB: " . $user['password'] . "</p>";
        
        // Fix it
        $newHash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$newHash' WHERE id = " . $user['id']);
        echo "<p style='color:green'>✓ Password has been fixed! Try login again.</p>";
    }
} else {
    echo "<p style='color:red'>✗ User not found!</p>";
}

$stmt->close();
$conn->close();

echo "<br><a href='login.php' class='btn btn-primary'>Back to Login Page</a>";
?>