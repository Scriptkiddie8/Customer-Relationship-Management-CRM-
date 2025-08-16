<?php

include 'header.php';
require 'methods/database.php'; // Include database connection

$username = isset($_GET['username']) ? $_GET['username'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';

$query = "SELECT * FROM justification_crm WHERE 1=1";
$params = [];
$types = '';

if ($username) {
    $query .= " AND username = ?";
    $params[] = $username;
    $types .= 's'; // String type for username
}
if ($month && $year) {
    $query .= " AND MONTH(created_at) = ? AND YEAR(created_at) = ?";
    $params[] = $month;
    $params[] = $year;
    $types .= 'ii'; // Integer types for month and year
}

$query .= " ORDER BY created_at DESC";

$stmt = $link->prepare($query);

if ($params) { // Check if there are parameters to bind
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$data = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Justification CRM</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        background-color: #f4f4f4;
    }

    .container {
        width: 80% !important;
        margin: auto;
        margin-top: 90px;
        padding: 20px;
        background: #fff;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
        text-align: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }

    .search-form {
        margin-bottom: 20px;
        display: flex;
        justify-content: end;
    }

    .search-input,
    .search-select {
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: 150px;
        margin-right: 10px;
        font-size: 16px;
    }

    .search-button {
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        background-color: #28a745;
        color: white;
        font-size: 16px;
        cursor: pointer;
    }

    .search-button:hover {
        background-color: #218838;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>Justification CRM Data</h1>

        <form method="GET" action="" class="search-form">
            <input type="text" name="username" placeholder="Search by username"
                value="<?php echo htmlspecialchars($username); ?>" class="search-input">

            <select name="month" class="search-select">
                <option value="">Select Month</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php if($month == $m) echo 'selected'; ?>>
                    <?php echo date("F", mktime(0, 0, 0, $m, 1)); ?>
                </option>
                <?php endfor; ?>
            </select>

            <select name="year" class="search-select">
                <option value="">Select Year</option>
                <?php for ($y = date('Y'); $y >= 2000; $y--): ?>
                <option value="<?php echo $y; ?>" <?php if($year == $y) echo 'selected'; ?>>
                    <?php echo $y; ?>
                </option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="search-button">Search</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Department</th>
                    <th>Justification</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data)): ?>
                <?php foreach ($data as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['department_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['comments']); ?></td>
                    <td>
                        <?php
                                $createdAt = new DateTime($row['created_at']);
                                echo htmlspecialchars($createdAt->format('Y-m-d'));
                            ?>
                    </td>
                    <td><?php echo htmlspecialchars($createdAt->format('H:i:s')); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="6">No data available</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script src="script.js">
    // script.js
    document.addEventListener('DOMContentLoaded', () => {
        // Future JavaScript functionality can go here
    });
    </script>
</body>

</html>