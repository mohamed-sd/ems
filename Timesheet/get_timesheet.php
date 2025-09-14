<?php
include '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode([]);
    exit;
}
$id = intval($_GET['id']);
$q = mysqli_query($conn, "SELECT * FROM timesheet WHERE id = $id LIMIT 1");
$row = mysqli_fetch_assoc($q);
if (!$row) {
    echo json_encode([]);
    exit;
}

// لإظهار فرق العداد بشكل ودود نعيّن counter_diff_display
$row['counter_diff_display'] = '';
if (!empty($row['counter_diff'])) {
    $diff = intval($row['counter_diff']);
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    $row['counter_diff_display'] = $hours . " ساعة " . $minutes . " دقيقة " . $seconds . " ثانية";
}

echo json_encode($row);

?>