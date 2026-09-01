<?php
/**
 * tools/gov_exec_inv_triage.php — فرزُ «منفَّذٌ بلا إثبات» بحاجزِه المكتوب (GOV_EXEC §3 · §22)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كُشف بالقراءة**: السجلُّ الجامعُ يصف واحدًا وعشرين مطلبًا
 *   `IMPLEMENTED_NOT_VERIFIED` — أي «منفَّذٌ ينقصه إثبات». وبالرجوعِ إلى
 *   **الدفترِ الرسميِّ نفسِه** (`docs/sources/INJ-FRD-REM-01/workbook.xlsx`)
 *   وُجد لكلِّ واحدٍ منها **حاجزٌ مكتوبٌ في عمودِ `Blocker`** — ولا واحدَ منها
 *   ينقصه شاهدٌ آليٌّ يُكتب. فالتصنيفُ كان يقول «ينقصه إثبات» والمصدرُ يقول
 *   «ينقصه قرارُ مالكٍ» أو «نافذةُ ملاحظةٍ لم تنقضِ» أو «امتيازُ بيئةٍ».
 *
 * ◆ **والفرقُ ليس لفظيًّا**: §3 من الأمرِ يستثني ثلاثةً فقط من التصحيحِ الفوريِّ
 *   — **قرارُ أعمالٍ غائبٌ · مصدرٌ حاكمٌ مفقودٌ · صلاحيةُ بيئة** — ويأمر بحجبِ
 *   البندِ المتأثرِ وحدَه. فبندٌ محجوبٌ بقرارِ مالكٍ مكتوبٍ في مصدرِه ويُحسب
 *   «ينقصه إثبات» **يُغري بعملٍ لا يجوز**: الملءُ الرجعيُّ أو قلبُ سلطةٍ على
 *   نظامٍ حيّ. فالتصنيفُ الصحيحُ **يحمي من العمل الخطأ** لا يجمّل رقمًا.
 *
 * ⛔ **ولا يُغلَق بندٌ هنا ولا يُرفَع دليل**: الأداةُ **لا تكتب `EVIDENCE_CLOSED`
 *   أبدًا** — تنقل الحاجزَ من مصدرِه إلى السجلِّ حرفًا وتصنّفه بقاعدةٍ معلنة.
 *   والعددُ الذي ينقص من `IMPLEMENTED_NOT_VERIFIED` **يظهر كاملًا** في
 *   `BLOCKED_*` — فلا بندَ يختفي.
 *
 * التشغيل: php tools/gov_exec_inv_triage.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';
$APPLY = in_array('--apply', $argv, true);

$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
if (!is_file($XLSX)) { exit("⛔ الدفترُ الرسميُّ مفقود\n"); }
$wb = xlsx_read($XLSX);
$rows = $wb[array_keys($wb)[0]];
$hdr = $rows[3];
$ix = array();
foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }

/* ── قاعدةُ التصنيفِ — مُعلَنةٌ بمفرداتِها لا مخفيّةٌ في شرطٍ ─────────────────
   ⛔ والترتيبُ مقصود: البيئةُ أوّلًا (أضيقُها)، ثمَّ المصدرُ الحاكمُ المفقود،
      ثمَّ التحقّقُ البشريّ، ثمَّ قرارُ المالك (أوسعُها). فبندٌ يحمل مفردتَين
      يُصنَّف بأضيقِهما — والأضيقُ أدقُّ وصفًا للحاجز. */
$RULES = array(
    'BLOCKED_ENVIRONMENT' => array('لا يملك SUPER', 'امتياز', 'صلاحية بيئة', 'SUPER مع سجل'),
    'BLOCKED_GOVERNING_SOURCE' => array('NEEDS_GOVERNING_SOURCE', 'لا تعريفَ لها في أيِّ مصدرٍ حاكم',
                                        'لا تعريف لها في اي مصدر حاكم'),
    'BLOCKED_UAT' => array('اختبارٌ بشريٌّ مستقلّ', 'مستخدمٌ حقيقيٌّ بحسابِ دورِه',
                           'اختبار بشري مستقل'),
    'BLOCKED_OWNER' => array('قرارُ مالك', 'قرار مالك', 'قرارُ مالكِ', 'يقرّرها مالكُ',
                             'بقرارِ مالك', 'لمالكِ المجال', 'لمالك المجال'),
);
/* وما لا يحمل مفردةً من هذه **يبقى كما هو** — والبقاءُ حكمٌ لا سهو. */

$plan = array();
foreach ($rows as $i => $r) {
    if ($i < 4) { continue; }
    $id = trim((string) ($r[$ix['المعرِّف']] ?? ''));
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $id)) { continue; }
    $cl = trim((string) ($r[$ix['Closure_State']] ?? ''));
    if ($cl !== 'IMPLEMENTED_NOT_CLOSED') { continue; }
    $blk = trim((string) ($r[$ix['Blocker']] ?? ''));
    $to = '';
    foreach ($RULES as $status => $needles) {
        foreach ($needles as $n) {
            if ($blk !== '' && mb_strpos($blk, $n) !== false) { $to = $status; break 2; }
        }
    }
    $plan[$id] = array('blocker' => $blk, 'to' => $to);
}

/* ── السجلُّ الجامع ─────────────────────────────────────────────────────── */
$REG = $ROOT . '/docs/REPAIR01_20260823/registers/MASTER_FINAL_CLOSURE_REGISTER.json';
$db = json_decode((string) file_get_contents($REG), true);
$SNAP = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

$n = array('moved' => 0, 'kept' => 0, 'absent' => 0);
$byStatus = array();
echo "═ فرزُ «منفَّذٌ بلا إثبات» بحاجزِه المكتوب — " . count($plan) . " مطلبًا ═\n\n";
foreach ($plan as $id => $p) {
    $key = 'CL-' . $id;
    if (!isset($db['items'][$key])) { $n['absent']++; printf("  ○ %-14s ليس في السجلِّ الجامع\n", $id); continue; }
    $cur = $db['items'][$key]['Current_Status'];
    if ($p['to'] === '') {
        $n['kept']++;
        printf("  = %-14s يبقى %-26s — حاجزُه لا يطابق مفردةً معلنة\n", $id, $cur);
        continue;
    }
    if ($cur === $p['to']) { $n['kept']++; printf("  = %-14s %s سلفًا\n", $id, $cur); continue; }
    printf("  ⇒ %-14s %-26s ⇐ %s\n", $id, $p['to'], mb_substr($p['blocker'], 0, 88));
    $byStatus[$p['to']] = (isset($byStatus[$p['to']]) ? $byStatus[$p['to']] : 0) + 1;
    $n['moved']++;
    if ($APPLY) {
        $db['items'][$key]['Current_Status'] = $p['to'];
        $db['items'][$key]['Blocker_Class'] = $p['to'];
        $db['items'][$key]['Current_Snapshot'] = 'حاجزٌ منقولٌ حرفًا من عمودِ Blocker في الدفترِ الرسميّ: '
            . mb_substr($p['blocker'], 0, 240) . ' @ ' . $SNAP;
        $db['items'][$key]['Last_Updated_Snapshot'] = $SNAP;
        $db['items'][$key]['Status_Changed_At'] = date('Y-m-d H:i') . ' @ ' . $SNAP;
        $db['items'][$key]['Last_Evidence_Ref'] = 'docs/sources/INJ-FRD-REM-01/workbook.xlsx · عمود Blocker';
    }
}
echo "\n  نُقل: {$n['moved']} · بقي: {$n['kept']} · خارجَ السجل: {$n['absent']}\n";
foreach ($byStatus as $s => $c) { printf("     %-28s %d\n", $s, $c); }
echo "\n⛔ ولا بندَ أُغلق هنا: الأداةُ لا تكتب EVIDENCE_CLOSED — والناقصُ من\n"
   . "   IMPLEMENTED_NOT_VERIFIED يظهر كاملًا في BLOCKED_* أعلاه.\n";
if ($APPLY) {
    $db['meta']['snapshot'] = $SNAP;
    $db['meta']['updated_at'] = date('Y-m-d H:i');
    file_put_contents($REG, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    $back = json_decode((string) file_get_contents($REG), true);
    if (!is_array($back) || count($back['items']) !== count($db['items'])) { exit("⛔ كتابةٌ مزعومة\n"); }
    echo "✔ كُتب السجلُّ وأُعيدت قراءتُه (" . count($back['items']) . " بندًا)\n";
} else { echo "\nمعاينةٌ فقط — أضف --apply\n"; }
