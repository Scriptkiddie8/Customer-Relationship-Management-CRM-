<?php
include 'header.php';
require 'methods/database.php'; // Include database connection

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to calculate working days
function getWorkingDays($startDate, $endDate) {
    $count = 0;
    $currentDate = $startDate;

    while ($currentDate <= $endDate) {
        if (date('N', strtotime($currentDate)) < 6) {
            $count++;
        }
        $currentDate = date('Y-m-d', strtotime($currentDate . '+1 day'));
    }

    return $count;
}

// Get project ID and subtask ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$subtask_id = isset($_GET['subtask_id']) ? intval($_GET['subtask_id']) : 0;

// Fetch project details
$project_query = $link->prepare("SELECT * FROM projects WHERE project_id = ?");
$project_query->bind_param("i", $project_id);
$project_query->execute();
$project = $project_query->get_result()->fetch_assoc();

// Fetch subtasks, comments, and users
$subtasks_query = $link->prepare("SELECT subtasks.*, users.username AS assigned_username 
                                    FROM subtasks 
                                    LEFT JOIN users ON subtasks.assigned_to = users.id 
                                    WHERE subtasks.project_id = ?");
$subtasks_query->bind_param("i", $project_id);
$subtasks_query->execute();
$subtasks_result = $subtasks_query->get_result();

$users_query = $link->query("SELECT id, username FROM users WHERE role = 'User'");
$users = $users_query->fetch_all(MYSQLI_ASSOC);

$comments_query = $link->prepare("SELECT * FROM comments WHERE project_id = ?");
$comments_query->bind_param("i", $project_id);
$comments_query->execute();
$comments_result = $comments_query->get_result();

$timeline_query = $link->prepare("SELECT * FROM timeline WHERE project_id = ? ORDER BY event_time DESC");
$timeline_query->bind_param("i", $project_id);
$timeline_query->execute();
$timeline_result = $timeline_query->get_result();

// Prepare join query for subtasks
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
        timeline.file_image
    FROM subtasks
    LEFT JOIN timeline ON subtasks.subtask_id = timeline.pro_sub_id
    WHERE subtasks.subtask_id = ?
");
$join_query->bind_param("i", $subtask_id);
$join_query->execute();
$join_result = $join_query->get_result();
$subtask = $join_result->fetch_assoc(); // Fetch single subtask data
$join_query->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Project Details</title>
    <link rel="stylesheet" href="css/project_timeline.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script>
    function toggleEditForm(subtaskId) {
        const form = document.getElementById('edit-form-' + subtaskId);
        form.classList.toggle('active');
    }

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
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementsByClassName("tab")[0].click();
    });
    </script>
</head>
<style>
.container {
    margin-top: 60px;
}

.file-container {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
}

.file-item {
    border: 1px solid #ccc;
    border-radius: 5px;
    padding: 10px;
    background-color: #f9f9f9;
    text-align: center;
    width: calc(30.333% - 10px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s;
}

.file-item:hover {
    transform: scale(1.05);
}

.image_show {
    width: 100%;
    height: auto;
    border-radius: 5px;
}

.pdf-preview {
    width: 100%;
    height: 200px;
}

.file-item a {
    color: #007bff;
    text-decoration: none;
}

.file-item a:hover {
    text-decoration: underline;
}
</style>

<body>
    <div class="container">
        <div class="content-wrapper">
            <div class="left-side">
                <div class="tab" onclick="openTab(event, 'tab2')">
                    <button>Subtasks Completed</button>
                </div>
                <div class="tab">
                    <button>
                        <a href="project_timeline.php?project_id=<?= $project_id ?>">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </button>
                </div>

                <table>
                    <tr>
                        <th>Project</th>
                        <td><?= htmlspecialchars($project['project_name']) ?></td>
                    </tr>
                    <tr>
                        <th>Subtask</th>
                        <td><?= htmlspecialchars($subtask['subtask_name']) ?></td>
                    </tr>
                </table>
                <div id="tab2" class="tab-content">
                    <div class="tb">
                        <div class="timeline">


                            <!-- Use subtask variable -->
                            <table border="1" cellpadding="10">
                                <thead>
                                    <tr>
                                        <th>Images</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="file-container">
                                                <?php if (!empty($subtask['file_image'])): 
                                                    $file_paths = explode(',', $subtask['file_image']); 
                                                    foreach ($file_paths as $file_path): 
                                                        $file_path = trim($file_path); 
                                                        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION)); 
                                                ?>
                                                <div class="file-item">
                                                    <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                    <a href="<?= htmlspecialchars($file_path); ?>" target="_blank">
                                                        <img class="image_show"
                                                            src="<?= htmlspecialchars($file_path); ?>"
                                                            alt="Found Image">
                                                    </a>
                                                    <?php elseif ($file_ext == 'pdf'): ?>
                                                    <div class="pdf-item">
                                                        <p><strong>PDF:</strong> <a
                                                                href="<?= htmlspecialchars($file_path); ?>"
                                                                target="_blank">View PDF</a></p>
                                                        <embed src="<?= htmlspecialchars($file_path); ?>"
                                                            type="application/pdf" class="pdf-preview">
                                                    </div>
                                                    <?php elseif ($file_ext == 'mp4'): ?>
                                                    <video width="350" height="240" controls>
                                                        <source src="<?= htmlspecialchars($file_path); ?>"
                                                            type="video/mp4">
                                                    </video>
                                                    <?php else: ?>
                                                    <p><a href="<?= htmlspecialchars($file_path); ?>"
                                                            target="_blank">View File</a></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php else: ?>
                                                <p>No File Available</p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>