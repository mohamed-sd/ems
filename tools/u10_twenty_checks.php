<?php
/**
 * tools/u10_twenty_checks.php — بوابة الفحوص الآلية العشرين (U10-G40 · الفصل 15)
 * ───────────────────────────────────────────────────────────────────────────
 * «تشغيل الفحوص الآلية العشرين في خط التسليم» — عشرون فحصًا مسمًّى بشاهده،
 * كلٌّ يمر أو يفشل صريحًا، والبوابة تفشل بأي واحد. تصلح خطاف ما قبل الالتزام.
 * php tools/u10_twenty_checks.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$PHPB = PHP_BINARY;
$pass = 0; $fail = 0; $i = 0;
$say = function ($name, $ok, $note = '') use (&$pass, &$fail, &$i) {
    $i++;
    fwrite(STDOUT, sprintf("  %s %02d· %s%s\n", $ok ? '✔' : '✘', $i, $name, $note !== '' ? " — $note" : ''));
    $ok ? $pass++ : $fail++;
};
$runTool = function ($rel) use ($ROOT, $PHPB) {
    $out = array(); $rc = 1;
    exec('"' . $PHPB . '" ' . escapeshellarg($ROOT . '/' . $rel) . ' 2>&1', $out, $rc);
    return $rc === 0;
};
$q1 = function ($sql) use ($conn) { $r = mysqli_query($conn, $sql); $x = mysqli_fetch_row($r); return $x ? $x[0] : null; };

fwrite(STDOUT, "════ بوابة الفحوص العشرين (U10) ════\n");

/* ① نظام الشاشات */
$say('المولّد يطابق وثيقة NAV-09 حرفًا (موضع/وجهة/صلاحية/مقيد)', $runTool('tools/nav09_verify.php'));
$say('الأعمدة الحاكمة بنطاق معلن 194+9', $runTool('tools/cmp03_gov_check.php'));
$say('محرك الشاشات البنيوي (السؤال · الطقم · CM-00 · المقام المعلن)', $runTool('tools/e03_checks.php'));
$say('معيار السايدبار السباعي', $runTool('tools/nav_seven_guard.php'));

/* ② الأفعال */
$say('صفر رمز بمعنيين (البوابة الساكنة)', (int) $q1("SELECT COUNT(*) - COUNT(DISTINCT canonical_code) FROM nav09_action_map") === 0);
$say('صفر رمز معلق الربط (البوابة الحية — فحص ⑤)', (int) $q1("SELECT COUNT(*) FROM nav09_action_map WHERE state = 'pending'") === 0);
$say('تصنيف الكتابة مكتمل للرموز كلها (ورقة 21)', (int) $q1("SELECT COUNT(*) FROM nav09_action_map WHERE write_class IS NULL") === 0);
$say('أحزمة الأفعال والوقائع (act_checks)', $runTool('tools/act_checks.php'));
$say('أفعال الورقة 09 لخطواتها (act09_links_check)', $runTool('tools/act09_links_check.php'));

/* ③ الهوية والعزل والبيانات */
$say('الهوية والكيانات وعزل DEC-D مصنفًا (e05)', $runTool('tools/e05_checks.php'));
$say('وحدات الصلاحيات 203/203', (int) $q1("SELECT COUNT(*) FROM nav09_file_map fm WHERE fm.real_path IS NOT NULL AND fm.state <> 'soon' AND NOT EXISTS (SELECT 1 FROM modules m WHERE m.code = fm.real_path)") === 0);
$say('صلاحيات الحوكمة (sec_perm_checks)', $runTool('tools/sec_perm_checks.php'));
$say('درع التكرار ق-18 حي', $runTool('tests/ue_dup_shield_test.php'));
$say('طوبولوجيا القاعدتين CR-04 (المصدر الواحد والموروثة قراءة)', $runTool('tests/cr04_topology_test.php'));
$say('تعارض المزامنة محسوم بنيويًّا', $runTool('tests/u10_sync_conflict_test.php'));

/* ④ الدورات والمحركات */
$say('دورة الوحدة والتايم شيت (e02)', $runTool('tools/e02_checks.php'));
$say('محرك العمل الشخصي (wfm)', $runTool('tools/wfm_checks.php'));
$say('نواة التأجير (rental_core)', $runTool('tools/rental_core_checks.php'));
$say('العقد الخماسي للأحداث بنيويًّا (e01_contract_sweep)', $runTool('tools/e01_contract_sweep.php'));

/* ⑤ الطابور */
$say('صفر رسالة ميتة في الناقل', (int) $q1("SELECT COUNT(*) FROM ems_event_dead_letter") === 0);

/* ⑥ حزاما update0011: إدارة المخاطر (G3) والقبول البصري (G5) */
$say('إدارة المخاطر M-16 كاملة (m16_checks — بوابة G3)', $runTool('tools/m16_checks.php'));
$say('القبول البصري UXR الـ16 (uxr_visual_gate — بوابة G5)', $runTool('tools/uxr_visual_gate.php'));

fwrite(STDOUT, str_repeat('─', 46) . "\n");
fwrite(STDOUT, "النتيجة: $pass/" . ($pass + $fail) . ($fail === 0 ? " — ✔ البوابة تمر\n" : " — ✘ البوابة تفشل\n"));
exit($fail === 0 ? 0 : 1);
