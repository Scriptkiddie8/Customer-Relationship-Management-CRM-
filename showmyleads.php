<?php
include "header.php";

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Include database connection file
require_once 'methods/database.php';

// Initialize variables
$leads = [];
$user_id = $_SESSION["id"]; // Assuming user ID is stored in the session
$search_phone = "";
$search_results = false; // Flag to check if there are search results

// Handle search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    $search_phone = trim($_POST["search_phone"]);
    $search_results = true; // Set flag to true for search results

    $sql = "
        SELECT l.id, l.name, l.phone, l.email, l.source, l.form, l.channel, l.owner, l.labels, l.created_at, l.progress, l.category, l.ad_name, u.first_name AS owner_name
        FROM leads l
        LEFT JOIN users u ON l.owner = u.id
        WHERE l.owner = ? AND l.phone LIKE ?
    ";

    if ($stmt = mysqli_prepare($link, $sql)) {
        $param_phone = '%' . $search_phone . '%';
        mysqli_stmt_bind_param($stmt, "is", $user_id, $param_phone);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $leads[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }
} else {
    // Fetch all leads if no search
    $sql = "
        SELECT l.id, l.name, l.phone, l.email, l.source, l.form, l.channel, l.owner, l.labels, l.created_at, l.progress, l.category, l.ad_name, u.first_name AS owner_name
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
} 

// Handle lead progress update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_progress'])) {
    $lead_id = $_POST['lead_id'];
    $progress = $_POST['progress'];
    $category = $_POST['category'];

    // Update the lead progress
    $sql = "UPDATE leads SET progress = ?, category = ? WHERE id = ? AND owner = ?";

    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssii", $progress, $category, $lead_id, $user_id);

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
$counter=1;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leads</title>
    <link rel="stylesheet" href="css/showmyleads.css">
    <style>
        /* Make the entire row clickable */
        .clickable-row {
            cursor: pointer;
        }

        .clickable-row a {
            color: inherit;
            text-decoration: none;
        }

        .clickable-row:hover {
            background-color: #f0f0f0;
        }

        .empty-message {
            text-align: center;
            color: #999;
        }
    </style>
</head>

<body>
    <div class="leads-container">
        <div class="header">
            <h2>My Leads</h2>
            <a href="index.php" class="btn-home">Go to Home</a>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="text" name="search_phone" placeholder="Search by phone number" value="<?php echo htmlspecialchars($search_phone); ?>">
                <input type="submit" name="search" value="Search">
                <?php if ($search_results): ?>
                    <a href="showmyleads.php" class="btn-back">Back to All Leads</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-button" onclick="openTab(event, 'tab-all')">All Leads</button>
            <?php
            $progress_categories = [
                'Lost',
                'Not Interested',
                'Follow-up',
                'Didn\'t Connect',
                'First Call Done',
                'Quote Sent',
                'Converted',
                'Fresh Lead'
            ];

            // Create tabs for progress categories
            foreach ($progress_categories as $index => $category) {
                echo '<button class="tab-button" onclick="openTab(event, \'tab-' . $index . '\')">' . htmlspecialchars($category) . '</button>';
            }
            ?>
        </div>

        <!-- All Leads Tab -->
        <div id="tab-all" class="tab-content">
            <table>
                <thead>
                    <tr>
                        <th>S.NO</th>
                        <th>Name</th>
                        <th>Source</th>
                        <th>Owner</th>
                        <th>Date</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Form</th>
                        <th>Channel</th>
                        <th>Progress</th>
                        <th>Category</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($leads as $lead) {
                        // Add a class for the progress color to the row
                        $progress_class = 'progress-' . strtolower(str_replace(' ', '-', $lead['progress']));
                        echo '<tr class="clickable-row ' . htmlspecialchars($progress_class) . '" onclick="window.location.href=\'lead_action.php?id=' . htmlspecialchars($lead['id']) . '\'">';
                        echo '<td>'.$counter.'</td>';
                        $counter+=1;
                        echo '<td>' . htmlspecialchars($lead['name']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['ad_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['owner_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['created_at']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['email']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['form']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['channel']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['progress']) . '</td>';
                        echo '<td>' . htmlspecialchars($lead['category']) . '</td>';
                        echo '</tr>';
                    }
                    ?>

                </tbody>
            </table>
        </div>

        <!-- Other Tabs for Progress Categories -->
        <?php
        foreach ($progress_categories as $index => $category) {
            echo '<div id="tab-' . $index . '" class="tab-content">';
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
            echo '<th>Progress</th>';
            echo '<th>Category</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            $hasLeads = false; // Track if we found any leads
            foreach ($leads as $lead) {
                if (trim($lead['progress']) === trim($category)) {
                    $hasLeads = true; // We found at least one lead
                    echo '<tr class="clickable-row" onclick="window.location.href=\'lead_action.php?id=' . htmlspecialchars($lead['id']) . '\'">';
                    echo '<td>' . htmlspecialchars($lead['name']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['ad_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['owner_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['created_at']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['email']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['form']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['channel']) . '</td>';
                    echo '<td class="' . 'progress-' . strtolower(str_replace(' ', '-', $lead['progress'])) . '">' . htmlspecialchars($lead['progress']) . '</td>';
                    echo '<td>' . htmlspecialchars($lead['category']) . '</td>';
                    echo '<td>';
                    echo '</tr>';
                }
            }

            // Show a message if no leads found
            if (!$hasLeads) {
                echo '<tr><td colspan="10" class="empty-message">No leads found in this category.</td></tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        ?>
    </div>
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-button");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        // Show the first tab by default
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelector(".tab-button").click();
        });
    </script>
</body>

</html>