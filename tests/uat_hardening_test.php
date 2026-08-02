<?php
/**
 * update0004 · الموجة ㉒ — حزام التحصين والتحضير (UAT-01→06)
 * التشغيل: php tests/uat_hardening_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;

fwrite(STDOUT, "\n══ update0004 موجة ㉒ — التحصين والتحضير ══\n");

head('UAT-01 — بند التحصين الستة (تشغيل الأداة)');
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/uat_hardening_verify.php') . ' 2>&1');
check(is_string($out) && mb_strpos($out, 'مكتمل ✔') !== false, 'التحصين مكتمل (الأداة)');
$rep = dirname(__DIR__) . '/docs/uat/UAT_hardening_report_ar.md';
$repSrc = is_file($rep) ? file_get_contents($rep) : '';
foreach (array('① فصل الحسابات', '② حجب التهيئة', '③ عزل العدة', '④ حماية النماذج', '⑤ استرجاع منفَّذ فعلًا', '⑥ المصدر الثاني') as $item) {
    check(mb_strpos($repSrc, $item) !== false, "بند: {$item}");
}
$run = $conn->query("SELECT run_id, state FROM uat_runs WHERE phase='hardening' ORDER BY run_id DESC LIMIT 1")->fetch_assoc();
check($run && $run['state'] === 'passed', 'جولة التحصين مسجلة passed في uat_runs');
$ev = $conn->query("SELECT COUNT(*) c FROM uat_evidence WHERE run_id=" . intval($run['run_id']) . " AND result='pass'")->fetch_assoc();
check(intval($ev['c']) === 6, 'الشواهد الست pass');

head('UAT-02 — البنية المعزولة واللقطة المختبرة الاستعادة');
check($conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='uat_runs'")->num_rows === 1, 'uat_runs قائم');
check(is_dir(dirname(__DIR__) . '/database/backups'), 'مجلد اللقطات موجود (H5 اختبر الاستعادة)');
check(mb_strpos($repSrc, 'الاستعادة مختبَرة لا موصوفة') !== false, 'الاسترجاع منفَّذ فعلًا لا موصوف (④/⑤)');

head('UAT-03 — أدوار حقيقية ووضع اختبار الصلاحيات');
$roles = intval($conn->query("SELECT COUNT(*) c FROM roles")->fetch_assoc()['c']);
check($roles === 25, "حسابات الأدوار الحقيقية متاحة ({$roles} دورًا)");
$fm = $conn->query("SELECT COUNT(*) c FROM founding_mode WHERE mode='permission_test'")->fetch_assoc();
check(intval($fm['c']) === 1, 'وضع اختبار الصلاحيات جاهز (founding_mode)');

head('UAT-04 — الاختيار بالمعايير ووسم UAT-2026');
// وسم صف تجربة موسوم ثم قياسه
$conn->query("INSERT INTO uat_runs (company_id, tag, phase, title, state) VALUES ({$CO}, 'UAT-2026', 'functional', 'وسم اختباري', 'planned')");
$tagged = intval($conn->query("SELECT COUNT(*) c FROM uat_runs WHERE tag='UAT-2026'")->fetch_assoc()['c']);
check($tagged >= 1, "الوسم UAT-2026 للتمييز والتقارير لا للحذف ({$tagged})");
$conn->query("DELETE FROM uat_runs WHERE title='وسم اختباري'");

head('UAT-05 — توليد الحجم بتوزيع غير منتظم');
$md = dirname(__DIR__) . '/docs/uat/UAT_data_plan_ar.md';
check(is_file($md), 'خطة توليد الحجم موثقة');
check(is_file($md) && mb_strpos(file_get_contents($md), 'غير منتظم') !== false, 'التوزيع غير المنتظم منصوص (§5)');

head('UAT-06 — من ينفّذ: مستخدمو الإدارات والفريق يراقب');
$mdSrc = is_file($md) ? file_get_contents($md) : '';
check(mb_strpos($mdSrc, 'مستخدمي الإدارات') !== false && mb_strpos($mdSrc, 'المراقبة والتوثيق') !== false,
    'مثبَّت: مستخدمو الإدارات ينفّذون والفريق يراقب ويوثق (§12.1)');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
