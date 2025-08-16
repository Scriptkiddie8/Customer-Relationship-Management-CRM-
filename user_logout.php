<?php

include 'header.php';
require 'methods/database.php'; // Include database connection

// Ensure the session ID is set
if (!isset($_SESSION['id'])) {
    die("User is not logged in.");
}

$id = $_SESSION['id']; // Get the user's ID from the session

// Set timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Process the form submission for logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loggingoff']) && !empty($_POST['loggingoff'])) {
    // Get the logout reason and time
    $reason = $_POST['loggingoff'];
    $logout_time = date('Y-m-d H:i:s');

    // Prepare the SQL query for logging off
    $query = "INSERT INTO logging_off (id, reason, logout_time) VALUES (?, ?, ?)";
    $stmt = $link->prepare($query);

    if ($stmt) {
        $stmt->bind_param("iss", $id, $reason, $logout_time);

        // Execute the query and check for success
        if ($stmt->execute()) {
            $stmt->close();

            // Redirect to logout.php after successful logoff
            header("Location: logout.php");
            exit();
        } 
    } 
} 

$link->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Off</title>
    <style>
        /* Styling as per your original code */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .container h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 20px;
        }
        form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        select {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 16px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: #f4f4f4;
            color: #333;
            outline: none;
            transition: border-color 0.3s ease;
        }
        select:focus {
            border-color: #3498db;
        }
        button {
            padding: 12px 20px;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            background-color: #3498db;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Why Logging Off</h1>
        <form method="POST" action=""> <!-- Ensure form action points to the same file -->
            <select name="loggingoff" id="loggingoff" required>
                <option value="" disabled selected hidden>Select a Reason</option>
                <option value="Chai Sutta Bar">Chai Sutta Bar</option>  
                <option value="Lunch">Lunch</option>  
                <option value="Meeting">Meeting</option>  
                <option value="End of the Day">End of the Day</option>  
            </select>
            <button type="submit">Logout</button> <!-- Removed onclick for proper form submission -->
        </form>
    </div>
</body>
</html>
