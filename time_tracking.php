<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Check if the action and subtask_id are provided
if (!isset($_GET['action']) || !isset($_GET['subtask_id']) || !in_array($_GET['action'], ['start', 'stop'])) {
    die("Invalid request");
}

$subtask_id = intval($_GET['subtask_id']);
$action = $_GET['action'];
$user_id = 1; // Replace with session user ID

if ($action == 'start') {
    // Insert a new time tracking entry
    $start_time = date('Y-m-d H:i:s');
    $stmt = $link->prepare("INSERT INTO time_tracking (subtask_id, user_id, start_time) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $subtask_id, $user_id, $start_time);

    if ($stmt->execute()) {
        header("Location: subtask_details.php?subtask_id=$subtask_id");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
} elseif ($action == 'stop') {
    // Stop the time tracking entry and calculate duration
    $tracking_id = $_POST['tracking_id'];
    $end_time = date('Y-m-d H:i:s');

    $stmt = $link->prepare("UPDATE time_tracking SET end_time = ?, duration = TIMESTAMPDIFF(MINUTE, start_time, end_time) WHERE tracking_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $end_time, $tracking_id, $user_id);

    if ($stmt->execute()) {
        header("Location: subtask_details.php?subtask_id=$subtask_id");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}

$stmt->close();
$link->close();
?>