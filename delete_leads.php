<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Include database connection file
require_once 'methods/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_selected'])) {
    // Check if any lead IDs are selected
    if (isset($_POST['lead_ids']) && is_array($_POST['lead_ids'])) {
        $lead_ids = $_POST['lead_ids'];
        $ids_placeholder = implode(',', array_fill(0, count($lead_ids), '?'));

        // Prepare the SQL statement to delete the selected leads
        $sql = "DELETE FROM leads WHERE id IN ($ids_placeholder)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind parameters
            mysqli_stmt_bind_param($stmt, str_repeat('i', count($lead_ids)), ...$lead_ids);

            if (mysqli_stmt_execute($stmt)) {
                // Redirect back to the leads page
                header("Location: leads.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            mysqli_stmt_close($stmt);
        }
    } else {
        echo "No leads selected for deletion.";
    }
}

// Close database connection
mysqli_close($link);
?>