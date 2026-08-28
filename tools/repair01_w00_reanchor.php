<?php
/**
 * tools/repair01_w00_reanchor.php — إعادةُ ترسيةِ مقامٍ **حدثًا موثَّقًا لا تحريرَ شيفرة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا أداةٌ لا `UPDATE` يدويّ**: المرساةُ إنّما نُقلت من الشيفرةِ إلى
 *   المخزنِ لتصير **مراجَعةً**. فلو نُقلت قيمتُها بيدٍ عارية عادت إلى ما كانت
 *   عليه: رقمًا يتغيّر بلا أثر. ⇒ كلُّ نقلةٍ هنا **تكتب صفَّها في الدفتر**
 *   بالقيمةِ القديمةِ والجديدةِ وما قِيس حيًّا وسببِها ومرجعِ حزمتِها.
 *
 * ⛔ **ولا تُقبَل نقلةٌ بلا سببٍ ومرجع** — والقاعدةُ نفسُها تردُّ الفارغ
 *   (`chk_w00_log_reason`)، فالمنعُ في مكانَين لا في الأداةِ وحدَها.
 *
 * ⚠ **وتُعلِن التطابقَ ولا تُخفيه**: إن كانت القيمةُ المطلوبةُ تخالف المقيسَ
 *   حيًّا طُبع التحذيرُ وسُجِّل المقيسُ في الدفتر — **فمن يُرسي مقامًا لا يقيسه
 *   يرسي دعوى**.
 *
 * التشغيل:
 *   php tools/repair01_w00_reanchor.php --list
 *   php tools/repair01_w00_reanchor.php --metric=X --value=N --package="…" --why="…" [--apply]
 *   php tools/repair01_w00_reanchor.php --add=X --label="…" --sql="…" --value=N --package="…" --src="…" --why="…" [--apply]
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
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$arg = function ($k) use ($argv) {
    foreach ($argv as $a) { if (strpos($a, "--$k=") === 0) { return substr($a, strlen($k) + 3); } }
    return null;
};
$APPLY = in_array('--apply', $argv, true);

if (in_array('--list', $argv, true)) {
    $r = $conn->query("SELECT metric, label_ar, anchor_value, measure_sql, package_ref
                         FROM repair01_w00_anchor ORDER BY metric");
    printf("\n%-22s %-6s %-8s %s\n", 'المقام', 'المرسى', 'المقيس', 'الحزمة');
    echo str_repeat('─', 100) . "\n";
    while ($x = $r->fetch_assoc()) {
        $q = @$conn->query($x['measure_sql']);
        $m = $q ? (int) $q->fetch_row()[0] : null;
        printf("%-22s %-6d %-8s %s%s\n", $x['metric'], $x['anchor_value'],
            $m === null ? '؟' : $m, mb_substr($x['package_ref'], 0, 46),
            ($m !== null && $m !== (int) $x['anchor_value']) ? '   ⛔ انزياح' : '');
    }
    echo "\n";
    exit(0);
}

$why = (string) $arg('why');
$pkg = (string) $arg('package');
if (trim($why) === '' || trim($pkg) === '') {
    exit("⛔ **لا ترسيةَ بلا سببٍ مكتوبٍ ومرجعِ حزمة** — `--why=\"…\" --package=\"…\"`\n");
}
$NOW = date('Y-m-d H:i:s');
$BY  = 'repair01_w00_reanchor.php';

/* ── إضافةُ مقامٍ جديد ──────────────────────────────────────────────────── */
$add = $arg('add');
if ($add !== null) {
    $sql = (string) $arg('sql'); $label = (string) $arg('label'); $src = (string) $arg('src');
    $val = $arg('value');
    if (trim($sql) === '' || trim($label) === '' || trim($src) === '' || $val === null) {
        exit("⛔ المقامُ الجديدُ يحتاج `--label` و`--sql` و`--src` و`--value`\n");
    }
    $q = @$conn->query($sql);
    if (!$q) { exit("✘ استعلامُ القياسِ لا يعمل: {$conn->error}\n"); }
    $m = (int) $q->fetch_row()[0];
    printf("◆ %s — المطلوب %d · والمقيسُ حيًّا %d%s\n", $add, (int) $val, $m,
        ($m !== (int) $val) ? '   ⛔ **مختلفان**' : '   ✔ متطابقان');
    if (!$APPLY) { exit("\n◆ تجربةٌ بلا كتابة — أضِفْ `--apply`\n"); }
    $ok = $conn->query("INSERT INTO repair01_w00_anchor
        (metric,label_ar,measure_sql,anchor_value,package_ref,src_ref,why,anchored_at,anchored_by)
        VALUES ('" . $e($add) . "','" . $e($label) . "','" . $e($sql) . "'," . (int) $val . ",
                '" . $e($pkg) . "','" . $e($src) . "','" . $e($why) . "','" . $e($NOW) . "','" . $e($BY) . "')");
    if (!$ok) { exit("✘ {$conn->error}\n"); }
    $conn->query("INSERT INTO repair01_w00_anchor_log
        (metric,value_before,value_after,measured_now,package_ref,why,moved_at,moved_by)
        VALUES ('" . $e($add) . "',NULL," . (int) $val . "," . $m . ",'" . $e($pkg) . "',
                '" . $e($why) . "','" . $e($NOW) . "','" . $e($BY) . "')");
    exit("✔ رُسي مقامٌ جديد: $add = " . (int) $val . "\n");
}

/* ── نقلُ مقامٍ قائم ────────────────────────────────────────────────────── */
$metric = (string) $arg('metric'); $val = $arg('value');
if (trim($metric) === '' || $val === null) { exit("⛔ يلزم `--metric` و`--value`\n"); }
$r = $conn->query("SELECT * FROM repair01_w00_anchor WHERE metric='" . $e($metric) . "'");
if (!$r || !$r->num_rows) { exit("⛔ مقامٌ غيرُ مرسًى: $metric — استعملْ `--add=`\n"); }
$row = $r->fetch_assoc();
$q = @$conn->query($row['measure_sql']);
$m = $q ? (int) $q->fetch_row()[0] : null;
printf("◆ %s — المرسى %d ⇐ المطلوب %d · والمقيسُ حيًّا %s%s\n",
    $metric, (int) $row['anchor_value'], (int) $val, $m === null ? '؟' : $m,
    ($m !== null && $m !== (int) $val) ? '   ⛔ **المطلوبُ يخالف المقيس**' : '   ✔ متطابقان');
if (!$APPLY) { exit("\n◆ تجربةٌ بلا كتابة — أضِفْ `--apply`\n"); }
$ok = $conn->query("UPDATE repair01_w00_anchor
      SET anchor_value=" . (int) $val . ", package_ref='" . $e($pkg) . "',
          why='" . $e($why) . "', anchored_at='" . $e($NOW) . "', anchored_by='" . $e($BY) . "'
    WHERE metric='" . $e($metric) . "'");
if (!$ok) { exit("✘ {$conn->error}\n"); }
$conn->query("INSERT INTO repair01_w00_anchor_log
    (metric,value_before,value_after,measured_now,package_ref,why,moved_at,moved_by)
    VALUES ('" . $e($metric) . "'," . (int) $row['anchor_value'] . "," . (int) $val . ",
            " . ($m === null ? 'NULL' : $m) . ",'" . $e($pkg) . "','" . $e($why) . "',
            '" . $e($NOW) . "','" . $e($BY) . "')");
printf("✔ نُقلت المرساة: %s  %d ⇐ %d — وقُيِّدت في الدفتر\n", $metric, (int) $row['anchor_value'], (int) $val);
