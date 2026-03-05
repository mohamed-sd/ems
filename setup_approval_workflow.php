<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') != '-1') {
    die('غير مصرح لك بالدخول - يتطلب صلاحيات الإدارة العليا');
}

include 'config.php';

$sql_file = __DIR__ . '/database/approval_workflow.sql';
if (!file_exists($sql_file)) {
    die('ملف SQL غير موجود: ' . $sql_file);
}

$sql = file_get_contents($sql_file);
$queries = explode(';', $sql);

$ok = 0;
$warnings = [];
$errors = [];

foreach ($queries as $query) {
    $query = trim($query);
    if ($query === '' || strpos($query, '--') === 0) {
        continue;
    }

    if (mysqli_query($conn, $query)) {
        $ok++;
        continue;
    }

    $error = mysqli_error($conn);
    if (stripos($error, 'Duplicate') !== false || stripos($error, 'already exists') !== false) {
        $warnings[] = $error;
    } else {
        $errors[] = $error;
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تثبيت نظام الموافقات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container bg-white p-4 rounded shadow-sm">
    <h3 class="mb-3">تثبيت نظام الموافقات متعدد المراحل</h3>
    <div class="alert alert-success">تم تنفيذ <strong><?php echo intval($ok); ?></strong> استعلام بنجاح.</div>

    <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning">
            <strong>تحذيرات:</strong>
            <ul class="mb-0">
                <?php foreach ($warnings as $w): ?>
                    <li><?php echo htmlspecialchars($w); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>أخطاء:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-info">تم تجهيز جداول الموافقات ويمكنك الآن استخدام النظام.</div>
    <?php endif; ?>

    <a href="main/dashboard.php" class="btn btn-primary">العودة للوحة التحكم</a>
</div>
</body>
</html>
