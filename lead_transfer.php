<?php
// Include database connection
include 'methods/database.php';

// Get current date
$currentDate = date('Y-m-d');

try {
    // Begin transaction
    $link->begin_transaction();

    // Step 1: Identify absent users for today
    $absentUserQuery = "
        SELECT u.id, u.username
        FROM users u
        LEFT JOIN attendance a
        ON u.id = a.user_id AND a.attendance_date = ?
        WHERE a.user_id IS NULL
    ";
    $absentUserStmt = $link->prepare($absentUserQuery);
    $absentUserStmt->bind_param('s', $currentDate);
    $absentUserStmt->execute();
    $absentUserResult = $absentUserStmt->get_result();
    $absentUserIds = $absentUserResult->fetch_all(MYSQLI_ASSOC);

    // Step 2: Identify present users with Sales role for today
    $presentUserQuery = "
        SELECT DISTINCT u.id, u.username
        FROM users u
        JOIN attendance a
        ON u.id = a.user_id
        WHERE a.attendance_date = ? 
          AND a.logout_time IS NOT NULL
          AND u.role = 'Sales'
    ";
    $presentUserStmt = $link->prepare($presentUserQuery);
    $presentUserStmt->bind_param('s', $currentDate);
    $presentUserStmt->execute();
    $presentUserResult = $presentUserStmt->get_result();
    $presentUserIds = $presentUserResult->fetch_all(MYSQLI_ASSOC);

    if (empty($absentUserIds) || empty($presentUserIds)) {
        throw new Exception('No absent or present users found.');
    }

    // Output for debugging
    echo "Absent users for today:\n";
    foreach ($absentUserIds as $user) {
        echo $user['id'] . " - " . $user['username'] . "\n";
    }

    echo "\nPresent users for today:\n";
    foreach ($presentUserIds as $user) {
        echo $user['id'] . " - " . $user['username'] . "\n";
    }

    // Step 3: Find leads of absent users with follow-up scheduled for today
    $absentUserIdsArray = array_column($absentUserIds, 'id');
    $presentUserIdsArray = array_column($presentUserIds, 'id');

    $leadsQuery = "
        SELECT id AS lead_id, owner
        FROM leads
        WHERE owner IN (" . implode(',', array_fill(0, count($absentUserIdsArray), '?')) . ")
        AND followup_date = ?
    ";
    $leadsStmt = $link->prepare($leadsQuery);

    // Bind parameters dynamically
    $types = str_repeat('i', count($absentUserIdsArray)) . 's';
    $params = array_merge($absentUserIdsArray, [$currentDate]);
    $leadsStmt->bind_param($types, ...$params);
    $leadsStmt->execute();
    $leadsResult = $leadsStmt->get_result();
    $leads = $leadsResult->fetch_all(MYSQLI_ASSOC);

    if (!empty($leads)) {
        echo "\nLeads of absent users with follow-up scheduled for today:\n";
        foreach ($leads as $lead) {
            echo "Lead ID: " . $lead['lead_id'] . " - User ID: " . $lead['owner'] . "\n";

            // Step 4: Randomly select a present user with Sales role for the lead
            $newUserId = $presentUserIdsArray[array_rand($presentUserIdsArray)];

            // Insert record into lead_transfer table
            $insertTransferQuery = "
                INSERT INTO lead_transfer (lead_id, old_user_id, new_user_id, transfer_date)
                VALUES (?, ?, ?, ?)
            ";
            $insertTransferStmt = $link->prepare($insertTransferQuery);
            $insertTransferStmt->bind_param('iiss', $lead['lead_id'], $lead['owner'], $newUserId, $currentDate);
            $insertTransferStmt->execute();

            // Step 5: Update leads table to assign the lead to the new user
            $updateLeadsQuery = "
                UPDATE leads
                SET owner = ?
                WHERE id = ?
            ";
            $updateLeadsStmt = $link->prepare($updateLeadsQuery);
            $updateLeadsStmt->bind_param('ii', $newUserId, $lead['lead_id']);
            $updateLeadsStmt->execute();
        }
    } else {
        echo "\nNo leads found for absent users with follow-up scheduled for today.\n";
    }

    // Commit transaction
    $link->commit();

} catch (Exception $e) {
    // Rollback transaction if something goes wrong
    $link->rollback();
    echo "Failed to process leads: " . $e->getMessage();
}

// Close connection
$link->close();
?>
