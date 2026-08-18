<?php
/**
 * tests/f17_formula_test.php — F-17 في موضعِها الحيِّ باختبارٍ يرسّب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تفويضُ المالكِ (د): «F-17 في الموضعِ الحيِّ مع اختبارٍ يرسّب».
 * ◆ الوثيقةُ 70 تنصُّ الصيغةَ على أعمدةٍ **لا وجودَ لها** في هذا النظام
 *   (unit_entries.run_hours · machine_code · work_date). والحيُّ المطبَّعُ هو
 *   unit_time_log(equipment_id · log_date · hours · ops_state) — و«ساعاتُ
 *   التشغيل» هي ops_state='actual_work' وحدَها لا مجموعُ الزمن.
 * ◆ هذا الاختبارُ يحرس أربعةَ أشياءَ يرسّب عند أيٍّ منها:
 *   ① الأعمدةُ الأربعةُ قائمةٌ في المخطَّطِ الحيِّ بأنواعِها.
 *   ② الصيغةُ المنفَّذةُ تقرأ من unit_time_log بشرطِ actual_work حرفًا.
 *   ③ الصيغةُ تعمل فعلًا على القاعدةِ وتُرجع صفوفًا (لا SQL ميتة).
 *   ④ الفرزُ صحيح: مجموعُ actual_work < مجموعِ الكلِّ — أي أن الشرطَ يفرز
 *      فعلًا ولم يُلغَ سهوًا (وهو الانحرافُ الذي يُخفي نفسَه بأرقامٍ أكبر).
 *
 * التشغيل: php tests/f17_formula_test.php   ·   الخروج 0 نجاحٌ · 1 رسوب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Assets/AssetHoursService.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0;
function t($name, $ok, $note = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ✔ {$name}\n"; }
    else { $fail++; echo "  ✘ FAIL: {$name}" . ($note !== '' ? " — {$note}" : '') . "\n"; }
}

echo "══ F-17 · ساعاتُ التشغيلِ لكلِّ معدة-شهر ══\n";

/* ① الأعمدةُ الحيّة */
$cols = array();
$r = $conn->query("SHOW COLUMNS FROM unit_time_log");
while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = $x['Type']; }
t('unit_time_log موجود بأعمدتِه', !empty($cols));
foreach (array('equipment_id', 'log_date', 'hours', 'ops_state', 'company_id') as $c) {
    t("العمود {$c} قائم", isset($cols[$c]), 'الصيغةُ تنكسر بلا هذا العمود');
}
t("ops_state يحمل القيمةَ actual_work",
   isset($cols['ops_state']) && strpos($cols['ops_state'], "'actual_work'") !== false,
   'التعدادُ تغيّر — الشرطُ يصير دائمًا كاذبًا فالساعاتُ صفر');

/* ② الصيغةُ المنفَّذةُ نصًّا */
$f17 = \App\Services\Assets\AssetHoursService::F17;
t('الصيغةُ تقرأ من unit_time_log', stripos($f17, 'unit_time_log') !== false);
t("الصيغةُ تشترط ops_state = 'actual_work'",
   preg_match("~ops_state`?\s*=\s*'actual_work'~i", $f17) === 1,
   'بلا الشرطِ تُحسب ساعاتُ الانتظارِ والعطلِ تشغيلًا');
t('الصيغةُ تجمع hours بالمعدةِ والشهر',
   stripos($f17, 'SUM(`hours`)') !== false && stripos($f17, 'equipment_id') !== false,
   'التجميعُ تغيّر عن عقدِ F-17');
t('الصيغةُ لا تشير إلى أعمدةِ الوثيقةِ غيرِ الموجودة',
   stripos($f17, 'run_hours') === false && stripos($f17, 'machine_code') === false && stripos($f17, 'work_date') === false,
   'عادت الصيغةُ إلى أعمدةٍ لا وجودَ لها في المخطَّط');

/* ③ تعمل فعلًا */
$res = $conn->query($f17 . " LIMIT 5");
t('الصيغةُ تُنفَّذ على القاعدةِ بلا خطأ', $res !== false, $conn->error);
$rowCount = 0;
if ($res) { $rowCount = $res->num_rows; }
t('الصيغةُ تُرجع صفوفًا من البياناتِ الحية', $rowCount > 0, 'صفرُ صفٍّ — إمّا لا بياناتٍ أو الشرطُ يمنع كلَّ شيء');

/* ④ الفرزُ يفرز فعلًا */
$a = $conn->query("SELECT ROUND(SUM(hours),2) h FROM unit_time_log WHERE ops_state='actual_work'");
$b = $conn->query("SELECT ROUND(SUM(hours),2) h FROM unit_time_log");
$ha = $a ? (float) $a->fetch_assoc()['h'] : 0.0;
$hb = $b ? (float) $b->fetch_assoc()['h'] : 0.0;
t("التشغيلُ الفعليُّ ({$ha}) أقلُّ من مجموعِ الزمنِ كلِّه ({$hb})",
   $hb > 0 && $ha > 0 && $ha < $hb,
   'الشرطُ لا يفرز — «ساعاتُ التشغيل» صارت مجموعَ الزمنِ بما فيه العطلُ والانتظار');

echo "\nالنتيجة: {$pass} ناجح · {$fail} فاشل\n";
exit($fail === 0 ? 0 : 1);
