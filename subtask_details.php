<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Initialize variables
$subtask_id = isset($_GET['subtask_id']) ? intval($_GET['subtask_id']) : 0;
$user_id = 1; // Replace with session user ID when integrating authentication
$message = '';

// Fetch subtask details
$subtask_query = $link->prepare("SELECT * FROM subtasks WHERE subtask_id = ?");
$subtask_query->bind_param("i", $subtask_id);
$subtask_query->execute();
$subtask = $subtask_query->get_result()->fetch_assoc();

// Fetch time tracking entries for the subtask
$time_tracking_query = $link->prepare("SELECT * FROM time_tracking WHERE subtask_id = ? AND user_id = ?");
$time_tracking_query->bind_param("ii", $subtask_id, $user_id);
$time_tracking_query->execute();
$time_tracking_result = $time_tracking_query->get_result();

// Start Time Tracking
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['start_tracking'])) {
    $start_time = date('Y-m-d H:i:s');
    $stmt = $link->prepare("INSERT INTO time_tracking (subtask_id, user_id, start_time) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $subtask_id, $user_id, $start_time);

    if ($stmt->execute()) {
        $message = "Time tracking started!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Stop Time Tracking
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['stop_tracking'])) {
    $tracking_id = $_POST['tracking_id'];
    $end_time = date('Y-m-d H:i:s');
    $stmt = $link->prepare("UPDATE time_tracking SET end_time = ?, duration = TIMESTAMPDIFF(MINUTE, start_time, end_time) WHERE tracking_id = ?");
    $stmt->bind_param("si", $end_time, $tracking_id);

    if ($stmt->execute()) {
        $message = "Time tracking stopped!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Complete Subtask
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['complete_subtask'])) {
    $note_text = $_POST['note_text'];
    $file_path = $_FILES['file_upload']['name'] ? 'uploads/' . basename($_FILES['file_upload']['name']) : null;

    // Handle file upload
    if ($file_path && !move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path)) {
        $message = "Failed to upload file.";
    } else {
        // Update subtask status
        $stmt = $link->prepare("UPDATE subtasks SET status = 'Completed' WHERE subtask_id = ?");
        $stmt->bind_param("i", $subtask_id);

        if ($stmt->execute()) {
            // Fetch project_id associated with the subtask
            $project_query = $link->prepare("SELECT project_id FROM subtasks WHERE subtask_id = ?");
            $project_query->bind_param("i", $subtask_id);
            $project_query->execute();
            $project_id = $project_query->get_result()->fetch_assoc()['project_id'];

            // Insert into timeline
            $event_title = 'Subtask Completed';
            $event_description = "Subtask $subtask_id completed with note: $note_text";
            $event_time = date('Y-m-d H:i:s');
            $stmt_timeline = $link->prepare("INSERT INTO timeline (project_id, user_id, event_title, event_description, event_time, file_image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_timeline->bind_param("iissss", $project_id, $user_id, $event_title, $event_description, $event_time, $file_path);

            if ($stmt_timeline->execute()) {
                $message = "Subtask marked as completed and event added to timeline!";
                header("Location: project_dashboard.php");
                exit();
            } else {
                $message = "Error: " . $stmt_timeline->error;
            }
            $stmt_timeline->close();
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Comment or File Upload During the Task
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['upload_comment'])) {
    $comment_text = $_POST['comment_text'];
    $file_path = $_FILES['file_upload_mid_task']['name'] ? 'uploads/' . basename($_FILES['file_upload_mid_task']['name']) : null;

    // Handle file upload
    if ($file_path && !move_uploaded_file($_FILES['file_upload_mid_task']['tmp_name'], $file_path)) {
        $message = "Failed to upload file.";
    } else {
        // Insert comment and file path into notes table
        $stmt = $link->prepare("INSERT INTO notes (subtask_id, note_text, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $subtask_id, $comment_text, $file_path);

        if ($stmt->execute()) {
            $message = "Comment and/or file uploaded successfully!";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subtask Details</title>
    <link rel="stylesheet" href="css/subtask_details.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <style>
        .hidden { display: none; }
    </style>
    <script>
        $(document).ready(function() {
            $('#toggleTimeTracking').on('click', function() {
                $('.hidden').toggle();
            });
        });
    </script>
</head>

<body>
    <div class="container">
        <h1>Subtask Details</h1>

        <div class="message"><?php echo htmlspecialchars($message); ?></div>

        <!-- Subtask Details -->
        <div class="subtask-info">
            <h2><?php echo htmlspecialchars($subtask['subtask_name']); ?></h2>
            <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($subtask['assigned_to']); ?></p>
            <p><strong>Deadline:</strong> <?php echo htmlspecialchars($subtask['deadline']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($subtask['status']); ?></p>
        </div>

        <!-- Time Tracking -->
        <div class="time-tracking">
            <h3>Time Tracking</h3>
            <form method="post" action="">
                <?php if ($subtask['status'] != 'Completed'): ?>
                    <button type="submit" name="start_tracking" class="btn btn-primary">Start Tracking</button>
                <?php endif; ?>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th><button id="toggleTimeTracking" class="btn btn-primary">Start Time</button></th>
                         
                    </tr>
                </thead>
                <tbody>
                    <?php while ($tracking = $time_tracking_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tracking['start_time']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Form to Upload Comments or Files -->
        <!-- <?php if ($subtask['status'] != ''): ?> -->
            <div class="upload-comments ">
                <h3>Upload Comments or Files</h3>
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="comment_text">Comment:</label>
                        <textarea id="comment_text" name="comment_text" rows="4" class="form-control"></textarea>
                    </div>
                    <div class="form-group mt-2">
                        <label for="file_upload_mid_task">Upload File:</label>
                        <input type="file" id="file_upload_mid_task" name="file_upload_mid_task" class="form-control">
                    </div>
                    <button type="submit" name="upload_comment" class="btn btn-primary mt-2">Upload</button>
                </form>
            </div>
        <!-- <?php endif; ?> -->

        <!-- Complete Subtask Modal -->
        <div class="modal fade" id="completeSubtaskModal" tabindex="-1" aria-labelledby="completeSubtaskModalLabel" aria-hidden="true">
            <div class="modal-dialog hidden">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="completeSubtaskModalLabel">Complete Subtask</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="note_text">Note:</label>
                                <textarea id="note_text" name="note_text" rows="4" class="form-control" required></textarea>
                            </div>
                            <div class="form-group mt-2">
                                <label for="file_upload">Upload Screenshot:</label>
                                <input type="file" id="file_upload" name="file_upload" class="form-control" accept="image/*">
                            </div>
                            <button type="submit" name="complete_subtask" class="btn btn-success mt-2">Complete Subtask</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trigger Complete Subtask Modal -->
    <?php if ($subtask['status'] != 'Completed'): ?>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeSubtaskModal">
            Complete Subtask
        </button>
    <?php endif; ?>
</body>

</html>
