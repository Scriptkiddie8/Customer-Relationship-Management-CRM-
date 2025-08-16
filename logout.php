<?php
session_start();
require_once 'methods/database.php';

// Set the default time zone to IST
date_default_timezone_set('Asia/Kolkata');

// Check for session role, login time, and login hours
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin' || !isset($_SESSION['logintime']) || date("H:i") < "09:30") {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Verify user ID is set in session
if (!isset($_SESSION['id'])) {
    echo "User ID not set in session.";
    exit();
}
$user_id = $_SESSION['id'];

// Capture the current time as logout time
$current_time = date('Y-m-d H:i:s');

// Determine the logout time, setting it to 19:00 if current time is past 19:00
$current_time_obj = new DateTime($current_time);
$end_of_day_time_obj = new DateTime($current_time_obj->format('Y-m-d') . ' 19:00:00');
$logout_time = ($current_time_obj > $end_of_day_time_obj) ? $end_of_day_time_obj->format('Y-m-d H:i:s') : $current_time;

// Insert a new record in the attendance table for each logout
$attendance_sql = "INSERT INTO attendance (user_id, login_time, logout_time, attendance_date) VALUES (?, ?, ?, ?)";
$stmt = $link->prepare($attendance_sql);
$attendance_date = date('Y-m-d'); // Capture the date of attendance
$login_time = $_SESSION['logintime']; // Fetch the login time from session
$stmt->bind_param("isss", $user_id, $login_time, $logout_time, $attendance_date);

if ($stmt->execute()) {
    echo "Logout time recorded successfully in attendance.";
} else {
    echo "Error recording logout time: " . $stmt->error;
}
$stmt->close();

// Update the user's last login time in the users table
$update_sql = "UPDATE users SET last_login = ? WHERE id = ?";
$stmt = $link->prepare($update_sql);
$stmt->bind_param("si", $logout_time, $user_id);
if ($stmt->execute()) {
    echo "User's last login time updated.";
} else {
    echo "Error updating last login: " . $stmt->error;
}
$stmt->close();

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: index.php");
exit();
?>
