<?php
/**
 * tools/u13_families_extract.php — استخراجُ كلِّ عائلةٍ معلَنةٍ في الوثائقِ السبع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا ملفٌّ ثانٍ بجانب `u13_spec_extract.php`:
 *   الأولُ استخرج ما **بُني عليه** المحرّكُ (المسارات · القواعد · الطبقات …).
 *   وهذا يستخرج **كلَّ ما تعلنه الوثائقُ** في جداولِ «الأرقامِ الحاكمة» — بما
 *   لم يُبنَ بعد. والفرقُ بينهما هو **قياسُ التغطيةِ الصادق**: ما أُعلن مقابلَ
 *   ما له أثرٌ حيّ. ولو خلطتُهما لصار الفاحصُ يقيس نفسَه بنفسِه.
 *
 * العوائلُ المستخرَجة (بمعرّفِ كلِّ بندٍ ونصِّه وشاهدِ قبولِه):
 *   duties · my_day · limits · perm_factors · scenarios · ctrl_competencies ·
 *   quality_kpis · mgr_competencies · tre_roles · tre_competencies ·
 *   pay_cycle · receive_cycle · charter · iaf_cycle · contract_fields ·
 *   standard_tests · ceo_actions · dept_propagation · actions
 *
 * التشغيل: php tools/u13_families_extract.php [--out=docs/update0013/families.json]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$SRC  = $ROOT . '/docs/update0013/extracted';
$out  = $ROOT . '/docs/update0013/families.json';
foreach ($argv as $a) { if (strpos($a, '--out=') === 0) { $out = $ROOT . '/' . substr($a, 6); } }

require_once $ROOT . '/tools/u13_spec_extract_lib.php';

$FILES = array('FIN-OBL-01' => 'FIN-OBL-01-v1.md', 'PROP-01' => 'PROP-01.md',
               'FIN-ACC-01' => 'FIN-ACC-01.md', 'FIN-CTRL-01' => 'FIN-CTRL-01.md',
               'FIN-MGR-01' => 'FIN-MGR-01.md', 'FIN-TRE-01' => 'FIN-TRE-01.md',
               'IAF-01' => 'IAF-01.md');
$A = array();
foreach ($FILES as $code => $fn) { $A[$code] = u13_atoms($SRC . '/' . $fn); }

$F = array();
$seq = array();

/** يضيف بندًا إلى عائلة. */
function add(&$F, &$seq, $doc, $family, $title, $detail, $test, $src)
{
    $title = u13_clean($title);
    if ($title === '') { return; }
    $key = $doc . '|' . $family;
    $seq[$key] = isset($seq[$key]) ? $seq[$key] + 1 : 1;
    $F[] = array(
        'doc' => $doc, 'family' => $family,
        'code' => $family . '-' . sprintf('%02d', $seq[$key]),
        'seq' => $seq[$key], 'title' => mb_substr($title, 0, 300),
        'detail' => mb_substr(u13_clean($detail), 0, 500),
        'test' => mb_substr(u13_clean($test), 0, 300), 'doc_ref' => $src,
    );
}

/** يمسح عائلةً بنمطٍ على النصِّ المدموج · ويأخذ العنوانَ من مجموعةِ الالتقاط. */
function scan(&$F, &$seq, array $atoms, $doc, $family, $re, $titleGroup = 1, $detailFrom = 2)
{
    foreach ($atoms as $id => $a) {
        if (!preg_match($re, $a['text'], $m)) { continue; }
        $title = isset($m[$titleGroup]) ? $m[$titleGroup] : '';
        /* العنوانُ «نطاقُ الحكم» يعني أن الاسمَ في الجزءِ التالي لا في الأول. */
        if ($title === '' || mb_strpos($title, 'نطاقُ الحكم') !== false) {
            $title = isset($a['parts'][1]) ? $a['parts'][1] : '';
        }
        $detail = implode(' · ', array_slice($a['parts'], $detailFrom));
        add($F, $seq, $doc, $family, $title, $detail, $a['test'], $id);
    }
}

/* ══ FIN-ACC-01 ═══════════════════════════════════════════════════════════ */
scan($F, $seq, $A['FIN-ACC-01'], 'FIN-ACC-01', 'DUTY',
     '~واجبُ محاسبِ الإدارة\s*«([^»]+)»~u');
scan($F, $seq, $A['FIN-ACC-01'], 'FIN-ACC-01', 'MYDAY',
     '~يظهر في «عملي اليوم» لمحاسبِ الإدارة\s*[—–-]\s*(.*)$~u');
scan($F, $seq, $A['FIN-ACC-01'], 'FIN-ACC-01', 'LIMIT',
     '~لا يملك محاسبُ الإدارة:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-ACC-01'], 'FIN-ACC-01', 'PFACTOR',
     '~عاملُ اشتقاقِ الصلاحية:\s*(.+)$~u');
scan($F, $seq, $A['FIN-ACC-01'], 'FIN-ACC-01', 'SCEN',
     '~سيناريو قبولٍ~u', 9, 2);

/* ══ FIN-CTRL-01 ══════════════════════════════════════════════════════════ */
scan($F, $seq, $A['FIN-CTRL-01'], 'FIN-CTRL-01', 'COMP',
     '~اختصاصُ رئيسِ الحساباتِ في «([^»]+)»~u');
scan($F, $seq, $A['FIN-CTRL-01'], 'FIN-CTRL-01', 'KPI',
     '~مؤشرُ جودةِ المحاسبة:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-CTRL-01'], 'FIN-CTRL-01', 'LIMIT',
     '~لا يملك رئيسُ الحسابات:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-CTRL-01'], 'FIN-CTRL-01', 'SCEN',
     '~سيناريو قبولٍ~u', 9, 2);

/* ══ FIN-MGR-01 ═══════════════════════════════════════════════════════════ */
scan($F, $seq, $A['FIN-MGR-01'], 'FIN-MGR-01', 'COMP',
     '~اختصاصٌ قياديٌّ ماليّ:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-MGR-01'], 'FIN-MGR-01', 'LIMIT',
     '~لا يملك القيادةُ المالية:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-MGR-01'], 'FIN-MGR-01', 'SCEN',
     '~سيناريو قبولٍ~u', 9, 2);

/* ══ FIN-TRE-01 ═══════════════════════════════════════════════════════════ */
scan($F, $seq, $A['FIN-TRE-01'], 'FIN-TRE-01', 'ROLE',
     '~دورٌ في وحدةِ الخزينة\s*[—–-]\s*(.*)$~u');
scan($F, $seq, $A['FIN-TRE-01'], 'FIN-TRE-01', 'COMP',
     '~اختصاصُ الخزينةِ في «[^»]+»:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-TRE-01'], 'FIN-TRE-01', 'LIMIT',
     '~لا يملك الخزينةُ والبنوك:?\s*(.*)$~u');
scan($F, $seq, $A['FIN-TRE-01'], 'FIN-TRE-01', 'SCEN',
     '~سيناريو قبولٍ~u', 9, 2);

/* ══ IAF-01 ═══════════════════════════════════════════════════════════════ */
scan($F, $seq, $A['IAF-01'], 'IAF-01', 'CHARTER',
     '~ميثاقُ المراجعةِ\s*[—–-]\s*«([^»]+)»~u');
scan($F, $seq, $A['IAF-01'], 'IAF-01', 'LIMIT',
     '~لا يملك المراجعُ الداخلي:?\s*(.*)$~u');
scan($F, $seq, $A['IAF-01'], 'IAF-01', 'SCEN',
     '~سيناريو قبولٍ~u', 9, 2);

/* ══ FIN-OBL-01 ═══════════════════════════════════════════════════════════ */
scan($F, $seq, $A['FIN-OBL-01'], 'FIN-OBL-01', 'CFIELD',
     '~حقلُ العقد\s*«([^»]+)»~u');
/* الاختباراتُ المعياريةُ عنوانُها في الجزءِ «ب» لا في الأول («سيناريو قبولٍ
   معياري — نطاقُ الحكم» ترويسةٌ مكرَّرةٌ لا اسمُ اختبار). */
scan($F, $seq, $A['FIN-OBL-01'], 'FIN-OBL-01', 'STDTEST',
     '~سيناريو قبولٍ معياري~u', 9, 2);

/* ══ PROP-01 ══════════════════════════════════════════════════════════════ */
/* ◆ أفعالُ الرئيسِ السبعةُ في **جدولِ §٥-٢** لا في السجلِّ الذري. والمسحُ على
     السجلِّ يلتقط عقودَ أفعالِ الشاشاتِ (SCN) — وهي شيءٌ آخر: أربعةُ أفعالٍ
     بعكسِ كلٍّ زائدَ التعمّق. فالقراءةُ من الجدولِ نفسِه. */
$inAct = false;
foreach (u13_md_rows($SRC . '/PROP-01.md') as $c) {
    if (count($c) >= 3 && trim($c[0]) === 'الفعل' && trim($c[1]) === 'الفاعل') { $inAct = true; continue; }
    if ($inAct) {
        if (count($c) < 3 || trim($c[0]) === '') { $inAct = false; continue; }
        add($F, $seq, 'PROP-01', 'CEOACT', $c[0], $c[1] . ' · ' . $c[2], '', 'PROP-01 §5-2');
    }
}

/* ── دورتا الدفعِ والقبضِ ودورةُ المراجعة: مراحلُ سلسلةٍ في متطلبٍ واحد ──── */
function stages(&$F, &$seq, array $atoms, $doc, $family, $needle)
{
    foreach ($atoms as $id => $a) {
        if (mb_strpos($a['text'], $needle) === false) { continue; }
        foreach ($a['parts'] as $p) {
            if (mb_strpos($p, '←') === false) { continue; }
            foreach (preg_split('~\s*←\s*~u', $p) as $st) {
                add($F, $seq, $doc, $family, $st, '', $a['test'], $id);
            }
            return;
        }
    }
}
stages($F, $seq, $A['FIN-TRE-01'], 'FIN-TRE-01', 'PAYSTG', 'دورةُ الدفع');
stages($F, $seq, $A['FIN-TRE-01'], 'FIN-TRE-01', 'RCVSTG', 'دورةُ القبض');
stages($F, $seq, $A['IAF-01'],     'IAF-01',     'CYCLE',  'دورةُ المراجعةِ الداخلية');
stages($F, $seq, $A['FIN-ACC-01'], 'FIN-ACC-01', 'CYCLE',  'دورةُ عملِ محاسبِ الإدارة');

/* ── الأحكامُ المنتشرةُ في كلِّ إدارة (PROP-01 §٦-١) — جدولٌ لا متطلب ────── */
$prop = array();
$inT = false;
foreach (u13_md_rows($SRC . '/PROP-01.md') as $c) {
    if (count($c) >= 3 && trim($c[1]) === 'أحكامٌ منتشرة') { $inT = true; continue; }
    if ($inT) {
        if (count($c) < 3 || trim($c[0]) === '') { $inT = false; continue; }
        $prop[] = array('dept' => u13_clean($c[0]),
                        'propagated' => (int) u13_ar2en($c[1]),
                        'total' => (int) u13_ar2en($c[2]));
    }
}
/* ── تعارضاتٌ تكشفها العوائلُ نفسُها: المعلَنُ في «الأرقامِ الحاكمة» مقابلَ
     ما يسجّله السجلُّ الذريُّ فعلًا. وهي تتمةُ ما كشفه `u13_spec_extract`. ── */
$declared = array(
    'FIN-CTRL-01|COMP'  => array(45, 'البنودُ التفصيليةُ للاختصاص', 'FCTRL-0003..0046'),
    'FIN-OBL-01|CFIELD' => array(29, 'حقولُ العقدِ الحاكمة',        'OBL-0058..0085'),
    'IAF-01|CYCLE'      => array(8,  'مراحلُ دورةِ المراجعة',       'IAF-0044'),
);
/* ◆ «الأفعالُ بعقودها» تُعلَن في ترويسةِ خمسِ وثائقَ (23+21+14+12+11 = 81)
     **بلا سجلٍّ ذريٍّ يسمّي فعلًا واحدًا** — كحالِ الشاشاتِ في V-03..V-08.
     فتُرفع مخالفةً واحدةً جامعة، ويُبنى ما يقع فعلًا لا ما يُوفّي رقمًا. */
$actDeclared = 0;
foreach ($FILES as $code => $fn) {
    $txt = @file_get_contents($SRC . '/' . $fn);
    if (preg_match('~\| الأفعالُ بعقودها \| ([٠-٩0-9]+)~u', (string) $txt, $m)) {
        $actDeclared += (int) u13_ar2en($m[1]);
    }
}
$counts = array();
foreach ($F as $x) { $k = $x['doc'] . '|' . $x['family']; $counts[$k] = isset($counts[$k]) ? $counts[$k] + 1 : 1; }
$vars = array();
foreach ($declared as $k => $d) {
    $live = isset($counts[$k]) ? $counts[$k] : 0;
    if ($live === $d[0]) { continue; }
    list($doc, $fam) = explode('|', $k);
    $vars[] = array('doc' => $doc, 'family' => $fam, 'subject' => $d[1],
                    'declared' => $d[0], 'registered' => $live, 'range' => $d[2]);
}

if ($actDeclared > 0) {
    $vars[] = array('doc' => 'الوثائقُ الخمس', 'family' => 'ACTION',
                    'subject' => 'الأفعالُ بعقودها', 'declared' => $actDeclared,
                    'registered' => 0, 'range' => 'ترويساتٌ بلا سجلٍّ ذريّ');
}

$F2 = array('items' => $F, 'dept_propagation' => $prop, 'variances' => $vars,
            'actions_declared' => $actDeclared);

@mkdir(dirname($out), 0777, true);
$json = json_encode($F2, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) { exit('تعذّر الترميز: ' . json_last_error_msg() . "\n"); }
file_put_contents($out, $json);

/* ── التقرير ─────────────────────────────────────────────────────────────── */
$by = array();
foreach ($F as $x) { $k = $x['doc'] . ' · ' . $x['family']; $by[$k] = isset($by[$k]) ? $by[$k] + 1 : 1; }
ksort($by);
printf("✔ %s — %d بندًا في %d عائلة\n\n", str_replace($ROOT . '/', '', $out), count($F), count($by));
foreach ($by as $k => $v) { printf("  %-30s %d\n", $k, $v); }
printf("\n  الإداراتُ المنتشرُ فيها: %d · مجموعُ الأحكامِ المنتشرة: %d\n",
    count($prop), array_sum(array_column($prop, 'propagated')));
if ($vars) {
    printf("\n  ◆ تعارضاتٌ إضافيةٌ مكشوفة: %d\n", count($vars));
    foreach ($vars as $v) {
        printf("     %-12s %-34s معلَن %-4s ✕ مسجَّل %-4s (%s)\n",
            $v['doc'], mb_substr($v['subject'], 0, 32), $v['declared'], $v['registered'], $v['range']);
    }
}
