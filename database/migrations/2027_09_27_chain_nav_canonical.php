<?php
/**
 * 2027_09_27_chain_nav_canonical.php
 *   تسجيلُ شاشاتِ السلسلةِ في السجلِّ الكنسيِّ للتنقّل — U1 و U9
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العنوانُ الفرعيُّ المُصيَّرُ مصدرُه `nav_canonical.group_name`** لا اسمُ
 *   `link_groups`. وقد جُرِّب الأولُ فلم يتغيّر شيء: أُنشئت خمسَ عشرةَ مجموعةً
 *   ونُقلت ثمانيةٌ وخمسون بندًا — **وبقي القسمُ اثنَي عشرَ عنصرًا**. لأن
 *   الشاشةَ بلا صفٍّ كنسيٍّ تُصيَّر بقسمٍ فارغٍ فتصعد إلى صدرِ المجموعةِ المكشوف.
 *   ⇒ **الوسمُ في الجدولِ الخطأ لا يُحرّك عنصرًا واحدًا — والدليلُ عدٌّ لا نية.**
 *
 * ◆ **والاسمُ واحدٌ في الموضعَين**: `group_name` هنا يطابق `canonical_group`
 *   في مصفوفةِ الواجهة — فلا اسمان لقسمٍ واحد.
 *
 * ◆ **ولا يُخترَع ترتيبٌ**: `sort_no` من المصفوفةِ نفسِها، و`matrix_row` يشير
 *   إلى صفِّها فيُقرأ الحكمُ من مصدرِه.
 *
 * التشغيل:  php database/migrations/2027_09_27_chain_nav_canonical.php
 * الرجوع :  php database/migrations/2027_09_27_chain_nav_canonical.php --revert
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

$GRP = 'سلسلة الأثر — الاستحقاق والفوترة والصرف';
$SRC = 'INJ-CHAIN-CLOSE-01';

/* route, ar, en, sort, owner_dept, output_doc */
$S = array(
 array('Finance/unit_fin_final.php',     'الاعتماد المالي النهائي',            'Unit Final Approval',           410, 'المالية والخزينة', 'قفلُ الأثرِ الماليِّ للفترة'),
 array('Finance/ar_accrual_gen.php',     'توليد استحقاقات عقد العميل',         'AR Accrual Generation',         420, 'المالية والخزينة', 'استحقاقٌ محاسبيٌّ معتمَد'),
 array('Finance/ar_completion_cert.php', 'شهادة الإنجاز الشهرية',              'Monthly Completion Certificate',430, 'المالية والخزينة', 'شهادةُ إنجازٍ معتمَدة'),
 array('Finance/ar_claim_invoice.php',   'فاتورة المطالبة وإحالتها',           'Claim Invoice and Referral',    440, 'المالية والخزينة', 'فاتورةٌ محالةٌ للتحصيل'),
 array('Operations/unit_correction.php', 'تصحيح الوحدات بالسلسلة الثلاثية',    'Unit Correction Triple Chain',  450, 'إدارة التشغيل',   'تصحيحٌ معتمَدٌ بالأطرافِ الثلاثة'),
 array('Finance/tre_beneficiary.php',    'سجل المستفيدين والحسابات البنكية',   'Beneficiary Register',          760, 'المالية والخزينة', 'مستفيدٌ متحقَّقٌ منه'),
 array('Finance/tre_pay_batch.php',      'دفعات الدفع والتنفيذ',               'Payment Batch Execution',       770, 'المالية والخزينة', 'مرجعُ حركةٍ بنكيّ'),
);
$in = "'" . implode("','", array_map(function ($x) use ($conn) {
        return $conn->real_escape_string($x[0]); }, $S)) . "'";

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `nav_canonical` WHERE `route` IN ({$in}) AND `derivation` = '{$SRC}'");
    echo "↺ حُذف {$conn->affected_rows} صفًّا كنسيًّا\n";
    exit(0);
}

$q = $conn->query("SELECT COALESCE(MAX(`id`),0), COALESCE(MAX(`matrix_row`),0) FROM `nav_canonical`");
list($nextId, $nextRow) = $q ? $q->fetch_row() : array(0, 0);
$nextId = (int) $nextId; $nextRow = (int) $nextRow;

$ins = $conn->prepare(
  "INSERT INTO `nav_canonical`
     (`id`,`route`,`canonical_ar`,`canonical_en`,`level_no`,`level_name`,`group_name`,`sort_no`,
      `nature`,`owner_dept`,`status`,`decision_state`,`application_state`,`policy_domain`,
      `derivation`,`retirement_status`,`current_label`,`current_parent`,`matrix_row`,
      `created_at`,`output_doc`,`placement_kind`,`placement_basis`,`space_class`)
   VALUES (?,?,?,?,2,'2 — العمليات',?,?, 'شاشةٌ مستقلة',?, 'APPROVED','APPROVED','DEPLOYED',
           'NAVIGATION_NAMING_POSITION', ?, 'ACTIVE', ?, ?, ?, NOW(), ?, 'SINGLE', ?, 'OWNED')
   ON DUPLICATE KEY UPDATE
     `group_name`=VALUES(`group_name`), `sort_no`=VALUES(`sort_no`),
     `canonical_ar`=VALUES(`canonical_ar`), `status`=VALUES(`status`)");

$made = 0; $bad = array();
$basis = 'عقدةٌ في سلسلةِ الأثرِ المشتركة — موضعُها مرحلتُها في الدورةِ المستندية';
foreach ($S as $r) {
    list($route, $ar, $en, $sort, $owner, $outDoc) = $r;
    $chk = $conn->query("SELECT COUNT(*) FROM `nav_canonical` WHERE `route` = '"
                        . $conn->real_escape_string($route) . "'");
    if ($chk && (int) $chk->fetch_row()[0] > 0) { continue; }
    $nextId++; $nextRow++;
    /* ثلاثةَ عشرَ متغيرًا ⇐ ثلاثةَ عشرَ حرفًا: i s s s s i s s s s i s s */
    $ins->bind_param('issssissssiss',
        $nextId, $route, $ar, $en, $GRP, $sort, $owner, $SRC, $ar, $GRP, $nextRow, $outDoc, $basis);
    if ($ins->execute()) { $made++; }
    else { $bad[] = $route . ': ' . $ins->error; }
}
$ins->close();
printf("① صفوفٌ كنسيةٌ أُضيفت: %d\n", $made);
foreach ($bad as $b) { echo "  ✘ {$b}\n"; }

$q = $conn->query("SELECT `route`, `group_name`, `sort_no` FROM `nav_canonical`
                    WHERE `route` IN ({$in}) ORDER BY `sort_no`");
echo "② الموضعُ الكنسيُّ بعدَ التسجيل:\n";
while ($q && $r = $q->fetch_row()) { printf("   %-36s %-42s %s\n", $r[0], mb_substr($r[1], 0, 40), $r[2]); }

ems_migration_recorded(__FILE__, $conn, 0);
