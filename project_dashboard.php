<?php
include 'header.php';
require 'methods/database.php';

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}
$user_id = $_SESSION['id'];



// .................................... Not working code start php ..................................
$inactivity_query = "
    SELECT 
        il.recorded_at,
        il.counter_count,
        u.username,u.id,
        SUM(il.inactivity_duration) AS total_inactivity
    FROM 
        inactivity_logs il
    JOIN 
        users u ON il.user_id = u.id
    WHERE 
        DATE(il.recorded_at) = CURDATE()
        AND u.id=$user_id
    GROUP BY 
        u.username
";

$inactivity_result = $link->query($inactivity_query);

// Check for query error
if (!$inactivity_result) {
    die('Error: ' . $link->error);
}

$inactivity_data = [];
// Fetch the results
while ($row = $inactivity_result->fetch_assoc()) {
    $inactivity_data[] = $row; // Store each row for displaying later
}
//fetch the inactitvity logs along with the user name 
// Fetch total inactivity time for today grouped by username
function formatEstimatedManHours($estimated_manhours)
{
    // Set default value to avoid issues
    $default_time = '00:00:00';

    // Use the provided time or the default if not set
    $time = isset($estimated_manhours) ? $estimated_manhours : $default_time;

    // Split the time into hours, minutes, and seconds
    $time_parts = explode(":", $time);

    // Check if we have the correct number of parts
    $hours = isset($time_parts[0]) ? (int)$time_parts[0] : 0;
    $minutes = isset($time_parts[1]) ? (int)$time_parts[1] : 0;
    $seconds = isset($time_parts[2]) ? (int)$time_parts[2] : 0;

    // Format the time to a user-friendly format
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function formatSecondsToTime($seconds)
{
    // Ensure that $seconds is an integer
    $seconds = (int)$seconds;
    // Calculate hours, minutes, and seconds
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    // Format the output string
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
function timeStringToSeconds($timeString)
{
    $time_parts = explode(":", $timeString);

    // Assuming the format is HH:MM:SS
    $hours = isset($time_parts[0]) ? (int)$time_parts[0] : 0;
    $minutes = isset($time_parts[1]) ? (int)$time_parts[1] : 0;
    $seconds = isset($time_parts[2]) ? (int)$time_parts[2] : 0;

    return $hours * 3600 + $minutes * 60 + $seconds;
}

function formatEstimatedManHours1($estimated_manhours)
{
    // Set default value to avoid issues
    $default_time = '00:00:00';

    // Use the provided time or the default if not set
    $time = isset($estimated_manhours) ? $estimated_manhours : $default_time;

    // Return the time string for display purposes
    return $time;
}

function formatSecondsToTime1($seconds)
{
    // Ensure that $seconds is an integer
    $seconds = (int)$seconds;
    // Calculate hours, minutes, and seconds
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    // Format the output string
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// .................................... Not working code end php ..................................



// Define the SQL query    
    $sql_users = "SELECT DISTINCT department_id FROM users WHERE id = $user_id";
    $result = $link->query($sql_users);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
         $department = htmlspecialchars($row["department_id"]) ;
        }
    } else {
        echo "No departments found";
    }    
// Fetch all projects
// Active project code start php 
$projects_query = "
SELECT p.*, 
COUNT(s.subtask_id) as total_tasks, 
           SUM(CASE WHEN s.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
           SUM(s.new_total_manhours) as total_manhours, -- Calculate total manhours per project
           CASE 
               WHEN p.deadline < CURDATE() THEN 2  -- Deadline has passed
               ELSE 1  -- Upcoming deadline
           END as deadline_status
    FROM projects p
    LEFT JOIN subtasks s ON p.project_id = s.project_id
    WHERE template_id = $department AND p.status !='Completed'
    GROUP BY p.project_id
    ORDER BY 
             FIELD(p.priority, 'Urgent', 'High', 'Medium', 'Low') ASC, 
             total_manhours DESC,  -- Order by the total calculated manhours
             deadline_status ASC,  -- Projects with past deadlines first
             p.deadline DESC,      -- Nearest upcoming deadline first
             p.created_at DESC     -- Latest projects appear first
";

$projects_result = $link->query($projects_query);
// Execute the query
// Active project code end php 


// Archive project for status completed start code php  
$completed = 'Completed';
$project_date = $_POST['project_date'] ?? null; // Default to null
$name_project = $_POST['name_project'] ?? '';
$project_status = 'Completed'; 
$archive_projects = "
    SELECT p.*, 
           COUNT(s.subtask_id) as total_tasks, 
           SUM(CASE WHEN s.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
           SUM(s.new_total_manhours) as total_manhours,
           CASE 
               WHEN p.deadline < CURDATE() THEN 2
               ELSE 1
           END as deadline_status
    FROM projects p
    LEFT JOIN subtasks s ON p.project_id = s.project_id
    WHERE template_id = $department AND p.status = '$completed'
";
$conditions = [];
$params = [];
if ($project_date) {
    $conditions[] = "p.deadline = ?";
    $params[] = $project_date;
}
if ($name_project) {
    $conditions[] = "p.project_name LIKE ?";
    $params[] = '%' . $name_project . '%';
}
if ($conditions) {
    $archive_projects .= " AND " . implode(" AND ", $conditions);
}
$archive_projects .= "
    GROUP BY p.project_id
    ORDER BY 
        FIELD(p.priority, 'Urgent', 'High', 'Medium', 'Low') ASC, 
        total_manhours DESC, 
        deadline_status ASC, 
        p.deadline DESC, 
        p.created_at DESC
";
$stmt = $link->prepare($archive_projects);
if ($params) {
    $param_types = str_repeat('s', count($params)); 
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$projects_archive = $stmt->get_result();

// Archive project for status completed end code php

// My Tasks code php start

$tasks_query = "
      SELECT  s.subtask_id, s.estimated_manhours, s.subtask_name, s.deadline, s.remaining_time, p.project_name, s.status, p.priority ,t.start_time,t.intervaltime
    FROM subtasks s
    JOIN projects p ON s.project_id = p.project_id
    Left JOIN timeline t ON s.subtask_id = t.pro_sub_id
    WHERE s.assigned_to = ? And s.status !='Completed'
    ORDER BY 
    FIELD(p.priority, 'Urgent', 'High', 'Medium', 'Low') ASC,  -- Prioritize by task priority (Urgent first, then High, Medium, Low)
 FIELD(s.status, 'in progress', 'paused' , 'Not Started', 'Completed') ASC,
    -- Prioritize by task status, ensuring 'In Progress' first, then 'paused', followed by 'Not Started' at the bottom
   FIELD(s.status, 'in progress') ASC,         -- 'In Progress' tasks first
    FIELD(s.status, 'paused') ASC,               -- Then 'paused' tasks
    CASE 
        WHEN s.status = 'Not Started' AND p.priority = 'Urgent' THEN 0 
        ELSE 1 
    END ASC,                                     -- Urgent 'Not Started' tasks prioritized
    CASE 
        WHEN s.status = 'paused' AND p.priority = 'High' THEN 0 
        ELSE 1 
    END ASC,                                     -- High priority 'paused' tasks prioritized
    FIELD(s.status, 'Not Started') ASC,          -- Then 'Not Started' tasks
    CASE 
        WHEN s.status = 'Completed' THEN 0       -- Completed tasks at the bottom
        ELSE 1 
    END ASC, 

    -- Urgent tasks with lowest remaining time first
    (p.priority = 'Urgent') ASC,
    CASE 
        WHEN p.priority = 'Urgent' AND s.remaining_time <= 1000 THEN 1
        WHEN p.priority = 'Urgent' AND s.remaining_time <= 1800 THEN 2
        WHEN p.priority = 'Urgent' AND s.remaining_time <= 3600 THEN 3
        ELSE 4
    END ASC,
    s.remaining_time ASC,

    -- High priority tasks with lowest remaining time first
    (p.priority = 'High') ASC,
    CASE 
        WHEN p.priority = 'High' AND s.remaining_time <= 1000 THEN 1
        WHEN p.priority = 'High' AND s.remaining_time <= 1800 THEN 2
        WHEN p.priority = 'High' AND s.remaining_time <= 3600 THEN 3
        ELSE 4
    END ASC,

    -- Medium priority tasks with lowest remaining time first
    (p.priority = 'Medium') ASC,
    CASE 
        WHEN p.priority = 'Medium' AND s.remaining_time <= 1000 THEN 1
        WHEN p.priority = 'Medium' AND s.remaining_time <= 1800 THEN 2
        WHEN p.priority = 'Medium' AND s.remaining_time <= 3600 THEN 3
        ELSE 4
    END ASC,

    -- Low priority tasks with remaining time considered
    (p.priority = 'Low') ASC,
    CASE 
        WHEN p.priority = 'Low' AND s.remaining_time <= 1000 THEN 1
        WHEN p.priority = 'Low' AND s.remaining_time <= 1800 THEN 2
        WHEN p.priority = 'Low' AND s.remaining_time <= 3600 THEN 3
        ELSE 4
    END ASC,

    -- Final ordering by deadline and remaining time
    s.deadline ASC,
    s.remaining_time ASC;
";

$stmt = $link->prepare($tasks_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$tasks_result = $stmt->get_result();

// My Tasks end the code 


// .......................... Archive my task start code php ...........................
$completed = 'Completed';
$search_date = isset($_POST['search_date']) ? $_POST['search_date'] : date('Y-m-d'); // Default to today's date
$search_project = $_POST['search_project'] ?? '';

// Base SQL query
$archive_query = "
    SELECT s.subtask_id, s.estimated_manhours, s.subtask_name, s.deadline, 
           s.remaining_time, p.project_name, s.status, p.priority, t.start_time, t.intervaltime
    FROM subtasks s
    LEFT JOIN projects p ON s.project_id = p.project_id
    LEFT JOIN timeline t ON s.subtask_id = t.pro_sub_id
    WHERE s.assigned_to = ? AND s.status = ?
";

// Add search filters if provided
$param_types = "is"; // Start with the base types for assigned_to and status
$params = [$_SESSION['id'], $completed];

if ($search_date) {
    $archive_query .= " AND s.deadline = ?";
    $param_types .= "s"; // Add type for search_date
    $params[] = $search_date;
}

if ($search_project) {
    $archive_query .= " AND p.project_name LIKE ?";
    $param_types .= "s"; // Add type for search_project
    $params[] = '%' . $search_project . '%'; // Use LIKE with wildcards
}

$stmt = $link->prepare($archive_query);

// Prepare bind_params dynamically
$stmt->bind_param($param_types, ...$params);

$stmt->execute();
$archive_result = $stmt->get_result();





// .......................... Archive my task end code php ...........................





// Fetch upcoming deadlines (next 7 days)
$deadlines_query = "
    SELECT s.subtask_name, s.deadline, p.project_name
    FROM subtasks s
    JOIN projects p ON s.project_id = p.project_id
    WHERE s.assigned_to = ? AND s.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.deadline ASC
";
$stmt = $link->prepare($deadlines_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$deadlines_result = $stmt->get_result();



// Fetch recent activity
$activity_query = "
    SELECT t.subtask_id, s.subtask_name, t.start_time, t.end_time, 
           TIMEDIFF(t.end_time, t.start_time) as duration
    FROM time_tracking t
    JOIN subtasks s ON t.subtask_id = s.subtask_id
    WHERE t.user_id = ? AND t.end_time IS NOT NULL
    ORDER BY t.start_time DESC
    LIMIT 5
";
$stmt = $link->prepare($activity_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activity_result = $stmt->get_result();




// Calculate total time worked today
$today_work_query = "
    SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time)))) as total_time
    FROM time_tracking
    WHERE user_id = ? AND DATE(start_time) = CURDATE() AND end_time IS NOT NULL
";
$stmt = $link->prepare($today_work_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today_work_result = $stmt->get_result()->fetch_assoc();
$total_time_today = $today_work_result['total_time'] ?? '00:00:00';


function getRemainingTime($link, $subtask_id) {
    $remaining_times = $link->prepare("SELECT remaining_time FROM subtasks WHERE subtask_id = ?");
    $remaining_times->bind_param("i", $subtask_id);
    $remaining_times->execute();
    $remaining_time_result = $remaining_times->get_result();
    $remaining_time_formatted = "00:00:00"; // Default message

    if ($remaining_time_result->num_rows > 0) {
        $remaining_time = $remaining_time_result->fetch_assoc();
        $remaining_seconds = $remaining_time['remaining_time'];

        if (is_numeric($remaining_seconds) && $remaining_seconds >= 0) {
            $remaining_hours = floor($remaining_seconds / 3600);
            $remaining_seconds %= 3600;
            $remaining_minutes = floor($remaining_seconds / 60);
            $remaining_seconds %= 60;

            $remaining_time_formatted = sprintf("%02d:%02d:%02d", $remaining_hours, $remaining_minutes, $remaining_seconds);
        }
        
    }
    return [$remaining_time_formatted, $remaining_time['remaining_time'] ?? 0]; // Return both formatted time and remaining seconds
}
if (!function_exists('convertToSeconds')) {
    function convertToSeconds($timeString) {
        $time_parts = explode(":", $timeString);
        $hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0;
        $minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
        $seconds = isset($time_parts[2]) ? intval($time_parts[2]) : 0;
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}

?>


<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Dashboard</title>
    <link rel="stylesheet" href="css/project_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="container">
        <header>
            <h1>Project Dashboard</h1>
        </header>

        <div class="dashboard-grid">
            <!-- Assigned Tasks -->
            <!-- ............................................ Not Working start code ...............................  -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>User</th>
                        <th></th>
                        <th>Total Inactivity Time</th>
                        <th>Not Started Subtask</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inactivity_data)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No inactivity records for today.</td>
                    </tr>
                    <?php else: ?>
                    <?php
                        // Sort the inactivity data by total_inactivity in descending order
                        usort($inactivity_data, function ($a, $b) {
                            return $b['total_inactivity'] <=> $a['total_inactivity'];
                        });

                        foreach ($inactivity_data as $data):
                            $userId = $data['id'];  // Get the user_id

                            // Prepare user progress query
                            $user_progress = "
                                SELECT 
                                    u.username,
                                    u.id,
                                    s.subtask_name,
                                    s.estimated_manhours,
                                    t.start_time,
                                    s.active_duration,
                                    p.project_name,
                                    IFNULL(SUM(il.inactivity_duration), 0) AS total_inactivity,
                                    s.remaining_time,
                                    s.status  -- Fetch status to check if it's in progress
                                FROM 
                                    users u
                                LEFT JOIN 
                                    inactivity_logs il ON il.user_id = u.id AND DATE(il.recorded_at) = CURDATE()
                                JOIN 
                                    subtasks s ON s.assigned_to = u.id
                                LEFT JOIN 
                                    timeline t ON t.pro_sub_id = s.subtask_id
                                JOIN 
                                    projects p ON p.project_id = s.project_id
                                WHERE 
                                    u.id = $userId  
                                    AND (s.status = 'in progress' OR s.status =! 'not started') 
                                    AND s.estimated_manhours != '00:00:00'
                                GROUP BY 
                                    u.username, u.id, s.subtask_name, s.estimated_manhours, t.start_time, p.project_name
                            ";
                            $user_progress_result = $link->query($user_progress);
                            if (!$user_progress_result) {
                                die('Error: ' . $link->error);
                            }
                            // Check if any subtasks are in progress
                            $hasInProgress = false;
                            while ($row = $user_progress_result->fetch_assoc()) {
                                if ($row['status'] === 'in progress') {
                                    $hasInProgress = true;
                                    break;
                                }
                            }
                            $user_progress_result->data_seek(0);

                            // Reset the result pointer to the beginning
                        ?>
                    <tr>
                        <td><?php echo htmlspecialchars($data['username']); ?></td>
                        <?php if ($user_progress_result->num_rows > 0): ?>
                        <?php while ($row = $user_progress_result->fetch_assoc()): ?>
                        <td></td>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <td colspan="" class="text-center">Not Working</td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars(number_format($data['total_inactivity'], 2)) . ' Minutes'; ?>
                        </td>
                        <form id="timerForm_<?php echo $userId; ?>" action="" method="POST">
                            <td>
                                <input type="text" id="timerDisplay_<?php echo $userId; ?>" name="counter_timer"
                                    value="" readonly style="border:none" />
                                <input type="hidden" name="user_id" value="<?php echo $userId; ?>" />
                                <input type="hidden" name="counter_old" value="<?php echo $data['counter_count']; ?>" />
                            </td>
                        </form>
                    </tr>
                    <?php
                        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['counter_timer'])) {
                            $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                            $counterTimer = isset($_POST['counter_timer']) ? intval($_POST['counter_timer']) : 0;
                            // $counter_old = $data['counter_count'];
                            // $newCount = $counter_old = $counterTimer;
                            if ($userId > 0) {
                                    $stmt = $link->prepare("UPDATE inactivity_logs SET counter_count = ? WHERE user_id = ? AND DATE(recorded_at) = CURDATE()");
                                    $stmt->bind_param("ii", $counterTimer, $userId);

                                    if ($stmt->execute()) {
                                        header('Location : project_dashboard.php');
                                        exit();
                                    } else {
                                        echo "Failed to update timer.";
                                    }
                                
                            } else {
                                echo "Invalid user ID.";
                            }
                        }
                        ?>
                    <!-- Your Timer HTML and JavaScript -->
                    <script>
                    let timer_<?php echo $userId; ?>;
                    let elapsedTime_<?php echo $userId; ?> =
                        <?php echo isset($data['counter_count']) && is_numeric($data['counter_count']) ? $data['counter_count'] : 0; ?>;


                    function formatTime(seconds) {
                        const hours = Math.floor(seconds / 3600);
                        const minutes = Math.floor((seconds % 3600) / 60);
                        const secs = seconds % 60;

                        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                    }

                    function startTimer(userId) {
                        timer_<?php echo $userId; ?> = setInterval(() => {
                            elapsedTime_<?php echo $userId; ?>++;
                            document.getElementById("timerDisplay_" + userId).value = formatTime(
                                elapsedTime_<?php echo $userId; ?>); // Display elapsed time in HH:MM:SS

                            // Submit the form to update the timer value in the database
                            const form = document.getElementById("timerForm_" + userId);
                            const formData = new FormData(form);
                            formData.set('counter_timer',
                                elapsedTime_<?php echo $userId; ?>); // Update the timer value

                            fetch("", {
                                    method: "POST",
                                    body: formData
                                })
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error("Network response was not ok");
                                    }
                                    return response.json(); // Parse as JSON
                                })
                                .then(data => {
                                    console.log(data); // Log the server response
                                    if (data.status === 'error') {
                                        console.error(data.message); // Handle error if needed
                                    }
                                })
                                .catch(error => {
                                    console.error("Error updating timer:", error);
                                });
                        }, 1000);
                    }

                    function stopTimer() {
                        clearInterval(timer_<?php echo $userId; ?>);
                    }
                    // Check if the user has any subtasks in progress
                    <?php if ($hasInProgress): ?>
                    stopTimer(); // Stop timer if any subtask is in progress
                    <?php else: ?>
                    startTimer(<?php echo $userId; ?>); // Start timer if no subtasks are in progress
                    <?php endif; ?>
                    </script>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            </table>
            <!-- ............................................ Not Working end code ...............................  -->
            <!-- My Tasks start the code              -->
            <section class="section tasks">
                <h2>My Tasks</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Task Name</th>
                            <th>Project</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Manhours</th>
                            <th>Task Priority</th>
                            <th>Remaining Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                                $tasks = [];
                                while ($task = $tasks_result->fetch_assoc()) {
                                    $tasks[] = $task;
                                }
        
                                usort($tasks, function($a, $b) {
                                    $statusOrder = ['in progress', 'paused', 'Not Started','Completed'];
                                    return array_search($a['status'], $statusOrder) - array_search($b['status'], $statusOrder);
                                });
        
                                foreach ($tasks as $task): ?>
                        <tr class="<?php echo htmlspecialchars($task['status'] == 'Completed' ? 'completed-task' : ''); ?>"
                            style="<?php echo htmlspecialchars($task['status'] == 'in progress' ? 'background-color: #ff0000; color: white; animation: blinker 1s linear infinite;' : ($task['status'] == 'Not Started' ? 'background-color: lightgray;' : '')); ?>">
                            <td><?php echo htmlspecialchars($task['subtask_name']); ?></td>
                            <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($task['deadline']); ?></td>
                            <td><?php echo htmlspecialchars($task['status']); ?></td>
                            <td><?php echo htmlspecialchars($task['estimated_manhours']); ?></td>
                            <td><?php echo htmlspecialchars($task['priority']); ?></td>

                            <td>
                                <input type="hidden"
                                    name="subtasks[<?php echo htmlspecialchars($task['subtask_id']); ?>][subtask_id]"
                                    value="<?php echo htmlspecialchars($task['subtask_id']); ?>">
                                <?php
                                $task_status = htmlspecialchars($task['status']);
                                if (!function_exists('displayCompletedTask')) {
                                    function displayCompletedTask($task) {
                                        if (!empty($task['start_time']) && !empty($task['intervaltime'])) {
                                            $total_seconds = convertToSeconds($task['intervaltime']);
                                            $total_manhours = convertToSeconds($task['estimated_manhours'] ?? '00:00:00');
                                            
                                            if ($total_manhours >= $total_seconds) {
                                                return 'Completed In: ' . htmlspecialchars($task['intervaltime']) . ' hrs';
                                            } else {
                                                $late_time = $total_seconds - $total_manhours;
                                                return '<span style="color: red;">Late By: ' . sprintf('%02d:%02d:%02d', floor($late_time / 3600), floor(($late_time % 3600) / 60), $late_time % 60) . ' hrs</span>';
                                            }
                                        }
                                        return '<strong></strong>';
                                    }
                                }
                                $total_manhours = convertToSeconds($task['estimated_manhours'] ?? '00:00:00');
                                $total_seconds = convertToSeconds($task['intervaltime']);

                                if ($task_status === 'Completed') {
                                    echo displayCompletedTask($task);
                                }
                                if($task_status === 'in progress' && $total_manhours <= $total_seconds){
                                    echo displayCompletedTask($task);
                                }
                                else {
                                    list($remaining_time_formatted, $remaining_seconds) = getRemainingTime($link, $task['subtask_id']);
                                    if ($task_status === 'paused') {
                                        $remaining_time_formatted = 'paused';
                                    }
                                    ?>


                                <span id="time-display-<?php echo htmlspecialchars($task['subtask_id']); ?>"
                                    style="<?php echo $task_status === 'paused' ? 'color: red;' : ''; ?>">
                                    <?php
                                        echo htmlspecialchars($remaining_time_formatted);
                                        if ($task_status === 'paused') {
                                            echo ' (paused at: ' . htmlspecialchars($task['event_time']) . ')';
                                        }
                                        ?>
                                </span>

                                <input type="hidden"
                                    id="remaining-seconds-<?php echo htmlspecialchars($task['subtask_id']); ?>"
                                    value="<?php echo (int)$remaining_seconds; ?>">
                                <input type="hidden"
                                    id="task-status-<?php echo htmlspecialchars($task['subtask_id']); ?>"
                                    value="<?php echo htmlspecialchars($task['status']); ?>">
                                <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    const taskId = <?php echo json_encode($task['subtask_id']); ?>;
                                    const taskStatus = document.getElementById('task-status-' + taskId).value;
                                    let remainingSeconds = parseInt(document.getElementById(
                                        'remaining-seconds-' +
                                        taskId).value, 10);

                                    function formatTime(seconds) {
                                        const hours = Math.floor(seconds / 3600);
                                        seconds %= 3600;
                                        const minutes = Math.floor(seconds / 60);
                                        seconds %= 60;
                                        return String(hours).padStart(2, '0') + ':' +
                                            String(minutes).padStart(2, '0') + ':' +
                                            String(seconds).padStart(2, '0');
                                    }
                                    // Handle task completion
                                    if (taskStatus === "Completed") {
                                        document.getElementById('time-display-' + taskId).innerText =
                                            formatTime(
                                                remainingSeconds);
                                        return;
                                    }
                                    // Handle paused tasks
                                    if (taskStatus === "paused") {
                                        document.getElementById('time-display-' + taskId).innerText =
                                            formatTime(
                                                remainingSeconds);
                                        if (remainingSeconds <= 0) {
                                            document.getElementById('time-display-' + taskId).innerText =
                                                ''; // Clear the display when timer reaches zero
                                            return; // Optional: Stop any further processing
                                        }
                                        return; // Stop the script here; don't start the countdown
                                    }


                                    function updateRemainingTime() {
                                        if (remainingSeconds > 0) {
                                            remainingSeconds--;
                                            const formattedTime = formatTime(remainingSeconds);
                                            document.getElementById('time-display-' + taskId).innerText =
                                                formattedTime;
                                        } else {
                                            document.getElementById('time-display-' + taskId).innerText =
                                                "";
                                            clearInterval(timer);
                                        }
                                    }

                                    // Start the countdown only if the task is not paused or completed
                                    const timer = setInterval(updateRemainingTime, 1000);
                                });
                                </script>
                                <?php } ?>
                            </td>


                            <td>
                                <?php if ($task['status'] == 'Completed'): ?>
                                <a href="javascript:void(0);" class="btn-view"
                                    style="pointer-events: none; color: gray;">View</a>
                                <?php else: ?>
                                <a href="project_spf.php?subtask_id=<?php echo htmlspecialchars($task['subtask_id']); ?>&status=<?php echo htmlspecialchars($task['status']); ?>"
                                    class="btn-view">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <!-- My Tasks end the code  -->


            <!-- Active Projects -->
            <section class="section projects">
                <h2>Active Projects</h2>
                <div class="projects-list">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Project id</th>
                                <th>Project Name</th>
                                <th>Deadline</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <script>
                            // Function to redirect when clicking on the table row
                            function redirectToProject(projectId) {
                                window.location.href = 'project_details.php?project_id=' + projectId;
                            }
                            </script>

                            <?php 
                            // Define an array to prioritize the display order
                            $order = ['Not Started', 'In Progress', 'Completed'];
                            
                            $projects = [];
                            while ($project = $projects_result->fetch_assoc()) {
                                $projects[] = $project;
                            }

                            // Sort the projects array by the custom order of status
                            usort($projects, function($a, $b) use ($order) {
                                return array_search($a['status'], $order) - array_search($b['status'], $order);
                            });

                            foreach ($projects as $project):
                                $progress = ($project['total_tasks'] > 0)
                                    ? round(($project['completed_tasks'] / $project['total_tasks']) * 100)
                                    : 0;
                            ?>
                            <tr onclick="redirectToProject(<?php echo $project['project_id']; ?>)"
                                style="cursor: pointer; <?php echo $project['status'] == 'Completed' ? 'background-color: #d3f9d8;' : ''; ?>">

                                <td><?php echo htmlspecialchars($project['project_id']); ?></td>
                                <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                <td><?php 
                                    $deadline = new DateTime($project['deadline']);
                                    echo htmlspecialchars($deadline->format('F, d, Y')); 
                                    ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo $progress; ?>%;">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                        if ($progress == 100) {
                                            $project_id = $project['project_id'];
                                            $project_status = $link->prepare("UPDATE `projects` SET `status` = ? WHERE project_id = ?");
                                            $status = 'Completed';
                                            $project_status->bind_param("si", $status, $project_id);

                                            if ($project_status->execute()) {
                                                echo 'Completed';
                                            } else {
                                                echo 'Error updating status: ' . $link->error;
                                            }
                                        } elseif ($progress > 0) {
                                            $project_id = $project['project_id'];
                                            $project_status = $link->prepare("UPDATE `projects` SET `status` = ? WHERE project_id = ?");
                                            $status = 'In Progress';
                                            $project_status->bind_param("si", $status, $project_id);

                                            if ($project_status->execute()) {
                                                echo 'In Progress';
                                            } else {
                                                echo 'Error updating status: ' . $link->error;
                                            }
                                        } else {
                                            echo 'Not Started';
                                        }
                                        ?>
                                </td>
                                <td>
                                    <?php 
                                        $priorityClass = '';
                                        switch ($project['priority']) {
                                            case 'Urgent':
                                                $priorityClass = 'text-color-urgent';
                                                break;
                                            case 'High':
                                                $priorityClass = 'text-color-high';
                                                break;
                                            case 'Medium':
                                                $priorityClass = 'text-color-medium';
                                                break;
                                            case 'Low':
                                                $priorityClass = 'text-color-low';
                                                break;
                                        }
                                        ?>
                                    <div class="<?php echo $priorityClass; ?>">
                                        <?php echo $project['priority']; ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="project_details.php?project_id=<?php echo $project['project_id']; ?>"
                                        class="btn-view">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
            </section>

            <!-- .............Archive My task start code ................... -->
            <section>
                <h1>Completed task</h1>
                <form action="" method="post" class="search-form" onsubmit="return validateForm()">
                    <input type="date" name="search_date" class="form-input"
                        value="<?php echo htmlspecialchars($search_date); ?>" required>
                    <input type="text" name="search_project" placeholder="Project name" class="form-input"
                        value="<?php echo htmlspecialchars($search_project); ?>">
                    <button type="submit" class="form-button">Search</button>
                </form>

                <script>
                function validateForm() {
                    const dateInput = document.querySelector('input[name="search_date"]');
                    const projectInput = document.querySelector('input[name="search_project"]');

                    // Check if date is selected
                    if (!dateInput.value) {
                        alert("Please select a date.");
                        return false;
                    }
                    return true; // Form is valid
                }
                </script>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Task Name</th>
                            <th>Project</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Manhours</th>
                            <th>Task Priority</th>
                            <th>Remaining Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="task-body"
                        style="<?php echo !empty($tasks) ? 'display: table-row-group;' : 'display: none;'; ?>">
                        <?php
            $tasks = [];
            while ($task = $archive_result->fetch_assoc()) {
                $tasks[] = $task;
            }

            if (empty($tasks)) {
                echo "<tr><td colspan='8'>No tasks found.</td></tr>";
            } else {
                foreach ($tasks as $task): ?>
                        <tr class="<?php echo htmlspecialchars($task['status'] == 'Completed' ? 'completed-task' : ''); ?>"
                            style="<?php echo htmlspecialchars($task['status'] == 'in progress' ? 'background-color: #ff0000; color: white; animation: blinker 1s linear infinite;' : ($task['status'] == 'Not Started' ? 'background-color: lightgray;' : '')); ?>">
                            <td><?php echo htmlspecialchars($task['subtask_name']); ?></td>
                            <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                            <td><?php 
                                    $deadline = new DateTime($task['deadline']);
                                    echo htmlspecialchars($deadline->format('F, d, Y')); 
                                    ?>
                            </td>
                            <td><?php echo htmlspecialchars($task['status']); ?></td>
                            <td><?php echo htmlspecialchars($task['estimated_manhours']); ?></td>
                            <td><?php echo htmlspecialchars($task['priority']); ?></td>
                            <td>
                                <?php
                            if ($task['status'] === 'Completed') {
                                echo  displayCompletedTask($task);
                            } else {
                                list($remaining_time_formatted, $remaining_seconds) = getRemainingTime($link, $task['subtask_id']);
                                echo htmlspecialchars($remaining_time_formatted);
                            }
                            ?>
                            </td>
                            <td>
                                <?php if ($task['status'] == 'Completed'): ?>
                                <a href="javascript:void(0);" class="btn-view"
                                    style="pointer-events: none; color: gray;">View</a>
                                <?php else: ?>
                                <a href="project_spf.php?subtask_id=<?php echo htmlspecialchars($task['subtask_id']); ?>&status=<?php echo htmlspecialchars($task['status']); ?>"
                                    class="btn-view">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; 
            } ?>
                    </tbody>
                </table>
            </section>
            <!-- .............Archive my task end code ................... -->

            <!-- Archive project completed 100% progress start the code  -->
            <section class="section projects">
                <h2>Completed Projects</h2>
                <form action="" method="post" class="search-form">
                    <input type="date" name="project_date" class="form-input"
                        value="<?php echo htmlspecialchars($project_date); ?>">
                    <input type="text" name="name_project" placeholder="Project name" class="form-input"
                        value="<?php echo htmlspecialchars($name_project); ?>">
                    <input type="hidden" name="project_status" placeholder="Project Status" class="form-input"
                        value="Completed">
                    <button type="submit" class="form-button">Search</button>
                </form>

                <div class="projects-list">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Project id</th>
                                <th>Project Name</th>
                                <th>Deadline</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <script>
                            // Function to redirect when clicking on the table row
                            function redirectToProject(projectId) {
                                window.location.href = 'project_details.php?project_id=' + projectId;
                            }
                            </script>

                            <?php 
                            // Define an array to prioritize the display order
                            $order = ['Not Started', 'In Progress', 'Completed'];
                            
                            $projects = [];
                            while ($project = $projects_archive->fetch_assoc()) {
                                $projects[] = $project;
                            }

                            // Sort the projects array by the custom order of status
                            usort($projects, function($a, $b) use ($order) {
                                return array_search($a['status'], $order) - array_search($b['status'], $order);
                            });

                            foreach ($projects as $project):
                                $progress = ($project['total_tasks'] > 0)
                                    ? round(($project['completed_tasks'] / $project['total_tasks']) * 100)
                                    : 0;
                            ?>
                            <tr onclick="redirectToProject(<?php echo $project['project_id']; ?>)"
                                style="cursor: pointer; <?php echo $project['status'] == 'Completed' ? 'background-color: #d3f9d8;' : ''; ?>">

                                <td><?php echo htmlspecialchars($project['project_id']); ?></td>
                                <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                <td>
                                    <?php 
                                    $deadline = new DateTime($project['deadline']);
                                    echo htmlspecialchars($deadline->format('F, d, Y')); 
                                    ?>
                                </td>

                                <td>
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo $progress; ?>%;">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                        if ($progress == 100) {
                                            $project_id = $project['project_id'];
                                            $project_status = $link->prepare("UPDATE `projects` SET `status` = ? WHERE project_id = ?");
                                            $status = 'Completed';
                                            $project_status->bind_param("si", $status, $project_id);

                                            if ($project_status->execute()) {
                                                echo 'Completed';
                                            } else {
                                                echo 'Error updating status: ' . $link->error;
                                            }
                                        } elseif ($progress > 0) {
                                            $project_id = $project['project_id'];
                                            $project_status = $link->prepare("UPDATE `projects` SET `status` = ? WHERE project_id = ?");
                                            $status = 'In Progress';
                                            $project_status->bind_param("si", $status, $project_id);

                                            if ($project_status->execute()) {
                                                echo 'In Progress';
                                            } else {
                                                echo 'Error updating status: ' . $link->error;
                                            }
                                        } else {
                                            echo 'Not Started';
                                        }
                                        ?>
                                </td>
                                <td>
                                    <?php 
                                        $priorityClass = '';
                                        switch ($project['priority']) {
                                            case 'Urgent':
                                                $priorityClass = 'text-color-urgent';
                                                break;
                                            case 'High':
                                                $priorityClass = 'text-color-high';
                                                break;
                                            case 'Medium':
                                                $priorityClass = 'text-color-medium';
                                                break;
                                            case 'Low':
                                                $priorityClass = 'text-color-low';
                                                break;
                                        }
                                        ?>
                                    <div class="<?php echo $priorityClass; ?>">
                                        <?php echo $project['priority']; ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="project_details.php?project_id=<?php echo $project['project_id']; ?>"
                                        class="btn-view">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
            </section>
            <!-- Archive project completed 100% progress end the code  -->

            <!-- Upcoming Deadlines -->
            <section class="section deadlines">
                <h2>Upcoming Deadlines</h2>
                <ul class="deadline-list">
                    <?php while ($deadline = $deadlines_result->fetch_assoc()):
                        $days_left = (new DateTime($deadline['deadline']))->diff(new DateTime())->days;
                        ?>
                    <li class="deadline-item <?php echo ($days_left <= 2) ? 'urgent' : ''; ?>">
                        <span class="deadline-project">
                            <?php echo htmlspecialchars($deadline['project_name']); ?>
                        </span>
                        <span class="deadline-task">
                            <?php echo htmlspecialchars($deadline['subtask_name']); ?>
                        </span>
                        <span class="deadline-date">
                            <?php echo htmlspecialchars($deadline['deadline']); ?>
                        </span>
                        <span class="deadline-countdown">
                            <?php echo $days_left; ?>
                            day
                            <?php echo ($days_left != 1) ? 's' : ''; ?> left
                        </span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </section>

            <!-- Recent Activity -->
            <section class="section activity">
                <h2>Recent Activity</h2>
                <ul class="activity-list">
                    <?php while ($activity = $activity_result->fetch_assoc()): ?>
                    <li class="activity-item">
                        <span class="activity-task">
                            <?php echo htmlspecialchars($activity['subtask_name']); ?>
                        </span>
                        <span class="activity-time">
                            <?php echo htmlspecialchars($activity['start_time']); ?> -
                            <?php echo htmlspecialchars($activity['end_time']); ?>
                        </span>
                        <span class="activity-duration">Duration:
                            <?php echo htmlspecialchars($activity['duration']); ?>
                        </span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </section>

        </div>
    </div>
</body>

</html>