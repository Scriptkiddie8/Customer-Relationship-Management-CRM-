<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);


echo"hasdsh";



// Include database connection file
require_once 'methods/database.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw JSON data from the POST request
    $jsonData = file_get_contents('php://input');

    // Save the raw JSON data to data.txt (append mode)
    file_put_contents('data.txt', $jsonData . PHP_EOL, FILE_APPEND);

    // Decode the JSON data to an associative array
    $data = json_decode($jsonData, true);

    // Initialize variables
    $name = $phone = $email = $createdTime = $adName = "";

    // Extract data from the decoded JSON array
    if (isset($data['name'])) $name = $data['name'];
    if (isset($data['phone'])) $phone = $data['phone'];
    if (isset($data['email'])) $email = $data['email'];
    if (isset($data['created_time'])) $createdTime = $data['created_time'];
    if (isset($data['ad_name'])) $adName = $data['ad_name'];

    // Validate the required fields
    $errors = [];
    if (empty($name)) $errors[] = 'Name is missing.';
    if (empty($phone)) $errors[] = 'Phone number is missing.';
    if (empty($email)) $errors[] = 'Email address is missing.';
    if (empty($createdTime)) $errors[] = 'Created time is missing.';
    if (empty($adName)) $errors[] = 'Ad name is missing.';

    if (empty($errors)) {
        // Prepare an insert statement
        $sql = "INSERT INTO leads (name, phone, email, created_at, ad_name) VALUES (?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement
            mysqli_stmt_bind_param($stmt, "sssss", $name, $phone, $email, $createdTime, $adName);

            // Execute the statement
            if (mysqli_stmt_execute($stmt)) {
                echo "Data successfully inserted into the database.";
            } else {
                // Output error if the statement fails
                echo "Failed to insert data into the database. Error: " . mysqli_error($link);
            }

            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            // Output error if the prepared statement fails
            echo "Failed to prepare the SQL statement. Error: " . mysqli_error($link);
        }
    } else {
        // Output errors
        echo "Errors: " . implode(", ", $errors);
    }

    // Close connection
    mysqli_close($link);
}
?>
