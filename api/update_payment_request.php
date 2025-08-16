<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *"); // Allow requests from any origin
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific HTTP methods
header("Access-Control-Allow-Headers: Content-Type"); // Allow specific headers
// Database linkection
require '../methods/database.php'; // Ensure this file correctly linkects to your database

// Check if $link is defined
if (!isset($link)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database linkection failed.']);
    exit;
}

// Get the action and request ID from the POST data
$data = json_decode(file_get_contents('php://input'), true);
$action = isset($data['action']) ? $data['action'] : '';
$request_id = isset($data['request_id']) ? intval($data['request_id']) : 0;

if ($action === '' || !in_array($action, ['approve', 'decline']) || $request_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid action or request ID.']);
    exit;
}

// Determine the new status based on the action
$new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

// Update the payment request status
$sql = "UPDATE payment_requests SET payment_status = ? WHERE request_id = ?";
$stmt = $link->prepare($sql);
if ($stmt === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Prepare failed: ' . htmlspecialchars($link->error)]);
    exit;
}

$stmt->bind_param('si', $new_status, $request_id);
if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => 'Payment request has been ' . htmlspecialchars($new_status) . '.']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error: ' . htmlspecialchars($stmt->error)]);
}

$stmt->close();
$link->close();
?>
