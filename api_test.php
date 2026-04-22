<?php
// Simple Gemini API Test
$api_key = 'AIzaSyAlMXVhLIfMz4lTtvCRuje0pbOmyt-QmO8';

echo "<h1>Gemini API Simple Test</h1>";

// Test 1: Check if cURL works
echo "<h3>Test 1: Check cURL</h3>";
if (function_exists('curl_version')) {
    echo "✓ cURL is working<br>";
} else {
    echo "✗ cURL is NOT working<br>";
}

// Test 2: Try different API endpoints
echo "<h3>Test 2: Try different endpoints</h3>";

$endpoints = [
    "https://generativelanguage.googleapis.com/v1/models?key=$api_key",
    "https://generativelanguage.googleapis.com/v1beta/models?key=$api_key"
];

foreach ($endpoints as $url) {
    echo "<br>Testing: $url<br>";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code<br>";
    
    if ($http_code == 200) {
        echo "✓ SUCCESS! This endpoint works!<br>";
        $data = json_decode($response, true);
        if (isset($data['models'])) {
            echo "Available models:<br>";
            foreach ($data['models'] as $model) {
                echo "- " . $model['name'] . "<br>";
            }
        }
        break;
    } else {
        echo "✗ Failed<br>";
    }
}

// Test 3: Try text generation with a working model
echo "<h3>Test 3: Try text generation</h3>";

$url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=$api_key";

$data = [
    'contents' => [
        ['parts' => [['text' => 'Say "API is working"']]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code<br>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'];
    echo "✓ SUCCESS! Response: <strong>$text</strong><br>";
    echo "<p style='color:green'>Your API key is WORKING! Now you can use it in activity.php</p>";
} else {
    echo "✗ Failed. Response: $response<br>";
    echo "<p style='color:red'>Your API key may be invalid. Please get a new key from: https://makersuite.google.com/app/apikey</p>";
}
?>