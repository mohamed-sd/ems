<?php
/**
 * operations/site_day_shifts.php — ورديات اليوم (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: ورديةٌ واحدةٌ في يومِ موقع
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_day_shift
 * الأصل: ورقةُ «إدارة الموقع» — السطح «ورديات اليوم»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_day_shifts.php',
    'screen'     => 'site_day_shifts',
    'table'      => 'site_day_shift',
    'title'      => 'ورديات اليوم',
    'icon'       => 'fa fa-clock',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · ورديات اليوم',
    'intro'      => 'ورديّاتُ اليومِ بمشرفيها وحالةِ السلامةِ فيها وسريانِ تصاريحِ العمل',
    'rule'       => 'حالةُ HSE وStop-Work تُقرآن من مصدرِهما — والوردياتُ لا تُغلق بتصريحٍ منتهٍ',
    'empty_hint' => 'لا ورديةَ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_day_shifts.register',
            'label' => 'تسجيلُ ورديات اليوم',
            'rule'  => 'حالةُ HSE وStop-Work تُقرآن من مصدرِهما — والوردياتُ لا تُغلق بتصريحٍ منتهٍ',
            'fields' => array(
                'day_id' => 'معرّف اليوم ◄',
                'shift' => 'نوع الوردية ▼',
                'supervisor_id' => 'مشرف الوردية ◄',
                'hse_state' => 'حالة HSE ▼',
                'state' => 'حالة الوردية ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('day_id', 'shift', 'supervisor_id', 'hse_state', 'state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_day_shift', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SSH-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_day_shift',
                            array('shift_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_day_shift');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
