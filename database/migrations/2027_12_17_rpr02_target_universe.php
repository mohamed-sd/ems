<?php
/**
 * 2027_12_17_rpr02_target_universe.php — كونُ الأهدافِ الموحَّد · `RPR-02` §٤·١
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **النصُّ الحاكم** — `RPR-02` §٤·١: *«قبل أي حكم: ادمج مستهدفنا مع أشباحِ
 *   سجلكم في كونٍ واحد **بمعرّفٍ واحد لكلِّ هدف**. فالهدفُ الموجودُ في الاثنين
 *   هدفٌ واحد لا اثنان، والموجودُ في أحدهما فقط يُسمّى مصدرُه. ⛔ **ولا تُقاس
 *   تغطيةٌ على كونَين**»*.
 *
 * ◆ **والكونان مقيسان لا مظنونان**:
 *   · **مستهدَفُنا** `repair01_requirements` — ٤٣٣ صفًّا مفتاحُها **اسمٌ عربيٌّ
 *     + وحدة**، ولا `screen_id` فيها.
 *   · **سجلُّهم** `repair01_screen_registry` — و`GHOST_TARGET` منها **١٦٠**
 *     مفتاحُها **مسارُ ملفٍّ** و`canonical_label_ar` فيها **فارغٌ في ١٥٧**.
 *   ⇒ **مفتاحان مختلفان لا مفتاحٌ واحدٌ سيّئُ الكتابة** — وهذا سببُ أنَّ ١٥٧
 *      متطلبًا لم تجد مقابلَها، لا أنَّ أسطحَها غائبة.
 *
 * ◆ **والمعرِّفُ القانونيُّ `TGT-nnnn`** يحمل الهدفَ أيًّا كان مصدرُه، ويحمل
 *   `source` من ثلاثة: `OURS` · `THEIRS` · `BOTH`. **فالمشتركُ صفٌّ واحد.**
 *
 * ◆ **والحكمُ لا يُكتب بلا شاهد** — `RPR-02` §٤·٢: *«فحكمٌ صحيحٌ بلا شاهدٍ لا
 *   يُقبل»*. فعمودُ `verdict` يبقى **فارغًا** حيث الدليلُ غيرُ قاطع، ⛔ **ولا
 *   يُملأ بـ`NOT_BUILT` لمجرّدِ أنَّ الاسمَ لم يُطابَق** — وذاك عجزٌ من طرحِ
 *   عددَين يمنعه §١١.
 *
 * ◆ **والمقياسان منفصلان** (§٤·٢): `Target Decision Closure` كم هدفٍ له حكمٌ ·
 *   و`Target Realization` كم هدفًا منطبقًا تحقّق. ⛔ **ولا تُخلطان.**
 *
 * التشغيل: php database/migrations/2027_12_17_rpr02_target_universe.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_target_universe` (
  `target_uid`      VARCHAR(12)  NOT NULL COMMENT 'المعرف القانوني الواحد لكل هدف — TGT-nnnn',
  `source`          ENUM('OURS','THEIRS','BOTH') NOT NULL
                    COMMENT 'OURS دفتر المتطلبات · THEIRS سجل الشاشات · BOTH هدف واحد لا اثنان',
  `unit`            VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'رمز النطاق — 21 لا 22',
  `name_ar`         VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الاسم كما ورد في مصدره',
  `name_norm`       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الاسم مطبعا — مفتاح المطابقة',
  `screen_file`     VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'مسار الملف حيث عرف',
  `requirement_id`  VARCHAR(32)  NOT NULL DEFAULT '' COMMENT 'من دفترنا',
  `screen_id`       VARCHAR(12)  NOT NULL DEFAULT '' COMMENT 'من سجلهم',
  `gap_id`          INT(11)      NULL COMMENT 'صف دفتر الاهداف غير المبنية',
  `match_method`    ENUM('EXACT_UNIT','EXACT_ANY','COMPOUND_SPLIT','CONTAINMENT_CANDIDATE','NONE')
                    NOT NULL DEFAULT 'NONE' COMMENT 'كيف طوبق — ويعلن ولا يخفى',
  `match_witness`   VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الشاهد المقيس على المطابقة',
  `verdict`         ENUM('MATCHED','MERGED_INTO','TAB_CHILD','PROJECTION','NOT_BUILT',
                         'NOT_APPLICABLE','RETIRED_TARGET') NULL
                    COMMENT 'احكام RPR-02 §4-2 السبعة — وفارغ يعني لم يحكم بعد',
  `verdict_witness` VARCHAR(400) NOT NULL DEFAULT ''
                    COMMENT 'مرجع الدليل ومصدر القرار — وحكم بلا شاهد لا يقبل',
  `verdict_snapshot` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'معرف اللقطة التي قيس عليها',
  `verdict_at`      DATETIME NULL,
  PRIMARY KEY (`target_uid`),
  KEY `ix_tu_unit` (`unit`),
  KEY `ix_tu_norm` (`name_norm`),
  KEY `ix_tu_verdict` (`verdict`),
  KEY `ix_tu_req` (`requirement_id`),
  CONSTRAINT `chk_tu_witness` CHECK (`verdict` IS NULL OR `verdict_witness` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 §4-1 كون الاهداف الموحد — معرف واحد لكل هدف'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }
echo "  ✔ `repair01_target_universe` — والقاعدةُ الصلبةُ: لا حكمَ بلا شاهد\n";

$r = $conn->query("SELECT COUNT(*) FROM repair01_target_universe");
printf("  صفوفٌ الآن: %d\n", (int) $r->fetch_row()[0]);

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ الكونُ مُهيَّأ — والبناءُ بـ`rpr02_target_universe.php`\n";
