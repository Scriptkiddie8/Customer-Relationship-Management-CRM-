<?php
include "header.php";
require_once 'methods/database.php';

session_start();

if (!isset($_SESSION["loggedin"]) ) {
    header("location: index.php");
    exit;
}

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
<!DOCTYPE html>
<html>

<head>
    <title>Sales Dashboard</title>
    <link rel="stylesheet" href="css/Sales_Dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    var tableRows = document.querySelectorAll('tr[data-id]');
    
    tableRows.forEach(function (row) {
        row.addEventListener('click', function () {
            var leadId = this.getAttribute('data-id');
            window.location.href = 'lead_action.php?id=' + leadId;
        });
    });
});
</script>

</head>

<body>
    <h1>Sales Dashboard</h1>
    
    <!-- Date Selector Form -->
    <!--<form method="post" action="">-->
    <!--    <label for="start_date">Start Date:</label>-->
    <!--    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">-->
    <!--    <label for="end_date">End Date:</label>-->
    <!--    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">-->
    <!--    <button type="submit">Filter</button>-->
    <!--</form>-->
    
    <div class="container">
        <div class="leads">
           
            <iframe src="leads.php?iframe=true" class="new-leads-section" style="width: 45vw; height: 100%; border: none;"></iframe>
        </div>
        <div class="Followup">
            <h2>Leads Follow-up Overdue</h2>
            <table border="1">
                <thead>
                    <tr>
                        <th id="Name">Name</th>
                        <th id="follow">Follow-Up Date</th>
                        <th id="'Follow-Up">Follow-Up Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads_to_check as $lead): ?>
                        <?php
                        $row_class = '';
                        if (date('Y-m-d') > $lead['followup_date']) {

                            $row_class = 'missed-followup';
                        }
                        ?>
                        <tr class="<?php echo htmlspecialchars($row_class); ?>"
                            data-id="<?php echo htmlspecialchars($lead['id']); ?>">
                            <td><?php echo htmlspecialchars($lead['name']); ?></td>
                            <td><?php echo htmlspecialchars($lead['followup_date']); ?></td>
                            <td><?php echo htmlspecialchars($lead['followup_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <!--<div class="chart-container">-->
        <!--    <canvas id="progressChart"></canvas>-->
        <!--</div>-->

        <!--<div class="bar-chart-container">-->
        <!--    <canvas id="productivityChart"></canvas>-->
        <!--</div>-->
        <!--<div class="sales-chart-container">-->
        <!--    <canvas id="salesChart"></canvas>-->
        <!--</div>-->
    </div>
    <!-- Embed the New Leads page using an iframe -->

    </div>

</body>

</html>