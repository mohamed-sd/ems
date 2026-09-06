<?php
/**
 * 2028_05_16_navarch_auth_screens_placement_down.php — عكسُ الموضعَين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُسقِط **ما أضافته الهجرةُ وحدَه**، ويُميَّز بمعرِّفٍ مشتقٍّ من المسارِ
 *   (`placement_id`) وبالهدفَين المسمَّيَين — فلا يمسُّ صفًّا لم يكتبه أحدٌ هنا.
 * ◆ والترتيبُ عكسُ الإنشاء: الموضعُ ثمَّ موضعُ الدليلِ ثمَّ الهدف — لأنَّ
 *   `nav_placements.target_id` مفتاحٌ أجنبيٌّ على `nav_targets`، وحذفُ الأبِ
 *   قبلَ ابنِه يفشل.
 * ◆ **ولا يُمَسُّ إذنٌ ولا قالبٌ**: الشاشتان تبقيان مفتوحتَين بالرابطِ المباشر
 *   كما كانتا قبلَ الهجرة — العكسُ يزيل الرابطَ لا الصلاحية.
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

$WS = 'DEP-08';
$SLUGS   = array('auth_profiles' => 'NT-DEP-08-042', 'auth_grants' => 'NT-DEP-08-043');
$removed = array('nav_workspace_placements' => 0, 'nav_placements' => 0, 'nav_targets' => 0);

foreach ($SLUGS as $slug => $target) {
    $lower   = 'governance/' . $slug;
    $withPhp = $lower . '.php';
    $pid     = 'WP-' . strtoupper(substr(md5($WS . '|' . $lower), 0, 16));

    $st = $conn->prepare("DELETE FROM nav_workspace_placements WHERE placement_id = ?");
    $st->bind_param('s', $pid);
    $st->execute(); $removed['nav_workspace_placements'] += $st->affected_rows; $st->close();

    $st = $conn->prepare("DELETE FROM nav_placements WHERE workspace_id = ? AND route = ? AND target_id = ?");
    $st->bind_param('sss', $WS, $withPhp, $target);
    $st->execute(); $removed['nav_placements'] += $st->affected_rows; $st->close();

    $st = $conn->prepare("DELETE FROM nav_targets WHERE target_id = ?");
    $st->bind_param('s', $target);
    $st->execute(); $removed['nav_targets'] += $st->affected_rows; $st->close();
}

foreach ($removed as $t => $n) { printf("   %-28s صفوفٌ محذوفة: %d\n", $t, $n); }

/* الشاهدُ من المُصيَّرِ: البندان غابا والباقي لم يُمَسّ. */
require_once $ROOT . '/includes/navarch_renderer.php';
$tree = navarch_render($conn, $WS, 15, array('include_shell' => false));
$seen = 0; $total = 0;
foreach ($tree['groups'] as $g) {
    foreach ($g['items'] as $it) {
        $total++;
        if (isset($SLUGS[substr($it['route'], strlen('governance/'))])) { $seen++; }
    }
}
printf("   المُصيَّرُ الآن: %d بندًا · والبندان الظاهران منهما: %d (المستهدف صفر)\n", $total, $seen);
if ($seen > 0) { exit("⛔ العكسُ لم يكتمل.\n"); }

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_16_navarch_auth_screens_placement.php'");
echo "✔ عُكس.\n";
