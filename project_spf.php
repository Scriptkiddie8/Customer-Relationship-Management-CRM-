<?php
include 'header.php';
require 'methods/database.php'; // Include database connection
// session_start(); // Ensure session is started

// Get the user ID from the session
//$user_id1 = $_SESSION['id'];
// Prepare the SQL statement

// Initialize variables
$subtask_id = isset($_GET['subtask_id']) ? intval($_GET['subtask_id']) : 0;
//$user_id = 1; // Replace with session user ID when integrating authentication
$user_id=$_SESSION['id'];
$message = '';
$current_time = new DateTime();
$subtask_query = $link->prepare("SELECT * FROM subtasks WHERE subtask_id = ?");
$subtask_query->bind_param("i", $subtask_id);
$subtask_query->execute();
$subtask_result = $subtask_query->get_result();




$pro_sub_id = $_GET['subtask_id'];

if ($subtask_result->num_rows > 0) {
    $subtask = $subtask_result->fetch_assoc();
    $project_id = $subtask['project_id'];
    $subtask_status = $subtask['status']; // Get subtask status
    $estimated_manhours = $subtask['estimated_manhours']; // Get subtask status

    // Prepare and execute the query to get the most recent event_id
    $stmt_timeline = $link->prepare("SELECT event_id FROM timeline WHERE project_id = ? AND user_id = ? ORDER BY event_id DESC LIMIT 1");
    $stmt_timeline->bind_param("ii", $project_id, $user_id);
    $stmt_timeline->execute();
    $result = $stmt_timeline->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $event_id = $row['event_id']; // Get the most recent event_id

        // Prepare and execute the query to get the latest timeline entry for the user
        $timeline_query = $link->prepare("SELECT * FROM timeline WHERE event_id = ? AND user_id = ? ORDER BY event_time DESC LIMIT 1");
        $timeline_query->bind_param("ii", $event_id, $user_id);
        $timeline_query->execute();
        $timeline_result = $timeline_query->get_result();

        $remaining_hours = 0;
        $remaining_minutes = 0;
        $remaining_seconds = 0;
        $remaining_total_seconds = null;

        // Check if timeline data is available
        if ($timeline_result->num_rows > 0) {
            $timeline = $timeline_result->fetch_assoc();
            $start_time_going = new DateTime($timeline['start_time'], new DateTimeZone('Asia/Kolkata'));
            // Convert estimated manhours to seconds
            $estimated_seconds = $estimated_manhours * 3600;
            if ($subtask_status != "paused") {
                // Handle pause/resume time if available
                $time_string = isset($timeline['pause_resume_time']) ? $timeline['pause_resume_time'] : '00:00:00';
                
                // Split the time string into hours, minutes, and seconds
                list($hours, $minutes, $seconds) = explode(":", $time_string);
                $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;
            
                // Get the current time and subtract the pause/resume time interval
                $current_time = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                $interval = new DateInterval("PT{$total_seconds}S");
                $current_time->sub($interval);
            
                // Calculate the time difference from the start time
                $interval = $current_time->diff($start_time_going);
                $total_active_seconds = ($interval->days * 24 * 3600) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
                $remaining_seconds = $estimated_seconds - $total_active_seconds;
                $remaining_time_formatted = '';
                if ($remaining_seconds > 0) {
                    // Convert seconds to hours, minutes, and seconds
                    $remaining_hours = floor($remaining_seconds / 3600);
                    $remaining_seconds %= 3600;
                    $remaining_minutes = floor($remaining_seconds / 60);
                    $remaining_seconds %= 60;
            
                    // Format the remaining time
                    $remaining_time_formatted = sprintf("%02d hours, %02d minutes, %02d seconds", $remaining_hours, $remaining_minutes, $remaining_seconds);
                    $remaining_total_seconds = ($remaining_hours * 3600) + ($remaining_minutes * 60) + $remaining_seconds;
            
                    // Update the remaining time in the database
                    $update_query = $link->prepare("UPDATE subtasks SET remaining_time = ?  WHERE subtask_id = ?");
                    if ($update_query) {
                        $update_query->bind_param("ii", $remaining_total_seconds, $subtask_id);
                        $update_query->execute();
                        $update_query->close();
                    } else {
                        die('Prepare failed: ' . htmlspecialchars($link->error));
                    }
                } else {
                    // Task has exceeded the estimated time, set remaining time to zero
                    $remaining_time_formatted = "Task has exceeded the estimated time!";
                    $remaining_total_seconds = 0; // Set to zero if exceeded
            
                    // Update the remaining time in the database
                    $update_query = $link->prepare("UPDATE subtasks SET remaining_time = ? WHERE subtask_id = ?");
                    if ($update_query) {
                        $update_query->bind_param("ii", $remaining_total_seconds, $subtask_id);
                        $update_query->execute();
                        $update_query->close();
                    } else {
                        die('Prepare failed: ' . htmlspecialchars($link->error));
                    }
                }
            
                // Display remaining time or message if exceeded
                echo $remaining_time_formatted;
            }
            $interval_formatted = "00:00:00"; // Default value, in case the conditions don't set it
            // Handle paused state
            if ($subtask_status == "paused") {
                // Check if paused and timeline has a paused_time value
                if (!empty($timeline['paused_current_time']) && !empty($timeline['start_time'])) {
                    // Split time strings into hours, minutes, and seconds (assuming time is in H:i:s format)
                    $start_time_parts = explode(":", $timeline['start_time']);
                    $paused_time_parts = explode(":", $timeline['paused_current_time']);
            
                    // Check if both times are in the correct format (H:i:s)
                    if (count($start_time_parts) == 3 && count($paused_time_parts) == 3) {
                        // Convert the time parts to integers (with validation)
                        list($start_hours, $start_minutes, $start_seconds) = array_map('intval', $start_time_parts);
                        list($paused_hours, $paused_minutes, $paused_seconds) = array_map('intval', $paused_time_parts);
            
                        // Convert start time and paused time to total seconds
                        $start_total_seconds = ($start_hours * 3600) + ($start_minutes * 60) + $start_seconds;
                        $paused_total_seconds = ($paused_hours * 3600) + ($paused_minutes * 60) + $paused_seconds;
            
                        // Calculate the difference in seconds
                        $total_seconds = $paused_total_seconds - $start_total_seconds;
            
                        // Handle negative time differences (if needed)
                        if ($total_seconds < 0) {
                            $total_seconds = 0;
                        }
            
                        // Convert back to hours, minutes, and seconds for display purposes
                        $hours = floor($total_seconds / 3600);
                        $minutes = floor(($total_seconds % 3600) / 60);
                        $seconds = $total_seconds % 60;
                        // Format the time for display (HH:MM:SS)
                        $interval_formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            
                        // Update the paused_after field in the timeline with the calculated total seconds
                        $update_query = $link->prepare("UPDATE timeline SET paused_after = ? WHERE event_id = ? AND user_id = ?");
                        if ($update_query) {
                            $update_query->bind_param("sii", $total_seconds, $timeline['event_id'], $user_id);
                            if (!$update_query->execute()) {
                                error_log("Execution failed: " . htmlspecialchars($update_query->error));
                            }
                            $update_query->close();
                        } else {
                            error_log("Prepare failed: " . htmlspecialchars($link->error));
                        }
                    } else {
                        error_log("Invalid time format in start_time or paused_current_time.");
                    }
                }
                // Freeze the remaining time (do not update the remaining time)
                $remaining_time_formatted = sprintf("%02d hours, %02d minutes, %02d seconds", $remaining_hours, $remaining_minutes, $remaining_seconds);
                // Pass the flag to frontend to indicate paused state
                echo "<script>var ispaused = true;</script>";
            } else {
                // Subtask is not paused, update the time as usual
                $remaining_time_formatted = sprintf("%02d hours, %02d minutes, %02d seconds", $remaining_hours, $remaining_minutes, $remaining_seconds);
                // Pass the flag to frontend to indicate active state
                echo "<script>var ispaused = false;</script>";
            }
            // Check for completed status
            if ($subtask_status == "Completed") {
                // Set remaining time to zero if completed
                $remaining_total_seconds = 0;
                $update_query = $link->prepare("UPDATE subtasks SET remaining_time = ? WHERE subtask_id = ?");
                if ($update_query) {
                    $update_query->bind_param("ii", $remaining_total_seconds, $subtask_id);
                    $update_query->execute();
                    $update_query->close();
                } else {
                    die('Prepare failed: ' . htmlspecialchars($link->error));
                }
                $remaining_time_formatted = "Task has been completed!";
            }
        } else {
            echo "<tr><td colspan='2'>No details found for the most recent event.</td></tr>";
        }
        $timeline_query->close();
    }



    // Start Time Tracking
    if (isset($_POST['start_time'])) {
        // Get the posted start_time from the form (already in 'Y-m-d H:i:s' format)
        $formatted_start_time = $_POST['start_time'];

        // Prepare the insert statement for time tracking
        $stmt = $link->prepare("INSERT INTO timeline (pro_sub_id, start_time, project_id, user_id) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            // Log error if prepare fails
            error_log("Error preparing insert statement: " . $link->error);
        } else {
            $stmt->bind_param("isii", $subtask_id, $formatted_start_time, $project_id, $user_id);

            if ($stmt->execute()) {
                // Store the start_time in session for future reference
                $message = "Time tracking started!";

                // Update the status of the subtask to 'in progress'
                $update_status_query = $link->prepare("UPDATE subtasks SET status = 'in progress' WHERE subtask_id = ?");
                if ($update_status_query === false) {
                    // Log error if prepare fails
                    error_log("Error preparing status update statement: " . $link->error);
                } else {
                    $update_status_query->bind_param("i", $subtask_id);

                    if ($update_status_query->execute()) {
                        // Redirect to the same page with the subtask ID to prevent form resubmission
                        header("Location: project_spf.php?subtask_id=$subtask_id");
                        exit(); // Stop further script execution
                    } else {
                        // Log error if status update fails
                        $message = "Error updating subtask status: " . $update_status_query->error;
                        error_log($message);
                    }

                    // Close the update query statement
                    $update_status_query->close();
                }
            } else {
                // Log error if insert into timeline fails
                $message = "Error inserting into timeline: " . $stmt->error;
                error_log($message);
            }

            // Close the prepared statement
            $stmt->close();
        }
    }

   
} else {
    $message = "Subtask not found.";
}
$subtask_query->close();


// Helper function for handling subtask updates
if (isset($_POST['pause_subtask']) || isset($_POST['resume_subtask']) || isset($_POST['complete_subtask'])) {
    global $link, $subtask_id, $message;

    // Determine status and event based on action
    if (isset($_POST['pause_subtask'])) {
        // Step 1: Set variables from POST request
        $status = 'paused';
        $eventTitle = 'Subtask paused';
        $pause_time = $_POST['pause_time'];
        $paused_current_time = $_POST['paused_current_time'];
        $interval_time = $_POST['interval'];
        $paused_count = $_POST['paused_count'];

        $note_text = isset($_POST['note_text']) ? $_POST['note_text'] : ''; // If there's a note to add
        $file_path = ''; // You can define or fetch this if file_image needs to be handled

        // Step 2: Update the subtask status in the database
        if ($stmt = $link->prepare("UPDATE subtasks SET status = ? WHERE subtask_id = ?")) {
            $stmt->bind_param("si", $status, $subtask_id);

            if ($stmt->execute()) {
                // Step 3: Fetch the latest event in the timeline for this subtask
                if ($fetch_latest_event = $link->prepare("SELECT event_id FROM timeline WHERE pro_sub_id = ? ORDER BY event_id DESC LIMIT 1")) {
                    $fetch_latest_event->bind_param("i", $subtask_id);
                    $fetch_latest_event->execute();
                    $fetch_latest_event_result = $fetch_latest_event->get_result();

                    if ($fetch_latest_event_result->num_rows > 0) {
                        // Fetch the latest event ID
                        $latest_event = $fetch_latest_event_result->fetch_assoc();
                        $event_id = $latest_event['event_id'];
                        $pro_sub_id = $latest_event['pro_sub_id'];

                        // Step 4: Update the timeline event with new status and description
                        if ($stmt_timeline = $link->prepare("UPDATE timeline SET event_title=?, event_description=?, paused_time=?, file_image=?, paused_current_time=?, paused_counting=? ,intervaltime=? WHERE event_id = ? ")) {
                            $event_description = "Subtask $subtask_id updated with note: $note_text";
                            $stmt_timeline->bind_param("sssssssi", $eventTitle, $event_description, $pause_time, $file_path, $paused_current_time, $paused_count, $interval_time, $event_id );

                            if ($stmt_timeline->execute()) {
                                // Successfully updated timeline event
                                $message = "Subtask marked as $status and timeline updated!";
                                header("Location: project_dashboard.php"); // Redirect to avoid form resubmission
                                exit();
                            } else {
                                // Log any errors from the timeline update
                                error_log("Timeline update error: " . $stmt_timeline->error);
                            }
                            $stmt_timeline->close();
                        } else {
                            // Log preparation failure for the timeline update
                            error_log("Timeline update preparation failed: " . $link->error);
                        }
                    } else {
                        // No timeline event found for the specified subtask
                        error_log("No timeline event found for subtask ID: " . $subtask_id);
                    }
                    $fetch_latest_event->close();
                } else {
                    // Log any issues fetching the latest event
                    error_log("Timeline event fetch failed: " . $link->error);
                }
            } else {
                // Log errors from the subtask update
                error_log("Subtask update error: " . $stmt->error);
            }
            $stmt->close();
        } else {
            // Log any errors from preparing the subtask update query
            error_log("Subtask update query preparation failed: " . $link->error);
        }
    }
    // Check if the resume_subtask button is clicked
    if (isset($_POST['resume_subtask'])) {
        $status = 'in progress';
        $eventTitle = 'Subtask Resumed';
        $resume_time = $_POST['resume_time']; // Get the resume time from the form input
    
        // Convert paused and resume times to DateTime objects
        $pausedTime = new DateTime($timeline['paused_current_time']);
        $resumeTime = new DateTime($resume_time);
    
        // Calculate the difference between paused and resume times
        $interval = $pausedTime->diff($resumeTime);
        
        // Format the time difference as an array
        $timeDifference = [
            'days' => $interval->d,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'seconds' => $interval->s
        ];
    
        // Calculate total pause duration in seconds
        $totalSecondsNew = ($timeDifference['days'] * 86400) + 
            ($timeDifference['hours'] * 3600) +  
            ($timeDifference['minutes'] * 60) +  
            $timeDifference['seconds'];
    
        // Get the old pause time and convert it to seconds
        $oldTime = isset($timeline['pause_resume_time']) ? $timeline['pause_resume_time'] : '00:00:00';
        sscanf($oldTime, "%d:%d:%d", $oldHours, $oldMinutes, $oldSeconds); 
        $totalSecondsOld = ($oldHours * 3600) + ($oldMinutes * 60) + $oldSeconds; 
    
        // Add new and old pause times together
        $totalSecondsResume = $totalSecondsOld + $totalSecondsNew;
    
        // Convert total seconds back to HH:MM:SS format
        $hours = floor($totalSecondsResume / 3600);
        $minutes = floor(($totalSecondsResume % 3600) / 60);
        $seconds = $totalSecondsResume % 60;
        $totalTimeResume = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    
        // Fetch paused count
        $paused_count = isset($timeline['paused_counting']) ? $timeline['paused_counting'] : 0;
    
        // Check if the paused count is within limits
        if ($paused_count < 100) {
            // Step 1: Update the status of the subtask to 'in progress'
            if ($stmt = $link->prepare("UPDATE subtasks SET status = ? WHERE subtask_id = ?")) {
                $stmt->bind_param("si", $status, $subtask_id);
    
                if ($stmt->execute()) {
                    // Step 3: Fetch the latest event in the timeline for this subtask
                    if ($fetch_latest_event = $link->prepare("SELECT event_id FROM timeline WHERE pro_sub_id = ? ORDER BY event_id DESC LIMIT 1")) {
                        $fetch_latest_event->bind_param("i", $subtask_id);
                        $fetch_latest_event->execute();
                        $fetch_latest_event_result = $fetch_latest_event->get_result();
    
                        if ($fetch_latest_event_result->num_rows > 0) {
                            // Fetch the latest event ID
                            $latest_event = $fetch_latest_event_result->fetch_assoc();
                            $event_id = $latest_event['event_id'];
                            $pro_sub_id = $latest_event['pro_sub_id'];
    
                            // Step 4: Update the timeline event with new status and description
                            if ($stmt_timeline = $link->prepare(
                                "UPDATE timeline SET event_title = ?, event_description = ?, resume_time = ?, pause_resume_time = ?, resumesecond = ?, file_image = ? WHERE event_id = ? "
                                )) {
                                    $event_description = "Subtask $subtask_id resumed.";
                                    $stmt_timeline->bind_param("ssssssi", $eventTitle, $event_description, $resume_time, $totalTimeResume, $totalSecondsNew, $file_path, $event_id);
        
                                    if ($stmt_timeline->execute()) {
                                        header("Location: project_dashboard.php");
                                        exit();
                                    } else {
                                        error_log("Timeline update error: " . $stmt_timeline->error);
                                    }
                                    $stmt_timeline->close();
                            } else {
                                // Log preparation failure for the timeline update
                                error_log("Timeline update preparation failed: " . $link->error);
                            }
                        } else {
                            // No timeline event found for the specified subtask
                            error_log("No timeline event found for subtask ID: " . $subtask_id);
                        }
                        $fetch_latest_event->close();
                    } else {
                        // Log any issues fetching the latest event
                        error_log("Timeline event fetch failed: " . $link->error);
                    }
                } else {
                    // Log errors from the subtask update
                    error_log("Subtask update error: " . $stmt->error);
                }
                $stmt->close();
            } else {
                // Log any errors from preparing the subtask update query
                error_log("Subtask update query preparation failed: " . $link->error);
            }
        } else {
            // If the paused count exceeds the limit
            echo "<script>alert('Cannot change status to In Progress. You have reached the maximum pause limit.'); window.location = 'project_dashboard.php';</script>";
        }
    }
    
     elseif (isset($_POST['complete_subtask'])) {
        $status = 'Completed';
        $eventTitle = 'Subtask Completed';
        $reason_late_text = $_POST['reason_late_text'];
        $event_time = $_POST['complete_time'];
        $interval_time = $_POST['complete_intervaltime'];
     
     }
    function uploadFile($tmp_file, $file_name) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_path = $upload_dir . basename($file_name);
        if (move_uploaded_file($tmp_file, $file_path)) {
            return $file_path;
        } else {
            error_log("Failed to move uploaded file: " . $file_name);
            return null;
        }
    }
    $note_text = $_POST['note_text'] ?? '';
    $files = $_FILES['file_upload'] ?? []; 

    // Check if note_text is empty
    if (empty($note_text)) {
        $message = "Note text is required.";
    } else {
        // Update subtask status in the database
        if ($stmt = $link->prepare("UPDATE subtasks SET status = ? WHERE subtask_id = ?")) {
            $stmt->bind_param("si", $status, $subtask_id);
            if ($stmt->execute()) {
                // Fetch latest event in timeline
                if ($fetch_latest_event = $link->prepare("SELECT event_id FROM timeline WHERE pro_sub_id = ? ORDER BY event_id DESC LIMIT 1")) {
                    $fetch_latest_event->bind_param("i", $subtask_id);
                    $fetch_latest_event->execute();
                    $fetch_latest_event_result = $fetch_latest_event->get_result();

                    if ($fetch_latest_event_result->num_rows > 0) {
                        $latest_event = $fetch_latest_event_result->fetch_assoc();
                        $event_id = $latest_event['event_id'];
                        $pro_sub_id = $latest_event['pro_sub_id'];

                        // Prepare the event description
                        $event_description = $note_text;
                        // $event_time = date("Y-m-d H:i:s"); // Current time

                        // Initialize an array for file paths
                        $file_paths = [];

                        // Check if any files were uploaded
                        if (!empty($files['name'][0])) {
                            for ($i = 0; $i < count($files['name']); $i++) {
                                // Check if the file was uploaded without error
                                if (isset($files['error'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                                    $file_path = uploadFile($files['tmp_name'][$i], $files['name'][$i]);
                                    if ($file_path) {
                                        $file_paths[] = $file_path; // Add file path to array
                                    }
                                } else {
                                    error_log("File upload error for file $i: " . ($files['error'][$i] ?? 'Unknown error'));
                                }
                            }
                        }

                        // Convert file paths array to a comma-separated string
                        $file_paths_string = implode(',', $file_paths);

                        // Update timeline event with new status and description
                        if ($stmt_timeline = $link->prepare("UPDATE timeline SET reason_late_text=?, event_title=?, event_description=?, event_time=?, file_image=?,intervaltime=? WHERE event_id = ? ")) {
                            $eventTitle = "Subtask Status Updated";
                            $stmt_timeline->bind_param("ssssssi", $reason_late_text, $eventTitle, $event_description, $event_time, $file_paths_string,$interval_time, $event_id);
                            if ($stmt_timeline->execute()) {
                                $message = "Subtask marked as $status and timeline updated!";
                                header("Location: project_dashboard.php");
                                exit();
                            } else {
                                error_log("Timeline update error: " . $stmt_timeline->error);
                            }
                            $stmt_timeline->close();
                        }
                    } else {
                        error_log("No timeline event found for subtask ID: " . $subtask_id);
                    }
                    $fetch_latest_event->close();
                }
            } else {
                error_log("Subtask update error: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}


$subtasks_query = $link->prepare("SELECT subtasks.*, 
           users.username,
           p.project_name

    FROM subtasks 
    LEFT JOIN users 
    ON subtasks.assigned_to = users.id 
    JOIN projects p ON subtasks.project_id = p.project_id
    WHERE subtasks.subtask_id = ? ");
$subtasks_query->bind_param("i", $subtask_id);
$subtasks_query->execute();
$subtasks_result = $subtasks_query->get_result();



// Prepare the query to fetch the latest start_time from the timeline table for a specific user and task
$timeline_start_time = $link->prepare("SELECT start_time  FROM timeline WHERE pro_sub_id = ? AND user_id = ? ORDER BY event_time DESC");
if ($timeline_start_time === false) {
    die("Error preparing statement: " . $link->error);
}
$timeline_start_time->bind_param("ii", $pro_sub_id, $user_id);
if (!$timeline_start_time->execute()) {
    die("Error executing query: " . $timeline_start_time->error);
}
$timeline_start_result = $timeline_start_time->get_result();
if ($timeline_start_result->num_rows > 0) {
    $timeline_start = $timeline_start_result->fetch_assoc();
    $formatted_time = date('Y-m-d H:i:s', strtotime($timeline_start['start_time']));
} else {
    echo "No start time found for this task.";
}
$timeline_start_time->close();

// Helper function to get status class based on subtask status
function getStatusClass($status)
{
    switch ($status) {
        case 'pause':
            return 'text-danger';
        case 'Completed':
            return 'text-success';
        case 'in progress':
        default:
            return 'text-primary';
    }
}

// Function to format time interval// Helper function to format the interval
function formatTimeInterval($interval)
{
    if ($interval === null) {
        return "00:00:00"; // Default value if interval is null
    }

    // Hours (with zero padding)
    $formatted = str_pad($interval->h, 2, '0', STR_PAD_LEFT) . ':';
    // Minutes (with zero padding)
    $formatted .= str_pad($interval->i, 2, '0', STR_PAD_LEFT) . ':';
    // Seconds (with zero padding)
    $formatted .= str_pad($interval->s, 2, '0', STR_PAD_LEFT);

    return $formatted;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subtask Details</title>
    <link rel="stylesheet" href="css/subtask_details.css">
    <link rel="stylesheet" href="css/project_spf.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>


</head>
<style>
.text-danger {
    color: red;
    /* Or any other color you prefer */
    font-weight: bold;
}
</style>

<body>
    <div class="container">
        <h1>Subtask Details</h1>

        <div class="message"><?php echo htmlspecialchars($message); ?></div>

        <!-- Subtask Info start code php -->
        <div class="subtask-info">
            <?php
            $stmt1 = $link->prepare("SELECT * FROM users WHERE id = ?");
            if ($stmt1 === false) {
                die('Prepare failed: ' . htmlspecialchars($link->error));
            }
            $stmt1->bind_param("i", $user_id);  //yaha change hua hai 25 ko
            if (!$stmt1->execute()) {
                die('Execute failed: ' . htmlspecialchars($stmt1->error));
            }
            $result = $stmt1->get_result();
            if ($result === false) {
                die('Get result failed: ' . htmlspecialchars($stmt1->error));
            }

            echo '<table class="subtask-table">';
            echo '<thead>
                    <tr></tr>
                </thead>';
            echo '<tbody>';

            $user_name = '';
            $assigned_username_display = '';
            $subtask_name = '';
            $deadline = '';
            $status_class = '';
            $status = '';

            while ($users = $result->fetch_assoc()):
                $user_name = htmlspecialchars($users['username']);
            endwhile;

            while ($assigned_username = $subtasks_result->fetch_assoc()):
                $project_name = htmlspecialchars($assigned_username['project_name']);
                $assigned_username_display = $assigned_username ? $assigned_username : 'Unassigned';
                $subtask_name = htmlspecialchars($subtask['subtask_name']);
                $deadline = htmlspecialchars($assigned_username['deadline']);
                $deadline_dateee = $assigned_username['deadline'];
                $description = htmlspecialchars($subtask['description']);
                $estimated_manhours = htmlspecialchars($subtask['estimated_manhours']);
                $status_class = getStatusClass($subtask_status);
                $status = htmlspecialchars($subtask_status);

                echo '<tr><th>Project </th><td>' . $project_name . '</td></tr>';
                echo '<tr><th>Subtask Name</th><td>' . $subtask_name . '</td></tr>';
                $currentDate = new DateTime();
                function calculateDeadlines($currentDate, $days) {
                    $dates = [];
                    $deadline = clone $currentDate;
                    while (count($dates) < $days) {
                        $deadline->modify('+1 day');
                        if ($deadline->format('N') < 6) {
                            // Store the date and day of the week
                            $dates[] = [
                                'date' => $deadline->format('Y-m-d'),
                                'day' => $deadline->format('l')
                            ];
                        }
                    }
                    return $dates;
                }
                function countRemainingWeekdays($deadlineDate) {
                    $count = 0;
                    $currentDate = new DateTime(); // Get the current date
                    $deadlineDate = new DateTime($deadlineDate); // Convert the SQL date string to DateTime
                
                    // Ensure we only calculate if the current date is before the deadline
                    if ($currentDate >= $deadlineDate) {
                        return $count; // Return 0 if the deadline is today or in the past
                    }
                
                    while ($currentDate < $deadlineDate) {
                        $currentDate->modify('+1 day');
                        if ($currentDate->format('N') < 6) { // Check if it's a weekday (Mon-Fri)
                            $count++; // Increment the counter for each weekday
                        }
                    }
                
                    return $count; // Return the total count of remaining weekdays
                }
                $initialDeadlineDays = 1; // Change this to test with different values
                // Warning if more than 5 days
                if ($initialDeadlineDays > 5) {
                    echo '<tr><th>Warning</th><td style="color: red;">Please limit the deadline to a maximum of 5 working days.</td></tr>';
                } else {
                    function getDayOfWeek($date) {
                        // Convert the input date to a DateTime object
                        $dateTime = new DateTime($date);
                        
                        // Return the day of the week
                        return $dateTime->format('l'); // 'l' gives the full textual representation of the day
                    }
                    
                    $adjustedDeadlines = calculateDeadlines($currentDate, $initialDeadlineDays);
                    // Output the first deadline with its respective day
                    if (!empty($adjustedDeadlines)) {
                        $daytoday = getDayOfWeek($deadline_dateee);
                        $firstDeadline = $adjustedDeadlines[0];
                        echo '<tr><th>Deadline</th><td class="' . $status_class . '">';
                        echo $deadline_dateee . ' (' . $daytoday . ')</td></tr>';
                        // echo $firstDeadline['date'] . ' (' . $firstDeadline['day'] . ')</td></tr>';
                    }
                    // Calculate and check completed days
                    $completedDays = [];
                    foreach ($adjustedDeadlines as $deadline) {
                        $deadlineDate = new DateTime($deadline['date']);
                        if ($deadlineDate < $currentDate) {
                            $completedDays[] = $deadline['date'] . ' (' . $deadline['day'] . ') - Completed ✅';
                        }
                    }
                    // Output completed days only if there are any
                    if (!empty($completedDays)) {
                        echo '<tr><th>Days Completed</th><td>' . implode('<br>', $completedDays) . '</td></tr>';
                    }
                    // Calculate remaining days
                    $remainingDays = countRemainingWeekdays($deadline_dateee);
                    echo '<tr><th>Remaining Days</th><td>' . $remainingDays .' ' . 'days'. '</td></tr>';
                }
                echo '<tr><th>Description</th><td>' . $description . '</td></tr>';
                $totalMinutes = $estimated_manhours * 60; 
                $hours = floor($totalMinutes / 60); 
                $minutes = $totalMinutes % 60; 
                $seconds = 0; 
                $formattedTime = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
                echo '<tr><th>Estimated Manhours</th><td>' . $formattedTime . '</td></tr>';
                echo '<tr><th>Status</th><td class="' . $status_class . '">' . $status . '</td></tr>';
            endwhile;
            echo '</tbody></table>';
            $stmt1->close();
            ?>
        </div>
        <!-- Subtask Info end  code php -->
        <?php if ($subtask['status'] == 'Not Started'): ?>
        <!-- start subtask code  -->
        <form id="start-task-form" method="post" action="">
            <input type="hidden" id="current-time" name="start_time">
            <button type="submit" class="btn btn-primary">Start Task</button>
        </form>
        <script>
        function getCurrentTime() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('current-time').value = getCurrentTime();
        });
        </script>
        <!-- end subtask code  -->

        <?php endif; ?>
        <?php if ($subtask['status'] == 'paused'): ?>

        <!-- Resume Subtask start code   -->
        <form id="subtask-form" method="post" action="">
            <input type="hidden" id="pause_resume_time" name="pause_resume_time"
                value="<?= htmlspecialchars($timeline['pause_resume_time']); ?>">
            <input type="hidden" id="" name="" value="<?= htmlspecialchars($timeline['resumesecond']); ?>">
            <input type="hidden" id="resume_time" name="resume_time"> <!-- Corrected to type="text" -->
            <button id="resume-btn" name="resume_subtask" type="submit" class="btn btn-success">Resume Subtask</button>
            <script>
            function getCurrentTime() {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            }

            document.getElementById('resume-btn').addEventListener('click', function(e) {
                document.getElementById('resume_time').value =
                    getCurrentTime(); // Ensure the function name matches
            });
            </script>
        </form>
        <!-- Resume Subtask end code   -->
        <?php endif; ?>
        <?php if ($subtask['status'] == 'in progress' || $subtask['status'] == 'paused'): ?>
        <div id="trackingSection">
            <h3>Pause or Complete Subtask</h3>
            <?php if (isset($timeline)): ?>
            <div class="timeline-container">
                <h3>Task Timeline</h3>
                <table class="timeline-table">
                    <tbody>
                        <!-- Display Start Time -->
                        <tr>
                            <td><strong>Start date:</strong></td>
                            <td>
                                <span class="<?php echo getStatusClass($subtask_status); ?>">
                                    <?php 
                                    if (!empty($formatted_time)) {
                                        $timestamp = strtotime($formatted_time);
                                        echo htmlspecialchars(date('d-M-Y', $timestamp)); // Format: Oct, 21, 2024
                                    } else {
                                        echo "No date available"; 
                                    }
                                    ?>
                                </span>
                            </td>

                        </tr>
                        <tr>
                            <td><strong>Start time:</strong></td>
                            <td>
                                <span class="<?php echo getStatusClass($subtask_status); ?>">
                                    <?php 
                                    if (!empty($formatted_time)) {
                                        $timestamp = strtotime($formatted_time);
                                        echo htmlspecialchars(date('H:i:s', $timestamp));
                                    } else {
                                        echo "No time available"; 
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>

                        <!-- Display Current Status and Time Interval -->
                        <?php if ($subtask_status == "paused"): ?>

                        <tr>
                            <td><strong>paused Counting:</strong></td>
                            <td><span
                                    class='text-warning'><?php echo htmlspecialchars($timeline['paused_counting']); ?></span>
                            </td>
                        </tr>
                        <?php if ($timeline['paused_counting'] >= 99 && $timeline['paused_counting'] < 100): ?>
                        <tr>
                            <td colspan="2" class="text-danger">
                                <strong>Warning:</strong> You have reached 4 pauses. Only one more pause is allowed.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($timeline['paused_counting'] == 100): ?>
                        <tr>
                            <td colspan="2" class="text-danger">
                                <strong>Warning:</strong> paused Count completed. Your subtask has not been submitted.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Remaining Time:</strong></td>
                            <td><?php echo htmlspecialchars($timeline['paused_time']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>paused Interval Time:</strong></td>
                            <td>
                                <?php
                                            $pausedTime = new DateTime($timeline['paused_current_time'], new DateTimeZone('Asia/Kolkata'));
                                            $currentTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
                                            $interval = $pausedTime->diff($currentTime);
                                            echo htmlspecialchars($interval->format('%h hours, %i minutes, %s seconds'));
                                            ?>
                            </td>
                        </tr>
                        <?php elseif ($subtask_status == "in progress"): ?>
                        <tr>
                            <td><strong>Current Status:</strong></td>
                            <td><span class='text-primary'
                                    id='time-status1'><?php echo formatTimeInterval($interval); ?>
                                </span>
                            </td>
                        </tr>

                        <?php if (!empty($timeline['pause_resume_time'])): ?>
                        <tr>
                            <td><strong>Total Paused Time:</strong></td>
                            <td><span
                                    class='text-primary'><?php echo htmlspecialchars($timeline['pause_resume_time']); ?></span>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <td class="color_red"><strong>Remaining Time:</strong></td>
                        <td><span class='color_red' id="remaining-time"><?php echo $remaining_time_formatted; ?></span>
                        </td>
                        <input type="hidden" id="remaining-time-seconds"
                            value="<?php echo $remaining_total_seconds; ?>">
                        <input type="hidden" id="paused-state" value="0">
                        <!-- Add this hidden input for the pause state -->
                        <input type="hidden" id="subtask-id" value="<?php echo $subtask_id; ?>">
                        <!-- Define the subtask ID -->
                        </tr>


                        <?php else: ?>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class='text-primary'>Upcoming in
                                    <?php echo formatTimeInterval($interval); ?></span></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php if ($subtask['status'] == 'in progress'): ?>
            <form method="post" action="" enctype="multipart/form-data" id="subtaskForm">
                <div class="form-group" id="late-reason-div" style="display: none;">
                    <label for="reason_late_text">Reason for late completion of task:</label>
                    <textarea id="reason_late_text" name="reason_late_text" rows="3" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label for="note_text">Notes:</label>
                    <textarea id="note_text" name="note_text" rows="4" class="form-control"></textarea>
                </div>
                <div class="form-group mt-2">
                    <label for="file_upload">Upload File:</label>
                    <input type="file" id="file_upload" name="file_upload[]" class="form-control" multiple>
                </div>

                <input type="hidden" id="complete_time" name="complete_time">
                <input type="hidden" id="complete_intervaltime" name="complete_intervaltime"
                    value="<?php echo htmlspecialchars(formatTimeInterval($interval)); ?>">
                <script>
                let intervalTime = "<?php echo htmlspecialchars(formatTimeInterval($interval)); ?>";

                function updateIntervalTime() {
                    let parts = intervalTime.split(':'); // Assuming HH:MM:SS format
                    let seconds = parseInt(parts[2]) + 1; // Increment seconds
                    if (seconds >= 60) {
                        seconds = 0;
                        let minutes = parseInt(parts[1]) + 1;
                        if (minutes >= 60) {
                            minutes = 0;
                            parts[0] = parseInt(parts[0]) + 1; // Increment hours
                        }
                        parts[1] = minutes < 10 ? '0' + minutes : minutes; // Format minutes
                    }
                    parts[2] = seconds < 10 ? '0' + seconds : seconds; // Format seconds
                    intervalTime = parts.join(':');
                    // document.getElementById('time_current').textContent = intervalTime; // Update the span
                    document.getElementById('complete_intervaltime').value = intervalTime; // Update first input
                    // document.getElementById('interval').value = intervalTime; // Update second input
                }
                setInterval(updateIntervalTime, 1000);
                updateIntervalTime();
                </script>

                <div class="mt-3">
                    <!-- <button id="pauseBtn" name="pause_subtask" type="submit" class="btn btn-warning">Pause Subtask</button> -->
                    <?php if ($subtask['status'] == 'in progress'): ?>
                    <form id="subtask-form" method="post" action="">
                        <?php
                                    $currentDate = date('Y-m-d'); // Get current date in 'YYYY-MM-DD' format
                                    $hours = date('H'); // Get current hour in 24-hour format
                                    $minutes = date('i'); // Get current minutes
                                    $seconds = date('s'); // Get current seconds

                                    ?>
                        <div id="remaining-time-container" disabled style="display: none;">
                            Remaining Time: <span id="remaining-time">
                                <?php
                                            // Calculate hours, minutes, and seconds from remaining_total_seconds
                                            $hours = floor($remaining_total_seconds / 3600);
                                            $minutes = floor(($remaining_total_seconds % 3600) / 60);
                                            $seconds = $remaining_total_seconds % 60;
                                            echo $currentDate . '  ' .
                                                str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' .
                                                str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' .
                                                str_pad($seconds, 2, '0', STR_PAD_LEFT);
                                            ?>
                            </span>
                        </div>
                        <input type="hidden" id="remaining-time-seconds"
                            value="<?php echo $remaining_total_seconds; ?>">
                        <input type="hidden" id="pause_time" name="pause_time"
                            value="<?php echo $currentDate . '  ' .str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' .    str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' .str_pad($seconds, 2, '0', STR_PAD_LEFT);?>">
                        <input type="hidden" id="paused_current_time" name="paused_current_time">
                        <input type="hidden" id="interval" name="interval"
                            value="<?php echo htmlspecialchars(formatTimeInterval($interval)); ?>" />
                        <input type="hidden" id="paused_count" name="paused_count"
                            value="<?php echo $timeline['paused_counting']; ?>">
                        <!-- This will show the pause count from the database -->
                        <button id="pauseBtn" name="pause_subtask" type="submit" class="btn btn-warning">Pause
                            Subtask</button>
                    </form>
                    <?php endif ?>
                    <button id="completeBtn" name="complete_subtask" type="submit" class="btn btn-success">Complete
                        Subtask</button>
                </div>
                <div id="error-message" style="color: red; font-weight: bold;"></div>

            </form>
            <?php endif; ?>

            <script>
            function getnewtime() {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0'); // Ensure two digits
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`; // MySQL DATETIME format
            }

            document.getElementById('completeBtn').addEventListener('click', function(e) {
                document.getElementById('complete_time').value = getnewtime();
            });
            </script>

        </div>
        <?php endif; ?>
    </div>
</body>

</html>
<script>
// ........................... Completed subtask start code script..........................
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('subtaskForm'); // Form ID
    const noteField = document.getElementById('note_text'); // Note field ID
    const fileInput = document.getElementById('file_upload'); // File input field ID
    const errorMessageDiv = document.getElementById('error-message'); // Error message div
    const completeBtn = document.getElementById('completeBtn'); // Complete button ID
    const pauseBtn = document.getElementById('pauseBtn'); // Pause button ID

    form.addEventListener('submit', function(e) {
        // Reset error message on each submit attempt
        errorMessageDiv.innerHTML = '';

        // Check if the Complete Subtask button was clicked
        if (e.submitter === completeBtn) {
            const noteValue = noteField.value.trim(); // Get the trimmed note value
            const fileUploaded = fileInput.files.length >
                0; // Check if any file has been uploaded

            // Validate fields for complete subtask
            if (!noteValue && !fileUploaded) {
                e.preventDefault(); // Prevent form submission
                displayError(
                    'Please fill in the note field and upload at least one file.');
            } else if (!noteValue) {
                e.preventDefault(); // Prevent form submission
                displayError('Please fill in the note field.');
            } else if (!fileUploaded) {
                e.preventDefault(); // Prevent form submission
                displayError('Please upload at least one file.');
            }
        }
        // If the Pause Subtask button is clicked, skip validation and allow form submission
    });

    // Function to display the error message
    function displayError(message) {
        errorMessageDiv.innerHTML = message;
    }
});
// ........................... Completed subtask end code script..........................
</script>

<script>
// .......................paused timer and counter show start code................................
let totalTime = parseInt(document.getElementById('remaining-time-seconds').value);
let timerElement = document.getElementById('remaining-time');
let timer; // Variable to hold the setInterval ID
let ispaused =
    <?php echo $subtask_status == "paused" ? "true" : "false"; ?>; // Determine if it's paused initially

function updateTimer() {
    if (totalTime > 0) {
        totalTime--;
        let minutes = Math.floor(totalTime / 60);
        let seconds = totalTime % 60;
        timerElement.textContent = minutes + ':' + String(seconds).padStart(2, '0');
    } else {
        clearInterval(timer); // Clear the interval when time is up
        alert("Time's up!");
    }
}
// Start the timer only if it is not paused
if (!ispaused) {
    timer = setInterval(updateTimer, 1000);
}
// Function to get the current time in 'Y-m-d H:i:s' format
function getCurrentTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}
</script>
<script>
let startTime = null;
let pauseCount = parseInt(document.getElementById('paused_count')
    .value); // Initialize the pause count from the database
const maxPauseCount = 100; // Define the maximum number of pauses allowed

// Function to get current datetime in a readable format (YYYY-MM-DD HH:MM:SS)
function getCurrentDateTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0'); // Months are 0-based
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}

// Event listener for the Pause Subtask button
document.getElementById('pauseBtn').addEventListener('click', function(event) {
    if (pauseCount >= maxPauseCount) {
        // If the pause count has reached the maximum, display an alert and prevent further pausing
        alert("You cannot pause the subtask more than " + maxPauseCount + " times.");
        event.preventDefault(); // Prevent form submission
        return;
    }

    if (!startTime) {
        // Set the start time on the first click and update the paused_current_time field
        startTime = new Date();
        document.getElementById('paused_current_time').value = getCurrentDateTime();

        // Increment pause count and update the paused_count field
        pauseCount++;
        document.getElementById('paused_count').value = pauseCount;
    }

    // Reset startTime for future pauses if necessary
    startTime = null;
});
// .......................paused timer and counter show end  code................................
//............................................ remaining time show in progress ................................ 
document.addEventListener("DOMContentLoaded", function() {
    let remainingTime = parseInt(document.getElementById('remaining-time-seconds')
        .value, 10);
    const timeDisplay = document.getElementById('remaining-time');
    const pausedState = document.getElementById('paused-state');
    const subtaskId = document.getElementById('subtask-id').value;
    const lateReasonDiv = document.getElementById('late-reason-div');
    let alertShown = false;
    let timer;

    // Pause/Resume function
    function pauseTimer() {
        pausedState.value = "1"; // Set to paused state
        clearInterval(timer); // Stop the timer
        updateRemainingTimeInDB(); // Update remaining time in the database
    }

    function resumeTimer() {
        pausedState.value = "0"; // Resume state
        startTimer(); // Start the timer again
    }

    // Function to update the remaining time in the database
    function updateRemainingTimeInDB() {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "project_spf.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send("remaining_time=" + remainingTime + "&subtask_id=" +
            subtaskId); // Send remaining time and subtask_id to server
    }

    function updateRemainingTime() {
        if (pausedState.value === "0") { // Only decrement if not paused
            if (remainingTime > 0) {
                remainingTime--; // Decrement time if greater than 0
            } else {
                remainingTime = 0; // Ensure the time never goes negative
                clearInterval(timer); // Stop the timer when time is zero
            }

            // Update the input field with the remaining seconds
            document.getElementById('remaining-time-seconds').value = remainingTime;

            const hours = Math.floor(remainingTime / 3600);
            const minutes = Math.floor((remainingTime % 3600) / 60);
            const seconds = remainingTime % 60;

            if (remainingTime <= 1800 && remainingTime > 0 && !alertShown) {
                alert(`Warning: Only ${minutes} minute and ${seconds} second remaining!`);
                alertShown = true;
            }


            if (remainingTime > 0) {
                timeDisplay.innerText =
                    `${hours.toString().padStart(2, '0')} hours, ${minutes.toString().padStart(2, '0')} minutes, ${seconds.toString().padStart(2, '0')} seconds`;
            } else if (remainingTime === 0) {
                timeDisplay.innerText = 'Task has reached the estimated time!';
                if (lateReasonDiv) lateReasonDiv.style.display =
                    'block'; // Show late reason field if exists
            }
        }
    }

    // Start the timer
    function startTimer() {
        timer = setInterval(updateRemainingTime, 1000);
    }

    startTimer(); // Start the timer when the page loads

    // Button listeners for pause/resume
    document.getElementById("pause-button").addEventListener("click", pauseTimer);
    document.getElementById("resume-button").addEventListener("click", resumeTimer);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.subtask-table tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', function() {
            rows.forEach(r => r.classList.remove('highlighted'));
            this.classList.add('highlighted');
        });
    });
});
</script>