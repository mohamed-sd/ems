<?php
/**
 * contracts/contract_commercial_lines.php — بنود العقد والالتزام التجاري (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: بندُ بيعٍ واحدٌ في عقدٍ واحد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: contractequipments
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «بنود العقد والالتزام التجاري»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/contract_commercial_lines.php',
    'screen'     => 'sal_contract_lines',
    'table'      => 'contractequipments',
    'title'      => 'بنود العقد والالتزام التجاري',
    'icon'       => 'fa fa-list-ol',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · بنود العقد والالتزام التجاري',
    'intro'      => 'بنودُ العقدِ بوحدةِ قياسِها وأساسِها الشهريِّ ومستهدفِها وسعرِ وحدتِها ونسختِه',
    'rule'       => 'نصُّ السعرِ كما ورد بالمصدرِ يُحفَظ — والسعرُ المُطبَّقُ نسخةٌ لها سريان (§18)',
    'empty_hint' => 'لا بندَ عقدٍ مسجَّلٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_contract_lines.register',
            'label' => 'تسجيلُ بنود العقد والالتزام التجاري',
            'rule'  => 'نصُّ السعرِ كما ورد بالمصدرِ يُحفَظ — والسعرُ المُطبَّقُ نسخةٌ لها سريان (§18)',
            'fields' => array(
                'contract_id' => 'كود العقد',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'business_model' => 'نموذج العمل',
                'equip_type' => 'البند (بند البيع)',
                'equip_unit' => 'وحدة القياس',
                'equip_count' => 'الوحدات المتعاقدة (التركيبة الحالية)',
                'monthly_unit_basis' => 'أساس الوحدة الشهري',
                'equip_monthly_target' => 'المستهدف الشهري للبند',
                'equip_price_currency' => 'العملة',
                'equip_price' => 'سعر وحدة البند',
                'price_version_from' => 'سريان النسخة السعرية',
                'price_state' => 'حالة سعر البند',
                'pricing_basis' => 'أساس التسعير (رأس العقد)',
                'price_source_text' => 'نص السعر كما ورد بالمصدر',
                'shortfall_rule' => 'الحد الأدنى/قاعدة العجز',
                'mix_valid_from' => 'سريان التركيبة الحالية',
                'container_key' => 'مرجع دورة الالتزام',
                'target_source' => 'مصدر المستهدف',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('contract_id', 'client_no', 'client_name', 'business_model', 'equip_type', 'equip_unit', 'equip_count', 'monthly_unit_basis', 'equip_monthly_target', 'equip_price_currency', 'equip_price', 'price_version_from', 'price_state', 'pricing_basis', 'price_source_text', 'shortfall_rule', 'mix_valid_from', 'container_key', 'target_source');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('contractequipments', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'CLN-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('contractequipments',
                            array('line_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في contractequipments');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
