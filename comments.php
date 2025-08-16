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
$lead_id = $comments = "";
$comments_err = "";

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate lead ID
    if (isset($_POST["lead_id"]) && !empty(trim($_POST["lead_id"]))) {
        $lead_id = trim($_POST["lead_id"]);
    } else {
        $comments_err = "Invalid lead ID.";
    }

    // Validate comments
    if (empty(trim($_POST["comments"]))) {
        $comments_err = "Please enter your comments.";
    } else {
        $comments = trim($_POST["comments"]);
    }

    // Insert comment into the database
    if (empty($comments_err)) {
        $sql = "INSERT INTO comments (lead_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "iis", $lead_id, $_SESSION["id"], $comments);

            if (mysqli_stmt_execute($stmt)) {
                $comments = ""; // Clear the comment field
            } else {
                $comments_err = "Oops! Something went wrong. Please try again later.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}

// Get lead ID from URL
if (isset($_GET["lead_id"]) && !empty(trim($_GET["lead_id"]))) {
    $lead_id = trim($_GET["lead_id"]);

    // Fetch existing comments for the lead
    $sql = "SELECT c.id, c.comment, c.created_at, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.lead_id = ? ORDER BY c.created_at DESC";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $lead_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $comment_id, $comment_text, $created_at, $username);

        $comments_list = [];
        while (mysqli_stmt_fetch($stmt)) {
            $comments_list[] = [
                'id' => $comment_id,
                'text' => $comment_text,
                'created_at' => $created_at,
                'username' => $username
            ];
        }

        mysqli_stmt_close($stmt);
    }
} else {
    header("location: leads.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM System - Comments</title>
    <link rel="stylesheet" href="css/comments.css">
</head>
<body>

<div class="comments-container">
    <h2>Comments for Lead #<?php echo htmlspecialchars($lead_id); ?></h2>

    <!-- Display existing comments -->
    <div class="comments-list">
        <?php if (!empty($comments_list)): ?>
            <?php foreach ($comments_list as $comment): ?>
                <div class="comment">
                    <p><strong><?php echo htmlspecialchars($comment['username']); ?>:</strong> <?php echo htmlspecialchars($comment['text']); ?></p>
                    <p><em><?php echo htmlspecialchars($comment['created_at']); ?></em></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No comments yet.</p>
        <?php endif; ?>
    </div>

    <!-- Add new comment -->
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?lead_id=" . htmlspecialchars($lead_id); ?>" method="post">
        <div>
            <textarea name="comments" rows="4" placeholder="Add a comment"><?php echo htmlspecialchars($comments); ?></textarea>
            <span class="error-message"><?php echo $comments_err; ?></span>
        </div>
        <div>
            <input type="submit" value="Add Comment">
        </div>
    </form>
</div>

</body>
</html>
