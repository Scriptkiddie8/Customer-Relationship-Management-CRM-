<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in and has admin role
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] != 'Admin') {
    header("location: index.php");
    exit;
}

// Check if the ID parameter is present
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $user_id = trim($_GET["id"]);

    // Delete the user from the database
    $sql = "DELETE FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);

        if (mysqli_stmt_execute($stmt)) {
            echo(<script> window.alert("user deleted successfully ! ")</script>);
            header("location: user_management.php");
            exit;
        } else {
            echo "Error deleting user.";
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "Error preparing statement.";
    }
} else {
    echo "Invalid ID.";
}

// Close connection
mysqli_close($link);
?>
