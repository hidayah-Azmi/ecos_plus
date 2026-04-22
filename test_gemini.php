<?php
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Gemini API Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; border-radius: 15px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<div class='container'>
    <div class='card'>
        <div class='card-header bg-primary text-white'>
            <h3>Gemini API Diagnostic Tool</h3>
        </div>
        <div class='card-body'>";

$api_key = 'AIzaSyDp8tt7vUaXckCAF3DwmNTcTCjfzKZi9gs';

// Test 1: Check cURL
echo "<div class='card'><div class='card-header'>Test 1: cURL Status</div><div class='card-body'>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "<p class='success'>✓ cURL is installed. Version: " . $curl_version['version'] . "</p>";
} else {
    echo "<p class='error'>✗ cURL is NOT installed!</p>";
}
echo "</div></div>";

// Test 2: CORRECTED API endpoint - using v1 not v1beta
echo "<div class='card'><div class='card-header'>Test 2: Basic API Connection (Corrected)</div><div class='card-body'>";

// CORRECT ENDPOINT - use v1 instead of v1beta
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $api_key;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say "API is working"']
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
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<p>URL: " . htmlspecialchars($url) . "</p>";
echo "<p>HTTP Status Code: <strong>$http_code</strong></p>";

if ($curl_error) {
    echo "<p class='error'>cURL Error: $curl_error</p>";
}

if ($http_code == 200) {
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $result['candidates'][0]['content']['parts'][0]['text'];
        echo "<p class='success'>✓ API is working! Response: <strong>$text</strong></p>";
    } else {
        echo "<p class='warning'>Response received but unexpected format</p>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
} else {
    echo "<p class='error'>✗ API connection failed!</p>";
    echo "<p>Response: " . htmlspecialchars(substr($response, 0, 300)) . "</p>";
}
echo "</div></div>";

// Test 3: Image recognition test
echo "<div class='card'><div class='card-header'>Test 3: Image Recognition Test</div><div class='card-body'>";
echo "<p>Upload an image to test Gemini's vision capabilities:</p>";
echo '<form method="POST" enctype="multipart/form-data" action="">';
echo '<input type="file" name="test_image" accept="image/*" class="form-control mb-3" required>';
echo '<button type="submit" name="test_recognition" class="btn btn-primary">Test Recognition</button>';
echo '</form>';

if (isset($_POST['test_recognition']) && isset($_FILES['test_image']) && $_FILES['test_image']['error'] === 0) {
    echo "<hr><h5>Recognition Result:</h5>";
    
    $image_data = file_get_contents($_FILES['test_image']['tmp_name']);
    $image_base64 = base64_encode($image_data);
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'What type of recyclable material is in this image? Answer with ONE word from: Plastic, Paper, Glass, E-Waste, Organic, Metal, Cardboard, Textile'],
                    [
                        'inline_data' => [
                            'mime_type' => 'image/jpeg',
                            'data' => $image_base64
                        ]
                    ]
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $result['candidates'][0]['content']['parts'][0]['text'];
            echo "<p class='success'>✓ Recognition Result: <strong>" . htmlspecialchars($text) . "</strong></p>";
        } else {
            echo "<p class='warning'>Could not parse response</p>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        }
    } else {
        echo "<p class='error'>Recognition failed. HTTP Code: $http_code</p>";
        echo "<p>Response: " . htmlspecialchars(substr($response, 0, 300)) . "</p>";
    }
}
echo "</div></div>";

echo "</div></div></div></body></html>";
?>