<?php
/**
 * portal/exec_leadership_meetings.php — اجتماعات الإدارة العليا (EX-CEO · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: اجتماعُ إدارةٍ عليا واحدٌ
 * المالك: مساحة الرئيس التنفيذي · مصدرُ الحقيقة: exec_meeting
 * الأصل: ورقةُ «مساحة الرئيس التنفيذي» — السطح «اجتماعات الإدارة العليا»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'portal/exec_leadership_meetings.php',
    'screen'     => 'exec_meetings',
    'table'      => 'exec_meeting',
    'title'      => 'اجتماعات الإدارة العليا',
    'icon'       => 'fa fa-users-line',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · اجتماعات الإدارة العليا',
    'intro'      => 'اجتماعاتُ الإدارةِ العليا بجداولِ أعمالِها ووثائقِها وحالةِ محاضرِها',
    'rule'       => 'قراراتُ الاجتماعِ تُقيَّد في سطحِها ومحضرُه لا يُغلَق بلا حالةٍ مسجَّلة',
    'empty_hint' => 'لا اجتماعَ مسجَّلٌ بعد',
    'order'       => 'meeting_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.exec_meetings.register',
            'label' => 'تسجيلُ اجتماعات الإدارة العليا',
            'rule'  => 'قراراتُ الاجتماعِ تُقيَّد في سطحِها ومحضرُه لا يُغلَق بلا حالةٍ مسجَّلة',
            'fields' => array(
                'meeting_type' => 'Meeting_Type ▼',
                'meeting_date' => 'Date',
                'chair_ref' => 'Chair — مرجع رئيس الاجتماع',
                'participants' => 'Participants',
                'agenda' => 'Agenda',
                'documents' => 'Documents',
                'minutes_status' => 'Minutes_Status ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('meeting_type', 'meeting_date', 'chair_ref', 'participants', 'agenda', 'documents', 'minutes_status');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('exec_meeting', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'EMT-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('exec_meeting',
                            array('meeting_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في exec_meeting');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
