<?php
$page_title = 'Sign Up';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $year_of_study = trim($_POST['year_of_study'] ?? '');
    
    $errors = array();
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Please enter your full name';
    }
    if (empty($email)) {
        $errors[] = 'Please enter your email address';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format';
    }
    if (!validateEmailDomain($email)) {
        $errors[] = 'Only @adab.umpsa.edu.my email addresses are allowed';
    }
    if (empty($password)) {
        $errors[] = 'Please enter a password';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if (empty($errors)) {
        // Call register function with all parameters
        $result = register($full_name, $email, $password, $phone, $student_id, $faculty, $year_of_study);
        
        if ($result['success']) {
            $success = $result['message'];
            $_POST = array();
            echo '<meta http-equiv="refresh" content="2;url=login.php">';
        } else {
            $error = $result['message'];
        }
    } else {
        $error = implode('<br>', $errors);
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
            background: linear-gradient(135deg, #0f0c29 0%, #1a4d2e 50%, #24243e 100%);
            position: relative;
            overflow-x: hidden;
        }

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

        .register-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-card {
            max-width: 550px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .register-card:hover {
            transform: translateY(-5px);
        }

        .register-header {
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 50%, #8BC34A 100%);
            padding: 35px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '🌱';
            position: absolute;
            right: -30px;
            bottom: -30px;
            font-size: 120px;
            opacity: 0.15;
            transform: rotate(-15deg);
        }

        .register-header::after {
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
            margin: 0 auto 15px;
            backdrop-filter: blur(5px);
            animation: pulse 2s infinite;
        }

        .logo-icon img {
            width: 60px;
            height: 60px;
            object-fit: contain;
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

        .register-header h2 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .register-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
        }

        .register-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .register-body::-webkit-scrollbar {
            width: 4px;
        }

        .register-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .register-body::-webkit-scrollbar-thumb {
            background: #4CAF50;
            border-radius: 10px;
        }

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

        .input-group-custom {
            margin-bottom: 18px;
            position: relative;
        }

        .input-group-custom .input-icon {
            position: absolute;
            left: 18px;
            top: 42px;
            color: #4CAF50;
            font-size: 15px;
            z-index: 2;
            pointer-events: none;
        }

        .input-group-custom input, .input-group-custom select {
            width: 100%;
            padding: 12px 18px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 60px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
            height: 46px;
        }

        .input-group-custom select {
            cursor: pointer;
        }

        .input-group-custom input:focus, .input-group-custom select:focus {
            outline: none;
            border-color: #4CAF50;
            background: white;
            box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.1);
        }

        .input-group-custom label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
            margin-left: 5px;
        }

        .input-group-custom label i {
            margin-right: 5px;
            font-size: 11px;
            color: #4CAF50;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px;
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 12px;
            cursor: pointer;
            color: #999;
            font-size: 15px;
            z-index: 5;
        }

        .password-toggle:hover {
            color: #4CAF50;
        }

        .hint {
            font-size: 10px;
            color: #888;
            margin-top: 5px;
            margin-left: 12px;
        }

        .hint i {
            font-size: 9px;
            margin-right: 3px;
        }

        .row-2cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%);
            border: none;
            border-radius: 60px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
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

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .login-link p {
            font-size: 13px;
            color: #666;
            margin: 0;
        }

        .login-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert-custom {
            border-radius: 60px;
            padding: 10px 18px;
            margin-bottom: 20px;
            font-size: 12px;
            border: none;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border-left: 3px solid #c62828;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 3px solid #2e7d32;
        }

        .green-quote {
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 20px;
        }

        .green-quote i {
            font-size: 20px;
            color: #4CAF50;
            margin-bottom: 8px;
        }

        .green-quote p {
            font-size: 11px;
            color: #2E7D32;
            margin: 0;
            font-style: italic;
        }

        @media (max-width: 576px) {
            .register-body {
                padding: 25px;
            }
            .register-header {
                padding: 25px 20px;
            }
            .logo-icon {
                width: 65px;
                height: 65px;
            }
            .logo-icon img {
                width: 45px;
                height: 45px;
            }
            .row-2cols {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .input-group-custom .input-icon {
                top: 38px;
            }
            .password-toggle {
                top: 10px;
            }
        }
    </style>
</head>
<body>
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

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo-icon">
                    <img src="assets/logo/12.png" alt="Ecos+ Logo" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-seedling\'></i>';">
                </div>
                <h2>Join Ecos+</h2>
                <p>Start your green journey today</p>
            </div>
            <div class="register-body">
                <div class="back-button-container">
                    <a href="login.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-custom">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?> Redirecting...
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm">
                    <div class="input-group-custom">
                        <label><i class="fas fa-user"></i> Full Name <span style="color:#e53935;">*</span></label>
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" placeholder="Enter your full name" required>
                    </div>

                    <div class="input-group-custom">
                        <label><i class="fas fa-envelope"></i> University Email <span style="color:#e53935;">*</span></label>
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="example@adab.umpsa.edu.my" required>
                        <div class="hint">
                            <i class="fas fa-info-circle"></i> Only @adab.umpsa.edu.my email addresses are allowed
                        </div>
                    </div>

                    <div class="row-2cols">
                        <div class="input-group-custom">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="012-3456789">
                        </div>

                        <div class="input-group-custom">
                            <label><i class="fas fa-id-card"></i> Student/Staff ID</label>
                            <i class="fas fa-id-card input-icon"></i>
                            <input type="text" name="student_id" value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" placeholder="e.g., TG25008">
                        </div>
                    </div>

                    <div class="row-2cols">
                        <div class="input-group-custom">
                            <label><i class="fas fa-building"></i> Faculty</label>
                            <i class="fas fa-building input-icon"></i>
                            <select name="faculty">
                                <option value="">Select Faculty</option>
                                <option value="FKOM" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FKOM') ? 'selected' : ''; ?>>Faculty of Chemical and Process Engineering Technology</option>
                                <option value="FKE" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FKE') ? 'selected' : ''; ?>>Faculty of Civil Engineering Technology</option>
                                <option value="FPTP" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FPTP') ? 'selected' : ''; ?>>Faculty of Electrical and Electronics Engineering Technology</option>
                                <option value="FSSH" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FSSH') ? 'selected' : ''; ?>>Faculty of Manufacturing and Mechatronic Engineering Technology</option>
                                <option value="FSSH" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FSSH') ? 'selected' : ''; ?>>Faculty of Mechanical and Automotive Engineering Technology</option>
                                <option value="FSSH" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FSSH') ? 'selected' : ''; ?>>Centre for Mathematical Sciences</option>
                                <option value="FSSH" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FSSH') ? 'selected' : ''; ?>>Faculty of Computing</option>
                                <option value="FSSH" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FSSH') ? 'selected' : ''; ?>>Faculty of Industrial Sciences and Technology</option>
                                <option value="FSSH" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] == 'FSSH') ? 'selected' : ''; ?>>Faculty of Industrial Management</option>
                            </select>
                        </div>

                        <div class="input-group-custom">
                            <label><i class="fas fa-graduation-cap"></i> Year of Study</label>
                            <i class="fas fa-graduation-cap input-icon"></i>
                            <select name="year_of_study">
                                <option value="">Select Year</option>
                                <option value="1" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '1') ? 'selected' : ''; ?>>Year 1</option>
                                <option value="2" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '2') ? 'selected' : ''; ?>>Year 2</option>
                                <option value="3" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '3') ? 'selected' : ''; ?>>Year 3</option>
                                <option value="4" <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == '4') ? 'selected' : ''; ?>>Year 4</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group-custom">
                        <label><i class="fas fa-lock"></i> Password <span style="color:#e53935;">*</span></label>
                        <i class="fas fa-lock input-icon"></i>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Minimum 6 characters" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                    </div>

                    <div class="input-group-custom">
                        <label><i class="fas fa-lock"></i> Confirm Password <span style="color:#e53935;">*</span></label>
                        <i class="fas fa-lock input-icon"></i>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter your password" required>
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn-register">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>

                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Sign In</a></p>
                </div>

                <div class="green-quote">
                    <i class="fas fa-quote-left"></i>
                    <p>"The greatest threat to our planet is the belief that someone else will save it."</p>
                    <small style="color: #4CAF50;">- Robert Swan</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const icon = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');

        function validateMatch() {
            if (password.value !== confirm.value && confirm.value !== '') {
                confirm.style.borderColor = '#e53935';
            } else {
                confirm.style.borderColor = '#e0e0e0';
            }
        }

        password.addEventListener('keyup', validateMatch);
        confirm.addEventListener('keyup', validateMatch);

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (password.value !== confirm.value) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>