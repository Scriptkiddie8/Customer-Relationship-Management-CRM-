<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// include 'header.php';
session_start();
require 'methods/database.php';

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

$user_id = $_SESSION['id'];

// Prepare and execute the SQL statement to get user info
$stmt = $link->prepare("SELECT DISTINCT department_id, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $department = htmlspecialchars($row["department_id"]);
    $username = htmlspecialchars($row["username"]);
} else {
    echo "No departments found";
    exit; // Stop further execution if no user found
}

if (isset($_POST['justificationbtn'])) {
    $username = $_POST['username'];
    $department_id = $_POST['department_id'];
    $comments = $_POST['comments'];

    // Prepare the SQL statement to insert justification
    $stmt = $link->prepare("INSERT INTO justification_crm (user_id, username, department_id, comments) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $username, $department_id, $comments);

    // Execute the statement and check for success
    if ($stmt->execute()) {
        header('Location: project_dashboard.php');
        exit;
    } else {
        echo "Error: " . htmlspecialchars($stmt->error);
    }



    $stmt->close();
}

$today = date('Y-m-d');
$tasks_query = "
    SELECT s.subtask_name, s.deadline, s.remaining_time, 
           p.project_name, s.status, p.priority 
    FROM subtasks s
    JOIN projects p ON s.project_id = p.project_id
    WHERE s.assigned_to = ? 
      AND DATE(s.deadline) = ? 
      AND s.status = 'Not Started'"; // Filter for tasks with status "Not Started"

$stmt = $link->prepare($tasks_query);
$stmt->bind_param("is", $_SESSION['id'], $today);
$stmt->execute();
$tasks_result = $stmt->get_result();

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Justification</title>
    <link rel="stylesheet" href="styles.css">
</head>
<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 600px;
    width: 50%;
    margin: auto;
    background: #fff;
    padding: 50px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

h1,
h2 {
    color: #333;
}

ul {
    margin: 10px 0;
    padding-left: 20px;
}

label {
    display: block;
    margin: 10px 0 5px;
}

input,
textarea {
    width: 100%;
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

button {
    background-color: #28a745;
    color: white;
    padding: 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background-color: #218838;
}

.message {
    margin-top: 20px;
    color: green;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background-color: #fff;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

th,
td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #007bff;
    color: #fff;
}

tr:hover {
    background-color: #f1f1f1;
}

p {
    font-size: 16px;
    color: #666;
}

th a {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 15px;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
}

a:hover {
    background-color: #0056b3;
}
</style>
<script>
document.getElementById('feedbackForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const comments = document.getElementById('comments').value;

    if (comments) {
        document.getElementById('message').innerText = 'Feedback submitted successfully!';
        document.getElementById('feedbackForm').reset();
    } else {
        document.getElementById('message').innerText = 'Please fill in all fields.';
    }
});
</script>

<body>
    <div class="container">
        <h1>Justification for CRM</h1>
        <form id="feedbackForm" method="POST">
            <label for="user_id">User ID:</label>
            <input type="text" id="user_id" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>" readonly>

            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($username); ?>" readonly>

            <label for="department_id">Department:</label>
            <input type="text" id="department_id" name="department_id" value="<?php
            if ($department == 1) {
                echo "3D Team";
            } elseif ($department == 2) {
                echo "Development Team";
            } else {
                echo "Other Department";
            }
        ?>" readonly>
            <label for="comments">Comments:</label>
            <textarea id="comments" name="comments" rows="4" required></textarea>
            <button name="justificationbtn">Submit</button>
        </form>
        <?php if ($tasks_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Subtask Name</th>
                    <th>Deadline</th>
                    <th>Project Name</th>
                    <th>Status</th>
                    <th>Priority</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($task = $tasks_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($task['subtask_name']); ?></td>
                    <td><?php echo htmlspecialchars($task['deadline']); ?></td>
                    <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                    <td><?php echo htmlspecialchars($task['status']); ?></td>
                    <td><?php echo htmlspecialchars($task['priority']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No tasks with 'Not Started' status are due today.</p>
        <?php endif; ?>
        <script src="script.js"></script>
    </div>

</body>

</html>