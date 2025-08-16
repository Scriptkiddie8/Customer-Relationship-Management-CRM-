<?php
session_start();

// Include database connection file
require_once 'methods/database.php';

// Check if the user is logged in
if (!isset($_SESSION["loggedin"])) {
    header("location: index.php");
    exit;
}

// Define variables and initialize with empty values
$search_term = "";
$leads = [];

// Process search form when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $search_term = trim($_POST["search_term"]);

    // Query to search leads
    $sql = "SELECT id, name, phone, email, source, form, channel, stage, owner, labels FROM leads WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        $param_search_term = "%" . $search_term . "%";
        mysqli_stmt_bind_param($stmt, "sss", $param_search_term, $param_search_term, $param_search_term);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_bind_result($stmt, $id, $name, $phone, $email, $source, $form, $channel, $stage, $owner, $labels);
            while (mysqli_stmt_fetch($stmt)) {
                $leads[] = [
                    'id' => $id,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'source' => $source,
                    'form' => $form,
                    'channel' => $channel,
                    'stage' => $stage,
                    'owner' => $owner,
                    'labels' => $labels
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
    <title>Search Leads</title>
    <link rel="stylesheet" href="css/search_leads.css">
</head>
<body>

<div class="search-leads-container">
    <h2>Search Leads</h2>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="text" name="search_term" placeholder="Search..." value="<?php echo htmlspecialchars($search_term); ?>">
        <input type="submit" value="Search">
    </form>

    <?php if (!empty($leads)) : ?>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Source</th>
                <th>Form</th>
                <th>Channel</th>
                <th>Stage</th>
                <th>Owner</th>
                <th>Labels</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leads as $lead) : ?>
            <tr>
                <td><?php echo htmlspecialchars($lead['name']); ?></td>
                <td><?php echo htmlspecialchars($lead['phone']); ?></td>
                <td><?php echo htmlspecialchars($lead['email']); ?></td>
                <td><?php echo htmlspecialchars($lead['source']); ?></td>
                <td><?php echo htmlspecialchars($lead['form']); ?></td>
                <td><?php echo htmlspecialchars($lead['channel']); ?></td>
                <td><?php echo htmlspecialchars($lead['stage']); ?></td>
                <td><?php echo htmlspecialchars($lead['owner']); ?></td>
                <td><?php echo htmlspecialchars($lead['labels']); ?></td>
                <td>
                    <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn-view">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <p>No leads found.</p>
    <?php endif; ?>
</div>

</body>
</html>

