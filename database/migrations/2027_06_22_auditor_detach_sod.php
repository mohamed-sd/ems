<?php
/**
 * 2027_06_22_auditor_detach_sod.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تصحيحُ قرارٍ مفوَّضٍ التقطه قياسُ التكافؤ: إلحاقُ «المراجعِ والمدققِ المالي»
 * (20 — رقابيٌّ بارتباطٍ مباشرٍ بالرئيس) بقالبِ G6 وحَّده مع رئيسِ الحساباتِ
 * فمنحه بنودَ **كتابةٍ محاسبية** — كسرٌ لفصلِ الواجبات، والرقابيون صنفٌ
 * محميٌّ في GOV-AUTH-01 (لا سحبَ عن رقابيٍّ · لا نيابةَ عليه) لا يُصهر في
 * السلّمِ التنفيذي.
 *   ① فكُّ إلحاقِ مستخدمي الدورِ 20 (يعودون لمنحِهم الحيِّ القائم — معلَنين).
 *   ② نزعُ بنودِ G6 المبذورةِ من منحِ الدورِ 20 (فلا يكتسب رئيسُ الحساباتِ
 *      شاشاتِ الرقابةِ ولا العكس).
 *   قالبُ الرقابيِّ الماليِّ يُصاغ بوثيقتِه لا قياسًا على درجةٍ تنفيذية.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

$conn->query("UPDATE gov_authority_grants g
                JOIN users u ON u.id = g.user_id
                 SET g.revoked_at = NOW(),
                     g.reason = CONCAT(g.reason, ' | فُكَّ: رقابيٌّ لا يُقولب تنفيذيًّا (SoD) 2026-08-17')
               WHERE u.role = 20 AND g.revoked_at IS NULL");
echo "✔ فُكَّ إلحاقُ مستخدمي الدورِ 20: {$conn->affected_rows}\n";

$conn->query("DELETE i FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id = i.profile_id
               WHERE p.dept_code = 'المالية والخزينة' AND p.grade = 'G6'
                 AND i.seeded_from = 'role_permissions:20'
                 AND NOT EXISTS (SELECT 1 FROM role_permissions rp
                                   JOIN modules m ON m.id = rp.module_id
                                  WHERE rp.role_id = 31 AND rp.can_view = 1 AND m.code = i.item_ref)");
echo "✔ نُزعت بنودُ الرقابةِ من قالبِ G6 (وبقي المشتركُ مع رئيسِ الحسابات): {$conn->affected_rows}\n";

printf("· مستخدمون بلا قالبٍ الآن (معلَنون — الرقابيُّ الماليُّ ينتظر وثيقتَه): %s\n",
    $one("SELECT COUNT(*) FROM users u WHERE u.status = 1
           AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g WHERE g.user_id = u.id AND g.revoked_at IS NULL)"));
$loss = (int) $one(
    "SELECT COUNT(*) FROM role_permissions rp
       JOIN modules m ON m.id = rp.module_id
       JOIN gov_role_profiles p ON p.dept_code = 'المالية والخزينة' AND p.grade = 'G6' AND p.version = 1
      WHERE rp.role_id = 31 AND rp.can_view = 1
        AND NOT EXISTS (SELECT 1 FROM gov_profile_items i
                         WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                           AND i.item_ref = m.code AND i.allow = 1)");
printf($loss === 0 ? "✔ رئيسُ الحساباتِ بلا خسارة (القالبُ ⊇ منحِه)\n" : "✗ خسائرُ لرئيسِ الحسابات: %d!\n", $loss);
