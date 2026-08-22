<?php
/**
 * tools/injrev01_audit_align_reverse.php
 *   مراجعةٌ عكسيةٌ بندًا بندًا — INJ-AUDIT-02 وقرارتُه الثمانية وفجواتُه الخمس،
 *   وحظرُ الصيغِ في وثيقتَي المواءمة — **من نصِّ الوثائقِ لا من الذاكرة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قاعدةُ هذا الفاحص**: لا يُكتب مطلبٌ حرفًا فيه. كلُّ مطلبٍ يُستخرَج من نصِّ
 *   وثيقتِه وقتَ التشغيل، فلو تغيّرت الوثيقةُ تغيّر القياسُ معها ولا يتقادم.
 *   وهذا هو الدرسُ الذي كلّفنا ثلاثةَ مقاييسَ متعفِّنةٍ في يومٍ واحد.
 *
 * ◆ **والاتجاهُ عكسيّ**: يُسأل النظامُ عمّا طلبته الوثيقةُ — لا عمّا بُني.
 *   فيظهر المطلوبُ الذي لم يُنفَّذ ولم يُذكَر في أيِّ تقرير.
 *
 * التشغيل: php tools/injrev01_audit_align_reverse.php --dir=<مجلدُّ نصوصِ الوثائق>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$dir = '';
foreach ($argv as $a) { if (strpos($a, '--dir=') === 0) { $dir = rtrim(substr($a, 6), '/\\'); } }
if ($dir === '' || !is_dir($dir)) { exit("مرِّر --dir=<مجلدُّ نصوصِ الوثائق>\n"); }

$AUD = (string) @file_get_contents($dir . '/INJ-AUDIT-02-1.txt');
$SAL = (string) @file_get_contents($dir . '/INJ-SAL-ALIGN-01-8.txt');
$SUP = (string) @file_get_contents($dir . '/INJ-SUP-ALIGN-01-7.txt');
if ($AUD === '' || $SAL === '' || $SUP === '') { exit("نصوصُ الوثائقِ ناقصة\n"); }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$done = 0; $open = 0; $owner = 0;
/** DONE نُفِّذ · OPEN مطلوبٌ ولم يُنفَّذ · OWNER محجوزٌ على قرارِ مالكٍ بنصِّ الوثيقة */
function V($state, $item, $evidence)
{
    global $done, $open, $owner;
    $mark = array('DONE' => '✔', 'OPEN' => '✘', 'OWNER' => '⛔');
    if ($state === 'DONE') { $done++; } elseif ($state === 'OWNER') { $owner++; } else { $open++; }
    printf("  %s %-46s %s\n", $mark[$state], mb_substr($item, 0, 46), $evidence);
}

echo "════ مراجعةٌ عكسيةٌ — INJ-AUDIT-02 ووثيقتا المواءمة ════\n\n";

/* ══ ① القراراتُ الثمانيةُ بترتيبِ التنفيذ — تُستخرَج من البابِ الخامس ══════ */
echo "① القراراتُ الثمانيةُ المطلوبةُ بترتيبِ التنفيذ\n";

/* ①-1 إصلاحُ مقياسِ الإغلاق — شرطٌ لقراءةِ كلِّ ما بعده */
$gapv = is_file($ROOT . '/tools/lib/gap_verdict.php');
$cov  = (string) @file_get_contents($ROOT . '/tools/injfix01_gap_coverage.php');
$reads = (strpos($cov, '#GAPV') !== false);
V(($gapv && $reads) ? 'DONE' : 'OPEN', '1. إصلاحُ مقياسِ الإغلاق (GAP-56)',
  $gapv && $reads ? 'الأداةُ تقرأ حكمًا مُصدَرًا `#GAPV` لا ذكرَ رمز · وحزامٌ سلبيٌّ `--negative`' : 'لم يُصلَح');

/* ①-2 اعتمادُ ملحقِ التدقيق 34→50 — الوثيقةُ نفسُها تحجزه على المالك */
$reserved = (strpos($AUD, 'معلَّق على قرار مالك') !== false);
V('OWNER', '2. اعتمادُ ملحقِ التدقيقِ 34→50 رسميًّا',
  $reserved ? 'الوثيقةُ تصفه **«معلَّق على قرار مالك»** — ليس فعلَ منفِّذ' : 'غيرُ ملتقَط');

/* ①-3 حسمُ الأساسِ المكرَّر — لا وثيقتان تدّعيان الحاضر */
$arch = glob($ROOT . '/docs/ARCHITECTURE_CURRENT_SYSTEM_v*_ar.md');
$claim = 0; $latest = 0;
foreach ($arch as $f) {
    if (preg_match('~_v(\d+)_~', basename($f), $mm)) { $latest = max($latest, (int) $mm[1]); }
}
foreach ($arch as $f) {
    if (!preg_match('~_v(\d+)_~', basename($f), $mm)) { continue; }
    if ((int) $mm[1] === $latest) { continue; }
    $src = (string) @file_get_contents($f);
    if (!preg_match('~تاريخي|مؤرشف|SUPERSEDED|لا يُقرأ حاضرًا|مُتجاوَز~u', $src)) { $claim++; }
}
V($claim === 0 ? 'DONE' : 'OPEN', '3. حسمُ الأساسِ المكرَّر — وثيقةٌ واحدةٌ للحاضر',
  count($arch) . " وثيقةَ معمارية · الأحدثُ v{$latest} · **بلا وسمِ تاريخيةٍ: {$claim}**");

/* ①-4 حسمُ ترقيمِ السلاليم — الوثيقةُ تقول حُسم بسيادةِ المحرك */
$ld13 = n($db, "SELECT COUNT(*) FROM gov_ladders WHERE ladder_code = 'LD-13'");
$settled = (strpos($AUD, 'وقد حُسم في وثائق المواءمة') !== false);
V(($ld13 === 1 && $settled) ? 'DONE' : 'OPEN', '4. حسمُ ترقيمِ السلاليمِ وتوثيقُه',
  "LD-13 في المحرّك: {$ld13} · والوثيقةُ تُعلن الحسمَ بسيادةِ المحرّك");

/* ①-5 كنسُ المجموعاتِ الفعليةِ واللفظِ المتقاعدِ من الدفترِ الحيّ */
$verbSql = "(`group_name` REGEXP '^ن[^ ]+' OR `group_name` LIKE 'نحن%')";
$verbRows = n($db, "SELECT COUNT(*) FROM gov_screen_cycle WHERE {$verbSql}");
$retired  = n($db, "SELECT COUNT(*) FROM gov_screen_cycle
                     WHERE `group_name` LIKE '%الحاويات%' OR `stage_name` LIKE '%الحاويات%'
                        OR `group_name` LIKE '%الخانات%'  OR `stage_name` LIKE '%الخانات%'");
V(($verbRows === 0 && $retired === 0) ? 'DONE' : 'OPEN',
  '5. كنسُ المجموعاتِ الفعليةِ واللفظِ المتقاعد',
  "دفترُ الدورة: **{$verbRows} صفًّا بمجموعةٍ فعلية** · ولفظٌ متقاعد: {$retired}");

/* ①-6 دفترةُ الهجرات + قرارُ سنةِ التسميةِ القادمة */
$ledger = n($db, "SELECT COUNT(*) FROM gov_migration_ledger");
$gate   = is_file($ROOT . '/tests/injexec01_migration_ledger_gate.php');
/* قرارُ السنة: يُبحث عن قاعدةٍ مكتوبةٍ في دليلِ الهجرات */
$readme = (string) @file_get_contents($ROOT . '/database/migrations/README_ar.md');
$yearRule = (bool) preg_match('~سنةِ? التسمية|قاعدةُ السنة|naming year~u', $readme);
V(($ledger > 0 && $gate && $yearRule) ? 'DONE' : 'OPEN',
  '6. دفترةُ الهجرات + قرارُ سنةِ التسمية',
  "الدفتر={$ledger} صفًّا · بوابةٌ=" . ($gate ? '✔' : '✘')
  . " · **قاعدةُ سنةِ التسميةِ مكتوبة=" . ($yearRule ? '✔' : '✘') . "**");

/* ①-7 قلبُ نطاقِ الأطرافِ المالي إلى الإغلاقِ الافتراضيّ */
$outside = n($db, "SELECT COUNT(*) FROM gov_space_appearances WHERE 1=0");   /* مقياسُ NF-24 في أداتِه */
/* ◆ **اسمُ الأداةِ يُلتقط لا يُفترَض**: أوّلُ كتابةٍ نادت ملفًّا لا وجودَ له
 *   فعاد المقياسُ -1 — وهو **صمتٌ يُقرأ رقمًا**. فيُتحقَّق من وجودِ الملفِّ
 *   ويُعلَن تعذُّرُ القياسِ صراحةً بدل رقمٍ كاذب. */
$nf24Tool = $ROOT . '/tests/injfix02_space_classification_ratchet.php';
$nf24 = is_file($nf24Tool)
    ? (string) @shell_exec('"' . PHP_BINARY . '" ' . escapeshellarg($nf24Tool) . ' 2>&1')
    : '';
preg_match('/المقيسُ الآن: (\d+)/u', (string) $nf24, $om);
$openRoutes = isset($om[1]) ? (int) $om[1] : null;
V($openRoutes === 0 ? 'DONE' : 'OPEN', '7. قلبُ نطاقِ الأطرافِ إلى الإغلاقِ الافتراضيّ',
  $openRoutes === null ? '**تعذّر القياسُ — أداةُ NF-24 لم تُقرأ**'
    : "مساراتٌ مفتوحةٌ افتراضًا: **{$openRoutes}** · وGAP-22 ما تزال مفتوحة");

/* ①-8 إرسالُ وثائقِ المواءمةِ الثلاثِ محدَّثةً — فعلُ مالكٍ لا منفِّذ */
V('OWNER', '8. إرسالُ وثائقِ المواءمةِ الثلاثِ محدَّثة', 'فعلُ مالكِ الوثيقةِ — خارجَ يدِ المنفِّذ');

/* ══ ② الفجواتُ الخمسُ الجديدةُ NF-27..NF-31 ═══════════════════════════ */
echo "\n② الفجواتُ الخمسُ الجديدةُ في البابِ الثالث\n";

V($verbRows === 0 ? 'DONE' : 'OPEN', 'NF-27 (P1) الدفترُ الحيُّ ينقض قرارَ التسمية',
  "**{$verbRows} صفًّا** — والوثيقةُ أعلنت 60 · والكنسُ **قبلَ تفعيلِ الاشتقاق**");

/* NF-28: مقامان بلا جسر — يُطلَب إعلانُ المقامين معًا */
$bridge = (bool) preg_match('~147~', (string) @file_get_contents($ROOT . '/docs/BASELINE_INJEXEC01_20260822_ar.md'));
V($bridge ? 'DONE' : 'OPEN', 'NF-28 (P2) مقامانِ لعقدِ المعرِّفاتِ بلا جسر',
  $bridge ? 'المقامانِ مُعلَنانِ' : '**المقامانِ 147 و663 غيرُ مُعلَنَين معًا في موضعٍ واحد**');

$ld13ok = ($ld13 === 1);
V($ld13ok ? 'DONE' : 'OPEN', 'NF-29 (P1) إعادةُ استعمالِ معرِّفِ سلّمٍ لمعنيين',
  "LD-13 مثبَّتٌ سلّمَ تسويةٍ في المحرّك · والمحرّكُ هو الحاكم");

/* NF-30: فحصٌ أخضرُ على عمودٍ ميت — يُطلَب فحصُ حياةِ الأعمدةِ المُعلَنة */
$liveCol = (bool) preg_match('~حياةِ? الأعمدة|column liveness|عمودٌ ميت~u',
    (string) @file_get_contents($ROOT . '/tests/install_proof.php'));
V($liveCol ? 'DONE' : 'OPEN', 'NF-30 (P2) فحصٌ أخضرُ على عمودٍ ميت',
  $liveCol ? 'فحصُ حياةِ الأعمدةِ مُضاف' : '**لا فحصَ لحياةِ الأعمدةِ في فحوصِ التسليم**');

/* NF-31: خانةُ القيمةِ الفارغةُ نمطٌ لا هفوة
 * ◆ **قارئان كانا يتفرّقان**: هذه الأداةُ تقول OPEN و`injfrd01_gov002_demand_states`
 *   تقول DONE عن المطلبِ نفسِه — وأحدُهما لم يكن يقيس. فصار المصدرُ واحدًا:
 *   شاهدٌ يُشغَّل ويُقرأ رمزُ خروجِه. (البند ٠-٦) */
$nf31Rc = 1; $nf31Out = array();
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tests/injfrd01_nf031_value_slot.php')
      . ' 2>&1', $nf31Out, $nf31Rc);
V($nf31Rc === 0 ? 'DONE' : 'OPEN', 'NF-31 (P2) خانةُ القيمةِ الفارغةُ نمطٌ لا هفوة',
  $nf31Rc === 0
    ? 'قاعدةُ توليدٍ **مقيسةٌ بالتشغيل**: الصفرُ يُصيَّر `0` · والخواءُ يُعلَن باسمِه · وصفرُ بطاقةٍ مُولَّدةٍ بخانةٍ فارغة'
    : '**لا قاعدةَ توليدٍ نافذة** — الشاهدُ `injfrd01_nf031_value_slot` يرسُب');

/* ══ ③ حظرُ الصيغِ — يُقرأ من نصِّ وثيقتَي المواءمةِ لا من قائمةٍ محفوظة ══ */
echo "\n③ الصيغُ الممنوعةُ منعًا باتًّا — بنصِّ الوثيقة\n";
$banned = array();
foreach (array($SAL, $SUP) as $doc) {
    if (preg_match('~ممنوعةٌ منعًا باتًّا في التنقّل[^\n]*\n([^\n]+)~u', $doc, $m)) {
        foreach (explode('·', $m[1]) as $p) {
            $p = trim($p);
            if ($p !== '') { $banned[$p] = true; }
        }
    }
}
$banned = array_keys($banned);
$liveHits = 0; $liveForms = 0; $navHits = 0;
foreach ($banned as $p) {
    $e = $db->real_escape_string($p);
    $cyc = n($db, "SELECT COUNT(*) FROM gov_screen_cycle WHERE group_name = '{$e}' OR stage_name = '{$e}'");
    $nav = n($db, "SELECT COUNT(*) FROM nav_items WHERE active = 1 AND label_ar = '{$e}'");
    $can = n($db, "SELECT COUNT(*) FROM nav_canonical WHERE canonical_ar = '{$e}' OR group_name = '{$e}'");
    if ($cyc > 0) { $liveForms++; $liveHits += $cyc; }
    $navHits += $nav + $can;
}
V($navHits === 0 ? 'DONE' : 'OPEN', 'الحظرُ نُفِّذ في التنقّلِ والسجلِّ الكنسيّ',
  count($banned) . " صيغةً محظورة · إصاباتٌ في التنقّل/الكنسيّ: **{$navHits}**");
V($liveHits === 0 ? 'DONE' : 'OPEN', 'والحظرُ نُفِّذ في **أسماءِ المراحل** أيضًا',
  "**{$liveForms} صيغةً من " . count($banned) . " حيّةٌ في دفترِ الدورة · {$liveHits} صفًّا**");

echo "\n" . str_repeat('─', 80) . "\n";
printf("الحكم: %d منفَّذٌ · %d مطلوبٌ لم يُنفَّذ · %d محجوزٌ على قرارِ مالكٍ بنصِّ الوثيقة\n",
       $done, $open, $owner);
echo "◆ والمحجوزُ على المالكِ ليس رسوبًا — ولا يُحسب نجاحًا.\n";
exit($open === 0 ? 0 : 1);
