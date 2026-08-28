<?php
/**
 * tools/repair01_freeze.php — تجميدُ اللقطةِ وفتحُ نافذةِ القياسِ الرسميّة
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ⑨** يوجب `Freeze` قبل `Full Reconciliation`، **والبندُ ⑬** يجعل
 * التعديلَ داخلَ النافذةِ ممنوعًا: «لا يجوز تعديلُ النظامِ أثناءَ نافذةِ قياسٍ
 * رسميّةٍ تُستخدم لإصدارِ `Baseline` أو تقريرِ إغلاق».
 *
 * ◆ **والتجميدُ لا يُعلَن بل يُستحَقّ** — فثلاثةُ شروطٍ تُقاس قبلَه:
 *   ① شجرةٌ مُلزَمةٌ نظيفة · ② انحدارٌ **يُشغَّل الآنَ**: أخضرُ للأساسِ · مختومُ الإحصاءِ للتشخيص ·
 *   ③ **لا لقطةَ مفتوحةً سابقة** — فنافذتان مفتوحتان معًا تجعلان «أيَّ لقطةٍ
 *      يمثّلها التقرير» سؤالًا بلا جواب.
 *
 * ⛔ **ولا يُعدَّل شيءٌ في هذه الأداة**: تقرأ وتختم. **والختمُ نفسُه ليس تعديلًا
 *   للنظام** — هو تسجيلُ حالتِه.
 *
 * التشغيل:
 *   php tools/repair01_freeze.php --purpose="..."     ← يُجمّد أساسًا — يشترط الخضرة
 *   php … --purpose="..." --kind=diagnostic          ← نافذةٌ تشخيصيّةٌ تختم الحمرةَ بأسمائها
 *   php tools/repair01_freeze.php --status            ← يُخبر
 *   php tools/repair01_freeze.php --release --why="…" ← يفكّ بسببٍ مكتوب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$arg = function ($k) use ($argv) {
    foreach ($argv as $a) { if (strpos($a, "--$k=") === 0) { return substr($a, strlen($k) + 3); } }
    return null;
};
$STATUS  = in_array('--status', $argv, true);
$RELEASE = in_array('--release', $argv, true);
/* نوعُ النافذة — والافتراضُ الأشدُّ `BASELINE` */
$kindArg = strtolower((string) $arg('kind'));
$DIAG    = ($kindArg === 'diagnostic');
if ($kindArg !== '' && !in_array($kindArg, array('baseline', 'diagnostic'), true)) {
    exit("⛔ نوعٌ غيرُ معروف: `$kindArg` — والمعروفُ `baseline` أو `diagnostic`\n");
}
$KIND = $DIAG ? 'DIAGNOSTIC' : 'BASELINE';

$git = function ($a) use ($ROOT) {
    $o = array(); exec('git -C ' . escapeshellarg($ROOT) . ' ' . $a . ' 2>&1', $o);
    return trim(implode(' ', $o));
};
$one = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? $r->fetch_row()[0] : null; };

/* ── الحالُ الراهن ────────────────────────────────────────────────────────── */
$open = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $open = $r->fetch_assoc(); }

if ($STATUS) {
    echo "\n═══ حالُ التجميد ═══\n";
    if (!$open) { echo "  ◆ **لا نافذةَ مفتوحة** — البناءُ مسموح.\n"; exit(0); }
    printf("  ⛔ **نافذةٌ مفتوحة**: %s\n", $open['snapshot_id']);
    printf("     الالتزام %s · المخطَّط %s · السجل %d · الإعداد %s · MT %s\n",
        substr($open['commit_hash'], 0, 8), $open['schema_version'],
        $open['registry_rows'], $open['config_baseline'],
        isset($open['measurement_tool_version']) ? $open['measurement_tool_version'] : '-');
    printf("     جُمِّدت %s · الغرض: %s\n", $open['frozen_at'], $open['purpose']);
    echo "  ⛔ **ولا يُعدَّل النظامُ حتّى تُفَكَّ بسببٍ مكتوب.**\n";
    exit(0);
}

if ($RELEASE) {
    if (!$open) { exit("◆ لا نافذةَ مفتوحةً تُفَكّ\n"); }
    $why = (string) $arg('why');
    if (trim($why) === '') { exit("⛔ **فكُّ التجميدِ يحتاج سببًا مكتوبًا** — والقاعدةُ تردُّ الفارغَ\n"); }
    $ok = $conn->query("UPDATE repair01_freeze_snapshot
        SET released_at = NOW(), release_why = '" . $e($why) . "'
      WHERE snapshot_id = '" . $e($open['snapshot_id']) . "'");
    echo $ok ? "✔ فُكَّ التجميدُ عن {$open['snapshot_id']} — بسببٍ مسجَّل\n" : "✘ " . $conn->error . "\n";
    exit($ok ? 0 : 1);
}

/* ══ التجميد — وثلاثةُ شروطٍ تُقاس قبلَه ═══════════════════════════════════ */
$purpose = (string) $arg('purpose');
if (trim($purpose) === '') { exit("⛔ **التجميدُ يحتاج غرضًا مُعلَنًا** — `--purpose=\"…\"`\n"); }

echo "\n═══ تجميدُ اللقطة — البندُ ⑨ ═══\n";
$fail = 0;
$chk = function ($ok, $m, $d = '') use (&$fail) {
    if ($ok) { echo "  ✔ $m" . ($d !== '' ? " — $d" : '') . "\n"; }
    else { $fail++; echo "  ✘ $m" . ($d !== '' ? " — $d" : '') . "\n"; }
};

/* ③ لا لقطةَ مفتوحةً سابقة */
$chk($open === null, 'لا نافذةَ قياسٍ مفتوحةً سلفًا',
     $open ? ('مفتوحةٌ منذ ' . $open['frozen_at'] . ' — ' . $open['snapshot_id']) : '');

/* ① شجرةٌ مُلزَمةٌ نظيفة */
$dirty = ($git('status --porcelain') !== '');
$chk(!$dirty, 'الشجرةُ مُلزَمةٌ نظيفة', $dirty ? '**متّسخة** — واللقطةُ لا تُثبت ما لم يُلزَم' : '');

/* ② الانحدارُ — يُشغَّل الآنَ دائمًا · وحكمُه بنوعِ النافذة
   ═══════════════════════════════════════════════════════════════════════
   ◆ **نافذتان لا نافذةٌ واحدة** — و`AMD-01` المرحلة ٥ يحكم: *«ولا تستخدمْ
     استثناءً لتجاوزِ قاعدةٍ لا تنطبق أصلًا — صحِّحْ قاعدةَ الانطباقِ نفسَها»*.
     فالنصُّ الدستوريُّ (البند ⑬) يمنع **التعديلَ أثناءَ** النافذة، **ولا يشترط
     الخضرةَ لدخولِها**. والخضرةُ شرطُ **إصدارِ أساسٍ معتمَد** لا شرطُ **قياسٍ
     تشخيصيّ** يُفتح ليقيسَ حمرةً قائمة.
   ⛔ **ولولا التصحيحُ لصار الشرطُ حاجزًا دائريًّا**: `MASTER_EXEC` §٢ يوجب
     تجميدًا قبل أيِّ قياس، وهذه الأداةُ تشترط خضرةً قبل التجميد، والخضرةُ
     تحتاج عملًا يُبنى على قياس. فلا مخرجَ من الدائرةِ إلّا بتصحيحِ الانطباق.
   ◆ **والتصحيحُ يشدُّ ولا يُرخي**: التشخيصيّةُ **تختم الإحصاءَ في اللقطةِ
     نفسِها بأسماءِ الساقطين**. فاليومَ تُجمَّد لقطةٌ ولا تحمل عن الانحدارِ
     حرفًا؛ وبعدَه **لا يدّعي تقريرٌ خضرةً يكذّبها ختمُ لقطتِه**.
   ◆ **والافتراضُ يبقى الأشدَّ**: من لم يُعلن `--kind=diagnostic` خضع للشرطِ
     القديمِ كما كان. */
$o = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/repair01_regression_run.php') . ' 2>&1', $o, $rc);
$sum = ''; $red = array();
foreach ($o as $l) {
    if (strpos($l, 'نجح') !== false) { $sum = trim($l); }
    /* أسماءُ الساقطين — تُلتقط من سطرِ الحكمِ لا من عدٍّ مجرَّد */
    if (mb_strpos($l, '✘') !== false && preg_match('~tools/([A-Za-z0-9_]+)\.php~', $l, $m)) {
        $red[$m[1]] = 1;
    }
}
$redList = implode(' · ', array_keys($red));
/* ⛔ **ولا يُقرأ التقريرُ المكتوبُ بدل التشغيل**: ملفٌّ على القرصِ قد يكون من
     التزامٍ سابق، **ولقطةٌ تستند إلى قياسٍ قديمٍ تختم حالةً لم تعد قائمة**. */
if ($DIAG) {
    /* ⛔ **والتشخيصُ لا يعني تصديقَ أيِّ مخرَج**: لا بدَّ أن يُنتج المُشغِّلُ
         إحصاءً مقروءًا — فمُشغِّلٌ انهار بلا سطرِ حصيلةٍ ليس قياسًا. */
    $chk($sum !== '', 'الانحدارُ **شُغِّل الآنَ وأنتج إحصاءً مقروءًا** — والحكمُ عليه لا به', $sum);
    if ($redList !== '') {
        echo "     ◆ **الساقطون يُختمون في اللقطةِ بأسمائهم** — ولا تقريرٌ عنها يدّعي خضرة:\n";
        echo "       $redList\n";
    }
} else {
    $chk($rc === 0, 'الانحدارُ الشاملُ أخضرُ **مُشغَّلًا الآنَ لا مقروءًا من ملفّ**', $sum);
}
$census = ($sum !== '' ? preg_replace('~\*\*~', '', $sum) : 'لا إحصاء')
        . ($redList !== '' ? ' · الساقطون: ' . $redList : ' · لا ساقط');
if (mb_strlen($census) > 500) { $census = mb_substr($census, 0, 497) . '…'; }

if ($fail) {
    echo "\n⛔ **لم يُجمَّد** — والتجميدُ لا يُعلَن بل يُستحَقّ.\n";
    exit(1);
}

/* ── البصمةُ الخماسيّة ────────────────────────────────────────────────────── */
$commit = $git('rev-parse HEAD');
$branch = $git('rev-parse --abbrev-ref HEAD');
$tbl = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
$col = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
$reg = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry");
/* بصمةُ الإعداد: أسماءُ مفاتيحِ البيئةِ الحاكمةِ لا قيمُها ⛔ فالقيمُ أسرار */
$envFile = $ROOT . '/.env';
$keys = array();
if (is_file($envFile)) {
    foreach (file($envFile) as $l) {
        if (preg_match('~^\s*([A-Z0-9_]+)\s*=~', $l, $m)) { $keys[] = $m[1]; }
    }
}
sort($keys);
$cfg = substr(sha1(implode(',', $keys) . '|' . PHP_VERSION), 0, 16);
/* ── السادسةُ: بصمةُ عُدّةِ القياس ─────────────────────────────────────────
   ◆ **ما تجيبه**: `MASTER_EXEC` §٢② يوجب `Measurement Tool Version` سادسةً،
     و`RPR-02` §١٠ يعلّل: «أدواتُ القياسِ نفسُها موثَّقةٌ في المستودع فيُعرَف ما
     الذي قاس». **فلقطتان ببصمةِ التزامٍ واحدةٍ وعُدّتَين مختلفتَين تُنتجان
     رقمَين** — وبلا هذا العمودِ يصير الفرقُ سؤالًا بلا جواب.
   ⛔ **ولا تكفي بصمةُ الالتزام**: هي تُثبت أنَّ العُدّةَ لم تتغيّر، ولا تقول أيَّ
     عُدّةٍ قاست ولا كم ملفًّا فيها — وتقاريرُ القياسِ تُقتبس منفصلةً عن شجرتِها
     فيبقى الرقمُ بلا نسب.
   ◆ **والمقامُ مطبوعٌ لا مُدَّعًى**: عددُ الملفّاتِ يُكتب بجانبِ البصمة، فبصمةٌ
     على صفرِ ملفٍّ تُفضح بنفسِها ولا تمرُّ خضراءَ صامتة. */
$toolFiles = array();
foreach (array('tools', 'tests') as $dir) {
    $base = $ROOT . '/' . $dir;
    if (!is_dir($base)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,
        FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($ROOT) + 1));
        $toolFiles[$rel] = sha1_file($f->getPathname());
    }
}
/* ⛔ **وعُدّةٌ فارغةٌ لا تُختم**: بصمةُ لا شيءٍ ثابتةٌ وتبدو سليمة. */
if (!$toolFiles) { exit("⛔ **صفرُ ملفِّ عُدّةٍ مقيس** — ولا تُختم لقطةٌ بعُدّةٍ لا وجودَ لها\n"); }
ksort($toolFiles);
$mtvParts = array();
foreach ($toolFiles as $k => $v) { $mtvParts[] = $k . ':' . $v; }
$mtv = 'MT-' . substr(sha1(implode("\n", $mtvParts)), 0, 12) . '/' . count($toolFiles);

$sid = 'SNAP-' . substr($commit, 0, 8) . '-' . date('Ymd-His');

$ok = $conn->query("INSERT INTO repair01_freeze_snapshot
    (snapshot_id, commit_hash, branch, schema_version, registry_rows, config_baseline,
     measurement_tool_version, frozen_at, frozen_by, purpose, window_kind, regression_census)
    VALUES ('" . $e($sid) . "', '" . $e($commit) . "', '" . $e($branch) . "',
            '" . $e($tbl . 'T/' . $col . 'C') . "', $reg, '" . $e($cfg) . "',
            '" . $e($mtv) . "', NOW(), 'repair01_freeze.php', '" . $e($purpose) . "',
            '" . $e($KIND) . "', '" . $e($census) . "')");
if (!$ok) { exit("✘ " . $conn->error . "\n"); }

echo "\n────────────────────────────────────────────────────────────\n";
printf("✔ **جُمِّدت اللقطة** `%s`\n", $sid);
printf("   `Commit Hash`      %s (%s)\n", $commit, $branch);
printf("   `Schema Version`   %dT/%dC\n", $tbl, $col);
printf("   `Registry Version` %d صفًّا\n", $reg);
printf("   `Config Baseline`  %s (%d مفتاحَ بيئةٍ · PHP %s)\n", $cfg, count($keys), PHP_VERSION);
printf("   `Measure Tool Ver` %s\n", $mtv);
printf("   `Frozen At`        %s\n", date('Y-m-d H:i:s'));
printf("   `Window Kind`      %s\n", $KIND);
printf("   `Regression`       %s\n", $census);
printf("   `Purpose`          %s\n", $purpose);
echo "\n⛔ **نافذةُ القياسِ مفتوحةٌ الآن — والتعديلُ ممنوعٌ حتّى تُفَكّ بسببٍ مكتوب.**\n";
