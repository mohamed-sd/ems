<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * N-04 — اختبار قبول خطة الترحيل: علَمُ الرجوع مختبَرٌ لا موعود
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/rollback_flag_test.php
 *
 * ما يُثبته:
 *   ① دلالةُ «قائمة النطاق لا المفتاح الثنائي» (PLAN-01 §11-③): حذفُ وحدةٍ
 *      من قائمة علمٍ يُرجعها وحدَها للسلوك القديم — بلا نشر كودٍ وبلا مساسٍ
 *      ببقية الوحدات (النموذجُ الحي: EMS_NAV_UNIFIED_ROLES).
 *   ② أعلامُ الخطة كلُّها قائمةٌ فعلًا في .env وقارئُها موجود — لا علمَ
 *      موثَّقًا بلا مقبضٍ حي.
 *   ③ مساران مختبَران سلفًا يُستوثق خضرارُهما: قلبُ كاتب الدوام
 *      (writer_flip_test) وحارسُ الوثائق (document_guard_test) — الخطةُ
 *      تحيل إليهما لا تعيد بناءهما.
 *   ④ گوتشا البيئة محروسة: ems_env يقرأ ملفَّ .env لا متغيّرَ العملية —
 *      فطريقُ الرجوع الوحيدُ الصحيح تحريرُ الملف (يسري فورًا).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/unified_nav.php';

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

fwrite(STDOUT, "\n══ N-04 — علَمُ الرجوع مختبَرًا ══\n");

// ═══ ① قائمةُ النطاق لا المفتاح الثنائي ═══
head('① الرجوعُ بوحدةٍ واحدة: حذفُ رقمها من القائمة يُرجعها وحدَها');
check(unifiedNavEnabled(13, '1,2,13,19') === true,  'الدور 13 داخل القائمة: على المصدر الموحّد');
check(unifiedNavEnabled(13, '1,2,19') === false,    'حُذف 13 من القائمة: عاد للمصادر القديمة — بلا نشر كود');
check(unifiedNavEnabled(19, '1,2,19') === true,     'بقيةُ الوحدات (19) لم تتأثر برجوع 13');
check(unifiedNavEnabled(13, '') === false,          'قائمةٌ فارغة = رجوعٌ شامل fail-safe');
check(unifiedNavEnabled(13, ' 1 , 13 , 2 ') === true, 'القائمةُ متسامحةٌ مع الفراغات (خطأُ تحريرٍ لا يقتل الرجوع)');

// ═══ ② أعلامُ الخطة قائمةٌ بمقابضها ═══
head('② كلُّ علمٍ موثَّقٍ في الخطة له سطرٌ في .env وقارئٌ حي');
$envFile = file_get_contents(dirname(__DIR__) . '/.env');
$flags = array(
    'EMS_ACTION_GUARD', 'EMS_DDL_FREEZE', 'EMS_EVENT_ROOT', 'EMS_TENANT_GATE',
    'EMS_UNIT_CONVERT_GATE', 'EMS_UNIT_DUAL_WRITE', 'EMS_TS_TIME_LINES', 'EMS_TS_WRITER',
    'EMS_UNIT_FANOUT_SOURCE', 'EMS_APPROVAL_BOX', 'EMS_NAV_UNIFIED_ROLES', 'EMS_ROLE_BOARD_ROLES',
    'EMS_ATTRIBUTION_MATRIX', 'EMS_RESP_PARTY_STRICT', 'EMS_DOC_EXPIRY_GUARD', 'EMS_CONTAINER_GATE',
    'EMS_FAILCLOSED_ENFORCE_PREFIXES', 'EMS_FAILCLOSED_MONITOR_PREFIXES',
);
$missing = array();
foreach ($flags as $f) {
    if (!preg_match('/^' . preg_quote($f, '/') . '=/m', $envFile)) { $missing[] = $f; }
}
check(empty($missing), 'الأعلامُ الـ' . count($flags) . ' كلُّها في .env' . ($missing ? ' — الغائب: ' . implode(',', $missing) : ''));
check(function_exists('ems_env'), 'القارئُ المركزي ems_env قائم');
$grep = 0;
foreach ($flags as $f) {
    // كلُّ علمٍ يُقرأ في الشجرة (بحثٌ خام في includes/ وapp/ والجذر)
    $found = false;
    foreach (array('includes', 'app', 'Finance', 'Timesheet', '.') as $dir) {
        $cmd = 'findstr /s /m /c:"' . $f . '" "' . dirname(__DIR__) . '\\' . $dir . '\\*.php" 2>nul';
        $out = shell_exec($cmd);
        if ($out !== null && trim((string) $out) !== '') { $found = true; break; }
    }
    if ($found) { $grep++; }
}
check($grep === count($flags), "لكل علمٍ قارئٌ في الكود ({$grep}/" . count($flags) . ')');

// ═══ ③ المساران المختبَران سلفًا ═══
head('③ مسارا رجوعٍ مختبَران سلفًا — تُستوثق خضرتُهما الآن');
$php = escapeshellarg(PHP_BINARY);   // نفسُ مفسِّر السطر الحالي — لا مسارَ نسخةٍ مثبَّتًا
$out = shell_exec($php . ' ' . escapeshellarg(dirname(__DIR__) . '/tests/writer_flip_test.php') . ' 2>&1');
check(strpos((string) $out, '0 فاشل') !== false, 'قلبُ كاتب الدوام (TS_WRITER legacy↔service) أخضر — رجوعٌ بلا فقد');
// حارسُ الوثائق مرَّ بدورة monitor→enforce→monitor فعليةً في TASK-0 وتغطيتُه قائمة
$out = shell_exec($php . ' ' . escapeshellarg(dirname(__DIR__) . '/tests/document_guard_test.php') . ' 2>&1');
check(strpos((string) $out, '0 فاشل') !== false, 'حارسُ الوثائق (DOC_EXPIRY_GUARD) أخضر بعد رجوعه الفعلي إلى monitor');

// ═══ ④ گوتشا البيئة ═══
head('④ ems_env يقرأ الملفَّ لا متغيّرَ العملية — طريقُ الرجوع تحريرُ .env');
putenv('EMS_TS_WRITER=hijacked_by_process_env');
$v = ems_env('EMS_TS_WRITER', '');
check($v !== 'hijacked_by_process_env', 'متغيّرُ العملية لا يجاوز الملف (المقروء: ' . $v . ')');
putenv('EMS_TS_WRITER');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
