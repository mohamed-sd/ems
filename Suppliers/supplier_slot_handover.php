<?php
/**
 * suppliers/supplier_slot_handover.php — سجل تسليم الخانات (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: إشغالُ وحدةٍ تعاقديةٍ واحدةٍ في مدّة
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_handover_slot
 * الأصل: ورقةُ «إدارة الموردين» — السطح «سجل تسليم الخانات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_slot_handover.php',
    'screen'     => 'sup_handover',
    'table'      => 'sup_handover_slot',
    'title'      => 'سجل تسليم الخانات',
    'icon'       => 'fa fa-people-arrows',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · سجل تسليم الخانات',
    'intro'      => 'إشغالُ الوحداتِ التعاقديةِ ومن سلّمها لمن ومدّةُ إشغالِه وتحققُه',
    'rule'       => 'الشغورُ يحتاج توثيقًا ومبرِّرًا — والتناوبُ يُقسَم بنسبةِ مساهمةٍ مشتقّة',
    'empty_hint' => 'لا إشغالَ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_handover.register',
            'label' => 'تسجيلُ سجل تسليم الخانات',
            'rule'  => 'الشغورُ يحتاج توثيقًا ومبرِّرًا — والتناوبُ يُقسَم بنسبةِ مساهمةٍ مشتقّة',
            'fields' => array(
                'no_supplier' => 'رقم المورد',
                'occupancy_kind' => 'نوع الإشغال ▼',
                'from_date' => 'من تاريخ',
                'to_date' => 'إلى تاريخ',
                'documented_vacancy' => 'شغور موثَّق؟ ▼',
                'vacancy_reason' => 'مبرر الشغور',
                'decision_ref' => 'مرجع القرار',
                'occupancy_state' => 'حالة الإشغال ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('no_supplier', 'occupancy_kind', 'from_date', 'to_date', 'documented_vacancy', 'vacancy_reason', 'decision_ref', 'occupancy_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_handover_slot', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SHS-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_handover_slot',
                            array('occupancy_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_handover_slot');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
