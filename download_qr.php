<?php
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die('No file specified.');
}

$file = $_GET['file'];

// Security check: prevent directory traversal and external URLs
if (strpos($file, '..') !== false || strpos($file, 'http') === 0 || strpos($file, '/') === 0) {
    die('Invalid file path.');
}

$filepath = __DIR__ . '/' . $file;

if (!file_exists($filepath) || !is_file($filepath)) {
    die('File not found.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Set headers to force download
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="QR_Code_' . basename($filepath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Output file
readfile($filepath);
exit;
