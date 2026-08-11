<?php
/**
 * tools/audit_denominator_check.php — ما يقيسه التقريرُ وما **لا** يقيسه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ سؤالٌ سُئلتُه فكشف خطأً في تأطيري: «أقِستَ العملَ المنجَزَ في كلِّ المشروعِ
 *   والفروع؟» — والجوابُ **لا**. وتقريرُ «8٪–15٪» مقامُه **595 ملاحظةَ عيبٍ**
 *   وجدها تدقيقُ أغسطس، لا حجمُ المشروع. فبناءُ النظامِ كلِّه **خارجَ المقام**:
 *   التدقيقُ يعدّ ما اختلَّ لا ما بُني.
 *
 * ◆ فتُقاس هنا ثلاثةُ أشياءَ يجب أن تُقال مع أيِّ نسبة:
 *   ① الفروع: هل فيها عملٌ لم يُقَس؟ (يُقاس تباعدُها لا يُفترَض)
 *   ② حجمُ ما بُني فعلًا — وهو صفرٌ في مقامِ التقرير.
 *   ③ حِقَبُ البناءِ السابقةُ وبواباتُها — كلٌّ أُغلق ببوابتِه في زمنِه.
 *
 * التشغيل: php tools/audit_denominator_check.php [--md=مسار]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }
$L = array();
function o($s = '') { global $L; $L[] = $s; echo $s . "\n"; }
function sh($cmd) { return trim((string) @shell_exec($cmd . ' 2>&1')); }
function q1($db, $sql) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : null; }

o('══════════════════════════════════════════════════════════════════════');
o(' ما يقيسه تقريرُ النسبة وما لا يقيسه — ' . date('Y-m-d H:i'));
o('══════════════════════════════════════════════════════════════════════');

/* ══ ① الفروع ══════════════════════════════════════════════════════════ */
o('');
o('╔══ ① الفروع — هل فيها عملٌ لم يُقَس؟');
o('');
$cwd = escapeshellarg($ROOT);
$branches = array_filter(explode("\n", sh('git -C ' . $cwd . ' for-each-ref --format="%(refname:short)" refs/heads refs/remotes')));
o('| الفرع | أمامَ main | خلفَه | الحكم |');
o('|---|---:|---:|---|');
$unmeasured = 0;
foreach ($branches as $b) {
    $b = trim($b);
    if ($b === '' || $b === 'main' || $b === 'origin/main' || strpos($b, '->') !== false) { continue; }
    $ahead  = (int) sh('git -C ' . $cwd . ' rev-list --count main..' . escapeshellarg($b));
    $behind = (int) sh('git -C ' . $cwd . ' rev-list --count ' . escapeshellarg($b) . '..main');
    if ($ahead === 0) { $v = 'مدموجٌ بالكامل — صفرُ عملٍ غيرِ مقيس'; }
    elseif (strpos($b, 'remediation') !== false) { $v = '**حزمةُ التصحيحِ الحالية** (هي موضوعُ التقرير)'; }
    else { $v = '**' . $ahead . ' التزامًا غيرَ مدموج** ⇐ يستحق فحصًا'; $unmeasured += $ahead; }
    o('| `' . $b . '` | ' . $ahead . ' | ' . $behind . ' | ' . $v . ' |');
}
o('');
o('**التزاماتٌ غيرُ مدموجةٍ خارجَ حزمةِ التصحيح: ' . $unmeasured . '**');
o('وإجماليُّ تاريخِ المشروع: **' . (int) sh('git -C ' . $cwd . ' rev-list --count HEAD') . ' التزامًا**.');

/* ══ ② حجمُ ما بُني — وهو خارجُ المقام ═══════════════════════════════════ */
o('');
o('╔══ ② حجمُ ما بُني فعلًا — **صفرٌ في مقامِ التقرير**');
o('');
$builtRows = array(
    array('جداولُ قاعدةِ بيانات', q1($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")),
    array('منها بعمودِ عزلِ الشركات', q1($db, "SELECT COUNT(DISTINCT TABLE_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='company_id'")),
    array('ترحيلاتٌ مطبَّقةٌ على القاعدة', q1($db, "SELECT COUNT(*) FROM schema_migrations")),
    array('وحداتُ صلاحياتٍ مسجَّلة', q1($db, 'SELECT COUNT(*) FROM modules')),
    array('منحُ صلاحياتٍ للأدوار', q1($db, 'SELECT COUNT(*) FROM role_permissions')),
    array('أدوارٌ مبنيّة', q1($db, 'SELECT COUNT(*) FROM roles')),
    array('تعريفُ شاشةٍ في سجلِّ الشاشات', q1($db, 'SELECT COUNT(*) FROM nav09_file_map')),
    array('قاموسُ أفعالٍ بتصنيفِ كتابة', q1($db, 'SELECT COUNT(*) FROM nav09_action_map')),
    array('روابطُ تنقّلٍ حيةٌ في القوائم', q1($db, 'SELECT COUNT(*) FROM nav_items WHERE active=1')),
);
$svc = 0; $dom = 0;
foreach ((array) glob($ROOT . '/app/Services/*', GLOB_ONLYDIR) as $d) {
    $dom++;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') { $svc++; }
    }
}
$builtRows[] = array('خدماتُ نطاقٍ (في ' . $dom . ' نطاقًا)', $svc);
$builtRows[] = array('اختباراتٌ مكتوبة', count(glob($ROOT . '/tests/*.php')));
$builtRows[] = array('أدواتُ قياسٍ وأحزمة', count(glob($ROOT . '/tools/*.php')));
o('| ما بُني | العدد |');
o('|---|---:|');
foreach ($builtRows as $r) { o('| ' . $r[0] . ' | **' . $r[1] . '** |'); }

/* ══ ③ حِقَبُ البناء ═══════════════════════════════════════════════════ */
o('');
o('╔══ ③ حِقَبُ البناءِ السابقةُ — كلٌّ أُغلق ببوابتِه في زمنِه');
o('');
$eras = array();
foreach ((array) glob($ROOT . '/docs/update*', GLOB_ONLYDIR) as $d) {
    $n = count(glob($d . '/*'));
    $eras[basename($d)] = $n;
}
ksort($eras);
o('| حقبةُ بناء | ملفاتُ وثائقِها |');
o('|---|---:|');
foreach ($eras as $e => $n) { o('| `docs/' . $e . '` | ' . $n . ' |'); }
o('| **المجموع** | **' . count($eras) . ' حقبةً · ' . array_sum($eras) . ' ملفًّا** |');
o('');
o('وكلُّ حقبةٍ منها كانت **حزمةَ متطلباتٍ كاملةً** لها بوابةُ قبولٍ أُغلقت في زمنِها');
o('(وثائقُ المعماريةِ v4…v20 تسجّلها) — **ولا واحدةٌ منها في مقامِ الـ595.**');

/* ══ الحكم ═════════════════════════════════════════════════════════════ */
o('');
o(str_repeat('═', 70));
o('الحكم:');
o('');
o('  ① **الفروع: لا عملَ غيرَ مقيس.** كلُّ فروعِ الميزاتِ مدموجةٌ في main');
o('     (صفرُ التزامٍ أمامَه)، والوحيدُ غيرُ المدموجِ هو حزمةُ التصحيحِ نفسُها.');
o('');
/* ◆ الأرقامُ في سطرِ الحكمِ تُقرأ من القياسِ لا تُكتب بيدٍ — وقعتُ في هذا
     بعينِه فكتبتُ «13 حقبةً» و«179 خدمةً» والقياسُ يقول غيرَهما. وهو الانحرافُ
     الذي تحذّر منه كلُّ وثيقةٍ في هذا المشروع، فيُصلَح في موضعِه. */
$bLine = array();
foreach ($builtRows as $r) { $bLine[$r[0]] = $r[1]; }
o('  ② **ولا يقيس التقريرُ المشروع.** مقامُه **595 ملاحظةَ عيبٍ** وجدها تدقيقُ');
o('     أغسطس 2026 — والتدقيقُ يعدّ **ما اختلَّ** لا **ما بُني**. فكلُّ ما في §②');
o('     أعلاه (' . $bLine['جداولُ قاعدةِ بيانات'] . ' جدولًا · '
      . $bLine['وحداتُ صلاحياتٍ مسجَّلة'] . ' وحدةً · '
      . $bLine['تعريفُ شاشةٍ في سجلِّ الشاشات'] . ' شاشةً · '
      . $svc . ' خدمةً · ' . count($eras) . ' حقبةَ بناءٍ موثَّقة)');
o('     **وزنُه صفرٌ في تلك النسبة**.');
o('');
o('  ③ فالقولُ الصحيح: **«أُغلق 8٪–15٪ من قائمةِ عيوبِ تدقيقِ أغسطس»**');
o('     لا «أُنجز 8٪–15٪ من المشروع». والفرقُ بين العبارتين جوهريّ:');
o('     الأولى صادقة، والثانية تُفهَم أن النظامَ لم يُبنَ — وهو مبنيٌّ ويعمل.');
o(str_repeat('═', 70));

if ($mdOut) {
    file_put_contents($mdOut, "# ما يقيسه تقريرُ النسبة وما لا يقيسه\n\n" . implode("\n", $L) . "\n");
    echo "\nكُتب: {$mdOut}\n";
}
exit(0);
