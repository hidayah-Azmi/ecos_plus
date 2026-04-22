<?php
require_once 'config.php';

echo "<h2>Testing API Keys</h2>";

foreach (GEMINI_API_KEYS as $index => $key) {
    if (empty($key)) {
        echo "Key " . ($index + 1) . ": <span style='color:orange'>EMPTY</span><br>";
        continue;
    }
    
    $result = testApiKey($key);
    if ($result) {
        echo "Key " . ($index + 1) . ": <span style='color:green'>WORKING ✓</span><br>";
    } else {
        echo "Key " . ($index + 1) . ": <span style='color:red'>INVALID ✗</span><br>";
    }
}
?>