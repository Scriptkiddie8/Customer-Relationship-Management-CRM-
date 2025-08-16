<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in and has admin role
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] != 'Admin') {
    header("location: index.php");
    exit;
}

// Define variables and initialize with empty values
$username = $email = $role = $first_name = $last_name = $password = "";
$username_err = $email_err = $role_err = $first_name_err = $last_name_err = $password_err = "";

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $role = trim($_POST["role"]);
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $password = trim($_POST["password"]);

    // Validate inputs
    if (empty($username)) {
        $username_err = "Please enter a username.";
    }
    if (empty($email)) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format.";
    }
    if (empty($password)) {
        $password_err = "Please enter a password.";
    }
    if (empty($role)) {
        $role = 'Sales'; // Default role to Sales
    }
    if (empty($first_name)) {
        $first_name_err = "Please enter a first name.";
    }
    if (empty($last_name)) {
        $last_name_err = "Please enter a last name.";
    }

    // Insert user into the database if no errors
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($first_name_err) && empty($last_name_err)) {
        // Hash the password using MD5
        $hashed_password = md5($password);

        $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssss", $username, $email, $hashed_password, $role, $first_name, $last_name);

            if (mysqli_stmt_execute($stmt)) {
                header("location: user_management.php");
                exit;
            } else {
                echo "Error adding user.";
            }
            mysqli_stmt_close($stmt);
        } else {
            echo "Error preparing statement.";
        }
    }
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link rel="stylesheet" href="css/add_user.css">
</head>

<body>

    <div class="add-user-container">
        <h2>Add User</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div>
                <label>Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>">
                <span class="error-message"><?php echo $username_err; ?></span>
            </div>
            <div>
                <label>Email</label>
                <input type="text" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span class="error-message"><?php echo $email_err; ?></span>
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="password" value="<?php echo htmlspecialchars($password); ?>">
                <span class="error-message"><?php echo $password_err; ?></span>
            </div>
            <div>
                <label>Role</label>
                <select name="role">
                    <option value="">Select Role</option>
                    <option value="Admin" <?php echo $role == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="User" <?php echo $role == 'User' ? 'selected' : ''; ?>>User</option>
                    <option value="Sales" <?php echo $role == 'Sales' ? 'selected' : ''; ?>>Sales</option>
                </select>
                <span class="error-message"><?php echo $role_err; ?></span>
            </div>
            <div>
                <label>First Name</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                <span class="error-message"><?php echo $first_name_err; ?></span>
            </div>
            <div>
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                <span class="error-message"><?php echo $last_name_err; ?></span>
            </div>
            <div>
                <input type="submit" value="Add User">
            </div>
        </form>
    </div>

</body>

</html>
