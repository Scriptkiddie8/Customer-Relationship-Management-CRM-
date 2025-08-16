<?php
include_once "header.php";

// Check if the user is logged in; if not, redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
    exit;
}
date_default_timezone_set('Asia/Kolkata');

// Include database connection file
require_once 'methods/database.php';

// Initialize variables
$errors = [];
$success = "";
$lead = [];
$lead_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
$owner= $_SESSION['id'];

// Fetch lead details along with the owner's username
if ($lead_id) {
    $sql = "
        SELECT
            l.*,
            u.username AS username
        FROM leads l
        LEFT JOIN users u ON l.owner = u.id
        WHERE l.id = ?
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $lead_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $lead = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle lead progress update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_progress'])) {
    $progress = $_POST['progress'];
    $category = $_POST['category'] ?? 'B2C';
    $note = trim($_POST['note']);
    $followup_date = $_POST['followup_date'] ?? null;
    $followup_time = $_POST['followup_time'] ?? null;
    $quote_screenshot = $_FILES['quote_screenshot'] ?? null;
    $payment_screenshot = $_FILES['payment_screenshot'] ?? null;
    $audio_recording = $_FILES['audio_recording'] ?? null;
    $file = $_FILES['file'] ?? null;
        $form_quality = $_POST['form_quality'] ?? null;


    // Validate required fields
    if ($progress == "Didnt Connect" && strlen($note) < 10) {
        $errors[] = "For 'Didnt Connect', the note must be at least 10 characters long.";
    } elseif ($progress != "Didnt Connect" && strlen($note) < 40) {
        $errors[] = "Note must be at least 40 characters long.";
    }
    if (empty($note)) {
        $errors[] = "Note is required.";
    }

    // Follow-up date and time validation for compulsory progress types
    if (!in_array($progress, ['Converted', 'Lost','Didnt Connect']) && (empty($followup_date) || empty($followup_time))) {
        $errors[] = "Follow-up date and time are required for this progress type.";
    }

    // Validate fields for 'Quote Sent'
    if ($progress == 'Quote Sent') {
        if (empty($_POST['quote_price']) || empty($quote_screenshot)) {
            $errors[] = "For 'Quote Sent', both a quote price and a quote screenshot must be provided.";
        }
    }

    // Validate required fields for 'Converted'
    if ($progress == 'Converted') {
        if (empty($_POST['converted_price']) || empty($_POST['conversion_date']) || empty($payment_screenshot)) {
            $errors[] = "For 'Converted' leads, all required fields (converted_price, conversion_date) and a payment screenshot must be filled.";
        }
    }
if (empty($lead['form']) && !in_array($progress, ['Didnt Connect', 'Converted', 'Lost'])) {
    if (empty($form_quality)) {
        $errors[] = "Lead quality (Cold, Warm, Hot, Super Hot) is required.";
    } else {
        // Validate form quality
        $valid_qualities = ['Cold', 'Warm', 'Hot', 'Super Hot'];
        if (!in_array($form_quality, $valid_qualities)) {
            $errors[] = "Invalid lead quality.";
        }
    }
}
    // Validate file upload for 'Lost'
    if ($progress == 'Lost' && (!$audio_recording || $audio_recording['error'] === UPLOAD_ERR_NO_FILE)) {
    $errors[] = "For 'Lost' leads, an audio recording is required.";
}

    // If there are no errors, proceed with the update
    if (empty($errors)) {
        $file_path = null;
        $quote_screenshot_path = null;
        $payment_screenshot_path = null;
        $audio_recording_path = null;

        // Function to handle file upload
        function handleFileUpload($file, $lead_dir) {
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        if (!file_exists($lead_dir)) {
            mkdir($lead_dir, 0777, true);
        }
        $file_path = $lead_dir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return $file_path;
        } else {
            return null; // Return null if file upload fails
        }
    }
    return null;
}


        // Setup directory and handle file uploads
        $upload_dir = 'uploads/';
        $current_month = date('Y-m');
        $lead_name = preg_replace('/[^a-zA-Z0-9-_]/', '_', $lead['name']); // Sanitize lead name
        $lead_dir = $upload_dir . $current_month . '/' . $lead_name . '/';

        $file_path = handleFileUpload($file, $lead_dir);
        $quote_screenshot_path = handleFileUpload($quote_screenshot, $lead_dir);
        $payment_screenshot_path = handleFileUpload($payment_screenshot, $lead_dir);
        $audio_recording_path = handleFileUpload($audio_recording, $lead_dir);

        if (($progress == 'Quote Sent' && !$quote_screenshot_path) || ($progress == 'Converted' && !$payment_screenshot_path)) {
            $errors[] = "Failed to upload required file(s).";
        } else {
            $current_time = date('Y-m-d H:i:s');
            $sql = "INSERT INTO lead_notes (lead_id, note, followup_date, followup_time, file_path, quote_screenshot, payment_screenshot, audio_recording, progress, created_at, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                // Type definition string: "i" for integer, "s" for string
                mysqli_stmt_bind_param($stmt, "isssssssssi", $lead_id, $note, $followup_date, $followup_time, $file_path, $quote_screenshot_path, $payment_screenshot_path, $audio_recording_path, $progress, $current_time, $owner);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['last_lead_note_time'] = time(); // Store the current timestamp

            } else {
                $errors[] = "Failed to prepare the statement.";
            }


            // Update lead progress in the leads table
            $sql = "UPDATE leads SET audio_call_recording = ?, progress = ?, category = ?, note = ?, followup_date = ?, followup_time = ?, quote_screenshot = ?, payment_screenshot = ?, quote_price = ?, converted_price = ?, conversion_date = ?, verification_status = ?, verified_by = ?, form = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                

                // Check if lead is being marked as "Lost"
                if ($progress === "Lost") {
                    $sql_check = "SELECT verification_status FROM leads WHERE id = ?";
                    if ($stmt_check = mysqli_prepare($link, $sql_check)) {
                        mysqli_stmt_bind_param($stmt_check, "i", $lead_id);
                        mysqli_stmt_execute($stmt_check);
                        mysqli_stmt_bind_result($stmt_check, $current_verification_status);
                        mysqli_stmt_fetch($stmt_check);
                        mysqli_stmt_close($stmt_check);
                        
                        if ($current_verification_status === "Pending") {
                            $verification_status = "Verified";
                            $verified_by = $_SESSION['id'];
                            
                        }
                        if($current_verification_status === NULL){
                            $verification_status = "Pending";
                            $verified_by = NULL;
                            
                        }
                    }
                }
                    if ($progress == "Didnt Connect") {
                        $sql = "UPDATE leads SET owner = NULL, progress = 'Didnt Connect' WHERE id = ? AND powner = '" . $_SESSION['id'] . "'";
                        if ($stmt = mysqli_prepare($link, $sql)) 
                        {
                            mysqli_stmt_bind_param($stmt, "i", $lead_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    }

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssssssisi",
                    $audio_recording_path,
                    $progress,
                    $category,
                    $note,
                    $followup_date,
                    $followup_time,
                    $quote_screenshot_path,
                    $payment_screenshot_path,
                    $_POST['quote_price'],
                    $_POST['converted_price'],
                    $_POST['conversion_date'],
                    $verification_status,
                    $verified_by,
                    $form_quality,
                    $lead_id
                );
                     // Set owner to NULL and update powner if progress is 'Didnt Connect' or verification status is 'Pending'
                    if ($verification_status == "Pending") {
                        $sql = "UPDATE leads SET owner = NULL, powner = ?, progress = 'Didnt Connect' WHERE id = ?";
                        if ($stmt = mysqli_prepare($link, $sql)) {
                            mysqli_stmt_bind_param($stmt, "ii", $_SESSION['id'], $lead_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    }
            
                    // Set owner to powner if verification status is 'Verified'
                    if ($verification_status == "Verified") {
                        $sql = "UPDATE leads SET owner = powner WHERE id = ?";
                        if ($stmt = mysqli_prepare($link, $sql)) {
                            mysqli_stmt_bind_param($stmt, "i", $lead_id);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);


                header("Location: showmyleads.php");
                $success = "Lead progress updated successfully.";
            } else {
                $errors[] = "Failed to update lead progress.";
            }
        }
    }
}

if (isset($lead_id) && is_int($lead_id)) {
    $timeline = [];
if ($lead_id) {
    $sql = "SELECT lead_notes.*, users.username 
            FROM lead_notes 
            JOIN users ON lead_notes.owner = users.id 
            WHERE lead_notes.lead_id = ? 
            ORDER BY lead_notes.created_at DESC";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $lead_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $timeline[] = $row;
                }
            } else {
                echo "Error: " . mysqli_error($link);
            }
        } else {
            echo "Execute Error: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "Prepare Error: " . mysqli_error($link);
    }
} else {
    echo "No lead_id provided.";
}

mysqli_close($link);

} else {
    echo "Invalid or missing lead_id.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Action</title>
    <link rel="stylesheet" href="css/lead_action.css">
    <script>
    
        //   function loadNote() {
        //     var savedNote = localStorage.getItem('note');
        //     if (savedNote) {
        //         document.getElementById('note').value = savedNote;
        //         updateCharacterCount(savedNote.length); // Update character count when page loads
        //     }
        // }

        // // Function to save the note to localStorage
        // function saveNote() {
        //     var noteText = document.getElementById('note').value;
        //     localStorage.setItem('note', noteText);
        // }

        // Function to update the character count display
        function updateCharacterCount(count) {
            document.getElementById('charCount').innerText = count + "/40";
            const updateButton = document.getElementById('update_progress');

            // Disable the button if character count is less than 40
            if (count < 40) {
                updateButton.disabled = true; // Disable the button until the note has at least 40 characters
            } else {
                updateButton.disabled = false; // Enable the button when the note has 40 or more characters
            }
        }

        // Add event listener to update character count in real-time
        document.addEventListener('DOMContentLoaded', function() {
            const noteText = document.getElementById('note');
            noteText.addEventListener('input', function() {
                saveNote(); // Save note to localStorage as user types
                updateCharacterCount(noteText.value.length); // Update character count display
            });

            loadNote(); // Load the note from localStorage when the page loads

            // Event listener for form submission
            document.getElementById('update_progress').addEventListener('click', function(event) {
                var noteTextValue = document.getElementById('note').value;

                // Check if the note is less than 40 characters
                if (noteTextValue.trim().length < 40) {
                    // Prevent form submission if validation fails
                    event.preventDefault();
                    // Display error message
                    document.getElementById('errorMessage').innerText = 'Note must be at least 40 characters long.';
                } else {
                    // Clear error message if input is valid
                    document.getElementById('errorMessage').innerText = '';

                    // Clear the note from localStorage upon successful submission
                    localStorage.removeItem('note');
                }
            });

        document.addEventListener("DOMContentLoaded", function() {
            const form = document.querySelector("form");
            const followupDateInput = document.getElementById('followup_date');
            const followupTimeInput = document.getElementById('followup_time');
            const progressSelect = document.getElementById('progress');
            const convertedFields = document.getElementById('converted-fields');
            const quoteSentFields = document.getElementById('quote-sent-fields');
            const fileInput = document.getElementById('file');

            // Set up current date and max date
            const today = new Date();
            const maxDate = new Date();
            maxDate.setDate(today.getDate() + 3);

            // Format date as YYYY-MM-DD
            function formatDate(date) {
                const year = date.getFullYear();
                const month = ('0' + (date.getMonth() + 1)).slice(-2);
                const day = ('0' + date.getDate()).slice(-2);
                return `${year}-${month}-${day}`;
            }

            // Set min and max date for follow-up date input
            followupDateInput.min = formatDate(today);
            followupDateInput.max = formatDate(maxDate);

            // Function to toggle visibility of fields based on progress
            function toggleFields() {
                if (progressSelect.value === 'Converted') {
                    convertedFields.style.display = 'block';
                    quoteSentFields.style.display = 'none';
                } else if (progressSelect.value === 'Quote Sent') {
                    convertedFields.style.display = 'none';
                    quoteSentFields.style.display = 'block';
                } else {
                    convertedFields.style.display = 'none';
                    quoteSentFields.style.display = 'none';
                }
            }

            // Initialize visibility based on current progress
            toggleFields();

            // Add event listener to handle changes in progress selection
            progressSelect.addEventListener('change', toggleFields);

            // Form validation before submission
            form.addEventListener('submit', function(event) {
                const progress = progressSelect.value;
                const file = fileInput.files.length;

                if (progress === 'Lost' && file === 0) {
                    alert("For 'Lost' leads, an audio recording is required.");
                    event.preventDefault(); // Prevent form submission
                }
            });
        });
    </script>
</head>
<body>
    <?php if (isset($lead)): ?>
        <div class="header">
            <div class="center-container">
                <div class="lead-details">
                    <h2>Lead Information</h2>
                    <p><strong>Lead Name:</strong> <?php echo htmlspecialchars($lead['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($lead['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($lead['phone']); ?></p>
                    <p><strong>Created Time:</strong> <?php echo htmlspecialchars($lead['created_at']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($lead['category']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($lead['progress']); ?></p>
                    <?php if ($lead['followup_date'] !== '0000-00-00' || $lead['followup_time'] !== '00:00:00'): ?>
                        <p><strong>Follow-up Date:</strong> <?php echo htmlspecialchars($lead['followup_date']); ?></p>
                        <p><strong>Follow-up Time:</strong> <?php echo htmlspecialchars($lead['followup_time']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($lead['audio_call_recording'])): ?>
                        <p><strong>Audio Recording:</strong></p>
                        <audio controls>
                            <source src="<?php echo htmlspecialchars($lead['audio_call_recording']); ?>" >
                            Your browser does not support the audio element.
                        </audio>
                    <?php endif; ?>

                    <?php if (!empty($lead['quote_screenshot'])): ?>
                        <p><strong>Quote Screenshot:</strong> <img
                                src="<?php echo htmlspecialchars($lead['quote_screenshot']); ?>" width="200" height="400"></p>
                    <?php endif; ?>
                    <?php if (!empty($lead['payment_screenshot'])): ?>
                        <p><strong>Payment Screenshot:</strong> <img
                                src="<?php echo htmlspecialchars($lead['payment_screenshot']); ?>" width="200" height="400"></p>
                    <?php endif; ?>

                    <p><strong>Owner Name:</strong> <?php echo htmlspecialchars($lead['username']); ?></p>
                </div>
            </div>
        <?php endif; ?>


        <div class="lead-action-container">
            <h2>Update Lead Progress</h2>

            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="lead_action.php?id=<?php echo $lead_id; ?>" method="post" enctype="multipart/form-data">
                <label for="progress">Progress:</label>
                <select name="progress" id="progress" required>
                    <option value="Fresh Lead" <?php echo $lead['progress'] == 'Fresh Lead' ? 'selected' : ''; ?>>Fresh Lead</option>
                    <option value="Follow-up" <?php echo $lead['progress'] == 'Follow-up' ? 'selected' : ''; ?>>
                        Follow-up
                    </option>
                    <option value="Converted" <?php echo $lead['progress'] == 'Converted' ? 'selected' : ''; ?>>
                        Converted
                    </option>
                    <option value="Lost" <?php echo $lead['progress'] == 'Lost' ? 'selected' : ''; ?>>Lost</option>
                    <option value="Didnt Connect" <?php echo $lead['progress'] == 'Didnt Connect' ? 'selected' : ''; ?>>
                       Lost( Didnt Connect) </option>
                    <option value="First Call Done"
                        <?php echo $lead['progress'] == 'First Call Done' ? 'selected' : ''; ?>>
                        Follow-up(First Call Done)</option>
                    <option value="Quote Sent" <?php echo $lead['progress'] == 'Quote Sent' ? 'selected' : ''; ?>>Follow-up(Quote
                        Sent)
                    </option>

                </select>
                <div id="errorMessage" style="color: red;"></div>
                <label for="note">Note:</label>
                <textarea name="note" id="note" rows="4" required></textarea>
                  <div id="charCount">0/40</div> <!-- Display for character count -->
                 <div class="form-group">
                <label for="form_quality">Lead Quality:</label>
                <?php if (empty($lead['form'])): ?>
                    <select name="form_quality" id="form_quality">
                        <option value="">Select Quality</option>
                        <option value="Cold">Cold</option>
                        <option value="Warm">Warm</option>
                        <option value="Hot">Hot</option>
                        <option value="Super Hot">Super Hot</option>
                    </select>
                <?php else: ?>
                    <p><?php echo htmlspecialchars($lead['form']); ?></p>
                <?php endif; ?>
            </div>


                <label for="followup_date">Follow-up Date:</label>
                <input type="date" name="followup_date" id="followup_date"
                    value="<?php echo htmlspecialchars($lead['followup_date']); ?>">

                <label for="followup_time">Follow-up Time:</label>
                <input type="time" name="followup_time" id="followup_time"
                    value="<?php echo htmlspecialchars($lead['followup_time']); ?>">

                <div id="converted-fields"
                    style="display: <?php echo $lead['progress'] == 'Converted' ? 'block' : 'none'; ?>;">
                    <label for="converted_price">Converted Price:</label>
                    <input type="text" name="converted_price" id="converted_price"
                        value="<?php echo htmlspecialchars($lead['converted_price']); ?>">

                    <label for="conversion_date">Conversion Date:</label>
                    <input type="date" name="conversion_date" id="conversion_date"
                        value="<?php echo htmlspecialchars($lead['conversion_date']); ?>">

                    <label for="payment_screenshot">Payment Screenshot:</label>
                    <input type="file" name="payment_screenshot" id="payment_screenshot" accept="image/*">
                </div>

                <div id="quote-sent-fields"
                    style="display: <?php echo $lead['progress'] == 'Quote Sent' ? 'block' : 'none'; ?>;">
                    <label for="quote_price">Quote Price:</label>
                    <input type="text" name="quote_price" id="quote_price"
                        value="<?php echo htmlspecialchars($lead['quote_price']); ?>">

                    <label for="quote_screenshot">Quote Screenshot:</label>
                    <input type="file" name="quote_screenshot" id="quote_screenshot" accept="image/*">
                </div>
                <div id="lost-fields" style="display: <?php echo $lead['progress'] == 'Lost' ? 'block' : 'none'; ?>;">
                    <label for="audio_recording">Audio Recording (required for 'Lost'):</label>
                    <input type="file" name="audio_recording" id="file" accept="audio/*">
                </div>
                <label for="optional file">optional file:</label>
                <input type="file" name="file" id="file2" accept="image/*">

               

                <button type="submit" name="update_progress" id = "update_progress">Update Progress</button>
            </form>
        </div>
        </div>
<div class="nextHead">
  <h2>Lead Timeline</h2>
<?php
date_default_timezone_set('Asia/Kolkata'); // Ensure the correct timezone is set

// Assume $userRole and $verification_status are obtained from session or user data
$userRole = $_SESSION["role"] ?? ''; // Example: get user role from session
$verification_status = $lead['verification_status'] ?? ''; // Example: get verification status from lead data

// Check if the user is an Admin or the verification status is not 'Pending' before showing the timeline
if ($userRole === 'Admin' || $verification_status !== 'Pending'):
    if (!empty($timeline)): ?>
        <ul class="timeline">
            <?php foreach ($timeline as $entry): ?>
                <?php
                // Check if the user is not an admin and progress is "Didnt Connect" or "Lost"
                if ($userRole !== 'Admin' && ( $entry['progress'] === "Lost")) {
                    // If the user is not an admin and progress is "Didn't Connect" or "Lost", skip this entry
                    continue;
                }
                ?>
                <li>
                    <strong><?php echo htmlspecialchars($entry['note']); ?></strong>
                    <br> <strong> Created at: </strong>
                    <small><?php
                                    // $timeZone = new DateTimeZone('Asia/Kolkata'); // Set the timezone to match your data
                                    // $created_at = new DateTime($entry['created_at'], $timeZone);
                                    // $now = new DateTime('now', $timeZone); // Current time in the same timezone
                                    // $interval = $now->diff($created_at);

                                    // // Handle the formatting based on the difference
                                    // if ($interval->y > 0) {
                                    //     echo $interval->format('%y years %m months %d days %h hours %i minutes ago');
                                    // } elseif ($interval->m > 0) {
                                    //     echo $interval->format('%m months %d days %h hours %i minutes ago');
                                    // } elseif ($interval->d > 0) {
                                    //     echo $interval->format('%d days %h hours %i minutes ago');
                                    // } elseif ($interval->h > 0) {
                                    //     echo $interval->format('%h hours %i minutes ago');
                                    // } else {
                                    //     echo $interval->format('%i minutes ago');
                                    // }
                                    $timeZone = new DateTimeZone('Asia/Kolkata');
                                    $created_at = new DateTime($entry['created_at'], $timeZone);
                                    echo "" . $created_at->format('Y-m-d H:i:s'); 
                                    ?>
                    </small>
                    <br><strong>Progress:</strong> <?php echo htmlspecialchars($entry['progress']); ?>
                    <br><strong>Follow-up Date:</strong>
                    <?php echo htmlspecialchars($entry['followup_date'] != '0000-00-00' ? $entry['followup_date'] : 'N/A'); ?>
                    <br><strong>Follow-up Time:</strong>
                    <?php echo htmlspecialchars($entry['followup_time'] != '00:00:00' ? $entry['followup_time'] : 'N/A'); ?>
                    <br><strong>Last Updated by:</strong> <?php echo htmlspecialchars($entry['username']); ?>

                    <?php if (!empty($entry['file_path'])): ?>
                        <br><strong>File:</strong>
                        <img src="<?php echo htmlspecialchars($entry['file_path']); ?>" alt="File"
                            style="max-width: 100%; height: auto;" oncontextmenu="return false;">
                    <?php endif; ?>

                    <?php if (!empty($entry['quote_screenshot'])): ?>
                        <br><strong>Quote Screenshot:</strong>
                        <img src="<?php echo htmlspecialchars($entry['quote_screenshot']); ?>" alt="Quote Screenshot"
                            style="max-width: 100%; height: auto;" oncontextmenu="return false;">
                    <?php endif; ?>

                    <?php if (!empty($entry['payment_screenshot'])): ?>
                        <br><strong>Payment Screenshot:</strong>
                        <img src="<?php echo htmlspecialchars($entry['payment_screenshot']); ?>" alt="Payment Screenshot"
                            style="max-width: 100%; height: auto;" oncontextmenu="return false;">
                    <?php endif; ?>

                    <?php if (!empty($entry['audio_recording'])): ?>
                        <p><strong>Audio Recording:</strong></p>
                        <audio controls>
                            <source src="<?php echo htmlspecialchars($entry['audio_recording']); ?>" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                        <button onclick="generateTranscript('<?php echo htmlspecialchars($entry['audio_recording']); ?>')">Generate Transcript</button>
                        <!-- Added p tag to display transcript -->
                        <p id="loading" style="display: none;">Loading...</p>
                        <p id="transcript">Transcript will be displayed here.</p>
                    <?php endif; ?>

                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No timeline entries found.</p>
    <?php endif;
else: ?>
    <p>The lead timeline is not available due to pending verification status.</p>
<?php endif; ?>
</div>
<script>
        const headerWrapper = document.getElementById('navbar');

    // Initially hide the header
    headerWrapper.style.display = 'none';

    // After 1 minute (60000 milliseconds), show the header
    // setTimeout(function() {
    //     headerWrapper.style.display = 'block';
    // }, 60000); // 60000 ms = 1 minute
     
    
    
function generateTranscript(audioFilePath) {
    // Show loading indicator
    var loadingIndicator = document.getElementById('loading');
    if (loadingIndicator) {
        loadingIndicator.style.display = 'block';
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'generate_transcript.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function () {
        // Hide loading indicator
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }

        if (xhr.status >= 200 && xhr.status < 300) {
            var response = JSON.parse(xhr.responseText);
            var transcriptP = document.getElementById('transcript');
            if (transcriptP) {
                if (response.error) {
                    transcriptP.innerText = 'Error: ' + response.error;
                } else {
                    // Decode HTML entities if necessary
                    transcriptP.innerText = response.text || 'No transcript available';
                }
            } else {
                console.error('Transcript element not found for audio file path:', audioFilePath);
            }
        } else {
            console.error('Request failed. Returned status of ' + xhr.status);
        }
    };

    xhr.onerror = function () {
        // Hide loading indicator on error
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
        console.error('Request failed.');
    };

    xhr.send('audio_file_path=' + encodeURIComponent(audioFilePath));
}


</script>
</body>

</html>