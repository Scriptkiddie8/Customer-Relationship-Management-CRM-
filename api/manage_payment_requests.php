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

// Fetch payment requests
$sql = "SELECT pr.request_id, l.id AS lead_id, l.name AS lead_name, 
                u.username AS owner_username, 
                pr.amount, pr.payment_method, pr.payment_status, pr.created_at
        FROM payment_requests pr
        JOIN leads l ON pr.lead_id = l.id
        JOIN users u ON pr.user_id = u.id
        WHERE pr.payment_status = 'Pending'";

$result = $link->query($sql);

if ($result === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Query failed: ' . htmlspecialchars($link->error)]);
    exit;
}

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

header('Content-Type: application/json');
echo json_encode($requests);

$result->free();
$link->close();
?>
