<?php
/**
 * 2027_12_08_gov_migration_settlement.php — دفترُ أحكامِ الهجراتِ غيرِ المصالَحة
 * ═══════════════════════════════════════════════════════════════════════════
 * **`RPR-02` §٩ و`RPR-03` §٣·١**: «**ولا تبقى هجرةٌ واحدةٌ بلا حالةٍ محكومةٍ في
 * الدفتر**». والدفترُ `schema_migrations` يحمل **ما طُبِّق**؛ وهذا الجدولُ يحمل
 * **حكمَ ما لم يُقيَّد فيه ولماذا** — فالحكمُ صفٌّ بمرجعِه لا فقرةٌ في وثيقة.
 *
 * ◆ **وثلاثُ عائلاتٍ لا عائلةٌ واحدة** (`kind`):
 *   ① `DISK_NOT_LEDGERED` — ملفٌّ على القرصِ خارجَ الدفتر (السبعُ والخمسون).
 *   ② `LEDGER_NOT_ON_DISK` — صفٌّ في الدفترِ بلا ملفّ (المئتان وواحدٌ وأربعون).
 *   ③ `UNMANAGED_NAME` — ملفٌّ في المجلَّدِ لا يطابق عُرفَ التسميةِ فليس هجرة.
 *
 * ◆ **و`verified` ليست دعوى**: تُرفَع فقط حين يُقاس وجودُ أثرِ الهجرةِ في
 *   `information_schema` كائنًا كائنًا. **والقيدُ `chk_gms_verified_needs_evidence`
 *   يمنع في القاعدةِ أن تُرفَع بلا دليلٍ مكتوب** — ⛔ فلا يُخضِرُّ سكربت.
 *
 * ◆ **و`owner_ref` إلزاميّ**: كلُّ إخراجٍ من المقامِ يحمل مرجعَ قرارِه (البندُ
 *   §٥·٣ · حارسُ الحكمَين المُخرجَين). **وحكمٌ بلا مرجعٍ لا يُقبل.**
 *
 * التشغيل: php database/migrations/2027_12_08_gov_migration_settlement.php
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
$t0 = microtime(true);

$sql = "CREATE TABLE IF NOT EXISTS `gov_migration_settlement` (
  `filename`   VARCHAR(255) NOT NULL COMMENT 'اسمُ ملفِّ الهجرةِ أو صفِّ الدفتر',
  `kind`       ENUM('DISK_NOT_LEDGERED','LEDGER_NOT_ON_DISK','UNMANAGED_NAME') NOT NULL
               COMMENT 'عائلةُ عدمِ المصالحة',
  `ruling`     VARCHAR(48) NOT NULL COMMENT 'الحكمُ المعتمد',
  `evidence`   VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'الدليلُ المقيسُ — كائناتُ المخطَّطِ التي فُحصت',
  `verified`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'أُثبت أثرُه في المخطَّطِ قياسًا',
  `objects_checked` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'مقامُ الفحص — كم كائنًا سُئل',
  `objects_found`   SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'كم منها وُجد',
  `owner_ref`  VARCHAR(190) NOT NULL COMMENT 'مرجعُ القرارِ المخوِّل — ⛔ ولا حكمَ بلا مرجع',
  `settled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `settled_by` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`filename`),
  KEY `ix_gms_kind` (`kind`),
  KEY `ix_gms_ruling` (`ruling`),
  CONSTRAINT `chk_gms_verified_needs_evidence`
    CHECK (`verified` = 0 OR (CHAR_LENGTH(`evidence`) >= 12 AND `objects_checked` > 0)),
  CONSTRAINT `chk_gms_owner_ref`
    CHECK (CHAR_LENGTH(`owner_ref`) >= 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='أحكامُ الهجراتِ غيرِ المصالَحةِ مع الدفتر — RPR-02 §٩ · RPR-03 §٣·١'";

if (!$conn->query($sql)) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }
echo "✔ gov_migration_settlement جاهز\n";

/* ── القيدُ المطلوبُ في `schema_migrations` لمنعِ الازدواج ─────────────────
   ⛔ **والبوّابةُ تحتاج مفتاحًا فريدًا على `filename`** وإلّا صار
   `ON DUPLICATE KEY UPDATE` بلا مفعول، **فيتضاعف الصفُّ صامتًا**. */
$has = $conn->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'
      AND INDEX_NAME = 'uq_schema_migrations_filename'");
$n = $has ? (int) $has->fetch_row()[0] : 0;
if ($n === 0) {
    if ($conn->query("ALTER TABLE `schema_migrations`
                      ADD UNIQUE KEY `uq_schema_migrations_filename` (`filename`)")) {
        echo "✔ أُضيف المفتاحُ الفريدُ على schema_migrations.filename\n";
    } else {
        echo "⚠ تعذّرت إضافةُ المفتاحِ الفريد: {$conn->error}\n";
    }
} else {
    echo "✔ المفتاحُ الفريدُ قائمٌ سلفًا على schema_migrations.filename\n";
}

/* ── وتُقيِّد الهجرةُ نفسَها في دفترِها — وهي القاعدةُ التي تُنفَّذ من اليوم ── */
require_once __DIR__ . '/_ledger.php';
$ms = (int) round((microtime(true) - $t0) * 1000);
ems_migration_recorded(__FILE__, $conn, $ms);
echo "✔ قُيِّدت الهجرةُ في دفترِها\n";
