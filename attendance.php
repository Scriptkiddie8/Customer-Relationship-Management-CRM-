<?php include "header.php"; ?>
<?php
require_once 'methods/database.php';

// Check if the user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: index.php");
    exit();
}

// Fetch all users
$sql = "SELECT id, first_name, last_name FROM users ";
$result = $link->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - Admin</title>
    <link rel="stylesheet" href="css/attendance.css">
</head>

<body>
    <div class="container">
        <h2 class="page-title">Attendance Records</h2>
        <div class="table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>View Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><a href="attendance_calendar.php?user_id=<?php echo $row['id']; ?>" class="view-btn">View
                                Calendar</a></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="2">No users found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>