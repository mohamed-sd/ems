<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['project_id']) || empty($_GET['project_id'])) {
    die(json_encode(['success' => false, 'message' => 'معرف المشروع مطلوب']));
}

$project_id = intval($_GET['project_id']);

// جلب بيانات المشروع من جدول company_project
$query = "SELECT project_code, category, sub_sector, state, region, nearest_market, latitude, longitude 
          FROM company_project 
          WHERE id = $project_id";

$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']));
}

if (mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'المشروع غير موجود']);
}

mysqli_close($conn);
exit;
?>
