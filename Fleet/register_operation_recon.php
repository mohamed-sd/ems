<?php
/**
 * fleet/register_operation_recon.php — مصالحة السجل بالتشغيل (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: أصلٌ واحدٌ — ساعاتُ سجلِّه مقابلَ ساعاتِ تشغيلِه
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_register_operation_recon
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مصالحة السجل بالتشغيل»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/register_operation_recon.php',
    'screen'     => 'flt_reg_op_recon',
    'table'      => 'flt_register_operation_recon',
    'title'      => 'مصالحة السجل بالتشغيل',
    'icon'       => 'fa fa-scale-balanced',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصالحة السجل بالتشغيل',
    'intro'      => 'فرقُ الساعاتِ بين سجلِّ الأسطولِ والتايم شيت وتفسيرُه أصلًا أصلًا',
    'rule'       => 'الفرقُ يُفسَّر ولا يُسوّى بالكتابةِ على أحدِ المصدرَين (§17 · §18)',
    'empty_hint' => 'لا مصالحةَ سجلٍّ بتشغيلٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_reg_op_recon.register',
            'label' => 'تسجيلُ مصالحة السجل بالتشغيل',
            'rule'  => 'الفرقُ يُفسَّر ولا يُسوّى بالكتابةِ على أحدِ المصدرَين (§17 · §18)',
            'fields' => array(
                'asset_code' => 'كود الأصل',
                'unified_desc' => 'الوصف الموحد',
                'class_code' => 'رمز التصنيف',
                'ownership_type' => 'نوع الملكية',
                'operating_state' => 'الحالة التشغيلية',
                'register_hours' => 'الساعات المتراكمة بالسجل',
                'intersection_state' => 'حالة التقاطع',
                'timesheet_hours' => 'ساعات التايم شيت',
                'timesheet_supplier' => 'المورد بالتايم شيت',
                'hours_diff' => 'فرق الساعات',
                'diff_explanation' => 'تفسير الفرق',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('asset_code', 'unified_desc', 'class_code', 'ownership_type', 'operating_state', 'register_hours', 'intersection_state', 'timesheet_hours', 'timesheet_supplier', 'hours_diff', 'diff_explanation');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_register_operation_recon', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_register_operation_recon');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
