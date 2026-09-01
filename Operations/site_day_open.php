<?php
/**
 * operations/site_day_open.php — فتح يوم الموقع (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: موقعٌ × يومٌ — سطرٌ واحد
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_day
 * الأصل: ورقةُ «إدارة الموقع» — السطح «فتح يوم الموقع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_day_open.php',
    'screen'     => 'site_day_open',
    'table'      => 'site_day',
    'title'      => 'فتح يوم الموقع',
    'icon'       => 'fa fa-door-open',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · فتح يوم الموقع',
    'intro'      => 'افتتاحُ يومِ الموقعِ بحضورِ معداتِه ومشغّليه وحالةِ طقسِه',
    'rule'       => 'اليومُ لا يُفتح مرّتَين — والمتخلّفُ يُكتب ولا يُسكَت عنه',
    'empty_hint' => 'لا يومَ موقعٍ مفتوحٌ بعد',
    'order'       => 'day_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_day_open.register',
            'label' => 'تسجيلُ فتح يوم الموقع',
            'rule'  => 'اليومُ لا يُفتح مرّتَين — والمتخلّفُ يُكتب ولا يُسكَت عنه',
            'fields' => array(
                'site_id' => 'الموقع ◄',
                'day_date' => 'تاريخ اليوم',
                'shift' => 'الوردية ▼',
                'supervisor_id' => 'مشرف الوردية ◄',
                'equipment_absent' => 'معدات متخلفة',
                'operators_absent' => 'مشغّلون متخلفون',
                'substitutes_activated' => 'بدلاء مفعَّلون',
                'weather_state' => 'حالة الطقس ▼',
                'opening_note' => 'ملاحظة الافتتاح',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_id', 'day_date', 'shift', 'supervisor_id', 'equipment_absent', 'operators_absent', 'substitutes_activated', 'weather_state', 'opening_note');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_day', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SDY-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_day',
                            array('day_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_day');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
