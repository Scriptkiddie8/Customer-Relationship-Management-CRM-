<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"])) {
    header("location: index.php");
    exit;
}

// Get user ID and role
$user_id = $_SESSION["id"];
$user_role = $_SESSION["role"];

// Fetch leads assigned to the logged-in user
$sql_leads = "SELECT id, name, email, status FROM leads WHERE assigned_to = ?";
$leads = [];
if ($stmt_leads = mysqli_prepare($link, $sql_leads)) {
    mysqli_stmt_bind_param($stmt_leads, "i", $user_id);
    mysqli_stmt_execute($stmt_leads);
    mysqli_stmt_bind_result($stmt_leads, $lead_id, $lead_name, $lead_email, $lead_status);
    while (mysqli_stmt_fetch($stmt_leads)) {
        $leads[] = [
            'id' => $lead_id,
            'name' => $lead_name,
            'email' => $lead_email,
            'status' => $lead_status
        ];
    }
    mysqli_stmt_close($stmt_leads);
}

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_status"])) {
    $lead_id = $_POST["lead_id"];
    $new_status = $_POST["status"];

    $sql_update = "UPDATE leads SET status = ? WHERE id = ? AND assigned_to = ?";
    if ($stmt_update = mysqli_prepare($link, $sql_update)) {
        mysqli_stmt_bind_param($stmt_update, "sii", $new_status, $lead_id, $user_id);
        if (mysqli_stmt_execute($stmt_update)) {
            // Refresh the page to see changes
            header("Location: followups.php");
            exit;
        } else {
            echo "Error updating lead status: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_update);
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Follow-ups</title>
    <link rel="stylesheet" href="css/followups.css">
    <style>
    .lead-status-new {
        background-color: white;
    }

    .lead-status-follow-up {
        background-color: blue;
        color: white;
    }

    .lead-status-contacted {
        background-color: orange;
        color: white;
    }

    .lead-status-completed {
        background-color: green;
        color: white;
    }

    .lead-status-lost {
        background-color: red;
        color: white;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>Follow-ups</h1>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr class="lead-status-<?php echo htmlspecialchars($lead['status']); ?>">
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo htmlspecialchars($lead['email']); ?></td>
                    <td>
                        <form action="followups.php" method="post">
                            <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead['id']); ?>">
                            <select name="status">
                                <option value="new" <?php echo ($lead['status'] == 'new') ? 'selected' : ''; ?>>New
                                </option>
                                <option value="follow-up"
                                    <?php echo ($lead['status'] == 'follow-up') ? 'selected' : ''; ?>>Follow-up</option>
                                <option value="contacted"
                                    <?php echo ($lead['status'] == 'contacted') ? 'selected' : ''; ?>>Contacted</option>
                                <option value="completed"
                                    <?php echo ($lead['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="lost" <?php echo ($lead['status'] == 'lost') ? 'selected' : ''; ?>>Lost
                                </option>
                            </select>
                    </td>
                    <td>
                        <button type="submit" name="update_status">Update Status</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>