<?php
session_start();
require_once 'methods/database.php';

// Check if the user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $holiday_date = $_POST['holiday_date'];
    $description = $_POST['description'];
    
    if ($holiday_date) {
        $sql = "INSERT INTO holidays (holiday_date, description) VALUES ('$holiday_date', '$description')";
        if ($link->query($sql)) {
            header("Location: attendance_calendar.php?user_id=" . $_POST['user_id']);
            exit();
        } else {
            echo "Error: " . $link->error;
        }
    }
}
