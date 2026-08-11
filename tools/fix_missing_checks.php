<?php
/**
 * tools/fix_missing_checks.php — قيودٌ تظنُّ الشيفرةُ أنها موجودةٌ وليست
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **جاء من عطلٍ حقيقيّ**: حكمُ `M-11` («لا خصمَ بلا مستندِ مصدر») كان محروسًا
 *   بقيدِ `ck_dues_debit_source` — وسقط القيدُ من القاعدةِ وبقيت **سبعةُ مواضعَ**
 *   في الشيفرةِ والفواحصِ تُصرّح باتكائها عليه. فقُرِئت الحمايةُ موجودةً فلم
 *   يُضَف فحصٌ تطبيقيٌّ يعوّضها، والثغرةُ عاشت شهرًا.
 *
 * ◆ **والعيبُ صنفٌ لا حالة**: كلُّ اسمِ قيدٍ يُذكر في الشيفرةِ أو في فاحصٍ
 *   ادّعاءٌ بأن القاعدةَ تحرس. فتُقاس الادّعاءاتُ كلُّها مرةً واحدةً بدل انتظارِ
 *   فاحصٍ يحمرُّ فيُقاد إلى واحدٍ منها.
 *
 * ◆ ويُقاس **الحرّان معًا**: قيودُ CHECK وقيودُ الفرادة (UNIQUE) — فكلتاهما
 *   طبقةُ منعٍ في القاعدة، والفواحصُ تحرسهما بالاسم.
 *
 * التشغيل: php tools/fix_missing_checks.php [--md=<path>]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
$db = fix_db();
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

/* ── ① الأسماءُ الحيّةُ في القاعدة ───────────────────────────────────────── */
$liveCheck = array();
$r = $db->query("SELECT CONSTRAINT_NAME, TABLE_NAME FROM information_schema.CHECK_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()");
while ($r && ($x = $r->fetch_assoc())) { $liveCheck[$x['CONSTRAINT_NAME']] = $x['TABLE_NAME']; }

$liveUnique = array();
$r = $db->query("SELECT DISTINCT INDEX_NAME, TABLE_NAME FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0");
while ($r && ($x = $r->fetch_assoc())) { $liveUnique[$x['INDEX_NAME']] = $x['TABLE_NAME']; }

$liveFk = array();
$r = $db->query("SELECT DISTINCT CONSTRAINT_NAME, TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
while ($r && ($x = $r->fetch_assoc())) { $liveFk[$x['CONSTRAINT_NAME']] = $x['TABLE_NAME']; }

/* ── ② الادّعاءاتُ في الشجرة ─────────────────────────────────────────────
   نمطُ التسميةِ في هذا المشروعِ مُستقَرّ: `ck_*` · `chk_*` · `uq_*` · `fk_*`.
   ويُستثنى ما في نصِّ الهجراتِ والمخطَّطِ — فذاك **تعريفٌ** لا ادّعاء. */
$claims = array();   // name => array of "file:line"
$scanDirs = array('app', 'includes', 'tests', 'tools', 'Finance', 'Contracts', 'Operations',
                  'Approvals', 'Timesheet', 'api', 'Equipments', 'main', 'docs');
foreach ($scanDirs as $dir) {
    $abs = $ROOT . '/' . $dir;
    if (!is_dir($abs)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (!in_array($ext, array('php', 'md'), true)) { continue; }
        if (strpos($p, '/storage/') !== false || strpos($p, '/vendor/') !== false) { continue; }
        $lines = (array) @file($p);
        foreach ($lines as $i => $line) {
            if (!preg_match_all('/\b((?:ck|chk|uq|fk)_[a-z0-9_]{3,})\b/i', $line, $m)) { continue; }
            foreach ($m[1] as $name) {
                $claims[$name][] = str_replace($ROOT . '/', '', $p) . ':' . ($i + 1);
            }
        }
    }
}

/* ── ③ الحكم ─────────────────────────────────────────────────────────────── */
$missing = array(); $present = 0;
foreach ($claims as $name => $where) {
    $kind = 'CHECK';
    $exists = isset($liveCheck[$name]);
    if (!$exists && (strpos($name, 'uq_') === 0)) { $kind = 'UNIQUE'; $exists = isset($liveUnique[$name]); }
    if (!$exists && (strpos($name, 'fk_') === 0)) { $kind = 'FK';     $exists = isset($liveFk[$name]); }
    /* اسمٌ قد يكون من أيِّ الحرّاسِ الثلاثة — لا نحكم بالبادئةِ وحدَها */
    if (!$exists) { $exists = isset($liveUnique[$name]) || isset($liveFk[$name]) || isset($liveCheck[$name]); }
    if ($exists) { $present++; continue; }
    $missing[$name] = array('kind' => $kind, 'where' => array_values(array_unique($where)));
}

/* ── ④ الفرزُ: ما يحرسه فاحصٌ أهمُّ — لأن حُمرتَه مضمونة ─────────────────── */
uasort($missing, static function ($a, $b) {
    $ta = 0; $tb = 0;
    foreach ($a['where'] as $w) { if (strpos($w, 'tests/') === 0) { $ta++; } }
    foreach ($b['where'] as $w) { if (strpos($w, 'tests/') === 0) { $tb++; } }
    if ($ta !== $tb) { return $tb <=> $ta; }
    return count($b['where']) <=> count($a['where']);
});

$L = array();
$L[] = '**القياس:** ' . date('Y-m-d H:i');
$L[] = '';
$L[] = 'الحيُّ في القاعدة: **' . count($liveCheck) . '** CHECK · **' . count($liveUnique)
     . '** UNIQUE · **' . count($liveFk) . '** FK';
$L[] = 'أسماءُ قيودٍ مذكورةٌ في الشجرة: **' . count($claims) . '** — منها **' . $present
     . '** قائمةٌ و**' . count($missing) . '** مفقودة';
$L[] = '';
$L[] = '## قيودٌ تدّعي الشيفرةُ وجودَها وهي مفقودة';
$L[] = '';
if (!$missing) {
    $L[] = '✔ لا ادّعاءَ بلا سند — كلُّ اسمِ قيدٍ مذكورٍ في الشجرةِ قائمٌ في القاعدة.';
} else {
    $L[] = '| # | القيد | النوعُ المرجَّح | يحرسه فاحص | مواضعُ الادّعاء |';
    $L[] = '|---|---|---|---|---|';
    $i = 0;
    foreach ($missing as $name => $info) {
        $i++;
        $inTests = array();
        foreach ($info['where'] as $w) { if (strpos($w, 'tests/') === 0) { $inTests[] = $w; } }
        $L[] = '| ' . $i . ' | `' . $name . '` | ' . $info['kind'] . ' | '
             . (count($inTests) ? '**نعم** (' . count($inTests) . ')' : 'لا') . ' | '
             . implode(' · ', array_slice($info['where'], 0, 4))
             . (count($info['where']) > 4 ? ' … +' . (count($info['where']) - 4) : '') . ' |';
    }
}
$L[] = '';
$L[] = '◆ **اسمٌ مفقودٌ يحرسه فاحصٌ = حُمرةٌ مضمونةٌ في المجموعة.** وما لا يحرسه فاحصٌ';
$L[] = '  أخطرُ: الشيفرةُ تتكئ عليه وصمتُه لا يُكشف حتى يقع المال في المكان الخطأ.';

$out = implode("\n", $L);
echo "══════════════════════════════════════════════════════════════════════\n";
echo " قيودٌ مُدَّعاةٌ وغيرُ قائمة\n";
echo "══════════════════════════════════════════════════════════════════════\n";
echo $out . "\n";
if ($mdOut) {
    @file_put_contents($mdOut, "# قيودٌ تدّعي الشيفرةُ وجودَها\n\n" . $out . "\n");
    echo "\nتقرير: {$mdOut}\n";
}
exit(count($missing) === 0 ? 0 : 1);
