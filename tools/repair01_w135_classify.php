<?php
/**
 * tools/repair01_w135_classify.php — تسييجُ الدَّينِ وقرارُ الشبحِ وسدُّ اليُتم
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك · البنود 15 · 16 · 17 · 18**. ثلاثةُ محاورَ في أداةٍ واحدةٍ
 * لأنَّ مصدرَها واحد: **حكمُ الملكيّةِ الذي كُتب في المصالحة**.
 *
 * ◆ **ولا حكمَ بلا قاعدةٍ** — يفرضه `chk_w135_ghost` في القاعدةِ للأشباح،
 *   ويُكتب في `verdict_rule` للأسطح. فما لا تحسمه قاعدةٌ **يُترَك فارغًا
 *   ويُرفع للمالك** ⛔ ولا يُملأ ليخضرَّ العدّاد.
 *
 * ◆ **والمساحةُ المشتركةُ ليست يتيمة** (‏البند 18 نصًّا: «قد يكون
 *   `Platform SLA Capability` وليس إدارة»): أحدَ عشرَ سطحًا مالكُها المنصّةُ
 *   لا إدارة — فتُسجَّل `PLATFORM` صراحةً، والفراغُ وحدَه يُتمٌ يُعالَج.
 *
 * التشغيل: php tools/repair01_w135_classify.php [--report]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$REPORT = in_array('--report', $argv, true);
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$W = function ($sql) use ($conn, $REPORT) { return $REPORT ? true : ($conn->query($sql) === true); };

echo "\n═══ W13.5 · تسييجُ الدَّينِ وقرارُ الشبحِ وسدُّ اليُتم ═══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n" : "\n");

/* ═══ ① تسييجُ دَينِ الماليةِ والخزينة (البندان 15 · 16) ═══════════════════
     ◆ **التصنيفُ يُشتقُّ من حكمِ الملكيّةِ لا يُخترَع**: فالسطحُ الذي حُكم فيه
       `RETIRE` لا يصير `TARGET`، والذي حُكم `PROJECTION` لا يصير `SOURCE`.
       واتّساقُ المحورَين شرطٌ — وإلّا صار لكلِّ سطحٍ حقيقتان. */
echo "① تصنيفُ دَينِ الماليةِ والخزينة\n";
$FIN = array(
    array("ownership_verdict = 'RETIRE'", 'RETIRE', 'شبح منقول الى دفتر الفجوات — لا ملف'),
    array("ownership_verdict = 'TAB_CHILD'", 'MERGE', 'تبويب في شاشة ابيه — يندمج ولا يستقل'),
    array("ownership_verdict = 'LEGACY'", 'LEGACY_READ_ONLY', 'موروث ينقصه مالك او حارس — دين معدود'),
    array("origin IN ('W11','W12')", 'TARGET', 'سطح مستهدف بني في موجته'),
    array("surface_kind = 'PROJECTION'", 'PROJECTION', 'يعرض حقيقة يملكها نطاق اخر'),
    array("surface_kind = 'SOURCE'", 'SOURCE', 'مصدر حقيقة نطاقه'),
);
$fin = 0;
foreach ($FIN as $f) {
    $ok = $W("UPDATE repair01_screen_registry
                 SET finance_debt_class = '" . $e($f[1]) . "',
                     debt_owner = owner_code,
                     debt_wave  = 'W13.5'
               WHERE owner_code IN ('DEP-05','DEP-06')
                 AND finance_debt_class = '' AND " . $f[0]);
    if ($ok && !$REPORT) { $fin += $conn->affected_rows; }
}
$q = $conn->query("SELECT finance_debt_class c, COUNT(*) n FROM repair01_screen_registry
                    WHERE owner_code IN ('DEP-05','DEP-06') GROUP BY c ORDER BY n DESC");
while ($q && ($x = $q->fetch_assoc())) {
    printf("  %-20s %d\n", ($x['c'] === '' ? '(بلا تصنيف)' : $x['c']), $x['n']);
}

/* ═══ ② قرارُ الشبحِ — ستُّ قيمٍ (البند 17) ════════════════════════════════
     ⛔ **ولا تُبنى المئةُ والستّون تلقائيًّا** — نصُّ الأمر. فالقاعدةُ تفرز
       ما يُحسم بأثرٍ مقيسٍ فقط، والباقي يُرفع. */
echo "\n② قرارُ الشبحِ — ستُّ قيمٍ لا غير\n";
$GH = array(
    array("built_counterpart <> ''", 'MERGE',
          'سطح قائم باسم اخر يوفيه — قيد في built_counterpart ولا يبنى له توام'),
    array("verdict = 'TAB'", 'TAB', 'محكوم تبويبا في دفتر الفجوات'),
    array("verdict IN ('RETIRE','RETIRED')", 'RETIRE', 'محكوم متقاعدا في دفتر الفجوات'),
    array("wave_stage <> '' AND wave_stage NOT IN ('W00','W01','W02')", 'BUILD',
          'مختوم بموجة قادمة — يبنى في موجته لا الان'),
);
$gh = 0;
foreach ($GH as $g) {
    $ok = $W("UPDATE repair01_target_gaps
                 SET ghost_disposition = '" . $e($g[1]) . "',
                     disposition_why   = '" . $e($g[2]) . "'
               WHERE ghost_disposition = '' AND " . $g[0]);
    if ($ok && !$REPORT) { $gh += $conn->affected_rows; }
}
$q = $conn->query("SELECT ghost_disposition d, COUNT(*) n FROM repair01_target_gaps GROUP BY d ORDER BY n DESC");
$ghOpen = 0;
while ($q && ($x = $q->fetch_assoc())) {
    if ($x['d'] === '') { $ghOpen = (int) $x['n']; }
    printf("  %-18s %d\n", ($x['d'] === '' ? '(يُرفع للمالك)' : $x['d']), $x['n']);
}

/* ═══ ③ سدُّ اليُتم (البند 18) ═════════════════════════════════════════════
     ◆ **المساحةُ المشتركةُ تُعلَن ولا تُنسَب إلى إدارة** — فنسبتُها إلى إدارةٍ
       تخترع ملكيّةً، وتركُها فارغةً يُبقيها يتيمة. والإعلانُ هو الحلُّ الثالث. */
echo "\n③ سدُّ اليُتم — لا سطحَ بلا حكمِ ملكيّة\n";
$ok = $W("UPDATE repair01_screen_registry
             SET owner_code = 'PLATFORM',
                 verdict_rule = CONCAT(verdict_rule, ' | البند 18: قدرة منصة مشتركة لا ادارة')
           WHERE COALESCE(owner_code,'') = '' AND on_disk = 1
             AND ownership_verdict = 'PLATFORM_SHARED'");
$plat = ($ok && !$REPORT) ? $conn->affected_rows : 0;
printf("  أُعلن قدرةَ منصّةٍ: %d\n", $plat);
$orph = $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                       WHERE COALESCE(owner_code,'') = '' AND on_disk = 1");
$orph = $orph ? (int) $orph->fetch_row()[0] : -1;
printf("  يتيمٌ باقٍ يُرفع للمالك: %d\n", $orph);

/* ═══ ④ مالكُ اليتيمِ من مالكِ مجلَّدِه الغالب ═══════════════════════════════
     ◆ **إشارةٌ مقيسةٌ لا تخمين**: مجلَّدُ `Maintenance/` فيه ثلاثون سطحًا
       مالكُها `DEP-14` — فسطحٌ فيه بلا مالكٍ مالكُه هو، والقاعدةُ تُكتب.
     ⛔ **وشرطُ الغلبةِ صريح**: لا يُنسَب إلّا حيث يملك مجلَّدَه **مالكٌ واحدٌ
       بأغلبيّةٍ مطلقة** — فمجلَّدٌ متنازَعٌ يُرفع للمالكِ ولا يُحسم بأكثريّةٍ
       نسبيّةٍ تخترع ملكيّةً من فرقِ صوتَين. */
echo "\n④ مالكُ اليتيمِ من مجلَّدِه — بشرطِ الغلبةِ المطلقة\n";
$byDir = array();
$q = $conn->query("SELECT screen_file, owner_code FROM repair01_screen_registry
                    WHERE on_disk = 1 AND COALESCE(owner_code,'') <> '' AND owner_code <> 'PLATFORM'");
while ($q && ($x = $q->fetch_assoc())) {
    $d = strtolower(dirname((string) $x['screen_file']));
    if ($d === '.' || $d === '') { continue; }
    if (!isset($byDir[$d])) { $byDir[$d] = array(); }
    $o = $x['owner_code'];
    $byDir[$d][$o] = isset($byDir[$d][$o]) ? $byDir[$d][$o] + 1 : 1;
}
$fixed = 0; $left = array();
$q = $conn->query("SELECT screen_id, screen_file FROM repair01_screen_registry
                    WHERE COALESCE(owner_code,'') = '' AND on_disk = 1");
$orphRows = array();
while ($q && ($x = $q->fetch_assoc())) { $orphRows[] = $x; }
foreach ($orphRows as $x) {
    $d = strtolower(dirname((string) $x['screen_file']));
    if (!isset($byDir[$d])) { $left[] = $x['screen_file'] . ' (‏مجلد بلا مالك)'; continue; }
    $tot = array_sum($byDir[$d]);
    arsort($byDir[$d]);
    $top = key($byDir[$d]); $cnt = current($byDir[$d]);
    if ($tot < 3 || $cnt * 2 <= $tot) { $left[] = $x['screen_file'] . " (‏مجلد متنازع: $top ‏$cnt/$tot)"; continue; }
    $why = "البند 18 — مالك مجلد $d بغلبة مطلقة $cnt من $tot";
    if ($W("UPDATE repair01_screen_registry SET owner_code = '" . $e($top) . "',
              verdict_rule = CONCAT(verdict_rule, ' | " . $e($why) . "')
            WHERE screen_id = '" . $e($x['screen_id']) . "'")) { $fixed++; }
}
printf("  نُسب بغلبةٍ مطلقة: %d · يبقى للمالك: %d\n", $fixed, count($left));
foreach (array_slice($left, 0, 10) as $l) { echo "     · $l\n"; }
if (count($left) > 10) { printf("     … و%d غيرُها\n", count($left) - 10); }
$orph = $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                       WHERE COALESCE(owner_code,'') = '' AND on_disk = 1");
$orph = $orph ? (int) $orph->fetch_row()[0] : -1;

/* ═══ ⑤ الحصيلة ═══════════════════════════════════════════════════════════ */
echo "\n────────────────────────────────────────────────────────────\n";
if ($REPORT) { echo "وضعُ التقرير — لم يُكتب شيء.\n"; exit(0); }
printf("دَينٌ مُصنَّف %d · شبحٌ محكومٌ %d · منصّةٌ %d\n", $fin, $gh, $plat);
printf("**يُرفع للمالك: %d شبحًا · %d سطحًا يتيمًا**\n", $ghOpen, $orph);
echo "الخطوةُ التالية: php tools/repair01_w135_scan.php\n";
