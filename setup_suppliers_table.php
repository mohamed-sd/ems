<?php
// تنفيذ تحديث جدول الموردين
// تاريخ: 2026-02-05

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != "-1") {
    die("غير مصرح لك بالدخول");
}

include 'config.php';

$sql_file = file_get_contents('database/update_suppliers_table.sql');
$queries = explode(';', $sql_file);

$success = 0;
$warnings = [];
$errors = [];

echo '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث جدول الموردين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap");
        body { font-family: "Cairo", sans-serif; background: #f5f7fa; padding: 40px; }
        .container { max-width: 800px; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        h2 { color: #01072a; margin-bottom: 30px; }
        .result { padding: 15px; border-radius: 10px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; border-right: 4px solid #28a745; }
        .warning { background: #fff3cd; color: #856404; border-right: 4px solid #ffc107; }
        .error { background: #f8d7da; color: #721c24; border-right: 4px solid #dc3545; }
        .icon { margin-left: 10px; font-size: 1.2rem; }
        .btn-link { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-database icon"></i> تحديث جدول الموردين</h2>';

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query) || strpos($query, '--') === 0) continue;
    
    if (mysqli_query($conn, $query)) {
        $success++;
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate column') !== false) {
            $warnings[] = "العمود موجود مسبقاً: " . $error;
        } else {
            $errors[] = $error;
        }
    }
}

if ($success > 0) {
    echo '<div class="result success">
            <i class="fas fa-check-circle icon"></i>
            <strong>نجح:</strong> تم تنفيذ ' . $success . ' عملية بنجاح
          </div>';
}

if (!empty($warnings)) {
    foreach ($warnings as $warning) {
        echo '<div class="result warning">
                <i class="fas fa-exclamation-triangle icon"></i>
                <strong>تحذير:</strong> ' . htmlspecialchars($warning) . '
              </div>';
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        echo '<div class="result error">
                <i class="fas fa-times-circle icon"></i>
                <strong>خطأ:</strong> ' . htmlspecialchars($error) . '
              </div>';
    }
}

echo '<div class="btn-link">
        <a href="Suppliers/suppliers.php" class="btn btn-primary btn-lg">
            <i class="fas fa-arrow-left"></i> الانتقال إلى صفحة الموردين
        </a>
      </div>
    </div>
</body>
</html>';

mysqli_close($conn);
?>
