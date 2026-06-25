<?php
/**
 * اشتقاق تواريخ بداية/نهاية تشغيل السائق على المعدة (equipment_drivers)
 * من عقد السائق الساري (drivercontracts).
 *
 * القاعدة:
 *  - يوجد عقد ساري (status=1 وله actual_start حقيقي):
 *        start = actual_start
 *        end   = actual_end (أو "النهاية المفتوحة" إن كان فارغاً)
 *  - لا يوجد عقد ساري:
 *        start = تاريخ اليوم (لحظة الإسناد)
 *        end   = "النهاية المفتوحة" (تبقى مفتوحة حتى يُنهى العمل فعلياً)
 *
 * "النهاية المفتوحة" تُمثَّل بالسنتينل '2099-12-31' حفاظاً على الاتساق مع باقي
 * النظام (API / تطبيق الموبايل / شاشات الحركة) الذي يعتمد هذا الاصطلاح ويعرضه «مستمر».
 */

if (!defined('EMS_OPEN_END_DATE')) {
    define('EMS_OPEN_END_DATE', '2099-12-31');
}

if (!function_exists('ems_is_open_end_date')) {
    /** هل قيمة النهاية تعني "مفتوحة" (NULL/فارغ/0000-00-00/السنتينل)؟ */
    function ems_is_open_end_date($end)
    {
        if ($end === null) {
            return true;
        }
        $end = trim((string) $end);
        return $end === '' || $end === '0000-00-00' || $end === EMS_OPEN_END_DATE;
    }
}

if (!function_exists('ems_format_open_end')) {
    /** للعرض: يعيد «مستمر» للنهاية المفتوحة، وإلا التاريخ بصيغة Y-m-d. */
    function ems_format_open_end($end, $open_label = 'مستمر')
    {
        if (ems_is_open_end_date($end)) {
            return $open_label;
        }
        $ts = strtotime((string) $end);
        return $ts ? date('Y-m-d', $ts) : (string) $end;
    }
}

if (!function_exists('ems_driver_active_contract')) {
    /**
     * أحدث عقد ساري للسائق، أو null.
     * عند تعدد العقود السارية: يُعتمد الأحدث (actual_start الأكبر ثم id الأكبر).
     */
    function ems_driver_active_contract($conn, $employee_id, $company_id = 0, $is_super_admin = false)
    {
        $employee_id = intval($employee_id);
        if ($employee_id <= 0) {
            return null;
        }

        $scope = '';
        if (!$is_super_admin && intval($company_id) > 0
            && function_exists('db_table_has_column')
            && db_table_has_column($conn, 'drivercontracts', 'company_id')) {
            $scope = ' AND company_id = ' . intval($company_id);
        }

        // ملاحظة: actual_start عمود DATE؛ في STRICT mode تُسبّب المقارنة
        // actual_start <> '' خطأ "Incorrect DATE value: ''" فيفشل الاستعلام كله.
        // لذا نكتفي بـ IS NOT NULL و<> '0000-00-00' (صالحان لعمود DATE).
        $sql = "SELECT actual_start, actual_end
                FROM drivercontracts
                WHERE employee_id = $employee_id
                  AND status = 1
                  AND actual_start IS NOT NULL
                  AND actual_start <> '0000-00-00'
                  $scope
                ORDER BY actual_start DESC, id DESC
                LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            return mysqli_fetch_assoc($res);
        }
        return null;
    }
}

if (!function_exists('ems_resolve_equipment_driver_dates')) {
    /**
     * يطبّق القاعدة ويعيد ['start' => 'Y-m-d', 'end' => 'Y-m-d', 'from_contract' => bool].
     * end يكون السنتينل '2099-12-31' عند النهاية المفتوحة.
     */
    function ems_resolve_equipment_driver_dates($conn, $employee_id, $company_id = 0, $is_super_admin = false)
    {
        $contract = ems_driver_active_contract($conn, $employee_id, $company_id, $is_super_admin);

        if ($contract && !empty($contract['actual_start']) && $contract['actual_start'] !== '0000-00-00') {
            $start = date('Y-m-d', strtotime($contract['actual_start']));
            $end = (!empty($contract['actual_end']) && $contract['actual_end'] !== '0000-00-00')
                ? date('Y-m-d', strtotime($contract['actual_end']))
                : EMS_OPEN_END_DATE;
            return ['start' => $start, 'end' => $end, 'from_contract' => true];
        }

        return ['start' => date('Y-m-d'), 'end' => EMS_OPEN_END_DATE, 'from_contract' => false];
    }
}
