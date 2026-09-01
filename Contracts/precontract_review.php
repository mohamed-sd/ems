<?php
/**
 * contracts/precontract_review.php — مراجعة ما قبل العقد (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: مراجعةُ ما قبلَ العقدِ واحدةٌ لعرضٍ واحد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: fin_precontract_review
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «مراجعة ما قبل العقد»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/precontract_review.php',
    'screen'     => 'sal_precontract',
    'table'      => 'fin_precontract_review',
    'title'      => 'مراجعة ما قبل العقد',
    'icon'       => 'fa fa-magnifying-glass-chart',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · مراجعة ما قبل العقد',
    'intro'      => 'مراجعةُ العرضِ قبلَ التوقيع: النطاقُ والأسعارُ والكمياتُ والضماناتُ والمخاطرُ التجارية',
    'rule'       => 'الجاهزيةُ للتوقيعِ لا تُعلَن وملاحظةٌ مفتوحةٌ قائمة — والمعتمِدُ مسمًّى',
    'empty_hint' => 'لا مراجعةَ ما قبلَ عقدٍ مسجَّلةٌ بعد',
    'order'       => 'id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_precontract.register',
            'label' => 'تسجيلُ مراجعة ما قبل العقد',
            'rule'  => 'الجاهزيةُ للتوقيعِ لا تُعلَن وملاحظةٌ مفتوحةٌ قائمة — والمعتمِدُ مسمًّى',
            'fields' => array(
                'offer_id' => 'رقم العرض',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'project_no' => 'رقم المشروع',
                'final_offer_match' => 'مطابقة العرض النهائي',
                'scope_review' => 'النطاق',
                'prices_review' => 'الأسعار',
                'quantities_review' => 'الكميات',
                'currency_ref' => 'العملة',
                'payment_terms' => 'شروط الدفع',
                'advance_terms' => 'المقدم',
                'guarantee_terms' => 'الضمان',
                'penalty_terms' => 'الغرامات',
                'client_obligations' => 'التزامات العميل',
                'commercial_risks' => 'المخاطر التجارية',
                'open_notes' => 'الملاحظات المفتوحة',
                'sign_readiness' => 'حالة الجاهزية للتوقيع',
                'closed_date' => 'تاريخ الإقفال',
                'contract_ref' => 'مرجع العقد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('offer_id', 'client_no', 'client_name', 'project_no', 'final_offer_match', 'scope_review', 'prices_review', 'quantities_review', 'currency_ref', 'payment_terms', 'advance_terms', 'guarantee_terms', 'penalty_terms', 'client_obligations', 'commercial_risks', 'open_notes', 'sign_readiness', 'closed_date', 'contract_ref');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('fin_precontract_review', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في fin_precontract_review');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
