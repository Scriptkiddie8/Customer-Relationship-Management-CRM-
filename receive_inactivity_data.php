<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// require 'methods/database.php';  // Include the database connection script

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // Ensure parameters are present
//     if (isset($_POST['user_id']) && isset($_POST['inactivity_time']) && isset($_POST['datetime'])) {
//         $user_id = $_POST['user_id'];
//         $inactivity_time = $_POST['inactivity_time'];  // Expecting a floating-point value
//         $datetime = $_POST['datetime'];

//         // Validate inputs
//         if (is_numeric($user_id) && is_numeric($inactivity_time) && strtotime($datetime) !== false) {
//             // Check if the current time is after 7:30 PM
//             $current_time = new DateTime();
//             $cutoff_time = new DateTime('19:30:00'); // 7:30 PM

//             if ($current_time < $cutoff_time) {
//                 // Prepare an SQL statement to insert the inactivity data
//                 $query = "INSERT INTO inactivity_logs (user_id, inactivity_duration, recorded_at) VALUES (?, ?, ?)";
//                 if ($stmt = $link->prepare($query)) {
//                     // Bind parameters (user_id as integer, inactivity_duration as float, recorded_at as string)
//                     $stmt->bind_param("ids", $user_id, $inactivity_time, $datetime);

//                     if ($stmt->execute()) {
//                         echo "Inactivity data recorded successfully";
//                     } else {
//                         echo "Error executing statement: " . $stmt->error;
//                     }

//                     $stmt->close();
//                 } else {
//                     echo "Failed to prepare query: " . $link->error;
//                 }
//             } else {
//                 echo "Data not recorded: submissions are not allowed after 7:30 PM.";
//             }
//         } else {
//             echo "Invalid input data";
//         }
//     } else {
//         echo "Missing required parameters";
//     }

//     $link->close();  // Close the tenant database connection
// } else {
//     echo "Invalid request method";
// }
?>



<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'methods/database.php';  // Include the database connection script

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure parameters are present
    if (isset($_POST['user_id']) && isset($_POST['inactivity_time']) && isset($_POST['datetime'])) {
        $user_id = $_POST['user_id'];
        $inactivity_time = $_POST['inactivity_time'];  // Expecting a floating-point value
        $datetime = $_POST['datetime'];

        // Retrieve the client's IP address
        $ip_address = $_SERVER['REMOTE_ADDR'];
        // if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        //     $ip_address = $_SERVER['HTTP_CLIENT_IP']; // IP from shared internet
        // } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        //     $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR']; // IP passed from proxies
        // }
        // Validate inputs
        if (is_numeric($user_id) && is_numeric($inactivity_time) && strtotime($datetime) !== false) {
            // Check if the current time is after 7:30 PM
            $current_time = new DateTime();
            $cutoff_time = new DateTime('19:30:00'); // 7:30 PM

            if ($current_time < $cutoff_time) {
                // Prepare an SQL statement to insert the inactivity data
                $query = "INSERT INTO inactivity_logs (user_id, inactivity_duration, recorded_at, ipaddress) VALUES (?, ?, ?, ?)";
                if ($stmt = $link->prepare($query)) {
                    // Bind parameters (user_id as integer, inactivity_duration as float, recorded_at as string, ip_address as string)
                    $stmt->bind_param("idss", $user_id, $inactivity_time, $datetime, $ip_address);

                    if ($stmt->execute()) {
                        echo "Inactivity data recorded successfully";
                    } else {
                        echo "Error executing statement: " . $stmt->error;
                    }

                    $stmt->close();
                } else {
                    echo "Failed to prepare query: " . $link->error;
                }
            } else {
                echo "Data not recorded: submissions are not allowed after 7:30 PM.";
            }
        } else {
            echo "Invalid input data";
        }
    } else {
        echo "Missing required parameters";
    }

    $link->close();  // Close the database connection
} else {
    echo "Invalid request method";
}
?>

