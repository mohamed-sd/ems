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
            'supplier' => array(
                'label' => 'رحلةُ المورد',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Suppliers/suppliers.php',
                    'التأهيلُ والقدرة' => 'Suppliers/supplier_capacity.php',
                    'العقودُ والحصص' => 'Suppliers/shares_coverage.php',
                    'الحاوياتُ والتوزيع' => '',
                    'المعداتُ والمشغّلون' => 'Suppliers/supplierscontracts.php',
                    'التنفيذُ والاستحقاق' => 'Suppliers/unit_statement_supplier.php',
                    'التسوياتُ والصرف' => 'Suppliers/supplier_advances.php',
                    'التقييم' => 'Suppliers/supplier_evaluation.php',
                    'المستندات' => 'Suppliers/supplier_documents.php',
                ),
            ),
            'client' => array(
                'label' => 'رحلةُ العميل',
                'tabs' => array(
                    'نظرةٌ عامة' => 'Clients/clients.php',
                    'المشاريعُ والمواقع' => 'Projects/projects.php',
                    'العقود' => 'Contracts/contracts.php',
                    'الفوترةُ والتحصيل' => 'Contracts/claims.php',
                    'الذمم' => 'Contracts/client_statement.php',
                    'المستندات' => '',
                    'سجلُّ التغييرات' => 'Clients/contract_events.php',
                ),
            ),
            'equipment' => array(
                'label' => 'رحلةُ المعدة',
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
                'label' => 'رحلةُ البلاغ',
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
                'label' => 'رحلةُ المشغّل',
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
                'label' => 'رحلةُ الخطر',
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
                'label' => 'رحلةُ عمليةِ التمويل',
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
                'label' => 'رحلةُ المشروع',
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
                $h .= '<span class="' . $cls . '" title="غيرُ مبنيةٍ بعدُ — هدفٌ معلَنٌ في الدفتر">' . $nameH . '</span>';
            } elseif ($isActive) {
                $h .= '<span class="' . $cls . '" aria-current="page">' . $nameH . '</span>';
            } else {
                $h .= '<a class="' . $cls . '" href="' . htmlspecialchars($base . $route, ENT_QUOTES, 'UTF-8') . '">' . $nameH . '</a>';
            }
        }
        return $h . '</nav>';
    }
}
