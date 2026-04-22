<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token) || !validateResetToken($token)) {
    $error = 'Invalid or expired reset token';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $result = resetPassword($token, $password);
        if ($result['success']) {
            $success = $result['message'];
            $token = '';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
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
        .btn-reset {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            color: white;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #4CAF50;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="reset-header">
            <i class="fas fa-lock-open"></i>
            <h2>Reset Password</h2>
            <p>Create a new password</p>
        </div>
        <div class="reset-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-success">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <?php if (!$error && !$success && $token): ?>
                <form method="POST">
                    <div class="form-group mb-3">
                        <input type="password" class="form-control" name="password" placeholder="New Password" required>
                    </div>
                    <div class="form-group mb-3">
                        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
                    </div>
                    <button type="submit" class="btn-reset">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="login-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>