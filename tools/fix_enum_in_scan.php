<?php
/**
 * tools/fix_enum_in_scan.php — كلُّ قيمةٍ في شرطِ IN لها موضعٌ في تعدادِ عمودها
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0334 — والنمطُ يتكرّر في المستودعِ كلِّه لا في بندٍ واحد.
 *
 * ── العلّة ────────────────────────────────────────────────────────────────
 * `Procurement/rfq_compare_award.php` يصفّي `state IN ('sent','opened','quoted')`
 * والتعدادُ في القاعدة — **مقروءًا حيًّا لا من مخطَّطٍ محفوظ** —
 * `enum('draft','sent','closed','awarded','contracted','cancelled')`.
 * فـ`'opened'` و`'quoted'` **لا وجودَ لهما**، والقائمةُ تنقص صامتةً: لا خطأَ
 * ولا تحذير — صفوفٌ حيّةٌ لا تظهر، والموظفُ يظنُّ ألا عمل.
 *
 * ── ولماذا فاحصٌ عامٌّ لا إصلاحُ سطر ──────────────────────────────────────
 * القيمةُ المكتوبةُ في شرطٍ لا يحرسها شيء: يتغيّر التعدادُ في هجرةٍ فتبقى
 * الشاشةُ تسأل عن قيمةٍ ماتت. **فالفاحصُ هو الحارسُ**، وهو وحدَه يمنع عودةَ
 * النمطِ في كلِّ ملفٍّ قادم.
 *
 * ◆ **والتعدادُ يُقرأ من `SHOW COLUMNS`** لا من `database/schema/schema.sql` —
 *   فالمخطَّطُ المحفوظُ لقطةٌ تتقادم، والقاعدةُ هي الحقيقة.
 * ◆ ولا يُدان ما لا يُعرف: عمودٌ لا نجد جدولَه أو ليس تعدادًا **يُتخطّى ويُعلَن**
 *   — فاتهامٌ بلا دليلٍ يُفقد الفاحصَ قيمتَه.
 *
 * التشغيل: php tools/fix_enum_in_scan.php [--json] [--all]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

/* ── تعداداتُ القاعدةِ كلُّها: جدول.عمود ⇒ [قيم] ─────────────────────────── */
$ENUMS = array();
$r = $conn->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
                     FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('enum','set')");
while ($r && ($x = $r->fetch_assoc())) {
    $t = (string) $x['COLUMN_TYPE'];
    if (!preg_match('~^(?:enum|set)\((.*)\)$~is', $t, $m)) { continue; }
    $vals = array();
    foreach (explode("','", trim($m[1], "'")) as $v) { $vals[] = $v; }
    $ENUMS[strtolower($x['TABLE_NAME']) . '.' . strtolower($x['COLUMN_NAME'])] = $vals;
}
/* والعمودُ قد يُذكر بلا جدولٍ في الشرط — فخريطةُ العمودِ وحدَه تُبنى، ولا
   تُستعمل إلا إن كان الاسمُ **فريدًا** في القاعدةِ كلِّها (وإلا فالتخمينُ ظلم) */
$BYCOL = array();
foreach ($ENUMS as $k => $v) {
    list(, $col) = explode('.', $k, 2);
    if (!isset($BYCOL[$col])) { $BYCOL[$col] = array('vals' => $v, 'n' => 0, 'tbl' => $k); }
    $BYCOL[$col]['n']++;
}

/* ── مسحُ الملفات ────────────────────────────────────────────────────────── */
$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = str_replace('\\', '/', $f->getPathname());
    if (substr($p, -4) !== '.php') { continue; }
    if (strpos($p, '/.claude/') !== false || strpos($p, '/storage/backups/') !== false
        || strpos($p, '/vendor/') !== false || strpos($p, '/node_modules/') !== false) { continue; }
    $files[] = $p;
}

$ALL = in_array('--all', $argv, true);
$hits = array(); $skipped = 0; $checked = 0;

foreach ($files as $abs) {
    $rel = ltrim(str_replace($ROOT, '', $abs), '/');
    if (!$ALL && (strpos($rel, 'tools/') === 0 || strpos($rel, 'tests/') === 0)) { continue; }
    $src = (string) @file_get_contents($abs);
    if ($src === '' || stripos($src, ' IN (') === false) { continue; }

    /* ══ الوحدةُ المقيسةُ **نصُّ استعلامٍ واحدٍ** لا الملفُّ كلُّه ═════════════════
         أوّلُ صياغةٍ حلَّت الجدولَ من `FROM/JOIN` في **الملفِّ كلِّه** — فشاشةٌ
         تحمل استعلامًا عن القرارات واستعلامًا آخرَ عن المستخدمين نُسبت قيمُ
         الأولِ إلى تعدادِ `users.status`، **فاتُّهمت أربعُ شاشاتٍ بريئة**.
         والصوابُ: يُقتطع نصُّ الاستعلامِ الحاوي للشرطِ وحدَه، ويُحَلُّ الجدولُ
         من `FROM/JOIN` **داخلَه**. فما لا يُحَلُّ يُتخطّى ويُعلَن — لا يُخمَّن. */
    if (!preg_match_all('~"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'~s', $src, $qm, PREG_SET_ORDER)) { continue; }
    foreach ($qm as $qs) {
        $sql = ($qs[1] !== '' ? $qs[1] : (isset($qs[2]) ? $qs[2] : ''));
        if ($sql === '' || stripos($sql, ' IN (') === false) { continue; }
        if (!preg_match('~\b(?:FROM|JOIN|UPDATE)\s+`?[A-Za-z_]~i', $sql)) { continue; }

        $ALIAS = array(); $TBL = array();
        if (preg_match_all('~\b(?:FROM|JOIN|UPDATE)\s+`?([A-Za-z_][A-Za-z0-9_]*)`?(?:\s+(?:AS\s+)?`?([A-Za-z_][A-Za-z0-9_]{0,4})`?)?~i',
                $sql, $am, PREG_SET_ORDER)) {
            foreach ($am as $a2) {
                $t = strtolower($a2[1]);
                $TBL[$t] = true;
                $al = isset($a2[2]) ? strtolower($a2[2]) : '';
                if ($al !== '' && !in_array($al, array('on','set','where','left','inner','join','as','and','or','group','order'), true)) {
                    $ALIAS[$al] = $t;
                }
            }
        }
        if (!preg_match_all(
            '~(?:([A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?([A-Za-z_][A-Za-z0-9_]*)\s+IN\s*\(\s*((?:\'[^\']*\'\s*,\s*)*\'[^\']*\')\s*\)~i',
            $sql, $mm, PREG_SET_ORDER)) { continue; }

        foreach ($mm as $m) {
            $col = strtolower($m[2]);
            $pfx = strtolower((string) $m[1]);
            if (!isset($BYCOL[$col])) { continue; }

            $tbl = '';
            if ($pfx !== '') {
                if (isset($ALIAS[$pfx])) { $tbl = $ALIAS[$pfx]; }
                elseif (isset($ENUMS[$pfx . '.' . $col])) { $tbl = $pfx; }
            }
            if ($tbl === '') {
                $cands = array();
                foreach (array_keys($TBL) as $t) { if (isset($ENUMS[$t . '.' . $col])) { $cands[] = $t; } }
                if (count($cands) === 1) { $tbl = $cands[0]; }
            }
            if ($tbl === '' || !isset($ENUMS[$tbl . '.' . $col])) { $skipped++; continue; }

            $vals = $ENUMS[$tbl . '.' . $col];
            $checked++;
            preg_match_all("~'([^']*)'~", $m[3], $vm);
            $bad = array();
            foreach ($vm[1] as $v) {
                if ($v === '') { continue; }
                if (!in_array($v, $vals, true)) { $bad[] = $v; }
            }
            if ($bad) {
                $hits[] = array('file' => $rel, 'col' => $tbl . '.' . $col,
                                'bad' => $bad, 'enum' => $vals);
            }
        }
    }
}
echo "══ قيمةٌ في شرطِ IN بلا موضعٍ في تعدادِ عمودها ══\n\n";
echo '  تعداداتُ القاعدة: ' . count($ENUMS) . " عمودًا\n";
echo '  شروطُ IN مقيسةٌ: ' . $checked . ' · تُخُطّيت لتكرارِ اسمِ العمود: ' . $skipped . "\n\n";
if (!$hits) {
    echo "  ✔ **صفرُ قيمةٍ ميتةٍ** — كلُّ ما يُسأل عنه له موضعٌ في تعدادِه\n";
} else {
    echo '  ✘ **' . count($hits) . " موضعًا يسأل عن قيمةٍ لا وجودَ لها**\n\n";
    foreach ($hits as $h) {
        echo '  · ' . $h['file'] . "\n";
        echo '      العمود: `' . $h['col'] . '` · الميت: ' . implode(' · ', $h['bad']) . "\n";
        echo '      التعداد: ' . implode(' · ', $h['enum']) . "\n";
    }
}
if (in_array('--json', $argv, true)) {
    file_put_contents($ROOT . '/docs/fix_progress/enum_in_scan.json',
        json_encode($hits, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n  · كُتب: docs/fix_progress/enum_in_scan.json\n";
}
exit(empty($hits) ? 0 : 1);
