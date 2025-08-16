<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in and has Admin role
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] !== 'Admin') {
    header("location: index.php");
    exit;
}

// Get user ID
$user_id = $_SESSION["id"];

// Initialize variables
$attendances = [];
$users = [];

// Fetch all users
$sql_users = "SELECT id, username FROM users WHERE role != 'Admin'";
if ($stmt_users = mysqli_prepare($link, $sql_users)) {
    mysqli_stmt_execute($stmt_users);
    mysqli_stmt_bind_result($stmt_users, $id, $username);
    while (mysqli_stmt_fetch($stmt_users)) {
        $users[] = [
            'id' => $id,
            'username' => $username
        ];
    }
    mysqli_stmt_close($stmt_users);
}

// Fetch attendance data
$sql_attendance = "SELECT user_id, date, status FROM attendance WHERE date = CURDATE()";
if ($stmt_attendance = mysqli_prepare($link, $sql_attendance)) {
    mysqli_stmt_execute($stmt_attendance);
    mysqli_stmt_bind_result($stmt_attendance, $user_id, $date, $status);
    while (mysqli_stmt_fetch($stmt_attendance)) {
        $attendances[] = [
            'user_id' => $user_id,
            'date' => $date,
            'status' => $status
        ];
    }
    mysqli_stmt_close($stmt_attendance);
}

// Handle attendance update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_attendance"])) {
    $user_id = $_POST["user_id"];
    $status = $_POST["status"];

    $sql_update = "INSERT INTO attendance (user_id, date, status) VALUES (?, CURDATE(), ?) ON DUPLICATE KEY UPDATE status = ?";
    if ($stmt_update = mysqli_prepare($link, $sql_update)) {
        mysqli_stmt_bind_param($stmt_update, "iss", $user_id, $status, $status);
        if (mysqli_stmt_execute($stmt_update)) {
            echo "Attendance updated successfully!";
        } else {
            echo "Error updating attendance: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt_update);
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance</title>
    <link rel="stylesheet" href="css/manage_attendance.css">
</head>
<body>
<div class="container">
    <h1>Manage Attendance</h1>

    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td>
                        <?php
                        $attendance = array_filter($attendances, function($att) use ($user) {
                            return $att['user_id'] == $user['id'];
                        });
                        echo htmlspecialchars($attendance ? array_shift($attendance)['status'] : 'Not Recorded');
                        ?>
                    </td>
                    <td>
                        <form action="manage_attendance.php" method="post">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                            <select name="status" required>
                                <option value="">Select Status</option>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                            </select>
                            <button type="submit" name="update_attendance">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
