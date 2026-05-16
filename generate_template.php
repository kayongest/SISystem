<?php
// generate_csv_template.php - Generate CSV Template
session_start();

// Define root directory
$rootDir = __DIR__; // Current directory (ability_app_main)

// Include required files with absolute paths
require_once $rootDir . '/includes/db_connect.php';
require_once $rootDir . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get categories, conditions, statuses from functions
$categories = getCategories();
$conditions = getConditions();
$statuses = getStatuses();

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="item_import_template_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Headers
$headers = [
    'item_name',
    'serial_number',
    'category',
    'brand',
    'model',
    'department',
    'description',
    'specifications',
    'condition',
    'stock_location',
    'storage_location',
    'notes',
    'quantity',
    'status',
    'tags'
];

fputcsv($output, $headers);

// Example data
$examples = [
    [
        'Laptop Dell XPS',
        'LT-DLX-001',
        'IT',
        'Dell',
        'XPS 13',
        'IT',
        'Developer laptop for programming team',
        'i7-1165G7, 16GB RAM, 512GB SSD',
        'excellent',
        'Warehouse A',
        'Shelf B3',
        'For development team',
        1,
        'available',
        'laptop,dell,developer'
    ],
    [
        'Projector Sony',
        'PROJ-SON-101',
        'Video',
        'Sony',
        'VPL-HW45ES',
        'VID',
        '4K Home Theater Projector for events',
        '4K HDR, 1800 lumens, 3LCD',
        'good',
        'BK Arena',
        'Storage Room 2',
        'Needs bulb replacement soon',
        2,
        'available',
        'projector,4k,sony'
    ],
    [
        'Audio Mixer',
        'MIX-YAM-202',
        'Audio',
        'Yamaha',
        'MG10XU',
        'AUD',
        '10-channel mixer for small events',
        '10 inputs, 4 buses, effects processor',
        'fair',
        'Masoro',
        'Sound Room',
        'Channel 3 has crackling sound',
        1,
        'maintenance',
        'mixer,audio,yamaha'
    ]
];

// Add example rows
foreach ($examples as $example) {
    fputcsv($output, $example);
}

// Add blank row
fputcsv($output, array_fill(0, count($headers), ''));

// Add instructions
$instructions = [
    '=== INSTRUCTIONS ===',
    '1. Required fields: item_name, serial_number, category',
    '2. Serial Number must be unique for each item',
    '3. Category options: ' . implode(', ', array_keys($categories)),
    '4. Condition options: ' . implode(', ', array_keys($conditions)),
    '5. Status options: ' . implode(', ', array_keys($statuses)),
    '6. Quantity must be a number (default: 1)',
    '7. Tags: separate multiple tags with commas',
    '8. Delete example rows before adding your data',
    '9. Save as CSV (Comma Delimited) format',
    '10. Maximum 1000 rows per import'
];

foreach ($instructions as $instruction) {
    fputcsv($output, [$instruction]);
}

fclose($output);
exit;
?>