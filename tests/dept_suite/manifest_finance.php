<?php
/**
 * tests/dept_suite/manifest_finance.php — مانيفستُ «إدارة المالية · محاسب الإدارة المالية · مدير الإدارة المالية · المراجع والمدقق المالي · قارئ مالي · رئيس الحسابات · المدير المالي · منفذ المدفوعات البنكية · معد المطابقة البنكية»  (DEP-05)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مولَّدٌ آليًّا** بـ`tools/dept_suite_manifest_gen.php` — وهذا الوسمُ هو ما
 *   يسمح بإعادةِ التوليد. **إن عمّقتَه بيدك فاحذفِ السطرَ أعلاه** كي لا يُدهَس.
 *
 * ◆ **مصدرُ العضوية**: `repair01_screen_registry` حيث `owner_code='DEP-05'`
 *   و`on_disk=1` — **93 سطحًا**. لا اجتهادَ في العضوية.
 *
 * ◆ **عمقُ الفحصِ الآن: التصييرُ وحدَه** — لكلِّ سطحٍ يُقاس:
 *   ① `HTTP 200` أم ردٌّ بحارسٍ أم خطأُ خادم · ② صفرُ تحذيرِ PHP في الجسد.
 *   وهذا يكشف **الانحدارَ** (سطحٌ كان يُصيَّر فصار يُردُّ أو يشتكي) وهو أكثرُ
 *   ما يقع بعدَ التعديلات.
 *
 * ⛔ **ولا `crud` مخمَّنٌ هنا**: حمولةُ كلِّ نموذجٍ تختلف حقلًا حقلًا، وتخمينُها
 *   يُنتج فشلًا كاذبًا يُفسِد خطَّ الأساس. فالخانةُ **فارغةٌ مصرَّحٌ بها**.
 *
 * ◆ **كيف تُعمَّق شاشةٌ** (انسخِ النمطَ من `manifest_sales.php`):
 *   ① أضِفْ للسطحِ `'crud' => array('add' => array('post' => array(...),
 *      'verify' => array('table' => '...', 'where' => "...='{MARK}'")), ...)`.
 *   ② أضِفْ جدولَ ذلك السطحِ إلى `sweep` بعمودِ وسمِه النصّيّ — وإلّا
 *      بقيت صفوفُ الفحصِ في قاعدتك.
 *   ③ وأضِفْ `guard` (اختبارٌ سالبٌ) وإلّا لم تقس أنّ الحارسَ يمنع.
 *
 * التشغيل: php tests/dept_suite/run.php --dept=finance --quick
 * الأساس:  php tests/dept_suite/run.php --dept=finance --save-baseline
 * ═══════════════════════════════════════════════════════════════════════════
 */

return array(

'dept'    => 'finance',
'dept_ar' => 'إدارة المالية · محاسب الإدارة المالية · مدير الإدارة المالية · المراجع والمدقق المالي · قارئ مالي · رئيس الحسابات · المدير المالي · منفذ المدفوعات البنكية · معد المطابقة البنكية',
'user'    => 'مديرمالي',
'pass'    => '12345678',

// ⛔ **فارغانِ ما دام لا `crud`**: لا صفَّ يُنشأ فلا صفَّ يُكنَس.
//    وأوّلُ ما تُعمِّق سطحًا، أضِفْ جدولَه هنا بعمودِ وسمِه.
'sweep'    => array(),
'sweep_by' => array(),

'screens' => array(

// ── Contracts ───────────────────────────────────────────────────
array(
  'route' => 'Contracts/claims.php', 'label' => 'المطالبات والتسليم للمالية',
  'group' => 'Contracts',
  'view'  => array(),
),
array(
  'route' => 'Contracts/client_statement.php', 'label' => 'كشف حساب العميل',
  'group' => 'Contracts',
  'view'  => array(),
),
array(
  'route' => 'Contracts/collections.php', 'label' => 'ذمم العملاء وأعمارها',
  'group' => 'Contracts',
  'view'  => array(),
),
array(
  'route' => 'Contracts/contract_card.php', 'label' => 'أحكام العقد للعملات والمقدم والمهل',
  'group' => 'Contracts',
  'view'  => array(),
),

// ── علاقات العملاء ──────────────────────────────────────────────
array(
  'route' => 'Contracts/contracts.php', 'label' => 'سجل عقود المشاريع',
  'group' => 'علاقات العملاء',
  'view'  => array(),
),

// ── Contracts ───────────────────────────────────────────────────
array(
  'route' => 'Contracts/tax_invoices.php', 'label' => 'الفاتورة الضريبية والإقرارات',
  'group' => 'Contracts',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/acc_adjustments.php', 'label' => 'الاستحقاقات والمقدَّمات والمخصصات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_approval_chain.php', 'label' => 'سلسلة الاعتماد الرباعية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_backflow.php', 'label' => 'المرتجع المالي للإدارات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_closing_checklist.php', 'label' => 'قائمة إقفال الفترة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_cost_centers.php', 'label' => 'مراكز التكلفة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_credit_control.php', 'label' => 'الرقابة الائتمانية وحدود العملاء',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_my_day.php', 'label' => 'مساحة عمل محاسب التخصص اليوم',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_reconciliations.php', 'label' => 'مطابقات الحسابات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_reopen_governance.php', 'label' => 'حوكمة إعادة فتح الفترات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_routing_matrix.php', 'label' => 'مصفوفة التوجيه لمحاسبي التخصصات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_specializations.php', 'label' => 'التخصصات المحاسبية العشرة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/acc_trial_balance.php', 'label' => 'ميزان المراجعة',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الحسابات والقيود ────────────────────────────────────────────
array(
  'route' => 'Finance/accountants_fin.php', 'label' => 'المحاسبون والوحدات',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/ar_accrual_gen.php', 'label' => 'توليد استحقاقات عقد العميل',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ar_claim_invoice.php', 'label' => 'فواتير العملاء',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ar_completion_cert.php', 'label' => 'شهادة الإنجاز الشهرية',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الأصول والتمويل ─────────────────────────────────────────────
array(
  'route' => 'Finance/assets_fin.php', 'label' => 'الإهلاك والقيمة الدفترية',
  'group' => 'الأصول والتمويل',
  'view'  => array(),
),

// ── الميزانية والتكاليف ─────────────────────────────────────────
array(
  'route' => 'Finance/budget_form_fin.php', 'label' => 'ميزانية الإدارة المالية',
  'group' => 'الميزانية والتكاليف',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/budget_master.php', 'label' => 'الميزانية والتقدير',
  'group' => 'Finance',
  'view'  => array(),
),

// ── اللوحات والتقارير ───────────────────────────────────────────
array(
  'route' => 'Finance/cfo_daily_board_fin.php', 'label' => 'لوحة المالية',
  'group' => 'اللوحات والتقارير',
  'view'  => array(),
),
array(
  'route' => 'Finance/cost_report_fin.php', 'label' => 'التكاليف والربحية بالمركز',
  'group' => 'اللوحات والتقارير',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/ctrl_authority_limits.php', 'label' => 'الحدود الصريحة لما لا يملكه كل دور',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ctrl_dept_propagation.php', 'label' => 'الأحكام المنتشرة على الإدارات الست عشرة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ctrl_doc_registry.php', 'label' => 'سجل بنود الوثائق وتغطيتها',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ctrl_doc_variance.php', 'label' => 'مخالفات الوثائق وحسمها',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ctrl_quality_kpis.php', 'label' => 'مؤشرات جودة المحاسبة الاثنا عشر',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ctrl_role_migration.php', 'label' => 'ترحيل الأدوار المالية القديمة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ctrl_supervision.php', 'label' => 'إشراف رئيس الحسابات على محاسبي التخصصات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/currencies_fin.php', 'label' => 'أسعار الصرف',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/daily_pricing_fin.php', 'label' => 'التسعير اليومي',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الذمم والمدفوعات ────────────────────────────────────────────
array(
  'route' => 'Finance/dues_fin.php', 'label' => 'فواتير الموردين والمستحقات',
  'group' => 'الذمم والمدفوعات',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/entitlement_gate.php', 'label' => 'فحص شروط الاستحقاق',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/entitlement.php', 'label' => 'توليد المستحق من العمل المعتمد',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الحسابات والقيود ────────────────────────────────────────────
array(
  'route' => 'Finance/events_list_fin.php', 'label' => 'سجل الأحداث المالية',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),

// ── اللوحات والتقارير ───────────────────────────────────────────
array(
  'route' => 'Finance/executive_dashboard_fin.php', 'label' => 'اللوحة التنفيذية',
  'group' => 'اللوحات والتقارير',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/fin_cashflow_stmt.php', 'label' => 'قائمة التدفقات النقدية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_contract_margin.php', 'label' => 'هامش العقد ونموذج العمل',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_early_warning.php', 'label' => 'الإنذار المالي المبكر',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_equity_stmt.php', 'label' => 'قائمة التغيرات في حقوق الملكية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_posting_matrix.php', 'label' => 'مصفوفة الترحيل من الإدارات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_project_pl.php', 'label' => 'قائمة دخل المشروع',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_ratio_detail.php', 'label' => 'تفصيل نسبة مالية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_ratio_targets.php', 'label' => 'حدود النسب وأهدافها',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_ratios.php', 'label' => 'لوحة النسب المالية',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/fin_unit_economics.php', 'label' => 'ربحية المعدة الواحدة',
  'group' => 'Finance',
  'view'  => array(),
),

// ── اللوحات والتقارير ───────────────────────────────────────────
array(
  'route' => 'Finance/financial_statements_fin.php', 'label' => 'القوائم المالية',
  'group' => 'اللوحات والتقارير',
  'view'  => array(),
),

// ── الأصول والتمويل ─────────────────────────────────────────────
array(
  'route' => 'Finance/funding_fin.php', 'label' => 'التمويل والالتزامات',
  'group' => 'الأصول والتمويل',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/gov_dept_fin.php', 'label' => 'حوكمة المالية والخزينة',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الحسابات والقيود ────────────────────────────────────────────
array(
  'route' => 'Finance/import_events_fin.php', 'label' => 'استقبال معاملات الإدارات',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),
array(
  'route' => 'Finance/journal_form_fin.php', 'label' => 'القيود اليومية',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),

// ── الميزانية والتكاليف ─────────────────────────────────────────
array(
  'route' => 'Finance/maintenance_provision_fin.php', 'label' => 'مخصص الصيانة والعمرات',
  'group' => 'الميزانية والتكاليف',
  'view'  => array(),
),

// ── اللوحات والتقارير ───────────────────────────────────────────
array(
  'route' => 'Finance/management_accounting_fin.php', 'label' => 'دليل الحسابات',
  'group' => 'اللوحات والتقارير',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/ob_commitments.php', 'label' => 'الملتزم به وأثره في الموازنة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ob_contingent.php', 'label' => 'الالتزامات المحتملة والإفصاح',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ob_due_soon.php', 'label' => 'المستحق قريبا والتذكيرات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ob_horizon.php', 'label' => 'آفاق الالتزامات الثلاثة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ob_overdue.php', 'label' => 'الالتزامات المتأخرة والذمم الدائنة',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ob_register.php', 'label' => 'سجل الالتزامات',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/ob_schedule.php', 'label' => 'جدول الاستحقاقات',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الميزانية والتكاليف ─────────────────────────────────────────
array(
  'route' => 'Finance/operator_pay_fin.php', 'label' => 'قواعد مستحقات المشغّلين',
  'group' => 'الميزانية والتكاليف',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/operator_pay_policies_fin.php', 'label' => 'سياسات مستحقات المشغلين',
  'group' => 'Finance',
  'view'  => array(),
),
array(
  'route' => 'Finance/periodic_events_fin.php', 'label' => 'الدوريات المالية',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الحسابات والقيود ────────────────────────────────────────────
array(
  'route' => 'Finance/periods_fin.php', 'label' => 'التقويم المحاسبي للفترات',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),

// ── الذمم والمدفوعات ────────────────────────────────────────────
array(
  'route' => 'Finance/supplier_statement_fin.php', 'label' => 'ذمم الموردين وأعمارها',
  'group' => 'الذمم والمدفوعات',
  'view'  => array(),
),

// ── الميزانية والتكاليف ─────────────────────────────────────────
array(
  'route' => 'Finance/tax_fin.php', 'label' => 'الضرائب والقيمة المضافة',
  'group' => 'الميزانية والتكاليف',
  'view'  => array(),
),

// ── Finance ─────────────────────────────────────────────────────
array(
  'route' => 'Finance/unit_fin_final.php', 'label' => 'الاعتماد المالي النهائي',
  'group' => 'Finance',
  'view'  => array(),
),

// ── الحسابات والقيود ────────────────────────────────────────────
array(
  'route' => 'Finance/unit_records_fin.php', 'label' => 'أحكام العميل والمورد والمشغل',
  'group' => 'الحسابات والقيود',
  'view'  => array(),
),

// ── الميزانية والتكاليف ─────────────────────────────────────────
array(
  'route' => 'Finance/variance_monitor_fin.php', 'label' => 'متابعة انحراف المنفذ عن المخطط',
  'group' => 'الميزانية والتكاليف',
  'view'  => array(),
),

// ── FinRequests ─────────────────────────────────────────────────
array(
  'route' => 'FinRequests/accountant_desk.php', 'label' => 'مهام المحاسب اليومية',
  'group' => 'FinRequests',
  'view'  => array(),
),

// ── بوابة الطلبات ───────────────────────────────────────────────
array(
  'route' => 'FinRequests/cycle_time_board.php', 'label' => 'زمن دورة الطلبات',
  'group' => 'بوابة الطلبات',
  'view'  => array(),
),

// ── FinRequests ─────────────────────────────────────────────────
array(
  'route' => 'FinRequests/dept_inbox.php', 'label' => 'موافقات إدارتي',
  'group' => 'FinRequests',
  'view'  => array(),
),

// ── بوابة الطلبات ───────────────────────────────────────────────
array(
  'route' => 'FinRequests/effect_map.php', 'label' => 'تتبّع الأثر من الواقعة إلى القيد',
  'group' => 'بوابة الطلبات',
  'view'  => array(),
),
array(
  'route' => 'FinRequests/finance_gateway.php', 'label' => 'الطلبات المالية',
  'group' => 'بوابة الطلبات',
  'view'  => array(),
),

// ── FinRequests ─────────────────────────────────────────────────
array(
  'route' => 'FinRequests/request_form.php', 'label' => 'الطلبات المقدَّمة',
  'group' => 'FinRequests',
  'view'  => array(),
),

// ── بوابة الطلبات ───────────────────────────────────────────────
array(
  'route' => 'FinRequests/requests_reports.php', 'label' => 'تقارير الطلبات المالية',
  'group' => 'بوابة الطلبات',
  'view'  => array(),
),
array(
  'route' => 'FinRequests/routing_admin.php', 'label' => 'قواعد توجيه الطلبات المالية',
  'group' => 'بوابة الطلبات',
  'view'  => array(),
),

// ── Maintenance ─────────────────────────────────────────────────
array(
  'route' => 'Maintenance/orders.php', 'label' => 'أوامر الصيانة',
  'group' => 'Maintenance',
  'view'  => array(),
),

// ── Operations ──────────────────────────────────────────────────
array(
  'route' => 'Operations/monthly_close.php', 'label' => 'الإقفال الشهري للتشغيل',
  'group' => 'Operations',
  'view'  => array(),
),

// ── Portal ──────────────────────────────────────────────────────
array(
  'route' => 'Portal/approvals_inbox.php', 'label' => 'صندوق ما ينتظر اعتمادي',
  'group' => 'Portal',
  'view'  => array(),
),

// ── Reports ─────────────────────────────────────────────────────
array(
  'route' => 'Reports/approval_lag_report.php', 'label' => 'الاعتمادات المتأخرة والوثائق',
  'group' => 'Reports',
  'view'  => array(),
),
array(
  'route' => 'Reports/margin_report.php', 'label' => 'هامش الربح للعقد والواقعة',
  'group' => 'Reports',
  'view'  => array(),
),

// ── Risk ────────────────────────────────────────────────────────
array(
  'route' => 'Risk/risk_dept_fin.php', 'label' => 'المخاطر المالية',
  'group' => 'Risk',
  'view'  => array(),
),

// ── Suppliers ───────────────────────────────────────────────────
array(
  'route' => 'Suppliers/settlements.php', 'label' => 'التسويات وكشف الحساب',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/shares_coverage.php', 'label' => 'قياس التغطية والعجز والفائض',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_entitlements.php', 'label' => 'استحقاقات الموردين',
  'group' => 'Suppliers',
  'view'  => array(),
),
array(
  'route' => 'Suppliers/supplier_profile.php', 'label' => 'بطاقة المورد',
  'group' => 'Suppliers',
  'view'  => array(),
),

// ── vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial ─
array(
  'route' => 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial/Depreciation.php', 'label' => 'Depreciation',
  'group' => 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Calculation/Financial',
  'view'  => array(),
),

),
);
