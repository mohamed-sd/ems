<?php
session_start();
session_unset();  // حذف جميع متغيرات الـ session
session_destroy(); // تدمير الجلسة بالكامل

// إعادة التوجيه لصفحة تسجيل الدخول
header("Location: login.php");
exit();
?>
