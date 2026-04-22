<?php
session_start();
require_once 'includes/auth.php';
requireLogin();

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// API KEY
define('GEMINI_API_KEY', 'AIzaSyCRmWcShWh_XxinN1Rpg83WATXtO43NUfc');

$activity_types = [
    'Plastic' => ['points' => 10, 'icon' => '🥤'],
    'Paper' => ['points' => 5, 'icon' => '📄'],
    'Glass' => ['points' => 15, 'icon' => '🥃'],
    'E-Waste' => ['points' => 25, 'icon' => '💻'],
    'Organic' => ['points' => 8, 'icon' => '🍎'],
    'Metal' => ['points' => 20, 'icon' => '🥫'],
    'Cardboard' => ['points' => 5, 'icon' => '📦'],
    'Textile' => ['points' => 12, 'icon' => '👕']
];

function detectWithGemini($image_base64) {
    $api_key = GEMINI_API_KEY;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $api_key;
    
    $prompt = "What type of recyclable material? Answer ONE WORD: Plastic, Paper, Glass, E-Waste, Organic, Metal, Cardboard, Textile";
    
    $data = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $image_base64]]
            ]
        ]]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        $categories = ['Plastic', 'Paper', 'Glass', 'E-Waste', 'Organic', 'Metal', 'Cardboard', 'Textile'];
        foreach ($categories as $cat) {
            if (stripos($text, $cat) !== false) return $cat;
        }
    }
    return null;
}

// Handle AJAX
if (isset($_POST['ai_detect'])) {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'No image']);
        exit;
    }
    
    $image_data = file_get_contents($_FILES['image']['tmp_name']);
    $image_base64 = base64_encode($image_data);
    
    $upload_dir = 'assets/uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = 'camera_' . $user_id . '_' . time() . '.jpg';
    $image_path = $upload_dir . $filename;
    file_put_contents($image_path, $image_data);
    
    $category = detectWithGemini($image_base64);
    
    if ($category && isset($activity_types[$category])) {
        echo json_encode([
            'success' => true,
            'category' => $category,
            'points' => $activity_types[$category]['points'],
            'icon' => $activity_types[$category]['icon'],
            'image_path' => $image_path
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Cannot identify. Select manually.', 'image_path' => $image_path]);
    }
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_activity'])) {
    $activity_type = $_POST['activity_type'];
    $description = trim($_POST['description']);
    $quantity = intval($_POST['quantity']);
    $weight = floatval($_POST['weight']);
    $location = trim($_POST['location']);
    $image_path = $_POST['image_path'] ?? null;
    
    if (empty($activity_type)) {
        $error = 'Select activity type';
    } elseif (empty($image_path)) {
        $error = 'Take a photo first';
    } else {
        $points = $activity_types[$activity_type]['points'] + ($quantity * 2) + ($weight * 5);
        
        $sql = "INSERT INTO activities (user_id, activity_type, description, points_earned, image_path, location, quantity, weight, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ississii", $user_id, $activity_type, $description, $points, $image_path, $location, $quantity, $weight);
        
        if ($stmt->execute()) {
            $success = "Submitted! +$points points (pending approval)";
        } else {
            $error = "Submission failed";
        }
        $stmt->close();
    }
}

// Get recent activities
$recentQuery = "SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recentQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recentActivities = $stmt->get_result();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Recycling - Ecos+</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .activity-option { cursor: pointer; border: 2px solid #ddd; border-radius: 10px; padding: 10px; text-align: center; display: inline-block; width: 23%; margin: 1%; }
        .activity-option.selected { border-color: #4CAF50; background: #e8f5e9; }
        .activity-icon { font-size: 30px; }
        .btn-ai { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; width: 100%; border-radius: 10px; border: none; }
        .camera-btn { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
        #video { width: 100%; border-radius: 10px; }
        #canvas { display: none; }
        .preview-img { max-width: 100%; border-radius: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h3><i class="fas fa-robot"></i> AI Recycling Assistant</h3>
            <p>Powered by Gemini 2.0 Flash</p>
            <hr>
            
            <button class="btn-ai" data-bs-toggle="modal" data-bs-target="#cameraModal">
                <i class="fas fa-camera"></i> Take Photo & AI Detect
            </button>
            
            <div id="previewArea" style="display:none; margin-top:15px;">
                <label>Captured:</label>
                <img id="previewImg" class="preview-img">
            </div>
            
            <form method="POST" id="activityForm">
                <input type="hidden" name="image_path" id="image_path">
                
                <div class="mt-3">
                    <label>Material Type</label>
                    <div id="activityTypes">
                        <?php foreach ($activity_types as $type => $data): ?>
                        <div class="activity-option" data-type="<?php echo $type; ?>" data-points="<?php echo $data['points']; ?>">
                            <div class="activity-icon"><?php echo $data['icon']; ?></div>
                            <div><?php echo $type; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="activity_type" id="selected_type" required>
                </div>
                
                <div class="row mt-3">
                    <div class="col-6">
                        <label>Quantity</label>
                        <input type="number" class="form-control" name="quantity" id="quantity" min="1" value="1">
                    </div>
                    <div class="col-6">
                        <label>Weight (kg)</label>
                        <input type="number" class="form-control" name="weight" id="weight" step="0.1" value="0">
                    </div>
                </div>
                
                <div class="mt-3">
                    <label>Location</label>
                    <input type="text" class="form-control" name="location">
                </div>
                
                <div class="mt-3">
                    <label>Description</label>
                    <textarea class="form-control" name="description" rows="2" required></textarea>
                </div>
                
                <div class="mt-3">
                    <h4>Points: <span id="totalPoints" class="text-success">0</span></h4>
                </div>
                
                <button type="submit" name="submit_activity" class="btn btn-success w-100 mt-3">Submit</button>
            </form>
            
            <?php if ($error): ?>
                <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success mt-3"><?php echo $success; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h4>Recent Activities</h4>
            <hr>
            <?php while($act = $recentActivities->fetch_assoc()): ?>
                <div class="border-bottom pb-2 mb-2">
                    <strong><?php echo $act['activity_type']; ?></strong>
                    <p class="mb-0 small"><?php echo substr($act['description'], 0, 50); ?></p>
                    <small class="text-success">+<?php echo $act['points_earned']; ?> pts</small>
                    <span class="float-end badge bg-<?php echo $act['status'] == 'approved' ? 'success' : 'warning'; ?>"><?php echo ucfirst($act['status']); ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Camera Modal -->
    <button class="camera-btn" data-bs-toggle="modal" data-bs-target="#cameraModal">
        <i class="fas fa-camera fa-2x"></i>
    </button>
    
    <div class="modal fade" id="cameraModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5>Take Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="video" autoplay></video>
                    <canvas id="canvas"></canvas>
                    <div id="loading" style="display:none;">
                        <div class="spinner-border text-success"></div>
                        <p>AI analyzing...</p>
                    </div>
                    <div id="result" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="switchCam">Switch</button>
                    <button class="btn btn-success" id="capture">Capture</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let selectedType = null;
        let currentFacing = 'environment';
        let stream = null;
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let ctx = canvas.getContext('2d');
        
        document.querySelectorAll('.activity-option').forEach(opt => {
            opt.onclick = function() {
                document.querySelectorAll('.activity-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                selectedType = this.dataset.type;
                document.getElementById('selected_type').value = selectedType;
                updatePoints();
            }
        });
        
        function updatePoints() {
            if (!selectedType) return;
            let qty = document.getElementById('quantity').value || 0;
            let wt = document.getElementById('weight').value || 0;
            let points = parseInt(document.querySelector('.activity-option.selected').dataset.points);
            document.getElementById('totalPoints').innerText = points + (qty * 2) + (wt * 5);
        }
        
        document.getElementById('quantity').oninput = updatePoints;
        document.getElementById('weight').oninput = updatePoints;
        
        async function startCamera() {
            if (stream) stream.getTracks().forEach(t => t.stop());
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { exact: currentFacing } } });
                video.srcObject = stream;
            } catch(e) {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
            }
        }
        
        document.getElementById('cameraModal').addEventListener('shown.bs.modal', () => startCamera());
        document.getElementById('cameraModal').addEventListener('hidden.bs.modal', () => {
            if (stream) stream.getTracks().forEach(t => t.stop());
        });
        
        document.getElementById('switchCam').onclick = () => {
            currentFacing = currentFacing === 'environment' ? 'user' : 'environment';
            startCamera();
        };
        
        document.getElementById('capture').onclick = function() {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);
            
            canvas.toBlob(async function(blob) {
                let formData = new FormData();
                formData.append('image', blob, 'photo.jpg');
                formData.append('ai_detect', '1');
                
                document.getElementById('loading').style.display = 'block';
                document.getElementById('result').innerHTML = '';
                
                try {
                    let response = await fetch('activity_simple.php', { method: 'POST', body: formData });
                    let result = await response.json();
                    document.getElementById('loading').style.display = 'none';
                    
                    if (result.success) {
                        document.getElementById('image_path').value = result.image_path;
                        document.getElementById('previewImg').src = result.image_path + '?t=' + new Date().getTime();
                        document.getElementById('previewArea').style.display = 'block';
                        
                        document.getElementById('result').innerHTML = `
                            <div class="alert alert-success">
                                <strong>AI Detected: ${result.category}</strong><br>
                                +${result.points} points<br>
                                <button class="btn btn-sm btn-success mt-2" onclick="selectCategory('${result.category}')">Use This</button>
                            </div>
                        `;
                    } else {
                        document.getElementById('result').innerHTML = `<div class="alert alert-warning">${result.error}</div>`;
                    }
                } catch (error) {
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('result').innerHTML = '<div class="alert alert-danger">Error. Try again.</div>';
                }
            }, 'image/jpeg');
        };
        
        function selectCategory(cat) {
            let options = document.querySelectorAll('.activity-option');
            for (let opt of options) {
                if (opt.querySelector('div:last-child').innerText === cat) {
                    opt.click();
                    break;
                }
            }
            bootstrap.Modal.getInstance(document.getElementById('cameraModal')).hide();
            document.getElementById('description').value = `AI detected: ${cat}`;
        }
        
        updatePoints();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>