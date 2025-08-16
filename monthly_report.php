<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection setup
include 'header.php';
include 'methods/database.php';

// Default values for start and end dates
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');
$selected_user_id = isset($_POST['username']) ? $_POST['username'] : '';

// Validate dates
if (strtotime($start_date) > strtotime($end_date)) {
    die("Start date cannot be after end date.");
}

// Fetch all users for the dropdown
$users_sql = "SELECT id, username FROM users";
$users_result = fetch_results($link, $users_sql);

// Helper function to execute a query and fetch results
function fetch_results($mysqli, $sql, $params = []) {
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
    $attendance_records_sql = "SELECT attendance_date, login_time, logout_time FROM attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ?";
    $attendance_records = fetch_results($link, $attendance_records_sql, [$selected_user_id, $start_date, $end_date]);

    function getWeekdaysInMonth($first_day, $last_day) {
        $weekdays = [];
        for ($date = clone $first_day; $date <= $last_day; $date->modify('+1 day')) {
            if ($date->format('N') < 6) { // Monday to Friday
                $weekdays[] = $date->format('Y-m-d');
            }
        }
        return $weekdays;
    }

    function calculateDailyHoursAndBreaks($logins, $logouts) {
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
function toDateTime($datetime_str) {
    return $datetime_str ? new DateTime($datetime_str) : null;
}

// Assuming this is where you need to create DateTime objects
function aggregateRecordsByDate($records) {
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


    function calculateAverageBreakTime($aggregated_records) {
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

    $lead_conversion_sql = "
        SELECT u.id, u.username, COUNT(l.id) AS total_leads_converted, 
               SUM(l.converted_price) AS total_conversion_revenue
        FROM users u
        LEFT JOIN leads l ON u.id = l.owner AND l.progress = 'Converted' AND l.conversion_date BETWEEN ? AND ?
        WHERE u.id = ?
        GROUP BY u.id, u.username
    ";
    $lead_conversion_data = fetch_results($link, $lead_conversion_sql, [$start_date, $end_date, $selected_user_id]);

    $lead_assignment_sql = "
        SELECT u.id, u.username, COUNT(l.id) AS total_leads_assigned
        FROM users u
        LEFT JOIN leads l ON u.id = l.owner AND l.assigned_at BETWEEN ? AND ?
        WHERE u.id = ?
        GROUP BY u.id, u.username
    ";
    $lead_assignment_data = fetch_results($link, $lead_assignment_sql, [$start_date, $end_date, $selected_user_id]);

    $justification_sql = "
        SELECT u.id, u.username, COUNT(j.owner) AS leads_shortfall
        FROM users u
        LEFT JOIN justification j ON u.id = j.owner AND j.date BETWEEN ? AND ?
        WHERE u.id = ? AND j.justification_leads_shortfall != 'Not needed'
        GROUP BY u.id, u.username
    ";
    $justification_data = fetch_results($link, $justification_sql, [$start_date, $end_date, $selected_user_id]);

    // $followups_missed_sql = "
    //     SELECT u.id, u.username, COUNT(l.id) AS followups_missed
    //     FROM users u
    //     LEFT JOIN leads l ON u.id = l.owner AND l.followup_date BETWEEN ? AND ?
    //     WHERE l.followup_date IS NOT NULL AND l.progress NOT IN ('Lost', 'Converted', 'Didnt Connect', 'Didn\'t Connect', 'Not Started') AND u.id = ?
    //     GROUP BY u.id, u.username
    // ";
    // $followups_missed_data = fetch_results($link, $followups_missed_sql, [$start_date, $end_date, $selected_user_id]);

    $overdue_followups_sql = "
        SELECT u.id, u.username, COUNT(l.id) AS total_overdue
        FROM users u
        LEFT JOIN leads l ON u.id = l.owner AND l.followup_date < NOW() AND l.followup_date BETWEEN ? AND ?
        WHERE l.followup_date IS NOT NULL AND l.progress NOT IN ('Lost', 'Converted', 'Didnt Connect', 'Didn\'t Connect', 'Fresh Lead') AND u.id = ?
        GROUP BY u.id, u.username
    ";
    $overdue_followups_data = fetch_results($link, $overdue_followups_sql, [$start_date, $end_date, $selected_user_id]);

    $leads_wasted_sql = "
    SELECT u.id AS user_id, u.username, l.id AS lead_id, l.name AS lead_name, l.created_at AS lead_created_at, ln.latest_note_date
    FROM users u
    LEFT JOIN leads l ON u.id = l.owner
    LEFT JOIN (
        SELECT lead_id, MAX(created_at) AS latest_note_date
        FROM lead_notes
        GROUP BY lead_id
    ) ln ON l.id = ln.lead_id
    WHERE (ln.latest_note_date IS NULL OR ln.latest_note_date < NOW() - INTERVAL 30 DAY) 
      AND l.created_at BETWEEN ? AND ?
      AND u.id = ?
    ORDER BY u.id, l.created_at
";

$leads_wasted_data = fetch_results($link, $leads_wasted_sql, [$start_date, $end_date, $selected_user_id]);
    // Fetch detailed justifications
// Fetch detailed attendance records
// Fetch detailed attendance records including logging_off data
$attendance_details_sql = "
    SELECT 
        a.attendance_date, 
        a.login_time, 
        a.logout_time, 
        u.username,
        lo.reason AS logging_reason,
        lo.duration AS logging_duration
    FROM 
        attendance a
    JOIN 
        users u ON a.user_id = u.id
    LEFT JOIN 
        logging_off lo ON a.user_id = lo.id 
        AND a.logout_time = lo.logout_time
    WHERE 
        a.user_id = ? 
        AND a.attendance_date BETWEEN ? AND ?
    ORDER BY 
        a.attendance_date DESC
";
$attendance_details_data = fetch_results($link, $attendance_details_sql, [$selected_user_id, $start_date, $end_date]);

// Fetch detailed justification records

$justification_details_sql = "
    SELECT 
        j.date, 
        j.owner, 
        u.username, 
        j.target_leads, 
        j.assigned_leads, 
        j.followups_due, 
        j.leads_shortfall, 
        j.followups_missed, 
        j.justification_leads_shortfall, 
        j.justification_followups_missed
    FROM 
        justification j
    JOIN 
        users u 
    ON 
        j.owner = u.id
    WHERE 
        j.owner = ? 
    AND 
        j.date BETWEEN ? AND ?
    ORDER BY 
        j.date DESC
";
$justification_details_data = fetch_results($link, $justification_details_sql, [$selected_user_id, $start_date, $end_date]);




$lead_conversion_details_sql = "
    SELECT l.id, l.conversion_date,l.name, l.converted_price, l.payment_screenshot AS screenshot_path
    FROM leads l
    WHERE l.owner = ? AND l.progress = 'Converted' AND l.conversion_date BETWEEN ? AND ?
";
$lead_conversion_details = fetch_results($link, $lead_conversion_details_sql, [$selected_user_id, $start_date, $end_date]);

// Convert details to a format suitable for displaying in HTML
$lead_conversion_details_by_lead = [];
foreach ($lead_conversion_details as $detail) {
    $lead_conversion_details_by_lead[$detail['id']] = [
        'conversion_date' => $detail['conversion_date'],
        'converted_price' => $detail['converted_price'],
        'name' => $detail['name'],
        'screenshot_path' => $detail['screenshot_path']
    ];
}


    $link->close();
} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Monthly Report</title>
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
    background-color: #f8d7da; /* Light red background */
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
        th, td {
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
<body><div class="head" style="margin-top: 50px;">
        <h1>Monthly Report</h1>
    </div>
    <form method="post" action="">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date ?? ''); ?>" required>
        <label for="end_date">End Date:</label>
        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date ?? ''); ?>" required>
        <label for="username">Username:</label>
        <select id="username" name="username">
            <option value="">Select User</option>
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
                    <th>Logout Time</th>
                    <th>Login Time</th>
                    <th>Reason</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                    <?php if (!empty($attendance_details_data)): ?>
                        <?php foreach ($attendance_details_data as $detail): ?>
                            <?php 
                                // Add any necessary condition to set a highlight class
                                $highlight_class = ''; 
                            ?>
                            <tr class="<?= htmlspecialchars($highlight_class); ?>">
                                <td><?= htmlspecialchars($detail['attendance_date'] ?? 'N/A'); ?></td>
                                <td>
                                    <?= htmlspecialchars(
                                        $detail['logout_time'] ? (new DateTime($detail['logout_time']))->format('H:i') : 'N/A'
                                    ); ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars(
                                        $detail['login_time'] ? (new DateTime($detail['login_time']))->format('H:i') : 'N/A'
                                    ); ?>
                                </td>
                                <td><?= htmlspecialchars($detail['logging_reason'] ?? 'No Reason'); ?></td>
                                <td><?= htmlspecialchars($detail['logging_duration'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No attendance records found</td>
                        </tr>
                    <?php endif; ?>
            </tbody>

        </table>
    </div>

    <!-- Lead Conversion Report -->
    <h2>Lead Conversion Report</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Total Leads Converted</th>
                <th>Total Conversion Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lead_conversion_data as $row): ?>
                <tr onclick="toggleDetails('conversion', 'expand-conversion-button')">
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($row['total_leads_converted'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['total_conversion_revenue'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Button to expand/collapse the details -->
<!--<button id="expand-conversion-button" class="expand-button" onclick="toggleDetails('conversion', 'expand-conversion-button')">Expand Details</button>-->

<!-- Table to display the details -->
<div id="conversion" class="expandable-content" style="display:none;">
    <h3>Detailed Payment Records</h3>
    <table>
        <thead>
            <tr>
                <th>Conversion Date</th>
                <th>Name</th>
                <th>Converted Price</th>
                <th>Screenshot</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($lead_conversion_details)): ?>
                <?php foreach ($lead_conversion_details as $details): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($details['conversion_date']); ?></td>
                        <td><?php echo htmlspecialchars($details['name']); ?></td>
                        <td><?php echo htmlspecialchars($details['converted_price']); ?></td>
                        <td>
                            <?php if (!empty($details['screenshot_path'])): ?>
                                <img src="<?php echo htmlspecialchars($details['screenshot_path']); ?>" alt="Screenshot" style="max-width: 200px;">
                            <?php else: ?>
                                No Screenshot
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No details available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


    <!-- Lead Assignment Report -->
    <h2>Lead Assignment Report</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Total Leads Assigned</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lead_assignment_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($row['total_leads_assigned'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Justification Report -->
    <h2>Justification Report</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>No of Times Justification Filled</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($justification_data as $row): ?>
                <tr onclick="toggleDetails('justification-details', 'expand-button')">
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($row['leads_shortfall'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!--<button id="expand-button" class="expand-button" onclick="toggleDetails('justification-details', 'expand-button')">Expand Details</button>-->
    <div id="justification-details" class="expandable-content">
        <h3>Detailed Justifications</h3>
        <?php if (!empty($justification_details_data)): ?>
            <table>
                <thead>
                    <tr>
                <th>Date</th>
                <th>Owner</th>
                <th>Username</th>
                <th>Target Leads</th>
                <th>Assigned Leads</th>
                <th>Follow-ups Due</th>
                <th>Leads Shortfall</th>
                <th>Follow-ups Missed</th>
                <th>Justification Leads Shortfall</th>
                <th>Justification Follow-ups Missed</th>
            </tr>
                </thead>
                <tbody>
                    <?php foreach ($justification_details_data as $detail): ?>
                <tr>
                    <td><?= htmlspecialchars($detail['date'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($detail['owner'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($detail['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($detail['target_leads'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($detail['assigned_leads'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($detail['followups_due'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($detail['leads_shortfall'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($detail['followups_missed'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($detail['justification_leads_shortfall'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($detail['justification_followups_missed'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No detailed justifications available.</p>
        <?php endif; ?>
    </div>

    <!-- Overdue Follow-ups Report -->
    <h2>Overdue Follow-ups Report</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Total Overdue</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($overdue_followups_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($row['total_overdue'] ?? 'N/A'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Leads Wasted Report -->
    <h2>Leads Wasted Report</h2>
<table>
    <thead>
        <tr>
            <th>User</th>
            <th>Lead ID</th>
            <th>Lead Name</th>
            <th>Lead Created At</th>
            <th>Last Note Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($leads_wasted_data)): ?>
            <?php foreach ($leads_wasted_data as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['username'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['lead_id'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['lead_name'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['lead_created_at'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($row['latest_note_date'] ?? 'Never'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No leads wasted</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
