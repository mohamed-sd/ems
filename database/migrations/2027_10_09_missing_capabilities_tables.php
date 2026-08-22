<?php
/**
 * 2027_10_09_missing_capabilities_tables.php
 *   جداولُ القدراتِ المفقودةِ فعلًا — بعدَ مصالحةِ ما أُغلق بالسلسلة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المصالحةُ قبلَ البناء — بنصِّ أمرِ التنفيذ**: «القدرةُ التي بُنيت فعلًا في
 *   مرحلةٍ سابقةٍ لا يُعاد بناؤها. ثم ابنِ فقط ما بقي REQUIRED_AND_MISSING فعلًا».
 *   و`ap_oblig_gen` **قدرةٌ واحدةٌ لا مهمتان**: العقدةُ 23 في السلسلةِ هي نفسُها
 *   القدرةُ المفقودةُ في وثيقةِ الموردين — وقياسُ المصالحةِ أثبتها حيّةً على
 *   `contract_obligations.php` بأثرٍ مقيس. ⇒ **`EVIDENCE_CLOSED_BY_CHAIN`
 *   ولا يُبنى لها سطحٌ موازٍ.**
 *
 * ◆ **فالباقي خمسٌ**: ثلاثٌ في المبيعاتِ (احتياجُ العميلِ وطلبُ العرض · بنودُ
 *   العروض · التفاوضُ ومراجعاتُ العرض) واثنتان في الموردين (المخالفاتُ
 *   والجزاءات · لوحةُ إدارةِ الموردين). ولوحةُ الإدارةِ **قراءةٌ بلا جدولٍ جديد**.
 *
 * ◆ **والوحداتُ التعاقديةُ المرقَّمةُ معلَّقةٌ لا مبنيةٌ ولا ملغاة**: قرارُ
 *   الوثيقةِ (تصحيحُ ت-3) «لا تُبنى قبلَ إثباتِ عدمِ التكافؤ مع شاشةِ الإسنادِ
 *   القائمة». والإثباتُ عملُ قياسٍ لا حكمُ منفِّذ — فيُقيَّد معلَّقًا بمعيارِه.
 *
 * التشغيل:  php database/migrations/2027_10_09_missing_capabilities_tables.php
 * الرجوع :  php database/migrations/2027_10_09_missing_capabilities_tables.php --revert
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

$T = array('sup_violations', 'sal_quotation_revisions', 'sal_quotation_lines', 'sal_client_needs');

if (in_array('--revert', $argv, true)) {
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($T as $t) { $conn->query("DROP TABLE IF EXISTS `{$t}`"); }
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    $conn->query("DELETE FROM `gov_screen_path_map` WHERE `book_file` = 'units.php'
                    AND `ruling` = 'PENDING_EQUIVALENCE'");
    echo "↺ أُسقطت " . count($T) . " جداول\n";
    exit(0);
}

$DDL = array();

/* ── الورقة 06 — احتياجُ العميلِ وطلبُ العرض (28 عمودًا في المرجع) ────────── */
$DDL['sal_client_needs'] = "CREATE TABLE IF NOT EXISTS `sal_client_needs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `need_no`        VARCHAR(30) NOT NULL,
  `opportunity_id` INT UNSIGNED NOT NULL COMMENT 'سجلٌّ تابعٌ للفرصة — لا سطحٌ مستقل',
  `client_id`      INT UNSIGNED NULL,
  `project_id`     INT UNSIGNED NULL,
  `business_model` VARCHAR(80)  NULL COMMENT 'نموذجُ العملِ من السجلِّ المرجعيّ',
  `service_type`   VARCHAR(120) NOT NULL,
  `qty`            DECIMAL(16,4) NOT NULL DEFAULT 0,
  `unit_type`      VARCHAR(16)  NOT NULL DEFAULT 'hour',
  `duration_months` SMALLINT UNSIGNED NULL,
  `required_from`  DATE NULL,
  `site_note`      VARCHAR(200) NULL,
  `notes`          VARCHAR(400) NULL,
  `state`          ENUM('draft','submitted','quoted','closed','cancelled') NOT NULL DEFAULT 'draft',
  `created_by`     INT UNSIGNED NOT NULL,
  `submitted_at`   DATETIME NULL,
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_scn_idem` (`idem_key`),
  UNIQUE KEY `uq_scn_no` (`company_id`,`need_no`),
  KEY `ix_scn_opp` (`opportunity_id`),
  CONSTRAINT `chk_scn_qty` CHECK (`qty` > 0),
  /* ◆ لا طلبَ عرضٍ بلا احتياجٍ مسجَّل — والإصدارُ يشترط الحالةَ `submitted` */
  CONSTRAINT `chk_scn_submit` CHECK (`state` = 'draft' OR `submitted_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الورقة 06 — احتياج العميل وطلب العرض · سجلٌّ تابعٌ للفرصة'";

/* ── الورقة 08 — بنودُ العروض (24 عمودًا) ────────────────────────────────── */
$DDL['sal_quotation_lines'] = "CREATE TABLE IF NOT EXISTS `sal_quotation_lines` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `quotation_id`   INT UNSIGNED NOT NULL COMMENT 'بنودُ العرضِ تابعةٌ لرأسِه',
  `line_no`        SMALLINT UNSIGNED NOT NULL,
  `product_id`     INT UNSIGNED NULL COMMENT 'من كتالوجِ الخدماتِ — اختيارٌ لا كتابةٌ حرة',
  `description`    VARCHAR(240) NOT NULL,
  `qty`            DECIMAL(16,4) NOT NULL,
  `unit_type`      VARCHAR(16) NOT NULL DEFAULT 'hour',
  `unit_price`     DECIMAL(16,2) NOT NULL,
  `currency`       VARCHAR(8) NOT NULL,
  `discount_pct`   DECIMAL(5,2) NOT NULL DEFAULT 0,
  `line_total`     DECIMAL(18,2) NOT NULL COMMENT 'محسوبٌ في طبقةِ الخدمة — يُعرض ولا يُدخَل',
  `notes`          VARCHAR(200) NULL,
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_sql_line` (`company_id`,`quotation_id`,`line_no`),
  KEY `ix_sql_q` (`quotation_id`),
  CONSTRAINT `chk_sql_qty` CHECK (`qty` > 0),
  CONSTRAINT `chk_sql_price` CHECK (`unit_price` >= 0),
  CONSTRAINT `chk_sql_disc` CHECK (`discount_pct` >= 0 AND `discount_pct` <= 100),
  CONSTRAINT `chk_sql_cur` CHECK (CHAR_LENGTH(`currency`) >= 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الورقة 08 — بنود العروض · تابعةٌ لرأسِ العرض'";

/* ── الورقة 09 — التفاوضُ ومراجعاتُ العرض (20 عمودًا) ────────────────────── */
$DDL['sal_quotation_revisions'] = "CREATE TABLE IF NOT EXISTS `sal_quotation_revisions` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `quotation_id`   INT UNSIGNED NOT NULL,
  `revision_no`    SMALLINT UNSIGNED NOT NULL,
  `event_kind`     ENUM('issued','sent','client_counter','revised','accepted','rejected','expired')
                   NOT NULL COMMENT 'واقعةُ تفاوضٍ محكومةٌ من قائمةٍ مغلقة',
  `party`          ENUM('us','client') NOT NULL,
  `note`           VARCHAR(400) NOT NULL COMMENT 'لا واقعةَ تفاوضٍ بلا نصٍّ يشرحها',
  `doc_ref`        VARCHAR(120) NULL,
  `amount_before`  DECIMAL(18,2) NULL,
  `amount_after`   DECIMAL(18,2) NULL,
  `currency`       VARCHAR(8) NULL,
  `valid_until`    DATE NULL,
  `decided_by`     INT UNSIGNED NOT NULL,
  `decided_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `idem_key`       VARCHAR(96) NOT NULL,
  UNIQUE KEY `uq_sqr_idem` (`idem_key`),
  UNIQUE KEY `uq_sqr_rev` (`company_id`,`quotation_id`,`revision_no`),
  KEY `ix_sqr_q` (`quotation_id`),
  CONSTRAINT `chk_sqr_note` CHECK (CHAR_LENGTH(`note`) >= 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الورقة 09 — سجل نسخ العرض ووقائع التفاوض'";

/* ── الورقة م19 — المخالفاتُ والجزاءات ──────────────────────────────────── */
$DDL['sup_violations'] = "CREATE TABLE IF NOT EXISTS `sup_violations` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `violation_no`   VARCHAR(30) NOT NULL,
  `supplier_id`    INT UNSIGNED NOT NULL,
  `contract_id`    INT UNSIGNED NULL,
  `settlement_id`  INT UNSIGNED NULL COMMENT 'سجلٌّ تابعٌ للتسوية — وأثرُه فيها',
  `rule_ref`       VARCHAR(120) NULL COMMENT 'القاعدةُ المخالَفة من `supplier_rules`',
  `violation_kind` ENUM('availability','quality','safety','document','delay','other') NOT NULL,
  `occurred_on`    DATE NOT NULL,
  `description`    VARCHAR(400) NOT NULL,
  `evidence_ref`   VARCHAR(120) NULL,
  `penalty_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `currency`       VARCHAR(8) NULL,
  `recorded_by`    INT UNSIGNED NOT NULL,
  `approved_by`    INT UNSIGNED NULL,
  `approved_at`    DATETIME NULL,
  `state`          ENUM('recorded','reviewed','approved','applied','waived','rejected')
                   NOT NULL DEFAULT 'recorded',
  `waive_reason`   VARCHAR(300) NULL,
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_sv_idem` (`idem_key`),
  UNIQUE KEY `uq_sv_no` (`company_id`,`violation_no`),
  KEY `ix_sv_sup` (`supplier_id`,`state`),
  /* ◆ **مَن رصد لا يعتمد** — والجزاءُ أثرٌ ماليٌّ لا يقرّره راصدُه وحدَه */
  CONSTRAINT `chk_sv_sod` CHECK (`approved_by` IS NULL OR `approved_by` <> `recorded_by`),
  /* ◆ ولا جزاءَ بمبلغٍ بلا عملة · ولا إسقاطَ بلا سببٍ مكتوب */
  CONSTRAINT `chk_sv_cur` CHECK (`penalty_amount` = 0 OR (`currency` IS NOT NULL AND CHAR_LENGTH(`currency`) >= 3)),
  CONSTRAINT `chk_sv_waive` CHECK (`state` <> 'waived' OR CHAR_LENGTH(COALESCE(`waive_reason`,'')) >= 8),
  CONSTRAINT `chk_sv_desc` CHECK (CHAR_LENGTH(`description`) >= 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='الورقة م19 — المخالفات والجزاءات · سجلٌّ تابعٌ للتسوية'";

$made = 0; $fail = array();
foreach (array('sal_client_needs', 'sal_quotation_lines', 'sal_quotation_revisions', 'sup_violations') as $t) {
    if ($conn->query($DDL[$t])) { $made++; echo "  ✔ {$t}\n"; }
    else { $fail[] = "{$t}: {$conn->error}"; echo "  ✘ {$t}: {$conn->error}\n"; }
}
printf("① أُنشئ %d جدولًا من 4\n", $made);
if ($fail) { exit("✘ توقّفت — لا تُقيَّد هجرةٌ نصفُها\n"); }

/* ── ② الوحداتُ التعاقديةُ المرقَّمة — معلَّقةٌ بمعيارِ الإثباتِ لا محذوفة ─── */
$st = $conn->prepare(
  "INSERT INTO `gov_screen_path_map`
     (`book_file`,`real_route`,`ruling`,`owner_dept`,`source_doc`,`reason`)
   VALUES ('units.php','Operations/equipment_quota.php','PENDING_EQUIVALENCE','إدارة التشغيل',
           'INJ-SAL-ALIGN-01',?)
   ON DUPLICATE KEY UPDATE `ruling` = VALUES(`ruling`), `reason` = VALUES(`reason`)");
$why = 'تصحيحُ ت-3: لا تُبنى قبلَ إثباتِ **عدمِ** التكافؤ مع شاشةِ إسنادِ المعداتِ للوحداتِ القائمة. '
     . 'ومعيارُ الإثبات: حقلٌ في المرجعِ الحاكمِ لا نظيرَ له في `equipment_quota`، أو فعلٌ لا تملكه. '
     . 'والإثباتُ عملُ قياسٍ على المصنَّفِ الحاكم — وهو ليس في الحزمة (BLOCKED_EXTERNAL_INPUT).';
$st->bind_param('s', $why);
$st->execute();
$st->close();
echo "② `units.php` قُيِّدت **معلَّقةً على إثباتِ عدمِ التكافؤ** — لا مبنيةً ولا ملغاة\n";

/* ── ③ ما أُغلق بالسلسلةِ لا يُبنى ثانيةً ───────────────────────────────── */
$q = $conn->query("SELECT `build_state`,`carrier_route` FROM `gov_chain_nodes` WHERE `node_no` = 23");
$n23 = $q ? $q->fetch_assoc() : null;
printf("③ `ap_oblig_gen` (العقدة 23): **%s** على %s ⇒ EVIDENCE_CLOSED_BY_CHAIN — ولا سطحَ موازٍ\n",
       $n23 ? $n23['build_state'] : '?', $n23 ? $n23['carrier_route'] : '?');

ems_migration_recorded(__FILE__, $conn, 0);
