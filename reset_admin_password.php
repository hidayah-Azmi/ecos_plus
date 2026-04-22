<?php
// Simple admin password reset script
require_once 'config/database.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in both password fields";
        $messageType = "danger";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match";
        $messageType = "danger";
    } elseif (strlen($new_password) < 4) {
        $message = "Password must be at least 4 characters";
        $messageType = "danger";
    } else {
        $conn = getConnection();
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update admin password
        $sql = "UPDATE users SET password = ? WHERE username = 'admin' OR role = 'admin'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $hashed_password);
        
        if ($stmt->execute()) {
            $message = "Admin password has been reset successfully!";
            $messageType = "success";
            
            // Also create admin if doesn't exist
            if ($conn->affected_rows == 0) {
                $insert_sql = "INSERT INTO users (username, email, password, full_name, role, email_verified) 
                               VALUES ('admin', 'admin@adab.umpsa.edu.my', ?, 'System Administrator', 'admin', 1)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("s", $hashed_password);
                $insert_stmt->execute();
                $insert_stmt->close();
                $message = "Admin user created with new password!";
            }
        } else {
            $message = "Failed to reset password: " . $conn->error;
            $messageType = "danger";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Display current admin info - using a NEW connection
$conn2 = getConnection();
$adminCheck = $conn2->query("SELECT id, username, email, role FROM users WHERE username = 'admin' OR role = 'admin'");
$adminExists = $adminCheck->num_rows > 0;
$adminInfo = $adminExists ? $adminCheck->fetch_assoc() : null;
$conn2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .reset-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .reset-header i {
            font-size: 50px;
            margin-bottom: 10px;
        }
        .reset-body {
            padding: 30px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
        }
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: none;
        }
        .btn-reset {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: bold;
            width: 100%;
            color: white;
            font-size: 16px;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="reset-header">
            <i class="fas fa-key"></i>
            <h2>Admin Password Reset</h2>
            <p>Reset administrator account password</p>
        </div>
        <div class="reset-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h5><i class="fas fa-info-circle"></i> Current Admin Status</h5>
                <?php if ($adminExists): ?>
                    <p class="mb-0 text-success">
                        <i class="fas fa-check-circle"></i> Admin user exists<br>
                        <strong>Username:</strong> <?php echo htmlspecialchars($adminInfo['username']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($adminInfo['email']); ?><br>
                        <strong>Role:</strong> <?php echo $adminInfo['role']; ?>
                    </p>
                <?php else: ?>
                    <p class="mb-0 text-warning">
                        <i class="fas fa-exclamation-triangle"></i> No admin user found. A new one will be created.
                    </p>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">New Password</label>
                    <input type="password" class="form-control" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>
                <button type="submit" class="btn-reset">
                    <i class="fas fa-save"></i> Reset Admin Password
                </button>
            </form>
            
            <hr>
            <div class="text-center">
                <p class="text-muted">Default admin credentials after reset:</p>
                <code>Username: admin</code><br>
                <code>Password: (your new password)</code>
            </div>
            
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>