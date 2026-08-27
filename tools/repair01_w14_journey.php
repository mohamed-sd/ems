<?php
/**
 * tools/repair01_w14_journey.php — رحلةُ الضابط (‏W14 §٦-أ · §27 · §12)
 * ═══════════════════════════════════════════════════════════════════════════
 * **انحرافٌ تشغيليٌّ يقع ← يُصنَّف بقاعدةٍ مكتوبة ← إن تجاوز شهيّةَ المخاطرِ صار
 *   تعرُّضًا عند المخاطر ← إن كسر ضابطًا صار خرقًا عند الحوكمة ← إن لم يكن
 *   أيَّهما بقي انحرافًا عند مالكِه ولا تُفتح حالةُ حوكمة ← والمراجعةُ تفحص
 *   العيّنةَ وترفع نتيجةً لا تعدّلها الحوكمة.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — انحرافٌ يفتح محفِّزًا · محفِّزٌ يفتح تعرُّضًا ·
 *   تعرُّضٌ يُقرأ في سجلِّ المخاطر · خرقٌ يفتح إجراءً · إجراءٌ يفتح الإغلاقَ ·
 *   ملاحظةٌ تفتح متابعةَ الحوكمةِ ولا تفتح لها تعديلًا.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ** — تُقاس **بالاستدعاءِ الفعليِّ ورمزِ الرفض**:
 *   «انحرافٌ يملكه نطاقُ رقابة» · «تصنيفٌ بلا قاعدةٍ مكتوبة» · «من كتب القاعدةَ
 *   يعتمدها» · «محفِّزُ الأربعِ والعشرينَ على صيانةٍ مخطَّطة» · «حدثُ خطرٍ بلا
 *   مرجعِ مصدرٍ» · «نسخةٌ ثانيةٌ لمصدرٍ واحد» · «قبولٌ بشهيّةٍ غيرِ معتمَدة» ·
 *   «من اقترح الإغلاقَ يعتمده» · **«حالةُ حوكمةٍ على انحرافٍ تشغيليٍّ صِرف»** ·
 *   «أساسٌ خارجَ الثمانية» · «من فتح الحالةَ يغلقها» · «مالكُ الإجراءِ يتحقّق
 *   منه» · «تحقيقٌ عند غيرِ مالكِه» · «مستقلٌّ بلا تكليف» · «من سجلِّ المنعِ بلا
 *   فرز» · «تعارضٌ بلا تنحٍّ» · «نطاقُ مراجعةٍ تحدّده الحوكمة» · «من نفَّذ
 *   يراجع» · **«الحوكمةُ تضع نتيجةَ مراجعة»** · **«الحوكمةُ تغلقها»**.
 *
 * ⚠ **والنظافةُ كنسٌ بالوسمِ لا إرجاعٌ بمعاملة** (‏درسُ W09): كلُّ صفٍّ تكتبه
 *   الرحلةُ يحمل وسمَ عائلتِها، والكنسُ يمسح **بالوسم** — ويُشغَّل مرّتَين.
 *
 * التشغيل: php tools/repair01_w14_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

/* ⚠ **حارسُ الموتِ الصامت**: `config.php` يبتلع مخرَجَ سطرِ الأوامر. */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, "\n✘ سقطت الرحلةُ بخطإٍ قاتل:\n   " . $e['message']
                     . "\n   في " . $e['file'] . ':' . $e['line'] . "\n");
    }
});

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w14_scan.php';
require_once $ROOT . '/app/Services/Control/ThresholdRegistry.php';
require_once $ROOT . '/app/Services/Control/DeviationClassifier.php';
require_once $ROOT . '/app/Services/Risk/RiskDomainService.php';
require_once $ROOT . '/app/Services/Governance/GovernanceDomainService.php';
require_once $ROOT . '/app/Services/Audit/AuditDomainService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';

use App\Services\Control\ThresholdRegistry as TR;
use App\Services\Control\DeviationClassifier as CTL;
use App\Services\Risk\RiskDomainService as RSK;
use App\Services\Governance\GovernanceDomainService as GOV;
use App\Services\Audit\AuditDomainService as IAF;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w14_one($conn, $sql); };

/* مُعرِّفُ الجولةِ بدقّةِ الميكروثانية (‏درسُ W04) */
$RUN = 'W14J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$TAG = 'W14J';

echo "═══════════ رحلةُ الضابط — REPAIR01 · W14 §٦-أ ═══════════\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$log = function ($leg, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed, $co = 0)
       use (&$ST) {
    $ST[] = array($leg, $station, $entity, $consumer, $expected, $measured, $effect, $state,
                  $passed ? 1 : 0, (int) $co);
};

/* ══════════════════════════════════════════════════════════════════════════
   كنسُ العائلةِ — يُشغَّل قبلَ البدءِ وبعدَ النهاية
   ══════════════════════════════════════════════════════════════════════════ */
$sweep = function () use ($conn, $TAG) {
    $q = array(
        "DELETE FROM gov_audit_followup WHERE followup_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM iaf_sample WHERE sample_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM iaf_evidence_request WHERE request_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM iaf_program WHERE program_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM iaf_function_risk WHERE risk_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM iaf_findings WHERE finding_no LIKE '$TAG%'",
        "DELETE FROM gov_corrective_action WHERE action_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_breach WHERE case_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_investigation WHERE inv_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_integrity_report WHERE report_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_conflict_disclosure WHERE disclosure_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_related_party WHERE party_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_gift_disclosure WHERE gift_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_conduct_ack WHERE code_version LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_sod_conflict WHERE conflict_code LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_request_type WHERE type_code LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_committee WHERE committee_code LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_filing WHERE filing_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_compliance_due WHERE obligation_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_obligation WHERE obligation_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM gov_policy WHERE policy_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM rsk_closure WHERE closure_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM rsk_event WHERE event_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM rsk_trigger WHERE trigger_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM risk_escalations WHERE reason_ar LIKE '$TAG%'",
        "DELETE FROM risk_acceptances WHERE reason_ar LIKE '$TAG%'",
        "DELETE FROM risk_register WHERE risk_code LIKE '$TAG%'",
        "DELETE FROM ctl_deviation WHERE deviation_no LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM ctl_classification_rule WHERE rule_code LIKE '$TAG%' OR src_ref LIKE '$TAG%'",
        "DELETE FROM ems_business_events WHERE idempotency_key LIKE 'w14:%'
            AND source_ref IN ('DeviationClassifier','RiskDomainService',
                               'GovernanceDomainService','AuditDomainService')
            AND JSON_EXTRACT(payload, '$.src_ref') IS NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    );
    foreach ($q as $s) { @$conn->query($s); }
};
$sweep();

/* ══════════════════════════════════════════════════════════════════════════
   الأرضيّة — كيانٌ قانونيٌّ واحدٌ وفاعلونَ متمايزون
   ══════════════════════════════════════════════════════════════════════════
   ◆ **وفصلُ الواجباتِ يحتاج أيديًا متمايزةً فعلًا** — فالأرضيّةُ تُخرِج ثمانيةَ
     مفاتيحَ مختلفةٍ من السجلِّ الحيِّ، والرحلةُ تشترط تمايزَها قبل أن تبدأ.
   ══════════════════════════════════════════════════════════════════════════ */
$company = (int) $one("SELECT company_id FROM employees WHERE company_id > 0
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { echo "✘ أرضيّةٌ ناقصة: لا كيانَ قانونيٌّ في سجلِّ الموظّفين\n"; exit(1); }

$actors = array();
$ra = $conn->query("SELECT id FROM employees WHERE company_id = $company ORDER BY id LIMIT 14");
while ($ra && $ax = $ra->fetch_row()) { $actors[] = (int) $ax[0]; }
if (count($actors) < 10) {
    echo "✘ أرضيّةٌ ناقصة: أقلُّ من عشرةِ فاعلين متمايزين في الكيان $company\n"; exit(1);
}
list($A_OPS, $A_QA, $A_RSK, $A_RSKMGR, $A_GOV, $A_GOVMGR, $A_ACTOWN, $A_AUD, $A_AUDLEAD, $A_RESERVED) =
    array_slice($actors, 0, 10);

/* **والعتبةُ من السجلِّ** — والمعتمَدةُ وحدَها تُقرأ في الإنتاج، والمعلَّقةُ
   تُقرأ بقيمةِ اختبارٍ **موسومةٍ** خلفَ علمِ الاختبارِ في هذه الرحلةِ وحدَها. */
TR::setConnection($conn);
TR::enableTestValues(true);
$thDown = TR::read('rsk.trigger.unplanned_downtime_hours');
if (!$thDown['ok'] || $thDown['tagged'] !== 'OWNER_APPROVED') {
    echo "✘ أرضيّةٌ ناقصة: عتبةُ التوقّفِ غيرُ معتمَدةٍ — شغّلْ tools/repair01_w14_apply.php\n"; exit(1);
}
$famN = (int) $one("SELECT COUNT(*) FROM rsk_taxonomy WHERE state = 'active'");
if ($famN < 4) {
    echo "✘ أرضيّةٌ ناقصة: شجرةُ العائلاتِ الأربعِ غيرُ مبذورة — شغّلْ tools/repair01_w14_apply.php\n"; exit(1);
}

foreach (array(CTL::class, RSK::class, GOV::class, IAF::class) as $svc) {
    $svc::setCompany($company);
    $svc::setEventConnection($conn);
}

$G = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $A_GOV, '', true));

printf("الأرضيّة: كيان=%d · فاعلون=%d · تشغيل=%d · مخاطر=%d · حوكمة=%d · مراجعة=%d · عتبةُ التوقّف=%s ساعة\n\n",
    $company, count($actors), $A_OPS, $A_RSK, $A_GOV, $A_AUD, (string) (int) $thDown['value']);

$FAIL = 0;
$must = function ($ok) use (&$FAIL) { if (!$ok) { $FAIL++; } return $ok; };

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ① · القاعدةُ المكتوبةُ تُكتب وتُعتمَد بيدَين
   ══════════════════════════════════════════════════════════════════════════ */
$ruleR = CTL::writeRule($G, array(
    'rule_code' => $TAG . '-RULE', 'title_ar' => 'قاعدة رحلة الضابط',
    'deviation_kind' => 'UNPLANNED_DOWNTIME',
    'exposure_test' => 'يصير تعرضا اذا تجاوز التوقف غير المخطط الحد المسجل او تكرر فوق حده',
    'breach_test' => 'يصير خرقا اذا ظهر تجاهل اجراء الزامي او تجاوز صلاحية او اخفاء',
    'retain_test' => 'يبقى انحرافا عند مالكه ان لم يتحقق شرط التعرض ولا شرط الخرق',
    'appetite_key' => 'rsk.appetite.limit_amount', 'src_ref' => $TAG,
), $A_QA);
$log('القاعدة', 'كتابة قاعدة تصنيف بشروطها الثلاثة', 'ctl_classification_rule',
     'DeviationClassifier', 'قاعدة مسودة بثلاثة شروط مكتوبة',
     'الرمز=' . $ruleR['code'],
     'التصنيف يصير له مرجع مكتوب - وبلا قاعدة لا يقع تصنيف اصلا', 'draft',
     $must($ruleR['ok']), $company);

$selfAppr = CTL::activateRule($G, isset($ruleR['rule_id']) ? $ruleR['rule_id'] : 0, $A_QA, date('Y-m-d'));
$log('القاعدة', 'من كتب القاعدة يعتمدها', 'ctl_classification_rule',
     'DeviationClassifier', 'رمز الرد SAME_ACTOR_AUTHOR_AND_APPROVE_RULE',
     'الرمز=' . $selfAppr['code'],
     'قاعدة يعتمدها كاتبها قرار بيد واحدة - والرد يمنعها قبل ان تنفذ', 'مرفوض',
     $must(!$selfAppr['ok'] && $selfAppr['code'] === 'SAME_ACTOR_AUTHOR_AND_APPROVE_RULE'), $company);

$actR = CTL::activateRule($G, isset($ruleR['rule_id']) ? $ruleR['rule_id'] : 0, $A_GOVMGR, date('Y-m-d'));
$activeRules = (int) $G->count('ctl_classification_rule',
    array('where' => array('rule_code' => $TAG . '-RULE', 'state' => 'active')));
$log('القاعدة', 'اعتماد القاعدة بيد ثانية', 'ctl_classification_rule',
     'DeviationClassifier', 'قاعدة نافذة واحدة', 'نافذة=' . $activeRules,
     'التصنيف يصير ممكنا - وقبل النفاذ يرد كل تصنيف يستند اليها', 'active',
     $must($actR['ok'] && $activeRules === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ② · الانحرافُ يقع عند مالكِه التشغيليّ
   ══════════════════════════════════════════════════════════════════════════ */
$srcRow = (int) $one("SELECT id FROM equipments ORDER BY id LIMIT 1");
if ($srcRow <= 0) { $srcRow = 1; }

$badOwner = CTL::register($G, array(
    'deviation_no' => $TAG . '-DEV-X', 'owner_dept' => 'DEP-08',
    'source_module' => 'maintenance', 'source_table' => 'equipments', 'source_row_id' => $srcRow,
    'deviation_kind' => 'UNPLANNED_DOWNTIME', 'src_ref' => $TAG,
), $A_OPS);
$log('الانحراف', 'انحراف يملكه نطاق رقابة', 'ctl_deviation', 'DeviationClassifier',
     'رمز الرد DEVIATION_OWNED_BY_CONTROL', 'الرمز=' . $badOwner['code'],
     'العطل يبقى Source Event عند التشغيل والصيانة - والرد يمنع ان تملكه الحوكمة', 'مرفوض',
     $must(!$badOwner['ok'] && $badOwner['code'] === 'DEVIATION_OWNED_BY_CONTROL'), $company);

/* ⓐ انحرافٌ غيرُ مخطَّطٍ يتجاوز الحدَّ ⇒ يصير تعرُّضًا */
$devA = CTL::register($G, array(
    'deviation_no' => $TAG . '-DEV-A', 'owner_dept' => 'DEP-04',
    'source_module' => 'maintenance', 'source_table' => 'equipments', 'source_row_id' => $srcRow,
    'deviation_kind' => 'UNPLANNED_DOWNTIME', 'downtime_kind' => 'UNPLANNED_DOWNTIME',
    'occurred_at' => date('Y-m-d H:i:s'), 'duration_hours' => $thDown['value'] * 2,
    'recurrence_no' => 1, 'preventable' => 0,
    'why' => 'توقف غير مخطط طويل', 'src_ref' => $TAG,
), $A_OPS);
$devAOwner = (string) $one("SELECT owner_dept FROM ctl_deviation
                             WHERE deviation_no = '" . $esc($TAG . '-DEV-A') . "'");
$log('الانحراف', 'تسجيل انحراف غير مخطط عند مالكه التشغيلي', 'ctl_deviation',
     'Risk/risk_events.php', 'مالك تشغيلي غير نطاق رقابة', 'المالك=' . $devAOwner,
     'سطح احداث المخاطر يقرأ الانحراف بمرجعه ولا ينسخه', 'registered',
     $must($devA['ok'] && $devAOwner === 'DEP-04'), $company);

$noRule = CTL::classify($G, $devA['deviation_id'], array('rule_code' => ''), $A_OPS);
$log('الانحراف', 'تصنيف بلا قاعدة مكتوبة', 'ctl_deviation', 'DeviationClassifier',
     'رمز الرد CLASSIFY_WITHOUT_WRITTEN_RULE', 'الرمز=' . $noRule['code'],
     'التمييز الثلاثي قاعدة تكتب لا اجتهاد مصنف - والرد يمنعه', 'مرفوض',
     $must(!$noRule['ok'] && $noRule['code'] === 'CLASSIFY_WITHOUT_WRITTEN_RULE'), $company);

$clsA = CTL::classify($G, $devA['deviation_id'], array('rule_code' => $TAG . '-RULE'), $A_QA);
$log('التصنيف', 'انحراف تجاوز الحد يصير تعرضا', 'ctl_deviation', 'RiskDomainService',
     'التصنيف RISK_EXPOSURE ومحفز واحد على الاقل',
     'التصنيف=' . (isset($clsA['classification']) ? $clsA['classification'] : $clsA['code'])
     . ' · محفزات=' . (isset($clsA['triggers']) ? count($clsA['triggers']) : 0),
     'المخاطر تفتح محفزا - والحوكمة لا تفتح شيئا لان الاساس لم يتحقق', 'RISK_EXPOSURE',
     $must($clsA['ok'] && $clsA['classification'] === 'RISK_EXPOSURE'
           && $clsA['opens_exposure'] === true && $clsA['opens_breach'] === false), $company);

/* ⓑ صيانةٌ مخطَّطةٌ أطولُ من الحدِّ ⇒ **لا محفِّزَ ولا حالة** */
$devB = CTL::register($G, array(
    'deviation_no' => $TAG . '-DEV-B', 'owner_dept' => 'DEP-04',
    'source_module' => 'maintenance', 'source_table' => 'equipments', 'source_row_id' => $srcRow,
    'deviation_kind' => 'PLANNED_DOWNTIME', 'downtime_kind' => 'PLANNED_MAINTENANCE',
    'occurred_at' => date('Y-m-d H:i:s'), 'duration_hours' => $thDown['value'] * 3,
    'recurrence_no' => 1, 'preventable' => 0,
    'why' => 'صيانة رئيسية مخططة ومجازة', 'src_ref' => $TAG,
), $A_OPS);
$clsB = CTL::classify($G, $devB['deviation_id'], array('rule_code' => $TAG . '-RULE'), $A_QA);
$log('التصنيف', 'صيانة مخططة اطول من الحد تبقى انحرافا عند مالكها', 'ctl_deviation',
     'RiskDomainService', 'التصنيف DEVIATION_ONLY وصفر محفز',
     'التصنيف=' . (isset($clsB['classification']) ? $clsB['classification'] : $clsB['code'])
     . ' · محفزات=' . (isset($clsB['triggers']) ? count($clsB['triggers']) : 0),
     'سجل المخاطر لا يمتلئ بصيانة مخططة طبيعية - وهو نص قرار المالك الثاني', 'DEVIATION_ONLY',
     $must($clsB['ok'] && $clsB['classification'] === 'DEVIATION_ONLY'
           && count($clsB['triggers']) === 0), $company);

/* ⓒ انحرافٌ كسر ضابطًا ⇒ خرقٌ عند الحوكمة */
$devC = CTL::register($G, array(
    'deviation_no' => $TAG . '-DEV-C', 'owner_dept' => 'DEP-04',
    'source_module' => 'maintenance', 'source_table' => 'equipments', 'source_row_id' => $srcRow,
    'deviation_kind' => 'CONTROL_DEVIATION', 'downtime_kind' => 'UNPLANNED_DOWNTIME',
    'occurred_at' => date('Y-m-d H:i:s'), 'duration_hours' => 1,
    'recurrence_no' => 1, 'preventable' => 0,
    'why' => 'تنفيذ بلا تصعيد مطلوب', 'src_ref' => $TAG,
), $A_OPS);
$clsC = CTL::classify($G, $devC['deviation_id'],
    array('rule_code' => $TAG . '-RULE', 'breach_basis' => 'NO_ESCALATION'), $A_QA);
$log('التصنيف', 'انحراف كسر ضابطا يصير خرقا عند الحوكمة', 'ctl_deviation',
     'GovernanceDomainService', 'التصنيف GOVERNANCE_BREACH بلا محفز خطر',
     'التصنيف=' . (isset($clsC['classification']) ? $clsC['classification'] : $clsC['code']),
     'الحوكمة تفتح حالة - والمخاطر لا تفتح محفزا لان الحد لم يتجاوز', 'GOVERNANCE_BREACH',
     $must($clsC['ok'] && $clsC['classification'] === 'GOVERNANCE_BREACH'
           && $clsC['opens_breach'] === true && $clsC['opens_exposure'] === false), $company);

$badBasis = CTL::classify($G, $devC['deviation_id'],
    array('rule_code' => $TAG . '-RULE', 'breach_basis' => 'OPERATIONAL_DEVIATION'), $A_QA);
$log('التصنيف', 'اساس خرق خارج الثمانية', 'ctl_deviation', 'DeviationClassifier',
     'رمز الرد BREACH_BASIS_OUTSIDE_EIGHT', 'الرمز=' . $badBasis['code'],
     'اساس مفتوح يجعل كل توقف قضية حوكمة - والحصر يمنعه', 'مرفوض',
     $must(!$badBasis['ok'] && $badBasis['code'] === 'BREACH_BASIS_OUTSIDE_EIGHT'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ③ · التعرُّضُ عند المخاطر — ولا نسخَ لحدثِ المصدر
   ══════════════════════════════════════════════════════════════════════════ */
$badTrg = RSK::raiseTrigger($G, array(
    'trigger_no' => $TAG . '-TRG-X', 'rule_code' => 'UNPLANNED_24H',
    'threshold_key' => 'rsk.trigger.unplanned_downtime_hours',
    'deviation_no' => $TAG . '-DEV-B', 'source_table' => 'ctl_deviation',
    'source_row_id' => $devB['deviation_id'], 'downtime_kind' => 'PLANNED_MAINTENANCE',
    'src_ref' => $TAG,
), $A_RSK);
$log('التعرض', 'محفز الحد على صيانة مخططة', 'rsk_trigger', 'RiskDomainService',
     'رمز الرد TRIGGER_ON_PLANNED_DOWNTIME', 'الرمز=' . $badTrg['code'],
     'الصيانة المخططة مستثناة من محفز الحد بنص المالك - والرد ينفذه', 'مرفوض',
     $must(!$badTrg['ok'] && $badTrg['code'] === 'TRIGGER_ON_PLANNED_DOWNTIME'), $company);

$trg = RSK::raiseTrigger($G, array(
    'trigger_no' => $TAG . '-TRG', 'rule_code' => 'UNPLANNED_24H',
    'threshold_key' => 'rsk.trigger.unplanned_downtime_hours',
    'deviation_no' => $TAG . '-DEV-A', 'source_table' => 'ctl_deviation',
    'source_row_id' => $devA['deviation_id'], 'downtime_kind' => 'UNPLANNED_DOWNTIME',
    'measured_value' => $thDown['value'] * 2, 'src_ref' => $TAG,
), $A_RSK);
$trgRef = (string) $one("SELECT deviation_no FROM rsk_trigger
                          WHERE trigger_no = '" . $esc($TAG . '-TRG') . "'");
$log('التعرض', 'فتح محفز خطر بمرجع الانحراف لا بنسخته', 'rsk_trigger', 'RiskDomainService',
     'محفز واحد يحمل رقم الانحراف مرجعا', 'مرجع الانحراف=' . $trgRef,
     'فتح التعرض يصير ممكنا - والانحراف يبقى صفا واحدا عند مالكه', 'raised',
     $must($trg['ok'] && $trgRef === $TAG . '-DEV-A'), $company);

$G->update('rsk_trigger', array('state' => 'triaged', 'triaged_by' => $A_RSKMGR),
           array('id' => $trg['trigger_id']));
$exp = RSK::openExposure($G, $trg['trigger_id'], array(
    'risk_code' => $TAG . '-RSK', 'ru_id' => 0, 'title' => 'تعرض توقف غير مخطط',
    'root_cause' => 'عطل غير مخطط تجاوز الحد', 'src_ref' => $TAG,
), $A_RSKMGR);
$riskRows = (int) $one("SELECT COUNT(*) FROM risk_register WHERE risk_code = '" . $esc($TAG . '-RSK') . "'");
$trgState = (string) $one("SELECT state FROM rsk_trigger WHERE id = " . (int) $trg['trigger_id']);
$log('التعرض', 'المحفز المفروز يصير خطرا مسجلا', 'risk_register', 'Risk/risk_register.php',
     'سجل خطر واحد ومحفز محول', 'خطر=' . $riskRows . ' · حالة المحفز=' . $trgState,
     'سجل المخاطر يقرأ الخطر برمزه فيصير للتعرض مالك ومهلة', 'converted',
     $must($exp['ok'] && $riskRows === 1 && $trgState === 'converted'), $company);

$badEv = RSK::recordEvent($G, array(
    'event_no' => $TAG . '-EV-X', 'family_code' => 'OPERATIONAL', 'event_kind' => 'event',
    'source_module' => '', 'source_table' => '', 'source_row_id' => 0, 'src_ref' => $TAG,
), $A_RSK);
$log('التعرض', 'حدث خطر بلا مرجع مصدر', 'rsk_event', 'RiskDomainService',
     'رمز الرد RISK_EVENT_WITHOUT_SOURCE_REF', 'الرمز=' . $badEv['code'],
     'الحدث يقرأ مصدره بمرجعه - وبلا مرجع يصير نسخة معزولة', 'مرفوض',
     $must(!$badEv['ok'] && $badEv['code'] === 'RISK_EVENT_WITHOUT_SOURCE_REF'), $company);

$ev = RSK::recordEvent($G, array(
    'event_no' => $TAG . '-EV', 'risk_code' => $TAG . '-RSK', 'family_code' => 'OPERATIONAL',
    'event_kind' => 'event', 'source_module' => 'maintenance', 'source_table' => 'equipments',
    'source_row_id' => $srcRow, 'source_ref' => 'equipments#' . $srcRow,
    'deviation_no' => $TAG . '-DEV-A', 'occurred_at' => date('Y-m-d H:i:s'), 'src_ref' => $TAG,
), $A_RSK);
$evRows = (int) $one("SELECT COUNT(*) FROM rsk_event WHERE event_no = '" . $esc($TAG . '-EV') . "'");
$log('التعرض', 'تسجيل حدث خطر بمرجع مصدره', 'rsk_event', 'Risk/risk_events.php',
     'حدث واحد بمرجع مصدر كامل', 'أحداث=' . $evRows,
     'سطح الاحداث يعرض مصدر الواقعة بمرجعه فيقرأ التقييم مصدرا واحدا لا نسختين', 'recorded',
     $must($ev['ok'] && $evRows === 1), $company);

$dupEv = RSK::recordEvent($G, array(
    'event_no' => $TAG . '-EV2', 'risk_code' => $TAG . '-RSK', 'family_code' => 'OPERATIONAL',
    'event_kind' => 'event', 'source_module' => 'maintenance', 'source_table' => 'equipments',
    'source_row_id' => $srcRow, 'src_ref' => $TAG,
), $A_RSK);
$log('التعرض', 'نسخة ثانية للمصدر نفسه', 'rsk_event', 'RiskDomainService',
     'رمز الرد RISK_EVENT_DUPLICATES_SOURCE', 'الرمز=' . $dupEv['code'],
     'النسخة الثانية لا تظهر خطأ بل رقما مضاعفا في تقرير - والمفتاح الفريد يردها', 'مرفوض',
     $must(!$dupEv['ok'] && $dupEv['code'] === 'RISK_EVENT_DUPLICATES_SOURCE'), $company);

/* **والقبولُ يحتاج شهيّةً معتمَدةً — والمعلَّقةُ تردُّ ولا تُخترَع** */
TR::enableTestValues(false);
$noAppetite = RSK::acceptRisk($G, array(
    'risk_id' => 0, 'residual_amount' => 1, 'reason_ar' => $TAG . ' قبول',
), $A_RSKMGR);
TR::enableTestValues(true);
$log('التعرض', 'قبول خطر وشهية المخاطر غير معتمدة عدديا', 'risk_acceptances', 'RiskDomainService',
     'رمز الرد APPETITE_NOT_CONFIGURED', 'الرمز=' . $noAppetite['code'],
     'المحرك يرد ولا يخترع رقما - والقيمة غير المعتمدة لا تمنع البناء ولا تخترع', 'مرفوض',
     $must(!$noAppetite['ok'] && $noAppetite['code'] === 'APPETITE_NOT_CONFIGURED'), $company);

$esc1 = RSK::escalate($G, array(
    'risk_id' => (int) $one("SELECT id FROM risk_register WHERE risk_code = '" . $esc($TAG . '-RSK') . "'"),
    'reason_ar' => $TAG . ' خروج عن الشهية', 'to_authority' => 'risk_manager', 'is_auto' => 1,
), $A_RSK);
$escRows = (int) $one("SELECT COUNT(*) FROM risk_escalations WHERE reason_ar LIKE '" . $esc($TAG) . "%'");
$log('التعرض', 'تصعيد الخطر بسببه', 'risk_escalations', 'Risk/risk_escalations.php',
     'واقعة تصعيد واحدة بسبب مكتوب', 'تصعيدات=' . $escRows,
     'سطح التصعيدات يعرض المصعد فيراه مستواه ولا يسكت الا باستلام مسجل', 'raised',
     $must($esc1['ok'] && $escRows === 1), $company);

$clo = RSK::proposeClosure($G, array(
    'closure_no' => $TAG . '-CLO', 'risk_code' => $TAG . '-RSK',
    'closure_basis' => 'CAUSE_REMOVED', 'evidence_ref' => $TAG . '/evidence.pdf', 'src_ref' => $TAG,
), $A_RSK);
$selfClose = RSK::approveClosure($G, isset($clo['closure_id']) ? $clo['closure_id'] : 0, $A_RSK);
$log('الإغلاق', 'من اقترح الاغلاق يعتمده', 'rsk_closure', 'RiskDomainService',
     'رمز الرد SAME_ACTOR_PROPOSE_AND_APPROVE_CLOSURE', 'الرمز=' . $selfClose['code'],
     'اغلاق بيد واحدة يجعل الاثبات شكلا - والرد يمنعه في الخدمة وفي القاعدة معا', 'مرفوض',
     $must(!$selfClose['ok'] && $selfClose['code'] === 'SAME_ACTOR_PROPOSE_AND_APPROVE_CLOSURE'), $company);

$okClose = RSK::approveClosure($G, isset($clo['closure_id']) ? $clo['closure_id'] : 0, $A_RSKMGR);
$cloState = (string) $one("SELECT state FROM rsk_closure WHERE closure_no = '" . $esc($TAG . '-CLO') . "'");
$log('الإغلاق', 'اعتماد الاغلاق بيد ثانية بدليله', 'rsk_closure', 'Risk/risk_closure.php',
     'الحالة approved', 'الحالة=' . $cloState,
     'سجل الاغلاق يقرأ الخطر مغلقا بدليله فيقرأ الكون الرقابي انخفاض تقديره', 'approved',
     $must($okClose['ok'] && $cloState === 'approved'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ④ · الخرقُ عند الحوكمة — **ولا حالةَ على انحرافٍ صِرف**
   ══════════════════════════════════════════════════════════════════════════ */
$pureBreach = GOV::openBreach($G, array(
    'case_no' => $TAG . '-CASE-X', 'opened_basis' => 'CONTROL_BROKEN', 'control_ref' => 'CTRL-1',
    'deviation_no' => $TAG . '-DEV-B', 'severity' => 'low', 'title_ar' => 'محاولة على انحراف صرف',
    'src_ref' => $TAG,
), $A_GOV);
$log('الخرق', 'حالة حوكمة على انحراف تشغيلي صرف', 'gov_breach', 'GovernanceDomainService',
     'رمز الرد BREACH_ON_PURE_DEVIATION', 'الرمز=' . $pureBreach['code'],
     'محور المرحلة: لا تفتح حالة حوكمة لكل انحراف - والانحراف الصرف يبقى عند مالكه', 'مرفوض',
     $must(!$pureBreach['ok'] && $pureBreach['code'] === 'BREACH_ON_PURE_DEVIATION'), $company);

$outEight = GOV::openBreach($G, array(
    'case_no' => $TAG . '-CASE-Y', 'opened_basis' => 'OPERATIONAL_DEVIATION', 'control_ref' => 'CTRL-1',
    'severity' => 'low', 'title_ar' => 'اساس خارج الثمانية', 'src_ref' => $TAG,
), $A_GOV);
$log('الخرق', 'فتح حالة باساس خارج الثمانية', 'gov_breach', 'GovernanceDomainService',
     'رمز الرد BREACH_BASIS_OUTSIDE_EIGHT', 'الرمز=' . $outEight['code'],
     'الاساس محصور فيما سماه المالك - فلا يوسع باجتهاد', 'مرفوض',
     $must(!$outEight['ok'] && $outEight['code'] === 'BREACH_BASIS_OUTSIDE_EIGHT'), $company);

$br = GOV::openBreach($G, array(
    'case_no' => $TAG . '-CASE', 'opened_basis' => 'NO_ESCALATION', 'control_ref' => 'CTRL-ESC',
    'deviation_no' => $TAG . '-DEV-C', 'severity' => 'high', 'title_ar' => 'عدم تصعيد مطلوب',
    'src_ref' => $TAG,
), $A_GOV);
$brDev = (string) $one("SELECT deviation_no FROM gov_breach WHERE case_no = '" . $esc($TAG . '-CASE') . "'");
$log('الخرق', 'فتح حالة حوكمة على انحراف مصنف خرقا', 'gov_breach', 'Governance/breaches.php',
     'حالة واحدة تحمل رقم الانحراف مرجعا', 'مرجع الانحراف=' . $brDev,
     'سطح الاخلالات يقرأ الحالة بمرجع انحرافها ولا ينسخ الواقعة', 'opened',
     $must($br['ok'] && $brDev === $TAG . '-DEV-C'), $company);

$act = GOV::assignAction($G, array(
    'action_no' => $TAG . '-ACT', 'source_kind' => 'BREACH', 'source_ref' => $TAG . '-CASE',
    'title_ar' => 'تثبيت التصعيد الالزامي', 'owner_dept' => 'DEP-04', 'owner_person' => $A_ACTOWN,
    'due_date' => date('Y-m-d', strtotime('+7 days')), 'src_ref' => $TAG,
), $A_GOV);
$actRows = (int) $one("SELECT COUNT(*) FROM gov_corrective_action
                        WHERE source_ref = '" . $esc($TAG . '-CASE') . "'");
$log('الخرق', 'اسناد اجراء تصحيحي بمالك ومهلة', 'gov_corrective_action',
     'Governance/corrective_actions.php', 'اجراء واحد على الحالة', 'اجراءات=' . $actRows,
     'حالة الحوكمة تقرأ اجراءها فتصير قابلة للاغلاق - وقبله لا تغلق', 'assigned',
     $must($act['ok'] && $actRows === 1), $company);

$selfVerify = GOV::verifyAction($G, $act['action_id'], $TAG . '/act-evidence.pdf', $A_ACTOWN);
$log('الخرق', 'مالك الاجراء يتحقق من اجرائه', 'gov_corrective_action', 'GovernanceDomainService',
     'رمز الرد SAME_ACTOR_OWN_AND_VERIFY_ACTION', 'الرمز=' . $selfVerify['code'],
     'التحقق بيد المالك يجعل الدليل شهادة على النفس - والرد يمنعه', 'مرفوض',
     $must(!$selfVerify['ok'] && $selfVerify['code'] === 'SAME_ACTOR_OWN_AND_VERIFY_ACTION'), $company);

$okVerify = GOV::verifyAction($G, $act['action_id'], $TAG . '/act-evidence.pdf', $A_GOVMGR);
$actState = (string) $one("SELECT state FROM gov_corrective_action WHERE action_no = '" . $esc($TAG . '-ACT') . "'");
$log('الخرق', 'التحقق من الاجراء بيد ثانية بدليله', 'gov_corrective_action',
     'Governance/breaches.php', 'الحالة verified', 'الحالة=' . $actState,
     'اغلاق حالة الحوكمة يصير ممكنا - وقبل التحقق يرد الاغلاق', 'verified',
     $must($okVerify['ok'] && $actState === 'verified'), $company);

$selfCloseBr = GOV::closeBreach($G, $br['breach_id'],
    array('action_no' => $TAG . '-ACT', 'close_evidence' => $TAG . '/close.pdf'), $A_GOV);
$log('الخرق', 'من فتح الحالة يغلقها', 'gov_breach', 'GovernanceDomainService',
     'رمز الرد SAME_ACTOR_OPEN_AND_CLOSE_BREACH', 'الرمز=' . $selfCloseBr['code'],
     'اغلاق بيد الفاتح يجعل الحالة سجل شكوى - والرد يمنعه في الخدمة وفي القاعدة', 'مرفوض',
     $must(!$selfCloseBr['ok'] && $selfCloseBr['code'] === 'SAME_ACTOR_OPEN_AND_CLOSE_BREACH'), $company);

$okCloseBr = GOV::closeBreach($G, $br['breach_id'],
    array('action_no' => $TAG . '-ACT', 'close_evidence' => $TAG . '/close.pdf'), $A_GOVMGR);
$brState = (string) $one("SELECT state FROM gov_breach WHERE case_no = '" . $esc($TAG . '-CASE') . "'");
$log('الخرق', 'اغلاق الحالة بيد ثانية بدليلها', 'gov_breach', 'Governance/gov_board.php',
     'الحالة closed', 'الحالة=' . $brState,
     'لوحة الحوكمة تنقص عداد المفتوح فيقرأ المستوى اثر الاغلاق لا دعواه', 'closed',
     $must($okCloseBr['ok'] && $brState === 'closed'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑤ · التحقيقُ بمالكِه — ثلاثةُ أنواعٍ بثلاثةِ ملّاك
   ══════════════════════════════════════════════════════════════════════════ */
$wrongOwner = GOV::openInvestigation($G, array(
    'inv_no' => $TAG . '-INV-X', 'inv_kind' => 'DISCIPLINARY', 'owner_dept' => 'DEP-08',
    'origin' => 'MANAGEMENT_REQUEST', 'scope_ar' => 'غياب موظف', 'src_ref' => $TAG,
), $A_GOV);
$log('التحقيق', 'تحقيق تاديبي عند الحوكمة', 'gov_investigation', 'GovernanceDomainService',
     'رمز الرد INVESTIGATION_KIND_OUTSIDE_OWNER', 'الرمز=' . $wrongOwner['code'],
     'التاديبي للموارد البشرية بنص DEC-OPEN-16 - والرد ينفذ الحسم لا يعيده اجتهادا', 'مرفوض',
     $must(!$wrongOwner['ok'] && $wrongOwner['code'] === 'INVESTIGATION_KIND_OUTSIDE_OWNER'), $company);

$noMandate = GOV::openInvestigation($G, array(
    'inv_no' => $TAG . '-INV-Y', 'inv_kind' => 'SPECIAL_INDEPENDENT', 'owner_dept' => 'IAF',
    'origin' => 'OWNER_ORDER', 'scope_ar' => 'قضية تنفيذي', 'src_ref' => $TAG,
), $A_GOV);
$log('التحقيق', 'تحقيق مستقل للمراجعة بلا تكليف مكتوب', 'gov_investigation',
     'GovernanceDomainService', 'رمز الرد IAF_INVESTIGATION_WITHOUT_MANDATE',
     'الرمز=' . $noMandate['code'],
     'المراجعة لا طابور تحقيق يومي لها - وتدخل بتكليف مكتوب حالة بحالة', 'مرفوض',
     $must(!$noMandate['ok'] && $noMandate['code'] === 'IAF_INVESTIGATION_WITHOUT_MANDATE'), $company);

$denialInv = GOV::openInvestigation($G, array(
    'inv_no' => $TAG . '-INV-Z', 'inv_kind' => 'INTEGRITY', 'owner_dept' => 'DEP-08',
    'origin' => 'DENIAL', 'origin_ref' => 'DENY-1', 'scope_ar' => 'محاولة ممنوعة', 'src_ref' => $TAG,
), $A_GOV);
$log('التحقيق', 'تحقيق من سجل المنع بلا فرز', 'gov_investigation', 'GovernanceDomainService',
     'رمز الرد DENIAL_IS_NOT_AN_INVESTIGATION', 'الرمز=' . $denialInv['code'],
     'النقر الممنوع ليس تحقيقا - والسلسلة سجل ثم نمط ثم تنبيه ثم فرز ثم تحقيق ان لزم', 'مرفوض',
     $must(!$denialInv['ok'] && $denialInv['code'] === 'DENIAL_IS_NOT_AN_INVESTIGATION'), $company);

$rep = GOV::receiveIntegrityReport($G, array(
    'report_no' => $TAG . '-REP', 'channel' => 'protected', 'is_anonymous' => 1,
    'reporter_token' => sha1($TAG . 'token'), 'disclosure_role_key' => 'gov.integrity.discloser',
    'subject_ar' => 'اشتباه تجاوز صلاحية', 'src_ref' => $TAG,
));
$anonOk = (int) $one("SELECT COUNT(*) FROM gov_integrity_report
                       WHERE report_no = '" . $esc($TAG . '-REP') . "' AND reporter_person = 0");
$log('التحقيق', 'بلاغ نزاهة بهوية محجوبة', 'gov_integrity_report',
     'Governance/integrity_reports.php', 'بلاغ برمز مبلغ وبلا مفتاح انسان',
     'محجوب=' . $anonOk,
     'سطح البلاغات يعرض الموضوع بلا هوية - والكشف لمستوى مخول وحده', 'received',
     $must($rep['ok'] && $anonOk === 1), $company);

$tri = GOV::triageIntegrityReport($G, $rep['report_id'], 'DEP-08', $A_GOVMGR);
$log('التحقيق', 'فرز البلاغ قبل اي تحقيق', 'gov_integrity_report', 'Governance/investigations.php',
     'مرجع فرز مقروء', 'مرجع الفرز=' . (isset($tri['triage_ref']) ? $tri['triage_ref'] : $tri['code']),
     'التحقيق يصير ممكنا بمرجع الفرز وحده - فلا يفتح تحقيق من تنبيه', 'referred',
     $must($tri['ok'] && $tri['triage_ref'] === $TAG . '-REP'), $company);

$conflictInv = GOV::openInvestigation($G, array(
    'inv_no' => $TAG . '-INV-C', 'inv_kind' => 'INTEGRITY', 'owner_dept' => 'DEP-08',
    'origin' => 'INTEGRITY_REPORT', 'origin_ref' => $TAG . '-REP', 'triage_ref' => $TAG . '-REP',
    'subject_person' => $A_GOVMGR, 'investigator_id' => $A_GOV, 'conflict_flag' => 1,
    'scope_ar' => 'تعارض معلن', 'src_ref' => $TAG,
), $A_GOV);
$log('التحقيق', 'تعارض معلن بلا تنح وسلطة محجوزة', 'gov_investigation',
     'GovernanceDomainService', 'رمز الرد CONFLICT_WITHOUT_RECUSAL',
     'الرمز=' . $conflictInv['code'],
     'ملكية السياسة لا تمنع التحقيق - لكن التعارض على مستوى القضية يوجب التنحي', 'مرفوض',
     $must(!$conflictInv['ok'] && $conflictInv['code'] === 'CONFLICT_WITHOUT_RECUSAL'), $company);

$inv = GOV::openInvestigation($G, array(
    'inv_no' => $TAG . '-INV', 'inv_kind' => 'INTEGRITY', 'owner_dept' => 'DEP-08',
    'origin' => 'INTEGRITY_REPORT', 'origin_ref' => $TAG . '-REP', 'triage_ref' => $TAG . '-REP',
    'subject_person' => $A_ACTOWN, 'investigator_id' => $A_GOVMGR,
    'scope_ar' => 'تجاوز صلاحية معلن', 'src_ref' => $TAG,
), $A_GOV);
$G->update('gov_investigation', array('state' => 'evidence'), array('id' => $inv['investigation_id']));
$selfConc = GOV::concludeInvestigation($G, $inv['investigation_id'],
    array('conclusion_ar' => 'ثبت التجاوز', 'referred_to' => 'DEP-07'), $A_GOV);
$log('التحقيق', 'من فتح التحقيق يحسمه', 'gov_investigation', 'GovernanceDomainService',
     'رمز الرد SAME_ACTOR_OPEN_AND_CONCLUDE', 'الرمز=' . $selfConc['code'],
     'الحسم بيد الفاتح يجعل التحقيق رأيا - والرد يمنعه في الخدمة وفي القاعدة', 'مرفوض',
     $must(!$selfConc['ok'] && $selfConc['code'] === 'SAME_ACTOR_OPEN_AND_CONCLUDE'), $company);

$conc = GOV::concludeInvestigation($G, $inv['investigation_id'],
    array('conclusion_ar' => 'ثبت التجاوز', 'referred_to' => 'DEP-07'), $A_RESERVED);
$invRef = (string) $one("SELECT referred_to FROM gov_investigation WHERE inv_no = '" . $esc($TAG . '-INV') . "'");
$log('التحقيق', 'حسم التحقيق بيد ثانية واحالة اثره', 'gov_investigation',
     'Employees/hr_disciplinary.php', 'الاحالة الى الموارد البشرية', 'أحيل الى=' . $invRef,
     'الموارد تستقبل النتيجة للاثر التاديبي ولا تعيد التحقيق نفسه', 'referred',
     $must($conc['ok'] && $invRef === 'DEP-07'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑥ · المراجعةُ خطٌّ ثالثٌ لا تعدّله الحوكمة
   ══════════════════════════════════════════════════════════════════════════ */
$govScope = IAF::draftProgram($G, array(
    'program_no' => $TAG . '-PRG-X', 'objective_ar' => 'اختبار الاعتمادات',
    'test_method' => 'inspection', 'scope_set_by_dept' => 'DEP-08', 'src_ref' => $TAG,
));
$log('المراجعة', 'نطاق برنامج مراجعة تحدده الحوكمة', 'iaf_program', 'AuditDomainService',
     'رمز الرد AUDIT_SCOPE_SET_BY_GOVERNANCE', 'الرمز=' . $govScope['code'],
     'الحوكمة لا تعطي المراجع نطاقه - والرد ينفذ استقلال الخط الثالث', 'مرفوض',
     $must(!$govScope['ok'] && $govScope['code'] === 'AUDIT_SCOPE_SET_BY_GOVERNANCE'), $company);

$prg = IAF::draftProgram($G, array(
    'program_no' => $TAG . '-PRG', 'engagement_no' => $TAG . '-ENG', 'step_no' => 1,
    'objective_ar' => 'اختبار تصعيد التوقفات فوق الحد', 'test_method' => 'inspection',
    'population_ar' => 'توقفات الفترة', 'sample_size' => 5,
    'sampling_basis' => 'عينة عشوائية من مجتمع التوقفات غير المخططة',
    'performer_id' => $A_AUD, 'src_ref' => $TAG,
));
$selfRev = IAF::approveProgram($G, isset($prg['program_id']) ? $prg['program_id'] : 0, $A_AUD);
$log('المراجعة', 'من نفذ الخطوة يراجعها', 'iaf_program', 'AuditDomainService',
     'رمز الرد SAME_ACTOR_PERFORM_AND_REVIEW', 'الرمز=' . $selfRev['code'],
     'مراجعة المنفذ لعمله تفرغ الاختبار - والرد يمنعها في الخدمة وفي القاعدة', 'مرفوض',
     $must(!$selfRev['ok'] && $selfRev['code'] === 'SAME_ACTOR_PERFORM_AND_REVIEW'), $company);

$okPrg = IAF::approveProgram($G, isset($prg['program_id']) ? $prg['program_id'] : 0, $A_AUDLEAD);
$prgState = (string) $one("SELECT state FROM iaf_program WHERE program_no = '" . $esc($TAG . '-PRG') . "'");
$log('المراجعة', 'اعتماد البرنامج بيد ثانية بمنهجية عينته', 'iaf_program',
     'Audit/iaf_test_samples.php', 'الحالة approved', 'الحالة=' . $prgState,
     'سحب العينة يصير ممكنا - وقبل الاعتماد لا مفردة تسحب', 'approved',
     $must($okPrg['ok'] && $prgState === 'approved'), $company);

$selfReq = IAF::requestEvidence($G, array(
    'request_no' => $TAG . '-REQ-X', 'engagement_no' => $TAG . '-ENG',
    'auditee_dept' => 'IAF', 'item_ar' => 'كشف', 'due_date' => date('Y-m-d'), 'src_ref' => $TAG,
));
$log('المراجعة', 'طلب دليل من المراجعة نفسها', 'iaf_evidence_request', 'AuditDomainService',
     'رمز الرد EVIDENCE_REQUEST_TO_SELF', 'الرمز=' . $selfReq['code'],
     'طلب المراجع دليلا من نفسه يفرغ التاكيد المستقل - والرد يمنعه', 'مرفوض',
     $must(!$selfReq['ok'] && $selfReq['code'] === 'EVIDENCE_REQUEST_TO_SELF'), $company);

$req = IAF::requestEvidence($G, array(
    'request_no' => $TAG . '-REQ', 'engagement_no' => $TAG . '-ENG', 'program_no' => $TAG . '-PRG',
    'auditee_dept' => 'DEP-04', 'auditee_person' => $A_ACTOWN,
    'item_ar' => 'سجل تصعيد التوقفات', 'due_date' => date('Y-m-d', strtotime('-20 days')),
    'src_ref' => $TAG,
));
$escEv = IAF::escalateEvidence($G, isset($req['request_id']) ? $req['request_id'] : 0, 20);
$reqState = (string) $one("SELECT state FROM iaf_evidence_request
                            WHERE request_no = '" . $esc($TAG . '-REQ') . "'");
$log('المراجعة', 'تاخر التزويد يصعد بعتبة من السجل', 'iaf_evidence_request',
     'Audit/iaf_evidence_requests.php', 'الحالة escalated ووسم العتبة',
     'الحالة=' . $reqState . ' · وسم=' . (isset($escEv['threshold_tag']) ? $escEv['threshold_tag'] : $escEv['code']),
     'سطح الطلبات يعرض المتاخر بسلمه - والعتبة معلقة فتقرأ قيمة اختبار موسومة لا رقما مخترعا',
     'escalated', $must($escEv['ok'] && $reqState === 'escalated'
                        && $escEv['threshold_tag'] === 'TEST_ONLY_VALUE'), $company);

$smp = IAF::drawSample($G, array(
    'sample_no' => $TAG . '-SMP', 'program_no' => $TAG . '-PRG', 'step_no' => 1,
    'item_ref' => 'ctl_deviation#' . $devC['deviation_id'],
    'source_table' => 'ctl_deviation', 'source_row_id' => $devC['deviation_id'], 'src_ref' => $TAG,
));
$tst = IAF::testSample($G, isset($smp['sample_id']) ? $smp['sample_id'] : 0,
    'exception', 'التوقف لم يصعد رغم تجاوز الحد', $A_AUD);
$smpRes = (string) $one("SELECT test_result FROM iaf_sample WHERE sample_no = '" . $esc($TAG . '-SMP') . "'");
$log('المراجعة', 'اختبار مفردة عينة بنتيجتها', 'iaf_sample', 'Audit/iaf_findings.php',
     'النتيجة exception بتفصيلها', 'النتيجة=' . $smpRes,
     'رفع الملاحظة يصير مسندا الى مفردة مختبرة لا الى انطباع', 'tested',
     $must($smp['ok'] && $tst['ok'] && $smpRes === 'exception'), $company);

$eng = (int) $one("SELECT id FROM iaf_engagements ORDER BY id LIMIT 1");
$fnd = IAF::raiseFinding($G, array(
    'finding_no' => $TAG . '-FND', 'engagement_id' => $eng > 0 ? $eng : 0, 'area_code' => 'OPS',
    'auditee_dept' => 'إدارة العمليات', 'auditee_user_id' => $A_ACTOWN,
    'title' => 'ضعف تصعيد التوقفات فوق الحد', 'detail' => 'مفردة عينة لم تصعد',
    'severity' => 'high', 'evidence_ref' => $TAG . '/wp.pdf',
), $A_AUD);
$fndSetBy = (string) $one("SELECT result_set_by_dept FROM iaf_findings
                            WHERE finding_no = '" . $esc($TAG . '-FND') . "'");
$log('المراجعة', 'رفع ملاحظة ونتيجتها من المراجعة وحدها', 'iaf_findings',
     'Governance/audit_followup.php', 'واضع النتيجة IAF', 'واضع النتيجة=' . $fndSetBy,
     'متابعة الحوكمة تفتح على الملاحظة بمرجعها ولا تلمس نتيجتها', 'open',
     $must($fnd['ok'] && $fndSetBy === 'IAF'), $company);

/* ⛔ **الحدُّ الذي لا يُعبَر — يُختبَر من ثلاثِ جهاتٍ لا واحدة** */
$govSet = GOV::attemptSetAuditResult();
$log('الاستقلال', 'الحوكمة تضع نتيجة مراجعة عبر خدمتها', 'iaf_findings',
     'GovernanceDomainService', 'رمز الرد GOVERNANCE_CANNOT_SET_AUDIT_RESULT',
     'الرمز=' . $govSet['code'],
     'الخدمة لا تملك بابا الى سجل الملاحظات اصلا - والنداء يعلن الحد ويرده', 'مرفوض',
     $must(!$govSet['ok'] && $govSet['code'] === 'GOVERNANCE_CANNOT_SET_AUDIT_RESULT'), $company);

$fndId = (int) $one("SELECT id FROM iaf_findings WHERE finding_no = '" . $esc($TAG . '-FND') . "'");
@$conn->query("UPDATE iaf_findings SET result_set_by_dept = 'DEP-08' WHERE id = $fndId");
$dbRefused = ($conn->errno !== 0);
$stillIaf = (string) $one("SELECT result_set_by_dept FROM iaf_findings WHERE id = $fndId");
$log('الاستقلال', 'الحوكمة تضع نتيجة مراجعة بكتابة مباشرة في القاعدة', 'iaf_findings',
     'chk_iaf_result_dept', 'القاعدة ترد والقيمة تبقى IAF',
     'ردت=' . ($dbRefused ? 'نعم' : 'لا') . ' · القيمة=' . $stillIaf,
     'الاستقلال لا يثبت بسياسة اذا كان الباب مفتوحا في المخطط - والقيد يغلقه', 'مرفوض',
     $must($dbRefused && $stillIaf === 'IAF'), $company);

$govClose = IAF::closeFinding($G, $fndId,
    array('evidence_ref' => $TAG . '/fix.pdf', 'closer_dept' => 'DEP-08'), $A_AUDLEAD);
$log('الاستقلال', 'الحوكمة تغلق ملاحظة مراجعة', 'iaf_findings', 'AuditDomainService',
     'رمز الرد AUDIT_RESULT_CLOSED_OUTSIDE_IAF', 'الرمز=' . $govClose['code'],
     'الاغلاق بتحقق المراجعة لا بادعاء الجهة ولا بقرار الحوكمة', 'مرفوض',
     $must(!$govClose['ok'] && $govClose['code'] === 'AUDIT_RESULT_CLOSED_OUTSIDE_IAF'), $company);

$auditeeClose = IAF::closeFinding($G, $fndId, array('evidence_ref' => $TAG . '/fix.pdf'), $A_ACTOWN);
$log('الاستقلال', 'الخاضع للمراجعة يغلق ملاحظته', 'iaf_findings', 'AuditDomainService',
     'رمز الرد AUDITEE_CANNOT_CLOSE_OWN_FINDING', 'الرمز=' . $auditeeClose['code'],
     'الاغلاق بادعاء الجهة يفرغ المتابعة - والرد يمنعه', 'مرفوض',
     $must(!$auditeeClose['ok'] && $auditeeClose['code'] === 'AUDITEE_CANNOT_CLOSE_OWN_FINDING'), $company);

$fu = GOV::trackAuditFinding($G, array(
    'followup_no' => $TAG . '-FU', 'finding_no' => $TAG . '-FND', 'finding_source' => 'internal',
    'mgmt_plan_ar' => 'تعديل اجراء التصعيد', 'plan_owner_dept' => 'DEP-04',
    'plan_due' => date('Y-m-d', strtotime('+30 days')), 'action_no' => $TAG . '-ACT', 'src_ref' => $TAG,
));
$fuHasResult = repair01_w14_col_exists($conn, 'gov_audit_followup', 'finding_severity') ? 1 : 0;
$log('الاستقلال', 'الحوكمة تتابع خطة الادارة بمرجع الملاحظة', 'gov_audit_followup',
     'Governance/audit_followup.php', 'متابعة بمرجع وصفر عمود نتيجة',
     'متابعة=' . ($fu['ok'] ? 1 : 0) . ' · عمود نتيجة=' . $fuHasResult,
     'الحوكمة تقرأ الملاحظة وتتابع خطتها - ولا تحمل نتيجتها ولا تقديرها اصلا', 'tracking',
     $must($fu['ok'] && $fuHasResult === 0), $company);

$okCloseF = IAF::closeFinding($G, $fndId, array('evidence_ref' => $TAG . '/fix.pdf'), $A_AUDLEAD);
$fndClosedBy = (string) $one("SELECT result_closed_by_dept FROM iaf_findings WHERE id = $fndId");
$log('الاستقلال', 'المراجعة تغلق ملاحظتها بتحققها', 'iaf_findings', 'Audit/iaf_overview.php',
     'مغلق النتيجة IAF', 'مغلق النتيجة=' . $fndClosedBy,
     'لوحة المراجعة تنقص عداد المفتوح - والحوكمة تقرأ الاغلاق ولا تصنعه', 'closed',
     $must($okCloseF['ok'] && $fndClosedBy === 'IAF'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑦ · الحدودُ الثلاثةُ الأخرى — تُختبَر ولا تُدَّعى
   ══════════════════════════════════════════════════════════════════════════ */
foreach (array(
    array(RSK::attemptWriteGovernanceCase(), 'RISK_CANNOT_OPEN_GOVERNANCE_CASE',
          'المخاطر تفتح حالة حوكمة', 'RiskDomainService'),
    array(RSK::attemptSetAuditResult(), 'RISK_CANNOT_SET_AUDIT_RESULT',
          'المخاطر تضع نتيجة مراجعة', 'RiskDomainService'),
    array(GOV::attemptWriteRiskRegister(), 'GOVERNANCE_CANNOT_WRITE_RISK_REGISTER',
          'الحوكمة تكتب في سجل المخاطر', 'GovernanceDomainService'),
    array(GOV::attemptSetAuditScope(), 'AUDIT_SCOPE_SET_BY_GOVERNANCE',
          'الحوكمة تحدد نطاق المراجعة', 'GovernanceDomainService'),
    array(IAF::attemptWriteRiskRegister(), 'AUDIT_CANNOT_WRITE_RISK_REGISTER',
          'المراجعة تكتب في سجل المخاطر', 'AuditDomainService'),
    array(IAF::attemptWriteGovernanceCase(), 'AUDIT_CANNOT_OPEN_GOVERNANCE_CASE',
          'المراجعة تفتح حالة حوكمة', 'AuditDomainService'),
    array(IAF::attemptOpenDailyInvestigation(), 'IAF_HAS_NO_DAILY_INVESTIGATION_QUEUE',
          'المراجعة تفتح طابور تحقيق يومي', 'AuditDomainService'),
) as $b) {
    $log('الحدود', $b[2], 'repair01_w14_domains', $b[3],
         'رمز الرد ' . $b[1], 'الرمز=' . $b[0]['code'],
         'ثلاثة نطاقات لا محرك واحد - والعلاقة مرجع لا مشاركة', 'مرفوض',
         $must(!$b[0]['ok'] && $b[0]['code'] === $b[1]), $company);
}

/* ══════════════════════════════════════════════════════════════════════════
   الختامُ — محاورُ المرحلةِ الثلاثةُ مقيسةٌ على الحيِّ بعد الرحلة
   ══════════════════════════════════════════════════════════════════════════ */
$gc = repair01_w14_gov_case_on_pure_deviation($conn);
$rc = repair01_w14_risk_event_copies($conn);
$at = repair01_w14_gov_touched_audit_result($conn);
$xw = repair01_w14_cross_domain_writes($ROOT);
$log('الختام', 'محاور المرحلة الثلاثة مقيسة على الحي بعد الرحلة', 'repair01_w14_domains',
     'repair01_w14_gate.php',
     'حالة على انحراف صرف 0 ونسخ حدث 0 وتعديل نتيجة 0 وكتابة عابرة 0',
     'حالة=' . $gc['n'] . ' · نسخ=' . $rc['n'] . ' · تعديل=' . $at['n'] . ' · عابرة=' . $xw['n'],
     'الرحلة مارست الجداول فعلا ثم قيست عليها فالبناء مثبت لا مدعى', 'صفر',
     $must($gc['n'] === 0 && $rc['n'] === 0 && $at['n'] === 0 && $xw['n'] === 0
           && $gc['front'] === 3 && $rc['front'] === 4 && $at['front'] === 4), $company);

/* ══════════════════════════════════════════════════════════════════════════
   التسجيلُ والكنسُ ثمَّ الحكم
   ══════════════════════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM repair01_w14_journey");
$n = 0; $passed = 0; $noEffect = 0;
foreach ($ST as $s) {
    $n++;
    if ($s[8]) { $passed++; }
    if (trim((string) $s[6]) === '') { $noEffect++; }
    $conn->query("INSERT INTO repair01_w14_journey
        (run_id,station_no,leg,station,entity,consumer,expected,measured,business_effect,
         state_after,company_id,passed,measured_at)
        VALUES ('" . $esc($RUN) . "'," . $n . ",'" . $esc($s[0]) . "','" . $esc($s[1]) . "',
                '" . $esc($s[2]) . "','" . $esc($s[3]) . "','" . $esc($s[4]) . "','" . $esc($s[5]) . "',
                '" . $esc($s[6]) . "','" . $esc($s[7]) . "'," . (int) $s[9] . "," . (int) $s[8] . ",NOW())");
    printf("  %s %-12s %s\n     متوقع: %s\n     مقيس : %s\n     أثر  : %s\n",
        $s[8] ? '✔' : '✘', $s[0], $s[1], $s[4], $s[5], $s[6]);
}

$consumers = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w14_journey WHERE run_id = '" . $esc($RUN) . "'");
$legs = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w14_journey WHERE run_id = '" . $esc($RUN) . "'");

/* **الكنسُ يُشغَّل مرّتَين** — والباقي يُقاس بعده لا يُدَّعى صفرًا */
$sweep();
$sweep();
$left = 0;
foreach (array("SELECT COUNT(*) FROM ctl_deviation WHERE deviation_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM ctl_classification_rule WHERE rule_code LIKE '$TAG%'",
               "SELECT COUNT(*) FROM rsk_trigger WHERE trigger_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM rsk_event WHERE event_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM rsk_closure WHERE closure_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM risk_register WHERE risk_code LIKE '$TAG%'",
               "SELECT COUNT(*) FROM gov_breach WHERE case_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM gov_corrective_action WHERE action_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM gov_investigation WHERE inv_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM gov_integrity_report WHERE report_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM gov_audit_followup WHERE followup_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM iaf_program WHERE program_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM iaf_evidence_request WHERE request_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM iaf_sample WHERE sample_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM iaf_findings WHERE finding_no LIKE '$TAG%'") as $q) {
    $left += (int) $one($q);
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("رحلةُ الضابط: %d/%d محطّة · %d أشواط · %d مستهلكًا متمايزًا · كيانٌ واحد · بلا أثرٍ تجاريٍّ %d · أثرٌ باقٍ %d\n",
    $passed, $n, $legs, $consumers, $noEffect, $left);
$ok = ($FAIL === 0 && $passed === $n && $noEffect === 0 && $left === 0);
echo $ok ? "الحكم: عبرت ✔\n" : "الحكم: لم تعبر ✘\n";
exit($ok ? 0 : 1);
