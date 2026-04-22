<?php
require_once 'includes/auth.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'No post ID']);
    exit;
}

$post_id = intval($_GET['post_id']);
$conn = getConnection();

$sql = "SELECT c.*, u.full_name, u.username 
        FROM community_comments c 
        INNER JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? 
        ORDER BY c.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while($row = $result->fetch_assoc()) {
    $comments[] = [
        'id' => $row['id'],
        'full_name' => $row['full_name'],
        'username' => $row['username'],
        'content' => $row['content'],
        'initial' => strtoupper(substr($row['full_name'], 0, 1)),
        'time' => date('M d, Y h:i A', strtotime($row['created_at']))
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'comments' => $comments]);
?>