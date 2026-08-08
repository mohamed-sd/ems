<?php

namespace App\Services\Risk;

require_once __DIR__ . '/../../Core/EventPublisher.php';

use App\Core\EventPublisher;

/**
 * RiskEvents — ناشرُ أحداثِ M-16 الستةِ والعشرين (§10-1)
 * ═══════════════════════════════════════════════════════════════════════════
 * الحكمُ الأول في الوثيقة (§2-2): «لا تكتب إدارةُ المخاطرِ في جدولِ إدارةٍ أخرى
 * — والأثرُ ينتقل بالأحداثِ لا بالنداءِ المباشر». فهذه الطبقةُ هي المنفذُ الوحيدُ
 * للأثرِ العابر: كلُّ فعلٍ كاتبٍ في RiskService ينشر حقيقتَه هنا، ومن يعنيه
 * يستهلكها من الجذرِ المحايد ems_business_events.
 *
 * لماذا publishFact لا publish (RK-06): «تقييمُ الخطرِ لا يُنشئ قيدًا ماليًّا —
 * وإن تحقق الخطرُ نُشر حدثٌ إلى المالية والماليةُ وحدَها تقرر المعالجةَ
 * المحاسبية». فأحداثُ المخاطرِ حقائقُ محايدةٌ لا تلمس الدفترَ الماليَّ إطلاقًا؛
 * وpublishFact تكتب في الجذرِ وحدَه بلا أيِّ إسقاطٍ مالي. و§2-1 يعلنها صريحًا:
 * صفرُ فعلٍ ذي أثرٍ ماليٍّ في هذه الوحدة.
 *
 * الذرّية: النشرُ على اتصالِ المستدعي نفسِه — فإن كان داخلَ معاملةٍ شارك فيها،
 * وفشلُ معاملةِ المصدر = تراجعُ الحدثِ معه (صفرُ حدثٍ يتيم).
 *
 * العطالة (§7-3): «كلُّ فعلٍ ذي أثرٍ يحمل مفتاحًا — وإعادةُ النداءِ ترجع مرجعَ
 * الأول». المفتاحُ حتميٌّ من (event_key، entity_type، entity_id) ما لم يُمرَّر —
 * ونمرّره صريحًا حيث يتكرر الفعلُ على الكيانِ نفسِه (التقييمُ نسخًا · التحققُ
 * دوريًّا · قراءةُ المؤشر) وإلا لاختُزلت النسخةُ الثانيةُ في الأولى.
 *
 * الإطفاء: publishFact تعود null حين EMS_EVENT_ROOT ≠ publish — فلا ترمي ولا
 * تكسر الفعلَ المجاليّ. ولا يُتَّخذ ذلك ذريعةً: العلمُ اليومَ publish والحزامُ يقيسه.
 */
class RiskEvents
{
    /**
     * قاموسُ الأحداثِ الستةِ والعشرين (§7-1 · §10-1) — الرمزُ ⇒ [مفتاحُ الحدث،
     * التصنيف، نوعُ الكيان]. لا حدثَ يُنشر من خارجِ هذا القاموس: فالرمزُ الذي
     * لا يُعرف أثرُه لا يُنشر (§8: لا يُقبل زرٌّ بلا صفٍّ في المصفوفة).
     *
     * التصنيف: operational لأحداثِ الدورةِ · analytics للقياسِ والتصدير ·
     * maintenance لفشلِ الضابطِ الحرجِ (يستهلكه بلاغُ الصيانةِ والسلامة).
     */
    const MAP = array(
        // ① الرصد والفرز
        'RiskSignalRaised'        => array('risk.signal.raised', 'operational', 'risk_signal'),
        'RiskSignalTriaged'       => array('risk.signal.triaged', 'operational', 'risk_signal'),
        'RiskSignalsSynced'       => array('risk.signal.synced', 'operational', 'risk_signal'),
        // ② التسجيل والتصنيف
        'RiskRegistered'          => array('risk.risk.registered', 'operational', 'risk'),
        'RiskClassified'          => array('risk.risk.classified', 'operational', 'risk'),
        'RiskOwnerAssigned'       => array('risk.risk.owner_assigned', 'operational', 'risk'),
        'RiskMerged'              => array('risk.risk.merged', 'operational', 'risk'),
        // ③ التقييم والتحدي
        'RiskAssessedInherent'    => array('risk.assessment.inherent', 'analytics', 'risk'),
        'RiskAssessedResidual'    => array('risk.assessment.residual', 'analytics', 'risk'),
        'RiskAssessedTarget'      => array('risk.assessment.target', 'analytics', 'risk'),
        'RiskAppetiteCompared'    => array('risk.appetite.compared', 'analytics', 'risk'),
        // ④ الضوابط والتحقق
        'RiskControlCreated'      => array('risk.control.created', 'operational', 'risk_control'),
        'RiskControlLinked'       => array('risk.control.linked', 'operational', 'risk_control'),
        'ControlEvidenceLogged'   => array('risk.control.evidence', 'operational', 'risk_control'),
        'ControlVerified'         => array('risk.control.verified', 'operational', 'risk_control'),
        'CriticalControlFailed'   => array('risk.control.failed', 'maintenance', 'risk_control'),
        // ⑤ المراقبة والمؤشرات
        'KRIThresholdSet'         => array('risk.kri.threshold_set', 'analytics', 'risk_kri'),
        'KRIBreached'             => array('risk.kri.breached', 'analytics', 'risk_kri'),
        // ⑥ المعالجة والمتابعة
        'RiskTreatmentPlanned'    => array('risk.treatment.planned', 'operational', 'risk_treatment'),
        'RiskTreatmentProgressed' => array('risk.treatment.progressed', 'operational', 'risk_treatment'),
        'RiskTreatmentClosed'     => array('risk.treatment.closed', 'operational', 'risk_treatment'),
        // ⑦ القبول والتصعيد والمراجعة
        'RiskAccepted'            => array('risk.risk.accepted', 'operational', 'risk'),
        'RiskEscalated'           => array('risk.risk.escalated', 'operational', 'risk'),
        'RiskReviewed'            => array('risk.risk.reviewed', 'operational', 'risk'),
        'RiskClosed'              => array('risk.risk.closed', 'operational', 'risk'),
        'RiskReopened'            => array('risk.risk.reopened', 'operational', 'risk'),
        // ⑧ الوقائع والحوكمة والتصدير
        'IncidentLogged'          => array('risk.incident.logged', 'operational', 'risk_incident'),
        'RiskTaxonomyDefined'     => array('risk.taxonomy.defined', 'operational', 'risk_unit'),
        'RiskAppetiteSet'         => array('risk.appetite.set', 'operational', 'risk_appetite'),
        'RiskReportExported'      => array('risk.report.exported', 'analytics', 'risk_export'),
        'AccessReviewAttested'    => array('risk.access_review.attested', 'operational', 'access_review'),
    );

    /**
     * الأحداثُ الستةُ والعشرون التي تُلزم بها §7-1 نصًّا — دونها لا تُعدُّ الوثيقةُ
     * منفَّذة. وما زاد عليها في MAP فحقائقُ الأفعالِ الأربعةِ الزائدةِ في التنفيذ
     * (التصنيف · الدمج · المستهدف · إنشاءُ الضابط) ومخرجاتُ المحرّكين (حكمُ الشهية
     * وتجاوزُ المؤشر) — تُنشر ولا تُحسب في مطابقةِ العدد.
     */
    const DOC_REQUIRED = array(
        'RiskSignalRaised', 'RiskSignalTriaged', 'RiskRegistered', 'RiskOwnerAssigned',
        'RiskAssessedInherent', 'RiskControlLinked', 'ControlVerified', 'CriticalControlFailed',
        'RiskAssessedResidual', 'RiskTreatmentPlanned', 'RiskTreatmentClosed', 'RiskAccepted',
        'RiskEscalated', 'RiskReviewed', 'RiskClosed', 'RiskReopened', 'IncidentLogged',
        'KRIThresholdSet', 'RiskAppetiteSet', 'RiskTaxonomyDefined', 'RiskReportExported',
        'ControlEvidenceLogged', 'RiskTreatmentProgressed', 'RiskSignalsSynced',
        'AccessReviewAttested', 'RiskAppetiteCompared',
    );

    /**
     * نشرُ حقيقةٍ واحدةٍ من القاموس.
     *
     * @param string $eventName اسمُ الحدثِ كما في §7-1 (RiskRegistered…)
     * @param int    $entityId  معرّفُ الكيانِ الذي وقع عليه الفعل
     * @param array  $payload   تفاصيلُ الحدثِ نفسِه — لا نسخَ حقولٍ من جداولِ غيره
     * @param string $idemSuffix لاحقةُ العطالةِ حين يتكرر الفعلُ على الكيانِ نفسِه
     * @return array|null ناتجُ الناشرِ أو null إن كان علمُ الجذرِ مطفأً
     */
    /**
     * الفاعلُ النظاميُّ للنبضات (نمطُ forSystem المتَّبع في المنصة: actor = 1).
     * عقدُ §9 يلزم created_by موجبًا حصرًا — فتمريرُ صفرٍ من كرونٍ يُسقط الحدثَ
     * صمتًا. والوقوعُ في ذلك يعني «صفرَ حدثٍ» بلا سببٍ ظاهر، فيُطبَّع هنا مرةً
     * واحدةً لكلِّ المسارات بدلًا من تكرارِ الحيلةِ في كلِّ نداء.
     */
    const SYSTEM_ACTOR = 1;

    public static function fire(\mysqli $db, $companyId, $eventName, $entityId, array $payload, $userId, $idemSuffix = '')
    {
        $userId = (int) $userId > 0 ? (int) $userId : self::SYSTEM_ACTOR;
        if (!isset(self::MAP[$eventName])) {
            // حدثٌ خارجَ القاموسِ خطأُ برمجةٍ لا حالةُ بيانات — يُرمى ليُصلَح بناءً.
            throw new \RuntimeException('RSK-500: حدثٌ خارجَ قاموسِ M-16: ' . $eventName);
        }
        list($key, $category, $entityType) = self::MAP[$eventName];
        $entityId = (int) $entityId;
        if ($entityId <= 0) {
            throw new \RuntimeException('RSK-500: الحدثُ ' . $eventName . ' بلا معرّفِ كيان');
        }
        $idem = null;
        if ($idemSuffix !== '') {
            $idem = substr('rsk:' . $key . ':' . $entityId . ':' . $idemSuffix, 0, 64);
        }
        $e = array(
            'event_key'     => $key,
            'category'      => $category,
            'source_module' => 'risk',
            'source_ref'    => $eventName,
            'company_id'    => (int) $companyId,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'occurred_at'   => gmdate('Y-m-d H:i:s'),
            'created_by'    => (int) $userId,
            'payload'       => $payload,
        );
        if ($idem !== null) { $e['idempotency_key'] = $idem; }
        try {
            return EventPublisher::publishFact($db, $e);
        } catch (\Throwable $ex) {
            // «الفشلُ يملأ طابورَ إعادةِ المحاولة» (§7-3) — ولا يُسقط الفعلَ المجاليَّ
            // الذي وقع فعلًا. فالحقيقةُ المجاليةُ مكتوبةٌ والحدثُ يُعاد نشرُه.
            error_log('RiskEvents::fire ' . $eventName . ' #' . $entityId . ': ' . $ex->getMessage());
            return null;
        }
    }

    /** عددُ الأحداثِ المعرَّفةِ — يقرأه الحزامُ ليطابقَ §10-1 */
    public static function count()
    {
        return count(self::MAP);
    }
}
