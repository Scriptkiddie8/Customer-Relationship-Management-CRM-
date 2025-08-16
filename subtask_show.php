<?php
include 'header.php'; // Include your header file
require 'methods/database.php'; // Include your database connection methods
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$user_details = [];
$subtasks = [];

//  today subtask show code start php 
// if ($user_id > 0) {
//     $query_user = "SELECT Username, id, department_id FROM users WHERE id = ?";
//     $stmt_user = $link->prepare($query_user);
//     if ($stmt_user) {
//         $stmt_user->bind_param("i", $user_id);
//         $stmt_user->execute();
//         $result_user = $stmt_user->get_result();

//         if ($result_user->num_rows > 0) {
//             $user_details = $result_user->fetch_assoc(); 
//         } else {
//             die("No user found with this ID.");
//         }
//         $stmt_user->close();
//     }
//     $today_date = date('Y-m-d'); // Get today's date
//     $query_subtasks = "SELECT subtask_name, description, deadline, estimated_manhours 
//                        FROM subtasks 
//                        WHERE assigned_to = ? AND DATE(deadline) = ?";
//     $stmt_subtasks = $link->prepare($query_subtasks);
//     if ($stmt_subtasks) {
//         $stmt_subtasks->bind_param("is", $user_id, $today_date);
//         $stmt_subtasks->execute();
//         $result_subtasks = $stmt_subtasks->get_result();
//         $subtasks = $result_subtasks->fetch_all(MYSQLI_ASSOC);
//         $stmt_subtasks->close();
//     }
// }
//  today subtask show code end php 
// To display subtasks with deadlines that are either up to three days from today or extend to five days if weekends are included, you can use the following PHP code (start):
if ($user_id > 0) {
    $query_user = "SELECT Username, id, department_id FROM users WHERE id = ?";
    $stmt_user = $link->prepare($query_user);
    if (!$stmt_user) {
        die("Database query error: " . $link->error);
    }
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 0) {
        die("No user found with this ID.");
    }
    $user_details = $result_user->fetch_assoc();
    $stmt_user->close();
    $today_date = date('Y-m-d');
    $dates_to_check = [];
    for ($i = 0; $i < 5; $i++) {
        $date = new DateTime($today_date);
        $date->modify("+$i days");
        if ($i < 3 || ($i >= 3 && $date->format('N') < 6)) {
            $dates_to_check[] = $date->format('Y-m-d');
        }
    }
    if (!empty($dates_to_check)) {
        $placeholders = implode(',', array_fill(0, count($dates_to_check), '?'));
        $query_subtasks = "SELECT subtask_name, description, deadline, estimated_manhours 
                           FROM subtasks 
                           WHERE assigned_to = ? AND DATE(deadline) IN ($placeholders)";
        
        $stmt_subtasks = $link->prepare($query_subtasks);
        
        if (!$stmt_subtasks) {
            die("Database query error: " . $link->error);
        }
        $params = array_merge([$user_id], $dates_to_check);
        $stmt_subtasks->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt_subtasks->execute();
        $result_subtasks = $stmt_subtasks->get_result();
        $subtasks = $result_subtasks->fetch_all(MYSQLI_ASSOC);
        $stmt_subtasks->close();
    } else {
        $subtasks = []; // No valid dates found
    }
}
// To display subtasks with deadlines that are either up to three days from today or extend to five days if weekends are included, you can use the following PHP code (end):
$query_all_users = "SELECT u.Username, u.id, s.subtask_name, s.description, s.estimated_manhours 
                    FROM users u 
                    LEFT JOIN subtasks s ON u.id = s.assigned_to";
$result_all_users = $link->query($query_all_users);
$all_users_data = $result_all_users->fetch_all(MYSQLI_ASSOC);
$search_term = '';
$search_results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['all_data_search'])) {
    $search_term = trim($_POST['all_data_search']); 

    if (!empty($search_term)) {
        $query_user = "SELECT Username, id, department_id FROM users WHERE Username LIKE ?";
        $stmt_user = $link->prepare($query_user);

        if ($stmt_user) {
            $search_like = "%" . $search_term . "%";
            $stmt_user->bind_param("s", $search_like);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($result_user->num_rows > 0) {
                $user_details = $result_user->fetch_assoc(); 
                $user_id = $user_details['id'];

                // Fetch subtasks for the found user
                $query_subtasks = "SELECT subtask_name, description, deadline, estimated_manhours FROM subtasks WHERE assigned_to = ?";
                $stmt_subtasks = $link->prepare($query_subtasks);

                if ($stmt_subtasks) {
                    $stmt_subtasks->bind_param("i", $user_id);
                    $stmt_subtasks->execute();
                    $result_subtasks = $stmt_subtasks->get_result();
                    $subtasks = $result_subtasks->fetch_all(MYSQLI_ASSOC);
                    $stmt_subtasks->close();
                }
            } else {
                echo "<p>No user found matching the search term.</p>";
            }
            $stmt_user->close();
        }
    }
}
function convertToTimeFormat($hours) {
    $total_seconds = $hours * 3600;
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $seconds = $total_seconds % 60;
    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        background-color: #f9f9f9;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
        transition: background-color 0.3s ease;
    }

    th {
        background-color: #4CAF50;
        color: white;
    }

    tr:hover {
        background-color: #f1f1f1;
    }

    .nav-bar {
        background-color: #007bff40;
        margin-top: 50px !important;
        padding: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .tab {
        display: flex;
    }

    .tab button {
        background-color: transparent;
        color: white;
        /* Text color */
        border: 1px solid transparent;
        /* No border */
        padding: 10px 15px;
        /* Padding for buttons */
        margin: 0 5px;
        /* Margin between buttons */
        border-radius: 5px;
        /* Rounded corners */
        cursor: pointer;
        /* Pointer on hover */
        transition: background-color 0.3s, border-color 0.3s;
        /* Smooth transition */
    }

    .tab button:hover {
        background-color: rgba(255, 255, 255, 0.2);
        /* Lighten background on hover */
    }

    .tab button.active {
        background-color: white;
        /* Active button background */
        color: #007BFF;
        /* Active button text color */
        border: 1px solid #007BFF;
        /* Active button border */
    }

    .search-form {
        display: flex;
        /* Flexbox for search form */
        align-items: center;
        /* Center items vertically */
        margin: 20px 0;
        /* Margin for the search form */
    }

    .search-form input[type="search"] {
        padding: 10px;
        /* Padding for input */
        border: 1px solid #ddd;
        /* Border for input */
        border-radius: 5px;
        /* Rounded corners */
        width: 250px;
        /* Fixed width */
        margin-right: 10px;
        /* Space between input and button */
        transition: border-color 0.3s;
        /* Smooth border transition */
    }

    .search-form input[type="search"]:focus {
        border-color: #007BFF;
        /* Change border color on focus */
        outline: none;
        /* Remove outline */
    }

    .search-form button {
        background-color: #007BFF;
        /* Button background */
        color: white;
        /* Button text color */
        border: none;
        /* No border */
        padding: 10px 15px;
        /* Padding for button */
        border-radius: 5px;
        /* Rounded corners */
        cursor: pointer;
        /* Pointer on hover */
        transition: background-color 0.3s;
        /* Smooth transition */
    }

    .search-form button:hover {
        background-color: #0056b3;
        /* Darker shade on hover */
    }

    .tab-content {
        display: none;
        /* Hide tab content by default */
    }

    .active {
        display: block;
        /* Show active tab content */
    }

    tr #tb-margin {
        margin-bottom: 10% !important;
    }
    </style>
</head>

<body>

    <h1>User and Task Details</h1>

    <!-- Tabs for User and All Users -->
    <div class="nav-bar">
        <div class="tab">
            <button onclick="openTab(event, 'tab1')" class="active">User Details for
                <?php echo htmlspecialchars($user_details['Username'] ?? ''); ?></button>
            <button onclick="openTab(event, 'tab2')">All User Details</button>
        </div>

        <!-- Search Form -->
        <div class="search-form">
            <form action="" method="post">
                <input type="search" name="all_data_search" placeholder="Search by username"
                    value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit">Search</button>
            </form>
        </div>
    </div>

    <!-- Display the results if user is found -->
    <div id="tab1" class="tab-content active">
        <?php if (!empty($user_details)): ?>
        <h2>Details for <?php echo htmlspecialchars($user_details['Username']); ?></h2>
        <table style="margin-top: 30px !important">
            <tbody>
                <?php
        $total_seconds = 0; // Overall total
        $subtasks_by_date = [];

        // Group subtasks by their deadline
        foreach ($subtasks as $subtask) {
            $deadline = date('Y-m-d', strtotime($subtask['deadline']));
            $subtasks_by_date[$deadline][] = $subtask;
        }

        // Sort deadlines
        ksort($subtasks_by_date);

        // Prepare to display subtasks by deadline
        foreach ($subtasks_by_date as $date => $tasks):
            if (in_array($date, $dates_to_check)):
                // Format the date
                $formatted_date = date('F j, Y', strtotime($date));
                
                // Initialize total seconds for the current date group
                $date_total_seconds = 0;

                // Output the table header for each date group
                echo "<thead >
                        <tr >
                            <th>Subtask Name</th>
                            <th>Description</th>
                            <th>Deadline: " . htmlspecialchars($formatted_date) . "</th>
                            <th>Estimated Manhours</th>
                        </tr>
                      </thead>";

                foreach ($tasks as $subtask):
                    $date_total_seconds += $subtask['estimated_manhours'] * 3600; // Total for this date group
                    $formatted_time = convertToTimeFormat($subtask['estimated_manhours']);
        ?>
                <tr>
                    <td><?php echo htmlspecialchars($subtask['subtask_name']); ?></td>
                    <td><?php echo htmlspecialchars($subtask['description']); ?></td>
                    <td><?php echo htmlspecialchars($formatted_date); ?></td>
                    <td><?php echo $formatted_time; ?> hrs</td>
                </tr>
                <?php 
                endforeach; 
        ?>
                <tr class="" id="tb-margin">
                    <td colspan="3" style="text-align: right;"><strong>Total Time for
                            <?php echo htmlspecialchars($formatted_date); ?></strong></td>
                    <td><?php echo convertToTimeFormat($date_total_seconds / 3600); ?> hrs</td>

                </tr>
                <?php if (true): ?>
                <tr>
                    <td colspan="6"></td>
                </tr>
                <?php endif; ?>

                <?php 
            $total_seconds += $date_total_seconds; // Add date total to overall total
            endif; 
        endforeach; 
        ?>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Overall Total Time</strong></td>
                    <td><?php echo convertToTimeFormat($total_seconds / 3600); ?> hrs</td>
                </tr>
            </tbody>
        </table>



        <?php else: ?>
        <p>No user details available.</p>
        <?php endif; ?>
    </div>

    <!-- Display all users and their subtasks -->
    <div id="tab2" class="tab-content">
        <h2>All User Details</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Subtask Name</th>
                    <th>Description</th>
                    <th>Estimated Manhours</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_users_data as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['Username']); ?></td>
                    <td><?php echo htmlspecialchars($user['subtask_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['description']); ?></td>
                    <td><?php echo htmlspecialchars($user['estimated_manhours']); ?> hrs</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }
    </script>
</body>

</html>