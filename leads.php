<?php 
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "header.php"; 


// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}
date_default_timezone_set('Asia/Kolkata');



// Include database connection file
require_once 'methods/database.php';

// Initialize variables
$leads = [];
$user_id = $_SESSION["id"];
$now = time();
$countdown_active = false;
$future_time = 0;
$today_date = date('Y-m-d');






// Assuming you have the current user's ID stored in a variable

$sql = "
    SELECT l.id, l.name, l.phone, l.email, l.source, l.form, l.channel, l.owner, l.created_at, l.labels, l.ad_name
    FROM leads l
    WHERE (l.owner IS NULL)  -- Fresh leads
      AND (l.powner IS NULL OR l.powner != ?)  -- Exclude leads where powner is the current user
    ORDER BY l.created_at DESC
";



// Prepare the statement
if ($stmt = mysqli_prepare($link, $sql)) {
    // Bind the parameter for the current user ID
    mysqli_stmt_bind_param($stmt, 's', $user_id); // 's' specifies the type of the parameter as string

    // Execute the statement
    mysqli_stmt_execute($stmt);

    // Get the result
    $result = mysqli_stmt_get_result($stmt);

    // Fetch the results into an array
    if ($result) {
        $leads = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $leads[] = $row;
        }
    }

    // Close the statement
    mysqli_stmt_close($stmt);
}



// Get the last lead note time from the session
$last_lead_note_time = isset($_SESSION['last_lead_note_time']) ? $_SESSION['last_lead_note_time'] : 0;



if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign'])) {
            $lead_id = $_POST['assign'];
            $current_time = date('Y-m-d H:i:s');
        
            // Check if it's the first lead of the day
            $first_lead_query = "
                SELECT COUNT(*) as lead_count 
                FROM lead_assignment_logs 
                WHERE user_id = ? AND DATE(assigned_at) = DATE(?)";
        
            $lead_count = 0;
            if ($first_lead_stmt = mysqli_prepare($link, $first_lead_query)) {
                mysqli_stmt_bind_param($first_lead_stmt, "is", $user_id, $current_time);
                mysqli_stmt_execute($first_lead_stmt);
                mysqli_stmt_bind_result($first_lead_stmt, $lead_count);
                mysqli_stmt_fetch($first_lead_stmt);
                mysqli_stmt_close($first_lead_stmt);
            }

                // Calculate time difference
                if ($lead_count == 0) {
                    // First lead of the day, calculate time difference from 09:30 AM
                    $time_diff = strtotime($current_time) - strtotime(date('Y-m-d') . ' 09:30:00');
                } else {
                    // Not the first lead, calculate from the last assigned lead time
                    $last_assignment_query = "
                        SELECT assigned_at 
                        FROM lead_assignment_logs 
                        WHERE user_id = ? 
                        ORDER BY assigned_at DESC 
                        LIMIT 1";
            
                    if ($last_stmt = mysqli_prepare($link, $last_assignment_query)) {
                        mysqli_stmt_bind_param($last_stmt, "i", $user_id);
                        mysqli_stmt_execute($last_stmt);
                        mysqli_stmt_bind_result($last_stmt, $last_assigned_at);
                        mysqli_stmt_fetch($last_stmt);
                        mysqli_stmt_close($last_stmt);
            
                        if ($last_assigned_at) {
                            $time_diff = strtotime($current_time) - strtotime($last_assigned_at);
                        } else {
                            $time_diff = NULL;
                        }
                    }
                }
                    // Get current time
                $current_time = date("Y-m-d H:i:s");

                    // Get total leads with no owner
                    $sqlTotalLeads = "SELECT COUNT(*) AS total_leads FROM leads WHERE owner IS NULL";
                    $resultTotalLeads = $link->query($sqlTotalLeads);
                    $totalLeadsRow = $resultTotalLeads->fetch_assoc();
                    $totalLeads = $totalLeadsRow['total_leads'];
                
                    // Get total present users in sales role
                    $sqlPresentUsers = "SELECT COUNT(DISTINCT a.user_id) AS total_present_users
                                        FROM attendance a
                                        INNER JOIN users u ON a.user_id = u.id
                                        WHERE a.attendance_date = CURDATE()
                                          AND a.login_time IS NOT NULL
                                          AND u.role = 'Sales'";
                    $resultPresentUsers = $link->query($sqlPresentUsers);
                    $presentUsersRow = $resultPresentUsers->fetch_assoc();
                    $totalPresentUsers = $presentUsersRow['total_present_users'];
                
                    // Calculate target leads
                    $targetLeads = ($totalPresentUsers > 0) ? intval($totalLeads / $totalPresentUsers) : 0;
                
                    // Insert or update target leads into the justification table
                    $targetLeads = intval($targetLeads); // Ensure $targetLeads is an integer
                
                $sqlInsertJustification = "INSERT INTO justification (date, owner, name, target_leads)
                                           SELECT CURDATE(), u.id, u.first_name, $targetLeads
                                           FROM users u
                                           INNER JOIN attendance a ON u.id = a.user_id
                                           WHERE a.attendance_date = CURDATE()
                                             AND a.login_time IS NOT NULL
                                             AND u.role = 'Sales'
                                           ON DUPLICATE KEY UPDATE target_leads = VALUES(target_leads)";
                
                if ($link->query($sqlInsertJustification) === TRUE) {
                    // Update lead ownership and log assignment
                $sql = "UPDATE leads SET owner = ?, assigned_at = ? WHERE id = ? AND owner IS NULL";

                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ssi", $user_id, $current_time, $lead_id);
                
                    if (mysqli_stmt_execute($stmt)) {
                        // Log the assignment
                        $log_sql = "INSERT INTO lead_assignment_logs (user_id, lead_id, assigned_at, time_diff) VALUES (?, ?, ?, ?)";
                        if ($log_stmt = mysqli_prepare($link, $log_sql)) {
                            mysqli_stmt_bind_param($log_stmt, "iiss", $user_id, $lead_id, $current_time, $time_diff);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        }
                
                        // Increment assigned leads count in justification table
                        $update_assigned_leads_sql = "UPDATE justification 
                                                      SET assigned_leads = assigned_leads + 1
                                                      WHERE owner = ? 
                                                        AND date = CURDATE()";
                        if ($update_stmt = mysqli_prepare($link, $update_assigned_leads_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }
                
                        // Redirect to lead_action.php with the lead_id as a query parameter
                        header("Location: lead_action.php?id=" . $lead_id);
                        exit;
                    } else {
                        echo "Oops! Something went wrong. Please try again later.";
                    }
                
                    mysqli_stmt_close($stmt);
                }
                    echo "Target leads updated successfully.";
                } else {
                    echo "Error: " . $link->error . "<br>";
                    echo "SQL: " . $sqlInsertJustification;
                }



                
} else {
    // Check the last assignment time from lead_assignment_logs
    $last_assignment_query = "SELECT assigned_at FROM lead_assignment_logs WHERE user_id = ? ORDER BY assigned_at DESC LIMIT 1";

    if ($last_stmt = mysqli_prepare($link, $last_assignment_query)) {
        mysqli_stmt_bind_param($last_stmt, "i", $user_id);
        mysqli_stmt_execute($last_stmt);
        mysqli_stmt_bind_result($last_stmt, $last_assigned_at);
        mysqli_stmt_fetch($last_stmt);
        mysqli_stmt_close($last_stmt);

        if ($last_assigned_at) {
            // Calculate the time difference
            $last_time = strtotime($last_assigned_at);
            $countdown_duration = 1 * 60; // 1 minute
            if ($now - $last_time < $countdown_duration) {
                $future_time = $last_time + $countdown_duration;
                $countdown_active = true;
            }
        }
    }
}

$userId = $_SESSION['id'];
// Get total leads with no owner
$sqlTotalLeads = "SELECT COUNT(*) AS total_leads FROM leads WHERE owner IS NULL";
$resultTotalLeads = $link->query($sqlTotalLeads);
$totalLeadsRow = $resultTotalLeads->fetch_assoc();
$totalLeads = $totalLeadsRow['total_leads'];

// Get total present users in sales role
$sqlPresentUsers = "SELECT COUNT(DISTINCT a.user_id) AS total_present_users
                    FROM attendance a
                    INNER JOIN users u ON a.user_id = u.id
                    WHERE a.attendance_date = CURDATE()
                      AND a.login_time IS NOT NULL
                      AND u.role = 'Sales'";
$resultPresentUsers = $link->query($sqlPresentUsers);
$presentUsersRow = $resultPresentUsers->fetch_assoc();
$totalPresentUsers = $presentUsersRow['total_present_users'];

// Calculate target leads for the logged-in user
$targetLeads = ($totalPresentUsers > 0) ? intval($totalLeads / $totalPresentUsers) : 0;

// Get assigned leads today for the logged-in user
$sqlUserLeads = "SELECT COALESCE(COUNT(l.id), 0) AS assigned_leads_today
                 FROM users u
                 LEFT JOIN lead_assignment_logs l ON u.id = l.user_id 
                                                    AND DATE(l.assigned_at) = CURDATE()
                 WHERE u.id = ?";
$stmt = $link->prepare($sqlUserLeads);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultUserLeads = $stmt->get_result();
$userLeadsRow = $resultUserLeads->fetch_assoc();
$assignedLeadsToday = $userLeadsRow['assigned_leads_today'];


// Get the number of lead notes created today by the logged-in user
$sqlNotes = "SELECT COUNT(*) AS notes_created_today 
             FROM lead_notes 
             WHERE DATE(created_at) = CURDATE() 
               AND owner = ?";
$stmtNotes = $link->prepare($sqlNotes);
$stmtNotes->bind_param("i", $userId);
$stmtNotes->execute();
$resultNotes = $stmtNotes->get_result();
$notesRow = $resultNotes->fetch_assoc();
$notesCreatedToday = $notesRow['notes_created_today'];

$counter = 1;
ob_end_flush();
// Close database connection
// mysqli_close($link);



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_counter'])) {
    $current_time = date('Y-m-d H:i:s');

    // Check if the user already has a counter record
    $stmt = $link->prepare("SELECT * FROM counter WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update the existing record
        $stmt = $link->prepare("UPDATE counter SET counter_count = counter_count + 1, created_at = ? WHERE user_id = ? and user_id != ?");
        $stmt->bind_param("sii", $current_time, $userId,44);
    } else {
        // Insert a new record
        $stmt = $link->prepare("INSERT INTO counter (user_id, created_at, counter_count) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $userId, $current_time);
    }

    $stmt->execute();
    $stmt->close();
}




?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM System - Leads</title>
    <link rel="stylesheet" href="css/leads.css">
    <script>
    
    
     function preventBack() {
    window.history.forward();
  }
 preventBack();
        function callFetchScript() {
            fetch('fetch.php', {
                    method: 'GET'
                })
                .then(response => response.text())
                .then(text => console.log(text))
                .catch(error => console.error('Error:', error));
        }

        // Run the fetch script on page load
        window.addEventListener('load', callFetchScript);

        function callFetchScript2() {
            fetch('fetch2.php', {
                    method: 'GET'
                })
                .then(response => response.text())
                .then(text => console.log(text))
                .catch(error => console.error('Error:', error));
        }
        // Lead note check period in milliseconds (10 minutes)
        const LEAD_NOTE_CHECK_PERIOD = 10 * 60 * 1000; // 10 minutes in milliseconds
        
        // Get the last lead note time from PHP
        const lastLeadNoteTime = <?php echo $last_lead_note_time; ?> * 1000; // Convert to milliseconds

        // Function to check lead note activity
        function checkLeadNoteActivity() {
            const currentTime = Date.now();

            // If the last lead note was created more than 10 minutes ago, show an alert
            if (currentTime - lastLeadNoteTime >= LEAD_NOTE_CHECK_PERIOD) {
                alert("You haven't updated your leads in the last 10 minutes. Please update your lead notes.");
                 updateCounter();
            }
        }
        
        function updateCounter() {
                fetch('', {  // Empty string to call the same page
                    method: 'POST',
                    credentials: 'include',
                    body: new URLSearchParams({ 'update_counter': true }) // Send data to indicate counter update
                })
                .then(response => {
                    if (!response.ok) {
                        console.error('Failed to update counter');
                    }
                })
                .catch(error => console.error('Error:', error));
            }


        // Run the fetch script on page load
        window.addEventListener('load', callFetchScript2);
        // Only run the check if the user's role is Sales
        <?php if ($user_role == 'Sales'): ?>
            // Set interval to check lead note activity every 10 minutes
            setInterval(checkLeadNoteActivity, LEAD_NOTE_CHECK_PERIOD);

            // Also check once on page load
            checkLeadNoteActivity();
        <?php endif; ?>
    </script>
</head>

<body>
    
    
    <div class="heading">
    <h2>My Lead Targets</h2>
</div>
<div class="table">
    <table border="1" style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 16px; text-align: left; border-color: white;">
        <thead style="background-color: #f4f4f4; color: #333;">
            <tr>
                <th style="padding: 12px; border-bottom: 1px solid #ddd;">Assigned Leads Today</th>
                <th style="padding: 12px; border-bottom: 1px solid #ddd;">Target Leads</th>
                <th style="padding: 12px; border-bottom: 1px solid #ddd;">Available Leads</th>
                <th style="padding: 12px; border-bottom: 1px solid #ddd;">Leads updated Today</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background-color: #f9f9f9;">
                <td style="padding: 12px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($assignedLeadsToday); ?></td>
                <td style="padding: 12px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($targetLeads); ?></td>
                <td style="padding: 12px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($totalLeads); ?></td>
                <td style="padding: 12px; border-bottom: 1px solid #ddd;"><?php echo htmlspecialchars($notesCreatedToday); ?></td>
            </tr>
        </tbody>
    </table>
</div>
    
    
    
    <div class="container">
        <div class="leads-container">
    <h2>Leads</h2>
    <form action="showmyleads.php?id=<?php echo $lead['id']; ?>" method="get">
        <input type="submit" value="View My Leads" id="btn-assign" class="btn-view-my-leads">
    </form>
    <table>
        <thead>
            <tr>
                <th>S.NO</th>
                <th>Name</th>
                <th>Source</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $counter = 1; // Initialize the counter
            foreach ($leads as $lead):
                // Convert created_at to a timestamp
                $createdAt = strtotime($lead['created_at']);
                // Get the current time
                $currentTime = time();
                // Calculate the time difference in seconds
                $timeDiff = $currentTime - $createdAt;

                // Determine how to display the time difference
                if ($timeDiff < 3600) { // Less than 1 hour
                    $timeAgo = floor($timeDiff / 60) . ' minutes ago';
                } elseif ($timeDiff < 86400) { // Less than 1 day
                    $timeAgo = floor($timeDiff / 3600) . ' hours ago';
                } else { // 1 day or more
                    $timeAgo = floor($timeDiff / 86400) . ' days ago';
                }

                // Apply red color if the lead is older than 5 days (432000 seconds)
                $color = ($timeDiff > 432000) ? '#FFCCCB' : 'white'; // 5 days = 432000 seconds
            ?>
                <tr style="background-color: <?php echo $color; ?>;">
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo htmlspecialchars($lead['ad_name']); ?></td>
                    <td id="action-<?php echo $lead['id']; ?>">
                        <?php if (empty($lead['owner'])): ?>
                            <?php if (!$countdown_active || ($now > $future_time)): ?>
                                <form action="leads.php" method="post" style="display:inline;">
                                    <button type="submit" name="assign" value="<?php echo $lead['id']; ?>"
                                            class="btn-assign">Assign to Me</button>
                                </form>
                            <?php else: ?>
                                <span class="countdown" data-future-time="<?php echo $future_time; ?>">
                                    Wait for <span class="countdown-seconds"></span> seconds to assign lead
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


        <script>
    // Function to calculate and display the countdown timer
    function updateCountdown() {
        document.querySelectorAll('.countdown').forEach(function(countdownElement) {
            var futureTime = parseInt(countdownElement.getAttribute('data-future-time'));
            var now = Math.floor(Date.now() / 1000); // Current time in seconds
            var timeLeft = futureTime - now;
            var countdownSecondsElement = countdownElement.querySelector('.countdown-seconds');

            if (timeLeft > 0) {
                countdownSecondsElement.textContent = timeLeft;
            } else {
                countdownElement.textContent = 'You can now assign leads.';
                setTimeout(function() {
                    window.location.href = 'leads.php'; // Redirect to leads.php when countdown reaches 0
                }, 1000);
            }
        });
    }

    // Start the countdown timer on page load
    window.onload = function() {
        updateCountdown();
        setInterval(updateCountdown, 1000); // Update every second
    }
</script>





        <?php

        require_once 'methods/database.php';


        date_default_timezone_set('Asia/Kolkata');

        $user_id = $_SESSION['id'];
        $today = date('Y-m-d', strtotime('+1 day'));

        // Handle the date range selection
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '2000-01-01';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : $today;

        $sql = "SELECT l.id, l.name, l.email, l.phone, l.note, l.followup_date, l.followup_time, l.progress
        FROM leads l
        WHERE l.owner = ? 
          AND l.followup_date IS NOT NULL
          AND l.followup_date != ''
          AND l.followup_time IS NOT NULL
          AND l.followup_time != ''
          AND l.progress != 'Lost'
          AND l.progress != 'Converted'
          AND l.progress != 'Didnt Connect'
        ORDER BY l.followup_date ASC, l.followup_time ASC";

        $stmt = $link->prepare($sql);
        $stmt->bind_param('s', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $leads_to_check = [];
        while ($row = $result->fetch_assoc()) {
            $leads_to_check[] = $row;
        }
        $stmt->close();

        $to_notify = [];
        $to_alert = [];
        $now = new DateTime();

        $link->close();
        ?>

        <title>Sales Dashboard</title>
        <link rel="stylesheet" href="css/Sales_Dashboard.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tableRows = document.querySelectorAll('tr[data-id]');

                tableRows.forEach(function(row) {
                    row.addEventListener('click', function() {
                        var leadId = this.getAttribute('data-id');
                        window.location.href = 'lead_action.php?id=' + leadId;
                    });
                });
            });
        </script>





        <div class="Followup">
    <h2>Leads Follow-up Overdue</h2>
    <table border="1">
        <thead>
            <tr>
                <th>S.No</th>
                <th id="Name">Name</th>
                <th id="follow">Follow-Up Date</th>
                <th id="Follow-Up">Follow-Up Time</th>
                <th>Quality</th>
                <?php $sno =1;?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leads_to_check as $lead): ?>
                <?php
                $row_class = '';
                $today = date('Y-m-d');
                if ($today > $lead['followup_date'] || ( $today == $lead['followup_date'] && date('H:i') > $lead['followup_time'])) {
                    $row_class = 'missed-followup';
                } elseif ($today == $lead['followup_date']) {
                    $row_class = 'blinking';
                }
                ?>
                <tr class="<?php echo htmlspecialchars($row_class); ?>"
                    data-id="<?php echo htmlspecialchars($lead['id']); ?>">
                    <td><?php echo $sno; $sno+=1; ?></td>
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo htmlspecialchars($lead['followup_date']); ?></td>
                    <td><?php echo htmlspecialchars($lead['followup_time']); ?></td>
                    <td><?php echo htmlspecialchars($lead['form'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.blinking {
    animation: blink-animation 1s steps(5, start) infinite;
    color: darkred;
}

@keyframes blink-animation {
    to {
        visibility: hidden;
    }
}
</style>
        
    </div>


</body>

</html>