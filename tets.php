<?php
// Include database connection file
require 'methods/database.php';
date_default_timezone_set('Asia/Kolkata');
session_start(); // Start session at the beginning

// Define session timeout (15 minutes)
$timeout_duration = 15 * 60; // 15 minutes in seconds

// Check if the user is logged in and handle session timeout
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $current_time = time();
    $last_login_time = isset($_SESSION['login_time']) ? strtotime($_SESSION['login_time']) : 0;
    
    if (($current_time - $last_login_time) > $timeout_duration) {
        // Redirect to justification page if session timeout
        header("Location: justification.php");
        exit;
    }
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";

// Preset location coordinates (latitude and longitude)
$allowed_latitude = 28.5840672;
$allowed_longitude = 77.3144912;
$distance_threshold = 2; // Adjust the threshold as needed

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
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";

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
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role);
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
                                $_SESSION['login_time'] = date('Y-m-d H:i:s');
                                header("location: dashboard.php");
                                exit;
                            }
                            if ((isset($latitude) && isset($longitude) &&
                                sqrt(pow($latitude - $allowed_latitude, 2) + pow($longitude - $allowed_longitude, 2)) < $distance_threshold)) {
                                // Location is valid or user is an admin, start a new session
                                session_start();

                                // Store data in session variables
                                $_SESSION["loggedin"] = true;
                                $_SESSION["id"] = $id; // This should set the user_id in session
                                $_SESSION["username"] = $username;
                                $_SESSION["role"] = $role;
                                // Capture the current login time
                                $login_time = date('Y-m-d H:i:s');

                                // Store login_time in the session
                                $_SESSION['login_time'] = $login_time;

                                // Capture the current login time
                                $current_time = date('Y-m-d H:i:s');
                                $login_time_obj = new DateTime($current_time);
                                $nine_thirty_am_obj = new DateTime($login_time_obj->format('Y-m-d') . ' 09:30:00');

                                if ($login_time_obj < $nine_thirty_am_obj) {
                                    $login_time = $nine_thirty_am_obj->format('Y-m-d H:i:s');
                                } else {
                                    $login_time = $current_time;
                                }

                                // Insert login record into the attendance table
                                if ((date("H:i") < "09:30") || (date("H:i") > "19:00")) {
                                    $_SESSION['logintime'] = null;
                                    if ($_SESSION["role"] === 'User') {
                                        header("location: project_dashboard.php");
                                    } else {
                                        header("location: leads.php");
                                    }
                                    exit();
                                } else {
                                    // Check if location is provided
                                    if ($latitude !== null && $longitude !== null) {
                                        // Calculate distance from office
                                        $distance = sqrt(pow($latitude - $allowed_latitude, 2) + pow($longitude - $allowed_longitude, 2));
                                        $is_in_office = $distance < $distance_threshold;
                                    } else {
                                        $is_in_office = false;
                                    }

                                    // Determine location status
                                    $location_status = $is_in_office ? "Office" : "Remote";

                                    // Adjust login time if before 9:30 AM
                                    $login_time_obj = new DateTime($current_time);
                                    $nine_thirty_am_obj = new DateTime($login_time_obj->format('Y-m-d') . ' 09:30:00');
                                    if ($login_time_obj < $nine_thirty_am_obj) {
                                        $login_time = $nine_thirty_am_obj->format('Y-m-d H:i:s');
                                    }
                                    $logout_time = $login_time;

                                    // Insert record into the attendance table
                                    $attendance_sql = "INSERT INTO attendance (user_id, attendance_date, login_time, location, logout_time) VALUES (?, CURDATE(), ?, ?, ?)";
                                    if ($stmt_attendance = mysqli_prepare($link, $attendance_sql)) {
                                        // Bind parameters: id, login_time, and location status
                                        mysqli_stmt_bind_param($stmt_attendance, "isss", $id, $login_time, $location_status, $logout_time);
                                        mysqli_stmt_execute($stmt_attendance);
                                        mysqli_stmt_close($stmt_attendance);
                                    }
                                    $_SESSION['logintime'] = date("h:i");
                                    // Capture the current login time
                                    $login_time = date('Y-m-d H:i:s');

                                    // Store login_time in the session
                                    $_SESSION['login_time'] = $login_time;

                                    if ($_SESSION["role"] === 'Admin') {
                                        header("location: dashboard.php");
                                    } elseif ($_SESSION["role"] === 'User') {
                                        header("location: project_dashboard.php");
                                    } else {
                                        header("location: leads.php");
                                    }
                                }
                                exit;
                            } else {
                                $password_err = "You are not in the Office.";
                            }
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
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}
?>
