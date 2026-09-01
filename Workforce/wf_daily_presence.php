<?php
/**
 * workforce/wf_daily_presence.php — الحركة والتواجد اليومي (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: سطرُ حركةٍ أو تواجدٍ ليومٍ واحدٍ لفرد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: worker_movement
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «الحركة والتواجد اليومي»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_daily_presence.php',
    'screen'     => 'wf_daily_presence',
    'table'      => 'worker_movement',
    'title'      => 'الحركة والتواجد اليومي',
    'icon'       => 'fa fa-person-walking',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الحركة والتواجد اليومي',
    'intro'      => 'حركةُ الأفرادِ وتواجدُهم يومًا بيوم بوسيلةِ انتقالِهم ومرجعِ أمرِ نقلِهم',
    'rule'       => 'التواجدُ بالموقعِ يُقرأ من الموقعِ ولا يُكتب هنا — والحركةُ بأمرِها لا بلا سند',
    'empty_hint' => 'لا سطرَ حركةٍ مسجَّلٌ بعد',
    'order'       => 'departure_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_daily_presence.register',
            'label' => 'تسجيلُ الحركة والتواجد اليومي',
            'rule'  => 'التواجدُ بالموقعِ يُقرأ من الموقعِ ولا يُكتب هنا — والحركةُ بأمرِها لا بلا سند',
            'fields' => array(
                'row_date' => 'التاريخ',
                'person_ref' => 'كود الفرد ◄',
                'row_kind' => 'نوع السطر ▼',
                'presence_state' => 'حالة التواجد ▼',
                'transport_mode' => 'وسيلة الانتقال ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('row_date', 'person_ref', 'row_kind', 'presence_state', 'transport_mode');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('worker_movement', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFD-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('worker_movement',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في worker_movement');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
