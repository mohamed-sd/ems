<?php
/**
 * 2028_02_08_users_role_id_retire.php — حسمُ ازدواجِ users.role/role_id (م117 · CL-PAT-USERROLE)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:users(role_id comment) + backfill
 * الحقيقةُ الواحدةُ بعمودَين يتفرّقان: `role` (varchar · مملوءٌ · تقرؤه الجلسةُ
 * وكلُّ الحرّاس) و`role_id` (int · 34 فارغًا · لا قارئَ إنتاجٍ له — حكمُ sec01:
 * «يُكتب ولا يُقرأ، عمودٌ أثريٌّ تقاعدُه فوريّ»). الحسم: `role` هو الحاكمُ ·
 * `role_id` يُردَم من الحاكمِ (لا فراغَ يقرؤه عدّادٌ فيُسقط مستخدمين) ويوسم
 * أثريًّا — وحذفُه النهائيُّ بندُ مراجعةِ مالكٍ لا فعلَ هجرةٍ صامتًا.
 * التشغيل: php database/migrations/2028_02_08_users_role_id_retire.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/* ① الردمُ من الحاكم — قيمُ role رقميّةٌ (ومنها -1 للسوبر) */
$ok = $conn->query("UPDATE users SET role_id = CAST(role AS SIGNED)
    WHERE role_id IS NULL OR role_id <> CAST(role AS SIGNED)");
if (!$ok) { exit("⛔ فشل الردم: {$conn->error}\n"); }
echo '① رُدم role_id من الحاكم: ' . $conn->affected_rows . " صفًّا\n";

/* ② الوسمُ الأثريّ — التعليقُ في المخطَّطِ نفسِه لا في وثيقةٍ تُنسى */
$ok = $conn->query("ALTER TABLE users MODIFY role_id INT NULL
    COMMENT 'اثري — الحاكم users.role (حكم sec01: يكتب ولا يقرا) · مردوم من الحاكم بهجرة 2028_02_08 · حذفه النهائي بمراجعة مالك'");
if (!$ok) { exit("⛔ فشل الوسم: {$conn->error}\n"); }
echo "② وُسم أثريًّا في المخطَّط\n";

$left = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role_id IS NULL OR role_id <> CAST(role AS SIGNED)")->fetch_row()[0];
echo $left === 0 ? "③ الانجرافُ صفر — العمودان متطابقان\n" : "⛔ بقي انجراف: {$left}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
exit($left === 0 ? 0 : 1);
