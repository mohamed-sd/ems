<?php
/**
 * tools/repair01_w10_docs.php — يولّد وثائقَ المرحلةِ العاشرةِ من المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **الثلاثةُ مولَّدةٌ — لا تُحرَّر يدويًّا**: أيُّ تحريرٍ يمحوه التشغيلُ التالي،
 *   والتعديلُ يقع في الجدولِ لا في الملفّ.
 *   · `W10_STATE_MACHINES.md` ⇐ `repair01_w10_states`
 *   · `W10_SOD.md`            ⇐ `repair01_w10_sod`
 *   · `W10_JOURNEY_EVIDENCE.md` ⇐ `repair01_w10_journey` (‏آخرُ جولةٍ كاملة)
 *
 * التشغيل: php tools/repair01_w10_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$DIR = $ROOT . '/docs/REPAIR01_20260823/plan';
$HDR = "> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w10_docs.php` يعيد كتابتَه.\n\n";

/* ═══ ① آلاتُ الحالة ═══════════════════════════════════════════════════ */
$md = "# RPR-W10 — آلاتُ الحالةِ لكيانَاتِ الشقّ\n\n" . $HDR;
$md .= "**الشقُّ قرارُ ملكيّةٍ لا إعادةُ ترقيم.** ولكلِّ كيانٍ هنا حالاتُه وانتقالاتُه "
     . "المسموحةُ بشروطِها ومستندِها وبوّابتِها، **وممنوعُه الصريحُ مُسبَّبًا**.\n\n";
$ents = array();
$r = $conn->query("SELECT * FROM repair01_w10_states ORDER BY entity, allowed DESC, from_state, to_state");
while ($r && $x = $r->fetch_assoc()) { $ents[$x['entity']][] = $x; }
$TITLES = array(
    'SURFACE_OWNERSHIP' => 'ملكيّةُ السطح',
    'DEPT_SPLIT'        => 'الوحدةُ المشقوقة',
    'LEGACY_POINTER'    => 'المؤشِّرُ القديم',
    'SIDEBAR_ITEM'      => 'بندُ القائمة',
    'AUDIT_REFERENCE'   => 'مرجعُ سجلِّ التدقيق',
);
$nT = 0; $nF = 0;
foreach ($ents as $e => $list) {
    $md .= "\n## " . ($TITLES[$e] ?? $e) . "  ·  `$e`\n\n";
    $states = array();
    foreach ($list as $x) { $states[$x['from_state']] = true; $states[$x['to_state']] = true; }
    $md .= '**الحالات:** ' . implode(' · ', array_keys($states)) . "\n\n";
    $md .= "### الانتقالاتُ المسموحة\n\n";
    $md .= "| من | إلى | المالك | الشرطُ المسبق | المستندُ الرسميّ | بوّابةُ الاعتماد | إعادةُ الفتح | التصحيح |\n";
    $md .= "|---|---|---|---|---|---|---|---|\n";
    foreach ($list as $x) {
        if ((int) $x['allowed'] !== 1) { continue; }
        $nT++;
        $md .= '| ' . $x['from_state'] . ' | ' . $x['to_state'] . ' | ' . $x['owner_role'] . ' | '
             . $x['precondition'] . ' | ' . $x['official_doc'] . ' | ' . $x['approval_gate'] . ' | '
             . $x['reopen_rule'] . ' | ' . $x['correct_rule'] . " |\n";
    }
    $md .= "\n### الممنوعُ صراحةً\n\n| من | إلى | لماذا يُمنع |\n|---|---|---|\n";
    foreach ($list as $x) {
        if ((int) $x['allowed'] !== 0) { continue; }
        $nF++;
        $md .= '| ' . $x['from_state'] . ' | ' . $x['to_state'] . ' | ' . $x['forbid_reason'] . " |\n";
    }
}
$md .= "\n---\n\n**المقيس:** " . count($ents) . " كيانًا · $nT انتقالًا مسموحًا · $nF ممنوعًا صراحةً.\n"
     . "⛔ **والكيانُ بلا ممنوعٍ صريحٍ لا يُغلَق** — `W10-16` يسقط عليه.\n";
file_put_contents($DIR . '/W10_STATE_MACHINES.md', $md);
echo "✔ W10_STATE_MACHINES.md — " . count($ents) . " كيانًا · $nT/$nF\n";

/* ═══ ② فصلُ الواجبات ══════════════════════════════════════════════════ */
$md = "# RPR-W10 — مصفوفةُ فصلِ الواجباتِ لنطاقِ الشقّ\n\n" . $HDR;
$md .= "**فصلُ الواجباتِ يُنفَّذ لا يُعلَن**: لكلِّ عمليّةٍ حرِجةٍ رمزُ ردٍّ **مُثبَتٌ من "
     . "القرص** في `app/Services/Governance/DeptSplitService.php`، و`W10-17` يقرأ الملفَّ "
     . "ولا يصدّق السجلّ.\n\n⛔ **ولا أسماءَ أشخاصٍ** — مفاتيحُ أدوارٍ وقواعدُ صلاحيةٍ ونائبٌ ونطاقٌ وتفويضٌ وتاريخُ سريان.\n\n";
$r = $conn->query("SELECT * FROM repair01_w10_sod ORDER BY process_key");
$n = 0;
while ($r && $x = $r->fetch_assoc()) {
    $n++;
    $md .= "\n## " . $x['process_key'] . "  ·  " . $x['process_name'] . "\n\n";
    $md .= "| الدور | المفتاح |\n|---|---|\n";
    $md .= '| Initiator | `' . $x['initiator_role'] . "` |\n";
    $md .= '| Reviewer | `' . $x['reviewer_role'] . "` |\n";
    $md .= '| Approver | `' . $x['approver_role'] . "` |\n";
    $md .= '| Executor | `' . $x['executor_role'] . "` |\n";
    $md .= '| Reconciler/Closer | `' . $x['closer_role'] . "` |\n";
    $md .= '| Deputy_Role | `' . $x['deputy_role'] . "` |\n";
    $md .= '| Authority_Rule_ID | `' . $x['authority_rule_id'] . "` |\n";
    $md .= '| Scope | ' . $x['scope_note'] . " |\n";
    $md .= '| Delegation | ' . $x['delegation_rule'] . " |\n";
    $md .= '| Effective_Date | ' . $x['effective_date'] . " |\n";
    $md .= "\n**التركيبةُ الممنوعةُ صراحةً:** " . $x['forbidden_combo']
         . "\n**رمزُ الردِّ المنفِّذ:** `" . $x['enforced_by'] . "`\n";
}
$md .= "\n---\n\n**المقيس:** $n عمليّةً حرِجةً — ولكلٍّ رمزُ ردٍّ مُثبَتٌ من القرص.\n";
file_put_contents($DIR . '/W10_SOD.md', $md);
echo "✔ W10_SOD.md — $n عمليّة\n";

/* ═══ ③ دليلُ عبورِ الرحلة ═════════════════════════════════════════════ */
$run = '';
$r = $conn->query("SELECT run_id, COUNT(*) c, SUM(passed) p FROM repair01_w10_journey
                    GROUP BY run_id ORDER BY MAX(id) DESC LIMIT 1");
if ($r && $x = $r->fetch_assoc()) { $run = (string) $x['run_id']; }
$md = "# RPR-W10 — دليلُ عبورِ رحلةِ الشقّ\n\n" . $HDR;
$md .= "**الجولة:** `$run`\n\n";
$md .= "أربعةُ أشواطٍ متتابعةٍ كما نصَّ §٦-أ، وشوطٌ خامسٌ لفصلِ الواجباتِ وسادسٌ للنظافة. "
     . "**ولكلِّ محطّةٍ مستهلكٌ بالاسمِ وأثرٌ تجاريٌّ مقيس** — لا «الحدثُ سُجِّل».\n\n";
$LEGS = array(
    'ACCOUNTING_SURFACE' => '① سطحٌ محاسبيٌّ يصل مالكَه DEP-05',
    'TREASURY_SURFACE'   => '② سطحُ تنفيذٍ نقديٍّ يصل مالكَه DEP-06',
    'LEGACY_BRIDGE'      => '③ رابطٌ قديمٌ ما زال يعمل عبرَ الجسر',
    'AUDIT_READBACK'     => '④ سجلُّ تدقيقٍ يُقرأ بمعرّفِه الأصليّ',
    'SEGREGATION'        => '⑤ فصلُ الواجباتِ منفَّذٌ لا مُعلَن',
    'CLEANUP'            => '⑥ النظافة — الرحلةُ لا تترك أثرًا',
);
$byLeg = array();
$r = $conn->query("SELECT * FROM repair01_w10_journey WHERE run_id = '"
    . $conn->real_escape_string($run) . "' ORDER BY seq");
$tot = 0; $ok = 0; $cons = array();
while ($r && $x = $r->fetch_assoc()) {
    $byLeg[$x['leg']][] = $x; $tot++;
    if ((int) $x['passed'] === 1) { $ok++; }
    $cons[$x['consumer']] = true;
}
foreach ($LEGS as $lg => $title) {
    if (!isset($byLeg[$lg])) { continue; }
    $md .= "\n## $title\n\n| # | المحطّة | الفاعل | المستهلك | الأثرُ التجاريُّ المقيس | الحكم |\n";
    $md .= "|---:|---|---|---|---|---|\n";
    foreach ($byLeg[$lg] as $x) {
        $md .= '| ' . $x['seq'] . ' | ' . $x['station'] . ' | ' . $x['actor'] . ' | `'
             . $x['consumer'] . '` | ' . $x['business_effect'] . ' | '
             . ((int) $x['passed'] === 1 ? '✔' : '✘') . " |\n";
    }
}
$md .= "\n---\n\n**المقيس:** $ok من $tot محطّةً · " . count($cons) . " مستهلكًا متمايزًا · "
     . count($byLeg) . " أشواط.\n"
     . "⛔ **ولا تُقبل المرحلةُ ببناءِ أسطحِها إن لم تعبر رحلتُها** ولو كانت كلُّ بوّاباتِها خضراء.\n";
file_put_contents($DIR . '/W10_JOURNEY_EVIDENCE.md', $md);
echo "✔ W10_JOURNEY_EVIDENCE.md — $ok/$tot محطّة · " . count($cons) . " مستهلكًا\n";
