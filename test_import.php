<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'import_process.php';

$_SESSION['import_session'] = [
    'id' => 'test',
    'file' => 'uploads/imports/temp_1781268497_ability_app_main_Import_Template.xlsx',
    'total_rows' => 140,
    'processed' => 0,
    'success' => 0,
    'updated' => 0,
    'qr_generated' => 0,
    'errors' => 0,
    'skipped' => 0,
    'log' => [],
    'field_mapping' => [
        'item_name' => 'B',
        'serial_number' => 'C',
        'category' => 'D',
        'brand' => 'E',
        'model' => 'F',
        'department' => 'G',
        'description' => 'H',
        'specifications' => 'I',
        'brand_model' => 'J'
    ]
];

processImportBatch('test', 1);

print_r($_SESSION['import_session']);
