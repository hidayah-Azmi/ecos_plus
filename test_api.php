<?php
// test_api.php - Complete AI Model Tester

// ============================================
// PASTE YOUR GEMINI API KEY HERE
// ============================================
$api_key = 'AIzaSyDSwniPc0kfsWkQtRmb9yUejAtrRokRH_M'; // <-- REPLACE WITH YOUR ACTUAL API KEY
// ============================================

// Jika tiada API key
if ($api_key == 'YOUR_GEMINI_API_KEY_HERE' || empty($api_key)) {
    die('<div style="background:#f8d7da; color:#721c24; padding:20px; border-radius:10px; font-family:Arial;">
            <h2>❌ API Key Required</h2>
            <p>Please set your Gemini API key in the <strong>$api_key</strong> variable at the top of this file.</p>
            <p>Get your free API key from: <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a></p>
          </div>');
}

// List of models to test
$models = [
    'gemini-2.0-flash-exp' => 'Latest Flash Model',
    'gemini-1.5-flash' => 'Fast & Efficient (Recommended)',
    'gemini-1.5-pro' => 'Powerful Model',
    'gemini-1.0-pro' => 'Legacy Model',
    'gemini-pro' => 'Original Pro Model',
    'gemini-pro-vision' => 'Vision Model (Deprecated)'
];

$results = [];
$working_model = null;
$working_model_name = null;

// Test each model
foreach ($models as $model => $description) {
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . $api_key;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Reply with only the word: OK']
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $results[$model] = [
        'http_code' => $http_code,
        'success' => ($http_code == 200),
        'description' => $description,
        'error' => $curl_error
    ];
    
    if ($http_code == 200 && !$working_model) {
        $working_model = $model;
        $working_model_name = $description;
        // Try to get response text
        $resp_data = json_decode($response, true);
        $response_text = $resp_data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
        $results[$model]['response'] = $response_text;
    }
}

// Check if API key is valid first by testing basic auth
$test_url = "https://generativelanguage.googleapis.com/v1/models?key=" . $api_key;
$test_ch = curl_init($test_url);
curl_setopt($test_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($test_ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($test_ch, CURLOPT_TIMEOUT, 10);
$test_response = curl_exec($test_ch);
$test_http = curl_getinfo($test_ch, CURLINFO_HTTP_CODE);
curl_close($test_ch);

$is_key_valid = ($test_http == 200);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini API Model Tester</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 30px; margin-bottom: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        h2 { font-size: 18px; margin: 20px 0 10px 0; color: #555; }
        .api-info { background: #f0f0f0; padding: 15px; border-radius: 10px; margin: 15px 0; font-family: monospace; word-break: break-all; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 15px; border-radius: 10px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px 15px; border-radius: 10px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 12px 15px; border-radius: 10px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 12px 15px; border-radius: 10px; margin: 10px 0; }
        .model-list { margin: 20px 0; }
        .model-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #eee; }
        .model-item:last-child { border-bottom: none; }
        .model-name { font-weight: 600; font-family: monospace; }
        .model-desc { font-size: 12px; color: #666; margin-left: 10px; }
        .model-status { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-working { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-left: 10px; }
        .badge-recommended { background: #4CAF50; color: white; }
        .btn { background: #4CAF50; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-size: 16px; margin-top: 15px; }
        .btn:hover { background: #45a049; }
        .vision-test { margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; }
        .preview-img { max-width: 100%; max-height: 300px; border-radius: 10px; margin-top: 10px; border: 2px solid #4CAF50; }
        hr { margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🤖 Gemini API Model Tester</h1>
        <p>Testing which AI model works with your API key</p>
        
        <!-- API Key Info -->
        <div class="api-info">
            <strong>🔑 API Key:</strong> <?php echo substr($api_key, 0, 15); ?>...<?php echo substr($api_key, -10); ?><br>
            <strong>📏 Length:</strong> <?php echo strlen($api_key); ?> characters<br>
            <strong>✅ Key Format Check:</strong> <?php echo (preg_match('/^AIza/', $api_key)) ? 'Valid format (starts with AIza)' : 'Unknown format'; ?>
        </div>
        
        <!-- API Key Validation -->
        <?php if ($is_key_valid): ?>
            <div class="success">✅ API Key is VALID! (Successfully connected to Google AI)</div>
        <?php else: ?>
            <div class="error">❌ API Key is INVALID! (HTTP <?php echo $test_http; ?>)<br>
            Please get a new API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a></div>
        <?php endif; ?>
        
        <!-- Model Testing Results -->
        <h2>📊 Model Compatibility Test</h2>
        <div class="model-list">
            <?php foreach ($results as $model => $data): ?>
            <div class="model-item">
                <div>
                    <span class="model-name"><?php echo $model; ?></span>
                    <span class="model-desc"><?php echo $data['description']; ?></span>
                </div>
                <div>
                    <?php if ($data['success']): ?>
                        <span class="model-status status-working">✅ WORKING</span>
                        <?php if ($model == $working_model): ?>
                            <span class="badge badge-recommended">⭐ RECOMMENDED</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="model-status status-failed">❌ Failed (HTTP <?php echo $data['http_code']; ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Working Model Result -->
        <?php if ($working_model): ?>
            <div class="success">
                <strong>✅ WORKING MODEL FOUND!</strong><br>
                Use: <code><?php echo $working_model; ?></code> - <?php echo $working_model_name; ?><br>
                Test response: "<?php echo htmlspecialchars($results[$working_model]['response'] ?? 'OK'); ?>"
            </div>
            
            <!-- Code to copy for activity.php -->
            <div class="info">
                <strong>📋 Copy this code into your activity.php:</strong><br>
                <code style="display:block; background:#f4f4f4; padding:10px; margin-top:10px; border-radius:5px;">
                    // Use this model in your activity.php<br>
                    $url = "https://generativelanguage.googleapis.com/v1/models/<?php echo $working_model; ?>:generateContent?key=" . $api_key;
                </code>
            </div>
        <?php else: ?>
            <div class="error">
                <strong>❌ NO WORKING MODEL FOUND!</strong><br>
                Your API key may be invalid, expired, or doesn't have access to Gemini models.<br>
                Please get a new API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a>
            </div>
        <?php endif; ?>
        
        <!-- Vision Test Section (only if working model found) -->
        <?php if ($working_model): ?>
        <div class="vision-test">
            <h2>📸 Test Image Recognition (Vision)</h2>
            <p>Upload a photo of a recyclable item to test if AI can detect it:</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="vision_image" accept="image/*" capture="environment" required style="margin: 10px 0;">
                <button type="submit" name="test_vision" class="btn">🔍 Test Vision Detection</button>
            </form>
            
            <?php
            if (isset($_POST['test_vision']) && isset($_FILES['vision_image']) && $_FILES['vision_image']['error'] == 0):
                $image_data = file_get_contents($_FILES['vision_image']['tmp_name']);
                $image_base64 = base64_encode($image_data);
                $mime_type = mime_content_type($_FILES['vision_image']['tmp_name']);
                
                echo "<div style='margin-top: 20px;'>";
                echo "<h3>📷 Your Image:</h3>";
                echo "<img src='data:image/jpeg;base64," . $image_base64 . "' class='preview-img'>";
                
                // Test vision with the working model (use 1.5-flash for vision)
                $vision_model = ($working_model == 'gemini-1.5-flash') ? $working_model : 'gemini-1.5-flash';
                $vision_url = "https://generativelanguage.googleapis.com/v1/models/{$vision_model}:generateContent?key=" . $api_key;
                
                $prompt = "You are a recycling expert. Look at this image and tell me what type of recyclable material it is. Answer with ONLY ONE WORD from this list: Plastic, Paper, Glass, E-Waste, Organic, Metal, Cardboard, Textile. Do not add any other words.";
                
                $vision_data = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                ['inline_data' => ['mime_type' => $mime_type, 'data' => $image_base64]]
                            ]
                        ]
                    ]
                ];
                
                $ch = curl_init($vision_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vision_data));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $vision_response = curl_exec($ch);
                $vision_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                echo "<h3>🤖 AI Detection Result:</h3>";
                
                if ($vision_http == 200):
                    $vision_result = json_decode($vision_response, true);
                    $detected = trim($vision_result['candidates'][0]['content']['parts'][0]['text'] ?? 'Unknown');
                    // Clean the response
                    $detected = preg_replace('/[^a-zA-Z\-]/', '', $detected);
                    $categories = ['Plastic', 'Paper', 'Glass', 'E-Waste', 'Organic', 'Metal', 'Cardboard', 'Textile'];
                    $matched = 'Unknown';
                    foreach ($categories as $cat) {
                        if (stripos($detected, $cat) !== false || stripos($cat, $detected) !== false) {
                            $matched = $cat;
                            break;
                        }
                    }
                    echo "<div class='success'><strong>✅ AI Detected: {$matched}</strong><br>";
                    echo "Raw response: " . htmlspecialchars($detected) . "</div>";
                else:
                    echo "<div class='error'>❌ Vision API Error (HTTP {$vision_http})<br>";
                    echo "Response: " . htmlspecialchars(substr($vision_response, 0, 300)) . "</div>";
                endif;
                
                echo "</div>";
            endif;
            ?>
        </div>
        <?php endif; ?>
        
        <hr>
        
        <!-- Recommendations -->
        <div class="info">
            <strong>💡 Recommendations:</strong><br>
            - Use <code>gemini-1.5-flash</code> for best balance of speed and accuracy<br>
            - Make sure images are clear, well-lit, and show the item clearly<br>
            - For best results, take photos of single items against plain backgrounds
        </div>
        
        <!-- Troubleshooting -->
        <div class="warning">
            <strong>🔧 Troubleshooting:</strong><br>
            - If no models work, your API key may be from Google Cloud instead of AI Studio<br>
            - Get a new API key from: <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a><br>
            - Make sure billing is enabled (free tier available)<br>
            - Check your internet connection
        </div>
    </div>
</div>
</body>
</html>