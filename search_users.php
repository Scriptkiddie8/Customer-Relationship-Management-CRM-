<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] != 'Admin') {
    header("location: index.php");
    exit;
}

// Define variables and initialize with empty values
$search_term = "";
$users = [];

// Process search form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $search_term = trim($_POST["search_term"]);

    // Query to search users
    $sql = "SELECT id, username, email, role, first_name, last_name FROM users WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        $param_search_term = "%" . $search_term . "%";
        mysqli_stmt_bind_param($stmt, "ssss", $param_search_term, $param_search_term, $param_search_term, $param_search_term);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $id, $username, $email, $role, $first_name, $last_name);
            while (mysqli_stmt_fetch($stmt)) {
                $users[] = [
                    'id' => $id,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ];
            }
        } else {
            echo "Error executing query.";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "Error preparing statement.";
    }
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Users</title>
    <link rel="stylesheet" href="css/search_users.css">
</head>
<body>

<div class="search-users-container">
    <h2>Search Users</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="text" name="search_term" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
        <input type="submit" value="Search">
    </form>

    <?php if (!empty($users)) : ?>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user) : ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                <td>
                    <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-view">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p>No users found.</p>
    <?php endif; ?>
</div>

</body>
</html>
