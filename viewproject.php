<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Get project ID from the URL and sanitize input
$id_project = isset($_GET['id_project']) ? intval($_GET['id_project']) : 0;

// Fetch project details based on the provided project ID
$project_query = "SELECT project_name, status, deadline 
                  FROM projects 
                  WHERE project_id = ?";
$stmt = $link->prepare($project_query);
$stmt->bind_param("i", $id_project); // "i" stands for integer, which is the type of project_id
$stmt->execute();
$project_result = $stmt->get_result();

// Check if the project exists
if ($project_result->num_rows > 0) {
    $project = $project_result->fetch_assoc();
} else {
    die('Project not found.');
}

// Fetch all projects for additional details
$projects_query = "
    SELECT p.project_id, p.project_name, p.status, p.deadline, 
           COUNT(s.subtask_id) as total_tasks, 
           SUM(CASE WHEN s.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM projects p
    LEFT JOIN subtasks s ON p.project_id = s.project_id
    GROUP BY p.project_id
    ORDER BY p.deadline ASC
";
$projects_result = $link->query($projects_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
            margin-top: 20px;
        }
        .box-1 {
            width: 340px;
            padding: 20px;
            background: #f8f9fa;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
            margin: 10px;
        }
        .box-1 span {
            display: block;
            font-size: 20px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .box-1 a {
            color: #007bff;
            text-decoration: none;
        }
        .box-1 a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Display details of the selected project -->
        <!-- <div class="box-1">
            <span>Project Name: <?php echo htmlspecialchars($project['project_name']); ?></span>
            <span>Status: <?php echo htmlspecialchars($project['status']); ?></span>
            <span>Deadline: <?php echo htmlspecialchars($project['deadline']); ?></span>
        </div> -->

        <!-- Display a list of all projects with their details -->
        <?php while ($proj = $projects_result->fetch_assoc()): ?>
            <div class="box-1">
                <span>Project Name: <?php echo htmlspecialchars($proj['project_name']); ?></span>
                <span>Status: <?php echo htmlspecialchars($proj['status']); ?></span>
                <span>Deadline: <?php echo htmlspecialchars($proj['deadline']); ?></span>
                <?php
                $progress = ($proj['total_tasks'] > 0)
                    ? round(($proj['completed_tasks'] / $proj['total_tasks']) * 100)
                    : 0;
                ?>
                <p>Progress: <?php echo $progress; ?>%</p>
                <p>
                    <a href="project_details.php?project_id=<?php echo $proj['project_id']; ?>">View Details</a>
                </p>
            </div>
        <?php endwhile; ?>
        
        
    </div>
</body>
</html>
