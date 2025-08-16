<?php
include "header.php";

// Include database connection file
require_once 'methods/database.php';
// Check if the user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) ) {
    header("location: index.php");
    exit;
}

// Define variables and initialize with empty values
$update_message = $update_error = "";
$new_password = $confirm_password = "";

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if new password and confirm password are provided
    if (!empty(trim($_POST["new_password"])) && !empty(trim($_POST["confirm_password"]))) {
        $new_password = trim($_POST["new_password"]);
        $confirm_password = trim($_POST["confirm_password"]);

        // Validate that passwords match
        if ($new_password === $confirm_password) {
            // Hash the new password
            $hashed_password = md5($new_password);

            // Update the password in the database
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "si", $hashed_password, $_SESSION["id"]);

                if (mysqli_stmt_execute($stmt)) {
                    $update_message = "Password updated successfully.";
                } else {
                    $update_error = "Oops! Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        } else {
            $update_error = "Passwords do not match.";
        }
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM System - Settings</title>
    <link rel="stylesheet" href="css/settings.css">
</head>

<body>

    <div class="settings-container">
        <h2>Settings</h2>

        <!-- Change Password Form -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div>
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <input type="submit" value="Update Password">
            </div>
            <span class="message"><?php echo $update_message; ?></span>
            <span class="error-message"><?php echo $update_error; ?></span>
        </form>

    </div>

</body>

</html>