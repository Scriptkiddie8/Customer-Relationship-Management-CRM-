<?php
include('methods/database.php'); // Include your database connection

session_start(); // Start the session to get the user ID and name

// Fetch all employee names for the dropdown filter
$employee_names = [];
$fetch_employees_sql = "SELECT DISTINCT employee_name FROM timely_leave ORDER BY employee_name ASC";
if ($stmt = mysqli_prepare($link, $fetch_employees_sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Fetch all employee names
    while ($row = mysqli_fetch_assoc($result)) {
        $employee_names[] = $row['employee_name'];
    }
    mysqli_stmt_close($stmt);
}

// Fetch all records from the timely_leave table
$leave_records = [];
$fetch_leaves_sql = "SELECT employee_name, leave_date, from_time, to_time, reason, duration, created_at, balance FROM timely_leave ORDER BY created_at DESC";
if ($stmt = mysqli_prepare($link, $fetch_leaves_sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Fetch all rows
    while ($row = mysqli_fetch_assoc($result)) {
        $leave_records[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard</title>
    <link rel="stylesheet" href="css/attendance_dashboard.css"> <!-- Optional: You can link specific CSS for this page -->
</head>
<body>

<div class="main-content">
    <?php include 'header.php'; ?>
</div>

<div class="left">
    <?php include 'leaves_request.php'; ?>
</div>

<div class="right">
    <div class="filter-option">
        <label for="employee-filter">Filter by Employee:</label>
        <select id="employee-filter" onchange="filterByEmployee()">
            <option value="all">All Employees</option>
            <?php foreach ($employee_names as $employee): ?>
                <option value="<?php echo htmlspecialchars($employee); ?>"><?php echo htmlspecialchars($employee); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Display the leave data in a table -->
    <div class="leave-records">
        <h3>Leave Records</h3>

        <?php if (!empty($leave_records)): ?>
            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Reason</th>
                        <th>Duration</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($leave_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                                <td>
                                    <?php 
                                    // Format leave_date
                                    $leave_date = new DateTime($record['leave_date']);
                                    echo $leave_date->format('M d'); // e.g., Oct 25, 2024
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($record['from_time']); ?></td>
                                <td><?php echo htmlspecialchars($record['to_time']); ?></td>
                                <td><?php echo htmlspecialchars($record['reason']); ?></td>
                                <td><?php echo htmlspecialchars($record['duration']); ?></td>
                                <td><?php echo htmlspecialchars($record['balance']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                </tbody>

            </table>
        <?php else: ?>
            <p>No leave records found.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    // Add filtering logic for employee name
    function filterByEmployee() {
        const filterValue = document.getElementById('employee-filter').value;
        const rows = document.querySelectorAll('.leave-records tbody tr');
        
        rows.forEach(row => {
            const employeeName = row.cells[0].textContent;
            if (filterValue === 'all' || employeeName === filterValue) {
                row.style.display = ''; // Show the row
            } else {
                row.style.display = 'none'; // Hide the row
            }
        });
    }
</script>

</body>
</html>
