<?php
/**
 * 2027_06_04_gov_stage_outputs_registry.php
 * ═══════════════════════════════════════════════════════════════════════════
 * سجلُّ «المستندِ الناتجِ» و«الحالةِ التالية» لكلِّ مرحلة — بمصدرٍ معلَنٍ لكلِّ خانة
 * ───────────────────────────────────────────────────────────────────────────
 * كانت مصفوفةُ السايدبارِ عند 66.8٪ لأن عمودَين بلا مصدرٍ حاكم (10.5٪).
 * والإجاباتُ في الوثائقِ والمخططِ الحيِّ معًا — لا في التخمين:
 *
 *   ① `GOV-24 §٥-٢` تحمل «المستندَ المنتَجَ» و«المعتمِدَ» لكلِّ مرحلةٍ من تسعِ
 *      مراحلِ الحوكمةِ نصًّا. تُنقل حرفًا بمصدرٍ `GOV-24`.
 *
 *   ② `action_writes` (68 صفًّا · 42 فعلًا) تقول أيُّ فعلٍ يكتب في أيِّ جدول.
 *      فالمستندُ الناتجُ لمرحلةٍ = الجداولُ التي تكتبها أفعالُ شاشاتِها.
 *      مقيسٌ من الحيِّ بمصدرٍ `action_writes`.
 *
 *   ③ `LAD-01` تحمل حمولةَ كلِّ سلّم — وهي مسجَّلةٌ سلفًا في
 *      `gov_ladders.payload_note`. تُستعمل للمراحلِ المرتبطةِ بسلّم.
 *
 *   ④ «الحالةُ التالية»: `GOV-24 §٥-١` تنصُّ أن **ترتيبَ السايدبارِ هو الدورةُ
 *      المستنديةُ نفسُها** («الترتيبُ من الدورةِ لا من تاريخ البرمجة») — فالحالةُ
 *      التاليةُ لمرحلةٍ هي المرحلةُ التاليةُ في دورةِ إدارتِها. مصدرٌ `cycle_order`.
 *
 * ◆ ولكلِّ خانةٍ عمودُ `source` يقول من أين جاءت — وما لا مصدرَ له يبقى `none`
 *   ظاهرًا في المصفوفةِ ولا يُملأ تخمينًا.
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
$run = function (string $s, string $l) use ($conn): bool {
    if ($conn->query($s)) { echo "   ✔ $l\n"; return true; }
    echo "   ✗ $l — " . $conn->error . "\n"; return false;
};
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ ① الجدول\n";
$run("CREATE TABLE IF NOT EXISTS `gov_stage_outputs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `company_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'عمودُ العزل — 0 = كلُّ كيان',
        `role_id` SMALLINT UNSIGNED NOT NULL COMMENT 'الإدارةُ المالكة',
        `stage_no` TINYINT UNSIGNED NOT NULL,
        `stage_title` VARCHAR(190) NOT NULL,
        `output_doc` VARCHAR(255) NULL COMMENT 'المستندُ الناتج',
        `output_source` ENUM('GOV-24','LAD-01','action_writes','none') NOT NULL DEFAULT 'none',
        `next_state` VARCHAR(255) NULL COMMENT 'الحالةُ التالية',
        `next_source` ENUM('GOV-24','LAD-01','cycle_order','none') NOT NULL DEFAULT 'none',
        `approver_note` VARCHAR(190) NULL COMMENT 'المعتمِدُ كما في الوثيقة',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_stage` (`role_id`,`stage_no`),
        KEY `ix_src` (`output_source`,`next_source`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='⑨ المستندُ الناتجُ والحالةُ التالية — ولكلِّ خانةٍ مصدرُها المعلَن'",
    'gov_stage_outputs');

// ───────── بذرةٌ عامةٌ: كلُّ (دور × مرحلة) حيّةٍ ─────────
echo "\n▐ ② بذرُ كلِّ مرحلةٍ حيّة\n";
$conn->query(
    "INSERT IGNORE INTO `gov_stage_outputs` (`role_id`,`stage_no`,`stage_title`)
     SELECT g.`owner_role_id`, g.`stage_no`, MIN(g.`stage_title`)
       FROM `link_groups` g
      WHERE g.`is_active`=1 AND g.`stage_no` IS NOT NULL AND g.`owner_role_id` IS NOT NULL
      GROUP BY g.`owner_role_id`, g.`stage_no`");
printf("   ✔ %s مرحلةً مبذورة\n", $one("SELECT COUNT(*) FROM `gov_stage_outputs`"));

// ───────── ① GOV-24 §٥-٢ حرفًا لدورِ الحوكمة (15) ─────────
echo "\n▐ ③ GOV-24 §٥-٢ — نصًّا لدورِ الحوكمة\n";
$GOV24 = array(
    // stage_no في الحيِّ لدور 15 → المستندُ المنتَجُ والمعتمِد
    1  => array('الإداراتُ والأدوارُ والمسمياتُ الوظيفية', 'بحسبِ سقفِ كلِّ مستند'),
    2  => array('التفويضاتُ بمدةٍ وسقفٍ ونطاق',           'بحسبِ سقفِ كلِّ مستند'),
    3  => array('سجلاتُ الوصولِ والاطّلاعِ والتدقيق',      'بحسبِ سقفِ كلِّ مستند'),
    4  => array('المخالفاتُ والاستثناءاتُ والامتثال',      'بحسبِ سقفِ كلِّ مستند'),
    5  => array('المخالفاتُ والاستثناءاتُ والامتثال',      'بحسبِ سقفِ كلِّ مستند'),
    6  => array('سجلاتُ الوصولِ والاطّلاعِ والتدقيق',      'بحسبِ سقفِ كلِّ مستند'),
    7  => array('بنيةُ المستنداتِ وأنواعُها وحالاتُها',   'بحسبِ نوعِ المستند'),
    8  => array('وضعُ التأسيسِ وبصمةُ الإصدار',            'الحوكمةُ والتطوير'),
    9  => array('مخاطرُ إدارتي وحوكمتُها وتقاريرُها',      'بحسبِ سقفِ كلِّ مستند'),
    10 => array('ناقلُ الأحداثِ وطابورُ المهامِّ والاستعادةُ لنقطةِ زمن', 'الحوكمةُ والتطوير'),
);
$st = $conn->prepare(
    "UPDATE `gov_stage_outputs`
        SET `output_doc`=?, `output_source`='GOV-24', `approver_note`=?
      WHERE `role_id`=15 AND `stage_no`=?");
$n = 0;
foreach ($GOV24 as $sn => $v) {
    $st->bind_param('ssi', $v[0], $v[1], $sn);
    if ($st->execute() && $conn->affected_rows > 0) { $n++; }
}
$st->close();
echo "   ✔ $n مرحلةً من GOV-24 نصًّا\n";

// ───────── ② المستندُ الناتجُ مقيسًا من action_writes ─────────
echo "\n▐ ④ المستندُ الناتجُ من action_writes — مقيسٌ لا مخمَّن\n";
$conn->query(
    "UPDATE `gov_stage_outputs` o
       JOIN (
            SELECT g.`owner_role_id` AS rid, g.`stage_no` AS sn,
                   GROUP_CONCAT(DISTINCT w.`table_name` ORDER BY w.`table_name` SEPARATOR ' · ') AS tabs
              FROM `link_groups` g
              JOIN `nav_items` n   ON n.`group_id` = g.`id` AND n.`active`=1
              JOIN `modules` m     ON m.`id` = n.`module_id`
              JOIN `actions` a     ON a.`module_id` = m.`id` AND a.`active`=1 AND a.`is_write`=1
              JOIN `action_writes` w ON w.`action_code` = a.`action_code`
             WHERE g.`is_active`=1 AND g.`stage_no` IS NOT NULL
             GROUP BY g.`owner_role_id`, g.`stage_no`
       ) d ON d.rid = o.`role_id` AND d.sn = o.`stage_no`
        SET o.`output_doc` = LEFT(d.tabs, 255), o.`output_source`='action_writes'
      WHERE o.`output_source` = 'none'");
printf("   ✔ %s مرحلةً بمستندٍ مقيسٍ من أفعالِها\n", $conn->affected_rows);

// ───────── ③ حمولاتُ LAD-01 للمراحلِ المرتبطةِ بسلّم ─────────
echo "\n▐ ⑤ حمولاتُ LAD-01 للمراحلِ ذاتِ السلاليم\n";
$conn->query(
    "UPDATE `gov_stage_outputs` o
       JOIN `gov_ladders` l ON l.`cycle_no` = o.`stage_no` AND l.`is_active`=1
        SET o.`output_doc` = l.`payload_note`, o.`output_source`='LAD-01'
      WHERE o.`output_source`='none' AND o.`role_id` IN (1,6,7,12,17,19)");
printf("   ✔ %s مرحلةً من حمولاتِ السلاليم\n", $conn->affected_rows);

// ───────── ④ الحالةُ التالية = المرحلةُ التاليةُ في دورةِ الإدارة ─────────
echo "\n▐ ⑥ الحالةُ التالية — GOV-24 §٥-١: الترتيبُ هو الدورة\n";
$conn->query(
    "UPDATE `gov_stage_outputs` o
       JOIN (
            SELECT a.`role_id`, a.`stage_no`,
                   (SELECT b.`stage_title` FROM `gov_stage_outputs` b
                     WHERE b.`role_id`=a.`role_id` AND b.`stage_no` > a.`stage_no`
                     ORDER BY b.`stage_no` ASC LIMIT 1) AS nxt
              FROM `gov_stage_outputs` a
       ) d ON d.`role_id`=o.`role_id` AND d.`stage_no`=o.`stage_no`
        SET o.`next_state` = d.nxt, o.`next_source`='cycle_order'
      WHERE d.nxt IS NOT NULL AND o.`next_source`='none'");
printf("   ✔ %s مرحلةً بحالةٍ تالية\n", $conn->affected_rows);

// المرحلةُ الأخيرةُ لكلِّ إدارةٍ: نهايةُ الدورةِ لا فراغ
$conn->query(
    "UPDATE `gov_stage_outputs` SET `next_state`='نهايةُ دورةِ الإدارة — لا مرحلةَ بعدَها',
            `next_source`='cycle_order'
      WHERE `next_source`='none'");
printf("   ✔ %s مرحلةً وُسمت نهايةَ دورة\n", $conn->affected_rows);

// ───────── التحقُّق ─────────
echo "\n▐ ⑦ التغطيةُ بمصدرِها\n";
$tot = (int) $one("SELECT COUNT(*) FROM `gov_stage_outputs`");
foreach (array('output_source' => 'المستند الناتج', 'next_source' => 'الحالة التالية') as $col => $lbl) {
    echo "   $lbl:\n";
    $r = $conn->query("SELECT `$col`, COUNT(*) c FROM `gov_stage_outputs` GROUP BY `$col` ORDER BY c DESC");
    while ($x = $r->fetch_row()) { printf("      %-16s %-5s (%.1f%%)\n", $x[0], $x[1], 100 * $x[1] / max(1, $tot)); }
}
printf("\n   إجماليُّ المراحل: %d\n\n", $tot);
