<?php
/**
 * fleet/use_right_ranges.php — نطاقات حق الاستخدام (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: نطاقُ ترقيمٍ واحدٌ لحالةِ ملكية
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_use_right_range
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «نطاقات حق الاستخدام»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/use_right_ranges.php',
    'screen'     => 'flt_use_right_ranges',
    'table'      => 'flt_use_right_range',
    'title'      => 'نطاقات حق الاستخدام',
    'icon'       => 'fa fa-arrows-left-right-to-line',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · نطاقات حق الاستخدام',
    'intro'      => 'نطاقاتُ الترقيمِ لكلِّ حالةِ ملكيةٍ — من وإلى وشرحُ النطاق',
    'rule'       => 'النطاقُ يفصل حالاتِ الملكيةِ بالترقيمِ — ولا تداخلَ بين نطاقَين',
    'empty_hint' => 'لا نطاقَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_use_right_ranges.register',
            'label' => 'تسجيلُ نطاقات حق الاستخدام',
            'rule'  => 'النطاقُ يفصل حالاتِ الملكيةِ بالترقيمِ — ولا تداخلَ بين نطاقَين',
            'fields' => array(
                'ownership_state' => 'حالة الملكية',
                'range_from' => 'من',
                'range_to' => 'إلى',
                'explanation' => 'الشرح',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('ownership_state', 'range_from', 'range_to', 'explanation');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_use_right_range', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_use_right_range');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
