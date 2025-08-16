<?php
session_start();
require_once 'methods/database.php';

// Set the default time zone to IST
date_default_timezone_set('Asia/Kolkata');

// Check if the user is logged in and login_time is in the session
if (isset($_SESSION['id']) && isset($_SESSION['login_time'])) {
    $user_id = $_SESSION['id'];
    $login_time = $_SESSION['login_time'];
    echo $login_time;
    // Capture the current time
    $attendance_date = date('Y-m-d', strtotime($login_time));

    $current_time = date('Y-m-d H:i:s');

    $max_logout_time = $attendance_date . ' 19:00:00';
    $logout_time = min($current_time, $max_logout_time);

    // Update the logout time in the attendance table for the specific login_time
    $attendance_sql = "UPDATE attendance SET logout_time = ? WHERE user_id = ? AND login_time = ?";
    $stmt = $link->prepare($attendance_sql);
    $stmt->bind_param("sis", $current_time, $user_id, $login_time);
    $stmt->execute();
    $stmt->close();
// Adjust logout time and calculate total work time

// Update attendance record
// $sql = "UPDATE attendance SET logout_time = '$logout_time', total_work_time = $total_work_time WHERE user_id = $user_id AND attendance_date = CURDATE()";
// $conn->query($sql);

    // Optionally, update the last_login in the users table
    $update_sql = "UPDATE users SET last_login = ? WHERE id = ?";
    $stmt = $link->prepare($update_sql);
    $stmt->bind_param("si", $current_time, $user_id);
    $stmt->execute();
    $stmt->close();
}
?>