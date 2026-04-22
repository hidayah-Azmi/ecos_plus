<?php
$page_title = 'Sign Up';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $result = register($full_name, $email, $password, $phone);
        
        if ($result['success']) {
            $success = $result['message'];
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Sign Up - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a4d2e 0%, #0f0c29 50%, #2E7D32 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        /* Floating Trees/Bushes */
        .bg-animation .tree {
            position: absolute;
            bottom: -50px;
            width: 200px;
            height: 200px;
            background: rgba(76, 175, 80, 0.08);
            border-radius: 50%;
            animation: sway 10s infinite ease-in-out;
        }

        .bg-animation .tree:nth-child(1) {
            left: -80px;
            width: 250px;
            height: 250px;
            animation-delay: 0s;
        }

        .bg-animation .tree:nth-child(2) {
            right: -100px;
            bottom: -30px;
            width: 300px;
            height: 300px;
            animation-delay: 3s;
            background: rgba(139, 195, 74, 0.06);
        }

        .bg-animation .tree:nth-child(3) {
            left: 20%;
            bottom: -60px;
            width: 180px;
            height: 180px;
            animation-delay: 6s;
            background: rgba(76, 175, 80, 0.05);
        }

        @keyframes sway {
            0%, 100% {
                transform: translateX(0) rotate(0deg);
            }
            50% {
                transform: translateX(30px) rotate(5deg);
            }
        }

        /* Floating Icons */
        .bg-animation .float-icon {
            position: absolute;
            font-size: 35px;
            opacity: 0.1;
            animation: floatIcon 18s infinite;
        }

        .bg-animation .float-icon:nth-child(4) {
            top: 15%;
            left: 10%;
            animation-delay: 0s;
            font-size: 50px;
        }

        .bg-animation .float-icon:nth-child(5) {
            top: 60%;
            right: 8%;
            animation-delay: 5s;
            font-size: 45px;
        }

        .bg-animation .float-icon:nth-child(6) {
            top: 30%;
            right: 20%;
            animation-delay: 10s;
            font-size: 40px;
        }

        .bg-animation .float-icon:nth-child(7) {
            bottom: 20%;
            left: 15%;
            animation-delay: 7s;
            font-size: 55px;
        }

        @keyframes floatIcon {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.05;
            }
            50% {
                transform: translateY(-50px) rotate(180deg);
                opacity: 0.15;
            }
        }

        /* Main Container */
        .register-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Register Card */
        .register-card {
            max-width: 550px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .register-card:hover {
            transform: translateY(-5px);
        }

        /* Header Section */
        .register-header {
            background: linear-gradient(135deg, #1B5E20 0%, #4CAF50 50%, #81C784 100%);
            padding: 35px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '🌍';
            position: absolute;
            right: -20px;
            bottom: -20px;
            font-size: 100px;
            opacity: 0.15;
        }

        .register-header::after {
            content: '♻️';
            position: absolute;
            left: -20px;
            top: -20px;
            font-size: 90px;
            opacity: 0.15;
        }

        .logo-icon {
            width: 75px;
            height: 75px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            backdrop-filter: blur(5px);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-8px);
            }
        }

        .logo-icon i {
            font-size: 40px;
            color: white;
        }

        .register-header h2 {
            color: white;
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .register-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
        }

        /* Body Section */
        .register-body {
            padding: 35px;
        }

        /* Form Inputs - FIXED */
        .input-group-custom {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group-custom .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #4CAF50;
            font-size: 16px;
            z-index: 2;
            pointer-events: none;
        }

        .input-group-custom input {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border: 2px solid #e0e0e0;
            border-radius: 60px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .input-group-custom input:focus {
            outline: none;
            border-color: #4CAF50;
            background: white;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .input-group-custom label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
            margin-left: 5px;
        }

        /* Email Hint - FIXED */
        .email-hint {
            font-size: 11px;
            color: #4CAF50;
            margin-top: -10px;
            margin-bottom: 15px;
            margin-left: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .email-hint i {
            font-size: 12px;
        }

        /* Domain Notice - FIXED */
        .domain-notice {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 60px;
            padding: 12px 20px;
            font-size: 13px;
            color: #2E7D32;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .domain-notice i {
            font-size: 16px;
        }

        /* Register Button */
        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
            border: none;
            border-radius: 60px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
        }

        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-register:hover::before {
            left: 100%;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .login-link p {
            font-size: 13px;
            color: #666;
        }

        .login-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Alert */
        .alert-custom {
            border-radius: 60px;
            padding: 12px 20px;
            margin-bottom: 20px;
            font-size: 13px;
            border: none;
        }

        /* University Badge */
        .university-badge {
            text-align: center;
            margin-top: 20px;
        }

        .university-badge p {
            font-size: 10px;
            color: #999;
        }

        /* Terms Checkbox */
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0 10px;
        }

        .terms-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: #4CAF50;
            cursor: pointer;
        }

        .terms-checkbox label {
            font-size: 12px;
            color: #666;
            cursor: pointer;
        }

        .terms-checkbox a {
            color: #4CAF50;
            text-decoration: none;
        }

        /* Password Hint */
        .password-hint {
            font-size: 11px;
            color: #666;
            margin-top: -10px;
            margin-bottom: 15px;
            margin-left: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 576px) {
            .register-body {
                padding: 25px;
            }
            .register-header {
                padding: 25px;
            }
            .logo-icon {
                width: 60px;
                height: 60px;
            }
            .logo-icon i {
                font-size: 30px;
            }
            .input-group-custom .input-icon {
                left: 15px;
            }
            .input-group-custom input {
                padding: 12px 15px 12px 45px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="tree"></div>
        <div class="tree"></div>
        <div class="tree"></div>
        <div class="float-icon"><i class="fas fa-recycle"></i></div>
        <div class="float-icon"><i class="fas fa-leaf"></i></div>
        <div class="float-icon"><i class="fas fa-seedling"></i></div>
        <div class="float-icon"><i class="fas fa-tree"></i></div>
    </div>

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo-icon">
                    <i class="fas fa-seedling"></i>
                </div>
                <h2>Join Ecos+</h2>
                <p>Start your green journey with UMPSA community</p>
            </div>
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-custom">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Domain Notice -->
                <div class="domain-notice">
                    <i class="fas fa-envelope"></i>
                    <strong>Only @adab.umpsa.edu.my email addresses are allowed</strong>
                </div>

                <form method="POST">
                    <!-- Full Name -->
                    <div class="input-group-custom">
                        <label>Full Name</label>
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="full_name" placeholder="Enter your full name" required>
                    </div>

                    <!-- University Email -->
                    <div class="input-group-custom">
                        <label>University Email</label>
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="yourname@adab.umpsa.edu.my" required>
                    </div>

                    <div class="email-hint">
                        <i class="fas fa-info-circle"></i> Must be a valid @adab.umpsa.edu.my email address
                    </div>

                    <!-- Phone Number -->
                    <div class="input-group-custom">
                        <label>Phone Number (Optional)</label>
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone" placeholder="Enter your phone number">
                    </div>

                    <!-- Password -->
                    <div class="input-group-custom">
                        <label>Password</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" placeholder="Create a password" required>
                    </div>

                    <div class="password-hint">
                        <i class="fas fa-shield-alt"></i> Password must be at least 6 characters
                    </div>

                    <!-- Confirm Password -->
                    <div class="input-group-custom">
                        <label>Confirm Password</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                    </div>

                    <!-- Terms Checkbox -->
                    <div class="terms-checkbox">
                        <input type="checkbox" id="terms" required>
                        <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                    </div>

                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>

                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                </div>

                <div class="university-badge">
                    <p><i class="fas fa-university"></i> Universiti Malaysia Pahang Al-Sultan Abdullah</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>