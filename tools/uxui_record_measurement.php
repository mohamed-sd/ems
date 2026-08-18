<?php
/**
 * tools/uxui_record_measurement.php — تسجيلُ قياسِ المتصفحِ الحقيقيِّ في السجل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحلقةُ التي تجعل قياسَ المتصفحِ **قابلًا لإعادةِ الإنتاجِ لا لقطةَ شاشة**:
 *   ① يُشغَّل `tools/uxui_browser_probe.js` في متصفحٍ حقيقيٍّ على الشاشةِ والدقة.
 *   ② يُمرَّر مخرَجُه JSON إلى هذه الأداةِ فتُسجّله بإصدارِ المكوّنِ وقتَه.
 *   ③ بوابةُ الترقيةِ تقرأ السجلَّ وترفض قياسًا أقدمَ من إصدارِ المكوّنِ الحالي.
 *
 * الاستعمال:
 *   php tools/uxui_record_measurement.php --json='{"url":"/ems/…","viewport":{…},…}'
 *   php tools/uxui_record_measurement.php --file=<path.json>
 *   php tools/uxui_record_measurement.php --list [--screen=X]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/s', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}

/** الإصدارُ العاملُ من بصمةِ ملفاتِ المكتبةِ الآن — لا من رقمٍ مكتوب */
function ux_current_version($conn, $ROOT) {
    $FILES = array('assets/css/uxui-tokens.css', 'assets/css/uxui-components.css',
                   'includes/uxui_components.php', 'includes/status_display.php');
    $parts = array();
    foreach ($FILES as $f) {
        $p = $ROOT . '/' . $f;
        if (is_file($p)) { $parts[$f] = hash_file('sha256', $p); }
    }
    ksort($parts);
    $fp = hash('sha256', implode('|', array_map(function ($k, $v) { return $k . ':' . $v; }, array_keys($parts), $parts)));
    $r = $conn->query("SELECT version_tag FROM gov_component_versions WHERE fingerprint='" . $conn->real_escape_string($fp) . "'");
    if ($r && $r->num_rows > 0) { return array($r->fetch_assoc()['version_tag'], $fp, true); }
    return array(null, $fp, false);   /* بصمةٌ غيرُ مسجَّلة = مكوّنٌ تغيّر بعد آخرِ تسجيل */
}

if (isset($args['list'])) {
    $w = !empty($args['screen']) ? " WHERE screen_file='" . $conn->real_escape_string($args['screen']) . "'" : '';
    $r = $conn->query("SELECT screen_file, viewport_w, header_px, header_within_limit, has_h_scroll,
                              primary_buttons, stacked_toolbars, worst_cell_actions, row_height_px,
                              component_version, measured_at
                         FROM gov_visual_measurements {$w} ORDER BY screen_file, viewport_w, measured_at DESC");
    echo "════ قياساتُ المتصفحِ المسجَّلة ════\n";
    while ($r && ($x = $r->fetch_assoc())) {
        printf("  %-38s %5dpx · ترويسة=%3s%s · تمرير=%s · رئيسي=%s · أشرطة=%s · خلية=%s · صف=%s · %s · %s\n",
            $x['screen_file'], $x['viewport_w'], $x['header_px'],
            $x['header_within_limit'] ? '✔' : '✗',
            $x['has_h_scroll'] ? '✗' : '✔',
            $x['primary_buttons'], $x['stacked_toolbars'], $x['worst_cell_actions'],
            $x['row_height_px'], $x['component_version'], mb_substr($x['measured_at'], 0, 16));
    }
    exit(0);
}

$raw = null;
if (!empty($args['file']) && is_file($args['file'])) { $raw = file_get_contents($args['file']); }
elseif (!empty($args['json'])) { $raw = $args['json']; }
if ($raw === null) { exit("حدِّد --json= أو --file= أو --list\n"); }

$data = json_decode($raw, true);
if (!is_array($data)) { exit("JSON غيرُ صالح\n"); }
$items = isset($data[0]) ? $data : array($data);   /* يقبل قياسًا واحدًا أو مصفوفة */

list($ver, $fp, $known) = ux_current_version($conn, $ROOT);
if (!$known) {
    echo "⚠ بصمةُ المكوّناتِ غيرُ مسجَّلةٍ ({$fp}) — شغِّلْ هجرةَ 2027_07_08 لتسجيلِ الإصدارِ أولًا\n";
    exit(2);
}

$ins = $conn->prepare("INSERT INTO gov_visual_measurements
    (screen_file, viewport_w, viewport_h, header_px, header_within_limit, has_h_scroll,
     primary_buttons, stacked_toolbars, worst_cell_actions, row_height_px, component_version)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)");
$n = 0;
foreach ($items as $d) {
    $screen = ltrim(str_replace('/ems/', '', (string) ($d['url'] ?? $d['screen'] ?? '')), '/');
    if ($screen === '') { continue; }
    $vw = (int) ($d['viewport']['w'] ?? $d['vw'] ?? 0);
    $vh = (int) ($d['viewport']['h'] ?? $d['vh'] ?? 0);
    $hpx = isset($d['header']['height']) ? (int) $d['header']['height'] : (isset($d['header']['h']) ? (int) $d['header']['h'] : null);
    $within = isset($d['headerWithinLimit']) ? (int) (bool) $d['headerWithinLimit']
            : (isset($d['withinLimit']) ? (int) (bool) $d['withinLimit'] : ($hpx !== null ? (int) ($hpx <= 96) : null));
    $hs = isset($d['hasHorizontalPageScroll']) ? (int) (bool) $d['hasHorizontalPageScroll']
        : (isset($d['hScroll']) ? (int) (bool) $d['hScroll'] : null);
    $prim = isset($d['visiblePrimaryButtons']) ? (int) $d['visiblePrimaryButtons'] : (isset($d['prim']) ? (int) $d['prim'] : null);
    $bars = isset($d['worstStack']) ? (int) $d['worstStack'] : (isset($d['visibleToolbars']) ? (int) $d['visibleToolbars'] : null);
    $cell = isset($d['worstCellActions']) ? (int) $d['worstCellActions'] : (isset($d['worstCell']) ? (int) $d['worstCell'] : null);
    $row = isset($d['measuredRowHeight']) ? (int) $d['measuredRowHeight'] : (isset($d['rowH']) ? (int) $d['rowH'] : null);
    $ins->bind_param('siiiiiiiiis', $screen, $vw, $vh, $hpx, $within, $hs, $prim, $bars, $cell, $row, $ver);
    if ($ins->execute()) { $n++; echo "  ✔ {$screen} @ {$vw}px · ترويسة={$hpx} · أشرطة={$bars} · رئيسي={$prim}\n"; }
    else { echo "  ✗ {$screen}: {$ins->error}\n"; }
}
echo "سُجِّل: {$n} قياسًا بإصدارِ المكوّن {$ver}\n";
