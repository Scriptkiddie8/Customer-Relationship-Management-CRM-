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
$username = $email = $role = $first_name = $last_name = $password = $team_leader ="";
$username_err = $email_err = $role_err = $first_name_err = $last_name_err = $password_err = "";

// Get the user ID from URL
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $id = trim($_GET["id"]);

    // Fetch user details from the database
    $sql = "SELECT username, email, role, first_name, last_name FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $username, $email, $role, $first_name, $last_name);
            if (mysqli_stmt_fetch($stmt)) {
                // Existing data fetched
            } else {
                echo "Error fetching user data.";
                exit;
            }
        } else {
            echo "Error executing query.";
            exit;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "Error preparing statement.";
        exit;
    }
} else {
    echo "Invalid ID.";
    exit;
}

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $role = trim($_POST["role"]);
    $team_leader = trim($_POST["team_lead"]);
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
    if (empty($role)) {
        $role_err = "Please select a role.";
    }
    if (empty($first_name)) {
        $first_name_err = "Please enter a first name.";
    }
    if (empty($last_name)) {
        $last_name_err = "Please enter a last name.";
    }

    // Prepare the update statement
    $sql = "UPDATE users SET username = ?, email = ?, role = ?, first_name = ?, last_name = ? , team_leader=?";
    $params = [$username, $email, $role, $first_name, $last_name , $team_leader];

    // If password is provided, add it to the update statement
    if (!empty($password)) {
        $sql .= ", password = ?";
        $hashed_password = md5($password);
        $params[] = $hashed_password;
    }

    $sql .= " WHERE id = ?";
    $params[] = $id;

    // Update user details in the database if no errors
    if (empty($username_err) && empty($email_err) && empty($role_err) && empty($first_name_err) && empty($last_name_err)) {
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, str_repeat('s', count($params) - 1) . 'i', ...$params);

            if (mysqli_stmt_execute($stmt)) {
                header("location: user_management.php");
                exit;
            } else {
                echo "Error updating user.";
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
    <title>Edit User</title>
    <link rel="stylesheet" href="css/edit_user.css">
</head>

<body>

    <div class="edit-user-container">
        <h2>Edit User</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $id; ?>" method="post">
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
                <label>Team Leader</label>
                <select name="team_lead">
                    <option value="">Select Option</option>
                    <option value="1" <?php echo $team_leader == 1 ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $team_leader == 0 ? 'selected' : ''; ?>>No</option>
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
                <label>Change Password (optional)</label>
                <input type="text" name="password" value="">
                <span class="error-message"><?php echo $password_err; ?></span>
            </div>
            <div>
                <input type="submit" value="Update User">
            </div>
        </form>
    </div>

</body>

</html>