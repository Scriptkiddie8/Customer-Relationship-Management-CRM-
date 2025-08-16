<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in and has the right role (Admin or similar)
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'Admin') {
    header("location: index.php");
    exit;
}

// Get user ID
$user_id = $_SESSION["id"];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["convert_lead"])) {
    $lead_id = $_POST["lead_id"];
    $status = $_POST["conversion_status"];
    $comments = $_POST["conversion_comments"];
    $details = $_POST["conversion_details"];

    // Update lead conversion details
    $sql_update = "UPDATE leads SET conversion_status = ?, conversion_comments = ?, conversion_details = ? WHERE id = ? AND owner = ?";
    if ($stmt_update = mysqli_prepare($link, $sql_update)) {
        mysqli_stmt_bind_param($stmt_update, "sssii", $status, $comments, $details, $lead_id, $user_id);
        if (mysqli_stmt_execute($stmt_update)) {
            header("Location: convert_lead.php?id=" . urlencode($lead_id) . "&status=success");
            exit;
        } else {
            echo "Error converting lead: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_update);
    }
}

// Fetch lead details for conversion
$lead_id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
$lead = null;

$sql_lead = "SELECT id, name, email, conversion_status, conversion_comments, conversion_details FROM leads WHERE id = ? AND owner = ?";
if ($stmt_lead = mysqli_prepare($link, $sql_lead)) {
    mysqli_stmt_bind_param($stmt_lead, "ii", $lead_id, $user_id);
    mysqli_stmt_execute($stmt_lead);
    mysqli_stmt_bind_result($stmt_lead, $id, $name, $email, $status, $comments, $details);
    if (mysqli_stmt_fetch($stmt_lead)) {
        $lead = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'status' => $status,
            'comments' => $comments,
            'details' => $details
        ];
    }
    mysqli_stmt_close($stmt_lead);
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert Lead</title>
    <link rel="stylesheet" href="css/convert_lead.css">
</head>
<body>
<div class="container">
    <h1>Convert Lead</h1>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <p class="success-message">Lead converted successfully!</p>
    <?php endif; ?>

    <?php if ($lead): ?>
        <form action="convert_lead.php" method="post">
            <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead['id']); ?>">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($lead['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($lead['email']); ?></p>

            <label for="conversion_status">Conversion Status:</label>
            <select id="conversion_status" name="conversion_status" required>
                <option value="Converted" <?php if ($lead['status'] == 'Converted') echo 'selected'; ?>>Converted</option>
                <option value="Not Converted" <?php if ($lead['status'] == 'Not Converted') echo 'selected'; ?>>Not Converted</option>
            </select>

            <label for="conversion_comments">Comments:</label>
            <textarea id="conversion_comments" name="conversion_comments" rows="4"><?php echo htmlspecialchars($lead['comments']); ?></textarea>

            <label for="conversion_details">Additional Details:</label>
            <textarea id="conversion_details" name="conversion_details" rows="4"><?php echo htmlspecialchars($lead['details']); ?></textarea>

            <button type="submit" name="convert_lead">Update Conversion</button>
        </form>
    <?php else: ?>
        <p>Lead not found or you do not have permission to access it.</p>
    <?php endif; ?>
</div>
</body>
</html>
