<?php
// config.php - Central configuration file for Ecos+

// =============================================
// GEMINI API CONFIGURATION
// =============================================

// List of API keys (add multiple keys for fallback)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config/api_config.php';

$apiKeys = GEMINI_API_KEY;
$response = null;

foreach ($apiKeys as $key) {

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $key;

    // call API
    $result = file_get_contents($url);

    if ($result !== false) {
        $response = $result;
        break; // stop if success
    }
}

// Current active model (change if Google updates their models)
define('GEMINI_MODEL', 'gemini-2.0-flash-exp');  // Options: gemini-1.5-flash, gemini-1.5-pro, gemini-pro

// =============================================
// SITE CONFIGURATION
// =============================================

define('SITE_NAME', 'Ecos+');
define('SITE_URL', 'http://localhost/new-ecos-plus');
define('SITE_EMAIL', 'admin@adab.umpsa.edu.my');

// =============================================
// DEVELOPMENT MODE
// =============================================

// Set to false for production
define('DEV_MODE', true);

// Error reporting (only show errors in development)
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// =============================================
// FILE UPLOAD CONFIGURATION
// =============================================

define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_DIR', 'assets/uploads/');

// =============================================
// POINTS CONFIGURATION
// =============================================

define('POINTS_PER_PLASTIC', 10);
define('POINTS_PER_PAPER', 5);
define('POINTS_PER_GLASS', 15);
define('POINTS_PER_EWASTE', 25);
define('POINTS_PER_ORGANIC', 8);
define('POINTS_PER_METAL', 20);
define('POINTS_PER_CARDBOARD', 5);
define('POINTS_PER_TEXTILE', 12);

// Bonus points
define('POINTS_PER_QUANTITY', 2);
define('POINTS_PER_KG', 5);

// =============================================
// FUNCTION TO GET WORKING API KEY
// =============================================

/**
 * Get a working API key (rotates through keys if needed)
 * @return string|false Returns working API key or false if none work
 */
function getWorkingApiKey() {
    $keys = GEMINI_API_KEY;
    
    // Filter out empty keys
    $validKeys = array_filter($keys, function($key) {
        return !empty($key);
    });
    
    // You can implement key validation here
    // For now, return the first valid key
    return !empty($validKeys) ? reset($validKeys) : false;
}

/**
 * Check if an API key is valid (optional - can be used for testing)
 * @param string $api_key The API key to test
 * @return bool True if key is valid
 */
function testApiKey($api_key) {
    if (empty($api_key)) return false;
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

/**
 * Log API errors for debugging
 * @param string $message Error message
 * @param string $api_key The API key that failed (will be masked)
 */
function logApiError($message, $api_key = '') {
    if (!DEV_MODE) return;
    
    $log_file = dirname(__FILE__) . '/logs/api_errors.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $masked_key = !empty($api_key) ? substr($api_key, 0, 10) . '...' : 'unknown';
    $log_entry = date('Y-m-d H:i:s') . " - Key: {$masked_key} - {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>