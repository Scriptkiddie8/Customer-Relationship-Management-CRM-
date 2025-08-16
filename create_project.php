<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'header.php';
require 'methods/database.php'; // Include database connection

// Initialize variables for form submission feedback
$message = '';

// Insert new converted leads into the 3d_projects table
$insert_query = "
    INSERT INTO `3d_projects` (id, name, email, phone, conversion_date)
    SELECT l.id, l.name, l.email, l.phone, l.conversion_date
    FROM leads l
    LEFT JOIN `3d_projects` p ON l.name COLLATE utf8mb4_unicode_ci = p.name COLLATE utf8mb4_unicode_ci
    WHERE p.name IS NULL AND l.progress = 'Converted';
";

if ($link->query($insert_query) === FALSE) {
    $message = "Error updating 3D projects: " . $link->error;
}

// Fetch project names from the 3d_projects table
$projects = [];
$projects_query = "SELECT id, name FROM 3d_projects WHERE converted = 0";
$projects_result = $link->query($projects_query);

if ($projects_result) {
    while ($project = $projects_result->fetch_assoc()) {
        $projects[] = $project; // Store projects in an array
    }
} else {
    $message = "Error fetching projects: " . $link->error;
}

// Function to add working days excluding weekends
function addWorkingDays($startDate, $daysToAdd) {
    $currentDate = new DateTime($startDate);
    $daysAdded = 0;

    while ($daysAdded < $daysToAdd) {
        $currentDate->modify('+1 day');
        if ($currentDate->format('N') < 6) { // 1 (Monday) to 5 (Friday) are working days
            $daysAdded++;
        }
    }

    return $currentDate->format('Y-m-d');
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $project_name = $_POST['project_name'];
    $priority = $_POST['priority'];
    $template_id = !empty($_POST['template_id']) ? $_POST['template_id'] : null;
    $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $created_by = $_SESSION['id']; // Replace with the session user ID when integrating authentication

    // Server-side validation (basic example)
    if (empty($project_name)) {
        $message = "Please fill out the project name.";
    } else {
        try {
            $deadline = addWorkingDays(date('Y-m-d'), 3); // 3 working days from today
            $total_manhours = 30; // Default manhours

            // Insert project into the database
            $stmt = $link->prepare("INSERT INTO projects (project_name, priority, template_id, deadline, total_manhours, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $link->error);
            }

            // Bind parameters correctly
            $stmt->bind_param("ssissi", $project_name, $priority, $template_id, $deadline, $total_manhours, $created_by);

            if ($stmt->execute()) {
                // Mark the selected 3D project as converted
                if (!empty($project_id)) {
                    $update_query = "UPDATE 3d_projects SET converted = 1 WHERE id = ?";
                    $update_stmt = $link->prepare($update_query);
                    $update_stmt->bind_param("i", $project_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                $message = "Project created successfully!";
                header("Location: project_dashboard.php");
                exit; // Ensure the script stops executing after redirect
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

        } catch (Exception $e) {
            // Display error message
            $message = "Error: " . $e->getMessage();
        } finally {
            // Close the prepared statements and the database connection
            if (isset($stmt)) {
                $stmt->close();
            }
            if (isset($link)) {
                $link->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Project</title>
    <link rel="stylesheet" href="css/create_project.css">
    <script>
    function toggleProjectSelection() {
        const templateSelect = document.getElementById('template_id');
        const projectSelect = document.getElementById('project_id_group');
        // Enable project selection only for "3D Template"
        if (templateSelect.options[templateSelect.selectedIndex].text === '3D Template') {
            projectSelect.style.display = 'block';
        } else {
            projectSelect.style.display = 'none';
        }
    }
    </script>
</head>

<body>
    <div class="container">
        <h1>Create New Project</h1>
        <form method="post" action="">
            <div class="form-group">
                <label for="project_name">Project Name:</label>
                <input type="text" id="project_name" name="project_name" required>
            </div>
            <div class="form-group">
                <label for="priority">Priority :</label>
                <select id="priority" name="priority" required>
                    <option value="Urgent">Urgent</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>
            </div>

            <div class="form-group">
                <label for="template_id">Project Template:</label>
                <select id="template_id" name="template_id" onchange="toggleProjectSelection()">
                    <option value="">--Select a Template--</option>
                    <?php
                    // Fetch templates from the database
                    $result = $link->query("SELECT template_id, template_name FROM project_templates");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($row['template_id']) . "'>" . htmlspecialchars($row['template_name']) . "</option>";
                        }
                    } else {
                        echo "<option value=''>Error fetching templates</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" id="project_id_group" style="display: none;">
                <label for="project_id">Select 3D Project:</label>
                <select id="project_id" name="project_id">
                    <option value="">--Select a 3D Project--</option>
                    <?php foreach ($projects as $project): ?>
                    <option value="<?php echo htmlspecialchars($project['id']); ?>">
                        <?php echo htmlspecialchars($project['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-submit">Create Project</button>
        </form>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    </div>
</body>

</html>