<?php
/**
 * tools/gov_exec_wh03_wire.php — توصيلُ شاشةِ «إسناد أمناء المخازن» بسجلّاتِها كلِّها
 * ═══════════════════════════════════════════════════════════════════════════
 * البندُ الجديدُ يفتح سجلّاتِه كلَّها لا رابطًا واحدًا (نمطُ CTL الخماسيُّ +
 * الأقفالُ الأربعة): سجلُّ الشاشاتِ ثم كونُ الأهدافِ ثم المظاهرُ والدورةُ
 * والقاموسُ المعياريُّ ومصفوفةُ UXUI ثم modules والصلاحياتُ (role_permissions
 * لدورِ DEP-17 وقوالبُ gov_profile_items النافذةُ بنمطِ جارِها حرفًا) ثم
 * دفترُ W9. إعادةُ التشغيلِ آمنة — كلُّ خطوةٍ تتحقّق قبل أن تكتب.
 * التشغيل: php tools/gov_exec_wh03_wire.php
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
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ROUTE = 'Procurement/wh_custodians.php';
$FILE  = 'wh_custodians.php';
$LABEL = 'إسناد أمناء المخازن';
$GROUP = 'التأسيس المرجعي';
$GRAIN = 'مخزن × شخص × فترة إسناد — Child Register';
$SNAPH = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$SNAP  = 'SNAP-govexec-' . $SNAPH;
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r ? $r[0] : null; };
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ═══ ① سجلُّ الشاشات ═══ */
$scr = $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '{$e($ROUTE)}'");
if ($scr === null) {
    $mx = (int) $one("SELECT MAX(CAST(SUBSTRING(screen_id,5) AS UNSIGNED)) FROM repair01_screen_registry WHERE screen_id LIKE 'SCR-%'");
    $scr = sprintf('SCR-%04d', $mx + 1);
    $parent = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE screen_file = 'warehouses.php' LIMIT 1");
    $ok = $conn->query("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, src_ref, canonical_label_ar, surface_kind,
         ownership_verdict, permission_policy, grain_ar, grain_entity, grain_witness,
         verdict_rule, verdict_at, source_of_truth, sot_rule, sot_witness, sot_snapshot)
        VALUES ('{$e($scr)}', '{$e($FILE)}', '{$e($ROUTE)}', 'DISK_SCAN', 'DEP-17', 'أمين المخزن',
         'GUIDE3:ورقة 17 · الشاشة 3 · Child of خ02', 'LIVE_REGISTERED', 'IN_GOV_SCREEN_CYCLE',
         '{$e($parent)}', 'CHILD_REGISTER_OF_WAREHOUSES', 'MENU_ITEM', 'NAV_ITEMS_ACTIVE',
         1, 'BUILD', 'SELF_EARLY', 'حارس صلاحية في الملف نفسه (enforce_current_page_view_permission)',
         'الحزمة -3 · GOV_EXEC محور 1', '{$e($LABEL)}', 'SOURCE', 'DOMAIN_SOURCE',
         'DOMAIN_OWNER_PLUS_GRANTED', '{$e($GRAIN)}', 'proc_wh_custodian',
         'الجدول proc_wh_custodian بحبة مخزن × شخص × فترة — قيود الفترة والاقفال في المخطط',
         'الدليل -3 · ورقة 17 · الشاشة 3 — الإدارة المالكة إدارة المخازن', NOW(),
         'proc_wh_custodian', 'GUIDE_SOT_LINE',
         'سطر المصدر في الورقة: الإسناد سجل تابع بفترته والأمين النافذ اليوم يشتق منه ولا يكتب يدويا',
         '{$e($SNAP)}')");
    if (!$ok) { exit("⛔ سجل الشاشات: {$conn->error}\n"); }
    echo "① سجلُّ الشاشات: {$scr}\n";
} else { echo "① قائمٌ سلفًا: {$scr}\n"; }

/* ═══ ② كونُ الأهداف: WH-03 صار مبنيًّا مطابقًا ═══ */
$conn->query("UPDATE repair01_target_universe
    SET screen_file = '{$e($FILE)}', screen_id = '{$e($scr)}', source = 'BOTH',
        match_method = 'EXACT_UNIT',
        match_witness = 'اسمٌ مطبَّعٌ يطابق سطحًا حيًّا: {$e($scr)} «{$e($LABEL)}»',
        verdict = 'MATCHED',
        verdict_witness = 'بُنيت الشاشةُ بأمرِ GOV_EXEC محور 1 — مطابقةٌ تامّةٌ بالاسمِ المطبَّع · لقطة {$e($SNAP)}',
        verdict_snapshot = '{$e($SNAP)}', verdict_at = NOW()
    WHERE requirement_id = 'WH-03' AND verdict = 'NOT_BUILT'");
echo '② كونُ الأهداف: ' . ($conn->affected_rows > 0 ? 'MATCHED' : 'كان مطابقًا سلفًا') . "\n";
$conn->query("UPDATE repair01_requirements SET amd01_state = 'IMPLEMENTED_NOT_VERIFIED',
    state_evidence = 'بُنيت: الجدول proc_wh_custodian والشاشة {$e($ROUTE)} بفعلَيها المسجَّلَين — والتثبيت بجولة تصيير ومقارنة',
    state_snapshot = '{$e($SNAP)}', state_at = NOW()
    WHERE requirement_id = 'WH-03' AND amd01_state = 'NOT_IMPLEMENTED'");

/* ═══ ③ المظاهر (القفل الرابع) ═══ */
if ((int) $one("SELECT COUNT(*) FROM gov_space_appearances WHERE route = '{$e($ROUTE)}'") === 0) {
    $mx = (int) $one("SELECT MAX(id) FROM gov_space_appearances");
    $id = $mx + 1;
    $ok = $conn->query("INSERT INTO gov_space_appearances
        (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
         src_class, src_ownership, src_decision, src_note, spaces_count, cls, ownership,
         decision, basis, rule_step, view_fields)
        VALUES ($id, 'إدارة المخازن', 'DEPARTMENT', '', '{$e($LABEL)}', '{$e($ROUTE)}',
         'إدارة المخازن', 'BUSINESS_DEPARTMENT', 'GOV_EXEC', 'VALID', 'CONFIRMED',
         'شاشةُ الحزمةِ -3 الجديدةُ — بُنيت بأمرِ GOV_EXEC في موضعِها المعياريّ',
         1, 'OWNED', 'VALID', 'CONFIRMED',
         'المساحةُ هي الإدارةُ المالكةُ للسطحِ في السجلِّ المعياريّ (DEP-17)', 1, '')");
    if (!$ok) { exit("⛔ المظاهر: {$conn->error}\n"); }
    echo "③ المظاهر: صف $id (OWNED)\n";
} else { echo "③ المظاهرُ قائمة\n"; }

/* ═══ ④ دورةُ الشاشة ═══ */
if ((int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file = '{$e($FILE)}'") === 0) {
    $mx = (int) $one("SELECT MAX(id) FROM gov_screen_cycle");
    $id = $mx + 1;
    $ok = $conn->query("INSERT INTO gov_screen_cycle
        (id, company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
         screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact,
         stage_kind, screen_id, bridge_rule, bridge_witness, bridge_snapshot)
        VALUES ($id, 0, 'إدارة المخازن', '{$e($GROUP)}', 3, '{$e($GROUP)}', '{$e($GROUP)}',
         '{$e($LABEL)}', '{$e($FILE)}', 'متطلبات: WH-03', 'سطر إسناد أمين بفترته ونطاقه',
         'إدارة المخازن', 'تصفية كل حركة بنطاق أمينها', 'المخازن والحوكمة', 'لا',
         'canonical', '{$e($scr)}', 'BASENAME_UNIQUE',
         'C1 · اسمُ الملفِّ {$e($FILE)} يُحلُّ إلى سطحٍ حيٍّ واحدٍ لا غير ({$e($scr)}) ⇒ الوصلُ بالمعرِّفِ لا بالاسم · لقطة {$e($SNAP)}',
         '{$e($SNAP)}')");
    if (!$ok) { exit("⛔ الدورة: {$conn->error}\n"); }
    echo "④ الدورة: صف $id\n";
} else { echo "④ الدورةُ قائمة\n"; }

/* ═══ ⑤ القاموسُ المعياريّ ═══ */
if ((int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '{$e($ROUTE)}'") === 0) {
    $ok = $conn->query("INSERT INTO nav_canonical
        (route, canonical_ar, level_no, level_name, group_name, sort_no, status, decision_state,
         application_state, decision_source, provisional_reversible, policy_domain)
        VALUES ('{$e($ROUTE)}', '{$e($LABEL)}', 2, 'العمليات', '{$e($GROUP)}', 3, 'APPROVED',
         'APPROVED', 'DEPLOYED', 'الدليل المعماري -3 · ورقة 17 · الشاشة 3 — GOV_EXEC (2026-09-01)',
         1, 'NAVIGATION_NAMING_POSITION')");
    if (!$ok) { exit("⛔ القاموس: {$conn->error}\n"); }
    echo "⑤ القاموسُ المعياريّ: APPROVED\n";
} else { echo "⑤ القاموسُ قائم\n"; }

/* ═══ ⑥ modules + الصلاحيات (الأقفالُ ①②) ═══ */
$mid = $one("SELECT id FROM modules WHERE code = '{$e($ROUTE)}' LIMIT 1");
if ($mid === null) {
    $ok = $conn->query("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order, owner_dept_note)
        VALUES ('{$e($LABEL)}', '{$e($ROUTE)}', 16, 0, 0, 'fa fa-user-shield', 3, 'DEP-17')");
    if (!$ok) { exit("⛔ modules: {$conn->error}\n"); }
    $mid = $conn->insert_id;
    echo "⑥أ modules: $mid\n";
} else { echo "⑥أ modules قائم: $mid\n"; }
$mid = (int) $mid;
if ((int) $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = 25 AND module_id = $mid") === 0) {
    $ok = $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
        VALUES (25, $mid, 1, 1, 1, 0)");
    if (!$ok) { exit("⛔ role_permissions: {$conn->error}\n"); }
    echo "⑥ب صلاحياتُ دورِ المخازن (25): عرضٌ وإضافةٌ وتحرير\n";
} else { echo "⑥ب الصلاحياتُ قائمة\n"; }
/* قوالبُ DEP-17 النافذة — بنمطِ جارِ الشاشةِ (wh_hazmat) حرفًا */
$q = $conn->query("SELECT gpi.profile_id, gpi.allow, gpi.can_add, gpi.can_edit, gpi.can_delete
    FROM gov_profile_items gpi JOIN gov_role_profiles grp ON grp.profile_id = gpi.profile_id
    WHERE gpi.item_ref = 'Procurement/wh_hazmat.php' AND grp.state = 'active'");
$np = 0;
while ($q && ($x = $q->fetch_assoc())) {
    $pid = (int) $x['profile_id'];
    $ex = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id = $pid AND item_kind = 'screen' AND item_ref = '{$e($ROUTE)}'");
    if ($ex > 0) { continue; }
    $iid = (int) $one("SELECT MAX(item_id) FROM gov_profile_items") + 1;
    $ok = $conn->query("INSERT INTO gov_profile_items
        (item_id, company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
        VALUES ($iid, 0, $pid, 'screen', '{$e($ROUTE)}', {$x['allow']}, {$x['can_add']}, {$x['can_edit']}, {$x['can_delete']}, 'GOV_EXEC — نمط جار الشاشة wh_hazmat')");
    if (!$ok) { exit("⛔ قالب $pid: {$conn->error}\n"); }
    $np++;
}
echo "⑥ج قوالبُ نافذة: +$np صفًّا\n";

/* ═══ ⑦ دفترُ W9 ═══ */
if ((int) $one("SELECT COUNT(*) FROM repair01_w9_scope WHERE requirement_id = 'WH-03'") === 0) {
    $srcRef = (string) $one("SELECT src_ref FROM repair01_requirements WHERE requirement_id = 'WH-03'");
    $ok = $conn->query("INSERT INTO repair01_w9_scope
        (requirement_id, unit, group_name, surface, anchor_screen_id, anchor_route, anchor_probe,
         owner_measured, owner_expected, owner_verdict, build_verdict, map_rule, map_why, src_ref)
        VALUES ('WH-03', '17 إدارة المخازن', '{$e($GROUP)}', '{$e($LABEL)}', '{$e($scr)}',
         '{$e($ROUTE)}', 'proc_wh_custodian', 'DEP-17', 'DEP-17', 'MATCH', 'BUILT_GOVEXEC',
         'GOVEXEC_ROUTE_TOUCHES_TABLE',
         'اسناد امناء المخازن — سجل تابع بفترته والنافذ اليوم يشتق منه لا يكتب', '{$e($srcRef)}')");
    if (!$ok) { exit("⛔ دفتر W9: {$conn->error}\n"); }
    echo "⑦ دفترُ W9: قُيّد\n";
} else { echo "⑦ دفترُ W9 قائم\n"; }

/* ═══ ⑧ مصفوفةُ UXUI ═══ */
$csv = $ROOT . '/docs/uxui_matrix_20260818.csv';
$body = file_get_contents($csv);
if (mb_strpos($body, $ROUTE) === false) {
    $maxN = 0;
    foreach (explode("\n", $body) as $line) {
        if (preg_match('~^(\d+),~', $line, $m)) { $maxN = max($maxN, (int) $m[1]); }
    }
    $n = $maxN + 1;
    $row = $n . ',' . $ROUTE . ',"' . $LABEL . '","' . $LABEL . '","",—,'
        . '"إسناد أمناء المخازن بفتراتهم من الدليل -3 (ورقة 17 · الشاشة 3): المخزن والشخص ونوع الإسناد وتاريخاه ونطاق صلاحيته — أساس تصفية كل حركة بنطاق أمينها.",'
        . '"إدارة المخازن","2 — العمليات","' . $GROUP . '",3,"شاشةٌ مستقلة",1,"المخازن والحوكمة",'
        . '"شاشةُ الحزمةِ -3 الجديدةُ بُنيت في موضعِها المعياريّ",APPROVED,"الدليل -3 · GOV_EXEC",—,—,ACTIVE,—,'
        . '"' . $LABEL . '","' . $GROUP . '","موضعُه من دورةِ العمل — قرارُ الورقة","' . $GROUP . '"';
    if (substr($body, -1) !== "\n") { $body .= "\n"; }
    file_put_contents($csv, $body . $row . "\n");
    echo "⑧ المصفوفة: صف $n\n";
} else { echo "⑧ المصفوفةُ تحمل المسار\n"; }

echo "═ التوصيلُ تامّ ═\n";
