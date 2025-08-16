<?php
include('methods/database.php'); // Include your database connection

session_start(); // Start the session to get the user ID and name

// Function to credit 3 hours on the 1st of every month for both new and existing users
function creditMonthlyHours($link, $user_id, $employee_name) {
    if (date('j') == 1) { // It's the 1st of the month
        // Check if the user has any existing record
        $check_sql = "SELECT COUNT(*) FROM timely_leave WHERE user_id = ?";
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $record_count);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if ($record_count == 0) {
                // New user: Initialize with 3 hours balance
                $insert_sql = "INSERT INTO timely_leave (user_id, employee_name, leave_date, balance, created_at) 
                               VALUES (?, ?, CURDATE(), 3, NOW())";
                if ($insert_stmt = mysqli_prepare($link, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, 'iss', $user_id, $employee_name);
                    mysqli_stmt_execute($insert_stmt);
                    mysqli_stmt_close($insert_stmt);
                }
            } else {
                // Existing user: Check if the user has been credited this month
                $check_credit_sql = "SELECT MAX(leave_date) FROM timely_leave WHERE user_id = ? AND leave_date = CURDATE()";
                if ($stmt_credit = mysqli_prepare($link, $check_credit_sql)) {
                    mysqli_stmt_bind_param($stmt_credit, 'i', $user_id);
                    mysqli_stmt_execute($stmt_credit);
                    mysqli_stmt_store_result($stmt_credit);

                    // If no record found for today, credit 3 hours
                    if (mysqli_stmt_num_rows($stmt_credit) == 0) {
                        $update_sql = "UPDATE timely_leave SET balance = balance + 3 WHERE user_id = ? ORDER BY sno DESC LIMIT 1";
                        if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                            mysqli_stmt_bind_param($update_stmt, 'i', $user_id);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                    mysqli_stmt_close($stmt_credit);
                }
            }
        }
    }
}

// Check and credit monthly hours for users
if (isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
    $employee_name = $_SESSION['username']; // Assuming 'username' is stored in session
    creditMonthlyHours($link, $user_id, $employee_name);

    // Fetch the user's current balance
    $balance_sql = "SELECT balance FROM timely_leave WHERE user_id = ? ORDER BY sno DESC LIMIT 1";
    if ($stmt = mysqli_prepare($link, $balance_sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $balance);
        if (mysqli_stmt_fetch($stmt) === false || $balance === null) {
            // If no record found, assign default balance of 3 hours for new users
            $balance = 3;
        }
        mysqli_stmt_close($stmt);
    }

}

// Fetch all leave records for the current user
$leave_records = [];
$fetch_leaves_sql = "SELECT leave_date, from_time, to_time, reason, duration, created_at, balance FROM timely_leave WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt = mysqli_prepare($link, $fetch_leaves_sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Fetch all rows
    while ($row = mysqli_fetch_assoc($result)) {
        $leave_records[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['id']; // Get user ID from session
    $employee_name = $_SESSION['username']; // Get employee name from session

    // Get the data from the form
    $from_time = $_POST['fromTime'];
    $to_time = $_POST['toTime'];
    $reason = $_POST['leaveReason'];

    // Calculate duration between from_time and to_time
    $from = new DateTime($from_time);
    $to = new DateTime($to_time);
    $interval = $from->diff($to);
    $hours_taken = $interval->h + ($interval->i / 60); // Convert to hours

    // Check if user has enough balance
    if ($balance >= $hours_taken) {
        // Deduct the hours from the balance
        $new_balance = $balance - $hours_taken;

        // Insert the data into the 'timely_leave' table as a new record
        $sql = "INSERT INTO timely_leave (user_id, employee_name, leave_date, from_time, to_time, reason, duration, balance, created_at) 
                VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, NOW())";
        if ($stmt = mysqli_prepare($link, $sql)) {
            $duration = $interval->h . ' hr and ' . $interval->i . ' min';
            if($interval->h == 0){
                $duration = $interval->i .' ' . 'min';
            }
            mysqli_stmt_bind_param($stmt, 'issssss', $user_id, $employee_name, $from_time, $to_time, $reason, $duration, $new_balance);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Redirect to avoid form resubmission
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        echo "<script>document.getElementById('errorMessage').textContent = 'Insufficient balance. You have only $balance hours remaining.';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div class="container2">
        <div class="header">
            <!-- Include header -->
            <?php include "header.php"; ?>
        </div>

        <div class="body-container">
            <div class="left">
                <!-- Left sidebar (includes leaves_request.php) -->
                <?php include 'leaves_request.php'; ?>
            </div>

            <div class="right">
                <div class="Lapply">
                    <!-- Apply Leaves button -->
                    <button value="submit" onclick="openApplyForm()">Take Your Time</button>
                </div>

                <div class="inner-right">
                    <div class="outer-right">
                        <h3>Your Leave Records</h3>
                        <?php if (!empty($leave_records)): ?>
                            <table border="1" cellpadding="8" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Leave Date</th>
                                        <th>From Time</th>
                                        <th>To Time</th>
                                        <th>Reason</th>
                                        <th>Duration</th>
                                        <th>Created At</th>
                                        <th>Remaining Balance (hr:min)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leave_records as $record): ?>
                                        <tr>
                                            <!-- <td><?php echo $record['leave_date']; ?></td> -->
                                            <td>
                                                <?php 
                                                // Format leave_date
                                                $leave_date = new DateTime($record['leave_date']);
                                                echo $leave_date->format('M d'); // e.g., Oct 25, 2024
                                                ?>
                                            </td>
                                            <td><?php echo $record['from_time']; ?></td>
                                            <td><?php echo $record['to_time']; ?></td>
                                            <td><?php echo $record['reason']; ?></td>
                                            <td><?php echo $record['duration']; ?></td>
                                            <td><?php echo $record['created_at']; ?></td>
                                            <td><?php echo $record['balance']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No leave records found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popup form for applying timely leave -->
        <div class="popup" id="applyLeaveForm">
            <div class="pop-container">
                <h2>Apply Timely Leave <span class="close-btn" onclick="closeApplyForm()">&times;</span></h2>

                <!-- Form to submit the leave request -->
                <form id="leaveRequestForm" method="POST" action="">
                    <div class="days-info">
                        <div>Timely Leave Balance: <span id="timelyLeaveBalance"><?php echo isset($balance) ? $balance : '3'; ?></span> hours</div>
                    </div>

                    <div class="date-picker">
                        <div>
                            <label for="fromTime">From:</label>
                            <input type="time" id="fromTime" name="fromTime" value="09:30" required>
                        </div>

                        <div>
                            <label for="toTime">To:</label>
                            <input type="time" id="toTime" name="toTime" required>
                        </div>
                    </div>

                    <div class="leave-status" id="leaveApplicationInfo">Time off For: </div>
                    <div class="error-msg" id="errorMessage" style="color:red; font-weight:bold;"></div>

                    <textarea id="leaveReason" name="leaveReason" placeholder="Leave Reason"></textarea>

                    <button type="submit">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openApplyForm() {
            document.getElementById('applyLeaveForm').style.display = 'flex';
        }

        function closeApplyForm() {
            document.getElementById('applyLeaveForm').style.display = 'none';
        }

        // Function to calculate duration between fromTime and toTime
        function calculateDuration() {
            const fromTime = document.getElementById('fromTime').value;
            const toTime = document.getElementById('toTime').value;
            const leaveApplicationInfo = document.getElementById('leaveApplicationInfo');
            const errorMessage = document.getElementById('errorMessage');

            if (fromTime && toTime) {
                const from = new Date(`1970-01-01T${fromTime}:00`);
                const to = new Date(`1970-01-01T${toTime}:00`);

                if (to <= from) {
                    errorMessage.textContent = 'To Time must be later than From Time.';
                    leaveApplicationInfo.textContent = 'Time off For: Invalid time range';
                    return false;
                } else {
                    errorMessage.textContent = ''; // Clear error
                }

                const diffMs = to - from;
                const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

                leaveApplicationInfo.textContent = `Time off For: ${diffHours} hr : ${diffMinutes} minutes`;

                const balance = parseFloat(document.getElementById('timelyLeaveBalance').textContent);
                if (diffHours + diffMinutes / 60 > balance) {
                    errorMessage.textContent = 'Insufficient balance. Be in your limits.';
                    return false;
                } else {
                    errorMessage.textContent = ''; // Clear error
                    return true;
                }
            } else {
                leaveApplicationInfo.textContent = 'Time off For:';
                return false;
            }
        }

        document.getElementById('fromTime').addEventListener('change', calculateDuration);
        document.getElementById('toTime').addEventListener('change', calculateDuration);

        document.getElementById('leaveRequestForm').addEventListener('submit', function (event) {
            const fromTime = document.getElementById('fromTime').value;
            const toTime = document.getElementById('toTime').value;

            if (fromTime >= toTime) {
                event.preventDefault();
                document.getElementById('errorMessage').textContent = 'To Time must be later than From Time.';
                return;
            }

            if (!calculateDuration()) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
