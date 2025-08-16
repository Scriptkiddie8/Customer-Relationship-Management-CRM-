<?php
// Enable error reporting
include 'header.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require 'methods/database.php'; // Ensure this file correctly connects to your database

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $lead_id = filter_var($_POST['lead_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['id'];
    $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $payment_method = htmlspecialchars($_POST['payment_method'], ENT_QUOTES, 'UTF-8');
    $notes = htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8');

    // Handle file upload
    $payment_screenshot = null;
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $upload_file = $upload_dir . basename($_FILES['payment_screenshot']['name']);
        if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $upload_file)) {
            $payment_screenshot = $upload_file;
        } else {
            echo "<div class='alert alert-danger'>File upload failed.</div>";
            exit;
        }
    }

    // Prepare and execute SQL statement
    $sql = "INSERT INTO payment_requests (lead_id, user_id, amount, payment_method, payment_screenshot, notes)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $link->prepare($sql);
    if ($stmt === false) {
        die('<div class="alert alert-danger">Prepare failed: ' . htmlspecialchars($link->error) . '</div>');
    }

    $stmt->bind_param('iidsss', $lead_id, $user_id, $amount, $payment_method, $payment_screenshot, $notes);
    if ($stmt->execute()) {
        // Redirect to payment history page
        header('Location: payment_history.php');
        exit; // Ensure that no further code is executed after the redirect
    } else {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($stmt->error) . "</div>";
    }

    $stmt->close();
    $link->close();
} else {
    // Display the form
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Payment Request</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <style>
        .container {
            max-width: 600px;
            margin-top: 20px;
        }
        .form-control {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Create Payment Request</h1>
        <form action="payment_confirmation.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="lead_id">Lead ID:</label>
                <input type="number" id="lead_id" name="lead_id" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="amount">Amount:</label>
                <input type="number" id="amount" name="amount" step="0.01" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="payment_method">Payment Method:</label>
                <select id="payment_method" name="payment_method" class="form-control">
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="PayPal">PayPal</option>
                </select>
            </div>

            <div class="form-group">
                <label for="payment_screenshot">Payment Screenshot (optional):</label>
                <input type="file" id="payment_screenshot" name="payment_screenshot" class="form-control">
            </div>

            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea id="notes" name="notes" class="form-control" rows="4"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Submit Payment Request</button>
        </form>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Example: Add a spinner or loading indication if needed
            $('form').on('submit', function() {
                $(this).find('button[type="submit"]').prop('disabled', true).text('Submitting...');
            });
        });
    </script>
</body>
</html>

<?php
}
?>
