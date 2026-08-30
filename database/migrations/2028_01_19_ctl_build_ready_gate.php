<?php
/**
 * 2028_01_19_ctl_build_ready_gate.php — موضعُ بوّابةِ جاهزيّةِ البناء
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **أمرُ الضبطِ §٣**: *«لا تتحوّل الأهدافُ غيرُ المبنيّةِ تلقائيًّا إلى شاشاتٍ
 *   مستقلّة … لكلِّ Target حدٌّ أدنى إلزاميٌّ قبل دخولِه البناء»* — والبوّابةُ
 *   تُكتب هنا صفًّا لكلِّ هدفٍ غيرِ مبنيٍّ بحقولِه العشرةِ وحاجبِه المسمّى.
 * ◆ ⛔ **ولا يُؤلَّف ما لا مصدرَ له**: حقلٌ لا يُشتقُّ من الدفترِ أو التصميمِ
 *   يُكتب حاجبًا باسمِه — فبوّابةٌ ممتلئةٌ بالتلفيقِ أسوأُ من غيابِها.
 * التشغيل: php database/migrations/2028_01_19_ctl_build_ready_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_build_ready` (
  `target_uid`        VARCHAR(16) NOT NULL,
  `requirement_id`    VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'او تصريف موثق في الكون',
  `owner_domain`      VARCHAR(12) NOT NULL DEFAULT '',
  `canonical_entity`  VARCHAR(80) NOT NULL DEFAULT '' COMMENT 'فارغ = غير مسمى بعد — حاجب للمعاملات',
  `grain`             VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'حبة التصميم من الدفتر — لا تؤلف',
  `realization_type`  VARCHAR(32) NOT NULL DEFAULT 'UNDECIDED' COMMENT 'MENU_SCREEN_DESIGNED/CHILD_OR_TAB/PROJECTION_REPORT/UNDECIDED',
  `source_of_truth`   VARCHAR(190) NOT NULL DEFAULT '',
  `sm_applicable`     ENUM('YES','NO','UNDETERMINED') NOT NULL DEFAULT 'UNDETERMINED' COMMENT 'STATE_MACHINE_APPLICABLE — بالانطباق لا بالعدد',
  `wf_applicable`     ENUM('YES','NO','UNDETERMINED') NOT NULL DEFAULT 'UNDETERMINED' COMMENT 'WORKFLOW_APPLICABLE',
  `decision_impact`   VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'NOT_AFFECTED/AFFECTED_PENDING — على مستوى الهدف لا البرنامج',
  `build_blocker`     VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الحاجب المسمى — وفارغه يعني لا حاجب',
  `build_ready`       ENUM('YES','NO') NOT NULL DEFAULT 'NO',
  `witness`           VARCHAR(600) NOT NULL COMMENT 'كيف اشتق كل حقل — ولا صف بلا شاهد',
  `snapshot_id`       VARCHAR(48) NOT NULL,
  `gated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_uid`),
  KEY `ix_br_ready` (`build_ready`),
  KEY `ix_br_owner` (`owner_domain`),
  CONSTRAINT `chk_br_witness` CHECK (`witness` <> ''),
  CONSTRAINT `chk_br_yes`     CHECK (`build_ready` = 'NO' OR `build_blocker` = '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='امر الضبط 3 · بوابة جاهزية البناء — صف لكل هدف غير مبني بحاجبه المسمى'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }
$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_build_ready")->fetch_row()[0];
echo "  ✔ `repair01_build_ready` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ⛔ قيدُ المخطَّط: `build_ready='YES'` لا يقبل حاجبًا غيرَ فارغ\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ البوّابةِ مفتوح\n";
