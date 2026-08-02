<?php
/**
 * app/Services/Capacity/CapacityEvents.php — أحداثُ مجال القدرات الستة (CAP-29)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §14: «الأحداثُ الستةُ أحداثُ مجال القدرات — ولا يُولَّد منها حدثٌ
 * ماليٌّ إلا حين يوجد أثرٌ ماليٌّ بقاعدة العقد. فـ①→④ تُنتج آثارًا مالية،
 * و⑤ و⓺ تشغيليٌّ ونطاقيٌّ حتى ينصَّ العقدُ على خلاف ذلك».
 *
 * التسجيلُ في FES-01 (DEC-CAP-D): المفاتيحُ هنا قاموسُ المجال المعتمد —
 * تُنشر بمصدر `capacity` (عقدُ الناشر §9) وتفصيلُها في
 * docs/specs/SPEC_CAP_W5_OUTBOX_EVENTS_ar.md؛ **ولا خريطةَ أثرٍ ماليٍّ لها في
 * fin_effect_map** (DEC-CAP-B: المروحةُ لا تُمسّ) — الماليُّ عبر محركات
 * ENT-01/02/03 المؤجَّلة، وبوابتُه financialEffectAllowed أدناه.
 */

namespace App\Services\Capacity;

class CapacityEvents
{
    // §14 — الستةُ بمفاتيح عقد الناشر (domain.entity_action)
    const CAPACITY_CONSUMED       = 'capacity.consumed';             // ① استهلاكُ التزام عميل
    const SUPPLIER_SHARE_CONSUMED = 'capacity.share_consumed';       // ② احتسابُ حصةِ مورد
    const CONSUMPTION_REVERSED    = 'capacity.consumption_reversed'; // ③ عكسٌ بمرجع السطر
    const COVERAGE_RECOGNIZED     = 'capacity.coverage_recognized';  // ④ تغطيةٌ استثنائية
    const STANDBY_ACTIVATED       = 'capacity.standby_activated';    // ⑤ تشغيليٌّ لا مالي
    const GAP_OPENED              = 'capacity.gap_opened';           // ⑥ نطاقيٌّ لا مالي

    /** ما يُنتج أثرًا ماليًّا بذاته (①→④) — و⑤⑥ بقاعدة عقدٍ صريحةٍ حصرًا. */
    const FINANCIAL_BY_DEFAULT = array(
        self::CAPACITY_CONSUMED, self::SUPPLIER_SHARE_CONSUMED,
        self::CONSUMPTION_REVERSED, self::COVERAGE_RECOGNIZED,
    );

    const ALL = array(
        self::CAPACITY_CONSUMED, self::SUPPLIER_SHARE_CONSUMED, self::CONSUMPTION_REVERSED,
        self::COVERAGE_RECOGNIZED, self::STANDBY_ACTIVATED, self::GAP_OPENED,
    );

    /**
     * بوابةُ الأثر المالي (§14-◆): لا حدثَ ماليًّا من StandbyActivated أو
     * CoverageGapOpened **إلا بقاعدة عقدٍ صريحة**.
     * @param string $eventKey
     * @param array  $contractRow صفُّ الالتزام أو بندُ المورد بحقول المقابل/الجزاء
     * @return array{allowed:bool,reason:string}
     */
    public static function financialEffectAllowed($eventKey, array $contractRow = array())
    {
        if (in_array((string) $eventKey, self::FINANCIAL_BY_DEFAULT, true)) {
            return array('allowed' => true, 'reason' => 'حدثٌ ماليُّ الأثر بطبيعته (§14-①→④)');
        }
        if ((string) $eventKey === self::STANDBY_ACTIVATED) {
            $comp = isset($contractRow['standby_compensation_type']) ? (string) $contractRow['standby_compensation_type'] : '';
            if ($comp !== '' && $comp !== 'none') {
                return array('allowed' => true, 'reason' => 'العقدُ ينصّ على مقابلِ جاهزية: ' . $comp);
            }
            return array('allowed' => false,
                'reason' => 'StandbyActivated تشغيليٌّ — لا أثرَ ماليًّا بلا نصِّ مقابلٍ في العقد (DEC-CAP-A)');
        }
        if ((string) $eventKey === self::GAP_OPENED) {
            $penalty = isset($contractRow['shortfall_rule']) ? (string) $contractRow['shortfall_rule'] : '';
            if ($penalty === 'penalty') {
                return array('allowed' => true, 'reason' => 'قاعدةُ العقد تولّد الجزاء (shortfall_rule=penalty)');
            }
            return array('allowed' => false,
                'reason' => 'CoverageGapOpened نطاقيٌّ — الجزاءُ يُولَّد من قاعدة العقد لا من الحدث نفسِه');
        }
        return array('allowed' => false, 'reason' => 'حدثٌ خارج قاموس مجال القدرات الستة');
    }
}
