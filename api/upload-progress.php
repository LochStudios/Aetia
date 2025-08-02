<?php
// api/upload-progress.php - Get upload progress using uploadprogress extension

header('Content-Type: application/json');

if (!extension_loaded('uploadprogress')) {
    echo json_encode(['error' => 'Upload progress extension not available']);
    exit;
}

$upload_id = $_GET['upload_id'] ?? '';

if (empty($upload_id)) {
    echo json_encode(['error' => 'No upload ID provided']);
    exit;
}

$progress = uploadprogress_get_info($upload_id);

if (!$progress) {
    echo json_encode([
        'upload_id' => $upload_id,
        'bytes_uploaded' => 0,
        'bytes_total' => 0,
        'speed_average' => 0,
        'speed_last' => 0,
        'files_uploaded' => 0,
        'est_sec' => 0,
        'percentage' => 0,
        'done' => false
    ]);
} else {
    $percentage = $progress['bytes_total'] > 0 ? 
        round(($progress['bytes_uploaded'] / $progress['bytes_total']) * 100, 2) : 0;
    
    echo json_encode([
        'upload_id' => $upload_id,
        'bytes_uploaded' => $progress['bytes_uploaded'],
        'bytes_total' => $progress['bytes_total'],
        'speed_average' => $progress['speed_average'],
        'speed_last' => $progress['speed_last'],
        'files_uploaded' => $progress['files_uploaded'],
        'est_sec' => $progress['est_sec'],
        'percentage' => $percentage,
        'done' => $progress['done'] ?? false
    ]);
}
?>
