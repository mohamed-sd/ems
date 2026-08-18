<?php
/**
 * 2027_06_05_stage_outputs_from_nav09_map.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ردمُ «المستندِ الناتج» من `nav09_action_map` — المصدرُ الحاكمُ الذي كان موجودًا
 * ───────────────────────────────────────────────────────────────────────────
 * بحثتُ عن مصدرٍ للمستندِ الناتجِ فجرّبتُ `action_writes` — وأعطى صفرًا لأن
 * أفعالَه الاثنينِ والأربعين كلَّها `module_id = NULL` («فعلٌ عابرٌ للشاشات»
 * بنصِّ تعليقِ العمود)، فلا يصل الفعلَ بشاشةٍ ولا بمرحلة.
 *
 * والمصدرُ الحاكمُ كان موجودًا: **`nav09_action_map`** (390 صفًّا · حصيلةُ حملةِ
 * NAV-09) وفيها لكلِّ فعل:
 *   `canonical_file`  الشاشةُ التي يقع فيها
 *   `writes_text`     **ما يكتبه** = المستندُ الناتج   (353 صفًّا)
 *   `effect_text`     **أثرُه بعدَ الوقوع** = الحالةُ التالية (353 صفًّا)
 *
 * والوصلُ بالاسمِ الأخيرِ للمسار (`canonical_file` = `coa.php` مقابل
 * `nav_items.route` = `Finance/coa.php`) — 142 ملفًّا مطابقًا يغطّي 219 مرحلة.
 *
 * ◆ ولا يُكتب شيءٌ تخمينًا: ما لا يجد مصدرًا يبقى `none` ظاهرًا في المصفوفة.
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

// المصدرُ الجديدُ يُضاف إلى قائمةِ المصادرِ المعلَنة
echo "\n▐ ① توسيعُ قائمةِ المصادر\n";
$conn->query("ALTER TABLE `gov_stage_outputs`
    MODIFY COLUMN `output_source` ENUM('GOV-24','LAD-01','action_writes','nav09_map','none')
        NOT NULL DEFAULT 'none',
    MODIFY COLUMN `next_source` ENUM('GOV-24','LAD-01','cycle_order','nav09_map','none')
        NOT NULL DEFAULT 'none'");
echo ($conn->errno ? "   ✗ " . $conn->error : "   ✔ أُضيف nav09_map") . "\n";

echo "\n▐ ② المستندُ الناتجُ من writes_text\n";
$conn->query(
    "UPDATE `gov_stage_outputs` o
       JOIN (
            SELECT g.`owner_role_id` AS rid, g.`stage_no` AS sn,
                   GROUP_CONCAT(DISTINCT NULLIF(TRIM(a.`writes_text`),'—')
                                ORDER BY a.`writes_text` SEPARATOR ' · ') AS docs
              FROM `link_groups` g
              JOIN `nav_items` n ON n.`group_id` = g.`id` AND n.`active` = 1
              JOIN `nav09_action_map` a
                ON SUBSTRING_INDEX(n.`route`, '/', -1) = a.`canonical_file`
             WHERE g.`is_active` = 1 AND g.`stage_no` IS NOT NULL
               AND a.`writes_text` IS NOT NULL AND TRIM(a.`writes_text`) NOT IN ('', '—')
             GROUP BY g.`owner_role_id`, g.`stage_no`
       ) d ON d.rid = o.`role_id` AND d.sn = o.`stage_no`
        SET o.`output_doc` = LEFT(d.docs, 255), o.`output_source` = 'nav09_map'
      WHERE o.`output_source` = 'none' AND d.docs IS NOT NULL AND d.docs <> ''");
printf("   ✔ %s مرحلةً بمستندٍ من writes_text\n", $conn->affected_rows);

echo "\n▐ ③ الحالةُ التالية من effect_text — أدقُّ من ترتيبِ الدورة\n";
$conn->query(
    "UPDATE `gov_stage_outputs` o
       JOIN (
            SELECT g.`owner_role_id` AS rid, g.`stage_no` AS sn,
                   GROUP_CONCAT(DISTINCT NULLIF(TRIM(a.`effect_text`),'—')
                                ORDER BY a.`effect_text` SEPARATOR ' · ') AS eff
              FROM `link_groups` g
              JOIN `nav_items` n ON n.`group_id` = g.`id` AND n.`active` = 1
              JOIN `nav09_action_map` a
                ON SUBSTRING_INDEX(n.`route`, '/', -1) = a.`canonical_file`
             WHERE g.`is_active` = 1 AND g.`stage_no` IS NOT NULL
               AND a.`effect_text` IS NOT NULL AND TRIM(a.`effect_text`) NOT IN ('', '—')
             GROUP BY g.`owner_role_id`, g.`stage_no`
       ) d ON d.rid = o.`role_id` AND d.sn = o.`stage_no`
        SET o.`next_state` = LEFT(d.eff, 255), o.`next_source` = 'nav09_map'
      WHERE o.`next_source` = 'cycle_order' AND d.eff IS NOT NULL AND d.eff <> ''");
printf("   ✔ %s مرحلةً بأثرٍ مصرَّحٍ بدل ترتيبِ الدورة\n", $conn->affected_rows);

echo "\n▐ ④ التغطيةُ بمصدرِها\n";
$tot = (int) $one("SELECT COUNT(*) FROM `gov_stage_outputs`");
foreach (array('output_source' => 'المستند الناتج', 'next_source' => 'الحالة التالية') as $col => $lbl) {
    $known = (int) $one("SELECT COUNT(*) FROM `gov_stage_outputs` WHERE `$col` <> 'none'");
    printf("   %s: %d/%d = %.1f%%\n", $lbl, $known, $tot, 100 * $known / max(1, $tot));
    $r = $conn->query("SELECT `$col`, COUNT(*) c FROM `gov_stage_outputs` GROUP BY `$col` ORDER BY c DESC");
    while ($x = $r->fetch_row()) { printf("      %-16s %-5s (%.1f%%)\n", $x[0], $x[1], 100 * $x[1] / max(1, $tot)); }
}
echo "\n";
