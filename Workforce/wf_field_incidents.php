<?php
/**
 * workforce/wf_field_incidents.php — وقائع الميدان والإحالة التأديبية (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: واقعةُ ميدانٍ واحدةٌ على فردٍ واحد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: wf_field_incident
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «وقائع الميدان والإحالة التأديبية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_field_incidents.php',
    'screen'     => 'wf_incidents',
    'table'      => 'wf_field_incident',
    'title'      => 'وقائع الميدان والإحالة التأديبية',
    'icon'       => 'fa fa-triangle-exclamation',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · وقائع الميدان والإحالة التأديبية',
    'intro'      => 'وقائعُ الميدانِ بأدلّتِها وجهةِ إحالتِها ومرجعِ قضيّتِها أو تسويةِ مورِّدِها',
    'rule'       => 'الإحالةُ تُشتقُّ من التبعيّةِ — والموظفُ إلى الموارد والمورِّدُ إلى تسويتِه',
    'empty_hint' => 'لا واقعةَ ميدانٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_incidents.register',
            'label' => 'تسجيلُ وقائع الميدان والإحالة التأديبية',
            'rule'  => 'الإحالةُ تُشتقُّ من التبعيّةِ — والموظفُ إلى الموارد والمورِّدُ إلى تسويتِه',
            'fields' => array(
                'incident_date' => 'التاريخ',
                'person_ref' => 'كود الفرد ◄',
                'incident_type' => 'نوع الواقعة ▼',
                'incident_desc' => 'وصف الواقعة',
                'evidence_ref' => 'الدليل/المرفق',
                'incident_state' => 'حالة الواقعة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('incident_date', 'person_ref', 'incident_type', 'incident_desc', 'evidence_ref', 'incident_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('wf_field_incident', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFI-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('wf_field_incident',
                            array('incident_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في wf_field_incident');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
