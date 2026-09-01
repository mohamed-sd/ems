<?php
/**
 * 2028_02_13_dc19_route_case_debt.php — `DC-19` دَينُ حالةِ أحرفِ المسار (GOV_EXEC §21)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: row:repair01_debt_register/DC-19
 *
 * ◆ **ما كُشف بالقياسِ أثناءَ بناءِ `DEP-04`**: مسارُ الموضعِ في `nav_placements`
 *   مكتوبٌ **بحرفٍ صغيرٍ** (`fleet/asset_intake.php`) واسمُ المجلدِ على القرصِ
 *   **بحرفٍ كبير** (`Fleet/`). على ويندوز يُحلُّ المساران إلى الملفِّ نفسِه
 *   **فلا يظهر العطبُ ولا يُسقِط قياسًا**، وعلى نظامِ ملفاتٍ حسّاسٍ للحالةِ
 *   (النشرُ على Hostinger — [[mariadb-portability-hostinger]]) **يسقط الرابطُ 404**.
 *
 * ◆ **والقياسُ الحيُّ عند القيدِ: 308 مسارًا بمجلدٍ · وكلُّها منزاحةُ الحالة**
 *   على 24 مجلدًا (Fleet · Finance · Governance · Portal · Procurement …) —
 *   ⛔ **فهو دَينٌ نظاميٌّ سابقٌ لهذه الجولةِ لا أثرُها**، والثلاثةُ والعشرون
 *   موضعًا الجديدةُ اتّبعت العُرفَ القائمَ نفسَه فلا تزيده صنفًا.
 *
 * ◆ **ولماذا لا يُصحَّح في هذه الجولةِ**: التصحيحُ يمسُّ ثمانيةَ سجلّاتٍ معًا
 *   (`nav_placements` · `nav_items` · `modules` · `gov_profile_items` ·
 *   `repair01_screen_registry` · `gov_screen_cycle` · `nav_canonical` ·
 *   مصفوفةُ UXUI) و**كلُّ مقارنةٍ تقرأ المسارَ مفتاحًا** — فهو كنسٌ مستقلٌّ
 *   بإثباتِه لا بندٌ جانبيٌّ داخلَ جولةِ بناء (§11 من أمرِ NAVR).
 *   ⇒ فيُقيَّد **بمقياسِه** لا بنصٍّ في وثيقة (درسُ «الفجوةُ في الدفترِ لا في النثر»).
 *
 * التشغيل: php database/migrations/2028_02_13_dc19_route_case_debt.php
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

/* المقياسُ يُقارن بادئةَ المسارِ بالمجلدِ الحقيقيِّ — ولا يُقاس بالقرصِ من SQL،
   فالعدُّ هنا **بادئاتٌ منزاحةٌ عن العُرفِ المعتمَدِ** (المجلدُ يبدأ بحرفٍ كبير)
   وأداةُ القياسِ الحيّةِ `tools/gov_exec_route_case_probe.php` تُطابقها بالقرص. */
$sql = "SELECT COUNT(*) FROM (
          SELECT DISTINCT route FROM nav_placements
           WHERE active = 1 AND route IS NOT NULL AND route LIKE '%/%'
             AND BINARY LEFT(route,1) = BINARY LOWER(LEFT(route,1))
        ) t";
$st = $conn->prepare("INSERT INTO repair01_debt_register
    (class_code, class_name_ar, measure_sql, measure_tool, measured_count, measured_at,
     blocking_level, assigned_wave, debt_owner, exit_criteria, owner_ruling)
    VALUES ('DC-19', ?, ?, 'tools/gov_exec_route_case_probe.php', -1, NULL,
            'MINOR', 'GOV_EXEC', 'NAVR', ?, ?)
    ON DUPLICATE KEY UPDATE class_name_ar = VALUES(class_name_ar),
        measure_sql = VALUES(measure_sql), measure_tool = VALUES(measure_tool),
        exit_criteria = VALUES(exit_criteria), owner_ruling = VALUES(owner_ruling)");
$name = 'مسارُ موضعٍ منزاحُ حالةِ الأحرفِ عن مجلدِه على القرص';
$exit = 'صفرُ مسارٍ تختلف بادئتُه عن اسمِ المجلدِ حرفًا — يُقاس بالقرصِ لا بالعُرف';
$rule = 'دَينٌ سابقٌ للجولةِ يظهر على نظامِ ملفاتٍ حسّاسٍ للحالةِ وحدَه — كنسٌ مستقلٌّ بإثباتِه';
$st->bind_param('ssss', $name, $sql, $exit, $rule);
if (!$st->execute()) { exit("⛔ {$conn->error}\n"); }
$st->close();
echo "✔ DC-19 مقيَّدٌ في دفترِ الدَّين\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
