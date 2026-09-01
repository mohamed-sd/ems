<?php
/**
 * contracts/monthly_sales_performance.php — الأداء والمبيعات الشهرية (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عقدٌ × بندٌ × شهرٌ — سطرُ أداءٍ ومبيعاتٍ واحد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: monthly_performance
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «الأداء والمبيعات الشهرية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/monthly_sales_performance.php',
    'screen'     => 'sal_monthly_perf',
    'table'      => 'monthly_performance',
    'title'      => 'الأداء والمبيعات الشهرية',
    'icon'       => 'fa fa-chart-column',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · الأداء والمبيعات الشهرية',
    'intro'      => 'المنفَّذُ والمنجزُ والإيرادُ المحسوبُ والمفوترُ وغيرُ المطالَبِ به شهرًا بشهر',
    'rule'       => 'الإيرادُ يُحسب من الكميةِ والسعرِ ولا يُدخَل — والمنفَّذُ غيرُ المفوترِ يُعرَض ولا يُطوى',
    'empty_hint' => 'لا سطرَ أداءٍ شهريٍّ مسجَّلٌ بعد',
    'order'       => 'period DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_monthly_perf.register',
            'label' => 'تسجيلُ الأداء والمبيعات الشهرية',
            'rule'  => 'الإيرادُ يُحسب من الكميةِ والسعرِ ولا يُدخَل — والمنفَّذُ غيرُ المفوترِ يُعرَض ولا يُطوى',
            'fields' => array(
                'container_key' => 'مفتاح دورة الالتزام',
                'contract_ref' => 'كود العقد',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'business_model' => 'نموذج العمل',
                'renewal_no' => 'رقم التجديد',
                'month_no' => 'رقم الشهر',
                'month_from' => 'من',
                'month_to' => 'إلى',
                'line_ref' => 'البند',
                'monthly_target' => 'المستهدف الشهري',
                'uom_ref' => 'الوحدة',
                'executed_achievement' => 'تحقق المنفَّذ',
                'measured_achievement' => 'تحقق المنجز',
                'statement_source' => 'مصدر البيان',
                'unbilled_executed' => 'منفَّذ غير مفوتر',
                'unclaimed_revenue' => 'إيراد غير مطالَب به ($)',
                'invoice_ref' => 'مرجع الفاتورة',
                'contract_currency' => 'عملة العقد',
                'revenue_columns_state' => 'حالة أعمدة الإيراد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('container_key', 'contract_ref', 'client_no', 'client_name', 'business_model', 'renewal_no', 'month_no', 'month_from', 'month_to', 'line_ref', 'monthly_target', 'uom_ref', 'executed_achievement', 'measured_achievement', 'statement_source', 'unbilled_executed', 'unclaimed_revenue', 'invoice_ref', 'contract_currency', 'revenue_columns_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('monthly_performance', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'MSP-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('monthly_performance',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في monthly_performance');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
