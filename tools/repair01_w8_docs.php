<?php
/**
 * tools/repair01_w8_docs.php — توليدُ مخرَجاتِ المرحلةِ الثامنة من المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المخزنُ هو الحقيقةُ والوثيقةُ إسقاطُه** (‏_CONTEXT §المصدرُ الواحد):
 *   `W08_STATE_MACHINES.md` و`W08_SOD.md` و`W08_JOURNEY_EVIDENCE.md` تُولَّد
 *   من `repair01_w8_states` و`repair01_w8_sod` و`repair01_w8_journey` — ولا
 *   تُحرَّر يدًا، فنسختانِ من الحقيقةِ تتفرّقانِ بأوّلِ تعديل.
 *
 * ◆ **ولا نصَّ حالةٍ حرّ** (§٧): كلُّ انتقالٍ صفٌّ بمالكِه وشرطِه ومستندِه
 *   وبوّابتِه وقاعدتَي إعادةِ الفتحِ والتصحيح — والممنوعُ بسببِه المكتوب.
 *
 * التشغيل: php tools/repair01_w8_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w8_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$DIR = $ROOT . '/docs/REPAIR01_20260823/plan';
$one = function ($sql) use ($conn) { return repair01_w8_one($conn, $sql); };
$HDR = "> ⛔ **مولَّدٌ من المخزن — لا تحرّره يدويًّا**: `php tools/repair01_w8_docs.php` يعيد كتابتَه.\n";

/* ═══ ① آلاتُ الحالة ═══════════════════════════════════════════════════ */
$ENT_AR = array(
    'opportunities' => 'الفرصة البيعية', 'quotations' => 'عرض السعر',
    'contracts' => 'عقد العميل', 'claims' => 'المستخلص',
    'suppliers' => 'المورد', 'supplier_contracts' => 'عقد المورد',
    'settlements' => 'تسوية المورد', 'supplier_contract_closures' => 'إقفال عقد المورد',
);
$m = "# RPR-W08 — آلاتُ الحالةِ لكياناتِ المبيعاتِ والموردين\n\n" . $HDR . "\n";
$entN = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w8_states");
$allN = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed = 1");
$noN  = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed = 0");
$m .= "**الكيانات:** $entN  ·  **انتقالٌ مسموح:** $allN  ·  **ممنوعٌ صراحةً:** $noN\n\n";
$m .= "> **الممنوعُ يُكتب ولا يُسكت عنه**: انتقالٌ غيرُ مذكورٍ في الجدولِ **غيرُ محسوم**، "
    . "وانتقالٌ مذكورٌ ممنوعًا **محسومٌ بسببِه**. والفرقُ بينهما هو الفرقُ بين سهوٍ وقرار.\n\n---\n\n";

$r = $conn->query("SELECT DISTINCT entity FROM repair01_w8_states ORDER BY entity");
$ents = array();
while ($r && $x = $r->fetch_assoc()) { $ents[] = $x['entity']; }
foreach ($ents as $e) {
    $ar = isset($ENT_AR[$e]) ? $ENT_AR[$e] : $e;
    $m .= "## `$e` — $ar\n\n";
    $m .= "### الانتقالاتُ المسموحة\n\n";
    $m .= "| من | إلى | المالك | الشرطُ المسبق | المستندُ الرسميّ | بوّابةُ الاعتماد | إعادةُ الفتح | التصحيح |\n";
    $m .= "|---|---|---|---|---|---|---|---|\n";
    $q = $conn->query("SELECT * FROM repair01_w8_states WHERE entity='" . $conn->real_escape_string($e) . "'
                         AND allowed=1 ORDER BY from_state, to_state");
    while ($q && $x = $q->fetch_assoc()) {
        $m .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['owner_role'] . ' | '
            . $x['precondition'] . ' | ' . $x['official_doc'] . ' | ' . $x['approval_gate'] . ' | '
            . $x['reopen_rule'] . ' | ' . $x['correct_rule'] . " |\n";
    }
    $m .= "\n### الانتقالاتُ الممنوعةُ صراحةً\n\n";
    $m .= "| من | إلى | سببُ المنع | المرجع |\n|---|---|---|---|\n";
    $q = $conn->query("SELECT * FROM repair01_w8_states WHERE entity='" . $conn->real_escape_string($e) . "'
                         AND allowed=0 ORDER BY from_state, to_state");
    while ($q && $x = $q->fetch_assoc()) {
        $m .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['forbid_reason']
            . ' | ' . $x['src_ref'] . " |\n";
    }
    $m .= "\n---\n\n";
}
file_put_contents($DIR . '/W08_STATE_MACHINES.md', $m);
echo "  ✔ W08_STATE_MACHINES.md — $entN كيانًا · $allN مسموحًا · $noN ممنوعًا\n";

/* ═══ ② فصلُ الواجبات ═══════════════════════════════════════════════════ */
$m = "# RPR-W08 — مصفوفةُ فصلِ الواجباتِ للمبيعاتِ والموردين\n\n" . $HDR . "\n";
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod");
$enf  = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod WHERE enforced_by LIKE 'ENFORCED:%'");
$notE = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod WHERE enforced_by LIKE 'NOT_ENFORCED:%'");
$m .= "**عملياتٌ حرِجة:** $sodN  ·  **منفَّذةٌ بحارسٍ مُثبَتٍ بالاستدعاء:** $enf  ·  "
    . "**بلا حارسٍ (مُعلَنةٌ في `W8-D-10`):** $notE\n\n";
$m .= "> **ولا أسماءَ أشخاصٍ صلبة**: `Role_Key` و`Authority_Rule_ID` و`Deputy_Role` و`Scope` و`Delegation`.\n>\n"
    . "> **و`enforced_by` يحمل المقيسَ لا المرغوب**: `ENFORCED:` حارسٌ يُستدعى في الفحصِ السلبيِّ فيردّ، "
    . "و`NOT_ENFORCED:` إعلانٌ بسببِه — **ولا يُدَّعى حارسٌ لا وجودَ له** لتخضرَّ بوّابة.\n\n---\n\n";

$r = $conn->query("SELECT * FROM repair01_w8_sod ORDER BY process_key");
while ($r && $x = $r->fetch_assoc()) {
    $enfMark = (strpos($x['enforced_by'], 'ENFORCED:') === 0) ? '✅ منفَّذ' : '⚠ بلا حارس';
    $m .= "## `" . $x['process_key'] . "` — " . $x['process_name'] . "\n\n";
    $m .= "| الدور | الجهة |\n|---|---|\n";
    $m .= '| `Initiator` | ' . $x['initiator_role'] . " |\n";
    $m .= '| `Reviewer` | ' . $x['reviewer_role'] . " |\n";
    $m .= '| `Approver` | ' . $x['approver_role'] . " |\n";
    $m .= '| `Executor` | ' . $x['executor_role'] . " |\n";
    $m .= '| `Reconciler/Closer` | ' . $x['closer_role'] . " |\n";
    $m .= '| `Deputy_Role` | ' . $x['deputy_role'] . " |\n\n";
    $m .= '⛔ **التركيبةُ الممنوعةُ صراحةً:** ' . $x['forbidden_combo'] . "\n\n";
    $m .= '**الإنفاذُ المقيس:** ' . $enfMark . ' — `' . $x['enforced_by'] . "`\n\n";
    $m .= '**`Authority_Rule_ID`:** `' . $x['authority_rule_id'] . '`  ·  '
        . '**`Scope`:** ' . $x['scope_rule'] . '  ·  '
        . '**`Delegation`:** ' . $x['delegation'] . '  ·  '
        . '**`Effective_Date`:** ' . $x['effective_date'] . "\n\n";
    $m .= '**المرجع:** ' . $x['src_ref'] . "\n\n---\n\n";
}
file_put_contents($DIR . '/W08_SOD.md', $m);
echo "  ✔ W08_SOD.md — $sodN عمليةً · منفَّذٌ $enf · بلا حارسٍ $notE\n";

/* ═══ ③ دليلُ الرحلتَين ════════════════════════════════════════════════ */
$RUN = (string) $one("SELECT run_id FROM repair01_w8_journey ORDER BY id DESC LIMIT 1");
$rE = $conn->real_escape_string($RUN);
$tot = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id='$rE'");
$ok  = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id='$rE' AND passed=1");
$cons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w8_journey WHERE run_id='$rE'");
$m = "# RPR-W08 — دليلُ عبورِ رحلةِ العميلِ ورحلةِ المورد\n\n" . $HDR . "\n";
$m .= "**الجولة:** `$RUN`  ·  **عابرٌ:** $ok/$tot  ·  **مستهلكونَ متمايزون:** $cons\n\n";
$m .= "> **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ محطّةٍ رقمٌ "
    . "يعنيه مستهلكُها بالاسم.\n>\n"
    . "> **والمحطّاتُ الموجبةُ مقيسةٌ على السلسلةِ الحيّةِ القائمة** لا على بذرةٍ تُصطنَع ثمَّ تُرجَع — "
    . "فالوحدتانِ مرجعيّتان (§19)، وسلسلةٌ عبرت فعلًا أقوى دليلًا ممّا تولّده الأداةُ لنفسِها. "
    . "**والمحطّاتُ السالبةُ تُقاس بالاستدعاءِ الفعليِّ ورمزِ الردّ** داخلَ معاملةٍ تُرجَع.\n\n---\n\n";

foreach (array('client' => 'رحلةُ العميل', 'supplier' => 'رحلةُ المورد') as $k => $lbl) {
    $kE = $conn->real_escape_string($k);
    $n  = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id='$rE' AND journey_key='$kE'");
    $p  = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id='$rE' AND journey_key='$kE' AND passed=1");
    $m .= "## $lbl — عابرٌ $p/$n\n\n";
    $m .= "| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | الأثرُ التجاريّ | الحالةُ بعدها | الحكم |\n";
    $m .= "|---:|---|---|---|---|---|---|---|:--:|\n";
    $q = $conn->query("SELECT * FROM repair01_w8_journey WHERE run_id='$rE' AND journey_key='$kE'
                        ORDER BY station_no");
    while ($q && $x = $q->fetch_assoc()) {
        $m .= '| ' . $x['station_no'] . ' | ' . $x['station'] . ' | `' . $x['entity'] . '` | '
            . $x['consumer'] . ' | ' . $x['expected'] . ' | ' . $x['measured'] . ' | '
            . $x['business_effect'] . ' | `' . $x['state_after'] . '` | '
            . ((int) $x['passed'] === 1 ? '✔' : '✘') . " |\n";
    }
    $m .= "\n---\n\n";
}
file_put_contents($DIR . '/W08_JOURNEY_EVIDENCE.md', $m);
echo "  ✔ W08_JOURNEY_EVIDENCE.md — عابرٌ $ok/$tot · مستهلكون $cons\n";

echo "\nتمَّ التوليدُ في docs/REPAIR01_20260823/plan/\n";
exit(0);
