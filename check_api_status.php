<?php
$api_key = 'AIzaSyD630X9nZxoM60FbfqViCd6wRx6iZfhPOc'; // Guna API key baru awak

echo "<h1>API Key Status Check</h1>";

// Test 1: List models (check if API key works)
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>1. API Key Validation:</h3>";
echo "HTTP Code: $http_code<br>";

if ($http_code == 200) {
    echo "<span style='color:green'>✓ API key is VALID</span><br>";
    $data = json_decode($response, true);
    echo "Models available: " . count($data['models']) . "<br>";
} elseif ($http_code == 403) {
    echo "<span style='color:red'>✗ API key is INVALID or billing not enabled</span><br>";
} else {
    echo "<span style='color:orange'>Response: " . htmlspecialchars(substr($response, 0, 200)) . "</span><br>";
}

// Test 2: Check rate limit headers (if possible)
echo "<h3>2. Rate Limit Information:</h3>";
echo "Free tier limits for Gemini 2.5 Flash:<br>";
echo "- 250 requests per day (RPD)<br>";
echo "- 10 requests per minute (RPM)<br>";
echo "<br>";

// Test 3: Simple text generation
echo "<h3>3. Testing Text Generation (counts as 1 request):</h3>";

$url2 = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say "Hello"']
            ]
        ]
    ]
];

$ch = curl_init($url2);
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
    echo "<span style='color:green'>✓ Working! Response: $text</span><br>";
} elseif ($http_code == 429) {
    echo "<span style='color:red'>✗ RATE LIMIT REACHED! You've used all your daily requests.</span><br>";
    echo "Try again tomorrow or get a new API key.<br>";
} else {
    echo "<span style='color:red'>Error: " . htmlspecialchars($response) . "</span><br>";
}

echo "<hr>";
echo "<h3>💡 Solutions if you see 429 (Rate Limit):</h3>";
echo "<ol>";
echo "<li>Wait until tomorrow - limit resets daily</li>";
echo "<li>Create a NEW API key at <a href='https://aistudio.google.com/app/apikey' target='_blank'>Google AI Studio</a></li>";
echo "<li>Use a different Google account to create new API key</li>";
echo "<li>Switch to a different model like 'gemini-2.0-flash' (different limit)</li>";
echo "</ol>";
?>