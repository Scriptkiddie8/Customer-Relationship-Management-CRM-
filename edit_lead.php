<?php
session_start();

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Include database connection file
require_once 'methods/database.php';

// Define variables and initialize with empty values
$lead_id = $name = $phone = $email = $source = $form = $channel = $stage = $owner = $labels = "";
$name_err = $phone_err = $email_err = $source_err = $form_err = $channel_err = $stage_err = $owner_err = $labels_err = "";

// Get lead ID from URL
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $lead_id = trim($_GET["id"]);

    // Prepare a select statement
    $sql = "SELECT name, phone, email, source, form, channel, stage, owner, labels FROM leads WHERE id = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $lead_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $name, $phone, $email, $source, $form, $channel, $stage, $owner, $labels);
            if (mysqli_stmt_fetch($stmt)) {
                // Lead data retrieved successfully
            } else {
                echo "No record found with that ID.";
                exit;
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
            exit;
        }

        mysqli_stmt_close($stmt);
    }
} else {
    echo "Invalid ID.";
    exit;
}

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate and sanitize inputs
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter the lead's name.";
    } else {
        $name = trim($_POST["name"]);
    }

    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter the lead's phone number.";
    } else {
        $phone = trim($_POST["phone"]);
    }

    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter the lead's email address.";
    } else {
        $email = trim($_POST["email"]);
    }

    if (empty(trim($_POST["source"]))) {
        $source_err = "Please enter the source of the lead.";
    } else {
        $source = trim($_POST["source"]);
    }

    if (empty(trim($_POST["form"]))) {
        $form_err = "Please specify the form used.";
    } else {
        $form = trim($_POST["form"]);
    }

    if (empty(trim($_POST["channel"]))) {
        $channel_err = "Please specify the communication channel.";
    } else {
        $channel = trim($_POST["channel"]);
    }

    if (empty(trim($_POST["stage"]))) {
        $stage_err = "Please select the stage of the lead.";
    } else {
        $stage = trim($_POST["stage"]);
    }

    // Owner and labels are optional
    $owner = trim($_POST["owner"]) ?: 'Unassigned';
    $labels = trim($_POST["labels"]);

    // Check for errors before updating the database
    if (empty($name_err) && empty($phone_err) && empty($email_err) && empty($source_err) && empty($form_err) && empty($channel_err) && empty($stage_err)) {
        $sql = "UPDATE leads SET name = ?, phone = ?, email = ?, source = ?, form = ?, channel = ?, stage = ?, owner = ?, labels = ? WHERE id = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssssi", $name, $phone, $email, $source, $form, $channel, $stage, $owner, $labels, $lead_id);

            if (mysqli_stmt_execute($stmt)) {
                // Redirect to leads page after successful update
                header("location: leads.php");
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
    <title>CRM System - Edit Lead</title>
    <link rel="stylesheet" href="css/edit_lead.css">
</head>
<body>

<div class="edit-lead-container">
    <h2>Edit Lead</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $lead_id; ?>" method="post">
        <div>
            <label>Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>">
            <span class="error-message"><?php echo $name_err; ?></span>
        </div>
        <div>
            <label>Phone Number</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
            <span class="error-message"><?php echo $phone_err; ?></span>
        </div>
        <div>
            <label>Email Address</label>
            <input type="text" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <span class="error-message"><?php echo $email_err; ?></span>
        </div>
        <div>
            <label>Source</label>
            <input type="text" name="source" value="<?php echo htmlspecialchars($source); ?>">
            <span class="error-message"><?php echo $source_err; ?></span>
        </div>
        <div>
            <label>Form</label>
            <input type="text" name="form" value="<?php echo htmlspecialchars($form); ?>">
            <span class="error-message"><?php echo $form_err; ?></span>
        </div>
        <div>
            <label>Channel</label>
            <input type="text" name="channel" value="<?php echo htmlspecialchars($channel); ?>">
            <span class="error-message"><?php echo $channel_err; ?></span>
        </div>
        <div>
            <label>Stage</label>
            <input type="text" name="stage" value="<?php echo htmlspecialchars($stage); ?>">
            <span class="error-message"><?php echo $stage_err; ?></span>
        </div>
        <div>
            <label>Owner</label>
            <input type="text" name="owner" value="<?php echo htmlspecialchars($owner); ?>">
        </div>
        <div>
            <label>Labels</label>
            <input type="text" name="labels" value="<?php echo htmlspecialchars($labels); ?>">
        </div>
        <div>
            <input type="submit" value="Save Changes">
        </div>
    </form>
</div>

</body>
</html>
