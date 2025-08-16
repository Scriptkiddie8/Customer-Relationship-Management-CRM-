<?php
// Enable error reporting
include 'header.php';

// Database connection
require 'methods/database.php'; // Ensure this file correctly connects to your database

// Fetch payment history
$user_id = $_SESSION['id'];
$sql = "SELECT lead_id, amount, payment_method, payment_screenshot, notes, payment_status FROM payment_requests WHERE user_id = ?";
$stmt = $link->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($link->error));
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$link->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">
    
    <style>
        .payment_status-approved { color: green; font-weight: bold; }
        .payment_status-pending { color: orange; font-weight: bold; }
        .payment_status-rejected { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Payment History</h1>
        
        <table id="paymentTable" class="display table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Lead ID</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Payment Screenshot</th>
                    <th>Notes</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)) : ?>
                    <tr>
                        <td colspan="6">No payment requests found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($payments as $payment) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['lead_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(number_format($payment['amount'] ?? 0, 2)); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($payment['payment_screenshot'])) : ?>
                                    <a href="<?php echo htmlspecialchars($payment['payment_screenshot']); ?>" target="_blank">View Screenshot</a>
                                <?php else : ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($payment['notes'] ?? 'N/A'); ?></td>
                            <td class="<?php echo 'payment_status-' . strtolower(htmlspecialchars($payment['payment_status'] ?? 'pending')); ?>">
                                <?php echo htmlspecialchars($payment['payment_status'] ?? 'Pending'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#paymentTable').DataTable({
                "responsive": true
            });
        });
    </script>
</body>
</html>
