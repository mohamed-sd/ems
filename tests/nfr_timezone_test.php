<?php
/**
 * update0004 · الموجة ⑲ — حزام N-25: توحيد التوقيت (NFR-01→04)
 * التشغيل: php tests/nfr_timezone_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
$conn = $GLOBALS['conn'];

fwrite(STDOUT, "\n══ update0004 موجة ⑲ — N-25 التوقيت ══\n");

// NFR-02: ساعة واحدة — php == db بفارق ≤ 2 ثانية
$r = $conn->query("SELECT NOW() n, UTC_TIMESTAMP() u")->fetch_assoc();
$diff = abs(strtotime($r['n']) - time());
check($diff <= 2, "ساعة التطبيق = ساعة القاعدة (فارق {$diff} ثانية)");
check(date_default_timezone_get() !== 'UTC' || $r['n'] === $r['u'],
    'لا ساعتين مختلطتين: PHP على منطقة القاعدة (' . date_default_timezone_get() . ')');

// علم الرجوع موجود وقابل القلب
$tz = function_exists('ems_env') ? ems_env('EMS_APP_TIMEZONE', '') : '';
check($tz !== '', "علم الرجوع EMS_APP_TIMEZONE مضبوط ({$tz})");

// NFR-04: التوثيق موجود بقياساته وخطة UTC الجاهزة بلا تشغيل
$rep = dirname(__DIR__) . '/docs/nfr/N-25_timezone_report_ar.md';
check(is_file($rep) && mb_strpos(file_get_contents($rep), 'إعدادٌ لا ترحيل') !== false,
    'تقرير NFR-04 موثَّق بنتيجته (§12.6-⑤)');
$plan = dirname(__DIR__) . '/docs/nfr/N-25_utc_migration_plan_ar.md';
check(is_file($plan) && mb_strpos(file_get_contents($plan), 'CONVERT_TZ') !== false,
    'خطة ترحيل UTC جاهزة ولا تُشغَّل (NFR-03 · §1.3)');

// حافة منتصف الليل: منتصف واحد عند الطرفين
$r = $conn->query("SELECT DATE(NOW()) d")->fetch_assoc();
check($r['d'] === date('Y-m-d'), 'يوم القاعدة = يوم التطبيق — منتصف ليل واحد (شرط UAT-11)');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
