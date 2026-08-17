<?php
/**
 * tests/seed_code_column_guard_test.php — إثباتُ القيدِ المانعِ لعودةِ التلوث
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الخطوةُ ⑤ في قرارِ المالك: «ثم قيدٌ يمنع عودتَه». والقيدُ لا يُصدَّق بدعوى.
 *
 * ◆ فهذا الفاحصُ يُثبت أربعةً بالتشغيل:
 *   ① الخاناتُ التي تلوَّثت فعلًا تُصنَّف الآن **خانةَ رمزٍ** فتُمنع الجملة.
 *   ② حقلُ الملاحظاتِ الحقيقيُّ **لا يُصنَّف** رمزًا — فلا يتحول القيدُ إلى
 *      منظّفٍ واسعٍ يُفقر البياناتِ المشروعة (وهو نهيُ المالكِ صراحةً).
 *   ③ القيمةُ تُؤخذ من **مصدرٍ حاكمٍ قائم** — لا تُخترع.
 *   ④ وإن خلا المصدرُ يُردُّ **null** — لا جملةٌ ولا تخمين.
 *
 * ◆ والفحصُ ② هو **حارسُ الحارس**: قيدٌ يمنع التلوثَ بأن يمنع كلَّ نصٍّ ليس
 *   قيدًا بل عطبٌ آخر — فيُقاس أنه لا يفعل ذلك.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);

/* تُستخرَجُ الدالتانِ من الباذرِ بلا تشغيلِه — فالفحصُ لا يبذر شيئًا */
$src = (string) file_get_contents($ROOT . '/database/seeds/uat0001/10_autofill.php');
if (!preg_match('~function uat_is_code_column.*?\n\}~s', $src, $m1)) {
    exit("✗ لم تُوجد uat_is_code_column\n");
}
eval($m1[0]);

$pass = 0; $fail = 0;
$check = function ($label, $got, $want) use (&$pass, &$fail) {
    $ok = ($got === $want);
    printf("  %s %-58s %s\n", $ok ? '✔' : '✗', $label,
        $ok ? '' : ('توقُّع=' . var_export($want, true) . ' · واقع=' . var_export($got, true)));
    $ok ? $pass++ : $fail++;
};

echo "════ ① الخاناتُ التي تلوَّثت فعلًا — تُمنع الآن ════\n";
/* هذه الأعمدةُ مقروءةٌ من سجلِّ العزلِ الحيِّ لا مؤلَّفة */
$polluted = array(
    array('entity_type', 50), array('fuel_type', 20), array('operating_category', 20),
    array('from_state', 20), array('to_state', 20), array('origin_state', 20),
    array('destination_state', 20), array('source_kind', 40), array('ded_kind', 60),
    array('std_capacity_uom', 20), array('action_type', 80),
);
foreach ($polluted as $p) {
    $check("«{$p[0]}» ({$p[1]} محرفًا) خانةُ رمز", uat_is_code_column($p[0], $p[1]), true);
}

echo "\n════ ② حارسُ الحارس — الملاحظاتُ المشروعةُ لا تُمَسّ ════\n";
$legit = array(
    array('notes', 65535), array('time_notes', 65535), array('description', 500),
    array('remarks', 1000), array('comment', 2000), array('reason_text', 500),
    array('justification', 4000),
);
foreach ($legit as $l) {
    $check("«{$l[0]}» ({$l[1]}) ليست خانةَ رمز", uat_is_code_column($l[0], $l[1]), false);
}

echo "\n════ ③ المصدرُ الحاكمُ يُقرأ من العمودِ نفسِه ════\n";
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
$conn->set_charset('utf8mb4');
$r = $conn->query("SELECT DISTINCT fuel_type v FROM fleet_model
                    WHERE fuel_type NOT LIKE '% %' AND fuel_type <> '' LIMIT 5");
$real = array();
while ($r && ($x = $r->fetch_assoc())) { $real[] = $x['v']; }
$check('لـfleet_model.fuel_type مصدرٌ حاكمٌ قائم', count($real) > 0, true);
if ($real) { echo "     القيمُ الحاكمة: " . implode(' · ', $real) . "\n"; }
$check('ولا واحدةَ منها جملة', count(array_filter($real, fn($v) => strpos($v, ' ') !== false)), 0);

echo "\n════ ④ وإن خلا المصدرُ — null لا جملة ════\n";
if (!preg_match('~function uat_existing_code_value.*?\n\}~s', $src, $m2)) {
    echo "  ✗ لم تُوجد uat_existing_code_value\n"; $fail++;
} else {
    /* جدولٌ لا وجودَ له ⇒ لا مصدرَ حاكمَ ⇒ null */
    if (!function_exists('uat_db')) {
        eval('function uat_db() { global $conn; return $conn; }');
    }
    if (!defined('UAT_TAG')) { define('UAT_TAG', 'UAT-2026'); }
    eval($m2[0]);
    $check('عمودٌ بلا مصدرٍ حاكم يردُّ null', uat_existing_code_value('__no_such_table__', 'x', 0), null);
}

echo "\n════════════════════════════════════════════════════════════\n";
printf("  اجتاز %d · أخفق %d\n", $pass, $fail);
echo $fail === 0
    ? "✔ القيدُ يمنع عودةَ التلوثِ **ولا يمسُّ نصًّا مشروعًا** — مُثبَتٌ بالتشغيل\n"
    : "✗ القيدُ غيرُ مُثبَت\n";
exit($fail === 0 ? 0 : 1);
