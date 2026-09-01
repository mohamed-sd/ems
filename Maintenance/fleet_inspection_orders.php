<?php
/**
 * maintenance/fleet_inspection_orders.php — أوامر التفتيش الواردة من الأسطول (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: أمرُ تفتيشٍ واحدٌ واردٌ من الأسطول
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: mnt_inspection
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «أوامر التفتيش الواردة من الأسطول»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/fleet_inspection_orders.php',
    'screen'     => 'mnt_fleet_inspections',
    'table'      => 'mnt_inspection',
    'title'      => 'أوامر التفتيش الواردة من الأسطول',
    'icon'       => 'fa fa-inbox',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · أوامر التفتيش الواردة من الأسطول',
    'intro'      => 'أوامرُ التفتيشِ الواردةُ من الأسطولِ بفاحصِها وموعدِ تنفيذِها ونتيجتِها وبطاقتِها المعادة',
    'rule'       => 'أمرُ التفتيشِ يُنشَأ عند الأسطولِ ويُنفَّذ هنا — والبطاقةُ تعود إليه بمرجعِها (§17)',
    'empty_hint' => 'لا أمرَ تفتيشٍ واردٌ بعد',
    'where'       => 'is_deleted = 0',
    'order'       => 'scheduled_date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_fleet_inspections.register',
            'label' => 'تسجيلُ أوامر التفتيش الواردة من الأسطول',
            'rule'  => 'أمرُ التفتيشِ يُنشَأ عند الأسطولِ ويُنفَّذ هنا — والبطاقةُ تعود إليه بمرجعِها (§17)',
            'fields' => array(
                'fleet_order_ref' => 'مرجع أمر التفتيش بالأسطول ◄',
                'equipment_id' => 'كود المعدة ◄',
                'scheduled_date' => 'موعد التنفيذ',
                'overall_result' => 'النتيجة ▼',
                'returned_card_ref' => 'مرجع بطاقة التفتيش المعادة ◄',
                'row_state' => 'حالة السطر ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('fleet_order_ref', 'equipment_id', 'scheduled_date', 'overall_result', 'returned_card_ref', 'row_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('mnt_inspection', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'MFI-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('mnt_inspection',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في mnt_inspection');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
