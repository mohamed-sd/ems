<?php
/**
 * app/Services/Rental/AvailabilityService.php — توفُّرُ الأسطول (RENTAL-CORE ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * «التأجيرُ يؤجّر الأصلَ نفسه مرارًا» — فالسؤالُ الأول لأي مندوب: أهي متاحةٌ من
 * كذا إلى كذا؟ هذه الخدمةُ تجيبه من **مصدرين معًا**:
 *   ① التشغيلاتُ القائمة (`operations`) — المعدةُ مشغولةٌ فعلًا في نافذةٍ زمنية.
 *   ② الحجوزاتُ (`fleet_reservations`) — محجوزةٌ ولمّا يبدأ التشغيل بعد.
 *
 * قاعدةُ التقاطع (نصفُ المفتوح): نافذتان تتقاطعان إذا `a.start <= b.end` و
 * `b.start <= a.end`. نهايةٌ فارغةٌ تعني «مفتوح» فتُعامَل 2099-12-31.
 *
 * الحارسُ هنا لا في الشاشة: أيُّ مستدعٍ (شاشةٌ · استيرادٌ · واجهةٌ برمجية) يمرّ
 * بالفحص نفسِه — «لا حارسَ في الواجهة وحدَها» (عرفُ H-07).
 *
 * العزلُ عبر البوابة حصرًا: لا استعلامَ خامًا ولا company_id نصًّا.
 */

namespace App\Services\Rental;

class AvailabilityService
{
    /** نهايةٌ مفتوحة — تُعامَل أقصى تاريخ. */
    const OPEN_END = '2099-12-31';

    /** حالاتُ الحجز التي تحجز فعلًا (الملغى والمنتهي لا يحجزان). */
    const BLOCKING_STATES = array('مبدئي', 'مؤكَّد', 'محوَّل لعقد');

    /**
     * أمتاحةٌ هذه المعدةُ في النافذة؟ يُرجع مصفوفةَ التعارضات (فارغةٌ = متاحة).
     *
     * @param  object $gate   ems_tenant_db()
     * @param  int    $equipmentId
     * @param  string $from   Y-m-d
     * @param  string $to     Y-m-d
     * @param  int    $ignoreReservationId  حجزٌ يُستثنى (عند التعديل)
     * @return array{operations: array, reservations: array}
     */
    public static function conflictsFor($gate, $equipmentId, $from, $to, $ignoreReservationId = 0)
    {
        $out = array('operations' => array(), 'reservations' => array());
        $equipmentId = (int) $equipmentId;
        if ($equipmentId <= 0 || !self::validDate($from) || !self::validDate($to)) { return $out; }

        // ① تشغيلاتٌ متقاطعة — `end` الفارغة تعني تشغيلًا مفتوحًا
        try {
            $out['operations'] = $gate->scopedQuery(
                array('scope' => array('o' => 'operations'), 'enrich' => array('c' => 'contracts')),
                "SELECT o.id, o.start, o.end, o.contract_id, o.project_id, o.status
                   FROM operations o
                   LEFT JOIN contracts c ON c.id = o.contract_id
                  WHERE {TENANT_SCOPE}
                    AND o.equipment = ?
                    AND o.status = '1'
                    AND o.start IS NOT NULL
                    AND o.start <= ?
                    AND COALESCE(o.end, ?) >= ?
                  ORDER BY o.start",
                array($equipmentId, $to, self::OPEN_END, $from)
            );
        } catch (\Throwable $t) { error_log('AvailabilityService ops: ' . $t->getMessage()); }

        // ② حجوزاتٌ متقاطعة
        $ignore = (int) $ignoreReservationId;
        try {
            $states = "'" . implode("','", self::BLOCKING_STATES) . "'";
            $out['reservations'] = $gate->scopedQuery(
                array('scope' => array('r' => 'fleet_reservations')),
                "SELECT r.id, r.reservation_no, r.start_date, r.end_date, r.state, r.client_id, r.purpose
                   FROM fleet_reservations r
                  WHERE {TENANT_SCOPE}
                    AND COALESCE(r.is_deleted,0) = 0
                    AND r.equipment_id = ?
                    AND r.state IN ($states)
                    AND r.id <> ?
                    AND r.start_date <= ?
                    AND r.end_date >= ?
                  ORDER BY r.start_date",
                array($equipmentId, $ignore, $to, $from)
            );
        } catch (\Throwable $t) { error_log('AvailabilityService res: ' . $t->getMessage()); }

        return $out;
    }

    /** أمتاحةٌ تمامًا؟ (لا تشغيلَ ولا حجزَ متقاطع) */
    public static function isFree($gate, $equipmentId, $from, $to, $ignoreReservationId = 0)
    {
        $c = self::conflictsFor($gate, $equipmentId, $from, $to, $ignoreReservationId);
        return !count($c['operations']) && !count($c['reservations']);
    }

    /**
     * المعداتُ المتاحةُ في نافذةٍ زمنية — أساسُ «بماذا أستطيع أن أَعِد؟».
     * يُرجع صفوفَ المعدات المتاحة، ويُرشَّح اختياريًّا بفئةٍ واحدة.
     */
    public static function freeEquipment($gate, $from, $to, $typeId = 0, $limit = 500)
    {
        if (!self::validDate($from) || !self::validDate($to)) { return array(); }
        $rows = array();
        $params = array();
        $typeSql = '';
        if ((int) $typeId > 0) { $typeSql = ' AND e.type = ? '; $params[] = (int) $typeId; }

        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('e' => 'equipments'), 'enrich' => array('t' => 'equipments_types')),
                "SELECT e.id, e.code, e.name, e.type, e.availability_status,
                        t.type AS type_name
                   FROM equipments e
                   LEFT JOIN equipments_types t ON t.id = e.type
                  WHERE {TENANT_SCOPE} $typeSql
                  ORDER BY e.code
                  LIMIT " . (int) $limit,
                $params
            );
        } catch (\Throwable $t) { error_log('AvailabilityService free: ' . $t->getMessage()); return array(); }

        $free = array();
        foreach ($rows as $r) {
            if (self::isFree($gate, (int) $r['id'], $from, $to)) { $free[] = $r; }
        }
        return $free;
    }

    /**
     * عدُّ المتاح لكل فئة في نافذة — جوابُ «كم حفارًا أستطيع أن أَعِد به؟».
     * @return array [ ['type_id','type_name','total','free','busy'] ]
     */
    public static function capacityByType($gate, $from, $to)
    {
        if (!self::validDate($from) || !self::validDate($to)) { return array(); }
        $rows = array();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('e' => 'equipments'), 'enrich' => array('t' => 'equipments_types')),
                "SELECT e.id, e.type, t.type AS type_name
                   FROM equipments e
                   LEFT JOIN equipments_types t ON t.id = e.type
                  WHERE {TENANT_SCOPE}",
                array()
            );
        } catch (\Throwable $t) { error_log('AvailabilityService cap: ' . $t->getMessage()); return array(); }

        $agg = array();
        foreach ($rows as $r) {
            $tid = (int) $r['type'];
            $key = $tid > 0 ? $tid : 0;
            if (!isset($agg[$key])) {
                $agg[$key] = array(
                    'type_id' => $key,
                    'type_name' => ($r['type_name'] !== null && $r['type_name'] !== '') ? $r['type_name'] : 'غير مصنَّفة',
                    'total' => 0, 'free' => 0, 'busy' => 0,
                );
            }
            $agg[$key]['total']++;
            if (self::isFree($gate, (int) $r['id'], $from, $to)) { $agg[$key]['free']++; }
            else { $agg[$key]['busy']++; }
        }
        usort($agg, function ($a, $b) { return $b['total'] - $a['total']; });
        return $agg;
    }

    /** رقمُ حجزٍ تاليٍ — تسلسلٌ بسيطٌ داخل الشركة (RES-YYYY-NNNN). */
    public static function nextReservationNo($gate, $companyId)
    {
        $year = date('Y');
        $prefix = 'RES-' . $year . '-';
        $n = 0;
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('r' => 'fleet_reservations')),
                "SELECT r.reservation_no FROM fleet_reservations r
                  WHERE {TENANT_SCOPE} AND r.reservation_no LIKE ?
                  ORDER BY r.id DESC LIMIT 1",
                array($prefix . '%')
            );
            if (count($rows)) {
                $tail = substr((string) $rows[0]['reservation_no'], strlen($prefix));
                $n = (int) $tail;
            }
        } catch (\Throwable $t) { error_log('AvailabilityService no: ' . $t->getMessage()); }
        return $prefix . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function validDate($d)
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
    }
}
