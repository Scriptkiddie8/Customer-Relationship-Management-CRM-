<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Set user role and other session variables
$user_role = isset($_SESSION["role"]) ? $_SESSION["role"] : '';
$team_leader = isset($_SESSION["team_leader"]) ? $_SESSION["team_leader"] : '';
$needs_justification = isset($_SESSION['needs_justification']) && $_SESSION['needs_justification'] === true;
if($needs_justification){
        header("location: justification.php");

}


// Initialize navigation links based on user role
$nav_links = [];

if ($user_role == 'Admin') {
    $nav_links = [
        'Dashboard' => 'dashboard.php',
        'Leads' => 'adminleads.php',
        'Import Leads' => 'import_leads.php',
        'Lead Transfer' => 'transfer_leads.php',
        'Operations' => 'admin_dashboard.php',
        'Attendance' => 'attendance.php',
        'Users' => 'user_management.php',
        'Monthly Report' => 'monthly_report.php',
        // 'Monthly Report Crm' => 'monthly_report_crm.php',
        'crm report'=>'monthly_reportoperations.php',
        'Client Project' => 'project_view.php',
        'Leaves' => 'leave_approval.php',
        'Logout' => 'logout.php'
        // 'Change Password' => 'settings.php',
    ];
} elseif ($user_role == 'Sales') {
    $nav_links = [
        'Payment confirmation' => 'payment_confirmation.php',
        'Payment History' => 'payment_history.php',
        'Leads' => 'leads.php',
        'My Leads' => 'showmyleads.php',
        'Add Lead' => 'add_lead.php',
        'Change Password' => 'settings.php',
        'Leaves' => 'myleaves.php',
        'Logout' => 'user_logout.php'
    ];
}elseif ($team_leader == 1) {
    $nav_links = [
        'Dashboard' => 'project_dashboard.php',
        'Create New Project' => 'create_project.php',
        'Manage Subtasks' => 'subtask_management.php',
        'Track Time' => 'time_tracking.php',
        'Change Password' => 'settings.php',
        'Leaves' => 'myleaves.php',
        'Logout' => 'user_logout.php'
    ];
}

elseif ($user_role == 'User') {
    $nav_links = [
        'Dashboard' => 'project_dashboard.php',
        'Manage Subtasks' => 'subtask_management.php',
        'Track Time' => 'time_tracking.php',
        'Change Password' => 'settings.php',
        'Leaves' => 'myleaves.php',
        'Logout' => 'user_logout.php'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/header.css"> <!-- Link to your CSS file for styling -->
    <link rel="stylesheet" href="css/navbar.css"> <!-- Link to the Navbar CSS file -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <nav id="navbar">
        <div class="navbar-container">
            <?php if ($user_role == 'Admin') { ?>
            <a href="dashboard.php" class="navbar-logo">CRM System</a>
            <?php } elseif ($user_role == 'Sales') { ?>
            <a href="leads.php" class="navbar-logo">CRM System</a>
            <?php } else { ?>
            <a href="project_dashboard.php" class="navbar-logo">CRM System</a>
            <?php } ?>
            <ul class="navbar-menu">
                <?php foreach ($nav_links as $name => $link): ?>
                <li><a href="<?php echo htmlspecialchars($link); ?>"><?php echo htmlspecialchars($name); ?></a></li>
                <?php endforeach; ?>
                <?php if ($needs_justification): ?>
                <li><a href="justification.php">Submit Justification</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Rest of your page content -->
</body>

</html>