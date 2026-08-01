<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Migration N-21: سرية الملكية — المجال المقيَّد (PLAN-04 §1.3 · FIN-01 §1.1)
 * ───────────────────────────────────────────────────────────────────────────
 * ① equipment_ownership_registry: بيت بيانات ملكية المعدات الوحيد — تُرحَّل إليه
 *    قيم equipments ثم تُصفَّر النسخ التشغيلية وتُهجَر الأعمدة بمنع كتابة (Triggers)
 *    **ولا تُسقط** (لا هدم). البيانات محفوظة في السجل + لقطة ما قبل الهجرة.
 * ② sensitive_read_log (بيته LEG-01 §9 — يُنشأ هنا لأن N-21 تسبقه بالترتيب).
 * ③ ownership_access_grants: الرؤية بأكواد فردية — وأشدها بمدة وسبب (CHECK).
 * صيغة .php لأن أجسام الـTriggers لا تمر عبر multi_query.
 * idempotent — آمن لإعادة التشغيل.
 * ═══════════════════════════════════════════════════════════════════════════
 */
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';
$mu = ems_env('DB_MIGRATOR_USER'); $mp = ems_env('DB_MIGRATOR_PASS');
if ($mu === null || $mu === '') { $mu = ems_env('DB_USER'); $mp = ems_env('DB_PASS'); }
$conn = new mysqli(ems_env('DB_HOST'), $mu, $mp, ems_env('DB_NAME'));
if ($conn->connect_error) { die("CONN FAIL: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$conn->query("SET collation_connection='utf8mb4_unicode_ci'");

function q($c, $sql, $label) {
    if (!$c->query($sql)) { die("  ✘ {$label}: " . $c->error . "\n"); }
    echo "  ✔ {$label}\n";
}

q($conn, "CREATE TABLE IF NOT EXISTS `equipment_ownership_registry` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `equipment_id` INT NOT NULL,
  `actual_owner_name` VARCHAR(255) NULL DEFAULT NULL,
  `owner_type` VARCHAR(60) NULL DEFAULT NULL,
  `owner_phone` VARCHAR(60) NULL DEFAULT NULL,
  `owner_supplier_relation` VARCHAR(120) NULL DEFAULT NULL,
  `purchase_value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'قيمة الشراء — أشد الحقول سرية',
  `purchase_currency` VARCHAR(8) NULL DEFAULT NULL,
  `migrated_from` VARCHAR(40) NOT NULL DEFAULT 'equipments',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eor_equipment` (`company_id`, `equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-21: المجال المقيَّد لملكية المعدات — لا يُستعلم منه إلا عبر OwnershipDomainGuard'",
  'equipment_ownership_registry');

q($conn, "CREATE TABLE IF NOT EXISTS `sensitive_read_log` (
  `read_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `person_id` INT NOT NULL,
  `element_code` VARCHAR(80) NOT NULL,
  `subject_type` VARCHAR(60) NOT NULL,
  `subject_id` BIGINT UNSIGNED NOT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) NULL DEFAULT NULL,
  `result` ENUM('allowed','denied') NOT NULL,
  PRIMARY KEY (`read_id`),
  KEY `ix_srl_person` (`person_id`, `at`),
  KEY `ix_srl_subject` (`subject_type`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='LEG-01 §9: سجل اطلاع على الحقول الحساسة — Insert-only'",
  'sensitive_read_log');

q($conn, "CREATE TABLE IF NOT EXISTS `ownership_access_grants` (
  `grant_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `person_id` INT NOT NULL,
  `permission_code` ENUM('ownership.owner_view','ownership.finance_terms','ownership.purchase_value') NOT NULL,
  `reason` VARCHAR(255) NULL DEFAULT NULL,
  `valid_from` DATE NULL DEFAULT NULL,
  `valid_to` DATE NULL DEFAULT NULL,
  `granted_by` INT NOT NULL,
  `state` ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `revoked_by` INT NULL DEFAULT NULL,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`grant_id`),
  KEY `ix_oag_person` (`company_id`, `person_id`, `permission_code`, `state`),
  CONSTRAINT `ck_oag_value_strict` CHECK (
    `permission_code` <> 'ownership.purchase_value'
    OR (`reason` IS NOT NULL AND `valid_from` IS NOT NULL AND `valid_to` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='N-21: الرؤية بأكواد فردية لا بالعضوية — وأشدها بمدة وسبب'",
  'ownership_access_grants');

echo "[ترحيل بيانات الملكية]\n";
q($conn, "INSERT INTO `equipment_ownership_registry`
  (`company_id`, `equipment_id`, `actual_owner_name`, `owner_type`, `owner_phone`,
   `owner_supplier_relation`, `purchase_value`, `purchase_currency`, `note`)
SELECT e.`company_id`, e.`id`, e.`actual_owner_name`, e.`owner_type`, e.`owner_phone`,
       e.`owner_supplier_relation`, e.`acquisition_cost`, e.`acquisition_currency`,
       'مرحَّل من equipments — N-21'
  FROM `equipments` e
 WHERE (e.`actual_owner_name` IS NOT NULL AND e.`actual_owner_name` <> '')
    OR (e.`owner_phone` IS NOT NULL AND e.`owner_phone` <> '')
    OR (e.`owner_type` IS NOT NULL AND e.`owner_type` <> '')
    OR (e.`owner_supplier_relation` IS NOT NULL AND e.`owner_supplier_relation` <> '')
ON DUPLICATE KEY UPDATE `equipment_ownership_registry`.`updated_at` = `equipment_ownership_registry`.`updated_at`",
  'backfill من equipments');
$migrated = $conn->query("SELECT COUNT(*) n FROM equipment_ownership_registry")->fetch_assoc();
echo "  → صفوف السجل المقيَّد: {$migrated['n']}\n";

q($conn, "UPDATE `equipments`
   SET `actual_owner_name` = NULL, `owner_type` = NULL, `owner_phone` = NULL,
       `owner_supplier_relation` = NULL
 WHERE `actual_owner_name` IS NOT NULL OR `owner_type` IS NOT NULL
    OR `owner_phone` IS NOT NULL OR `owner_supplier_relation` IS NOT NULL",
  'تصفير النسخ التشغيلية (محفوظة في السجل + اللقطة)');

echo "[هجر الأعمدة بمنع الكتابة]\n";
$conn->query("DROP TRIGGER IF EXISTS `trg_n21_equipments_owner_ins`");
$conn->query("DROP TRIGGER IF EXISTS `trg_n21_equipments_owner_upd`");

q($conn, "CREATE TRIGGER `trg_n21_equipments_owner_ins` BEFORE INSERT ON `equipments`
FOR EACH ROW
BEGIN
  IF NEW.`actual_owner_name` IS NOT NULL OR NEW.`owner_type` IS NOT NULL
     OR NEW.`owner_phone` IS NOT NULL OR NEW.`owner_supplier_relation` IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
      'N-21: أعمدة المالك في equipments مهجورة — بيانات الملكية في المجال المقيَّد حصرًا';
  END IF;
END", 'trigger منع الكتابة (INSERT)');

q($conn, "CREATE TRIGGER `trg_n21_equipments_owner_upd` BEFORE UPDATE ON `equipments`
FOR EACH ROW
BEGIN
  IF (NEW.`actual_owner_name` IS NOT NULL AND (OLD.`actual_owner_name` IS NULL OR NEW.`actual_owner_name` <> OLD.`actual_owner_name`))
     OR (NEW.`owner_type` IS NOT NULL AND (OLD.`owner_type` IS NULL OR NEW.`owner_type` <> OLD.`owner_type`))
     OR (NEW.`owner_phone` IS NOT NULL AND (OLD.`owner_phone` IS NULL OR NEW.`owner_phone` <> OLD.`owner_phone`))
     OR (NEW.`owner_supplier_relation` IS NOT NULL AND (OLD.`owner_supplier_relation` IS NULL OR NEW.`owner_supplier_relation` <> OLD.`owner_supplier_relation`)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
      'N-21: أعمدة المالك في equipments مهجورة — الكتابة في المجال المقيَّد حصرًا';
  END IF;
END", 'trigger منع الكتابة (UPDATE)');

echo "اكتمل N-21 (المخطط والترحيل والهجر) بنجاح.\n";
