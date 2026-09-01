<?php
/**
 * tools/govui_metrics.php — المقاييسُ الاثنا عشرَ المطلوبةُ نصًّا (§19)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بنصِّ الأمر**: «مقاييسُ الاغلاق منفصلة — **لا نسبة واحدة اسمها مطابقة
 *   السايدبار**… وكلُّ مقياسٍ **ببسطِه ومقامِه واستبعادِه**». فكلُّ صفٍّ هنا
 *   يحمل الثلاثةَ، ⛔ ولا تُجمع في رقمٍ واحد.
 *
 * ◆ **والجديدان لم يُقاسا قطُّ**:
 *   · `LABEL_CONFORMANCE` — من `govui_label_measure.php` (تصييرٌ حيٌّ لا مخزن).
 *   · `GRAIN_CONFORMANCE` — الحبّةُ المُعلَنةُ في الدليلِ مقابلَ **الحبّةِ المقيسةِ
 *     من الأثر** (`repair01_screen_registry.grain_entity`/`grain_multi`).
 *     والحكمُ شرطان مقيسان لا رأيٌ: ① للسطحِ حبّةٌ مقيسةٌ بشاهدِها ·
 *     ② ولا يجمع حبّتَين — و«السطرُ الواحد» في بطاقةِ الدليلِ كيانٌ واحد.
 *
 * ◆ **والاستبعاداتُ تُسمّى ولا تُبتلع** (§19): مساحةٌ بلا دورٍ حيٍّ · هدفٌ لم
 *   يُبنَ · ابنُ تبويبٍ يُقاس في صفحتِه لا في القائمة.
 *
 * التشغيل: php tools/govui_metrics.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/govui_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);
$one = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : 0; };

$M = json_decode(@file_get_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_LABEL_MEASURE.json'), true);
if (!$M) { exit("⛔ شغّلْ tools/govui_label_measure.php أوّلًا\n"); }
$navr = @file_get_contents($ROOT . '/docs/REPAIR01_20260823/NAVR_METRICS.md');
$grab = function ($key) use ($navr) {
    if (preg_match('~`' . preg_quote($key, '~') . '`\s*\|\s*\*\*([^*]+)\*\*~u', (string) $navr, $m)) { return trim($m[1]); }
    return '—';
};

$rows = array();   /* [رمز, بسط, مقام, استبعاد, أداة] */

/* ① TARGET_BUILD_COVERAGE */
$built = $one("SELECT COUNT(*) FROM nav_placements WHERE route IS NOT NULL AND route <> ''");
$allT  = $one("SELECT COUNT(*) FROM nav_targets");
$rows[] = array('TARGET_BUILD_COVERAGE', $built, $allT,
    'لا استبعاد — كونُ الأهدافِ كلُّه من الملفَّين', 'nav_placements × nav_targets');

/* ② MENU_TARGET_COVERAGE — بندُ القائمةِ وصفحةُ الهبوطِ وحدَهما يُطالَبان ببندٍ مُصيَّر */
$menuT = $one("SELECT COUNT(*) FROM govui_target_registry WHERE surface_type IN ('MENU_ITEM','LANDING_PAGE')");
$menuB = $one("SELECT COUNT(*) FROM govui_target_registry g JOIN nav_placements p ON p.target_id = g.target_id
               WHERE g.surface_type IN ('MENU_ITEM','LANDING_PAGE') AND p.route IS NOT NULL AND p.route <> ''");
$rows[] = array('MENU_TARGET_COVERAGE', $menuB, $menuT,
    'خارجَ المقام: TAB_CHILD وPROJECTION وDIRECT_ONLY وUTILITY وNOT_BUILT (§8)', 'govui_target_registry');

/* ③ BUILT_NOT_RENDERED */
$bnr = 0; foreach ($M['rows'] as $r) { if ($r['verdict'] === 'NOT_RENDERED') { $bnr++; } }
$rows[] = array('BUILT_NOT_RENDERED', $bnr, 0,
    'مبنيٌّ بموضعِه ولا بندَ في سايدبارِ دورِه — الهدف 0', 'govui_label_measure (تصييرٌ حيّ)');

/* ④⑤ GROUP / ORDER — من لوحةِ NAVR الحيّة (المُصيَّرُ مقابلَ الدليل) */
$rows[] = array('GROUP_CONFORMANCE', $grab('GROUP_CONFORMANCE'), '',
    'المُصيَّرُ في مجموعةِ دليلِه — المقامُ في اللوحة', 'sidebar_guide_compare');
$rows[] = array('ORDER_CONFORMANCE', $grab('ORDER_CONFORMANCE'), '',
    'المُصيَّرُ في ترتيبِ دليلِه (LCS)', 'sidebar_guide_compare');

/* ⑥ LABEL_CONFORMANCE */
$ex = $M['excluded'];
$rows[] = array('LABEL_CONFORMANCE', $M['numerator'], $M['denominator'],
    'غيرُ مبنيٍّ ' . $ex['NOT_BUILT'] . ' · بلا دورٍ حيٍّ ' . $ex['NO_LIVE_ROLE']
    . ' · ابنُ تبويبٍ يُقاس في صفحتِه ' . ($ex['TAB_SURFACE'] + $ex['TAB_ON_PARENT']), 'govui_label_measure');

/* ⑦ ROLE_VISIBILITY_CONFORMANCE */
$rows[] = array('ROLE_VISIBILITY_CONFORMANCE', $grab('ROLE_VISIBILITY_CONFORMANCE'), '',
    'موضعُ قائمةٍ مبنيٌّ لدورِ مساحتِه صلاحيةُ عرضِه قائمة', 'sidebar_guide_compare');

/* ⑧ FIELD_CONFORMANCE */
$fn = $one("SELECT COALESCE(SUM(matched),0) FROM repair01_field_measure");
$fd = $one("SELECT COALESCE(SUM(design_applicable),0) FROM repair01_field_measure");
$fa = $one("SELECT COALESCE(SUM(design_total - design_applicable),0) FROM repair01_field_measure");
$fs = $one("SELECT COUNT(*) FROM repair01_field_measure");
$rows[] = array('FIELD_CONFORMANCE', $fn, $fd,
    'AUDIT إلحاقيّةٌ خارجَ المقام ' . $fa . ' حقلًا (§7·11) · المقيسُ ' . $fs . ' سطحًا مطابَقًا',
    'rpr02_field_measure (الدفترُ مُسوًّى على الحزمةِ الجديدة)');

/* ⑨ GRAIN_CONFORMANCE — الجديدُ الأوّل */
$gd = $one("SELECT COUNT(*) FROM govui_target_registry g
             JOIN repair01_screen_registry s ON s.screen_id = g.target_screen_id
            WHERE g.target_screen_id <> '' AND g.grain <> ''");
$gn = $one("SELECT COUNT(*) FROM govui_target_registry g
             JOIN repair01_screen_registry s ON s.screen_id = g.target_screen_id
            WHERE g.target_screen_id <> '' AND g.grain <> ''
              AND s.grain_entity <> '' AND s.grain_multi = 0");
$gMulti = $one("SELECT COUNT(*) FROM govui_target_registry g
                 JOIN repair01_screen_registry s ON s.screen_id = g.target_screen_id
                WHERE g.target_screen_id <> '' AND g.grain <> '' AND s.grain_multi = 1");
$gNone = $gd - $gn - $gMulti;
$rows[] = array('GRAIN_CONFORMANCE', $gn, $gd,
    'يجمع حبّتَين ' . $gMulti . ' · بلا حبّةٍ مقيسةٍ ' . $gNone
    . ' · وغيرُ المبنيِّ خارجَ المقام', 'govui_target_registry × rpr02_grain_measure');

/* ⓪ STATE_MODEL_CONFORMANCE — والمقامُ **المطالَبُ** لا المبنيُّ كلُّه
   ◆ «المطلوب» مقيسٌ لا مفترَض: سطحٌ حبّتُه سطرٌ أو بندٌ من حقيقةِ أعمالٍ
     يملكُها (`grain_cardinality IN (ROW,LINE)` و`grain_fact_scope = OWN_FACT`) —
     وهو مدى `rpr02_state_model_bind` حرفًا فلا مقامانِ لسؤالٍ واحد.
   ⛔ ومرجعٌ أجوفٌ أسوأُ من غيابِه — فالبسطُ مرجعٌ مؤلَّفٌ لا خانةٌ ملأى. */
$SMSCOPE = "repair01_screen_registry WHERE on_disk = 1 AND ownership_verdict <> 'RETIRE'
            AND grain_cardinality IN ('ROW','LINE') AND grain_fact_scope = 'OWN_FACT'";
$sd = $one("SELECT COUNT(*) FROM $SMSCOPE");
$sn = $one("SELECT COUNT(*) FROM $SMSCOPE AND state_model_ref <> ''");
$rows[] = array('STATE_MODEL_CONFORMANCE', $sn, $sd,
    'المقامُ أسطحُ المعاملاتِ الحقيقيّةُ وحدها (حبّةٌ سطرٌ/بندٌ وحقيقةٌ مملوكة) · والباقي بلا آلةٍ مؤلَّفةٍ — والتأليفُ حكمُ أعمالٍ لا قياس',
    'rpr02_state_model_bind (المدى نفسُه)');

/* ⑪ STRUCTURAL_DEPARTMENT_PASS */
$rows[] = array('STRUCTURAL_DEPARTMENT_PASS', $grab('STRUCTURAL_NAV_PASS'), '',
    'إداراتٌ بلغت المطابقةَ البنيويّةَ — وDEP-08 وEX-DVP بلا دورٍ حيٍّ خارجَ المقام',
    'sidebar_guide_compare');

/* ⑫ HUMAN_DEPARTMENT_PASS */
$rows[] = array('HUMAN_DEPARTMENT_PASS', $grab('HUMAN_NAV_PASS'), '',
    'التحقُّقُ البشريُّ بدورٍ حقيقيٍّ — بطاقاتُه جاهزةٌ والتوقيعُ بشريّ',
    'gov_exec_human_card');

$snap = 'BL-' . date('Ymd') . '-' . trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
printf("═ المقاييسُ الاثنا عشرَ · اللقطة %s ═\n", $snap);
foreach ($rows as $r) {
    $val = $r[2] === '' ? $r[1] : ($r[1] . '/' . $r[2]);
    printf("  %-32s %-12s %s\n", $r[0], $val, mb_substr($r[3], 0, 70));
}
if ($MD) {
    $md = "# `GOVUI_METRICS` — المقاييسُ الاثنا عشرَ المطلوبةُ نصًّا (§19)\n\n"
        . "> مولَّدةٌ حيًّا بـ`tools/govui_metrics.php` · اللقطة **{$snap}** · "
        . date('Y-m-d H:i') . "\n"
        . "> ⛔ **ولا تُجمع في نسبةٍ واحدة** — «لا نسبة واحدة اسمها مطابقة السايدبار» (§19).\n"
        . "> **وكلُّ مقياسٍ ببسطِه ومقامِه واستبعادِه.**\n\n"
        . "| المقياس | القيمة | الاستبعادُ والقراءة | الأداة |\n|---|---|---|---|\n";
    foreach ($rows as $r) {
        $val = $r[2] === '' ? $r[1] : ($r[1] . '/' . $r[2]);
        $md .= "| `{$r[0]}` | **{$val}** | {$r[3]} | `{$r[4]}` |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_METRICS.md', $md);
    echo "⇐ docs/REPAIR01_20260823/GOVUI_METRICS.md\n";
}
