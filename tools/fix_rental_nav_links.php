<?php
/**
 * tools/fix_rental_nav_links.php — تسوية روابط التأجير الثلاثة (سابقة ق-16)
 * ───────────────────────────────────────────────────────────────────────────
 * أُدخلت يدويًّا ببادئة ../ (فكسرت فحص الوجهة) وداخل مجموعات n9s الموثقة
 * (فكسرت عدّ ورقة NAV-09 للدور 12: 44≠41). التسوية بلا حذف: تطبيع المسار،
 * والنقل إلى مجموعات الدور غير الموثقة المناسبة دلاليًّا — فتبقى الشاشات
 * حية في قائمته وتصدق الورقة (الأحدث يغلب وتلحق الورقة لاحقًا — ق-16).
 * idempotent. التشغيل: php tools/fix_rental_nav_links.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* الوجهة ← مجموعة الدور 12 الملائمة (من الجرد الحي: g3 العمل اليومي فارغة · g10 السجلات) */
$plan = array(
    'Operations/fleet_calendar.php'    => 100, // g3 العمل اليومي
    'Operations/fleet_utilization.php' => 100,
    'Clients/rate_books.php'           => 280, // g10 السجلات والملفات
);

$r = mysqli_query($conn, "SELECT ni.id, ni.route, lg.group_code FROM nav_items ni
                           JOIN link_groups lg ON lg.id = ni.group_id
                          WHERE ni.route LIKE '../%'");
$rows = array();
while ($x = mysqli_fetch_assoc($r)) { $rows[] = $x; }
foreach ($rows as $x) {
    $clean = preg_replace('~^(\.\./)+~', '', $x['route']);
    $file = preg_replace('/[#?].*$/', '', $clean);
    if (!is_file(dirname(__DIR__) . '/' . $file)) {
        fwrite(STDOUT, "⚠ {$x['route']} — الملف غائب حتى بعد التطبيع، يُترك للفحص\n");
        continue;
    }
    $id = intval($x['id']);
    $set = "route = '" . $conn->real_escape_string($clean) . "'";
    if (isset($plan[$file]) && strpos($x['group_code'], 'n9s') === 0) {
        $set .= ", group_id = " . intval($plan[$file]);
    }
    mysqli_query($conn, "UPDATE nav_items SET {$set} WHERE id = {$id}");
    fwrite(STDOUT, "✔ #{$id}: {$x['route']} → {$clean}" . (isset($plan[$file]) ? ' (نُقل خارج n9s)' : '') . "\n");
}
fwrite(STDOUT, "اكتمل — أعد nav09_verify ثم dump-schema (بيانات nav تغيرت)\n");
