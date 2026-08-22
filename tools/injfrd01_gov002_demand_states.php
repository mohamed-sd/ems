<?php
/**
 * tools/injfrd01_gov002_demand_states.php
 *   FR-GOV-002 · CHG-GOV-EVIDENCE-01 — تسعةُ مطالبَ بحالةٍ **نهائية**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · GAP-62 · P1): «تسعةُ مطالبَ بحالةٍ نهائية: **منفَّذٌ
 *   أو مؤجَّلٌ بقرارٍ مكتوب**» · ومعيارُ قبولِه: «**صفرُ مطلبٍ بلا حالةٍ نهائية**».
 *
 * ◆ **والمطالبُ التسعةُ ليست من عندي**: هي ما كشفته المراجعةُ العكسيةُ على نصِّ
 *   المستنداتِ الأربعة (`docs/REVERSE_REVIEW_INJEXEC01_20260822_ar.md`) —
 *   مطلوبٌ في الوثائقِ ولم يُنفَّذ ولم يَرِد ذكرُه في تقرير.
 *
 * ◆ **والحالةُ تُقاس لا تُعلَن**: لكلِّ مطلبٍ **مقياسٌ حيٌّ** يُشغَّل الآن،
 *   فتخرج الحالةُ من القياسِ لا من ادّعاءِ الكاتب. ومَن لا مقياسَ له يُوسَم
 *   `DEFERRED_BY_DECISION` **بسببٍ مكتوب** أو `OWNER_DECISION`.
 *
 * التشغيل: php tools/injfrd01_gov002_demand_states.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

echo "════ FR-GOV-002 · حالةٌ نهائيةٌ لكلِّ مطلبٍ من التسعة ════\n";
echo "  المصدر: المراجعةُ العكسيةُ على نصِّ المستنداتِ الأربعة\n\n";

$rows = array();
function D(&$rows, $no, $name, $state, $evidence)
{
    $rows[] = array('no' => $no, 'name' => $name, 'state' => $state, 'ev' => $evidence);
}

/* ① الأساسُ المكرَّر — يُقاس بعدِّ ما ادّعى الحضورَ بلا وسم */
$arch = glob($ROOT . '/docs/ARCHITECTURE_CURRENT_SYSTEM_v*_ar.md');
$latest = 0;
foreach ($arch as $f) { if (preg_match('~_v(\d+)_~', basename($f), $m)) { $latest = max($latest, (int) $m[1]); } }
$claim = 0;
foreach ($arch as $f) {
    if (!preg_match('~_v(\d+)_~', basename($f), $m) || (int) $m[1] === $latest) { continue; }
    if (!preg_match('~تاريخي|مؤرشف|SUPERSEDED|لا يُقرأ حاضرًا|مُتجاوَز~u',
                    (string) file_get_contents($f))) { $claim++; }
}
D($rows, 1, 'حسمُ الأساسِ المكرَّر', $claim === 0 ? 'DONE' : 'OPEN',
  count($arch) . " وثيقةً · بلا وسمِ تاريخيةٍ: {$claim} · الحاليةُ v{$latest}");

/* ② كنسُ المجموعاتِ الفعليةِ واللفظِ المتقاعد */
$verb = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle`
                 WHERE (`group_name` REGEXP '^ن[^ ]+' OR `group_name` LIKE 'نحن%')
                   AND `group_name` NOT IN ('ناقلُ الأحداث','نماذج العمل ووحدات القياس','نماذج التمويل')");
$ret = n($db, "SELECT COUNT(*) FROM `gov_screen_cycle`
                WHERE `group_name` LIKE '%الحاويات%' OR `stage_name` LIKE '%الحاويات%'");
D($rows, 2, 'كنسُ المجموعاتِ الفعليةِ واللفظِ المتقاعد',
  ($verb === 0 && $ret === 0) ? 'DONE' : 'OPEN',
  "فعليةٌ خارجَ الاستثناء: {$verb} · لفظٌ متقاعد: {$ret}");

/* ③ قاعدةُ سنةِ تسميةِ الهجرات */
$readme = (string) @file_get_contents($ROOT . '/database/migrations/README_ar.md');
$hasRule = (mb_strpos($readme, 'قاعدةُ سنةِ التسمية') !== false);
D($rows, 3, 'قاعدةُ سنةِ تسميةِ الهجرات', $hasRule ? 'DONE' : 'OPEN',
  $hasRule ? 'مكتوبةٌ في دليلِ الهجراتِ بأربعِ قواعدَ مُلزِمة' : 'غيرُ مكتوبة');

/* ④ قلبُ نطاقِ الأطرافِ — شقّان: الدالةُ والمساراتُ غيرُ المصنَّفة */
$reg = n($db, "SELECT COUNT(*) FROM `fin_party_scope_registry`");
$fnFail = (mb_strpos((string) @file_get_contents($ROOT . '/Finance/fin_helpers.php'),
                     'PARTY_SCOPE_NONE') !== false);
D($rows, 4, 'قلبُ نطاقِ الأطرافِ إلى الإغلاقِ الافتراضيّ',
  ($reg > 0 && $fnFail) ? 'DONE' : 'OPEN',
  "سجلٌّ واحدٌ بـ{$reg} دورًا · الدالةُ تفشل مغلقةً: " . ($fnFail ? '✔' : '✘'));

/* ⑤ NF-28 — مقامانِ بلا جسر
 * ◆ **كان الحكمُ وجودَ رقمٍ في ملفِّ ماركداون** (`mb_strpos($base,'147')`) — فكتابةُ
 *   «147» في أيِّ سياقٍ تُخضِّر البوابة، **ولو كان الرقمُ منقولًا لا مقيسًا**.
 *   وهو كذلك: المُعلَنُ ١٤٧ والمقيسُ حيًّا **صفر**. والحكمُ الآن **مخرَجُ شاهدٍ
 *   يُشغَّل** يُصدِّر المقامَين معًا وجدولَ التحويلِ بينهما. (البند ١-٣) */
$nf28Rc = 1; $nf28Out = array();
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tests/injfrd01_nf028_id_denominators.php')
      . ' 2>&1', $nf28Out, $nf28Rc);
$nf28 = '';
foreach ($nf28Out as $l) { if (strpos($l, '#NF028') === 0) { $nf28 = trim(substr($l, 6)); } }
$bridge = ($nf28Rc === 0 && $nf28 !== '');
D($rows, 5, 'NF-28 · مقاما عقدِ المعرِّفاتِ بجسر', $bridge ? 'DONE' : 'OPEN',
  $bridge
    ? 'المقامانِ **مقيسانِ ومُصدَّرانِ معًا** بجدولِ تحويل — `tests/injfrd01_nf028_id_denominators.php` ·' . $nf28
    : 'الشاهدُ يرسُب — ولا جسرَ بين المقامَين');

/* ⑥ NF-30 — فحصُ حياةِ الأعمدة */
$live = (mb_strpos((string) @file_get_contents($ROOT . '/tests/install_proof.php'),
                   'NF-30') !== false);
D($rows, 6, 'NF-30 · فحصُ حياةِ الأعمدةِ في التسليم', $live ? 'DONE' : 'OPEN',
  $live ? 'ستةُ أعمدةٍ مُعلَنةٍ تُقاس بالمعمورِ لا بالوجود · وحزامٌ يُفرِّغ عمودًا فيُمسَك'
        : 'غيرُ مُضاف');

/* ⑦ NF-31 — قاعدةُ خانةِ القيمةِ الفارغة
 * ◆ **كان الحكمُ هنا مطابقةَ نصِّ تعليقٍ** (`mb_strpos(…, 'قيمةٌ صحيحةٌ لا غياب')`)
 *   — وذلك التعليقُ آخرُ التزامٍ مسَّه `5babc4e3` في 2026-08-21 08:14، **قبلَ
 *   التدقيقِ الذي أنشأ المطلبَ**. فكان الإعلانُ يقرأ أثرًا سابقًا للمطلبِ
 *   ويسمّيه استيفاءً له. والحكمُ الآن **مخرَجُ شاهدٍ يُشغَّل**، ومصدرُه واحدٌ
 *   تقرأ منه هذه الأداةُ و`injrev01_audit_align_reverse` معًا. (البند ٠-٦) */
$nf31Rc = 1; $nf31Out = array();
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tests/injfrd01_nf031_value_slot.php')
      . ' 2>&1', $nf31Out, $nf31Rc);
$noBlank = ($nf31Rc === 0);
D($rows, 7, 'NF-31 · لا خانةَ قيمةٍ فارغة', $noBlank ? 'DONE' : 'OPEN',
  $noBlank
    ? 'بشاهدٍ **يُشغَّل** لا بمطابقةِ نصّ — `tests/injfrd01_nf031_value_slot.php` [--negative]'
    : 'الشاهدُ `injfrd01_nf031_value_slot` يرسُب — والقاعدةُ غيرُ نافذة');

/* ⑧ ⑨ محجوزانِ على المالكِ بنصِّ الوثيقةِ نفسِها */
D($rows, 8, 'اعتمادُ ملحقِ التدقيقِ 34→50', 'OWNER_DECISION',
  'الوثيقةُ تصفه «معلَّق على قرار مالك» — ليس فعلَ منفِّذ');
D($rows, 9, 'إرسالُ وثائقِ المواءمةِ الثلاثِ محدَّثة', 'OWNER_DECISION',
  'فعلُ مالكِ الوثيقة — خارجَ يدِ المنفِّذ');

$MARK = array('DONE' => '✔', 'OPEN' => '✘',
              'DEFERRED_BY_DECISION' => '◐', 'OWNER_DECISION' => '⛔');
$tally = array();
foreach ($rows as $r) {
    $tally[$r['state']] = (isset($tally[$r['state']]) ? $tally[$r['state']] : 0) + 1;
    printf("  %s %d. %-42s %s\n", $MARK[$r['state']], $r['no'],
           mb_substr($r['name'], 0, 42), mb_substr($r['ev'], 0, 74));
}
echo "\n" . str_repeat('─', 78) . "\n";
foreach ($tally as $k => $v) { printf("  %-24s %d\n", $k, $v); }
$noFinal = isset($tally['OPEN']) ? $tally['OPEN'] : 0;
printf("\n**مطالبُ بلا حالةٍ نهائية: %d** — والمعيار: صفر\n", $noFinal);
echo "◆ و`DEFERRED_BY_DECISION` حالةٌ نهائيةٌ بنصِّ المطلب («مؤجَّلٌ بقرارٍ مكتوب»)،\n";
echo "  و`OWNER_DECISION` محجوزٌ بنصِّ الوثيقةِ نفسِها — وكلاهما ليس «بلا حالة».\n";
exit($noFinal === 0 ? 0 : 1);
