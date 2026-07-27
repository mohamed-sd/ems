<?php
/**
 * شرطُ «ولها سائقٌ يعمل عليها» — قائمةُ الآليات (UX-03 · تشخيصُ المالك 2026-07-27)
 * ────────────────────────────────────────────────────────────────────────────
 * المقصدُ محفوظٌ كما وُضع: لا تظهر في قائمة الآليات آليةٌ لا مشغّلَ مرتبطًا بها،
 * فلا يُحفظ يومُ عملٍ بساعاتِ مشغّلٍ لا يُعرف صاحبُها.
 *
 * وأمّا **الأداة** فقد تغيّرت: الصيغةُ الأولى وضعت الشرط
 *   `AND EXISTS (SELECT 1 FROM equipment_drivers ed JOIN employees dd ...)`
 * داخل استعلامِ scopedQuery — وبوابةُ المستأجر ترفض ذلك رفضًا قاطعًا:
 * «كل جدولٍ مستأجرٍ مسجَّلٍ يظهر مصدرَ FROM/JOIN يجب إعلانُه» (TenantDb §scopedQuery)،
 * وإعلانُه مستحيلٌ هنا لأن البوابة تحقن `alias.company_id` في **WHERE العلوية**
 * حيث لا وجودَ لاسمَي ed/dd أصلًا. فكانت النتيجةُ استثناءً يُبتلع في catch
 * وقائمةَ آلياتٍ **فارغةً تمامًا** في الشاشات الثلاث وفي get_operations.
 * (نفسُ الگوتشا موثّقةٌ في عدّاد اليوم: «EXISTS على مستأجرٍ يعطّل حقن النطاق».)
 *
 * فالشرطُ يُبنى الآن على خطوتين: استعلامٌ معزولٌ مستقلٌّ يجمع معدّاتِ الشركة
 * التي لها سائقٌ نشط، ثم `IN (…)` على القائمة — بنفس منطق get_drivers.php
 * حرفيًّا (الجدولُ والحالتان وأنواعُ الموظفين المشغّلة وترشيحُ الوردية)، فما
 * يظهر في قائمة الآليات له بالضرورة خيارٌ في قائمة السائقين.
 */

if (!function_exists('ts_equipment_ids_with_driver')) {
    /**
     * معدّاتُ الشركة التي لها سائقٌ نشطٌ (مع ترشيح الوردية إن طُلب).
     *
     * @param object $gate       بوابةُ المستأجر (أو forAllTenants للسوبر)
     * @param object $conn       اتصالُ mysqli — لأنواع الموظفين المشغّلة
     * @param string $shift_type 'D' أو 'N' أو '' (بلا ترشيح)
     * @return array|null        قائمةُ equipment_id، أو null إن تعذّر الاستعلام
     */
    function ts_equipment_ids_with_driver($gate, $conn, $shift_type = '')
    {
        $filter = '';
        $params = array();
        if ($shift_type === 'D' || $shift_type === 'N') {
            $filter .= " AND (ed.shift_type = 'B' OR ed.shift_type = ?)";
            $params[] = $shift_type;
        }
        // قصرُ السائقين على أنواع الموظفين المشغّلة — كما في get_drivers.php
        $types_sql = function_exists('ems_operation_types_in_sql')
            ? ems_operation_types_in_sql($conn, 'd') : '';

        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('ed' => 'equipment_drivers', 'd' => 'employees')),
                "SELECT DISTINCT ed.equipment_id AS eq
                   FROM equipment_drivers ed
                   JOIN employees d ON d.id = ed.employee_id
                  WHERE ed.status = 1 AND d.status = 1" . $filter . $types_sql . " AND {TENANT_SCOPE}",
                $params);
        } catch (\Throwable $t) {
            error_log('ts_equipment_ids_with_driver: ' . $t->getMessage());
            return null;
        }

        $ids = array();
        foreach ($rows as $r) {
            $id = intval($r['eq']);
            if ($id > 0) { $ids[] = $id; }
        }
        return $ids;
    }
}

if (!function_exists('ts_has_driver_sql')) {
    /**
     * الشرطُ جاهزًا للإلصاق بجملة WHERE — أعدادٌ صحيحةٌ حصرًا فلا حقنَ ولا معاملات.
     * تعذُّرُ الاستعلام أو خلوُّ القائمة ⇒ إغلاقٌ صريح (1=0): لا آليةَ بلا مشغّل.
     *
     * @param string $col العمودُ المقارَن (مثلًا 'o.equipment')
     */
    function ts_has_driver_sql($gate, $conn, $shift_type = '', $col = 'o.equipment')
    {
        $ids = ts_equipment_ids_with_driver($gate, $conn, $shift_type);
        if ($ids === null || empty($ids)) {
            return ' AND 1=0 ';
        }
        return ' AND ' . $col . ' IN (' . implode(',', $ids) . ') ';
    }
}
