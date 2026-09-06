<?php
/**
 * tools/gov_round_check_wire.php — توصيلُ شاشةِ «فحص جاهزية الالتزام»
 * ═══════════════════════════════════════════════════════════════════════════
 * **السطحُ المُصيَّرُ يحتاج أربعةَ سجلّاتٍ** ([[round-closeout-before-commit]]):
 * `repair01_screen_registry` (‏`RP-01`) · `gov_screen_cycle` (‏`RP-01`+`RP-02`)
 * · `docs/uxui_matrix_20260818.csv` (‏`U1`) · `gov_space_appearances` (‏`NF-24`)
 * — **وموضعٌ نشطٌ في `nav_workspace_placements` وصفٌّ في `nav_items`** كي تُصيَّر.
 *
 * ◆ **والمجموعةُ 1360** (إدارةُ الصلاحياتِ والأدوار) مستثناةٌ من حدِّ `U9`
 *   بنصِّ ورقةِ الدليل، فالإضافةُ إليها لا تُشعل الحاجب.
 *
 * التشغيل: php tools/gov_round_check_wire.php [--apply]
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

$FILE  = 'Governance/round_check.php';
$ROUTE = 'governance/round_check.php';
$LABEL = 'فحص جاهزية الالتزام';
$DEPT  = 'DEP-08';
$DEPTA = 'إدارة الصلاحيات';
$GRP   = 1360;
$SRC   = 'GOV-CHK-01 — شاشةُ فحصٍ تُسمّي الحارسَ الراسبَ بدل رسالةٍ عامّة';
if (!is_file($ROOT . '/' . $FILE)) { exit("⛔ الملفُّ مفقود: {$FILE}\n"); }

$steps = array(); $did = 0;

/* ═══ ① سجلُّ الشاشات ═══ */
$q = $conn->query("SELECT screen_id FROM repair01_screen_registry WHERE LOWER(route)='{$e($ROUTE)}'");
if ($q && $q->num_rows) { $SCR = $q->fetch_row()[0]; $steps[] = "① سجلُّ الشاشات: قائمٌ {$SCR}"; }
else {
    $q = $conn->query("SELECT MAX(CAST(SUBSTRING(screen_id,5) AS UNSIGNED)) FROM repair01_screen_registry WHERE screen_id LIKE 'SCR-%'");
    $SCR = sprintf('SCR-%04d', (int) $q->fetch_row()[0] + 1);
    $steps[] = "① سجلُّ الشاشات: يُنشأ {$SCR}";
    if ($APPLY) {
        $ok = $conn->query("INSERT INTO repair01_screen_registry
            (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
             lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
             on_disk, origin, ghost_verdict, ghost_why, guard_kind, guard_evidence, w2_why, src_ref,
             updated_at, canonical_label_ar, surface_kind, ownership_verdict, action_guard,
             permission_policy, grain_ar, grain_entity, grain_cardinality, grain_measured,
             grain_rule, grain_witness, grain_multi, source_of_truth, state_model_ref,
             finance_debt_class, debt_owner, debt_wave, verdict_rule, verdict_at,
             sot_rule, sot_witness, sot_snapshot, grain_tier, grain_fact_scope)
            VALUES ('{$e($SCR)}','{$e($FILE)}','{$e($ROUTE)}','NAV_WORKSPACE_PLACEMENT',
             '{$e($DEPT)}','{$e($DEPTA)}','NAV_WORKSPACE_PLACEMENT','LIVE_REGISTERED','ACTIVE_PLACEMENT','','',
             'MENU_ITEM','NAV_WORKSPACE_PLACEMENT',1,'BUILD','','','حارس صلاحية في الملف نفسه','',
             '{$e($SRC)}','nav_workspace_placements','" . date('Y-m-d H:i:s') . "',
             '{$e($LABEL)}','SOURCE','DOMAIN_SOURCE','','DOMAIN_OWNER_PLUS_GRANTED','','','NONE','',
             '','',0,'','','','','','{$e($SRC)}','" . date('Y-m-d H:i:s') . "','','','','NONE','NONE')");
        if (!$ok) { exit("⛔ ①: {$conn->error}\n"); }
        $did++;
    }
}

/* ═══ ② دفترُ الدورة ═══ */
$q = $conn->query("SELECT id FROM gov_screen_cycle WHERE screen_file='{$e($FILE)}' OR screen_file LIKE '%/round_check.php'");
if ($q && $q->num_rows) { $steps[] = '② دفترُ الدورة: قائم'; }
else {
    $steps[] = '② دفترُ الدورة: يُنشأ';
    if ($APPLY) {
        $ok = $conn->query("INSERT INTO gov_screen_cycle
            (company_id, dept_name, layer_name, stage_order, stage_name, group_name,
             screen_title, screen_file, inputs_note, output_doc, resp_role, next_state,
             consumers, fin_impact, stage_kind, screen_id, bridge_rule)
            VALUES (0,'{$e($DEPTA)}','المرجع والإدارة','6','الحوكمة والضوابط','إدارة الصلاحيات والأدوار',
             '{$e($LABEL)} (round_check.php)','{$e($FILE)}','مخرج tools/round_closeout.php',
             'حكم كل حارس وسبب رسوبه','{$e($DEPTA)}','قراءة فقط — لا تصلح ولا تكتب',
             'كل من يلتزم','لا','canonical','{$e($SCR)}','BASENAME_UNIQUE')");
        if (!$ok) { exit("⛔ ②: {$conn->error}\n"); }
        $did++;
    }
}

/* ═══ ③ سجلُّ تصنيفِ المساحات — والمسارُ خارجَه مفتوحٌ افتراضًا ═══ */
$q = $conn->query("SELECT id FROM gov_space_appearances WHERE route='{$e($FILE)}' OR route LIKE '%/round_check.php'");
if ($q && $q->num_rows) { $steps[] = '③ تصنيفُ المساحات: قائم'; }
else {
    $steps[] = '③ تصنيفُ المساحات: يُنشأ';
    if ($APPLY) {
        $q = $conn->query('SELECT COALESCE(MAX(id),0)+1 FROM gov_space_appearances');
        $nid = (int) $q->fetch_row()[0];
        $ok = $conn->query("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ({$nid},'{$e($DEPTA)}','CONTROL','','{$e($LABEL)}','{$e($FILE)}','{$e($DEPTA)}',
             'PLATFORM_SHARED','GOV-CHK-01','VALID','CONFIRMED','{$e($SRC)}',1,
             'OWNED','VALID','CONFIRMED','شاشة فحص للحراس — قراءة فقط ومالكها الدور 15',1,'',NOW())");
        if (!$ok) { exit("⛔ ③: {$conn->error}\n"); }
        $did++;
    }
}

/* ═══ ④ موضعٌ نشطٌ في مساحةِ العمل ═══ */
$q = $conn->query("SELECT placement_id FROM nav_workspace_placements WHERE route LIKE '%round_check%'");
if ($q && $q->num_rows) { $steps[] = '④ موضعُ مساحةِ العمل: قائم'; }
else {
    $steps[] = "④ موضعُ مساحةِ العمل: يُنشأ في المجموعة {$GRP}";
    if ($APPLY) {
        $q = $conn->query("SELECT COALESCE(MAX(CAST(sort_no AS SIGNED)),0)+1 FROM nav_workspace_placements WHERE workspace_id='{$e($DEPT)}'");
        $sort = (int) $q->fetch_row()[0];
        $pid = 'WP-' . strtoupper(substr(md5($ROUTE . microtime(true)), 0, 16));
        $ok = $conn->query("INSERT INTO nav_workspace_placements
            (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
             canonical_label, governing_source, source_ref, reason_code, status, version, created_by, created_at, updated_at)
            VALUES ('{$e($pid)}','{$e($SCR)}','{$e($DEPT)}',{$GRP},'DEPARTMENT',{$sort},'governance/round_check',
             '{$e($LABEL)}','{$e($SRC)}','GOV-CHK-01','GOV_CHK_SCREEN','ACTIVE',1,'GOV-CHK-01',NOW(),NOW())");
        if (!$ok) { exit("⛔ ④: {$conn->error}\n"); }
        $did++;
    }
}

foreach ($steps as $s) { echo "  {$s}\n"; }
printf("\nمعرِّفُ الشاشة: %s · خطواتٌ نُفِّذت: %d\n", $SCR, $did);
echo $APPLY ? "\n⚠ وبقي صفُّ المصفوفةِ (`docs/uxui_matrix_20260818.csv`) وصفُّ `nav_items`"
            . " — انظر `tools/perm01_matrix_rows.php` نموذجًا.\n"
            : "\nقياسٌ فقط — أعِد بـ`--apply`\n";
