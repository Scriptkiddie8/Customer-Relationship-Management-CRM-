<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Initialize variables
$total_projects = 0;
$completed_projects = 0;
$ongoing_projects = 0;
$active_projects = []; // Initialize as an empty array
$completed_projects_details = []; // Array to store completed project details
$ongoing_projects_details = []; // Array to store ongoing project details

// Fetch project statistics
$projects_query = "SELECT project_id, project_name, status, deadline FROM projects";
$projects_result = $link->query($projects_query);

// Check for query error
if (!$projects_result) {
    die('Error: ' . $link->error);
}

// Calculate project statistics and collect project details
$total_projects = $projects_result->num_rows;

while ($project = $projects_result->fetch_assoc()) {
    if ($project['status'] == 'Completed' || $project['status'] == 'Completed') {
        $completed_projects++;
        $completed_projects_details[] = $project; // Collect completed project details
    } else {
        $ongoing_projects++;
        $ongoing_projects_details[] = $project; // Collect ongoing project details
    }
}

// Determine badge status and class
$badge_status = $ongoing_projects > 0 ? 'Active' : ($completed_projects > 0 ? 'Completed' : 'Active');
$badge_class = $ongoing_projects > 0 ? 'badge-warning' : ($completed_projects > 0 ? 'badge-secondary' : 'badge-success');
$badge_class1 = $ongoing_projects > 0 ? 'badge-danger' : ($completed_projects > 0 ? 'badge-danger' : 'badge-danger');
$badge_complete = $ongoing_projects > 0 ? 'Complete' : ($completed_projects > 0 ? 'badge-danger' : 'badge-red');

// Fetch upcoming deadlines and overdue projects
$upcoming_deadlines = [];
$overdue_projects = [];

// Fetch overdue projects
$deadline_query = "SELECT project_name, deadline FROM projects WHERE deadline < NOW() AND status != 'Completed'";
$deadline_result = $link->query($deadline_query);

if (!$deadline_result) {
    die('Error: ' . $link->error);
}

while ($deadline = $deadline_result->fetch_assoc()) {
    $overdue_projects[] = $deadline;
}

// Fetch upcoming deadlines
$deadline_query = "SELECT project_name, deadline FROM projects WHERE deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND status != 'Completed'";
$deadline_result = $link->query($deadline_query);

if (!$deadline_result) {
    die('Error: ' . $link->error);
}

while ($deadline = $deadline_result->fetch_assoc()) {
    $upcoming_deadlines[] = $deadline;
}
// Fetch all projects
//fetch the inactitvity logs along with the user name 
// Fetch total inactivity time for today grouped by username
// $inactivity_query = "
//     SELECT 
//         u.username,
//         u.id,
//         s.subtask_name,
//         s.estimated_manhours,
//         t.start_time,
//         p.project_name,  -- Include project name or any other project fields you need
//         IFNULL(SUM(il.inactivity_duration), 0) AS total_inactivity
//     FROM 
//         inactivity_logs il
//     JOIN 
//         users u ON il.user_id = u.id
//     JOIN    
//         subtasks s ON s.assigned_to = u.id
//     LEFT JOIN 
//         timeline t ON t.pro_sub_id = s.subtask_id
//     JOIN 
//         projects p ON p.project_id = s.project_id  -- Joining the projects table
//     WHERE 
//         DATE(il.recorded_at) = CURDATE()
//         AND s.status = 'in progress' 
//     AND (t.start_time IS NOT NULL AND t.start_time != 'Not Started')  -- Exclude not started tasks
//     GROUP BY 
//         u.username, u.id, s.subtask_name, s.estimated_manhours, t.start_time, p.project_name  -- Include project_name in GROUP BY
// ";



// $inactivity_query = "SELECT  
//     u.username,
//     u.id,
//     s.subtask_name,
//     s.project_id,
//     s.active_duration,
//     s.estimated_manhours,
//     p.project_name,
//     SUM(il.inactivity_duration) AS total_inactivity
// FROM 
//     inactivity_logs il
// JOIN 
//     users u ON il.user_id = u.id
// JOIN 
//     subtasks s ON s.assigned_to = u.id
// JOIN 
//     projects p ON s.project_id = p.project_id
// WHERE 
//     DATE(il.recorded_at) = CURDATE() and 
//     s.status = 'in progress'
// GROUP BY 
//     u.username, u.id, s.subtask_name, s.project_id, p.project_name,s.active_duration";


$sql = "
   SELECT 
    u.first_name,
    u.last_name,
    MAX(a.login_time) as last_login_time,
    a.location,
    a.address
FROM 
    users u
JOIN 
    attendance a ON u.id = a.user_id
WHERE 
    DATE(a.attendance_date) = CURDATE() AND (u.role = 'User' OR u.role = 'Admin')
GROUP BY 
    u.id
ORDER BY 
    last_login_time DESC;
";
$resultslogin = $link->query($sql);




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



$projects_query = "
    SELECT p.*, 
           COUNT(s.subtask_id) as total_tasks, 
           SUM(CASE WHEN s.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
           CASE 
               WHEN p.deadline < CURDATE() THEN 2  -- Deadline has passed
               ELSE 1  -- Upcoming deadline
           END as deadline_status
    FROM projects p
    LEFT JOIN subtasks s ON p.project_id = s.project_id
    GROUP BY p.project_id
    ORDER BY FIELD(p.priority, 'Urgent', 'High', 'Medium', 'Low') ASC, 
             deadline_status ASC, -- Projects with past deadlines first
             p.deadline DESC,      -- Nearest upcoming deadline first
             p.created_at DESC    -- Latest projects appear first
";
$projects_result = $link->query($projects_query);

function formatEstimatedManHours($estimated_manhours)
{
    $default_time = '00:00:00';
    $time = isset($estimated_manhours) ? $estimated_manhours : $default_time;
    $time_parts = explode(":", $time);
    $hours = isset($time_parts[0]) ? (int)$time_parts[0] : 0;
    $minutes = isset($time_parts[1]) ? (int)$time_parts[1] : 0;
    $seconds = isset($time_parts[2]) ? (int)$time_parts[2] : 0;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
function formatSecondsToTime($seconds)
{
    $seconds = (int)$seconds;
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
function timeStringToSeconds($timeString)
{
    $time_parts = explode(":", $timeString);
    $hours = isset($time_parts[0]) ? (int)$time_parts[0] : 0;
    $minutes = isset($time_parts[1]) ? (int)$time_parts[1] : 0;
    $seconds = isset($time_parts[2]) ? (int)$time_parts[2] : 0;
    return $hours * 3600 + $minutes * 60 + $seconds;
}

function formatEstimatedManHours1($estimated_manhours)
{
    $default_time = '00:00:00';
    $time = isset($estimated_manhours) ? $estimated_manhours : $default_time;
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
?>

<!-- ....................... Active time end code php.........................  -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/admin_dashboard.css">
    <style>
    #completed_project {
        display: none;
    }

    .disabled {
        pointer-events: none;
        opacity: 0.5;
    }
    </style>
</head>


<body>
    <div class="container">
        <h1 class="text-center">Dashboard</h1>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="headd">
                <div class="popup-wrapper left">
                    <div class="popup bg-primary text-white" id="popupTotalProjects">
                        <h3>Total Projects</h3>
                        <span class="popup-number"><?php echo $total_projects; ?></span>
                    </div>
                </div>
                <div class="popup-wrapper right">
                    <div class="popup bg-success text-white" id="popupCompletedProjects">
                        <h3>Completed Projects</h3>
                        <span class="popup-number"><?php echo $completed_projects; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="stat bg-warning text-dark">
                    <h3>Ongoing Projects</h3>
                    <div class="container hidden">
                        <table border="1" cellpadding="10" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Project ID</th>
                                    <th>Project Name</th>
                                    <th>Deadline</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                // Get the current date
                $currentDate = new DateTime();
                
                while ($project = $projects_result->fetch_assoc()): 
                    $progress = ($project['total_tasks'] > 0) 
                        ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) 
                        : 0; 
                    
                    $deadlineDate = new DateTime($project['deadline']);
                    
                    // Check if the project is ongoing (deadline is greater than current date)
                    //if ($progress < 100 && $deadlineDate > $currentDate): 
                      if ($progress < 100): ?>
                                <tr onclick="redirectToProject(<?php echo $project['project_id']; ?>)"
                                    style="cursor: pointer;">
                                    <td><?php echo htmlspecialchars($project['project_id']); ?></td>
                                    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['deadline']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress" style="width: <?php echo $progress; ?>%;">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $progress == 100 ? 'Completed' : 'In Progress'; ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Completed Projects Section -->
                <div class="stat bg-success text-white">
                    <button id="completedButton" onclick="showCompletedProjects(this)">
                        <h3>Completed Projects</h3>
                    </button>
                    <button id="hideButton" onclick="hideCompletedProjects()" style="display: none;">
                        <h3>Hide Completed Projects</h3>
                    </button>
                    <div id="completed_project" class="container">
                        <table border="1" cellpadding="10" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Project ID</th>
                                    <th>Project Name</th>
                                    <th>Deadline</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                // Reset the result pointer to the beginning
                $projects_result->data_seek(0); 
                while ($project = $projects_result->fetch_assoc()): 
                    $progress = ($project['total_tasks'] > 0) 
                        ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) 
                        : 0; 
                    
                    // Get the current date
                    $currentDate = new DateTime();
                    $deadlineDate = new DateTime($project['deadline']);
                    
                    // Check if the project is completed and deadline has passed
                    //if ($progress == 100 || $deadlineDate < $currentDate): 
                       if ($progress == 100): ?>
                                <tr onclick="redirectToProject(<?php echo $project['project_id']; ?>)"
                                    style="cursor: pointer;">
                                    <td><?php echo htmlspecialchars($project['project_id']); ?></td>
                                    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['deadline']); ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress" style="width: <?php echo $progress; ?>%;">
                                                <?php echo $progress; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo 'Completed'; ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <script>
                function showCompletedProjects(button) {
                    button.classList.add('disabled');
                    document.getElementById('completed_project').style.display = 'block';
                    document.getElementById('hideButton').style.display = 'inline-block';
                }

                function hideCompletedProjects() {
                    const completedButton = document.getElementById('completedButton');
                    completedButton.classList.remove('disabled');
                    document.getElementById('completed_project').style.display = 'none';
                    document.getElementById('hideButton').style.display = 'none';
                }

                function redirectToProject(projectId) {
                    window.location.href = 'project_details.php?project_id=' + projectId;
                }
                </script>
                <script>
                function redirectToProject(projectId) {
                    window.location.href = 'project_details.php?project_id=' + projectId;
                }
                </script>

            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Upcoming Deadlines (Next 7 Days)</h5>
                <ul class="list-group deadlines">
                    <?php foreach ($upcoming_deadlines as $deadline): ?>
                    <li><?php echo htmlspecialchars($deadline['project_name']); ?> -
                        <?php echo htmlspecialchars($deadline['deadline']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Overdue Projects</h5>
                <ul class="list-group deadlines">
                    <?php foreach ($overdue_projects as $project): ?>
                    <li><?php echo htmlspecialchars($project['project_name']); ?> -
                        <?php echo htmlspecialchars($project['deadline']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="inactivity card">
            <div class="card-body">
                <h5 class="card-title">User Inactivity Time (Today)</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Subtask Name</th>
                            <th>Project Name</th>
                            <th>Assigned Manhours</th>
                            <th>Active Duration</th>
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
                s.subtask_id,
                t.intervaltime,
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
                AND (s.status = 'in progress' OR s.status =! 'Not Started') 
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
                        <tr onclick="redirectToUser(<?php echo $data['id']; ?>)" style="cursor: pointer;">
                            <td><?php echo htmlspecialchars($data['username']); ?></td>
                            <?php if ($user_progress_result->num_rows > 0): ?>
                            <?php while ($row = $user_progress_result->fetch_assoc()): ?>
                            <td><?php echo htmlspecialchars($row['subtask_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['estimated_manhours']); ?></td>
                            <td>
                                <input type="hidden"
                                    name="subtasks[<?php echo htmlspecialchars($row['subtask_id']); ?>][subtask_id]"
                                    value="<?php echo htmlspecialchars($row['subtask_id']); ?>">

                                <?php
                                    if (!empty($row['start_time']) || !empty($row['intervaltime'])) {
                                        $intervalTime = $row['intervaltime'] ?? '00:00:00';
                                        $total_seconds = timeStringToSeconds($intervalTime);

                                        $manhours = $row['estimated_manhours'] ?? '00:00:00';
                                        $total_manhours = timeStringToSeconds($manhours);

                                        if ($total_manhours >= $total_seconds) {
                                            $remainingTime = formatSecondsToTime1($row['remaining_time']);
                                            $estimatedManHours = formatEstimatedManHours1($row['estimated_manhours']);
                                            $remainingTimeInSeconds = timeStringToSeconds($remainingTime);
                                            $estimatedManHoursInSeconds = timeStringToSeconds($estimatedManHours);

                                            if (is_numeric($remainingTimeInSeconds) && is_numeric($estimatedManHoursInSeconds)) {
                                                $active_time = $estimatedManHoursInSeconds - $remainingTimeInSeconds;
                                                echo $active_time < 0
                                                    ? 'Over of time estimated manhours: ' . formatSecondsToTime(abs($active_time))
                                                    : formatSecondsToTime($active_time);
                                            }
                                        } else {
                                            // Late time calculation
                                            $late_time = $total_seconds - $total_manhours;
                                            echo '<span style= "color: red >" ' . formatSecondsToTime($late_time) . ' hrs' . '</span>';
                                        }
                                    } else {
                                        echo '<strong></strong>';
                                    }
                                ?>
                            </td>

                            <?php endwhile; ?>
                            <?php else: ?>
                            <td colspan="4" class="text-center">Not Working</td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars(number_format($data['total_inactivity'], 2)) . ' Minutes'; ?>
                            </td>
                            <form id="timerForm_<?php echo $userId; ?>" action="" method="POST">
                                <td>
                                    <input type="text" id="timerDisplay_<?php echo $userId; ?>" name="counter_timer"
                                        value="" readonly style="background-color: #606d7d ; color: white" />
                                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>" />
                                    <input type="hidden" name="counter_old"
                                        value="<?php echo $data['counter_count']; ?>" />
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
                                        header('Location : admin_dashboard.php');
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
                        <script>
                        function redirectToUser(userid) {
                            window.location.href = 'subtask_show.php?user_id=' + userid;
                        }
                        </script>
                        <?php endif; ?>

                    </tbody>

                </table>
            </div>

        </div>
        <div class="container mt-5">
            <h2>Today's User Logins</h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Login Time</th>
                        <th>Location</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultslogin): ?>
                    <?php foreach ($resultslogin as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['last_login_time']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No users logged in today.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#popupTotalProjects').on('click', function() {
            // Add your code here if needed
        });

        $('#popupCompletedProjects').on('click', function() {
            // Add your code here if needed
        });
    });
    </script>
</body>

</html>