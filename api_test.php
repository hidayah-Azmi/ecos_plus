<?php
$api_key = 'AIzaSyDocphUtJI-RX0iYep1hIk0R5IoOU5s9vI'; // Your actual API key
$image_path = 'assets/uploads/test_image.jpg';

// Test with a sample prompt
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-pro-vision:generateContent?key=" . $api_key;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'What recyclable material is this? Answer with one word: plastic, paper, glass, metal, or others']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $http_code . "\n";
echo "Response: " . $response . "\n";
?>