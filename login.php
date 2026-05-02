<?php
$page_title = 'Login';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        if (login($email, $password)) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Login - Ecos+</title>
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
            background: linear-gradient(135deg, #0f0c29 0%, #1a4d2e 50%, #24243e 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background with Floating Elements */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .bg-animation .leaf {
            position: absolute;
            background: rgba(76, 175, 80, 0.15);
            border-radius: 0 100% 0 100%;
            transform: rotate(45deg);
            animation: floatLeaf 15s infinite;
        }

        .bg-animation .leaf:nth-child(1) {
            width: 150px;
            height: 150px;
            top: 10%;
            left: -50px;
            animation-delay: 0s;
        }

        .bg-animation .leaf:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: 10%;
            right: -70px;
            animation-delay: 5s;
            background: rgba(139, 195, 74, 0.1);
        }

        .bg-animation .leaf:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 50%;
            left: 80%;
            animation-delay: 10s;
            background: rgba(76, 175, 80, 0.12);
        }

        .bg-animation .leaf:nth-child(4) {
            width: 80px;
            height: 80px;
            bottom: 30%;
            left: 15%;
            animation-delay: 7s;
            background: rgba(139, 195, 74, 0.08);
        }

        .bg-animation .leaf:nth-child(5) {
            width: 120px;
            height: 120px;
            top: 70%;
            left: 40%;
            animation-delay: 3s;
            background: rgba(76, 175, 80, 0.1);
        }

        @keyframes floatLeaf {
            0%, 100% {
                transform: rotate(45deg) translateY(0) translateX(0);
                opacity: 0.3;
            }
            50% {
                transform: rotate(60deg) translateY(-30px) translateX(20px);
                opacity: 0.6;
            }
        }

        /* Floating recycle icons */
        .bg-animation .recycle-icon {
            position: absolute;
            font-size: 40px;
            opacity: 0.08;
            animation: floatRecycle 20s infinite;
        }

        .bg-animation .recycle-icon:nth-child(6) {
            top: 20%;
            right: 15%;
            animation-delay: 0s;
            font-size: 60px;
        }

        .bg-animation .recycle-icon:nth-child(7) {
            bottom: 25%;
            left: 10%;
            animation-delay: 8s;
            font-size: 50px;
        }

        .bg-animation .recycle-icon:nth-child(8) {
            top: 60%;
            right: 25%;
            animation-delay: 4s;
            font-size: 45px;
        }

        @keyframes floatRecycle {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.05;
            }
            50% {
                transform: translateY(-40px) rotate(180deg);
                opacity: 0.12;
            }
        }

        /* Main Container */
        .login-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Login Card */
        .login-card {
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
        }

        /* Header Section */
        .login-header {
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 50%, #8BC34A 100%);
            padding: 40px 30px 30px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '🌱';
            position: absolute;
            right: -30px;
            bottom: -30px;
            font-size: 120px;
            opacity: 0.15;
            transform: rotate(-15deg);
        }

        .login-header::after {
            content: '♻️';
            position: absolute;
            left: -30px;
            top: -30px;
            font-size: 100px;
            opacity: 0.15;
            transform: rotate(15deg);
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(5px);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 15px rgba(255, 255, 255, 0);
            }
        }

        .logo-icon img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }

        .logo-icon i {
            font-size: 45px;
            color: white;
        }

        .login-header h2 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        /* Body Section */
        .login-body {
            padding: 35px;
        }

        /* Form Inputs - FIXED ICON POSITION */
        .input-group-custom {
            margin-bottom: 25px;
            position: relative;
        }

        .input-group-custom .input-icon {
            position: absolute;
            left: 18px;
            top: 42px;
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
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.1);
        }

        .input-group-custom label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
            margin-left: 5px;
        }

        /* Login Button */
        .btn-login {
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
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        /* Back Button Container */
        .back-button-container {
            margin-bottom: 20px;
            text-align: left;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(46, 125, 50, 0.3);
            border-radius: 60px;
            padding: 8px 20px;
            color: #2E7D32;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(2px);
        }

        .btn-back i {
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .btn-back:hover {
            background: rgba(76, 175, 80, 0.1);
            border-color: #4CAF50;
            color: #1B5E20;
            transform: translateX(-3px);
        }

        .btn-back:hover i {
            transform: translateX(-3px);
        }

        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .register-link p {
            font-size: 14px;
            color: #666;
        }

        .register-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* Alert */
        .alert-custom {
            border-radius: 60px;
            padding: 12px 20px;
            margin-bottom: 25px;
            font-size: 13px;
            border: none;
        }

        /* Green Quote */
        .green-quote {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 20px;
        }

        .green-quote i {
            font-size: 24px;
            color: #4CAF50;
            margin-bottom: 8px;
        }

        .green-quote p {
            font-size: 12px;
            color: #2E7D32;
            margin: 0;
            font-style: italic;
        }

        @media (max-width: 576px) {
            .login-body {
                padding: 25px;
            }
            .login-header {
                padding: 30px 25px;
            }
            .logo-icon {
                width: 65px;
                height: 65px;
            }
            .logo-icon img {
                width: 40px;
                height: 40px;
            }
            .logo-icon i {
                font-size: 35px;
            }
            .btn-back {
                padding: 6px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="leaf"></div>
        <div class="recycle-icon"><i class="fas fa-recycle"></i></div>
        <div class="recycle-icon"><i class="fas fa-leaf"></i></div>
        <div class="recycle-icon"><i class="fas fa-seedling"></i></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <!-- Logo from assets/logo folder - Updated path -->
                    <img src="assets/logo/12.png" alt="Ecos+ Logo" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-leaf\'></i>';">
                </div>
                <h2>Welcome Back!</h2>
                <p>Continue your green journey with Ecos+</p>
            </div>
            <div class="login-body">
                <!-- Back Button - Now redirects to index.php -->
                <div class="back-button-container">
                    <a href="index.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>

                <!-- Fixed: Check if $error is set and not empty before displaying -->
                <?php if (isset($error) && $error !== ''): ?>
                    <div class="alert alert-danger alert-custom">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group-custom">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" placeholder="yourname@adab.umpsa.edu.my" required>
                    </div>
                    <div class="input-group-custom">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>

                <div class="register-link">
                    <p>Don't have an account? <a href="register.php">Create an account</a></p>
                </div>

                <div class="green-quote">
                    <i class="fas fa-quote-left"></i>
                    <p>"The greatest threat to our planet is the belief that someone else will save it."</p>
                    <small style="color: #4CAF50;">- Robert Swan</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>