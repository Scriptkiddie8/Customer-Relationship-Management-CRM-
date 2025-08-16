<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection file
require 'methods/database.php';
date_default_timezone_set('Asia/Kolkata');

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";

// Preset location coordinates (latitude and longitude) for the office
$allowed_latitude = 28.5840672;
$allowed_longitude = 77.3144912;
$distance_threshold = 2; // Threshold distance in kilometers
$apiKey = 'Nu5IAlTKk2Tf3ZrripgWXKP6UO_gJnU5-8IdAPJMgqs'; // Replace with your HERE API key


// Function to calculate distance between two coordinates using Haversine formula
function calculate_distance($lat1, $lon1, $lat2, $lon2)
{
    $earth_radius = 6371; // Earth radius in kilometers

    $lat_diff = deg2rad($lat2 - $lat1);
    $lon_diff = deg2rad($lon2 - $lon1);

    $a = sin($lat_diff / 2) * sin($lat_diff / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($lon_diff / 2) * sin($lon_diff / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius * $c; // Distance in kilometers
}

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Check if latitude and longitude are provided
    if (isset($_POST["latitude"]) && isset($_POST["longitude"])) {
        $latitude = (float) $_POST["latitude"];
        $longitude = (float) $_POST["longitude"];

        // Calculate distance from the office
        $distance = calculate_distance($latitude, $longitude, $allowed_latitude, $allowed_longitude);
        $location_status = ($distance < $distance_threshold) ? "Office" : "Remote";
        $url = "https://revgeocode.search.hereapi.com/v1/revgeocode?at={$latitude}%2C{$longitude}&lang=en-US&apiKey={$apiKey}";
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        if (isset($result['items']) && count($result['items']) > 0) {
            $item = $result['items'][0];
            $subdistrict = $item['address']['subdistrict'] ?? 'N/A';
            $houseNumber = $item['address']['district'] ?? 'N/A';
            $location_address =  "  $houseNumber $subdistrict";
            // echo json_encode([
            //     'subdistrict' => $subdistrict,
            //     'houseNumber' => $houseNumber,
            // ]);
        }
    } else {
        $latitude = $longitude = null; // Default to null if not set
        $location_status = "Unknown";
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password, role,team_leader FROM users WHERE username = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if username exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role, $team_leader);
                    if (mysqli_stmt_fetch($stmt)) {
                        // Hash the input password using MD5
                        $md5_password = md5($password);

                        // Verify the hashed password
                        if ($md5_password === $hashed_password) {
                            // Password is correct, check location if not an admin
                            $is_admin = ($role === 'Admin');
                            if ($is_admin) {
                                session_start();
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id; // This should set the user_id in session
                                $_SESSION["username"] = $username;
                                $_SESSION["role"] = "Admin";
                                $login_time = date('Y-m-d H:i:s');

                                $_SESSION['login_time'] = $login_time;

                                // Get current date and previous date
                                $current_date = date('Y-m-d');
                                $yesterday = date('Y-m-d', strtotime('-1 day'));
                                $latitude = (float) $_POST["latitude"];
                                $longitude = (float) $_POST["longitude"];
                                // $attendance_sql = "INSERT INTO attendance (user_id, attendance_date, login_time, location, address, latitude, longitude) VALUES (?, CURDATE(), ?, ?, ?, ?, ?)";
                                // if ($stmt_attendance = mysqli_prepare($link, $attendance_sql)) {
                                //     // Bind parameters: id, login_time, location status, address, latitude, longitude
                                //     mysqli_stmt_bind_param($stmt_attendance, "isssdd", $id, $login_time, $location_status, $location_address, $latitude, $longitude);
                                //     mysqli_stmt_execute($stmt_attendance);
                                //     mysqli_stmt_close($stmt_attendance);
                                // } else {
                                //     error_log("Error: " . mysqli_error($link));
                                //     echo "Oops! Something went wrong. Please try again later.";
                                //     exit;
                                // }
                                header("location: dashboard.php");
                                exit;
                            }

                            // Start a new session
                            session_start();

                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id; // This should set the user_id in session
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            $_SESSION["team_leader"] = $team_leader;
                            // Capture the current date and login time
                            $current_date = date('Y-m-d');
                            $login_time = date('Y-m-d H:i:s');
                            $logout_time = null;  // Set to NULL initially

                            // Store login time in session for later use if needed
                            $_SESSION['login_time'] = $login_time;

                            ///////////////////////////////////////////
                            
                            /////////////////////////////////////////////////////////////////


                            // SQL to insert attendance record
                            $attendance_sql = "INSERT INTO attendance (user_id, attendance_date, login_time, logout_time, location, address, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            if ($stmt_attendance = mysqli_prepare($link, $attendance_sql)) {
                                // Bind parameters for user ID, date, login time, logout time (NULL), and location details
                                mysqli_stmt_bind_param($stmt_attendance, "isssssdd", $id, $current_date, $login_time, $logout_time, $location_status, $location_address, $latitude, $longitude);

                                // Execute statement and check for errors
                                if (!mysqli_stmt_execute($stmt_attendance)) {
                                    error_log("MySQL error: " . mysqli_error($link));
                                    echo "Oops! Something went wrong. Please try again later.";
                                    exit;
                                }
                                mysqli_stmt_close($stmt_attendance);
                            } else {
                                error_log("Error preparing statement: " . mysqli_error($link));
                                echo "Oops! Something went wrong. Please try again later.";
                                exit;
                            }

                            // // Redirect to dashboard or desired page after login
                            // header("location: dashboard.php");
                            // exit;
                            // Capture the current logout time
                            $logout_time = date('Y-m-d H:i:s');

                            // SQL to update logout time for the current date and user
                            $logout_sql = "UPDATE attendance SET logout_time = ? WHERE user_id = ? AND attendance_date = ? AND logout_time IS NULL";
                            if ($stmt_logout = mysqli_prepare($link, $logout_sql)) {
                                // Bind parameters for logout time, user ID, and current date
                                $current_date = date('Y-m-d');
                                mysqli_stmt_bind_param($stmt_logout, "sis", $logout_time, $id, $current_date);

                                // Execute statement and check for errors
                                if (!mysqli_stmt_execute($stmt_logout)) {
                                    error_log("MySQL error: " . mysqli_error($link));
                                    echo "Oops! Something went wrong. Please try again later.";
                                    exit;
                                }
                                mysqli_stmt_close($stmt_logout);
                            } else {
                                error_log("Error preparing logout statement: " . mysqli_error($link));
                                echo "Oops! Something went wrong. Please try again later.";
                                exit;
                            }

                      
                            // Redirect based on user role
                            if ($_SESSION['role'] === 'User') {
                                // new code for break tracking 
                                $id = $_SESSION['id']; // User's ID from session
                                date_default_timezone_set('Asia/Kolkata'); // Set timezone to IST
                                $checkSql = "
                                SELECT sno 
                                FROM logging_off 
                                WHERE id = ? AND logout_time IS NOT NULL 
                                ORDER BY sno DESC 
                                LIMIT 1";
                            $checkStmt = $link->prepare($checkSql);

                                // Check if user has a previous session with `logout_time` set
                                if ($checkStmt) {
                                    $checkStmt->bind_param("i", $id);
                                    $checkStmt->execute();
                                    //$checkStmt->store_result();
                                    $checkStmt->bind_result($sno); // Bind the result to the $sno variable
                                    $checkStmt->fetch();
                                    $checkStmt->free_result();
                                    // Only insert a new login entry if a previous session with `logout_time` exists
                                    if ($sno) {
                                        $login_time = date('Y-m-d H:i:s'); // Current time as login time
                                        $insertSql = "UPDATE logging_off set login_time = ? where id = ? and sno = ?";
                                        $insertStmt = $link->prepare($insertSql);
                                        if ($insertStmt) {
                                            $insertStmt->bind_param("sii", $login_time, $id ,$sno);

                                            if ($insertStmt->execute()) {
                                                echo "Login time recorded successfully.";
                                            } else {
                                                echo "Failed to record login time: " . $link->error;
                                            }
                                            $insertStmt->close();
                                        } else {
                                            echo "Failed to prepare insert statement: " . $link->error;
                                        }
                                    } else {
                                        // This is either the first login or the user is already logged in
                                        $login_time = date('Y-m-d H:i:s');
                                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                                        $updateSql = " UPDATE logging_off SET login_time = ? WHERE id = ? AND DATE(logout_time) = ? AND reason = 'End of the Day'";
                                        $updateStmt = $link->prepare($updateSql);
                                        $updateStmt->bind_param("sis", $login_time, $id, $yesterday);
                                        $updateStmt->execute();
                                    }
                                    $checkStmt->close();
                                }

                                // till here
                                header("location: project_dashboard.php");
                                exit;
                            }



                            try {
                                $justificationQuery = "
                                    SELECT justification_leads_shortfall, justification_followups_missed 
                                    FROM justification 
                                    WHERE owner = ? AND date = ?";
                                $stmt = $link->prepare($justificationQuery);
                                if ($stmt === false) {
                                    throw new Exception('Prepare statement failed: ' . $link->error);
                                }
                                $stmt->bind_param("is", $id, $yesterday);
                                $stmt->execute();
                                $stmt->bind_result($existing_leads_justification, $existing_followups_justification);
                                $stmt->fetch();
                                $stmt->close();

                                // Check if either of the justification columns is NULL
                                if ($existing_leads_justification === null || $existing_followups_justification === null) {
                                    echo $existing_leads_justification;
                                    echo $existing_followups_justification;
                                    echo '<script type="text/javascript">
                                const popup = window.open("justification.php", "PopupWindow", "width=600,height=400");
                                // Optionally, disable main window interaction here if needed
                                if (popup) {
                                    popup.focus();
                                } else {
                                    window.alert("Popup blocked! Please allow popups for this site.");
                                }
                              </script>';
                                } else {
                                    if ($_SESSION["role"] === 'Admin') {
                                        header("location: dashboard.php");
                                    } elseif ($_SESSION["role"] === 'User') {
                                        // new code for break tracking
                                        $id = $_SESSION['id']; // User's ID from session
                                        date_default_timezone_set('Asia/Kolkata'); // Set timezone to IST

                                        // Check if user has a previous session with `logout_time` set
                                        $checkSql = "
                                            SELECT sno 
                                            FROM logging_off 
                                            WHERE id = ? AND logout_time IS NOT NULL 
                                            ORDER BY sno DESC 
                                            LIMIT 1";
                                        $checkStmt = $link->prepare($checkSql);

                                        if ($checkStmt) {
                                            $checkStmt->bind_param("i", $id);
                                            $checkStmt->execute();
                                            //$checkStmt->store_result();
                                            $checkStmt->bind_result($sno); // Bind the result to the $sno variable
                                            $checkStmt->fetch();

                                            // Only insert a new login entry if a previous session with `logout_time` exists
                                            if ($sno) {
                                                
                                                $login_time = date('Y-m-d H:i:s'); // Current time as login time
                                                $insertSql = "UPDATE logging_off set login_time = ? where id = ? and sno = ?";
                                                $insertStmt = $link->prepare($insertSql);
                                                if ($insertStmt) {
                                                    $insertStmt->bind_param("sii", $login_time, $id ,$sno);

                                                    if ($insertStmt->execute()) {
                                                        echo "Login time recorded successfully.";
                                                    } else {
                                                        echo "Failed to record login time: " . $link->error;
                                                    }
                                                    $insertStmt->close();
                                                } else {
                                                    echo "Failed to prepare insert statement: " . $link->error;
                                                }
                                            } else {
                                                // This is either the first login or the user is already logged in
                                                $login_time = date('Y-m-d H:i:s');
                                                $yesterday = date('Y-m-d', strtotime('-1 day'));
                                                $updateSql = " UPDATE logging_off SET login_time = ? WHERE id = ? AND DATE(logout_time) = ? AND reason = 'End of the Day'";
                                                $updateStmt = $link->prepare($updateSql);
                                                $updateStmt->bind_param("sis", $login_time, $id, $yesterday);
                                                $updateStmt->execute();
                                            }
                                            $checkStmt->close();
                                        }
                                        //till here
                                        header("location: project_dashboard.php");
                                    } else {
                                        echo '<script type="text/javascript">
                                const popup = window.open("leads.php", "PopupWindow", "width=600,height=400");
                                // Optionally, disable main window interaction here if needed
                                if (popup) {
                                    popup.focus();
                                } else {
                                    alert("Popup blocked! Please allow popups for this site.");
                                }
                              </script>';
                                    }
                                    exit;
                                }
                            } catch (Exception $e) {
                                $errors[] = 'Error retrieving existing justification: ' . $e->getMessage();
                            }

                            // Adjust login time if before 9:30 AM
                            $login_time_obj = new DateTime($login_time);
                            $nine_thirty_am_obj = new DateTime($login_time_obj->format('Y-m-d') . ' 09:30:00');
                            if ($login_time_obj < $nine_thirty_am_obj) {
                                $login_time = $nine_thirty_am_obj->format('Y-m-d H:i:s');
                            }
                            $logout_time = $login_time;
                        } else {
                            // Display an error message if the password is not valid
                            $password_err = "The password you entered was not valid.";
                        }
                    }
                } else {
                    // Display an error message if username doesn't exist
                    $username_err = "No account found with that username.";
                }
            } else {
                error_log("Error: " . mysqli_error($link));
                echo "Oops! Something went wrong. Please try again later.";
            }

            // // Close statement
            // mysqli_stmt_close($stmt);
        } else {
            error_log("Error: " . mysqli_error($link));
            echo "Oops! Something went wrong. Please try again later.";
        }
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM System - Login</title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
        function getLocationAndSubmit() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    // Set the latitude and longitude values in the hidden fields
                    document.getElementById('latitude').value = latitude;
                    document.getElementById('longitude').value = longitude;

                    // Submit the form
                    document.getElementById('loginForm').submit();
                }, error => {
                    alert('Error getting location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }
    </script>
</head>

<body>

    <div class="login-container">
        <img src="css/logo.png" alt="CRM Logo" class="logo">
        <h2>Login to CRM</h2>
        <form id="loginForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post"
            onsubmit="event.preventDefault(); getLocationAndSubmit();">
            <div>
                <input type="text" name="username" placeholder="Username"
                    value="<?php echo htmlspecialchars($username); ?>">
                <span class="error-message"><?php echo htmlspecialchars($username_err); ?></span>
            </div>
            <div>
                <input type="password" name="password" placeholder="Password">
                <span class="error-message"><?php echo htmlspecialchars($password_err); ?></span>
            </div>
            <!-- Hidden fields for location -->
            <input type="hidden" id="latitude" name="latitude" value="">
            <input type="hidden" id="longitude" name="longitude" value="">
            <div>
                <input type="submit" value="Login">
            </div>
        </form>
    </div>

</body>

</html>