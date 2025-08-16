<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *"); // Allow requests from any origin
header("Access-Control-Allow-Methods: POST"); // Allow only POST requests
header("Access-Control-Allow-Headers: Content-Type"); // Allow specific headers

// Include your database connection
require '../methods/database.php';

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate the received data
if (!isset($data['token']) || !isset($data['userId'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$token = $data['token'];
$userId = $data['userId'];

// Prepare and execute the query to save the token
$stmt = $link->prepare("INSERT INTO user_push_tokens (user_id, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token = ?");
$stmt->bind_param('iss', $userId, $token, $token);

if ($stmt->execute()) {
    echo json_encode(['success' => 'Token saved successfully']);
} else {
    echo json_encode(['error' => 'Failed to save token']);
}

$stmt->close();
$link->close();
?>
