<?php
/**
 * fleet/exception_register.php — سجل الاستثناءات (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: استثناءٌ واحدٌ بأصولِه المتأثرة
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_exception_register
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «سجل الاستثناءات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/exception_register.php',
    'screen'     => 'flt_exceptions',
    'table'      => 'flt_exception_register',
    'title'      => 'سجل الاستثناءات',
    'icon'       => 'fa fa-circle-exclamation',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · سجل الاستثناءات',
    'intro'      => 'استثناءاتُ الأسطولِ بخطورتِها وإجرائِها الموصى ومسؤولِها وتاريخِ استحقاقِها',
    'rule'       => 'لا استثناءَ يُغلَق بلا نصِّ «كيف حُسم» ومعتمِدٍ مسمًّى',
    'empty_hint' => 'لا استثناءَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_exceptions.register',
            'label' => 'تسجيلُ سجل الاستثناءات',
            'rule'  => 'لا استثناءَ يُغلَق بلا نصِّ «كيف حُسم» ومعتمِدٍ مسمًّى',
            'fields' => array(
                'exception_type' => 'نوع الاستثناء ▼',
                'description' => 'وصف الاستثناء',
                'affected_assets' => 'الأصول المتأثرة والقياس',
                'severity' => 'الخطورة ▼',
                'recommended_action' => 'الإجراء الموصى',
                'owner_name' => 'المسؤول',
                'accounting_legal_impact' => 'الأثر المحاسبي أو القانوني',
                'state' => 'الحالة ▼',
                'due_date' => 'تاريخ الاستحقاق',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('exception_type', 'description', 'affected_assets', 'severity', 'recommended_action', 'owner_name', 'accounting_legal_impact', 'state', 'due_date');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_exception_register', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'EXC-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('flt_exception_register',
                            array('exception_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_exception_register');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
