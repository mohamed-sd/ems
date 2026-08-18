<?php
/**
 * 2027_06_06_sidebar_empty_groups_cleanup.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تعطيلُ المجموعاتِ الفارغة — بنصِّ GOV-24: «والمجموعةُ التي فرغت لا تُعرض»
 * ───────────────────────────────────────────────────────────────────────────
 * 233 مجموعةً نشطةً بلا شاشةٍ واحدة. لا يراها مستخدمٌ (فارغةٌ أصلًا) لكنها
 * تُحسب مرحلةً بلا مخرَجٍ في مصفوفةِ التحقُّق فتُنقص نسبةَ الإثباتِ بلا سببٍ حقيقيّ.
 *
 * ◆ ولم أمسّ المجموعاتِ الموروثةَ بلا `stage_no` (306 مجموعة): قِستُ روابطَها
 *   فوجدتُ **125 رابطًا فريدًا لا بديلَ له في مجموعةٍ مرحَّلة** — منها «طلب مالي
 *   جديد» و«تسجيل الوحدات» و«الموظفون» و«المعدات». فتعطيلُها يُخفي شاشاتِ عملٍ
 *   حقيقيةً عن أصحابِها. تبقى كما هي، وتُقاس بترتيبِها الموروث.
 *
 * ولا يُحذف صفٌّ: التعطيلُ `is_active=0` والصفُّ باقٍ شاهدًا.
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

echo "\n▐ ① قياسٌ قبلَ المسّ\n";
printf("   · مجموعاتٌ نشطة        : %s\n", $one("SELECT COUNT(*) FROM `link_groups` WHERE `is_active`=1"));
printf("   · منها فارغةٌ بلا شاشة : %s\n", $one(
    "SELECT COUNT(*) FROM `link_groups` g WHERE g.`is_active`=1
      AND NOT EXISTS (SELECT 1 FROM `nav_items` n WHERE n.`group_id`=g.`id` AND n.`active`=1)"));

echo "\n▐ ② شبكةُ أمان: هل تُخفي أيَّ رابطٍ نشط؟\n";
$risk = (int) $one(
    "SELECT COUNT(*) FROM `nav_items` n
       JOIN `link_groups` g ON g.`id` = n.`group_id`
      WHERE n.`active`=1 AND g.`is_active`=1
        AND NOT EXISTS (SELECT 1 FROM `nav_items` n2 WHERE n2.`group_id`=g.`id` AND n2.`active`=1)");
printf("   · روابطُ نشطةٌ داخلَ مجموعةٍ ستُعطَّل: %d   [يجب أن يكون 0]\n", $risk);
if ($risk !== 0) { exit("   ✗ يوقف — التعطيلُ سيُخفي روابطَ نشطة\n"); }

echo "\n▐ ③ التعطيل — بنصِّ GOV-24 ولا حذف\n";
$conn->query(
    "UPDATE `link_groups` g
        SET g.`is_active` = 0
      WHERE g.`is_active` = 1
        AND NOT EXISTS (SELECT 1 FROM `nav_items` n WHERE n.`group_id`=g.`id` AND n.`active`=1)");
printf("   ✔ عُطّلت %d مجموعةً فارغة — والصفوفُ باقيةٌ شاهدةً\n", $conn->affected_rows);

echo "\n▐ ④ بعدَ التعطيل\n";
printf("   · مجموعاتٌ نشطة        : %s\n", $one("SELECT COUNT(*) FROM `link_groups` WHERE `is_active`=1"));
printf("   · فارغةٌ باقية         : %s   [المتوقَّع 0]\n", $one(
    "SELECT COUNT(*) FROM `link_groups` g WHERE g.`is_active`=1
      AND NOT EXISTS (SELECT 1 FROM `nav_items` n WHERE n.`group_id`=g.`id` AND n.`active`=1)"));
printf("   · روابطُ تنقّلٍ نشطةٌ ظاهرة: %s\n", $one(
    "SELECT COUNT(*) FROM `nav_items` n JOIN `link_groups` g ON g.`id`=n.`group_id`
      WHERE n.`active`=1 AND g.`is_active`=1"));

// وسجلُّ المراحلِ يُنظَّف مما لم يعُد له مجموعةٌ نشطة
$conn->query(
    "DELETE o FROM `gov_stage_outputs` o
      WHERE NOT EXISTS (SELECT 1 FROM `link_groups` g
                         WHERE g.`owner_role_id`=o.`role_id` AND g.`stage_no`=o.`stage_no`
                           AND g.`is_active`=1)");
printf("   ✔ نُظّف %d صفًّا من سجلِّ المراحلِ بلا مجموعةٍ نشطة\n", $conn->affected_rows);
printf("   · مراحلُ السجل: %s\n", $one("SELECT COUNT(*) FROM `gov_stage_outputs`"));
echo "\n";
