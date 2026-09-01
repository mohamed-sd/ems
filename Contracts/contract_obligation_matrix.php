<?php
/**
 * contracts/contract_obligation_matrix.php — مصفوفة الالتزامات (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عقدٌ واحدٌ — مصفوفةُ التزاماتِه سطرٌ واحد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: contracts
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «مصفوفة الالتزامات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/contract_obligation_matrix.php',
    'screen'     => 'sal_obligation_matrix',
    'table'      => 'contracts',
    'title'      => 'مصفوفة الالتزامات',
    'icon'       => 'fa fa-table-cells-large',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصفوفة الالتزامات',
    'intro'      => 'من يتحمّل ماذا في عقدِ العميل، وما الذي سكت عنه العقدُ صراحةً',
    'rule'       => 'حبّةُ المصفوفةِ **العقدُ نفسُه** فلا جدولَ ثانيَ له (§7) — والمسكوتُ عنه يُكتب مسكوتًا عنه لا فارغًا',
    'empty_hint' => 'لا عقدَ مسجَّلٌ بعد',
    'where'       => 'is_deleted = 0',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_obligation_matrix.register',
            'label' => 'تسجيلُ مصفوفة الالتزامات',
            'rule'  => 'حبّةُ المصفوفةِ **العقدُ نفسُه** فلا جدولَ ثانيَ له (§7) — والمسكوتُ عنه يُكتب مسكوتًا عنه لا فارغًا',
            'fields' => array(
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'business_model' => 'نموذج العمل',
                'obl_fuel' => 'الوقود',
                'obl_oils' => 'الزيوت',
                'obl_maintenance' => 'الصيانة',
                'obl_spare_parts' => 'قطع الغيار',
                'obl_operators' => 'المشغلون (السائقون)',
                'obl_housing' => 'السكن والإعاشة',
                'obl_mobilization' => 'ترحيل الذهاب',
                'obl_demobilization' => 'ترحيل العودة',
                'obl_insurance' => 'التأمين',
                'obl_damage' => 'الضرر',
                'obl_waiting' => 'الانتظار',
                'obl_breakdown' => 'العطل',
                'obl_violations' => 'المخالفات',
                'obl_min_hours' => 'الحد الأدنى للساعات',
                'obl_operating_guarantee' => 'ضمان التشغيل',
                'obl_site_schedule' => 'جدول عمل الموقع',
                'obl_violation_deduction' => 'خصم ساعات المخالفة',
                'obl_unpaid_stoppage' => 'التوقف غير المدفوع',
                'obl_termination' => 'الإنهاء',
                'obl_renewal' => 'التجديد',
                'obl_governing_law' => 'القانون الحاكم',
                'obl_specific_bearing' => 'بنود تحمُّل محددة',
                'obl_specific_terms' => 'شروط تعاقدية محددة',
                'obl_silent_items' => 'بنود مسكوت عنها',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('client_no', 'client_name', 'business_model', 'obl_fuel', 'obl_oils', 'obl_maintenance', 'obl_spare_parts', 'obl_operators', 'obl_housing', 'obl_mobilization', 'obl_demobilization', 'obl_insurance', 'obl_damage', 'obl_waiting', 'obl_breakdown', 'obl_violations', 'obl_min_hours', 'obl_operating_guarantee', 'obl_site_schedule', 'obl_violation_deduction', 'obl_unpaid_stoppage', 'obl_termination', 'obl_renewal', 'obl_governing_law', 'obl_specific_bearing', 'obl_specific_terms', 'obl_silent_items');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('contracts', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في contracts');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
