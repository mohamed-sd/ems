<?php
/**
 * tools/repair01_w7_docs.php — توليدُ مخرَجاتِ W07 الحاكمةِ من سجلّاتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ إسقاطٌ والسجلُّ حكم**: آلاتُ الحالةِ ومصفوفةُ فصلِ الواجباتِ
 *   ودليلُ الرحلةِ تُقرأ من `repair01_w7_states` و`repair01_w7_sod` و
 *   `repair01_w7_journey` — فلا تتفرَّق الوثيقةُ عن الحاجبِ الذي يفحصها.
 *   و`FINDINGS.md` اليدويَّةُ تُسقط وسمَ «مُغلق» عند إعادةِ كتابتِها (GAP-CODES) —
 *   والتوليدُ من المصدرِ يمنع ذلك بنيةً لا انضباطًا.
 *
 * التشغيل: php tools/repair01_w7_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
define('EMS_CLI', true); mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn->set_charset('utf8mb4');
$D = $ROOT . '/docs/REPAIR01_20260823/plan/';

$ENT = array(
 'mnt_order' => 'أمر العمل',
 'mnt_return_cert' => 'شهادة إعادة الخدمة',
 'mnt_repeat_repair' => 'واقعة إعادة الإصلاح',
 'mnt_part_request' => 'طلب صرف القطع',
 'transfer_orders' => 'أمر الترحيل',
 'trp_trip_leg' => 'مرحلة الرحلة',
 'trp_closure' => 'إقفال أمر الترحيل',
 'trp_damage_claim' => 'مطالبة التلف والحوادث',
);

/* ═══ آلاتُ الحالة ═══ */
$o = "# RPR-W07 — آلاتُ الحالةِ لكيانات النطاق\n"
   . "> ⛔ **مولَّدٌ من `repair01_w7_states` — لا يُحرَّر يدويًّا.** التعديلُ في\n"
   . "> `tools/repair01_w7_apply.php` §٩ ثمَّ إعادةُ التوليد.\n"
   . "> والبوّابةُ `W7-16` تقيس السجلَّ نفسَه: **كيانٌ بلا انتقالٍ ممنوعٍ صراحةً يُسقطها**،\n"
   . "> ومسموحٌ ناقصُ الحقولِ يُسقطها، وممنوعٌ بلا سببٍ مكتوبٍ يُسقطها.\n\n"
   . "**الكيانات: " . count($ENT) . "**  ·  "
   . "**الانتقالات: " . (int) $conn->query("SELECT COUNT(*) FROM repair01_w7_states")->fetch_row()[0] . "**  ·  "
   . "**الممنوعُ صراحةً: " . (int) $conn->query("SELECT COUNT(*) FROM repair01_w7_states WHERE allowed=0")->fetch_row()[0] . "**\n\n";

foreach ($ENT as $key => $ar) {
    $o .= "---\n\n## " . $ar . "  `" . $key . "`\n\n";
    $o .= "### الانتقالاتُ المسموحة\n\n";
    $o .= "| من | إلى | مالكُ الانتقال | الشرطُ المسبق | المستندُ الرسميّ | بوّابةُ الاعتماد | قاعدةُ إعادةِ الفتح | قاعدةُ التصحيح |\n";
    $o .= "|---|---|---|---|---|---|---|---|\n";
    $r = $conn->query("SELECT * FROM repair01_w7_states WHERE entity='" . $conn->real_escape_string($key)
                    . "' AND allowed=1 ORDER BY from_state, to_state");
    $n = 0;
    while ($r && $x = $r->fetch_assoc()) {
        $n++;
        $o .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['owner_role'] . ' | '
            . $x['precondition'] . ' | ' . $x['official_doc'] . ' | ' . $x['approval_gate'] . ' | '
            . $x['reopen_rule'] . ' | ' . $x['correct_rule'] . " |\n";
    }
    if ($n === 0) { $o .= "| — | — | — | — | — | — | — | — |\n"; }
    $o .= "\n### الانتقالاتُ الممنوعةُ صراحةً\n\n";
    $o .= "| من | إلى | لماذا مُنع |\n|---|---|---|\n";
    $r = $conn->query("SELECT * FROM repair01_w7_states WHERE entity='" . $conn->real_escape_string($key)
                    . "' AND allowed=0 ORDER BY from_state, to_state");
    $m = 0;
    while ($r && $x = $r->fetch_assoc()) {
        $m++;
        $o .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['forbid_reason'] . " |\n";
    }
    if ($m === 0) { $o .= "| — | — | — |\n"; }
    $o .= "\n";
}
file_put_contents($D . 'W07_STATE_MACHINES.md', $o);
echo "  ✔ W07_STATE_MACHINES.md\n";

/* ═══ فصلُ الواجبات ═══ */
$s = "# RPR-W07 — مصفوفةُ فصلِ الواجباتِ لنطاقِ الصيانةِ والنقل\n"
   . "> ⛔ **مولَّدةٌ من `repair01_w7_sod` — لا تُحرَّر يدويًّا.** التعديلُ في\n"
   . "> `tools/repair01_w7_apply.php` §١٠ ثمَّ إعادةُ التوليد.\n"
   . "> **ولا اسمَ شخصٍ هنا**: الأدوارُ مفاتيحُ أدوارٍ لا أشخاص.\n"
   . "> و`W7-17` تشترط أن يكون **رمزُ الردِّ المكتوبُ في `enforced_by` موجودًا حرفيًّا\n"
   . "> في إحدى خدمتَي النطاق** — فالمصفوفةُ ضابطٌ منفَّذٌ لا سردٌ مُعلَن.\n\n";
$r = $conn->query("SELECT * FROM repair01_w7_sod ORDER BY process_key");
$c = 0;
while ($r && $x = $r->fetch_assoc()) {
    $c++;
    $s .= "---\n\n## " . $x['process_name'] . "  `" . $x['process_key'] . "`\n\n";
    $s .= "| الدور | مفتاحُ الدور |\n|---|---|\n";
    $s .= "| Initiator — المُبادر | " . $x['initiator_role'] . " |\n";
    $s .= "| Reviewer — المراجع | " . $x['reviewer_role'] . " |\n";
    $s .= "| Approver — المعتمِد | " . $x['approver_role'] . " |\n";
    $s .= "| Executor — المنفِّذ | " . $x['executor_role'] . " |\n";
    $s .= "| Reconciler/Closer — المُقفِل | " . $x['closer_role'] . " |\n";
    $s .= "| Deputy — النائب | " . $x['deputy_role'] . " |\n\n";
    $s .= "**التركيبةُ الممنوعةُ صراحةً:** " . $x['forbidden_combo'] . "\n\n";
    $s .= "**ما يُنفِّذها في الشيفرة:** `" . str_replace(' · ', '` · `', $x['enforced_by']) . "`\n\n";
    $s .= "**سندُ الصلاحية:** `" . $x['authority_rule_id'] . "`  ·  "
        . "**النطاق:** " . $x['scope_rule'] . "  ·  "
        . "**التفويض:** " . $x['delegation'] . "  ·  "
        . "**سريانٌ من:** " . $x['effective_date'] . "\n\n";
}
$s .= "---\n\n**العملياتُ الحرِجة: " . $c . "**  ·  كلُّها بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ ينفّذها.\n";
file_put_contents($D . 'W07_SOD.md', $s);
echo "  ✔ W07_SOD.md\n";

/* ═══ دليلُ عبورِ رحلةِ التوقّف (§18) — بمحطّاتِها وأثرِ كلِّ مستهلك ═══════
   ◆ **الجولةُ الأخيرةُ وحدَها**: دليلُ جولةٍ سابقةٍ يبقى في الجدولِ بعد رحلةٍ لم
     تنعقد، وكتابتُه هنا تجعل الوثيقةَ تشهد على عبورٍ لم يقع. */
$run = (string) $conn->query("SELECT run_id FROM repair01_w7_journey ORDER BY id DESC LIMIT 1")->fetch_row()[0];
$rk = $conn->real_escape_string($run);
$tot = (int) $conn->query("SELECT COUNT(*) FROM repair01_w7_journey WHERE run_id='$rk'")->fetch_row()[0];
$psd = (int) $conn->query("SELECT COUNT(*) FROM repair01_w7_journey WHERE run_id='$rk' AND passed=1")->fetch_row()[0];
$cns = (int) $conn->query("SELECT COUNT(DISTINCT consumer) FROM repair01_w7_journey WHERE run_id='$rk'")->fetch_row()[0];

$j = "# RPR-W07 — دليلُ عبورِ رحلةِ التوقّف (§18)\n"
   . "> ⛔ **مولَّدٌ من `repair01_w7_journey` — لا يُحرَّر يدويًّا.** التعديلُ في\n"
   . "> `tools/repair01_w7_journey.php` ثمَّ إعادةُ التشغيلِ فالتوليد.\n"
   . "> **والجولةُ المكتوبةُ هنا هي الأخيرةُ وحدَها** — ودليلُ جولةٍ سابقةٍ يبقى في\n"
   . "> الجدولِ بعد رحلةٍ لم تنعقد، فكتابتُه شهادةٌ على عبورٍ لم يقع.\n"
   . "> **وكلُّ ما كتبته الرحلةُ أُرجع بمعاملتِه** — ولا يبقى منها إلّا هذا الدليل.\n\n"
   . "**الجولة:** `$run`  ·  **عابرٌ:** $psd من $tot  ·  **مستهلكونَ متمايزون:** $cns\n\n"
   . "| # | المحطّة | الكيان | المستهلك | المقيس | الأثرُ التجاريّ | الجاهزيّةُ بعدها |\n"
   . "|---:|---|---|---|---|---|---|\n";
$r = $conn->query("SELECT * FROM repair01_w7_journey WHERE run_id='$rk' ORDER BY station_no");
while ($r && $x = $r->fetch_assoc()) {
    $j .= '| ' . ((int) $x['passed'] ? '✔ ' : '✘ ') . (int) $x['station_no'] . ' | ' . $x['station']
        . ' | `' . $x['entity'] . '` | ' . $x['consumer'] . ' | ' . str_replace('|', '/', $x['measured'])
        . ' | ' . str_replace('|', '/', $x['business_effect']) . ' | ' . $x['readiness_after'] . " |\n";
}
file_put_contents($D . 'W07_JOURNEY_EVIDENCE.md', $j);
echo "  ✔ W07_JOURNEY_EVIDENCE.md ($psd/$tot)\n";
