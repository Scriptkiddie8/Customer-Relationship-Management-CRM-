<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in
if (!isset($_SESSION["loggedin"])) {
    header("location: index.php");
    exit;
}

// Check if ID parameter exists
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Fetch lead details from the database
    $sql = "SELECT name, phone, email, source, form, channel, stage, owner, labels FROM leads WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $name, $phone, $email, $source, $form, $channel, $stage, $owner, $labels);
            if (mysqli_stmt_fetch($stmt)) {
                // Lead details fetched successfully
            } else {
                echo "Error fetching lead data.";
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
    <title>View Lead</title>
    <link rel="stylesheet" href="css/view_lead.css">
</head>
<body>

<div class="view-lead-container">
    <h2>Lead Details</h2>
    <div>
        <label>Name:</label>
        <p><?php echo htmlspecialchars($name); ?></p>
    </div>
    <div>
        <label>Phone:</label>
        <p><?php echo htmlspecialchars($phone); ?></p>
    </div>
    <div>
        <label>Email:</label>
        <p><?php echo htmlspecialchars($email); ?></p>
    </div>
    <div>
        <label>Source:</label>
        <p><?php echo htmlspecialchars($source); ?></p>
    </div>
    <div>
        <label>Form:</label>
        <p><?php echo htmlspecialchars($form); ?></p>
    </div>
    <div>
        <label>Channel:</label>
        <p><?php echo htmlspecialchars($channel); ?></p>
    </div>
    <div>
        <label>Stage:</label>
        <p><?php echo htmlspecialchars($stage); ?></p>
    </div>
    <div>
        <label>Owner:</label>
        <p><?php echo htmlspecialchars($owner); ?></p>
    </div>
    <div>
        <label>Labels:</label>
        <p><?php echo htmlspecialchars($labels); ?></p>
    </div>
    <a href="leads.php" class="back-button">Back to Leads</a>
</div>

</body>
</html>
