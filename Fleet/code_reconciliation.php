<?php
/**
 * fleet/code_reconciliation.php — مصالحة مطابقة الأكواد (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: كودٌ موحَّدٌ واحدٌ مقابلَ أكوادِه القديمة
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_code_reconciliation
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مصالحة مطابقة الأكواد»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/code_reconciliation.php',
    'screen'     => 'flt_code_recon',
    'table'      => 'flt_code_reconciliation',
    'title'      => 'مصالحة مطابقة الأكواد',
    'icon'       => 'fa fa-right-left',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصالحة مطابقة الأكواد',
    'intro'      => 'جسرُ الكودِ الموحَّدِ الجديدِ بالأكوادِ القديمةِ ونظامِها المصدرِ وحالةِ مطابقتِها',
    'rule'       => 'كلُّ مطابقةٍ بمن طابَقها وتاريخِها — ولا مطابقةَ بلا شاهد',
    'empty_hint' => 'لا مطابقةَ أكوادٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_code_recon.register',
            'label' => 'تسجيلُ مصالحة مطابقة الأكواد',
            'rule'  => 'كلُّ مطابقةٍ بمن طابَقها وتاريخِها — ولا مطابقةَ بلا شاهد',
            'fields' => array(
                'unified_code' => 'الكود الموحد الجديد',
                'old_code_main' => 'الكود القديم (رئيسي)',
                'old_code_alt' => 'الكود القديم (ثانوي)',
                'asset_name' => 'اسم الأصل',
                'source_system' => 'النظام المصدر',
                'match_state' => 'حالة المطابقة',
                'match_notes' => 'ملاحظات المطابقة',
                'matched_by_name' => 'طُوبِق بواسطة',
                'match_date' => 'تاريخ المطابقة',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('unified_code', 'old_code_main', 'old_code_alt', 'asset_name', 'source_system', 'match_state', 'match_notes', 'matched_by_name', 'match_date');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_code_reconciliation', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_code_reconciliation');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
