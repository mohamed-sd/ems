<?php
/**
 * 2028_05_11_perm01_seed_source_review.php — مراجعةُ مصدرِ بذرٍ مضاف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **البندُ الثالثُ نصُّه «مبذورٌ من أكثرَ من مصدرٍ بلا مراجعة»** — وشرطُ
 *   «بلا مراجعة» لم يكن مُنفَّذًا: كان المقياسُ يعُدُّ **كلَّ** قالبٍ تعدَّدت
 *   مصادرُه، فيستوي المخلوطُ صامتًا والمُضافُ عن قصدٍ بدليل. وهذه الهجرةُ
 *   تبني نصفَ الشرطِ الغائبَ — **سجلَّ المراجعة** — لا لتُخفِيَ رقمًا بل
 *   ليقيسَ المقياسُ ما نصَّ عليه الأمرُ حرفًا.
 *
 * ⛔ **والمراجعةُ واقعةٌ لا وسمٌ**: لكلِّ صفٍّ **سببٌ** و**دليلٌ** يُراجَع —
 *   واسمُ الهجرةِ التي أنشأت البذرَ. وقالبٌ خُلط مصدرُه بلا صفٍّ هنا **يبقى
 *   راسبًا**: السجلُّ يُضيِّق المقياسَ على المُراجَعِ وحدَه ولا يُلغيه.
 *
 * ◆ **والحبّةُ (قالبٌ × مصدر)** لا القالبُ وحدَه: مراجعةُ مصدرٍ لا تُجيز
 *   مصدرًا آخرَ يُضاف غدًا إلى القالبِ نفسِه.
 *
 * ◆ **والمراجَعُ هنا واقعةٌ واحدة**: `link_closure:equipments` — أُضيف كرتُ
 *   المعدةِ لتسعةِ قوالبَ تملك القائمةَ التي تقود إليه بالنقر، تمهيدًا
 *   لحراستِه بعدَ أن كان بلا حارس. والاشتقاقُ من شيفرةِ الشاشةِ نفسِها
 *   (سطرُ الرابطِ في `Equipments/equipments.php`) لا من رأيٍ فيها.
 *
 * التشغيل: php database/migrations/2028_05_11_perm01_seed_source_review.php
 * العكس:   php database/migrations/2028_05_11_perm01_seed_source_review_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

echo "══ ① سجلُّ المراجعة ══════════════════════════════════════════════════\n";
$ok = $conn->query("CREATE TABLE IF NOT EXISTS `perm01_seed_source_review` (
    `review_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `profile_id` INT UNSIGNED NOT NULL,
    `seeded_from` VARCHAR(60) NOT NULL COMMENT 'مصدر البذر المراجع كما هو في gov_profile_items',
    `reason` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'لماذا اضيف هذا المصدر الى هذا القالب',
    `evidence` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'الهجرة او الاداة التي انشات البذر',
    `reviewed_by` INT NOT NULL DEFAULT 0 COMMENT 'صفر يعني هجرة',
    `reviewed_at` DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`review_id`),
    UNIQUE KEY `uq_profile_source` (`profile_id`, `seeded_from`),
    KEY `ix_source` (`seeded_from`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='PERM-01 §10-3 — مراجعة مصدر بذر مضاف الى قالب نافذ'");
echo '   perm01_seed_source_review: ' . ($ok ? 'قائم' : $conn->error) . "\n";

echo "\n══ ② قيدُ الواقعةِ المراجَعة ═══════════════════════════════════════════\n";
$conn->query("INSERT IGNORE INTO perm01_seed_source_review
                (profile_id, seeded_from, reason, evidence, reviewed_by)
              SELECT DISTINCT i.profile_id, 'link_closure:equipments',
                     'اغلاق وصول: كرت المعدة يبلغ بالنقر من قائمة المعدات ولا رابط له في القائمة الجانبية — بذر ليحرس بلا كسر عمل',
                     '2028_05_10_perm01_screen_identity.php', 0
                FROM gov_profile_items i
                JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
               WHERE i.seeded_from = 'link_closure:equipments'");
printf("   صفوفُ مراجعةٍ قُيِّدت: %d\n", $conn->affected_rows);

echo "\n══ ③ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$unreviewed = $one("SELECT COUNT(*) FROM (
        SELECT i.profile_id FROM gov_profile_items i
          JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
         WHERE NOT EXISTS(SELECT 1 FROM perm01_seed_source_review r
                           WHERE r.profile_id = i.profile_id AND r.seeded_from = i.seeded_from)
         GROUP BY i.profile_id HAVING COUNT(DISTINCT i.seeded_from) > 1) z");
printf("   ★ قالبٌ نافذٌ متعدِّدُ المصادرِ **بلا مراجعة**: %d %s\n", $unreviewed, $unreviewed === 0 ? '' : '✘');
if ($unreviewed !== 0) { $good = false; }

$reviewed = $one("SELECT COUNT(*) FROM perm01_seed_source_review");
$seeds = $one("SELECT COUNT(DISTINCT profile_id) FROM gov_profile_items
                WHERE seeded_from = 'link_closure:equipments'");
printf("   ★ صفوفُ المراجعةِ تطابق القوالبَ المبذورة: %d من %d %s\n",
    $reviewed, $seeds, $reviewed === $seeds ? '' : '✘');
if ($reviewed !== $seeds) { $good = false; }

$noReason = $one("SELECT COUNT(*) FROM perm01_seed_source_review WHERE reason = '' OR evidence = ''");
printf("   ★ ضابطٌ سالب — مراجعةٌ بلا سببٍ أو دليل: %d %s\n", $noReason, $noReason === 0 ? '' : '✘');
if ($noReason !== 0) { $good = false; }

/* ⛔ ضابطٌ سالبٌ ثانٍ: السجلُّ لا يُجيز مصدرًا لم يُقيَّد. */
$ghost = $one("SELECT COUNT(*) FROM perm01_seed_source_review r
                WHERE NOT EXISTS(SELECT 1 FROM gov_profile_items i
                                  WHERE i.profile_id = r.profile_id AND i.seeded_from = r.seeded_from)");
printf("   ★ ضابطٌ سالب — مراجعةٌ لمصدرٍ لا وجودَ له: %d %s\n", $ghost, $ghost === 0 ? '' : '✘');
if ($ghost !== 0) { $good = false; }

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — وشرط «بلا مراجعة» صار منفذا لا مهملا.\n" : "سقط شاهد\n");
