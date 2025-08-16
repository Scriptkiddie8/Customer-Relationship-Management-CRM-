<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Initialize variables
$total_projects = 0;
$completed_projects = 0;
$ongoing_projects = 0;
$active_projects = []; // Initialize as an empty array

// Fetch project statistics
$projects_query = "SELECT project_id, project_name, status, deadline FROM projects";
$projects_result = $link->query($projects_query);

// Check for query error
if (!$projects_result) {
    die('Error: ' . $link->error);
}

// Calculate project statistics and collect active project details
$total_projects = $projects_result->num_rows;
$projects_result->data_seek(0); // Reset result pointer

while ($project = $projects_result->fetch_assoc()) {
    if ($project['status'] == 'Completed') {
        $completed_projects++;
    } else {
        $ongoing_projects++;
        $active_projects[] = $project; // Collect detailed project info
    }
}

// Determine badge status and class
$badge_status = 'Active'; // Default status
$badge_class = 'badge-success'; // Default green for 'Active'

if ($completed_projects > 0) {
    $badge_status = 'Completed';
    $badge_class = 'badge-secondary'; // Grey for 'Completed'
} elseif ($ongoing_projects > 0) {
    $badge_status = 'Ongoing';
    $badge_class = 'badge-warning'; // Yellow for 'Ongoing'
}

// Fetch upcoming deadlines and overdue projects
$upcoming_deadlines = [];
$overdue_projects = [];

// Fetch overdue projects
$deadline_query = "SELECT project_name, deadline FROM projects WHERE deadline < NOW() AND status != 'Completed'";
$deadline_result = $link->query($deadline_query);

// Check for query error
if (!$deadline_result) {
    die('Error: ' . $link->error);
}

while ($deadline = $deadline_result->fetch_assoc()) {
    $overdue_projects[] = $deadline;
}

// Fetch upcoming deadlines
$deadline_query = "SELECT project_name, deadline FROM projects WHERE deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND status != 'Completed'";
$deadline_result = $link->query($deadline_query);

// Check for query error
if (!$deadline_result) {
    die('Error: ' . $link->error);
}

while ($deadline = $deadline_result->fetch_assoc()) {
    $upcoming_deadlines[] = $deadline;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dashboard.css">

    <style>
    body {
        margin: 0;
        padding: 0;
        background-color: #f8f9fa;
        font-family: Arial, sans-serif;
    }

    .sidebar h2 {
        margin-top: 0;
    }

    .sidebar a {
        color: #fff;
        text-decoration: none;
        display: block;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 5px;
    }

    .sidebar a:hover {
        background-color: #495057;
    }

    .content {
        margin-top: 20px !important;
        margin-left: 50px;
        padding: 20px;
    }

    .card {
        margin-bottom: 20px;
    }

    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .stat {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 15px;
    }

    .stat h3 {
        margin-top: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .deadlines ul {
        list-style-type: none;
        padding-left: 0;
    }

    .deadlines ul li {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 5px;
    }

    th,
    td {
        padding: 15px;
    }

    .hidden {
        display: none;
    }
    .details-box{
        display: none;
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        background-color: #28a745;
        /* Green color for live status */
        color: #fff;
        padding: 5px 10px;
        border-radius: 50px;
    }
    </style>
</head>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#toggleTimeTracking').on('click', function() {
        $('.hidden').toggle();
    });

    
    $('.project-name').on('click', function() {
        $('.details-box').toggle();
    });
});
</script>
<style>
.hidden a{
    text-decoration: none;
    color : black
}
</style>

<body>
    <div class="d-flex">
        <div class="content">
            <h1>Dashboard</h1>
            <div class="row">
                <div class="col-md-4">
                    <div class="stat bg-primary text-white">
                        <h3 id="toggleTimeTracking">Total Projects <span
                                class="badge-status <?php echo htmlspecialchars($badge_class); ?>"><?php echo htmlspecialchars($badge_status); ?></span>
                        </h3>
                        <p class="hidden"><a href="viewproject.php?id_project=<?=$total_projects?>"><?php echo $total_projects; ?></a></p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat bg-success text-white">
                        <h3>Completed Projects</h3>
                        <p><?php echo $completed_projects; ?></p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat bg-warning text-dark">
                        <h3>Ongoing Projects</h3>
                        <p><?php echo $ongoing_projects; ?></p>
                    </div>
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>

</html>