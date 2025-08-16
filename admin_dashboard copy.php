<?php
require 'methods/database.php'; // Include database connection

// Fetch project statistics
$projects_query = "SELECT * FROM projects";
$projects_result = $link->query($projects_query);

// Calculate project statistics
$total_projects = $projects_result->num_rows;
$projects_result->data_seek(0); // Reset result pointer

$completed_projects = 0;
$ongoing_projects = 0;

while ($project = $projects_result->fetch_assoc()) {
    if ($project['status'] == 'Completed') {
        $completed_projects++;
    } else {
        $ongoing_projects++;
    }
}

$upcoming_deadlines = [];
$overdue_projects = [];

// Fetch upcoming deadlines and overdue projects
$deadline_query = "SELECT project_name, deadline FROM projects WHERE deadline < NOW() AND status != 'Completed'";
$deadline_result = $link->query($deadline_query);

while ($deadline = $deadline_result->fetch_assoc()) {
    $overdue_projects[] = $deadline;
}

$deadline_query = "SELECT project_name, deadline FROM projects WHERE deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND status != 'Completed'";
$deadline_result = $link->query($deadline_query);

while ($deadline = $deadline_result->fetch_assoc()) {
    $upcoming_deadlines[] = $deadline;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>
<style>
    
</style>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>

        <!-- Project Statistics -->
        <div class="statistics">
            <h2>Project Statistics</h2>
            <div class="stat">
                <h3>Total Projects</h3>
                <p><?php echo $total_projects; ?></p>
            </div>
            <div class="stat">
                <h3>Completed Projects</h3>
                <p><?php echo $completed_projects; ?></p>
            </div>
            <div class="stat">
                <h3>Ongoing Projects</h3>
                <p><?php echo $ongoing_projects; ?></p>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="deadlines">
            <h2>Upcoming Deadlines (Next 7 Days)</h2>
            <ul>
                <?php foreach ($upcoming_deadlines as $deadline): ?>
                    <li><?php echo htmlspecialchars($deadline['project_name']); ?> -
                        <?php echo htmlspecialchars($deadline['deadline']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Overdue Projects -->
        <div class="deadlines">
            <h2>Overdue Projects</h2>
            <ul>
                <?php foreach ($overdue_projects as $project): ?>
                    <li><?php echo htmlspecialchars($project['project_name']); ?> -
                        <?php echo htmlspecialchars($project['deadline']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>

</html>