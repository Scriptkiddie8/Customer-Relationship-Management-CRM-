<?php include "header.php"; ?>
<?php

// Include database connection file
require_once 'methods/database.php';
// Check if the user is logged in and has admin role
if (!isset($_SESSION["loggedin"]) || $_SESSION["role"] != 'Admin') {
    header("location: index.php");
    exit;
}

// Fetch all users from the database
$sql = "SELECT id, username, email, role, first_name, last_name, created_at,team_leader FROM users";
$result = mysqli_query($link, $sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/user_management.css">
</head>

<body>

    <div class="user-management-container">
        <h2>User Management</h2>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New User
        </a>

        <table>
            <thead>
                <tr>
                    <th>ID <i class="fas fa-id-badge"></i></th>
                    <th>Username <i class="fas fa-user"></i></th>
                    <th>Email <i class="fas fa-envelope"></i></th>
                    <th>Role <i class="fas fa-user-tag"></i></th>
                    <th>First Name <i class="fas fa-address-card"></i></th>
                    <th>Last Name <i class="fas fa-address-card"></i></th>
                    <th>Created At <i class="fas fa-calendar-alt"></i></th>
                    <th>Actions <i class="fas fa-tools"></i></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td>
                        <?php if ($row['team_leader'] == 1): ?>
                        <i class="fas fa-star" title="Team Lead"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($row['username']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['role']); ?></td>
                    <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="delete_user.php?id=<?php echo $row['id']; ?>" class="btn btn-danger"
                            onclick="return confirm('Are you sure you want to delete this user?');">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>

        </table>
    </div>

</body>

</html>

<?php
// Close connection
mysqli_close($link);
?>