<?php
// test_paths.php
echo "Current directory: " . __DIR__ . "<br>";
echo "Script directory: " . dirname(__FILE__) . "<br>";

$paths = [
    '1. __DIR__ . \'/../../includes/db_connect.php\'' => __DIR__ . '/../../includes/db_connect.php',
    '2. __DIR__ . \'/../includes/db_connect.php\'' => __DIR__ . '/../includes/db_connect.php',
    '3. \'includes/db_connect.php\'' => 'includes/db_connect.php',
    '4. \'../includes/db_connect.php\'' => '../includes/db_connect.php',
    '5. \'../../includes/db_connect.php\'' => '../../includes/db_connect.php',
];

echo "<h3>Checking paths:</h3>";
foreach ($paths as $label => $path) {
    $exists = file_exists($path) ? '✅ EXISTS' : '❌ NOT FOUND';
    echo "$label: $path - $exists<br>";
}
