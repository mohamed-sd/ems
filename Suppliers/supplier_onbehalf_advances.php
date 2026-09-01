<?php
/**
 * suppliers/supplier_onbehalf_advances.php — النيابية والسلف والخصومات (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: واقعةُ نيابةٍ أو سلفةٍ واحدةٌ على مورِّد
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_onbehalf_advance_deduction
 * الأصل: ورقةُ «إدارة الموردين» — السطح «النيابية والسلف والخصومات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_onbehalf_advances.php',
    'screen'     => 'sup_onbehalf',
    'table'      => 'sup_onbehalf_advance_deduction',
    'title'      => 'النيابية والسلف والخصومات',
    'icon'       => 'fa fa-hand-holding-dollar',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · النيابية والسلف والخصومات',
    'intro'      => 'ما دُفع نيابةً عن المورِّدِ ومن قدَّمه ومن دفعه ومن يتحمّله اقتصاديًّا',
    'rule'       => 'سلسلةُ النيابةِ ثلاثيّةٌ — Provided/Paid/Bearer — والمسترَدُّ والمتبقّي يُشتقّان',
    'empty_hint' => 'لا واقعةَ نيابةٍ مسجَّلةٌ بعد',
    'order'       => 'date DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_onbehalf.register',
            'label' => 'تسجيلُ النيابية والسلف والخصومات',
            'rule'  => 'سلسلةُ النيابةِ ثلاثيّةٌ — Provided/Paid/Bearer — والمسترَدُّ والمتبقّي يُشتقّان',
            'fields' => array(
                'date' => 'التاريخ',
                'no_supplier' => 'رقم المورد',
                'name_supplier' => 'اسم المورد (بحث)',
                'code_contract_supplier' => 'كود عقد المورد',
                'unit_or_equipment_code' => 'كود الوحدة التعاقدية/المعدة (إن وجد)',
                'onbehalf_kind' => 'نوع الواقعة النيابية',
                'operator_code' => 'كود المشغل E###',
                'description' => 'الوصف',
                'amount' => 'المبلغ',
                'currency_ref' => 'العملة',
                'provided_by' => 'Provided_By',
                'paid_by' => 'Paid_By',
                'economic_cost_bearer' => 'Economic_Cost_Bearer',
                'recoverable' => 'قابل للاسترداد؟',
                'doc_evidence' => 'المستند/الإثبات',
                'disburse_approver' => 'معتمد الصرف',
                'chain_state' => 'حالة السلسلة النيابية',
                'offset_settlement_ref' => 'مرجع التسوية الخاصمة (م17)',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('date', 'no_supplier', 'name_supplier', 'code_contract_supplier', 'unit_or_equipment_code', 'onbehalf_kind', 'operator_code', 'description', 'amount', 'currency_ref', 'provided_by', 'paid_by', 'economic_cost_bearer', 'recoverable', 'doc_evidence', 'disburse_approver', 'chain_state', 'offset_settlement_ref');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_onbehalf_advance_deduction', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SOB-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_onbehalf_advance_deduction',
                            array('onbehalf_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_onbehalf_advance_deduction');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
