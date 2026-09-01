<?php
/**
 * contracts/contract_coverage_cycles.php — التغطية التعاقدية — دورات الالتزام (DEP-01 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: دورةُ التزامٍ واحدةٌ على عقد
 * المالك: إدارة المبيعات التعاقدية والعقود · مصدرُ الحقيقة: contract_commitments
 * الأصل: ورقةُ «إدارة المبيعات التعاقدية والعقود» — السطح «التغطية التعاقدية — دورات الالتزام»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'contracts/contract_coverage_cycles.php',
    'screen'     => 'sal_coverage_cycles',
    'table'      => 'contract_commitments',
    'title'      => 'التغطية التعاقدية — دورات الالتزام',
    'icon'       => 'fa fa-arrows-spin',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · التغطية التعاقدية — دورات الالتزام',
    'intro'      => 'دوراتُ الالتزامِ بسعتِها ومنفَّذِها وفجوةِ تغطيتِها ونسختِها السابقةِ وما تغيّر',
    'rule'       => 'فتحُ حاويةٍ جديدةٍ بسببٍ من قائمتِه — والنسخةُ السابقةُ تبقى بنصِّها (§18)',
    'empty_hint' => 'لا دورةَ التزامٍ مسجَّلةٌ بعد',
    'where'       => 'is_deleted = 0',
    'order'       => 'valid_from DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sal_coverage_cycles.register',
            'label' => 'تسجيلُ التغطية التعاقدية — دورات الالتزام',
            'rule'  => 'فتحُ حاويةٍ جديدةٍ بسببٍ من قائمتِه — والنسخةُ السابقةُ تبقى بنصِّها (§18)',
            'fields' => array(
                'contract_ref' => 'كود العقد',
                'client_no' => 'رقم العميل',
                'client_name' => 'اسم العميل (بحث)',
                'business_model' => 'نموذج العمل',
                'contract_no' => 'رقم العقد',
                'renewal_no' => 'رقم التجديد (دورة الالتزام)',
                'cycle_kind' => 'نوع دورة الالتزام',
                'contractual_from' => 'السريان التعاقدي من',
                'contractual_to' => 'إلى',
                'cycle_months' => 'أشهر دورة الالتزام',
                'cycle_capacity' => 'سعة دورة الالتزام (المستهدف)',
                'uom_ref' => 'الوحدة',
                'recorded_monthly_plan' => 'المخطط الشهري المسجَّل',
                'executed_qty' => 'المنفَّذ',
                'measured_qty' => 'المنجز/المقاس',
                'coverage_gap' => 'فجوة التغطية',
                'cycle_state' => 'حالة دورة الالتزام',
                'evidence_level' => 'الحجية',
                'source_cycle_state' => 'حالة دورة الالتزام كما وردت بالمصدر',
                'previous_version' => 'النسخة السابقة',
                'version_kind' => 'تكييف النسخة',
                'changed_vs_previous' => 'ما الذي تغيّر عن النسخة السابقة',
                'cycle_pattern' => 'نمط دورة الالتزام ▼',
                'measure_unit' => 'وحدة القياس ▼',
                'slot_monthly_basis' => 'أساس الوحدة التعاقدية الشهري',
                'min_guarantee' => 'الضمان الأدنى — عند نمط الضمان والعتبة',
                'billing_threshold' => 'عتبة الفوترة — عند نمط الضمان والعتبة',
                'new_container_reason' => 'سبب فتح حاوية جديدة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('contract_ref', 'client_no', 'client_name', 'business_model', 'contract_no', 'renewal_no', 'cycle_kind', 'contractual_from', 'contractual_to', 'cycle_months', 'cycle_capacity', 'uom_ref', 'recorded_monthly_plan', 'executed_qty', 'measured_qty', 'coverage_gap', 'cycle_state', 'evidence_level', 'source_cycle_state', 'previous_version', 'version_kind', 'changed_vs_previous', 'cycle_pattern', 'measure_unit', 'slot_monthly_basis', 'min_guarantee', 'billing_threshold', 'new_container_reason');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('contract_commitments', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في contract_commitments');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
