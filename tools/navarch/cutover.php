<?php
/**
 * tools/navarch/cutover.php — قياسُ القلبِ المتدرِّجِ على **التصييرِ الحيّ**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§30 · [[render-not-store-rule]]**: «لا يُقاس سطحٌ بما في جدولِه بل بما
 *   يظهر في الجلسة». فهذه الأداةُ **لا تسأل المخزنَ** — تُشغِّل
 *   `tools/lib/render_role_cli.php` **عمليّةً مستقلّةً لكلِّ دور** فلا يعبر
 *   مخبأٌ ساكنٌ من دورٍ لآخرَ ولا من قبلِ القلبِ لِبعدِه.
 *
 * ◆ **وعدَّادا السقوطِ من المُصيِّرِ الإنتاجيِّ نفسِه** (‏`ems_nav_fallback_counters`)
 *   لا من الظلّ — فالصفرُ هنا «لم يقع» مقيسًا لا «حقلٌ لم يُزَد»
 *   [[measure-token-must-exist]].
 *
 * ◆ **والدورُ يُنسَب إلى مساحتِه** بـ`navarch_role_workspace` — `PRIMARY` ثمَّ
 *   `SECONDARY` — فيُقرأ أثرُ القلبِ **مساحةً مساحةً** لا جملةً واحدة.
 *
 * التشغيل:
 *   php tools/navarch/cutover.php --save=before      ← لقطةٌ قبلَ القلب
 *   php tools/navarch/cutover.php --save=after       ← لقطةٌ بعدَه
 *   php tools/navarch/cutover.php --diff             ← الفرقُ دورًا دورًا
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
require_once $ROOT . '/includes/navarch_renderer.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$save = ''; $diff = in_array('--diff', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--save=') === 0) { $save = substr($a, 7); } }
$DIR = $ROOT . '/docs/REPAIR01_20260823/navarch';

/* ⛔ **والأدوارُ المقيسةُ هي التي لها روابطُ حيّة** — فدورٌ بصفرِ رابطٍ
   يُقاس صفرًا قبلَ القلبِ وبعدَه، ووجودُه في المقامِ يُميِّع الفرق. */
$roles = array();
$r = $conn->query("SELECT r.id, r.name,
                          (SELECT COUNT(*) FROM nav_items n WHERE n.role_id = r.id AND n.active = 1) links
                     FROM roles r ORDER BY r.id");
while ($x = $r->fetch_assoc()) {
    if ((int) $x['links'] === 0) { continue; }
    $roles[] = array('id' => (int) $x['id'], 'name' => $x['name'],
                     'ws' => navarch_role_workspace($conn, (int) $x['id']));
}

$flag = trim((string) ems_env('EMS_NAV_ARCH', 'off'));
echo "══ التصييرُ الحيُّ · EMS_NAV_ARCH = {$flag} · " . count($roles) . " دورًا ══\n\n";

$snap = array('flag' => $flag, 'at' => date('c'), 'roles' => array());
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$cli = $ROOT . '/tools/lib/render_role_cli.php';
$totLinks = 0; $totFb = 0;

foreach ($roles as $ro) {
    $out = array(); $rc = 0;
    @exec('"' . $php . '" ' . escapeshellarg($cli) . ' ' . $ro['id'] . ' 2>&1', $out, $rc);
    $j = json_decode(implode("\n", $out), true);
    $n = ($j && isset($j['positions'])) ? count($j['positions']) : -1;
    /* السقوطاتُ الخمسُ — المجموعُ لا التفصيل، والتفصيلُ محفوظٌ في اللقطة */
    $fb = 0; $fbd = array();
    if ($j && isset($j['fallbacks']) && is_array($j['fallbacks'])) {
        foreach ($j['fallbacks'] as $k => $v) { if (is_int($v)) { $fb += $v; $fbd[$k] = $v; } }
    }
    $gr = ($j && isset($j['shells'])) ? count($j['shells']) : -1;
    $snap['roles'][$ro['id']] = array('ws' => $ro['ws'], 'name' => $ro['name'],
                                      'links' => $n, 'groups' => $gr,
                                      'fallbacks' => $fb, 'fb_detail' => $fbd);
    $totLinks += max(0, $n); $totFb += $fb;
    printf("  دور %-3d %-12s روابط %-5d رؤوس %-4d سقوط %-5d %s\n",
           $ro['id'], $ro['ws'] ?: '—', $n, $gr, $fb, $ro['name']);
}
printf("\n  الإجمالي: روابط %d · سقوطٌ حيٌّ %d\n", $totLinks, $totFb);
$snap['total_links'] = $totLinks; $snap['total_fallbacks'] = $totFb;

if ($save !== '') {
    $f = $DIR . '/NAV_ARCH_RENDER_' . strtoupper($save) . '.json';
    file_put_contents($f, json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "  ⇒ {$f}\n";
}

if ($diff) {
    $b = @json_decode(@file_get_contents($DIR . '/NAV_ARCH_RENDER_BEFORE.json'), true);
    if (!$b) { exit("⛔ لا لقطةَ «قبل» — شغِّل --save=before أوّلًا\n"); }
    echo "\n══ الفرقُ عن «قبل» (‏المفتاح {$b['flag']}) ══\n";
    $byWs = array();
    foreach ($snap['roles'] as $rid => $a) {
        $p = isset($b['roles'][$rid]) ? $b['roles'][$rid] : null;
        if (!$p) { continue; }
        $d = $a['links'] - $p['links'];
        $ws = $a['ws'] ?: '—';
        if (!isset($byWs[$ws])) { $byWs[$ws] = array('before' => 0, 'after' => 0, 'roles' => 0, 'fb' => 0); }
        $byWs[$ws]['before'] += $p['links']; $byWs[$ws]['after'] += $a['links'];
        $byWs[$ws]['roles']++; $byWs[$ws]['fb'] += $a['fallbacks'];
        if ($d !== 0) {
            printf("  دور %-3d %-12s %4d ⇒ %-4d (%+d) %s\n", $rid, $ws, $p['links'], $a['links'], $d, $a['name']);
        }
    }
    echo "\n  ── بالمساحة ──\n";
    ksort($byWs);
    foreach ($byWs as $ws => $v) {
        printf("  %-12s أدوار %-3d %4d ⇒ %-4d (%+d) · سقوطٌ حيٌّ %d %s\n",
               $ws, $v['roles'], $v['before'], $v['after'], $v['after'] - $v['before'],
               $v['fb'], $v['fb'] === 0 ? '✔' : '✖');
    }
    printf("\n  الإجمالي: %d ⇒ %d (%+d) · سقوطٌ حيٌّ %d ⇒ %d\n",
           $b['total_links'], $snap['total_links'], $snap['total_links'] - $b['total_links'],
           $b['total_fallbacks'], $snap['total_fallbacks']);
}
exit(0);
