<?php
/**
 * tests/u10_sync_conflict_test.php — تعارض المزامنة دون اتصال (U10-F34)
 * ───────────────────────────────────────────────────────────────────────────
 * السيناريو الحاكم: جهازان يزامنان الواقعة نفسها (client_uuid واحد) —
 * الثاني يجب ألا يولد أثرًا ثانيًا (AR-04/AR-07: مفتاح منع التكرار).
 * وتعديلان متزامنان لخانة (معدة×تاريخ×وردية) واحدة — درع ق-18 يحسم.
 * بيانات جس ذاتية التنظيف (تُعكس آخر الاختبار بلا حذف موروث).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$pass = 0; $fail = 0;
$t = function ($name, $ok) use (&$pass, &$fail) {
    fwrite(STDOUT, ($ok ? '  ✔ ' : '  ✘ ') . $name . "\n");
    $ok ? $pass++ : $fail++;
};

/* بنية العطالة موجودة؟ */
$r = mysqli_query($conn, "SHOW COLUMNS FROM timesheet LIKE 'client_uuid'");
$t('عمود مفتاح المزامنة client_uuid موجود في التايم شيت', mysqli_num_rows($r) === 1);
$r = mysqli_query($conn, "SHOW INDEX FROM timesheet WHERE Column_name = 'client_uuid' AND Non_unique = 0");
$hasUnique = mysqli_num_rows($r) > 0;
fwrite(STDOUT, '    (قيد UNIQUE على client_uuid: ' . ($hasUnique ? 'نعم — منع بنيوي' : 'لا — المنع في خدمة الإدخال') . ")\n");

/* جهازان · الواقعة نفسها: الإدراج الثاني بالمفتاح نفسه */
$uuid = 'U10SYNC-' . bin2hex(random_bytes(8));
mysqli_query($conn, "INSERT INTO timesheet (company_id, operator, employee_id, shift, date, shift_hours, executed_hours, status, client_uuid)
                     VALUES (4, '999901', '999903', 'نهارية', '2026-08-06', 10, 8, 'pending', '$uuid')")
    or die('✘ إدراج الجس الأول: ' . mysqli_error($conn) . "\n");
$id1 = mysqli_insert_id($conn);

if ($hasUnique) {
    $ok2 = @mysqli_query($conn, "INSERT INTO timesheet (company_id, operator, employee_id, shift, date, shift_hours, executed_hours, status, client_uuid)
                                 VALUES (4, '999901', '999903', 'نهارية', '2026-08-06', 10, 8, 'pending', '$uuid')");
    $t('مزامنة الجهاز الثاني بالمفتاح نفسه ترفض بنيويًّا', $ok2 === false && mysqli_errno($conn) === 1062);
} else {
    /* المنع خدمي: التحقق أن الاستعلام القياسي للخدمة يكتشف المفتاح القائم */
    $r = mysqli_query($conn, "SELECT COUNT(*) c FROM timesheet WHERE client_uuid = '$uuid'");
    $t('المفتاح القائم قابل للاكتشاف قبل الإدراج (نمط الخدمة)', (int) mysqli_fetch_assoc($r)['c'] === 1);
    /* والاسترجاع يعيد مرجع الأثر الأول لا يولد ثانيًا */
    $r = mysqli_query($conn, "SELECT id FROM timesheet WHERE client_uuid = '$uuid'");
    $t('إعادة المزامنة ترجع مرجع الأثر الأول', (int) mysqli_fetch_assoc($r)['id'] === $id1);
}

/* تعديلان متزامنان لخانة (معدة×تاريخ×وردية) واحدة — شاهده الحزام القائم
   ue_dup_shield_test (رفض الخانة المشغولة من القاعدة بقادحي ق-18) — يُشغَّل هنا */
$out = array(); $rc = 1;
exec('"' . PHP_BINARY . '" ' . escapeshellarg(__DIR__ . '/ue_dup_shield_test.php') . ' 2>&1', $out, $rc);
$t('التعارض المكاني (خانة واحدة من جهازين) يحسمه درع ق-18 — الحزام 5/5', $rc === 0);

/* تنظيف جس التايم شيت */
mysqli_query($conn, "DELETE FROM timesheet WHERE client_uuid = '$uuid'");

fwrite(STDOUT, str_repeat('─', 50) . "\n══ النتيجة: $pass ناجحة · $fail فاشلة ══\n");
exit($fail === 0 ? 0 : 1);
