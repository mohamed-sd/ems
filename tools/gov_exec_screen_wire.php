<?php
/**
 * tools/gov_exec_screen_wire.php — التوصيلُ الخماسيُّ المعمَّمُ لأيِّ شاشةٍ جديدة (GOV_EXEC §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * تعميمُ نمطِ توصيلِ WH-03: سجلُّ الشاشاتِ ثم كونُ الأهدافِ ثم المظاهرُ
 * والدورةُ والقاموسُ المعياريُّ ومصفوفةُ UXUI ثم modules والصلاحياتُ (القفلانِ
 * الأوّلان + قوالبُ المساحةِ النافذةُ بنمطِ جارِ الشاشة) ودفترُ حالةِ الهدف.
 * المواصفةُ ملفُّ JSON — إعادةُ التشغيلِ آمنةٌ خطوةً خطوة.
 * التشغيل: php tools/gov_exec_screen_wire.php --spec=path/to/spec.json
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
date_default_timezone_set((string) ems_env('EMS_APP_TIMEZONE', 'Africa/Cairo'));
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$specPath = null;
foreach ($argv as $a) { if (strpos($a, '--spec=') === 0) { $specPath = substr($a, 7); } }
if ($specPath === null || !is_file($specPath)) { exit("⛔ --spec=<json> مطلوب\n"); }
$S = json_decode((string) file_get_contents($specPath), true);
foreach (array('route', 'file', 'label', 'group', 'grain', 'entity', 'dept', 'dept_ar', 'role_id',
               'owner_role_ar', 'guide_ref', 'sort_no', 'neighbor_route', 'stage_name', 'output_doc',
               'next_state', 'consumers', 'icon', 'level_name', 'purpose') as $k) {
    if (!isset($S[$k])) { exit("⛔ ناقصٌ في المواصفة: {$k}\n"); }
}
$SNAPH = trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));
$SNAP = 'SNAP-govexec-' . $SNAPH;
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r ? $r[0] : null; };
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
function gw_nz($s)
{
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', (string) $s);
    $s = str_replace('ى', 'ي', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = preg_replace('~[\x{064B}-\x{065F}\x{0640}]~u', '', $s);
    return preg_replace('~\s+~u', ' ', trim($s));
}

/* ═══ ① سجلُّ الشاشات ═══ */
$scr = $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '{$e($S['route'])}'");
if ($scr === null) {
    $mx = (int) $one("SELECT MAX(CAST(SUBSTRING(screen_id,5) AS UNSIGNED)) FROM repair01_screen_registry WHERE screen_id LIKE 'SCR-%'");
    $scr = sprintf('SCR-%04d', $mx + 1);
    $ok = $conn->query("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, visibility_class, visibility_rule,
         on_disk, origin, guard_kind, guard_evidence, src_ref, canonical_label_ar, surface_kind,
         ownership_verdict, permission_policy, grain_ar, grain_entity, grain_witness,
         verdict_rule, verdict_at, source_of_truth, sot_rule, sot_witness, sot_snapshot)
        VALUES ('{$e($scr)}', '{$e($S['file'])}', '{$e($S['route'])}', 'DISK_SCAN', '{$e($S['dept'])}',
         '{$e($S['owner_role_ar'])}', '{$e($S['guide_ref'])}', 'LIVE_REGISTERED', 'IN_GOV_SCREEN_CYCLE',
         'MENU_ITEM', 'NAV_ITEMS_ACTIVE', 1, 'BUILD', 'SELF_EARLY',
         'حارس صلاحية في الملف نفسه (enforce_current_page_view_permission)',
         'GOV_EXEC §5 — بناء بموضعه المعياري', '{$e($S['label'])}', 'SOURCE', 'DOMAIN_SOURCE',
         'DOMAIN_OWNER_PLUS_GRANTED', '{$e($S['grain'])}', '{$e($S['entity'])}',
         'الجدول {$e($S['entity'])} بحبته وقيوده في المخطط',
         '{$e($S['guide_ref'])} — الادارة المالكة {$e($S['dept_ar'])}', NOW(),
         '{$e($S['entity'])}', 'GUIDE_SOT_LINE', 'سطر المصدر في الورقة الحاكمة', '{$e($SNAP)}')");
    if (!$ok) { exit("⛔ سجل الشاشات: {$conn->error}\n"); }
    echo "① سجلُّ الشاشات: {$scr}\n";
} else { echo "① قائمٌ سلفًا: {$scr}\n"; }

/* ═══ ② كونُ الأهداف ═══ */
$norm = gw_nz($S['label']);
$exU = $one("SELECT target_uid FROM repair01_target_universe WHERE unit = '{$e($S['dept'])}' AND name_norm = '{$e($norm)}'");
if ($exU === null && isset($S['target_title'])) {
    $normT = gw_nz($S['target_title']);
    $exU = $one("SELECT target_uid FROM repair01_target_universe WHERE unit = '{$e($S['dept'])}' AND name_norm = '{$e($normT)}'");
    if ($exU !== null) { $norm = $normT; }
}
$tTitle = isset($S['target_title']) ? $S['target_title'] : $S['label'];
if ($exU === null) {
    $mx = (int) $one("SELECT MAX(CAST(SUBSTRING(target_uid,5) AS UNSIGNED)) FROM repair01_target_universe");
    $exU = sprintf('TGT-%04d', $mx + 1);
    $ok = $conn->query("INSERT INTO repair01_target_universe
        (target_uid, source, unit, name_ar, name_norm, screen_file, requirement_id, screen_id,
         match_method, match_witness, verdict, verdict_witness, verdict_snapshot, verdict_at)
        VALUES ('{$e($exU)}', 'BOTH', '{$e($S['dept'])}', '{$e($tTitle)}', '{$e(gw_nz($tTitle))}',
         '{$e($S['file'])}', '', '{$e($scr)}', 'EXACT_UNIT',
         'بني السطح باسم هدفه — {$e($S['guide_ref'])}', 'MATCHED',
         'بناء GOV_EXEC §5 بموضعه المعياري · لقطة {$e($SNAP)}', '{$e($SNAP)}', NOW())");
    if (!$ok) { exit("⛔ كون الاهداف: {$conn->error}\n"); }
    echo "② كونُ الأهداف: {$exU} MATCHED\n";
} else {
    $conn->query("UPDATE repair01_target_universe SET screen_file = '{$e($S['file'])}', screen_id = '{$e($scr)}',
        source = 'BOTH', match_method = 'EXACT_UNIT',
        match_witness = 'بني السطح باسم هدفه — {$e($S['guide_ref'])}', verdict = 'MATCHED',
        verdict_witness = 'بناء GOV_EXEC §5 بموضعه المعياري · لقطة {$e($SNAP)}',
        verdict_snapshot = '{$e($SNAP)}', verdict_at = NOW()
        WHERE target_uid = '{$e($exU)}' AND verdict IN ('NOT_BUILT','MERGED_INTO')");
    echo "② كونُ الأهداف: {$exU} " . ($conn->affected_rows ? 'MATCHED' : 'قائم') . "\n";
}

/* ═══ ③ المظاهر ═══ */
if ((int) $one("SELECT COUNT(*) FROM gov_space_appearances WHERE route = '{$e($S['route'])}'") === 0) {
    $ok = $conn->query("INSERT INTO gov_space_appearances
        (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
         src_class, src_ownership, src_decision, src_note, spaces_count, cls, ownership,
         decision, basis, rule_step, view_fields)
        SELECT COALESCE(MAX(id),0)+1, '{$e($S['dept_ar'])}', 'DEPARTMENT', '', '{$e($S['label'])}',
         '{$e($S['route'])}', '{$e($S['dept_ar'])}', 'BUSINESS_DEPARTMENT', 'GOV_EXEC', 'VALID',
         'CONFIRMED', 'شاشة بنيت بموضعها المعياري — {$e($S['guide_ref'])}', 1, 'OWNED', 'VALID',
         'CONFIRMED', 'المساحة هي الادارة المالكة للسطح في السجل المعياري ({$e($S['dept'])})', 1, ''
        FROM gov_space_appearances");
    if (!$ok) { exit("⛔ المظاهر: {$conn->error}\n"); }
    echo "③ المظاهر: OWNED\n";
} else { echo "③ المظاهرُ قائمة\n"; }

/* ═══ ④ دورةُ الشاشة ═══ */
if ((int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file = '{$e($S['file'])}'") === 0) {
    $ok = $conn->query("INSERT INTO gov_screen_cycle
        (id, company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
         screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact,
         stage_kind, screen_id, bridge_rule, bridge_witness, bridge_snapshot)
        SELECT COALESCE(MAX(id),0)+1, 0, '{$e($S['dept_ar'])}', '{$e($S['stage_name'])}', {$e((string) (int) $S['sort_no'])},
         '{$e($S['stage_name'])}', '{$e($S['group'])}', '{$e($S['label'])}', '{$e($S['file'])}',
         '{$e($S['guide_ref'])}', '{$e($S['output_doc'])}', '{$e($S['dept_ar'])}',
         '{$e($S['next_state'])}', '{$e($S['consumers'])}', 'لا', 'canonical', '{$e($scr)}',
         'BASENAME_UNIQUE',
         'C1 · اسمُ الملفِّ {$e($S['file'])} يُحلُّ إلى سطحٍ حيٍّ واحدٍ ({$e($scr)}) · لقطة {$e($SNAP)}',
         '{$e($SNAP)}'
        FROM gov_screen_cycle");
    if (!$ok) { exit("⛔ الدورة: {$conn->error}\n"); }
    echo "④ الدورة قُيّدت\n";
} else { echo "④ الدورةُ قائمة\n"; }

/* ═══ ⑤ القاموسُ المعياريّ ═══ */
if ((int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '{$e($S['route'])}'") === 0) {
    $ok = $conn->query("INSERT INTO nav_canonical
        (route, canonical_ar, level_no, level_name, group_name, sort_no, status, decision_state,
         application_state, decision_source, provisional_reversible, policy_domain)
        VALUES ('{$e($S['route'])}', '{$e($S['label'])}', 2, '{$e($S['level_name'])}', '{$e($S['group'])}',
         {$e((string) (int) $S['sort_no'])}, 'APPROVED', 'APPROVED', 'DEPLOYED',
         '{$e($S['guide_ref'])} — GOV_EXEC', 1, 'NAVIGATION_NAMING_POSITION')");
    if (!$ok) { exit("⛔ القاموس: {$conn->error}\n"); }
    echo "⑤ القاموس: APPROVED\n";
} else { echo "⑤ القاموسُ قائم\n"; }

/* ═══ ⑥ modules + الصلاحيات ═══ */
$mid = $one("SELECT id FROM modules WHERE code = '{$e($S['route'])}' LIMIT 1");
if ($mid === null) {
    $ok = $conn->query("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order, owner_dept_note)
        VALUES ('{$e($S['label'])}', '{$e($S['route'])}', {$e((string) (int) $S['role_id'])}, 0, 0,
                '{$e($S['icon'])}', {$e((string) (int) $S['sort_no'])}, '{$e($S['dept'])}')");
    if (!$ok) { exit("⛔ modules: {$conn->error}\n"); }
    $mid = $conn->insert_id;
}
$mid = (int) $mid;
echo "⑥أ modules: {$mid}\n";
if ((int) $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = " . (int) $S['role_id'] . " AND module_id = $mid") === 0) {
    $ok = $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
        VALUES (" . (int) $S['role_id'] . ", $mid, 1, 1, 1, 0)");
    if (!$ok) { exit("⛔ role_permissions: {$conn->error}\n"); }
    echo "⑥ب صلاحياتُ الدور: عرضٌ وإضافةٌ وتحرير\n";
} else { echo "⑥ب الصلاحياتُ قائمة\n"; }
/* قوالبُ المساحةِ النافذةُ بنمطِ الجار */
$q = $conn->query("SELECT gpi.profile_id, gpi.allow, gpi.can_add, gpi.can_edit, gpi.can_delete
    FROM gov_profile_items gpi JOIN gov_role_profiles grp ON grp.profile_id = gpi.profile_id
    WHERE gpi.item_ref = '{$e($S['neighbor_route'])}' AND grp.state = 'active'");
$np = 0;
while ($q && ($x = $q->fetch_assoc())) {
    $pid = (int) $x['profile_id'];
    $ex = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id = $pid AND item_kind = 'screen' AND item_ref = '{$e($S['route'])}'");
    if ($ex > 0) { continue; }
    $iid = (int) $one("SELECT MAX(item_id) FROM gov_profile_items") + 1;
    $ok = $conn->query("INSERT INTO gov_profile_items
        (item_id, company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
        VALUES ($iid, 0, $pid, 'screen', '{$e($S['route'])}', {$x['allow']}, {$x['can_add']}, {$x['can_edit']}, {$x['can_delete']}, 'GOV_EXEC — نمط جار الشاشة')");
    if (!$ok) { exit("⛔ قالب {$pid}: {$conn->error}\n"); }
    $np++;
}
/* وقوالبُ مستخدمي الدورِ النافذةُ أنفسُها — جارُ الشاشةِ قد يسكن قوالبَ غيرِ
   قالبِ مستخدمِ المساحةِ الحيِّ (درسُ FIN-G3: المستخدمُ المغطّى يُحكم بقالبِه حصرًا) */
$q = $conn->query("SELECT DISTINCT g.profile_id FROM users u
    JOIN gov_authority_grants g ON g.user_id = u.id AND g.revoked_at IS NULL
        AND (g.valid_to IS NULL OR g.valid_to > NOW())
    JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
    WHERE u.role = '" . (int) $S['role_id'] . "'");
while ($q && ($x = $q->fetch_assoc())) {
    $pid = (int) $x['profile_id'];
    $ex = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id = $pid AND item_kind = 'screen' AND item_ref = '{$e($S['route'])}'");
    if ($ex > 0) { continue; }
    $tpl = $conn->query("SELECT allow, can_add, can_edit FROM gov_profile_items
        WHERE profile_id = $pid AND item_kind = 'screen' AND allow = 1 LIMIT 1");
    $tv = $tpl ? $tpl->fetch_assoc() : null;
    if ($tv === null) { continue; }
    $iid = (int) $one("SELECT MAX(item_id) FROM gov_profile_items") + 1;
    $ok = $conn->query("INSERT INTO gov_profile_items
        (item_id, company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
        VALUES ($iid, 0, $pid, 'screen', '{$e($S['route'])}', {$tv['allow']}, {$tv['can_add']}, {$tv['can_edit']}, 0, 'GOV_EXEC — قالب مستخدم الدور النافذ')");
    if (!$ok) { exit("⛔ قالب مستخدم {$pid}: {$conn->error}\n"); }
    $np++;
}
echo "⑥ج قوالبُ نافذة: +{$np}\n";

/* ═══ ⑦ مصفوفةُ UXUI ═══ */
$csv = $ROOT . '/docs/uxui_matrix_20260818.csv';
$body = (string) file_get_contents($csv);
if (mb_strpos($body, $S['route']) === false) {
    $maxN = 0;
    foreach (explode("\n", $body) as $line) {
        if (preg_match('~^(\d+),~', $line, $m)) { $maxN = max($maxN, (int) $m[1]); }
    }
    $n = $maxN + 1;
    $row = $n . ',' . $S['route'] . ',"' . $S['label'] . '","' . $S['label'] . '","",—,"'
        . $S['purpose'] . '","' . $S['dept_ar'] . '","2 — العمليات","' . $S['group'] . '",'
        . (int) $S['sort_no'] . ',"شاشةٌ مستقلة",1,"' . $S['consumers'] . '","بناء GOV_EXEC بموضعه المعياري",APPROVED,"'
        . $S['guide_ref'] . '",—,—,ACTIVE,—,"' . $S['label'] . '","' . $S['group'] . '","موضعُه من دورةِ العمل — قرارُ الورقة","' . $S['group'] . '"';
    if (substr($body, -1) !== "\n") { $body .= "\n"; }
    file_put_contents($csv, $body . $row . "\n");
    echo "⑦ المصفوفة: صف {$n}\n";
} else { echo "⑦ المصفوفةُ تحمل المسار\n"; }

echo "═ التوصيلُ تامّ — أعد الاستيرادَ والربطَ والقياس ═\n";
