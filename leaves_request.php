<?php
// Check if session is not already started, then start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Get user role from session
$user_role = isset($_SESSION["role"]) ? $_SESSION["role"] : '';

// Define the navigation links and icons based on user role
$nav_links = [];

if ($user_role == 'Admin') {
    $nav_links = [
        'Approvals' => ['link' => 'leave_approval.php', 'icon' => 'bx bx-message-rounded-dots'],
        'Holidays' => ['link' => 'holiday.php', 'icon' => 'bx bx-child'],
        'Timely Leaves' => ['link' => 'leaves_timely.php', 'icon' => 'bx bx-cheese'],
        'Setting' => ['link' => 'leave_setting.php', 'icon' => 'bx bx-cog'],
    ];
} else {
    // Default to 'User' role
    $nav_links = [
        'My Leaves' => ['link' => 'myleaves.php', 'icon' => 'bx bx-cheese'],    
        'Timely Leave' => ['link' => 'meri_attendance.php', 'icon' => 'bx bx-child'],
        'Holiday' => ['link' => 'holiday.php', 'icon' => 'bx bx-analyse'],
        'Approvals' => ['link' => 'user_leave_approve.php', 'icon' => 'bx bx-message-rounded-dots'],
        'setting' => ['link' => 'leave_sett.php', 'icon' => 'bx bx-message-rounded-dots'],
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Dashboard</title>
    <link rel="stylesheet" href="css/leaves_request.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        .sidebar a.active {
            background-color: #f0f0f0;
            color: #ff0000; /* Active link color */
        }
    </style>
</head>
<body>
    <div class="body-container">
        <div class="container">
            <aside>
                <div class="top">
                    <div class="logo">
                        <h2>Lea<span class="danger">ve</span></h2>
                        <h2>Mana<span class="primary">gement</span></h2>
                    </div>
                    <div class="close" id="close-btn">
                        <i class='bx bx-x'></i>
                    </div>
                </div>
                <div class="sidebar">
                    <?php foreach ($nav_links as $name => $data): ?>
                        <a href="<?php echo htmlspecialchars($data['link']); ?>" class="<?php echo ($current_page == basename($data['link'])) ? 'active' : ''; ?>">
                            <i class='<?php echo htmlspecialchars($data['icon']); ?>'></i>
                            <h3><?php echo htmlspecialchars($name); ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
