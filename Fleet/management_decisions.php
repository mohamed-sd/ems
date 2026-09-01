<?php
/**
 * fleet/management_decisions.php — حزمة القرارات الإدارية (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: قرارٌ إداريٌّ واحدٌ بخيارَيه
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_management_decision
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «حزمة القرارات الإدارية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/management_decisions.php',
    'screen'     => 'flt_mgmt_decisions',
    'table'      => 'flt_management_decision',
    'title'      => 'حزمة القرارات الإدارية',
    'icon'       => 'fa fa-gavel',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · حزمة القرارات الإدارية',
    'intro'      => 'القرارُ المطلوبُ ودليلُه وخياراه وأثرُ كلٍّ والتوصيةُ الفنيةُ ومهلتُه',
    'rule'       => 'الخياران بأثرَيهما مكتوبان قبلَ القرار — ولا قرارَ بلا معتمِدٍ وتاريخ',
    'empty_hint' => 'لا قرارَ إداريٌّ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_mgmt_decisions.register',
            'label' => 'تسجيلُ حزمة القرارات الإدارية',
            'rule'  => 'الخياران بأثرَيهما مكتوبان قبلَ القرار — ولا قرارَ بلا معتمِدٍ وتاريخ',
            'fields' => array(
                'requested_decision' => 'القرار المطلوب',
                'evidence_current_state' => 'الدليل والوضع الحالي',
                'option_a' => 'الخيار (أ) وأثره',
                'option_b' => 'الخيار (ب) وأثره',
                'technical_recommendation' => 'التوصية الفنية',
                'owner_name' => 'المسؤول',
                'deadline' => 'المهلة',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('requested_decision', 'evidence_current_state', 'option_a', 'option_b', 'technical_recommendation', 'owner_name', 'deadline');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_management_decision', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'DEC-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('flt_management_decision',
                            array('decision_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_management_decision');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
