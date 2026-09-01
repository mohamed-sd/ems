<?php
/**
 * workforce/wf_nomination.php — الترشيح والاختيار للتغطية (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: ترشيحُ فردٍ واحدٍ على احتياجٍ واحد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: wf_nomination
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «الترشيح والاختيار للتغطية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_nomination.php',
    'screen'     => 'wf_nomination',
    'table'      => 'wf_nomination',
    'title'      => 'الترشيح والاختيار للتغطية',
    'icon'       => 'fa fa-user-check',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · الترشيح والاختيار للتغطية',
    'intro'      => 'ترشيحُ الأفرادِ لتغطيةِ الاحتياجِ بفحصِ تأهيلِهم ورخصِهم ونتيجةِ اختبارِهم',
    'rule'       => 'فحصُ التأهيلِ والرخصةِ يُشتقَّان من سجلَّيهما — ولا قرارَ ترشيحٍ يتجاوزهما',
    'empty_hint' => 'لا ترشيحَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_nomination.register',
            'label' => 'تسجيلُ الترشيح والاختيار للتغطية',
            'rule'  => 'فحصُ التأهيلِ والرخصةِ يُشتقَّان من سجلَّيهما — ولا قرارَ ترشيحٍ يتجاوزهما',
            'fields' => array(
                'requirement_ref' => 'معرّف الاحتياج ◄',
                'person_ref' => 'كود الفرد ◄',
                'nomination_source' => 'مصدر الترشيح ▼',
                'interview_result' => 'نتيجة المقابلة/الاختبار',
                'decision' => 'القرار ▼',
                'nomination_state' => 'حالة الترشيح ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('requirement_ref', 'person_ref', 'nomination_source', 'interview_result', 'decision', 'nomination_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('wf_nomination', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFN-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('wf_nomination',
                            array('nomination_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في wf_nomination');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
