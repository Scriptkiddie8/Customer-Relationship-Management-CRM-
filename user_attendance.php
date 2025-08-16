<?php
// Include the database connection
include 'database.php';

// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Admin') {
    header("Location: login.php"); // Redirect to login if not admin
    exit();
}

// Get user ID from URL
$user_id = $_GET['user_id'] ?? null;
if (!$user_id) {
    die("Invalid user ID.");
}

// Fetch user details
$sql = "SELECT first_name, last_name FROM users WHERE id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch attendance records for the user
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$sql = "SELECT login_time, logout_time FROM attendance WHERE user_id = ? AND YEAR(login_time) = ? AND MONTH(login_time) = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("iii", $user_id, $year, $month);
$stmt->execute();
$attendance_result = $stmt->get_result();

// Create an array to store attendance data
$attendance_days = [];
while ($row = $attendance_result->fetch_assoc()) {
    $day = date('j', strtotime($row['login_time']));
    $attendance_days[$day] = $row['logout_time'] ? 'present' : 'absent';
}

// Function to generate calendar
function generate_calendar($year, $month, $attendance_days) {
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $first_day_of_month = date('w', strtotime("$year-$month-01"));
    
    $calendar = '<table border="1"><tr>';
    $days_of_week = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    // Header row for days of the week
    foreach ($days_of_week as $day) {
        $calendar .= "<th>$day</th>";
    }
    $calendar .= '</tr><tr>';
    
    // Empty cells for days before the first day of the month
    for ($i = 0; $i < $first_day_of_month; $i++) {
        $calendar .= '<td></td>';
    }
    
    // Days of the month
    for ($day = 1; $day <= $days_in_month; $day++) {
        $class = isset($attendance_days[$day]) ? $attendance_days[$day] : 'absent';
        $calendar .= "<td class='$class'>$day</td>";
        
        // Start a new row after Saturday
        if (($day + $first_day_of_month) % 7 == 0) {
            $calendar .= '</tr><tr>';
        }
    }
    
    // Fill in the remaining empty cells in the last row
    while (($day + $first_day_of_month) % 7 != 1) {
        $calendar .= '<td></td>';
        $day++;
    }
    
    $calendar .= '</tr></table>';
    return $calendar;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance for <?= $user['first_name'] . ' ' . $user['last_name']; ?></title>
    <style>
        .present { background-color: green; color: white; }
        .absent { background-color: red; color: white; }
    </style>
</head>
<body>
    <h1>Attendance for <?= $user['first_name'] . ' ' . $user['last_name']; ?></h1>
    
    <form method="GET" action="user_attendance.php">
        <input type="hidden" name="user_id" value="<?= $user_id; ?>">
        <label for="month">Month:</label>
        <select name="month" id="month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m; ?>" <?= $m == $month ? 'selected' : ''; ?>>
                    <?= date('F', mktime(0, 0, 0, $m, 1)); ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="year">Year:</label>
        <select name="year" id="year">
            <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                <option value="<?= $y; ?>" <?= $y == $year ? 'selected' : ''; ?>><?= $y; ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit">View</button>
    </form>

    <?= generate_calendar($year, $month, $attendance_days); ?>

    <h2>Attendance Analytics</h2>
    <!-- Analytics and Graphs would be generated here -->
</body>
</html>
