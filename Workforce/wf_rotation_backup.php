<?php
/**
 * workforce/wf_rotation_backup.php — التناوب والبدلاء (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: دورةُ تناوبٍ واحدةٌ لفردٍ واحد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: operator_rotations
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «التناوب والبدلاء»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_rotation_backup.php',
    'screen'     => 'wf_rotation',
    'table'      => 'operator_rotations',
    'title'      => 'التناوب والبدلاء',
    'icon'       => 'fa fa-rotate',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · التناوب والبدلاء',
    'intro'      => 'دوراتُ التناوبِ بإجازاتِها وبدلائِها المكلَّفين وفحصِ تأهيلِ البديل',
    'rule'       => 'البديلُ لا يُكلَّف بلا فحصِ تأهيلٍ — وأثرُ التغطيةِ يُقرأ ولا يُقدَّر',
    'empty_hint' => 'لا دورةَ تناوبٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_rotation.register',
            'label' => 'تسجيلُ التناوب والبدلاء',
            'rule'  => 'البديلُ لا يُكلَّف بلا فحصِ تأهيلٍ — وأثرُ التغطيةِ يُقرأ ولا يُقدَّر',
            'fields' => array(
                'person_ref' => 'كود الفرد ◄',
                'rotation_pattern' => 'نمط التناوب ▼',
                'cycle_start_date' => 'بداية الدورة',
                'leave_start' => 'بداية الإجازة',
                'leave_end' => 'نهاية الإجازة',
                'leave_type' => 'نوع الإجازة ▼',
                'swap_state' => 'حالة التبادل ▼',
                'cycle_state' => 'حالة الدورة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('person_ref', 'rotation_pattern', 'cycle_start_date', 'leave_start', 'leave_end', 'leave_type', 'swap_state', 'cycle_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('operator_rotations', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFR-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('operator_rotations',
                            array('cycle_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في operator_rotations');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
