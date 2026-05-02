<?php
// set_api_key.php - Setup API Key once

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
    $api_key = trim($_POST['api_key']);
    
    // Save to config file
    $config_content = "<?php\n// Auto-generated API config\n\$GEMINI_API_KEY = '" . addslashes($api_key) . "';\n?>";
    file_put_contents('config/api_config.php', $config_content);
    
    echo "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:10px; margin-bottom:20px;'>
            ✅ API Key saved successfully! <a href='activity.php'>Go to Activity Page</a>
          </div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Gemini API Key</title>
    <style>
        body { font-family: Arial; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { max-width: 500px; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 10px; }
        input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 10px; font-size: 14px; margin: 15px 0; font-family: monospace; }
        button { background: #4CAF50; color: white; border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-size: 16px; width: 100%; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 10px; margin: 20px 0; font-size: 14px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
        a { color: #4CAF50; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔑 Setup Google Gemini API</h1>
    <p>Paste your Gemini API key below to enable AI detection.</p>
    
    <div class="info">
        <strong>📌 How to get API key:</strong><br>
        1. Go to <a href="https://aistudio.google.com/app/apikey" target="_blank">https://aistudio.google.com/app/apikey</a><br>
        2. Login with Google account<br>
        3. Click <strong>"Create API Key"</strong><br>
        4. Click <strong>"Create API key in new project"</strong><br>
        5. Copy the key (starts with <code>AIzaSy</code>)<br>
        6. Paste below and click Save
    </div>
    
    <form method="POST">
        <input type="text" name="api_key" placeholder="AIzaSy..." required autocomplete="off">
        <button type="submit">💾 Save API Key & Enable AI</button>
    </form>
    
    <p style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
        ⚡ AI detection will work immediately after saving
    </p>
</div>
</body>
</html>