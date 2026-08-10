<?php
/**
 * tools/nav_flag_flip_diff.php — فرقُ قلبِ دورٍ إلى المصدر الموحَّد، قبلَ قلبِه
 * ═══════════════════════════════════════════════════════════════════════════
 * قلبُ دورٍ في `EMS_NAV_UNIFIED_ROLES` **يُبدّل قائمتَه كلَّها** لا سطرًا فيها:
 * قبلَ القلب تُقرأ من `modules`/`link_groups`، وبعدَه من `nav_items`. فالسؤالُ
 * الحاكمُ الوحيد: **أيُّ مسارٍ يراه اليومَ ولا يراه غدًا؟**
 *
 * الفاحصُ ينادي دوالَّ التصييرِ نفسَها في الحالتين (لا يعيد كتابةَ استعلامٍ
 * يشبهها)، ويطبع ثلاث مجموعات:
 *   ✘ يُفقد   — مسارٌ حيٌّ اليومَ وغائبٌ عن `nav_items` ⇐ **مانعُ قلب**
 *   ＋ يُكتسب — مسارٌ يظهر بعد القلب (بنيةُ NAV-09 المولَّدة)
 *   ＝ يبقى   — مشتركٌ بين الحالتين
 *
 * التشغيل:  php tools/nav_flag_flip_diff.php 27 28 29 30
 * الخروج:   0 إن لم يُفقد مسارٌ لأيِّ دور · 1 إن فُقد شيء (فلا تقلب).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
define('EMS_CLI', true);
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/dynamic_nav.php';
require_once dirname(__DIR__) . '/includes/unified_nav.php';
require_once dirname(__DIR__) . '/includes/finreq_badges.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$argvRoles = array_slice($argv, 1);
if (!$argvRoles) { fwrite(STDERR, "الاستعمال: php tools/nav_flag_flip_diff.php <دور> [دور…]\n"); exit(1); }

$norm = function ($route) { return strtolower(ltrim(preg_replace('/[#?].*$/', '', trim((string) $route)), './')); };
$lost = 0;

foreach ($argvRoles as $arg) {
    $roleId = intval($arg);
    if ($roleId <= 0) { continue; }
    $_SESSION['user'] = array('id' => 1, 'role' => (string) $roleId, 'company_id' => 4);

    $rn = '';
    $r = mysqli_query($conn, "SELECT name FROM roles WHERE id = $roleId");
    if ($r && ($x = mysqli_fetch_assoc($r))) { $rn = $x['name']; }

    /* ── قبلَ القلب: المسارُ القديم كما يبنيه insidebar.php حرفيًّا ── */
    $before = array();
    foreach (getDynamicNavLinks($conn, $roleId) as $l) { $before[$norm($l['code'])] = (string) $l['name']; }
    // العُقَدُ الاصطناعيةُ تُلحق في insidebar بعد الشجرة — وهي **مستقلةٌ عن العلَم**
    // (تُطبع في المسار القديم وحدَه) فتدخل الفرقَ كما هي.
    foreach (ems_finreq_nav_links($conn) as $code => $meta) { $before[$norm($code)] = (string) $meta['label']; }
    if (function_exists('ems_finance_nav_links')) {
        foreach (ems_finance_nav_links($conn) as $code => $meta) { $before[$norm($code)] = (string) $meta['label']; }
    }

    /* ── بعدَ القلب: المصدرُ الموحَّد ── */
    $after = array();
    foreach (getUnifiedNavItems($conn, $roleId) as $it) { $after[$norm($it['route'])] = (string) $it['label_ar']; }

    $gone = array_diff_key($before, $after);
    $new  = array_diff_key($after, $before);
    $same = array_intersect_key($before, $after);

    printf("\n══ الدور %-3s %s\n", $roleId, $rn);
    printf("   قبل=%d  بعد=%d  ⇒  يبقى=%d · يُكتسب=%d · يُفقد=%d\n",
        count($before), count($after), count($same), count($new), count($gone));

    if ($gone) {
        $lost += count($gone);
        echo "   ✘ يُفقد:\n";
        foreach ($gone as $p => $n) { echo "      - {$n}  ({$p})\n"; }
    } else {
        echo "   ✔ لا مسارَ يُفقد\n";
    }
    if ($new) {
        echo "   ＋ يُكتسب (" . count($new) . "):\n";
        $i = 0;
        foreach ($new as $p => $n) { if ($i++ < 8) { echo "      + {$n}  ({$p})\n"; } }
        if (count($new) > 8) { echo "      … و" . (count($new) - 8) . " غيرها\n"; }
    }
}

echo "\n" . str_repeat('─', 78) . "\n";
echo ($lost === 0 ? "✔ القلبُ آمن — صفرُ مسارٍ يُفقد\n" : "✘ القلبُ يُفقد $lost مسارًا — لا تقلب قبل سدِّها\n");
exit($lost === 0 ? 0 : 1);
