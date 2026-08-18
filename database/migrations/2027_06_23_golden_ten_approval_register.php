<?php
/**
 * 2027_06_23_golden_ten_approval_register.php
 * ═══════════════════════════════════════════════════════════════════════════
 * سجلُّ اعتمادِ العشرِ الذهبيةِ — بقرارِ المالكِ 2026-08-18:
 * «لا ننتظر اعتمادَ العشرِ مجتمعة: كلُّ شاشةٍ أعتمدها تمضي في التعميمِ مباشرةً،
 *  وما أُبديَ عليه ملاحظةٌ يُصلَّح ثم يُعاد لي».
 *
 * فالاعتمادُ صار **إفراديًّا** لا دفعةً واحدة — وهذا الجدولُ بيتُه: صفٌّ لكلِّ
 * شاشةٍ بحالتِه وتاريخِه وملاحظةِ المالكِ إن وُجدت. ولا يُعمَّم نمطُ شاشةٍ
 * على غيرِها قبلَ أن يصير صفُّها `approved`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

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
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

if ($conn->query(
    "CREATE TABLE IF NOT EXISTS `gov_golden_approvals` (
        `id` TINYINT UNSIGNED NOT NULL,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عمودُ العزل — 0: قرارٌ معياريٌّ لكلِّ الكيانات',
        `screen_file` VARCHAR(160) NOT NULL,
        `title_ar` VARCHAR(160) NOT NULL,
        `test_account` VARCHAR(60) NOT NULL COMMENT 'حسابُ الدورِ المخوَّلِ لفتحِها — مقيسٌ من صلاحيتِها الحية',
        `role_id` SMALLINT UNSIGNED NOT NULL,
        `url_params` VARCHAR(60) NOT NULL DEFAULT '' COMMENT 'معاملُ مسارٍ تشترطه الشاشةُ للدخول',
        `state` ENUM('pending','approved','noted') NOT NULL DEFAULT 'pending'
            COMMENT 'pending بانتظارِ المالك · approved تمضي في التعميم · noted ملاحظةٌ تُصلَّح ثم تُعاد',
        `owner_note` VARCHAR(500) NOT NULL DEFAULT '',
        `decided_at` DATETIME NULL,
        `fixed_at` DATETIME NULL COMMENT 'لحظةُ إصلاحِ الملاحظةِ قبلَ إعادتِها للمالك',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_screen` (`company_id`,`screen_file`),
        KEY `ix_state` (`state`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='UXW-01 §8-1 — اعتمادُ العشرِ الذهبيةِ إفراديًّا بقرارِ المالك 2026-08-18'") === false) {
    exit("✗ الجدول: {$conn->error}\n");
}
echo "✔ gov_golden_approvals\n";

$TEN = array(
    array(1,'Portal/ceo_board.php',          'المركزُ التنفيذيُّ للرئيسِ والنواب','تنفيذ',        9,  ''),
    array(2,'Portal/my_tasks.php',           'مركزُ العمل',                      'محمد',          1,  ''),
    array(3,'Operations/sites_board.php',    'لوحةُ إدارةِ التشغيل',              'محمد',          1,  ''),
    array(4,'Contracts/contracts.php',       'العقود',                           'مبيعات',        12, ''),
    array(5,'Timesheet/timesheet.php',       'التايم شيتُ اليوميّ',               'محمد',          1,  'type=1'),
    array(6,'Maintenance/orders.php',        'أمرُ الصيانة',                     'صيانة',         13, ''),
    array(7,'Suppliers/supplier_profile.php','ملفُّ المورد',                      'مصعب',          2,  'id=1'),
    array(8,'Risk/risk_register.php',        'المخاطر',                          'مخاطر',         28, ''),
    array(9,'FinRequests/request_form.php',  'طلبُ الدفعِ الماليّ',               'محمد',          1,  ''),
    array(10,'Finance/approvals_inbox.php',  'صندوقُ الاعتمادات',                'مشرف المالية',  17, ''),
);
$st = $conn->prepare(
    "INSERT INTO `gov_golden_approvals`
        (`id`,`company_id`,`screen_file`,`title_ar`,`test_account`,`role_id`,`url_params`,`state`)
     VALUES (?,0,?,?,?,?,?,'pending')
     ON DUPLICATE KEY UPDATE `title_ar`=VALUES(`title_ar`), `test_account`=VALUES(`test_account`),
        `role_id`=VALUES(`role_id`), `url_params`=VALUES(`url_params`)");
$n = 0;
foreach ($TEN as $t) {
    list($id, $file, $title, $acct, $role, $params) = $t;
    $st->bind_param('isssis', $id, $file, $title, $acct, $role, $params);
    if ($st->execute()) { $n++; } else { echo "   ✗ {$file}: {$st->error}\n"; }
}
$st->close();
printf("✔ العشرُ مسجَّلةٌ بحالةِ الانتظار: %d · بانتظارِ المالك: %s\n", $n,
    $one("SELECT COUNT(*) FROM gov_golden_approvals WHERE state='pending'"));
echo "◆ ولا يُعمَّم نمطُ شاشةٍ قبلَ أن يصير صفُّها approved — والملاحظةُ تُصلَّح ثم تُعاد\n";
