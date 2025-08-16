<?php // fetch_facebook_leads.php

// Enable error reporting for debugging
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Database connection
include 'methods/database.php'; // Ensure this file sets up $link correctly

// Check if $link is a valid MySQLi instance
// if (!$link instanceof mysqli) {
//     die("Database connection failed.");
// }

// Facebook Access Token and Page IDs
// $access_token = 'EAAHdItIVSVEBOZBMRH1KS3nDHfkZCJUj3OcJwDewxN6vNcHltZBoK4TW4T5XRAZCHF9JZATt3peWMKTsZCUd8QVziKJ8wZAweG4ZBuwortT0WbU9Im8tyvujvaucY4QlKtHFwzDaCz4wmSkMlFwFrs5wz9VZCq0SJLWcOSvpx6dc0HlQodf1sBKH1qwZDZD'; // Add your access token here
// $page_ids = ['7708883552481436', '1464978541005865']; // Array of Page IDs



// Define the time frame
// Calculate the Unix timestamps
// $now = new DateTime();
// $sinceDate = $now->modify('-2 days'); // 2 days prior
// $sinceTimestamp = $sinceDate->getTimestamp(); // Convert to Unix timestamp

// $untilTimestamp = $now->getTimestamp(); // Today's date and time in Unix timestamp

// // Define the time frame in Unix timestamps
// $since = $sinceTimestamp;
// $until = $untilTimestamp;

// // Open log file for writing
// $log_file = fopen("fetch_facebook_leads.log", "a");

// // Initialize counters and error array
// $leads_inserted = 0;
// $leads_skipped = 0;
// $errors = [];

// // Loop through each page ID

//     // Facebook Graph API URL with filtering parameter for Unix timestamps
   
// // Initialize cURL session
// foreach ($page_ids as $page_id) {
//     // Facebook Graph API URL with time frame
//   $graph_url = "https://graph.facebook.com/v20.0/{$page_id}/leads?access_token={$access_token}&filtering=[{%22field%22:%22time_created%22,%22operator%22:%22GREATER_THAN%22,%22value%22:{$since}}]&since={$since}&until={$until}";


//     // Initialize cURL session
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $graph_url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//     // Execute cURL request and get response
//     $response = curl_exec($ch);

//     // Check if there was a cURL error
//     if (curl_errno($ch)) {
//         $curl_error_message = "cURL error for page ID $page_id: " . curl_error($ch);
//         echo "<p>$curl_error_message</p>";
//         fwrite($log_file, date('Y-m-d H:i:s') . " - $curl_error_message" . PHP_EOL);
//         curl_close($ch);
//         continue; // Move to next page ID
//     }

//     curl_close($ch);

//     // Decode JSON response
//     $data = json_decode($response, true);

//     // Check for errors in API response
//     if (isset($data['error'])) {
//         $error_message = "Error fetching data from Facebook for page ID $page_id: " . $data['error']['message'];
//         fwrite($log_file, date('Y-m-d H:i:s') . " - " . $error_message . PHP_EOL);
//         continue; // Move to next page ID
//     }

//     // Process each lead
//     if (isset($data['data']) && is_array($data['data'])) {
//         foreach ($data['data'] as $lead) {
//             // Extract lead details
//             $lead_id = $lead['id'];
//             $created_time = $lead['created_time'];
//             $full_name = '';
//             $email = '';
//             $phone = '';

//             // Loop through field_data to get values
//             if (isset($lead['field_data']) && is_array($lead['field_data'])) {
//                 foreach ($lead['field_data'] as $field) {
//                     switch ($field['name']) {
//                         case 'full_name':
//                             $full_name = $field['values'][0];
//                             break;
//                         case 'email':
//                             $email = $field['values'][0];
//                             break;
//                         case 'phone_number':
//                             $phone = $field['values'][0];
//                             break;
//                     }
//                 }
//             }

//             // Set ad_name to '3d'
//             $ad_name = '3d';

//             // Insert data into the database if not already present
//             if (!empty($email)) {
//                 try {
//                     // Check if lead already exists
//                     $query = "SELECT COUNT(*) FROM leads WHERE email = ?";
//                     $stmt = $link->prepare($query);
//                     if ($stmt === false) {
//                         throw new Exception("Failed to prepare query: " . $link->error);
//                     }
//                     $stmt->bind_param("s", $email);
//                     $stmt->execute();
//                     $stmt->bind_result($count);
//                     $stmt->fetch();
//                     $stmt->close();

//                     if ($count == 0) {
//                         // Insert lead into database
//                         $insert_query = "INSERT INTO leads (name, email, phone, ad_name, created_at) VALUES (?, ?, ?, ?, ?)";
//                         $stmt = $link->prepare($insert_query);
//                         if ($stmt === false) {
//                             throw new Exception("Failed to prepare insert query: " . $link->error);
//                         }
//                         $stmt->bind_param("sssss", $full_name, $email, $phone, $ad_name, $created_time);
//                         $stmt->execute();
//                         $stmt->close();
//                         $leads_inserted++;
//                         echo "<p>Lead inserted: $full_name ($email)</p>";
//                     } else {
//                         $leads_skipped++;
//                         echo "<p>Lead skipped (already exists): $full_name ($email)</p>";

//                     }
//                 } catch (Exception $e) {
//                     $error_message = "Error for email ($email): " . $e->getMessage();
//                     fwrite($log_file, date('Y-m-d H:i:s') . " - " . $error_message . PHP_EOL);
//                     $errors[] = $error_message;
//                 }
//             }
//         }
//     } else {

//         echo "<p>No data found for page ID: $page_id</p>";
//     }
// }

// // Log the results
// $log_message = date('Y-m-d H:i:s') . " - Leads inserted: $leads_inserted, Leads skipped: $leads_skipped" . PHP_EOL;
// fwrite($log_file, $log_message);

// fclose($log_file);

// // Print summary
// echo "<h2>Summary</h2>";
// echo "<p>Leads inserted: $leads_inserted</p>";
// echo "<p>Leads skipped: $leads_skipped</p>";

// if (!empty($errors)) {
//     echo "<h2>Errors</h2>";
//     foreach ($errors as $error) {
//         echo "<p>$error</p>";
//     }
// }
// ?>
