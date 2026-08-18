<?php
/**
 * 2027_06_29_uxui_nav_canonical.php — سجلُّ التنقلِ المعياريّ (UXUI-01 ف١٥)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ «قلبُ العقدِ سجلٌّ واحدٌ اسمُه سجلُّ التنقلِ المعياريّ — ورقةُ (مصفوفة
 *   التنقل المعيارية) هي صورتُه المعتمَدة: 359 مسارًا فريدًا، لكلٍّ اسمٌ واحدٌ
 *   وموضعٌ واحدٌ وترتيبٌ واحدٌ وطبيعةٌ واحدة. ومنه يُولَّد السايدبار.»
 * ◆ البذرُ حرفيٌّ من docs/uxui_matrix_20260818.csv (تصديرُ الدفترِ المعتمَد —
 *   sha256 أوله 96d42a26) — 272 APPROVED · 76 PENDING_OWNER · 11 PENDING_DEDUP.
 * ◆ الجدولُ مرجعُ نظامٍ عامٌّ (TenantRegistry: T_GLOBAL كـnav_items) — ولا
 *   يحلُّ محلَّ nav_items: التبعيةُ والظهورُ بالدورِ يبقيان هناك (بند ٥)،
 *   والاسمُ والموضعُ المعياريانِ من هنا (بندا ٢ و٣).
 * ◆ حالةُ الاعتمادِ لا يرقّيها مبرمجٌ (ف١٥-٢): التحديثُ بهجرةٍ لاحقةٍ بعد
 *   توقيعِ «جلسة إغلاق المعلَّق» — لا UPDATE يدويٌّ ولا من الشاشات.
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
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$csv = $ROOT . '/docs/uxui_matrix_20260818.csv';
if (!is_file($csv)) { exit("لا مصفوفة: {$csv}\n"); }

/* ── الجدول ── */
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `nav_canonical` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `route` VARCHAR(160) NOT NULL COMMENT 'المعرِّف — Dir/file.php بلا استعلامٍ ولا مرساة',
  `canonical_ar` VARCHAR(190) NOT NULL COMMENT 'الاسمُ العربيُّ الرسميُّ الواحد',
  `canonical_en` VARCHAR(190) NULL,
  `level_no` TINYINT NOT NULL COMMENT 'المستوى 1..6 (ف٧-١)',
  `level_name` VARCHAR(60) NOT NULL,
  `group_name` VARCHAR(190) NOT NULL COMMENT 'المجموعةُ المعياريةُ الواحدة (بند ٣)',
  `sort_no` INT NOT NULL DEFAULT 999 COMMENT 'الترتيبُ داخل المجموعة — من الدورةِ المستندية',
  `nature` VARCHAR(60) NULL,
  `owner_dept` VARCHAR(120) NULL COMMENT 'الإدارةُ المالكةُ للمفهوم',
  `status` ENUM('APPROVED','PENDING_OWNER','PENDING_DEDUP','TECHNICAL_ONLY','MERGED','RETIRED') NOT NULL,
  `old_names` TEXT NULL COMMENT 'المسمياتُ الملغاة — مرادفاتٌ تاريخية',
  `derivation` VARCHAR(190) NULL COMMENT 'مصدرُ الاشتقاق (بند ٤ — إلزاميّ)',
  `matrix_row` SMALLINT NULL COMMENT 'رقمُ الصفِّ في الدفترِ المعتمَد',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route` (`route`),
  KEY `ix_status_level` (`status`, `level_no`, `sort_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01: سجلُّ التنقلِ المعياريُّ — صورةُ مصفوفةِ الـ359 المعتمَدة'");
if (!$ok) { exit("CREATE فشل: {$conn->error}\n"); }

/* ── البذرُ الحرفيُّ — إدراجٌ عاطلٌ عند التكرار (يُعاد تشغيلُها بأمان) ── */
$fh = fopen($csv, 'r');
$hdr = fgetcsv($fh);
$ins = $conn->prepare("INSERT IGNORE INTO nav_canonical
    (route, canonical_ar, canonical_en, level_no, level_name, group_name, sort_no, nature, owner_dept, status, old_names, derivation, matrix_row)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
if (!$ins) { exit("prepare فشل: {$conn->error}\n"); }
$n = 0; $bad = 0;
while (($r = fgetcsv($fh)) !== false) {
    $row = array_combine($hdr, $r);
    $lvlNo = 0; $lvlName = trim((string) $row['level']);
    if (preg_match('/^([1-6١-٦])\s*[—-]\s*(.+)$/u', $lvlName, $m)) {
        $map = array('١'=>1,'٢'=>2,'٣'=>3,'٤'=>4,'٥'=>5,'٦'=>6);
        $lvlNo = isset($map[$m[1]]) ? $map[$m[1]] : (int) $m[1];
        $lvlName = trim($m[2]);
    }
    if ($lvlNo < 1 || $lvlNo > 6) { $bad++; echo "⚠ مستوًى غيرُ مقروء للصف {$row['n']}: «{$row['level']}»\n"; continue; }
    $sort = is_numeric(trim((string) $row['sort'])) ? (int) $row['sort'] : 999;
    $route = trim($row['route']);
    $ca = trim($row['canonical_ar']); $ce = trim($row['canonical_en']);
    $grp = trim($row['canonical_group']); $nat = trim($row['nature']);
    $own = trim($row['owner_dept']); $st = trim($row['status']);
    $old = trim($row['old_names']); $der = trim($row['derivation']);
    $mrow = (int) $row['n'];
    $ins->bind_param('sssisssissssi', $route, $ca, $ce, $lvlNo, $lvlName, $grp, $sort, $nat, $own, $st, $old, $der, $mrow);
    if ($ins->execute() && $ins->affected_rows > 0) { $n++; }
}
fclose($fh);

/* ── الإثبات ── */
echo "بُذر: {$n} صفًّا · مرفوض: {$bad}\n";
$r = $conn->query("SELECT status, COUNT(*) c FROM nav_canonical GROUP BY status ORDER BY status");
while ($x = $r->fetch_assoc()) { echo "  {$x['status']}: {$x['c']}\n"; }
$tot = $conn->query("SELECT COUNT(*) c FROM nav_canonical")->fetch_assoc();
echo "الإجمالي: {$tot['c']} (المتوقَّع 359: 272 معتمدًا · 76 معلَّقَ مالكٍ · 11 معلَّقَ ازدواج)\n";
if ((int) $tot['c'] !== 359) { exit("✗ العددُ لا يطابق المصفوفةَ — راجِع قبل أيِّ توليد\n"); }
echo "✔ سجلُّ التنقلِ المعياريُّ مبذورٌ مطابقًا\n";
