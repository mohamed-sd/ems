<?php
/**
 * tests/period_guard_quad_test.php — رباعية فعلٍ قديم: حارس الفترة (M-39 · AC-E06-01)
 * تعميم «سماح · منع · تكرار · عكس» على الأفعال الحاكمة القديمة:
 * سماح: فترة مفتوحة تقبل · منع: مقفلة 423 باسمها · حالة planned ليست قفلًا
 * (اجتهاد مسجل) · عكس: إعادة الفتح الاستثنائية تفك الحجب · تكرار: الفحص
 * idempotent بلا أثر جانبي.
 * التشغيل: php tests/period_guard_quad_test.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../includes/period_guard.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$pass = 0; $fail = 0;
function ok($c, $l) { global $pass, $fail; $c ? $pass++ : $fail++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ ') . $l . "\n"); }

/* فترة اختبار معزولة 2027-12 */
$conn->query("DELETE FROM fin_financial_periods WHERE company_id = 4 AND period_type='month' AND start_date = '2027-12-01'");
$conn->query("INSERT INTO fin_financial_periods (company_id, period_type, start_date, end_date, state, posting_allowed)
              VALUES (4, 'month', '2027-12-01', '2027-12-31', 'open', 1)");
$pid = intval($conn->insert_id);

/* ① سماح: مفتوحة تقبل */
$v = ems_period_check($conn, 4, '2027-12-10');
ok($v['ok'] === true, 'سماح: الفترة المفتوحة تقبل القيد');

/* ② منع: الإقفال يحجب 423 باسم الحالة */
$conn->query("UPDATE fin_financial_periods SET state = 'closed', posting_allowed = 0 WHERE id = {$pid}");
$v = ems_period_check($conn, 4, '2027-12-10');
ok(!$v['ok'] && $v['code'] === 423 && mb_strpos($v['reason'], 'closed') !== false,
   'منع: المقفلة ترفض 423 وتسمي حالتها');

/* ③ تكرار: الفحص idempotent — نتيجتان متطابقتان بلا أثر */
$v2 = ems_period_check($conn, 4, '2027-12-10');
ok($v2['code'] === $v['code'] && $v2['period_id'] === $v['period_id'], 'تكرار: الفحص بلا أثرٍ جانبيٍّ ونتيجته ثابتة');

/* ④ عكس: إعادة الفتح الاستثنائية تفك الحجب */
$conn->query("UPDATE fin_financial_periods SET state = 'open', posting_allowed = 1 WHERE id = {$pid}");
$v = ems_period_check($conn, 4, '2027-12-10');
ok($v['ok'] === true, 'عكس: إعادة الفتح الموثقة تفك الحجب فورًا');

/* ⑤ planned ليست قفلًا (اجتهاد مسجل في الحارس) */
$conn->query("UPDATE fin_financial_periods SET state = 'planned', posting_allowed = 0 WHERE id = {$pid}");
$v = ems_period_check($conn, 4, '2027-12-10');
ok($v['ok'] === true, 'حد: planned ليست إقفالًا — وقائع التشغيل لا تتجمد');

$conn->query("DELETE FROM fin_financial_periods WHERE id = {$pid}");
fwrite(STDOUT, "\n══ النتيجة: {$pass} ناجحة · {$fail} فاشلة ══\n");
exit($fail ? 1 : 0);
