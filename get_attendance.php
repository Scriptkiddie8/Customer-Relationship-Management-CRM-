<?php
include 'methods/database.php';

$userId = $_GET['userId'];
$month = $_GET['month'];
$year = $_GET['year'];

$stmt = $conn->prepare("SELECT DATE_FORMAT(date, '%Y-%m-%d') as date, checkin_time, checkout_time 
                        FROM attendance 
                        WHERE user_id = :userId 
                        AND MONTH(date) = :month 
                        AND YEAR(date) = :year");
$stmt->bindParam(':userId', $userId);
$stmt->bindParam(':month', $month);
$stmt->bindParam(':year', $year);
$stmt->execute();

$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($attendance);
?>
