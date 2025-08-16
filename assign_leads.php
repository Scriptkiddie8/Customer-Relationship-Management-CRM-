<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if user is logged in and if they are an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'Admin') {
    header("location: index.php");
    exit;
}

// Fetch all users (team members) to assign leads to
$sql_users = "SELECT id, username FROM users WHERE role = 'team_member'";
$users = [];
if ($stmt_users = mysqli_prepare($link, $sql_users)) {
    mysqli_stmt_execute($stmt_users);
    mysqli_stmt_bind_result($stmt_users, $user_id, $username);
    while (mysqli_stmt_fetch($stmt_users)) {
        $users[] = ['id' => $user_id, 'username' => $username];
    }
    mysqli_stmt_close($stmt_users);
}

// Fetch leads that are not yet assigned
$sql_leads = "SELECT id, name, email FROM leads WHERE owner IS NULL";
$leads = [];
if ($stmt_leads = mysqli_prepare($link, $sql_leads)) {
    mysqli_stmt_execute($stmt_leads);
    mysqli_stmt_bind_result($stmt_leads, $lead_id, $lead_name, $lead_email);
    while (mysqli_stmt_fetch($stmt_leads)) {
        $leads[] = ['id' => $lead_id, 'name' => $lead_name, 'email' => $lead_email];
    }
    mysqli_stmt_close($stmt_leads);
}

// Handle lead assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["assign_lead"])) {
    $lead_id = $_POST["lead_id"];
    $assigned_to = $_POST["assigned_to"];

    // Assign the lead to the selected team member
    $sql_assign = "UPDATE leads SET assigned_to = ? WHERE id = ? AND assigned_to IS NULL";
    if ($stmt_assign = mysqli_prepare($link, $sql_assign)) {
        mysqli_stmt_bind_param($stmt_assign, "ii", $assigned_to, $lead_id);
        if (mysqli_stmt_execute($stmt_assign)) {
            // Redirect to refresh the lead list
            header("Location: assignlead.php");
            exit;
        } else {
            echo "Error assigning lead: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_assign);
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Leads</title>
    <link rel="stylesheet" href="css/assign_leads.css">
</head>

<body>
    <div class="container">
        <h1>Assign Leads</h1>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Assign To</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr>
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo htmlspecialchars($lead['email']); ?></td>
                    <td>
                        <form action="assignlead.php" method="post">
                            <select name="assigned_to">
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                    </td>
                    <td>
                        <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead['id']); ?>">
                        <button type="submit" name="assign_lead">Assign Lead</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>