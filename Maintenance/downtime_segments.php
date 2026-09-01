<?php
/**
 * maintenance/downtime_segments.php — تقطيع مسؤولية التوقف (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: قطاعُ مسؤوليةٍ واحدٌ داخلَ واقعةِ توقّف
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: mnt_downtime_segment
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «تقطيع مسؤولية التوقف»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/downtime_segments.php',
    'screen'     => 'mnt_downtime_seg',
    'table'      => 'mnt_downtime_segment',
    'title'      => 'تقطيع مسؤولية التوقف',
    'icon'       => 'fa fa-scissors',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · تقطيع مسؤولية التوقف',
    'intro'      => 'تقسيمُ زمنِ التوقّفِ قطاعاتٍ بمالكِ كلٍّ ونسبتِه وإقرارِه أو تنازعِه',
    'rule'       => 'المتنازَعُ عليه يُحسَم بحكمِ مديرِ التشغيلِ — ولا نسبةَ تُحمَّل بلا إقرارٍ أو حكم',
    'empty_hint' => 'لا قطاعَ توقّفٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_downtime_seg.register',
            'label' => 'تسجيلُ تقطيع مسؤولية التوقف',
            'rule'  => 'المتنازَعُ عليه يُحسَم بحكمِ مديرِ التشغيلِ — ولا نسبةَ تُحمَّل بلا إقرارٍ أو حكم',
            'fields' => array(
                'stop_event_ref' => 'مرجع واقعة التوقف ◄',
                'equipment_ref' => 'كود المعدة ◄',
                'segment_seq' => 'تسلسل القطاع',
                'segment_kind' => 'نوع القطاع ▼',
                'segment_reason' => 'سبب القطاع',
                'owner_ack' => 'إقرار صاحب القطاع ▼',
                'segment_state' => 'حالة القطاع ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('stop_event_ref', 'equipment_ref', 'segment_seq', 'segment_kind', 'segment_reason', 'owner_ack', 'segment_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('mnt_downtime_segment', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'MDS-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('mnt_downtime_segment',
                            array('segment_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في mnt_downtime_segment');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
