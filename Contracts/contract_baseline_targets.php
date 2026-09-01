<?php
/**
 * contracts/contract_baseline_targets.php — خط الأساس والمستهدفات (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عقدٌ × بندٌ × شهرٌ — سطرُ مستهدفٍ واحد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: contract_monthly_plan
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «خط الأساس والمستهدفات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/contract_baseline_targets.php',
    'screen'     => 'sal_baseline_targets',
    'table'      => 'contract_monthly_plan',
    'title'      => 'خط الأساس والمستهدفات',
    'icon'       => 'fa fa-bullseye',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · خط الأساس والمستهدفات',
    'intro'      => 'مستهدفاتُ الأشهرِ لكلِّ بندٍ بنسخةِ خطّتِها ومصدرِ مستهدفِها',
    'rule'       => 'نسخةُ الخطّةِ تُعلَن ولا تُدهَس — والمستهدفُ بمصدرِه لا بتقديرٍ (§18)',
    'empty_hint' => 'لا سطرَ مستهدفٍ مسجَّلٌ بعد',
    'order'       => 'period_month DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_baseline_targets.register',
            'label' => 'تسجيلُ خط الأساس والمستهدفات',
            'rule'  => 'نسخةُ الخطّةِ تُعلَن ولا تُدهَس — والمستهدفُ بمصدرِه لا بتقديرٍ (§18)',
            'fields' => array(
                'container_key' => 'مفتاح دورة الالتزام',
                'contract_id' => 'كود العقد',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'business_model' => 'نموذج العمل',
                'renewal_no' => 'رقم التجديد',
                'month_no' => 'رقم الشهر',
                'month_start' => 'بداية الشهر',
                'month_end' => 'نهاية الشهر',
                'line_ref' => 'البند',
                'unit_basis' => 'أساس الوحدة',
                'line_monthly_target' => 'المستهدف الشهري للبند',
                'full_monthly_target' => 'المستهدف الشهري الكامل',
                'uom_ref' => 'الوحدة',
                'plan_version' => 'نسخة الخطة',
                'target_source' => 'مصدر المستهدف',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('container_key', 'contract_id', 'client_no', 'client_name', 'business_model', 'renewal_no', 'month_no', 'month_start', 'month_end', 'line_ref', 'unit_basis', 'line_monthly_target', 'full_monthly_target', 'uom_ref', 'plan_version', 'target_source');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('contract_monthly_plan', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'CBT-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('contract_monthly_plan',
                            array('row_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في contract_monthly_plan');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
