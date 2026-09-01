<?php
/**
 * fleet/fleet_board.php — لوحة الأسطول (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مؤشرٌ واحدٌ في لوحةِ الأسطول
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_dashboard_kpi
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «لوحة الأسطول»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/fleet_board.php',
    'screen'     => 'flt_board',
    'table'      => 'flt_dashboard_kpi',
    'title'      => 'لوحة الأسطول',
    'icon'       => 'fa fa-chart-line',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · لوحة الأسطول',
    'intro'      => 'مؤشراتُ الأسطولِ بقيمِها ووحداتِها وشيتِها المصدرِ وحدِّها المقبول',
    'rule'       => 'اللوحةُ إسقاطٌ لا مصدرَ حقيقة — ولكلِّ مؤشرٍ شيتُه المصدرُ مسمًّى (§19)',
    'empty_hint' => 'لا مؤشرَ معرَّفٌ بعدُ في لوحةِ الأسطول',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_board.register',
            'label' => 'تسجيلُ لوحة الأسطول',
            'rule'  => 'اللوحةُ إسقاطٌ لا مصدرَ حقيقة — ولكلِّ مؤشرٍ شيتُه المصدرُ مسمًّى (§19)',
            'fields' => array(
                'indicator' => 'المؤشر',
                'uom' => 'الوحدة',
                'source_sheet' => 'الشيت المصدر',
                'acceptable_limit' => 'الحد المقبول',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('indicator', 'uom', 'source_sheet', 'acceptable_limit');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_dashboard_kpi', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_dashboard_kpi');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
