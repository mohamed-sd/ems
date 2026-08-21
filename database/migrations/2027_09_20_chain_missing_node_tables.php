<?php
/**
 * 2027_09_20_chain_missing_node_tables.php
 *   جداولُ العقدِ الستِّ المفقودةِ فعلًا — INJ-CHAIN-CLOSE-01 الموجات 3·4·5·7
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولا يُبنى إلا ما ثبت غيابُه**: مصالحةُ `injchain01_node_reconcile`
 *   أثبتت أن ثلاثَ عشرةَ عقدةً مبنيةٌ باسمِها، وعشرًا حيّةٌ على سطحٍ آخرَ
 *   بأثرٍ مقيس، و**ستًّا** مفقودةٌ فعلًا. فهذه جداولُ الستِّ وحدَها:
 *     ٩ الاعتمادُ الماليُّ النهائيّ · ١٣ تصحيحُ الوحدات · ١٦ الاستحقاق ·
 *     ١٧ شهادةُ الإنجاز · ١٨ فاتورةُ المطالبة · ٢٥ دفعاتُ الدفع
 *   ومعها الشرطُ السابقُ خارجَ العقد: **سجلُّ المستفيدين والحساباتِ البنكية**.
 *
 * ◆ **وفصلُ الواجباتِ في البنيةِ لا في النصِّ وحدَه**: كلُّ جدولٍ يفصل
 *   **المُعِدَّ** عن **المعتمِد** عن **المنفِّذِ نقدًا** عن **مُجيزِ الترحيل** —
 *   وقيدُ `CHECK` يمنع أن تجتمع يدان في خانةٍ واحدة حيثُ يجب افتراقُهما.
 *
 * ◆ **ولا كاتبَ بشريٌّ إلى دفترِ الأستاذ**: عمودُ `journal_entry_id` يُملأ
 *   بمرجعِ القيدِ الذي أنشأه محرّكُ الترحيل، ولا يُكتب فيه يدويًّا.
 *
 * التشغيل:  php database/migrations/2027_09_20_chain_missing_node_tables.php
 * الرجوع :  php database/migrations/2027_09_20_chain_missing_node_tables.php --revert
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

$TABLES = array('tre_pay_batch_lines', 'tre_pay_batches', 'tre_beneficiaries',
                'ar_claim_invoices', 'ar_completion_certs', 'ar_accruals',
                'unit_corrections', 'unit_final_approvals');

if (in_array('--revert', $argv, true)) {
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    foreach ($TABLES as $t) { $conn->query("DROP TABLE IF EXISTS `{$t}`"); }
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    echo "↺ أُسقطت " . count($TABLES) . " جدولًا\n";
    exit(0);
}

$DDL = array();

/* ── العقدة ٩ — الاعتمادُ الماليُّ النهائيّ · LD-07 ───────────────────────── */
$DDL['unit_final_approvals'] = "CREATE TABLE IF NOT EXISTS `unit_final_approvals` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `period`         CHAR(7) NOT NULL COMMENT 'YYYY-MM — الفترةُ المقفلة',
  `entry_id`       INT UNSIGNED NOT NULL COMMENT 'الواقعةُ التي قُفل أثرُها',
  `ladder_id`      VARCHAR(12) NOT NULL DEFAULT 'LD-07',
  `prepared_by`    INT UNSIGNED NOT NULL COMMENT 'المحاسبُ المنتدبُ — إعدادُ بياناتِ القيدِ فقط',
  `approved_by`    INT UNSIGNED NULL     COMMENT 'الاعتمادُ الماليُّ النهائيّ',
  `approved_at`    DATETIME NULL,
  `control_by`     INT UNSIGNED NULL     COMMENT 'رئيسُ الحساباتِ — إجازةٌ مستقلةٌ تسبق الترحيل',
  `control_at`     DATETIME NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL COMMENT 'يملؤه محرّكُ الترحيلِ وحدَه',
  `state`          ENUM('prepared','approved','controlled','posted','rejected') NOT NULL DEFAULT 'prepared',
  `reject_reason`  VARCHAR(160) NULL,
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ufa_idem` (`idem_key`),
  KEY `ix_ufa_co_state` (`company_id`,`state`),
  KEY `ix_ufa_entry` (`entry_id`),
  /* ◆ لا يدَ تجمع الإعدادَ والاعتماد، ولا الاعتمادَ والرقابة */
  CONSTRAINT `chk_ufa_sod_prep` CHECK (`approved_by` IS NULL OR `approved_by` <> `prepared_by`),
  CONSTRAINT `chk_ufa_sod_ctrl` CHECK (`control_by`  IS NULL OR `control_by`  <> `approved_by`),
  /* ◆ لا ترحيلَ قبلَ الإجازة */
  CONSTRAINT `chk_ufa_post_after_control` CHECK (`journal_entry_id` IS NULL OR `control_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عقدة 9 — الاعتماد المالي النهائي · LD-07 · لا ترحيل قبل الإجازة'";

/* ── العقدة ١٣ — تصحيحُ الوحداتِ بالسلسلةِ الثلاثية ───────────────────────── */
$DDL['unit_corrections'] = "CREATE TABLE IF NOT EXISTS `unit_corrections` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `entry_id`       INT UNSIGNED NOT NULL COMMENT 'الواقعةُ المُصحَّحة',
  `correction_kind` ENUM('adjustment','reversal','split','merge') NOT NULL,
  `field_changed`  ENUM('quantity','responsible_party','time_state','classification') NOT NULL,
  `value_before`   VARCHAR(120) NOT NULL,
  `value_after`    VARCHAR(120) NOT NULL,
  `reason`         VARCHAR(400) NOT NULL COMMENT 'سببٌ مكتوبٌ إلزامًا — لا تصحيحَ بلا سبب',
  `doc_ref`        VARCHAR(120) NULL,
  `requested_by`   INT UNSIGNED NOT NULL,
  /* ◆ **السلسلةُ الثلاثيةُ كاملةً أو لا تمرّ** — عميلٌ ومورّدٌ ومشغّل */
  `client_ok_by`   INT UNSIGNED NULL, `client_ok_at`   DATETIME NULL,
  `supplier_ok_by` INT UNSIGNED NULL, `supplier_ok_at` DATETIME NULL,
  `worker_ok_by`   INT UNSIGNED NULL, `worker_ok_at`   DATETIME NULL,
  `state`          ENUM('draft','in_chain','approved','applied','rejected','reversed') NOT NULL DEFAULT 'draft',
  `applied_at`     DATETIME NULL,
  `reversal_ref`   VARCHAR(120) NULL,
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_uc_idem` (`idem_key`),
  KEY `ix_uc_co_state` (`company_id`,`state`),
  KEY `ix_uc_entry` (`entry_id`),
  /* ◆ لا يُطبَّق تصحيحٌ إلا بمرورِ الأطرافِ الثلاثةِ كلِّها */
  CONSTRAINT `chk_uc_triple` CHECK (`applied_at` IS NULL OR
      (`client_ok_at` IS NOT NULL AND `supplier_ok_at` IS NOT NULL AND `worker_ok_at` IS NOT NULL)),
  CONSTRAINT `chk_uc_reason` CHECK (CHAR_LENGTH(`reason`) >= 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عقدة 13 — لا تصحيح إلا بمرور السلسلة الثلاثية كاملةً'";

/* ── العقدة ١٦ — استحقاقاتُ عقدِ العميل ─────────────────────────────────── */
$DDL['ar_accruals'] = "CREATE TABLE IF NOT EXISTS `ar_accruals` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `accrual_no`     VARCHAR(30) NOT NULL,
  `period`         CHAR(7) NOT NULL,
  `contract_id`    INT UNSIGNED NOT NULL,
  `client_id`      INT UNSIGNED NULL,
  `claim_id`       INT UNSIGNED NULL COMMENT 'المطالبةُ التجاريةُ مصدرُ الاستحقاق',
  `qty`            DECIMAL(16,4) NOT NULL DEFAULT 0,
  `unit_type`      VARCHAR(16) NOT NULL DEFAULT 'hour',
  `amount`         DECIMAL(18,2) NOT NULL,
  `currency`       VARCHAR(8) NOT NULL,
  `fx_rate`        DECIMAL(20,8) NOT NULL DEFAULT 1,
  `base_amount`    DECIMAL(18,2) NOT NULL,
  `policy_key`     VARCHAR(48) NOT NULL DEFAULT 'ar_accrual',
  `prepared_by`    INT UNSIGNED NOT NULL,
  `control_by`     INT UNSIGNED NULL, `control_at` DATETIME NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `state`          ENUM('prepared','controlled','posted','reversed','rejected') NOT NULL DEFAULT 'prepared',
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ara_idem` (`idem_key`),
  UNIQUE KEY `uq_ara_no` (`company_id`,`accrual_no`),
  KEY `ix_ara_state` (`company_id`,`state`),
  CONSTRAINT `chk_ara_sod` CHECK (`control_by` IS NULL OR `control_by` <> `prepared_by`),
  CONSTRAINT `chk_ara_post_after_control` CHECK (`journal_entry_id` IS NULL OR `control_at` IS NOT NULL),
  CONSTRAINT `chk_ara_currency` CHECK (CHAR_LENGTH(`currency`) >= 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عقدة 16 — استحقاق عقد العميل · إجازة رئيس الحسابات قبل الترحيل'";

/* ── العقدة ١٧ — شهادةُ الإنجازِ الشهرية · LD-06 ──────────────────────────── */
$DDL['ar_completion_certs'] = "CREATE TABLE IF NOT EXISTS `ar_completion_certs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `cert_no`        VARCHAR(30) NOT NULL,
  `period`         CHAR(7) NOT NULL,
  `contract_id`    INT UNSIGNED NOT NULL,
  `claim_id`       INT UNSIGNED NULL,
  `approved_qty`   DECIMAL(16,4) NOT NULL DEFAULT 0,
  `unit_type`      VARCHAR(16) NOT NULL DEFAULT 'hour',
  `measure_ref`    VARCHAR(120) NULL COMMENT 'مرجعُ القياسِ المعتمد',
  `ladder_id`      VARCHAR(12) NOT NULL DEFAULT 'LD-06',
  `instance_scope` VARCHAR(24) NOT NULL DEFAULT 'LD-06-INST'
      COMMENT 'العقدتان 17 و18 مرحلتان في نسخةِ سلّمٍ واحدة — لا طلبَ اعتمادٍ ثانٍ',
  `prepared_by`    INT UNSIGNED NOT NULL,
  `approved_by`    INT UNSIGNED NULL, `approved_at` DATETIME NULL,
  `state`          ENUM('prepared','approved','issued','rejected') NOT NULL DEFAULT 'prepared',
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_acc_idem` (`idem_key`),
  UNIQUE KEY `uq_acc_no` (`company_id`,`cert_no`),
  KEY `ix_acc_state` (`company_id`,`state`),
  CONSTRAINT `chk_acc_sod` CHECK (`approved_by` IS NULL OR `approved_by` <> `prepared_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عقدة 17 — شهادة الإنجاز الشهرية · LD-06'";

/* ── العقدة ١٨ — فاتورةُ المطالبةِ وإحالتُها · LD-06 ─────────────────────── */
$DDL['ar_claim_invoices'] = "CREATE TABLE IF NOT EXISTS `ar_claim_invoices` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `invoice_no`     VARCHAR(30) NOT NULL,
  `period`         CHAR(7) NOT NULL,
  `claim_id`       INT UNSIGNED NOT NULL,
  `cert_id`        BIGINT UNSIGNED NULL COMMENT 'شهادةُ الإنجازِ التي تُبنى عليها',
  `accrual_id`     BIGINT UNSIGNED NULL,
  `tax_invoice_id` INT UNSIGNED NULL COMMENT 'الفاتورةُ الرسميةُ عند مالكِها — لا نسخةٌ ثانية',
  `amount`         DECIMAL(18,2) NOT NULL,
  `currency`       VARCHAR(8) NOT NULL,
  `ladder_id`      VARCHAR(12) NOT NULL DEFAULT 'LD-06',
  `instance_scope` VARCHAR(24) NOT NULL DEFAULT 'LD-06-INST',
  `prepared_by`    INT UNSIGNED NOT NULL COMMENT 'محاسبُ المبيعاتِ يهيّئ ولا يعتمد',
  `approved_by`    INT UNSIGNED NULL, `approved_at` DATETIME NULL,
  `control_by`     INT UNSIGNED NULL, `control_at`  DATETIME NULL,
  `journal_entry_id` BIGINT UNSIGNED NULL,
  `referred_to`    ENUM('collections','on_hold','cancelled') NULL COMMENT 'الإحالةُ لقسمِ التحصيل',
  `referred_at`    DATETIME NULL,
  `state`          ENUM('prepared','approved','controlled','issued','referred','rejected') NOT NULL DEFAULT 'prepared',
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_aci_idem` (`idem_key`),
  UNIQUE KEY `uq_aci_no` (`company_id`,`invoice_no`),
  KEY `ix_aci_state` (`company_id`,`state`),
  CONSTRAINT `chk_aci_sod_prep` CHECK (`approved_by` IS NULL OR `approved_by` <> `prepared_by`),
  CONSTRAINT `chk_aci_sod_ctrl` CHECK (`control_by`  IS NULL OR `control_by`  <> `approved_by`),
  CONSTRAINT `chk_aci_post_after_control` CHECK (`journal_entry_id` IS NULL OR `control_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عقدة 18 — فاتورة المطالبة وإحالتها · LD-06'";

/* ── الشرطُ السابق — سجلُّ المستفيدين والحساباتِ البنكية ──────────────────── */
$DDL['tre_beneficiaries'] = "CREATE TABLE IF NOT EXISTS `tre_beneficiaries` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `party_type`     ENUM('supplier','employee','client','other') NOT NULL,
  `party_ref`      INT UNSIGNED NOT NULL,
  `beneficiary_ar` VARCHAR(160) NOT NULL,
  `bank_name`      VARCHAR(120) NULL,
  `iban`           VARCHAR(64)  NULL,
  `account_no`     VARCHAR(64)  NULL,
  `currency`       VARCHAR(8)   NOT NULL,
  `verified_by`    INT UNSIGNED NULL COMMENT 'التحقُّقُ من الحسابِ شرطٌ لطلبِ الدفع',
  `verified_at`    DATETIME NULL,
  `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_ben_party_acc` (`company_id`,`party_type`,`party_ref`,`account_no`),
  KEY `ix_ben_active` (`company_id`,`is_active`),
  /* ◆ **مَن يُنشئ لا يتحقق** — والحسابُ غيرُ المتحقَّقِ لا يُصرَف إليه */
  CONSTRAINT `chk_ben_sod` CHECK (`verified_by` IS NULL OR `verified_by` <> `created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='شرط سابق للموجة 7 — سجل المستفيدين والحسابات البنكية'";

/* ── العقدة ٢٥ — دفعاتُ الدفعِ والتنفيذ ─────────────────────────────────── */
$DDL['tre_pay_batches'] = "CREATE TABLE IF NOT EXISTS `tre_pay_batches` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `batch_no`       VARCHAR(30) NOT NULL,
  `value_date`     DATE NOT NULL,
  `bank_account`   VARCHAR(64) NULL,
  `currency`       VARCHAR(8) NOT NULL,
  `total_amount`   DECIMAL(18,2) NOT NULL DEFAULT 0,
  `policy_key`     VARCHAR(48) NOT NULL DEFAULT 'treasury_disbursement',
  `prepared_by`    INT UNSIGNED NOT NULL COMMENT 'الخزينةُ تُعِدُّ الدفعة',
  `executed_by`    INT UNSIGNED NULL COMMENT 'التنفيذُ النقديُّ — ولا يملك قيدًا',
  `executed_at`    DATETIME NULL,
  `bank_ref`       VARCHAR(120) NULL COMMENT 'مرجعُ الحركةِ الذي ينتجه التنفيذ',
  `state`          ENUM('draft','ready','executed','partially_executed','cancelled') NOT NULL DEFAULT 'draft',
  `idem_key`       VARCHAR(96) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tpb_idem` (`idem_key`),
  UNIQUE KEY `uq_tpb_no` (`company_id`,`batch_no`),
  KEY `ix_tpb_state` (`company_id`,`state`),
  CONSTRAINT `chk_tpb_sod` CHECK (`executed_by` IS NULL OR `executed_by` <> `prepared_by`),
  /* ◆ لا تنفيذَ بلا مرجعِ حركة — «ينتج مرجعَ الحركة» شرطٌ لا وصف */
  CONSTRAINT `chk_tpb_ref` CHECK (`executed_at` IS NULL OR (`bank_ref` IS NOT NULL AND `bank_ref` <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='عقدة 25 — دفعات الدفع والتنفيذ · تنفيذ نقدي ولا قيد'";

$DDL['tre_pay_batch_lines'] = "CREATE TABLE IF NOT EXISTS `tre_pay_batch_lines` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`     INT UNSIGNED NOT NULL,
  `batch_id`       BIGINT UNSIGNED NOT NULL,
  `payment_id`     INT UNSIGNED NOT NULL COMMENT 'طلبُ الدفعِ المعتمد — العقدة 24',
  `beneficiary_id` INT UNSIGNED NOT NULL,
  `amount`         DECIMAL(18,2) NOT NULL,
  `currency`       VARCHAR(8) NOT NULL,
  `line_state`     ENUM('pending','executed','failed','returned') NOT NULL DEFAULT 'pending',
  `bank_ref`       VARCHAR(120) NULL,
  `failed_reason`  VARCHAR(160) NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tpbl` (`batch_id`,`payment_id`),
  KEY `ix_tpbl_co` (`company_id`,`line_state`),
  CONSTRAINT `fk_tpbl_batch` FOREIGN KEY (`batch_id`) REFERENCES `tre_pay_batches`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tpbl_ben`   FOREIGN KEY (`beneficiary_id`) REFERENCES `tre_beneficiaries`(`id`),
  CONSTRAINT `chk_tpbl_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='سطور دفعة الدفع — لا سطر بلا مستفيد متحقَّق'";

$made = 0; $fail = array();
foreach (array('unit_final_approvals', 'unit_corrections', 'ar_accruals', 'ar_completion_certs',
               'ar_claim_invoices', 'tre_beneficiaries', 'tre_pay_batches', 'tre_pay_batch_lines') as $t) {
    if ($conn->query($DDL[$t])) { $made++; echo "  ✔ {$t}\n"; }
    else { $fail[] = "{$t}: {$conn->error}"; echo "  ✘ {$t}: {$conn->error}\n"; }
}
printf("① أُنشئ %d جدولًا من 8\n", $made);
if ($fail) { exit("✘ توقّفت — لا تُقيَّد هجرةٌ نصفُها\n"); }

/* ── التسجيلُ في سجلِّ المستأجِرِ الحاكم — وإلا حجبها الحارس ─────────────── */
echo "② كلُّ جدولٍ يحمل `company_id` — فحارسُ الهجراتِ لا يُسقطه\n";

ems_migration_recorded(__FILE__, $conn, 0);
