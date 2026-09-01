<?php
/**
 * workforce/wf_equipment_shift_assignment.php — تخصيص المعدة والوردية (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تكليفُ فردٍ على معدّةٍ في وردية
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: wf_equipment_shift_assignment
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «تخصيص المعدة والوردية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_equipment_shift_assignment.php',
    'screen'     => 'wf_shift_assign',
    'table'      => 'wf_equipment_shift_assignment',
    'title'      => 'تخصيص المعدة والوردية',
    'icon'       => 'fa fa-user-gear',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · تخصيص المعدة والوردية',
    'intro'      => 'تكليفُ الفردِ بمعدّةٍ في وردية، بفحصِ المصفوفةِ وبديلِه المعتمَد',
    'rule'       => 'فحصُ المصفوفةِ شرطُ التكليفِ — ولا تكليفَ يخالف أدنى تأهيلِ النوع',
    'empty_hint' => 'لا تكليفَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_shift_assign.register',
            'label' => 'تسجيلُ تخصيص المعدة والوردية',
            'rule'  => 'فحصُ المصفوفةِ شرطُ التكليفِ — ولا تكليفَ يخالف أدنى تأهيلِ النوع',
            'fields' => array(
                'allocation_ref' => 'رقم تخصيص المشروع ◄',
                'person_ref' => 'كود الفرد ◄',
                'equipment_ref' => 'كود المعدة ◄',
                'shift' => 'الوردية ▼',
                'from_date' => 'من تاريخ',
                'to_date' => 'إلى تاريخ',
                'end_reason' => 'سبب الإنهاء ▼',
                'assignment_state' => 'حالة التكليف ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('allocation_ref', 'person_ref', 'equipment_ref', 'shift', 'from_date', 'to_date', 'end_reason', 'assignment_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('wf_equipment_shift_assignment', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFS-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('wf_equipment_shift_assignment',
                            array('assignment_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في wf_equipment_shift_assignment');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
