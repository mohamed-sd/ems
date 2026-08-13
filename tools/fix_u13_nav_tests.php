<?php
/**
 * tools/fix_u13_nav_tests.php — INJ-0032 · INJ-0502: أيقيس ⑨-04 البلوغَ فعلًا؟
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0032 · INJ-0502
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ البندان يشتكيان فحصًا **يقيس غيرَ ما يدّعي**: كان نصُّه
 *       SELECT COUNT(*) FROM nav_items WHERE route='../{$rel}' AND active=1
 *   فيشترط البادئةَ `../` — وهي عينُها ما يجعل الصفَّ غيرَ قابلٍ للتصييرِ أصلًا.
 *   فكان يقيس **وجودَ صفٍّ بصيغةٍ معطوبة** لا **بلوغَ الشاشةِ من القائمة**.
 *
 * ◆ وأثرٌ كشفه القياسُ ولم يكن في الحكم: هجرةُ INJ-0061 أزالت البادئةَ من كلِّ
 *   الصفوفِ وقيّدت القاعدةَ بمنعِ عودتِها ⇒ **صفرُ صفٍّ** ببادئة `../`، فصار
 *   الفحصُ يرسب على **الأربعين وواحدٍ** جميعًا وهي سليمة. **إصلاحٌ صحيحٌ كسر
 *   فاحصًا اعتمد على الشكلِ المعطوب** — وهذا وجهٌ من «الفاحصُ الأوسعُ من معياره».
 *
 * ◆ واختبارُ القبولِ يطلب تمييزًا صريحًا: «أدخِل صفَّ `nav_items` ببابٍ وهميٍّ
 *   لشاشةٍ في البيان: يجب أن يرسب الفحصُ ⑨-٠٤؛ وبعد التصحيحِ يمرّ». فيُجَسُّ
 *   في الاتجاهين، وتُستعاد الحالُ كما كانت حرفًا بحرف.
 *
 * التشغيل: php tools/fix_u13_nav_tests.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
require_once $ROOT . '/tools/u13_screens_manifest.php';
$db = fix_db();
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

/** يُشغّل بوابةَ u13 ويعيد حكمَ فحصٍ بعينِه وشاهدَه. */
function u13_check($ROOT, $code)
{
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($ROOT . '/tools/u13_gate.php') . ' 2>&1');
    $lines = explode("\n", $out);
    foreach ($lines as $i => $ln) {
        if (strpos($ln, $code) !== false) {
            return array('pass' => strpos($ln, '✔') !== false,
                         'line' => trim($ln),
                         'detail' => isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '');
        }
    }
    return array('pass' => null, 'line' => '', 'detail' => '');
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " ⑨-04: أيقيس البلوغَ أم وجودَ صفّ؟ — INJ-0032 · INJ-0502 · " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";

/* ══ ① الشرطُ المعطوبُ مرفوعٌ من الشيفرة ═══════════════════════════════ */
head('① الشرطُ المعطوبُ مرفوعٌ — والقياسُ صار على مصدرِ القائمة');
$raw = (string) file_get_contents($ROOT . '/tools/u13_gate.php');
$src = function_exists('fix_strip_comments') ? fix_strip_comments($raw) : preg_replace('#/\*.*?\*/#s', '', $raw);
check(strpos($src, "route='../") === false,
    'صفرُ اشتراطٍ للبادئة `../` في **الشيفرةِ** (بعد تجريدِ التعليقات)');
check(strpos($src, 'getUnifiedNavItems($db, $role)') !== false,
    'والفحصُ ينادي `getUnifiedNavItems` — مصدرَ القائمةِ نفسَه لا استعلامًا موازيًا');

/* ══ ② الواقعُ الذي كان الفحصُ يعمى عنه ═══════════════════════════════ */
head('② الواقعُ المقيس: صفرُ بادئةٍ نسبيةٍ وقيدٌ يمنع عودتَها');
$q = static function ($sql) use ($db) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : null; };
$rel = $q("SELECT COUNT(*) FROM nav_items WHERE route LIKE '../%'");
check($rel === 0, 'صفوفُ تنقّلٍ ببادئة `../`: ' . var_export($rel, true) . ' — فالفحصُ القديمُ كان يبحث عن معدوم');
$chk = $q("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_nav_route_not_relative'");
check($chk > 0, 'وقيدُ القاعدةِ يمنع عودتَها — فالشرطُ الثالثُ في الفحصِ شهادةٌ لا اعتماد');

/* ══ ③ الحالُ الراهنة: الأربعون وواحدٌ تُبلَغ ══════════════════════════ */
head('③ الحالُ الراهنة');
$MAN = u13_screens_manifest();
$N = count($MAN);
$now = u13_check($ROOT, '⑨-04');
check($now['pass'] === true, "⑨-04 يمرُّ على {$N} شاشةً — " . mb_substr($now['detail'], 0, 60));

/* ══ ④ التمييزُ السالب: بابٌ وهميٌّ يجب أن يُرصَد ═══════════════════════ */
head('④ التمييزُ: شاشةٌ بلا بابٍ ولا مرحلةٍ **يجب أن ترسب**');
$s0 = $MAN[0];
$rel0 = $s0['dir'] . '/' . $s0['file'];
$role0 = (int) $s0['role'];
$esc = $db->real_escape_string($rel0);
$row = $db->query("SELECT id, door, group_id FROM nav_items
                    WHERE route='{$esc}' AND role_id={$role0} AND active=1 LIMIT 1");
$nav = $row ? $row->fetch_assoc() : null;
check($nav !== null, 'وُجد صفُّ التنقّلِ للشاشةِ الأولى: ' . $rel0 . ' (دور ' . $role0 . ')');
if ($nav === null) { echo "\nتعذّر الجسُّ — لا صفَّ تنقّلٍ للشاشةِ الأولى\n"; exit(1); }

$navId = (int) $nav['id'];
$oldDoor = $nav['door'];
$oldGroup = $nav['group_id'];
$restore = static function () use ($db, $navId, $oldDoor, $oldGroup) {
    $st = $db->prepare('UPDATE nav_items SET door = ?, group_id = ? WHERE id = ?');
    if (!$st) { return false; }
    $st->bind_param('sii', $oldDoor, $oldGroup, $navId);
    $ok = $st->execute();
    $st->close();
    return $ok;
};
register_shutdown_function($restore);   // تُستعاد الحالُ ولو مات الاختبار

/* بابٌ فارغٌ ومجموعةٌ منزوعةٌ ⇒ لا بابَ ولا مرحلة */
$db->query("UPDATE nav_items SET door = '', group_id = NULL WHERE id = {$navId}");
$broken = u13_check($ROOT, '⑨-04');
check($broken['pass'] === false, '⑨-04 **يرسب** حين تفقد الشاشةُ بابَها ومرحلتَها');
check(strpos($broken['detail'], $rel0) !== false || strpos($broken['detail'], 'بلا بابٍ') !== false,
    'ويسمّي السببَ في الشاهد — ' . mb_substr($broken['detail'], 0, 66));

/* ══ ⑤ والاستعادةُ تُعيده أخضر ═══════════════════════════════════════════ */
head('⑤ الاستعادة');
check($restore() !== false, 'أُعيد البابُ والمجموعةُ إلى ما كانا');
$back = u13_check($ROOT, '⑨-04');
check($back['pass'] === true, 'و⑨-04 يمرُّ من جديد — فالفاحصُ يميّز ولا يرسب على الكل');
$after = $db->query("SELECT door, group_id FROM nav_items WHERE id = {$navId}")->fetch_assoc();
check((string) $after['door'] === (string) $oldDoor && (string) $after['group_id'] === (string) $oldGroup,
    'والصفُّ عاد حرفًا بحرف (باب=' . var_export($after['door'], true) . ' · مجموعة=' . var_export($after['group_id'], true) . ')');

echo "\n" . str_repeat('═', 70) . "\n";
printf("ناجحٌ: %d · فاشلٌ: %d\n", $PASS, $FAIL);
echo "◆ ⑨-04 صار يقيس **بلوغَ** الشاشةِ من `getUnifiedNavItems` لا وجودَ صفٍّ\n";
echo "  بصيغةٍ معطوبة — ويرسب على المخالفِ ويمرُّ على السليم.\n";
echo str_repeat('═', 70) . "\n";
exit($FAIL === 0 ? 0 : 1);
