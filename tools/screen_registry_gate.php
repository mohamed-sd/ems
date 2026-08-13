<?php
/**
 * tools/screen_registry_gate.php — INJ-0494: مقامُ «الشاشة» واحدٌ أو لا يُعلَن
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0494
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطلُ المقيس: أربعةُ جرودٍ حيةٍ تعطي أربعةَ أعدادٍ لـ«عددِ الشاشات»
 *   (`inheader` · `insidebar` · `ems_shell_axes` · `modules`) — فكلُّ نسبةِ
 *   تبنٍّ تُحسب على مقامٍ مختلفٍ عن أختِها، وتقاريرُ الجاهزيةِ تتناقض بلا سببٍ
 *   ظاهر. **وعيبُ مقامٍ يُفسد كلَّ نسبةٍ تُبنى عليه.**
 *
 * ◆ فتُقاس المجموعتان ويُخرَج الفرقُ في **الاتجاهين**، ولكلِّ اتجاهٍ معناه:
 *     ① **ملفٌّ حيٌّ بلا صفٍّ في `modules`** — وهذا أخطرُهما: حارسُ الشاشةِ
 *        يقرأ صلاحيتَه من السجل، فالسطحُ غيرُ المسجَّلِ إما يُغلق (فيُفقد) أو
 *        **يُفتح بلا صلاحية** — وهو جذرُ ثغرةِ SEC المسجَّلةِ في هذا المشروع.
 *     ② **صفٌّ في `modules` بلا ملفّ** — منحةٌ لبابٍ لا يوجد: تشوّش الجردَ
 *        وتُمنح صلاحيةً لمعدوم.
 *
 * ◆ ولا يُخلط «سطحٌ يُصيَّر شاشةً» بـ«ملفِّ PHP»: الشاشةُ ما **يُضمِّن**
 *   `insidebar.php` (فذاك ما يُنتج قشرةً وقائمةً). والمعالجاتُ وملفاتُ AJAX
 *   والخدماتُ ليست شاشاتٍ ولو كانت مسجَّلةً في `modules` لغرضِ الحارس.
 *
 * التشغيل: php tools/screen_registry_gate.php [--md=مسار] [--verbose]
 * يعيد 0 إن كان الفرقان صفرًا، و1 إن بقي فرقٌ (fail-closed).
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$mdOut = null; $verbose = false;
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); }
    if ($a === '--verbose') { $verbose = true; }
}
$L = array();
function o($s = '') { global $L; $L[] = $s; echo $s . "\n"; }

/* ══ ① مجموعةُ الأسطحِ الحية — ما يُضمِّن `insidebar.php` ═══════════════ */
/* ◆ **تضييقٌ مقيسٌ لا مُفترَض:** أولُ جولةٍ أبلغت ستةَ أسطحٍ حيةٍ بلا صفٍّ،
     **خمسةٌ منها ليست شاشاتٍ**: `includes/topbar.php` · `includes/u13_screen_kit.php`
     · `includes/dept_gov_space.php` · `includes/fin_analysis_shell.php` (أغلفةٌ
     يُضمِّنها غيرُها فلا تُبلَغ بمسارٍ ولا يحرسها حارسُ شاشة) و`examples/…` (مثالٌ
     لا إنتاج). فبقي **واحدٌ حقيقيّ**.
   ◆ والمقامُ الصحيحُ إذًا: **ما يُضمِّن القشرةَ ويُبلَغ بمسارٍ** — فيُستثنى
     `includes/` و`examples/`. ولو تُركا لأُنتج «دَينٌ وهميٌّ» يُنفَق عليه عملٌ. */
$SKIP = array('/vendor/', '/storage/', '/.claude/', '/node_modules/', '/.git/',
              '/tools/', '/tests/', '/docs/', '/database/',
              '/includes/', '/examples/');
$live = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { continue 2; } }
    $src = (string) file_get_contents($p);
    /* التضمينُ بأشكالِه: include/require · بمسارٍ نسبيٍّ أو __DIR__ */
    if (!preg_match('/(?:include|require)(?:_once)?\s*\(?\s*[^;]{0,80}insidebar\.php/u', $src)) { continue; }
    $live[ltrim(substr($p, strlen($ROOT)), '/')] = 1;
}

/* ══ ② مجموعةُ السجل — `modules.code` ═════════════════════════════════ */
$reg = array(); $regInactive = 0;
$rs = $db->query('SELECT code FROM modules');
while ($rs && ($r = $rs->fetch_row())) {
    $c = trim((string) $r[0]);
    if ($c === '') { continue; }
    $reg[str_replace('\\', '/', $c)] = 1;
}

/* ══ ③ الفرقُ في الاتجاهين ════════════════════════════════════════════ */
$liveNoRow = array_diff_key($live, $reg);          // ① سطحٌ حيٌّ بلا صفّ
$rowNoFile = array();                               // ② صفٌّ بلا ملفّ
foreach (array_keys($reg) as $code) {
    if (!is_file($ROOT . '/' . $code)) { $rowNoFile[$code] = 1; }
}
/* صفٌّ لملفٍّ قائمٍ لكنه ليس شاشةً (لا يُضمِّن insidebar) — يُعلَن ولا يُحسب عطلًا:
   المعالجاتُ تُسجَّل عمدًا لأن حارسَ الأفعالِ يقرأ منها. */
$rowNotScreen = array();
foreach (array_keys($reg) as $code) {
    if (isset($rowNoFile[$code]) || isset($live[$code])) { continue; }
    $rowNotScreen[$code] = 1;
}

o('══════════════════════════════════════════════════════════════════════');
o(' جردُ الشاشات: مقامٌ واحدٌ — ' . date('Y-m-d H:i'));
o('══════════════════════════════════════════════════════════════════════');
o('');
o('| المجموعة | العدد | ما هي |');
o('|---|---:|---|');
o('| **أسطحٌ حيةٌ** (تُضمِّن `insidebar.php`) | **' . count($live) . '** | ما يُصيَّر شاشةً بقشرةٍ وقائمة |');
o('| **صفوفُ `modules`** | **' . count($reg) . '** | سجلُّ الصلاحياتِ — يشمل معالجاتٍ لا شاشاتٍ فقط |');
o('');
o('| الفرق | العدد | معناه |');
o('|---|---:|---|');
o('| ① **سطحٌ حيٌّ بلا صفٍّ في السجل** | **' . count($liveNoRow) . '** | حارسُ الشاشةِ بلا مرجع ⇒ يُغلق فيُفقد أو **يُفتح بلا صلاحية** |');
o('| ② **صفٌّ بلا ملفٍّ على القرص** | **' . count($rowNoFile) . '** | منحةٌ لبابٍ لا يوجد |');
o('| ③ صفٌّ لملفٍّ قائمٍ ليس شاشةً | ' . count($rowNotScreen) . ' | معالجاتٌ وAJAX — **مسجَّلةٌ عمدًا** لحارسِ الأفعال (لا عطل) |');

if ($liveNoRow) {
    o('');
    o('### ① أسطحٌ حيةٌ بلا صفٍّ في `modules`');
    $i = 0;
    foreach (array_keys($liveNoRow) as $k) {
        if (!$verbose && ++$i > 20) { o('- … و' . (count($liveNoRow) - 20) . ' غيرها (`--verbose` للكل)'); break; }
        o('- `' . $k . '`');
    }
}
if ($rowNoFile) {
    o('');
    o('### ② صفوفٌ بلا ملفٍّ على القرص');
    $i = 0;
    foreach (array_keys($rowNoFile) as $k) {
        if (!$verbose && ++$i > 20) { o('- … و' . (count($rowNoFile) - 20) . ' غيرها'); break; }
        o('- `' . $k . '`');
    }
}

/* ══ ④ الحكم ══════════════════════════════════════════════════════════ */
$ok = (count($liveNoRow) === 0 && count($rowNoFile) === 0);
o('');
o(str_repeat('═', 70));
o('الحكم: ' . ($ok ? '✔ صفرُ فرقٍ في الاتجاهين — المقامُ واحدٌ ومُعلَن'
                  : '✘ فرقٌ باقٍ: ' . count($liveNoRow) . ' سطحًا بلا صفٍّ · ' . count($rowNoFile) . ' صفًّا بلا ملفّ'));
o('');
o('◆ **المقامُ المُعلَنُ لنسبِ الشاشات: ' . count($live) . '** (أسطحٌ حيةٌ تُضمِّن القشرة).');
o('  ولا يُستعمل `modules` (' . count($reg) . ') مقامًا لنسبةِ شاشاتٍ — فهو سجلُّ');
o('  صلاحياتٍ يشمل ' . count($rowNotScreen) . ' معالجًا ليس شاشةً.');
o(str_repeat('═', 70));

if ($mdOut) { file_put_contents($mdOut, "# جردُ الشاشات — مقامٌ واحد\n\n" . implode("\n", $L) . "\n"); echo "\nكُتب: {$mdOut}\n"; }
exit($ok ? 0 : 1);
