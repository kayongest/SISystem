<?php
// test_submit.php
$testData = [
    'technician' => [
        'id' => 1,
        'full_name' => 'Kayonga Raul',
        'username' => 'kraul'
    ],
    'items' => [
        [
            'id' => 1,
            'name' => 'Test Laptop',
            'serial_number' => 'TEST-001',
            'category' => 'Electronics',
            'status' => 'available',
            'quantity' => 1
        ]
    ],
    'jobDetails' => [
        'stockLocation' => 'KCC',
        'eventName' => 'Test Event',
        'jobSheet' => 'JS-001',
        'projectManager' => 'Test Manager',
        'vehicleNumber' => 'TEST-001',
        'driverName' => 'Test Driver',
        'batchLocation' => 'Site A'
    ],
    'batchId' => 'TEST-' . time()
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1/ability_app_main/api/batch/submit.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response;
?>