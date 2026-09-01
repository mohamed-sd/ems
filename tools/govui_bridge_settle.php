<?php
/**
 * tools/govui_bridge_settle.php — تسويةُ جسرِ الهدفِ والمبنيّ (‏§10 · §18)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: ثلاثةُ أهدافٍ تُقرأ `FIELD_MISMATCH` في مصفوفةِ §18
 *   وسببُها **«لا صفَّ في دفترِ حقولِ المبنيّ»** — أي **غيرُ مقيسٍ لا مخالف**.
 *   والجذرُ أبعدُ خطوةً: `repair01_target_universe` يحمل لكلِّ واحدٍ منها صفًّا
 *   حكمُه `NOT_BUILT` **وشاشتُه مبنيّةٌ على القرصِ باسمِها الحاكمِ حرفًا**.
 *   ⇒ **دفتران لكيانٍ واحد** [[two-registers-target-vs-built]]: أحدُهما يقول
 *   «لم يُبنَ» والآخرُ يعرضه في السايدبار. **والحكمُ المتقادمُ يمنع القياسَ
 *   فيُقرأ الغيابُ مخالفةً** — وهو صفرٌ كاذبٌ من جسرٍ مكسور.
 *
 * ◆ **والعلاجُ في الأداةِ لا في البيانات** (‏قاعدةُ الجولةِ ⑦): قاعدةٌ واحدةٌ
 *   مكتوبةٌ تُطبَّق على المدى كلِّه — **لا تصحيحُ ثلاثةِ صفوفٍ باليد**:
 *     صفٌّ حكمُه `NOT_BUILT` وبلا `screen_id`، واسمُه الحاكمُ يطابق **شاشةً
 *     واحدةً بعينِها** على القرصِ (`on_disk = 1`) ⇒ يُربَط ويُعاد حكمُه
 *     `MATCHED` بشاهدٍ مكتوب.
 *   ⛔ **والملتبِسُ لا يُربَط**: اسمٌ يطابق شاشتَين يُعرَض ولا يُحسَم
 *   (‏المقيسُ اليومَ: صفرُ ملتبِس — ويُعاد قياسُه في كلِّ تشغيل).
 *
 * ◆ **ولا يُغيَّر `Target_ID` ولا `Screen_ID`** (§4): الربطُ يملأ خانةً فارغةً
 *   ويُصحِّح حكمًا — **ولا يُنشئ هدفًا ولا شاشة**.
 *
 * التشغيل: php tools/govui_bridge_settle.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);

echo "══ تسويةُ جسرِ الهدفِ والمبنيّ ══\n";

/* ① الملتبِسُ أوّلًا — ⛔ ولا يُربَط */
$amb = array();
$r = $conn->query("SELECT u.target_uid, u.name_ar, COUNT(DISTINCT s.screen_id) n
                     FROM repair01_target_universe u
                     JOIN repair01_screen_registry s
                       ON s.canonical_label_ar = u.name_ar AND s.on_disk = 1
                    WHERE u.verdict = 'NOT_BUILT' AND (u.screen_id = '' OR u.screen_id IS NULL)
                    GROUP BY u.target_uid, u.name_ar HAVING n > 1");
while ($x = $r->fetch_assoc()) { $amb[] = $x; }

/* ② المرشَّحُ بلا لبس */
$cand = array();
$r = $conn->query("SELECT u.target_uid, u.unit, u.name_ar, u.requirement_id,
                          MIN(s.screen_id) screen_id, MIN(s.route) route
                     FROM repair01_target_universe u
                     JOIN repair01_screen_registry s
                       ON s.canonical_label_ar = u.name_ar AND s.on_disk = 1
                    WHERE u.verdict = 'NOT_BUILT' AND (u.screen_id = '' OR u.screen_id IS NULL)
                    GROUP BY u.target_uid, u.unit, u.name_ar, u.requirement_id
                   HAVING COUNT(DISTINCT s.screen_id) = 1
                    ORDER BY u.unit, u.name_ar");
while ($x = $r->fetch_assoc()) { $cand[] = $x; }

/* ③ والاتجاهُ المعاكس: صفٌّ مبنيٌّ بلا متطلبٍ ونظيرُه التصميميُّ يحمله */
$orphan = array();
$r = $conn->query("SELECT b.target_uid, b.unit, b.name_ar, b.screen_id,
                          d.target_uid AS design_uid, d.requirement_id
                     FROM repair01_target_universe b
                     JOIN repair01_target_universe d
                       ON d.name_ar = b.name_ar AND d.target_uid <> b.target_uid
                      AND d.requirement_id <> ''
                    WHERE (b.requirement_id = '' OR b.requirement_id IS NULL)
                      AND b.screen_id <> ''");
while ($x = $r->fetch_assoc()) { $orphan[] = $x; }

printf("  حكمٌ `NOT_BUILT` متقادمٌ وشاشتُه مبنيّة: **%d** · ملتبِسٌ لا يُربَط: **%d**\n",
    count($cand), count($amb));
printf("  صفٌّ مبنيٌّ بلا متطلبٍ ونظيرُه التصميميُّ يحمله: **%d**\n", count($orphan));
foreach ($cand as $c) {
    printf("     %-10s %-12s %-40s %-12s %s\n", $c['unit'], $c['requirement_id'],
        mb_substr($c['name_ar'], 0, 38), $c['screen_id'], mb_substr($c['route'], 0, 40));
}
foreach ($amb as $a) { printf("     ⚠ ملتبِس: %s · %s (%d شاشات)\n", $a['target_uid'], $a['name_ar'], $a['n']); }
foreach ($orphan as $o) {
    printf("     ↔ %-10s %-38s %s ⇐ متطلبُ %s من %s\n", $o['unit'],
        mb_substr($o['name_ar'], 0, 36), $o['screen_id'], $o['requirement_id'], $o['design_uid']);
}

if (!$APPLY) { echo "\n  ◆ قياسٌ فقط — للتطبيق: `--apply`\n"; exit(0); }

$now = date('Y-m-d H:i:s');
$snapRow = $conn->query("SELECT snapshot_id FROM repair01_field_measure ORDER BY id DESC LIMIT 1");
$snap = $snapRow && $snapRow->num_rows ? $snapRow->fetch_assoc()['snapshot_id'] : 'SNAP-BRIDGE';

$n1 = 0;
foreach ($cand as $c) {
    $wit = 'اسمُ الهدفِ الحاكمُ يطابق `repair01_screen_registry.canonical_label_ar` '
         . 'لشاشةٍ **واحدةٍ** على القرص (' . $c['route'] . ') — govui_bridge_settle';
    $q = $conn->prepare("UPDATE repair01_target_universe
                            SET screen_id = ?, verdict = 'MATCHED', verdict_witness = ?,
                                verdict_snapshot = ?, verdict_at = ?,
                                match_method = 'CANONICAL_LABEL_UNIQUE', match_witness = ?
                          WHERE target_uid = ?");
    $q->bind_param('ssssss', $c['screen_id'], $wit, $snap, $now, $wit, $c['target_uid']);
    $q->execute(); $n1 += $q->affected_rows; $q->close();
}
$n2 = 0;
foreach ($orphan as $o) {
    $wit = 'المتطلبُ مأخوذٌ من نظيرِه التصميميِّ ' . $o['design_uid']
         . ' بالاسمِ الحاكمِ نفسِه — govui_bridge_settle';
    $q = $conn->prepare("UPDATE repair01_target_universe SET requirement_id = ?, match_witness = ?
                          WHERE target_uid = ? AND (requirement_id = '' OR requirement_id IS NULL)");
    $q->bind_param('sss', $o['requirement_id'], $wit, $o['target_uid']);
    $q->execute(); $n2 += $q->affected_rows; $q->close();
}
printf("\n  ⇒ رُبط: أحكامٌ متقادمةٌ صُحِّحت **%d** · متطلباتٌ مُلئت **%d**\n", $n1, $n2);
echo "  ◆ ثمَّ أعِدْ: `rpr02_field_measure --apply` تحتَ نافذةٍ · ثمَّ `govui_conformance_matrix`\n";
