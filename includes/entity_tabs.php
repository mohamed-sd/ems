<?php
/**
 * includes/entity_tabs.php — شريطُ رحلةِ الكيانِ الموحَّد (UXW-01 §8-2)
 * ───────────────────────────────────────────────────────────────────────────
 * «رحلةُ الكيانِ في ملفٍّ واحدٍ — وتفاصيلُ النموذجِ داخلَه لا شاشاتٍ متفرقةً
 *  في السايدبار». أسماءُ التبويباتِ من جدولِ §8-2 حرفًا؛ والمقيسُ حيًّا
 * يُفعَّل رابطُه، وغيرُ المبنيِّ يُعرض معطَّلًا معلَنًا لا يُخفى ولا يُخترع.
 * الشاشاتُ الموسومةُ «تُدمج تبويبًا» في الدفترِ تحمل الشريطَ نفسَه بتبويبِها
 * النشطِ — فيصير الملفُّ الأمُّ وأبناؤه جسدًا واحدًا ظاهرَ الرحلة.
 * عرضٌ محضٌ: لا معاملَ يُمرَّر ولا منطقَ يُلمس — والصلاحيةُ على الوجهةِ
 * يفحصها حارسُها هي.
 */

if (!function_exists('ems_entity_tabs')) {

    function ems_entity_tabs_registry(): array
    {
        /* اسمُ التبويبِ (§8-2 حرفًا) => المسارُ الحيُّ المقيسُ أو '' (غيرُ مبنيةٍ بعد) */
        return array(
            /* SUP-01: «ستةُ تبويبات: البيانات · جهاتُ الاتصالِ والمفوضون ·
               التأهيلُ والوثائقُ والحسابُ البنكي · المعداتُ المقدَّمة · الطاقةُ
               والجاهزية · التقييمُ والمخاطر». و«الحاوياتُ والتوزيع» سقطت — لفظٌ
               متقاعدٌ كُنس نظيرُه في XC-02. */
            'supplier' => array(
                'label' => 'ملف المورد',
                'tabs' => array(
                    'البيانات' => 'Suppliers/suppliers.php',
                    'جهاتُ الاتصالِ والمفوضون' => 'Suppliers/supplier_contacts.php',
                    'التأهيلُ والوثائقُ والحساب' => 'Suppliers/supplier_documents.php',
                    'المعداتُ المقدَّمة' => 'Suppliers/equipment_plan.php',
                    'الطاقةُ والجاهزية' => 'Suppliers/supplier_capacity.php',
                    'التقييمُ والمخاطر' => 'Suppliers/supplier_evaluation.php',
                ),
            ),
            /* SAL-07: «أربعةُ تبويبات: بيانات العرض · بنوده · سجلُّ التفاوض ·
               مراجعةُ ما قبل التعاقد» — كيانٌ لم يكن مسجَّلًا البتة. */
            'quotation' => array(
                'label' => 'ملف العرض',
                'tabs' => array(
                    'بيانات العرض' => 'Clients/quotations.php',
                    'بنودُ العرض' => 'Clients/quotation_lines.php',
                    'سجلُّ التفاوض' => 'Clients/quotation_negotiation.php',
                    'مراجعةُ ما قبل التعاقد' => 'Clients/readiness_lines.php',
                ),
            ),
            /* SUP-08: «خمسةُ تبويبات» — ونصُّ المتطلبِ هو الحاكمُ على ملاحظةِ
               `gov_target_nav` التي تقول أربعة، لأنَّ معيارَ القبولِ معلَّقٌ به. */
            'supplier_contract' => array(
                'label' => 'ملف عقد المورد',
                'tabs' => array(
                    'بياناتُ العقد' => 'Suppliers/supplierscontracts.php',
                    'بنودُ العقد' => 'Suppliers/supplier_contract_lines.php',
                    'مصفوفةُ المسؤولياتِ والتكاليف' => 'Suppliers/supplier_rules.php',
                    'الحصصُ والتغطية' => 'Suppliers/shares_coverage.php',
                    'الملاحقُ والتصفية' => 'Suppliers/supplier_closure.php',
                ),
            ),
            /* SUP-19: «كشفُ الحسابِ التجاريُّ تبويبٌ فيه» — والاستحقاقاتُ
               (SUP-18) والسلفُ (SUP-17) والمخالفاتُ (SUP-23) وحالةُ الصرفِ
               (SUP-20) أبناؤه. وحالةُ الصرفِ **إسقاطٌ من المالية** لا سطحٌ هنا. */
            'settlement' => array(
                'label' => 'ملف التسوية',
                'tabs' => array(
                    'التسوياتُ وكشفُ الحساب' => 'Suppliers/settlements.php',
                    'الاستحقاقات' => 'Suppliers/supplier_entitlements.php',
                    'السلفُ والنيابية' => 'Suppliers/supplier_advances.php',
                    'المخالفاتُ والجزاءات' => 'Suppliers/supplier_violations.php',
                    'طلباتُ الدفعِ وحالةُ الصرف' => 'Finance/payments_fin.php',
                ),
            ),
            /* SAL-01: «خمسةُ تبويبات: البيانات الأساسية · جهات الاتصال ·
               المشاريع · العقود · المركز المالي إسقاطًا مقيَّدًا». */
            'client' => array(
                'label' => 'ملف العميل',
                'tabs' => array(
                    'البيانات الأساسية' => 'Clients/clients.php',
                    'جهات الاتصال' => 'Clients/client_contacts.php',
                    'المشاريع' => 'Projects/projects.php',
                    'العقود' => 'Contracts/contracts.php',
                    'المركز المالي' => 'Contracts/client_statement.php',
                ),
            ),
            'equipment' => array(
                'label' => 'رحلة المعدة',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Equipments/equipments.php',
                    'الملكيةُ والتمويل' => 'Equipments/equipment_sourcing.php',
                    'الوثائقُ وصلاحيتُها' => 'Equipments/equipment_documents.php',
                    'الجاهزية' => '',
                    'ساعاتُ التشغيل' => 'Finance/asset_hours_link.php',
                    'الأعطالُ والصيانة' => 'Maintenance/orders.php',
                    'الإهلاك' => 'Finance/depr_run.php',
                    'التكليف' => 'Operations/daily_plan.php',
                    'سجلُّ التغييرات' => '',
                ),
            ),
            'ticket' => array(
                'label' => 'رحلة البلاغ',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Tickets/tickets_list.php',
                    'السياقُ والمصدر' => 'Tickets/ticket_form.php',
                    'التصنيفُ والمهلة' => '',
                    'التحويلُ والتفريع' => '',
                    'الإجراءات' => '',
                    'الإغلاق' => 'Tickets/ticket_close.php',
                    'سجلُّ الحالات' => '',
                ),
            ),
            'operator' => array(
                'label' => 'رحلة المشغل',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Oprators/oprators.php',
                    'التأهيلُ والرخص' => 'Workforce/op_qual.php',
                    'التكليفُ على المعدات' => 'Operations/daily_plan.php',
                    'الأداء' => '',
                    'الوقائعُ اليومية' => '',
                    'الخصومات' => 'Workforce/deductions.php',
                    'المستندات' => '',
                ),
            ),
            'risk' => array(
                'label' => 'رحلة الخطر',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Risk/risk_register.php',
                    'التصنيفُ والمالك' => '',
                    'التقييمُ والقياس' => 'Risk/risk_assessment.php',
                    'الضوابطُ والمعالجة' => 'Risk/risk_controls.php',
                    'القبولُ والتصعيد' => 'Risk/risk_acceptance.php',
                    'المؤشرات' => 'Risk/risk_signals.php',
                    'سجلُّ المراجعات' => 'Risk/risk_reviews.php',
                ),
            ),
            'financing' => array(
                'label' => 'رحلة عملية التمويل',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Financing/operation_profile.php',
                    'الممولُ والشروط' => 'Financing/fin_models.php',
                    'الأصولُ الممولة' => 'Equipments/fin_assets.php',
                    'الأقساطُ والسداد' => 'Financing/installments.php',
                    'الضمانات' => '',
                    'تكلفةُ التمويل' => '',
                    'المستندات' => '',
                ),
            ),
            'project' => array(
                'label' => 'رحلة المشروع',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Projects/projects.php',
                    'المواقع' => 'Operations/sites_board.php',
                    'العقود' => 'Contracts/contracts.php',
                    'المواردُ المخصَّصة' => '',
                    'التنفيذ' => '',
                    'الميزانيةُ والإنجاز' => 'Finance/fin_project_pl.php',
                    'المخاطر' => 'Risk/risk_register.php',
                    'المستندات' => '',
                ),
            ),
            'contract' => array(
                'label' => 'رحلة العقد',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Contracts/contracts.php',
                    'الأطراف' => '',
                    'الأسعارُ والشروط' => '',
                    'التغطية' => 'Suppliers/shares_coverage.php',
                    'الموردون' => 'Suppliers/supplierscontracts.php',
                    'المعدات' => '',
                    'التنفيذ' => 'Contracts/unit_client_match.php',
                    'المستخلصاتُ والفواتير' => 'Contracts/claims.php',
                    'المخاطر' => 'Clients/commercial_risks.php',
                    'المستندات' => '',
                    'سجلُّ التغييرات' => 'Clients/contract_amendments.php',
                ),
            ),
            'employee' => array(
                'label' => 'رحلة الموظف',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Employees/employees.php',
                    'العقود' => 'Workforce/contract_registry.php',
                    'الوثائق' => '',
                    'الحضور' => 'Operations/attendance.php',
                    'الأجورُ والمستحقات' => '',
                    'الخصوماتُ والسلف' => 'Workforce/deductions.php',
                    'العهد' => '',
                    'الأداء' => 'Workforce/worker_evaluation.php',
                    'إنهاءُ الخدمة' => 'Workforce/final_settlement.php',
                ),
            ),
            'mnt_order' => array(
                'label' => 'رحلة أمر الصيانة',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Maintenance/orders.php',
                    'البلاغُ الأصل' => 'Tickets/tickets_list.php',
                    'التشخيص' => 'Maintenance/inspections.php',
                    'قطعُ الغيارِ والمخزون' => 'Procurement/warehouses.php',
                    'الفنيونَ والساعات' => '',
                    'التكلفة' => '',
                    'الإقفال' => '',
                    'سجلُّ الحالات' => '',
                ),
            ),
        );
    }

    /** يطبع شريطَ الرحلةِ لكيانٍ — $active اسمُ التبويبِ النشطِ حرفًا. */
    function ems_entity_tabs(string $entityKey, string $active = ''): string
    {
        $reg = ems_entity_tabs_registry();
        if (!isset($reg[$entityKey])) { return ''; }
        $base = function_exists('ems_url') ? rtrim(ems_url(''), '/') . '/' : '../';
        $h = '<nav class="ems-entity-tabs" dir="rtl" aria-label="'
           . htmlspecialchars($reg[$entityKey]['label'], ENT_QUOTES, 'UTF-8') . '">';
        $h .= '<span class="ems-entity-tabs-label">'
            . htmlspecialchars($reg[$entityKey]['label'], ENT_QUOTES, 'UTF-8') . ':</span>';
        foreach ($reg[$entityKey]['tabs'] as $name => $route) {
            $isActive = ($name === $active);
            $cls = 'ems-entity-tab' . ($isActive ? ' is-active' : '') . ($route === '' ? ' is-unbuilt' : '');
            $nameH = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            if ($route === '') {
                $h .= '<span class="' . $cls . '" title="غير مبنية بعد — هدف معلن في الدفتر">' . $nameH . '</span>';
            } elseif ($isActive) {
                $h .= '<span class="' . $cls . '" aria-current="page">' . $nameH . '</span>';
            } else {
                $h .= '<a class="' . $cls . '" href="' . htmlspecialchars($base . $route, ENT_QUOTES, 'UTF-8') . '">' . $nameH . '</a>';
            }
        }
        $h .= '</nav>';

        /* ── الموضعُ: تحتَ الرأسِ لا فوقَه (قرارُ المالك 2026-08-19) ──────────
           ◆ **المقيس**: 25 شاشةً من 60 تطبع هذا الشريطَ **قبل** أن تفتح
             `<div class="main">`، فيخرج من غلافِ الشاشةِ ويطفو فوقَها — بينما
             35 شاشةً تطبعه في موضعِه الصحيحِ داخلَها.
           ◆ **ولا يُصلَح بجعلِ الدالةِ تصفُّ دائمًا**: الخمسُ والثلاثون تطبعه
             **بعد** الرأس، فلو صفَّته الدالةُ لوجد الطابورَ مصروفًا سلفًا
             فاختفى الشريطُ من 35 شاشةً صامتًا — إصلاحٌ يهدم أكثرَ مما يبني.
           ◆ فالحكمُ **بلحظةِ النداء**: نُودِيت قبلَ الرأسِ ⇐ تُصَفُّ ليصرفها
             الرأسُ في موضعِها (تحتَ الشريطِ الأصفرِ وتحتَ تبويباتِ الملف)،
             ونُودِيت بعدَه ⇐ تُرجَع كما كانت فتُطبع حيث كتبتها الشاشة. */
        if (empty($GLOBALS['__ems_head_printed'])) {
            $GLOBALS['__ems_entity_tabs'] = $h;
            return '';
        }
        return $h;
    }

    /** يصرف شريطَ الرحلةِ المصفوفَ — يُنادى من `page_header.php` بعد تبويباتِ الملف. */
    function ems_entity_tabs_flush()
    {
        if (empty($GLOBALS['__ems_entity_tabs'])) { return ''; }
        $h = $GLOBALS['__ems_entity_tabs'];
        $GLOBALS['__ems_entity_tabs'] = '';
        return $h;
    }
}
