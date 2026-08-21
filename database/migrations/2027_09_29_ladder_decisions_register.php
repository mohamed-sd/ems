<?php
/**
 * 2027_09_29_ladder_decisions_register.php
 *   سجلُّ قراراتِ السلالمِ الواحد — الشرطُ البنيويُّ لوصلِ الثمانيةِ الباقية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **«لا يدَ تمشي خطوتَين» لا تُنفَّذ بلا سجلٍّ يُسأل**. سلسلةُ الوحداتِ لها
 *   `unit_approvals` تُسأل، وبقيةُ السلالمِ (LD-06..LD-13) **بلا سجلٍّ مكافئ**
 *   — فالقاعدةُ فيها **كلامٌ لا حكم**.
 *
 * ◆ **وسجلٌّ واحدٌ لكلِّ السلالمِ لا سجلٌّ لكلِّ إدارة**: `scope_key` يحمل نسخةَ
 *   السلّم، فالعقدتان المتصلتان بنسخةٍ واحدة (١٧ و١٨ في `LD-06-INST`) تُحسَبان
 *   مستندًا واحدًا — **فتكرارُ معرِّفِ السلّمِ لا يُنشئ طلبَ اعتمادٍ ثانيًا**،
 *   ولا يوقّع المستخدمُ اعتمادًا واحدًا مرتين لأن الواجهةَ قُسِّمت شاشتين.
 *
 * ◆ **والمفتاحُ الفريدُ يخدم غرضين**: يمنع القرارَ مرتين (عطالة)، ويجعل فحصَ
 *   «اليدِ الواحدة» قياسًا على صفوفٍ لا افتراضًا.
 *
 * التشغيل:  php database/migrations/2027_09_29_ladder_decisions_register.php
 * الرجوع :  php database/migrations/2027_09_29_ladder_decisions_register.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP TABLE IF EXISTS `gov_ladder_decisions`");
    echo "↺ أُسقط سجلُّ قراراتِ السلالم\n";
    exit(0);
}

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_ladder_decisions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`   INT UNSIGNED NOT NULL,
  `ladder_code`  VARCHAR(12)  NOT NULL,
  `subject_kind` VARCHAR(40)  NOT NULL COMMENT 'نوعُ المستند — claim_invoice · payment · settlement …',
  `subject_ref`  BIGINT UNSIGNED NOT NULL,
  `scope_key`    VARCHAR(96)  NOT NULL COMMENT 'نسخةُ السلّم — عقدتان متصلتان تتشاركانها',
  `step_no`      TINYINT UNSIGNED NOT NULL,
  `actor_id`     INT UNSIGNED NOT NULL,
  `note`         VARCHAR(190) NULL,
  `decided_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_gld` (`company_id`,`scope_key`,`step_no`,`actor_id`),
  KEY `ix_gld_ladder` (`ladder_code`,`subject_kind`),
  KEY `ix_gld_scope` (`scope_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سجلُّ قراراتِ السلالمِ الواحد — INJ-CHAIN-CLOSE-01 · GAP-01'");
if (!$ok) { exit("✘ تعذّر الإنشاء: {$conn->error}\n"); }
echo "① أُنشئ `gov_ladder_decisions` — سجلٌّ واحدٌ لكلِّ السلالم\n";
echo "② المفتاحُ الفريدُ (شركة · نسخةُ سلّم · خطوة · فاعل) يمنع القرارَ مرتين\n";

ems_migration_recorded(__FILE__, $conn, 0);
