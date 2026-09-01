<?php
/**
 * tools/navarch/metrics.php — مقاييسُ §26 و`EXACT_WORKSPACE_NAV_CONFORMANCE` (‏§25)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§24 يُعيد التسمية**: المقياسُ الذي يعطي 17/17 لأنّه يجد شاشاتِ الهدفِ فقط
 *   **لا يُسمّى بعدَ اليومِ `Exact`** بل `TARGET_NAV_RECALL` — سؤالُه: «هل كلُّ
 *   الهدفِ المطلوبِ موجود؟» وحدَه. و⛔ **اعتبارُ 17/17 دليلَ مطابقةٍ تامّةٍ محظورٌ
 *   بنصِّ §42** — لأنَّه لا يقول شيئًا عن الفائض.
 *
 * ◆ **و`EXACT_WORKSPACE_NAV_CONFORMANCE` الحقيقيُّ لا يمرُّ إلّا بتسعةِ أصفارٍ**
 *   (§25) — والدقّةُ هي الوجهُ الغائبُ عن الاسترجاع: `PLACEMENT_PRECISION`
 *   = ظهوراتٌ لها موضعٌ حاكم / كلُّ الظهورات.
 *
 * ◆ **والمقاييسُ تُقاس على الظلِّ لا على المخزن** [[render-not-store-rule]]:
 *   «بعد» = مخرَجُ `navarch_render()` حرفًا، و«قبل» = لقطةُ الأساس.
 *
 * التشغيل: php tools/navarch/metrics.php [--ws=DEP-11] [--gate]
 *   `--gate` ⇒ يرسُب إن لم يبلغ الطيّارُ `DEP-11` معاييرَ §40 الاثنَي عشرَ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
require_once $ROOT . '/includes/navarch_renderer.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$onlyWs = ''; $gate = in_array('--gate', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $onlyWs = substr($a, 5); } }

$OUT  = $ROOT . '/docs/REPAIR01_20260823/navarch';
$BL   = json_decode(file_get_contents($OUT . '/NAV_ARCH_BASELINE.json'), true);
$BLID = $BL['baseline_id'];
$nrm  = 'navarch_norm_route';

/* ═══ ① الهدفُ الحاكمُ لكلِّ مساحة — بندُ قائمةٍ في ورقةِ الدليل ═════════════ */
$target = array(); $tgtGroup = array(); $tgtOrder = array(); $tgtLabel = array();
/* ⛔ **والاسمُ الحاكمُ من `nav_targets.canonical_title` لا من `target_ref`**:
   الأخيرُ **مفتاحُ مطابقةٍ مُسوّى** («غرفه العمليات») لا اسمًا («غرفة العمليات»)،
   فقياسُ الاسمِ به يُنتج ثمانيَ مخالفاتٍ كلُّها همزةٌ وتاءٌ مربوطة —
   **رقمٌ يصف قارئَه لا مقروءَه** [[measure-blind-spots]] · [[nav-label-four-source-precedence]]. */
$r = $conn->query("SELECT p.workspace_id, p.route, p.sort_no, p.target_id, g.label_ar, g.sort_no gno,
                          t.canonical_title
                     FROM nav_placements p
                     LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                     LEFT JOIN nav_targets t ON t.target_id = p.target_id AND t.active = 1
                    WHERE p.active = 1 AND p.placement_type IN ('MENU_ITEM','LANDING_PAGE')
                      AND p.route IS NOT NULL AND p.route <> ''");
while ($x = $r->fetch_assoc()) {
    $k = $nrm($x['route']);
    $target[$x['workspace_id']][$k] = true;
    $tgtGroup[$x['workspace_id']][$k] = (string) $x['label_ar'];
    $tgtOrder[$x['workspace_id']][$k] = (int) $x['sort_no'];
    if ((string) $x['canonical_title'] !== '') {
        $tgtLabel[$x['workspace_id']][$k] = trim((string) $x['canonical_title']);
    }
}

/* ═══ ② سجلَّاتُ الحكمِ ══════════════════════════════════════════════════════ */
$legacyRuled = array(); $crossRuled = array();
$r = $conn->query("SELECT current_workspace w, current_route rt FROM nav_legacy_disposition");
while ($x = $r->fetch_assoc()) { $legacyRuled[$x['w'] . '|' . $x['rt']] = true; }
/* ⛔ **والقيمةُ لا تكون `NULL`**: `approved_by` فارغٌ في أغلبِ الصفوفِ،
   و`isset()` تُرجِع `false` على `NULL` — فقيست 40 صفًّا موجودًا «بلا حكم».
   العطبُ في **القارئِ** لا في البيانات [[measure-blind-spots]]. */
$r = $conn->query("SELECT consumer_workspace w, route rt, approved_by FROM nav_cross_domain_register");
while ($x = $r->fetch_assoc()) {
    $crossRuled[$x['w'] . '|' . $x['rt']] = ($x['approved_by'] === null ? '' : $x['approved_by']);
}

/* ═══ ②-ب **إضافةٌ معتمَدةٌ بقرارِ جولةٍ مكتوب** — مفردةُ التبريرِ الثالثةُ (§29) ═══
   ⛔ **ولا يكفي مصدرٌ واحد**: يلزم **اقترانُ** حكمِ المصالحةِ `APPROVED_POST_GUIDE_ADDITION`
   **مع** صفٍّ في جدولِ الإدارةِ المستهدَفِ يسمّي **مجموعةً من دورةِ هذه المساحةِ نفسِها**.
   فمجموعةُ مساحةٍ أخرى لا تُبرِّر ظهورًا هنا (§13)، وحكمٌ بلا نشرٍ لا يضع موضعًا. */
$approvedAddition = array();
$reconOk = array();
$r = $conn->query("SELECT route FROM gov_legacy_nav_recon
                    WHERE verdict = 'APPROVED_POST_GUIDE_ADDITION'");
while ($x = $r->fetch_assoc()) { $reconOk[$nrm($x['route'])] = true; }

$wsPrimRole = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding = 'PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsPrimRole[$x['workspace_id']] = (int) $x['role_id']; }

$nzm = function ($s) {
    $s = preg_replace('~[\x{064B}-\x{0652}\x{0640}]~u', '', (string) $s);
    $s = str_replace(array('أ','إ','آ','ى','ة','ؤ','ئ'), array('ا','ا','ا','ي','ه','و','ي'), $s);
    return trim(preg_replace('~\s+~u', ' ', $s));
};
$lcKey = array();
$r = $conn->query("SELECT workspace_id, group_key, label_ar FROM nav_lifecycle_groups WHERE active = 1");
while ($x = $r->fetch_assoc()) {
    $lcKey[$x['workspace_id']][$nzm($x['group_key'])] = true;
    $lcKey[$x['workspace_id']][$nzm($x['label_ar'])]  = true;
}
$r = $conn->query("SELECT role_id, route, group_ar FROM gov_target_nav
                    WHERE route IS NOT NULL AND route <> '' AND group_ar IS NOT NULL");
while ($x = $r->fetch_assoc()) {
    if (strncmp((string) $x['route'], 'GAP:', 4) === 0) { continue; }
    $k = $nrm($x['route']);
    if ($k === '' || !isset($reconOk[$k])) { continue; }
    foreach ($wsPrimRole as $w => $rid) {
        if ($rid !== (int) $x['role_id']) { continue; }
        if (isset($lcKey[$w][$nzm($x['group_ar'])])) { $approvedAddition[$w][$k] = true; }
    }
}

/* كلُّ موضعٍ حاكمٍ مكتوبٍ — سواءٌ صُيِّر أم لم يُصيَّر (‏قشرةً كان أو شخصيًّا أو تبويبًا) */
$placedAny = array();
$r = $conn->query("SELECT workspace_id w, route rt FROM nav_workspace_placements");
while ($x = $r->fetch_assoc()) { $placedAny[$x['w'] . '|' . $x['rt']] = true; }

$wsRole = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding='PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsRole[$x['workspace_id']] = (int) $x['role_id']; }

/* الطبقةُ الشخصيّةُ والقشرةُ — للتمييزِ في §26 (‏مقياسان مستقلّان) */
$personalRoutes = array(); $shellRoutes = array('main/role_board' => 1, 'chats/index' => 1);
$r = $conn->query("SELECT route FROM nav_placements WHERE active=1 AND workspace_id='WS-MY'");
while ($x = $r->fetch_assoc()) { $personalRoutes[$nrm($x['route'])] = true; }

/* ═══ ③ القياس ═════════════════════════════════════════════════════════════ */
/* ما تقلبه `.env` فعلًا اليومَ — حقيقةُ **نشرٍ** لا حقيقةَ مطابقة */
$deployFlag = function_exists('ems_env') ? trim((string) ems_env('EMS_NAV_ARCH', 'off')) : 'off';

$M = array(); $ALL = array();
foreach ($BL['snapshot'] as $ws => $s) {
    if ($s['rendered'] === null) { continue; }
    if ($onlyWs !== '' && $ws !== $onlyWs) { continue; }
    $rid = isset($wsRole[$ws]) ? $wsRole[$ws] : (int) $s['role_id'];
    $deployedWs = (strtolower($deployFlag) === 'on')
        || in_array($ws, array_map('trim', explode(',', $deployFlag)), true);

    $tg = isset($target[$ws]) ? $target[$ws] : array();
    $new = navarch_render($conn, $ws, $rid);

    /* المُصيَّرُ الجديدُ في دورةِ الإدارةِ وحدَها — القشرةُ والشخصيُّ خارجَه (§10·§11) */
    $lifecycle = array(); $lcGroup = array(); $lcOrder = array(); $lcLabel = array(); $secUnapproved = 0;
    foreach ($new['groups'] as $g) {
        $o = 0;
        foreach ($g['items'] as $i) {
            $lifecycle[$i['route']] = $i['placement_type'];
            $lcGroup[$i['route']] = $g['label'];
            $lcOrder[$i['route']] = ++$o;
            $lcLabel[$i['route']] = $i['label'];
        }
    }
    foreach ($new['blocked'] as $b) { if ($b['why'] === 'UNAPPROVED_SECONDARY') { $secUnapproved++; } }

    /* ① TARGET_NAV_RECALL — المطلوبُ الموجود / كلُّ المطلوب (§24) */
    $found = 0; $missing = array();
    foreach ($tg as $rt => $_) {
        if (isset($lifecycle[$rt])) { $found++; } else { $missing[] = $rt; }
    }
    $recall = count($tg) ? $found * 100 / count($tg) : 100;

    /* ② PLACEMENT_PRECISION — ظهوراتٌ لها موضعٌ حاكمٌ / كلُّ الظهوراتِ (‏على القديم) */
    $oldN = count($s['items']); $withPlacement = 0;
    $seen = array();
    foreach ($s['items'] as $it) {
        $k = $ws . '|' . $it['route'];
        if (isset($seen[$it['route']])) { continue; }
        $seen[$it['route']] = 1;
        if (isset($lifecycle[$it['route']]) || isset($shellRoutes[$it['route']])
            || isset($personalRoutes[$it['route']]) || isset($legacyRuled[$k])
            || isset($crossRuled[$k])) { $withPlacement++; }
    }
    $prec = count($seen) ? $withPlacement * 100 / count($seen) : 100;

    /* ③ UNEXPLAINED_EXTRA_MENU_ITEM — **مُصيَّرٌ في الدورةِ بلا مبرِّرٍ حاكم** (§29)
       ─────────────────────────────────────────────────────────────────────────
       ◆ **نصُّ §29 حرفًا**: «الهدف: **كل رابط معروض له `Placement` حاكم ومبرر**…
         والمعيار هو `UNEXPLAINED_EXTRA = 0`» — ⛔ **وليس** «كلُّ رابطٍ في ورقةِ
         الدليل»: §29 نفسُه يرفض فرضَ `Final Menu Count = 12`.
       ◆ **والمقيسُ الذي كشف ضيقَ القارئ**: أربعُ شاشاتٍ في `DEP-11`
         (`operations_room` · `distribution_space` · `view_timesheet` ·
         `stops_unattributed`) **أقرَّها قرارُ جولةٍ مكتوبٌ** ونشرها جدولُ
         الإدارةِ المستهدَفُ بمجموعتِها وترتيبِها — فهي **حاكمةٌ ومبرَّرة**
         بنصِّ §29، ومع ذلك عدَّها القارئُ فائضًا لأنّه لا يعرف إلّا مفردتَين:
         «في ورقةِ الدليل» أو `SECONDARY_APPROVED`.
         ⇒ **مفردةُ تبريرٍ ثالثةٌ لا يقرؤها القارئُ تُنتج أحمرَ كاذبًا**
         [[enum-vocabulary-consumers]] · [[measure-blind-spots]].
       ◆ **والبارُ لا يُخفَّض**: المبرِّرُ الثالثُ **اقترانُ مصدرَين مكتوبَين**
         لا واحدًا — ① حكمُ مصالحةٍ `APPROVED_POST_GUIDE_ADDITION` في
         `gov_legacy_nav_recon` ② **و**صفٌّ في `gov_target_nav` لدورِ هذه
         المساحةِ يسمّي **مجموعةً من دورتِها هي**. فموضعٌ يُعطى مجموعةً بلا
         قرارٍ مكتوبٍ **يبقى فائضًا أحمرَ** — والمقياسُ يقدر أن يحمرَّ.
       ⛔ **والسؤالُ لا يُطوى بهذا**: كونُها مبرَّرةً **لا يعني أنَّ الدليلَ
         استوعبها** — ولذلك يخرج لها عدّادٌ مستقلٌّ باسمِه
         (`APPROVED_ADDITION_OUTSIDE_GUIDE`) يُرفع في `OWNER_ACTION_REGISTER`
         سؤالًا صريحًا (§17 · §34-L4): أتُدمَج في الدليلِ أم يُلغى قرارُ الجولة؟ */
    $extra = array(); $outsideGuide = array();
    foreach ($lifecycle as $rt => $pt) {
        if (isset($tg[$rt])) { continue; }
        if ($pt === 'SECONDARY_APPROVED') { continue; }
        if (isset($approvedAddition[$ws][$rt])) { $outsideGuide[] = $rt; continue; }
        $extra[] = $rt;
    }

    /* ④ PERMISSION_ONLY_RENDERED_ITEM — يُصيَّر ولا موضعَ حاكمَ له إطلاقًا */
    $permOnly = 0;
    foreach ($lifecycle as $rt => $pt) {
        $q = $conn->query("SELECT 1 FROM nav_workspace_placements
                            WHERE workspace_id='" . $conn->real_escape_string($ws) . "'
                              AND route='" . $conn->real_escape_string($rt) . "' LIMIT 1");
        if (!$q || !$q->num_rows) { $permOnly++; }
    }

    /* ⑤ ACTIVE_LEGACY_WITHOUT_DISPOSITION — إرثٌ في القديمِ بلا صفِّ حكم */
    $legacyNoRule = 0;
    foreach ($seen as $rt => $_) {
        if (isset($tg[$rt]) || isset($shellRoutes[$rt]) || isset($personalRoutes[$rt])) { continue; }
        $k = $ws . '|' . $rt;
        if (!isset($legacyRuled[$k]) && !isset($crossRuled[$k])
            && !isset($placedAny[$k])) { $legacyNoRule++; }
    }

    /* ⑥⑦⑧ WRONG_GROUP / WRONG_ORDER / WRONG_LABEL — على الهدفِ الموجودِ وحدَه */
    $wg = $wo = $wl = 0;
    $ord = array();
    foreach ($tg as $rt => $_) { if (isset($lcOrder[$rt])) { $ord[$rt] = $lcOrder[$rt]; } }
    foreach ($tg as $rt => $_) {
        if (!isset($lifecycle[$rt])) { continue; }
        if (isset($tgtGroup[$ws][$rt]) && $tgtGroup[$ws][$rt] !== ''
            && $lcGroup[$rt] !== $tgtGroup[$ws][$rt]) { $wg++; }
        if (isset($tgtLabel[$ws][$rt]) && $tgtLabel[$ws][$rt] !== ''
            && $lcLabel[$rt] !== $tgtLabel[$ws][$rt]) { $wl++; }
    }
    /* الترتيبُ يُقاس **نسبيًّا**: أيتفق تسلسلُ الأهدافِ المُصيَّرةِ مع تسلسلِ الدليل؟ */
    $pairsBad = 0; $keys = array_keys($ord);
    for ($i = 0; $i < count($keys); $i++) {
        for ($j = $i + 1; $j < count($keys); $j++) {
            $a = $keys[$i]; $b = $keys[$j];
            $ta = $tgtOrder[$ws][$a]; $tb = $tgtOrder[$ws][$b];
            if ($ta === $tb) { continue; }
            if ((($ta < $tb) ? 1 : -1) !== (($ord[$a] < $ord[$b]) ? 1 : -1)) { $pairsBad++; }
        }
    }
    $wo = $pairsBad;

    /* ⑨ GLOBAL_FALLBACK / LEGACY_FALLBACK — **من التصييرِ الحيِّ للإنتاج** (§23)
       ─────────────────────────────────────────────────────────────────────────
       ⛔ **وكانا يُقرآنِ من حقلَين ميّتَين**: `navarch_render()` تُهيِّئ
       `global_fallback` و`legacy_fallback` صفرًا **ولا تزيدهما سطرٌ واحد** —
       فصفرُهما بنيويٌّ لا مقيس، ورقمٌ لا يقدر أن يحمرَّ لا يحرس
       [[measure-token-must-exist]]. والأدهى أنَّ السقوطَ يقع في **المُصيِّرِ
       الإنتاجيّ** بينما القراءةُ من **الظلّ** [[render-not-store-rule]].
       ⇒ فيُصيَّر دورُ المساحةِ **في عمليّةٍ نقيّةٍ** وتُقرأ عدَّاداتُ §23 الخمسُ
       من `unified_nav.php` نفسِه حيث تقع:
         `GLOBAL_FALLBACK_COUNT` = التصنيفُ العامُّ + استنتاجُ المجموعةِ من
             المسارِ + المجموعةُ الافتراضيّة (§21 · §23-①·②·④)
         `LEGACY_FALLBACK_RENDER_COUNT` = `nav_items` مصدرَ ظهورٍ + الظهورُ
             المشتقُّ من الصلاحيّة (§21 · §23-③·⑤)
       ◆ و`DEFAULT_GROUP_DAILY` صفرٌ **بحكمِ المخطَّطِ لا بحكمِ الحظّ**: القيدُ
         `fk_nrg_group` يربط `nav_route_group.group_code` بـ`nav_group_taxonomy`
         فلا يخرج رمزٌ عن التصنيف — ويحرسه `NT-09`. */
    /* ◆ **والقياسُ في تهيئةِ المساحةِ نفسِها لا في تهيئةِ النشرِ**: §40 يسأل
         «أتبلغ هذه المساحةُ المعايير؟» لا «أنُشرت؟». فيُصيَّر دورُها **والمفتاحُ
         مفتوحٌ لها** بتراكبِ بيئةٍ مؤقّت — ⛔ ولا يُكتب في `.env` الحيّ.
         **والنشرُ حقيقةٌ مستقلّةٌ تُعلَن**: `CUTOVER_DEPLOYED` أدناه يقول
         أمقلوبةٌ هي فعلًا اليومَ أم لا — فلا يُقرأ «يبلغ المعايير» نشرًا. */
    $ovF = sys_get_temp_dir() . '/navarch_fb_' . getmypid() . '.env';
    file_put_contents($ovF, "EMS_NAV_ARCH=" . $ws . "\n");
    putenv('EMS_ENV_OVERLAY=' . str_replace(DIRECTORY_SEPARATOR, '/', $ovF));
    $fbOut = array(); @exec('"' . PHP_BINARY . '" '
        . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . (int) $rid . ' 2>NUL', $fbOut);
    putenv('EMS_ENV_OVERLAY'); @unlink($ovF);
    $fbJ = json_decode(implode("\n", $fbOut), true);
    $FB = (is_array($fbJ) && !empty($fbJ['fallbacks'])) ? $fbJ['fallbacks'] : null;
    $gf = $FB === null ? -1 : ($FB['GLOBAL_TAXONOMY'] + $FB['ROUTE_DERIVED_GROUP']
                               + $FB['DEFAULT_GROUP_DAILY']);
    $lf = $FB === null ? -1 : ($FB['LEGACY_ITEMS_SOURCE'] + $FB['PERMISSION_DERIVED']);

    /* ⑩ PERSONAL / GLOBAL_SHELL محسوبَينِ إدارةً — يجب صفر (§26 · NT-06 · NT-07) */
    $pAsDept = 0; $gAsDept = 0;
    foreach ($lifecycle as $rt => $pt) {
        if (isset($personalRoutes[$rt])) { $pAsDept++; }
        if (isset($shellRoutes[$rt]))    { $gAsDept++; }
    }

    /* ⑪ TARGET_LINEAGE_BROKEN — موضعٌ حاكمٌ بلا مصدرٍ حاكمٍ مكتوب */
    $q = $conn->query("SELECT COUNT(*) c FROM nav_workspace_placements
                        WHERE workspace_id='" . $conn->real_escape_string($ws) . "'
                          AND (governing_source IS NULL OR governing_source='')");
    $lineage = $q ? (int) $q->fetch_assoc()['c'] : 0;

    $m = array(
        'TARGET_NAV_RECALL'                 => round($recall, 1),
        'TARGET_FOUND'                      => $found,
        'TARGET_TOTAL'                      => count($tg),
        'PLACEMENT_PRECISION'               => round($prec, 1),
        'UNEXPLAINED_EXTRA_MENU_ITEM'       => count($extra),
        'APPROVED_ADDITION_OUTSIDE_GUIDE'   => count($outsideGuide),
        'PERMISSION_ONLY_RENDERED_ITEM'     => $permOnly,
        'UNAPPROVED_SECONDARY_PLACEMENT'    => $secUnapproved,
        'ACTIVE_LEGACY_WITHOUT_DISPOSITION' => $legacyNoRule,
        'WRONG_GROUP'                       => $wg,
        'WRONG_ORDER'                       => $wo,
        'WRONG_LABEL'                       => $wl,
        'GLOBAL_FALLBACK_COUNT'             => $gf,
        'LEGACY_FALLBACK_RENDER_COUNT'      => $lf,
        'FALLBACKS_MEASURED'                => $FB,
        'CUTOVER_DEPLOYED'                  => $deployedWs,
        'PERSONAL_ITEM_COUNTED_AS_DEPARTMENT'     => $pAsDept,
        'GLOBAL_SHELL_COUNTED_AS_DEPARTMENT'      => $gAsDept,
        'TARGET_LINEAGE_BROKEN'             => $lineage,
        'OLD_RENDERED'                      => $oldN,
        'NEW_LIFECYCLE'                     => count($lifecycle),
        'MISSING_TARGETS'                   => $missing,
        'EXTRA_ITEMS'                       => $extra,
        'ADDITIONS_OUTSIDE_GUIDE'           => $outsideGuide,
    );
    /* §25 — تسعةُ أصفارٍ لا ثمانية */
    $m['EXACT_WORKSPACE_NAV_CONFORMANCE'] =
        ($m['TARGET_NAV_RECALL'] >= 100 && $m['WRONG_GROUP'] === 0 && $m['WRONG_ORDER'] === 0
         && $m['WRONG_LABEL'] === 0 && $m['PERMISSION_ONLY_RENDERED_ITEM'] === 0
         && $m['UNAPPROVED_SECONDARY_PLACEMENT'] === 0
         && $m['ACTIVE_LEGACY_WITHOUT_DISPOSITION'] === 0
         && $m['UNEXPLAINED_EXTRA_MENU_ITEM'] === 0
         && $m['GLOBAL_FALLBACK_COUNT'] === 0) ? 'PASS' : 'FAIL';
    $M[$ws] = $m;
}

/* ═══ ④ الإخراج ═════════════════════════════════════════════════════════════ */
echo "══ NAV-ARCH-02 §26 — المقاييسُ · الأساس {$BLID} ══\n";
echo "◆ **§24**: `TARGET_NAV_RECALL` هو الاسمُ الجديدُ لما كان يُسمَّى `Exact` —\n";
echo "  وهو **استرجاعٌ لا مطابقة**؛ والمطابقةُ التامّةُ `EXACT_WORKSPACE_NAV_CONFORMANCE`\n";
echo "  ولا تمرُّ إلّا بتسعةِ أصفار (§25).\n\n";
printf("%-8s %6s %5s %5s %5s %5s %5s %5s %5s %5s %5s %5s %5s %6s\n",
    'المساحة', 'Recall', 'Prec', 'Extra', 'Add+', 'Perm', 'UnSec', 'NoRul', 'WGrp', 'WOrd', 'WLbl',
    'Fall', 'Line', 'CONF');
foreach ($M as $ws => $m) {
    printf("%-8s %5.1f%% %4.0f%% %5d %5d %5d %5d %5d %5d %5d %5d %5d %5d %6s\n", $ws,
        $m['TARGET_NAV_RECALL'], $m['PLACEMENT_PRECISION'], $m['UNEXPLAINED_EXTRA_MENU_ITEM'],
        $m['APPROVED_ADDITION_OUTSIDE_GUIDE'], $m['PERMISSION_ONLY_RENDERED_ITEM'], $m['UNAPPROVED_SECONDARY_PLACEMENT'],
        $m['ACTIVE_LEGACY_WITHOUT_DISPOSITION'], $m['WRONG_GROUP'], $m['WRONG_ORDER'],
        $m['WRONG_LABEL'], $m['GLOBAL_FALLBACK_COUNT'] + $m['LEGACY_FALLBACK_RENDER_COUNT'],
        $m['TARGET_LINEAGE_BROKEN'], $m['EXACT_WORKSPACE_NAV_CONFORMANCE']);
}

/* §40 — معاييرُ الطيّارِ الاثنا عشرَ */
$pilot = 'DEP-11';
if (isset($M[$pilot])) {
    $m = $M[$pilot];
    $crit = array(
        'TARGET_NAV_RECALL = 100%'              => $m['TARGET_NAV_RECALL'] >= 100,
        'WRONG_GROUP = 0'                       => $m['WRONG_GROUP'] === 0,
        'WRONG_ORDER = 0'                       => $m['WRONG_ORDER'] === 0,
        'WRONG_LABEL = 0'                       => $m['WRONG_LABEL'] === 0,
        'PERMISSION_ONLY_RENDERED_ITEM = 0'     => $m['PERMISSION_ONLY_RENDERED_ITEM'] === 0,
        'UNAPPROVED_SECONDARY_PLACEMENT = 0'    => $m['UNAPPROVED_SECONDARY_PLACEMENT'] === 0,
        'ACTIVE_LEGACY_WITHOUT_DISPOSITION = 0' => $m['ACTIVE_LEGACY_WITHOUT_DISPOSITION'] === 0,
        'UNEXPLAINED_EXTRA_MENU_ITEM = 0'       => $m['UNEXPLAINED_EXTRA_MENU_ITEM'] === 0,
        'GLOBAL_FALLBACK_COUNT = 0'             => $m['GLOBAL_FALLBACK_COUNT'] === 0,
        'LEGACY_FALLBACK_RENDER_COUNT = 0'      => $m['LEGACY_FALLBACK_RENDER_COUNT'] === 0,
        'TARGET_LINEAGE_BROKEN = 0'             => $m['TARGET_LINEAGE_BROKEN'] === 0,
    );
    echo "\n── §40 · معاييرُ الطيّارِ {$pilot} ──\n";
    $ok = 0;
    foreach ($crit as $k => $v) { printf("  %s %s\n", $v ? '✔' : '✘', $k); if ($v) { $ok++; } }
    printf("  ◆ %d من %d · و`HUMAN_UAT_PASS` بندٌ بشريٌّ يُثبَت في DEP11_PILOT_UAT\n",
        $ok, count($crit));
    if ($m['MISSING_TARGETS']) {
        echo "  ── هدفٌ مطلوبٌ ولا يُصيَّر ──\n";
        foreach ($m['MISSING_TARGETS'] as $x) { echo "     · {$x}\n"; }
    }
    if ($m['EXTRA_ITEMS']) {
        echo "  ── فائضٌ بلا هدفٍ ولا موضعٍ ثانويٍّ معتمَد ──\n";
        foreach ($m['EXTRA_ITEMS'] as $x) { echo "     · {$x}\n"; }
    }
}

/* ⛔ **و`--gate` قراءةٌ خالصة**: يعمل داخلَ خطّافِ الالتزام، وحاجزٌ يكتب في
   ملفٍّ متتبَّعٍ **بعدَ تجهيزِه** يترك الشجرةَ متّسخةً عند أوّلِ رقمٍ يتحرَّك —
   فيبدو الالتزامُ ناجحًا وقد خلَّف فرقًا غيرَ ملتزَم. */
if (!$gate) {
    file_put_contents($OUT . '/WORKSPACE_NAV_CONFORMANCE.json',
        json_encode(array('baseline_id' => $BLID, 'metrics' => $M),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n  ⇒ {$OUT}/WORKSPACE_NAV_CONFORMANCE.json\n";
}

if ($gate && isset($M[$pilot])) {
    exit($M[$pilot]['EXACT_WORKSPACE_NAV_CONFORMANCE'] === 'PASS' ? 0 : 1);
}
exit(0);
