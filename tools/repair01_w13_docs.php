<?php
/**
 * tools/repair01_w13_docs.php — يولّد وثائقَ المرحلةِ الثالثةَ عشرةَ من المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **الأربعةُ مولَّدةٌ — لا تُحرَّر يدويًّا**: أيُّ تحريرٍ يمحوه التشغيلُ التالي،
 *   والتعديلُ يقع في الجدولِ لا في الملفّ.
 *   · `W13_STATE_MACHINES.md`   ⇐ `repair01_w13_states`
 *   · `W13_SOD.md`              ⇐ `repair01_w13_sod`
 *   · `W13_EVENT_CONTRACTS.md`  ⇐ `repair01_events` (‏موجةُ W13)
 *   · `W13_JOURNEY_EVIDENCE.md` ⇐ `repair01_w13_journey` (‏آخرُ جولةٍ كاملة)
 *
 * ◆ **والوثيقةُ تقرأ ما وقع لا ما نُوي**: عقودُ الأثرِ تُقرأ من السجلِّ ودليلُ
 *   الرحلةِ من جولتِها الأخيرةِ بمحطّاتِها — فلا سردَ بلا صفٍّ يسنده.
 *
 * التشغيل: php tools/repair01_w13_docs.php
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
$HDR = "> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w13_docs.php` يعيد كتابتَه.\n\n";

$TITLES = array(
    'tkt_verification'      => '**دورةُ التحقّقِ والإغلاق** (‏معالجة ← تحقّق ← إغلاق)',
    'tkt_assignment'        => 'إسنادُ البلاغِ لمكلَّف',
    'tkt_routing'           => 'توجيهُ البلاغِ لإدارةٍ مختصّة',
    'tkt_reopen'            => 'إعادةُ فتحِ البلاغ',
    'tkt_subject_type'      => 'نوعُ محلِّ البلاغِ في الكتالوج',
    'hr_disciplinary_case'  => '**القضيّةُ التأديبيّة** (‏واقعة ← تحقيق ← قرار)',
    'hr_job_movement'       => 'الحركةُ الوظيفيّة',
    'hr_onboarding_item'    => 'بندُ التهيئةِ والمباشرة',
    'hr_employee_document'  => 'مستندُ الموظّف',
    'hr_training_record'    => 'سجلُّ التدريبِ والكفاءة',
    'hr_performance_review' => 'تقييمُ الأداءِ الوظيفيّ',
    'hr_benefit_enrollment' => 'اشتراكُ المزايا والتأمينات',
);

/* ═══ ① آلاتُ الحالة ═══════════════════════════════════════════════════ */
$md = "# RPR-W13 — آلاتُ الحالةِ لكياناتِ الموارد البشرية والبلاغات\n\n" . $HDR;
$md .= "**أربعةُ أطرافٍ لا طرفان** (§28): `Reporter ≠ Subject ≠ Ticket Owner ≠ Resolution Owner`. "
     . "ودورةُ التذكرةِ مملوكةٌ لمركزِ البلاغاتِ **وتنفيذُ الحلِّ مملوكٌ للإدارةِ المختصّة** (§9) — "
     . "فآلةُ حالةِ دورةِ التحقّقِ تتقدَّم بيدِ مالكِ الحلِّ وتُغلَق بيدِ مالكِ التذكرة، "
     . "**ولا تعبر التحقّقَ إلى الإغلاق**. ودورةُ الموظّفِ سبعُ آلاتٍ متمايزة، "
     . "**والقضيّةُ التأديبيّةُ عمليةٌ بمراحلِها لا حقلُ خصمٍ في المسيّر**.\n\n";
$ents = array();
$r = $conn->query("SELECT * FROM repair01_w13_states ORDER BY entity, allowed DESC, from_state, to_state");
while ($r && $x = $r->fetch_assoc()) { $ents[$x['entity']][] = $x; }

$nEnt = count($ents); $nTr = 0; $nForbid = 0;
foreach ($ents as $e => $rowsE) {
    $md .= "\n## " . (isset($TITLES[$e]) ? $TITLES[$e] : $e) . "  ·  `$e`\n\n";
    $states = array();
    foreach ($rowsE as $x) {
        if ((int) $x['allowed'] === 1) { $states[$x['from_state']] = true; $states[$x['to_state']] = true; }
    }
    $md .= '**الحالات:** ' . implode(' · ', array_map(function ($s) { return '`' . $s . '`'; },
           array_keys($states))) . "\n\n";
    $md .= "| من | إلى | مالكُ الانتقال | شروطُه المسبقة | المستندُ الرسميّ | بوّابةُ الاعتماد | إعادةُ الفتح | التصحيح |\n";
    $md .= "|---|---|---|---|---|---|---|---|\n";
    foreach ($rowsE as $x) {
        if ((int) $x['allowed'] !== 1) { continue; }
        $nTr++;
        $md .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['owner_role']
             . ' | ' . $x['preconditions'] . ' | ' . $x['output_doc'] . ' | ' . $x['approval_gate']
             . ' | ' . $x['reopen_rule'] . ' | ' . $x['correct_rule'] . " |\n";
    }
    $forb = array();
    foreach ($rowsE as $x) { if ((int) $x['allowed'] === 0) { $forb[] = $x; } }
    if ($forb) {
        $md .= "\n**ممنوعٌ صراحةً:**\n\n";
        foreach ($forb as $x) {
            $nForbid++;
            $md .= '- ⛔ `' . $x['from_state'] . '` ⇐ `' . $x['to_state'] . '` — ' . $x['forbid_why'] . "\n";
        }
    }
}
$md .= "\n---\n\n**المقيس:** كيانات **$nEnt** · انتقالاتٌ مسموحة **$nTr** · ممنوعٌ صراحةً **$nForbid**.\n";
file_put_contents($DIR . '/W13_STATE_MACHINES.md', $md);
echo "  ✔ W13_STATE_MACHINES.md — كيانات $nEnt · انتقالات $nTr · ممنوع $nForbid\n";

/* ═══ ② فصلُ الواجبات ═══════════════════════════════════════════════════ */
$md = "# RPR-W13 — مصفوفةُ فصلِ الواجباتِ للموارد البشرية والبلاغات\n\n" . $HDR;
$md .= "⛔ **لا أسماءَ أشخاصٍ صلبة** — الدورُ ومرجعُ صلاحيتِه ونائبُه ومداه وتفويضُه وتاريخُ سريانِه.\n\n"
     . "◆ **والتركيبةُ الممنوعةُ مُنفَّذةٌ برمزِ ردٍّ يُقرأ** في `PeopleCycleService` — "
     . "فإعلانُ فصلِ واجباتٍ بلا رمزِ ردٍّ في الشيفرةِ وعدٌ لا وفاء، و`W13-17` يقيس وجودَ الرمزِ "
     . "في ملفِّ الخدمةِ نصًّا لا في الدفترِ وحدَه.\n\n";
$md .= "| العملية | Initiator | Reviewer | Approver | Executor | Reconciler/Closer | ⛔ التركيبةُ الممنوعة | رمزُ الردِّ المنفَّذ | Authority_Rule_ID | Deputy_Role | Scope | Delegation | Effective_Date |\n";
$md .= "|---|---|---|---|---|---|---|---|---|---|---|---|---|\n";
$nSod = 0;
$r = $conn->query("SELECT * FROM repair01_w13_sod ORDER BY process_key");
while ($r && $x = $r->fetch_assoc()) {
    $nSod++;
    $md .= '| `' . $x['process_key'] . '` ' . $x['process_name'] . ' | ' . $x['initiator_role']
         . ' | ' . $x['reviewer_role'] . ' | ' . $x['approver_role'] . ' | ' . $x['executor_role']
         . ' | ' . $x['closer_role'] . ' | ' . $x['forbidden_combo'] . ' | `' . $x['enforced_by']
         . '` | ' . $x['authority_rule_id'] . ' | ' . $x['deputy_role'] . ' | ' . $x['scope_rule']
         . ' | ' . $x['delegation'] . ' | ' . $x['effective_date'] . " |\n";
}
$md .= "\n---\n\n**المقيس:** عملياتٌ حرِجة **$nSod** — ولكلٍّ رمزُ ردٍّ مُثبَتٌ في ملفِّ الخدمة.\n";
file_put_contents($DIR . '/W13_SOD.md', $md);
echo "  ✔ W13_SOD.md — عمليات $nSod\n";

/* ═══ ③ عقودُ الأثر ═════════════════════════════════════════════════════ */
$md = "# RPR-W13 — عقودُ الأثرِ لأحداثِ الموارد البشرية والبلاغات\n\n" . $HDR;
$md .= "⛔ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** (§٧). و`W13-19` يقرأ **مفاتيحَ الأحداثِ من "
     . "شيفرةِ الخدمةِ نفسِها** ويشترط لكلٍّ صفًّا هنا — فحدثٌ يُطلَق بلا عقدٍ يُسقط البوّابة.\n\n"
     . "◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عمودُ «أثرُ المستهلك» "
     . "رقمٌ يتغيّر عند قارئٍ مسمًّى، ورحلةُ §٦-أ تقيسه محطّةً محطّة.\n\n";
$nEv = 0;
$r = $conn->query("SELECT * FROM repair01_events WHERE wave = 'W13' ORDER BY event_code");
while ($r && $x = $r->fetch_assoc()) {
    $nEv++;
    $md .= "\n## `" . $x['event_code'] . "` — " . $x['name'] . "\n\n";
    $md .= '| البند | القيمة |' . "\n|---|---|\n";
    $md .= '| المصدر | `' . $x['source_screen'] . '` · ' . $x['source_unit'] . " |\n";
    $md .= '| المحفِّز | ' . $x['trigger_rule'] . " |\n";
    $md .= '| الحمولةُ الدنيا | `' . $x['min_payload'] . "` |\n";
    $md .= '| **المستهلكُ الفعليُّ بالاسم** | `' . $x['consumer_list'] . "` |\n";
    $md .= '| **أثرُه التجاريّ** | ' . $x['consumer_effect'] . " |\n";
    $md .= '| الشروطُ المسبقة | ' . $x['preconditions'] . " |\n";
    $md .= '| سياسةُ الإعادة | ' . $x['retry_policy'] . " |\n";
    $md .= '| مفتاحُ منعِ التكرار | `' . $x['idempotency_key'] . "` |\n";
    $md .= '| سلوكُ الفشل | ' . $x['failure_policy'] . " |\n";
    $md .= '| التعويض | ' . $x['compensation'] . " |\n";
}
$md .= "\n---\n\n**المقيس:** عقودُ أثرٍ **$nEv** — ولكلٍّ مستهلكٌ واحدٌ بالاسمِ وأثرٌ تجاريٌّ مقيس.\n";
file_put_contents($DIR . '/W13_EVENT_CONTRACTS.md', $md);
echo "  ✔ W13_EVENT_CONTRACTS.md — عقود $nEv\n";

/* ═══ ④ دليلُ عبورِ الرحلة ══════════════════════════════════════════════ */
$run = '';
$rr = $conn->query("SELECT run_id FROM repair01_w13_journey ORDER BY measured_at DESC LIMIT 1");
if ($rr && $rx = $rr->fetch_row()) { $run = (string) $rx[0]; }
$md = "# RPR-W13 — دليلُ عبورِ رحلةِ العامل (§٦-أ)\n\n" . $HDR;
$md .= "**تعيينٌ ← تأهيلٌ وجاهزيّة ← إسنادٌ لموقع ← حضورٌ فعليّ ← أداء ← بلاغٌ يخصّه يُنشئه "
     . "مُبلِّغٌ غيرُه ← توجيهٌ لمالكِ الحلِّ في إدارتِه المختصّة ← تصعيدٌ عند التجاوز ← تحقّق ← "
     . "إغلاق ← تسويةُ مستحقّاتِه.**\n\n"
     . "◆ **وأطرافُ التذكرةِ الأربعةُ أشخاصٌ متمايزون في السجلّ** — والمحطّةُ الختاميّةُ تقيس "
     . "`COUNT(DISTINCT party_role)` و`COUNT(DISTINCT actor_id)` معًا، فأربعةُ أدوارٍ بثلاثةِ "
     . "أشخاصٍ تُسقط الرحلة.\n\n"
     . "◆ **والمحطّةُ السالبةُ محطّة**: كلُّ سطرٍ حكمُه `مرفوض` استُدعيت فيه الخدمةُ فعلًا "
     . "ورُدَّت **برمزٍ يُقرأ** — لا بامتناعٍ عن الاستدعاء.\n\n";
$md .= "**الجولة:** `$run`\n\n";
$md .= "| # | الشوط | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالة | العبور |\n";
$md .= "|---:|---|---|---|---|---|---|---|---|:--:|\n";
$nJ = 0; $okJ = 0; $legs = array(); $cons = array();
$r = $conn->query("SELECT * FROM repair01_w13_journey WHERE run_id = '"
                  . $conn->real_escape_string($run) . "' ORDER BY station_no");
while ($r && $x = $r->fetch_assoc()) {
    $nJ++; if ((int) $x['passed'] === 1) { $okJ++; }
    $legs[$x['leg']] = true; $cons[$x['consumer']] = true;
    $md .= '| ' . $x['station_no'] . ' | ' . $x['leg'] . ' | ' . $x['station'] . ' | `' . $x['entity']
         . '` | `' . $x['consumer'] . '` | ' . $x['expected'] . ' | ' . $x['measured'] . ' | '
         . $x['business_effect'] . ' | `' . $x['state_after'] . '` | '
         . ((int) $x['passed'] === 1 ? '✔' : '✘') . " |\n";
}
$md .= "\n---\n\n**المقيس:** محطّات **$okJ/$nJ** · أشواط **" . count($legs)
     . "** · مستهلكونَ متمايزون **" . count($cons) . "** · بلا أثرٍ تجاريٍّ **"
     . (int) (($conn->query("SELECT COUNT(*) FROM repair01_w13_journey WHERE run_id = '"
        . $conn->real_escape_string($run) . "' AND business_effect = ''")->fetch_row())[0]) . "**.\n";
file_put_contents($DIR . '/W13_JOURNEY_EVIDENCE.md', $md);
echo "  ✔ W13_JOURNEY_EVIDENCE.md — محطّات $okJ/$nJ · أشواط " . count($legs)
   . ' · مستهلكون ' . count($cons) . "\n";
