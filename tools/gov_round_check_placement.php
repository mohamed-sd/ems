<?php
/**
 * tools/gov_round_check_placement.php — الموضعُ الحاكمُ لشاشةِ الفحص
 * ═══════════════════════════════════════════════════════════════════════════
 * **`nav_items` مصدرٌ إرثيٌّ احتياطيّ** ([[navr-placement-model]]): المُصيِّرُ
 * الحاكمُ في `includes/unified_nav.php` يقرأ **`nav_placements` ⋈ `nav_targets`
 * ⋈ `nav_ws_roles`** — و`nav_items` مصدرُ تفويضٍ فقط. ولذلك لم يظهر الرابطُ
 * رغمَ صفِّ `nav_items` النشطِ والمنحِ في القوالب.
 *
 * ◆ **فالبندُ الظاهرُ يحتاج حلقتَين أُخريَين**: هدفٌ في `nav_targets` (‏عنوانٌ
 *   معياريٌّ ومساحةٌ ومجموعة) وموضعٌ في `nav_placements` يشير إليه.
 *
 * التشغيل: php tools/gov_round_check_placement.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("⛔ اتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$ROUTE = 'governance/round_check.php';
$TITLE = 'فحص جاهزية الالتزام';
$WS    = 'DEP-08';
$GRP   = 1360;                 /* مجموعةُ المواضعِ الحاكمةِ لقسمِ الصلاحيات */
$SRC   = 'GOV-CHK-01 — شاشةُ فحصٍ تُسمّي الحارسَ الراسبَ بدل رسالةٍ عامّة';
$done  = 0;

/* معرِّفُ الشاشةِ من السجلِّ الرسميّ — لا يُخترع */
$q = $conn->query("SELECT screen_id FROM repair01_screen_registry WHERE LOWER(route)='{$e($ROUTE)}'");
if (!$q || !$q->num_rows) { exit("⛔ الشاشةُ غيرُ مسجَّلةٍ في السجلِّ الرسميّ\n"); }
$SCR = $q->fetch_row()[0];
echo "معرِّفُ الشاشة: {$SCR}\n";

/* ═══ ① الهدف ═══ */
$q = $conn->query("SELECT target_id FROM nav_targets WHERE workspace_id='{$e($WS)}' AND canonical_title='{$e($TITLE)}'");
if ($q && $q->num_rows) { $TID = $q->fetch_row()[0]; echo "① الهدف: قائمٌ {$TID}\n"; }
else {
    /* الترقيمُ يتبع عُرفَ المساحةِ: NT-<ws>-<nnn> */
    $q = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(target_id,'-',-1) AS UNSIGNED))
                         FROM nav_targets WHERE workspace_id='{$e($WS)}'");
    $nx  = (int) $q->fetch_row()[0] + 1;
    $TID = sprintf('NT-%s-%03d', $WS, $nx);
    $q = $conn->query("SELECT COALESCE(MAX(target_order),0)+1 FROM nav_targets WHERE workspace_id='{$e($WS)}'");
    $ord = (int) $q->fetch_row()[0];
    echo "① الهدف: يُنشأ {$TID}\n";
    if ($APPLY) {
        $ok = $conn->query("INSERT INTO nav_targets
            (target_id, source_doc, sheet_code, row_no, canonical_title, workspace_id,
             group_key, target_order, visibility_class, active)
            VALUES ('{$e($TID)}', '{$e($SRC)}', 'GOV-CHK', {$nx}, '{$e($TITLE)}', '{$e($WS)}',
             '{$GRP}', {$ord}, 'MENU_ITEM', 1)");
        if (!$ok) { exit("⛔ ①: {$conn->error}\n"); }
        $done++;
    }
}

/* ═══ ② الموضعُ الحاكم ═══ */
$q = $conn->query("SELECT id FROM nav_placements WHERE LOWER(route)='{$e($ROUTE)}'");
if ($q && $q->num_rows) { echo "② الموضعُ الحاكم: قائم\n"; }
else {
    echo "② الموضعُ الحاكم: يُنشأ في المجموعة {$GRP}\n";
    if ($APPLY) {
        $q = $conn->query("SELECT COALESCE(MAX(sort_no),0)+1 FROM nav_placements WHERE workspace_id='{$e($WS)}' AND group_id={$GRP}");
        $so = (int) $q->fetch_row()[0];
        $ref = $WS . '·' . $so . '·' . $TITLE;
        $ok = $conn->query("INSERT INTO nav_placements
            (workspace_id, screen_id, route, target_ref, target_id, group_id, sort_no,
             placement_type, source_ref, active)
            VALUES ('{$e($WS)}', '{$e($SCR)}', '{$e($ROUTE)}', '{$e($ref)}', '{$e($TID)}',
             {$GRP}, {$so}, 'MENU_ITEM', '{$e($SRC)}', 1)");
        if (!$ok) { exit("⛔ ②: {$conn->error}\n"); }
        $done++;
    }
}

printf("\nخطواتٌ نُفِّذت: %d\n", $done);
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }

/* ═══ التحقّقُ باستعلامِ المُصيِّرِ نفسِه — لا بعدِّ الصفوف ═══════════════
   ⛔ **والبرهانُ أن يراه القارئُ الحاكم**: صفٌّ في جدولٍ ليس ظهورًا. */
$sql = "SELECT LOWER(p.route) rt, t.canonical_title
          FROM nav_placements p
          JOIN nav_targets  t ON t.target_id = p.target_id
          JOIN nav_ws_roles w ON w.workspace_id = p.workspace_id AND w.binding = 'PRIMARY'
         WHERE w.role_id = 15 AND LOWER(p.route) = '{$e($ROUTE)}'";
$q = $conn->query($sql);
if ($q && $r = $q->fetch_assoc()) {
    printf("✔ المُصيِّرُ الحاكمُ يراه: «%s» ⇐ %s\n", $r['canonical_title'], $r['rt']);
    exit(0);
}
echo "✘ المُصيِّرُ لا يراه بعدُ — راجعْ nav_ws_roles للدور 15\n";
exit(1);
