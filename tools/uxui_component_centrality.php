<?php
/**
 * tools/uxui_component_centrality.php — بوابةُ الترقيةِ البند ٦: مركزيةُ المكوّنات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ ف١٦-٢: «‏100٪ من المكتبةِ المركزيةِ · **صفرُ نمطٍ محليٍّ في الشاشة**».
 *   ونصُّ ف١٢: «بعدَ اعتمادِ الرموزِ يُمنع أيُّ لونٍ مثبَّتٍ أو قياسٍ محليٍّ أو
 *   نمطٍ موضعيّ — والمنعُ ببوابةٍ ترسِّب البناءَ لا بتوصية».
 *
 * ◆ ما يُقاس في **ملفِّ الشاشةِ نفسِه** (لا في المكتبةِ المركزية):
 *   ① كتلةُ `<style>` داخلَ الشاشة — نمطٌ محليٌّ صريح
 *   ② سمةُ `style="…"` سطريةٌ في الوسوم
 *   ③ لونٌ مثبَّتٌ (hex أو rgb) خارجَ الرموز
 *   ④ قياسٌ بالبكسل خارجَ سلّمِ المسافاتِ المعتمَد
 *
 * ◆ والنطاقُ الافتراضيُّ **الشاشاتُ الذهبيةُ العشر** — فالبوابةُ بوابةُ ترقيةِ
 *   نمطٍ لا كنسٌ عام. و`--all` يوسّعه للنطاقِ المرحَّلِ كلِّه للاستطلاع.
 *
 * التشغيل:
 *   php tools/uxui_component_centrality.php            العشرُ الذهبية
 *   php tools/uxui_component_centrality.php --enforce  رمزُ خروجٍ 1 عند مخالفة
 *   php tools/uxui_component_centrality.php --all      مسحٌ استطلاعيٌّ أوسع
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
$ENFORCE = in_array('--enforce', $argv, true);
$ALL = in_array('--all', $argv, true);

/* سلّمُ المسافاتِ المعتمَد (ف١٢-١) + قياساتٌ بنيويةٌ مشروعة */
$SPACING = array(0, 1, 2, 4, 6, 8, 12, 16, 24, 32, 36, 40, 44, 48, 64, 96, 100);

$files = array();
if ($ALL) {
    $q = $conn->query("SELECT route FROM nav_canonical WHERE status = 'APPROVED'");
    while ($q && ($x = $q->fetch_assoc())) { if (is_file($ROOT . '/' . $x['route'])) { $files[] = $x['route']; } }
} else {
    $q = $conn->query("SELECT screen_file FROM gov_golden_approvals ORDER BY id");
    while ($q && ($x = $q->fetch_assoc())) { if (is_file($ROOT . '/' . $x['screen_file'])) { $files[] = $x['screen_file']; } }
}
if (!$files) { exit("لا ملفاتٍ للفحص\n"); }

/** يجرّد التعليقاتِ فلا يُحاسَب شرحٌ على ما يشرحه */
function cc_strip($src) {
    $src = preg_replace('~/\*.*?\*/~su', '', $src);
    $src = preg_replace('~^\s*//.*$~mu', '', $src);
    $src = preg_replace('~^\s*\*.*$~mu', '', $src);
    return $src;
}

$rows = array(); $totalViol = 0;
foreach ($files as $rel) {
    $src = cc_strip((string) @file_get_contents($ROOT . '/' . $rel));
    if ($src === '') { continue; }

    /* ① كتلُ style المحلية */
    $styleBlocks = preg_match_all('~<style\b[^>]*>~iu', $src);
    /* ② سماتُ style السطرية — و**الاستثناءُ المصرَّح** يُعَدُّ ولا يُرسِّب:
       قيمةٌ تُحسب من بياناتٍ حيةٍ لحظةَ التصيير (عرضُ شريطِ تقدمٍ بنسبةٍ مقيسة)
       لا يمكن أن تعيش في ورقةِ أنماطٍ ثابتة. والمستودعُ يعلّمها سلفًا بـ
       `data-allow-style` — فهي «مُعتمَدةٌ بسببٍ مكتوب» لا مخالفةٌ مسكوتٌ عنها،
       وتُعرض في التقريرِ بعددِها فلا تختفي خلف رقمٍ أخضر. */
    /* ◆ ولا يُطابَق الوسمُ بـ`[^>]*`: وسمُ PHP بداخله `>` فيبتر المطابقةَ باكرًا
         (وقع فعلًا). فالمطابقةُ على السطرِ بنافذةٍ محدودةٍ بعد العلامة. */
    $allowed = preg_match_all('~data-allow-style[^\n]{0,240}?\sstyle\s*=~iu', $src);
    $inlineStyles = preg_match_all('~\sstyle\s*=\s*["\']~iu', $src) - $allowed;
    if ($inlineStyles < 0) { $inlineStyles = 0; }
    /* ③ ألوانٌ مثبَّتة */
    $hex = array();
    if (preg_match_all('~#[0-9a-fA-F]{3,8}\b~u', $src, $m3)) { $hex = array_unique($m3[0]); }
    $rgb = preg_match_all('~\brgba?\s*\(~iu', $src);
    /* ④ قياساتٌ خارجَ السلّم */
    $offScale = array();
    if (preg_match_all('~(?<![\w-])(\d{1,4})px~u', $src, $m4)) {
        foreach (array_unique($m4[1]) as $v) {
            if (!in_array((int) $v, $SPACING, true)) { $offScale[] = $v . 'px'; }
        }
    }
    $v = $styleBlocks + $inlineStyles + count($hex) + $rgb + count($offScale);
    $totalViol += $v;
    $rows[] = array('file' => $rel, 'style_blocks' => $styleBlocks, 'inline' => $inlineStyles, 'allowed' => $allowed,
                    'hex' => count($hex), 'rgb' => $rgb, 'offscale' => count($offScale),
                    'hex_s' => array_slice(array_values($hex), 0, 3),
                    'off_s' => array_slice($offScale, 0, 4), 'total' => $v);
}

usort($rows, function ($a, $b) { return $b['total'] - $a['total']; });
echo "════ مركزيةُ المكوّنات (البند ٦) — صفرُ نمطٍ محليٍّ في الشاشة ════\n";
echo "  النطاق: " . count($rows) . " ملفًّا" . ($ALL ? ' (استطلاعيٌّ موسَّع)' : ' (العشرُ الذهبية)') . "\n\n";
$clean = 0;
foreach ($rows as $r) {
    if ($r['total'] === 0) { $clean++; if (!$ALL) { echo "  ✔ {$r['file']}\n"; } continue; }
    echo "  ✗ {$r['file']} — مخالفات: {$r['total']}\n";
    if ($r['style_blocks']) { echo "      كتلُ style محلية: {$r['style_blocks']}\n"; }
    if ($r['inline'])       { echo "      سماتُ style سطرية: {$r['inline']}\n"; }
    if ($r['hex'])          { echo "      ألوانٌ مثبَّتة: {$r['hex']} → " . implode(' · ', $r['hex_s']) . "\n"; }
    if ($r['rgb'])          { echo "      rgb()/rgba(): {$r['rgb']}\n"; }
    if ($r['offscale'])     { echo "      قياساتٌ خارجَ السلّم: {$r['offscale']} → " . implode(' · ', $r['off_s']) . "\n"; }
}
echo "\n  نظيفةٌ تمامًا: {$clean}/" . count($rows) . " · إجماليُّ المخالفات: {$totalViol}\n";
echo "  ◆ لا يقيس: المكتبةَ المركزيةَ نفسَها (موضعُ الرموزِ المشروع) ولا التعليقات.\n";
exit(($ENFORCE && $totalViol > 0) ? 1 : 0);
