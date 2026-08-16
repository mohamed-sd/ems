<?php
/**
 * 2027_05_08_gate_residue_close.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تخضيرُ بوابةِ الدمجِ — أربعةُ رواسبَ قاست فاحصاتُها فرسبت
 *
 * ① wfm④: 20 صفَّ إنجازٍ `source_kind` فيها **نصُّ ملاحظةِ UAT** («بانتظار
 *   اعتماد الإدارة…») لا رمزَ مصدرٍ — عينُ عائلةِ عطبِ ems_job_queue الموثَّقة.
 *   بقايا جولةٍ ماتت ⇐ تُكنس بالعائلة (لا بمعرّفاتٍ فردية).
 * ② act⑦: فعلٌ ماليٌّ (خطوةُ اعتمادِ الخصم) بلا فعلٍ عاكس ⇐ يُسجَّل عاكسُه
 *   ويُربط — «كلُّ ماليٍّ له عكسٌ بمرجعِه».
 * ③ act⑩: خمسةُ أدوارٍ ماليةٍ جديدة (31-35) بلا سطحِ بلاغات ⇐ منحُ عرضٍ
 *   ورابطُ dept_inbox لكلٍّ (المجموعةُ تُنشأ لمن لا مجموعةَ له).
 * ④ act⑪: أحدَ عشرَ ظهورًا لغيرِ المالكِ بلا صفِّ عرضٍ مُعلَن ⇐ تُسجَّل
 *   صفوفُ screen_view_rows بزاويةِ «اطّلاعٌ بنطاقِه» — فالظهورُ إمّا مُعلَنٌ
 *   بصفِّه أو يُرفع.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

echo "══ تخضيرُ بوابةِ الدمج ══\n\n";

/* ① كنسُ بقايا UAT من سجلِّ الإنجاز — بالعائلة */
$conn->query("DELETE FROM achievement_records
              WHERE source_kind NOT IN ('task','request','approval','work_order','unit','claim','ticket','corrective')");
echo '  ① كُنس ' . $conn->affected_rows . " صفَّ إنجازٍ مصدرُها نصُّ UAT (المتوقَّع 20)\n";

/* ② الفعلُ العاكسُ للخصم */
$has = (int) $one("SELECT COUNT(*) FROM actions WHERE action_code='screen.workforce.deduction.reverse_step'");
if (!$has) {
    $conn->query("INSERT INTO actions (action_code, name_ar, placement, handler_path, is_write, is_financial, reverse_action_code, owner_doc, active, created_at)
                  SELECT 'screen.workforce.deduction.reverse_step', 'عكسُ خطوةِ اعتمادِ الخصم — حركةٌ مقابلةٌ بمرجعِ الأصل',
                         placement, handler_path, 1, 1, 'screen.workforce.deduction.approve_step', owner_doc, 1, NOW()
                  FROM actions WHERE action_code='screen.workforce.deduction.approve_step'");
    echo "  ② سُجِّل الفعلُ العاكس\n";
}
$conn->query("UPDATE actions SET reverse_action_code='screen.workforce.deduction.reverse_step'
              WHERE action_code='screen.workforce.deduction.approve_step' AND (reverse_action_code IS NULL OR reverse_action_code='')");
echo '  ② رُبط الأصلُ بعاكسِه: ' . $conn->affected_rows . "\n";

/* ③ سطحُ البلاغاتِ للأدوارِ 31-35 */
$mid = (int) $one("SELECT id FROM modules WHERE code='Tickets/dept_inbox.php' LIMIT 1");
if (!$mid) { exit("  ✘ لا وحدةَ لـdept_inbox\n"); }
foreach (array(31, 32, 33, 34, 35) as $role) {
    $q = $conn->query("SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$mid");
    if (!($q && $q->num_rows)) {
        $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ($role, $mid, 1, 0, 0, 0)");
    }
    $gid = (int) $one("SELECT id FROM link_groups WHERE group_code='n9o_tickets_r$role'");
    if (!$gid) {
        $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                      VALUES ('البلاغات', 'n9o_tickets_r$role', $role, 'fa fa-inbox', 90, 98, 'البلاغات', 1)");
        $gid = (int) $conn->insert_id;
    }
    $q = $conn->query("SELECT 1 FROM nav_items WHERE role_id=$role AND route='Tickets/dept_inbox.php'");
    if (!($q && $q->num_rows)) {
        $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                      VALUES ($role, 'DAILY', $gid, $mid, 'صندوقُ بلاغاتِ الإدارة', 'Tickets/dept_inbox.php', 'fa fa-inbox', 1, 'Tickets/dept_inbox.php', 1, NOW())");
    }
}
echo "  ③ سطحُ البلاغاتِ للأدوارِ الخمسة\n";

/* ④ صفوفُ العرضِ المُعلَنةُ للظهوراتِ الأحدَ عشرة */
$VIEWS = array(
    array('Employees/employees.php', 'إدارة التشغيل', 1),
    array('Equipments/fleet_models.php', 'إدارة التشغيل', 1),
    array('Equipments/fleet_depreciation_profiles.php', 'إدارة التشغيل', 1),
    array('main/users.php', 'إدارة التشغيل', 1),
    array('Financing/financiers_registry.php', 'إدارة التشغيل', 1),
    array('admin/org_permits.php', 'إدارة التشغيل', 1),
    array('Clients/clients.php', 'إدارة الموقع', 6),
    array('Equipments/equipments_types.php', 'إدارة الموقع', 6),
    array('ActivityLogs/activity_logs.php', 'إدارة الموقع', 6),
    array('Finance/cost_report_fin.php', 'إدارة الموقع', 6),
    array('Equipments/meter_readings.php', 'إدارة الموقع', 6),
);
$st = $conn->prepare("INSERT INTO screen_view_rows (screen_name, route, dept, role_id, role_kind, scope_text, angle, active, created_at)
                      SELECT ?, ?, ?, ?, 'viewer', 'نطاقُ إدارتِه', 'اطّلاعٌ بنطاقِه — الظهورُ مُعلَنٌ بصفِّه', 1, NOW()
                      WHERE NOT EXISTS (SELECT 1 FROM screen_view_rows WHERE route=? AND dept=? AND role_kind='viewer' AND active=1)");
$n4 = 0;
foreach ($VIEWS as [$route, $dept, $role]) {
    $name = basename($route);
    $st->bind_param('sssiss', $name, $route, $dept, $role, $route, $dept);
    if ($st->execute() && $conn->affected_rows > 0) { $n4++; }
}
$st->close();
echo "  ④ صفوفُ عرضٍ سُجِّلت: $n4 من " . count($VIEWS) . "\n";
echo "\n✔ تمّت\n";
