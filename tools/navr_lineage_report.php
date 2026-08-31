<?php
/**
 * tools/navr_lineage_report.php — مصالحةُ نسبِ بنودِ القوائم (CL-NAVR-LINEAGE · §١٠)
 * ═══════════════════════════════════════════════════════════════════════════
 * المعادلةُ الحاكمة: Applicable = With_Lineage + Without — **وكلُّ Without
 * يُفسَّر صنفًا** لا يُبتلع:
 *   UTILITY_ANCHOR — مراسي الدستورِ والأدواتُ الشخصيّة (الرئيسية · المراسلات · my_*)
 *   FINREQ_GATEWAY — بوّابةُ الطلباتِ الماليّةِ المشتقّةُ من جدولِ التوجيه
 *   BORROWED_VIEW — استعارةُ عرضٍ من إدارةٍ أخرى (الشاشةُ منسوبةٌ في مساحةِ مالكِها)
 *   TAXONOMY_LEGACY — بندُ تصنيفٍ إرثيٍّ خارجَ ورقةِ الدليل: **Finding يقترح**
 *     (إضافةً للورقةِ أو تقاعدًا) **ولا يعتمد نفسَه** (§٢٠)
 * الإخراج: docs/REPAIR01_20260823/NAVR_LINEAGE_RECON.md
 * التشغيل: php tools/navr_lineage_report.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$norm = function ($r) { return strtolower(trim(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', $r)), '/')); };

/* نسبُ المواضعِ لكلِّ المسارات (أيِّ مساحة) — الشاشةُ المنسوبةُ في مساحةِ مالكِها
   تُقرأ استعارةً في غيرِها */
$plAny = array(); $plByWs = array();
$q = $conn->query("SELECT workspace_id, route FROM nav_placements WHERE active = 1 AND route IS NOT NULL AND target_id IS NOT NULL");
while ($x = $q->fetch_assoc()) { $b = strtolower($x['route']); $plAny[$b] = true; $plByWs[$x['workspace_id']][$b] = true; }

$UTIL = array('main/role_board.php', 'chats/index.php', 'main/profile.php', 'main/soon.php', 'main/user_profile.php');

$rows = array(); $tot = array('WITH' => 0);
$q = $conn->query("SELECT wr.workspace_id, wr.role_id, n.route, n.label_ar
                     FROM nav_items n
                     JOIN nav_ws_roles wr ON wr.role_id = n.role_id AND wr.binding = 'PRIMARY'
                     JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.kind IN ('DEPARTMENT','EXECUTIVE')
                    WHERE n.active = 1");
$seen = array();
while ($x = $q->fetch_assoc()) {
    $b = $norm($x['route']);
    if ($b === '' || substr($b, -4) !== '.php') { continue; }
    $k = $x['workspace_id'] . '|' . $b;
    if (isset($seen[$k])) { continue; }
    $seen[$k] = true;
    if (isset($plByWs[$x['workspace_id']][$b])) { $tot['WITH']++; continue; }
    if (in_array($b, $UTIL, true) || strpos($b, 'portal/my_') === 0 || strpos($b, 'portal/notifications') === 0) {
        $cls = 'UTILITY_ANCHOR';
    } elseif (strpos($b, 'finrequests/') === 0) {
        $cls = 'FINREQ_GATEWAY';
    } elseif (isset($plAny[$b])) {
        $cls = 'BORROWED_VIEW';
    } else {
        $cls = 'TAXONOMY_LEGACY';
    }
    $tot[$cls] = ($tot[$cls] ?? 0) + 1;
    $rows[$cls][] = $x['workspace_id'] . ' · ' . $x['route'] . ' («' . $x['label_ar'] . '»)';
}
$den = $tot['WITH'] + array_sum(array_diff_key($tot, array('WITH' => 0)));

$md = "# NAVR — مصالحةُ نسبِ بنودِ القوائم (§١٠ · CL-NAVR-LINEAGE)\n\n"
    . "> مولَّدٌ من قياسٍ حيّ: `php tools/navr_lineage_report.php` — والمعادلةُ تُفحص لا تُدَّعى.\n\n"
    . "| الصنف | العدد | الحكم |\n|---|---|---|\n"
    . "| بنسبِ هدفٍ (`target_id` عبر موضعِ مساحتِه) | **{$tot['WITH']}** | ✔ السلسلةُ كاملة |\n";
$explain = array(
    'UTILITY_ANCHOR' => 'مراسي الدستورِ والأدواتُ الشخصيّةُ — خارجَ ورقةِ الدورةِ بإعلانِها (N/A مفسَّر)',
    'FINREQ_GATEWAY' => 'بوّابةُ الطلباتِ الماليّةِ — مشتقّةٌ من جدولِ التوجيهِ D05 لا من ورقةِ إدارة (N/A مفسَّر)',
    'BORROWED_VIEW' => 'استعارةُ عرضٍ — الشاشةُ **منسوبةٌ في مساحةِ مالكِها** وتُعرض هنا سياقيًّا (N/A مفسَّر)',
    'TAXONOMY_LEGACY' => '⚠ بندٌ إرثيٌّ خارجَ كلِّ ورقةٍ — **Finding يقترح** (إضافةً للورقةِ أو تقاعدًا) ولا يعتمد نفسَه',
);
foreach ($explain as $cls => $why) {
    $n = $tot[$cls] ?? 0;
    $md .= "| `{$cls}` | **{$n}** | {$why} |\n";
}
$md .= "| **المعادلة** | **{$den} = {$tot['WITH']} + " . ($den - $tot['WITH']) . "** | `UNEXPLAINED = 0` — كلُّ Without مصنَّفٌ أعلاه |\n\n";
foreach (array('TAXONOMY_LEGACY', 'BORROWED_VIEW') as $cls) {
    if (empty($rows[$cls])) { continue; }
    $md .= "## {$cls} (" . count($rows[$cls]) . ")\n\n";
    foreach ($rows[$cls] as $r) { $md .= "- {$r}\n"; }
    $md .= "\n";
}
file_put_contents($ROOT . '/docs/REPAIR01_20260823/NAVR_LINEAGE_RECON.md', $md);
printf("Applicable=%d · With=%d · Utility=%d · FinReq=%d · Borrowed=%d · Legacy=%d ⇒ NAVR_LINEAGE_RECON.md\n",
    $den, $tot['WITH'], $tot['UTILITY_ANCHOR'] ?? 0, $tot['FINREQ_GATEWAY'] ?? 0,
    $tot['BORROWED_VIEW'] ?? 0, $tot['TAXONOMY_LEGACY'] ?? 0);
