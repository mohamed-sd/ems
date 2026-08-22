<?php
/**
 * 2027_10_05_align_ownership_and_names.php
 *   فتحُ ما أمرت الوثيقةُ بفتحِه · وتوحيدُ الاسمِ الظاهرِ بالكنسيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **① ما أمرت الوثيقةُ بفتحِه — ولا يُفتَح بلا نصٍّ يأمر به**:
 *   · `Contracts/contract_coverage.php` في مساحةِ الموردين: وثيقةُ الموردين
 *     تقول بنصِّها «احتياجاتُ التغطية — **إسقاطُ قراءة** · جانبُ الطلبِ من عقدِ
 *     العميل · **محجوبٌ اليومَ عن الإدارةِ ويجب فتحُه قراءةً**» — و«بدونه كتلةُ
 *     التعاقدِ بلا مدخل». فالحكمُ `FORBIDDEN` هو عينُ ما أمرت بقلبه.
 *   · `Reports/reports.php` في مساحتَي المبيعاتِ والموردين: الوثيقتان تجعلانه
 *     **بندًا من الثلاثةَ عشرَ والأربعةَ عشر** («التقارير والتحليلات» و«تقارير
 *     الموردين»). فحكمُ المنعِ سابقٌ للوثيقتين ويُصحَّح بهما.
 *   ◆ **والحكمُ يصير `CONTEXTUAL_READ_ONLY` لا `OWNED`**: المِلكيةُ لا تنتقل —
 *     «حقُّ القراءةِ لا ينقل الملكية».
 *
 * ◆ **② قاموسُ المسمياتِ الملزم**: «ممنوعٌ اسمان لشاشةٍ واحدة». وسبعةٌ وثلاثون
 *   مسارًا معياريًّا (`APPROVED`) يُصيَّر بأسماءٍ مختلفةٍ باختلافِ الدور —
 *   «العملاء» و«سجل العملاء»، «المستخلصات» و«المستخلصات والمطالبات»…
 *   فيُوحَّد الاسمُ الظاهرُ على **الاسمِ الكنسيِّ في السجل**، ولا يُخترَع اسم.
 *   ◆ والاسمُ السابقُ محفوظٌ في سجلِّ التوحيدِ — والرجوعُ يعيده حرفًا.
 *
 * التشغيل:  php database/migrations/2027_10_05_align_ownership_and_names.php
 * الرجوع :  php database/migrations/2027_10_05_align_ownership_and_names.php --revert
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

$conn->query("CREATE TABLE IF NOT EXISTS `gov_label_unified_log` (
  `nav_id`      INT NOT NULL PRIMARY KEY,
  `role_id`     INT NOT NULL,
  `route`       VARCHAR(160) NOT NULL,
  `label_before` VARCHAR(120) NOT NULL,
  `label_after`  VARCHAR(120) NOT NULL,
  `basis`       VARCHAR(160) NOT NULL,
  `at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='توحيدُ الاسمِ الظاهرِ على الكنسيّ — بالاسمِ السابقِ لكلِّ صفّ'");

/* ما فُتح بأمرِ الوثيقة: route, space, doc */
$OPEN = array(
  array('Contracts/contract_coverage.php', 'ادارة الموردين', 'INJ-SUP-ALIGN-01 · م05'),
  array('Reports/reports.php',             'ادارة المبيعات', 'INJ-SAL-ALIGN-01 · التقارير والتحليلات'),
  array('Reports/reports.php',             'ادارة الموردين', 'INJ-SUP-ALIGN-01 · تقارير الموردين'),
);

if (in_array('--revert', $argv, true)) {
    $q = $conn->query("SELECT `nav_id`,`label_before` FROM `gov_label_unified_log`");
    $back = 0;
    while ($q && $r = $q->fetch_assoc()) {
        $st = $conn->prepare("UPDATE `nav_items` SET `label_ar` = ? WHERE `id` = ?");
        $id = (int) $r['nav_id'];
        $st->bind_param('si', $r['label_before'], $id);
        $st->execute(); $back += $st->affected_rows; $st->close();
    }
    $conn->query("DROP TABLE IF EXISTS `gov_label_unified_log`");
    foreach ($OPEN as $o) {
        $st = $conn->prepare("UPDATE `gov_space_appearances` SET `cls` = 'FORBIDDEN'
                               WHERE `route` = ? AND `space_ar` = ? AND `cls` = 'CONTEXTUAL_READ_ONLY'");
        $st->bind_param('ss', $o[0], $o[1]); $st->execute(); $st->close();
    }
    echo "↺ أُعيد {$back} اسمًا · وأُعيدت أحكامُ المساحاتِ إلى المنع\n";
    exit(0);
}

/* ══ ① فتحُ ما أمرت الوثيقةُ بفتحِه ═════════════════════════════════════ */
$opened = 0;
foreach ($OPEN as $o) {
    list($route, $space, $basis) = $o;
    $st = $conn->prepare("UPDATE `gov_space_appearances`
                             SET `cls` = 'CONTEXTUAL_READ_ONLY',
                                 `decision` = 'CONFIRMED',
                                 `basis` = ?
                           WHERE `route` = ? AND `space_ar` = ? AND `cls` = 'FORBIDDEN'");
    $b = mb_substr('قراءةٌ سياقيةٌ بأمرِ ' . $basis . ' — والمِلكيةُ لا تنتقل', 0, 255);
    $st->bind_param('sss', $b, $route, $space);
    $st->execute();
    $opened += $st->affected_rows;
    $st->close();
}
printf("① فُتح %d ظهورًا قراءةً سياقيةً بأمرِ الوثيقة — ولا تنتقل ملكية\n", $opened);

/* ══ ② توحيدُ الاسمِ الظاهرِ على الكنسيّ ══════════════════════════════ */
$q = $conn->query("
  SELECT n.`id`, n.`role_id`, n.`route`, n.`label_ar`, c.`canonical_ar`
    FROM `nav_items` n
    JOIN `nav_canonical` c ON c.`route` = n.`route` AND c.`status` = 'APPROVED'
   WHERE n.`active` = 1 AND n.`route` NOT LIKE '%#%'
     AND n.`label_ar` <> c.`canonical_ar`
     AND n.`route` IN (SELECT `route` FROM (
           SELECT n2.`route` FROM `nav_items` n2
             JOIN `nav_canonical` c2 ON c2.`route` = n2.`route` AND c2.`status` = 'APPROVED'
            WHERE n2.`active` = 1 AND n2.`route` NOT LIKE '%#%'
            GROUP BY n2.`route` HAVING COUNT(DISTINCT n2.`label_ar`) > 1) t)");
$rows = array();
while ($q && $r = $q->fetch_assoc()) { $rows[] = $r; }

$log = $conn->prepare("INSERT INTO `gov_label_unified_log`
      (`nav_id`,`role_id`,`route`,`label_before`,`label_after`,`basis`)
      VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE `label_after` = VALUES(`label_after`)");
$upd = $conn->prepare("UPDATE `nav_items` SET `label_ar` = ? WHERE `id` = ?");
$basis = 'قاموسُ المسمياتِ الملزم — الاسمُ الكنسيُّ في nav_canonical';
$n = 0;
foreach ($rows as $r) {
    $id = (int) $r['id']; $role = (int) $r['role_id'];
    $log->bind_param('iissss', $id, $role, $r['route'], $r['label_ar'], $r['canonical_ar'], $basis);
    $log->execute();
    $upd->bind_param('si', $r['canonical_ar'], $id);
    if ($upd->execute() && $upd->affected_rows) { $n++; }
}
$log->close(); $upd->close();
printf("② وُحِّد %d اسمًا ظاهرًا على الكنسيّ — والسابقُ محفوظ\n", $n);

$left = $conn->query("SELECT COUNT(*) FROM (
      SELECT n.`route` FROM `nav_items` n
        JOIN `nav_canonical` c ON c.`route` = n.`route` AND c.`status` = 'APPROVED'
       WHERE n.`active` = 1 AND n.`route` NOT LIKE '%#%'
       GROUP BY n.`route` HAVING COUNT(DISTINCT n.`label_ar`) > 1) x");
printf("③ مسارٌ معياريٌّ ما يزال باسمَين: **%d**\n", $left ? (int) $left->fetch_row()[0] : -1);

ems_migration_recorded(__FILE__, $conn, 0);
