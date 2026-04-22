<?php
$api_key = 'AIzaSyCRmWcShWh_XxinN1Rpg83WATXtO43NUfc';

echo "<h1>Final Gemini API Test</h1>";

// First, list all available models
echo "<h2>Step 1: Finding available models...</h2>";

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    $data = json_decode($response, true);
    echo "<h3>Available models:</h3>";
    echo "<ul>";
    foreach ($data['models'] as $model) {
        echo "<li>" . $model['name'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Error getting models: $response</p>";
}

// Try the correct model name from the list
echo "<h2>Step 2: Testing correct model...</h2>";

// Use the model name exactly as shown in the list
$model_name = "models/gemini-1.5-flash";
$url = "https://generativelanguage.googleapis.com/v1beta/" . $model_name . ":generateContent?key=" . $api_key;

echo "<p>Testing URL: $url</p>";

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say "Hello, API is working"']
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
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'];
    echo "<p style='color:green'>✓ SUCCESS! Response: $text</p>";
    echo "<p style='color:green; font-size:18px;'>🎉 YOUR API KEY IS WORKING! 🎉</p>";
} else {
    echo "<p style='color:red'>Failed. Response: $response</p>";
}
?>