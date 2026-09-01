<?php
/**
 * workforce/wf_project_allocation.php — التخصيص للمشروع (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تخصيصُ فردٍ واحدٍ لمشروعٍ واحدٍ في مدّة
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: wf_project_allocation
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «التخصيص للمشروع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_project_allocation.php',
    'screen'     => 'wf_allocation',
    'table'      => 'wf_project_allocation',
    'title'      => 'التخصيص للمشروع',
    'icon'       => 'fa fa-diagram-project',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · التخصيص للمشروع',
    'intro'      => 'تخصيصُ الفردِ للمشروعِ بمدّتِه ووحدةِ سكنِه وفحصَي تأهيلِه وعقدِه',
    'rule'       => 'لا تخصيصَ بلا فحصِ تأهيلٍ نافذٍ وعقدِ مشروعٍ ساري — والفحصان يُشتقّان',
    'empty_hint' => 'لا تخصيصَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_allocation.register',
            'label' => 'تسجيلُ التخصيص للمشروع',
            'rule'  => 'لا تخصيصَ بلا فحصِ تأهيلٍ نافذٍ وعقدِ مشروعٍ ساري — والفحصان يُشتقّان',
            'fields' => array(
                'requirement_ref' => 'معرّف الاحتياج المغطَّى ◄',
                'person_ref' => 'كود الفرد ◄',
                'from_date' => 'من تاريخ',
                'to_date' => 'إلى تاريخ',
                'allocation_state' => 'حالة التخصيص ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('requirement_ref', 'person_ref', 'from_date', 'to_date', 'allocation_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('wf_project_allocation', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFA-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('wf_project_allocation',
                            array('allocation_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في wf_project_allocation');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
