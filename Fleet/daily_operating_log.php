<?php
/**
 * fleet/daily_operating_log.php — السجل اليومي للتشغيل (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: أصلٌ × يومٌ × وردية — سطرٌ واحد
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_daily_operating_log
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «السجل اليومي للتشغيل»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/daily_operating_log.php',
    'screen'     => 'flt_daily_log',
    'table'      => 'flt_daily_operating_log',
    'title'      => 'السجل اليومي للتشغيل',
    'icon'       => 'fa fa-calendar-day',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · السجل اليومي للتشغيل',
    'intro'      => 'يومُ الأصلِ بالساعاتِ والعدّادِ وثمانيةِ أبوابِ التوقفِ بمسمّياتِها',
    'rule'       => 'حبّتُه أصلٌ لا تشغيلةُ وحدة — والتايم شيت حبّةٌ أخرى لا نسخةٌ منه (حكمُ W05)',
    'empty_hint' => 'لا يومَ تشغيلٍ مسجَّلٌ بعدُ لهذا الأصل',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_daily_log.register',
            'label' => 'تسجيلُ السجل اليومي للتشغيل',
            'rule'  => 'حبّتُه أصلٌ لا تشغيلةُ وحدة — والتايم شيت حبّةٌ أخرى لا نسخةٌ منه (حكمُ W05)',
            'fields' => array(
                'asset_code' => 'كود الأصل',
                'log_date' => 'التاريخ',
                'shift' => 'الوردية ▼',
                'operator_no' => 'رقم المشغل',
                'meter_start' => 'العداد أول الوردية',
                'meter_end' => 'العداد آخر الوردية',
                'work_hours' => 'ساعات العمل الفعلي',
                'standby_hours' => 'ساعات الاستعداد',
                'data_entry_by' => 'مدخل البيانات',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('asset_code', 'log_date', 'shift', 'operator_no', 'meter_start', 'meter_end', 'work_hours', 'standby_hours', 'data_entry_by');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_daily_operating_log', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'DOL-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('flt_daily_operating_log',
                            array('log_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_daily_operating_log');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
