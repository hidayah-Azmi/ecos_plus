<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecos_plus');

// Allowed email domain
define('ALLOWED_EMAIL_DOMAIN', '@adab.umpsa.edu.my');

// Development mode
define('DEV_MODE', true);
define('SITE_NAME', 'Ecos+');
define('SITE_URL', 'http://localhost/new-ecos-plus/');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

// Close connection
function closeConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
        $conn = null;
    }
}

// Validate email domain
function validateEmailDomain($email) {
    $domain = substr(strrchr($email, "@"), 0);
    return $domain === ALLOWED_EMAIL_DOMAIN;
}

// Send email (development logging)
function sendEmail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Ecos+ <noreply@ecosplus.com>" . "\r\n";
    
    if (DEV_MODE) {
        $logFile = dirname(__DIR__) . '/email_log.txt';
        $logEntry = date('Y-m-d H:i:s') . " - To: $to\nSubject: $subject\nMessage: $message\n\n---\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        $htmlLog = dirname(__DIR__) . '/email_preview.html';
        $htmlContent = "<!DOCTYPE html><html><head><title>Email Preview</title></head><body>";
        $htmlContent .= "<h3>Email to: $to</h3>";
        $htmlContent .= "<h4>Subject: $subject</h4>";
        $htmlContent .= "<div style='border:1px solid #ddd; padding:20px; margin:10px 0;'>$message</div>";
        $htmlContent .= "<hr><p>Sent at: " . date('Y-m-d H:i:s') . "</p>";
        $htmlContent .= "</body></html>";
        file_put_contents($htmlLog, $htmlContent);
        return true;
    }
    
    return mail($to, $subject, $message, $headers);
}

// NOTE: generateToken() is now ONLY in auth.php - removed from here
?>