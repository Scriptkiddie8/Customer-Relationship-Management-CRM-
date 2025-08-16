<?php
session_start();

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Include database connection file
require_once 'methods/database.php';

// Fetch leads from the database
$leads = [];
$user_id = $_SESSION["id"]; // Assuming user ID is stored in the session

$sql = "
    SELECT l.id, l.name, l.phone, l.email, l.source, l.form, l.channel, l.stage, l.owner, l.labels, l.progress, l.category, u.first_name AS owner_name
    FROM leads l
    LEFT JOIN users u ON l.owner = u.id
    WHERE l.owner = ?
";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $leads[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
}

// Handle lead progress update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_progress'])) {
    $lead_id = $_POST['lead_id'];
    $progress = $_POST['progress'];
    $category = $_POST['category'];

    // Update the lead progress
    $sql = "UPDATE leads SET progress = ?, category = ? WHERE id = ? AND owner = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssii", $progress,$category, $lead_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Redirect to the same page to see changes
            header("location: showmyleads.php");
            exit;
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Close database connection
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leads</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
    }

    .leads-container {
        width: 90%;
        margin: 20px auto;
        padding: 20px;
        background: #fff;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border-radius: 5px;
    }

    h2 {
        text-align: center;
        color: #333;
    }

    .section {
        margin-bottom: 20px;
        padding: 10px;
        border-radius: 5px;
        background: #f9f9f9;
    }

    .section h3 {
        margin-top: 0;
        color: #333;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table,
    th,
    td {
        border: 1px solid #ddd;
    }

    th,
    td {
        padding: 8px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }

    .btn-update {
        background-color: #4caf50;
        color: white;
        border: none;
        padding: 5px 10px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 14px;
        margin: 4px 2px;
        cursor: pointer;
        border-radius: 4px;
    }

    /* Add styles for the progress categories */
    .progress-lost {
        background-color: #f44336;
        color: white;
    }

    .progress-not-interested {
        background-color: #ff9800;
        color: white;
    }

    .progress-follow-up {
        background-color: #ffc107;
        color: black;
    }

    .progress-buys {
        background-color: #4caf50;
        color: white;
    }

    .progress-didnt-connect {
        background-color: #9e9e9e;
        color: white;
    }

    .progress-first-call-done {
        background-color: #03a9f4;
        color: white;
    }

    .progress-quote-sent {
        background-color: #00bcd4;
        color: white;
    }

    .progress-converted {
        background-color: #8bc34a;
        color: white;
    }

    /* Add styles for client categories */
    .category-b2b {
        background-color: #e0f7fa;
    }

    .category-sole-client {
        background-color: #f1f8e9;
    }
    </style>
</head>

<body>
    <div class="leads-container">
        <h2>My Leads</h2>

        <?php
        $progress_categories = [
            'Lost',
            'Not Interested',
            'Follow-up',
            'Buys',
            'Didn\'t Connect',
            'First Call Done',
            'Quote Sent',
            'Converted'
        ];

        foreach ($progress_categories as $category) {
            echo '<div class="section">';
            echo '<h3>' . htmlspecialchars($category) . '</h3>';
            echo '<table>';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Name</th>';
            echo '<th>Source</th>';
            echo '<th>Owner</th>';
            echo '<th>Labels</th>';
            echo '<th>Phone</th>';
            echo '<th>Email</th>';
            echo '<th>Form</th>';
            echo '<th>Channel</th>';
            echo '<th>Stage</th>';
            echo '<th>Progress</th>';
            echo '<th>Category</th>';
            echo '<th>Actions</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($leads as $lead) {
                if ($lead['progress'] == $category) {
                    echo '<tr class="' . 'progress-' . strtolower(str_replace(' ', '-', $lead['progress'])) . ' ' . 'category-' . strtolower(str_replace(' ', '-', $lead['category'])) . '">';
                    echo '<td>' . htmlspecialchars($lead['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['source']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['owner_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['labels']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['form']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['channel']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['stage']) . '</td>';
                    echo '<td class="' . 'progress-' . strtolower(str_replace(' ', '-', $lead['progress'])) . '">' . htmlspecialchars($lead['progress']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['category']) . '</td>';
                    echo '<td>';
                    echo '<form action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" method="post" style="display:inline;">';
                    echo '<input type="hidden" name="lead_id" value="' . $lead['id'] . '">';
                    echo '<select name="progress">';
                    foreach ($progress_categories as $progress_option) {
                        echo '<option value="' . $progress_option . '" ' . ($lead['progress'] == $progress_option ? 'selected' : '') . '>' . $progress_option . '</option>';
                    }
                    echo '</select>';
                    echo '<select name="category">';
                    echo '<option value="B2B" ' . ($lead['category'] == 'B2B' ? 'selected' : '') . '>B2B</option>';
                    echo '<option value="Sole Client" ' . ($lead['category'] == 'Sole Client' ? 'selected' : '') . '>Sole Client</option>';
                    echo '</select>';
                    echo '<input type="submit" name="update_progress" value="Update Progress" class="btn-update">';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        ?>
    </div>
</body>

</html>