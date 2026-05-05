<?php
$page_title = 'Recycling Map';
$current_page = 'map';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/notifications.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];

// Get user for navbar
$navbar_user = getCurrentUser();
$navbar_initial = $navbar_user ? strtoupper(substr($navbar_user['full_name'], 0, 1)) : 'U';
$unreadCount = getUnreadCount($user_id);

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
    
    // Insert sample data for UMP area
    $sampleData = [
        ['Main Campus Recycling Center', 'Universiti Malaysia Pahang, Gambang, Pahang', 3.5462, 103.4264, 'mixed', 'Mon-Fri: 8am-6pm, Sat: 9am-1pm', '09-1234567'],
        ['Student Residence Recycling Point', 'Student Hostel Area, UMP Gambang', 3.5485, 103.4248, 'plastic_paper', '24 Hours', NULL],
        ['Faculty of Engineering E-Waste', 'Faculty of Engineering, UMP Gambang', 3.5448, 103.4282, 'ewaste', 'Mon-Fri: 9am-5pm', '09-1234568'],
        ['Green Campus Initiative Hub', 'Student Activity Center, UMP Gambang', 3.5501, 103.4255, 'mixed', 'Mon-Sat: 10am-8pm', '09-1234569'],
        ['Library Recycling Corner', 'UMP Library, Gambang', 3.5475, 103.4270, 'paper', 'Mon-Sun: 8am-10pm', NULL],
        ['Cafeteria Waste Station', 'Main Cafeteria, UMP Gambang', 3.5455, 103.4258, 'organic', 'Mon-Fri: 7am-7pm', NULL],
        ['Faculty of Computing Green Point', 'Faculty of Computing, UMP Gambang', 3.5490, 103.4285, 'plastic_paper', 'Mon-Fri: 8am-5pm', '09-1234570']
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
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #0a2e1a 0%, #1a4a2a 50%, #0d3b1f 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }
        
        /* Animated Leaves */
        @keyframes leafFloat1 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(-20px,-30px) rotate(10deg); } }
        @keyframes leafFloat2 { 0%,100% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(20px,-20px) rotate(-10deg); } }
        
        .leaf-bg { position: fixed; font-size: 45px; opacity: 0.06; pointer-events: none; z-index: 0; }
        .leaf-bg:nth-child(1) { top: 10%; left: 5%; animation: leafFloat1 12s ease-in-out infinite; }
        .leaf-bg:nth-child(2) { top: 70%; right: 8%; animation: leafFloat2 15s ease-in-out infinite; font-size: 60px; }
        .leaf-bg:nth-child(3) { top: 40%; left: 85%; animation: leafFloat1 18s ease-in-out infinite; }
        .leaf-bg:nth-child(4) { bottom: 15%; left: 10%; animation: leafFloat2 14s ease-in-out infinite; }
        
        /* Navbar */
        .navbar-custom { background: rgba(10, 46, 26, 0.95); backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); padding: 0; position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(76,175,80,0.3); }
        .navbar-container { max-width: 1400px; margin: 0 auto; padding: 0 25px; }
        .navbar-brand-custom { display: flex; align-items: center; gap: 10px; text-decoration: none; padding: 12px 0; }
        .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, #6B8E23, #4CAF50); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .logo-icon img { width: 30px; height: 30px; object-fit: cover; border-radius: 8px; }
        .logo-text { font-size: 22px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo-text span { color: #8BC34A; }
        .nav-links { display: flex; gap: 5px; margin: 0; padding: 0; list-style: none; }
        .nav-link-custom { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 14px; border-radius: 12px; transition: all 0.3s ease; }
        .nav-link-custom:hover { background: rgba(107, 142, 35, 0.3); color: #8BC34A; transform: translateY(-2px); }
        .nav-link-custom.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; box-shadow: 0 4px 10px rgba(76,175,80,0.3); }
        
        /* Notification */
        .notification-wrapper { position: relative; margin-right: 10px; }
        .notification-bell { background: rgba(255,255,255,0.1); border: none; color: white; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #f44336; color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 50%; }
        .user-dropdown { position: relative; cursor: pointer; }
        .user-trigger { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 40px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6B8E23, #8BC34A); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 15px; color: white; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-size: 13px; font-weight: 600; color: white; }
        .user-points { font-size: 10px; color: #FFD700; }
        .dropdown-arrow { color: rgba(255,255,255,0.6); font-size: 12px; transition: transform 0.3s; }
        .user-dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        .dropdown-menu-custom { position: absolute; top: 55px; right: 0; width: 220px; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 100; }
        .user-dropdown:hover .dropdown-menu-custom { opacity: 1; visibility: visible; top: 60px; }
        .dropdown-item-custom { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #333; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .dropdown-item-custom:hover { background: #f8f9fa; color: #6B8E23; }
        .mobile-toggle { display: none; background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 8px; }
        .mobile-menu { display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); background: #0a2e1a; z-index: 999; padding: 20px; overflow-y: auto; transform: translateX(100%); transition: transform 0.3s ease; }
        .mobile-menu.show { transform: translateX(0); display: block; }
        .mobile-nav { list-style: none; padding: 0; }
        .mobile-nav a { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 12px; font-weight: 500; }
        .mobile-nav a:hover, .mobile-nav a.active { background: rgba(107, 142, 35, 0.3); color: #8BC34A; }
        
        @media (max-width: 992px) { .nav-links { display: none; } .mobile-toggle { display: block; } .user-info { display: none; } }
        @media (max-width: 576px) { .logo-text { display: none; } }

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 25px; position: relative; z-index: 1; }
        
        #map { height: 550px; width: 100%; border-radius: 20px; z-index: 1; border: 3px solid rgba(107,142,35,0.3); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .map-card { background: rgba(255,255,255,0.95); border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
        .card-header-custom { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #6B8E23; padding: 18px 22px; font-weight: 700; font-size: 16px; }
        .location-card { cursor: pointer; transition: all 0.3s; border: 1px solid #e0e0e0; border-radius: 16px; padding: 14px; margin-bottom: 12px; background: white; }
        .location-card:hover { border-color: #6B8E23; transform: translateX(8px); background: #e8f5e9; }
        .filter-btn { margin: 5px; border-radius: 30px; padding: 6px 18px; font-size: 12px; font-weight: 500; border: 1.5px solid #6B8E23; background: white; }
        .filter-btn.active { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border-color: #6B8E23; }
        .category-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 10px; font-weight: 600; margin-top: 6px; }
        .category-mixed { background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; }
        .fab {
            position: fixed;
            bottom: 25px;
            right: 25px;
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6B8E23, #4CAF50);
            color: white;
            border: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .fab:hover { transform: scale(1.1); }
        
        @media (max-width: 768px) { .container-custom { padding: 15px; } #map { height: 380px; } .fab { width: 48px; height: 48px; } }
        #locationList { max-height: 500px; overflow-y: auto; }
    </style>
</head>
<body>

<!-- Animated Leaves -->
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>
<div class="leaf-bg"><i class="fas fa-seedling"></i></div>
<div class="leaf-bg"><i class="fas fa-tree"></i></div>
<div class="leaf-bg"><i class="fas fa-leaf"></i></div>

<!-- Navigation Bar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <a href="dashboard.php" class="navbar-brand-custom">
                <div class="logo-icon"><img src="assets/logo/12.png" alt="Logo"></div>
                <div class="logo-text">Ecos<span>+</span></div>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="nav-link-custom"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="activity.php" class="nav-link-custom"><i class="fas fa-recycle"></i> Recycle</a></li>
                <li><a href="map.php" class="nav-link-custom active"><i class="fas fa-map-marker-alt"></i> Map</a></li>
                <li><a href="leaderboard.php" class="nav-link-custom"><i class="fas fa-trophy"></i> Leaderboard</a></li>
                <li><a href="ai-insights.php" class="nav-link-custom"><i class="fas fa-robot"></i> AI Tips</a></li>
                <li><a href="community.php" class="nav-link-custom"><i class="fas fa-users"></i> Community</a></li>
                <li><a href="events.php" class="nav-link-custom"><i class="fas fa-calendar"></i> Events</a></li>
                <?php if (isAdmin()): ?>
                <li><a href="admin/dashboard.php" class="nav-link-custom"><i class="fas fa-cog"></i> Admin</a></li>
                <?php endif; ?>
            </ul>
            <div style="display: flex; align-items: center; gap: 10px;">

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
                        <a href="history.php" class="dropdown-item-custom"><i class="fas fa-history"></i> Activity History</a>
                        <div style="height: 1px; background: #f0f0f0; margin: 5px 0;"></div>
                        <a href="logout.php" class="dropdown-item-custom"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
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
        <li><a href="ai-insights.php"><i class="fas fa-robot"></i> AI Tips</a></li>
        <li><a href="community.php"><i class="fas fa-users"></i> Community</a></li>
        <li><a href="events.php"><i class="fas fa-calendar"></i> Events</a></li>
        <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
        <li><a href="rewards.php"><i class="fas fa-gift"></i> Rewards</a></li>
        <li><a href="history.php"><i class="fas fa-history"></i> History</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>

<div class="container-custom">
    <div class="row">
        <div class="col-lg-8">
            <div class="map-card">
                <div class="card-header-custom"><i class="fas fa-map-marker-alt" style="color: #6B8E23;"></i> ♻️ Campus Recycling Map</div>
                <div class="p-0"><div id="map"></div></div>
                <div class="p-2 text-center small text-muted">
                    <i class="fas fa-info-circle"></i> Click on any location marker → <strong>"Get Directions"</strong> for route from your location
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="map-card">
                <div class="card-header-custom"><i class="fas fa-sliders-h"></i> Filter Locations</div>
                <div class="p-3">
                    <div class="mb-3">
                        <button class="filter-btn active" data-category="all">📍 All Locations</button>
                        <?php foreach ($categories as $cat): ?>
                            <button class="filter-btn" data-category="<?php echo $cat; ?>">
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
                                <div class="location-card" data-lat="<?php echo htmlspecialchars($loc['latitude']); ?>" data-lng="<?php echo htmlspecialchars($loc['longitude']); ?>" data-category="<?php echo htmlspecialchars($loc['category']); ?>" data-name="<?php echo htmlspecialchars($loc['name']); ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <i class="fas fa-recycle" style="font-size: 28px; color: #6B8E23;"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div class="fw-bold">📍 <?php echo htmlspecialchars($loc['name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($loc['address']); ?></small>
                                            <div><span class="category-badge category-<?php echo $loc['category']; ?>"><?php echo ucfirst(str_replace('_', ' ', $loc['category'])); ?></span></div>
                                            <small class="text-muted"><i class="fas fa-clock"></i> 🕒 <?php echo htmlspecialchars($loc['operating_hours']); ?></small>
                                            <button class="btn btn-sm btn-success mt-2 w-100 get-direction-btn" style="border-radius: 20px; background: linear-gradient(135deg, #6B8E23, #4CAF50);">
                                                <i class="fas fa-directions"></i> Get Directions
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">No locations found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Button -->
<button class="fab" id="myLocationBtn" title="📍 My Location">
    <i class="fas fa-location-dot"></i>
</button>

<!-- Leaflet JS and Routing Machine -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
    // Initialize map
    var map = L.map('map').setView([3.5462, 103.4264], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    
    // Store markers and routing control
    var markers = [];
    var routingControl = null;
    var userMarker = null;
    var currentUserLocation = null;
    
    // Location data
    var locations = [];
    
    <?php 
    $locations->data_seek(0);
    while($loc = $locations->fetch_assoc()): 
    ?>
    locations.push({
        id: <?php echo $loc['id']; ?>,
        name: '<?php echo addslashes($loc['name']); ?>',
        address: '<?php echo addslashes($loc['address']); ?>',
        lat: <?php echo $loc['latitude']; ?>,
        lng: <?php echo $loc['longitude']; ?>,
        category: '<?php echo $loc['category']; ?>',
        hours: '<?php echo addslashes($loc['operating_hours']); ?>'
    });
    <?php endwhile; ?>
    
    // Get marker color based on category
    function getMarkerColor(category) {
        switch(category) {
            case 'mixed': return '#6B8E23';
            case 'plastic_paper': return '#2196F3';
            case 'ewaste': return '#FF9800';
            case 'paper': return '#9C27B0';
            case 'organic': return '#8BC34A';
            default: return '#6B8E23';
        }
    }
    
    function getMarkerIcon(category) {
        var color = getMarkerColor(category);
        return L.divIcon({
            html: `<div style="background: ${color}; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); border: 2px solid white;"><i class="fas fa-recycle" style="color: white; font-size: 14px;"></i></div>`,
            iconSize: [30, 30],
            popupAnchor: [0, -15]
        });
    }
    
    // Function to get directions
    window.getDirections = function(destLat, destLng, destName) {
        if (!currentUserLocation) {
            alert('📍 Please enable location services first. Click the location button.');
            getCurrentLocation();
            return;
        }
        
        // Remove existing routing if any
        if (routingControl) {
            map.removeControl(routingControl);
        }
        
        // Create new route
        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(currentUserLocation.lat, currentUserLocation.lng),
                L.latLng(destLat, destLng)
            ],
            routeWhileDragging: true,
            showAlternatives: false,
            lineOptions: {
                styles: [{ color: '#6B8E23', weight: 5, opacity: 0.8 }]
            },
            createMarker: function() { return null; },
            addWaypoints: false,
            draggableWaypoints: false,
            fitSelectedRoutes: true,
            show: true
        }).addTo(map);
        
        // Show popup with info
        L.popup()
            .setLatLng([destLat, destLng])
            .setContent(`
                <div class="text-center">
                    <strong>📍 ${destName}</strong><br>
                    <i class="fas fa-check-circle text-success"></i> Route calculated!<br>
                    <small>Follow the green line</small>
                </div>
            `)
            .openOn(map);
    };
    
    // Add markers to map
    function addMarkers(filterCategory) {
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];
        
        locations.forEach(loc => {
            if (filterCategory === 'all' || loc.category === filterCategory) {
                var icon = getMarkerIcon(loc.category);
                var marker = L.marker([loc.lat, loc.lng], { icon: icon }).addTo(map);
                
                var popupContent = `
                    <div style="min-width: 220px;">
                        <strong style="color: #6B8E23;">♻️ ${loc.name}</strong><br>
                        <small><i class="fas fa-location-dot"></i> ${loc.address}</small><br>
                        <small><i class="fas fa-clock"></i> 🕒 ${loc.hours}</small><br>
                        <span style="background: ${getMarkerColor(loc.category)}; color: white; padding: 2px 10px; border-radius: 20px; font-size: 10px; display: inline-block; margin-top: 5px;">${loc.category.replace('_', ' ').toUpperCase()}</span>
                        <hr class="my-2">
                        <button class="btn btn-sm w-100" style="background: linear-gradient(135deg, #6B8E23, #4CAF50); color: white; border: none; border-radius: 20px;" onclick="getDirections(${loc.lat}, ${loc.lng}, '${loc.name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-directions"></i> Get Directions
                        </button>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markers.push(marker);
            }
        });
    }
    
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            addMarkers(this.dataset.category);
        });
    });
    
    // Location card click - pan and show directions
    document.querySelectorAll('.location-card').forEach(card => {
        var btn = card.querySelector('.get-direction-btn');
        if (btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var lat = parseFloat(card.dataset.lat);
                var lng = parseFloat(card.dataset.lng);
                var name = card.dataset.name;
                getDirections(lat, lng, name);
            });
        }
        
        card.addEventListener('click', function(e) {
            if (e.target.classList.contains('get-direction-btn')) return;
            var lat = parseFloat(this.dataset.lat);
            var lng = parseFloat(this.dataset.lng);
            map.setView([lat, lng], 18);
        });
    });
    
    // Get user's current location
    function getCurrentLocation() {
        if ("geolocation" in navigator) {
            var btn = document.getElementById('myLocationBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            navigator.geolocation.getCurrentPosition(function(position) {
                var userLat = position.coords.latitude;
                var userLng = position.coords.longitude;
                currentUserLocation = { lat: userLat, lng: userLng };
                
                if (userMarker) map.removeLayer(userMarker);
                
                userMarker = L.marker([userLat, userLng], {
                    icon: L.divIcon({
                        html: '<div style="background: #2196F3; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"></div>',
                        iconSize: [20, 20]
                    })
                }).bindPopup('<strong>📍 You are here!</strong>').addTo(map);
                
                map.setView([userLat, userLng], 16);
                userMarker.openPopup();
                btn.innerHTML = '<i class="fas fa-location-dot"></i>';
                
            }, function(error) {
                btn.innerHTML = '<i class="fas fa-location-dot"></i>';
                alert('Unable to get your location. Please enable GPS.');
            });
        } else {
            alert('Geolocation not supported.');
        }
    }
    
    // My Location button
    document.getElementById('myLocationBtn').addEventListener('click', getCurrentLocation);
    
    // Add all markers
    addMarkers('all');
    
    // Auto get location
    setTimeout(getCurrentLocation, 1000);
    
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
    
    // Notification count
    const notificationCount = document.getElementById('notificationCount');
    function updateBadgeCount(count) { 
        if (notificationCount) { 
            notificationCount.textContent = count; 
            notificationCount.style.display = count > 0 ? 'inline-block' : 'none'; 
        } 
    }
    fetch('ajax/get_unread_count.php').then(r => r.json()).then(data => updateBadgeCount(data.count)).catch(() => {});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>