<?php
/**
 * 2027_06_30_uxui_matrix_v3.php — تصحيحاتُ المالكِ الأربعةُ على سجلِّ التنقل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المرجع: UXUI_MASTER_AUDIT-3 (sha256 أوله ec09cf1e7ae1b3667765a74b4e38f7f0)
 *   بتفويضِ 2026-08-18: «المولّدُ يقرأ من المصفوفةِ وحدَها ويتصرف بالحالة —
 *   لا سايدبارَ موروثٌ خلفَ المصفوفةِ أبدًا».
 * ◆ ① الحالةُ الجديدة PENDING_OWNER_MERGE (صفّان: equipments_fleet يُدمج منظرًا
 *   محفوظًا في equipments.php · وreports.php يُتقاعَد بعد إثباتِ التغطية).
 *   ② أعمدةُ الانتقال view_of · merge_into · retirement_status · current_*.
 *   ③ nav_canonical_current: موضعُ المعلَّقِ الحاليُّ **لكلِّ دورٍ** — مبذورٌ من
 *   الأساسِ الحيِّ الملتزم docs/uxui_live_positions.tsv (911 موضعًا · 382a0b7)
 *   لأن الخليةَ الواحدةَ لا تحمل تعدُّدَ مواضعِ الدورِ الواحد، وهو ما فوَّضه
 *   المالكُ نصًّا («current_order يملؤه المبرمجُ من uxui_live_positions.tsv»).
 *   ④ nav_canonical_variants: المداخلُ الثانية (#مرساة · ?view=منظرٌ معلَن)
 *   بمفاتيحِها الأربعة canonical_route · variant_key · variant_type · purpose.
 * ◆ إعادةُ البذرِ upsert بالمسارِ — لا حذفَ صفٍّ (صفرُ فقدٍ حتى في السجل).
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
$tsv = $ROOT . '/docs/uxui_live_positions.tsv';
if (!is_file($csv) || !is_file($tsv)) { exit("ناقصُ المصادر: {$csv} أو {$tsv}\n"); }

/* ── ①+② توسيعُ الجدول (idempotent) ── */
$col = function ($name) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='nav_canonical' AND COLUMN_NAME='{$name}'");
    return $r && $r->num_rows > 0;
};
$conn->query("ALTER TABLE nav_canonical MODIFY status ENUM('APPROVED','PENDING_OWNER','PENDING_DEDUP','PENDING_OWNER_MERGE','TECHNICAL_ONLY','MERGED','RETIRED') NOT NULL");
if (!$col('view_of'))            { $conn->query("ALTER TABLE nav_canonical ADD view_of VARCHAR(255) NULL COMMENT 'علاقةُ المنظر/الارتباط بمسارٍ داخل الـ359' AFTER derivation"); }
if (!$col('merge_into'))         { $conn->query("ALTER TABLE nav_canonical ADD merge_into VARCHAR(255) NULL AFTER view_of"); }
if (!$col('retirement_status'))  { $conn->query("ALTER TABLE nav_canonical ADD retirement_status VARCHAR(40) NULL AFTER merge_into"); }
if (!$col('current_label'))      { $conn->query("ALTER TABLE nav_canonical ADD current_label VARCHAR(190) NULL COMMENT 'اسمُ الانتقالِ للمعلَّق — من الصفِّ لا من إرث' AFTER retirement_status"); }
if (!$col('current_parent'))     { $conn->query("ALTER TABLE nav_canonical ADD current_parent VARCHAR(255) NULL AFTER current_label"); }

/* ── إعادةُ البذرِ upsert من CSV v3 ── */
$fh = fopen($csv, 'r');
$hdr = fgetcsv($fh);
$up = $conn->prepare("INSERT INTO nav_canonical
    (route, canonical_ar, canonical_en, level_no, level_name, group_name, sort_no, nature, owner_dept, status, old_names, derivation, matrix_row, view_of, merge_into, retirement_status, current_label, current_parent)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), canonical_en=VALUES(canonical_en),
      level_no=VALUES(level_no), level_name=VALUES(level_name), group_name=VALUES(group_name),
      sort_no=VALUES(sort_no), nature=VALUES(nature), owner_dept=VALUES(owner_dept), status=VALUES(status),
      old_names=VALUES(old_names), derivation=VALUES(derivation), matrix_row=VALUES(matrix_row),
      view_of=VALUES(view_of), merge_into=VALUES(merge_into), retirement_status=VALUES(retirement_status),
      current_label=VALUES(current_label), current_parent=VALUES(current_parent)");
if (!$up) { exit("prepare فشل: {$conn->error}\n"); }
$n = 0;
while (($r = fgetcsv($fh)) !== false) {
    $row = array_combine($hdr, $r);
    $lvlNo = 0; $lvlName = trim((string) $row['level']);
    if (preg_match('/^([1-6١-٦])\s*[—-]\s*(.+)$/u', $lvlName, $m)) {
        $map = array('١'=>1,'٢'=>2,'٣'=>3,'٤'=>4,'٥'=>5,'٦'=>6);
        $lvlNo = isset($map[$m[1]]) ? $map[$m[1]] : (int) $m[1];
        $lvlName = trim($m[2]);
    }
    if ($lvlNo < 1 || $lvlNo > 6) { echo "⚠ مستوًى غيرُ مقروء للصف {$row['n']}\n"; continue; }
    $sort = is_numeric(trim((string) $row['sort'])) ? (int) $row['sort'] : 999;
    $dash = function ($v) { $v = trim((string) $v); return ($v === '—' || $v === '') ? null : $v; };
    $route = trim($row['route']); $ca = trim($row['canonical_ar']); $ce = trim($row['canonical_en']);
    $grp = trim($row['canonical_group']); $nat = trim($row['nature']); $own = trim($row['owner_dept']);
    $st = trim($row['status']); $old = trim($row['old_names']); $der = trim($row['derivation']);
    $mrow = (int) $row['n'];
    $vo = $dash($row['view_of']); $mi = $dash($row['merge_into']); $rs = $dash($row['retirement_status']);
    $cl = $dash($row['current_label']); $cp = $dash($row['current_parent']);
    $up->bind_param('sssisssissssisssss', $route, $ca, $ce, $lvlNo, $lvlName, $grp, $sort, $nat, $own, $st, $old, $der, $mrow, $vo, $mi, $rs, $cl, $cp);
    $up->execute(); $n++;
}
fclose($fh);
echo "بُذر/حُدِّث: {$n} صفًّا\n";

/* ── ③ موضعُ المعلَّقِ الحاليُّ لكلِّ دور — من الأساسِ الحيِّ الملتزم ── */
$conn->query("CREATE TABLE IF NOT EXISTS `nav_canonical_current` (
  `route` VARCHAR(160) NOT NULL,
  `role_id` INT NOT NULL,
  `cur_label` VARCHAR(190) NOT NULL,
  `cur_group` VARCHAR(190) NOT NULL,
  `cur_order` INT NOT NULL COMMENT 'تسلسلُ الظهورِ الحيُّ في الدور — من uxui_live_positions.tsv',
  PRIMARY KEY (`route`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01 v3: موضعُ الانتقالِ الحاليُّ للمعلَّقِ لكلِّ دور — المصفوفةُ وحدَها مصدرًا'");
$ins = $conn->prepare("INSERT INTO nav_canonical_current (route, role_id, cur_label, cur_group, cur_order)
                       VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE cur_label=VALUES(cur_label), cur_group=VALUES(cur_group), cur_order=VALUES(cur_order)");
$lines = file($tsv); array_shift($lines);
$cc = 0;
foreach ($lines as $l) {
    $c = explode("\t", rtrim($l, "\n"));
    if (count($c) < 11) { continue; }
    $rid = (int) $c[0]; $seq = (int) $c[3]; $grp = trim($c[4]); $lbl = trim($c[5]);
    $routeBase = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($c[6]))));
    /* المتغيراتُ (#·?view) لها سجلُّها الرابع — هنا الأصلُ فقط */
    if (strpbrk(trim($c[6]), '#?') !== false) { continue; }
    $ins->bind_param('sissi', $routeBase, $rid, $lbl, $grp, $seq);
    $ins->execute(); $cc++;
}
echo "مواضعُ current المبذورة: {$cc}\n";

/* ── ④ المداخلُ الثانية — route_variant بمفاتيحِه الأربعة ── */
$conn->query("CREATE TABLE IF NOT EXISTS `nav_canonical_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `canonical_route` VARCHAR(160) NOT NULL,
  `variant_key` VARCHAR(120) NOT NULL COMMENT '#مرساة أو ?view=منظر',
  `variant_type` ENUM('anchor','declared_view') NOT NULL,
  `variant_purpose` VARCHAR(190) NOT NULL COMMENT 'الاسمُ الظاهرُ للمدخلِ الثاني',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant` (`canonical_route`, `variant_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='UXUI-01 v3: سجلُّ المداخلِ الثانية — المرساةُ والمنظرُ المعلَن'");
$vin = $conn->prepare("INSERT INTO nav_canonical_variants (canonical_route, variant_key, variant_type, variant_purpose)
                       VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE variant_purpose=VALUES(variant_purpose)");
$vc = 0; $seen = array();
foreach ($lines as $l) {
    $c = explode("\t", rtrim($l, "\n"));
    if (count($c) < 11) { continue; }
    $href = preg_replace('~^(\.\./)+~', '', trim($c[6]));
    if (strpbrk($href, '#?') === false) { continue; }
    $base = strtolower(preg_replace('/[?#].*$/u', '', $href));
    $key = substr($href, strlen(preg_replace('/[?#].*$/u', '', $href)));
    $type = ($key !== '' && $key[0] === '#') ? 'anchor' : 'declared_view';
    $k = $base . '|' . $key;
    if (isset($seen[$k])) { continue; }
    $seen[$k] = true;
    $lbl = trim($c[5]);
    $vin->bind_param('ssss', $base, $key, $type, $lbl);
    $vin->execute(); $vc++;
}
echo "المداخلُ الثانيةُ المسجَّلة: {$vc}\n";

/* ── الإثبات ── */
$r = $conn->query("SELECT status, COUNT(*) c FROM nav_canonical GROUP BY status ORDER BY status");
while ($x = $r->fetch_assoc()) { echo "  {$x['status']}: {$x['c']}\n"; }
$tot = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical")->fetch_assoc()['c'];
$cur = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical_current")->fetch_assoc()['c'];
$var = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical_variants")->fetch_assoc()['c'];
echo "nav_canonical={$tot} (المتوقَّع 359: 281·76·2) · current={$cur} · variants={$var}\n";
if ($tot !== 359) { exit("✗ العددُ لا يطابق\n"); }
echo "✔ سجلُّ v3 نافذٌ — المصفوفةُ وحدَها مصدرُ التنقل\n";
