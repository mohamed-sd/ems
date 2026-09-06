<?php
/**
 * 2028_05_31_perm05_profile_screen_universal_down.php — نزعُ بندِ «الملفِّ الشخصيِّ» المبذور
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ينزع ما بذرته الهجرةُ وحدَه**: الحذفُ بـ`seeded_from` لا بـ`item_ref` —
 *   فالشاشةُ قد يكون لها بندٌ كُتب من شاشةِ البناءِ بقرارِ إنسانٍ بعدَ الهجرة،
 *   وحذفٌ بالمرجعِ يبتلعه ويُسمّي فعلَ الإنسانِ عكسًا لفعلِ الآلة.
 *
 * ⚠ **وبعدَ العكسِ يعود الحجبُ لكلِّ دورٍ**: `main/profile.php` يعود إلى
 *   «صفرٍ من 32»، ورابطُ الشريطِ العلويِّ يعود يصطدم بـ403. فلا يُعكَس هذا
 *   إلّا مع قرارٍ صريحٍ بديل (بندٌ من شاشةِ البناء، أو إعفاءٌ في الحارس).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = @$conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

const SCREEN   = 'main/profile.php';
const SEED_TAG = 'perm05_profile_universal_20260906';

echo "══ عكسُ بندِ «الملفِّ الشخصيِّ» ═══════════════════════════════════════\n";
$before = $one("SELECT COUNT(*) FROM gov_profile_items WHERE seeded_from = '" . SEED_TAG . "'");
printf("   بنودٌ مبذورةٌ بهذا الوسم: %d\n", $before);

$conn->query("DELETE FROM gov_profile_items WHERE seeded_from = '" . SEED_TAG . "'");
printf("   منزوع: %d\n", $conn->affected_rows);

$left = $one("SELECT COUNT(*) FROM gov_profile_items i
               JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
              WHERE i.item_kind = 'screen' AND i.item_ref = '" . SCREEN . "' AND i.allow = 1");
printf("   ◆ بنودٌ باقيةٌ على الشاشةِ في قوالبَ نافذةٍ (بقرارِ إنسانٍ لا بذرٍ): %d\n", $left);
if ($left === 0) {
    echo "   ⚠ الشاشةُ عادت محجوبةً عن كلِّ دور — ورابطُ الشريطِ العلويِّ يصطدم بـ403.\n";
}

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, 0);
echo "\n✔ عُكِس.\n";
