<?php
session_start();
require 'methods/database.php'; // Your database connection file

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize variables for error handling
$errors = [];
$leads_justification_required = false;
$followups_justification_required = false;

// Get user ID from session
$userId = $_SESSION['id'];
$yesterday = date('Y-m-d', strtotime('-1 day'));
$current_date = date('Y-m-d');

// Retrieve missed follow-ups from the leads table
try {
    // Calculate all follow-ups (due + future)
    $allFollowupsQuery = "
        SELECT COUNT(*) 
        FROM leads 
        WHERE owner = ? AND progress NOT IN ('Converted', 'Lost', 'Didnt Connect')";
    $stmtAllFollowups = $link->prepare($allFollowupsQuery);
    if ($stmtAllFollowups === false) {
        throw new Exception('Prepare statement failed: ' . $link->error);
    }
    $stmtAllFollowups->bind_param("i", $userId);
    $stmtAllFollowups->execute();
    $stmtAllFollowups->bind_result($followups_due);
    $stmtAllFollowups->fetch();
    $stmtAllFollowups->close();

    // Calculate missed follow-ups (date < today)
    $missedFollowupsQuery = "
        SELECT COUNT(*) 
        FROM leads 
        WHERE owner = ? AND followup_date < ? AND progress NOT IN ('Converted', 'Lost', 'Didnt Connect')";
    $stmtMissedFollowups = $link->prepare($missedFollowupsQuery);
    if ($stmtMissedFollowups === false) {
        throw new Exception('Prepare statement failed: ' . $link->error);
    }
    $stmtMissedFollowups->bind_param("is", $userId, $current_date);
    $stmtMissedFollowups->execute();
    $stmtMissedFollowups->bind_result($followups_missed);
    $stmtMissedFollowups->fetch();
    $stmtMissedFollowups->close();

    // Update the justification table with the calculated values
    $updateJustificationQuery = "
        UPDATE justification 
        SET followups_due = ?, followups_missed = ? 
        WHERE owner = ? AND date = ?";
    $stmtUpdateJustification = $link->prepare($updateJustificationQuery);
    if ($stmtUpdateJustification === false) {
        throw new Exception('Prepare statement failed: ' . $link->error);
    }
    $stmtUpdateJustification->bind_param("iiis", $followups_due, $followups_missed, $userId, $yesterday);
    $stmtUpdateJustification->execute();
    $stmtUpdateJustification->close();

    // Retrieve missed follow-up leads details
    $missedFollowupDetailsQuery = "
        SELECT name, followup_date 
        FROM leads 
        WHERE owner = ? AND followup_date < ? AND progress NOT IN ('Converted', 'Lost', 'Didnt Connect')";
    $stmtMissedFollowupDetails = $link->prepare($missedFollowupDetailsQuery);
    if ($stmtMissedFollowupDetails === false) {
        throw new Exception('Prepare statement failed: ' . $link->error);
    }
    $stmtMissedFollowupDetails->bind_param("is", $userId, $current_date);
    $stmtMissedFollowupDetails->execute();
    $stmtMissedFollowupDetails->bind_result($lead_name, $followup_date);
    $missedFollowupLeads = [];
    while ($stmtMissedFollowupDetails->fetch()) {
        $missedFollowupLeads[] = ['name' => $lead_name, 'followup_date' => $followup_date];
    }
    $stmtMissedFollowupDetails->close();

    // Retrieve target leads and assigned leads
    $combinedSql = "
        SELECT 
            target_leads, 
            COALESCE(SUM(assigned_leads), 0) AS assigned_leads
        FROM justification
        WHERE owner = ? AND date = ?
        GROUP BY target_leads";
    
    $stmtCombined = $link->prepare($combinedSql);
    if ($stmtCombined === false) {
        throw new Exception('Prepare statement failed: ' . $link->error);
    }
    $stmtCombined->bind_param("is", $userId, $yesterday);
    $stmtCombined->execute();
    $stmtCombined->bind_result($target_leads, $assigned_leads);
    $stmtCombined->fetch();
    $stmtCombined->close();

    // Check if justification is required
    if ($assigned_leads < $target_leads) {
        $leads_justification_required = true;
    }
    if ($followups_missed > 0) {
        $followups_justification_required = true;
    }

    // Redirect to the leads page if no justification is required
    if (!$leads_justification_required && !$followups_justification_required) {
        $updateJustificationSql = "
    INSERT INTO justification (owner, date, justification_leads_shortfall, justification_followups_missed)
    VALUES (?, ?, 'Not needed', 'Not needed')
    ON DUPLICATE KEY UPDATE 
        justification_leads_shortfall = 'Not needed',
        justification_followups_missed = 'Not needed'";

        $stmtUpdate = $link->prepare($updateJustificationSql);
        if ($stmtUpdate === false) {
            throw new Exception('Prepare statement failed: ' . $link->error);
        }
        $stmtUpdate->bind_param("is", $userId, $yesterday);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        header("Location: leads.php");
        exit();
    }

    // Retrieve existing justifications
    $justificationQuery = "
        SELECT justification_leads_shortfall, justification_followups_missed 
        FROM justification 
        WHERE owner = ? AND date = ?";
    $stmtJustification = $link->prepare($justificationQuery);
    if ($stmtJustification === false) {
        throw new Exception('Prepare statement failed: ' . $link->error);
    }
    $stmtJustification->bind_param("is", $userId, $yesterday);
    $stmtJustification->execute();
    $stmtJustification->bind_result($existing_leads_justification, $existing_followups_justification);
    $stmtJustification->fetch();
    $stmtJustification->close();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $leads_justification = $_POST['leads_justification'] ?? '';
        $followups_justification = $_POST['followups_justification'] ?? '';

        // Validate input data
        if ($leads_justification_required && empty($leads_justification)) {
            $errors[] = 'Justification for leads shortfall is required.';
        }
        if ($followups_justification_required && empty($followups_justification)) {
            $errors[] = 'Justification for missed follow-ups is required.';
        }

        if (empty($errors)) {
            try {
                // If only follow-up justification is required, set leads justification to 'Not needed'
                if (!$leads_justification_required && $followups_justification_required) {
                    $leads_justification = 'Not needed';
                }

                // Update existing justification
                $updateQuery = "
                    INSERT INTO justification (owner, date, justification_leads_shortfall, justification_followups_missed) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        justification_leads_shortfall = VALUES(justification_leads_shortfall),
                        justification_followups_missed = VALUES(justification_followups_missed),
                        leads_shortfall = (target_leads - assigned_leads)";
                $stmtUpdate = $link->prepare($updateQuery);
                if ($stmtUpdate === false) {
                    throw new Exception('Prepare statement failed: ' . $link->error);
                }
                $stmtUpdate->bind_param("isss", $userId, $yesterday, $leads_justification, $followups_justification);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                // Unset the session variable and redirect
                unset($_SESSION['needs_justification']);
                header("Location: leads.php");
                exit();
            } catch (Exception $e) {
                $errors[] = 'Error updating or inserting justification: ' . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $errors[] = 'Error retrieving data: ' . $e->getMessage();
}

// // Initialize variables for error handling 
// $errors = [];
// $leads_justification_required = false;
// $followups_justification_required = false;

// // Get user ID from session
// $userId = $_SESSION['id'];
// $yesterday = date('Y-m-d', strtotime('-1 day'));
// $current_date = date('Y-m-d');

// // Function to check if a date is a weekend
// function isWeekend($date) {
//     return (date('N', strtotime($date)) >= 6); // 6 = Saturday, 7 = Sunday
// }

// // Retrieve missed follow-ups from the leads table
// try {
//     // Calculate all follow-ups (due + future) excluding weekends
//     $allFollowupsQuery = "
//         SELECT COUNT(*) 
//         FROM leads 
//         WHERE owner = ? 
//         AND progress NOT IN ('Converted', 'Lost', 'Didnt Connect')
//         AND (followup_date >= CURDATE() OR DATE(followup_date) NOT IN (SELECT date FROM calendar WHERE day_of_week IN (6, 7)))"; // Example of using a calendar table if you have one

//     $stmtAllFollowups = $link->prepare($allFollowupsQuery);
//     if ($stmtAllFollowups === false) {
//         throw new Exception('Prepare statement failed: ' . $link->error);
//     }
//     $stmtAllFollowups->bind_param("i", $userId);
//     $stmtAllFollowups->execute();
//     $stmtAllFollowups->bind_result($followups_due);
//     $stmtAllFollowups->fetch();
//     $stmtAllFollowups->close();

//     // Calculate missed follow-ups (date < today) excluding weekends
//     $missedFollowupsQuery = "
//         SELECT COUNT(*) 
//         FROM leads 
//         WHERE owner = ? 
//         AND followup_date < ? 
//         AND progress NOT IN ('Converted', 'Lost', 'Didnt Connect')
//         AND NOT (DAYOFWEEK(followup_date) = 1 OR DAYOFWEEK(followup_date) = 7)"; // 1 = Sunday, 7 = Saturday
    
//     $stmtMissedFollowups = $link->prepare($missedFollowupsQuery);
//     if ($stmtMissedFollowups === false) {
//         throw new Exception('Prepare statement failed: ' . $link->error);
//     }
//     $stmtMissedFollowups->bind_param("is", $userId, $current_date);
//     $stmtMissedFollowups->execute();
//     $stmtMissedFollowups->bind_result($followups_missed);
//     $stmtMissedFollowups->fetch();
//     $stmtMissedFollowups->close();

//     // Update the justification table with the calculated values
//     $updateJustificationQuery = "
//         UPDATE justification 
//         SET followups_due = ?, followups_missed = ? 
//         WHERE owner = ? AND date = ?";

//     $stmtUpdateJustification = $link->prepare($updateJustificationQuery);
//     if ($stmtUpdateJustification === false) {
//         throw new Exception('Prepare statement failed: ' . $link->error);
//     }
//     $stmtUpdateJustification->bind_param("iiis", $followups_due, $followups_missed, $userId, $yesterday);
//     $stmtUpdateJustification->execute();
//     $stmtUpdateJustification->close();

//     // Retrieve missed follow-up leads details
//     $missedFollowupDetailsQuery = "
//         SELECT name, followup_date 
//         FROM leads 
//         WHERE owner = ? 
//         AND followup_date < ? 
//         AND progress NOT IN ('Converted', 'Lost', 'Didnt Connect')
//         AND NOT (DAYOFWEEK(followup_date) = 1 OR DAYOFWEEK(followup_date) = 7)"; // Exclude weekends
    
//     $stmtMissedFollowupDetails = $link->prepare($missedFollowupDetailsQuery);
//     if ($stmtMissedFollowupDetails === false) {
//         throw new Exception('Prepare statement failed: ' . $link->error);
//     }
//     $stmtMissedFollowupDetails->bind_param("is", $userId, $current_date);
//     $stmtMissedFollowupDetails->execute();
//     $stmtMissedFollowupDetails->bind_result($lead_name, $followup_date);
//     $missedFollowupLeads = [];
//     while ($stmtMissedFollowupDetails->fetch()) {
//         $missedFollowupLeads[] = ['name' => $lead_name, 'followup_date' => $followup_date];
//     }
//     $stmtMissedFollowupDetails->close();

//     // Retrieve target leads and assigned leads
//     $combinedSql = "
//         SELECT 
//             target_leads, 
//             COALESCE(SUM(assigned_leads), 0) AS assigned_leads
//         FROM justification
//         WHERE owner = ? AND date = ?
//         GROUP BY target_leads";
    
//     $stmtCombined = $link->prepare($combinedSql);
//     if ($stmtCombined === false) {
//         throw new Exception('Prepare statement failed: ' . $link->error);
//     }
//     $stmtCombined->bind_param("is", $userId, $yesterday);
//     $stmtCombined->execute();
//     $stmtCombined->bind_result($target_leads, $assigned_leads);
//     $stmtCombined->fetch();
//     $stmtCombined->close();

//     // Check if justification is required
//     if ($assigned_leads < $target_leads) {
//         $leads_justification_required = true;
//     }
//     if ($followups_missed > 0) {
//         $followups_justification_required = true;
//     }

//     // Redirect to the leads page if no justification is required
//     if (!$leads_justification_required && !$followups_justification_required) {
//         $updateJustificationSql = "
//         INSERT INTO justification (owner, date, justification_leads_shortfall, justification_followups_missed)
//         VALUES (?, ?, 'Not needed', 'Not needed')
//         ON DUPLICATE KEY UPDATE 
//             justification_leads_shortfall = 'Not needed',
//             justification_followups_missed = 'Not needed'";

//         $stmtUpdate = $link->prepare($updateJustificationSql);
//         if ($stmtUpdate === false) {
//             throw new Exception('Prepare statement failed: ' . $link->error);
//         }
//         $stmtUpdate->bind_param("is", $userId, $yesterday);
//         $stmtUpdate->execute();
//         $stmtUpdate->close();

//         header("Location: leads.php");
//         exit();
//     }

//     // Retrieve existing justifications
//     $justificationQuery = "
//         SELECT justification_leads_shortfall, justification_followups_missed 
//         FROM justification 
//         WHERE owner = ? AND date = ?";
    
//     $stmtJustification = $link->prepare($justificationQuery);
//     if ($stmtJustification === false) {
//         throw new Exception('Prepare statement failed: ' . $link->error);
//     }
//     $stmtJustification->bind_param("is", $userId, $yesterday);
//     $stmtJustification->execute();
//     $stmtJustification->bind_result($existing_leads_justification, $existing_followups_justification);
//     $stmtJustification->fetch();
//     $stmtJustification->close();

//     // Handle form submission
//     if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//         $leads_justification = $_POST['leads_justification'] ?? '';
//         $followups_justification = $_POST['followups_justification'] ?? '';

//         // Validate input data
//         if ($leads_justification_required && empty($leads_justification)) {
//             $errors[] = 'Justification for leads shortfall is required.';
//         }
//         if ($followups_justification_required && empty($followups_justification)) {
//             $errors[] = 'Justification for missed follow-ups is required.';
//         }

//         if (empty($errors)) {
//             try {
//                 // If only follow-up justification is required, set leads justification to 'Not needed'
//                 if (!$leads_justification_required && $followups_justification_required) {
//                     $leads_justification = 'Not needed';
//                 }

//                 // Update existing justification
//                 $updateQuery = "
//                     INSERT INTO justification (owner, date, justification_leads_shortfall, justification_followups_missed) 
//                     VALUES (?, ?, ?, ?)
//                     ON DUPLICATE KEY UPDATE 
//                         justification_leads_shortfall = VALUES(justification_leads_shortfall),
//                         justification_followups_missed = VALUES(justification_followups_missed),
//                         leads_shortfall = (target_leads - assigned_leads)";
                
//                 $stmtUpdate = $link->prepare($updateQuery);
//                 if ($stmtUpdate === false) {
//                     throw new Exception('Prepare statement failed: ' . $link->error);
//                 }
//                 $stmtUpdate->bind_param("isss", $userId, $yesterday, $leads_justification, $followups_justification);
//                 $stmtUpdate->execute();
//                 $stmtUpdate->close();

//                 // Unset the session variable and redirect
//                 unset($_SESSION['needs_justification']);
//                 header("Location: leads.php");
//                 exit();
//             } catch (Exception $e) {
//                 $errors[] = 'Error updating or inserting justification: ' . $e->getMessage();
//             }
//         }
//     }
// } catch (Exception $e) {
//     $errors[] = 'Error retrieving data: ' . $e->getMessage();
// }



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Justification</title>
    <link rel='stylesheet' href = "css/justification.css" >
   
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        form.addEventListener('submit', function(event) {
            const leadsJustification = document.getElementById('leads_justification');
            const followupsJustification = document.getElementById('followups_justification');
            let errors = [];

            // Check if leads justification is displayed and validate its length
            if (leadsJustification && leadsJustification.required && leadsJustification.value.trim().length < 100) {
                errors.push('Justification for Leads Shortfall must be at least 100 characters.');
            }

            // Check if follow-ups justification is displayed and validate its length
            if (followupsJustification && followupsJustification.required && followupsJustification.value.trim().length < 100) {
                errors.push('Justification for Missed Follow-ups must be at least 100 characters.');
            }

            // If errors exist, prevent form submission and display errors
            if (errors.length > 0) {
                event.preventDefault();
                alert(errors.join("\n"));
            }
        });
    });
</script>

</head>
<body>
    <h2>Submit Justification</h2>
    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
    <?php if ($leads_justification_required):
    $_SESSION['needs_justification']=true;
    ?>
        <div>
            <label for="leads_justification">Justification for Leads Shortfall:</label>
            <textarea id="leads_justification" name="leads_justification" required><?php echo htmlspecialchars($existing_leads_justification ?? ''); ?></textarea>
        </div>
    <?php endif; ?>

    <?php if ($followups_justification_required):
    $_SESSION['needs_justification']=true;
    ?>
        <div>
            <label for="followups_justification">Justification for Missed Follow-ups:</label>
            <textarea id="followups_justification" name="followups_justification" required><?php echo htmlspecialchars($existing_followups_justification ?? ''); ?></textarea>
        </div>
    <?php endif; ?>

    <button type="submit">Submit</button>
</form>

</body>
</html>
