<?php
/**
 * tools/repair01_edc_backfill.php — ردمُ المسمّى وسياسةِ الصلاحيةِ للقديم
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك · البند 50**: `Missing Canonical Labels` و`Missing Permissions`
 * بندانِ من خمسةَ عشرَ تُغلق في `Enterprise Debt Closure`.
 *
 * ◆ **والعمودانِ أُنشئا في W13.5 ولم يُملآ للقديم** — فـ579 سطحًا «بلا مسمًّى»
 *   ليست بلا اسمٍ في النظام، بل **بلا اسمٍ في العمودِ الجديد**. والاسمُ موجودٌ
 *   في `nav_canonical` و`gov_screen_cycle` و`nav_items` و`modules`.
 *   ⛔ **فهذا ردمٌ من مصدرٍ قائمٍ لا اختراعُ أسماء.**
 *
 * ◆ **وترجيحُ المصدرِ يُكتب في `verdict_rule`** — فيُعرَف لكلِّ سطحٍ **من أين
 *   جاء اسمُه**، ويُراجَع بمصدرِه لا بالحدس. (‏قاعدةُ «الاسمُ أربعةُ مصادرَ»)
 *
 * ◆ **وسياسةُ الصلاحيةِ تُشتقُّ من الواقعِ الحيّ**: أيُّ الأدوارِ تملك الشاشةَ
 *   فعلًا في `role_permissions`. ⛔ **ولا تُخترَع سياسةٌ لسطحٍ لا يراه أحد** —
 *   فذاك يُعلَن `NO_GRANT` ويبقى دَينًا مرئيًّا.
 *
 * التشغيل: php tools/repair01_edc_backfill.php [--report]
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
$n = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };

echo "\n═══ ردمُ المسمّى وسياسةِ الصلاحية — البند 50 ═══\n";
echo ($REPORT ? "  وضعُ التقرير: يقرأ ولا يكتب\n\n" : "\n");

/* ═══ ① المسمّى — أربعةُ مصادرَ بترجيحٍ مكتوب ══════════════════════════════ */
echo "① المسمّى المعياريّ\n";
$LBL = array(); $SRC = array();
$put = function ($file, $name, $src) use (&$LBL, &$SRC) {
    $k = strtolower(basename((string) $file));
    $name = trim(preg_replace('~\s*\([^)]*\.php\)\s*$~u', '', (string) $name));
    $name = trim(preg_replace('~^\s*[^|]{0,20}\|\s*~u', '', $name));
    if ($k === '' || $name === '') { return; }
    if (isset($LBL[$k])) { return; }
    $LBL[$k] = $name; $SRC[$k] = $src;
};
/* الترجيحُ من الأقوى إلى الأضعف — والمعتمَدُ يغلب غيرَ المعتمَد */
$q = $conn->query("SELECT route, canonical_ar FROM nav_canonical
                    WHERE status = 'APPROVED' AND canonical_ar <> ''");
while ($q && ($x = $q->fetch_assoc())) { $put($x['route'], $x['canonical_ar'], 'nav_canonical APPROVED'); }
$q = $conn->query("SELECT route, canonical_ar FROM nav_canonical WHERE canonical_ar <> ''");
while ($q && ($x = $q->fetch_assoc())) { $put($x['route'], $x['canonical_ar'], 'nav_canonical'); }
$q = $conn->query("SELECT screen_file, screen_title FROM gov_screen_cycle WHERE screen_title <> ''");
while ($q && ($x = $q->fetch_assoc())) { $put($x['screen_file'], $x['screen_title'], 'gov_screen_cycle'); }
$q = $conn->query("SELECT route, label_ar FROM nav_items WHERE label_ar <> ''");
while ($q && ($x = $q->fetch_assoc())) { $put($x['route'], $x['label_ar'], 'nav_items'); }
$q = $conn->query("SELECT code, name FROM modules WHERE name <> ''");
while ($q && ($x = $q->fetch_assoc())) { $put($x['code'], $x['name'], 'modules'); }

$need = array();
$q = $conn->query("SELECT screen_id, screen_file FROM repair01_screen_registry
                    WHERE COALESCE(canonical_label_ar,'') = '' AND on_disk = 1
                      AND ownership_verdict <> 'RETIRE'");
while ($q && ($x = $q->fetch_assoc())) { $need[] = $x; }
$done = 0; $miss = array(); $bySrc = array();
foreach ($need as $x) {
    $k = strtolower(basename((string) $x['screen_file']));
    if (!isset($LBL[$k])) { $miss[] = $x['screen_file']; continue; }
    $bySrc[$SRC[$k]] = isset($bySrc[$SRC[$k]]) ? $bySrc[$SRC[$k]] + 1 : 1;
    if ($REPORT) { $done++; continue; }
    if ($conn->query("UPDATE repair01_screen_registry
                         SET canonical_label_ar = '" . $e($LBL[$k]) . "',
                             verdict_rule = CONCAT(verdict_rule, ' | مسمى من " . $e($SRC[$k]) . "')
                       WHERE screen_id = '" . $e($x['screen_id']) . "'")) { $done++; }
}
printf("  المقام %d · رُدم %d · بلا مصدرٍ %d\n", count($need), $done, count($miss));
foreach ($bySrc as $s => $c) { printf("     من %-24s %d\n", $s, $c); }
if ($miss) {
    echo "     ◆ بلا اسمٍ في أيِّ سجلّ (يُرفع للمالك):\n";
    foreach (array_slice($miss, 0, 8) as $m) { echo "        · $m\n"; }
    if (count($miss) > 8) { printf("        … و%d غيرُها\n", count($miss) - 8); }
}

/* ═══ ② سياسةُ الصلاحية — من المنحِ الحيِّ لا من التقدير ══════════════════ */
echo "\n② سياسةُ الصلاحية\n";
/* ◆ **السياسةُ اسمُ قاعدةٍ لا قائمةُ أدوار**: الأدوارُ تتغيّر والسياسةُ تبقى.
     فتُسمّى بمصدرِ الحكمِ ومداه: مالكُ النطاقِ · مشتركٌ · لوحةٌ · إلخ. */
$rows = array();
$q = $conn->query("SELECT sr.screen_id, sr.screen_file, sr.owner_code, sr.ownership_verdict, sr.surface_kind,
                          (SELECT COUNT(DISTINCT rp.role_id) FROM role_permissions rp
                             JOIN modules m ON m.id = rp.module_id
                            WHERE rp.can_view = 1 AND m.code LIKE CONCAT('%', sr.screen_file)) roles
                     FROM repair01_screen_registry sr
                    WHERE COALESCE(sr.permission_policy,'') = '' AND sr.on_disk = 1
                      AND sr.ownership_verdict <> 'RETIRE'");
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }
$p2 = 0; $tally = array();
foreach ($rows as $x) {
    $roles = (int) $x['roles'];
    $v = (string) $x['ownership_verdict'];
    if ($roles === 0)                        { $pol = 'NO_GRANT_DECLARED'; }
    elseif ($v === 'PLATFORM_SHARED')        { $pol = 'PLATFORM_ALL_AUTHENTICATED'; }
    elseif ($v === 'EXECUTIVE_PROJECTION')   { $pol = 'EXECUTIVE_SCOPE_READ'; }
    elseif ($v === 'AUDIT_ASSURANCE')        { $pol = 'ASSURANCE_READ_BY_ENGAGEMENT'; }
    elseif ($v === 'TAB_CHILD')              { $pol = 'INHERIT_FROM_PARENT'; }
    elseif ((string) $x['surface_kind'] === 'PROJECTION') { $pol = 'DOMAIN_PROJECTION_READ'; }
    elseif ($roles === 1)                    { $pol = 'DOMAIN_OWNER_ONLY'; }
    else                                     { $pol = 'DOMAIN_OWNER_PLUS_GRANTED'; }
    $tally[$pol] = isset($tally[$pol]) ? $tally[$pol] + 1 : 1;
    if ($REPORT) { $p2++; continue; }
    if ($conn->query("UPDATE repair01_screen_registry
                         SET permission_policy = '" . $e($pol) . "',
                             verdict_rule = CONCAT(verdict_rule, ' | سياسة من المنح الحي: " . (int) $roles . " دورا')
                       WHERE screen_id = '" . $e($x['screen_id']) . "'")) { $p2++; }
}
printf("  المقام %d · كُتب %d\n", count($rows), $p2);
arsort($tally);
foreach ($tally as $k => $c) { printf("     %-32s %d\n", $k, $c); }

/* ═══ ③ الحصيلة — تُقاس بعدَ الكتابةِ لا تُفترَض ══════════════════════════ */
echo "\n────────────────────────────────────────────────────────────\n";
if ($REPORT) { echo "وضعُ التقرير — لم يُكتب شيء.\n"; exit(0); }
printf("مسمًّى باقٍ ناقصًا: %d · سياسةٌ باقيةٌ ناقصةً: %d\n",
    $n("SELECT COUNT(*) FROM repair01_screen_registry
        WHERE COALESCE(canonical_label_ar,'') = '' AND on_disk = 1 AND ownership_verdict <> 'RETIRE'"),
    $n("SELECT COUNT(*) FROM repair01_screen_registry
        WHERE COALESCE(permission_policy,'') = '' AND on_disk = 1 AND ownership_verdict <> 'RETIRE'"));
echo "◆ و`NO_GRANT_DECLARED` **دَينٌ مرئيٌّ لا إغلاق** — سطحٌ لا يراه دورٌ واحد.\n";
echo "الخطوةُ التالية: php tools/repair01_edc_scan.php\n";
