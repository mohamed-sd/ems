<?php
/**
 * operations/site_state_requests.php — طلبات تغيير الحالة ومعالجة المتعثر (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: طلبُ تغييرِ حالةٍ واحدٌ على عنصرٍ واحد
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_state_change_request
 * الأصل: ورقةُ «إدارة الموقع» — السطح «طلبات تغيير الحالة ومعالجة المتعثر»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_state_requests.php',
    'screen'     => 'site_state_request',
    'table'      => 'site_state_change_request',
    'title'      => 'طلبات تغيير الحالة ومعالجة المتعثر',
    'icon'       => 'fa fa-arrows-rotate',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · طلبات تغيير الحالة ومعالجة المتعثر',
    'intro'      => 'طلبُ تحويلِ حالةِ عنصرٍ ميدانيٍّ بسببِه ودليلِه وأولويتِه',
    'rule'       => 'الجهةُ المالكةُ للقرارِ تُشتقُّ من نوعِ الطلبِ — ولا حالةَ تتحوّل بلا طلبٍ مقيَّد',
    'empty_hint' => 'لا طلبَ تغييرِ حالةٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_state_request.register',
            'label' => 'تسجيلُ طلبات تغيير الحالة ومعالجة المتعثر',
            'rule'  => 'الجهةُ المالكةُ للقرارِ تُشتقُّ من نوعِ الطلبِ — ولا حالةَ تتحوّل بلا طلبٍ مقيَّد',
            'fields' => array(
                'site_ref' => 'الموقع ◄',
                'request_date' => 'تاريخ الطلب',
                'request_type' => 'نوع الطلب ▼',
                'target_state' => 'الحالة المطلوبة ▼',
                'reason' => 'السبب',
                'priority' => 'الأولوية ▼',
                'evidence_ref' => 'المرفق/الدليل',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'request_date', 'request_type', 'target_state', 'reason', 'priority', 'evidence_ref');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_state_change_request', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SSR-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_state_change_request',
                            array('request_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_state_change_request');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
