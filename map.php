<?php
$page_title = 'Recycling Map';
$current_page = 'map';
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';

// Check if table exists and create if not
$tableExists = false;
$checkTable = $conn->query("SHOW TABLES LIKE 'recycling_locations'");
if ($checkTable && $checkTable->num_rows > 0) {
    $tableExists = true;
}

// If table doesn't exist, create it with sample data
if (!$tableExists) {
    $createTable = "CREATE TABLE IF NOT EXISTS `recycling_locations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `address` text NOT NULL,
        `latitude` decimal(10,8) NOT NULL,
        `longitude` decimal(11,8) NOT NULL,
        `category` enum('mixed','plastic_paper','ewaste','paper','organic') DEFAULT 'mixed',
        `operating_hours` varchar(255) DEFAULT NULL,
        `phone` varchar(50) DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createTable);
    
    // Insert sample data for UTM area
    $sampleData = [
        ['UTM Main Recycling Center', 'Universiti Teknologi Malaysia, Skudai, Johor', 1.56080000, 103.64120000, 'mixed', 'Mon-Fri: 8am-6pm, Sat: 9am-1pm', '07-5533333'],
        ['Kolej Rahman Putra Recycling', 'Kolej Rahman Putra, UTM, Skudai', 1.56500000, 103.64500000, 'plastic_paper', '24 Hours', NULL],
        ['Kolej Tuanku Canselor Point', 'Kolej Tuanku Canselor, UTM, Skudai', 1.55800000, 103.63800000, 'mixed', '24 Hours', NULL],
        ['Kolej 9 & 10 Recycling', 'Kolej 9 & 10, UTM, Skudai', 1.56250000, 103.64250000, 'paper', '24 Hours', NULL],
        ['FABU E-Waste Center', 'Fakulti Alam Bina, UTM, Skudai', 1.56300000, 103.64000000, 'ewaste', 'Mon-Fri: 9am-5pm', '07-5534567'],
        ['Perpustakaan Sultanah Zanariah', 'PSZ, UTM, Skudai', 1.55950000, 103.63950000, 'paper', 'Mon-Sun: 8am-10pm', NULL],
        ['Arked Cengal', 'Arked Cengal, UTM, Skudai', 1.56150000, 103.64300000, 'plastic_paper', 'Mon-Sat: 10am-8pm', NULL],
        ['Kolej Perdana Recycling', 'Kolej Perdana, UTM, Skudai', 1.56600000, 103.64600000, 'mixed', '24 Hours', NULL],
        ['Fakulti Kejuruteraan', 'Fakulti Kejuruteraan, UTM, Skudai', 1.56400000, 103.63700000, 'ewaste', 'Mon-Fri: 8am-5pm', '07-5535678'],
        ['Pusat Kesihatan UTM', 'Pusat Kesihatan, UTM, Skudai', 1.55750000, 103.63650000, 'organic', 'Mon-Fri: 8am-8pm', NULL]
    ];
    
    foreach ($sampleData as $data) {
        $stmt = $conn->prepare("INSERT INTO recycling_locations (name, address, latitude, longitude, category, operating_hours, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddsss", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6]);
        $stmt->execute();
        $stmt->close();
    }
}

// Get all recycling locations
$locationsQuery = "SELECT * FROM recycling_locations WHERE is_active = 1 ORDER BY name ASC";
$locations = $conn->query($locationsQuery);

// Get categories for filter
$categories = ['mixed', 'plastic_paper', 'ewaste', 'paper', 'organic'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Recycling Map - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Poppins', sans-serif; }

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
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; align-items: center; justify-content: center; flex: 1; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom i { font-size: 16px; }
        .nav-link-custom:hover { background: rgba(76, 175, 80, 0.15); color: #4CAF50; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        .user-dropdown { position: relative; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(255,255,255,0.08); border-radius: 40px; transition: all 0.3s ease; }
        .user-trigger:hover { background: rgba(255,255,255,0.15); }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #4CAF50, #8BC34A); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 15px; color: white; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-arrow { color: rgba(255,255,255,0.6); font-size: 12px; transition: transform 0.3s; }
        .user-dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        .dropdown-menu-custom { position: absolute; top: 55px; right: 0; width: 220px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
        .user-dropdown:hover .dropdown-menu-custom { opacity: 1; visibility: visible; top: 60px; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; transition: all 0.2s; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:last-child { border-bottom: none; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #4CAF50; }
        .dropdown-item-custom i { width: 20px; color: #4CAF50; }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #1a1a2e; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav li { margin-bottom: 5px; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .mobile-nav a i { width: 24px; }
        
        @media (max-width: 992px) { 
            .nav-links { display: none; } 
            .mobile-toggle { display: block; } 
            .user-info { display: none; } 
            .user-trigger { padding: 6px 12px; } 
            .navbar-container { padding: 0 15px; }
        }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; }
        #map { height: 550px; border-radius: 20px; z-index: 1; width: 100%; }
        .map-card { background: white; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
        .card-header-custom { background: white; border-bottom: 2px solid #4CAF50; padding: 15px 20px; font-weight: 600; font-size: 16px; }
        .location-card { cursor: pointer; transition: all 0.3s; border: 1px solid #e0e0e0; border-radius: 12px; padding: 12px; margin-bottom: 10px; }
        .location-card:hover { border-color: #4CAF50; transform: translateX(5px); background: #f8f9fa; }
        .filter-btn { margin: 5px; border-radius: 30px; padding: 6px 16px; font-size: 12px; transition: all 0.3s; }
        .filter-btn.active { background: #4CAF50; color: white; border-color: #4CAF50; }
        .filter-btn:hover:not(.active) { background: #e8f5e9; border-color: #4CAF50; }
        .category-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; margin-top: 5px; }
        .category-mixed { background: #4CAF50; color: white; }
        .category-plastic_paper { background: #2196F3; color: white; }
        .category-ewaste { background: #FF9800; color: white; }
        .category-paper { background: #9C27B0; color: white; }
        .category-organic { background: #8BC34A; color: white; }
        
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fab:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        
        @media (max-width: 768px) { 
            .container-custom { padding: 15px; } 
            #map { height: 350px; }
            .fab { width: 45px; height: 45px; bottom: 15px; right: 15px; }
        }
        
        #locationList { max-height: 450px; overflow-y: auto; scrollbar-width: thin; }
        #locationList::-webkit-scrollbar { width: 6px; }
        #locationList::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        #locationList::-webkit-scrollbar-thumb { background: #4CAF50; border-radius: 10px; }
    </style>
</head>
<body>

<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon">
                    <img src="assets/images/umpsa.png" alt="Logo" style="height:25px; object-fit:cover;">
                </div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom active"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Insights</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-dropdown">
                <div class="user-trigger">
                    <div class="user-avatar"><?php echo $navbar_initial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($navbar_user['full_name'] ?? 'User'); ?></span>
                        <span class="user-points"><i class="fas fa-star"></i> <?php echo number_format($navbar_user['points'] ?? 0); ?> pts</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu-custom">
                    <a href="profile.php" class="dropdown-item-custom"><i class="fas fa-user-circle"></i> My Profile</a>
                    <a href="rewards.php" class="dropdown-item-custom"><i class="fas fa-gift"></i> My Rewards</a>
                    <div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div>
                    <a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
            <button class="mobile-toggle" id="mobileToggleBtn"><i class="fas fa-bars"></i></button>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <ul class="mobile-nav">
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="activity.php"><i class="fas fa-recycle"></i> Recycle</a></li>
        <li><a href="map.php" class="active"><i class="fas fa-map-marker-alt"></i> Map</a></li>
        <li><a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a></li>
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Insights</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <?php if (isAdmin()): ?>
        <li><a href="admin/dashboard.php"><i class="fas fa-cog"></i> Admin</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <div class="row">
        <div class="col-lg-8">
            <div class="map-card">
                <div class="card-header-custom"><i class="fas fa-map-marker-alt text-success"></i> Campus Recycling Map</div>
                <div class="p-0"><div id="map"></div></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="map-card">
                <div class="card-header-custom"><i class="fas fa-filter"></i> Filter Locations</div>
                <div class="p-3">
                    <div class="mb-3">
                        <button class="btn btn-sm btn-outline-success filter-btn active" data-category="all">All Locations</button>
                        <?php foreach ($categories as $cat): ?>
                            <button class="btn btn-sm btn-outline-success filter-btn" data-category="<?php echo $cat; ?>">
                                <?php 
                                    $icons = [
                                        'mixed' => '♻️',
                                        'plastic_paper' => '🥤',
                                        'ewaste' => '💻',
                                        'paper' => '📄',
                                        'organic' => '🍎'
                                    ];
                                    echo $icons[$cat] . ' ' . ucfirst(str_replace('_', ' ', $cat)); 
                                ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <div id="locationList">
                        <?php if ($locations && $locations->num_rows > 0): ?>
                            <?php while($loc = $locations->fetch_assoc()): ?>
                                <div class="location-card" data-lat="<?php echo htmlspecialchars($loc['latitude']); ?>" data-lng="<?php echo htmlspecialchars($loc['longitude']); ?>" data-category="<?php echo htmlspecialchars($loc['category']); ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <?php 
                                                $catIcons = [
                                                    'mixed' => 'fa-recycle',
                                                    'plastic_paper' => 'fa-bottle-water',
                                                    'ewaste' => 'fa-laptop',
                                                    'paper' => 'fa-newspaper',
                                                    'organic' => 'fa-apple-alt'
                                                ];
                                                $icon = isset($catIcons[$loc['category']]) ? $catIcons[$loc['category']] : 'fa-trash-alt';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>" style="font-size: 24px; color: #4CAF50;"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div class="fw-bold"><?php echo htmlspecialchars($loc['name'] ?? 'Unknown Location'); ?></div>
                                            <small class="text-muted"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($loc['address'] ?? 'Address not available'); ?></small>
                                            <div><span class="category-badge category-<?php echo htmlspecialchars($loc['category'] ?? 'mixed'); ?>"><?php echo ucfirst(str_replace('_', ' ', $loc['category'] ?? 'Mixed')); ?></span></div>
                                            <small class="text-muted"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($loc['operating_hours'] ?? 'Not specified'); ?></small>
                                            <?php if(!empty($loc['phone'])): ?>
                                                <br><small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($loc['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #ccc;"></i>
                                <p class="mt-2 text-muted">No recycling locations found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab" id="myLocationBtn" title="My Location">
    <i class="fas fa-location-dot fa-lg"></i>
</button>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
    // Initialize map centered on UTM
    var map = L.map('map').setView([1.5608, 103.6412], 15);
    
    // Add beautiful tile layer
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
        subdomains: 'abcd',
        maxZoom: 19,
        minZoom: 3
    }).addTo(map);
    
    // Custom marker icons for different categories
    var markerIcons = {
        mixed: L.divIcon({
            html: '<div style="background: linear-gradient(135deg, #4CAF50, #45a049); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><i class="fas fa-recycle" style="color: white; font-size: 16px;"></i></div>',
            iconSize: [32, 32],
            popupAnchor: [0, -16]
        }),
        plastic_paper: L.divIcon({
            html: '<div style="background: linear-gradient(135deg, #2196F3, #1976D2); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><i class="fas fa-bottle-water" style="color: white; font-size: 16px;"></i></div>',
            iconSize: [32, 32],
            popupAnchor: [0, -16]
        }),
        ewaste: L.divIcon({
            html: '<div style="background: linear-gradient(135deg, #FF9800, #F57C00); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><i class="fas fa-laptop" style="color: white; font-size: 16px;"></i></div>',
            iconSize: [32, 32],
            popupAnchor: [0, -16]
        }),
        paper: L.divIcon({
            html: '<div style="background: linear-gradient(135deg, #9C27B0, #7B1FA2); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><i class="fas fa-newspaper" style="color: white; font-size: 16px;"></i></div>',
            iconSize: [32, 32],
            popupAnchor: [0, -16]
        }),
        organic: L.divIcon({
            html: '<div style="background: linear-gradient(135deg, #8BC34A, #689F38); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"><i class="fas fa-apple-alt" style="color: white; font-size: 16px;"></i></div>',
            iconSize: [32, 32],
            popupAnchor: [0, -16]
        })
    };
    
    var userLocationIcon = L.divIcon({
        html: '<div style="background: linear-gradient(135deg, #2196F3, #1976D2); width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 10px rgba(0,0,0,0.2);"></div>',
        iconSize: [24, 24],
        popupAnchor: [0, -12]
    });
    
    var markers = [];
    var locations = [];
    var routingControl = null;
    var userMarker = null;
    var currentUserLocation = null;
    
    <?php 
    $locations->data_seek(0);
    while($loc = $locations->fetch_assoc()): 
        $phone = isset($loc['phone']) ? addslashes($loc['phone']) : '';
        $hours = isset($loc['operating_hours']) ? addslashes($loc['operating_hours']) : 'Not specified';
        $address = isset($loc['address']) ? addslashes($loc['address']) : 'Address not available';
    ?>
    locations.push({
        id: <?php echo $loc['id']; ?>,
        name: '<?php echo addslashes($loc['name']); ?>',
        address: '<?php echo $address; ?>',
        lat: <?php echo $loc['latitude']; ?>,
        lng: <?php echo $loc['longitude']; ?>,
        category: '<?php echo $loc['category']; ?>',
        hours: '<?php echo $hours; ?>',
        phone: '<?php echo $phone; ?>'
    });
    <?php endwhile; ?>
    
    function addMarkers(filterCategory) {
        // Remove existing markers
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];
        
        locations.forEach(loc => {
            if (filterCategory === 'all' || loc.category === filterCategory) {
                var icon = markerIcons[loc.category] || markerIcons.mixed;
                var marker = L.marker([loc.lat, loc.lng], { icon: icon }).addTo(map);
                
                var phoneHtml = loc.phone ? `<br><small><i class="fas fa-phone"></i> ${loc.phone}</small>` : '';
                
                var popupContent = `
                    <div style="min-width: 220px;">
                        <strong style="color: #4CAF50;">♻️ ${loc.name}</strong><br>
                        <small><i class="fas fa-location-dot"></i> ${loc.address}</small><br>
                        <small><i class="fas fa-clock"></i> ${loc.hours}</small>
                        ${phoneHtml}
                        <br><span class="badge bg-success mt-2">${loc.category.replace('_', ' ').toUpperCase()}</span>
                        <hr class="my-1">
                        <button class="btn btn-sm btn-success w-100 mt-1" onclick="getDirections(${loc.lat}, ${loc.lng}, '${loc.name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-directions"></i> Get Directions
                        </button>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markers.push(marker);
            }
        });
    }
    
    window.getDirections = function(lat, lng, name) {
        if (currentUserLocation) {
            if (routingControl) {
                map.removeControl(routingControl);
            }
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(currentUserLocation.lat, currentUserLocation.lng),
                    L.latLng(lat, lng)
                ],
                routeWhileDragging: true,
                showAlternatives: false,
                lineOptions: {
                    styles: [{ color: '#4CAF50', weight: 4, opacity: 0.8 }]
                },
                createMarker: function() { return null; }
            }).addTo(map);
            
            L.popup()
                .setLatLng([lat, lng])
                .setContent(`<div class="text-center"><i class="fas fa-check-circle text-success"></i> Route to ${name}<br><small>Follow the green line</small></div>`)
                .openOn(map);
        } else {
            alert('Please enable location services to get directions.');
            getCurrentLocation();
        }
    };
    
    function getCurrentLocation() {
        if ("geolocation" in navigator) {
            var btn = document.getElementById('myLocationBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            navigator.geolocation.getCurrentPosition(function(position) {
                var userLat = position.coords.latitude;
                var userLng = position.coords.longitude;
                currentUserLocation = { lat: userLat, lng: userLng };
                
                if (userMarker) {
                    map.removeLayer(userMarker);
                }
                
                userMarker = L.marker([userLat, userLng], { icon: userLocationIcon })
                    .bindPopup('<div class="text-center"><strong>📍 You are here!</strong></div>')
                    .addTo(map);
                
                map.setView([userLat, userLng], 16);
                userMarker.openPopup();
                btn.innerHTML = '<i class="fas fa-location-dot fa-lg"></i>';
                
                // Add accuracy circle
                var accuracyCircle = L.circle([userLat, userLng], {
                    radius: position.coords.accuracy,
                    color: '#2196F3',
                    fillColor: '#2196F3',
                    fillOpacity: 0.1,
                    weight: 1
                }).addTo(map);
                
                setTimeout(function() {
                    map.removeLayer(accuracyCircle);
                }, 5000);
                
            }, function(error) {
                btn.innerHTML = '<i class="fas fa-location-dot fa-lg"></i>';
                console.log('Geolocation error:', error);
            });
        } else {
            alert('Geolocation is not supported by your browser.');
        }
    }
    
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            addMarkers(this.dataset.category);
        });
    });
    
    // Location card click handler
    document.querySelectorAll('.location-card').forEach(card => {
        card.addEventListener('click', function() {
            var lat = parseFloat(this.dataset.lat);
            var lng = parseFloat(this.dataset.lng);
            map.setView([lat, lng], 18);
            
            // Find and open the corresponding marker
            var targetMarker = markers.find(m => 
                Math.abs(m.getLatLng().lat - lat) < 0.0001 && 
                Math.abs(m.getLatLng().lng - lng) < 0.0001
            );
            if (targetMarker) {
                targetMarker.openPopup();
            }
        });
    });
    
    // Initialize map
    addMarkers('all');
    
    // My Location button
    document.getElementById('myLocationBtn').addEventListener('click', getCurrentLocation);
    
    // Mobile menu toggle
    var mobileToggleBtn = document.getElementById('mobileToggleBtn');
    var mobileMenu = document.getElementById('mobileMenu');
    
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
    
    // Auto get location on load
    setTimeout(function() {
        getCurrentLocation();
    }, 1000);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>