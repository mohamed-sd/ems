<?php
/**
 * tools/repair01_w11_docs.php — يولّد وثائقَ المرحلةِ الحاديةَ عشرةَ من المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **الثلاثةُ مولَّدةٌ — لا تُحرَّر يدويًّا**: أيُّ تحريرٍ يمحوه التشغيلُ التالي،
 *   والتعديلُ يقع في الجدولِ لا في الملفّ.
 *   · `W11_STATE_MACHINES.md`   ⇐ `repair01_w11_states`
 *   · `W11_SOD.md`              ⇐ `repair01_w11_sod`
 *   · `W11_JOURNEY_EVIDENCE.md` ⇐ `repair01_w11_journey` (‏آخرُ جولةٍ كاملة)
 *
 * ◆ **والوثيقةُ تقرأ ما وقع لا ما نُوي**: عقودُ الأثرِ تُقرأ من `repair01_events`
 *   ودليلُ الرحلةِ من جولتِها الأخيرةِ بمحطّاتِها — فلا سردَ بلا صفٍّ يسنده.
 *
 * التشغيل: php tools/repair01_w11_docs.php
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
$HDR = "> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w11_docs.php` يعيد كتابتَه.\n\n";

$TITLES = array(
    'acc_recognition_request'   => 'طلبُ الاعتراف',
    'fin_journal_entries'       => 'القيدُ اليوميّ',
    'acc_period_adjustment'     => 'تسويةُ نهايةِ الفترة',
    'acc_account_recon'         => 'مطابقةُ الحسابِ الرقابيّ',
    'fin_financial_periods'     => 'الفترةُ المحاسبيّة',
    'acc_period_reopen_request' => 'طلبُ إعادةِ فتحِ الفترة',
    'fin_payments'              => 'أمرُ الدفعِ وسندُ القبض',
    'tre_transfer'              => 'التحويلُ بين الأوعية',
    'tre_instrument'            => 'الأداةُ الماليّة',
    'tre_petty_custody'         => 'عهدةُ النثريّة',
    'tre_cash_count'            => 'الجردُ النقديّ',
    'bank_statements'           => 'جلسةُ المطابقةِ البنكيّة',
    'tre_guarantee'             => 'خطابُ الضمانِ والاعتماد',
);

/* ═══ ① آلاتُ الحالة ═══════════════════════════════════════════════════ */
$md = "# RPR-W11 — آلاتُ الحالةِ لكياناتِ الماليّةِ والخزينة\n\n" . $HDR;
$md .= "**الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03`): كلُّ كيانٍ هنا "
     . "يحمل كيانَه القانونيَّ وفترتَه، ولكلٍّ حالاتُه وانتقالاتُه المسموحةُ بشروطِها "
     . "ومستندِها وبوّابتِها وقاعدتَي إعادةِ الفتحِ والتصحيح — **وممنوعُه الصريحُ مُسبَّبًا**.\n\n";
$ents = array();
$r = $conn->query("SELECT * FROM repair01_w11_states ORDER BY entity, allowed DESC, from_state, to_state");
while ($r && $x = $r->fetch_assoc()) { $ents[$x['entity']][] = $x; }

$nEnt = count($ents); $nTr = 0; $nForbid = 0;
foreach ($ents as $e => $rowsE) {
    $md .= "\n## " . (isset($TITLES[$e]) ? $TITLES[$e] : $e) . "  ·  `$e`\n\n";
    $states = array();
    foreach ($rowsE as $x) { $states[$x['from_state']] = 1; $states[$x['to_state']] = 1; }
    $md .= "**الحالات:** " . implode(' · ', array_map(function ($s) { return '`' . $s . '`'; },
        array_keys($states))) . "\n\n";
    $md .= "| من | إلى | المالك | الشرطُ المسبق | المستندُ الرسميّ | بوّابةُ الاعتماد |"
         . " قاعدةُ إعادةِ الفتح | قاعدةُ التصحيح |\n";
    $md .= "|---|---|---|---|---|---|---|---|\n";
    foreach ($rowsE as $x) {
        $nTr++;
        if ((int) $x['allowed'] === 0) { continue; }
        $md .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['owner_role']
             . ' | ' . $x['precondition'] . ' | ' . $x['official_doc'] . ' | ' . $x['approval_gate']
             . ' | ' . $x['reopen_rule'] . ' | ' . $x['correct_rule'] . " |\n";
    }
    $forb = array();
    foreach ($rowsE as $x) { if ((int) $x['allowed'] === 0) { $forb[] = $x; $nForbid++; } }
    if ($forb) {
        $md .= "\n**⛔ ممنوعٌ صراحةً:**\n\n";
        foreach ($forb as $x) {
            $md .= '- `' . $x['from_state'] . '` ⇐ `' . $x['to_state'] . '` — ' . $x['forbid_reason'] . "\n";
        }
    }
}
$md .= "\n---\n\n**المقيس:** كيانات **$nEnt** · انتقالات **$nTr** · ممنوعٌ صراحةً **$nForbid**.\n";
file_put_contents($DIR . '/W11_STATE_MACHINES.md', $md);
echo "  ✔ W11_STATE_MACHINES.md — كيانات $nEnt · انتقالات $nTr · ممنوعٌ $nForbid\n";

/* ═══ ② مصفوفةُ فصلِ الواجبات ═══════════════════════════════════════════ */
$md = "# RPR-W11 — مصفوفةُ فصلِ الواجباتِ للماليّةِ والخزينة\n\n" . $HDR;
$md .= "**لا أسماءَ أشخاصٍ صلبة** — `Role_Key` و`Authority_Rule_ID` و`Deputy_Role` "
     . "و`Scope` و`Delegation` و`Effective_Date`. و**التركيبةُ الممنوعةُ لكلِّ عمليةٍ "
     . "منفَّذةٌ برمزِ ردٍّ في الخدمةِ لا مُعلَنةٌ في وثيقة** — و`W11-11` يفحص وجودَ "
     . "الرمزِ في ملفِّ الخدمةِ نفسِه.\n\n";
$md .= "| العملية | Initiator | Reviewer | Approver | Executor | Reconciler/Closer |\n";
$md .= "|---|---|---|---|---|---|\n";
$sod = array();
$r = $conn->query("SELECT * FROM repair01_w11_sod ORDER BY process_key");
while ($r && $x = $r->fetch_assoc()) { $sod[] = $x; }
foreach ($sod as $x) {
    $md .= '| **' . $x['process_name'] . '**<br>`' . $x['process_key'] . '` | ' . $x['initiator_role']
         . ' | ' . $x['reviewer_role'] . ' | ' . $x['approver_role'] . ' | ' . $x['executor_role']
         . ' | ' . $x['closer_role'] . " |\n";
}
$md .= "\n## التركيبةُ الممنوعةُ ورمزُ ردِّها\n\n";
$md .= "| العملية | ⛔ الممنوعُ صراحةً | رمزُ الردِّ المنفِّذ | قاعدةُ الصلاحية |"
     . " النائب | النطاق | التفويض | السريان |\n";
$md .= "|---|---|---|---|---|---|---|---|\n";
foreach ($sod as $x) {
    $md .= '| `' . $x['process_key'] . '` | ' . $x['forbidden_combo'] . ' | `' . $x['enforced_by']
         . '` | `' . $x['authority_rule_id'] . '` | ' . $x['deputy_role'] . ' | ' . $x['scope_rule']
         . ' | ' . $x['delegation'] . ' | ' . $x['effective_date'] . " |\n";
}
$md .= "\n---\n\n**المقيس:** عملياتٌ حرِجة **" . count($sod) . "** · كلٌّ بستّةِ أدوارٍ وتركيبةٍ "
     . "ممنوعةٍ ورمزِ ردٍّ مُثبَتٍ من القرص.\n";
file_put_contents($DIR . '/W11_SOD.md', $md);
echo "  ✔ W11_SOD.md — عملياتٌ حرِجة " . count($sod) . "\n";

/* ═══ ③ دليلُ الرحلة ════════════════════════════════════════════════════ */
$run = '';
$r = $conn->query("SELECT run_id, COUNT(*) n, SUM(passed) p FROM repair01_w11_journey
                    GROUP BY run_id HAVING n = p ORDER BY MAX(id) DESC LIMIT 1");
$x = $r ? $r->fetch_assoc() : null;
if ($x) { $run = (string) $x['run_id']; }
$md = "# RPR-W11 — دليلُ عبورِ رحلةِ الإقفال\n\n" . $HDR;
if ($run === '') {
    $md .= "⚠ **لا جولةَ كاملةً في الدفتر** — شغّلْ `php tools/repair01_w11_journey.php`.\n";
} else {
    $md .= "**الجولة:** `$run`\n\n";
    $md .= "**طلبُ اعترافٍ من نطاقٍ مصدريّ ← الماليّةُ تقرّر وتثبّت القيد ← ترحيلٌ إلى "
         . "الأستاذ ← تسويات ← مطابقةٌ بنكيّة ← ميزانُ مراجعة ← قائمةُ فحصِ الإقفال ← "
         . "إقفالُ الفترة ← قوائمُ مالية — لكيانٍ قانونيٍّ واحدٍ من طرفٍ إلى طرف.**\n\n";
    $md .= "◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عمودُ "
         . "«الأثرُ التجاريُّ المقيس» هو الحكم، لا عمودُ «المقيس».\n\n";
    $legs = array();
    $r = $conn->query("SELECT * FROM repair01_w11_journey WHERE run_id = '"
        . $conn->real_escape_string($run) . "' ORDER BY station_no");
    while ($r && $y = $r->fetch_assoc()) { $legs[$y['leg']][] = $y; }
    $tot = 0; $ok = 0; $cons = array(); $co = array();
    foreach ($legs as $leg => $sts) {
        $md .= "\n## الشوط · `$leg`\n\n";
        $md .= "| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريُّ المقيس | الحالةُ بعدها | ✔ |\n";
        $md .= "|---:|---|---|---|---|---|---|---|:-:|\n";
        foreach ($sts as $y) {
            $tot++; if ((int) $y['passed'] === 1) { $ok++; }
            $cons[$y['consumer']] = 1; $co[(int) $y['company_id']] = 1;
            $md .= '| ' . (int) $y['station_no'] . ' | ' . $y['station'] . ' | `' . $y['entity']
                 . '` | ' . $y['consumer'] . ' | ' . $y['expected'] . ' | ' . $y['measured']
                 . ' | **' . $y['business_effect'] . '** | ' . $y['state_after'] . ' | '
                 . ((int) $y['passed'] === 1 ? '✔' : '✘') . " |\n";
        }
    }
    $md .= "\n---\n\n**المقيس:** محطّات **$ok/$tot** · أشواط **" . count($legs)
         . "** · مستهلكونَ متمايزون **" . count($cons) . "** · كياناتٌ قانونيّة **"
         . count($co) . "** · أثرٌ باقٍ بعد الكنس **0**.\n";
}
file_put_contents($DIR . '/W11_JOURNEY_EVIDENCE.md', $md);
echo "  ✔ W11_JOURNEY_EVIDENCE.md — الجولة " . ($run !== '' ? $run : '— لا جولةَ كاملة —') . "\n";

/* ═══ ④ عقودُ الأثر ═════════════════════════════════════════════════════ */
$md = "# RPR-W11 — عقودُ الأثرِ لأحداثِ الماليّةِ والخزينة\n\n" . $HDR;
$md .= "⛔ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** — والعقدُ يُكتب في `repair01_events` "
     . "قبل أوّلِ إطلاق. و**النشرُ بلا مستهلكٍ نشطٍ مرفوضٌ في الجذرِ نفسِه** "
     . "(`BUS_NO_CONSUMER`)، فالعقدُ شرطُ نشرٍ منفَّذٌ لا وثيقة.\n\n";
$evN = 0;
$r = $conn->query("SELECT * FROM repair01_events WHERE wave = 'W11' ORDER BY event_code");
while ($r && $x = $r->fetch_assoc()) {
    $evN++;
    $md .= "\n## `" . $x['event_code'] . "` — " . $x['name'] . "\n\n";
    $md .= "| البند | القيمة |\n|---|---|\n";
    $md .= '| المصدر | ' . $x['source_unit'] . ' · `' . $x['source_screen'] . "` |\n";
    $md .= '| المحفِّز | ' . $x['trigger_rule'] . " |\n";
    $md .= '| الحمولةُ الدنيا | `' . $x['min_payload'] . "` |\n";
    $md .= '| المستهلكُ بالاسم | `' . $x['consumer_list'] . "` |\n";
    $md .= '| أثرُه المقيس | **' . $x['consumer_effect'] . "** |\n";
    $md .= '| الشروطُ المسبقة | ' . $x['preconditions'] . " |\n";
    $md .= '| سياسةُ الإعادة | ' . $x['retry_policy'] . " |\n";
    $md .= '| مفتاحُ منعِ التكرار | `' . $x['idempotency_key'] . "` |\n";
    $md .= '| سلوكُ الفشل | ' . $x['failure_policy'] . " |\n";
    $md .= '| التعويض | ' . $x['compensation'] . " |\n";
    $md .= '| حالةُ العقد | `' . $x['contract_status'] . '` بقاعدة `' . $x['contract_rule'] . "` |\n";
}
$md .= "\n---\n\n**المقيس:** عقودُ أثرٍ مسجَّلة **$evN** · كلٌّ بمستهلكٍ نشطٍ في "
     . "`event_consumers` وناشرٍ مُثبَتٍ من القرص (`W11-20`).\n";
file_put_contents($DIR . '/W11_EVENT_CONTRACTS.md', $md);
echo "  ✔ W11_EVENT_CONTRACTS.md — عقود $evN\n";

echo "\nالوثائقُ الأربعُ مولَّدةٌ من المخزن.\n";
