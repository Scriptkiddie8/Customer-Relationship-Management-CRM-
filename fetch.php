<?php
// Enable error reporting for debugging
session_start();
echo "Starting session and checking login status...<br>";
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    echo "User not logged in. Redirecting to index.php...<br>";
    header("location: index.php");
    exit;
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Determine the current subdomain
echo "Determining the subdomain...<br>";
$host = $_SERVER['HTTP_HOST'];
echo "Host: $host<br>";
$host_parts = explode('.', $host);
$subdomain = array_shift($host_parts);
echo "Subdomain: $subdomain<br>";

// Database connection settings for the master database
$master_db_host = "localhost";
$master_db_user = "u657474163_mdb";
$master_db_pass = "Shiva@3553";
$master_db_name = "u657474163_master_db";

echo "Connecting to the master database...<br>";
// Create connection to the master database
$master_link = new mysqli($master_db_host, $master_db_user, $master_db_pass, $master_db_name);
if ($master_link->connect_error) {
    die("Master database connection failed: " . $master_link->connect_error . "<br>");
}
echo "Connected to master database.<br>";
$master_link->set_charset("utf8mb4");

// Prepare and execute query to get tenant information
echo "Fetching tenant information for subdomain: $subdomain<br>";
$query = "SELECT id, db_host, db_name, db_user, db_pass FROM tenants WHERE subdomain = ?";
$stmt = $master_link->prepare($query);
if ($stmt === false) {
    die("Failed to prepare query: " . $master_link->error . "<br>");
}
$stmt->bind_param("s", $subdomain);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("No tenant database found for the subdomain: " . $subdomain . "<br>");
}

$stmt->bind_result($tenant_id, $db_host, $db_name, $db_user, $db_pass);
$stmt->fetch();
$stmt->close();
echo "Tenant ID: $tenant_id, Database Host: $db_host, Database Name: $db_name<br>";

// Create connection to the tenant database
echo "Connecting to the tenant database...<br>";
$tenant_link = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($tenant_link->connect_error) {
    die("Tenant database connection failed: " . $tenant_link->connect_error . "<br>");
}
echo "Connected to tenant database.<br>";
$tenant_link->set_charset("utf8mb4");

// Define the time frame
echo "Defining the time frame...<br>";
$now = new DateTime();
$sinceDate = $now->modify('-7 days'); // 7 days prior
$sinceTimestamp = $sinceDate->getTimestamp(); // Convert to Unix timestamp
// $now = new DateTime();
$untilTimestamp = $now->getTimestamp(); // Today's date and time in Unix timestamp

$since = $sinceTimestamp;
$until = $untilTimestamp;
echo "Time frame: Since - $since, Until - $until<br>";

// Open log file for writing
$log_file = fopen("fetch_facebook_leads.log", "a");
echo "Log file opened for writing.<br>";

// Initialize counters and arrays
$leads_inserted = 0;
$leads_skipped = 0;
$errors = [];
$skipped_leads_with_page_id = [];
$leads_with_page_id = [];

// Fetch Facebook credentials specific to the tenant
echo "Fetching Facebook credentials for tenant ID: $tenant_id<br>";
$query = "SELECT access_token, page_id, ad_name FROM facebook_credentials WHERE tenant_id = ?";
$stmt = $master_link->prepare($query);
if ($stmt === false) {
    die("Failed to prepare query: " . $master_link->error . "<br>");
}
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    die("Failed to fetch Facebook credentials: " . $master_link->error . "<br>");
}

// Loop through each Facebook credential
while ($credential = $result->fetch_assoc()) {
    $access_token = $credential['access_token'];
    $page_id = $credential['page_id'];
    $ad_name = $credential['ad_name'];
    echo "Processing Facebook credentials for Page ID: $page_id, Ad Name: $ad_name<br>";

    // Facebook Graph API URL with time frame
    $graph_url = "https://graph.facebook.com/v20.0/{$page_id}/leads?access_token={$access_token}&filtering=[{%22field%22:%22time_created%22,%22operator%22:%22GREATER_THAN%22,%22value%22:{$since}}]&since={$since}&until={$until}";
    echo "Graph API URL: $graph_url<br>";

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $graph_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL request and get response
    echo "Executing cURL request...<br>";
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $curl_error_message = "cURL error for page ID $page_id: " . curl_error($ch);
        fwrite($log_file, date('Y-m-d H:i:s') . " - $curl_error_message" . PHP_EOL);
        curl_close($ch);
        echo $curl_error_message . "<br>";
        continue; // Move to next credential
    }
    curl_close($ch);

    // Decode JSON response
    echo "Decoding response from Facebook API...<br>";
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        $error_message = "Error fetching data from Facebook for page ID $page_id: " . $data['error']['message'];
        fwrite($log_file, date('Y-m-d H:i:s') . " - $error_message" . PHP_EOL);
        echo $error_message . "<br>";
        continue; // Move to next credential
    }

    // Process each lead
    if (isset($data['data']) && is_array($data['data'])) {
        echo "Processing leads...<br>";
        foreach ($data['data'] as $lead) {
            $lead_id = $lead['id'];
            $created_time = $lead['created_time'];
            $full_name = '';
            $email = '';
            $phone = '';

            // Loop through field_data to get values
            if (isset($lead['field_data']) && is_array($lead['field_data'])) {
                foreach ($lead['field_data'] as $field) {
                    switch (strtolower($field['name'])) {
                        case 'full_name':
                        case 'name':
                            $full_name = $field['values'][0];
                            break;
                        case 'email':
                            $email = $field['values'][0];
                            break;
                        case 'phone_number':
                        case 'phone':
                            $phone = $field['values'][0];
                            break;
                    }
                }
            }

            // Process the lead if email is not empty
            if (!empty($email)) {
                echo "Processing lead: Name: $full_name, Email: $email, Phone: $phone<br>";
                try {
                    // Check if lead already exists
                    $query = "SELECT COUNT(*) FROM leads WHERE email = ?";
                    $stmt = $tenant_link->prepare($query);
                    if ($stmt === false) {
                        throw new Exception("Failed to prepare query: " . $tenant_link->error);
                    }
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $stmt->bind_result($count);
                    $stmt->fetch();
                    $stmt->close();

                    if ($count == 0) {
                        // Insert lead into database
                        echo "Inserting new lead into the database...<br>";
                        $insert_query = "INSERT INTO leads (name, email, phone, ad_name, created_at) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $tenant_link->prepare($insert_query);
                        if ($stmt === false) {
                            throw new Exception("Failed to prepare insert query: " . $tenant_link->error);
                        }
                        $stmt->bind_param("sssss", $full_name, $email, $phone, $ad_name, $created_time);
                        $stmt->execute();
                        $stmt->close();
                        $leads_inserted++;
                        echo "Lead inserted successfully.<br>";
                    } else {
                        $leads_skipped++;
                        echo "Lead already exists. Skipping...<br>";
                    }
                } catch (Exception $e) {
                    $error_message = "Error for email ($email): " . $e->getMessage();
                    fwrite($log_file, date('Y-m-d H:i:s') . " - " . $error_message . PHP_EOL);
                    echo $error_message . "<br>";
                    $errors[] = $error_message;
                }
            }
        }
    }
}

// Free the result set
$result->free();
echo "Result set freed.<br>";

// Close database connections
$tenant_link->close();
$master_link->close();
echo "Database connections closed.<br>";

// Write log and output summary
fwrite($log_file, date('Y-m-d H:i:s') . " - Inserted Leads: $leads_inserted, Skipped Leads: $leads_skipped" . PHP_EOL);
fclose($log_file);
echo "Log file closed.<br>";

// Output result summary
echo "Summary: Inserted Leads: $leads_inserted, Skipped Leads: $leads_skipped<br>";

// Display inserted and skipped leads in table format
if (!empty($leads_with_page_id)) {
    echo "<table>";
    echo "<thead><tr><th>Name</th><th>Email</th><th>Ad Name</th><th>Page ID</th></tr></thead>";
    echo "<tbody>";
    foreach ($leads_with_page_id as $lead) {
        echo "<tr><td>{$lead['name']}</td><td>{$lead['email']}</td><td>{$lead['ad_name']}</td><td>{$lead['page_id']}</td></tr>";
    }
    echo "</tbody></table>";
}

if (!empty($skipped_leads_with_page_id)) {
    echo "<table>";
    echo "<thead><tr><th>Name</th><th>Email</th><th>Ad Name</th><th>Page ID</th></tr></thead>";
    echo "<tbody>";
    foreach ($skipped_leads_with_page_id as $lead) {
        echo "<tr><td>{$lead['name']}</td><td>{$lead['email']}</td><td>{$lead['ad_name']}</td><td>{$lead['page_id']}</td></tr>";
    }
    echo "</tbody></table>";
}
?>
