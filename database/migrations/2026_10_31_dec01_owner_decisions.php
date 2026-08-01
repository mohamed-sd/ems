<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * Migration DEC-01: قرارات المالك — الدفعة الأولى (docs/files/DEC-01)
 * ───────────────────────────────────────────────────────────────────────────
 * القرارات التسعة الموقَّعة تُطبَّق على مواضع DEC-PENDING:
 *  ② بابا «الحوكمة» و«التمويل» — الأبواب ثمانية (CHECK الأبواب يُعاد ببابين،
 *    الشاشات الست 206–211 تسكنهما لا الإعدادات، والتمويل خلف المجال المقيَّد).
 *  ① النيابة عن مدير الحركة: delegated_from_auth_id في signing_authorities —
 *    نائب بمدة مكتوبة (تحرسه الخدمة: لا نيابة مفتوحة المدة).
 *  ④ إشعار التصنيف الآلي: attendance_sweep_notices — 48 ساعة إشعار + 24 مهلة.
 *  ⑥ اعتراضات السلسلة تُرصد (chain_objections) — اعتراضان في شهر → يومية آليًّا.
 *  ⑨ synced_late على فترات الورديات — مزامنة تجاوزت يومًا تُعلَّم لا تُرفض.
 * صيغة .php لأن إسقاط CHECK يختلف بين MySQL/MariaDB. idempotent.
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
function hasCol($c, $table, $col) {
    $r = $c->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$col}'");
    return $r && $r->num_rows > 0;
}

// ═══ DEC-② · الأبواب الثمانية — CHECK الأبواب يُعاد بـGOV وFIN ═══
$dropped = $conn->query('ALTER TABLE `nav_items` DROP CHECK `chk_nav_door`');
if (!$dropped) { $dropped = $conn->query('ALTER TABLE `nav_items` DROP CONSTRAINT `chk_nav_door`'); }
echo $dropped ? "  ✔ chk_nav_door أُسقط\n" : "  · chk_nav_door غير قائم (عاطل): " . $conn->error . "\n";
q($conn, "ALTER TABLE `nav_items` ADD CONSTRAINT `chk_nav_door` CHECK (`door` IN ('HOME','DAILY','APPR','REC','REP','SET','GOV','FIN'))",
  'chk_nav_door — ثمانية أبواب (DEC-01 ②: قرار صريح لا تنفيذ صامت)');

// ═══ DEC-① · النيابة عن مدير الحركة — بمدة وسقف مكتوبين ═══
if (!hasCol($conn, 'signing_authorities', 'delegated_from_auth_id')) {
    q($conn, "ALTER TABLE `signing_authorities`
      ADD COLUMN `delegated_from_auth_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'DEC-01 ①: نيابة — مرجع تفويض الأصيل؛ النائب بمدة مكتوبة إلزامًا (تحرسه الخدمة)' AFTER `joint_required`,
      ADD KEY `ix_sa_delegated` (`delegated_from_auth_id`)",
      'signing_authorities.delegated_from_auth_id — النائب المفوَّض');
} else { echo "  · delegated_from_auth_id قائم\n"; }

// ═══ DEC-⑨ · مزامَن متأخر — يُعلَّم لا يُرفض ═══
if (!hasCol($conn, 'shift_period_logs', 'synced_late')) {
    q($conn, "ALTER TABLE `shift_period_logs`
      ADD COLUMN `synced_late` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'DEC-01 ⑨: مزامنة بعد أكثر من يوم من تاريخ العمل — يدخل السلسلة كأي صف ولا يُعتمد آليًّا'",
      'shift_period_logs.synced_late');
} else { echo "  · synced_late قائم\n"; }

// ═══ DEC-④ · إشعار التصنيف الآلي: 48 ساعة إشعار + 24 ساعة مهلة ثم A2 ═══
q($conn, "CREATE TABLE IF NOT EXISTS `attendance_sweep_notices` (
  `notice_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `person_id` INT NOT NULL,
  `att_date` DATE NOT NULL,
  `notified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notice_id`),
  UNIQUE KEY `uq_asn_person_date` (`company_id`, `person_id`, `att_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-01 ④: إشعار ما قبل A2 — لا يصير A2 بصمت ولا بلا مهلة إضافية (48+24)'",
  'attendance_sweep_notices');

// ═══ DEC-⑥ · اعتراضات السلسلة تُرصد — اعتراضان في شهر → العودة لليومية ═══
q($conn, "CREATE TABLE IF NOT EXISTS `chain_objections` (
  `obj_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `line_ref` VARCHAR(120) NOT NULL,
  `domain` VARCHAR(20) NOT NULL,
  `reason_code` VARCHAR(60) NOT NULL COMMENT 'من decision_reasons حصرًا',
  `policy_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'سياسة السلسلة المعنية — مرجع الرجوع الآلي',
  `site_id` INT NULL DEFAULT NULL,
  `person_id` INT NOT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`obj_id`),
  KEY `ix_co_policy` (`policy_id`, `at`),
  KEY `ix_co_company` (`company_id`, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEC-01 ⑥: رصد الاعتراضات — اعتراضان في شهر أو نزاع → دورية يومية آليًّا (Insert-only)'",
  'chain_objections');

// ═══ DEC-② · الشاشات الست في بابيهما + مؤشر DEC-⑦/⑧ في التقارير ═══
q($conn, "INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `is_quick`, `icon`, `display_order`)
SELECT * FROM (
    SELECT 206 i, 'سجل الكيانات'        n, 'Governance/entities_registry.php'      c, 1 r, 0 l, 1 q, 'fa fa-building-columns' ic, 0 d UNION ALL
    SELECT 207,   'التفويض بالتوقيع',      'Governance/signing_authority.php',        1,   0,   1,   'fa fa-file-signature',      0   UNION ALL
    SELECT 208,   'التراخيص والكفالات',    'Governance/licenses_guarantees.php',      1,   0,   1,   'fa fa-certificate',         0   UNION ALL
    SELECT 209,   'أنماط التفعيل',         'Governance/activation_patterns.php',      1,   0,   1,   'fa fa-toggle-on',           0   UNION ALL
    SELECT 210,   'سجل الممولين',          'Financing/financiers_registry.php',       1,   0,   1,   'fa fa-hand-holding-dollar', 0   UNION ALL
    SELECT 211,   'إنشاء عملية تمويل',     'Financing/financing_operation_new.php',   1,   0,   1,   'fa fa-money-check-dollar',  0   UNION ALL
    SELECT 212,   'الاعتمادات المتأخرة والوثائق', 'Reports/approval_lag_report.php', 1,   0,   1,   'fa fa-hourglass-half',      0
) m
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `modules`) x WHERE x.`code` = m.c)",
  'modules 206–212 — شاشات بابي الحوكمة والتمويل ومؤشر المتابعة');

q($conn, "INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT p.rid, p.mid, 1, p.ad, p.ed, 0
  FROM (
    SELECT 1 rid, 206 mid, 1 ad, 1 ed UNION ALL SELECT 19, 206, 1, 1 UNION ALL
    SELECT 1, 207, 1, 1 UNION ALL SELECT 19, 207, 1, 1 UNION ALL
    SELECT 1, 208, 1, 1 UNION ALL SELECT 19, 208, 1, 1 UNION ALL
    SELECT 1, 209, 0, 1 UNION ALL SELECT 19, 209, 0, 1 UNION ALL
    SELECT 1, 210, 0, 0 UNION ALL SELECT 19, 210, 0, 0 UNION ALL
    SELECT 1, 211, 1, 0 UNION ALL SELECT 19, 211, 1, 0 UNION ALL
    SELECT 1, 212, 0, 0 UNION ALL SELECT 19, 212, 0, 0 UNION ALL SELECT 17, 212, 0, 0
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `role_permissions`) rp
                    WHERE rp.`role_id` = p.rid AND rp.`module_id` = p.mid)",
  'role_permissions للشاشات السبع (1 · 19 · +17 للمؤشر)');

q($conn, "INSERT INTO `nav_items` (`role_id`, `door`, `module_id`, `label_ar`, `route`, `icon`, `sort_order`, `permission_code`, `active`)
SELECT p.rid, p.door, p.mid, p.lbl, p.rt, p.ic, p.so, p.rt, 1
  FROM (
    SELECT 1 rid, 'GOV' door, 206 mid, 'سجل الكيانات' lbl,        'Governance/entities_registry.php' rt,    'fa fa-building-columns' ic, 1 so UNION ALL
    SELECT 1, 'GOV', 207, 'التفويض بالتوقيع',   'Governance/signing_authority.php',      'fa fa-file-signature',      2 UNION ALL
    SELECT 1, 'GOV', 208, 'التراخيص والكفالات', 'Governance/licenses_guarantees.php',    'fa fa-certificate',         3 UNION ALL
    SELECT 1, 'GOV', 209, 'أنماط التفعيل',      'Governance/activation_patterns.php',    'fa fa-toggle-on',           4 UNION ALL
    SELECT 1, 'FIN', 210, 'سجل الممولين',       'Financing/financiers_registry.php',     'fa fa-hand-holding-dollar', 1 UNION ALL
    SELECT 1, 'FIN', 211, 'إنشاء عملية تمويل',  'Financing/financing_operation_new.php', 'fa fa-money-check-dollar',  2 UNION ALL
    SELECT 1, 'REP', 212, 'الاعتمادات المتأخرة والوثائق', 'Reports/approval_lag_report.php', 'fa fa-hourglass-half', 90 UNION ALL
    SELECT 19, 'GOV', 206, 'سجل الكيانات',       'Governance/entities_registry.php',      'fa fa-building-columns',    1 UNION ALL
    SELECT 19, 'GOV', 207, 'التفويض بالتوقيع',   'Governance/signing_authority.php',      'fa fa-file-signature',      2 UNION ALL
    SELECT 19, 'GOV', 208, 'التراخيص والكفالات', 'Governance/licenses_guarantees.php',    'fa fa-certificate',         3 UNION ALL
    SELECT 19, 'GOV', 209, 'أنماط التفعيل',      'Governance/activation_patterns.php',    'fa fa-toggle-on',           4 UNION ALL
    SELECT 19, 'FIN', 210, 'سجل الممولين',       'Financing/financiers_registry.php',     'fa fa-hand-holding-dollar', 1 UNION ALL
    SELECT 19, 'FIN', 211, 'إنشاء عملية تمويل',  'Financing/financing_operation_new.php', 'fa fa-money-check-dollar',  2 UNION ALL
    SELECT 19, 'REP', 212, 'الاعتمادات المتأخرة والوثائق', 'Reports/approval_lag_report.php', 'fa fa-hourglass-half', 90
  ) p
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT * FROM `nav_items`) n
                    WHERE n.`role_id` = p.rid AND n.`route` = p.rt)",
  'nav_items — بابا GOV وFIN للدورين 1 و19');

// ═══ نقل شاشات الحوكمة الثلاث (203–205) تبقى حيث هي — تقارير وإعدادات ═══
// (DEC-01 ② حدد ساكني البابين حصرًا: 4 حوكمة + 2 تمويل — والتقارير في REP)

echo "\nDEC-01 migration اكتملت — الأبواب ثمانية والبنى جاهزة.\n";
