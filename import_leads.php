<?php
include "header.php";
include 'methods/database.php'; // Include the database connection file

function cleanString($string)
{
    // Remove unwanted characters and convert to lowercase
    return trim(preg_replace('/[^\w\s\@\.\-\+]/u', '', strtolower($string)));
}

function formatDateTime($datetime)
{
    // Convert datetime format to MySQL format (Y-m-d H:i:s)
    $date = DateTime::createFromFormat(DateTime::ISO8601, $datetime);
    return $date ? $date->format('Y-m-d H:i:s') : null;
}

function cleanPhoneNumber($phone)
{
    // Remove unwanted characters from phone number
    return preg_replace('/[^0-9\+]/', '', $phone);
}

function insertDataIntoLeads($data)
{
    global $link; // Use the global database connection from database.php

    $sql = "INSERT INTO leads (
        source, name, phone, email, created_at
    ) VALUES (
        ?, ?, ?, ?, ?
    )";

    $errorFile = fopen('error_leads.csv', 'a'); // Open error log file

    if ($stmt = $link->prepare($sql)) {
        foreach ($data as $row) {
            $source = cleanString($row['source']);
            $fullname = cleanString($row['client_name']);
            $phone = cleanPhoneNumber($row['phone']);
            $email = cleanString($row['email']);
            $createdAt = date("Y-m-d H:i:s"); // Set current time for created_at

            $stmt->bind_param('sssss', $source, $fullname, $phone, $email, $createdAt);

            try {
                if (!$stmt->execute()) {
                    // Log error to file if there's a duplicate entry or other SQL error
                    $errorRow = [
                        $source,
                        $fullname,
                        $phone,
                        $email,
                        $createdAt,
                        $link->error
                    ];
                    fputcsv($errorFile, $errorRow);
                    echo "Error inserting row: " . htmlspecialchars($email) . " - " . htmlspecialchars($link->error) . "<br>";
                }
            } catch (mysqli_sql_exception $e) {
                // Log error to file if there's a duplicate entry or other SQL error
                $errorRow = [
                    $source,
                    $fullname,
                    $phone,
                    $email,
                    $createdAt,
                    $e->getMessage()
                ];
                fputcsv($errorFile, $errorRow);
                echo "Error inserting row: " . htmlspecialchars($email) . " - " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
        $stmt->close();
        fclose($errorFile);
        echo "Data processed with potential errors logged.";
    } else {
        echo "Error preparing the SQL statement: " . $link->error;
    }
}
function displayCSVAsTable($fileName)
{
    $data = [];
    if (($file = fopen($fileName, "r")) !== FALSE) {
        // Read the header row
        $header = fgetcsv($file, 10000, ","); // Use comma as the delimiter
        if ($header !== FALSE) {
            // Normalize header names to lowercase and remove special characters
            $header = array_map('cleanString', $header);

            // Display actual headers for debugging
            echo "<h3>Headers Found:</h3>";
            echo "<ul>";
            foreach ($header as $h) {
                echo "<li>" . htmlspecialchars($h) . "</li>";
            }
            echo "</ul>";

            // Mapping for header names
            $headerMapping = [
                'source' => ['source'],
                'date_created' => ['date created', 'date_created'],
                'client_name' => ['client name', 'client_name'],
                'phone' => ['phone number', 'phone_number'],
                'email' => ['email']
            ];

            // Determine column indices
            $columnIndices = [];
            foreach ($headerMapping as $key => $names) {
                foreach ($names as $name) {
                    if (($index = array_search(cleanString($name), $header)) !== FALSE) {
                        $columnIndices[$key] = $index;
                        break;
                    }
                }
            }

            // Check if all required columns are present
            $missingColumns = [];
            foreach ($headerMapping as $key => $names) {
                if (!isset($columnIndices[$key])) {
                    $missingColumns[] = implode(', ', $names);
                }
            }

            if (empty($missingColumns)) {
                echo "<table border='1'>";
                echo "<tr><th>Source</th><th>Client Name</th><th>Phone</th><th>Email</th><th>Date Created</th></tr>";

                while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
                    $source = isset($row[$columnIndices['source']]) ? cleanString($row[$columnIndices['source']]) : '';
                    $clientName = isset($row[$columnIndices['client_name']]) ? cleanString($row[$columnIndices['client_name']]) : '';
                    $phone = isset($row[$columnIndices['phone']]) ? cleanPhoneNumber($row[$columnIndices['phone']]) : '';
                    $email = isset($row[$columnIndices['email']]) ? cleanString($row[$columnIndices['email']]) : '';
                    $createdAt = isset($row[$columnIndices['date_created']]) ? formatDateTime($row[$columnIndices['date_created']]) : date('Y-m-d H:i:s'); // Use Date Created or current time

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($source) . "</td>";
                    echo "<td>" . htmlspecialchars($clientName) . "</td>";
                    echo "<td>" . htmlspecialchars($phone) . "</td>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td>" . htmlspecialchars($createdAt) . "</td>";
                    echo "</tr>";

                    $data[] = [
                        'source' => $source,
                        'client_name' => $clientName,
                        'phone' => $phone,
                        'email' => $email,
                        'created_at' => $createdAt
                    ];
                }
                echo "</table>";

                // Insert data into the database
                insertDataIntoLeads($data);
            } else {
                echo "<tr><td colspan='5'>Required columns are missing in the CSV file: " . implode(', ', $missingColumns) . "</td></tr>";
            }
        }

        fclose($file);
    } else {
        echo "Unable to open file.";
    }
}

if (isset($_POST['submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $uploadFile = $_FILES['csv_file']['tmp_name'];
        $destination = 'uploaded_file.csv';

        if (move_uploaded_file($uploadFile, $destination)) {
            echo "<h2>Uploaded CSV File Content</h2>";
            displayCSVAsTable($destination);
        } else {
            echo "Failed to upload file.";
        }
    } else {
        echo "No file uploaded or upload error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/import_leads.css">
    <title>Upload and Display CSV File</title>
</head>

<body>
    <h3>Upload and Display CSV File</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <br><br>
        <input type="submit" name="submit" value="Upload">
    </form>
</body>

</html>