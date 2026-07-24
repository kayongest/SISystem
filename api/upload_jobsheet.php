<?php
// api/upload_jobsheet.php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_clean();
header('Content-Type: application/json');

function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit();
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Please log in to upload files');
}

if (!isset($_FILES['jobsheet_file'])) {
    jsonResponse(false, 'No file uploaded');
}

$file = $_FILES['jobsheet_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Upload error code: ' . $file['error']);
}

// Validate file type
$allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    jsonResponse(false, 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, JPEG, PNG');
}

// Create uploads directory
$uploadDir = '../uploads/jobsheets/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$newFilename = 'jobsheet_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
$targetPath = $uploadDir . $newFilename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Return relative path from root
    $relativePath = 'uploads/jobsheets/' . $newFilename;
    jsonResponse(true, 'File uploaded successfully', ['file_path' => $relativePath]);
} else {
    jsonResponse(false, 'Failed to move uploaded file');
}
?>
