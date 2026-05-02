<?php
$page_title = 'Ecos+ - Smart Recycling Platform';
require_once 'includes/auth.php';

// ONLY CHECK - NO REDIRECT
$isLoggedIn = isLoggedIn();
$user = $isLoggedIn ? getCurrentUser() : null;

// Scan images from assets/images folder for slideshow
$slideshow_images = [];
$images_dir = 'assets/images/';
if (is_dir($images_dir)) {
    $files = scandir($images_dir);
    foreach ($files as $file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $slideshow_images[] = $images_dir . $file;
        }
    }
}
// Default images if no images found
if (empty($slideshow_images)) {
    $slideshow_images = [
        'https://placehold.co/600x400/4CAF50/white?text=♻️+Recycle+Plastic',
        'https://placehold.co/600x400/2196F3/white?text=📄+Recycle+Paper',
        'https://placehold.co/600x400/FF9800/white?text=💻+E-Waste+Recycling',
        'https://placehold.co/600x400/9C27B0/white?text=🥤+Plastic+Bottles'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Ecos+ - Smart Recycling Platform for UMPSA</title>
    <meta name="description" content="Join Ecos+ - Malaysia's first AI-powered recycling platform for UMPSA community. Earn points, recycle smart, and save the environment!">
    <meta name="keywords" content="recycling, UMPSA, ecos, green, sustainability, AI recycling">
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
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar-custom {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #4CAF50, #8BC34A); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { font-size: 20px; color: white; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #4CAF50; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; align-items: center; justify-content: flex-end; flex: 1; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom i { font-size: 16px; }
        .nav-link-custom:hover { background: rgba(76, 175, 80, 0.15); color: #4CAF50; transform: translateY(-2px); }
        .btn-login-nav { background: linear-gradient(135deg, #4CAF50, #45a049); color: white !important; }
        .btn-login-nav:hover { background: linear-gradient(135deg, #45a049, #4CAF50); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #1a1a2e; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        
        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .mobile-toggle { display: block; } 
            .navbar-container { padding: 0 15px; }
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        /* Hero Section with Slideshow */
        .hero {
            min-height: 90vh;
            background: linear-gradient(135deg, #0f0c29 0%, #1a4d2e 50%, #24243e 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            padding: 80px 0;
        }
        .hero::before {
            content: '♻️';
            position: absolute;
            right: -50px;
            bottom: -50px;
            font-size: 300px;
            opacity: 0.05;
            pointer-events: none;
        }
        .hero-content { position: relative; z-index: 2; }
        .hero-badge {
            background: rgba(76, 175, 80, 0.2);
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        .hero-title {
            font-size: 48px;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            margin-bottom: 20px;
        }
        .hero-title span { color: #4CAF50; }
        .hero-subtitle {
            font-size: 16px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 30px;
            max-width: 500px;
        }
        .hero-stats {
            display: flex;
            gap: 30px;
            margin-top: 30px;
        }
        .stat-number { font-size: 32px; font-weight: 700; color: #4CAF50; }
        .stat-label { font-size: 12px; color: rgba(255,255,255,0.7); }
        
        /* Slideshow Styles */
        .slideshow-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .slideshow-container .mySlides {
            display: none;
            animation: fadeEffect 1s ease-in-out;
        }
        .slideshow-container .mySlides img {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        @keyframes fadeEffect {
            from { opacity: 0.4; }
            to { opacity: 1; }
        }
        .slideshow-container .prev, .slideshow-container .next {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            padding: 16px;
            margin-top: -22px;
            color: white;
            font-weight: bold;
            font-size: 18px;
            transition: 0.6s ease;
            border-radius: 0 3px 3px 0;
            user-select: none;
            background-color: rgba(0,0,0,0.5);
            text-decoration: none;
        }
        .slideshow-container .next {
            right: 0;
            border-radius: 3px 0 0 3px;
        }
        .slideshow-container .prev:hover, .slideshow-container .next:hover {
            background-color: rgba(0,0,0,0.8);
        }
        .slideshow-container .dot-container {
            text-align: center;
            padding: 10px;
            background: rgba(0,0,0,0.5);
            position: absolute;
            bottom: 0;
            width: 100%;
        }
        .slideshow-container .dot {
            cursor: pointer;
            height: 12px;
            width: 12px;
            margin: 0 5px;
            background-color: rgba(255,255,255,0.5);
            border-radius: 50%;
            display: inline-block;
            transition: background-color 0.6s ease;
        }
        .slideshow-container .active, .slideshow-container .dot:hover {
            background-color: #4CAF50;
        }

        /* Features Section */
        .features { padding: 80px 0; background: #f8f9fa; }
        .section-title { text-align: center; font-size: 36px; font-weight: 700; margin-bottom: 15px; }
        .section-subtitle { text-align: center; color: #666; margin-bottom: 50px; }
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(76,175,80,0.15);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .feature-icon i { font-size: 40px; color: #4CAF50; }
        .feature-title { font-size: 20px; font-weight: 600; margin-bottom: 10px; }
        .feature-desc { font-size: 14px; color: #666; }

        /* How It Works */
        .how-it-works { padding: 80px 0; background: white; }
        .step-card {
            text-align: center;
            padding: 20px;
            position: relative;
        }
        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        .step-title { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
        .step-desc { font-size: 13px; color: #666; }

        /* Stats Counter */
        .stats-section {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            padding: 60px 0;
            color: white;
        }
        .counter-card { text-align: center; }
        .counter-number { font-size: 48px; font-weight: 800; }
        .counter-label { font-size: 14px; opacity: 0.9; margin-top: 5px; }

        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            text-align: center;
        }
        .btn-cta {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            margin: 10px;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        .btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(76,175,80,0.4);
            color: white;
        }
        .btn-outline-cta {
            background: transparent;
            border: 2px solid #4CAF50;
            color: #4CAF50;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        .btn-outline-cta:hover {
            background: #4CAF50;
            color: white;
            transform: translateY(-2px);
        }

        /* Testimonials */
        .testimonials { padding: 80px 0; background: #f8f9fa; }
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .testimonial-text { font-size: 14px; color: #555; font-style: italic; margin-bottom: 15px; }
        .testimonial-author { display: flex; align-items: center; gap: 12px; }
        .testimonial-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #4CAF50, #8BC34A);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        .testimonial-name { font-weight: 600; font-size: 14px; margin: 0; }
        .testimonial-role { font-size: 11px; color: #999; }

        /* Footer */
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 50px 0 20px;
        }
        .footer-logo { font-size: 24px; font-weight: 700; margin-bottom: 15px; }
        .footer-logo span { color: #4CAF50; }
        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .footer-links a:hover { color: #4CAF50; padding-left: 5px; }
        .social-icons a {
            color: white;
            background: rgba(255,255,255,0.1);
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.3s;
        }
        .social-icons a:hover {
            background: #4CAF50;
            transform: translateY(-3px);
        }
        .copyright {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 32px; }
            .hero { text-align: center; }
            .hero-stats { justify-content: center; }
            .section-title { font-size: 28px; }
            .counter-number { font-size: 32px; }
            .slideshow-container .mySlides img { height: 250px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" class="navbar-brand-custom">
                <div class="logo-icon">
                    <img src="assets/logo/12.png" alt="Logo" style="height:30px; object-fit:cover;">
                </div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="#features" class="nav-link-custom"><i class="fas fa-star"></i> Features</a></li>
                <li><a href="#how-it-works" class="nav-link-custom"><i class="fas fa-play-circle"></i> How It Works</a></li>
                <li><a href="#testimonials" class="nav-link-custom"><i class="fas fa-users"></i> Testimonials</a></li>
                <li><a href="login.php" class="nav-link-custom btn-login-nav"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="register.php" class="nav-link-custom"><i class="fas fa-user-plus"></i> Sign Up</a></li>
            </ul>
            <button class="mobile-toggle" id="mobileToggleBtn"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
        <li><a href="#how-it-works"><i class="fas fa-play-circle"></i> How It Works</a></li>
        <li><a href="#testimonials"><i class="fas fa-users"></i> Testimonials</a></li>
        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        <li><a href="register.php"><i class="fas fa-user-plus"></i> Sign Up</a></li>
    </ul>
</div>

<!-- Hero Section with Slideshow -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="hero-content">
                    <div class="hero-badge">
                        <i class="fas fa-robot"></i> AI-Powered Recycling Platform
                    </div>
                    <h1 class="hero-title">
                        Recycle Smart, <br><span>Earn Points,</span> <br>Save Our Planet!
                    </h1>
                    <p class="hero-subtitle">
                        Join Malaysia's first AI-powered recycling platform for UMPSA community. 
                        Snap a photo, let AI identify recyclable items, and earn rewards!
                    </p>
                    <div>
                        <a href="register.php" class="btn-cta">
                            <i class="fas fa-user-plus"></i> Sign Up Now - It's Free!
                        </a>
                        <a href="#features" class="btn-outline-cta">
                            <i class="fas fa-play"></i> Learn More
                        </a>
                    </div>
                    <div class="hero-stats">
                        <div>
                            <div class="stat-number">1,000+</div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div>
                            <div class="stat-number">5,000+</div>
                            <div class="stat-label">Items Recycled</div>
                        </div>
                        <div>
                            <div class="stat-number">2,500kg</div>
                            <div class="stat-label">CO₂ Saved</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <!-- Slideshow Container -->
                <div class="slideshow-container">
                    <?php foreach ($slideshow_images as $index => $image): ?>
                    <div class="mySlides fade">
                        <img src="<?php echo $image; ?>" alt="Ecos+ Recycling Slide <?php echo $index + 1; ?>">
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Next and previous buttons -->
                    <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
                    <a class="next" onclick="plusSlides(1)">&#10095;</a>
                    
                    <!-- Dots/indicators -->
                    <div class="dot-container">
                        <?php foreach ($slideshow_images as $index => $image): ?>
                        <span class="dot" onclick="currentSlide(<?php echo $index + 1; ?>)"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features" id="features">
    <div class="container">
        <h2 class="section-title">Why Choose <span style="color: #4CAF50;">Ecos+</span>?</h2>
        <p class="section-subtitle">Making recycling easier, fun, and rewarding for everyone</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3 class="feature-title">AI Detection</h3>
                    <p class="feature-desc">Powered by Google Gemini AI - Just snap a photo and our AI automatically identifies the recyclable material type.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="feature-title">Points & Rewards</h3>
                    <p class="feature-desc">Earn points for every recycling activity. Redeem them for exciting rewards and vouchers!</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="feature-title">Recycling Map</h3>
                    <p class="feature-desc">Find nearest recycling centers on campus. Get directions and operating hours.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="feature-title">AIs</h3>
                    <p class="feature-desc">Personalized recycling analytics and tips to help you become a better recycler.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="feature-title">Community</h3>
                    <p class="feature-desc">Connect with fellow recyclers, share tips, and participate in campus events.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="feature-title">Campus Events</h3>
                    <p class="feature-desc">Join recycling drives and eco-events. Earn bonus points for participating!</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how-it-works" id="how-it-works">
    <div class="container">
        <h2 class="section-title">How <span style="color: #4CAF50;">It Works</span></h2>
        <p class="section-subtitle">Three simple steps to start your green journey</p>
        <div class="row">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-title">📸 Take a Photo</div>
                    <div class="step-desc">Snap a picture of your recyclable items using our AI-powered camera.</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-title">🤖 AI Detection</div>
                    <div class="step-desc">Our AI automatically identifies the material type (Plastic, Paper, Glass, etc.)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-title">🏆 Earn Points</div>
                    <div class="step-desc">Submit your activity and earn points. Redeem them for amazing rewards!</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Counter -->
<section class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <div class="counter-card">
                    <div class="counter-number">1,250+</div>
                    <div class="counter-label">Active Recyclers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="counter-card">
                    <div class="counter-number">8,500+</div>
                    <div class="counter-label">Items Recycled</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="counter-card">
                    <div class="counter-number">50,000+</div>
                    <div class="counter-label">Points Earned</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="counter-card">
                    <div class="counter-number">4,200kg</div>
                    <div class="counter-label">CO₂ Saved</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="testimonials" id="testimonials">
    <div class="container">
        <h2 class="section-title">What <span style="color: #4CAF50;">Users Say</span></h2>
        <p class="section-subtitle">Join thousands of satisfied recyclers</p>
        <div class="row">
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        "Ecos+ has completely changed how I recycle! The AI detection is super accurate and the points system keeps me motivated. Highly recommended!"
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">A</div>
                        <div>
                            <div class="testimonial-name">Ahmad Faizal</div>
                            <div class="testimonial-role">UMPSA Student</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        "The recycling map feature is a lifesaver! I can always find the nearest recycling center on campus. Plus, the rewards are amazing!"
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">S</div>
                        <div>
                            <div class="testimonial-name">Siti Nurhaliza</div>
                            <div class="testimonial-role">Environmental Club Member</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        "I love the AIs feature! It helps me understand my recycling habits and gives me tips to improve. Great platform for our campus!"
                    </div>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">M</div>
                        <div>
                            <div class="testimonial-name">Mohd Ali</div>
                            <div class="testimonial-role">Green Ambassador</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <h2 style="font-size: 36px; margin-bottom: 20px;">Ready to Start Your Green Journey?</h2>
        <p style="margin-bottom: 30px;">Join Ecos+ today and be part of the change!</p>
        <div>
            <a href="register.php" class="btn-cta">
                <i class="fas fa-user-plus"></i> Sign Up Now - It's Free!
            </a>
            <a href="login.php" class="btn-outline-cta">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="footer-logo">Ecos<span>+</span></div>
                <p style="font-size: 13px; color: rgba(255,255,255,0.7); margin-top: 15px;">
                    Malaysia's first AI-powered recycling platform for UMPSA community. Making recycling easy, fun, and rewarding!
                </p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="col-md-2">
                <h5 style="margin-bottom: 20px;">Quick Links</h5>
                <div class="footer-links">
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How It Works</a>
                    <a href="#testimonials">Testimonials</a>
                    <a href="register.php">Sign Up</a>
                </div>
            </div>
            <div class="col-md-3">
                <h5 style="margin-bottom: 20px;">Resources</h5>
                <div class="footer-links">
                    <a href="#">Recycling Guide</a>
                    <a href="#">FAQ</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
            <div class="col-md-3">
                <h5 style="margin-bottom: 20px;">Contact Us</h5>
                <div class="footer-links">
                    <a href="#"><i class="fas fa-envelope"></i> support@ecosplus.com</a>
                    <a href="#"><i class="fas fa-phone"></i> +609-424 1234</a>
                    <a href="#"><i class="fas fa-map-marker-alt"></i> UMPSA, Pahang, Malaysia</a>
                </div>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2024 Ecos+. All rights reserved. | Made with <i class="fas fa-heart" style="color: #4CAF50;"></i> for a greener Malaysia</p>
        </div>
    </div>
</footer>

<script>
    // Mobile menu toggle
    const mobileToggleBtn = document.getElementById('mobileToggleBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('show');
        });
    }
    
    document.addEventListener('click', function(event) {
        if (mobileMenu && mobileToggleBtn && mobileMenu.classList.contains('show') && 
            !mobileMenu.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
            mobileMenu.classList.remove('show');
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
                mobileMenu.classList.remove('show');
            }
        });
    });

    // =============================================
    // SLIDESHOW FUNCTIONALITY
    // =============================================
    let slideIndex = 1;
    let slideInterval;
    
    function showSlides(n) {
        let i;
        let slides = document.getElementsByClassName("mySlides");
        let dots = document.getElementsByClassName("dot");
        
        if (n > slides.length) { slideIndex = 1; }
        if (n < 1) { slideIndex = slides.length; }
        
        for (i = 0; i < slides.length; i++) {
            slides[i].style.display = "none";
        }
        for (i = 0; i < dots.length; i++) {
            dots[i].className = dots[i].className.replace(" active", "");
        }
        
        if (slides[slideIndex - 1]) {
            slides[slideIndex - 1].style.display = "block";
        }
        if (dots[slideIndex - 1]) {
            dots[slideIndex - 1].className += " active";
        }
    }
    
    function plusSlides(n) {
        clearInterval(slideInterval);
        showSlides(slideIndex += n);
        startAutoSlide();
    }
    
    function currentSlide(n) {
        clearInterval(slideInterval);
        showSlides(slideIndex = n);
        startAutoSlide();
    }
    
    function startAutoSlide() {
        slideInterval = setInterval(function() {
            plusSlides(1);
        }, 5000); // Change slide every 5 seconds
    }
    
    // Initialize slideshow
    showSlides(slideIndex);
    startAutoSlide();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>