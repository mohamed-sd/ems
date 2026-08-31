<?php
/**
 * 2028_02_06_wh_custodian_assign.php — إسناد أمناء المخازن (WH-03 الجديد · الحزمة -3)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:proc_wh_custodian
 * «أمينُ المخزنِ يتغيّر ولا يُمحى: حقلٌ واحدٌ في سجلِّ المخزنِ يفقد التاريخَ
 * ولا يستوعب البديلَ ولا المناوبَ ولا المؤقّت — فالإسنادُ سجلٌّ تابعٌ بفترتِه،
 * والأمينُ النافذُ اليومَ يُشتقُّ منه ولا يُكتب يدويًّا» (الدليل -3 · ورقة 17 ·
 * الشاشة 3 · Child of خ02). القوائمُ المحكومةُ حرفًا من سطرِ «القوائم المحكومة».
 * ويُسجَّل فعلا الشاشةِ في قاموسِ الأفعال (ADR-06).
 * التشغيل: php database/migrations/2028_02_06_wh_custodian_assign.php
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

/* ── ① جدولُ الإسناد — الحبّة: مخزن × شخص × فترة إسناد (Child of proc_warehouse) ── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `proc_wh_custodian` (
    `id` INT NOT NULL AUTO_INCREMENT COMMENT 'معرف الاسناد — يولده النظام',
    `company_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL COMMENT 'كود المخزن — موروث من الاب ويقفل',
    `employee_id` INT NOT NULL COMMENT 'مرجع الشخص بالموارد — من سجل الموظفين',
    `assign_type` ENUM('أساسي','بديل','مؤقت بالإنابة','مناوب بوردية') NOT NULL,
    `shift_name` ENUM('لا تنطبق','صباحية','مسائية','ليلية') NOT NULL DEFAULT 'لا تنطبق',
    `date_from` DATE NOT NULL,
    `date_to` DATE NULL DEFAULT NULL,
    `perm_scope` ENUM('استلام وصرف وجرد وتحويل','استلام وصرف فقط','جرد فقط','قراءة') NOT NULL,
    `handover_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع محضر التسليم — يشتق عند الاقفال بتسليم',
    `assign_state` ENUM('نافذ','منتهٍ بتسليم','منتهٍ بلا تسليم') NOT NULL DEFAULT 'نافذ',
    `close_note` VARCHAR(300) NULL DEFAULT NULL,
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `data_state` ENUM('حي','ملغي بمرجع') NOT NULL DEFAULT 'حي',
    `src_ref` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'مرجع المصدر — من اي بوابة او استيراد جاء الصف',
    PRIMARY KEY (`id`),
    KEY `ix_pwc_wh` (`warehouse_id`, `assign_state`, `date_from`),
    KEY `ix_pwc_emp` (`employee_id`),
    KEY `ix_pwc_co` (`company_id`),
    CONSTRAINT `fk_pwc_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `proc_warehouse` (`id`),
    CONSTRAINT `fk_pwc_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
    CONSTRAINT `chk_pwc_period` CHECK (`date_to` IS NULL OR `date_to` >= `date_from`),
    CONSTRAINT `chk_pwc_closed_has_note` CHECK (`assign_state` = 'نافذ' OR `close_note` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='WH-03 (حزمة -3): اسناد امناء المخازن — مخزن × شخص × فترة، والنافذ اليوم يشتق ولا يكتب'");
if (!$ok) { exit("⛔ فشل الجدول: {$conn->error}\n"); }
echo "✔ proc_wh_custodian قائم\n";

/* ── ② تسجيلُ فعلَي الشاشةِ في قاموسِ الأفعال (ADR-06 — ولا معالجَ بفعلٍ غيرِ مسجَّل) ── */
$ACTIONS = array(
    array('proc.wh.custodian_assign', 'إسنادُ أمينِ مخزنٍ بفترتِه',
        'إسناد أمناء المخازن', 'wh_custodians.php',
        'proc_wh_custodian', 'WarehouseCustodianAssigned',
        'صفُّ إسنادٍ جديدٌ بفترتِه ونوعِه ونطاقِه — والأمينُ النافذُ يُشتقُّ من السجلِّ لا يُكتب في سجلِّ المخزن',
        'إقفالُ الإسنادِ بحالتِه لا حذفُه — والتاريخُ باقٍ'),
    array('proc.wh.custodian_close', 'إقفالُ إسنادِ أمينِ مخزن',
        'إسناد أمناء المخازن', 'wh_custodians.php',
        'proc_wh_custodian', 'WarehouseCustodianClosed',
        'الإسنادُ يُقفَل بتسليمٍ (بمرجعِ محضرِه) أو بلا تسليمٍ — والثانيةُ واقعةٌ تُرفَع حدثًا',
        'إسنادٌ جديدٌ يفتتح — ولا يُعاد فتحُ المُقفَل'),
);
$st = $conn->prepare("INSERT INTO nav09_action_map
    (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
     effect_text, reverse_text, live_code, state, write_class)
    VALUES (?,?,?,?, 'المخازن', ?, ?, ?, ?, ?, 'bound_page', 'domain_write')
    ON DUPLICATE KEY UPDATE label_ar = VALUES(label_ar)");
foreach ($ACTIONS as $a) {
    $live = 'page:Procurement/' . $a[3];
    $st->bind_param('sssssssss', $a[0], $a[1], $a[2], $a[3], $a[4], $a[5], $a[6], $a[7], $live);
    if (!$st->execute()) { exit("⛔ فشل تسجيل {$a[0]}: {$conn->error}\n"); }
}
echo "✔ فعلا الشاشةِ مسجَّلان في قاموسِ الأفعال\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
