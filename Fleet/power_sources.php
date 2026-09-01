<?php
/**
 * fleet/power_sources.php — مصادر القدرة (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مصدرُ قدرةٍ واحدٌ — سطرٌ لكلِّ مصدر
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_power_source
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «مصادر القدرة»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/power_sources.php',
    'screen'     => 'flt_power_sources',
    'table'      => 'flt_power_source',
    'title'      => 'مصادر القدرة',
    'icon'       => 'fa fa-bolt',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · مصادر القدرة',
    'intro'      => 'مصادرُ القدرةِ وسياستُها: ما يُرسمَل وما يُهلَك وما يدخل سجلَّ الأسطول',
    'rule'       => 'مالكُ الحقيقةِ الماليةِ والتشغيليةِ مسمًّى لكلِّ مصدرٍ — ولا مصدرَ بلا مستندٍ حاكم',
    'empty_hint' => 'لا مصدرَ قدرةٍ مسجَّلٌ بعدُ — سجّلِ الأولَ من نموذجِ الأفعالِ أعلاه',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_power_sources.register',
            'label' => 'تسجيلُ مصادر القدرة',
            'rule'  => 'مالكُ الحقيقةِ الماليةِ والتشغيليةِ مسمًّى لكلِّ مصدرٍ — ولا مصدرَ بلا مستندٍ حاكم',
            'fields' => array(
                'source_name' => 'اسم المصدر',
                'definition' => 'التعريف',
                'is_capitalized' => 'يُرسمل؟ ▼',
                'has_depreciation' => 'يُحتسب له إهلاك؟ ▼',
                'in_fleet_register' => 'يدخل سجل الأسطول؟ ▼',
                'governing_doc' => 'المستند الحاكم',
                'accounting_ref' => 'المرجع المحاسبي',
                'financial_owner' => 'مالك الحقيقة المالية',
                'operational_owner' => 'مالك الحقيقة التشغيلية',
                'count_uom' => 'وحدة العدد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('source_name', 'definition', 'is_capitalized', 'has_depreciation', 'in_fleet_register', 'governing_doc', 'accounting_ref', 'financial_owner', 'operational_owner', 'count_uom');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_power_source', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'PWS-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('flt_power_source',
                            array('source_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_power_source');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
