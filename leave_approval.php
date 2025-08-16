<?php
// Include the database connection
include('methods/database.php');

// Check if the form is submitted to approve or reject the leave request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sno']) && isset($_POST['status'])) {
    $sno = mysqli_real_escape_string($link, $_POST['sno']);
    $new_status = mysqli_real_escape_string($link, $_POST['status']);

    // Update the status of the leave request using sno instead of id
    $update_sql = "UPDATE leave_requests SET status = '$new_status' WHERE sno = $sno";
    
    if (mysqli_query($link, $update_sql)) {
        // Redirect to the same page to reflect the changes
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error updating record: " . mysqli_error($link);
    }
}

// Initialize default filters (show all by default)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // Date range filter
$status = isset($_GET['status']) ? $_GET['status'] : 'all';  // Status filter

// Get the selected employee from the query parameters
$selected_employee = isset($_GET['employee']) ? $_GET['employee'] : 'all';

// Set SQL query based on the selected employee
$sql = "SELECT * FROM leave_requests WHERE 1=1"; // Initial base query

// Determine the date range for filtering based on the selected filter
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_week_start = date('Y-m-d', strtotime('monday this week'));
$this_week_end = date('Y-m-d', strtotime('sunday this week'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end = date('Y-m-d', strtotime('sunday last week'));
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('first day of last month'));
$last_month_end = date('Y-m-t', strtotime('last day of last month'));

// Apply the date filter based on the selected filter
if ($filter == 'today') {
    $sql .= " AND from_date = '$today'";
} elseif ($filter == 'yesterday') {
    $sql .= " AND from_date = '$yesterday'";
} elseif ($filter == 'this-week') {
    $sql .= " AND from_date BETWEEN '$this_week_start' AND '$this_week_end'";
} elseif ($filter == 'last-week') {
    $sql .= " AND from_date BETWEEN '$last_week_start' AND '$last_week_end'";
} elseif ($filter == 'this-month') {
    $sql .= " AND from_date BETWEEN '$this_month_start' AND '$this_month_end'";
} elseif ($filter == 'last-month') {
    $sql .= " AND from_date BETWEEN '$last_month_start' AND '$last_month_end'";
}

// Apply the employee filter if a specific employee is selected
if ($selected_employee !== 'all') {
    $sql .= " AND employee_name = '$selected_employee'";
}

// Apply the status filter based on the selected status
if ($status == 'pending') {
    $sql .= " AND status = 'pending'";
} elseif ($status == 'approved') {
    $sql .= " AND status = 'approved'";
} elseif ($status == 'rejected') {
    $sql .= " AND status = 'rejected'";
}

// Order by 'created_at' in descending order
$sql .= " ORDER BY created_at DESC";

// Execute the main query to fetch leave requests
$result = mysqli_query($link, $sql);

// Check for errors in the query execution
if (!$result) {
    die("Error executing query: " . mysqli_error($link));
}

// Check if the result has any rows
if (mysqli_num_rows($result) == 0) {
    $result = null;
}

// Fetch counts for the status summary (based on current filter)
$base_count_sql = "SELECT COUNT(*) as count FROM leave_requests WHERE 1=1";

// Apply employee and date filters for count queries
if ($selected_employee !== 'all') {
    $base_count_sql .= " AND employee_name = '$selected_employee'";
}
if ($filter == 'today') {
    $base_count_sql .= " AND from_date = '$today'";
} elseif ($filter == 'yesterday') {
    $base_count_sql .= " AND from_date = '$yesterday'";
} elseif ($filter == 'this-week') {
    $base_count_sql .= " AND from_date BETWEEN '$this_week_start' AND '$this_week_end'";
} elseif ($filter == 'last-week') {
    $base_count_sql .= " AND from_date BETWEEN '$last_week_start' AND '$last_week_end'";
} elseif ($filter == 'this-month') {
    $base_count_sql .= " AND from_date BETWEEN '$this_month_start' AND '$this_month_end'";
} elseif ($filter == 'last-month') {
    $base_count_sql .= " AND from_date BETWEEN '$last_month_start' AND '$last_month_end'";
}

// Fetch count for all leave requests
$all_result = mysqli_query($link, $base_count_sql);
$all_count = mysqli_fetch_assoc($all_result)['count'] ?? 0;

// Fetch count for pending leave requests
$pending_result = mysqli_query($link, $base_count_sql . " AND status = 'pending'");
$pending_count = mysqli_fetch_assoc($pending_result)['count'] ?? 0;

// Fetch count for approved leave requests
$approved_result = mysqli_query($link, $base_count_sql . " AND status = 'approved'");
$approved_count = mysqli_fetch_assoc($approved_result)['count'] ?? 0;

// Fetch count for rejected leave requests
$rejected_result = mysqli_query($link, $base_count_sql . " AND status = 'rejected'");
$rejected_count = mysqli_fetch_assoc($rejected_result)['count'] ?? 0;

// Fetch distinct employee names for the dropdown
$employee_result = mysqli_query($link, "SELECT DISTINCT employee_name FROM leave_requests");

if (!$employee_result) {
    die("Error fetching employee names: " . mysqli_error($link));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals</title>
    <style>
        /* Your CSS here */
    </style>
</head>

<body>
    <header class="main-content">
        <?php include "header.php"; ?>
    </header>

    <div class="main-container">
        <div>
            <div class="left">
                <h2>Leave <span style="color: black;">Management</span></h2>
                <?php include 'leaves_request.php'; ?>
            </div>

            <div class="right">
                <!-- Filter Section -->
                <div class="filter-section">
                    <button class="filter-btn" onclick="filterLeaves('all')">All Time</button>
                    <button class="filter-btn" onclick="filterLeaves('today')">Today</button>
                    <button class="filter-btn" onclick="filterLeaves('yesterday')">Yesterday</button>
                    <button class="filter-btn" onclick="filterLeaves('this-week')">This Week</button>
                    <button class="filter-btn" onclick="filterLeaves('last-week')">Last Week</button>
                    <button class="filter-btn" onclick="filterLeaves('this-month')">This Month</button>
                    <button class="filter-btn" onclick="filterLeaves('last-month')">Last Month</button>
                    <button class="filter-btn" id="custom-btn">Custom</button>
                </div>

                <!-- Employee Filter Dropdown -->
                <div class="filter-option">
                    <select id="employee-filter" onchange="filterByEmployee()">
                        <option value="all">All Employees</option>
                        <?php while ($employee = mysqli_fetch_assoc($employee_result)): ?>
                            <option value="<?php echo $employee['employee_name']; ?>" 
                                <?php echo ($selected_employee == $employee['employee_name']) ? 'selected' : ''; ?>>
                                <?php echo $employee['employee_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="tab-container">
                    <div class="tab active">Leave Applications</div>
                    <div class="tab">Regularization</div>
                </div>

                <!-- Status Summary Section -->
                <div class="status-summary">
                    <div class="status-item all" onclick="filterStatus('all')">
                        <i class="fas fa-list"></i>
                        <i class='bx bx-list-ul'></i>
                        <span>All :</span> <span class="count"><?php echo $all_count; ?></span>
                    </div>
                    <div class="status-item pending" onclick="filterStatus('pending')">
                        <i class="fas fa-clock"></i>
                        <i class='bx bxs-pin' style="color: #ffbb55;"></i>
                        <span>Pending :</span> <span class="count"><?php echo $pending_count; ?></span>
                    </div>
                    <div class="status-item approved" onclick="filterStatus('approved')">
                        <i class="fas fa-check"></i>
                        <i class='bx bxs-check-square' style="color: #32c766;"></i>
                        <span>Approved :</span> <span class="count"><?php echo $approved_count; ?></span>
                    </div>
                    <div class="status-item rejected" onclick="filterStatus('rejected')">
                        <i class="fas fa-times"></i>
                        <i class='bx bx-x-circle' style="color: #ff7782;"></i>
                        <span>Rejected :</span> <span class="count"><?php echo $rejected_count; ?></span>
                    </div>
                </div>

                <!-- Leave List -->
                <div class="inner-right">
                    <div class="outer-right">
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <div class="leave-list">
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <div class="leave-item">
                                <div class="leave-type">
                                    <div><?php echo $row['employee_name']; ?></div>
                                    <div><?php echo $row['leave_type']; ?></div> 
                                    <div>
                                        <span>From:</span>
                                         <?php 
                                        // Format the from_date to "Oct 9, 2024"
                                        echo date('M j, Y', strtotime($row['from_date'])); 
                                        ?>
                                    </div>  
                                    <div>
                                        <span>to:</span>
                                        <?php 
                                        // Format the to_date to "Oct 9, 2024"
                                        echo date('M j, Y', strtotime($row['to_date'])); 
                                        ?>
                                    </div>

                                    <!-- View Reason button inside the leave-item div -->
                                    <button value="submit" class="view-reason" onclick="openApplyForm('<?php echo addslashes($row['reason']); ?>')"><p>View Reason</p></button>

                                </div>

                                <!-- Show status dynamically -->
                                <div class="status-dynamic">
                                    <div class="status <?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </div>

                                    <!-- Show buttons for rejected and pending statuses -->
                                    <?php if ($row['status'] == 'pending' || $row['status'] == 'rejected'): ?>
                                        <div class="actions">
                                            <form method="POST" action="">
                                                <input type="hidden" name="sno" value="<?php echo $row['sno']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button class="approve-btn" type="submit" name="approve">Approve</button>
                                            </form>
                                            
                                            <!-- Only show the Reject button if the status is 'pending' -->
                                            <?php if ($row['status'] == 'pending'): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="sno" value="<?php echo $row['sno']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button class="reject-btn" type="submit" name="reject">Reject</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <p>No leave requests found for this Duration.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="popup" id="reviewreason">
    <div class="pop-container">
        <h4 style="color:#677483; font-weight:normal;">Reason:<span class="close-btn" onclick="closeApplyForm()">&times;</span>
        </h4>
        <h1 id="reason-text">No reason provided</h2> <!-- This will be updated dynamically -->
    </div>
</div>


    <script>
        // Function to apply the selected filter and highlight the clicked status item
        function filterLeaves(filter) {
            const status = new URLSearchParams(window.location.search).get('status') || 'all';
            const employee = new URLSearchParams(window.location.search).get('employee') || 'all';
            window.location.href = "?filter=" + filter + "&status=" + status + "&employee=" + employee;
        }

        // Function to apply the status filter within the selected date range
        function filterStatus(status) {
            const filter = new URLSearchParams(window.location.search).get('filter') || 'all';
            const employee = new URLSearchParams(window.location.search).get('employee') || 'all';
            window.location.href = "?filter=" + filter + "&status=" + status + "&employee=" + employee;
        }

        // Function to filter by employee
        function filterByEmployee() {
            const employeeName = document.getElementById('employee-filter').value;
            const filter = new URLSearchParams(window.location.search).get('filter') || 'all';
            const status = new URLSearchParams(window.location.search).get('status') || 'all';
            window.location.href = "?filter=" + filter + "&status=" + status + "&employee=" + employeeName;
        }

        // Viewing reason
        // Open the apply form and set the reason text dynamically
        function openApplyForm(reason) {
            document.getElementById('reviewreason').style.display = 'flex';
            document.getElementById('reason-text').innerText = reason ? reason : 'No reason provided';
        }

        // Function to close the apply reason form
        function closeApplyForm() {
            document.getElementById('reviewreason').style.display = 'none';
        }

        // Automatically highlight the active button on page load based on the URL filter
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const currentFilter = urlParams.get('filter') || 'all'; // Default to 'all'
            const currentStatus = urlParams.get('status') || 'all'; // Default to 'all'
            const currentEmployee = urlParams.get('employee') || 'all'; // Default to 'all'

            // Highlight the active filter button
            document.querySelectorAll('.filter-btn').forEach(button => {
                button.classList.remove('active'); // Remove active class from all buttons

                // If the button's onclick attribute contains the current filter, mark it as active
                if (button.getAttribute('onclick').includes(currentFilter)) {
                    button.classList.add('active');
                }
            });

            // Highlight the active status button
            document.querySelectorAll('.status-item').forEach(button => {
                button.classList.remove('active'); // Remove active class from all status items

                // Add active class to the status item that matches the current status
                if (button.getAttribute('onclick').includes(currentStatus)) {
                    button.classList.add('active');
                }
            });

            // Set the correct employee in the dropdown
            document.getElementById('employee-filter').value = currentEmployee;
        });
    </script>
</body>

</html>
