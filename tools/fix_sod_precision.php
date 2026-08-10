<?php
/**
 * tools/fix_sod_precision.php — دقةُ أزواجِ فصلِ الواجبات (INJ-0224)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكم INJ-0224 يطلب رفعَ `sod_payroll_cycle` إلى «منع». ودرجةُ الزوجِ
 *   **مشتقّةٌ** لا مُعلَنة: `ems_sod_pair_grade()` تحسبها من درجاتِ رموزِه —
 *   `exact` إن كان كلُّ رمزٍ دقيقًا، و`approx` إن كان فيها تقريبيٌّ واحد،
 *   و`absent` إن كان فيها رمزٌ بلا شاشة.
 * ◆ فرفعُ العلمِ يدويًّا يجعل الحارسَ يمنع بناءً على إشارةٍ غيرِ دقيقةٍ — وهو
 *   نقضٌ لقاعدةِ المالك المسجَّلةِ في رأسِ `includes/sod_map.php` (2026-08-06):
 *   «تُعلَّم تقريبية وتُبلَّغ · مسحٌ يكشف ثم منع». والطريقُ الصحيحُ **تدقيقُ
 *   الرمزِ** فيرتفع الزوجُ اشتقاقًا بلا قلبِ علم.
 * ◆ وهذه الأداةُ تجعل الدَّينَ مرئيًّا ومقيسًا: لكلِّ زوجٍ درجتُه، والرمزُ الذي
 *   يخفضه، والسطحُ الذي يلزمه ليصير دقيقًا. فلا يبقى «سنرفعه لاحقًا» بلا رقم.
 *
 * التشغيل: php tools/fix_sod_precision.php [--md=مسار]
 * الخروج: 0 دائمًا — أداةُ قياسٍ لا بوابةُ منع (تُعلن ما تقيس).
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
require_once $ROOT . '/includes/sod_map.php';
$db = fix_db();

$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

$map = ems_sod_map();
$rs = $db->query("SELECT sod_id, conflict_code, name_ar, permission_a, permission_b, severity
                    FROM sod_conflicts WHERE active = 1 ORDER BY sod_id");
if (!$rs) { exit('sod_conflicts: ' . $db->error . "\n"); }

echo "══════════════════════════════════════════════════════════════════════\n";
echo " دقةُ أزواجِ فصلِ الواجبات — الدرجةُ مشتقّةٌ من رموزِها لا مُعلَنة\n";
echo ' ' . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

$block = 0; $warn = 0; $blind = 0; $rows = array();
while ($p = $rs->fetch_assoc()) {
    $codes = array_merge(ems_sod_split_codes($p['permission_a']), ems_sod_split_codes($p['permission_b']));
    $grade = ems_sod_pair_grade($codes);
    $weak = array();
    foreach ($codes as $c) {
        $cg = isset($map[$c]) ? $map[$c]['grade'] : 'absent';
        if ($cg === 'exact') { continue; }
        $scr = isset($map[$c]['screen']) ? $map[$c]['screen'] : '—';
        $weak[] = $c . ' (' . $cg . ($cg === 'approx' ? ' · ' . $scr : '') . ')';
    }
    if ($grade === 'exact') { $block++; $mark = '✔ يمنع'; }
    elseif ($grade === 'approx') { $warn++; $mark = '● يُنذر'; }
    else { $blind++; $mark = '✘ لا يُقاس'; }

    printf("%-12s %-24s %s\n", $mark, $p['conflict_code'], mb_substr($p['name_ar'], 0, 40));
    if ($weak) { echo '              يخفضه: ' . implode(' · ', $weak) . "\n"; }
    $rows[] = array($mark, $p['conflict_code'], $p['name_ar'], $grade, $weak);
}

echo "\n" . str_repeat('─', 70) . "\n";
printf("يمنع: %d · يُنذر: %d · لا يُقاس: %d — من %d زوجًا\n",
    $block, $warn, $blind, $block + $warn + $blind);
echo "\n◆ قاعدةُ المالك (2026-08-06): «تُعلَّم تقريبية وتُبلَّغ · مسحٌ يكشف ثم منع».\n";
echo "◆ ورفعُ زوجٍ إلى المنعِ لا يكون بقلبِ علمٍ بل بتدقيقِ رمزِه: سطحٌ ورايةٌ\n";
echo "  تخصّانه وحدَه. ودونَ ذلك يمنع الحارسُ منحًا مشروعةً لأن الرايةَ أوسع.\n";
echo str_repeat('═', 70) . "\n";

if ($mdOut) {
    $md = "# دقةُ أزواجِ فصلِ الواجبات · " . date('Y-m-d H:i') . "\n\n";
    $md .= "| الزوج | الاسم | الدرجة | ما يخفضه |\n|---|---|---|---|\n";
    foreach ($rows as $r) {
        $md .= "| `{$r[1]}` | " . mb_substr($r[2], 0, 44) . " | {$r[0]} | "
             . ($r[4] ? implode(' · ', $r[4]) : '—') . " |\n";
    }
    $md .= "\n**يمنع: {$block} · يُنذر: {$warn} · لا يُقاس: {$blind}**\n";
    file_put_contents($mdOut, $md);
    echo "كُتب: {$mdOut}\n";
}
exit(0);
