<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['id'];
// Function to calculate the number of working days between two dates
function getWorkingDays($startDate, $endDate)
{
    $count = 0;
    $currentDate = $startDate;

    while ($currentDate <= $endDate) {
        $dayOfWeek = date('N', strtotime($currentDate));
        if ($dayOfWeek < 6) { // 1 (Monday) to 5 (Friday)
            $count++;
        }
        $currentDate = date('Y-m-d', strtotime($currentDate . '+1 day'));
    }

    return $count;
}

// Get the project ID from the URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Fetch project details
$project_query = $link->prepare("SELECT * FROM projects WHERE project_id = ?");
$project_query->bind_param("i", $project_id);
$project_query->execute();
$project = $project_query->get_result()->fetch_assoc();

// Fetch subtasks
$subtasks_query = $link->prepare("
    SELECT subtasks.*, 
           users.username AS assigned_username 
    FROM subtasks 
    LEFT JOIN users 
    ON subtasks.assigned_to = users.id 
    WHERE subtasks.project_id = ? 
    ORDER BY 
      FIELD(subtasks.status, 'Not Started', 'in progress', 'paused', 'completed'), 
      FIELD(subtasks.priority, 'Urgent', 'High', 'Medium', 'Low')
");
$subtasks_query->bind_param("i", $project_id);
$subtasks_query->execute();
$subtasks_result = $subtasks_query->get_result();

// Fetch the username and department_id for the given user_id
$department_query = $link->prepare("SELECT username, department_id , team_leader FROM users WHERE id = ?");
$department_query->bind_param("i", $user_id); // Assuming user_id is an integer
$department_query->execute();
$department_result = $department_query->get_result();

// Check if the query was successful
if ($department_result) {
    // Fetch the result as an associative array
    $users_name = $department_result->fetch_assoc();

    // Check if any data was returned
    if ($users_name) {
        // Access the department_id from the result
        $department_id = $users_name['department_id'];
        $team_leader = $users_name['team_leader'];
        // Output the department_id
        echo $department_id;
    } else {
        echo "No user found with the given ID.";
    }
} else {
    // Handle query error
    die('Query failed: ' . htmlspecialchars($department_query->error));
}

// Fetch users for assigning tasks
$users_query = $link->prepare("SELECT id, username FROM users WHERE role = 'User' AND department_id = ?  ORDER BY username ASC");
$users_query->bind_param("i", $department_id); // Bind the department_id
$users_query->execute();
$users_result = $users_query->get_result();

// Check if the users query was successful
if ($users_result) {
    // Fetch all users as an associative array
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
} else {
    // Handle query error
    die('Query failed: ' . htmlspecialchars($users_query->error));
}

// Close the prepared statements
$department_query->close();
$users_query->close();



// Fetch comments
$comments_query = $link->prepare("SELECT * FROM comments WHERE project_id = ?");
$comments_query->bind_param("i", $project_id);
$comments_query->execute();
$comments_result = $comments_query->get_result();

// Fetch timeline events
$timeline_query = $link->prepare("SELECT * FROM timeline WHERE project_id = ? ORDER BY event_time DESC");
$timeline_query->bind_param("i", $project_id);
$timeline_query->execute();
$timeline_result = $timeline_query->get_result();

// Initialize variables for form submission feedback
$message = '';
$message_manhours = '';
// Handle subtask creation
// Handle subtask creation
// Handle subtask creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_subtask'])) {
    // Fetch input values from the form
    $subtask_name = $_POST['subtask_name'];
    $deadline = $_POST['subtask_deadline'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $estimated_manhours = isset($_POST['estimated_manhours']) ? (int)$_POST['estimated_manhours'] : 0;

    // Validate inputs
    if (empty($subtask_name) || empty($deadline) || empty($estimated_manhours)) {
        $message = 'All fields are required.';
    } elseif ($estimated_manhours <= 0) {
        $message = 'Estimated manhours must be a positive number.';
    } else {
        // Get the total manhours already assigned to the project
        $query = $link->prepare("SELECT SUM(estimated_manhours) AS total_manhours FROM subtasks WHERE project_id = ?");
        $query->bind_param("i", $project_id);
        $query->execute();
        $result = $query->get_result();
        $row = $result->fetch_assoc();
        $current_manhours = $row['total_manhours'] ?: 0;

        // Calculate the new total manhours if the subtask is added
        $new_total_manhours = $current_manhours + $estimated_manhours;

        if ($new_total_manhours >= 31) {
            // Manhours exceed 30, so display a warning message
            $message_manhours = "Total manhours exceed 30! Current total: $current_manhours, adding $estimated_manhours will exceed the limit.";
        } else {
            // Validate deadline
            $today = new DateTime();
            $deadlineDate = new DateTime($deadline);
            $threeDayLimit = (clone $today)->modify('+3 weekdays');
            $fiveDayLimit = (clone $today)->modify('+5 weekdays');

            // Set the message based on the deadline
            if ($deadlineDate <= $fiveDayLimit) {
                if ($deadlineDate <= $threeDayLimit) {
                    $message = 'This task must be completed within 3 working days!';
                } else {
                    $message = 'This task must be completed within 5 working days!';
                }
            } else {
                $message = 'Warning: Deadline exceeds the 5-day working limit!';
            }

            // If there's no warning, proceed with the database insertion
            if (strpos($message, 'Warning') === false) {
                try {
                    // Prepare the SQL statement to insert the subtask
                    $stmt = $link->prepare("INSERT INTO subtasks (project_id, subtask_name, deadline, description, estimated_manhours, new_total_manhours, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Not Started')");

                    // Check if the statement was prepared correctly
                    if ($stmt === false) {
                        throw new Exception("Prepare failed: " . $link->error);
                    }

                    // Bind parameters and execute the statement
                    $stmt->bind_param("isssiss", $project_id, $subtask_name, $deadline, $description, $estimated_manhours, $new_total_manhours, $priority);

                    if ($stmt->execute()) {
                        // Success: redirect to the project details page
                        $message = "Subtask created successfully!";
                        header('Location: project_details.php?project_id=' . $project_id);
                        exit(); // Ensure no further code is executed after redirect
                    } else {
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                } catch (Exception $e) {
                    // Handle errors and log them
                    $message = "Error: " . $e->getMessage();
                    error_log($e->getMessage(), 3, "errors.log"); // Log errors to server
                } finally {
                    // Close the statement if it was prepared
                    if (isset($stmt)) {
                        $stmt->close();
                    }
                }
            } else {
                // Display the warning message if the deadline exceeds the limit
                echo "<strong class='text-warning'>$message</strong>";
            }
        }
    }
}


// Handle comment addition
$message = ""; // Initialize message variable
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['add_comment'])) {
        $comment_text = $_POST['comment_text'];
        $comment_username = $_POST['comment_username']; // Ensure comment_username is retrieved from form
        $file_path = !empty($_FILES['file_upload']['name']) ? 'uploads/' . basename($_FILES['file_upload']['name']) : null;

        // Handle file upload
        if ($file_path && !move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path)) {
            $message = "Failed to upload file.";
        } else {
            try {
                // Insert comment into the database (with username)
                $stmt = $link->prepare("INSERT INTO comments (project_id, user_id, comment_username, comment_text, file_path) VALUES (?, ?, ?, ?, ?)");
                // $user_id = 1; // Replace with session user ID if available
                $stmt->bind_param("iisss", $project_id, $user_id, $comment_username, $comment_text, $file_path);

                if ($stmt->execute()) {
                    $message = "Comment added successfully!";
                    header('Location: project_details.php?project_id=' . $project_id);
                    exit;
                } else {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                error_log($e->getMessage(), 3, "errors.log"); // Log errors to server
            } finally {
                if (isset($stmt)) {
                    $stmt->close();
                }
            }
        }
    }


    if (isset($_POST['update_comment'])) {
        $comment_id = $_POST['comment_id']; // Ensure comment ID is passed
        $comment_text = $_POST['comment_text'];
        $file_path = $_FILES['file_upload']['name'] ? 'uploads/' . basename($_FILES['file_upload']['name']) : null;

        // Handle file upload
        if ($file_path && !move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path)) {
            $message = "Failed to upload file.";
        } else {
            try {
                // Fetch existing file if no new file is uploaded
                if (!$file_path) {
                    $stmt = $link->prepare("SELECT file_path FROM comments WHERE comment_id = ?");
                    $stmt->bind_param("i", $comment_id);
                    $stmt->execute();
                    $stmt->bind_result($existing_file_path);
                    $stmt->fetch();
                    $file_path = $existing_file_path;
                    $stmt->close();
                }

                // Update comment in the database
                $query = "UPDATE comments SET comment_text = ?, file_path = ? WHERE comment_id = ?";
                $stmt = $link->prepare($query);
                $stmt->bind_param("ssi", $comment_text, $file_path, $comment_id);

                if ($stmt->execute()) {
                    $message = "Comment updated successfully!";
                    header('Location: project_details.php?project_id=' . $project_id);
                    exit;
                } else {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                error_log($e->getMessage(), 3, "errors.log");
            } finally {
                if (isset($stmt)) {
                    $stmt->close();
                }
            }
        }
    }
}
if (isset($_POST['delete_comment'])) {
    $comment_id = $_POST['comment_id']; // Ensure comment ID is passed

    try {
        // Delete comment from the database
        $stmt = $link->prepare("DELETE FROM comments WHERE comment_id = ?");
        $stmt->bind_param("i", $comment_id);

        if ($stmt->execute()) {
            $message = "Comment deleted successfully!";
            header('Location: project_details.php?project_id=' . $project_id);
            exit;
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        error_log($e->getMessage(), 3, "errors.log");
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}


// Handle task assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_task'])) {
    $subtask_ids = $_POST['subtasks'];

    foreach ($subtask_ids as $subtask_id => $subtask_data) {
        // Check if assigned_to and subtask_deadline keys exist
        $assigned_to = isset($subtask_data['assigned_to']) ? $subtask_data['assigned_to'] : null;
        $deadline = isset($subtask_data['subtask_deadline']) ? $subtask_data['subtask_deadline'] : null;

        // Validate assigned_to and deadline

        // Create DateTime object only if the deadline is valid
        if ($deadline) {
            try {
                $today = new DateTime();
                $deadlineDate = new DateTime($deadline);
            } catch (Exception $e) {
                echo "<script>alert('Error: Invalid deadline for subtask ID $subtask_id.');</script>";
                $all_tasks_assigned = false; // Mark as not fully successful
                continue; // Skip if the deadline is invalid
            }
        } else {
            continue; // Skip if no deadline is provided
        }

        $threeDayLimit = (clone $today)->modify('+2 weekday');
        $fiveDayLimit = (clone $today)->modify('+4 weekday');

        // Initialize message variable
        $message = '';

        if ($deadlineDate <= $fiveDayLimit) {
            if ($deadlineDate <= $threeDayLimit) {
                $message = 'This task must be completed within 3 working days!';
            } else {
                $message = 'This task must be completed within 5 working days!';
            }
        } else {
            $message = 'Warning: Deadline exceeds the 5-day working limit!';
        }

        // If there are no warnings, proceed to update the subtask
        if (strpos($message, 'Warning') === false) {
            // Prepare to fetch the subtask data
            $subtask_query = $link->prepare("SELECT status FROM subtasks WHERE subtask_id = ?");
            $subtask_query->bind_param("i", $subtask_id);
            $subtask_query->execute();
            $subtask_result = $subtask_query->get_result();

            if ($subtask_result->num_rows > 0) {
                $subtask = $subtask_result->fetch_assoc();

                // Check subtask status
                if ($subtask['status'] === 'Completed') {
                    $message = "Cannot update a completed subtask.";
                } else {
                    // Prepare the SQL update statement
                    if ($subtask['status'] === 'In Progress') {
                        // Update only the assigned user for "In Progress" or "Paused" tasks
                        $stmt = $link->prepare("UPDATE subtasks SET assigned_to = ? WHERE subtask_id = ?");
                        $stmt->bind_param("ii", $assigned_to, $subtask_id);
                    } else {
                        // Update assigned user and deadline for other statuses
                        $stmt = $link->prepare("UPDATE subtasks SET assigned_to = ?, deadline = ? WHERE subtask_id = ?");
                        $stmt->bind_param("isi", $assigned_to, $deadline, $subtask_id);
                    }

                    // Execute the prepared statement
                    if ($stmt->execute()) {
                        $message = "Task assigned successfully!";
                    } else {
                        $message = "Execute failed: " . $stmt->error;
                    }
                }
            } else {
                $message = "Subtask not found.";
            }
        }

        // If there's a message, alert it
        if (!empty($message)) {
            echo "<script>alert('$message');</script>"; // Display error message
        }

        // Close statement if it exists
        if (isset($stmt)) {
            $stmt->close();
        }
    }

    // Redirect if there was a success message
    if (strpos($message, 'successfully') !== false) {
        header('Location: project_details.php?project_id=' . $project_id);
        exit(); // Ensure no further code is executed
    }
}



?>
<?php
// Calculate today's date and the dates 3 and 5 days from now
$today = new DateTime();
$threeDaysLater = (clone $today)->modify('+2 days');
$fiveDaysLater = (clone $today)->modify('+4 days');

// If the date falls on a weekend, adjust to the next Monday
function adjustForWeekend(DateTime $date)
{
    $dayOfWeek = $date->format('N'); // 'N' returns 1 (for Monday) through 7 (for Sunday)
    if ($dayOfWeek == 6) { // Saturday
        $date->modify('+2 days');
    } elseif ($dayOfWeek == 7) { // Sunday
        $date->modify('+1 day');
    }
    return $date;
}

// Adjust dates if they fall on a weekend
$threeDaysLater = adjustForWeekend($threeDaysLater);
$fiveDaysLater = adjustForWeekend($fiveDaysLater);

$min_date = $today->format('Y-m-d');
$max_date = $threeDaysLater->format('Y-m-d');
$max_date1 = $fiveDaysLater->format('Y-m-d');


?>


<?php
// Prepare the SQL statement to join the timeline and subtask tables
$join_query = $link->prepare("
    SELECT 
        subtasks.subtask_id,
        subtasks.subtask_name,
        subtasks.deadline,
        subtasks.estimated_manhours,
        subtasks.status,
        subtasks.assigned_to,
        timeline.event_id,
        timeline.event_title,
        timeline.event_description,
        timeline.start_time,
        timeline.event_time,
        timeline.pause_resume_time,
        timeline.file_image,
        timeline.intervaltime
    FROM subtasks
    LEFT JOIN timeline ON subtasks.subtask_id = timeline.pro_sub_id
    WHERE subtasks.project_id = ?
    ORDER BY 
        FIELD(subtasks.status, 'completed', 'pause', 'Not Started' , 'in progress'),
        FIELD(timeline.event_title, 'Subtask completed', 'Subtask paused', 'N/A')
    ;
");

// Check if prepare was successful
if ($join_query === false) {
    die('Prepare failed: ' . htmlspecialchars($link->error));
}

// Bind the parameter for project_id instead of subtask_id
$join_query->bind_param("i", $project_id);

// Execute the query
if (!$join_query->execute()) {
    die('Execute failed: ' . htmlspecialchars($join_query->error));
}

// Get the result
$join_result = $join_query->get_result();
if ($join_result === false) {
    die('Get result failed: ' . htmlspecialchars($join_query->error));
}

// Close the statement
$join_query->close();
?>
<?php

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_subtask'])) {
    
    $subtask_id = $_POST['subtask_id'];
    $new_subtask_name = $_POST['subtask_name'];
    if (empty($subtask_id) || empty($new_subtask_name)) {
        $message = 'Subtask ID and name are required.';
    } else {
        try {
           
            $stmt = $link->prepare("UPDATE subtasks SET subtask_name = ? WHERE subtask_id = ?");

            // Check if the statement was prepared correctly
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $link->error);
            }

            // Bind parameters and execute the statement
            $stmt->bind_param("si", $new_subtask_name, $subtask_id);

            if ($stmt->execute()) {
                // Success: refresh the page to update the list or show a success message
                $message = "Subtask name updated successfully!";
                header('Location: project_details.php?project_id=' . $project_id);
                // Optional: header('Location: your_page.php'); // Redirect to avoid resubmission
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        } catch (Exception $e) {
            // Handle errors and log them
            $message = "Error: " . $e->getMessage();
            error_log($e->getMessage(), 3, "errors.log"); // Log errors to server
        } finally {
            // Close the statement if it was prepared
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
?>
<?php
$manhours = $project['total_manhours'];
$project_query = $link->prepare("SELECT * FROM project_templates WHERE default_deadline_days = ?");
$project_query->bind_param("i", $manhours);
$project_query->execute();
$result = $project_query->get_result(); // Fetch the result set

$template_names = [];
while ($project_template_name = $result->fetch_assoc()) {
    $template_names[] = $project_template_name['template_name'];
}

// Check if "Development" is in the list of templates
$showButton = in_array("Development", $template_names);
?>
<?php
$users_query_name = $link->query("SELECT username,department_id FROM users WHERE id = '$user_id'");
$users_name = $users_query_name->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Project Details</title>
    <link rel="stylesheet" href="css/project_details.css">

</head>
<script>
function toggleEditForm(subtaskId) {
    const form = document.getElementById('edit-form-' + subtaskId);
    form.classList.toggle('active');
}
</script>
<style>
.Upcase {
    text-transform: uppercase;
}
</style>

<body>
    <div class="container">
        <h1>Project Details</h1>

        <div class="content-wrapper">
            <!-- Left Side: Project Details and Subtasks -->
            <div class="left-side">
                <div class="project-info">
                    <h2>
                        <?php echo htmlspecialchars($project['project_name']); ?>
                    </h2>
                    <p><strong>Priority:</strong>
                        <?php echo htmlspecialchars($project['priority']); ?>
                    </p>
                    <p><strong>Deadline:</strong>
                        <?php echo htmlspecialchars($project['deadline']); ?>
                    </p>


                    <p>
                        <strong>Department: </strong>
                        <?php
                        $manhours = $project['total_manhours'];
                        $project_query = $link->prepare("SELECT * FROM project_templates WHERE default_deadline_days = ?");
                        $project_query->bind_param("i", $manhours);
                        $project_query->execute();
                        $result = $project_query->get_result();
                        $department_name = '';
                        while ($project_template_name = $result->fetch_assoc()) {
                            $template_name = $project_template_name['template_name'];
                            if ($template_name == "Development") {
                                $department_name = $template_name;
                                echo $department_name;
                                break; // Exit loop if "Development" is found
                            }
                        }
                        echo htmlspecialchars($department_name ?: '3D Template');
                        ?>
                    </p>

                    <p>
                        <strong>Total Manhours:</strong>
                        <?php
                        if ($department_name == "Development") {
                            $manhours = $project['total_manhours'];
                            if ($manhours <= 40) {
                                echo htmlspecialchars($manhours);
                            } else {
                                echo "<strong class='text-danger'>Manhours exceed 40. Please review the project requirements.</strong>";
                            }
                        } else {
                            $manhours = $project['total_manhours'];
                            if ($manhours <= 31) {
                                echo htmlspecialchars($manhours);
                            } else {
                                echo "<strong class='text-danger'>Manhours exceed 30. Please review the project requirements.</strong>";
                            }
                        }
                        ?>
                    </p>
                    <p>
                    <p>
                        <strong>Project Timeline: </strong>
                        <button onclick="redirectToProject(<?php echo $project['project_id']; ?>)" class="btn-submit"
                            style="cursor: pointer;">View Timeline</button>
                    </p>

                    <script>
                    // Function to redirect when clicking on the table row
                    function redirectToProject(projectId) {
                        window.location.href = 'project_timeline.php?project_id=' + projectId;
                    }
                    </script>
                    </p>

                </div>

                <div class="subtasks">
                    <div class="subtaskHead">
                        <h3>Subtasks</h3>
                    </div>
                    <div class="table">
                        <form method="post" action="">
                            <style>
                            .completed_status {
                                background-color: #00800069;
                                /* Light gray background for completed tasks */
                                color: black;
                                /* Gray text color */
                                opacity: 0.7;
                            }
                            </style>
                            <table border="1">
                                <thead>
                                    <tr>
                                        <th>Subtask Name</th>
                                        <th class="p-55">Assigned To</th>
                                        <th>Estimated Manhours</th>
                                        <th>Status</th>
                                        <th>SubTask Priority</th>
                                        <th>Description</th>
                                        <th>Action</th>
                                        <?php if ($showButton): ?>
                                        <th>Edit</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($subtask = $subtasks_result->fetch_assoc()): ?>
                                    <?php if ($subtask['status'] == 'Completed'): ?>
                                    <tr class="completed_status">
                                        <td><?php echo htmlspecialchars($subtask['subtask_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['assigned_username'] ? $subtask['assigned_username'] : 'Unassigned'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($subtask['estimated_manhours']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['status']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['priority']); ?></td>
                                        <td><?php echo !empty($subtask['description']) ? htmlspecialchars($subtask['description']) : ''; ?>
                                        </td>
                                        <td>
                                            <input type="hidden"
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][subtask_id]"
                                                value="<?php echo htmlspecialchars($subtask['subtask_id']); ?>">
                                            <?php if ($row = $join_result->fetch_assoc()): ?>
                                            <?php
                                                // Check if start_time and event_time are not empty
                                                if (!empty($row['start_time']) && !empty($row['intervaltime'])) {
                                                    $intervalTime = isset($row['intervaltime']) ? $row['intervaltime'] : '00:00:00';
                                                    $time_parts = explode(":", $intervalTime);
                                                    $hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0;
                                                    $minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
                                                    $seconds = isset($time_parts[2]) ? intval($time_parts[2]) : 0;
                                                    $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;
                                                    $manhours = isset($row['estimated_manhours']) ? $row['estimated_manhours'] : '00:00:00';
                                                    $time_parts = explode(":", $manhours);
                                                    $hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0;
                                                    $minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
                                                    $seconds = isset($time_parts[2]) ? intval($time_parts[2]) : 0;
                                                    $total_manhours = ($hours * 3600) + ($minutes * 60) + $seconds;
                                                    if ($total_manhours >= $total_seconds) {
                                                        echo 'Completed In: ' . $intervalTime . ' hrs';
                                                    } else {
                                                        // Late time calculation
                                                        $late_time = $total_seconds - $total_manhours;
                                                        // $late_time = 200-100;
                                                        $late_hours = floor($late_time / 3600);
                                                        $late_minutes = floor(($late_time % 3600) / 60);
                                                        $late_seconds = $late_time % 60;

                                                        // Output late completed time in a new <td>
                                                        // echo 'Completed In: ' . sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds) . '<br>'; // Output formatted man-hours
                                                        echo 'Late By : ' . sprintf('%02d:%02d:%02d', $late_hours, $late_minutes, $late_seconds) . ' ' .'hrs';
                                                    }
                                                } else {
                                                    echo '<strong></strong> ';
                                                }
                                                ?>
                                            <?php else: ?>
                                            <div><strong>No event data found.</strong></div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($showButton): ?>
                                            <button type="button" class="btn-submit"
                                                onclick="toggleEditForm(<?php echo htmlspecialchars($subtask['subtask_id']); ?>)">Subtask</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php elseif ($subtask['status'] == 'in progress'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subtask['subtask_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['assigned_username'] ? $subtask['assigned_username'] : 'Unassigned'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($subtask['estimated_manhours']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['status']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['priority']); ?></td>

                                        <td><?php echo !empty($subtask['description']) ? htmlspecialchars($subtask['description']) : ''; ?>
                                        </td>
                                        <td>
                                            <?php if($team_leader==1):?>
                                            <input type="hidden"
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][subtask_id]"
                                                value="<?php echo htmlspecialchars($subtask['subtask_id']); ?>">
                                            <select
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][assigned_to]">
                                                <option value="">--Select User--</option>
                                                <?php foreach ($users as $user): ?>
                                                <option value="<?php echo htmlspecialchars($user['id']); ?>"
                                                    <?php echo ($user['id'] == $subtask['assigned_to']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                                <?php endif;?>
                                            </select>

                                        </td>
                                        <td>
                                            <?php if ($showButton): ?>
                                            <button type="button" class="btn-submit"
                                                onclick="toggleEditForm(<?php echo htmlspecialchars($subtask['subtask_id']); ?>)">Subtask</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php elseif ($subtask['status'] == 'paused'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subtask['subtask_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['assigned_username'] ? $subtask['assigned_username'] : 'Unassigned'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($subtask['estimated_manhours']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['status']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['priority']); ?></td>

                                        <td><?php echo !empty($subtask['description']) ? htmlspecialchars($subtask['description']) : ''; ?>
                                        </td>
                                        <td>
                                            <?php if($team_leader==1):?>
                                            <input type="hidden"
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][subtask_id]"
                                                value="<?php echo htmlspecialchars($subtask['subtask_id']); ?>">
                                            <select
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][assigned_to]">
                                                <option value="">--Select User--</option>
                                                <?php foreach ($users as $user): ?>
                                                <option value="<?php echo htmlspecialchars($user['id']); ?>"
                                                    <?php echo ($user['id'] == $subtask['assigned_to']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                                <?php endif;?>

                                            </select>
                                        </td>
                                        <td>
                                            <?php if ($showButton): ?>
                                            <button type="button" class="btn-submit"
                                                onclick="toggleEditForm(<?php echo htmlspecialchars($subtask['subtask_id']); ?>)">Subtask</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php elseif ($subtask['status'] == 'Not Started'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subtask['subtask_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['assigned_username'] ? $subtask['assigned_username'] : 'Unassigned'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($subtask['estimated_manhours']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['status']); ?></td>
                                        <td><?php echo htmlspecialchars($subtask['priority']); ?></td>

                                        <td><?php echo !empty($subtask['description']) ? htmlspecialchars($subtask['description']) : ''; ?>
                                        </td>
                                        <td>
                                            <?php if($team_leader==1):?>
                                            <input type="hidden"
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][subtask_id]"
                                                value="<?php echo htmlspecialchars($subtask['subtask_id']); ?>">
                                            <select
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][assigned_to]">
                                                <option value="">--Select User--</option>
                                                <?php foreach ($users as $user): ?>

                                                <option value="<?php echo htmlspecialchars($user['id']); ?>"
                                                    <?php echo ($user['id'] == $subtask['assigned_to']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="date"
                                                name="subtasks[<?php echo htmlspecialchars($subtask['subtask_id']); ?>][subtask_deadline]"
                                                value="<?php echo htmlspecialchars($subtask['deadline']); ?>"
                                                min="<?php echo $min_date; ?>" max="<?php echo $max_date; ?>">
                                            <?php endif;?>
                                        </td>
                                        <td>
                                            <?php if ($showButton): ?>
                                            <button type="button" class="btn-submit"
                                                onclick="toggleEditForm(<?php echo htmlspecialchars($subtask['subtask_id']); ?>)">Subtask</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php endif; ?>
                                    <tr id="edit-form-<?php echo htmlspecialchars($subtask['subtask_id']); ?>"
                                        class="edit-form">
                                        <td colspan="7">
                                            <h3>Update Subtask Name</h3>
                                            <form method="post" action="">
                                                <input type="hidden" name="subtask_id"
                                                    value="<?php echo htmlspecialchars($subtask['subtask_id']); ?>">
                                                <div class="form-group">
                                                    <label for="subtask_name">New Subtask Name:</label>
                                                    <input type="text" id="subtask_name" name="subtask_name"
                                                        value="<?php echo htmlspecialchars($subtask['subtask_name']); ?>"
                                                        required>
                                                </div>
                                                <button type="submit" name="update_subtask"
                                                    class="btn-submit">Update</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>

                                </tbody>
                                <div class="button-td">
                                    <div colspan="7">
                                        <?php if($team_leader==1):?>
                                        <button type="submit" name="assign_task" class="btn-submit">Assign</button>
                                        <?php endif;?>
                                    </div>
                                </div>
                            </table>
                        </form>
                    </div>
                    <p class="message">
                        <?php echo htmlspecialchars($message); ?>
                    </p>

                </div>
                <?php
                // echo "Today's date: $min_date\n".'<br>';
                // echo "Date 3 days later: $max_date\n".'<br>';
                // echo "Date 5 days later: $max_date1\n".'<br>';
                ?>
                <!-- Add Subtask Form -->
                <div class="add">
                    <div class="add-subtask">
                        <div class="create-subtasks">
                            <h3>Create a Subtask</h3>
                        </div>
                        <form method="post" action="">
                            <table>

                                <tr>
                                    <td><label for="subtask_name">Subtask Name</label></td>
                                    <td><input type="text" placeholder="Please Enter Subtask Name" id="subtask_name"
                                            name="subtask_name" required></td>
                                </tr>
                                <tr>
                                    <td><label for="subtask_deadline">Deadline</label></td>
                                    <td>
                                        <input type="date" id="subtask_deadline" name="subtask_deadline"
                                            max="<?php echo $max_date; ?>" value="<?php echo date('Y-m-d'); ?>"
                                            required>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="priority">Subtask of Priority:</label></td>
                                    <td>
                                        <select id="priority" name="priority" required>
                                            <option value="Urgent">Urgent</option>
                                            <option value="High">High</option>
                                            <option value="Medium">Medium</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label for="description">Description</label>
                                    </td>
                                    <td>
                                        <textarea id="description" name="description" rows="5" required></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="estimated_manhours">Estimated Manhours</label></td>
                                    <td><input type="number" placeholder="Est. Manhours (Max-30)"
                                            id="estimated_manhours" name="estimated_manhours" required></td>
                                </tr>
                                <tr>
                                    <?php if($team_leader == 1):?>
                                    <td colspan="2">
                                        <button type="submit" name="create_subtask" class="btn-submit">Create
                                            Subtask</button>
                                    </td>
                                    <?php endif;?>
                                </tr>
                            </table>
                        </form>
                        <p class="message">
                            <?php echo $message_manhours ?>
                        </p>

                    </div>
                    <div class="add-comment">
                        <h3>Add a Comment</h3>
                        <form method="post" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="comment_text">Comment:</label>
                                <textarea id="comment_text" name="comment_text" required></textarea>
                            </div>
                            <div class="form-group">
                                <input type="hidden" value="<?= htmlspecialchars($users_name['username']) ?>"
                                    name="comment_username" required>
                            </div>
                            <div class="form-group">
                                <label for="file_upload">Upload File:</label>
                                <input type="file" id="file_upload" name="file_upload">
                            </div>
                            <button type="submit" name="add_comment" class="btn-submit">Add Comment</button>
                        </form>
                        <p class="message">
                            <?php echo isset($message) ? $message : ''; ?>
                        </p>
                    </div>

                </div>

                <script>
                function toggleEditForm(commentId) {
                    var form = document.getElementById('edit-form-' + commentId);
                    form.style.display = form.style.display === 'block' ? 'none' : 'block';
                }
                </script>

                <div class="comments">
                    <div class="commentt">
                        <h3>Comments</h3>
                    </div>
                    <div class="boom">
                        <?php while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="comment">
                            <p><strong>User Name :</strong>
                                <span class="Upcase"><?= htmlspecialchars($comment['comment_username']) ?></span>
                            </p>
                            <p><strong>Project Name :</strong>
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </p>
                            <p><strong>Comments :</strong>
                                <?php echo htmlspecialchars($comment['comment_text']); ?>
                            </p>

                            <!-- Edit Comment Section -->
                            <p>
                                <Button class="btn btn-blue"><a href="javascript:void(0)" class="edit"
                                        onclick="toggleEditForm(<?php echo $comment['comment_id']; ?>)">Edit</a></Button>

                                <button class="btn-danger"><a href="javascript:void(0)" class="edit"
                                        onclick="toggleDeleteForm(<?php echo $comment['comment_id']; ?>)">Delete</a></button>
                            </p>

                            <!-- Delete form (hidden initially) -->
                            <form id="delete-form-<?php echo $comment['comment_id']; ?>" method="post" action=""
                                style="display: none;">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                <button type="submit" name="delete_comment" class="btn-submit">Confirm Delete</button>
                            </form>

                            <script>
                            function toggleDeleteForm(commentId) {
                                var form = document.getElementById('delete-form-' + commentId);
                                form.style.display = form.style.display === 'block' ? 'none' : 'block';
                            }
                            </script>

                            <div id="edit-form-<?php echo $comment['comment_id']; ?>" class="edit-form">
                                <h3>Update a Comment</h3>
                                <form method="post" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="comment_id"
                                        value="<?php echo htmlspecialchars($comment['comment_id']); ?>">
                                    <div class="form-group">
                                        <label for="comment_text">Comment:</label> <br>
                                        <textarea id="comment_text" name="comment_text" col="10" row="10"
                                            required><?php echo htmlspecialchars($comment['comment_text']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="file_upload">Upload File:</label>
                                        <input type="file" id="file_upload" name="file_upload">
                                    </div>
                                    <button type="submit" name="update_comment" class="btn-submit">Update
                                        Comment</button>
                                </form>
                            </div>

                            <!-- File Display Section -->
                            <?php if (!empty($comment['file_path'])): ?>
                            <?php $file_ext = strtolower(pathinfo($comment['file_path'], PATHINFO_EXTENSION)); ?>

                            <span><strong>File:</strong></span>
                            <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank"
                                download>Download Image</a>
                            <br>
                            <img width="350px" height="350px"
                                src="<?php echo htmlspecialchars($comment['file_path']); ?>" alt="Comment Image"
                                style="max-width: 100%; height: auto;">

                            <?php elseif ($file_ext == 'pdf'): ?>
                            <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank">View
                                PDF</a> |
                            <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" download>Download PDF</a>
                            <br>
                            <embed width="350px" height="300px"
                                src="<?php echo htmlspecialchars($comment['file_path']); ?>" type="application/pdf"
                                style="max-width: 100%;">

                            <?php elseif ($file_ext == 'mp4'): ?>
                            <video width="350" height="240" controls>
                                <source src="<?php echo htmlspecialchars($comment['file_path']); ?>" type="video/mp4">
                            </video>

                            <?php else: ?>
                            <a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank">View
                                File</a>

                            <?php endif; ?>
                            <?php endif; ?>

                            <hr>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Add Comment Form -->

            </div>


        </div>
    </div>

    </div>
</body>

</html>