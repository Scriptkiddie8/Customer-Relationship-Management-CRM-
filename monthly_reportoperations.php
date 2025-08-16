<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection setup
include 'header.php';
include 'methods/database.php';

// Default values for start and end dates
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$max_date = date('Y-m-d');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');
$selected_user_id = isset($_POST['username']) ? $_POST['username'] : '';

// Validate dates
if (strtotime($start_date) > strtotime($end_date)) {
    die("Start date cannot be after end date.");
}


// Helper function to execute a query and fetch results
function fetch_results($mysqli, $sql, $params = [])
{
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        die("Failed to prepare query: " . $mysqli->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params)); // Assuming all parameters are strings for simplicity
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        die("Failed to get result: " . $stmt->error);
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}
// Fetch all users for the dropdown
$users_sql = "SELECT id, username FROM users";
$users_result = fetch_results($link, $users_sql);

try {
    // Fetch data based on the user input
    $attendance_sql = "
    SELECT u.id, u.username, 
           MIN(a.attendance_date) AS first_login_date,
           MAX(a.attendance_date) AS last_login_date,
           COUNT(DISTINCT a.attendance_date) AS total_days_worked, 
           SUM(TIMESTAMPDIFF(HOUR, a.login_time, a.logout_time)) AS total_hours_worked
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.attendance_date BETWEEN ? AND ?
    WHERE u.id = ?
    GROUP BY u.id, u.username
";

    $attendance_data = fetch_results($link, $attendance_sql, [$start_date, $end_date, $selected_user_id]);

    // Aggregate attendance records by date
    $attendance_records_sql = "
    SELECT a.attendance_date, a.login_time, a.logout_time, lo.logout_reason, lo.logout_time AS logging_off_time
    FROM attendance a
    LEFT JOIN logging_off lo ON a.user_id = lo.user_id AND a.attendance_date = lo.attendance_date
    WHERE a.user_id = ? AND a.attendance_date BETWEEN ? AND ?";

    $attendance_records = fetch_results($link, $attendance_records_sql, [$selected_user_id, $start_date, $end_date]);

    function getWeekdaysInMonth($first_day, $last_day)
    {
        $weekdays = [];
        for ($date = clone $first_day; $date <= $last_day; $date->modify('+1 day')) {
            if ($date->format('N') < 6) { // Monday to Friday
                $weekdays[] = $date->format('Y-m-d');
            }
        }
        return $weekdays;
    }

    function calculateDailyHoursAndBreaks($logins, $logouts)
    {
        $total_work_minutes = 0;
        $total_break_minutes = 0;
        $prev_logout_time = null;

        foreach ($logins as $index => $login_time) {
            $logout_time = isset($logouts[$index]) ? $logouts[$index] : new DateTime();
            $work_interval = ($logout_time->getTimestamp() - $login_time->getTimestamp()) / 60;
            $total_work_minutes += $work_interval;

            if ($prev_logout_time) {
                $break_interval = ($login_time->getTimestamp() - $prev_logout_time->getTimestamp()) / 60;
                $total_break_minutes += $break_interval;
            }

            $prev_logout_time = $logout_time;
        }

        $total_work_minutes = (int)$total_work_minutes;
        $total_break_minutes = (int)$total_break_minutes;

        $work_hours = intdiv($total_work_minutes, 60);
        $work_minutes = $total_work_minutes % 60;

        $break_hours = intdiv($total_break_minutes, 60);
        $break_minutes = $total_break_minutes % 60;

        return [
            'work' => sprintf("%d hours %d minutes", $work_hours, $work_minutes),
            'break' => sprintf("%d hours %d minutes", $break_hours, $break_minutes)
        ];
    }

    // Helper function to convert nullable datetime string to DateTime object
    function toDateTime($datetime_str)
    {
        return $datetime_str ? new DateTime($datetime_str) : null;
    }

    // Assuming this is where you need to create DateTime objects
    function aggregateRecordsByDate($records)
    {
        $aggregated = [];
        foreach ($records as $record) {
            $date = $record['attendance_date'];
            if (!isset($aggregated[$date])) {
                $aggregated[$date] = ['logins' => [], 'logouts' => []];
            }
            $login_time = $record['login_time'] ?? null;
            $logout_time = $record['logout_time'] ?? null;

            // Use the toDateTime function to handle potential null values
            if ($login_time && $logout_time) {
                $aggregated[$date]['logins'][] = toDateTime($login_time);
                $aggregated[$date]['logouts'][] = toDateTime($logout_time);
            }
        }
        return $aggregated;
    }


    function calculateAverageBreakTime($aggregated_records)
    {
        $total_break_minutes = 0;
        $days_with_breaks = 0;

        foreach ($aggregated_records as $date => $record) {
            $logins = $record['logins'];
            $logouts = $record['logouts'];

            $hours_and_breaks = calculateDailyHoursAndBreaks($logins, $logouts);
            $break_time = $hours_and_breaks['break'];
            list($break_hours, $break_minutes) = sscanf($break_time, "%d hours %d minutes");
            $total_break_minutes += ($break_hours * 60) + $break_minutes;
            $days_with_breaks++;
        }

        if ($days_with_breaks > 0) {
            $average_break_minutes = intdiv($total_break_minutes, $days_with_breaks);
            $average_break_hours = intdiv($average_break_minutes, 60);
            $average_break_minutes = $average_break_minutes % 60;
            return sprintf("%d hours %d minutes", $average_break_hours, $average_break_minutes);
        } else {
            return "No break data available";
        }
    }

    $aggregated_records = aggregateRecordsByDate($attendance_records);
    $average_break_time = calculateAverageBreakTime($aggregated_records);
} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage());
}
// Assuming an office day is 8 hours
define('OFFICE_HOURS_PER_DAY', 8);

function calculateInactiveTime($attendance_records)
{
    $total_inactive_minutes = 0;
    $days_with_records = 0;

    foreach ($attendance_records as $date => $record) {
        $hours_and_breaks = calculateDailyHoursAndBreaks($record['logins'], $record['logouts']);

        // Convert work time to minutes
        list($work_hours, $work_minutes) = sscanf($hours_and_breaks['work'], "%d hours %d minutes");
        $work_minutes_total = ($work_hours * 60) + $work_minutes;

        // Calculate inactive time (in minutes) assuming 8 hours per day
        $inactive_minutes = max(0, (OFFICE_HOURS_PER_DAY * 60) - $work_minutes_total);
        $total_inactive_minutes += $inactive_minutes;
        $days_with_records++;
    }

    // Calculate average inactive time
    $average_inactive_minutes = $days_with_records ? intdiv($total_inactive_minutes, $days_with_records) : 0;
    $average_inactive_hours = intdiv($average_inactive_minutes, 60);
    $average_inactive_minutes = $average_inactive_minutes % 60;

    return [
        'total_inactive' => sprintf("%d hours %d minutes", intdiv($total_inactive_minutes, 60), $total_inactive_minutes % 60),
        'average_inactive' => sprintf("%d hours %d minutes", $average_inactive_hours, $average_inactive_minutes),
    ];
}

// Example of calculating not started subtask time
function calculateNotStartedSubtaskTime($subtasks)
{
    $total_not_started_minutes = 0;
    foreach ($subtasks as $subtask) {
        if ($subtask['status'] == 'not_started') {
            $total_not_started_minutes += $subtask['estimated_time'];
        }
    }
    $not_started_hours = intdiv($total_not_started_minutes, 60);
    $not_started_minutes = $total_not_started_minutes % 60;
    return sprintf("%d hours %d minutes", $not_started_hours, $not_started_minutes);
}

// Fetch data for inactive time and not started subtasks
$inactive_time_data = calculateInactiveTime($aggregated_records);
//$not_started_time = calculateNotStartedSubtaskTime($subtasks_data);

?>
<!DOCTYPE html>
<html>

<head>
    <title>crm Report</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f6f9;
            color: #333;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
        }

        form {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        label {
            margin-right: 10px;
            font-weight: bold;
        }

        input[type="date"],
        select {
            margin-right: 15px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .highlight-row {
            background-color: #f8d7da;
            /* Light red background */
        }

        input[type="submit"] {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background-color: #3498db;
            color: white;
            cursor: pointer;
            font-size: 16px;
        }

        input[type="submit"]:hover {
            background-color: #2980b9;
        }

        .print-button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            background-color: #2ecc71;
            color: white;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .print-button:hover {
            background-color: #27ae60;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f1f1f1;
        }

        thead th {
            background-color: #2980b9;
        }

        .expandable-content {
            display: none;
            background-color: #f9f9f9;
            padding: 10px;
            border-top: 1px solid #ddd;
        }

        .expand-button {
            cursor: pointer;
            color: #3498db;
            text-decoration: underline;
            background: none;
            border: none;
        }
    </style>
    <script>
        function toggleDetails(id, buttonId) {
            var content = document.getElementById(id);
            var button = document.getElementById(buttonId);
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'block';
                button.innerText = 'Collapse Details';
            } else {
                content.style.display = 'none';
                button.innerText = 'Expand Details';
            }
        }

        function printReport() {
            window.print();
        }
    </script>
</head>

<body>
    <div class="head" style="margin-top: 50px;">
        <h1>crm Report</h1>
    </div>
    <form method="post" action="">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date ?? ''); ?>" required>
        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date ?? ''); ?>" max="<?php echo $max_date; ?>" required>
        <label for="username" >Username:</label>
        <select id="username" name="username" >
            <option value="" disabled selected hidden>Select User</option>
            <?php foreach ($users_result as $user): ?>
                <option value="<?= htmlspecialchars($user['id']); ?>" <?= $user['id'] == $selected_user_id ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($user['username']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" value="Generate Report">
    </form>

    <!-- Print Button -->
    <button class="print-button" onclick="printReport()">Print Report</button>


    <!-- Attendance Report -->
    <h2>Attendance Report</h2>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Total Days Worked</th>
                <th>Average Daily Break Time</th>
                <th>Current Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_data as $row): ?>
                <tr onclick="toggleDetails('attendance-details', 'expand-attendance-button')">
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($row['total_days_worked'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($average_break_time ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!--<button id="expand-attendance-button" class="expand-button" onclick="toggleDetails('attendance-details', 'expand-attendance-button')">Expand Details</button>-->
    <div id="attendance-details" class="expandable-content">
        <h3>Detailed Attendance Records</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Last Logout Time</th>
                    <th>First Login Time</th>
                    <th>Reason</th>
                    <th>Duration</th>

                </tr>
            </thead>
            <tbody>
                <?php if (count($aggregated_records) > 0): ?>
                    <?php foreach ($aggregated_records as $date => $record): ?>
                        <?php
                        $login_time = $record['logins'][0];
                        $highlight_class = ($login_time->format('H:i') > '10:00') ? 'highlight-row' : '';
                        ?>
                        <tr class="<?php echo htmlspecialchars($highlight_class); ?>" onclick="toggleDetails('attendance-details', 'expand-attendance-button')">
                            <td><?php echo htmlspecialchars($date); ?></td>
                            <td><?php echo htmlspecialchars(end($record['logouts'])->format('h:i a')); ?></td>
                            <?php
                            $hours_and_breaks = calculateDailyHoursAndBreaks($record['logins'], $record['logouts']);
                            ?>
                            <td><?php echo htmlspecialchars($login_time->format('h:i a')); ?></td>
                            <td><?php echo htmlspecialchars($hours_and_breaks['work']); ?></td>
                            <td><?php echo htmlspecialchars($hours_and_breaks['break']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No attendance records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Inactive Time and Not Started Subtask Time Report -->
    <h2>Inactive Time, Activity Time and Not Started Subtask Time Report</h2>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Total Inactive Time</th>
                <th>Average Inactive Time Per Day</th>
                <th>Total Not Started Subtask Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($inactive_time_data['total_inactive'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($inactive_time_data['average_inactive'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($not_started_time ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Break Time Report</h2>
    <table>
        <thead>
            <tr>
                <th>Break Time</th>
                <th>Avarage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($inactive_time_data['total_inactive'] ?? 'N/A'); ?></td>
                    <!-- <td><?= htmlspecialchars($inactive_time_data['average_inactive'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($not_started_time ?? 'N/A'); ?></td> -->
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Login Time & Logout Time</h2>
    <table>
        <thead>
            <tr>
                <th>Login Time </th>
                <th>Logout Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($inactive_time_data['total_inactive'] ?? 'N/A'); ?></td>
                    <!-- <td><?= htmlspecialchars($inactive_time_data['average_inactive'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($not_started_time ?? 'N/A'); ?></td> -->
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <h2>Subtast</h2>
    <table>
        <thead>
            <tr>
                <th>Subtast Name</th>
                <th>Subtast Avarage</th>
                <th>Total Subtast</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attendance_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($inactive_time_data['total_inactive'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($inactive_time_data['average_inactive'] ?? 'N/A'); ?></td>
                    <!-- <td><?= htmlspecialchars($not_started_time ?? 'N/A'); ?></td> -->
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>

</html>