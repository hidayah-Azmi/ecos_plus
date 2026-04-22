<?php
echo "<h1>PHP Configuration Check</h1>";

// Check cURL
if (extension_loaded('curl')) {
    echo "<p style='color:green'>✓ cURL extension is enabled</p>";
} else {
    echo "<p style='color:red'>✗ cURL extension is NOT enabled. Please enable it in php.ini</p>";
}

// Check file uploads
if (ini_get('file_uploads')) {
    echo "<p style='color:green'>✓ File uploads are enabled</p>";
} else {
    echo "<p style='color:red'>✗ File uploads are disabled</p>";
}

// Check upload max size
echo "<p>Upload max filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post max size: " . ini_get('post_max_size') . "</p>";

// Test Gemini API connection
$api_key = 'AIzaSyDp8tt7vUaXckCAF3DwmNTcTCjfzKZi9gs';
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . $api_key;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>Gemini API Connection Test:</h3>";
if ($http_code == 200) {
    echo "<p style='color:green'>✓ Gemini API is accessible!</p>";
} else {
    echo "<p style='color:red'>✗ Gemini API connection failed (HTTP $http_code)</p>";
    echo "<p>Your API key might be invalid or the service is not accessible.</p>";
}
?>