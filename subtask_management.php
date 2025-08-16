<?php
// session_start();
include 'header.php';       
require 'methods/database.php'; // Include database connection

if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'User') {
    header("location: index.php");
    exit;
}
$user_id = $_SESSION['id'];


// Get the project ID from the URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Initialize $selected_project to avoid undefined variable errors
$selected_project = '';

// Fetch existing subtasks for the project
$subtasks_query = $link->prepare("SELECT * FROM `subtasks` WHERE project_id = ?");
$subtasks_query->bind_param("i", $project_id);
$subtasks_query->execute();
$subtasks_result = $subtasks_query->get_result();

// Initialize variables for form submission feedback
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subtask'])) {
    $subtask_name = $_POST['subtask_name'];
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['deadline'];
    $status = 'Pending'; // Default status

    // Insert new subtask into the database
    $stmt = $link->prepare("INSERT INTO subtasks (project_id, subtask_name, assigned_to, deadline, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $project_id, $subtask_name, $assigned_to, $deadline, $status);

    if ($stmt->execute()) {
        $message = "Subtask added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_subtask'])) {
    $subtask_id = $_POST['subtask_id'];
    $subtask_name = $_POST['subtask_name'];
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['deadline'];
    $status = $_POST['status'];

    // Update subtask details
    $stmt = $link->prepare("UPDATE subtasks SET subtask_name = ?, assigned_to = ?, deadline = ?, status = ? WHERE subtask_id = ?");
    $stmt->bind_param("ssssi", $subtask_name, $assigned_to, $deadline, $status, $subtask_id);

    if ($stmt->execute()) {
        $message = "Subtask updated successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

?>

<?php
// Fetch subtasks
$subtasks_result = $link->query("SELECT * FROM `subtasks`");
if (!$subtasks_result) {
    die("Error in query: " . $link->error);
}

// Fetch projects for dropdown
$projects_result = $link->query("SELECT project_id, project_name FROM `projects`");
if (!$projects_result) {
    die("Error in query: " . $link->error);
}

// Convert projects result set to an array
$projects = [];
while ($project = $projects_result->fetch_assoc()) {
    $projects[] = $project;
}

// Fetch subtasks for dropdown (if needed)
$all_subtasks_result = $link->query("SELECT subtask_id, subtask_name FROM `subtasks`");
if (!$all_subtasks_result) {
    die("Error in query: " . $link->error);
}

// Convert all_subtasks result set to an array
$all_subtasks = [];
while ($subtask = $all_subtasks_result->fetch_assoc()) {
    $all_subtasks[] = $subtask;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subtask Management</title>
    <link rel="stylesheet" href="css/subtask_management.css">
</head>
<style>
    tr,
    td,
    a {
        text-decoration: none;
        color: black;
    }
    .substask_1{
        padding: 10px 20px;
    }
  
    
</style>

<body>
    <div class="container">
        <h1>Subtask Management</h1>

        <div class="message">
            <?php echo $message; ?>
        </div>

        <!-- Add Subtask Form -->
        <div class="form-container">
            <h2>Add New Subtask</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label for="subtask_name">Subtask Name:</label>
                    <input type="text" id="subtask_name" name="subtask_name" required>
                </div>
                <div class="form-group">
                    <label for="assigned_to">Assign To (User ID):</label>
                    <input type="text" id="assigned_to" name="assigned_to" required>
                </div>
                <div class="form-group">
                    <label for="deadline">Deadline:</label>
                    <input type="date" id="deadline" name="deadline" required>
                </div>
                <button type="submit" name="add_subtask" class="btn-submit">Add Subtask</button>
            </form>
        </div>

        <div class="subtasks-list">

            <!--<h2>Search Project</h2>-->
            <!--<form action="" method="post">-->
            <!--    <table>-->
            <!--        <thead>-->
            <!--            <tr>-->
            <!--                <th>Project Dropdown</th>-->
            <!--                <th>Click Here</th>-->
            <!--            </tr>-->
            <!--        </thead>-->
            <!--        <tbody>-->
            <!--            <?php if ($subtask = $subtasks_result->fetch_assoc()): ?>-->
            <!--            <tr>-->
                            <!-- Dropdown for selecting project -->
            <!--                <td>-->
            <!--                    <select class="substask_1" name="project_<?php echo htmlspecialchars($subtask['subtask_id']); ?>"-->
            <!--                        id="project_<?php echo htmlspecialchars($subtask['subtask_id']); ?>">-->
            <!--                        <option value="">--Select Project--</option>-->
            <!--                        <?php foreach ($projects as $option_project): ?>-->
            <!--                        <option value="<?php echo htmlspecialchars($option_project['project_id']); ?>">-->
            <!--                            <?php echo htmlspecialchars($option_project['project_name']); ?>-->
            <!--                        </option>-->
            <!--                        <?php endforeach; ?>-->
            <!--                    </select>-->
            <!--                </td>-->
            <!--                <td><button  class="substask_1 btn-submit"  name="btnsubmit">Show SubTask</button></td>-->
            <!--            </tr>-->
            <!--            <?php endif; ?>-->
            <!--        </tbody>-->
            <!--    </table>-->
                                    
            <!--</form>-->
            <?php
                // if (isset($_POST['btnsubmit'])) {
                //     // Loop through each subtask to get the selected values
                //     foreach ($_POST as $key => $value) {
                //         if (strpos($key, 'project_') === 0) {
                //             $subtask_id = str_replace('project_', '', $key);
                //             $selected_project = htmlspecialchars($value);
                //         }
                //     }
                // }
            ?>

            <!-- Subtasks List -->
            <div class="subtasks-list">
                <h2>Existing Subtasks</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Subtask ID</th>
                            <th>Subtask Name</th>
                            <th>Assigned To</th>
                            <th>Deadline</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <?php
                    
                        $subtasks_result = $link->query("SELECT * FROM `subtasks` WHERE assigned_to = $user_id ");

                        if (!$subtasks_result) {
                            die("Error in query: " . $link->error);
                        }
                    ?>
                    <tbody>
                        <?php while ($subtask = $subtasks_result->fetch_assoc()): ?>
                        <tr>
                            <td><a href="project_spf.php?subtask_id=<?php echo urlencode($subtask['subtask_id']); ?>">
                                <?php echo htmlspecialchars($subtask['subtask_id']); ?>
                                </a></td>
                            <td><a href="project_spf.php?subtask_id=<?php echo urlencode($subtask['subtask_id']); ?>">
                                    <?php echo htmlspecialchars($subtask['subtask_name']); ?>
                                </a></td>
                            <td><?php echo htmlspecialchars($subtask['assigned_to']); ?></td>
                            <td><?php echo htmlspecialchars($subtask['deadline']); ?></td>
                            <td><?php echo htmlspecialchars($subtask['status']); ?></td>
                            <!-- <td>
                                <a href="edit_subtask.php?subtask_id=<?php echo urlencode($subtask['subtask_id']); ?>"
                                    class="btn-edit">Edit</a>
                            </td> -->
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <script>
                function fetchSubtasks(projectId, subtaskId) {
                    if (!projectId) return;

                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', `fetch_subtasks.php?project_id=${encodeURIComponent(projectId)}`, true);
                    xhr.onload = function () {
                        if (xhr.status === 200) {
                            document.getElementById('subtasks_' + subtaskId).innerHTML = xhr.responseText;
                        } else {
                            console.error('Error fetching subtasks:', xhr.statusText);
                        }
                    };
                    xhr.onerror = function () {
                        console.error('Request failed');
                    };
                    xhr.send();
                }
            </script>
        </div>
</body>

</html>
