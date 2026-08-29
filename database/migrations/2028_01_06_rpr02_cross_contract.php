<?php
/**
 * 2028_01_06_rpr02_cross_contract.php — موضعُ عقدِ الكتابةِ العابرةِ مقيسًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — المقياسُ **#١١** كان يقول عن نفسِه في اللوحة: *«⛔
 *   **والعقدُ غيرُ مقيسٍ بعدُ**، فالرقمُ **سقفُ الخرقِ لا الخرقُ**: منه ما له
 *   عقدٌ مُعلَنٌ لم يُسجَّل»*. ⇒ **فالسقفُ يبقى سقفًا ما لم يُقَسِ العقد**.
 *
 * ◆ **والعقدُ ليس وثيقةً تُكتب بل مسارُ كتابةٍ يُقاس**: `ADR-15` يجعل
 *   `ems_business_events` سجلَّ الحقائق، والكتابةُ العابرةُ تمرُّ **بخدمةِ
 *   المجالِ المالكةِ أو بالناشر** — لا بجملةِ `SQL` خامٍّ في شاشةِ إدارةٍ أخرى.
 *   ⇒ **فهذا الجدولُ يحفظ القياسَ لا الدعوى**: لكلِّ كاتبٍ **عددُ جُملِ الكتابةِ
 *   الخامّةِ** و**الأبوابُ المسمّاةُ** التي يمرُّ بها، وحكمُه من ثلاثة.
 *
 * ⛔ **وثلاثةُ أحكامٍ لا اثنان** — و`X0` هي المفتاح: سطحٌ **يُصرِّح بجدولِه في
 *   ملفِّه** (`G1`/`G1B`) **ولا يكتب عليه جملةً واحدة** ⇒ **ليس كاتبًا أصلًا**،
 *   ولا يُعدُّ طرفًا في عبورٍ ولا في ازدواجِ مصدر. ولولا هذا التمييزُ لَعُدَّ
 *   التصريحُ كتابةً و**احتُسب خرقٌ على من لم يكتب**.
 *
 * ◆ **وقاعدتان صلبتان تُسنّان والجدولُ فارغ**:
 *   · شاهدٌ لازمٌ ولقطةٌ لازمة.
 *   · و**`X2_RAW_DIRECT` يوجب `raw_writes > 0`** — فحكمٌ بالكتابةِ المباشرةِ
 *     بصفرِ جُملٍ **دعوى بلا عدد**، والعكسُ كذلك: صفرُ حكمٍ مع جُملٍ يخفيها.
 *
 * التشغيل: php database/migrations/2028_01_06_rpr02_cross_contract.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_cross_contract` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `entity`         VARCHAR(64) NOT NULL COMMENT 'الكيان المكتوب',
  `entity_verdict` ENUM('NOT_A_DUPLICATE','CONTRACTED','DIRECT_WRITE_BREACH') NOT NULL
                   COMMENT 'حكم الكيان جملة بعد اسقاط من ليس كاتبا',
  `screen_id`      VARCHAR(12) NOT NULL COMMENT 'الكاتب',
  `owner_code`     VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'ادارة الكاتب',
  `grain_rule`     VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'قاعدة نسبة الكيان اليه',
  `raw_writes`     SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0
                   COMMENT 'جمل الكتابة الخامة على الكيان في مداه الخاص',
  `gates`          VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'الابواب المسماة: ناشر او خدمة مجال',
  `writer_verdict` ENUM('X0_NO_MEASURED_WRITE','X1_SERVICE_MEDIATED','X2_RAW_DIRECT') NOT NULL
                   COMMENT 'حكم الكاتب — ثلاثة لا اثنان',
  `witness`        VARCHAR(600) NOT NULL COMMENT 'شاهد الحكم — ولا صف بلا شاهد',
  `snapshot_id`    VARCHAR(48) NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `measured_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ent_scr` (`entity`, `screen_id`),
  KEY `ix_ent_verdict` (`entity_verdict`),
  KEY `ix_wr_verdict` (`writer_verdict`),
  CONSTRAINT `chk_cx_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_cx_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_cx_raw`      CHECK ((`writer_verdict` = 'X2_RAW_DIRECT' AND `raw_writes` > 0)
                                   OR (`writer_verdict` <> 'X2_RAW_DIRECT' AND `raw_writes` = 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 5-9 · عقد الكتابة العابرة مقيسا من الاثر — بشاهد لكل كاتب'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_cross_contract")->fetch_row()[0];
echo "  ✔ `repair01_cross_contract` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ قاعدةٌ صلبةٌ تربط الحكمَ بعددِه: `X2` يوجب جُملًا · وغيرُه يوجب صفرًا\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ العقدِ مفتوحٌ — و`repair01_canonical_source` لم يُمَسّ\n";
