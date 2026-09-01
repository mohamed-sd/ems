<?php
/**
 * suppliers/supplier_offers.php — عروض الموردين والتفاوض (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عرضُ مورِّدٍ واحدٌ بنسختِه
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_offer_supplier_negotiation
 * الأصل: ورقةُ «إدارة الموردين» — السطح «عروض الموردين والتفاوض»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_offers.php',
    'screen'     => 'sup_offers',
    'table'      => 'sup_offer_supplier_negotiation',
    'title'      => 'عروض الموردين والتفاوض',
    'icon'       => 'fa fa-comments-dollar',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · عروض الموردين والتفاوض',
    'intro'      => 'عروضُ الموردين ونسخُ التفاوضِ عليها: ما تغيّر ومن اقترحه ومتى',
    'rule'       => 'كلُّ نسخةٍ تُقيَّد بأساسِها وبما تغيّر — ولا تُدهَس نسخةٌ سابقةٌ (§18)',
    'empty_hint' => 'لا عرضَ مورِّدٍ مسجَّلٌ بعد',
    'order'       => 'date_offer DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_offers.register',
            'label' => 'تسجيلُ عروض الموردين والتفاوض',
            'rule'  => 'كلُّ نسخةٍ تُقيَّد بأساسِها وبما تغيّر — ولا تُدهَس نسخةٌ سابقةٌ (§18)',
            'fields' => array(
                'code_contract_supplier' => 'كود عقد المورد (الناتج)',
                'no_supplier' => 'رقم المورد',
                'name_supplier' => 'اسم المورد (بحث)',
                'business_model' => 'نموذج العمل',
                'offer_kind' => 'صفة العرض',
                'version_no' => 'رقم النسخة',
                'base_version' => 'النسخة الأساس',
                'changed_what' => 'ما الذي تغيّر عن سابقتها',
                'proposed_by' => 'من اقترح التغيير',
                'change_date' => 'تاريخ التغيير',
                'negotiation_state' => 'حالة التفاوض',
                'currency_ref' => 'العملة',
                'payment_terms' => 'شروط السداد المعروضة',
                'execution_site' => 'موقع التنفيذ',
                'date_offer' => 'تاريخ العرض',
                'competing_offers' => 'عروض منافسة قورنت',
                'required_guarantees' => 'الضمانات المطلوبة',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('code_contract_supplier', 'no_supplier', 'name_supplier', 'business_model', 'offer_kind', 'version_no', 'base_version', 'changed_what', 'proposed_by', 'change_date', 'negotiation_state', 'currency_ref', 'payment_terms', 'execution_site', 'date_offer', 'competing_offers', 'required_guarantees');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_offer_supplier_negotiation', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SOF-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_offer_supplier_negotiation',
                            array('offer_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_offer_supplier_negotiation');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
