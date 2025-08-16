<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "header.php";
require_once 'methods/database.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] != "Admin") {
    header("location: index.php");
    exit;
}

date_default_timezone_set('Asia/Kolkata');

$admin_email = 'nitishkapoor4321@gmail.com';
$today = date('Y-m-d', strtotime('+1 day'));

// Handle the date range selection
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : $today;


// Fetch all leads from the leads table for follow-up within the selected date range
$sql2 = "SELECT l.id, l.name, l.email, l.phone, l.note, l.followup_date, l.followup_time, l.progress, u.first_name AS owner_name
        FROM leads l
        LEFT JOIN users u ON l.owner = u.id
        WHERE l.followup_date IS NOT NULL
          AND l.followup_date != ''
          AND l.followup_time IS NOT NULL
          AND l.followup_time != ''
          AND l.progress != 'Lost'
          AND l.progress != 'Converted'
          AND l.progress != 'Didnt Connect'
          AND l.progress != 'Didn\'t Connect'
          AND l.progress != 'Fresh Lead'
          AND l.owner IS NOT NULL
        ORDER BY l.followup_date ASC, l.followup_time ASC";



$stmt = $link->prepare($sql2);
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

// Check for notifications and alerts
foreach ($leads_to_check as $lead) {
    $followup_datetime = new DateTime($lead['followup_date'] . ' ' . $lead['followup_time']);

    if ($now > $followup_datetime && $now->diff($followup_datetime)->i <= 60 && $lead['progress'] === 'Follow-up') {
        $to_notify[] = $lead;
    }

    if ($followup_datetime->diff($now)->i <= 5 && $followup_datetime > $now) {
        $to_alert[] = $lead;
    }
}

// Send notifications
if (!empty($to_notify)) {
    $subject = 'Leads Follow-Up Status Notification';
    $message = "The following leads have scheduled follow-ups today and their progress has not changed within an hour of the scheduled time:\n\n";
    // Email sending logic here
}


// Define an array to hold the progress counts
$progress_counts = [
    'Lost' => 0,
    'Not Interested' => 0,
    'Follow-up' => 0,
    'Buys' => 0,
    'Didnt Connect' => 0,
    'First Call Done' => 0,
    'Quote Sent' => 0,
    'Converted' => 0,
    'Fresh Lead' => 0
];

// Prepare and execute the SQL query
$sql = "SELECT progress FROM leads WHERE created_at BETWEEN ? AND ?";
$stmt = $link->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($link->error));
}

$stmt->bind_param('ss', $start_date, $end_date);
if (!$stmt->execute()) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}

$result = $stmt->get_result();
if ($result === false) {
    die('Get result failed: ' . htmlspecialchars($stmt->error));
}

// Fetch and count the progress values
while ($row = $result->fetch_assoc()) {
    $progress = $row['progress'];
    if (array_key_exists($progress, $progress_counts)) {
        $progress_counts[$progress]++;
    }
}


// Fetch productivity data for each user with metrics for assigned, converted, and lost leads within the selected date range
// Fetch productivity data for each user with metrics for all categories within the selected date range
$sql = "SELECT u.first_name,
               SUM(CASE WHEN l.progress = 'All Leads' THEN 1 ELSE 1 END) AS all_leads,
               SUM(CASE WHEN l.progress = 'Lost' THEN 1 ELSE 0 END) AS lost_leads,
               SUM(CASE WHEN l.progress = 'Not Interested' THEN 1 ELSE 0 END) AS not_interested_leads,
               SUM(CASE WHEN l.progress = 'Buys' THEN 1 ELSE 0 END) AS buys_leads,
               SUM(CASE WHEN l.progress = 'Didn’t Connect' THEN 1 ELSE 0 END) AS didnt_connect_leads,
               SUM(CASE WHEN l.progress = 'First Call Done' THEN 1 ELSE 0 END) AS first_call_done_leads,
               SUM(CASE WHEN l.progress = 'Quote Sent' THEN 1 ELSE 0 END) AS quote_sent_leads,
               SUM(CASE WHEN l.progress = 'Converted' THEN 1 ELSE 0 END) AS converted_leads,
               SUM(CASE WHEN l.followup_date IS NOT NULL AND l.followup_date != '' AND l.progress != 'Lost' AND l.progress != 'Converted' AND l.progress != 'Didnt Connect' AND l.progress != 'Didn\'t Connect' AND l.progress != 'Fresh Lead' THEN 1 ELSE 0 END) AS follow_up_leads,
               SUM(CASE WHEN l.progress = 'Fresh Lead' THEN 1 ELSE 0 END) AS not_started_leads
        FROM users u
        LEFT JOIN leads l ON u.id = l.owner
        WHERE u.role = 'Sales' AND l.assigned_at BETWEEN ? AND ?
        GROUP BY u.id";
$stmt = $link->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$user_productivity = [];
while ($row = $result->fetch_assoc()) {
    $user_productivity[] = [
        'name' => $row['first_name'],
        'all_leads' => $row['all_leads'],
        'lost_leads' => $row['lost_leads'],
        'not_interested_leads' => $row['not_interested_leads'],
        'follow_up_leads' => $row['follow_up_leads'],
        'buys_leads' => $row['buys_leads'],
        'didnt_connect_leads' => $row['didnt_connect_leads'],
        'first_call_done_leads' => $row['first_call_done_leads'],
        'quote_sent_leads' => $row['quote_sent_leads'],
        'converted_leads' => $row['converted_leads'],
        'not_started_leads' => $row['not_started_leads']
    ];
}
$sql = "SELECT u.username, SUM(l.converted_price) AS total_sales
        FROM users u
        LEFT JOIN leads l ON u.id = l.owner
        WHERE l.progress = 'Converted' AND u.role = 'Sales' AND l.conversion_date BETWEEN ? AND ?
        GROUP BY u.id";
$stmt = $link->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$user_sales = [];
while ($row = $result->fetch_assoc()) {
    $user_sales[] = [
        'name' => $row['username'],
        'total_sales' => $row['total_sales']
    ];
}



$counter = 1;
// $link->close();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   <style>
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-size: 16px;
        color: #333;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border: 1px solid #ddd; /* Add border to each cell */
    }
    th {
        background-color: #f4f4f4; /* Header background color */
    }
    tr:nth-child(even) {
        background-color: #f9f9f9; /* Even row color */
    }
    tr:hover {
        background-color: #f1f1f1; /* Hover effect */
    }
    .no-data {
        padding: 20px;
        text-align: center;
        color: #888; /* Style for no data message */
    }
</style>
</head>

<body>
    <div style="margin-top:80px;">
        <h1>Leads Follow-Up Dashboard</h1>
    </div>

    <!-- Date Selector Form -->
    <form method="post" action="">
        <div class="form-one">
        <label for="start_date">Start Date:</label>
        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="form-two">
        <label for="end_date">End Date:</label>
        
        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <button type="submit">Filter</button>
    </form>

<div class="head-Container">
    <div class="container">
        <div class="table-container">
            <div class="heading">
                <h2>Leads Follow-up overdue</h2>
            </div>
            <div class="table">
                <table border="2" style="border-color:white">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Owner</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Quality</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads_to_check as $lead): ?>
                        <?php
                            // $now = Date();
                            $row_class = '';
                            if (date('Y-m-d') > $lead['followup_date']) {
                                if (date('H:i') > $lead['followup_time'])

                                    $row_class = 'missed-followup';
                                $row_class = 'missed-followup';
                            }
                            ?>
                        <tr class="<?php echo htmlspecialchars($row_class); ?>"
                            onclick="window.location.href='lead_action.php?id=<?php echo htmlspecialchars($lead['id']); ?>'">
                            <td><?php echo htmlspecialchars($counter);
                                    $counter += 1; ?></td>
                            <td><?php echo htmlspecialchars($lead['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars($lead['name']); ?></td>
                            <td><?php echo htmlspecialchars($lead['followup_date']); ?></td>
                            <td><?php echo htmlspecialchars($lead['followup_time']); ?></td>
                            <td><?php echo htmlspecialchars(!empty($lead['form']) ? $lead['form'] : 'Not Set'); ?></td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="chart-container">
            <!--<canvas id="progressChart"></canvas>-->
            <div class="heading">
                <h2>Lead Target Table</h2>
            </div>
            
            
           <?php

// Get total leads with no owner
$sqlTotalLeads = "SELECT COUNT(*) AS total_leads FROM leads WHERE owner IS NULL and powner IS NULL ";
$resultTotalLeads = $link->query($sqlTotalLeads);
$totalLeadsRow = $resultTotalLeads->fetch_assoc();
$totalLeads = $totalLeadsRow['total_leads'];

// Get all distinct present users in the sales role by fetching their first attendance entry
// Only include users who logged in before 10:30 AM
// $sqlPresentUsers = "SELECT u.id AS user_id, u.first_name 
//                     FROM users u
//                     INNER JOIN (
//                         SELECT user_id, MIN(login_time) AS first_login 
//                         FROM attendance 
//                         WHERE attendance_date = CURDATE() 
//                           AND login_time IS NOT NULL 
//                         GROUP BY user_id
//                     ) a ON u.id = a.user_id
//                     WHERE u.role = 'Sales'
//                     AND TIME(a.first_login) < '10:30:00'";
$sqlPresentUsers = "SELECT u.id AS user_id, u.first_name 
                    FROM users u
                    INNER JOIN (
                        SELECT user_id, MIN(login_time) AS first_login 
                        FROM attendance 
                        WHERE attendance_date = CURDATE() 
                          AND login_time IS NOT NULL 
                        GROUP BY user_id
                    ) a ON u.id = a.user_id
                    WHERE u.role = 'Sales'";
$resultPresentUsers = $link->query($sqlPresentUsers);
// Total present users
$totalPresentUsers = $resultPresentUsers->num_rows;

$targetLeads = ($totalLeads > 0 && $totalPresentUsers > 0) ? intval($totalLeads / $totalPresentUsers) : 0;


echo '<div class="table">
        <table border="1" style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 16px; text-align: left; border-color: white;">
            <thead style="background-color: #f4f4f4; color: #333;">
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid #ddd;">User</th>
                    <th style="padding: 12px; border-bottom: 1px solid #ddd;">Assigned Leads Today</th>
                    <th style="padding: 12px; border-bottom: 1px solid #ddd;">Target Leads</th>
                    <th style="padding: 12px; border-bottom: 1px solid #ddd;">Available Leads</th>
                    <th style="padding: 12px; border-bottom: 1px solid #ddd;">Leads Updated Today</th>
                    <th style="padding: 12px; border-bottom: 1px solid #ddd;">Missed Follow-ups</th>
                </tr>
            </thead>
            <tbody>';

// Loop through each present user to get their data
while ($presentUserRow = $resultPresentUsers->fetch_assoc()) {
    $userId = $presentUserRow['user_id'];
    $userName = htmlspecialchars($presentUserRow['first_name']);

    // Get assigned leads today for the current user
    $sqlUserLeads = "SELECT COALESCE(COUNT(l.id), 0) AS assigned_leads_today
                     FROM lead_assignment_logs l
                     WHERE l.user_id = ? 
                       AND DATE(l.assigned_at) = CURDATE()";
    $stmt = $link->prepare($sqlUserLeads);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $resultUserLeads = $stmt->get_result();
    $userLeadsRow = $resultUserLeads->fetch_assoc();
    $assignedLeadsToday = $userLeadsRow['assigned_leads_today'];

    // Dynamically calculate target leads for this user
    // Target is proportional to the total available leads divided by the number of present users
    $targetLeads = ($totalLeads > 0 && $totalPresentUsers > 0) ? intval($totalLeads / $totalPresentUsers) : 0;

    // Calculate available leads for the current user
    $availableLeads = $totalLeads;

    // Get the number of lead notes created today by the current user
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

    // Get missed follow-ups for the current user
    $sqlMissedFollowups = "SELECT COUNT(*) AS missed_followups
                           FROM leads 
                           WHERE owner = ?
                           AND followup_date < CURDATE() and progress NOT IN ('Converted' , 'Lost' )";
    $stmtMissed = $link->prepare($sqlMissedFollowups);
    $stmtMissed->bind_param("i", $userId);
    $stmtMissed->execute();
    $resultMissed = $stmtMissed->get_result();
    $missedFollowupsRow = $resultMissed->fetch_assoc();
    $missedFollowups = $missedFollowupsRow['missed_followups'];

    // Output the user's data in the table
    echo '<tr style="background-color: #f9f9f9;">
            <td style="padding: 12px; border-bottom: 1px solid #ddd;">' . $userName . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($assignedLeadsToday) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($targetLeads) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($availableLeads) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($notesCreatedToday) . '</td>
            <td style="padding: 12px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($missedFollowups) . '</td>
          </tr>';
}

echo '  </tbody>
    </table>';
    
    
    
$followupquery = "
SELECT 
    u.id AS user_id,
    u.first_name,
    COUNT(l.id) AS total_leads,
    COALESCE(c.counter_count, 0) AS total_counter
FROM 
    users u
INNER JOIN (
    SELECT 
        user_id, 
        MIN(login_time) AS first_login 
    FROM 
        attendance 
    WHERE 
        attendance_date = CURDATE() 
        AND login_time IS NOT NULL 
    GROUP BY 
        user_id
) a ON u.id = a.user_id
LEFT JOIN 
    leads l ON l.owner = u.id 
            AND l.followup_date = CURDATE()  -- Filter for today's follow-up date
LEFT JOIN 
    counter c ON c.user_id = u.id  -- Joining counter table
WHERE 
    u.role = 'Sales'
GROUP BY 
    u.id, u.first_name";

$resultfollowup = $link->query($followupquery);
if ($resultfollowup->num_rows > 0) {
    echo '<table>
            <tr>
                <th>User Name</th>
                <th>Total Leads with Today\'s Follow-up</th>
                <th>Total Counter Updates</th> <!-- New column for counter -->
            </tr>';
    while ($row = $resultfollowup->fetch_assoc()) {
        $userName = htmlspecialchars($row['first_name']);
        $totalLeads = htmlspecialchars($row['total_leads']);
        $totalCounter = htmlspecialchars($row['total_counter']);

        echo '<tr>
                <td>' . $userName . '</td>
                <td>' . $totalLeads . '</td>
                <td>' . $totalCounter . '</td> <!-- Display counter -->
              </tr>'; 
    }
    echo '</table>';
} else {
    echo '<div class="no-data">No users present today.</div>';
}

?>



<?php
// Fetch first login times after 10:30 AM for today
$sqlLateLogins = "
    SELECT 
    u.id AS user_id,
    u.first_name,
    a.location,
    a.address,
    MIN(a.login_time) AS first_login
FROM 
    users u
INNER JOIN 
    attendance a ON u.id = a.user_id
WHERE 
    u.role = 'Sales'
    AND a.attendance_date = CURDATE() 
    AND a.login_time > '10:30:00'
GROUP BY 
    u.id, u.first_name;;
";

$resultLateLogins = $link->query($sqlLateLogins);

if ($resultLateLogins->num_rows > 0) {
    echo '<div class="table-container">
            <h2>Users Present Today</h2>
            <table border="1" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>First Login Time</th>
                        <th>Location</th>
                        <th>address</th>
                    </tr>
                </thead>
                <tbody>';
    
    while ($row = $resultLateLogins->fetch_assoc()) {
        echo '<tr>
                <td>' . htmlspecialchars($row['user_id']) . '</td>
                <td>' . htmlspecialchars($row['first_name']) . '</td>
                <td>' . htmlspecialchars($row['first_login']) . '</td>
                 <td>' . htmlspecialchars($row['location']) . '</td>
                 <td>' . htmlspecialchars($row['address']) . '</td>
              </tr>';
    }

    echo '  </tbody>
          </table>
          </div>
            </div>';
} else {
    echo '<div class="no-data">No late logins today.</div>';
}
?>





 </div>
          
          
          
    </div>
    </div>
    <div class="chart-head">
        <div class="bar-chart-container">
            <canvas id="productivityChart"></canvas>
        </div>
        <div class="sales-chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    </div>

    <script>
    // Bar Chart for Total Sales
    var ctxSales = document.getElementById('salesChart').getContext('2d');

    var salesLabels = <?php echo json_encode(array_column($user_sales, 'name')); ?>;
    var salesData = <?php echo json_encode(array_column($user_sales, 'total_sales')); ?>;

    new Chart(ctxSales, {
        type: 'bar',
        data: {
            labels: salesLabels,
            datasets: [{
                label: 'Total Sales',
                data: salesData,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });


    // Pie Chart
    // var ctx = document.getElementById('progressChart').getContext('2d');
    // var chartData = {
    //     labels: ['Lost', 'Not Interested', 'Follow-up', 'Buys', 'Didn’t Connect', 'First Call Done', 'Quote Sent', 'Converted', 'Fresh Lead'],
    //     datasets: [{
    //         label: 'Leads Progress',
    //         data: <?php echo json_encode(array_values($progress_counts)); ?>,
    //         backgroundColor: [
    //             '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FFCD56', '#4BC0C0', '#FF6384'
    //         ],
    //         hoverBackgroundColor: [
    //             '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FFCD56', '#4BC0C0', '#FF6384'
    //         ]
    //     }]
    // };

    // var progressChart = new Chart(ctx, {
    //     type: 'pie',
    //     data: chartData,
    //     options: {
    //         responsive: true
    //     }
    // });

    // Bar Chart
    var ctx2 = document.getElementById('productivityChart').getContext('2d');

    // Progress types as labels
    var barChartData = {
        labels: ['All Leads', 'Lost Leads', 'Not Interested Leads', 'Follow-Up Leads', 'Buys Leads',
            'Didn’t Connect Leads', 'First Call Done Leads', 'Quote Sent Leads', 'Converted Leads',
            'Fresh Lead Leads'
        ],
        datasets: [
            <?php foreach ($user_productivity as $user): ?> {
                label: '<?php echo $user["name"]; ?>',
                data: [
                    <?php echo $user['all_leads']; ?>,
                    <?php echo $user['lost_leads']; ?>,
                    <?php echo $user['not_interested_leads']; ?>,
                    <?php echo $user['follow_up_leads']; ?>,
                    <?php echo $user['buys_leads']; ?>,
                    <?php echo $user['didnt_connect_leads']; ?>,
                    <?php echo $user['first_call_done_leads']; ?>,
                    <?php echo $user['quote_sent_leads']; ?>,
                    <?php echo $user['converted_leads']; ?>,
                    <?php echo $user['not_started_leads']; ?>
                ],
                backgroundColor: '<?php echo '#' . substr(md5(rand()), 0, 6); ?>' // Random color for each user
            },
            <?php endforeach; ?>
        ]
    };

    var productivityChart = new Chart(ctx2, {
        type: 'bar',
        data: barChartData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y;
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: false, // Bars are side-by-side, not stacked
                    beginAtZero: true
                },
                y: {
                    stacked: false, // Bars are side-by-side, not stacked
                    beginAtZero: true
                }
            }
        }
    });
    </script>

</body>

</html>