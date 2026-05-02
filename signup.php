<?php
$page_title = 'Sign Up';
$current_page = 'signup';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $student_id = trim($_POST['student_id']);
    
    // Validation
    $errors = array();
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $errors[] = 'Please fill in all required fields';
    }
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = 'Username must be 3-20 characters (letters, numbers, underscore only)';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    if (!validateEmailDomain($email)) {
        $errors[] = 'Only @adab.umpsa.edu.my email addresses are allowed';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if (empty($errors)) {
        $result = register($username, $email, $password, $full_name, $student_id);
        
        if ($result['success']) {
            $success = $result['message'];
            // Clear form data
            $_POST = array();
            // Auto redirect after 2 seconds
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="theme-color" content="#4CAF50">
    <title>Sign Up - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            padding: 16px;
        }

        /* Container */
        .signup-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }

        /* Card */
        .signup-card {
            background: white;
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header */
        .signup-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 32px 24px;
            text-align: center;
        }

        .signup-header i {
            font-size: 56px;
            margin-bottom: 12px;
        }

        .signup-header h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .signup-header p {
            margin: 8px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        /* Body */
        .signup-body {
            padding: 32px 28px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 13px;
            color: #374151;
        }

        .form-group label i {
            margin-right: 8px;
            color: #4CAF50;
            width: 18px;
        }

        .required-star {
            color: #ef4444;
            margin-left: 4px;
        }

        /* Input Fields */
        .form-control {
            border-radius: 14px;
            border: 2px solid #e5e7eb;
            padding: 14px 16px;
            transition: all 0.3s;
            font-size: 15px;
            width: 100%;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 4px rgba(76,175,80,0.1);
            outline: none;
        }

        /* Password field with toggle */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
            font-size: 18px;
            z-index: 10;
            background: transparent;
            padding: 8px;
        }

        .password-toggle:active {
            color: #4CAF50;
        }

        /* Email hint */
        .email-hint {
            font-size: 11px;
            color: #6b7280;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .email-hint i {
            font-size: 10px;
        }

        /* Alert messages */
        .alert {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 14px;
            border: none;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        /* Submit Button */
        .btn-signup {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            border-radius: 14px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: all 0.3s;
            cursor: pointer;
            margin-top: 8px;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76,175,80,0.3);
        }

        .btn-signup:active {
            transform: translateY(0);
        }

        /* Login link */
        .login-link {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: #6b7280;
        }

        .login-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Touch-friendly buttons for mobile */
        @media (max-width: 640px) {
            body {
                padding: 12px;
            }
            
            .signup-header {
                padding: 28px 20px;
            }
            
            .signup-header i {
                font-size: 48px;
            }
            
            .signup-header h2 {
                font-size: 24px;
            }
            
            .signup-body {
                padding: 24px 20px;
            }
            
            .form-control {
                padding: 12px 14px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .btn-signup {
                padding: 14px;
            }
        }

        /* Desktop larger screens */
        @media (min-width: 768px) {
            .signup-container {
                max-width: 520px;
            }
        }

        /* Error summary */
        .error-list {
            margin: 0;
            padding-left: 20px;
        }
        .error-list li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <!-- Header -->
            <div class="signup-header">
                <i class="fas fa-seedling"></i>
                <h2>Join Ecos+</h2>
                <p>Start your green journey today</p>
            </div>
            
            <!-- Body -->
            <div class="signup-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2 small">Redirecting to login page...</div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="signupForm" accept-charset="UTF-8">
                    <!-- Full Name -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-user"></i> Full Name 
                            <span class="required-star">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               name="full_name" 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                               placeholder="Enter your full name" 
                               required 
                               autocomplete="name">
                    </div>
                    
                    <!-- Username -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-user-circle"></i> Username 
                            <span class="required-star">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               placeholder="3-20 characters (letters, numbers, _)" 
                               required 
                               autocomplete="username"
                               pattern="[A-Za-z0-9_]{3,20}"
                               title="3-20 characters, only letters, numbers and underscore">
                    </div>
                    
                    <!-- Student/Staff ID -->
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Student/Staff ID</label>
                        <input type="text" 
                               class="form-control" 
                               name="student_id" 
                               value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" 
                               placeholder="Optional"
                               autocomplete="off">
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-envelope"></i> University Email 
                            <span class="required-star">*</span>
                        </label>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="example@adab.umpsa.edu.my" 
                               required 
                               autocomplete="email"
                               inputmode="email">
                        <div class="email-hint">
                            <i class="fas fa-info-circle"></i> Only @adab.umpsa.edu.my email addresses are allowed
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-lock"></i> Password 
                            <span class="required-star">*</span>
                        </label>
                        <div class="password-wrapper">
                            <input type="password" 
                                   class="form-control" 
                                   name="password" 
                                   id="password" 
                                   placeholder="Minimum 6 characters" 
                                   required 
                                   autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-lock"></i> Confirm Password 
                            <span class="required-star">*</span>
                        </label>
                        <div class="password-wrapper">
                            <input type="password" 
                                   class="form-control" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   placeholder="Re-enter your password" 
                                   required 
                                   autocomplete="off">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="register" class="btn-signup">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Real-time password match validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validateMatch() {
            if (password.value !== confirmPassword.value && confirmPassword.value !== '') {
                confirmPassword.style.borderColor = '#ef4444';
            } else {
                confirmPassword.style.borderColor = '#e5e7eb';
            }
        }
        
        password.addEventListener('keyup', validateMatch);
        confirmPassword.addEventListener('keyup', validateMatch);
        
        // Form validation before submit
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const pwd = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (pwd !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (pwd.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters!');
                return false;
            }
            
            return true;
        });
        
        // Handle enter key on mobile
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = document.getElementById('signupForm');
                    if (form.checkValidity()) {
                        form.submit();
                    } else {
                        form.reportValidity();
                    }
                }
            });
        });
    </script>
</body>
</html>