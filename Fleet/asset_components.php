<?php
/**
 * fleet/asset_components.php — المكونات والملحقات (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مكوّنٌ واحدٌ على أصلٍ واحد
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: fleet_equipment_component
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «المكونات والملحقات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/asset_components.php',
    'screen'     => 'flt_components',
    'table'      => 'fleet_equipment_component',
    'title'      => 'المكونات والملحقات',
    'icon'       => 'fa fa-puzzle-piece',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · المكونات والملحقات',
    'intro'      => 'مكوناتُ الأصلِ وملحقاتُه بعمرِها المتوقَّعِ ونسبةِ استهلاكِها وقرارِ رسملتِها',
    'rule'       => 'المكوّنُ المرسمَلُ يحتاج مرجعَ قرارٍ — والفكُّ بسببِه ونقلُه إلى أصلٍ مسمًّى',
    'empty_hint' => 'لا مكوّنَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_components.register',
            'label' => 'تسجيلُ المكونات والملحقات',
            'rule'  => 'المكوّنُ المرسمَلُ يحتاج مرجعَ قرارٍ — والفكُّ بسببِه ونقلُه إلى أصلٍ مسمًّى',
            'fields' => array(
                'equipment_id' => 'كود الأصل الرئيسي',
                'component_type' => 'نوع المكوّن ▼',
                'description' => 'الوصف',
                'manufacturer' => 'الشركة المصنعة',
                'serial_no' => 'الرقم التسلسلي للمكوّن',
                'install_date' => 'تاريخ التركيب',
                'meter_at_install' => 'العداد عند التركيب',
                'expected_life_hours' => 'العمر المتوقع (ساعة)',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('equipment_id', 'component_type', 'description', 'manufacturer', 'serial_no', 'install_date', 'meter_at_install', 'expected_life_hours');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('fleet_equipment_component', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'CMP-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('fleet_equipment_component',
                            array('component_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في fleet_equipment_component');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
