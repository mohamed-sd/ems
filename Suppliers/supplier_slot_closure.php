<?php
/**
 * suppliers/supplier_slot_closure.php — الإقفال التعاقدي للوحدات التعاقدية (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: إقفالٌ تعاقديٌّ واحدٌ لوحدةٍ تعاقديةٍ في فترة
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_closure
 * الأصل: ورقةُ «إدارة الموردين» — السطح «الإقفال التعاقدي للوحدات التعاقدية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_slot_closure.php',
    'screen'     => 'sup_slot_closure',
    'table'      => 'sup_closure',
    'title'      => 'الإقفال التعاقدي للوحدات التعاقدية',
    'icon'       => 'fa fa-lock',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · الإقفال التعاقدي للوحدات التعاقدية',
    'intro'      => 'إقفالُ الوحدةِ التعاقديةِ للفترة: المتعاقدُ عليه والمنفَّذُ المعتمَدُ والفجوةُ ونسبةُ التحقق',
    'rule'       => 'الفجوةُ والقيمةُ التقديريةُ تُشتقّان من الأساسِ والسعرِ — ولا تُدخَلان',
    'empty_hint' => 'لا إقفالَ تعاقديٌّ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_slot_closure.register',
            'label' => 'تسجيلُ الإقفال التعاقدي للوحدات التعاقدية',
            'rule'  => 'الفجوةُ والقيمةُ التقديريةُ تُشتقّان من الأساسِ والسعرِ — ولا تُدخَلان',
            'fields' => array(
                'slot_code' => 'كود الوحدة التعاقدية · Slot_ID',
                'code_contract_supplier' => 'كود عقد المورد',
                'no_supplier' => 'رقم المورد',
                'name_supplier' => 'اسم المورد (بحث)',
                'business_model' => 'نموذج العمل',
                'line_type' => 'نوع الآلية/البند',
                'uom' => 'وحدة القياس',
                'period_kind' => 'نوع الفترة',
                'period_start' => 'بداية الفترة',
                'period_end' => 'نهاية الفترة',
                'currency_ref' => 'العملة',
                'unit_state' => 'حالة الوحدة التعاقدية (م10)',
                'monthly_close_ref' => 'مرجع الإقفال الشهري (م17)',
                'reviewer' => 'المراجع',
                'approved_at' => 'تاريخ الاعتماد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('slot_code', 'code_contract_supplier', 'no_supplier', 'name_supplier', 'business_model', 'line_type', 'uom', 'period_kind', 'period_start', 'period_end', 'currency_ref', 'unit_state', 'monthly_close_ref', 'reviewer', 'approved_at');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_closure', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SCC-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_closure',
                            array('slot_close_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_closure');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
