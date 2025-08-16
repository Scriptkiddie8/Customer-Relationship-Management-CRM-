<?php

// Include the database connection
include 'methods/database.php'; // Make sure this points to the correct path for your db connection file
// $session_start();
// Get the current date
$currentDate = new DateTime();
$currentMonth = $currentDate->format('m'); // Current month
$currentYear = $currentDate->format('Y'); // Current year

// Get selected month and year from the form, or default to current month
if (isset($_GET['month'])) {
    $selectedMonthYear = $_GET['month']; // Format: YYYY-MM
    $selectedDate = DateTime::createFromFormat('Y-m', $selectedMonthYear);
    $selectedMonth = $selectedDate->format('m');
    $selectedYear = $selectedDate->format('Y');
} else {
    // Default to current month if no selection is made
    $selectedMonth = $currentMonth;
    $selectedYear = $currentYear;
}

// Start date: First day of the selected month
$startDate = new DateTime($selectedYear . '-' . $selectedMonth . '-01');
// End date: Last day of the selected month
$endDate = new DateTime($selectedYear . '-' . $selectedMonth . '-01');
$endDate->modify('last day of this month');

// Define an array of day names
$daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Get all users
$usersQuery = "SELECT id, username FROM users";
$usersResult = mysqli_query($link, $usersQuery);
if (!$usersResult) {
    die("Query failed: " . mysqli_error($link));
}
$users = mysqli_fetch_all($usersResult, MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/leave_dashboard.css">
</head>

<body>
    <div class="container2">
        <header class='header'>
            <?php include "header.php"; ?>
        </header>

        <div class="body-container">
            <div class="left">
                <!-- Left content here -->
                <?php include 'leaves_request.php'; ?>
            </div>
            <div class="right">
                <div class="inner-right">
                    <div class="outer-right">
                        <div class="report">
                            <h1>Monthly Report</h1>
                            <form method="GET" action="">
                                <label for="month">Select Month:</label>
                                <input type="month" name="month" id="month" value="<?php echo $selectedYear . '-' . $selectedMonth; ?>">
                                <button type="submit">Go</button>
                            </form>
                        </div>

                        <div class="items">
                            <div class="item" id="tdays">Total Days: <?php echo $endDate->format('t'); ?></div>
                            <div class="item" id="working">Working: 22</div>
                            <div class="item" id="week">Week Offs: 4</div>
                            <div class="item" id="holidays">Holidays: 2</div>
                        </div>

                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>User</th>
                                        <th>Present</th>
                                        <th>Leave</th>
                                        <th>Absent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Loop through each day of the selected month
                                    while ($startDate <= $endDate) {
                                        // Format the date for display (e.g., Sep-1)
                                        $formattedDate = $startDate->format('Y-m-d');
                                        $displayDate = $startDate->format('M-j');
                                        // Get the day of the week (e.g., Mon)
                                        $dayOfWeek = $daysOfWeek[$startDate->format('w')];

                                        // Loop through each user
                                        foreach ($users as $user) {
                                            // Fetch attendance data for the user on the current date
                                            $attendanceQuery = "SELECT user_id FROM attendance WHERE user_id = {$user['id']} AND attendance_date = '$formattedDate'";
                                            $attendanceResult = mysqli_query($link, $attendanceQuery);
                                            $isPresent = mysqli_num_rows($attendanceResult) > 0; // Check if any rows are returned (i.e., present)

                                            echo "<tr>";
                                            echo "<td>{$displayDate}</td>";
                                            echo "<td>{$dayOfWeek}</td>";
                                            echo "<td>{$user['username']}</td>";

                                            // Present checkbox
                                            if ($isPresent) {
                                                echo '<td class="present"><input type="checkbox" checked disabled></td>';
                                            } else {
                                                echo '<td class="present"><input type="checkbox"></td>';
                                            }

                                            // Leave and Absent checkboxes
                                            echo '<td class="leave"><input type="checkbox"></td>';
                                            echo '<td class="absent"><input type="checkbox"></td>';

                                            echo "</tr>";
                                        }

                                        // Move to the next day
                                        $startDate->modify('+1 day');
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // JavaScript to set the current month and year to the month input field if not submitted yet
        document.addEventListener('DOMContentLoaded', function() {
            var monthInput = document.getElementById('month');
            if (!monthInput.value) {
                // Get the current date
                var today = new Date();
                // Extract the year and month, formatting them as YYYY-MM
                var month = today.toISOString().substring(0, 7); // YYYY-MM format
                // Set the value of the month input field
                monthInput.value = month;
            }
        });
    </script>
</body>

</html>