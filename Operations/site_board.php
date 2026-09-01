<?php
/**
 * operations/site_board.php — لوحة الموقع (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مؤشرٌ واحدٌ لموقعٍ واحد
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_dashboard_kpi
 * الأصل: ورقةُ «إدارة الموقع» — السطح «لوحة الموقع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_board.php',
    'screen'     => 'site_board',
    'table'      => 'site_dashboard_kpi',
    'title'      => 'لوحة الموقع',
    'icon'       => 'fa fa-chart-simple',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · لوحة الموقع',
    'intro'      => 'مؤشراتُ الموقعِ بقيمِها وحالاتِها وآخرِ تحديثٍ لكلٍّ',
    'rule'       => 'اللوحةُ إسقاطٌ لا مصدرَ حقيقة — ومصدرُ كلِّ مؤشرٍ من كتلوجِ المؤشرات (§19)',
    'empty_hint' => 'لا مؤشرَ معرَّفٌ بعدُ للموقع',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_board.register',
            'label' => 'تسجيلُ لوحة الموقع',
            'rule'  => 'اللوحةُ إسقاطٌ لا مصدرَ حقيقة — ومصدرُ كلِّ مؤشرٍ من كتلوجِ المؤشرات (§19)',
            'fields' => array(
                'site_ref' => 'الموقع ◄',
                'kpi_ref' => 'المؤشر — KPI Catalog ◄',
                'uom' => 'الوحدة ◄',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'kpi_ref', 'uom');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_dashboard_kpi', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_dashboard_kpi');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
