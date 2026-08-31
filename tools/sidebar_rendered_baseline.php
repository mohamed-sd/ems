<?php
/**
 * tools/sidebar_rendered_baseline.php — الأساسُ المُصيَّرُ للسايدبار (لكلِّ دور)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ SIDEBAR_RENDER_FIX §٤·١: «لكلٍّ من الأدوارِ: صيِّرِ الشجرةَ
 *   بـ`uxp_render_role_html` واستخرجْ بالترتيبِ الظاهر: المجموعة ← البند ←
 *   المسار» — **وهذا هو الأساسُ الصادقُ الوحيد**، والقاعدةُ الدائمة:
 *   «لا يُقاس سطحٌ بما في جدولِه — بل بما يظهر للمستخدمِ في جلستِه».
 * ◆ كلُّ دورٍ يُصيَّر في **عمليّةٍ نقيّةٍ** (`tools/lib/render_role_cli.php`)
 *   فلا يعبر مخبأٌ ساكنٌ بين الأدوار — الفخُّ المقيسُ في مجسِّ المصفوفة.
 *
 * التشغيل: php tools/sidebar_rendered_baseline.php [--md]
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

/* أدوارُ co4 الحيّةُ — بالمستخدمِ لا بالدورِ المجرَّد */
$roles = array();
$r = $conn->query("SELECT CAST(u.role AS UNSIGNED) rid, MIN(u.id) uid FROM users u
                    WHERE u.company_id = 4 GROUP BY rid ORDER BY rid");
while ($x = $r->fetch_assoc()) { $roles[(int) $x['rid']] = (int) $x['uid']; }

$snapQ = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot
                        WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
$snap = ($snapQ && $snapQ->num_rows) ? $snapQ->fetch_assoc()['snapshot_id'] : 'DRY';

/** تصييرُ دورٍ في عمليّةٍ نقيّة */
function srb_render($ROOT, $rid, $uid)
{
    $o = array();
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
        . ' ' . (int) $rid . ' ' . (int) $uid . ' 2>NUL', $o);
    $j = json_decode(implode('', $o), true);
    return is_array($j) ? $j : null;
}

printf("═══ الأساسُ المُصيَّرُ للسايدبار — لقطة %s ═══\n", $snap);
printf("  الأدوارُ الحيّة (co4 بالمستخدم): %d — يبدأ بالدورِ 1 موضعِ البلاغ\n\n", count($roles));

$md = "# الأساسُ المُصيَّرُ للسايدبار — ما يراه كلُّ دورٍ فعلًا\n\n"
    . "> ⛔ **مولَّدٌ من الشجرةِ المُصيَّرةِ حرفًا** (`uxp_render_role_html` بعمليّةٍ نقيّةٍ لكلِّ دور)\n"
    . "> — لا من أيِّ جدول. أمرُ `SIDEBAR_RENDER_FIX` §٤·١ · اللقطة `$snap` · "
    . date('Y-m-d H:i') . "\n\n";

$tot = 0;
foreach ($roles as $rid => $uid) {
    $j = srb_render($ROOT, $rid, $uid);
    if ($j === null) { printf("  ⛔ دور %d: تعذّر التصيير\n", $rid); continue; }
    $n = count($j['positions']);
    $tot += $n;
    printf("  دور %-3d مستخدم %-5d مواضعُ %-3d مجموعاتٌ %d\n", $rid, $uid, $n, count($j['shells']));
    $md .= "## الدور $rid — " . $n . " بندًا في " . count($j['shells']) . " مجموعةً (مستخدم $uid)\n\n";
    $g = null; $i = 0;
    foreach ($j['positions'] as $p) {
        if ($p['g'] !== $g) { $g = $p['g']; $md .= "**" . ($g === '' ? '(بلا مجموعة)' : $g) . "**\n\n"; }
        $i++;
        $md .= $i . '. ' . $p['l'] . ' — `' . $p['h'] . "`\n";
    }
    $md .= "\n";
}
printf("\n  المجموع: %d موضعًا مُصيَّرًا\n", $tot);

if ($MD) {
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/SIDEBAR_RENDERED_BASELINE.md', $md);
    echo "✔ كُتب docs/REPAIR01_20260823/SIDEBAR_RENDERED_BASELINE.md\n";
}
