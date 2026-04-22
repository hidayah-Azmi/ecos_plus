<?php
$api_key = 'AIzaSyCRmWcShWh_XxinN1Rpg83WATXtO43NUfc';

echo "<h1>Testing Correct Gemini Model</h1>";

// Use the correct model name from the list
$model = "gemini-2.5-flash";
$url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $api_key;

echo "<p>Testing URL: " . htmlspecialchars($url) . "</p>";

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say "API is working!"']
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

echo "<p>HTTP Status Code: $http_code</p>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'];
    echo "<p style='color:green'>✓ SUCCESS! Response: <strong>$text</strong></p>";
    echo "<p style='color:green; font-size:18px;'>🎉 YOUR API IS WORKING! 🎉</p>";
} else {
    echo "<p style='color:red'>Failed. Response: " . htmlspecialchars($response) . "</p>";
}
?>