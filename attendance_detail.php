<?php
include_once "header.php";
require_once 'methods/database.php';

// Check if the user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Fetch user ID and date from URL
if (!isset($_GET['date']) || !isset($_GET['user_id'])) {
    header("Location: attendance.php"); // Redirect if date or user_id is not provided
    exit();
}



$date = $_GET['date'];
$user_id = intval($_GET['user_id']);

// Fetch attendance records for the specific user and date
$sql = "SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ? ORDER BY login_time";
$stmt = $link->prepare($sql);
$stmt->bind_param("is", $user_id, $date);
$stmt->execute();
$attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch lead assignment data for the specific user and date
$lead_sql = "
    SELECT lal.*, l.progress
    FROM lead_assignment_logs lal
    JOIN leads l ON lal.lead_id = l.id
    WHERE lal.user_id = ? AND DATE(lal.assigned_at) = ?
    ORDER BY lal.assigned_at
";
$lead_stmt = $link->prepare($lead_sql);
$lead_stmt->bind_param("is", $user_id, $date);
$lead_stmt->execute();
$lead_result = $lead_stmt->get_result();
$lead_logs = $lead_result->fetch_all(MYSQLI_ASSOC);
$lead_stmt->close();

// Prepare data for charts
$aggregated_records = aggregateRecordsByDate($attendance_records);

$progress_categories = [
    'All Leads' => 0,
    'Lost' => 0,
    'Not Interested' => 0,
    'Follow-up' => 0,
    'Didn\'t Connect' => 0,
    'First Call Done' => 0,
    'Quote Sent' => 0,
    'Converted' => 0,
    'Fresh Lead' => 0,
];

$daily_lead_counts = [];
$daily_times = [];
$lead_labels = [];
$lead_times = [];

if (count($lead_logs) > 0) {
    $previous_date = '';
    $daily_count = 0;
    $total_time_diff = 0;
    $count_time_diff = 0;

    foreach ($lead_logs as $log) {
        $date = date('Y-m-d', strtotime($log['assigned_at']));
        $progress = $log['progress'];

        // Update progress category counts
        if (array_key_exists($progress, $progress_categories)) {
            $progress_categories[$progress]++;
            $progress_categories['All Leads']++;
        }

        // Prepare data for charts
        $lead_labels[] = $log['assigned_at'];
        $lead_times[] = $log['time_diff'] / 60 ?? 0;

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

    if ($previous_date != '') {
        $daily_lead_counts[$previous_date] = $daily_count;
        $daily_times[$previous_date] = $count_time_diff > 0 ? $total_time_diff / $count_time_diff : 0;
    }

    $lead_dates = array_map(fn($label) => date('Y-m-d', strtotime($label)), $lead_labels);
} else {
    // Default values for flat chart
    $lead_dates = ['No Data'];
    $lead_times = [0];
    $daily_lead_counts = ['No Data' => 0];
    $daily_times = ['No Data' => 0];
}



function aggregateRecordsByDate($records)
{
    $aggregated = [];
    foreach ($records as $record) {
        $date = $record['attendance_date'];
        if (!isset($aggregated[$date])) {
            $aggregated[$date] = ['logins' => [], 'logouts' => []];
        }
        $aggregated[$date]['logins'][] = new DateTime($record['login_time']);
        if ($record['logout_time']) {
            $aggregated[$date]['logouts'][] = new DateTime($record['logout_time']);
        }
    }
    return $aggregated;
}

function calculateWorkAndBreakTimes($logins, $logouts)
{
    $work_periods = [];
    $break_periods = [];

    for ($i = 0; $i < count($logins); $i++) {
        $login = $logins[$i];
        $logout = isset($logouts[$i]) ? $logouts[$i] : new DateTime(); // If no logout, assume current time

        $work_time = $login->diff($logout);
        $work_periods[] = [
            'start' => $login->format('Y-m-d H:i:s'),
            'end' => $logout->format('Y-m-d H:i:s'),
            'duration' => sprintf("%d hours %d minutes", $work_time->h, $work_time->i)
        ];

        // Assuming break time is between logouts and logins
        if ($i > 0) {
            $previous_logout = $logouts[$i - 1];
            $break_time = $login->diff($previous_logout);
            $break_periods[] = [
                'start' => $previous_logout->format('Y-m-d H:i:s'),
                'end' => $login->format('Y-m-d H:i:s'),
                'duration' => sprintf("%d hours %d minutes", $break_time->h, $break_time->i)
            ];
        }
    }

    return ['work' => $work_periods, 'break' => $break_periods];
}

// Calculate work and break times
$work_and_break_times = [];
foreach ($aggregated_records as $date => $record) {
    $work_and_break_times[$date] = calculateWorkAndBreakTimes($record['logins'], $record['logouts']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Day Details</title>
    <link rel="stylesheet" href="css/attendance_detail.css"> <!-- Moved CSS to external file -->
    <!-- Load Chart.js and the date adapter -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script> <!-- Date adapter -->
</head>

<body>

    <h1>Details for <?php echo htmlspecialchars($date); ?></h1>

    <h2>Work and Break Durations</h2>
    <?php foreach ($work_and_break_times as $date => $periods): ?>
    <div class="workTable">
        <h4>Work Periods</h4>
        <table>
            <thead>
                <tr>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods['work'] as $period): ?>
                <tr>
                    <td><?php echo htmlspecialchars($period['start']); ?></td>
                    <td><?php echo htmlspecialchars($period['end']); ?></td>
                    <td><?php echo htmlspecialchars($period['duration']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="breakTable">
        <h4>Break Periods</h4>
        <table>
            <thead>
                <tr>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periods['break'] as $period): ?>
                <tr>
                    <td><?php echo htmlspecialchars($period['start']); ?></td>
                    <td><?php echo htmlspecialchars($period['end']); ?></td>
                    <td><?php echo htmlspecialchars($period['duration']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

   
   <?php
require_once 'methods/database.php'; // Ensure this includes your database connection setup

$selectedDate = $_GET['date'] ?? date('Y-m-d'); // Default to today's date if not provided
$userId = $_GET['user_id'] ?? null; // Get user ID from query parameter

// Check if user ID is provided
if (!$userId) {
    die('User ID is required');
}

// Create the SQL query to get lead_notes timestamps for a specific user
$sqlNotes = "SELECT created_at FROM lead_notes WHERE DATE(created_at) = ? AND owner = ? ORDER BY created_at ASC";

$counterprog = 0;
$timeDiffsNotes = [];
$labelsNotes = [];

if ($stmt = $link->prepare($sqlNotes)) {
    $stmt->bind_param('si', $selectedDate, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $timestampsNotes = [];

    while ($row = $result->fetch_assoc()) {
        $timestampsNotes[] = $row['created_at'];
        $counterprog++;
    }

    $stmt->close();

    // Calculate time differences for notes
    for ($i = 1; $i < count($timestampsNotes); $i++) {
        $previous = new DateTime($timestampsNotes[$i - 1]);
        $current = new DateTime($timestampsNotes[$i]);
        $interval = $previous->diff($current);
        $timeDiffsNotes[] = ($interval->h * 60) + $interval->i; // Convert to minutes
    }

    // Include all timestamps as labels
    $labelsNotes = array_map(function($timestamp) {
        return (new DateTime($timestamp))->format('H:i:s'); // Format to only show time
    }, $timestampsNotes);
} else {
    echo "Error preparing statement for notes: " . $link->error;
}

// Close the database connection
$link->close();
?>



 

 <h3>Lead Assignment Data</h3>
    <div id="chart" style="max-height: 600px;align-items:center; justify-content:center;">
    <canvas id="progressCategoriesChart"></canvas>

</div>
<style>
    #notes,#chart, #logs {
    width: 70%; /* Adjust width as needed */
    margin: 20px auto; /* Center horizontally and add vertical margin */
    text-align: center; /* Center text inside the div */
}

#timeDiffChart, #leadTimeChart {
    max-width: 100%; /* Make the chart responsive */
    height: 300px; /* Set the height for the charts */
}
</style>
 <div id="notes">
<h1>How frequently user update the leads </h1>
<h2>Leads updated today: <?php echo $counterprog; ?></h2>
<canvas id="timeDiffChart" width="600" height="400" style="background-color: white;"></canvas>
</div>
<div id="logs">
<h1>How frequently user assigned the leads </h1>
<canvas id="leadTimeChart" width="600" height="400"></canvas>
</div>


</div>
    <script>
    // Lead Time Chart
    const ctxLeadTime = document.getElementById('leadTimeChart').getContext('2d');
    new Chart(ctxLeadTime, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($lead_labels); ?>,
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
                        unit: 'minute'
                    },
                    title: {
                        display: true,
                        text: 'Time'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Minutes'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true
                }
            }
        }
    });

 


    // Data for Progress Categories Chart
    const progressCategoriesData = <?php echo json_encode($progress_categories); ?>;

    // Progress Categories Chart
    const ctxProgressCategories = document.getElementById('progressCategoriesChart').getContext('2d');
    new Chart(ctxProgressCategories, {
        type: 'bar',
        data: {
            labels: Object.keys(progressCategoriesData),
            datasets: [{
                label: 'Lead Progress Categories',
                data: Object.values(progressCategoriesData),
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)',
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Progress Category'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Count'
                    }
                }
            },
            plugins: {
                legend: {
                    display: true
                }
            }
        }
    });
   
    // PHP variables passed to JavaScript
        const labelsNotes = <?php echo json_encode($labelsNotes); ?>;
        const timeDiffsNotes = <?php echo json_encode($timeDiffsNotes); ?>;

        // Create the chart
        const ctx = document.getElementById('timeDiffChart').getContext('2d');
        const timeDiffChart = new Chart(ctx, {
            type: 'line', // You can also use 'bar', 'radar', etc.
            data: {
                labels: labelsNotes, // X-axis labels (timestamps)
                datasets: [{
                    label: 'Time Difference (minutes)',
                    data: timeDiffsNotes, // Y-axis data (time differences in minutes)
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true, // Set to false if you don't want the area under the line filled
                    tension: 0.1 // Smooth curves
                }]
            },
            options: {
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Timestamp'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Time Difference (minutes)'
                        }
                    }
                }
            }
        });
</script>



</body>

</html>