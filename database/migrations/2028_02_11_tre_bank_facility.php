<?php
/**
 * 2028_02_11_tre_bank_facility.php — التسهيلات البنكية (DEP-06 · الشاشة 14 · GOV_EXEC §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:tre_bank_facility
 * «سجلُّ التسهيلات: الحدُّ والمستخدمُ والمتاحُ والضماناتُ والاستحقاقات — عينُ
 * الخزينةِ على الالتزاماتِ البنكيّة». الحبّة: تسهيلٌ بنكيٌّ × بنك — سطرُ
 * تسهيل. القوائمُ المحكومةُ حرفًا من سطرِ الورقة. والانطباقُ قائمٌ بقرارِ
 * DEC-OPEN-03 (منشأةٌ متعدّدةُ الكيانات). اعتمادُ الاقتراضِ AAM-012 يُقيَّد
 * مرجعًا عند نفاذِ قيمِ السلّم (OA-06) — والبناءُ لا يتوقّف عليه بنصِّ §16.
 * التشغيل: php database/migrations/2028_02_11_tre_bank_facility.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `tre_bank_facility` (
    `id` INT NOT NULL AUTO_INCREMENT COMMENT 'معرف التسهيل — يولده النظام',
    `company_id` INT NOT NULL,
    `bank_account_id` INT NOT NULL COMMENT 'البنك — من سجل الحسابات البنكية والعملة تشتق منه',
    `facility_type` ENUM('جاري مدين','تمويل مرابحة','خطابات ضمان','اعتمادات مستندية','تمويل مشروع') NOT NULL,
    `limit_amount` DECIMAL(18,2) NOT NULL,
    `aam_ref` VARCHAR(60) NULL DEFAULT NULL COMMENT 'مرجع اعتماد AAM-012 — يقيد عند نفاذ قيم السلم (OA-06)',
    `used_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'المستخدم — يحدث من حركات الاستخدام والسداد بمرجعها لا يدويا',
    `used_src_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجع اخر حركة حدثت المستخدم',
    `collateral_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الضمانات المقدمة — مرجع سجل الضمانات',
    `expiry_date` DATE NOT NULL,
    `schedule_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'جدول السداد — مرجع جدولته عند المالية',
    `facility_state` ENUM('ساري','مستنفَد','قيد التجديد','منتهٍ','مجمَّد') NOT NULL DEFAULT 'ساري',
    `state_note` VARCHAR(300) NULL DEFAULT NULL,
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `reviewed_by` INT NULL DEFAULT NULL,
    `approved_by` INT NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `data_state` ENUM('حي','ملغي بمرجع') NOT NULL DEFAULT 'حي',
    `src_ref` VARCHAR(120) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `ix_tbf_bank` (`bank_account_id`, `facility_state`),
    KEY `ix_tbf_co` (`company_id`, `expiry_date`),
    CONSTRAINT `fk_tbf_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `fin_bank_accounts` (`id`),
    CONSTRAINT `chk_tbf_limit` CHECK (`limit_amount` > 0),
    CONSTRAINT `chk_tbf_used` CHECK (`used_amount` >= 0 AND `used_amount` <= `limit_amount`),
    CONSTRAINT `chk_tbf_state_note` CHECK (`facility_state` IN ('ساري','مستنفَد') OR `state_note` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DEP-06 شاشة 14: التسهيلات البنكية — تسهيل × بنك والمتاح يشتق (الحد - المستخدم)'");
if (!$ok) { exit("⛔ فشل الجدول: {$conn->error}\n"); }
echo "✔ tre_bank_facility قائم\n";

$ACTIONS = array(
    array('tre.facility.register', 'تسجيلُ تسهيلٍ بنكيّ', 'التسهيلات البنكية', 'tre_facilities.php',
        'tre_bank_facility', 'BankFacilityRegistered',
        'سطرُ تسهيلٍ بحدِّه وبنكِه ونوعِه وانتهائِه — والمتاحُ يُشتقُّ ولا يُكتب',
        'تغييرُ حالةٍ بسببِه لا حذف'),
    array('tre.facility.state_change', 'تغييرُ حالةِ تسهيل', 'التسهيلات البنكية', 'tre_facilities.php',
        'tre_bank_facility', 'BankFacilityStateChanged',
        'الحالةُ من قائمتِها المحكومةِ وغيرُ السارية بسببٍ مكتوب',
        'حالةٌ جديدةٌ بسببِها — والتاريخُ باقٍ'),
);
$st = $conn->prepare("INSERT INTO nav09_action_map
    (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
     effect_text, reverse_text, live_code, state, write_class)
    VALUES (?,?,?,?, 'الخزينة', ?, ?, ?, ?, ?, 'bound_page', 'domain_write')
    ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar)");
foreach ($ACTIONS as $a) {
    $live = 'page:Finance/' . $a[3];
    $st->bind_param('sssssssss', $a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7], $live);
    if (!$st->execute()) { exit("⛔ فشل تسجيل {$a[0]}: {$conn->error}\n"); }
}
echo "✔ فعلا الشاشةِ مسجَّلان\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
