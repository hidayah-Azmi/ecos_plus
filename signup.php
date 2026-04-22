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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
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
        .bg-animation .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(76, 175, 80, 0.1);
            animation: float 20s infinite;
        }
        .bg-animation .circle:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
        }
        .bg-animation .circle:nth-child(2) {
            width: 500px;
            height: 500px;
            bottom: -200px;
            right: -200px;
            animation-delay: 5s;
        }
        .bg-animation .circle:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 20%;
            animation-delay: 10s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }

        /* Main Container */
        .signup-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Signup Card */
        .signup-card {
            max-width: 550px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            transition: transform 0.3s;
        }
        .signup-card:hover {
            transform: translateY(-5px);
        }

        /* Header Section */
        .signup-header {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            padding: 30px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .signup-header::before {
            content: '🌱';
            position: absolute;
            right: -20px;
            bottom: -20px;
            font-size: 100px;
            opacity: 0.1;
        }
        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        .logo-icon i {
            font-size: 30px;
            color: white;
        }
        .signup-header h2 {
            color: white;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .signup-header p {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }

        /* Body Section */
        .signup-body {
            padding: 30px;
        }

        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .input-group-custom {
            margin-bottom: 18px;
            position: relative;
        }
        .input-group-custom label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #555;
            margin-bottom: 6px;
        }
        .input-group-custom .input-icon {
            position: absolute;
            left: 14px;
            top: 38px;
            color: #4CAF50;
            font-size: 14px;
        }
        .input-group-custom input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 13px;
            transition: all 0.3s;
            background: white;
        }
        .input-group-custom input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76,175,80,0.1);
        }
        .input-group-custom .toggle-password {
            position: absolute;
            right: 14px;
            top: 38px;
            cursor: pointer;
            color: #999;
        }

        /* Email Hint */
        .email-hint {
            font-size: 10px;
            color: #999;
            margin-top: -10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .email-hint i {
            font-size: 10px;
        }

        /* Domain Notice */
        .domain-notice {
            background: #e8f5e9;
            border-radius: 10px;
            padding: 10px;
            font-size: 12px;
            color: #2e7d32;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .domain-notice i {
            font-size: 16px;
        }

        /* Terms Checkbox */
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        .terms-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: #4CAF50;
        }
        .terms-checkbox label {
            font-size: 12px;
            color: #666;
        }
        .terms-checkbox a {
            color: #4CAF50;
            text-decoration: none;
        }

        /* Signup Button */
        .btn-signup {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(76,175,80,0.4);
        }

        /* Divider */
        .divider {
            text-align: center;
            position: relative;
            margin: 20px 0;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
        }
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            font-size: 12px;
            color: #999;
        }

        /* Login Link */
        .login-link {
            text-align: center;
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

        /* University Badge */
        .university-badge {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .university-badge p {
            font-size: 10px;
            color: #999;
        }

        /* Alert */
        .alert-custom {
            border-radius: 12px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .signup-body {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>

    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <div class="logo-icon">
                    <i class="fas fa-seedling"></i>
                </div>
                <h2>Join Ecos+</h2>
                <p>Start your green journey with UMPSA community</p>
            </div>
            <div class="signup-body">
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
                    <div class="input-group-custom">
                        <label>Full Name *</label>
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="full_name" placeholder="Enter your full name" required>
                    </div>

                    <div class="input-group-custom">
                        <label>University Email *</label>
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="yourname@adab.umpsa.edu.my" required>
                    </div>

                    <div class="email-hint">
                        <i class="fas fa-info-circle"></i> Must be a valid @adab.umpsa.edu.my email address
                    </div>

                    <div class="input-group-custom">
                        <label>Phone Number (Optional)</label>
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone" placeholder="Enter your phone number">
                    </div>

                    <div class="input-group-custom">
                        <label>Password *</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" placeholder="Create a password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                    </div>

                    <div class="input-group-custom">
                        <label>Confirm Password *</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                    </div>

                    <div class="email-hint">
                        <i class="fas fa-shield-alt"></i> Password must be at least 6 characters
                    </div>

                    <div class="terms-checkbox">
                        <input type="checkbox" id="terms" required>
                        <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                    </div>

                    <button type="submit" class="btn-signup">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>

                <div class="divider">
                    <span>Already have an account?</span>
                </div>

                <div class="login-link">
                    <p><a href="login.php">Sign in to your account</a></p>
                </div>

                <div class="university-badge">
                    <p><i class="fas fa-university"></i> Universiti Malaysia Pahang Al-Sultan Abdullah</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, element) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                element.classList.remove('fa-eye');
                element.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                element.classList.remove('fa-eye-slash');
                element.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>