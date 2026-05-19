<?php
session_start();

// Record logout before destroying session.
if (isset($_SESSION['user'])) {
    require_once __DIR__ . '/config.php';
    if (class_exists('App\Services\ActivityLogService')) {
        \App\Services\ActivityLogService::logLogout();
    }
}

session_unset();  // حذف جميع متغيرات الـ session
session_destroy(); // تدمير الجلسة بالكامل

// إعادة التوجيه لصفحة تسجيل الدخول
header("Location: login.php");
exit();
?>
