<?php
include 'header.php';
require 'methods/database.php';

// Initialize search variables
$project_name = isset($_GET['project_name']) ? $_GET['project_name'] : '';
$username = isset($_GET['username']) ? $_GET['username'] : '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : '';
$deadline = isset($_GET['deadline']) ? $_GET['deadline'] : ''; // New variable for deadline

// Prepare the SQL query with conditions based on user input
$projects_query = "
SELECT p.*, 
       COUNT(s.subtask_id) as total_tasks, 
       SUM(CASE WHEN s.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
       SUM(s.new_total_manhours) as total_manhours,
       u.username, 
       CASE 
           WHEN p.deadline < CURDATE() THEN 2  
           ELSE 1  
       END as deadline_status
FROM projects p
LEFT JOIN subtasks s ON p.project_id = s.project_id
JOIN users u ON s.assigned_to = u.id
WHERE 1=1
"; // Start with a base condition

// Add filters based on user input
if (!empty($project_name)) {
    $projects_query .= " AND p.project_name LIKE '%" . $link->real_escape_string($project_name) . "%'";
}
if (!empty($username)) {
    $projects_query .= " AND u.username LIKE '%" . $link->real_escape_string($username) . "%'"; // Filter by username
}
if (!empty($month) && !empty($year)) {
    $projects_query .= " AND MONTH(p.deadline) = $month AND YEAR(p.deadline) = $year";
}
if (!empty($deadline)) {
    $projects_query .= " AND p.deadline = '" . $link->real_escape_string($deadline) . "'";
}

$projects_query .= " GROUP BY p.project_id
ORDER BY 
         FIELD(p.priority, 'Urgent', 'High', 'Medium', 'Low') ASC, 
         total_manhours DESC,  
         deadline_status ASC,  
         p.deadline DESC,     
         p.created_at DESC";

// Execute the query
$projects_result = $link->query($projects_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project View</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 1500px;
        margin-top: 60px !important;
        margin: auto;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
        text-align: center;
        color: #333;
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
        background-color: #f2f2f2;
    }

    tr:hover {
        background-color: #f5f5f5;
    }

    .completed-row {
        background-color: lightgreen;
        /* Light green background for completed projects */
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

    .search-button a {
        color: white;
        text-decoration: none;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>Project View</h1>
        <section class="section projects">
            <form method="GET" action="" class="search-form">
                <input type="text" name="project_name" placeholder="Search by project name"
                    value="<?php echo htmlspecialchars($project_name); ?>" class="search-input">



                <select name="month" class="search-select">
                    <option value="">Select Month</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php if($month == $m) echo 'selected'; ?>>
                        <?php echo date("F", mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                    <?php endfor; ?>
                </select>

                <select name="year" class="search-select">
                    <option value="">Select Year</option>
                    <?php for ($y = date('Y'); $y >= 2000; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php if($year == $y) echo 'selected'; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>

                <!-- New date input for deadline -->
                <input type="date" name="deadline" placeholder="Select Deadline"
                    value="<?php echo htmlspecialchars($deadline); ?>" class="search-input">

                <button type="submit" class="search-button">Search</button>

            </form>
            <div class="projects-list">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Deadline</th>
                            <th>Assigned To</th> <!-- New column for assigned user -->
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <script>
                        function redirectToProject(projectId, projectName) {
                            window.location.href = 'client_project.php?project_id=' + projectId + '&project_name=' +
                                encodeURIComponent(projectName);
                        }
                        </script>

                        <?php 
    $order = ['Not Started', 'In Progress', 'Completed'];
    
    $projects = [];
    while ($project = $projects_result->fetch_assoc()) {
        $projects[] = $project;
    }

    usort($projects, function($a, $b) use ($order) {
        return array_search($a['status'], $order) - array_search($b['status'], $order);
    });

    if (empty($projects)): ?>
                        <tr>
                            <td colspan="6">No projects found.</td>
                        </tr>
                        <?php else: 
        foreach ($projects as $project):
            $progress = ($project['total_tasks'] > 0)
                ? round(($project['completed_tasks'] / $project['total_tasks']) * 100)
                : 0;

            $rowClass = ($project['status'] == 'Completed') ? 'completed-row' : '';
    ?>

                        <?php if($project['project_type']=='3D'):?>
                        <!-- only show project 3D Start code  -->
                        <tr onclick="redirectToProject(<?php echo $project['project_id']; ?>, '<?php echo htmlspecialchars($project['project_name'], ENT_QUOTES); ?>')"
                            class="<?php echo $rowClass; ?>" style="cursor: pointer;">
                            <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($project['deadline']); ?></td>
                            <td><?php echo htmlspecialchars($project['username']); ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $progress; ?>%;">
                                        <?php echo $progress; ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($project['status']); ?></td>
                            <td><?php echo htmlspecialchars($project['priority']); ?></td>
                        </tr>
                        <!-- only show project 3D end code  -->

                        <?php endif;?>
                        <?php endforeach; endif; ?>
                    </tbody>

                </table>
            </div>
        </section>
    </div>
</body>

</html>