<?php
/**
 * 2027_05_19_risk_gov_pairs_registry.php
 * ═══════════════════════════════════════════════════════════════════════════
 * أزواجُ المخاطرِ والحوكمةِ للمبيعاتِ والموردين — التسجيلُ قبلَ الظهور
 * (الشاشاتُ الأربعُ كُتبت على نمطِ الأغلفةِ الحيّة · وهذه وحداتُها ومنحُها
 * وروابطُها في «متابعةُ إدارتي» وأفعالُها العشرةُ في القاموس)
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

echo "══ تسجيلُ الأزواج ══\n\n";

/* الشاشاتُ الأربع: (المسار · الاسم · المالك · المشاهدون · مرحلةُ متابعةِ إدارتي) */
$SCREENS = array(
    array('Risk/risk_dept_sal.php',       'مخاطرُ المبيعاتِ والعقود', 12, array(12 => 0, 15 => 0)),
    array('Governance/gov_dept_sal.php',  'حوكمةُ المبيعاتِ والعقود', 12, array(12 => 1, 15 => 0)),
    array('Risk/risk_dept_sup.php',       'مخاطرُ الموردين',           2,  array(2 => 0, 8 => 0, 15 => 0)),
    array('Governance/gov_dept_sup.php',  'حوكمةُ الموردين',           2,  array(2 => 1, 8 => 0, 15 => 0)),
);
foreach ($SCREENS as [$code, $name, $owner, $grants]) {
    $codeQ = $conn->real_escape_string($code);
    $mid = (int) $one("SELECT id FROM modules WHERE code='$codeQ'");
    if (!$mid) {
        $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                              VALUES (?, ?, ?, 1, 0, 'fa fa-scale-balanced', 100)");
        $st->bind_param('ssi', $name, $code, $owner);
        $st->execute(); $mid = (int) $conn->insert_id; $st->close();
    }
    foreach ($grants as $role => $w) {
        $q = $conn->query("SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$mid");
        if (!($q && $q->num_rows)) {
            $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                          VALUES ($role, $mid, 1, $w, $w, 0)");
        }
    }
    /* الرابطُ في مجموعةِ «متابعةُ إدارتي» (م7) لمالكِه */
    $gid = (int) $one("SELECT id FROM link_groups WHERE owner_role_id=$owner AND stage_no=7
                       AND group_code LIKE 'n9s%' AND group_code NOT LIKE 'n9s99%' ORDER BY display_order LIMIT 1");
    if (!$gid) {
        $gc = 'n9o_mydept_r' . $owner;
        $gid = (int) $one("SELECT id FROM link_groups WHERE group_code='$gc'");
        if (!$gid) {
            $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                          VALUES ('متابعةُ إدارتي', '$gc', $owner, 'fa fa-scale-balanced', 70, 7, 'متابعةُ إدارتي', 1)");
            $gid = (int) $conn->insert_id;
        }
    }
    $q = $conn->query("SELECT 1 FROM nav_items WHERE role_id=$owner AND route='$codeQ'");
    if (!($q && $q->num_rows)) {
        $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                              VALUES (?, 'DAILY', ?, ?, ?, ?, 'fa fa-scale-balanced', 60, ?, 1, NOW())");
        $st->bind_param('iiisss', $owner, $gid, $mid, $name, $code, $code);
        $st->execute(); $st->close();
    }
    echo "  ✔ $code (وحدة #$mid)\n";
}

/* الأفعالُ العشرة */
$ACTS = array(
    array('risk.sal.view',     'عرضُ مخاطرِ المبيعات',            'risk_dept_sal.php'),
    array('risk.sal.raise',    'إبلاغُ إشارةِ خطرٍ من المبيعات',  'risk_dept_sal.php'),
    array('risk.sal.evidence', 'دليلُ ضابطٍ تملكه المبيعات',      'risk_dept_sal.php'),
    array('gov.sal.view',      'عرضُ حوكمةِ المبيعات',            'gov_dept_sal.php'),
    array('gov.sal.attest',    'إشهادُ حوكمةِ المبيعات',          'gov_dept_sal.php'),
    array('risk.sup.view',     'عرضُ مخاطرِ الموردين',            'risk_dept_sup.php'),
    array('risk.sup.raise',    'إبلاغُ إشارةِ خطرٍ من الموردين',  'risk_dept_sup.php'),
    array('risk.sup.evidence', 'دليلُ ضابطٍ يملكه الموردون',      'risk_dept_sup.php'),
    array('gov.sup.view',      'عرضُ حوكمةِ الموردين',            'gov_dept_sup.php'),
    array('gov.sup.attest',    'إشهادُ حوكمةِ الموردين',          'gov_dept_sup.php'),
);
$n = 0;
foreach ($ACTS as [$code, $label, $file]) {
    $q = $conn->query("SELECT 1 FROM nav09_action_map WHERE canonical_code='" . $conn->real_escape_string($code) . "'");
    if ($q && $q->num_rows) { continue; }
    $st = $conn->prepare("INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
         consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
         idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
        VALUES (?,?,?,?, 'الإدارةُ المالكة', 'risk_signals · gov_approval_decisions', 'DeptGovSignal',
                'المخاطر · الحوكمة', 'ظهورٌ نطاقيٌّ للمكوِّنِ الواحدِ — والسجلُّ مركزي', 'قرارُ سحبٍ بمرجعِه',
                ?, 'bound_page', 'yes', 'حارسُ المكوِّنِ الواحدِ (dept_risk/gov_space) + منحةُ الشاشةِ الحاملة',
                'yes', 'إشارةُ الخطرِ بمفتاحِ قاعدتِها والإشهادُ بنطاقِه', 'pending', '',
                ?, NOW())");
    $wc = (strpos($code, '.view') !== false) ? 'read_only' : 'governance_write';
    $st->bind_param('ssssss', $code, $label, $label, $file, $code, $wc);
    if ($st->execute()) { $n++; }
    $st->close();
}
echo "  ✔ أفعالٌ سُجِّلت: $n\n";
echo "\n✔ تمّت\n";
