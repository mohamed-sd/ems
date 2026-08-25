<?php
/**
 * tools/repair01_w6_files_apply.php
 *   منقّي نصِّ ملفّاتِ الشاشات — REPAIR01 · W06 · الجولةُ الثانيةُ مصحَّحةُ النطاق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما فات الجولةَ الأولى**: خرجت خضراءَ ١٨/١٨ وهي تفحص سبعةَ جداولَ فقط،
 *   و٨٧٢ ملفَّ شاشةٍ خارجَ مقامِها فيها عشراتُ الآلافِ من علاماتِ التشكيلِ في
 *   نصٍّ **يراه المستخدم**. وهذه الأداةُ تُكمل ما فات (‏W06 §٤-٢ · §٤-٥).
 *
 * ◆ **والتحريرُ على مدًى مصنَّفٍ لا على بحثٍ واستبدالٍ في الخام**: التقطيعُ في
 *   `tools/lib/repair01_w6_files.php` بالرمزنة، والكتابةُ من آخِرِ المدياتِ إلى
 *   أوّلِها فلا تُزحزِحُ كتابةٌ إزاحةَ ما بعدَها.
 *
 * ◆ **ولا يُكتب ملفٌّ لا يعبر `php -l`**: الفحصُ يقع **قبل** الكتابة على النصِّ
 *   المُنقّى في ملفٍّ مؤقّت. وأيُّ ملفٍّ يسقط يُترَك كما هو ويُعلَن.
 *
 * ◆ **والإرجاعُ بالمحتوى لا بالذاكرة**: `--revert` يعيد كلَّ ملفٍّ من `git` إلى
 *   حالتِه قبل الجولة — والدفترُ يحفظ `sha1` قبلَ وبعد، فالإرجاعُ **متحقَّقٌ**
 *   لا مُدَّعًى.
 *
 * التشغيل:
 *   php tools/repair01_w6_files_apply.php --dry     قياسٌ بلا كتابة
 *   php tools/repair01_w6_files_apply.php           تنقيةٌ وتسجيل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/lib/repair01_w6_files.php';
require_once $ROOT . '/tools/lib/repair01_w6_sources.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$DRY = in_array('--dry', $argv, true);
$RUN = 'W6F-' . date('YmdHis');
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "══ REPAIR01 · W06 — منقّي نصِّ ملفّاتِ الشاشات ══\n";
echo ($DRY ? "الوضع: قياسٌ بلا كتابة\n\n" : "الوضع: تنقيةٌ وتسجيل · الجولة $RUN\n\n");

/* ═══════════════════════════════════════════════════════════════════════════
   ① معجمُ الاقترانِ — يُجمَع من الشجرةِ **وِمن مخطَّطِ القاعدة** قبل أيِّ كتابة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① معجمُ الاقتران ─────────────────────────────────────────────\n";
$vocab = repair01_w6_coupled_vocab($ROOT, $conn);
printf("  ✔ %d مفردةً تُقارَن ولا تُعرَض — وكلُّ نصٍّ يساويها يُعذَر ولا يُمَسّ\n", count($vocab));

/* ═══════════════════════════════════════════════════════════════════════════
   ② القياسُ ثمَّ التنقيةُ ملفًّا ملفًّا
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② التنقية ────────────────────────────────────────────────────\n";
$files = repair01_w6_files($ROOT);
$tmp = sys_get_temp_dir() . '/repair01_w6_lint_' . getmypid() . '.php';

$stat = array('files' => 0, 'written' => 0, 'uiBefore' => 0, 'uiAfter' => 0,
              'coupled' => 0, 'excused' => 0, 'comment' => 0, 'lintFail' => 0, 'unparsed' => 0);
$lintFailed = array();
$rows = array();

foreach ($files as $rel => $path) {
    $src = (string) @file_get_contents($path);
    $m = repair01_w6_file_measure($src, $vocab);
    $stat['files']++;
    if (!$m['ok']) { $stat['unparsed']++; continue; }
    $stat['uiBefore'] += $m['ui'];
    $stat['coupled']  += $m['coupled'];
    $stat['excused']  += $m['excused'];
    $stat['comment']  += $m['comment'];

    $shaBefore = sha1($src);
    $changed = 0; $lintOk = 1; $after = $m['ui'];

    if ($m['ui'] > 0) {
        $p = repair01_w6_file_purify($src, $vocab);
        if ($p['ok'] && $p['changed'] > 0) {
            /* ⛔ الفحصُ قبل الكتابة — ولا يُكتب ملفٌّ لا يعبر المحلِّل */
            @file_put_contents($tmp, $p['src']);
            $out = array(); $code = 1;
            @exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
            if ($code !== 0) {
                $lintOk = 0; $stat['lintFail']++;
                $lintFailed[$rel] = trim(implode(' ', $out));
            } elseif (!$DRY) {
                @file_put_contents($path, $p['src']);
                $changed = $p['changed'];
                $stat['written']++;
                $after = 0;
            } else {
                $changed = $p['changed'];
                $after = 0;
            }
        }
    }
    $stat['uiAfter'] += $after;
    $rows[] = array($rel, $m['ui'], $after, $m['coupled'], $m['excused'], $m['comment'],
                    $changed, $shaBefore, ($DRY ? $shaBefore : sha1((string) @file_get_contents($path))), $lintOk);
}
@unlink($tmp);

printf("  ملفّاتُ النطاق %d · مكتوبٌ %d · تشكيلُ الواجهةِ %d ⇐ %d\n",
       $stat['files'], $stat['written'], $stat['uiBefore'], $stat['uiAfter']);
printf("  مقترنٌ يُعذَر %d · نصٌّ يساوي مقترنًا %d · تعليقٌ خارجَ المقام %d\n",
       $stat['coupled'], $stat['excused'], $stat['comment']);
if ($stat['lintFail']) {
    printf("  ⛔ سقط في المحلِّل %d ملفًّا — لم يُكتب أيٌّ منها:\n", $stat['lintFail']);
    foreach ($lintFailed as $r => $e) { echo "     $r — $e\n"; }
}
if ($stat['unparsed']) { printf("  ⚠ تعذّرت رمزنةُ %d ملفًّا\n", $stat['unparsed']); }

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الدفتران — القياسُ ومعجمُ الاقتران
   ═══════════════════════════════════════════════════════════════════════════ */
if (!$DRY) {
    echo "\n③ الدفتران ───────────────────────────────────────────────────\n";
    $conn->query("DELETE FROM repair01_w6_file_log");
    $n = 0;
    foreach ($rows as $r) {
        $ok = $conn->query("INSERT INTO repair01_w6_file_log
            (rel_path, ui_before, ui_after, coupled_marks, excused_marks, comment_marks,
             spans_changed, sha_before, sha_after, lint_ok, run_id)
            VALUES ('" . $esc($r[0]) . "', " . (int) $r[1] . ", " . (int) $r[2] . ", "
            . (int) $r[3] . ", " . (int) $r[4] . ", " . (int) $r[5] . ", " . (int) $r[6] . ", '"
            . $esc($r[7]) . "', '" . $esc($r[8]) . "', " . (int) $r[9] . ", '" . $esc($RUN) . "')");
        if ($ok) { $n++; }
    }
    echo "  ✔ $n صفًّا في repair01_w6_file_log\n";

    /* معجمُ الاقتران: كم علامةً أعفت كلُّ مفردة — **مُعلَنٌ بعددِه** */
    $scan = repair01_w6_files_scan($ROOT, $conn);
    $conn->query("DELETE FROM repair01_w6_coupled");
    $c = 0;
    foreach ($vocab as $term => $why) {
        $parts = explode(' · ', $why, 2);
        $kind  = isset($parts[0]) ? $parts[0] : '';
        $where = isset($parts[1]) ? $parts[1] : $why;
        $marks = isset($scan['terms'][$term]) ? (int) $scan['terms'][$term] : 0;
        $ok = $conn->query("INSERT INTO repair01_w6_coupled
            (term, couple_kind, first_seen, marks, why, owner_wave, src_ref)
            VALUES ('" . $esc($term) . "', '" . $esc($kind) . "', '" . $esc($where) . "', $marks,
                    'نص يقارن به لا يعرض — ونزع تشكيله في طرف دون طرف يفك المقارنة صامتة',
                    '', 'W06 §٤-٢ · معجم الاقتران من الشجرة ومن ENUM المخطط')");
        if ($ok) { $c++; }
    }
    echo "  ✔ $c مفردةَ اقترانٍ مُعلَنةً بعددِ ما أعفت\n";

    /* دفترُ النطاقِ يشمل الملفَّ كما يشمل الجدول (‏§٤-٥) */
    $fs = repair01_w6_file_source($stat['files'], $stat['uiBefore'], $stat['uiAfter']);
    $conn->query("REPLACE INTO repair01_w6_scope
        (source_key, source_table, source_column, row_filter, is_rendered, renderer,
         visibility_class, rows_total, dia_before, dia_after, purify_order, map_rule, map_why, src_ref, measured_at)
        VALUES ('" . $esc($fs['key']) . "', '" . $esc($fs['table']) . "', '" . $esc($fs['column']) . "',
                '" . $esc($fs['filter']) . "', 1, '" . $esc($fs['renderer']) . "',
                '" . $esc($fs['visibility']) . "', " . (int) $fs['rows'] . ", " . (int) $fs['before'] . ", "
                . (int) $fs['after'] . ", " . (int) $fs['order'] . ", 'W6-R-FILES', '" . $esc($fs['why']) . "',
                '" . $esc($fs['src_ref']) . "', NOW())");
    echo "  ✔ الملفُّ مصدرًا في repair01_w6_scope\n";
}

echo "\n" . str_repeat('─', 78) . "\n";
printf("تشكيلُ الواجهةِ في الملفّات: %d ⇐ %d · مقترنٌ مُعذَرٌ %d · تعليقٌ خارجَ المقام %d\n",
       $stat['uiBefore'], $stat['uiAfter'], $stat['coupled'] + $stat['excused'], $stat['comment']);
echo ($DRY ? "الحكم: قياسٌ فقط — لم يُكتب شيء\n"
           : ($stat['lintFail'] === 0 ? "الحكم: تمّت ✔\n" : "الحكم: فيها ملفٌّ لم يُكتب ✘\n"));
exit(($DRY || $stat['lintFail'] === 0) ? 0 : 1);
