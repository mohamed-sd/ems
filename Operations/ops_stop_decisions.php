<?php
/**
 * operations/ops_stop_decisions.php — قرارات التوقف (DEP-11 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: قرارُ توقّفٍ واحدٌ على واقعةِ توقّف
 * المالك: إدارة التشغيل · مصدرُ الحقيقة: ops_stop_register
 * الأصل: ورقةُ «إدارة التشغيل» — السطح «قرارات التوقف»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/ops_stop_decisions.php',
    'screen'     => 'ops_stop_decisions',
    'table'      => 'ops_stop_register',
    'title'      => 'قرارات التوقف',
    'icon'       => 'fa fa-gavel',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · قرارات التوقف',
    'intro'      => 'قراراتُ التوقّفِ بمهلتِها الإلزاميةِ ومبرِّرِها وأثرِها على الجاهزية',
    'rule'       => 'حبّةُ التوقّفِ مملوكةٌ للتشغيلِ — والقرارُ يُتَّخذ هنا وحدَه ويُقرأ عند غيرِه (§17)',
    'empty_hint' => 'لا قرارَ توقّفٍ مسجَّلٌ بعد',
    'order'       => 'stop_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.ops_stop_decisions.register',
            'label' => 'تسجيلُ قرارات التوقف',
            'rule'  => 'حبّةُ التوقّفِ مملوكةٌ للتشغيلِ — والقرارُ يُتَّخذ هنا وحدَه ويُقرأ عند غيرِه (§17)',
            'fields' => array(
                'event_date' => 'تاريخ الواقعة',
                'stop_period_ref' => 'معرّف فترة التوقف ◄',
                'equipment_id' => 'كود المعدة ◄',
                'site_id' => 'الموقع ◄',
                'stop_reason_ref' => 'سبب التوقف ◄',
                'resp_party' => 'مسؤول التوقف ◄',
                'decision' => 'القرار ▼',
                'decision_reason' => 'مبرر القرار',
                'readiness_effect' => 'أثر القرار على الجاهزية ▼',
                'decision_effective_date' => 'تاريخ نفاذ القرار',
                'decision_state' => 'حالة القرار ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('event_date', 'stop_period_ref', 'equipment_id', 'site_id', 'stop_reason_ref', 'resp_party', 'decision', 'decision_reason', 'readiness_effect', 'decision_effective_date', 'decision_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('ops_stop_register', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'OSD-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('ops_stop_register',
                            array('decision_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في ops_stop_register');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
