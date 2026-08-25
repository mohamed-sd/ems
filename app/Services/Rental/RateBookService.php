<?php
/**
 * app/Services/Rental/RateBookService.php — دفترُ الأسعار (RENTAL-CORE ②)
 * ═══════════════════════════════════════════════════════════════════════════
 * «التأجيرُ يبيع الزمن» — فالسعرُ دالّةُ مدةٍ لا رقمٌ واحد. هذه الخدمةُ تُجيب:
 * ما سعرُ هذه الفئة لهذا النموذج لهذه المدة لهذا العميل في هذا التاريخ؟
 *
 * ترتيبُ الترجيح (الأخصُّ يغلب — عرفُ دفاتر الأسعار عالميًّا):
 *   ① دفترُ العميل نفسِه يغلب الدفترَ العام.
 *   ② الدفترُ الأحدثُ سريانًا يغلب الأقدم.
 *   ③ داخل الدفتر: الشريحةُ التي تحتوي المدةَ فعلًا.
 *
 * الشرائحُ مبنيةٌ على مدد هذا النظام الحقيقية لا على عرف سوق المياومة:
 * متوسطُ التشغيل 74 يومًا ومتوسطُ العقد 163 — فالشرائحُ ممتدةٌ لا يومية.
 *
 * والحدُّ الأدنى للمدة (`min_hire_days`) يُطبَّق على **الكمية المفوترة** لا على
 * منع الحجز: مَن استأجر يومين وحدُّه خمسةٌ يدفع خمسة — وهذا عرفُ التأجير.
 */

namespace App\Services\Rental;

require_once __DIR__ . '/../../../includes/catch_log.php';

class RateBookService
{
    /** شرائحُ المدة المقترحة (بالأيام) — مشتقةٌ من توزيع مددهم الفعلي. */
    const DEFAULT_TIERS = array(
        array(1, 7),      // ≤ أسبوع — نادرٌ عندهم (27 من 478)
        array(8, 30),     // شهرٌ فأقل
        array(31, 90),    // ربعٌ — الأكثفُ (262 من 478 بين شهرٍ وستة)
        array(91, 180),
        array(181, null), // ما فوق — 92 عملية
    );

    const WORK_MODELS = array(
        'hour' => 'ساعة', 'day' => 'يوم', 'shift' => 'وردية', 'month' => 'شهر',
        'ton' => 'طن', 'meter' => 'متر', 'trip' => 'نقلة', 'cbm' => 'متر مكعب',
    );

    /**
     * أفضلُ سعرٍ لفئةٍ ونموذجِ عملٍ ومدة.
     *
     * @return array|null  ['unit_price','tier_from_days','tier_to_days','min_hire_days',
     *                      'billable_days','line_total','book_code','book_name','currency',
     *                      'mobilization_fee','operator_included','fuel_included','line_id']
     */
    public static function bestRate($gate, $typeId, $workModel, $days, $clientId = 0, $onDate = null)
    {
        $typeId = (int) $typeId;
        $days   = (int) $days;
        if ($typeId <= 0 || $days <= 0 || !isset(self::WORK_MODELS[$workModel])) { return null; }
        $onDate = ($onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) ? $onDate : date('Y-m-d');
        $clientId = (int) $clientId;

        $rows = array();
        try {
            // الدفاترُ المعتمدةُ السارية: دفترُ العميل أو العام. الترجيحُ في ORDER BY:
            // دفترُ العميل أولًا (b.client_id = ? DESC)، ثم الأحدثُ سريانًا.
            // الجدولان في النطاق معًا لا إثراءً: البوابةُ تُلزم أن يُربط جدولُ
            // الإثراء بـLEFT JOIN حصرًا، وهنا الربطُ داخليٌّ مقصود (بندٌ بلا
            // دفترٍ لا معنى له) — فيُعلَنان نطاقًا ويُحقن العزلُ على كليهما.
            $rows = $gate->scopedQuery(
                array('scope' => array('l' => 'rate_book_lines', 'b' => 'rate_books')),
                "SELECT l.id AS line_id, l.unit_price, l.tier_from_days, l.tier_to_days,
                        l.min_hire_days, l.min_hours_per_day, l.mobilization_fee,
                        l.operator_included, l.fuel_included,
                        b.id AS book_id, b.book_code, b.name AS book_name, b.currency, b.client_id
                   FROM rate_book_lines l
                   JOIN rate_books b ON b.id = l.book_id
                  WHERE {TENANT_SCOPE}
                    AND COALESCE(l.is_deleted,0) = 0
                    AND COALESCE(b.is_deleted,0) = 0
                    AND b.state = 'معتمد'
                    AND b.valid_from <= ?
                    AND (b.valid_to IS NULL OR b.valid_to >= ?)
                    AND (b.client_id IS NULL OR b.client_id = ?)
                    AND l.equipment_type_id = ?
                    AND l.work_model = ?
                    AND l.tier_from_days <= ?
                    AND (l.tier_to_days IS NULL OR l.tier_to_days >= ?)
                  ORDER BY (b.client_id IS NOT NULL) DESC, b.valid_from DESC, l.tier_from_days DESC
                  LIMIT 1",
                array($onDate, $onDate, $clientId, $typeId, $workModel, $days, $days)
            );
        } catch (\Throwable $t) { error_log('RateBookService bestRate: ' . $t->getMessage()); return null; }

        if (!count($rows)) { return null; }
        $r = $rows[0];

        // الحدُّ الأدنى للمدة يرفع الكميةَ المفوترة لا يمنع الحجز
        $minDays = max(1, (int) $r['min_hire_days']);
        $billableDays = max($days, $minDays);

        $r['billable_days'] = $billableDays;
        $r['min_applied']   = ($billableDays > $days);
        $r['line_total']    = round(((float) $r['unit_price']) * $billableDays, 2);
        return $r;
    }

    /**
     * جدولُ الأسعار الكامل لفئةٍ — كلُّ الشرائح، ليرى المندوبُ «الأطولُ أرخص».
     */
    public static function tiersFor($gate, $typeId, $workModel, $clientId = 0, $onDate = null)
    {
        $typeId = (int) $typeId;
        if ($typeId <= 0 || !isset(self::WORK_MODELS[$workModel])) { return array(); }
        $onDate = ($onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) ? $onDate : date('Y-m-d');
        try {
            return $gate->scopedQuery(
                array('scope' => array('l' => 'rate_book_lines', 'b' => 'rate_books')),
                "SELECT l.tier_from_days, l.tier_to_days, l.unit_price, l.min_hire_days,
                        b.book_code, b.currency, b.client_id
                   FROM rate_book_lines l
                   JOIN rate_books b ON b.id = l.book_id
                  WHERE {TENANT_SCOPE}
                    AND COALESCE(l.is_deleted,0) = 0 AND COALESCE(b.is_deleted,0) = 0
                    AND b.state = 'معتمد' AND b.valid_from <= ?
                    AND (b.valid_to IS NULL OR b.valid_to >= ?)
                    AND (b.client_id IS NULL OR b.client_id = ?)
                    AND l.equipment_type_id = ? AND l.work_model = ?
                  ORDER BY (b.client_id IS NOT NULL) DESC, l.tier_from_days",
                array($onDate, $onDate, (int) $clientId, $typeId, $workModel)
            );
        } catch (\Throwable $t) { error_log('RateBookService tiers: ' . $t->getMessage()); return array(); }
    }

    /** وسمُ الشريحة للعرض. */
    public static function tierLabel($from, $to)
    {
        $from = (int) $from;
        if ($to === null || $to === '') { return $from . ' يوما فأكثر'; }
        return $from . ' – ' . (int) $to . ' يوما';
    }

    /** رقمُ دفترٍ تالٍ. */
    public static function nextBookCode($gate)
    {
        $prefix = 'RB-' . date('Y') . '-';
        $n = 0;
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('b' => 'rate_books')),
                "SELECT b.book_code FROM rate_books b WHERE {TENANT_SCOPE} AND b.book_code LIKE ?
                  ORDER BY b.id DESC LIMIT 1",
                array($prefix . '%')
            );
            if (count($rows)) { $n = (int) substr((string) $rows[0]['book_code'], strlen($prefix)); }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'RateBookService code'); error_log('RateBookService code: ' . $t->getMessage()); }
        return $prefix . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
    }
}
