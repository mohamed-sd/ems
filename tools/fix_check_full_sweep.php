<?php
/**
 * tools/fix_check_full_sweep.php — كلُّ قيدِ CHECK فُقد، مذكورًا كان أو غيرَ مذكور
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عيبٌ في أداتي السابقة**: `fix_missing_checks.php` تكنس أسماءَ القيودِ
 *   **المذكورةَ في الشجرة** — فما فُقد ولم يُذكر في شيفرةٍ ولا فاحصٍ ولا وثيقةٍ
 *   يبقى **خفيًّا تمامًا**. وقع فعلًا: `ck_ffe_fx_pair` و`ck_alloc_fx` فُقدا
 *   ولم يذكرهما أحدٌ باسمِهما، فلم تريهما الأداةُ — ورآهما فاحصُ `base_equivalent`
 *   عرَضًا لأنه يقيس **أثرَهما** (رمزَ الخطأ 3819).
 *
 * ◆ فالكنسُ الصحيحُ لا يبدأ من الشجرةِ بل من **نسخةِ ما قبلَ إعادةِ البناء**:
 *   كلُّ `CONSTRAINT x CHECK (...)` فيها يُقابَل بالحيّ. وهذا يغطي المذكورَ
 *   وغيرَ المذكورِ معًا — ولا يعتمد على أن أحدًا تذكّر أن يكتب الاسم.
 *
 * ◆ ولكلِّ مفقودٍ **يُقاس مخالفوه قبل الترميم** بنفيِ شرطِه على جدولِه، ولا
 *   يُضاف قيدٌ على بيانةٍ مخالفةٍ ولا تُعدَّل بيانةٌ لإرضاءِ قيد.
 *
 * ◆ گوتشا مقيسةٌ ومطبَّقة: النصُّ العربيُّ بمُقدِّم `_utf8mb4'…'` المنقولِ من
 *   `SHOW CREATE` **لا يعود** في `ADD CONSTRAINT` (اختلافُ الترتيبِ بين
 *   `collation_connection` و`collation_server`) — فتُنزَع المُقدِّماتُ من
 *   الحرفياتِ العربيةِ وحدَها.
 *
 * التشغيل: php tools/fix_check_full_sweep.php [--apply] [--md=<path>]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';
$db = fix_db();
$db->set_charset('utf8mb4');
/* ◆ **لا يُقتَل الكنسُ بقيدٍ واحدٍ معطوب**: نسخةُ 08-03 تحمل قيدًا يستعمل
     `regexp_like` — وهي دالةُ MySQL 8 **لا توجد في مارياDB**، فقياسُه يرمي
     ويقتل الجولةَ كلَّها. فيُطفأ الرميُ ويُقاس كلُّ قيدٍ على حِدةٍ، وما تعذّر
     قياسُه **يُعلَن باسمه** ولا يُخفى. (وقيدٌ بدالةٍ غيرِ موجودةٍ لم يكن يعمل
     على هذا المحرِّكِ أصلًا — وذاك بنفسِه خبرٌ يستحق الإعلان.) */
mysqli_report(MYSQLI_REPORT_OFF);
$apply = in_array('--apply', $argv, true);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

/* ── ① الحيُّ الآن ─────────────────────────────────────────────────────── */
$live = array();
$r = $db->query("SELECT CONSTRAINT_NAME n, TABLE_NAME t FROM information_schema.CHECK_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()");
while ($r && ($x = $r->fetch_assoc())) { $live[$x['n']] = $x['t']; }

/* ── ② كلُّ قيدٍ في نسخةِ ما قبلَ إعادةِ البناء ─────────────────────────── */
$dumps = glob($ROOT . '/database/baseline/auto_pre_up_20260803_*.sql');
sort($dumps);
$dump = $dumps ? $dumps[0] : null;
if (!$dump) { exit("لا نسخةَ 2026-08-03\n"); }

$was = array();
$curTable = '';
$fh = fopen($dump, 'r');
while (($line = fgets($fh)) !== false) {
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', $line, $m)) { $curTable = $m[1]; continue; }
    if (!preg_match('/^\s*CONSTRAINT `([^`]+)` CHECK \((.*)\),?\s*$/i', $line, $m)) { continue; }
    /* الأسماءُ التلقائيةُ لقيودِ json_valid ليست قواعدَ عملٍ — تُستثنى */
    if (stripos($m[2], 'json_valid') !== false) { continue; }
    $was[$m[1]] = array('table' => $curTable, 'clause' => rtrim(trim($m[2]), ','));
}
fclose($fh);

$missing = array();
foreach ($was as $n => $spec) { if (!isset($live[$n])) { $missing[$n] = $spec; } }

$L = array();
$L[] = '**القياس:** ' . date('Y-m-d H:i') . ' · النسخة: `' . basename($dump) . '`';
$L[] = '';
$L[] = 'قواعدُ عملٍ في النسخة: **' . count($was) . '** · حيّةٌ الآن: **'
     . (count($was) - count($missing)) . '** · **مفقودةٌ: ' . count($missing) . '**';
$L[] = '';
if (!$missing) {
    $L[] = '✔ لا قيدَ عملٍ فُقد — الحيُّ يغطّي كلَّ ما كان قبلَ إعادةِ البناء.';
} else {
    $L[] = '| # | القيد | الجدول | صفوف | **مخالفون** | الحال |';
    $L[] = '|---|---|---|---|---|---|';
}

$added = 0; $dirty = array(); $gone = array(); $i = 0;
foreach ($missing as $name => $spec) {
    $i++;
    $t = $spec['table'];
    /* نزعُ مُقدِّمِ الترميزِ من الحرفياتِ العربيةِ وحدَها (گوتشا مقيسة) */
    $clause = preg_replace_callback("/_utf8mb4'([^']*)'/u", static function ($m) {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $m[1]) ? "'" . $m[1] . "'" : $m[0];
    }, $spec['clause']);

    $ex = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $db->real_escape_string($t) . "'");
    if (!$ex || (int) $ex->fetch_row()[0] === 0) {
        $gone[$name] = $t;
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | **الجدولُ رُفع** | — | يُعلَن |';
        continue;
    }
    $rows = (int) $db->query("SELECT COUNT(*) FROM `{$t}`")->fetch_row()[0];
    $q = $db->query("SELECT COUNT(*) FROM `{$t}` WHERE NOT ({$clause})");
    if (!$q) {
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows
             . ' | **تعذّر القياس** | ' . mb_substr($db->error, 0, 40) . ' |';
        continue;
    }
    $bad = (int) $q->fetch_row()[0];
    if ($bad > 0) {
        $dirty[$name] = array('table' => $t, 'bad' => $bad, 'rows' => $rows, 'clause' => $clause);
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows . ' | **' . $bad . '** ⚠ | يُعلَن |';
        continue;
    }
    if ($apply) {
        if ($db->query("ALTER TABLE `{$t}` ADD CONSTRAINT `{$name}` CHECK ({$clause})") === false) {
            $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows . ' | 0 | ✘ '
                 . mb_substr($db->error, 0, 50) . ' |';
            continue;
        }
        $added++;
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows . ' | 0 ✔ | **رُمِّم** |';
    } else {
        $L[] = '| ' . $i . ' | `' . $name . '` | `' . $t . '` | ' . $rows . ' | 0 ✔ | جاهزٌ للترميم |';
    }
}

$L[] = '';
$L[] = $apply ? '**رُمِّم: ' . $added . '**' : '_قياسٌ فقط — أضِف `--apply` للترميم._';
if ($dirty) {
    $L[] = '';
    $L[] = '### بمخالفينَ أحياء — قرارُ مالكٍ لا قرارُ مُرحِّل';
    foreach ($dirty as $n => $h) {
        $L[] = '- **`' . $n . '`** · `' . $h['table'] . '` · **' . $h['bad'] . '** مخالفًا من ' . $h['rows'];
        $L[] = '  - `' . mb_substr($h['clause'], 0, 220) . '`';
    }
}
if ($gone) {
    $L[] = '';
    $L[] = '### جداولُ رُفعت (القيدُ زال معها بحق)';
    foreach ($gone as $n => $t) { $L[] = '- `' . $n . '` ← `' . $t . '`'; }
}

$out = implode("\n", $L);
echo "══════════════════════════════════════════════════════════════════════\n";
echo " كنسٌ شاملٌ لقيودِ CHECK — المذكورُ وغيرُ المذكور\n";
echo "══════════════════════════════════════════════════════════════════════\n";
echo $out . "\n";
if ($mdOut) { @file_put_contents($mdOut, "# كنسٌ شاملٌ لقيودِ CHECK\n\n" . $out . "\n"); }
exit((count($missing) - $added - count($dirty) - count($gone)) === 0 ? 0 : 1);
