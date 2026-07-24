<?php
require 'vendor/autoload.php';
$s = \PhpOffice\PhpSpreadsheet\IOFactory::load('uploads/imports/temp_1781268497_ability_app_main_Import_Template.xlsx');
$worksheet = $s->getActiveSheet();

for ($col = 1; $col <= 10; $col++) {
    echo "Col $col: " . $worksheet->getCellByColumnAndRow($col, 1)->getValue() . " | Row 2: " . $worksheet->getCellByColumnAndRow($col, 2)->getValue() . "\n";
}
