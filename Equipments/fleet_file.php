<?php
// تقديم مرفقات كرت المعدة من التخزين المحمي storage/fleet (بعد التحقق من الجلسة وملكية الشركة).
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('forbidden');
}
include '../config.php';

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super   = (isset($_SESSION['user']['role']) && strval($_SESSION['user']['role']) === '-1');

$f = isset($_GET['f']) ? basename($_GET['f']) : '';
if ($f === '' || strpos($f, '..') !== false) {
    http_response_code(400);
    exit('bad request');
}

$dir  = realpath(__DIR__ . '/../storage/fleet');
$path = $dir ? realpath($dir . DIRECTORY_SEPARATOR . $f) : false;
if (!$path || strpos($path, $dir) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('not found');
}

$rel = 'storage/fleet/' . $f;

// عزل الشركة: يجب أن يكون الملف مرجوعاً من سجلّ يخصّ شركة المستخدم
if (!$is_super) {
    $owned = false;
    foreach (['fleet_equipment_compliance', 'fleet_equipment_protection'] as $tbl) {
        $stmt = $conn->prepare("SELECT 1 FROM `$tbl` WHERE company_id = ? AND attachment_path = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("is", $company_id, $rel);
            $stmt->execute();
            if ($stmt->get_result()->fetch_row()) { $owned = true; break; }
        }
    }
    if (!$owned) {
        http_response_code(403);
        exit('forbidden');
    }
}

while (ob_get_level()) {
    ob_end_clean();
}
$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $f . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
