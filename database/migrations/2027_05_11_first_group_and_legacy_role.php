<?php
/**
 * 2027_05_11_first_group_and_legacy_role.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ① nav_seven ②: «لكلِّ دورٍ لوحةٌ أولى» — أدوارُ الماليةِ 31/32/33 أولى
 *   مجموعاتِها ord=10 فلا يُعرف من أين تبدأ ⇐ أولى كلِّ دورٍ تصير ord=1
 *   (و34/35 كذلك — مجموعتُهما الوحيدةُ هي البداية).
 * ② sec AC-GOV-02: الدورُ 5 «إدارة الموقع (قديم — مدمج في 6)» ما زال يحمل
 *   كتابةً على غلافِ حوكمةِ الموقع ⇐ الدورُ المدمجُ قراءةٌ فقط في كلِّ شيء
 *   (كتابتُه من بابِ خلَفِه 6).
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

echo "══ اللوحةُ الأولى والدورُ المدمج ══\n\n";
$n = 0;
foreach (array(31, 32, 33, 34, 35) as $role) {
    $r = $conn->query("SELECT id, display_order FROM link_groups
                       WHERE owner_role_id = $role AND is_active = 1
                       ORDER BY display_order LIMIT 1");
    $g = $r ? $r->fetch_assoc() : null;
    if ($g && (int) $g['display_order'] !== 1) {
        $conn->query("UPDATE link_groups SET display_order = 1 WHERE id = " . (int) $g['id']);
        $n += $conn->affected_rows;
    }
}
echo "  ① أولى المجموعاتِ صارت ord=1 لـ$n دور\n";

$conn->query("UPDATE role_permissions SET can_add=0, can_edit=0, can_delete=0 WHERE role_id = 5");
echo '  ② الدورُ المدمجُ 5 صار قراءةً في ' . $conn->affected_rows . " منحة\n";
echo "\n✔ تمّت\n";
