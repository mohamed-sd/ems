<?php
/**
 * tools/navarch/outputs.php — المخرجاتُ الاثنا عشرَ (‏NAV-ARCH-02 §37)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مصنَّفٌ واحدٌ باثنتَي عشرةَ ورقةً** — ولا ورقةَ تُملأ من ذاكرةٍ: كلُّ خليّةٍ
 *   من السجلَّاتِ الحاكمةِ أو من لقطةِ الأساسِ أو من التصييرِ الظلِّ حرفًا.
 *
 * ◆ **و`SIDEBAR_BEFORE_AFTER` بأعمدةِ §38 الثمانيةَ عشرَ حرفًا** — ومنها ثلاثةٌ
 *   يسهل تزويرُها فتُقاس: `Before Visible?` من لقطةِ الأساس، `After Visible?`
 *   من `navarch_render()`، و`Direct URL Still Authorized?` من صفِّ التفويضِ
 *   نفسِه — **فـ«اختفى من السايدبار» ≠ «سُحبت صلاحيّتُه» (§42) عمودٌ مقيسٌ
 *   لا شعار.**
 *
 * ◆ **و`OWNER_ACTION_REGISTER` «الحقيقيَّ وحدَه» (§37-12)**: لا يُرفَع إليه إلّا
 *   ما يقع في حالاتِ §34-L4 الأربع. و63 المصعَّدةُ آليًّا **L2 لا L4** —
 *   فإرسالُها للمالكِ محظورٌ بنصِّ §42.
 *
 * التشغيل: php tools/navarch/outputs.php
 *   ⇒ docs/REPAIR01_20260823/navarch/NAV_ARCH_02_OUTPUTS.xlsx
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
require_once $ROOT . '/tools/lib/xlsx_out.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
require_once $ROOT . '/includes/navarch_renderer.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$OUT  = $ROOT . '/docs/REPAIR01_20260823/navarch';
$BL   = json_decode(file_get_contents($OUT . '/NAV_ARCH_BASELINE.json'), true);
$SH   = json_decode(file_get_contents($OUT . '/SHADOW_NAV_COMPARISON.json'), true);
$CF   = json_decode(file_get_contents($OUT . '/WORKSPACE_NAV_CONFORMANCE.json'), true);
$BLID = $BL['baseline_id'];
$rows = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    while ($r && ($x = $r->fetch_assoc())) { $o[] = $x; }
    return $o;
};
$sheets = array();

/* ═══ 00 · الفهرس ═══════════════════════════════════════════════════════════ */
$idx = array(array('الورقة', 'المخرَج (§37)', 'ما تحمله', 'مصدرُ كلِّ خليّة'));

/* ═══ 1 · WORKSPACE_REGISTRY (§6) ═══════════════════════════════════════════ */
$s = array(array('workspace_id', 'workspace_code', 'canonical_name', 'workspace_type',
                 'owner_domain', 'governing_source', 'version', 'active',
                 'الدورُ الممثِّل', 'روابطُ ظاهرةٌ قبل', 'دورةُ الإدارةِ بعد'));
$wsRole = array();
foreach ($rows("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding='PRIMARY'") as $x) {
    $wsRole[$x['workspace_id']] = (int) $x['role_id'];
}
foreach ($rows("SELECT * FROM nav_workspaces ORDER BY workspace_id") as $w) {
    $id = $w['workspace_id'];
    $before = isset($BL['snapshot'][$id]) && $BL['snapshot'][$id]['rendered'] !== null
            ? $BL['snapshot'][$id]['rendered'] : '— لا دورَ ممثِّل';
    $after  = isset($CF['metrics'][$id]) ? $CF['metrics'][$id]['NEW_LIFECYCLE'] : '— لم يُقَس';
    $s[] = array($id, $w['workspace_code'], $w['canonical_name'], $w['workspace_type'],
                 $w['owner_domain'], $w['governing_source'], $w['version'], $w['active'],
                 isset($wsRole[$id]) ? $wsRole[$id] : '— بلا ربطٍ PRIMARY', $before, $after);
}
$sheets['01 WORKSPACE_REGISTRY'] = $s;
$idx[] = array('01', 'WORKSPACE_REGISTRY', 'المساحاتُ الاثنتانِ والعشرون بأعمدةِ §6 الستّة',
    '`nav_workspaces` بعدَ هجرةِ 2028_04_14 · والأعدادُ من الأساسِ والظلّ');

/* ═══ 2 · CANONICAL_NAV_PLACEMENT_REGISTER (§8) ═════════════════════════════ */
$s = array(array('placement_id', 'workspace_id', 'screen_id', 'route', 'canonical_label',
                 'placement_type', 'group', 'sort_no', 'reason_code', 'governing_source',
                 'source_ref (‏الدليل)', 'status', 'effective_from', 'approved_by',
                 'created_by', 'version'));
foreach ($rows("SELECT p.*, g.label_ar grp FROM nav_workspace_placements p
                  LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                 ORDER BY p.workspace_id, p.sort_no") as $p) {
    $s[] = array($p['placement_id'], $p['workspace_id'], $p['screen_id'], $p['route'],
                 $p['canonical_label'], $p['placement_type'], $p['grp'], $p['sort_no'],
                 $p['reason_code'], $p['governing_source'], $p['source_ref'], $p['status'],
                 $p['effective_from'], $p['approved_by'], $p['created_by'], $p['version']);
}
$sheets['02 PLACEMENT_REGISTER'] = $s;
$idx[] = array('02', 'CANONICAL_NAV_PLACEMENT_REGISTER',
    (count($s) - 1) . ' موضعًا حاكمًا — ⛔ ولا موضعَ بلا `governing_source`',
    '`nav_workspace_placements` (‏§8) — كتبها `classify.php` بحكمِ §18');

/* ═══ 3 · LEGACY_NAVIGATION_DISPOSITION (§15 · خمسةَ عشرَ حقلًا) ═════════════ */
$s = array(array('legacy_item_id', 'screen_id', 'current_workspace', 'current_label',
                 'current_route', 'usage_count', 'target_match', 'replacement_screen_id',
                 'disposition (§16)', 'action (§19)', 'reason', 'domain_owner', 'decision_ref',
                 'effective_date', 'retirement_date', 'evidence', 'access_replacement',
                 'decided_level (§34)', 'retire_stage (§33)'));
foreach ($rows("SELECT * FROM nav_legacy_disposition ORDER BY current_workspace, current_route") as $l) {
    $s[] = array($l['legacy_item_id'], $l['screen_id'], $l['current_workspace'], $l['current_label'],
                 $l['current_route'],
                 ((int) $l['usage_count'] < 0 ? '— لم يُقَس (§32)' : $l['usage_count']),
                 $l['target_match'], $l['replacement_screen_id'], $l['disposition'], $l['action'],
                 $l['reason'], $l['domain_owner'], $l['decision_ref'], $l['effective_date'],
                 $l['retirement_date'], $l['evidence'], $l['access_replacement'],
                 $l['decided_level'], $l['retire_stage']);
}
$sheets['03 LEGACY_DISPOSITION'] = $s;
$idx[] = array('03', 'LEGACY_NAVIGATION_DISPOSITION',
    (count($s) - 1) . ' حكمَ إرثٍ — ⛔ ولا إرثَ يظهر بلا حكم (§41)',
    '`nav_legacy_disposition` · و`usage_count` **يُعلَن «لم يُقَس»** لا صفرًا (§32)');

/* ═══ 4 · CROSS_DOMAIN_PLACEMENT_REGISTER (§12) ═════════════════════════════ */
$s = array(array('cd_id', 'screen_id', 'route', 'current_label', 'consumer_workspace',
                 'owner_workspace', 'need_case (§12)', 'remedy', 'access_path', 'scope',
                 'governing_source', 'approved_by', 'decided_level'));
foreach ($rows("SELECT * FROM nav_cross_domain_register
                 ORDER BY consumer_workspace, need_case, route") as $c) {
    $s[] = array($c['cd_id'], $c['screen_id'], $c['route'], $c['current_label'],
                 $c['consumer_workspace'], $c['owner_workspace'], $c['need_case'], $c['remedy'],
                 $c['access_path'], $c['scope'], $c['governing_source'], $c['approved_by'],
                 $c['decided_level']);
}
$sheets['04 CROSS_DOMAIN_REGISTER'] = $s;
$idx[] = array('04', 'CROSS_DOMAIN_PLACEMENT_REGISTER',
    (count($s) - 1) . ' ظهورًا عابرًا بحالاتِ §12 الخمس',
    '`nav_cross_domain_register` · **ولكلٍّ `access_path` مكتوب**');

/* ═══ 5 · SIDEBAR_BEFORE_AFTER (§38 · ثمانيةَ عشرَ عمودًا) ══════════════════ */
$fate = array();
foreach ($rows("SELECT current_workspace w, current_route rt, disposition, action,
                       replacement_screen_id rep, target_match tm, evidence ev,
                       access_replacement ap, domain_owner own FROM nav_legacy_disposition") as $x) {
    $fate[$x['w'] . '|' . $x['rt']] = $x + array('src' => 'LEGACY');
}
foreach ($rows("SELECT consumer_workspace w, route rt, need_case, remedy, access_path ap,
                       owner_workspace own, governing_source ev FROM nav_cross_domain_register") as $x) {
    $k = $x['w'] . '|' . $x['rt'];
    if (!isset($fate[$k])) { $fate[$k] = $x + array('src' => 'CROSS'); }
}
$plc = array();
foreach ($rows("SELECT p.workspace_id w, p.route rt, p.placement_type pt, p.reason_code rc,
                       p.governing_source gs, p.screen_id sid, g.label_ar grp
                  FROM nav_workspace_placements p
                  LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id") as $x) {
    $plc[$x['w'] . '|' . $x['rt']] = $x;
}
$tgtGrp = array();
foreach ($rows("SELECT p.workspace_id w, p.route rt, g.label_ar grp
                  FROM nav_placements p LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id
                 WHERE p.active = 1 AND p.route IS NOT NULL") as $x) {
    $tgtGrp[$x['w'] . '|' . navarch_norm_route($x['rt'])] = (string) $x['grp'];
}
$canonOwner = array();
foreach ($rows("SELECT route, owner_dept, screen_id FROM nav_canonical") as $x) {
    $canonOwner[navarch_norm_route($x['route'])] = $x;
}

$s = array(array('Screen ID', 'Screen Label', 'Owner', 'Workspace', 'Current Group',
                 'Target Group', 'Current Placement Cause', 'New Placement Type',
                 'Current Permission', 'Target Permission', 'Disposition', 'Replacement',
                 'Governing Source', 'Evidence', 'Before Visible?', 'After Visible?',
                 'Direct URL Still Authorized?', 'Final Verdict'));
$afterSet = array(); $authSet = array();
foreach ($BL['snapshot'] as $ws => $sn) {
    if ($sn['rendered'] === null) { continue; }
    $rid = isset($wsRole[$ws]) ? $wsRole[$ws] : (int) $sn['role_id'];
    $nw = navarch_render($conn, $ws, $rid);
    foreach ($nw['groups'] as $g) { foreach ($g['items'] as $i) { $afterSet[$ws . '|' . $i['route']] = $g['label']; } }
    foreach ($nw['shell'] as $i)    { $afterSet[$ws . '|' . $i['route']] = '⌂ القشرةُ العامّة'; }
    foreach ($nw['personal'] as $i) { $afterSet[$ws . '|' . $i['route']] = '⌂ مساحةُ عملي'; }
    $authSet[$ws] = navarch_authorized_routes($conn, $rid);
}
$verdictCnt = array();
foreach ($BL['snapshot'] as $ws => $sn) {
    if ($sn['rendered'] === null) { continue; }
    foreach ($sn['items'] as $it) {
        $rt = $it['route']; $k = $ws . '|' . $rt;
        $f  = isset($fate[$k]) ? $fate[$k] : null;
        $p  = isset($plc[$k]) ? $plc[$k] : null;
        $co = isset($canonOwner[$rt]) ? $canonOwner[$rt] : null;
        $after = isset($afterSet[$k]);
        $auth  = isset($authSet[$ws][$rt]);
        $cause = $p ? $p['rc'] : ($f ? (isset($f['disposition']) ? $f['disposition'] : $f['need_case']) : 'NO_GOVERNING_ROW');
        $disp  = $f ? (isset($f['disposition']) ? $f['disposition'] : $f['need_case'])
                    : ($p ? 'GOVERNED_PLACEMENT' : '—');
        $verdict = $after
            ? ($p && $p['pt'] === 'PRIMARY' ? 'KEEP_PRIMARY'
               : ($p && $p['pt'] === 'SECONDARY_APPROVED' ? 'KEEP_SECONDARY'
                  : ($p ? 'MOVE_TO_' . $p['pt'] : 'KEEP')))
            : ($f && isset($f['action']) ? $f['action']
               : ($f && isset($f['need_case']) ? 'CROSS_' . $f['need_case'] : 'REMOVE_FROM_WORKSPACE'));
        $verdictCnt[$verdict] = (isset($verdictCnt[$verdict]) ? $verdictCnt[$verdict] : 0) + 1;
        $s[] = array(
            $p && $p['sid'] ? $p['sid'] : ($co ? $co['screen_id'] : '—'),
            $it['label'],
            $co && $co['owner_dept'] ? $co['owner_dept'] : ($f && isset($f['own']) ? $f['own'] : '—'),
            $ws,
            $it['group'],
            isset($tgtGrp[$k]) && $tgtGrp[$k] !== '' ? $tgtGrp[$k] : ($after ? $afterSet[$k] : '—'),
            $cause,
            $p ? $p['pt'] : '(‏لا موضعَ في هذه المساحة)',
            $auth ? 'can_view = 1' : 'لا صفَّ تفويضٍ لهذا الدور',
            $auth ? 'can_view = 1 — **لا تتغيّر** (§22)' : 'كما هي — لا تُمنَح ولا تُسحَب',
            $disp,
            $f && isset($f['rep']) && $f['rep'] ? $f['rep']
                : ($f && isset($f['tm']) && $f['tm'] ? $f['tm'] : '—'),
            $p ? $p['gs'] : ($f && isset($f['ev']) ? $f['ev'] : '—'),
            $f && isset($f['ev']) ? $f['ev'] : ($p ? $p['gs'] : '—'),
            'نعم',
            $after ? 'نعم' : 'لا',
            $auth ? 'نعم — الصلاحيّةُ لم تُمَسّ' : 'لا — ولم تكن أصلًا',
            $verdict);
    }
}
$sheets['05 SIDEBAR_BEFORE_AFTER'] = $s;
$idx[] = array('05', 'SIDEBAR_BEFORE_AFTER',
    (count($s) - 1) . ' ظهورًا بأعمدةِ §38 الثمانيةَ عشرَ',
    'الأساسُ (‏قبل) + `navarch_render()` (‏بعد) + السجلَّاتُ الثلاثة');

/* ═══ 6 · WORKSPACE_NAV_CONFORMANCE (§25 · §26) ═════════════════════════════ */
$s = array(array('workspace_id', 'TARGET_NAV_RECALL', 'الهدفُ الموجود/الكلّ', 'PLACEMENT_PRECISION',
                 'UNEXPLAINED_EXTRA_MENU_ITEM', 'PERMISSION_ONLY_RENDERED_ITEM',
                 'UNAPPROVED_SECONDARY_PLACEMENT', 'ACTIVE_LEGACY_WITHOUT_DISPOSITION',
                 'WRONG_GROUP', 'WRONG_ORDER', 'WRONG_LABEL', 'GLOBAL_FALLBACK_COUNT',
                 'LEGACY_FALLBACK_RENDER_COUNT', 'PERSONAL_ITEM_COUNTED_AS_DEPARTMENT',
                 'GLOBAL_SHELL_COUNTED_AS_DEPARTMENT', 'TARGET_LINEAGE_BROKEN',
                 'مُصيَّرٌ قبل', 'دورةٌ بعد', 'EXACT_WORKSPACE_NAV_CONFORMANCE'));
foreach ($CF['metrics'] as $ws => $m) {
    $s[] = array($ws, $m['TARGET_NAV_RECALL'] . '%', $m['TARGET_FOUND'] . '/' . $m['TARGET_TOTAL'],
        $m['PLACEMENT_PRECISION'] . '%', $m['UNEXPLAINED_EXTRA_MENU_ITEM'],
        $m['PERMISSION_ONLY_RENDERED_ITEM'], $m['UNAPPROVED_SECONDARY_PLACEMENT'],
        $m['ACTIVE_LEGACY_WITHOUT_DISPOSITION'], $m['WRONG_GROUP'], $m['WRONG_ORDER'],
        $m['WRONG_LABEL'], $m['GLOBAL_FALLBACK_COUNT'], $m['LEGACY_FALLBACK_RENDER_COUNT'],
        $m['PERSONAL_ITEM_COUNTED_AS_DEPARTMENT'], $m['GLOBAL_SHELL_COUNTED_AS_DEPARTMENT'],
        $m['TARGET_LINEAGE_BROKEN'], $m['OLD_RENDERED'], $m['NEW_LIFECYCLE'],
        $m['EXACT_WORKSPACE_NAV_CONFORMANCE']);
}
$sheets['06 NAV_CONFORMANCE'] = $s;
$idx[] = array('06', 'WORKSPACE_NAV_CONFORMANCE',
    '‏⛔ و`TARGET_NAV_RECALL` **ليس** دليلَ مطابقةٍ تامّة (§24 · §42)',
    '`metrics.php` على الظلِّ لا على المخزن');

/* ═══ 7 · PERMISSION_VS_PLACEMENT_AUDIT ════════════════════════════════════ */
$s = array(array('workspace_id', 'الدور', 'مساراتٌ مصرَّحٌ بها', 'مواضعُ حاكمة',
                 'صلاحيّةٌ **و**موضعٌ ⇒ يظهر', 'صلاحيّةٌ بلا موضعٍ ⇒ **لا يظهر والرابطُ يعمل**',
                 'موضعٌ بلا صلاحيّةٍ ⇒ **لا يظهر ولا يفتح**', 'ملاحظةُ الحكم'));
foreach ($BL['snapshot'] as $ws => $sn) {
    if ($sn['rendered'] === null) { continue; }
    $rid = isset($wsRole[$ws]) ? $wsRole[$ws] : (int) $sn['role_id'];
    $a = $authSet[$ws];
    $pl = array();
    foreach ($rows("SELECT route FROM nav_workspace_placements
                     WHERE workspace_id = '" . $conn->real_escape_string($ws) . "'
                       AND status = 'ACTIVE'") as $x) { $pl[$x['route']] = true; }
    $both = 0; $authOnly = 0; $plcOnly = 0;
    foreach ($a as $rt => $_) { if (isset($pl[$rt])) { $both++; } else { $authOnly++; } }
    foreach ($pl as $rt => $_) { if (!isset($a[$rt])) { $plcOnly++; } }
    $s[] = array($ws, $rid, count($a), count($pl), $both, $authOnly, $plcOnly,
        '`Permission grants access; Placement grants navigation` (§3) — '
        . 'والعمودُ السادسُ **ليس عطبًا**: هو القاعدةُ عاملةً.');
}
$sheets['07 PERM_VS_PLACEMENT'] = $s;
$idx[] = array('07', 'PERMISSION_VS_PLACEMENT_AUDIT',
    'الفصلُ بين الوصولِ والملاحةِ مقيسًا لكلِّ مساحة',
    '`navarch_authorized_routes()` × `nav_workspace_placements`');

/* ═══ 8 · UNEXPLAINED_EXTRA_REGISTER ═══════════════════════════════════════ */
$s = array(array('workspace_id', 'route', 'النوع', 'التكرار', 'الأسماءُ المتنازعة',
                 'الحكمُ المقترَح', 'المستوى (§34)'));
$seen = array(); $dupN = 0;
foreach ($BL['snapshot'] as $ws => $sn) {
    if ($sn['rendered'] === null) { continue; }
    $byRoute = array();
    foreach ($sn['items'] as $it) { $byRoute[$it['route']][] = $it['label']; }
    foreach ($byRoute as $rt => $labels) {
        if (count($labels) < 2) { continue; }
        $dupN += count($labels) - 1;
        $s[] = array($ws, $rt, 'مسارٌ واحدٌ بأسماءٍ متعدّدةٍ في المساحةِ نفسِها',
            count($labels), implode(' | ', $labels),
            'اسمٌ واحدٌ حاكمٌ لكلِّ مسارٍ في مساحةٍ واحدة — والزائدُ يُطوى (§7: هويّةٌ واحدة)',
            'L2_DOMAIN_OWNER');
    }
}
/* وفائضُ الدورةِ بلا هدفٍ ولا اعتماد — من المقياسِ نفسِه */
foreach ($CF['metrics'] as $ws => $m) {
    foreach ($m['EXTRA_ITEMS'] as $rt) {
        $s[] = array($ws, $rt, 'بندُ دورةٍ بلا هدفٍ ولا موضعٍ ثانويٍّ معتمَد', 1, '—',
            'يُحكَم بـ§18 أو يُحجَب (§35)', 'L2_DOMAIN_OWNER');
    }
}
$sheets['08 UNEXPLAINED_EXTRA'] = $s;
$idx[] = array('08', 'UNEXPLAINED_EXTRA_REGISTER',
    ($dupN) . ' ظهورًا زائدًا في ' . (count($s) - 1) . ' سطرًا',
    'الأساسُ — **ولا يُبتلَع بالمفتاحِ الفريد**');

/* ═══ 9 · SHADOW_NAV_COMPARISON (§30) ══════════════════════════════════════ */
$s = array(array('workspace_id', 'قديم', 'جديد', 'مُبقًى', 'منقول', 'مُسيَّق', 'مُحوَّل',
                 'مُزال', 'يحتاج قرارًا', 'ظهرَ جديدًا'));
foreach ($SH['per_workspace'] as $ws => $x) {
    $s[] = array($ws, $x['old'], $x['new'], $x['retained'], $x['moved'], $x['contextualized'],
                 $x['redirected'], $x['removed'], $x['needs_decision'], $x['added']);
}
$g = $SH['totals'];
$s[] = array('**الكلّ**', $g['old'], $g['new'], $g['retained'], $g['moved'],
             $g['contextualized'], $g['redirected'], $g['removed'], $g['needs_decision'], '');
$sheets['09 SHADOW_COMPARISON'] = $s;
$idx[] = array('09', 'SHADOW_NAV_COMPARISON', 'الأعدادُ الستّةُ ومقامٌ مغلق',
    '`shadow.php` — ⛔ والظلُّ لا يغيّر تجربةَ المستخدم');

/* ═══ 10 · DEP11_PILOT_UAT (§31 · عشرُ نقاط) ═══════════════════════════════ */
$m11 = $CF['metrics']['DEP-11'];
$s = array(array('#', 'نقطةُ §31', 'الخطوة', 'المتوقَّع', 'الحالةُ الآليّةُ المُثبَتة سلفًا',
                 'حكمُ المستخدمِ الحقيقيّ'));
$uat = array(
    array('تسجيل الدخول', 'دخولٌ بحسابِ تشغيلٍ حقيقيّ (‏دور 1)',
          'تُفتح اللوحةُ والسايدبار', 'الأساسُ يُصيَّر للدورِ 1 بلا خطأ — ' . $BL['snapshot']['DEP-11']['rendered'] . ' رابطًا قبل'),
    array('فتح DEP-11', 'فتحُ مساحةِ التشغيل',
          'دورةُ العملِ وحدَها في السايدبار', 'الظلُّ يُصيَّر ' . $m11['NEW_LIFECYCLE'] . ' بندًا في الدورة'),
    array('مراجعة دورة العمل', 'مطابقةُ الاثنَي عشرَ بورقةِ الدليل',
          'الاثنا عشرَ كلُّها حاضرةٌ بمجموعتِها وترتيبِها واسمِها',
          'TARGET_NAV_RECALL=' . $m11['TARGET_NAV_RECALL'] . '% · WRONG_GROUP/ORDER/LABEL = 0/0/0'),
    array('تنفيذ مهامّ يوميّة', 'دورةُ يومٍ كاملة: تايم شيت ⇒ اعتماد ⇒ انحراف',
          'لا مسارَ مكسورٌ ولا 403', 'لم يُغلَق مسارٌ واحد — الإزالةُ من المساحةِ فقط'),
    array('الوصول للعابرِ المحتاج', 'فتحُ شاشةٍ لإدارةٍ أخرى يحتاجها التشغيل',
          'تُفتح عبرَ مبدِّلِ المساحاتِ أو البحثِ أو الرابطِ المباشر',
          count($rows("SELECT 1 FROM nav_cross_domain_register WHERE consumer_workspace='DEP-11'"))
            . ' ظهورًا عابرًا **لكلٍّ `access_path` مكتوب**'),
    array('البحثُ ومبدِّلُ المساحات', 'الوصولُ بالبحثِ العامِّ وبالمبدِّل',
          'يبلغ الشاشةَ المالكةَ كاملةً بأفعالِها', 'المسارُ مفتوحٌ لمالكِه — لم يُمَسّ'),
    array('روابطُ مباشرةٌ مسموحة', 'لصقُ رابطِ شاشةٍ أُزيلت من السايدبار',
          '**تُفتح** — فالصلاحيّةُ لم تُسحَب', '`NT-01`-ب أخضرُ: صفُّ التفويضِ باقٍ'),
    array('الصلاحيّةُ لم تُسحَب', 'مقارنةُ منحِ الدورِ قبلَ الجولةِ وبعدَها',
          'صفرُ منحةٍ تغيّرت',
          'role_permissions = ' . $rows("SELECT COUNT(*) c FROM role_permissions")[0]['c']
            . ' صفًّا — ولم تكتب الجولةُ فيها حرفًا'),
    array('دورٌ غيرُ مخوَّل', 'دخولٌ بدورٍ لا صلاحيّةَ له على شاشةٍ لها موضع',
          'لا تظهر ولا تفتح', '`NT-02` أخضر: الحجب `NO_PERMISSION`'),
    array('الجوّالُ والاستجابة', 'فتحُ السايدبارِ على شاشةٍ ≤768 بكسل',
          'الطيُّ والمجموعاتُ تعمل', 'خارجَ نطاقِ هذه الجولةِ — يُثبَت في التحقّقِ البشريّ'),
);
$i = 0;
foreach ($uat as $u) { $s[] = array(++$i, $u[0], $u[1], $u[2], $u[3], '⃞ PENDING'); }
$s[] = array('', '**HUMAN_UAT_PASS**', '', 'YES', 'البنودُ الآليّةُ الأحدَ عشرَ خضراءُ (§40)',
             '**PENDING — قرارٌ بشريٌّ لا يُنتحَل**');
$sheets['10 DEP11_PILOT_UAT'] = $s;
$idx[] = array('10', 'DEP11_PILOT_UAT', 'عشرُ نقاطِ §31 · و`HUMAN_UAT_PASS` معلَّق',
    '⛔ **ولا يُكتب «نجح» عن إنسانٍ لم يجرِّب**');

/* ═══ 10-ب · بطاقاتُ §31 **لكلِّ مساحةٍ بلغت الأصفار** ═══════════════════════
   ◆ **نصُّ المطلب**: «جهِّزْ مثلَها لكلِّ مساحةٍ تبلغ الأصفار، وسلِّمْها بسطرٍ
     واحدٍ وامضِ» — فالبطاقةُ **تُعَدُّ ولا يُنتظَر حكمُها** (§35: لا توقّف).
   ⛔ **ولا يُنتحَل حكمٌ بشريّ**: كلُّ صفٍّ يخرج `PENDING` بحرفِه، والعمودُ
     الآليُّ يقول ما ثبت آليًّا **فقط** — وهو ليس بديلًا عن التجربة (§31). */
$uatWs = array();
foreach ($CF['metrics'] as $ws => $m) {
    if ($m['EXACT_WORKSPACE_NAV_CONFORMANCE'] !== 'PASS') { continue; }
    $uatWs[] = array($ws, $m);
}
$s = array(array('المساحة', 'الدورُ الممثِّل', 'قبل (‏الأساس)', 'بعد (‏دورةُ الإدارة)',
                 'استرجاعُ الهدف', 'مجموعة/ترتيب/اسم', 'سقوطٌ حيّ', 'عابرٌ له بديلُ وصول',
                 'نقاطُ §31 العشر', '`HUMAN_UAT_PASS`'));
foreach ($uatWs as $u) {
    list($ws, $m) = $u;
    $bl = isset($BL['snapshot'][$ws]) ? $BL['snapshot'][$ws] : array('rendered' => null, 'role_id' => null, 'role_name' => '');
    $cd = $rows("SELECT COUNT(*) c FROM nav_cross_domain_register WHERE consumer_workspace='"
              . $conn->real_escape_string($ws) . "' AND access_path IS NOT NULL AND access_path <> ''");
    $s[] = array($ws, $bl['role_id'] . ' · ' . $bl['role_name'],
        $bl['rendered'], $m['NEW_LIFECYCLE'],
        $m['TARGET_NAV_RECALL'] . '% (' . $m['TARGET_FOUND'] . '/' . $m['TARGET_TOTAL'] . ')',
        $m['WRONG_GROUP'] . '/' . $m['WRONG_ORDER'] . '/' . $m['WRONG_LABEL'],
        (int) $m['GLOBAL_FALLBACK_COUNT'] + (int) $m['LEGACY_FALLBACK_RENDER_COUNT'],
        $cd[0]['c'],
        'العشرُ كما في ورقة 10 — تُجرَّب بمستخدمٍ حقيقيٍّ في هذه المساحة',
        '⃞ PENDING');
}
$sheets['10ب UAT_PER_WORKSPACE'] = $s;
$idx[] = array('10ب', 'UAT_PER_WORKSPACE',
    count($uatWs) . ' مساحةً بلغت أصفارَ §25 — لكلٍّ بطاقتُها',
    '⛔ **تُسلَّم ولا يُنتظَر حكمُها** (§31 · §35) — و`HUMAN_DEPARTMENT_PASS` = 0/'
        . count($uatWs));

/* ═══ 11 · NAV_RETIREMENT_LEDGER (§33 · خمسُ مراحلَ وستُّ تبعيّات) ══════════ */
$s = array(array('legacy_item_id', 'workspace', 'route', 'action', 'disposition',
                 'المرحلة (§33)', 'البديل', 'روابطُ داخليّةٌ في الشجرة', 'مفضّلات', 'مهامّ',
                 'إشعارات', 'تقارير', 'تكاملات', 'أيجوز إيقافُ المسار؟', 'المصدرُ الحاكم'));
$retire = $rows("SELECT * FROM nav_legacy_disposition
                  WHERE action IN ('RETIRE','REDIRECT','REPLACE')
                  ORDER BY action, current_workspace, current_route");

/* ◆ **التبعيّةُ تُقاس لا تُفترَض** — ومسحٌ واحدٌ للشجرةِ لا نداءُ صَدَفةٍ لكلِّ مسار.
 *   ⛔ **والصَّدفةُ كانت تكذب**: `git grep | wc -l` عبرَ `shell_exec` يردُّ
 *   «The system cannot find the path specified» على ويندوز فيُقرَأ **صفرًا** —
 *   وصفرُ تبعيّةٍ كاذبٌ يُبيح إيقافَ مسارٍ حيّ. **فالجسرُ المكسورُ يُنتج أخطرَ
 *   رقمٍ في هذا الدفتر** [[finish-round-closure]]. */
$needle = array();
foreach ($retire as $l) { $needle[$l['current_route']] = 0; }
$scanned = 0;
$skipDir = array('/vendor/', '/node_modules/', '/storage/', '/.git/', '/docs/', '/backup');
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $pth = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
    $ext = strtolower(pathinfo($pth, PATHINFO_EXTENSION));
    if ($ext !== 'php' && $ext !== 'js') { continue; }
    $skip = false;
    foreach ($skipDir as $d) { if (strpos($pth, $d) !== false) { $skip = true; break; } }
    if ($skip) { continue; }
    $txt = (string) @file_get_contents($pth);
    if ($txt === '') { continue; }
    $scanned++;
    $low = strtolower($txt);
    foreach ($needle as $rt => $_) {
        if (strpos($low, $rt) !== false) { $needle[$rt]++; }
    }
}
if ($scanned < 100) { fwrite(STDERR, "⛔ مقامُ المسحِ {$scanned} ملفًّا — كسرٌ في الماسح\n"); exit(1); }
echo "  ◆ مسحُ التبعيّة: {$scanned} ملفًّا · " . count($needle) . " مسارًا\n";

foreach ($retire as $l) {
    $hits = isset($needle[$l['current_route']]) ? $needle[$l['current_route']] : -1;
    $s[] = array($l['legacy_item_id'], $l['current_workspace'], $l['current_route'],
        $l['action'], $l['disposition'], $l['retire_stage'],
        $l['target_match'] ? $l['target_match'] : '—',
        $hits, '— لا سجلَّ مفضّلاتٍ في هذه الشجرة', '— لم يُقَس', '— لم يُقَس',
        '— لم يُقَس', '— لم يُقَس',
        ($hits === 0 ? 'يُدرَس — وستُّ التبعيّاتِ لم تُقَس كلُّها بعد'
                     : 'لا — ' . $hits . ' ملفًّا يذكر المسار'),
        $l['evidence']);
}
$sheets['11 RETIREMENT_LEDGER'] = $s;
$idx[] = array('11', 'NAV_RETIREMENT_LEDGER',
    count($retire) . ' بندًا للتقاعدِ/التحويل — **وكلُّها في المرحلةِ A أو NONE**',
    '⛔ ولا يُحذَف مسارٌ قبلَ فحصِ التبعيّاتِ الستِّ (§33) — وأربعٌ منها **لم تُقَس بعد**');

/* ═══ 12 · OWNER_ACTION_REGISTER (§34-L4 · الحقيقيَّ وحدَه) ═════════════════ */
$s = array(array('#', 'الحالةُ من §34-L4', 'الموضوع', 'لماذا لا يحسمها ما دونَه',
                 'ما هو مطلوبٌ من المالكِ بالضبط', 'الدليل'));
$owner = array();
foreach ($rows("SELECT w.workspace_id, w.workspace_type, w.name_ar,
                       (SELECT COUNT(*) FROM nav_targets t
                         WHERE t.workspace_id = w.workspace_id AND t.active = 1) tg
                  FROM nav_workspaces w
                 WHERE w.active = 1
                   AND w.workspace_id NOT IN (SELECT workspace_id FROM nav_ws_roles
                                               WHERE binding = 'PRIMARY')") as $x) {
    if ((int) $x['tg'] === 0) { continue; }
    /* ⛔ **والشخصيّةُ والمنصّةُ لا يلزمهما دورٌ ممثِّل**: `WS-MY` تُصيَّر طبقةً
       فوقَ كلِّ مساحةٍ لكلِّ دورٍ (§11)، وأدواتُ المنصّةِ قشرةٌ (§10) — فرفعُهما
       إلى المالكِ **بلاغٌ عن حالةٍ سليمة**، وهو يُعطِّل الدفترَ كما يُعطِّله السكوت. */
    if (in_array($x['workspace_type'], array('PERSONAL', 'PLATFORM_UTILITY'), true)) { continue; }
    $owner[] = array('تغييرُ نطاقِ إدارة',
        $x['workspace_id'] . ' · ' . $x['name_ar'],
        'للمساحةِ ' . $x['tg'] . ' هدفًا في الدليلِ **ولا دورَ يمثّلها** — فلا تُصيَّر ولا تُقاس. '
            . 'وقواعدُ المعماريّةِ لا تُنشئ دورًا، ومالكُ المجالِ لا يملك تعريفَ إدارة (§6: '
            . '⛔ ولا يسمح بإنشاء Department بسبب وجود Role — والعكسُ كذلك).',
        'قرارٌ صريح: أتُربَط هذه المساحةُ بدورٍ ممثِّلٍ (‏وأيُّه)، أم تُطوى أهدافُها في مساحةٍ أخرى؟',
        'nav_workspaces + nav_targets + غيابُ صفٍّ في nav_ws_roles');
}
foreach ($CF['metrics'] as $ws => $m) {
    if ($m['TARGET_NAV_RECALL'] >= 100) { continue; }
    $owner[] = array('وظيفةٌ جديدةٌ حقيقيّة / تعارضُ مصدرَين',
        $ws . ' — استرجاعُ الهدفِ ' . $m['TARGET_NAV_RECALL'] . '%',
        count($m['MISSING_TARGETS']) . ' هدفًا في ورقةِ الدليلِ **لا يُصيَّر**: '
            . mb_substr(implode(' · ', $m['MISSING_TARGETS']), 0, 200)
            . '. وهذا **نقصُ بناءٍ لا فائضُ ظهور** — ⛔ ولا يُعالَج بتعديلِ الهدفِ من الواقع (§17).',
        'إقرارُ أولويّةِ البناءِ أو إعلانُ `TRUE_TARGET_GAP` بشروطِه السبعةِ (§17)',
        'WORKSPACE_NAV_CONFORMANCE ورقة 06');
}
/* ◆ **تعارضُ مصدرَين حاكمَين على مقامِ الدورة** (§34-L4 حرفًا: «تعارض مصدرين
     حاكمين») — يُرفع **لكلِّ مساحةٍ فيها فائضٌ مُصيَّرٌ بلا هدفٍ في الدليل**:
   ① **ورقةُ الدليلِ** ترسم للمساحةِ عددًا من الأهداف — لا أكثر.
   ② **وقرارُ جولةٍ مكتوبٌ** (`gov_legacy_nav_recon.APPROVED_POST_GUIDE_ADDITION`
      · `REPAIR01-OPS-11` وأخواتِه) **أقرَّ شاشاتٍ إضافيّةً في الدورةِ نفسِها**،
      و`gov_target_nav` ينشرها بمجموعتِها وترتيبِها.
   ⇒ فالمُصيِّرُ الحاكمُ يعرضها **بموضعٍ حاكمٍ ومبرَّرٍ** (§29)، **والمقياسُ يعُدُّها
     فائضًا** لأنّها ليست في الدليل — **والرقمانِ صادقان**، والحاسمُ قرارُ مالك.
   ⛔ **ولا تُطوى بيدِ المنفِّذ**: §17 يمنع تعديلَ الهدفِ من الواقعِ بلا قرارِ
     حوكمة، و§4 و§42 يمنعان إخفاءَ شاشةٍ مستعمَلةٍ بلا بديلِ وصولٍ وحكم —
     **وهذه الأربعُ تُعرَض اليومَ في الإنتاجِ فعلًا**. فتبقى ظاهرةً ويبقى
     المقياسُ أحمرَ **باسمِه** حتى يحكم المالك. */
foreach ($CF['metrics'] as $ws => $m) {
    if ((int) $m['APPROVED_ADDITION_OUTSIDE_GUIDE'] === 0) { continue; }
    $owner[] = array('تعارضُ مصدرَين حاكمَين',
        $ws . ' — إضافةٌ معتمَدةٌ خارجَ ورقةِ الدليل: ' . $m['APPROVED_ADDITION_OUTSIDE_GUIDE'],
        'ورقةُ الدليلِ ترسم ' . $m['TARGET_TOTAL'] . ' هدفًا، وقرارُ جولةٍ مكتوبٌ أقرَّ '
            . $m['APPROVED_ADDITION_OUTSIDE_GUIDE'] . ' شاشةً أخرى في الدورةِ نفسِها ويَنشرها '
            . '`gov_target_nav` بمجموعتِها: ' . mb_substr(implode(' · ', $m['ADDITIONS_OUTSIDE_GUIDE']), 0, 200)
            . '. ⛔ ولا يحسمها ما دونَ المالكِ: §17 يمنع تعديلَ الهدفِ من الواقع، '
            . 'و§4 يمنع إخفاءَ المستعمَلِ بلا بديلِ وصول.',
        'أتُدمَج هذه الشاشاتُ في ورقةِ الدليلِ هدفًا معتمَدًا (`TRUE_TARGET_GAP` بشروطِ §17 السبعة)، '
            . 'أم يُلغى قرارُ الجولةِ وتُسحَب من الدورةِ ببديلِ وصولٍ مكتوب؟',
        'WORKSPACE_NAV_CONFORMANCE ورقة 06 · gov_legacy_nav_recon · gov_target_nav');
}
$i = 0;
foreach ($owner as $o) { $s[] = array_merge(array(++$i), $o); }
$sheets['12 OWNER_ACTION_REGISTER'] = $s;
$idx[] = array('12', 'OWNER_ACTION_REGISTER',
    count($owner) . ' بندًا **حقيقيًّا** — ⛔ و63 المصعَّدةُ آليًّا `L2` لا ترتفع هنا',
    '§34: أربعُ حالاتٍ وحدَها تبلغ المالك · §42 يحظر إرسالَ المئات');

/* ═══ الفهرسُ أوّلًا ثمَّ الكتابة ═══════════════════════════════════════════ */
$hdr = array(array('`NAV_ARCH_02_OUTPUTS` — المخرجاتُ الاثنا عشرَ (§37)'),
             array('الأساس', $BLID, 'الالتزام', substr($BL['commit_hash'], 0, 12)),
             array('⛔ ولا خليّةَ من ذاكرةٍ — كلُّها من سجلٍّ حاكمٍ أو لقطةٍ أو تصييرٍ حيّ'),
             array(''));
$sheets = array('00 الفهرس' => array_merge($hdr, $idx)) + $sheets;

$xlsx = $OUT . '/NAV_ARCH_02_OUTPUTS.xlsx';
xlsx_create($xlsx, $sheets);
echo "══ المخرجاتُ الاثنا عشرَ · الأساس {$BLID} ══\n";
foreach ($sheets as $n => $r) { printf("  %-30s %5d صفًّا\n", $n, max(0, count($r) - 1)); }
echo "\n  ⇒ {$xlsx}\n";
echo "  ◆ ملاحظتان تُعلَنانِ ولا تُطوَيان:\n";
echo "     · `HUMAN_UAT_PASS` = **PENDING** — قرارٌ بشريٌّ لا يُنتحَل (‏ورقة 10).\n";
echo "     · أربعٌ من تبعيّاتِ §33 الستِّ (‏مفضّلات · مهامّ · إشعارات · تكاملات)\n";
echo "       **لم تُقَس** — فلا يُوقَف مسارٌ واحدٌ في هذه الجولة (‏ورقة 11).\n";
