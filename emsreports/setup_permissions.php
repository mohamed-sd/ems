<?php
/**
 * إعداد جدول صلاحيات التقارير — يُشغَّل مرة واحدة
 * يُنشئ الجدول ويُضيف صلاحيات لكل الأدوار
 */
session_start();
if (!isset($_SESSION['user']) || intval($_SESSION['user']['role']) !== -1) {
    die('<p style="font-family:Arial;color:red">هذا الملف للمديرين فقط (Super Admin). سجّل دخولك كمدير أولاً.</p>');
}
require_once '../config.php';

$reports = [
    'timesheet_summary','timesheet_detailed','timesheet_by_project',
    'timesheet_by_equipment','timesheet_by_driver',
    'project_summary','project_detailed',
    'contracts_summary','contracts_detailed',
    'supplier_contracts_summary','supplier_contracts_detailed','supplier_equipment_performance',
    'supplier_timesheet',
    'fleet_equipment_summary','fleet_equipment_detailed','fleet_operations','fleet_timesheet',
    'drivers_summary','drivers_detailed','drivers_timesheet','drivers_contracts',
    'operations_summary','operations_detailed',
];

$roles = [-1, 1, 2, 3, 4, 5, 6, 7, 8, 9];

$createSQL = "CREATE TABLE IF NOT EXISTS `report_role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `report_code` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_report` (`role_id`, `report_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$r = mysqli_query($conn, $createSQL);
$msgs = [];
$msgs[] = $r ? '✅ الجدول جاهز (أُنشئ أو كان موجوداً)' : '❌ خطأ في إنشاء الجدول: ' . mysqli_error($conn);

$inserted = 0;
foreach ($roles as $role) {
    foreach ($reports as $code) {
        $safe = mysqli_real_escape_string($conn, $code);
        $ir = @mysqli_query($conn, "INSERT IGNORE INTO report_role_permissions (role_id, report_code) VALUES ($role, '$safe')");
        if ($ir && mysqli_affected_rows($conn) > 0) $inserted++;
    }
}
$msgs[] = "✅ تم إدراج $inserted صلاحية جديدة لجميع الأدوار";

?><!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>إعداد صلاحيات التقارير</title>
<style>body{font-family:Cairo,Arial,sans-serif;background:#f0f2f8;display:flex;justify-content:center;padding:40px}
.card{background:#fff;border-radius:16px;padding:32px 28px;max-width:520px;width:100%;box-shadow:0 8px 28px rgba(12,28,62,.13)}
h2{color:#0c1c3e;margin-top:0}li{padding:6px 0;font-size:.95rem}
.back{display:inline-block;margin-top:20px;background:#2563eb;color:#fff;padding:10px 22px;border-radius:10px;text-decoration:none;font-weight:700}
</style></head><body>
<div class="card">
    <h2>⚙️ إعداد صلاحيات التقارير</h2>
    <ul>
    <?php foreach ($msgs as $m) echo "<li>$m</li>"; ?>
    </ul>
    <p style="color:#64748b;font-size:.85rem">تم منح جميع الأدوار وصولاً كاملاً لكل التقارير. يمكنك تعديل ذلك لاحقاً من صفحة إدارة الصلاحيات.</p>
    <a class="back" href="index.php">← العودة لقائمة التقارير</a>
</div>
</body></html>
