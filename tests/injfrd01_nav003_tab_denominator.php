<?php
/**
 * tests/injfrd01_nav003_tab_denominator.php
 *   شاهدُ FR-NAV-003 — مقامٌ واحدٌ للتبويبِ لا مقامان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «**مقامٌ واحدٌ للتبويب** — واللوحةُ والسايدبارُ يقرآن
 *   السجلَّ نفسَه» · ومعيارُ القبول «**صفرُ معنًى واحدٍ بتبويبَين مختلفَين**» ·
 *   وسالبُه «معنًى واحدٌ بتبويبَين ← رسوب».
 *
 * ◆ **والمقامان مقيسان بأسمائِهما**:
 *   · **السايدبار** ⇒ `nav_items.group_id` ← `link_groups` (**1292 مجموعة**)
 *     وتصنيفُها الأعلى `nav_group_taxonomy` (**12 تبويبًا**).
 *   · **اللوحةُ والمساحات** ⇒ `gov_space_appearances.tab_ar` (**127 تبويبًا**).
 *   ⇒ **سجلّان لا سجل**، والسؤالُ: أيختلفان على المعنى الواحد؟
 *
 * ◆ **والفرقُ في العددِ وحدَه ليس عطبًا**: تصنيفٌ أعلى (12) وتبويبٌ أدقُّ (127)
 *   قد يكونان طبقتَين مشروعتَين. **والعطبُ أن يحمل مسارٌ واحدٌ تبويبَين
 *   متناقضَين** — أن يُقال في السايدبارِ إنه تحت «أ» وفي اللوحةِ تحت «ب».
 *   فيُقاس **التناقضُ** لا الاختلافُ في الحجم.
 *
 * ◆ **ومقامٌ صفرٌ ليس نجاحًا**: يُشترَط أن يكون ثمَّ مساراتٌ **مشتركةٌ بين
 *   السجلَّين** قبلَ أن يُقرأ «صفرُ تناقض» — وإلا كان الصفرُ صفرَ تقاطع.
 *
 * التشغيل: php tests/injfrd01_nav003_tab_denominator.php [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$list = in_array('--list', $argv, true);
echo "══ FR-NAV-003 — مقامٌ واحدٌ للتبويب ══\n";

/* ── ① المقامان بأسمائِهما ─────────────────────────────────────────────── */
$sideGroups = n($db, "SELECT COUNT(*) FROM `link_groups`");
$taxonomy   = n($db, "SELECT COUNT(*) FROM `nav_group_taxonomy`");
$boardTabs  = n($db, "SELECT COUNT(DISTINCT `tab_ar`) FROM `gov_space_appearances`
                       WHERE COALESCE(`tab_ar`,'') <> ''");
printf("  السايدبار: link_groups=%d · تصنيفٌ أعلى=%d\n", $sideGroups, $taxonomy);
printf("  اللوحةُ والمساحات: tab_ar متمايزٌ=%d\n", $boardTabs);
chk($sideGroups > 0 && $boardTabs > 0, '**المقامان غيرُ صفريَّين** — سجلّانِ حيّان',
    "{$sideGroups} · {$boardTabs}");

/* ── ② التقاطعُ — كم مسارًا في السجلَّين معًا ────────────────────────────── */
$shared = n($db, "SELECT COUNT(DISTINCT LOWER(n.`route`)) FROM `nav_items` n
                   JOIN `gov_space_appearances` a ON LOWER(a.`route`) = LOWER(n.`route`)
                  WHERE COALESCE(a.`tab_ar`,'') <> ''");
printf("\n  مساراتٌ في السجلَّين معًا: **%d**\n", $shared);
chk($shared > 0,
    '**المقامُ غيرُ صفريّ** — ثمَّ تقاطعٌ يُقاس عليه التناقض',
    $shared > 0 ? "{$shared} مسارًا" : '**صفرُ تقاطعٍ — فصفرُ التناقضِ صفرُ بحثٍ لا صفرُ عطب**');

/* ── ③ التناقض: مسارٌ بتبويبَين مختلفَين في السجلَّين ──────────────────────
 * ◆ **عمودٌ باسمٍ مُخمَّنٍ يُخرج مقامًا صفريًّا**: كتبتُ `lg.group_name` والعمودُ
 *   اسمُه `name` — فعاد فارغًا لكلِّ صفٍّ **فقُورن صفرُ مسارٍ وطُبع «صفرُ
 *   تناقض»**. ولولا شرطُ «مقامُ المقارنةِ غيرُ صفريّ» لمرَّ خضرةً كاذبة. */
$conflict = array();
$r = $db->query("SELECT LOWER(n.`route`) rt,
                        GROUP_CONCAT(DISTINCT lg.`name` ORDER BY lg.`name`) side,
                        GROUP_CONCAT(DISTINCT a.`tab_ar` ORDER BY a.`tab_ar`) board
                   FROM `nav_items` n
                   JOIN `gov_space_appearances` a ON LOWER(a.`route`) = LOWER(n.`route`)
                   LEFT JOIN `link_groups` lg ON lg.`id` = n.`group_id`
                  WHERE COALESCE(a.`tab_ar`,'') <> ''
                  GROUP BY rt");
$cmp = 0;
while ($r && $x = $r->fetch_assoc()) {
    $side = trim((string) $x['side']);
    $board = trim((string) $x['board']);
    if ($side === '' || $board === '') { continue; }
    $cmp++;
    /* التناقضُ: **صفرُ تقاطعٍ في التسمية** بين ما يقوله السجلّان */
    $sideSet  = array_map('trim', explode(',', $side));
    $boardSet = array_map('trim', explode(',', $board));
    $inter = array_intersect($sideSet, $boardSet);
    if (empty($inter)) {
        $conflict[] = $x['rt'] . ': سايدبار«' . mb_substr($side, 0, 22)
                    . '» ≠ لوحة«' . mb_substr($board, 0, 22) . '»';
    }
}
printf("  مساراتٌ قُورنت فعلًا (لهما تسميةٌ في الاثنَين): **%d**\n", $cmp);
chk($cmp > 0, 'و**مقامُ المقارنةِ غيرُ صفريّ**', "{$cmp} مسارًا");
chk(empty($conflict),
    'FR-NAV-003 · **صفرُ معنًى واحدٍ بتبويبَين مختلفَين**',
    empty($conflict) ? 'صفرُ تناقض'
        : '**' . count($conflict) . ' متناقضًا** من ' . $cmp
          . sprintf(' (%.1f٪)', $cmp ? 100 * count($conflict) / $cmp : 0));
if ($list) { foreach (array_slice($conflict, 0, 10) as $c2) { echo "     ✘ {$c2}\n"; } }

/* ── ④ ولا يُقرأ اختلافُ الحجمِ عطبًا ───────────────────────────────────── */
echo "\n  ◆ **واختلافُ الحجمِ ليس عطبًا**: تصنيفٌ أعلى ({$taxonomy}) وتبويبٌ أدقُّ\n";
echo "    ({$boardTabs}) قد يكونان طبقتَين مشروعتَين. **والمقيسُ هو التناقضُ**\n";
echo "    على المعنى الواحد، لا الفرقُ في العدد.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
