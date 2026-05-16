<?php
// debug_error.php - See what error the API is returning
$url = 'http://127.0.0.1/ability_app_main/api/scan/log.php';

$data = [
    'item_id' => 17,
    'scan_type' => 'check_in',
    'location' => 'Test Location',
    'notes' => 'Test note'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "<h1>API Debug</h1>";
echo "HTTP Status: $http_code<br>";
echo "<h2>Raw Response:</h2>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to find PHP error in response
if (strpos($response, '<b>') !== false) {
    echo "<h2 style='color:red'>PHP Error Detected!</h2>";
    
    // Extract error message
    preg_match('/<b>([^<]+)<\/b>/', $response, $matches);
    if ($matches) {
        echo "Error: " . htmlspecialchars($matches[1]) . "<br>";
    }
    
    // Try to find the file and line
    preg_match('/in <b>([^<]+)<\/b> on line <b>([^<]+)<\/b>/', $response, $matches2);
    if ($matches2) {
        echo "File: " . htmlspecialchars($matches2[1]) . "<br>";
        echo "Line: " . htmlspecialchars($matches2[2]) . "<br>";
    }
}

curl_close($ch);