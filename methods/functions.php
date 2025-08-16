<?php
// functions.php

// Function to fetch data from the database
function fetch_data($query, $conn) {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
?>
