<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Get the project ID from the URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Fetch project details
$project_query = $link->prepare("SELECT * FROM projects WHERE project_id = ?");
$project_query->bind_param("i", $project_id);
$project_query->execute();
$project = $project_query->get_result()->fetch_assoc();

// Fetch subtasks
$subtasks_query = $link->prepare("SELECT * FROM subtasks WHERE project_id = ?");
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_comment'])) {
    $comment_text = $_POST['comment_text'];
    $file_path = $_FILES['file_upload']['name'] ? 'uploads/' . basename($_FILES['file_upload']['name']) : null;

    // Handle file upload
    if ($file_path && !move_uploaded_file($_FILES['file_upload']['tmp_name'], $file_path)) {
        $message = "Failed to upload file.";
    }

    // Insert comment into the database
    $stmt = $link->prepare("INSERT INTO comments (project_id, user_id, comment_text, file_path) VALUES (?, ?, ?, ?)");
    $user_id = 1; // Replace with the session user ID when integrating authentication
    $stmt->bind_param("iiss", $project_id, $user_id, $comment_text, $file_path);

    if ($stmt->execute()) {
        $message = "Comment added successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Handle task assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_task'])) {
    $subtask_id = $_POST['subtask_id'];
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['subtask_deadline'];

    // Update the subtask with assigned user and deadline
    $stmt = $link->prepare("UPDATE subtasks SET assigned_to = ?, deadline = ? WHERE subtask_id = ?");
    $stmt->bind_param("isi", $assigned_to, $deadline, $subtask_id);

    if ($stmt->execute()) {
        $message = "Task assigned successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Project Details</title>
    <link rel="stylesheet" href="css/project_details.css">
</head>

<body>
    <div class="container">
        <h1>Project Details</h1>

        <div class="content-wrapper">
            <!-- Left Side: Project Details and Subtasks -->
            <div class="left-side">
                <div class="project-info">
                    <h2><?php echo htmlspecialchars($project['project_name']); ?></h2>
                    <p><strong>Deadline:</strong> <?php echo htmlspecialchars($project['deadline']); ?></p>
                    <p><strong>Total Manhours:</strong> <?php echo htmlspecialchars($project['total_manhours']); ?></p>
                </div>

                <div class="subtasks">
                    <h3>Subtasks</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Subtask Name</th>
                                <th>Assigned To</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subtask = $subtasks_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subtask['subtask_name']); ?></td>
                                    <td><?php echo htmlspecialchars($subtask['assigned_to'] ? "User " . htmlspecialchars($subtask['assigned_to']) : 'Unassigned'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($subtask['deadline']); ?></td>
                                    <td><?php echo htmlspecialchars($subtask['status']); ?></td>
                                    <td>
                                        <form method="post" action="">
                                            <input type="hidden" name="subtask_id"
                                                value="<?php echo htmlspecialchars($subtask['subtask_id']); ?>">
                                            <select name="assigned_to">
                                                <option value="">--Select User--</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <input type="date" name="subtask_deadline"
                                                value="<?php echo htmlspecialchars($subtask['deadline']);  ?> required">
                                            <button type="submit" name="assign_task" class="btn-submit">Assign</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="comments">
                    <h3>Comments</h3>
                    <?php while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="comment">
                            <p><strong>User <?php echo htmlspecialchars($comment['user_id']); ?>:</strong>
                                <?php echo htmlspecialchars($comment['comment_text']); ?></p>
                            <?php if ($comment['file_path']): ?>
                                <?php $file_ext = pathinfo($comment['file_path'], PATHINFO_EXTENSION); ?>
                                <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <!-- Display images directly -->
                                    <p><img src="<?php echo htmlspecialchars($comment['file_path']); ?>" alt="Comment File"
                                            style="max-width: 100%; height: auto;"></p>
                                <?php elseif ($file_ext == 'pdf'): ?>
                                    <!-- Embed PDF directly -->
                                    <p><embed src="<?php echo htmlspecialchars($comment['file_path']); ?>" type="application/pdf"
                                            width="600" height="800"></p>
                                <?php else: ?>
                                    <!-- Provide link for other file types -->
                                    <p><a href="<?php echo htmlspecialchars($comment['file_path']); ?>" target="_blank">View
                                            File</a></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>


                <!-- Add Comment Form -->
                <div class="add-comment">
                    <h3>Add a Comment</h3>
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="comment_text">Comment:</label>
                            <textarea id="comment_text" name="comment_text" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="file_upload">Upload File:</label>
                            <input type="file" id="file_upload" name="file_upload">
                        </div>
                        <button type="submit" name="add_comment" class="btn-submit">Add Comment</button>
                    </form>
                    <p class="message"><?php echo $message; ?></p>
                </div>
            </div>

            <!-- Right Side: Timeline -->
            <div class="right-side">
                <div class="timeline">
                    <h3>Project Timeline</h3>
                    <?php while ($event = $timeline_result->fetch_assoc()): ?>
                        <div class="timeline-event">
                            <p><strong><?php echo htmlspecialchars($event['event_title']); ?>:</strong> 
                                <?php echo htmlspecialchars($event['event_description']); ?></p>
                            <p><em><?php echo htmlspecialchars($event['event_time']); ?></em></p>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>