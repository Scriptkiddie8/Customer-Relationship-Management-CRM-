<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] != 'Admin') {
    header("location: index.php");
    exit;
}

// Check if ID parameter exists
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Fetch user details from the database
    $sql = "SELECT username, email, role, first_name, last_name FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $username, $email, $role, $first_name, $last_name);
            if (mysqli_stmt_fetch($stmt)) {
                // User details fetched successfully
            } else {
                echo "Error fetching user data.";
                exit;
            }
        } else {
            echo "Error executing query.";
            exit;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "Error preparing statement.";
        exit;
    }
} else {
    echo "Invalid ID.";
    exit;
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User</title>
    <link rel="stylesheet" href="css/view_user.css">
</head>
<body>

<div class="view-user-container">
    <h2>User Details</h2>
    <div>
        <label>Username:</label>
        <p><?php echo htmlspecialchars($username); ?></p>
    </div>
    <div>
        <label>Email:</label>
        <p><?php echo htmlspecialchars($email); ?></p>
    </div>
    <div>
        <label>Role:</label>
        <p><?php echo htmlspecialchars($role); ?></p>
    </div>
    <div>
        <label>First Name:</label>
        <p><?php echo htmlspecialchars($first_name); ?></p>
    </div>
    <div>
        <label>Last Name:</label>
        <p><?php echo htmlspecialchars($last_name); ?></p>
    </div>
    <a href="user_management.php" class="back-button">Back to Users</a>
</div>

</body>
</html>
