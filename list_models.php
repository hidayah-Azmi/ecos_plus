<?php
$api_key = 'AIzaSyCRmWcShWh_XxinN1Rpg83WATXtO43NUfc';

echo "<h1>Step 1: List Available Models</h1>";

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status: $http_code</p>";

if ($http_code == 200) {
    $data = json_decode($response, true);
    echo "<h3>Available Models:</h3>";
    echo "<ul>";
    foreach ($data['models'] as $model) {
        echo "<li>" . $model['name'] . " - " . $model['displayName'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>Error: " . $response . "</p>";
    echo "<p>Your API key may not have Gemini API enabled.</p>";
}
?>