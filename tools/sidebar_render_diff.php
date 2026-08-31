<?php
/**
 * tools/sidebar_render_diff.php — فرقُ الشجرةِ الحيّةِ عن الأساسِ المختوم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ SIDEBAR_DIRECTION_FIX §٤: «SIDEBAR_RENDERED_BASELINE.md محفوظٌ
 *   ومختومٌ — أيُّ محاذاةٍ لا تُنتج فرقًا عنه لم تقع. وإن كان بنودٌ انتقلت
 *   صفرًا فالبندُ راسبٌ ويُعلَن راسبًا».
 * ◆ الأساسُ يُقرأ من الملفِّ المختومِ حرفًا، والحيُّ يُصيَّر الآن بعمليّةٍ
 *   نقيّةٍ لكلِّ دورٍ — والفرقُ بالصيغةِ الملزمةِ لكلِّ دور.
 *
 * التشغيل: php tools/sidebar_render_diff.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

/* ═══ ① الأساسُ المختومُ يُفكّ ═══════════════════════════════════════════ */
$sealPath = $ROOT . '/docs/REPAIR01_20260823/SIDEBAR_RENDERED_BASELINE.md';
$seal = (string) @file_get_contents($sealPath);
if ($seal === '') { exit("⛔ الأساسُ المختومُ غيرُ موجود\n"); }
$base = array();   // rid => ['groups'=>[..], 'items'=>[base=>group]]
$rid = 0; $g = '';
foreach (preg_split('~\r?\n~u', $seal) as $ln) {
    if (preg_match('~^## الدور (\d+) ~u', $ln, $m)) { $rid = (int) $m[1]; $g = ''; continue; }
    if ($rid === 0) { continue; }
    if (preg_match('~^\*\*(.+)\*\*$~u', trim($ln), $m)) {
        $g = trim($m[1]);
        $base[$rid]['groups'][] = $g;
        continue;
    }
    if (preg_match('~^\d+\.\s+(.*?)\s+—\s+`([^`]+)`~u', $ln, $m)) {
        $b = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim($m[2]))));
        if ($b !== '' && !isset($base[$rid]['items'][$b])) { $base[$rid]['items'][$b] = $g; }
    }
}

/* ═══ ② الحيُّ يُصيَّر ══════════════════════════════════════════════════ */
$roleUid = array();
$r = $conn->query("SELECT CAST(u.role AS UNSIGNED) rid, MIN(u.id) uid FROM users u
                    WHERE u.company_id = 4 GROUP BY rid ORDER BY rid");
while ($x = $r->fetch_assoc()) { $roleUid[(int) $x['rid']] = (int) $x['uid']; }

$md = "# فرقُ الشجرةِ الحيّةِ عن الأساسِ المختوم — SIDEBAR_RENDER_DIFF\n\n"
    . "> أمرُ `SIDEBAR_DIRECTION_FIX` §٤ · «أيُّ محاذاةٍ لا تُنتج فرقًا عنه — لم تقع» · "
    . date('Y-m-d H:i') . "\n> الأساسُ: `SIDEBAR_RENDERED_BASELINE.md` المختومُ (حالُ ما بعدَ المحاذاةِ "
    . "المعكوسةِ المُلغاة) — والحيُّ بعد تصحيحِ الاتجاه.\n\n";
$totMoved = 0; $totSame = 0; $anyZero = array();
foreach ($base as $rid0 => $B) {
    if (!isset($roleUid[$rid0])) { continue; }
    $o = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
        . ' ' . $rid0 . ' ' . $roleUid[$rid0] . ' 2>NUL', $o);
    $j = json_decode(implode('', $o), true);
    if (!is_array($j)) { continue; }
    $liveGroups = array(); $liveItems = array(); $gg = null;
    foreach ($j['positions'] as $p) {
        if ($p['g'] !== $gg) { $gg = $p['g']; $liveGroups[] = $gg; }
        $b = strtolower(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $p['h']))));
        if ($b !== '' && !isset($liveItems[$b])) { $liveItems[$b] = (string) $p['g']; }
    }
    $moved = 0; $same = 0;
    foreach ($B['items'] as $b => $g0) {
        if (!isset($liveItems[$b])) { continue; }
        if (trim($liveItems[$b]) === trim($g0)) { $same++; } else { $moved++; }
    }
    $totMoved += $moved; $totSame += $same;
    if ($moved === 0) { $anyZero[] = $rid0; }
    $line = sprintf("الدور %d · الأبواب: %d ⇒ %d · البنود: %d ⇒ %d\n"
        . "  أوّلُ ٥ أبوابٍ قبل : %s\n  أوّلُ ٥ أبوابٍ بعد : %s\n"
        . "  بنودٌ انتقلت: %d   بنودٌ لم تتحرّك: %d\n",
        $rid0, count($B['groups']), count($liveGroups),
        count($B['items']), count($liveItems),
        implode(' ← ', array_slice($B['groups'], 0, 5)),
        implode(' ← ', array_slice($liveGroups, 0, 5)),
        $moved, $same);
    echo $line . "\n";
    $md .= "```\n" . $line . "```\n\n";
}
printf("الإجمالي: بنودٌ انتقلت %d · لم تتحرّك %d\n", $totMoved, $totSame);
$md .= "**الإجمالي: بنودٌ انتقلت $totMoved · لم تتحرّك $totSame**\n";
if ($anyZero) {
    $md .= "\n⛔ أدوارٌ بصفرِ انتقال: " . implode(' · ', $anyZero) . " — تُفحص فردًا\n";
}
if ($totMoved === 0) {
    echo "\n⛔ **بنودٌ انتقلت = صفر ⇒ البندُ راسبٌ ويُعلَن راسبًا**\n";
    $md .= "\n⛔ **بنودٌ انتقلت = صفر ⇒ البندُ راسبٌ**\n";
}
if ($MD) {
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_RENDER_DIFF.md', $md);
    echo "✔ كُتب docs/REPAIR01_20260823/SIDEBAR_RENDER_DIFF.md\n";
}
