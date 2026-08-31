<?php
/**
 * 2028_02_01_gov077_consumer_key_hygiene.php — نظافةُ مفتاحِ المستهلك (GAP-77)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:ems_delivery_key_quarantine, constraint:chk_evdeliv_key_machine
 *
 * ◆ الكشفُ `F-H14`: عمودُ `ems_event_deliveries.consumer_key` فيه **8 مفاتيحَ
 *   نصوصًا بشريّةً من بذرِ UAT** («مطابقٌ للمستند المرفق · UAT-2026» …) في
 *   10 صفوفٍ — و`GAP-09` («صفرُ نصٍّ بشريٍّ في حقلِ مفتاح») **أُغلق على مقامٍ
 *   لم يشمل هذا العمود**. فهذه فجوتُها المستقلّة `GAP-77`.
 *
 * ◆ **والعلاجُ بسابقةِ GAP-09 حرفًا**: «الملوَّثُ محجورٌ مؤرشَفٌ قبلَ الحذف»
 *   — تُنسخ الصفوفُ الملوَّثةُ إلى جدولِ حجرٍ بهويّتِها الكاملةِ ثم تُحذف،
 *   **ثم قيدُ مخطَّطٍ يمنع العودةَ بنيويًّا** لا بفاحصٍ يُنسى: المفتاحُ
 *   الآليُّ لا يحوي مسافةً ولا فاصلةً وسطى — فالجملةُ العربيّةُ تُرَدُّ من
 *   القاعدةِ نفسِها.
 *
 * ◆ ⛔ ولا يُحذف صفُّ إنتاجٍ: النطاقُ **بذرُ UAT وحدَه** (`seed_tag='UAT-2026'`
 *   والمفتاحُ ملوَّثٌ معًا) — ودفترُ التسليماتِ الحقيقيُّ لا يُمَسّ.
 *
 * التشغيل: php database/migrations/2028_02_01_gov077_consumer_key_hygiene.php
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

$POLLUTED = "(`consumer_key` LIKE '% %' OR `consumer_key` LIKE '%·%')";

/* ── ① جدولُ الحجرِ — نسخةٌ كاملةُ الهويّةِ لا ملخّص ─────────────────────── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `ems_delivery_key_quarantine` (
    `id` BIGINT UNSIGNED NOT NULL,
    `consumer` VARCHAR(64) NOT NULL,
    `consumer_key` VARCHAR(64) NOT NULL,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `outbox_id` BIGINT UNSIGNED NOT NULL,
    `state` VARCHAR(16) NOT NULL,
    `seed_tag` VARCHAR(32) NULL,
    `quarantined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reason` VARCHAR(200) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GAP-77: حجرُ صفوفِ تسليمٍ مفتاحُها نصٌّ بشريٌّ من بذرِ UAT — مؤرشَفةٌ قبلَ الحذفِ بسابقةِ GAP-09'");
if (!$ok) { exit("⛔ إنشاءُ جدولِ الحجرِ فشل: {$conn->error}\n"); }
echo "① جدولُ الحجرِ قائم\n";

/* ── ② الحجرُ ثم الحذفُ — النطاقُ بذرُ UAT الملوَّثُ وحدَه ───────────────── */
$ok = $conn->query("INSERT IGNORE INTO `ems_delivery_key_quarantine`
        (`id`,`consumer`,`consumer_key`,`event_id`,`outbox_id`,`state`,`seed_tag`,`reason`)
    SELECT `id`,`consumer`,`consumer_key`,`event_id`,`outbox_id`,`state`,`seed_tag`,
           'F-H14 · GAP-77: مفتاحٌ نصٌّ بشريٌّ من بذرِ UAT-2026'
      FROM `ems_event_deliveries`
     WHERE `seed_tag` = 'UAT-2026' AND {$POLLUTED}");
if (!$ok) { exit("⛔ الحجرُ فشل: {$conn->error}\n"); }
$archived = $conn->affected_rows;
$ok = $conn->query("DELETE FROM `ems_event_deliveries` WHERE `seed_tag` = 'UAT-2026' AND {$POLLUTED}");
if (!$ok) { exit("⛔ الحذفُ بعد الحجرِ فشل: {$conn->error}\n"); }
$deleted = $conn->affected_rows;
printf("② حُجر %d وحُذف %d — والدفترُ الحقيقيُّ لم يُمَسّ\n", $archived, $deleted);

/* ── ③ ما بقي ملوَّثًا خارجَ البذرِ يُسمّى ولا يُحذف صامتًا ──────────────── */
$q = $conn->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE {$POLLUTED}");
$left = $q ? (int) $q->fetch_row()[0] : -1;
if ($left !== 0) { exit("⛔ بقي {$left} صفًّا ملوَّثًا خارجَ بذرِ UAT — يحتاج حكمًا لا حذفًا صامتًا\n"); }
echo "③ صفرُ مفتاحٍ ملوَّثٍ باقٍ\n";

/* ── ④ القيدُ المانعُ — القاعدةُ تردُّ الجملةَ لا الفاحص ─────────────────── */
$conn->query("ALTER TABLE `ems_event_deliveries` DROP CONSTRAINT `chk_evdeliv_key_machine`");
$ok = $conn->query("ALTER TABLE `ems_event_deliveries`
    ADD CONSTRAINT `chk_evdeliv_key_machine`
    CHECK (`consumer_key` NOT LIKE '% %' AND `consumer_key` NOT LIKE '%·%')");
if (!$ok) { exit("⛔ قيدُ المفتاحِ الآليِّ فشل: {$conn->error}\n"); }
echo "④ قيدُ `chk_evdeliv_key_machine` مفعَّل — المسافةُ والفاصلةُ الوسطى تُرَدّان بنيويًّا\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "✔ اكتملت وقُيّدت ذاتيًّا\n";
