<?php
/**
 * 2028_06_08 · العكس — إعادةُ الدورِ 5 إلى حالِه قبلَ الاستعادة
 * ═══════════════════════════════════════════════════════════════════════════
 * ينزع مساحةَ DEP-18 وكلَّ سجلّاتِها ويعيد اسمَ الدورِ 5 وربطَه SECONDARY
 * بـDEP-12. ⛔ **ولا يُشغَّل إن كان على الدورِ مستخدمون** — فنزعُ مساحتِه
 * يُفرِغ سايدبارَهم.
 * ◆ الترتيبُ يحترم المفاتيحَ الأجنبيّة: المواضعُ قبلَ الأهدافِ والمجموعات.
 * التشغيل: php database/migrations/2028_06_08_dep18_movement_ops_restore_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                   ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "اتصال المرحِّل فشل\n"); exit(1); }
$conn->set_charset('utf8mb4');
$log = function ($m) { echo "  $m\n"; };

const WS = 'DEP-18';
const ROLE = 5;

$r = $conn->query("SELECT COUNT(*) FROM users WHERE role=" . ROLE);
$busy = (int) $r->fetch_row()[0];
if ($busy > 0) { fwrite(STDERR, "الدورُ 5 عليه {$busy} مستخدمًا — أوقفتُ العكسَ\n"); exit(1); }

$q = function ($sql) use ($conn) {
    if (!$conn->query($sql)) { fwrite(STDERR, "فشل: {$conn->error}\n"); exit(1); }
    return $conn->affected_rows;
};

$log('مواضعُ المساحة: ' . $q("DELETE FROM nav_workspace_placements WHERE workspace_id='" . WS . "'"));
$log('مواضعُ الدليل: '  . $q("DELETE FROM nav_placements WHERE workspace_id='" . WS . "'"));
$log('الأهداف: '        . $q("DELETE FROM nav_targets WHERE workspace_id='" . WS . "'"));
$log('مجموعاتُ الدورة: ' . $q("DELETE FROM nav_lifecycle_groups WHERE workspace_id='" . WS . "'"));
$log('ربطُ الدور: '      . $q("DELETE FROM nav_ws_roles WHERE workspace_id='" . WS . "'"));
$log('المساحة: '        . $q("DELETE FROM nav_workspaces WHERE workspace_id='" . WS . "'"));

/* بنودُ القائمةِ الإرثيّةُ تُطفأ ولا تُحذف — عُرفُ البيتِ: تعطيلٌ لا إسقاط */
$log('بنودُ nav_items أُطفئت: ' . $q("UPDATE nav_items SET active=0 WHERE role_id=" . ROLE . " AND active=1"));
$log('مجموعاتُ link_groups أُطفئت: ' . $q("UPDATE link_groups SET is_active=0 WHERE owner_role_id=" . ROLE . " AND is_active=1"));

$q("UPDATE roles SET name='إدارة الموقع (قديم — مدمج في 6)' WHERE id=" . ROLE);
$log('اسمُ الدورِ أُعيد');

if (!$conn->query("SELECT role_id FROM nav_ws_roles WHERE workspace_id='DEP-12' AND role_id=" . ROLE)->num_rows) {
    $q("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref, parent_role_id, ruling)
        VALUES ('DEP-12'," . ROLE . ",'SECONDARY','NAV_ARCH_02_CLEAN §١·٤ · roles.parent_role_id',NULL,
        'اسمُ الدورِ في `roles` يحمل حكمَه: «إدارة الموقع (قديم — مدمج في 6)» — والدورُ 6 مربوطٌ PRIMARY بـDEP-12 · صفرُ مستخدمٍ وصفرُ رابطٍ حيّ')");
    $log('ربطُ DEP-12 SECONDARY أُعيد');
}

echo "اكتمل عكسُ استعادةِ إدارةِ الحركةِ والتشغيل\n";
exit(0);
