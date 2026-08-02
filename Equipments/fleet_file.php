<?php
// تقديم مرفقات كرت المعدة من التخزين المحمي storage/fleet (بعد التحقق من الجلسة وملكية الشركة).
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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
// (البوابة تحقن قيد الشركة تلقائيًّا؛ includeDeleted يطابق الأصل الذي لا يستثني المحذوف)
if (!$is_super) {
    $owned = false;
    $file_gate = ems_tenant_db();
    foreach (['fleet_equipment_compliance', 'fleet_equipment_protection'] as $tbl) {
        try {
            $hit = $file_gate->selectOne($tbl, array(
                'columns'        => array('id'),
                'whereRaw'       => 'attachment_path = ?',
                'params'         => array($rel),
                'includeDeleted' => true,
            ));
        } catch (\Throwable $t) {
            $hit = null;
        }
        if ($hit) { $owned = true; break; }
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
