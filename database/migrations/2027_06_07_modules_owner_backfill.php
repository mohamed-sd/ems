<?php
/**
 * 2027_06_07_modules_owner_backfill.php
 * ═══════════════════════════════════════════════════════════════════════════
 * ردمُ «الدورِ المسؤول» — بالاشتقاقِ اليقينيِّ وبالنصِّ حين يكون مشترَكًا
 * ───────────────────────────────────────────────────────────────────────────
 * 17 وحدةً مستعمَلةً في التنقّلِ بلا `owner_role_id` تُنقص عمودَ «الدورِ المسؤول»
 * في مصفوفةِ التحقُّق (152 صفًّا). وقياسُ الوضعِ يفرز حالتين لا حالةً واحدة:
 *
 *   ① **6 وحداتٍ تظهر في سايدبارِ دورٍ واحدٍ فقط** — فمالكُها ذلك الدورُ يقينًا.
 *      اشتقاقٌ من الحيِّ لا تخمين.
 *
 *   ② **11 وحدةً مشترَكةٌ حقًّا** — `my_tasks` و`chats` في 26 دورًا،
 *      و`my_workspace` و`ticket_contextual_open` في 24. هذه مساحاتٌ شخصيةٌ
 *      لا تملكها إدارةٌ بعينِها، **وإسنادُها لدورٍ واحدٍ كذبٌ**. تأخذ بدلًا من
 *      ذلك **إدارتَها النصيةَ** من `nav09_file_map.owner_dept` — وهو المصدرُ
 *      الحاكمُ لملكيةِ الملفاتِ من حملةِ NAV-09.
 *
 * ◆ ولا يُسنَد دورٌ لوحدةٍ مشترَكةٍ لمجردِ رفعِ نسبةٍ في مصفوفة.
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
$hasCol = function (string $t, string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

echo "\n▐ ① عمودُ الإدارةِ النصيةِ للمشترَك\n";
if (!$hasCol('modules', 'owner_dept_note')) {
    if ($conn->query("ALTER TABLE `modules`
            ADD COLUMN `owner_dept_note` VARCHAR(120) NULL
                COMMENT 'الإدارةُ المالكةُ نصًّا (nav09_file_map.owner_dept) — للوحداتِ المشترَكةِ بين أدوارٍ كثيرة'")) {
        echo "   ✔ modules.owner_dept_note\n";
    } else { echo "   ✗ " . $conn->error . "\n"; }
} else { echo "   · موجودٌ سلفًا\n"; }

echo "\n▐ ② وصلُ روابطِ التنقّلِ الباقيةِ بوحداتِها\n";
$conn->query(
    "UPDATE `nav_items` n
       JOIN `modules` m ON m.`code` = SUBSTRING_INDEX(n.`route`, '#', 1)
        SET n.`module_id` = m.`id`
      WHERE n.`active` = 1 AND n.`module_id` IS NULL");
printf("   ✔ وُصل %d رابطًا\n", $conn->affected_rows);

echo "\n▐ ③ الاشتقاقُ اليقينيّ — وحدةٌ في سايدبارِ دورٍ واحد\n";
$conn->query(
    "UPDATE `modules` m
       JOIN (
            SELECT n.`module_id` AS mid,
                   COUNT(DISTINCT g.`owner_role_id`) AS roles,
                   MIN(g.`owner_role_id`) AS only_role
              FROM `nav_items` n
              JOIN `link_groups` g ON g.`id` = n.`group_id` AND g.`is_active` = 1
             WHERE n.`active` = 1 AND n.`module_id` IS NOT NULL
             GROUP BY n.`module_id`
            HAVING roles = 1
       ) d ON d.mid = m.`id`
        SET m.`owner_role_id` = d.only_role
      WHERE m.`owner_role_id` IS NULL");
printf("   ✔ أُسند مالكٌ يقينيٌّ لـ%d وحدة\n", $conn->affected_rows);

echo "\n▐ ④ المشترَكةُ حقًّا — إدارتُها نصًّا لا دورًا مخترَعًا\n";
$conn->query(
    "UPDATE `modules` m
       JOIN `nav09_file_map` f ON f.`canonical_file` = SUBSTRING_INDEX(m.`code`, '/', -1)
        SET m.`owner_dept_note` = f.`owner_dept`
      WHERE m.`owner_role_id` IS NULL
        AND f.`owner_dept` IS NOT NULL AND f.`owner_dept` <> ''");
printf("   ✔ %d وحدةً بإدارةٍ نصيةٍ من nav09_file_map\n", $conn->affected_rows);

echo "\n▐ ⑤ التحقُّق\n";
$used = (int) $one("SELECT COUNT(DISTINCT m.`id`) FROM `modules` m
    JOIN `nav_items` n ON n.`module_id`=m.`id` AND n.`active`=1
    JOIN `link_groups` g ON g.`id`=n.`group_id` AND g.`is_active`=1");
$noOwner = (int) $one("SELECT COUNT(DISTINCT m.`id`) FROM `modules` m
    JOIN `nav_items` n ON n.`module_id`=m.`id` AND n.`active`=1
    JOIN `link_groups` g ON g.`id`=n.`group_id` AND g.`is_active`=1
    WHERE m.`owner_role_id` IS NULL AND (m.`owner_dept_note` IS NULL OR m.`owner_dept_note`='')");
printf("   · وحداتٌ مستعمَلة        : %d\n", $used);
printf("   · بلا مالكٍ ولا إدارةٍ نصية: %d\n", $noOwner);
printf("   · روابطُ تنقّلٍ بلا وحدة  : %s   [المتوقَّع 0]\n", $one(
    "SELECT COUNT(*) FROM `nav_items` n JOIN `link_groups` g ON g.`id`=n.`group_id` AND g.`is_active`=1
      WHERE n.`active`=1 AND n.`module_id` IS NULL"));
$r = $conn->query("SELECT m.`code`, m.`owner_dept_note` FROM `modules` m
    WHERE m.`owner_role_id` IS NULL AND m.`owner_dept_note` IS NOT NULL LIMIT 12");
echo "   · المشترَكةُ بإدارتِها النصية:\n";
while ($x = $r->fetch_row()) { printf("      %-44s %s\n", $x[0], $x[1]); }
echo "\n";
