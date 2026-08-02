<?php
/**
 * tools/seed_org_assignments.php — بذرُ التكليفات التنظيمية الحية (ORG-01 v4 §8)
 * ───────────────────────────────────────────────────────────────────────────
 * القبول: «صفرُ موقعٍ بلا مديرِ موقعٍ نشط» و«صفرُ تكليفٍ موقعيٍّ بخطِّ تبعيةٍ
 * واحد» — مديرُ موقعٍ لكل موقعٍ نشطٍ (بخطّيه ونائبه وقدراته وسطرِ تدقيقه)
 * والمديرون المركزيون الخمسة. آمنُ الإعادة.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$OPS = 16;    // مسؤول تشغيل ثانٍ (مصدر القرار: مدير التشغيل)
$run = function ($sql) use ($conn) {
    if (!mysqli_query($conn, $sql)) { fwrite(STDERR, "✘ " . mysqli_error($conn) . "\n" . mb_substr($sql, 0, 140) . "\n"); exit(1); }
    return mysqli_insert_id($conn);
};

/* المديرون المركزيون الخمسة — أشخاصُهم من حسابات الأدوار المختصة */
$central = array(
    array('maintenance_mgr', 58, 9),   // مدير صيانة (دور 7)
    array('operators_mgr',   7, 10),   // مدير مشغلين (الموارد التشغيلية)
    array('procurement_ops_mgr', 71, 11),  // مدير مشتريات (دور 16)
    array('warehouse_mgr',   72, 12),  // مدير مخازن
    array('transport_mgr',   73, 13),  // مدير نقل
);
$made = 0;
foreach ($central as $cx) {
    list($type, $person, $unit) = $cx;
    $r = mysqli_query($conn, "SELECT 1 FROM org_assignments WHERE assignment_type_code = '$type' AND state = 'active' LIMIT 1");
    if ($r && mysqli_num_rows($r)) continue;
    $asg = $run("INSERT INTO org_assignments (person_id, assignment_type_code, org_unit_id, scope_type, scope_id,
                    valid_from, valid_to, decided_by_person_id, decision_ref, deputy_person_id, state)
                 VALUES ($person, '$type', $unit, 'site_group', 0, '2026-08-01', '2027-07-31', $OPS,
                         'OPS-2026-1" . sprintf('%02d', $made) . "', NULL, 'active')");
    $run("INSERT INTO assignment_capabilities (asg_id, capability_code, scope_limit_json, amount_cap, currency)
          VALUES ($asg, '$type.full', '{\"sites\": \"all\"}', NULL, NULL)"); // السقف التشغيلي نطاقي لا نقدي
    $run("INSERT INTO assignment_reporting_lines (asg_id, line_type, reports_to_assignment_id, valid_from)
          VALUES ($asg, 'operational', $asg, '2026-08-01')");
    $run("INSERT INTO assignment_audit (asg_id, action, reason, before_json, after_json, by_person_id)
          VALUES ($asg, 'created', 'بذرُ الهيكل الحي — ORG-01 §8', NULL, '{\"type\": \"$type\", \"person\": $person}', $OPS)");
    $made++;
}
echo "المركزيون: +$made\n";

/* مديرُ موقعٍ نشطٌ لكل موقعٍ له حاويات — بخطين اثنين ونائبٍ وسقفٍ نطاقي */
$sites = array();
$r = mysqli_query($conn, "SELECT DISTINCT project_id FROM op_containers WHERE is_deleted = 0 AND project_id IS NOT NULL AND project_id > 0");
while ($x = mysqli_fetch_row($r)) $sites[] = intval($x[0]);
$siteMgrs = array(41, 8, 9, 10, 11);   // حسابات مديري المواقع (دور 5/6)
$made = 0; $k = 0;
foreach ($sites as $site) {
    $r = mysqli_query($conn, "SELECT 1 FROM org_assignments WHERE assignment_type_code = 'site_manager'
                              AND scope_type = 'site' AND scope_id = $site AND state = 'active' LIMIT 1");
    if ($r && mysqli_num_rows($r)) { $k++; continue; }
    $person = $siteMgrs[$k % count($siteMgrs)];
    $deputy = $siteMgrs[($k + 1) % count($siteMgrs)];
    $asg = $run("INSERT INTO org_assignments (person_id, assignment_type_code, org_unit_id, scope_type, scope_id,
                    valid_from, valid_to, decided_by_person_id, decision_ref, deputy_person_id, state)
                 VALUES ($person, 'site_manager', 8, 'site', $site, '2026-08-01', '2027-07-31', $OPS,
                         'OPS-2026-2" . sprintf('%02d', $k) . "', $deputy, 'active')");
    foreach (array('unit.chain.approve.link1', 'daily_plan.submit', 'state_change.request', 'site.entry.approve') as $cap) {
        $run("INSERT INTO assignment_capabilities (asg_id, capability_code, scope_limit_json, amount_cap, currency)
              VALUES ($asg, '$cap', '{\"sites\": [$site]}', NULL, NULL)");
    }
    // الخطان إلزاميان؛ والإشارةُ الذاتيةُ تعني قمةَ السلسلة (مديرُ التشغيل بلا صف تكليفٍ — يُكلِّف ولا يُكلَّف)
    $run("INSERT INTO assignment_reporting_lines (asg_id, line_type, reports_to_assignment_id, valid_from)
          VALUES ($asg, 'operational', $asg, '2026-08-01')");
    $run("INSERT INTO assignment_reporting_lines (asg_id, line_type, reports_to_assignment_id, valid_from)
          VALUES ($asg, 'functional', $asg, '2026-08-01')");
    $run("INSERT INTO assignment_audit (asg_id, action, reason, before_json, after_json, by_person_id)
          VALUES ($asg, 'created', 'تكليفُ مدير موقعٍ — واحدٌ لكل موقعٍ نشط', NULL,
                  '{\"site\": $site, \"person\": $person, \"deputy\": $deputy}', $OPS)");
    $made++; $k++;
}
echo "مديرو المواقع: +$made (المواقع النشطة: " . count($sites) . ")\n";

/* القبول يقاس */
$r = mysqli_query($conn, "SELECT COUNT(*) FROM (SELECT DISTINCT oc.project_id FROM op_containers oc
    WHERE oc.is_deleted = 0 AND oc.project_id > 0
    AND NOT EXISTS (SELECT 1 FROM org_assignments a WHERE a.assignment_type_code = 'site_manager'
        AND a.scope_type = 'site' AND a.scope_id = oc.project_id AND a.state = 'active')) x");
echo "مواقعُ نشطةٌ بلا مدير (يجب 0): " . mysqli_fetch_row($r)[0] . "\n";
$r = mysqli_query($conn, "SELECT COUNT(*) FROM org_assignments a
    JOIN org_assignment_types t ON t.type_code = a.assignment_type_code AND t.level = 'site'
    WHERE a.state = 'active'
    AND (SELECT COUNT(DISTINCT line_type) FROM assignment_reporting_lines l WHERE l.asg_id = a.asg_id) < 2");
echo "موقعيٌّ بخطٍّ واحد (يجب 0): " . mysqli_fetch_row($r)[0] . "\n";
