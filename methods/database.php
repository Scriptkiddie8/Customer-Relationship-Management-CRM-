<?php
$servername = "localhost"; // Your server name
$username = "root";        // Your database username
$password = "";        // Your database password
$dbname = "crm3.0";    // Your database name

// Create connection
$link = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($link->connect_error) {
    die("Connection failed: " . $link->connect_error);
}

// Set charset to utf8mb4
$link->set_charset("utf8mb4");
