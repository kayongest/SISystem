<?php
// api/get_qr_progress.php - Get QR generation progress

header('Content-Type: application/json');

$progressFile = dirname(__DIR__) . '/uploads/temp/qr_progress.json';

if (file_exists($progressFile)) {
    $progressData = json_decode(file_get_contents($progressFile), true);
    echo json_encode([
        'success' => true,
        'progress' => $progressData
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No progress data available'
    ]);
}
?>
