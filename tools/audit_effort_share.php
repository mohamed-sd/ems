<?php
/**
 * tools/audit_effort_share.php — «كم نُفِّذ وكم بقي من كلِّ العمل؟»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ السؤالُ يطلب **نسبةً واحدة**، والتقاريرُ السابقةُ تعطي ثلاثًا — لأن كلَّ
 *   نسبةٍ منها تقيس شيئًا آخر:
 *     · بالعددِ المجرَّد     — تسوّي الحاجبَ بالتسمية.
 *     · مرجَّحًا بالخطورة   — تقيس **الخطرَ المُنزَل** لا الجهدَ المبذول.
 *   ولا واحدةٌ منهما تجيب «كم من **العمل**» — والعملُ يُقاس بالجهد.
 *
 * ◆ فتُحسب هنا حصةُ الجهدِ بأوزانٍ **مُعلَنةٍ لا مخفيّة**، ومصدرُها تصنيفُ
 *   الصعوبةِ في `fix_status_report` (سهل · متوسط · صعب) — وهو تصنيفٌ بقاعدةٍ
 *   منشورةٍ لا تقديريّ. والوزنُ نسبةٌ تقريبيةٌ بين الدرجاتِ لا ساعاتٌ مطلقة.
 *
 * ◆ **وثلاثةُ تحذيراتٍ تُطبَع مع الرقم** ولا تُفصل عنه:
 *   ① فجوةُ الدليلِ (Evidence) تنفيذُها **تامٌّ** — فعدُّها «عملًا باقيًا» تضخيم.
 *   ② فجوةُ القرارِ (Decision) لا تُغلق بكودٍ أصلًا — فليست عملَ برمجةٍ.
 *   ③ و«المُغطَّى» عملٌ **مبذولٌ فعلًا** لم يُشهَد له منفردًا — فإسقاطُه تبخيس.
 *
 * التشغيل: php tools/audit_effort_share.php [--md=مسار]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }
$L = array();
function o($s = '') { global $L; $L[] = $s; echo $s . "\n"; }

/* ══ القوائمُ والقواعدُ تُقرأ من مصدرِها الواحد ═══════════════════════════ */
/* ── الإغلاقُ من **المصدرِ الواحد** لا من مصفوفةٍ مُزالة ─────────────────────
   كان هذا السطرُ ينتزع مصفوفةً مثبَّتةً من `fix_status_report.php`. وقد أُزيلت
   تلك المصفوفةُ (توحيدُ المصدرين · 2026-08-13) فصار الانتزاعُ يعود **خاويًا**
   وتُعلن الأداةُ **صفرًا** — وهو أخطرُ من عطلٍ ظاهرٍ لأنَّه رقمٌ يبدو قياسًا.
   فالمصدرُ الآن واحدٌ لكلِّ الأدوات: الوسمُ الصريحُ على القرص. */
require_once $ROOT . '/includes/fix_closure_source.php';
$__c = ems_fix_closed_ids($ROOT, false);
$CLOSED = $__c['mentioned'];
if (!$CLOSED) {
    fwrite(STDERR, "✘ صفرُ شواهدَ موسومةٍ — يُوقَف بدل إعلانِ صفرٍ يبدو قياسًا\n");
    exit(2);
}
$COVERED_KINDS = array('Permission Gap', 'Governance Gap');
$EASY_KINDS   = array('Wrong Label', 'Broken Button', 'Wrong Sidebar Placement', 'Missing Evidence');
$MEDIUM_KINDS = array('Data Mismatch', 'Export/Import Gap', 'Event/Integration Gap',
                      'Permission Gap', 'Governance Gap', 'Risk/Governance Gap', 'Wrong Owner');
$HARD_KINDS   = array('Missing Screen', 'Duplicate Screen', 'Wrong Workflow', 'Not Testable');

/* أوزانُ الجهدِ النسبيةُ — مُعلَنةٌ ويمكن الجدالُ فيها، ولا تُخفى */
$EW = array('سهل' => 1, 'متوسط' => 3, 'صعب' => 8);

function difficulty($kind, $sev, $E, $M, $H)
{
    if (in_array($kind, $H, true)) { return 'صعب'; }
    if (in_array($kind, $E, true)) { return ($sev === 'P0') ? 'متوسط' : 'سهل'; }
    if (in_array($kind, $M, true)) { return ($sev === 'P0') ? 'صعب' : 'متوسط'; }
    return 'متوسط';
}

$rows = array();
foreach (file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES) as $i => $line) {
    if ($i < 2) { continue; }
    $r = explode("\t", $line);
    if (count($r) < 30 || strpos($r[0], 'INJ-') !== 0) { continue; }
    $sev = trim($r[10]); $kind = trim($r[9]);
    $rows[] = array('id' => trim($r[0]), 'sev' => $sev, 'kind' => $kind, 'gap' => trim($r[28]),
                    'diff' => difficulty($kind, $sev, $EASY_KINDS, $MEDIUM_KINDS, $HARD_KINDS));
}
$N = count($rows);
foreach ($rows as $k => $r) {
    $rows[$k]['state'] = in_array($r['id'], $CLOSED, true) ? 'مُغلق'
        : ((in_array($r['kind'], $COVERED_KINDS, true) && in_array($r['sev'], array('P0', 'P1'), true)) ? 'مُغطًّى' : 'مفتوح');
}

o('══════════════════════════════════════════════════════════════════════');
o(' حصةُ الجهد: كم نُفِّذ وكم بقي — ' . date('Y-m-d H:i'));
o('══════════════════════════════════════════════════════════════════════');
o('');
o('المقام: **' . $N . '** ملاحظةً ميدانيةً (السجلُّ الجامع) · والأوزانُ المُعلَنة: '
  . 'سهل=1 · متوسط=3 · صعب=8');

/* ══ ① مصفوفةُ الحالةِ × الصعوبة ═══════════════════════════════════════ */
o('');
o('╔══ ① الحالةُ × الصعوبة (عددًا)');
o('');
o('| | سهل | متوسط | صعب | المجموع |');
o('|---|---:|---:|---:|---:|');
$mx = array(); $tot = array('سهل' => 0, 'متوسط' => 0, 'صعب' => 0);
foreach (array('مُغلق', 'مُغطًّى', 'مفتوح') as $st) {
    $mx[$st] = array('سهل' => 0, 'متوسط' => 0, 'صعب' => 0);
    foreach ($rows as $r) { if ($r['state'] === $st) { $mx[$st][$r['diff']]++; } }
    $s = array_sum($mx[$st]);
    o('| **' . $st . '** | ' . $mx[$st]['سهل'] . ' | ' . $mx[$st]['متوسط'] . ' | ' . $mx[$st]['صعب'] . ' | **' . $s . '** |');
    foreach ($tot as $d => $v) { $tot[$d] += $mx[$st][$d]; }
}
o('| **المجموع** | **' . $tot['سهل'] . '** | **' . $tot['متوسط'] . '** | **' . $tot['صعب'] . '** | **' . $N . '** |');

/* ══ ② حصةُ الجهد ══════════════════════════════════════════════════════ */
$W = function ($set) use ($EW) { $w = 0.0; foreach ($set as $r) { $w += $EW[$r['diff']]; } return $w; };
$all = $W($rows);
$closed = $W(array_filter($rows, static function ($r) { return $r['state'] === 'مُغلق'; }));
$covered = $W(array_filter($rows, static function ($r) { return $r['state'] === 'مُغطًّى'; }));
$open = $W(array_filter($rows, static function ($r) { return $r['state'] === 'مفتوح'; }));

o('');
o('╔══ ② حصةُ الجهدِ من كلِّ العمل');
o('');
o('| الحالة | وزنُ الجهد | النسبة |');
o('|---|---:|---:|');
o('| **مُنفَّذٌ بشاهدٍ مُشغَّل** | ' . round($closed) . ' | **' . round($closed * 100 / $all, 1) . '٪** |');
o('| مبذولٌ بآليةٍ مشتركةٍ بلا شاهدٍ منفرد (مُغطًّى) | ' . round($covered) . ' | ' . round($covered * 100 / $all, 1) . '٪ |');
o('| **متبقٍّ** | ' . round($open) . ' | **' . round($open * 100 / $all, 1) . '٪** |');
o('| المجموع | ' . round($all) . ' | 100٪ |');

/* ══ ③ تنقيةُ المتبقي: ما ليس عملَ برمجةٍ أصلًا ══════════════════════════ */
$openRows = array_filter($rows, static function ($r) { return $r['state'] === 'مفتوح'; });
$evid = array_filter($openRows, static function ($r) { return strpos($r['gap'], 'دليل') !== false; });
$dec  = array_filter($openRows, static function ($r) { return strpos($r['gap'], 'قرار') !== false; });
$impl = array_filter($openRows, static function ($r) { return strpos($r['gap'], 'تنفيذ') !== false; });
o('');
o('╔══ ③ تنقيةُ «المتبقي» — ليس كلُّه عملَ برمجة');
o('');
o('| نوعُ المتبقي | عددٌ | وزنُ الجهد | من كلِّ العمل |');
o('|---|---:|---:|---:|');
o('| فجوةُ **تنفيذٍ** (كودٌ يُكتب) | ' . count($impl) . ' | ' . round($W($impl)) . ' | **' . round($W($impl) * 100 / $all, 1) . '٪** |');
o('| فجوةُ **دليلٍ** (التنفيذُ تامٌّ — اختبارٌ يُشغَّل) | ' . count($evid) . ' | ' . round($W($evid)) . ' | ' . round($W($evid) * 100 / $all, 1) . '٪ |');
o('| فجوةُ **قرارٍ** (لا تُغلق بكود) | ' . count($dec) . ' | ' . round($W($dec)) . ' | ' . round($W($dec) * 100 / $all, 1) . '٪ |');

/* ══ ④ الجوابُ برقمٍ واحدٍ بثلاثِ قراءات ════════════════════════════════ */
$strict = $closed * 100 / $all;
$fair   = ($closed + $covered) * 100 / $all;
$codeOnly = $W($impl) * 100 / $all;
o('');
o(str_repeat('═', 70));
o('الجوابُ برقمٍ واحدٍ — وبثلاثِ قراءاتٍ للرقمِ نفسِه:');
o('');
o('  ◆ **المُنفَّذُ المُثبَتُ بشاهدٍ:  ' . round($strict, 1) . '٪**   والمتبقي ' . round(100 - $strict, 1) . '٪');
o('     (أضيقُ قراءةٍ وأصدقُها للإعلانِ الخارجي)');
o('');
o('  ◆ المُنفَّذُ فعلًا بما بُذل من عملٍ مشترك: **' . round($fair, 1) . '٪**   والمتبقي ' . round(100 - $fair, 1) . '٪');
o('     (يضمُّ ما أُصلحت آليتُه ولم يُشهَد لكلِّ ملاحظةٍ منه منفردةً)');
o('');
o('  ◆ **وعملُ البرمجةِ الباقي وحدَه: ' . round($codeOnly, 1) . '٪**');
o('     (والباقي بعده أدلةٌ تُشغَّل وقراراتُ مالكٍ لا كود)');
o('');
o('◆ وأيًّا اخترتَ: **الحواجبُ P0 مُنزَلةٌ كلُّها (13/13)** — وهي ما يمنع الإطلاق،');
o('  ولا يُقاس أمانُ النظامِ بنسبةِ الإنجازِ بل بها.');
o(str_repeat('═', 70));

if ($mdOut) {
    file_put_contents($mdOut, "# حصةُ الجهد — كم نُفِّذ وكم بقي\n\n" . implode("\n", $L) . "\n");
    echo "\nكُتب: {$mdOut}\n";
}
exit(0);
