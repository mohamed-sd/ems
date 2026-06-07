<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
$iconsCss = function_exists('ems_url') ? ems_url('assets/css/all.min.css') : '../assets/css/all.min.css';

// Cache-busting version for unified stylesheets so CSS edits show immediately
// (the same constant is reused by insidebar.php's defensive CSS loader).
if (!defined('EMS_ASSET_VER')) {
    $__ems_main_css = __DIR__ . '/assets/css/ems.main.all.style.css';
    define('EMS_ASSET_VER', is_file($__ems_main_css) ? (string) filemtime($__ems_main_css) : '1');
}
if (!function_exists('ems_css_ver')) {
    function ems_css_ver($fileName)
    {
        $path = __DIR__ . '/assets/css/' . $fileName;
        return is_file($path) ? ('?v=' . filemtime($path)) : '';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ; ?></title>

    <!-- Font awsome icon link مكتبة الايقونات -->
    <link rel="stylesheet" href="<?php echo $iconsCss; ?>">

    <!-- Call bootstrap 5 -->
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
    <link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
    <!-- Unified Table Styles -->
    <link rel="stylesheet" href="/ems/assets/css/alltables.css<?php echo ems_css_ver('alltables.css'); ?>">
    <link rel="stylesheet" href="/ems/assets/css/local-fonts.css">
    <link rel="stylesheet" href="/ems/assets/css/design-tokens.css">
    <!-- Unified page styles: Dashboard + Chat -->
    <link rel="stylesheet" href="/ems/assets/css/ems.main.all.style.css<?php echo ems_css_ver('ems.main.all.style.css'); ?>">
    <script src="../assets/js/performance-boost.js" defer></script>
    <script src="/ems/assets/js/ui-unification.js" defer></script>
    <!-- Unified Details/View Modal System (نظام نافذة العرض الموحّد) -->
    <script src="/ems/assets/js/ems-details-modal.js" defer></script>
    <!-- Bootstrap Bundle JS (local, CSP-safe) -->
    <script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</head>
<body class="ems-site">
