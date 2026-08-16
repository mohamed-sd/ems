<?php
/**
 * 2027_05_16_hr_users_grant_drop.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مزامنةُ nav09 (05_13) منحت دورَ الموارد (4) عرضًا على `main/users.php` لأن
 * ورقةَ قسمِه تذكرها — لكنها شاشةُ حوكمةِ صلاحياتٍ (GOV_PERM_SCOPED) والحزامُ
 * AC-GOV-01 يمنعها لغيرِ مالكِها. **الحوكمةُ تعلو الورقة**: تُنزع المنحةُ
 * ويبقى الرابطُ (يطابق الورقةَ) محكومًا fail-closed — فمن ضغطه حُوِّل للوحته.
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

$conn->query("DELETE rp FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
              WHERE rp.role_id = 4 AND m.code = 'main/users.php'");
echo 'نُزعت منحةُ دورِ 4 على شاشةِ الحوكمة: ' . $conn->affected_rows . "\n✔ تمّت\n";
