<?php
/**
 * operations/site_shift_handover.php — محضر تسليم واستلام الورديات (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: محضرُ تسليمٍ واحدٌ بين وردِيَّتَين
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_shift_handover
 * الأصل: ورقةُ «إدارة الموقع» — السطح «محضر تسليم واستلام الورديات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_shift_handover.php',
    'screen'     => 'site_shift_handover',
    'table'      => 'site_shift_handover',
    'title'      => 'محضر تسليم واستلام الورديات',
    'icon'       => 'fa fa-right-left',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · محضر تسليم واستلام الورديات',
    'intro'      => 'تسليمُ الورديةِ بمعداتِها العاملةِ والمتوقفةِ وعُهدِها وملاحظاتِها المرحَّلة',
    'rule'       => 'الملاحظةُ المفتوحةُ تُرحَّل ولا تُطوى — والعدّاداتُ تُقرأ عند التسليم',
    'empty_hint' => 'لا محضرَ تسليمٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_shift_handover.register',
            'label' => 'تسجيلُ محضر تسليم واستلام الورديات',
            'rule'  => 'الملاحظةُ المفتوحةُ تُرحَّل ولا تُطوى — والعدّاداتُ تُقرأ عند التسليم',
            'fields' => array(
                'site_ref' => 'الموقع ◄',
                'handover_date' => 'التاريخ',
                'shift_out' => 'الوردية المُسلِّمة ▼',
                'shift_in' => 'الوردية المستلِمة ▼',
                'custody_handed' => 'عُهد مسلَّمة',
                'open_notes_carried' => 'ملاحظات مفتوحة مرحَّلة',
                'meter_readings' => 'قراءات عدّادات عند التسليم',
                'signature_out' => 'توقيع المُسلِّم',
                'signature_in' => 'توقيع المستلِم',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('site_ref', 'handover_date', 'shift_out', 'shift_in', 'custody_handed', 'open_notes_carried', 'meter_readings', 'signature_out', 'signature_in');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_shift_handover', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SHO-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_shift_handover',
                            array('minutes_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_shift_handover');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
