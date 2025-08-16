<?php
include_once "header.php";
require_once 'methods/database.php';

// Check if the user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Fetch user ID from URL
if (!isset($_GET['user_id'])) {
    header("Location: attendance.php"); // Redirect if no user_id is provided
    exit();
}
$user_id = intval($_GET['user_id']);

// Fetch user's name based on user_id
$user_name_sql = "SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?";
$user_stmt = $link->prepare($user_name_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_stmt->bind_result($user_name);
$user_stmt->fetch();
$user_stmt->close();

// Check if the user name is found
if (empty($user_name)) {
    header("Location: attendance.php");
    exit();
}

// Get selected month and year from GET parameters, or use current month/year as default
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Determine the first and last day of the selected month
$first_day_of_month = new DateTime("$year-$month-01");
$last_day_of_month = new DateTime($first_day_of_month->format('Y-m-t'));

// Fetch attendance records for the specific user and month/year
$sql = "SELECT * FROM attendance WHERE user_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date, login_time";
$stmt = $link->prepare($sql);
$first_day_str = $first_day_of_month->format('Y-m-d');
$last_day_str = $last_day_of_month->format('Y-m-d');
$stmt->bind_param("iss", $user_id, $first_day_str, $last_day_str);
$stmt->execute();
$attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch holidays
$holiday_sql = "SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?";
$holiday_stmt = $link->prepare($holiday_sql);
$holiday_stmt->bind_param("ss", $first_day_str, $last_day_str);
$holiday_stmt->execute();
$holiday_result = $holiday_stmt->get_result();
$holidays = $holiday_result->fetch_all(MYSQLI_ASSOC);
$holidays = array_column($holidays, 'holiday_date'); // Extract holiday dates into an array
$holiday_stmt->close();

// Function to get all weekdays in a given month
function getWeekdaysInMonth($first_day, $last_day) {
    $weekdays = [];
    for ($date = clone $first_day; $date <= $last_day; $date->modify('+1 day')) {
        if ($date->format('N') < 6) { // Monday to Friday
            $weekdays[] = $date->format('Y-m-d');
        }
    }
    return $weekdays;
}

// Function to calculate daily working hours and break times
function calculateDailyHoursAndBreaks($logins, $logouts) {
    $total_work_minutes = 0;
    $total_break_minutes = 0;
    $prev_logout_time = null;

    foreach ($logins as $index => $login_time) {
        // Default to current time if no logout time is provided
        $logout_time = isset($logouts[$index]) ? $logouts[$index] : new DateTime();
        
        // Calculate work interval
        $work_interval = ($logout_time->getTimestamp() - $login_time->getTimestamp()) / 60; // Work time in minutes
        $total_work_minutes += $work_interval;

        // Calculate break time between previous logout and current login
        if ($prev_logout_time) {
            $break_interval = ($login_time->getTimestamp() - $prev_logout_time->getTimestamp()) / 60; // Break time in minutes
            $total_break_minutes += $break_interval;
        }

        $prev_logout_time = $logout_time;
    }

    // Optional: Calculate any additional break time between the last logout and the end of the workday
    // if ($prev_logout_time) {
    //     $end_of_day = new DateTime($prev_logout_time->format('Y-m-d') . ' 19:00:00');
    //     if ($end_of_day > $prev_logout_time) {
    //         $additional_break_interval = ($end_of_day->getTimestamp() - $prev_logout_time->getTimestamp()) / 60; // Additional break time in minutes
    //         $total_break_minutes += $additional_break_interval;
    //     }
    // }

    $work_hours = intdiv($total_work_minutes, 60);
    $work_minutes = $total_work_minutes % 60;
    $break_hours = intdiv($total_break_minutes, 60);
    $break_minutes = $total_break_minutes % 60;

    return [
        'work' => sprintf("%d hours %d minutes", $work_hours, $work_minutes),
        'break' => sprintf("%d hours %d minutes", $break_hours, $break_minutes)
    ];
}



// Aggregate records by date
function aggregateRecordsByDate($records) {
    $aggregated = [];
    foreach ($records as $record) {
        $date = $record['attendance_date'];
        if (!isset($aggregated[$date])) {
            $aggregated[$date] = ['logins' => [], 'logouts' => []];
        }
        $aggregated[$date]['logins'][] = new DateTime($record['login_time']);
        $aggregated[$date]['logouts'][] = new DateTime($record['logout_time']);
    }
    return $aggregated;
}

// Calculate average break time for the month
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
        $average_break_minutes = $total_break_minutes / $days_with_breaks;
        $average_break_hours = intdiv($average_break_minutes, 60);
        $average_break_minutes %= 60;
        return sprintf("%d hours %d minutes", $average_break_hours, $average_break_minutes);
    } else {
        return "No break data available";
    }
}

$aggregated_records = aggregateRecordsByDate($attendance_records);

// Determine presence and absence
$weekdays = getWeekdaysInMonth($first_day_of_month, $last_day_of_month);
$working_days = array_diff($weekdays, $holidays);
$present_days = [];
$absent_days = [];
$worked_on_holidays = 0;

function isWeekend($date) {
    return in_array($date->format('N'), [6, 7]); // Saturday (6) or Sunday (7)
}

// Initialize an array to track processed dates
$processed_dates = [];

// Count worked on holidays or weekends, ensuring each date is counted only once
foreach ($attendance_records as $record) {
    $attendance_date = new DateTime($record['attendance_date']);
    $formatted_date = $attendance_date->format('Y-m-d');

    if (!isset($processed_dates[$formatted_date])) {
        // Check if the date is a weekend or holiday
        if (isWeekend($attendance_date) || in_array($formatted_date, $holidays)) {
            $worked_on_holidays++;
        }
        // Mark this date as processed
        $processed_dates[$formatted_date] = true;
    }
}

foreach ($working_days as $day) {
    if (isset($aggregated_records[$day])) {
        $present_days[] = $day;
    } else {
        $absent_days[] = $day;
    }
}
$average_break_time = calculateAverageBreakTime($aggregated_records);


// Query for lead assignment logs with time difference in minutes
$lead_sql = "SELECT assigned_at, TIMESTAMPDIFF(MINUTE, LAG(assigned_at) OVER (PARTITION BY DATE(assigned_at) ORDER BY assigned_at), assigned_at) AS time_diff 
              FROM lead_assignment_logs WHERE user_id = ? AND DATE(assigned_at) BETWEEN ? AND ? ORDER BY assigned_at";
$lead_stmt = $link->prepare($lead_sql);
$lead_stmt->bind_param("iss", $user_id, $first_day_str, $last_day_str);
$lead_stmt->execute();
$lead_result = $lead_stmt->get_result();
$lead_logs = $lead_result->fetch_all(MYSQLI_ASSOC);
$lead_stmt->close();

// Prepare data for the productivity chart
$lead_labels = [];
$lead_times = [];
$daily_lead_counts = [];
$daily_times = [];
$previous_date = '';
$daily_count = 0;
$total_time_diff = 0;
$count_time_diff = 0;

foreach ($lead_logs as $log) {
    $date = date('Y-m-d', strtotime($log['assigned_at']));
    $lead_labels[] = $log['assigned_at'];
    $lead_times[] = $log['time_diff'] ?? 0; // Use 0 if time_diff is NULL

    if ($date != $previous_date && $previous_date != '') {
        $daily_lead_counts[$previous_date] = $daily_count;
        $daily_times[$previous_date] = $count_time_diff > 0 ? $total_time_diff / $count_time_diff : 0;
        $daily_count = 0;
        $total_time_diff = 0;
        $count_time_diff = 0;
    }

    $daily_count++;
    $daily_lead_counts[$date] = $daily_count;
    $total_time_diff += $log['time_diff'] ?? 0;
    $count_time_diff++;
    $previous_date = $date;
}

// Last day count
if ($previous_date != '') {
    $daily_lead_counts[$previous_date] = $daily_count;
    $daily_times[$previous_date] = $count_time_diff > 0 ? $total_time_diff / $count_time_diff : 0;
}

// Convert lead labels to only dates for the chart
$lead_dates = array_map(fn($label) => date('Y-m-d', strtotime($label)), $lead_labels);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Analytics - Admin</title>
    <link rel="stylesheet" href="css/attendance_calendar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js">
    </script>
</head>

<body>
    <div class="container">
        <div class="heading">
            <h2>Attendance Analytics for <?php echo htmlspecialchars($user_name); ?></h2>
        </div>
        <!-- Month and Year Selector Form -->

        <form method="get" action="">
            <div class="timeSelector" style="display: flex; width:100%">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                <div class="month">
                    <label for="month">Month:</label>


                    <select name="month" id="month">

                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>"
                            <?php echo ($month == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="month">
                    <label for="year">Year:</label>

                    <select name="year" id="year">

                        <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear - 5; $y <= $currentYear + 5; $y++):
                        ?>
                        <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit">Update</button>
            </div>
        </form>

    </div>
    <div class="container">
        <div class="attendence">
            <div class="secondHead">
                <h3>Presence and Absence</h3>
            </div>
            <div class="dayss" style="display: flex;  " >
                <div>
                    <div class="headd">Total Working Days:</div>
                    <p> <?php echo count($working_days); ?></p>
                </div>
                <div>
                    <div class="head">Total Present Days:</div>
                    <p><?php echo count($present_days); ?></p>
                </div>
                <div>
                    <div class="head">Total Absent Days:</div>
                    <p><?php echo count($absent_days); ?></p>
                </div>
                <div>
                    <div class="head">Total Holidays Worked:</div>
                    <p> <?php echo $worked_on_holidays; ?></p>
                </div>
            </div>
        </div>
        <div class="Time">
            <div class="breakTime">
                <h3>Average Break Time</h3>
            </div>
            <div class="headd">Average Break Time for the Month:</div>
            <p><?php echo htmlspecialchars($average_break_time); ?></p>
        </div>
        <div class="thirdhead">
            <h3>Attendance Records</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>First Login Time</th>
                        <th>Last Logout Time</th>
                        <th>Hours Worked</th>
                        <th>Total Break Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($aggregated_records) > 0): ?>
                    <?php foreach ($aggregated_records as $date => $record): ?>
                    <tr onclick="window.location.href='attendance_detail.php?date=<?php echo htmlspecialchars($date); ?>&user_id=<?php echo htmlspecialchars($user_id); ?>'"
                        ;>
                        <td><?php echo htmlspecialchars($date); ?></td>
                        <td><?php echo htmlspecialchars($record['logins'][0]->format('Y-m-d H:i:s')); ?></td>
                        <td><?php echo htmlspecialchars(end($record['logouts'])->format('Y-m-d H:i:s')); ?></td>
                        <?php
                                $hours_and_breaks = calculateDailyHoursAndBreaks($record['logins'], $record['logouts']);
                                ?>
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
        <div class="productivity-chart">
            <div class="chart-containe">
                <h3>Productivity Data</h3>
            </div>
            <canvas id="productivityChart"></canvas>
        </div>
    </div>
    <!-- <h3>Lead Assignment Data</h3>
        <canvas id="leadTimeChart"></canvas>
        <canvas id="dailyLeadsChart"></canvas> -->

    <script>
    // Prepare data for Chart.js
    const labels = <?php echo json_encode(array_keys($aggregated_records)); ?>;
    const workData = <?php echo json_encode(array_map(function ($record) {
                                $hours_and_breaks = calculateDailyHoursAndBreaks($record['logins'], $record['logouts']);

                                // Extract hours and minutes
                                list($work_hours, $work_minutes) = sscanf($hours_and_breaks['work'], "%d hours %d minutes");
                                list($break_hours, $break_minutes) = sscanf($hours_and_breaks['break'], "%d hours %d minutes");

                                // Convert to decimal hours
                                $total_work_hours = $work_hours + ($work_minutes / 60);
                                $total_break_hours = $break_hours + ($break_minutes / 60);

                                return $total_work_hours; // Return work time in decimal hours
                            }, $aggregated_records)); ?>;

    const breakData = <?php echo json_encode(array_map(function ($record) {
                                $hours_and_breaks = calculateDailyHoursAndBreaks($record['logins'], $record['logouts']);

                                // Extract hours and minutes
                                list($work_hours, $work_minutes) = sscanf($hours_and_breaks['work'], "%d hours %d minutes");
                                list($break_hours, $break_minutes) = sscanf($hours_and_breaks['break'], "%d hours %d minutes");

                                // Convert to decimal hours
                                $total_work_hours = $work_hours + ($work_minutes / 60);
                                $total_break_hours = $break_hours + ($break_minutes / 60);

                                return $total_break_hours; // Return break time in decimal hours
                            }, $aggregated_records)); ?>;

    // Productivity Chart
    const ctxProductivity = document.getElementById('productivityChart').getContext('2d');
    new Chart(ctxProductivity, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Work Time (hours)',
                data: workData,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }, {
                label: 'Break Time (hours)',
                data: breakData,
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    // Lead Time Chart
    const ctxLeadTime = document.getElementById('leadTimeChart').getContext('2d');
    new Chart(ctxLeadTime, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($lead_dates); ?>,
            datasets: [{
                label: 'Lead Assignment Time Difference (minutes)',
                data: <?php echo json_encode($lead_times); ?>,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day'
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Daily Lead Counts Chart
    const ctxDailyLeads = document.getElementById('dailyLeadsChart').getContext('2d');
    new Chart(ctxDailyLeads, {
        type: 'bar',
        data: {
            labels: Object.keys(<?php echo json_encode($daily_lead_counts); ?>),
            datasets: [{
                label: 'Daily Lead Assignments',
                data: Object.values(<?php echo json_encode($daily_lead_counts); ?>),
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
    </div>
</body>

</html>