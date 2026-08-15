<?php
/**
 * 2027_04_06_permgov_data_violations.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مخالفتانِ في البياناتِ لا في الشفرة — ⇐ INJ-0410 · INJ-0338 · INJ-0371
 *
 * ① **المراجعُ الداخليُّ يكتب خارجَ نطاقِه.** نصُّ القبول: «استعلامُ
 *    `role_permissions` للدور ٣٣ يُظهر **صفرَ منحةِ كتابةٍ** على أيِّ مودولٍ
 *    جدولُه خارج `iaf_*`». والمقيس: منحُ كتابةٍ (وحذفٍ!) على `main/project_users.php`.
 *    **والمراجعُ يشهد ولا يكتب** — وإلا شهد على عملِ نفسِه.
 *    ◆ ومنحُه على `Audit/iaf_*` **تبقى**: هي نطاقُه بعينِه.
 *
 * ② **صفوفُ تنقّلٍ فعّالةٌ يحجبها الحارس.** ٢٤٦ صفًّا في ٢٥ دورًا: الصفُّ
 *    `active=1` ويحمل `permission_code` لا منحةَ للدورِ عليه. فالقاعدةُ تَعِدُ
 *    برابطٍ والحارسُ يردُّه. والمولِّدُ يُخفيها عند التصييرِ — فلا يراها
 *    المستخدمُ — لكنَّ **القاعدةَ والشاشةَ يتفرّقان**، وكلُّ تقريرٍ يقرأ
 *    القاعدةَ يعدُّ رابطًا لا وجودَ له.
 *    ◆ والعلاجُ **علمٌ لا حذف**: `active=0` — فالصفُّ باقٍ ويعود بمنحةٍ واحدة.
 *
 * ◆ ولا حذفَ في هذه الهجرةِ إطلاقًا: رفعُ رايةٍ وإطفاءُ علم — كلاهما يُردُّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ مخالفتانِ في البيانات ══\n\n";

/* ── ① المراجعُ يشهد ولا يكتب ───────────────────────────────────────────── */
$conn->query('CREATE TABLE IF NOT EXISTS role_permissions_archive_auditor LIKE role_permissions');
$before = array();
$r = $conn->query("SELECT rp.id, m.code FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE rp.role_id = 33 AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1)
                      AND m.code NOT LIKE '%iaf_%'");
while ($r && ($x = $r->fetch_assoc())) { $before[] = $x; }
$rev = 0;
foreach ($before as $row) {
    $id = (int) $row['id'];
    $conn->query('INSERT INTO role_permissions_archive_auditor SELECT * FROM role_permissions WHERE id = ' . $id);
    if ($conn->query('UPDATE role_permissions SET can_add=0, can_edit=0, can_delete=0 WHERE id = ' . $id)
        && $conn->affected_rows > 0) {
        $rev++;
        echo '  ▸ رُفعت الكتابةُ عن المراجعِ في `' . $row['code'] . "`\n";
    }
}
echo "  ⇒ منحُ كتابةٍ رُفعت: {$rev}" . ($rev === 0 ? ' (لا مخالفة)' : '') . "\n\n";

/* ── ② الصفُّ المحجوبُ يُطفأ لا يُحذف ────────────────────────────────────── */
$blocked = array();
$r = $conn->query(
    "SELECT n.id, n.role_id, n.label_ar, n.permission_code FROM nav_items n
      WHERE n.active = 1 AND n.permission_code IS NOT NULL AND n.permission_code <> ''
        AND NOT EXISTS (SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                         WHERE rp.role_id = n.role_id AND rp.can_view = 1 AND m.code = n.permission_code)");
while ($r && ($x = $r->fetch_assoc())) { $blocked[] = $x; }
$off = 0; $byRole = array();
foreach ($blocked as $row) {
    $id = (int) $row['id'];
    if ($conn->query('UPDATE nav_items SET active = 0 WHERE id = ' . $id) && $conn->affected_rows > 0) {
        $off++;
        $byRole[(int) $row['role_id']] = (isset($byRole[(int) $row['role_id']]) ? $byRole[(int) $row['role_id']] : 0) + 1;
    }
}
echo "  ⇒ صفوفٌ أُطفئت: {$off} في " . count($byRole) . " دورًا\n";
foreach (array_slice($byRole, 0, 6, true) as $rid => $n) { echo "     · دور {$rid}: {$n}\n"; }

/* ── التحقّقُ الفوريّ ─────────────────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM nav_items n WHERE n.active = 1 AND n.permission_code <> ''
                    AND NOT EXISTS (SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                                     WHERE rp.role_id = n.role_id AND rp.can_view = 1 AND m.code = n.permission_code)");
$left = ($q && ($x = $q->fetch_row())) ? (int) $x[0] : -1;
$q2 = $conn->query("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                     WHERE rp.role_id = 33 AND (rp.can_add=1 OR rp.can_edit=1 OR rp.can_delete=1)
                       AND m.code NOT LIKE '%iaf_%'");
$left2 = ($q2 && ($x = $q2->fetch_row())) ? (int) $x[0] : -1;
echo "\n  " . ($left === 0 ? '✔' : '✘') . " صفوفُ تنقّلٍ محجوبةٌ باقية: {$left}\n";
echo '  ' . ($left2 === 0 ? '✔' : '✘') . " منحُ كتابةٍ للمراجعِ خارج نطاقِه: {$left2}\n";
