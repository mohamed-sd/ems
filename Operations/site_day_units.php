<?php
/**
 * operations/site_day_units.php — تسجيل وحدات اليوم (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ وحدةٍ منجزةٍ في يومِ موقعٍ
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_day_unit
 * الأصل: ورقةُ «إدارة الموقع» — السطح «تسجيل وحدات اليوم»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_day_units.php',
    'screen'     => 'site_day_units',
    'table'      => 'site_day_unit',
    'title'      => 'تسجيل وحدات اليوم',
    'icon'       => 'fa fa-list-check',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · تسجيل وحدات اليوم',
    'intro'      => 'الوحداتُ المقيسةُ في يومِ الموقعِ بمعدّتِها ومشغّلِها ووسيلةِ قياسِها',
    'rule'       => 'الكميةُ تُقاس بوسيلةٍ مسمّاةٍ ومرجعٍ ميدانيّ — ولا وحدةَ بلا مصدرِ قياس',
    'empty_hint' => 'لا وحدةَ مسجَّلةٌ بعدُ لهذا اليوم',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_day_units.register',
            'label' => 'تسجيلُ تسجيل وحدات اليوم',
            'rule'  => 'الكميةُ تُقاس بوسيلةٍ مسمّاةٍ ومرجعٍ ميدانيّ — ولا وحدةَ بلا مصدرِ قياس',
            'fields' => array(
                'day_ref' => 'معرّف يوم الموقع ◄',
                'equipment_ref' => 'كود المعدة ◄',
                'measured_qty' => 'الكمية المقيسة',
                'measure_method' => 'وسيلة القياس ▼',
                'field_ref' => 'المرجع الميداني',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('day_ref', 'equipment_ref', 'measured_qty', 'measure_method', 'field_ref');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_day_unit', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SDU-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_day_unit',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_day_unit');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
