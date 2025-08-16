<?php
// Include database connection
require 'methods/database.php';

// Capture and sanitize project ID
$project_id = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
if ($project_id === false) {
    die("Invalid project ID.");
}
$projectName = $_GET['project_name'];
// Function to get remaining time
function getRemainingTime($link, $subtask_id) {
    $query = "SELECT estimated_manhours, status FROM subtasks WHERE subtask_id = ?";
    $stmt = $link->prepare($query);
    if (!$stmt) {
        die("Database query failed: " . $link->error);
    }
    $stmt->bind_param("i", $subtask_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $estimated_duration = $row['estimated_manhours']; // Assuming this is a time format (HH:MM:SS)

        if (strpos($estimated_duration, ':') !== false) {
            $duration_parts = explode(':', $estimated_duration);
            if (count($duration_parts) === 3) {
                $total_seconds = ($duration_parts[0] * 3600) + ($duration_parts[1] * 60) + $duration_parts[2];
                $formatted_time = gmdate("H:i:s", $total_seconds);
                return [$formatted_time, $total_seconds];
            }
        }
    }
    
    return ['00:00:00', 0]; // Default value if not found or format is incorrect
}

// Capture and sanitize input for search
$username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING);
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$deadline = filter_input(INPUT_GET, 'deadline', FILTER_SANITIZE_STRING);

// Prepare the tasks query
$tasks_query = "
    SELECT s.subtask_id, s.subtask_name,s.estimated_manhours,s.deadline, s.remaining_time, p.project_name, u.username, s.status, p.priority 
    FROM subtasks s
    JOIN projects p ON s.project_id = p.project_id
    JOIN users u ON s.assigned_to = u.id
    WHERE s.project_id = ?
";

// Initialize parameters and types for binding
$params = [$project_id];
$types = 'i';

// Filter by username
if (!empty($username)) {
    $tasks_query .= " AND u.username LIKE ?";
    $params[] = '%' . $username . '%';
    $types .= 's';
}

// Filter by month and year
if ($month && $year) {
    $tasks_query .= " AND MONTH(s.deadline) = ? AND YEAR(s.deadline) = ?";
    $params[] = $month;
    $params[] = $year;
    $types .= 'ii';
}

// Filter by deadline
if (!empty($deadline)) {
    $tasks_query .= " AND s.deadline = ?";
    $params[] = $deadline;
    $types .= 's';
}

// Complete the query with ordering
$tasks_query .= "
    ORDER BY 
        FIELD(p.priority, 'Urgent', 'High', 'Medium', 'Low') ASC,
        FIELD(s.status, 'in progress', 'paused', 'Not Started', 'Completed') ASC,
        s.deadline ASC,
        s.remaining_time ASC;
";

// Prepare and execute the final query
$stmt = $link->prepare($tasks_query);
if (!$stmt) {
    die("Database query failed: " . $link->error);
}

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    die("Execution failed: " . $stmt->error);
}

$tasks_result = $stmt->get_result();

function formatHoursToTime($total_hours) {
    // Convert hours to total seconds
    $total_seconds = $total_hours * 3600;

    // Calculate hours, minutes, and seconds
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $seconds = $total_seconds % 60;

    // Format the output
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

function getTimelineData($link, $subtask_id) {
    $timeline_query = "SELECT * FROM `timeline` WHERE pro_sub_id = ?";
    $stmt_timeline = $link->prepare($timeline_query);
    
    if (!$stmt_timeline) {
        die("Database query failed: " . $link->error);
    }
    
    $stmt_timeline->bind_param("i", $subtask_id);
    
    if (!$stmt_timeline->execute()) {
        die("Execution failed: " . $stmt_timeline->error);
    }
    
    $timeline_result = $stmt_timeline->get_result();
    return $timeline_result->fetch_assoc(); // Fetch timeline data
}

function calculateAdjustedTime($timeline_data, $manhours) {
    if (empty($timeline_data['start_time']) || empty($timeline_data['event_time'])) {
        return null; // Not enough data to calculate
    }

    $start_time = new DateTime($timeline_data['start_time'], new DateTimeZone('Asia/Kolkata'));
    $event_time = new DateTime($timeline_data['event_time'], new DateTimeZone('Asia/Kolkata'));
    $pause_total = $timeline_data['pause_resume_time'];

    // Handle pause_total formatting
    if (strpos($pause_total, ':') !== false) {
        list($pause_hours, $pause_minutes, $pause_seconds) = explode(":", $pause_total);
        $pause_hours = (int)$pause_hours;
        $pause_minutes = (int)$pause_minutes;
        $pause_seconds = (int)$pause_seconds;
    } else {
        $pause_hours = $pause_minutes = $pause_seconds = 0; // Default if invalid
    }

    $total_pause_seconds = ($pause_hours * 3600) + ($pause_minutes * 60) + $pause_seconds;

    $interval = $start_time->diff($event_time);
    $interval_total_seconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s + ($interval->d * 86400);
    $adjusted_seconds = max(0, $interval_total_seconds - $total_pause_seconds);

    return formatTime($adjusted_seconds, $manhours);
}

function formatTime($adjusted_seconds, $manhours) {
    $adjusted_hours = floor($adjusted_seconds / 3600);
    $adjusted_minutes = floor(($adjusted_seconds % 3600) / 60);
    $adjusted_seconds = $adjusted_seconds % 60;

    $time_parts = explode(":", $manhours);
    $hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0;
    $minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
    $seconds = isset($time_parts[2]) ? intval($time_parts[2]) : 0;
    $total_manhours = ($hours * 3600) + ($minutes * 60) + $seconds;

    return [$adjusted_hours, $adjusted_minutes, $adjusted_seconds, $total_manhours];
}


?>

<!DOCTYPE html>
<html lang="en">
<style>
body {
    font-family: 'Roboto', sans-serif;
    background-color: #f0f2f5;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 1200px;
    margin-top: 90px !important;
    margin: auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

h1 {
    text-align: center;
    color: #333;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    /* Space between inputs */
    justify-content: center;
    /* Center the form */
    margin-bottom: 20px;
    /* Space below the form */
}

.search-input,
.search-select,
.search-button {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 16px;
}

.search-input {
    flex: 1;
    /* Take available space */
    min-width: 200px;
    /* Minimum width for inputs */
}

.search-select {
    flex: 1;
    /* Take available space */
    min-width: 150px;
    /* Minimum width for selects */
}

.search-button {
    background-color: #5cb85c;
    /* Bootstrap success color */
    color: white;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.search-button:hover {
    background-color: #4cae4c;
    /* Darker green on hover */
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th,
td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #f8f9fa;
}

tr:hover {
    background-color: #f1f1f1;
}

.completed-task {
    background-color: lightgreen;
    color: green;
}

.in-progress {
    background-color: #ffcc00;
    color: #fff;
}

.search-button a {
    color: white;
    text-decoration: none;
}

.client_name {
    display: flex;
    justify-content: end;
    font-size: 25px;
    font-weight: 600;
}
</style>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project View</title>
    <link rel="stylesheet" href="styles.css">

</head>

<body>
    <div class="container">
        <h1>
            <?php echo strtoupper($projectName); ?>
        </h1>
        <div class="client_name">
            <span class="name_client">Client Name</span>
        </div>
        <section class="section tasks">
            <table class="table">
                <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Estimated Manhours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

        // Fetching tasks
        while ($task = $tasks_result->fetch_assoc()): 
            list($remaining_time_formatted, $remaining_seconds) = getRemainingTime($link, $task['subtask_id']);
        ?>
                    <tr class="<?php echo htmlspecialchars($task['status'] == 'Completed' ? 'completed-task' : ''); ?>"
                        style="<?php echo htmlspecialchars($task['status'] == 'in progress' ? 'background-color: #ff0000; color: white;' : ''); ?>">
                        <td>
                            <?php echo htmlspecialchars($task['subtask_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($task['project_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($task['status']); ?>
                        </td>
                        <td>
                            <?php echo formatHoursToTime($task['estimated_manhours']); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <!-- Display total row -->

                </tbody>
            </table>

        </section>
    </div>
</body>

</html>