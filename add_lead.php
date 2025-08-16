<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include "header.php"; ?>

<?php
// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Include database connection file
require_once 'methods/database.php';

// Define variables and initialize with empty values
$name = $phone = $email = $source = $channel = $owner = $progress = "";
$name_err = $phone_err = $email_err = $source_err = $channel_err = $owner_err = $progress_err = "";
$success_msg = "";

// Fetch sales users for the dropdown
$sales_users = [];
$sql_users = "SELECT id, username FROM users WHERE role = 'sales'";
if ($result_users = mysqli_query($link, $sql_users)) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $sales_users[] = $row;
    }
    mysqli_free_result($result_users);
}

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate and sanitize inputs
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter the lead's name.";
    } else {
        $name = trim($_POST["name"]);
    }

    // Validate phone number format (e.g., +917727808361)
    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter the lead's phone number.";
    } else {
        $phone = trim($_POST["phone"]);
        if (!preg_match('/^\+\d{10,15}$/', $phone)) {
            $phone_err = "Please enter a valid phone number in the format +<country code><number>.";
        }
    }
    $email = trim($_POST["email"]);
    // Validate email address format
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter the lead's email address.";
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        }
    }

    // if (empty(trim($_POST["source"]))) {
    //     $source_err = "Please enter the source of the lead.";
    // } else {
    //     $source = trim($_POST["source"]);
    // }

    // if (empty(trim($_POST["channel"]))) {
    //     $channel_err = "Please specify the communication channel.";
    // } else {
    //     $channel = trim($_POST["channel"]);
    // }
    $source = trim($_POST["source"]);
    if (empty(trim($_POST["progress"]))) {
        $progress_err = "Please specify the progress.";
    } else {
        $progress = trim($_POST["progress"]);
    }

    // Owner is optional
    $owner = trim($_POST["owner"]) ?: 'Unassigned';

    // Check for duplicate phone or email before inserting
    if (empty($phone_err) && empty($email_err)) {
        $sql_check = "SELECT id FROM leads WHERE phone = ?";
        if ($stmt_check = mysqli_prepare($link, $sql_check)) {
            mysqli_stmt_bind_param($stmt_check, "s", $phone);
            if (mysqli_stmt_execute($stmt_check)) {
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $phone_err = "A lead with this phone already exists.";
                }
            }
            mysqli_stmt_close($stmt_check);
        }
    }

    // Check for errors before inserting into database
    if (empty($name_err) && empty($phone_err) && empty($email_err) && empty($source_err) && empty($channel_err) && empty($progress_err)) {
        $sql = "INSERT INTO leads (name, phone, email, ad_name, channel, progress, owner) VALUES (?, ?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssss", $name, $phone, $email, $source, $channel, $progress, $owner);

            if (mysqli_stmt_execute($stmt)) {
                // Reset variables
                $name = $phone = $email = $source = $channel = $owner = $progress = "";
                $success_msg = "Lead added successfully!";
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            mysqli_stmt_close($stmt);
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
    <title>CRM System - Add Lead</title>
    <link rel="stylesheet" href="css/add_lead.css">
</head>

<body>

    <div class="add-lead-container">
        <h2>Add New Lead</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div>
                <label>Name</label>
                <input type="text" name="name" value="<?php echo $name; ?>">
                <span class="error-message"><?php echo $name_err; ?></span>
            </div>
            <div>
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?php echo $phone; ?>">
                <span class="error-message"><?php echo $phone_err; ?></span>
            </div>
            <div>
                <label>Email Address</label>
                <input type="text" name="email" value="<?php echo $email; ?>">
                <span class="error-message"><?php echo $email_err; ?></span>
            </div>
            <div>
                <label>AD Name</label>
                <input type="text" name="source" value="<?php echo $source; ?>">
                <span class="error-message"><?php echo $source_err; ?></span>
            </div>
            <div>
                <label>Channel</label>
                <input type="text" name="channel" value="<?php echo $channel; ?>">
                <span class="error-message"><?php echo $channel_err; ?></span>
            </div>
            <div>
                <label>Progress</label>
                <select name="progress" id="progress" required>
                    <option value="Fresh Lead" <?php echo $progress == 'Fresh Lead' ? 'selected' : ''; ?>>Fresh Lead</option>
                    <option value="Follow-up" <?php echo $progress == 'Follow-up' ? 'selected' : ''; ?>>Follow-up</option>
                    <option value="Converted" <?php echo $progress == 'Converted' ? 'selected' : ''; ?>>Converted</option>
                    <option value="Lost" <?php echo $progress == 'Lost' ? 'selected' : ''; ?>>Lost</option>
                    <option value="Didnt Connect" <?php echo $progress == 'Didnt Connect' ? 'selected' : ''; ?>>Didnt Connect</option>
                    <option value="First Call Done" <?php echo $progress == 'First Call Done' ? 'selected' : ''; ?>>First Call Done</option>
                    <option value="Quote Sent" <?php echo $progress == 'Quote Sent' ? 'selected' : ''; ?>>Quote Sent</option>
                    <option value="Not Interested" <?php echo $progress == 'Not Interested' ? 'selected' : ''; ?>>Not Interested</option>
                </select>
                <span class="error-message"><?php echo $progress_err; ?></span>
            </div>
            <div>
                <label>Owner</label>
                <select name="owner">
                    <option value="Unassigned" <?php echo $owner == 'Unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                    <?php foreach ($sales_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $owner == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <input type="submit" value="Add Lead">
                <span class="success-message"><?php echo $success_msg; ?></span>
            </div>
        </form>
    </div>

</body>

</html>
