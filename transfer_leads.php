<?php
include "header.php";

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in and has Admin role
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'Admin') {
    header("location: index.php");
    exit;
}

// Get user ID
$user_id = $_SESSION["id"];

// Initialize variables
$leads = [];
$users = [];

// Fetch distinct leads with owner names and progress
$sql_leads = "
    SELECT DISTINCT l.id, l.name AS lead_name, l.email, l.progress, u.username AS owner_name
    FROM leads l
    JOIN users u ON l.owner = u.id
    WHERE l.owner IS NOT NULL and l.progress!= 'Converted'
";

if ($stmt_leads = mysqli_prepare($link, $sql_leads)) {
    mysqli_stmt_execute($stmt_leads);
    mysqli_stmt_bind_result($stmt_leads, $lead_id, $lead_name, $lead_email, $lead_progress, $owner_name);
    while (mysqli_stmt_fetch($stmt_leads)) {
        $leads[] = [
            'id' => $lead_id,
            'name' => $lead_name,
            'email' => $lead_email,
            'progress' => $lead_progress,
            'owner_name' => $owner_name
        ];
    }
    mysqli_stmt_close($stmt_leads);
}

// Fetch all users (excluding Admin)
$sql_users = "SELECT id, username FROM users WHERE role != 'Admin' and role != 'User'";
if ($stmt_users = mysqli_prepare($link, $sql_users)) {
    mysqli_stmt_execute($stmt_users);
    mysqli_stmt_bind_result($stmt_users, $id, $username);
    while (mysqli_stmt_fetch($stmt_users)) {
        $users[] = [
            'id' => $id,
            'username' => $username
        ];
    }
    mysqli_stmt_close($stmt_users);
}

// Handle lead transfer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["transfer_lead"])) {
    $lead_id = $_POST["lead_id"];
    $new_owner = $_POST["new_owner"];

    $sql_transfer = "UPDATE leads SET owner = ? WHERE id = ?";
    if ($stmt_transfer = mysqli_prepare($link, $sql_transfer)) {
        mysqli_stmt_bind_param($stmt_transfer, "ii", $new_owner, $lead_id);
        if (mysqli_stmt_execute($stmt_transfer)) {
            echo "Script> window.alert('Lead transferred successfully!'); </script> ";
        } else {
            echo "Error transferring lead: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_transfer);
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Leads</title>
    <link rel="stylesheet" href="css/transfer_leads.css">
</head>

<body>
    <div class="container">
        <h1>Transfer Leads</h1>

        <table>
            <thead>
                <tr>
                    <th>Lead Name</th>
                    <th>Email</th>
                    <th>Progress</th>
                    <th>Current Owner</th>
                    <th>New Owner</th>

                </tr>
            </thead>
            <tbody border="1">
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lead['name']); ?></td>
                        <td><?php echo htmlspecialchars($lead['email']); ?></td>
                        <td><?php echo htmlspecialchars($lead['progress']); ?></td>
                        <td><?php echo htmlspecialchars($lead['owner_name']); ?></td>
                        <td>
                            <form action="transfer_leads.php" method="post">
                                <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead['id']); ?>">
                                <select name="new_owner" required>
                                    <option value="">Select New Owner</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="transfer_lead">Transfer</button>
                            </form>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>