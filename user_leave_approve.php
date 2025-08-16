<?php
include('methods/database.php');  // Include your database connection

session_start();

// Assuming you have user ID stored in session
$userId = $_SESSION['id']; // Get the logged-in user's ID

// Fetch approved leaves for the logged-in user
$sql = "SELECT leave_type, from_date, to_date FROM leave_requests WHERE id = ? AND status = 'approved'";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Close the database connection after fetching the results
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved Leaves</title>
    <style>
        /* Add basic styles for the table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .holiday_head {
            background-color: #7380ec; /* Header background color */
        }

        .holiday_row {
            background-color: #848bc82e; /* Row background color */
        }

    </style>
</head>
<body>
    <div class="container2">
        <div class="header">
            <!-- Include header -->
            <?php include "header.php"; ?>
        </div>

        <div class="body-container">
            <div class="left">
                <!-- Left sidebar (includes leaves_request.php) -->
                <?php include 'leaves_request.php'; ?>
            </div>

            <div class="right">
                    
                    <!-- Table for displaying approved leaves -->
                    <table>
                        <thead>
                            <tr>
                                <th class="holiday_head">Leave Type</th>
                                <th class="holiday_head">From Date</th>
                                <th class="holiday_head">To Date</th>
                                <th class="holiday_head">Day(s)</th> <!-- New column for total days -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Check if there are any results
                            if (mysqli_num_rows($result) > 0) {
                                // Loop through each approved leave and display it
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $leaveType = $row['leave_type'];
                                    $fromDate = date('M j, Y', strtotime($row['from_date']));
                                    $toDate = date('M j, Y', strtotime($row['to_date']));

                                    // Calculate the total days of leave
                                    $fromDateObj = new DateTime($row['from_date']);
                                    $toDateObj = new DateTime($row['to_date']);
                                    $interval = $fromDateObj->diff($toDateObj);
                                    $totalDays = $interval->days + 1; // Add 1 to include both from and to date

                                    echo "<tr class='holiday_row'>";
                                    echo "<td>{$leaveType}</td>";
                                    echo "<td>{$fromDate}</td>";
                                    echo "<td>{$toDate}</td>";
                                    echo "<td>{$totalDays} day</td>"; // Display total days
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4'>No approved leaves found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>

            </div>
        </div>
    </div>
</body>
</html>
