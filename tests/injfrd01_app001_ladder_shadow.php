<?php
/**
 * tests/injfrd01_app001_ladder_shadow.php
 *   شاهدُ FR-APP-001 — نمطُ الظلِّ يرصد ولا يمنع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معاييرُ الدفتر**: الموجب «معاملةٌ مطابقةٌ ← صفرُ صفِّ تباين» · والسالب
 *   «معاملةٌ مخالفةٌ ← **صفُّ تباينٍ مكتوبٌ بسببِه ولا توقُّف**» · وسلوكُ الفشل
 *   «لا يوقف الظلُّ معاملةً أبدًا».
 *
 * ◆ **والقياسُ على السلوكِ لا على الشيفرة**: يُنادى الحارسُ فعلًا بحالتَين —
 *   فاعلٌ صاحبُ يدٍ وفاعلٌ ليس صاحبَها — ويُقاس ما كُتب في سجلِّ الظلِّ وما
 *   أرجعه الحارسُ لمُنادِيه.
 *
 * ◆ **ولا يُدَّعى إغلاق**: نافذةُ الملاحظةِ (٥٠٠ قرارٍ أو ٧ أيام) مصدرُها
 *   الدفتر، وهي **لم تُستوفَ** — فالحكمُ «مبنيٌّ وموصولٌ ولم يُمارَس».
 *
 * التشغيل: php tests/injfrd01_app001_ladder_shadow.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
require_once $ROOT . '/includes/unit_chain_helpers.php';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$CO = 4; $MARK = 'APP001BELT';
/* كنسٌ بالعائلةِ قبلًا وبعدًا — والوسمُ ثابتٌ لا `getmypid()` */
$conn->query("DELETE FROM `gov_ladder_shadow` WHERE `subject_kind` = '{$MARK}'");

echo "══ FR-APP-001 · نمطُ الظلِّ — يرصد ولا يمنع ══\n";

/* ① السجلُّ قائمٌ ومُعدٌّ لقياسِ المقامِ لا البسطِ وحدَه */
$cols = array();
$r = $conn->query("SHOW COLUMNS FROM `gov_ladder_shadow`");
while ($r && $x = $r->fetch_assoc()) { $cols[$x['Field']] = true; }
chk(isset($cols['current_decision'], $cols['ladder_decision'], $cols['diverged']),
    'سجلُّ الظلِّ يحمل **القرارَين والتباينَ** لا المنعَ وحدَه',
    count($cols) . ' عمودًا');

/* ② سلّمٌ حيٌّ للقياسِ عليه */
$ld = $conn->query("SELECT `ladder_code` FROM `gov_ladders` LIMIT 1");
$ladder = $ld ? (string) ($ld->fetch_row()[0] ?? '') : '';
chk($ladder !== '', 'وُجد سلّمٌ حيٌّ للقياسِ عليه', $ladder);

$before = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow`");

/* ③ **الموجب**: الحارسُ يُنادى ولا يوقف معاملةً */
$g1 = ems_ladder_guard($conn, $ladder, $CO, $MARK, 900001, 900011, '', 'شاهدٌ موجب');
chk(!empty($g1['ok']), '**الحارسُ لا يوقف معاملةً في نمطِ الظلّ**',
    'الرمز: ' . (int) $g1['code']);

/* ④ وكلُّ تقييمٍ يُرصد — سماحًا كان أو منعًا */
$after = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow`");
chk($after > $before, 'وكلُّ تقييمٍ يُرصد — فالمقامُ معلوم',
    "المرصود: {$before} ⇐ {$after}");

/* ⑤ **السالب**: فاعلٌ ليس صاحبَ اليدِ ← صفُّ تباينٍ بسببِه ولا توقُّف */
$g2 = ems_ladder_guard($conn, $ladder, $CO, $MARK, 900002, 999999, '', 'شاهدٌ سالب');
chk(!empty($g2['ok']), 'وفاعلٌ ليس صاحبَ اليدِ **لا يُوقَف** في الظلّ',
    'الرمز: ' . (int) $g2['code']);

$div = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow`
                  WHERE `subject_kind` = '{$MARK}' AND `diverged` = 1");
$reasoned = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow`
                       WHERE `subject_kind` = '{$MARK}' AND `diverged` = 1 AND `reason` <> ''");
chk($div > 0, '**وصفُّ تباينٍ كُتب** عند اختلافِ القرارَين', "تباينات: {$div}");
chk($div === 0 || $reasoned === $div, 'وكلُّ تباينٍ **مكتوبٌ بسببِه**',
    "بسببٍ مكتوب: {$reasoned}/{$div}");

/* ⑥ والعطالة: تكرارُ المحاولةِ لا يضخّم المقام */
$b2 = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow` WHERE `subject_kind` = '{$MARK}'");
ems_ladder_guard($conn, $ladder, $CO, $MARK, 900002, 999999, '', 'شاهدٌ سالب');
$a2 = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow` WHERE `subject_kind` = '{$MARK}'");
chk($a2 === $b2, 'والتكرارُ عطالةٌ — لا يضخّم المقامَ ولا يُنشئ تباينًا ثانيًا',
    "{$b2} ⇐ {$a2}");

/* ⑦ **وفشلُ المقارنِ لا يمنع** — يُجرَّب بإخفاءِ سجلِّ الظلِّ نفسِه */
$renamed = false;
try {
    if ($conn->query("RENAME TABLE `gov_ladder_shadow` TO `gov_ladder_shadow__belt`")) { $renamed = true; }
    $g3 = ems_ladder_guard($conn, $ladder, $CO, $MARK, 900003, 900011, '', 'فشلُ المقارن');
    chk(!empty($g3['ok']), '**وفشلُ المقارنِ يُسجَّل ولا يمنع** — المعاملةُ تمضي',
        'الرمز: ' . (int) $g3['code']);
} catch (\Throwable $e) {
    chk(false, 'فشلُ المقارنِ أوقف المعاملة', mb_substr($e->getMessage(), 0, 60));
} finally {
    if ($renamed) { $conn->query("RENAME TABLE `gov_ladder_shadow__belt` TO `gov_ladder_shadow`"); }
}
chk(n($conn, "SELECT COUNT(*) FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_ladder_shadow'") === 1,
    'وأُعيد السجلُّ حتمًا — الحزامُ لا يترك أثرًا');

/* ⑧ **نافذةُ الملاحظةِ لم تُستوفَ — ولا يُدَّعى إغلاق** */
$obs = n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow` WHERE `subject_kind` <> '{$MARK}'");
$days = n($conn, "SELECT COALESCE(DATEDIFF(NOW(), MIN(`observed_at`)),0)
                    FROM `gov_ladder_shadow` WHERE `subject_kind` <> '{$MARK}'");
$met = ($obs >= 500 || $days >= 7);
echo "\n  ── نافذةُ الملاحظةِ الدنيا (مصدرُها الدفتر: ٥٠٠ قرارٍ أو ٧ أيام) ──\n";
printf("  ◆ المرصودُ الحقيقيُّ: %d قرارًا · %d يومًا ⇒ %s\n", $obs, $days,
       $met ? 'استُوفيت' : '**لم تُستوفَ**');
echo "  ◆ فالحكمُ: **مبنيٌّ وموصولٌ ولم يُمارَس** — ولا يُكتب EVIDENCE_CLOSED.\n";

$conn->query("DELETE FROM `gov_ladder_shadow` WHERE `subject_kind` = '{$MARK}'");
chk(n($conn, "SELECT COUNT(*) FROM `gov_ladder_shadow` WHERE `subject_kind` = '{$MARK}'") === 0,
    'وكُنس الشاهدُ أثرَه بالعائلة');

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
