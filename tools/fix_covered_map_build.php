<?php
/**
 * tools/fix_covered_map_build.php — بناءُ خريطةِ الإسنادِ للملاحظاتِ المُغطّاة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ سدُّ أسبابِ «غيرُ مقيس» في حملةِ المُغطّاة (2026-08-13)
 *
 * ── لماذا ملفُّ خريطةٍ منفصل ────────────────────────────────────────────────
 * اختبارُ القبولِ في السجلِّ الجامع يشترط «صفرَ أثرٍ» **ولا يُسمّي الجدولَ**
 * الذي يُقاس فيه. والسجلُّ **دليلُ تدقيقٍ لا يُحرَّر** — فيُبنى الإسنادُ في
 * ملفٍّ صريحٍ مستقلٍّ مع **سندِه من الكود**، ويبقى الأصلُ كما هو.
 *
 * ── والإسنادُ يُستخرَج لا يُخمَّن ───────────────────────────────────────────
 * لكلِّ بندٍ يُقرأ **مصدرُ الشاشةِ نفسِه** ويُستخرَج منه:
 *   · `action`  — رمزُ الفعلِ من عقدِ `ems_post_contract` (أو من `actions`).
 *   · `perm`    — الصلاحيةُ المطلوبةُ كما تُصرّح بها الشاشة.
 *   · `trigger` — الحقلُ الذي **يُقدِح** المعالج؛ وبلا هذا الحقلِ لا يعمل
 *                 الحارسُ أصلًا فيُقرأ تحويلٌ لسببٍ آخرَ رفضَ صلاحيةٍ وهو ليس
 *                 كذلك (وهو الفخُّ الذي أسقط الجولةَ السابقةَ كلَّها).
 *   · `tables`  — جداولُ الكتابةِ من `INSERT INTO` و`UPDATE` في المصدرِ نفسِه،
 *                 ومن `nav09_action_map.writes_text` للفعلِ المطابق، ويُبقى
 *                 على ما يوجد منها في القاعدةِ فعلًا.
 * ويُسجَّل **رقمُ السطرِ** لكلِّ استخراج — فمن يُخالف يستطيع نقضَ إسنادٍ بعينِه.
 *
 * ── وما لا يكتب في جدولٍ يُعلَن ─────────────────────────────────────────────
 * شاشةٌ بلا معالجِ كتابةٍ **بطبيعتها** تُوسَم `no_write` — وتلك تُقاس بأثرٍ
 * آخرَ (حدثٌ · سجلُّ تدقيقٍ · ردُّ الحالة) لا بعدِّ صفوفٍ في جدولٍ لا يُكتب.
 *
 * التشغيل: php tools/fix_covered_map_build.php [--out=<tsv>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$OUT = 'docs/fix_progress/covered_screen_map.tsv';
foreach ($argv as $a) { if (strpos($a, '--out=') === 0) { $OUT = substr($a, 6); } }

ob_start();
require_once $ROOT . '/config.php';
ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];

/* ── جداولُ القاعدةِ الحيّة — فلا يُسنَد بندٌ إلى جدولٍ لا وجودَ له ─────────── */
$live = array();
$r = $conn->query('SHOW TABLES');
while ($r && ($x = $r->fetch_row())) { $live[strtolower($x[0])] = true; }

/* ── خريطةُ الأفعال: رمزٌ ⇒ نصُّ ما يكتبه ────────────────────────────────── */
$writesOf = array();
$r = $conn->query("SELECT canonical_code, writes_text, canonical_file FROM nav09_action_map");
while ($r && ($x = $r->fetch_assoc())) {
    $writesOf[trim($x['canonical_code'])] = array('w' => (string) $x['writes_text'],
                                                  'f' => (string) $x['canonical_file']);
}

/* ── القائمة: نفسُ اشتقاقِ الحملة (مُغطًّى محسوبةٌ لا مقروءةٌ من عمود) ──────── */
$COVERED_KINDS = array('Permission Gap', 'Governance Gap');
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}
$todo = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++;
    if ($n <= 3) { continue; }
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (!in_array(trim($c[9]), $COVERED_KINDS, true)) { continue; }
    if (!in_array(trim($c[10]), array('P0', 'P1'), true)) { continue; }
    $id = trim($c[0]);
    if ((isset($state[$id]) ? $state[$id] : '') === 'مُغلقٌ بشاهد') { continue; }
    $todo[$id] = array('url' => trim($c[5]), 'test' => trim($c[20]), 'screen' => trim($c[4]));
}
fclose($fh);

echo "══ بناءُ خريطةِ الإسناد — " . count($todo) . " بندًا\n\n";

$rows = array();
$stat = array('write' => 0, 'no_write' => 0, 'no_file' => 0);
foreach ($todo as $id => $r0) {
    $rel = null;
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', $r0['url'], $m)
        && is_file($ROOT . '/' . $m[1])) { $rel = $m[1]; }
    if ($rel === null) {
        $rows[] = array($id, '—', '', '', '', '', 'no_file', 'الرابطُ لا يشير إلى ملفٍّ حيّ');
        $stat['no_file']++;
        continue;
    }
    $src = (string) file_get_contents($ROOT . '/' . $rel);
    $lines = preg_split('~\r?\n~', $src);

    /* عقدُ الكتابةِ المُصرَّح */
    $action = ''; $perm = ''; $trigger = ''; $ev = array();
    foreach ($lines as $i => $ln) {
        if (preg_match("~'action'\s*=>\s*'([^']+)'~", $ln, $mm) && $action === '') {
            $action = $mm[1]; $ev[] = 'action@' . ($i + 1);
        }
        if (preg_match("~'perm'\s*=>\s*'([^']+)'~", $ln, $mm) && $perm === '') {
            $perm = $mm[1]; $ev[] = 'perm@' . ($i + 1);
        }
        if (preg_match("~'trigger'\s*=>\s*'([^']+)'~", $ln, $mm) && $trigger === '') {
            $trigger = $mm[1]; $ev[] = 'trigger@' . ($i + 1);
        }
    }

    /* ── مُقدِحاتُ المعالجِ من **شروطِ المصدر** ────────────────────────────────
         لا كلُّ شاشةٍ تُصرّح بـ`'trigger'` في عقدٍ؛ وأكثرُها يفتح معالجَه بـ
         `if (isset($_POST['save']))` أو `!empty($_POST['x'])`. وبلا هذا المفتاحِ
         لا يدخل الطلبُ فرعَ الكتابةِ أصلًا فلا يشتعل الحارس — فيُقرأ تحويلٌ
         لسببٍ آخرَ رفضَ صلاحيةٍ وهو ليس كذلك (وهو ما أسقط الجولةَ السابقة).
         والمفاتيحُ **مقروءةٌ من الشرطِ** لا مخترَعة. */
    $trigs = array();
    foreach ($lines as $i => $ln) {
        if (!preg_match('~\b(if|elseif)\s*\(~', $ln)) { continue; }
        if (preg_match_all("~(?:isset|!\s*empty|empty)\s*\(\s*\\\$_POST\[\s*'([A-Za-z0-9_]+)'~", $ln, $mm)) {
            foreach ($mm[1] as $k) { $trigs[$k] = 'cond@' . ($i + 1); }
        }
        if (preg_match_all("~\\\$_POST\[\s*'([A-Za-z0-9_]+)'\s*\]\s*(?:===|==|!==|!=)~", $ln, $mm)) {
            foreach ($mm[1] as $k) { $trigs[$k] = 'cmp@' . ($i + 1); }
        }
    }
    if ($trigger !== '') { $trigs[$trigger] = 'contract'; }

    /* جداولُ الكتابةِ من المصدرِ نفسِه */
    $tables = array();
    foreach ($lines as $i => $ln) {
        if (preg_match_all('~INSERT\s+INTO\s+`?([a-z][a-z0-9_]+)`?~i', $ln, $mm)) {
            foreach ($mm[1] as $t) { $tables[strtolower($t)] = 'INSERT@' . ($i + 1); }
        }
        if (preg_match_all('~UPDATE\s+`?([a-z][a-z0-9_]+)`?\s+SET~i', $ln, $mm)) {
            foreach ($mm[1] as $t) { if (!isset($tables[strtolower($t)])) { $tables[strtolower($t)] = 'UPDATE@' . ($i + 1); } }
        }
        if (preg_match_all("~->(?:insert|update)\(\s*'([a-z][a-z0-9_]+)'~i", $ln, $mm)) {
            foreach ($mm[1] as $t) { if (!isset($tables[strtolower($t)])) { $tables[strtolower($t)] = 'gate@' . ($i + 1); } }
        }
    }
    /* ومن سجلِّ الأفعالِ إن صرّح الفعلُ بما يكتب */
    if ($action !== '' && isset($writesOf[$action])) {
        if (preg_match_all('~\b([a-z][a-z0-9_]{4,40})\b~', $writesOf[$action]['w'], $mm)) {
            foreach ($mm[1] as $t) {
                if (isset($live[strtolower($t)]) && !isset($tables[strtolower($t)])) {
                    $tables[strtolower($t)] = 'nav09_action_map';
                }
            }
        }
    }
    /* لا يُسنَد إلى جدولٍ غيرِ موجود */
    foreach (array_keys($tables) as $t) { if (!isset($live[$t])) { unset($tables[$t]); } }

    $kind = $tables ? 'write' : 'no_write';
    $stat[$kind]++;
    $rows[] = array($id, $rel, $action, $perm, implode(",", array_keys($trigs)),
                    implode(',', array_keys($tables)),
                    $kind,
                    $tables ? implode(' · ', array_map(function ($k, $v) { return $k . ':' . $v; },
                                array_keys($tables), array_values($tables)))
                            : 'لا معالجَ كتابةٍ في المصدر — يُقاس بأثرٍ آخر');
    printf("  %-10s %-38s %-7s %-11s جداول: %s\n", $id, mb_substr($rel, 0, 36),
           $perm !== '' ? $perm : '—', $trigs ? implode(",",array_slice(array_keys($trigs),0,3)) : '—',
           $tables ? implode(',', array_keys($tables)) : '(لا كتابة)');
}

$path = $ROOT . '/' . ltrim($OUT, '/');
@mkdir(dirname($path), 0777, true);
$out = "INJ\tscreen\taction\tperm\ttrigger\ttables\tkind\tevidence\n";
foreach ($rows as $x) { $out .= implode("\t", $x) . "\n"; }
file_put_contents($path, $out);

echo "\n── الحصيلة\n";
echo "  بمعالجِ كتابةٍ وجداولَ مُسنَدة : " . $stat['write'] . "\n";
echo "  بلا كتابةٍ (يُقاس بأثرٍ آخر)   : " . $stat['no_write'] . "\n";
echo "  بلا ملفٍّ حيّ                  : " . $stat['no_file'] . "\n";
echo "\n  · كُتبت الخريطة: {$OUT}\n";
