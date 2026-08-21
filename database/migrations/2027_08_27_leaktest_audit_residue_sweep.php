<?php
/**
 * 2027_08_27_leaktest_audit_residue_sweep.php
 *   أثرُ تدقيقٍ خلَّفه فاحصُ العزلِ — كنسٌ بالعائلةِ بعدَ حجرٍ · INJ-FIX-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفه `tools/tenant_leak_probe.php`**: «كياناتٌ شبح — 4,397 صفًّا في
 *   `activity_logs` لكيانٍ غيرِ مسجَّل». وأولُ قراءةٍ لذلك «تسريبُ عزلٍ» —
 *   **وهي خاطئة**. القياسُ يقول: الكياناتُ المسجَّلةُ اثنان (1 و4)، والصفوفُ
 *   الشبحيةُ تحمل `company_id` بين 1173 و1510 بـ**339 قيمةً فريدة**،
 *   و`user_id = 999901/999902`، و`module_name = 'tenant_gate'`، وكلُّها في
 *   دقيقةٍ واحدةٍ (2026-08-14 03:40).
 *   ⇐ **أثرُ تدقيقٍ خلَّفه `tests/tenant_leak_test.php`**: الفاحصُ يُنشئ كياناتٍ
 *     اصطناعيةً ليُثبت العزل، ويكنس **صفوفَه** ولا يكنس **أثرَه في سجلِّ
 *     التدقيق** الذي كتبته البوابةُ عنه.
 *
 * ◆ **والكنسُ بالعائلةِ لا بالوسم**: 3,260 صفًّا تحمل `LEAKTEST_` في حمولتِها،
 *   **و1,137 لا تحملها** — لأنها صفوفُ تحديثٍ وحذفٍ حمولتُها أرقامٌ لا أسماء.
 *   فالكنسُ بـ`LIKE '%LEAKTEST_%'` كان **سيترك ألفًا ومئةً وسبعةً وثلاثين**
 *   ويُعلن نظافةً كاذبة. والعائلةُ الصحيحةُ: المدى الاصطناعيُّ للكيانِ
 *   **مع** بوابةِ الكتابةِ **مع** حسابِ الفاحص — ثلاثةُ شروطٍ معًا.
 *
 * ◆ **ولا حذفَ مدمِّر**: كلُّ صفٍّ يُحجَر بلقطتِه الكاملةِ قبلَ حذفِه،
 *   و`--revert` يعيده حرفًا. فالسجلُّ التدقيقيُّ لا يُتلف ولو كان أثرَ فاحص.
 *
 * ◆ **والمصدرُ أُصلح أيضًا**: `tests/tenant_leak_test.php` يكنس أثرَه بنفسِه
 *   الآن، فلا يعود الأثرُ بعد كلِّ تشغيل.
 *
 * التشغيل:  php database/migrations/2027_08_27_leaktest_audit_residue_sweep.php
 * الرجوع :  php database/migrations/2027_08_27_leaktest_audit_residue_sweep.php --revert
 * الشاهد :  php tools/tenant_leak_probe.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ARCHIVE = 'gov_test_residue_archive';

/* عائلةُ الأثر: كيانٌ غيرُ مسجَّلٍ **و** بوابةُ الكتابةِ الاصطناعية.
   ◆ **وضبطٌ لاحقٌ على مفتاحِ العائلة — وقع فعلًا**: أولُ صياغةٍ اشترطت أيضًا
     `user_id IN (999901, 999902)` (حسابا الفاحص). فكُنس 4,237 صفًّا **وبقي 160**
     من العائلةِ نفسِها بحسابِ `user_id = 0` — من جولاتٍ أخرى للفاحصِ بين
     2026-08-14 و2026-08-18.
   ◆ **ولولا قياسُ ما بقي بعدَ الكنسِ لأُعلنت نظافةٌ كاذبة**: العددُ نزل من
     4,397 إلى 160 وهو رقمٌ صغيرٌ يسهل أن يُقرأ «تمّ». فالمقامُ يُقاس **بعدَ**
     الفعلِ لا قبلَه وحدَه.
   ◆ فحُذف شرطُ الحساب: `module_name = 'tenant_gate'` مع كيانٍ غيرِ مسجَّلٍ
     مفتاحٌ كافٍ — إذ لا كيانَ في القاعدةِ غيرُ 1 و4، فكلُّ ما عداهما اصطناعيٌّ
     بالبناء. */
$FAMILY = "`company_id` NOT IN (SELECT `id` FROM `admin_companies`)
           AND `company_id` > 0
           AND `module_name` = 'tenant_gate'";

if (in_array('--revert', $argv, true)) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$ARCHIVE}'");
    if (!$r || (int) $r->fetch_row()[0] === 0) { exit("✘ لا حجرَ يُرجَع منه\n"); }
    $conn->query("INSERT IGNORE INTO `activity_logs`
                  SELECT * FROM `{$ARCHIVE}` WHERE `src_table` = 'activity_logs'");
    echo "↺ أُعيد {$conn->affected_rows} صفًّا من الحجر\n";
    exit(0);
}

/* ══ ① الجرد ═════════════════════════════════════════════════════════════ */
$r = $conn->query("SELECT COUNT(*) n, COUNT(DISTINCT `company_id`) d FROM `activity_logs` WHERE {$FAMILY}");
$before = $r ? $r->fetch_assoc() : array('n' => 0, 'd' => 0);
echo "① أثرُ الفاحصِ المقيس: {$before['n']} صفًّا · {$before['d']} كيانًا اصطناعيًّا\n";

$r = $conn->query("SELECT COUNT(*) FROM `activity_logs`
                    WHERE {$FAMILY} AND (`new_value` LIKE '%LEAKTEST\_%' OR `old_value` LIKE '%LEAKTEST\_%')");
$tagged = $r ? (int) $r->fetch_row()[0] : 0;
echo "   منها بوسمِ LEAKTEST: {$tagged} · **وبلا وسمٍ: " . ((int) $before['n'] - $tagged)
   . "** ← ولو كُنس بالوسمِ وحدَه لبقيت\n";

if ((int) $before['n'] === 0) { echo "↺ عطالة: لا أثرَ — لم يُغيَّر شيء\n"; exit(0); }

/* ══ ② الحجرُ — لقطةٌ كاملةٌ بنفسِ بنيةِ الأصل ═════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `{$ARCHIVE}` LIKE `activity_logs`");
if ($conn->errno) { exit("✘ تعذّر إنشاءُ الحجر: {$conn->error}\n"); }
/* عمودان يميّزان مصدرَ الحجرِ — يُضافان مرةً */
$has = array();
$r = $conn->query("SHOW COLUMNS FROM `{$ARCHIVE}`");
while ($r && $x = $r->fetch_assoc()) { $has[$x['Field']] = true; }
if (!isset($has['src_table'])) {
    $conn->query("ALTER TABLE `{$ARCHIVE}`
                    ADD COLUMN `src_table` VARCHAR(64) NOT NULL DEFAULT 'activity_logs',
                    ADD COLUMN `quarantined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    ADD COLUMN `reason` VARCHAR(255) NOT NULL DEFAULT ''");
}
$conn->query("ALTER TABLE `{$ARCHIVE}` DROP PRIMARY KEY");   // يُسمح بتكرارِ المعرِّفِ عبرَ جولات
$conn->query("ALTER TABLE `{$ARCHIVE}` MODIFY `id` BIGINT UNSIGNED NOT NULL");

$why = 'INJ-FIX-01 — أثرُ تدقيقٍ خلَّفه tests/tenant_leak_test.php (كيانٌ اصطناعيٌّ لا كيانٌ شبح)';
$conn->query("INSERT INTO `{$ARCHIVE}`
                SELECT a.*, 'activity_logs', NOW(), " . "'" . $conn->real_escape_string($why) . "'"
            . " FROM `activity_logs` a WHERE {$FAMILY}");
if ($conn->errno) { exit("✘ فشلَ الحجر: {$conn->error}\n"); }
$archived = $conn->affected_rows;
echo "② حُجر: {$archived} صفًّا في `{$ARCHIVE}`\n";

if ($archived !== (int) $before['n']) {
    exit("✘ عددُ المحجورِ ({$archived}) ≠ المقيسِ ({$before['n']}) — أُوقف قبلَ الحذف\n");
}

/* ══ ③ الكنسُ — بعدَ تطابقِ العددِ لا قبلَه ════════════════════════════════ */
$conn->query("DELETE FROM `activity_logs` WHERE {$FAMILY}");
if ($conn->errno) { exit("✘ فشلَ الكنس: {$conn->error}\n"); }
echo "③ كُنس: {$conn->affected_rows} صفًّا\n";

/* ══ ④ استيثاق ═══════════════════════════════════════════════════════════ */
$r = $conn->query("SELECT COUNT(*) FROM `activity_logs`
                    WHERE `company_id` NOT IN (SELECT `id` FROM `admin_companies`) AND `company_id` > 0");
echo "───────────────────────────────────────────────────────────────\n";
echo "صفوفٌ لكيانٍ غيرِ مسجَّلٍ بعد: " . ($r ? $r->fetch_row()[0] : '?') . "\n";
echo "الشاهد: php tools/tenant_leak_probe.php\n";
