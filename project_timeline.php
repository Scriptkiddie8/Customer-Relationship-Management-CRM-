<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$subtasks_query = $link->prepare("SELECT subtasks.*, 
           users.username AS assigned_username 
    FROM subtasks 
    LEFT JOIN users 
    ON subtasks.assigned_to = users.id 
    WHERE subtasks.project_id = ? ");
$subtasks_query->bind_param("i", $project_id);
$subtasks_query->execute();
$subtasks_result = $subtasks_query->get_result();

// Fetch users for assigning tasks
$users_query = $link->query("SELECT id, username FROM users WHERE role = 'User'");
$users = $users_query->fetch_all(MYSQLI_ASSOC);

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_subtask'])) {
    // Fetch input values from the form
    $subtask_name = $_POST['subtask_name'];
    $deadline = $_POST['subtask_deadline'];
    $description = $_POST['description'];
    $estimated_manhours = $_POST['estimated_manhours'];

    // Validate inputs
    if (empty($subtask_name) || empty($deadline) || empty($estimated_manhours)) {
        $message = 'All fields are required.';
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

        if ($new_total_manhours > 30) {
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
                    $stmt = $link->prepare("INSERT INTO subtasks (project_id, subtask_name, deadline, description, estimated_manhours, status) VALUES (?, ?, ?, ?, ?, 'Not Started')");

                    // Check if the statement was prepared correctly
                    if ($stmt === false) {
                        throw new Exception("Prepare failed: " . $link->error);
                    }

                    // Bind parameters and execute the statement
                    $stmt->bind_param("isssi", $project_id, $subtask_name, $deadline, $description, $estimated_manhours);

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
        $file_path = $_FILES['file_upload']['name'] ? 'uploads/' . basename($_FILES['file_upload']['name']) : null;

        // Handle file upload
        if ($file_path && !move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path)) {
            $message = "Failed to upload file.";
        } else {
            try {
                // Insert comment into the database
                $stmt = $link->prepare("INSERT INTO comments (project_id, user_id, comment_text, file_path) VALUES (?, ?, ?, ?)");
                $user_id = 1; // Replace with session user ID
                $stmt->bind_param("iiss", $project_id, $user_id, $comment_text, $file_path);

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
        $assigned_to = $subtask_data['assigned_to'];
        $deadline = $subtask_data['subtask_deadline'];
        // Validate deadline
        $today = new DateTime();
        $deadlineDate = new DateTime($deadline);
        $threeDayLimit = (clone $today)->modify('+2 weekday');
        $fiveDayLimit = (clone $today)->modify('+4 weekday');

        if ($deadlineDate <= $fiveDayLimit) {
            if ($deadlineDate <= $threeDayLimit) {
                $message = 'This task must be completed within 3 working days!';
            } else {
                $message = 'This task must be completed within 5 working days!';
            }
        } else {
            $message = 'Warning: Deadline exceeds the 5-day working limit!';
        }
        if (strpos($message, 'Warning') === false) {
            try {
                // Update the subtask with assigned user and deadline
                $stmt = $link->prepare("UPDATE subtasks SET assigned_to = ?, deadline = ? WHERE subtask_id = ?");
                $stmt->bind_param("isi", $assigned_to, $deadline, $subtask_id);

                if ($stmt->execute()) {
                    // Success message
                    $message = "Task assigned successfully!";
                    // Ensure no output before this line
                    header('Location: project_details.php?project_id=' . $project_id);
                } else {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
            } catch (Exception $e) {
                // Display error message
                $message = "Error: " . $e->getMessage();
                error_log($e->getMessage(), 3, "errors.log"); // Log errors to server
            } finally {
                if (isset($stmt)) {
                    $stmt->close();
                }
            }
        }
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
        subtasks.description,
        subtasks.estimated_manhours,
        subtasks.status,
        subtasks.assigned_to,
        timeline.event_id,
        timeline.event_title,
        timeline.event_description,
        timeline.start_time,
        timeline.event_time,
        timeline.paused_time,
        timeline.paused_after,
        timeline.file_image,
        timeline.paused_current_time,
        timeline.paused_counting,
        timeline.pause_resume_time,
        timeline.resume_time,
        timeline.resumesecond,
        timeline.reason_late_text,
        timeline.intervaltime
    FROM subtasks
    LEFT JOIN timeline ON subtasks.subtask_id = timeline.pro_sub_id
    WHERE subtasks.project_id = ?
    ORDER BY 
        FIELD(subtasks.status, 'Completed', 'pause', 'Not Started' , 'in progress'),
        FIELD(timeline.event_title, 'Subtask Completed', 'Subtask Paused', '')
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
// Handle subtask name update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_subtask'])) {
    // Fetch input values from the form
    $subtask_id = $_POST['subtask_id'];
    $new_subtask_name = $_POST['subtask_name'];

    // Validate inputs
    if (empty($subtask_id) || empty($new_subtask_name)) {
        $message = 'Subtask ID and name are required.';
    } else {
        try {
            // Prepare the SQL statement to update the subtask name
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


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Project Details</title>
    <link rel="stylesheet" href="css/project_timeline.css">
</head>
<script>
function toggleEditForm(subtaskId) {
    const form = document.getElementById('edit-form-' + subtaskId);
    form.classList.toggle('active');
}
</script>

<body>
    <div class="container">
        <div class="timeline1">
            <h1>Project Timeline</h1>
        </div>

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
                            if ($manhours <= 30) {
                                echo htmlspecialchars($manhours);
                            } else {
                                echo "<strong class='text-danger'>Manhours exceed 30. Please review the project requirements.</strong>";
                            }
                        }
                        ?>
                    </p>
                    <p>
                        <strong>Project : </strong>
                        <button onclick="redirectToProject(<?php echo $project['project_id']; ?>)"
                            style="cursor: pointer;">Project Details</button>
                        <script>
                        // Function to redirect when clicking on the table row
                        function redirectToProject(projectId) {
                            window.location.href = 'project_details.php?project_id=' + projectId;
                        }
                        </script>
                    </p>
                </div>
                <div class="tab" onclick="openTab(event, 'tab1')"> <button>Subtasks Not Started</button> </div>
                <div class="tab" onclick="openTab(event, 'tab2')"> <button>Subtasks Completed</button></div>
                <div class="tab" onclick="openTab(event, 'tab3')"><button>Subtasks Paused And in progress</button></div>
                <!-- <div class="right-side"> -->
                <div id="tab1" class="tab-content">
                    <div class="tb">

                        <h1>Subtasks Not Started</h1>
                        <div class="timeline">
                            <table border="1" cellpadding="10">
                                <thead>
                                    <tr>
                                        <th class="text-primary">Project Name</th>
                                        <th>Subtask Name</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                        <th>Manhours</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $success = 'text-success';
                                    $primary = 'text-primary';
                                    $danger  = 'text-danger';
                                    $black = 'black';

                                    // Fetch user data in one query to avoid multiple queries inside the loop
                                    $user_data = [];
                                    $user_result = $link->query("SELECT id, username FROM users");
                                    while ($user_row = $user_result->fetch_assoc()) {
                                        $user_data[$user_row['id']] = htmlspecialchars($user_row['username']);
                                    }

                                    // Display subtasks that are not started
                                    $join_result->data_seek(0); // Reset the pointer to the beginning of the result set
                                    while ($row = $join_result->fetch_assoc()):
                                        if ($row['event_title'] == '' || $row['status'] == 'Not Started'  || $row['status'] == 'not started'): ?>
                                    <tr>
                                        <td class="text-primary"><?= htmlspecialchars($project['project_name']); ?></td>
                                        <td><?= !empty($row['subtask_name']) ? htmlspecialchars($row['subtask_name']) : ' '; ?>
                                        </td>
                                        <td>
                                            <?= !empty($row['assigned_to']) ? ($user_data[$row['assigned_to']] ?? ' (No user found)') : ' '; ?>
                                        </td>
                                        <td>
                                            <?php
                                                    $status = strtolower($row['status']);
                                                    if ($status == 'not started' || $status == 'Not Started') {
                                                        echo "<span class='$primary'>$status</span>";
                                                    } elseif ($status == 'in progress' || $status == 'pending' || $status == 'paused') {
                                                        echo "<span class='$danger'>$status</span>";
                                                    } elseif ($status == 'completed' || $status == 'finish' || $status == 'Completed') {
                                                        echo "<span class='$success'>$status</span>";
                                                    } else {
                                                        echo "<span class='$black'>$status</span>";
                                                    }
                                                    ?>
                                        </td>
                                        <td><?= !empty($row['description']) ? htmlspecialchars($row['description']) : ' '; ?>
                                        </td>
                                        <td>
                                            <?php
                                                    if (!empty($row['estimated_manhours'])) {
                                                        $manhours = (float) htmlspecialchars($row['estimated_manhours']);
                                                        $total_seconds = $manhours * 3600;
                                                        $hrs = floor($total_seconds / 3600);
                                                        $min = floor(($total_seconds % 3600) / 60);
                                                        $sec = $total_seconds % 60;
                                                        $formatted_time = sprintf('%02d:%02d:%02d ', $hrs, $min, $sec);
                                                        echo $formatted_time;
                                                    }
                                                    ?>
                                        </td>
                                    </tr>
                                    <?php endif;
                                    endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab2" class="tab-content">
                    <div class="tb">
                        <div class="timeline">
                            <h1>Subtasks Completed</h1>
                            <table border="1" cellpadding="10">
                                <thead>
                                    <tr>
                                        <th>Subtask Name</th>
                                        <th>Assigned To</th>
                                        <th>Manhours</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                        <th>Late Reason</th>
                                        <th>Note</th>
                                        <th>File Image</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Time Taken</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Display subtasks that are completed
                                    $join_result->data_seek(0); // Reset the pointer to the beginning of the result set
                                    while ($row = $join_result->fetch_assoc()):
                                        if ($row['event_title'] == 'Subtask Completed' || $row['status'] == 'Completed'): ?>
                                    <tr>
                                        <td><?= !empty($row['subtask_name']) ? htmlspecialchars($row['subtask_name']) : ''; ?>
                                        </td>
                                        <td>
                                            <?= !empty($row['assigned_to']) ? ($user_data[$row['assigned_to']] ?? ' (No user found)') : ''; ?>
                                        </td>
                                        <td>
                                            <?php
                                                    if (!empty($row['estimated_manhours'])) {
                                                        $manhours = (float) htmlspecialchars($row['estimated_manhours']);
                                                        $total_seconds = $manhours * 3600;
                                                        $hrs = floor($total_seconds / 3600);
                                                        $min = floor(($total_seconds % 3600) / 60);
                                                        $sec = $total_seconds % 60;
                                                        $formatted_time = sprintf('%02d:%02d:%02d ', $hrs, $min, $sec);
                                                        echo $formatted_time;
                                                    }
                                                    ?>
                                        </td>
                                        <td>
                                            <?php
                                                    $status = strtolower($row['status']);
                                                    if ($status == 'not started') {
                                                        echo "<span class='$primary'>$status</span>";
                                                    } elseif ($status == 'in progress' || $status == 'pending') {
                                                        echo "<span class='$danger'>$status</span>";
                                                    } elseif ($status == 'completed' || $status == 'finish' || $status == 'complete') {
                                                        echo "<span class='$success'>$status</span>";
                                                    } else {
                                                        echo "<span class='$black'>$status</span>";
                                                    }
                                                    ?>
                                        </td>


                                        <td><?= !empty($row['description']) ? htmlspecialchars($row['description']) : ''; ?>
                                        </td>
                                        <td><?= !empty($row['reason_late_text']) ? htmlspecialchars($row['reason_late_text']) : ''; ?>
                                        </td>
                                        <td><?= !empty($row['event_description']) ? htmlspecialchars($row['event_description']) : ''; ?>
                                        </td>

                                        <td>

                                            <?php if (!empty($row['file_image'])): ?>
                                            <?php 
                                            // Split file_image string into an array
                                            $file_paths = explode(',', $row['file_image']); 
                                            $image_displayed = false; // Flag to check if an image is displayed

                                            foreach ($file_paths as $file_path):
                                                $file_path = trim($file_path); // Clean up the file path
                                                $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION)); 

                                                // Check if the file is an image
                                                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): 
                                                    // Display the first valid image and break the loop
                                                    ?>
                                            <a href="<?= htmlspecialchars($file_path); ?>">
                                                <img class="image_show" src="<?= htmlspecialchars($file_path); ?>"
                                                    alt="Found Image">
                                            </a>
                                            <?php
                                                    $image_displayed = true; // Set flag if an image is displayed
                                                    break; // Exit the loop after displaying the first image
                                                endif;
                                            endforeach; ?>

                                            <?php if (!$image_displayed): ?>
                                            <p>No Image found</p>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <p>No File Available</p>
                                            <?php endif; ?>
                                            <p>
                                                <a
                                                    href="images_show.php?project_id=<?= htmlspecialchars($project['project_id']); ?>&subtask_id=<?= htmlspecialchars($row['subtask_id']); ?>">More
                                                    Images
                                                    Show</a>
                                            </p>
                                        </td>

                                        <td><?= !empty($row['start_time']) ? htmlspecialchars($row['start_time']) : ''; ?>
                                        </td>
                                        <td><?= !empty($row['event_time']) ? htmlspecialchars($row['event_time']) : ''; ?>
                                        </td>
                                        <td>
                                            <input type="hidden"
                                                name="subtasks[<?php echo htmlspecialchars($row['subtask_id']); ?>][subtask_id]"
                                                value="<?php echo htmlspecialchars($row['subtask_id']); ?>">
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
                                                        $late_time = 200-100;
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
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                    endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="tab3" class="tab-content">
                    <div class="tb">
                        <div class="timeline">
                            <h1>Subtasks Paused And In progress</h1>
                            <table border="1" cellpadding="10">
                                <thead>
                                    <tr>
                                        <th class="text-primary">Project Name</th>
                                        <th>Subtask Name</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Manhours</th>
                                        <th>Description</th>
                                        <th>Start Time</th>
                                        <th>Paused Time</th>
                                        <th>Subtask Progress</th>
                                        <th>Total Paused</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                $join_result->data_seek(0); // Reset the pointer to the beginning of the result set
                                while ($row = $join_result->fetch_assoc()):
                                    $eventTitle = strtolower($row['event_title'] ?? '');
                                    $status = strtolower($row['status'] ?? '');
                                    $isPaused = in_array($status, ['paused', 'in progress']) || 
                                                $eventTitle === 'subtask paused';
                                    if ($isPaused): ?>
                                    <tr>
                                        <td class="text-primary"><?= htmlspecialchars($project['project_name']); ?></td>
                                        <td><?= !empty($row['subtask_name']) ? htmlspecialchars($row['subtask_name']) : ''; ?>
                                        </td>
                                        <td><?= !empty($row['assigned_to']) ? ($user_data[$row['assigned_to']] ?? '(No user found)') : ''; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($status === 'not started') {
                                                echo "<span class='text-success'>$status</span>";
                                            } elseif (in_array($status, ['in progress', 'pending', 'pause', 'completed'])) {
                                                echo "<span class='text-danger'>In Progress</span>";
                                            } else {
                                                echo "<span class='text-muted'>$status</span>";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($row['estimated_manhours'])) {
                                                $manhours = (float) htmlspecialchars($row['estimated_manhours']);
                                                $total_seconds = $manhours * 3600;
                                                $formatted_time = sprintf('%02d:%02d:%02d', 
                                                    floor($total_seconds / 3600), 
                                                    floor(($total_seconds % 3600) / 60), 
                                                    $total_seconds % 60);
                                                echo $formatted_time;
                                            }
                                            ?>
                                        </td>
                                        <td><?= !empty($row['description']) ? htmlspecialchars($row['description']) : ''; ?>
                                        </td>
                                        <td><?= !empty($row['start_time']) ? htmlspecialchars($row['start_time']) : ''; ?>
                                        </td>
                                        <td><?= !empty($row['paused_current_time']) ? htmlspecialchars($row['paused_current_time']) : ''; ?>
                                        </td>
                                        <td>
                                            <input type="hidden"
                                                name="subtasks[<?= htmlspecialchars($row['subtask_id']); ?>][subtask_id]"
                                                value="<?= htmlspecialchars($row['subtask_id']); ?>">
                                            <?php
                                            if (!empty($row['start_time']) && !empty($row['intervaltime'])) {
                                                // Calculate total seconds from interval time
                                                $intervalTime = isset($row['intervaltime']) ? $row['intervaltime'] : '00:00:00';
                                                $time_parts = explode(":", $intervalTime);
                                                $hours = $time_parts[0] ?? 0;
                                                $minutes = $time_parts[1] ?? 0;
                                                $seconds = $time_parts[2] ?? 0;
                                                $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;

                                                // Calculate estimated manhours
                                                $manhours = isset($row['estimated_manhours']) ? $row['estimated_manhours'] : '00:00:00';
                                                $manhour_parts = explode(":", $manhours);
                                                $manhours_hours = $manhour_parts[0] ?? 0;
                                                $manhours_minutes = $manhour_parts[1] ?? 0;
                                                $manhours_seconds = $manhour_parts[2] ?? 0;
                                                $total_manhours = ($manhours_hours * 3600) + ($manhours_minutes * 60) + $manhours_seconds;

                                                // Determine completion status
                                                if ($total_manhours >= $total_seconds) {
                                                    echo 'Progress In: ' . $intervalTime . ' hrs';
                                                } else {
                                                    $late_time = $total_seconds - $total_manhours;
                                                    $late_hours = floor($late_time / 3600);
                                                    $late_minutes = floor(($late_time % 3600) / 60);
                                                    $late_seconds = $late_time % 60;
                                                    echo 'Progress Late By : ' . sprintf('%02d:%02d:%02d', $late_hours, $late_minutes, $late_seconds) . ' hrs';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td><?= !empty($row['pause_resume_time']) ? htmlspecialchars($row['pause_resume_time']) : ''; ?>
                                        </td>
                                    </tr>
                                    <?php endif;  endwhile; ?>
                                </tbody>

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
function openTab(evt, tabName) {
    let tabContent = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContent.length; i++) {
        tabContent[i].style.display = "none";
    }
    let tabs = document.getElementsByClassName("tab");
    for (let i = 0; i < tabs.length; i++) {
        tabs[i].classList.remove("active");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.classList.add("active");
}
document.getElementsByClassName("tab")[0].click();
</script>

</html>