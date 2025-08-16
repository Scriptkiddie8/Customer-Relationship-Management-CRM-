<?php
include('methods/database.php');  // Include your database connection

session_start();

// Log errors to a file
ini_set('display_errors', 0);           // Disable error display
ini_set('log_errors', 1);               // Enable error logging
ini_set('error_log', 'php-error.log');  // Log errors to this file
error_reporting(E_ALL);                 // Report all errors

// Start session safely
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to prevent any extra output before JSON
ob_start();



// Function to credit one earned leave on the 1st of every month
function creditEarnedLeave($link, $userId) {
    // Check if today is the 1st of the month
    if (date('j') == 1) {
        // Check if the user has already been credited with earned leave this month
        $check_sql = "SELECT COUNT(*) as count FROM leave_requests WHERE id = ? AND leave_type = 'Earned Leave' AND MONTH(from_date) = MONTH(CURRENT_DATE()) AND YEAR(from_date) = YEAR(CURRENT_DATE())";
        
        if ($stmt = mysqli_prepare($link, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $count);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            // If the count is 0, credit one earned leave
            if ($count == 0) {
                $fromDate = date('Y-m-01'); // First of the current month
                $toDate = date('Y-m-01');   // Same date for earned leave (single day)
                $leaveReason = 'Monthly Earned Leave';
                $status = 'approved';       // Automatically approve

                // Insert the earned leave for the user
                $insert_sql = "INSERT INTO leave_requests (id, employee_name, leave_type, from_date, to_date, reason, status, created_at) 
                               VALUES (?, ?, 'Earned Leave', ?, ?, ?, ?, NOW())";
                
                if ($insert_stmt = mysqli_prepare($link, $insert_sql)) {
                    $userName = $_SESSION['username']; // Assuming username is stored in session
                    mysqli_stmt_bind_param($insert_stmt, "issss", $userId, $userName, $fromDate, $toDate, $leaveReason, $status);
                    mysqli_stmt_execute($insert_stmt);
                    mysqli_stmt_close($insert_stmt);
                }
            }
        }
    }
}

// Call this function after session starts
$userId = $_SESSION['id'];  // Fetch the current user's ID from the session
creditEarnedLeave($link, $userId);



// Function to get the user's earned leave balance
function getEarnedLeaveBalance($link, $userId) {
    $sql = "SELECT COUNT(*) as count FROM leave_requests WHERE id = ? AND leave_type = 'Earned Leave' AND status = 'approved'";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $count;
    }
    return 0;
}

// Get the user's earned leave balance
$earnedLeaveBalance = getEarnedLeaveBalance($link, $userId);


// Fetch the user's leave requests for the initial page load
$status = isset($_GET['status']) ? $_GET['status'] : 'all';  // Status filter

// Construct the base query to fetch leave requests
$sql_fetch = "SELECT * FROM leave_requests WHERE id = ?";

// Apply the status filter dynamically
if ($status == 'pending') {
    $sql_fetch .= " AND status = 'pending'";
} elseif ($status == 'approved') {
    $sql_fetch .= " AND status = 'approved'";
} elseif ($status == 'rejected') {
    $sql_fetch .= " AND status = 'rejected'";
} // 'all' shows everything, so no extra condition

$sql_fetch .= " ORDER BY created_at DESC"; // Sort by creation date

// Prepare and execute the query
if ($stmt = mysqli_prepare($link, $sql_fetch)) {
    mysqli_stmt_bind_param($stmt, "i", $userId); // Bind the userId to the query
    mysqli_stmt_execute($stmt); // Execute the query
    $result = mysqli_stmt_get_result($stmt); // Fetch the result

    // Check if any records exist
    if (mysqli_num_rows($result) == 0) {
        $result = null;
    }
} else {
    echo "Error: Could not prepare the query: " . mysqli_error($link);
}


// Function to get the count of leave requests by user and status
function getLeaveCountByUser($link, $userId, $status = null) {
    if ($status) {
        $sql = "SELECT COUNT(*) as count FROM leave_requests WHERE id = ? AND status = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "is", $userId, $status);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $count);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $count;
        }
    } else {
        $sql = "SELECT COUNT(*) as count FROM leave_requests WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $count);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $count;
        }
    }
    return 0;
}

// Fetch counts for each status based on the user's leave requests
$userId = $_SESSION['id'];  // Fetch the current user's ID from the session
$user_all_count = getLeaveCountByUser($link, $userId);  // Total requests by user
$user_pending_count = getLeaveCountByUser($link, $userId, 'pending');  // Pending requests by user
$user_approved_count = getLeaveCountByUser($link, $userId, 'approved');  // Approved requests by user
$user_rejected_count = getLeaveCountByUser($link, $userId, 'rejected');  // Rejected requests by user


// AJAX request handling for form submission
if (isset($_POST['ajax']) && $_POST['ajax'] == 'submitLeaveForm') {
    // Fetch data from the form
    $leaveType = $_POST['leaveType'];
    $fromDate = $_POST['fromDate'];
    $toDate = $_POST['toDate'];
    $leaveReason = $_POST['leaveReason'];

    // Set the timezone to Indian Standard Time (Asia/Kolkata)
    date_default_timezone_set('Asia/Kolkata');

    // Assuming you have already stored user data in the session
    $userId = $_SESSION['id'];  // Fetch the user ID from the session
    $userName = $_SESSION['username'];  // Fetch the user name from the session
    $createdAt = date('Y-m-d H:i:s');  // Get current date and time
    $status = 'pending';  // Set the default status
    $targetDir = "uploads/medical_proofs/"; // Directory to save uploaded files
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true); // Create directory if it doesn't exist
    }

    $filePath = null;
    if (isset($_FILES['medicalProof']) && $_FILES['medicalProof']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['medicalProof']['tmp_name'];
        $fileName = basename($_FILES['medicalProof']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
        // Generate a unique filename to avoid conflicts
        $uniqueFileName = uniqid() . "_" . $fileName;
        $filePath = $targetDir . $uniqueFileName;
    
        // Move the file to the target directory
        if (!move_uploaded_file($fileTmpPath, $filePath)) {
            // Clean the output buffer before sending JSON response
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'File upload failed.']);
            exit;
        }
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or file upload error.']);
        exit;
    }

    // Use a prepared statement to prevent SQL injection
    $sql = "INSERT INTO leave_requests (id, employee_name, leave_type, from_date, to_date, reason, status, created_at,medical_proof_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)";

    if ($stmt = mysqli_prepare($link, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "issssssss", $userId, $userName, $leaveType, $fromDate, $toDate, $leaveReason, $status, $createdAt,$filePath);

        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Fetch the inserted record to return as a response to the AJAX call
            $inserted_id = mysqli_insert_id($link);
            $fetch_sql = "SELECT * FROM leave_requests WHERE request_id = $inserted_id";
            $fetch_result = mysqli_query($link, $fetch_sql);
            $new_leave = mysqli_fetch_assoc($fetch_result);

            // Clean the output buffer before sending JSON response
            ob_clean();
            
            // Return JSON data for the newly inserted leave request
            echo json_encode([
                'status' => 'success',
                'leave' => [
                    'employee_name' => $new_leave['employee_name'],
                'leave_type' => $new_leave['leave_type'],
                'from_date' => date('M j, Y', strtotime($new_leave['from_date'])),
                'to_date' => date('M j, Y', strtotime($new_leave['to_date'])),
                'reason' => $new_leave['reason'],
                'status' => ucfirst($new_leave['status']),
                'medical_proof_path' => $new_leave['medical_proof_path'] 
                ]
            ]);
        } else {
            // Clean the output buffer before sending JSON response
            ob_clean();
            
            echo json_encode(['status' => 'error', 'message' => mysqli_stmt_error($stmt)]);
        }

        // Close the statement
        mysqli_stmt_close($stmt);
    } else {
        // Clean the output buffer before sending JSON response
        ob_clean();

        echo json_encode(['status' => 'error', 'message' => mysqli_error($link)]);
    }

    // Close the connection
    mysqli_close($link);

    // Flush the output buffer to ensure the JSON is sent
    ob_end_flush();

    exit();  // Stop further script execution since this is an AJAX request
}

// Fetch the user's leave requests for the initial page load
$sql_fetch = "SELECT * FROM leave_requests WHERE id = ? ORDER BY created_at DESC";
if ($stmt = mysqli_prepare($link, $sql_fetch)) {
    // Bind the userId to the query
    $userId = $_SESSION['id'];
    mysqli_stmt_bind_param($stmt, "i", $userId);
    // Execute the statement
    mysqli_stmt_execute($stmt);
    // Get the result
    $result = mysqli_stmt_get_result($stmt);
    // Check if any records exist
    if (mysqli_num_rows($result) == 0) {
        $result = null;
    }
} else {
    echo "Error: Could not prepare the query: " . mysqli_error($link);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leaves</title>
    <style>
            /* Modal styling */
    .modal {
        display: none; /* Hidden by default */
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.8);
    }
    .modal-content {
        display: block;
        margin: auto;
        max-width: 80%;
        max-height: 80%;
    }
    .close {
        position: absolute;
        top: 20px;
        right: 35px;
        color: #fff;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
    }
    </style>
</head>

<body>
    <div class="container2">
        <div class="header">
            <!-- Include header -->
            <?php include "header.php"; ?>
        </div>

        <div class="body-container">
            <div class="left">
                <!-- Left sidebar (includes leaves_request.php) -->
                <?php include 'leaves_request.php' ?>
            </div>
            <div class="right">
                <div class="filter-section">
                    <!-- Filter buttons -->
                </div>

                <div class="Lapply">
                        <!-- Apply Leaves button -->
                        <button value="submit" onclick="openApplyForm()">Apply Leaves</button>
                </div>


                <div class="tab-container">
                    <div class="tab active">Leave Applications</div>
                    <div class="tab">Regularization</div>
                </div>

                


                <!-- Status Summary Section with User-Specific Counts -->
                <div class="status-summary">
                    <div class="status-item all" onclick="filterStatus('all')">
                        <i class="fas fa-list"></i>
                        <i class='bx bx-list-ul'></i>
                        <span>All :</span> <span class="count"><?php echo $user_all_count; ?></span>
                    </div>
                    <div class="status-item pending" onclick="filterStatus('pending')">
                        <i class="fas fa-clock"></i>
                        <i class='bx bxs-pin' style="color: #ffbb55;"></i>
                        <span>Pending :</span> <span class="count"><?php echo $user_pending_count; ?></span>
                    </div>
                    <div class="status-item approved" onclick="filterStatus('approved')">
                        <i class="fas fa-check"></i>
                        <i class='bx bxs-check-square' style="color: #32c766;"></i>
                        <span>Approved :</span> <span class="count"><?php echo $user_approved_count; ?></span>
                    </div>
                    <div class="status-item rejected" onclick="filterStatus('rejected')">
                        <i class="fas fa-times"></i>
                        <i class='bx bx-x-circle' style="color: #ff7782;"></i>
                        <span>Rejected :</span> <span class="count"><?php echo $user_rejected_count; ?></span>
                    </div>

                </div>

                <div class="inner-right">
                    <div class="outer-right">
                        

                        <!-- Display leave requests dynamically -->
                        <div class="leave-list" id="leave-list">
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <div class="leave-item">
                                    <div class="leave-type">
                                        <div><?php echo $row['employee_name']; ?></div>
                                        <div><?php echo $row['leave_type']; ?></div> 
                                        <div>
                                            <span>From:</span>
                                            <?php echo date('M j, Y', strtotime($row['from_date'])); ?>
                                        </div>  
                                        <div>
                                            <span>To:</span>
                                            <?php echo date('M j, Y', strtotime($row['to_date'])); ?>
                                        </div>
                                    </div>
                                    <button value="submit" class="view-reason" id="view-btn" onclick="alert('<?php echo addslashes($row['reason']); ?>')">View Reason</button>

                                    <?php if (!empty($row['medical_proof_path'])): ?>
                                        <?php 
                                            // Check if the file is an image based on its extension
                                            $fileExt = strtolower(pathinfo($row['medical_proof_path'], PATHINFO_EXTENSION));
                                            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png']);
                                        ?>
                                        <?php if ($isImage): ?>
                                            <button class="view-file" onclick="showImageModal('<?php echo htmlspecialchars($row['medical_proof_path']); ?>')">View File</button>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($row['medical_proof_path']); ?>" target="_blank" class="view-file">View File</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-file">No File Uploaded</span>
                                    <?php endif; ?>
                                    <div class="status-dynamic">
                                        <div class="status <?php echo $row['status']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <p>No leave requests found for this Duration.</p>
                            <?php endif; ?>
                        </div>
                        <!-- Modal Structure for Viewing Images -->
                        <div id="imageModal" class="modal" style="display: none;">
                            <span class="close" onclick="closeImageModal()">&times;</span>
                            <img class="modal-content" id="modalImage" alt="Medical Proof Image">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal popup (hidden by default) -->
        <div class="popup" id="applyLeaveForm">
            <div class="pop-container">
                <h2>Apply Leave <span class="close-btn" onclick="closeApplyForm()">&times;</span></h2>

                <!-- Form to submit the leave request -->
                <form id="leaveRequestForm" method="POST" action="">
                    <select id="leaveType" name="leaveType" required>
                        <option value="" disabled selected hidden>Select Leave Type</option>
                        <option value="Medical Leave">Medical Leave</option>
                        <option value="Planned Leave">Planned Leave</option>
                    </select>

                    <div class="days-info">
                        <div>Earned Leave Balance: <?php echo $earnedLeaveBalance; ?> day(s)</div>
                    </div>

                    <div class="date-picker">
                        <div>
                            <input type="date" id="fromDate" name="fromDate" required>
                        </div>
                        <div>
                            <input type="date" id="toDate" name="toDate" required>
                        </div>
                    </div>

                    <!-- Dynamically added medical proof upload section will appear here -->
                    <div  id="medicalProofContainer"></div>

                            
                    <div class="leave-status" id="leaveApplicationInfo">Leave Application For:</div>
                    <div class="error-msg" id="errorMessage" style="color:red; font-weight:bold;"></div>

                    <textarea id="leaveReason" name="leaveReason" placeholder="Leave Reason"></textarea>

                    <button type="submit">Request Leave</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        // Function to show the image in a modal
        function showImageModal(imageSrc) {
                const modal = document.getElementById("imageModal");
                const modalImage = document.getElementById("modalImage");
                modalImage.src = imageSrc;
                modal.style.display = "block";
            }

            // Function to close the image modal
            function closeImageModal() {
                const modal = document.getElementById("imageModal");
                modal.style.display = "none";
                document.getElementById("modalImage").src = ""; // Clear the image source
            }

            // Close the modal when clicking outside the image
            window.onclick = function(event) {
                const modal = document.getElementById("imageModal");
                if (event.target === modal) {
                    closeImageModal();
                }
            };
        // Open and close modal
        function openApplyForm() {
            document.getElementById('applyLeaveForm').style.display = 'flex';
        }

        function closeApplyForm() {
            document.getElementById('applyLeaveForm').style.display = 'none';
        }


        // Disable date inputs for 2 weeks if 'Planned Leave' is selected
        document.getElementById('leaveType').addEventListener('change', function () {
            const leaveType = this.value;
            const fromDate = document.getElementById('fromDate');
            const toDate = document.getElementById('toDate');
            const medicalProofContainer = document.getElementById('medicalProofContainer');

            if (leaveType === 'Planned Leave') {
                // Get today's date
                const today = new Date();
                
                // Calculate the date 14 days from today
                const minDate = new Date();
                minDate.setDate(today.getDate() + 14);

                // Format the date as YYYY-MM-DD for the input field
                const formattedMinDate = minDate.toISOString().split('T')[0];

                // Set the min attribute of both fromDate and toDate inputs
                fromDate.min = formattedMinDate;
                toDate.min = formattedMinDate;

                // Optionally, clear any previously selected dates that don't meet the requirement
                fromDate.value = '';
                toDate.value = '';

                // Remove the medical proof upload option if present
                medicalProofContainer.innerHTML = '';
            }   
            else if (leaveType === 'Medical Leave') {
                const today = new Date();
                const dayBeforeYesterday = new Date();
                dayBeforeYesterday.setDate(today.getDate() - 2);

                medicalProofContainer.classList.add('leave-status');

                // Format the date as YYYY-MM-DD for the input field
                const formattedMinDate = dayBeforeYesterday.toISOString().split('T')[0];

                // Set the min attribute to allow selecting dates from the day before yesterday onwards
                fromDate.min = formattedMinDate;
                toDate.min = formattedMinDate;

                // Add file input for medical proof
                medicalProofContainer.innerHTML = `
                    <div class="medical-proof">
                        <label for="medicalProof" class="upload-medical-proof">Upload Medical Proof:</label>
                        <input type="file" id="medicalProof" name="medicalProof" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>
                `; 
            }            
            else {
                // Reset the min attribute for other leave types (e.g., Medical Leave)
                fromDate.removeAttribute('min');
                toDate.removeAttribute('min');
            }
        });


        const earnedLeaveBalance = <?php echo $earnedLeaveBalance; ?>; // PHP variable for earned leave balance


         // Function to calculate the number of days between two dates
            function calculateDaysBetween() {
                const fromDateInput = document.getElementById('fromDate').value;
                const toDateInput = document.getElementById('toDate').value;
                const leaveApplicationInfo = document.getElementById('leaveApplicationInfo');
                const errorMessage = document.getElementById('errorMessage'); // Error message div


                // Ensure both dates are selected
                if (fromDateInput && toDateInput) {
                    const fromDate = new Date(fromDateInput);
                    const toDate = new Date(toDateInput);

                    // Calculate the difference in time (milliseconds)
                    const timeDifference = toDate.getTime() - fromDate.getTime();

                    // Calculate the difference in days (milliseconds in a day = 1000ms * 60s * 60min * 24h)
                    const dayDifference = Math.ceil(timeDifference / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end dates

                    // Check if the number of days is valid
                    if (dayDifference > 0) {
                        leaveApplicationInfo.innerText = `Leave Application For: ${dayDifference} day(s)`;

                            // Check if selected days exceed earned leave balance
                        if (dayDifference > earnedLeaveBalance) {
                            const extraDays = dayDifference - earnedLeaveBalance;
                            errorMessage.style.color = 'red';
                            errorMessage.innerText = `You are taking ${extraDays} extra day(s).`;
                        } else {
                            // Clear the error message if within balance
                            errorMessage.innerText = '';
                        }

                    } else {
                        leaveApplicationInfo.innerText = 'Invalid date range selected!';
                        errorMessage.innerText = '';
                    }
                }
                else {
                // Reset to default if one or both dates are not selected
                leaveApplicationInfo.innerText = "Leave Application For:";
                errorMessage.innerText = '';
                }
            }

            // Attach event listeners to detect changes in the date fields
            document.getElementById('fromDate').addEventListener('change', calculateDaysBetween);
            document.getElementById('toDate').addEventListener('change', calculateDaysBetween);



            // Function to apply the status filter
            function filterStatus(status) {
                 window.location.href = "?status=" + status;
            }

        // Submit form using AJAX and dynamically update the leave list
        document.getElementById('leaveRequestForm').addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent the form from submitting traditionally

            // Collect form data
            const formData = new FormData(this);
            formData.append('ajax', 'submitLeaveForm');

            // Send form data to server using AJAX
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Add the new leave request to the leave list
                    const leaveList = document.getElementById('leave-list');
                    const newLeave = `
                        <div class="leave-item">
                            <div class="leave-type">
                                <div>${data.leave.employee_name}</div>
                                <div>${data.leave.leave_type}</div>
                                <div><span>From:</span> ${data.leave.from_date}</div>
                                <div><span>To:</span> ${data.leave.to_date}</div>
                            </div>
                            <button value="submit" onclick="alert('${data.leave.reason}')">View Reason</button>
                            <div class="status-dynamic">
                                <div class="status ${data.leave.status.toLowerCase()}">
                                    ${data.leave.status}
                                </div>
                            </div>
                        </div>
                    `;
                    leaveList.insertAdjacentHTML('beforeend', newLeave);

                    // Close the form modal
                    closeApplyForm();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred: ' + error.message);
            });
        });
    </script>

</body>

</html>
