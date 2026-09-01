<?php
/**
 * fleet/inspection_card.php — بطاقة التفتيش (DEP-04 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: بطاقةُ تفتيشٍ واحدةٌ — أصلٌ × أمرُ تفتيش
 * المالك: إدارة الأسطول والأصول · مصدرُ الحقيقة: flt_inspection_card
 * الأصل: ورقةُ «إدارة الأسطول والأصول» — السطح «بطاقة التفتيش»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'fleet/inspection_card.php',
    'screen'     => 'flt_inspection_card',
    'table'      => 'flt_inspection_card',
    'title'      => 'بطاقة التفتيش',
    'icon'       => 'fa fa-clipboard-check',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · بطاقة التفتيش',
    'intro'      => 'نتيجةُ التفتيشِ عنصرًا عنصرًا: المحركُ والهيدروليكُ والفرامل — وأثرُها على الحالةِ الفنية',
    'rule'       => 'البطاقةُ تتبع أمرَ تفتيشٍ قائمًا — والنتيجةُ تُغيِّر الحالةَ الفنيةَ بمرجعِها لا بلا سند',
    'empty_hint' => 'لا بطاقةَ تفتيشٍ مسجَّلةٌ بعدُ — البطاقةُ تُفتح على أمرِ تفتيشٍ قائم',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.flt_inspection_card.register',
            'label' => 'تسجيلُ بطاقة التفتيش',
            'rule'  => 'البطاقةُ تتبع أمرَ تفتيشٍ قائمًا — والنتيجةُ تُغيِّر الحالةَ الفنيةَ بمرجعِها لا بلا سند',
            'fields' => array(
                'order_ref' => 'رقم أمر التفتيش',
                'asset_code' => 'كود الأصل',
                'inspector' => 'المفتش',
                'start_date' => 'تاريخ بدء التفتيش',
                'meter_reading' => 'قراءة العداد',
                'inspection_result' => 'نتيجة التفتيش ▼',
                'technical_notes' => 'الملاحظات الفنية',
                'resulting_action' => 'الإجراء الناتج ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('order_ref', 'asset_code', 'inspector', 'start_date', 'meter_reading', 'inspection_result', 'technical_notes', 'resulting_action');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('flt_inspection_card', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'INSP-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('flt_inspection_card',
                            array('card_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في flt_inspection_card');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
