<?php
/**
 * 2028_01_02_rpr02_sod_test_registry.php — سجلُّ ربطِ الفاحصِ السالبِ بفصلِ واجبٍ بعينِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — `RPR-02` §١٢ المقياس **#٥** («اختبارٌ سالبٌ لفصلِ
 *   الواجباتِ الحرج») كان **محجوبًا** بنصِّه: *«فواحصُ سالبةٌ على القرص **٢٠** ·
 *   **ولا سجلَّ يربط فاحصًا بفصلِ واجباتٍ بعينِه** ⇒ العددُ لا يصلح بسطًا ولا
 *   مقامًا»*. **والحجبُ صحيحٌ**: عشرون ملفًّا عددٌ لا يقول أيَّ واجبٍ حُرِس.
 *
 * ◆ **والمفتاحان مسمَّيان** (‏شرطُ `CONTINUE` §٢ الأوّل):
 *   · **مفتاحُ فصلِ الواجب**: `repair01_w{N}_sod.process_key` — **٩٢ عمليةً
 *     حرِجةً في عشرِ موجات**، ولكلٍّ رمزٌ قانونيٌّ واحدٌ (`acc.entry.post`).
 *   · **مفتاحُ الفاحص**: اسمُ ملفِّ الفاحصِ السالبِ ودعواه.
 *
 * ⛔ **و`enforced_by` ليس المفتاح — وقد جُرِّب فسقط**: العمودُ **ليس موحَّدًا
 *   عبرَ الموجات**. في `W7` و`W9` رموزٌ (`SAME_ACTOR_AWARD_AND_APPROVE`)، وفي
 *   `W8` **جُملٌ نثريّةٌ** تبدأ بـ`ENFORCED:`/`NOT_ENFORCED:` وتصف السلوكَ
 *   وصفًا، وفي `W6` و`W15` **لا وجودَ للعمودِ أصلًا**. فالمصالحةُ عليه تُنتج
 *   مفتاحًا يصيب موجةً ويخطئ أخرى — **وذلك مفتاحٌ مُلفَّقٌ لا مفتاح**.
 *   ⇒ فالسجلُّ يمسك `process_key` مفتاحًا **ويحفظ `enforced_by` كما ورد**
 *   مصنَّفًا بصنفِه (`CODE_LIST` · `PROSE` · `ABSENT`)، **فيُقرأ العطبُ ولا يُدهَس**.
 *
 * ◆ **وثلاثُ قواعدَ صلبةٍ تُسنُّ الآن** والجدولُ فارغٌ فيصدق شرطُها:
 *   · **شاهدٌ لازم** — فصفٌّ بلا شاهدٍ لا يُراجَع.
 *   · **والمربوطُ يُلزِم ملفَّه** — `bound = 1` بلا `test_file` دعوى بلا دليل.
 *   · **ولقطةٌ لازمة** — فرقمٌ بلا لقطةٍ لا يُعرف أيَّ نسخةٍ يمثّل.
 *
 * ⛔ **ولا يُقرأ هذا السجلُّ إثباتًا أنَّ الفاحصَ يحمرُّ فعلًا** — يُثبت أنَّ
 *   **فاحصًا مسمًّى يدَّعي حراسةَ واجبٍ مسمًّى**. وحمرتُه الفعليّةُ تُقاس بتشغيلِه،
 *   **وذاك حاجبٌ ثانٍ لا هذا**.
 *
 * التشغيل: php database/migrations/2028_01_02_rpr02_sod_test_registry.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `repair01_sod_test_registry` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `wave`            VARCHAR(8)   NOT NULL COMMENT 'الموجة التي يقع فيها فصل الواجب',
  `process_key`     VARCHAR(120) NOT NULL COMMENT 'المفتاح القانوني لفصل الواجب — الطرف الاول',
  `forbidden_combo` VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'التركيبة الممنوعة كما وردت',
  `enforced_raw`    VARCHAR(600) NOT NULL DEFAULT '' COMMENT 'enforced_by كما ورد — لا يصحح ولا يوحد',
  `enforced_kind`   ENUM('CODE_LIST','PROSE','ABSENT') NOT NULL
                    COMMENT 'صنف ما ورد: رموز · نثر يصف · لا عمود اصلا',
  `test_file`       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'ملف الفاحص السالب — الطرف الثاني',
  `test_claim`      VARCHAR(400) NOT NULL DEFAULT '' COMMENT 'الدعوى التي يذكرها الفاحص',
  `bound`           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل ارتبط الطرفان بمعرف',
  `witness`         VARCHAR(600) NOT NULL COMMENT 'شاهد الربط او شاهد انعدامه — ولا صف بلا شاهد',
  `snapshot_id`     VARCHAR(48)  NOT NULL COMMENT 'اللقطة التي قيس عليها',
  `measured_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wave_key` (`wave`, `process_key`),
  KEY `ix_bound` (`bound`),
  KEY `ix_kind` (`enforced_kind`),
  KEY `ix_snap` (`snapshot_id`),
  CONSTRAINT `chk_sodt_witness`  CHECK (`witness` <> ''),
  CONSTRAINT `chk_sodt_snapshot` CHECK (`snapshot_id` <> ''),
  CONSTRAINT `chk_sodt_bound`    CHECK (`bound` = 0 OR `test_file` <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='RPR-02 12-5 · سجل ربط الفاحص السالب بفصل واجب بعينه — بشاهد لكل صف'");
if (!$ok) { exit("✘ تعذّر إنشاءُ الجدول: {$conn->error}\n"); }

$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_sod_test_registry")->fetch_row()[0];
echo "  ✔ `repair01_sod_test_registry` جاهزٌ — صفوفٌ فيه $n\n";
echo "  ✔ ثلاثُ قواعدَ صلبة: شاهدٌ لازمٌ · ولقطةٌ لازمةٌ · والمربوطُ يُلزِم ملفَّه\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ موضعُ السجلِّ مفتوحٌ — وجداولُ `repair01_w*_sod` لم تُمَسّ\n";
