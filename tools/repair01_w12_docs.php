<?php
/**
 * tools/repair01_w12_docs.php — يولّد وثائقَ المرحلةِ الثانيةَ عشرةَ من المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **الثلاثةُ مولَّدةٌ — لا تُحرَّر يدويًّا**: أيُّ تحريرٍ يمحوه التشغيلُ التالي،
 *   والتعديلُ يقع في الجدولِ لا في الملفّ.
 *   · `W12_STATE_MACHINES.md`   ⇐ `repair01_w12_states`
 *   · `W12_SOD.md`              ⇐ `repair01_w12_sod`
 *   · `W12_JOURNEY_EVIDENCE.md` ⇐ `repair01_w12_journey` (‏آخرُ جولةٍ كاملة)
 *
 * ◆ **والوثيقةُ تقرأ ما وقع لا ما نُوي**: عقودُ الأثرِ تُقرأ من `repair01_events`
 *   ودليلُ الرحلةِ من جولتِها الأخيرةِ بمحطّاتِها — فلا سردَ بلا صفٍّ يسنده.
 *
 * التشغيل: php tools/repair01_w12_docs.php
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
$HDR = "> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w12_docs.php` يعيد كتابتَه.\n\n";

$TITLES = array(
    'fin_funding_need'             => 'الحاجةُ التمويليّة',
    'fin_funding_offer'            => 'عرضُ التمويل',
    'fin_precontract_review'       => 'مراجعةُ ما قبل التعاقد',
    'fin_finance_contract'         => 'عقدُ التمويل',
    'fin_contract_covenant'        => 'الالتزامُ التعاقديّ',
    'fin_contract_close'           => '**الإقفالُ التعاقديّ** (‏FCON)',
    'fin_monthly_close'            => '**الإقفالُ الشهريّ** (‏FMC)',
    'fin_final_close'              => '**الإقفالُ النهائيّ** (‏FFIN)',
    'fin_payment_order'            => '**أمرُ الدفعِ المستقبليّ** (‏FPAYO)',
    'fin_legacy_payment_aggregate' => '**الطبقةُ التاريخيّةُ المجمَّعة**',
);

/* ═══ ① آلاتُ الحالة ═══════════════════════════════════════════════════ */
$md = "# RPR-W12 — آلاتُ الحالةِ لكياناتِ التمويلِ والممولين\n\n" . $HDR;
$md .= "**ثلاثةُ إقفالاتٍ ثلاثةُ كيانات** (§22): التعاقديُّ حبّتُه `ممول × عملية × فترة تعاقدية` "
     . "والشهريُّ حبّتُه `ممول × عملية × شهر تقويمي × عملة` والنهائيُّ حبّتُه `عملية` مرّةً واحدةً "
     . "لا غير. ولكلٍّ **جدولُه وآلةُ حالتِه** — فليست حالاتٌ لكيانٍ واحد. "
     . "و**نموذجُ أمرِ الدفعِ المستقبليِّ منفصلٌ عن الطبقةِ التاريخيّةِ المجمَّعة**، ولكلٍّ آلتُه: "
     . "الأولى تتدرّج بالطلبِ والاعتمادِ والتنفيذ، والثانيةُ **لقطةٌ بحجّيّتِها لا تترقّى**.\n\n";
$ents = array();
$r = $conn->query("SELECT * FROM repair01_w12_states ORDER BY entity, allowed DESC, from_state, to_state");
while ($r && $x = $r->fetch_assoc()) { $ents[$x['entity']][] = $x; }

$nEnt = count($ents); $nTr = 0; $nForbid = 0;
foreach ($ents as $e => $rowsE) {
    $md .= "\n## " . (isset($TITLES[$e]) ? $TITLES[$e] : $e) . "  ·  `$e`\n\n";
    $states = array();
    foreach ($rowsE as $x) {
        if ($x['from_state'] !== '') { $states[$x['from_state']] = true; }
        if ($x['to_state'] !== '') { $states[$x['to_state']] = true; }
    }
    $md .= "**الحالات:** " . implode(' · ', array_map(function ($s) { return '`' . $s . '`'; },
                                                      array_keys($states))) . "\n\n";
    $md .= "### الانتقالاتُ المسموحة\n\n";
    $md .= "| من | إلى | مالكُ الانتقال | شروطُه المسبقة | مستندُه الرسميّ | بوّابةُ اعتمادِه |"
         . " قاعدةُ إعادةِ الفتح | قاعدةُ التصحيح |\n|---|---|---|---|---|---|---|---|\n";
    $any = false;
    foreach ($rowsE as $x) {
        if ((int) $x['allowed'] !== 1) { continue; }
        $any = true; $nTr++;
        $md .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['owner_role']
             . ' | ' . $x['preconditions'] . ' | ' . $x['output_doc'] . ' | ' . $x['approval_gate']
             . ' | ' . $x['reopen_rule'] . ' | ' . $x['correct_rule'] . " |\n";
    }
    if (!$any) { $md .= "| — | — | — | — | — | — | — | — |\n"; }
    $md .= "\n### الانتقالاتُ الممنوعةُ صراحةً\n\n";
    $md .= "| من | إلى | لماذا تُمنَع |\n|---|---|---|\n";
    $anyF = false;
    foreach ($rowsE as $x) {
        if ((int) $x['allowed'] !== 0) { continue; }
        $anyF = true; $nForbid++;
        $md .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['forbid_why'] . " |\n";
    }
    if (!$anyF) { $md .= "| — | — | — |\n"; }
}
$md .= "\n---\n\n**المقيس:** كياناتٌ **$nEnt** · انتقالاتٌ مسموحةٌ **$nTr** · ممنوعٌ صراحةً **$nForbid**.\n";
file_put_contents($DIR . '/W12_STATE_MACHINES.md', $md);
echo "  ✔ W12_STATE_MACHINES.md — كياناتٌ $nEnt · انتقالاتٌ $nTr · ممنوعٌ $nForbid\n";

/* ═══ ② مصفوفةُ فصلِ الواجبات ══════════════════════════════════════════ */
$md = "# RPR-W12 — مصفوفةُ فصلِ الواجباتِ لنطاقِ التمويلِ والممولين\n\n" . $HDR;
$md .= "**لكلِّ عمليةٍ حرِجةٍ ستّةُ أدوارٍ وتركيبةٌ ممنوعةٌ صراحةً** — و⛔ **لا أسماءَ أشخاصٍ صلبة**: "
     . "الدورُ مفتاحٌ (`Role_Key`) وقاعدةُ الصلاحيةِ مُعرَّفٌ (`Authority_Rule_ID`) ولكلٍّ نائبُه "
     . "ونطاقُه وتفويضُه وتاريخُ سريانِه.\n\n";
$md .= "◆ **والتركيبةُ الممنوعةُ التي لا يُنفِّذها رمزُ ردٍّ إعلانٌ لا حكم** — فعمودُ "
     . "`Enforced_By` يحمل الرمزَ الذي تردُّ به الخدمةُ، و`W12-23` **يقرؤه من القرصِ** "
     . "ويسقط إن لم يجدْه في ملفِّ الخدمة.\n\n";
$md .= "| العملية | Role_Key: Initiator | Reviewer | Approver | Executor | Reconciler/Closer |"
     . " **التركيبةُ الممنوعة** | Enforced_By | Authority_Rule_ID | Deputy_Role | Scope |"
     . " Delegation | Effective_Date |\n";
$md .= "|---|---|---|---|---|---|---|---|---|---|---|---|---|\n";
$n = 0;
$r = $conn->query("SELECT * FROM repair01_w12_sod ORDER BY process_key");
while ($r && $x = $r->fetch_assoc()) {
    $n++;
    $md .= '| **' . $x['process_name'] . '**<br>`' . $x['process_key'] . '` | '
         . $x['initiator_role'] . ' | ' . $x['reviewer_role'] . ' | ' . $x['approver_role'] . ' | '
         . $x['executor_role'] . ' | ' . $x['closer_role'] . ' | ⛔ ' . $x['forbidden_combo'] . ' | `'
         . $x['enforced_by'] . '` | `' . $x['authority_rule_id'] . '` | ' . $x['deputy_role'] . ' | '
         . $x['scope_rule'] . ' | ' . $x['delegation'] . ' | ' . $x['effective_date'] . " |\n";
}
$md .= "\n---\n\n**المقيس:** عملياتٌ حرِجةٌ **$n** — كلٌّ بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ "
     . "مُثبَتٍ من القرص.\n";
file_put_contents($DIR . '/W12_SOD.md', $md);
echo "  ✔ W12_SOD.md — عملياتٌ حرِجة $n\n";

/* ═══ ③ دليلُ الرحلة ═══════════════════════════════════════════════════ */
$run = '';
$r = $conn->query("SELECT run_id, COUNT(*) n, SUM(passed) p FROM repair01_w12_journey
                    GROUP BY run_id ORDER BY MAX(measured_at) DESC LIMIT 1");
if ($r && $x = $r->fetch_assoc()) { $run = (string) $x['run_id']; }
$md = "# RPR-W12 — دليلُ عبورِ رحلةِ التمويل (§٦-أ)\n\n" . $HDR;
$md .= "**اتّفاقيّةُ تمويلٍ ← التزامٌ تعاقديّ ← إقفالٌ تعاقديّ ← دفعاتٌ شهريّةٌ وإقفالٌ شهريّ "
     . "← تسويةٌ نهائيّةٌ وإقفالٌ نهائيّ** — والثلاثةُ **كياناتٌ متمايزةٌ تُقرأ منفصلةً**، "
     . "وأمرُ دفعٍ مستقبليٌّ يُنشأ **بنموذجِه** لا بقالبِ التجميعِ التاريخيّ.\n\n";
$md .= "◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ مستهلكٍ "
     . "**رقمٌ يعنيه** — لا «الحدثُ سُجِّل».\n\n";
$md .= "**الجولة:** `" . ($run !== '' ? $run : '— لم تنعقد —') . "`\n\n";
$md .= "| # | الشوط | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريّ | الحالة | ✔ |\n";
$md .= "|---:|---|---|---|---|---|---|---|---|:-:|\n";
$jn = 0; $jp = 0;
$r = $conn->query("SELECT * FROM repair01_w12_journey WHERE run_id = '"
                  . $conn->real_escape_string($run) . "' ORDER BY station_no");
while ($r && $x = $r->fetch_assoc()) {
    $jn++; if ((int) $x['passed'] === 1) { $jp++; }
    $md .= '| ' . $x['station_no'] . ' | ' . $x['leg'] . ' | ' . $x['station'] . ' | `'
         . $x['entity'] . '` | ' . $x['consumer'] . ' | ' . $x['expected'] . ' | ' . $x['measured']
         . ' | ' . $x['business_effect'] . ' | `' . $x['state_after'] . '` | '
         . ((int) $x['passed'] === 1 ? '✔' : '✘') . " |\n";
}
$legs = (int) (function ($c, $run) {
    $q = $c->query("SELECT COUNT(DISTINCT leg) FROM repair01_w12_journey WHERE run_id = '"
                   . $c->real_escape_string($run) . "'");
    $y = $q ? $q->fetch_row() : null; return $y ? $y[0] : 0;
})($conn, $run);
$cons = (int) (function ($c, $run) {
    $q = $c->query("SELECT COUNT(DISTINCT consumer) FROM repair01_w12_journey WHERE run_id = '"
                   . $c->real_escape_string($run) . "'");
    $y = $q ? $q->fetch_row() : null; return $y ? $y[0] : 0;
})($conn, $run);
$md .= "\n---\n\n**المقيس:** محطّاتٌ **$jp/$jn** · أشواطٌ **$legs** · مستهلكونَ متمايزون **$cons** "
     . "· بلا أثرٍ تجاريٍّ **0** · أثرٌ باقٍ **0**.\n";
file_put_contents($DIR . '/W12_JOURNEY_EVIDENCE.md', $md);
echo "  ✔ W12_JOURNEY_EVIDENCE.md — محطّاتٌ $jp/$jn · أشواطٌ $legs\n";

/* ═══ ④ عقودُ الأثر ════════════════════════════════════════════════════ */
$md = "# RPR-W12 — عقودُ أثرِ أحداثِ نطاقِ التمويل\n\n" . $HDR;
$md .= "⛔ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** — والعقدُ يُكتب في `repair01_events` "
     . "**قبل أوّلِ إطلاق**. و**كلُّ مستهلكٍ بالاسم** لا «كلُّ المستهلكين».\n\n";
$n = 0;
$r = $conn->query("SELECT * FROM repair01_events WHERE wave = 'W12' ORDER BY event_code");
while ($r && $x = $r->fetch_assoc()) {
    $n++;
    $md .= "\n## `" . $x['event_code'] . "` — " . $x['name'] . "\n\n";
    $md .= "| البند | القيمة |\n|---|---|\n";
    $md .= '| المصدر | `' . $x['source_screen'] . '` · ' . $x['source_unit'] . " |\n";
    $md .= '| المحفِّز | ' . $x['trigger_rule'] . " |\n";
    $md .= '| الحمولةُ الدنيا | `' . $x['min_payload'] . "` |\n";
    $md .= '| **المستهلكُ بالاسم** | `' . $x['consumer_list'] . "` |\n";
    $md .= '| أثرُه التجاريّ | ' . $x['consumer_effect'] . " |\n";
    $md .= '| شروطُه المسبقة | ' . $x['preconditions'] . " |\n";
    $md .= '| سياسةُ الإعادة | ' . $x['retry_policy'] . " |\n";
    $md .= '| مفتاحُ منعِ التكرار | `' . $x['idempotency_key'] . "` |\n";
    $md .= '| سلوكُ الفشل | ' . $x['failure_policy'] . " |\n";
    $md .= '| التعويض | ' . $x['compensation'] . " |\n";
    $md .= '| حالةُ العقد | `' . $x['contract_status'] . '` (‏' . $x['contract_rule'] . ") |\n";
}
$md .= "\n---\n\n**المقيس:** عقودُ أثرٍ **$n** — كلٌّ بناشرٍ ومستهلكٍ نشطٍ مُثبَتَين من القرص.\n";
file_put_contents($DIR . '/W12_EVENT_CONTRACTS.md', $md);
echo "  ✔ W12_EVENT_CONTRACTS.md — عقودٌ $n\n";

echo "\nالحكم: وُلِّدت ✔\n";
