<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config.php';

$api_status = [];

foreach (GEMINI_API_KEYS as $index => $key) {
    if (empty($key)) {
        $api_status[] = ['key' => "Key " . ($index + 1), 'status' => 'Empty', 'color' => 'warning'];
        continue;
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $key;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $status = $http_code === 200 ? 'Working' : 'Failed';
    $color = $http_code === 200 ? 'success' : 'danger';
    $api_status[] = [
        'key' => substr($key, 0, 15) . '...',
        'status' => $status,
        'color' => $color,
        'http_code' => $http_code
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>API Status - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Gemini API Keys Status</h1>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Status</th>
                    <th>HTTP Code</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($api_status as $api): ?>
                <tr class="table-<?php echo $api['color']; ?>">
                    <td><?php echo $api['key']; ?></td>
                    <td><?php echo $api['status']; ?></td>
                    <td><?php echo $api['http_code']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="../dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</body>
</html>