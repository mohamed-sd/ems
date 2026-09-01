<?php
/**
 * operations/site_day_approval.php — محضر اعتماد الموقع (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: محضرُ اعتمادٍ واحدٌ ليومِ موقع
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_day_approval
 * الأصل: ورقةُ «إدارة الموقع» — السطح «محضر اعتماد الموقع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_day_approval.php',
    'screen'     => 'site_day_approval',
    'table'      => 'site_day_approval',
    'title'      => 'محضر اعتماد الموقع',
    'icon'       => 'fa fa-file-signature',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · محضر اعتماد الموقع',
    'intro'      => 'محضرُ اعتمادِ سجلاتِ اليومِ بإقرارِ «حدثت فعلًا» وتوقيعِ مدير الموقع',
    'rule'       => 'المرفوضُ بسببٍ مكتوب — ولا اعتمادَ بلا إقرارٍ صريحٍ وتوقيع',
    'empty_hint' => 'لا محضرَ اعتمادٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_day_approval.register',
            'label' => 'تسجيلُ محضر اعتماد الموقع',
            'rule'  => 'المرفوضُ بسببٍ مكتوب — ولا اعتمادَ بلا إقرارٍ صريحٍ وتوقيع',
            'fields' => array(
                'day_ref' => 'معرّف يوم الموقع ◄',
                'site_ref' => 'الموقع ◄',
                'records_modified' => 'سجلات معدَّلة قبل الاعتماد',
                'records_rejected' => 'سجلات مرفوضة',
                'reject_reason' => 'سبب الرفض',
                'happened_declaration' => 'إقرار «حدثت فعلًا» ▼',
                'site_manager_signature' => 'توقيع مدير الموقع',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('day_ref', 'site_ref', 'records_modified', 'records_rejected', 'reject_reason', 'happened_declaration', 'site_manager_signature');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_day_approval', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SDA-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_day_approval',
                            array('minutes_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_day_approval');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
