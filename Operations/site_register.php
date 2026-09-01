<?php
/**
 * operations/site_register.php — سجل المواقع وحدودها (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: موقعٌ واحدٌ بحدودِه
 * المالك: إدارة الموقع · مصدرُ الحقيقة: sites
 * الأصل: ورقةُ «إدارة الموقع» — السطح «سجل المواقع وحدودها»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_register.php',
    'screen'     => 'site_register',
    'table'      => 'sites',
    'title'      => 'سجل المواقع وحدودها',
    'icon'       => 'fa fa-map-location-dot',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · سجل المواقع وحدودها',
    'intro'      => 'المواقعُ بمشاريعِها وحدودِها وجبهاتِ عملِها وطاقتِها الاستيعابية',
    'rule'       => 'الموقعُ مصدرُ حقيقةٍ واحدٌ — والمشروعُ والعقدُ يُقرآن ولا يُحرَّران هنا',
    'empty_hint' => 'لا موقعَ مسجَّلٌ بعد',
    'where'       => 'is_deleted = 0',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_register.register',
            'label' => 'تسجيلُ سجل المواقع وحدودها',
            'rule'  => 'الموقعُ مصدرُ حقيقةٍ واحدٌ — والمشروعُ والعقدُ يُقرآن ولا يُحرَّران هنا',
            'fields' => array(
                'name' => 'اسم الموقع',
                'project_id' => 'رقم المشروع ◄',
                'region' => 'الولاية/المنطقة',
                'coordinates' => 'الإحداثيات',
                'work_zones' => 'مناطق العمل/الجبهات',
                'responsible_employee_id' => 'مدير الموقع ◄',
                'shifts_count' => 'عدد الورديات ▼',
                'equipment_capacity' => 'الطاقة الاستيعابية للمعدات',
                'status' => 'حالة الموقع ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('name', 'project_id', 'region', 'coordinates', 'work_zones', 'responsible_employee_id', 'shifts_count', 'equipment_capacity', 'status');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sites', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SITE-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sites',
                            array('site_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sites');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
