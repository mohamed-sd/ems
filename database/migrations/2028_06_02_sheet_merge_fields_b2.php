<?php
/**
 * 2028_06_02_sheet_merge_fields_b2.php — حقولُ «سجل حقول الورقة» تصير أعمدةً في جدولِ الشاشة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القرار (المالك · 2026-09-07)**: بطاقةُ «سجل حقول الورقة» كانت جدولًا ثانيًا
 *   على الشاشةِ يقرأ **قوقعةً** خاصّةً به (‏`id · company_id · created_at ·
 *   created_by · updated_at` + `gN`) لا مفتاحَ لها إلى شيء، و**97 من 98 منها
 *   صفرُ صفّ**. فالحقلُ يُدمج في **جدولِ الشاشةِ الحقيقيِّ** ليحمل بياناتِه.
 *
 * ◆ **والاسمُ من المعنى لا من الرمز**: العمودُ الجديدُ يُسمّى بالإنجليزيةِ مشتقًّا
 *   من اسمِ الحقلِ العربيِّ في «02 · تتبع الحقول» (المعجم `tools/sheet_merge/
 *   glossary.json`)، **والاسمُ العربيُّ يُحفظ في `COMMENT` العمود** — فالجسرُ
 *   بين الورقةِ والمخطَّطِ يبقى مقروءًا من المخطَّطِ نفسِه لا من ملفٍّ جانبيّ.
 *
 * ◆ **ولا يُنشأ عمودٌ لحقلٍ له نظيرٌ بالاسمِ نفسِه**: ثلاثةٌ وعشرون حقلًا وجدت
 *   عمودَها قائمًا في جدولِ الشاشة، **فتُغذّى منه** ولا يُصنَع لها توأم.
 *
 * ⛔ **والمدى محسوم**: خمسٌ وعشرون شاشةً **قُطع فيها جدولُها الحقيقيُّ بشاهدَين**
 *   (تسمية + استعلامُ الصفحةِ نفسِها). وما التبس جدولُه **لم يُمَسّ** —
 *   يبقى بطاقتُه كما هي حتى يُقرَّر (‏`docs/sheet_merge/DEFERRED_ar.md`).
 *
 * ◆ **إجراؤها متكرِّر**: العمودُ القائمُ يُتخطّى، فتشغيلُها مرّتَين لا يضرّ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI \u{641}\u{642}\u{637}\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$t0 = microtime(true);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS'); if ($p === null || $p === '') { $p = ems_env('DB_PASS'); }
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_error) { exit('\u{62A}\u{639}\u{630}\u{631} \u{627}\u{644}\u{627}\u{62A}\u{635}\u{627}\u{644}: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

function ems_sm_cols(mysqli $c, $t) {
    $out = array();
    $r = $c->query("SHOW COLUMNS FROM `" . $c->real_escape_string($t) . "`");
    if ($r) { while ($x = $r->fetch_assoc()) { $out[strtolower($x['Field'])] = true; } }
    return $out;
}

/* الجدولُ ⇒ [اسمُ العمود، نوعُه، اسمُ الحقلِ العربيُّ كما في ورقةِ الدليل] */
$SPEC = array(
    'claims' => array(
        array('commitment_cycle_key', 'VARCHAR(80) NULL DEFAULT NULL', 'مفتاح دورة الالتزام'),
        array('contract_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود العقد'),
        array('client_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العميل'),
        array('client_name_search', 'VARCHAR(190) NULL DEFAULT NULL', 'اسم العميل (بحث)'),
        array('range_to', 'DATE NULL DEFAULT NULL', 'إلى'),
        array('reference_done_quantity', 'DECIMAL(18,2) NULL DEFAULT NULL', 'الكمية المنجزة المرجعية'),
        array('unit', 'VARCHAR(190) NULL DEFAULT NULL', 'الوحدة'),
        array('computed_due_usd', 'VARCHAR(190) NULL DEFAULT NULL', 'الاستحقاق المحسوب ($)'),
        array('computed_due_sdg', 'VARCHAR(190) NULL DEFAULT NULL', 'الاستحقاق المحسوب (ج.س)'),
        array('claimed_value_usd', 'VARCHAR(190) NULL DEFAULT NULL', 'القيمة المطالب بها ($)'),
        array('claimed_value_sdg', 'VARCHAR(190) NULL DEFAULT NULL', 'المطالب بها (ج.س)'),
        array('measure_or_claim_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع القياس/المستخلص'),
        array('client_approval_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة اعتماد العميل'),
        array('finance_handover_date', 'DATE NULL DEFAULT NULL', 'تاريخ التسليم للمالية'),
        array('invoice_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع الفاتورة'),
        array('followup_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة المتابعة'),
        array('invoiced_to_client_usd', 'VARCHAR(190) NULL DEFAULT NULL', 'المفوتر للعميل ($)'),
        array('unclaimed_due_usd', 'VARCHAR(190) NULL DEFAULT NULL', 'مستحق غير مطالب به ($)'),
        array('collection_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة التحصيل'),
        array('collection_state_basis', 'VARCHAR(190) NULL DEFAULT NULL', 'أساس حالة التحصيل'),
        array('measure_or_settlement_evidence', 'VARCHAR(190) NULL DEFAULT NULL', 'دليل القياس/التسوية بالمصدر'),
        array('evidence_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'مستوى الحجية'),
    ),
    'clients' => array(
        array('client_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العميل'),
        array('short_name', 'VARCHAR(190) NULL DEFAULT NULL', 'الاسم المختصر'),
        array('classification_basis', 'VARCHAR(190) NULL DEFAULT NULL', 'أساس التصنيف'),
        array('client_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة العميل'),
        array('sector', 'VARCHAR(190) NULL DEFAULT NULL', 'القطاع'),
        array('country', 'VARCHAR(190) NULL DEFAULT NULL', 'الدولة'),
        array('city_or_region', 'VARCHAR(190) NULL DEFAULT NULL', 'المدينة/المنطقة'),
        array('registration_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم التسجيل'),
        array('account_owner', 'VARCHAR(190) NULL DEFAULT NULL', 'مالك الحساب'),
        array('recognition_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدر التعرف'),
        array('priority_degree', 'DECIMAL(18,2) NULL DEFAULT NULL', 'درجة الأولوية'),
        array('credit_rating', 'VARCHAR(190) NULL DEFAULT NULL', 'التصنيف الائتماني'),
        array('credit_limit_usd', 'VARCHAR(190) NULL DEFAULT NULL', 'حد الائتمان ($)'),
        array('credit_limit_sdg', 'VARCHAR(190) NULL DEFAULT NULL', 'حد الائتمان (ج.س)'),
        array('default_payment_terms', 'VARCHAR(500) NULL DEFAULT NULL', 'شروط الدفع الافتراضية'),
        array('first_deal_date', 'DATE NULL DEFAULT NULL', 'تاريخ أول تعامل'),
        array('contracts_count', 'DECIMAL(18,2) NULL DEFAULT NULL', 'عدد العقود'),
        array('active_contracts', 'VARCHAR(190) NULL DEFAULT NULL', 'العقود الجارية'),
        array('last_exec_activity', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر نشاط تنفيذي'),
        array('dealing_models', 'VARCHAR(190) NULL DEFAULT NULL', 'نماذج التعامل'),
        array('notes', 'VARCHAR(500) NULL DEFAULT NULL', 'ملاحظات'),
        array('service_types', 'VARCHAR(190) NULL DEFAULT NULL', 'أنواع الخدمات'),
        array('dealt_currencies', 'VARCHAR(190) NULL DEFAULT NULL', 'العملات المتعامل بها'),
        array('source_billing_frequency', 'VARCHAR(190) NULL DEFAULT NULL', 'دورية الفوترة بالمصدر'),
        array('client_data_evidence_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'مستوى حجية بيانات العميل'),
    ),
    'exec_decisions' => array(
        array('action_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'Action_ID'),
        array('deputy_role', 'VARCHAR(190) NULL DEFAULT NULL', 'Deputy_Role'),
        array('decision_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدر القرار'),
        array('subject', 'VARCHAR(190) NULL DEFAULT NULL', 'الموضوع'),
        array('department', 'VARCHAR(190) NULL DEFAULT NULL', 'الإدارة'),
        array('responsible', 'VARCHAR(190) NULL DEFAULT NULL', 'المسؤول'),
        array('due_date', 'DATE NULL DEFAULT NULL', 'Due_Date'),
        array('priority', 'VARCHAR(190) NULL DEFAULT NULL', 'Priority'),
        array('delay_days', 'DECIMAL(18,2) NULL DEFAULT NULL', 'أيام التأخير'),
        array('evidence', 'VARCHAR(190) NULL DEFAULT NULL', 'Evidence'),
        array('closure', 'VARCHAR(190) NULL DEFAULT NULL', 'Closure'),
    ),
    'fin_bank_statement_lines' => array(
        array('match_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المطابقة'),
        array('bank_account', 'VARCHAR(190) NULL DEFAULT NULL', 'الحساب البنكي'),
        array('bank_statement_balance', 'VARCHAR(190) NULL DEFAULT NULL', 'رصيد الكشف البنكي'),
        array('book_balance', 'VARCHAR(190) NULL DEFAULT NULL', 'رصيد الدفتر'),
        array('variance_items', 'VARCHAR(190) NULL DEFAULT NULL', 'بنود الفروق'),
        array('variance_reason', 'VARCHAR(500) NULL DEFAULT NULL', 'سبب الفرق'),
        array('variance_treatment', 'VARCHAR(190) NULL DEFAULT NULL', 'معالجة الفرق'),
        array('remaining_variance', 'VARCHAR(190) NULL DEFAULT NULL', 'الفرق المتبقي'),
        array('statement_attachment', 'VARCHAR(500) NULL DEFAULT NULL', 'مرفق الكشف'),
        array('match_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة المطابقة'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'fin_event_links' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('original_event', 'VARCHAR(190) NULL DEFAULT NULL', 'الواقعة الأصلية'),
        array('publishing_event', 'VARCHAR(190) NULL DEFAULT NULL', 'الحدث الناشر'),
        array('integration_matrix_effect_rule', 'VARCHAR(190) NULL DEFAULT NULL', 'قاعدة الأثر بمصفوفة التكامل'),
        array('generated_entry', 'VARCHAR(190) NULL DEFAULT NULL', 'القيد المتولد'),
        array('entry_date', 'DATE NULL DEFAULT NULL', 'تاريخ القيد'),
        array('effect_value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'قيمة الأثر'),
        array('event_to_entry_lag', 'VARCHAR(190) NULL DEFAULT NULL', 'زمن التأخر بين الواقعة والقيد'),
        array('consistency_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الاتساق'),
        array('processing_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المعالجة'),
    ),
    'fin_journal_entries' => array(
        array('event_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع الحدث'),
        array('entry_lines_count_ref_m06_2', 'VARCHAR(190) NULL DEFAULT NULL', 'عدد أسطر القيد تفصيلها م06-2'),
        array('reversal_of', 'VARCHAR(190) NULL DEFAULT NULL', 'قيد عكسي ل'),
        array('entry_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة القيد'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('reviewer', 'VARCHAR(190) NULL DEFAULT NULL', 'المراجع'),
        array('approver', 'VARCHAR(190) NULL DEFAULT NULL', 'المعتمد'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'fin_payments' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('client_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العميل'),
        array('project_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم المشروع'),
        array('total_receivable', 'VARCHAR(190) NULL DEFAULT NULL', 'إجمالي الذمة'),
        array('bracket_value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'قيمة الشريحة'),
        array('last_collection', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر تحصيل'),
        array('claim_action', 'VARCHAR(190) NULL DEFAULT NULL', 'إجراء المطالبة'),
    ),
    'fin_tax_transactions' => array(
        array('declaration_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الإقرار'),
        array('tax_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع الضريبة'),
        array('output_tax', 'VARCHAR(190) NULL DEFAULT NULL', 'ضريبة المخرجات'),
        array('input_tax', 'VARCHAR(190) NULL DEFAULT NULL', 'ضريبة المدخلات'),
        array('net_due', 'DATE NULL DEFAULT NULL', 'الصافي المستحق'),
        array('statutory_due_date', 'DATE NULL DEFAULT NULL', 'تاريخ الاستحقاق النظامي'),
        array('governance_submission_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع التقديم بالحوكمة'),
        array('treasury_settlement_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع السداد بالخزينة'),
        array('declaration_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الإقرار'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
    ),
    'fleet_equipment_history' => array(
        array('event_sequence', 'DECIMAL(18,2) NULL DEFAULT NULL', 'تسلسل الواقعة'),
        array('location', 'VARCHAR(190) NULL DEFAULT NULL', 'الموقع'),
        array('contract_unit', 'VARCHAR(60) NULL DEFAULT NULL', 'الوحدة التعاقدية'),
        array('document', 'VARCHAR(190) NULL DEFAULT NULL', 'المستند'),
    ),
    'mnt_plan' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('equipment_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود المعدة'),
        array('equipment_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع المعدة'),
        array('cutoff_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدر الفاصل'),
        array('preventive_cycle', 'VARCHAR(190) NULL DEFAULT NULL', 'دورة الوقائية'),
        array('allocated_asset_cutoff', 'VARCHAR(190) NULL DEFAULT NULL', 'فاصل الأصل المخصص'),
        array('cycle_hours', 'DECIMAL(18,2) NULL DEFAULT NULL', 'ساعات الدورة'),
        array('last_preventive_meter', 'VARCHAR(190) NULL DEFAULT NULL', 'قراءة آخر وقائية'),
        array('current_meter_reading', 'VARCHAR(190) NULL DEFAULT NULL', 'قراءة العداد الحالية'),
        array('remaining_to_due', 'DATE NULL DEFAULT NULL', 'المتبقي للاستحقاق'),
        array('expected_due_date', 'DATE NULL DEFAULT NULL', 'تاريخ الاستحقاق المتوقع'),
        array('standard_cycle_items', 'VARCHAR(190) NULL DEFAULT NULL', 'بنود الدورة القياسية'),
        array('due_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الاستحقاق'),
        array('generated_order_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الأمر المتولد'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'org_units' => array(
        array('offer_status', 'VARCHAR(60) NULL DEFAULT NULL', 'وضع العرض'),
        array('deputy_role', 'VARCHAR(190) NULL DEFAULT NULL', 'Deputy_Role'),
        array('out_of_scope_read_only', 'VARCHAR(190) NULL DEFAULT NULL', 'خارج النطاق قراءة فقط'),
        array('kpi', 'VARCHAR(190) NULL DEFAULT NULL', 'KPI'),
        array('pending_requests', 'VARCHAR(190) NULL DEFAULT NULL', 'Pending_Requests'),
        array('overdue_actions', 'VARCHAR(190) NULL DEFAULT NULL', 'Overdue_Actions'),
        array('critical_risks', 'VARCHAR(190) NULL DEFAULT NULL', 'Critical_Risks'),
        array('budget_status', 'VARCHAR(60) NULL DEFAULT NULL', 'Budget_Status'),
        array('daily_report_status', 'VARCHAR(60) NULL DEFAULT NULL', 'Daily_Report_Status'),
        array('monthly_close_status', 'VARCHAR(60) NULL DEFAULT NULL', 'Monthly_Close_Status'),
        array('compliance_status', 'VARCHAR(60) NULL DEFAULT NULL', 'Compliance_Status'),
        array('download_link', 'VARCHAR(190) NULL DEFAULT NULL', 'رابط النزول'),
    ),
    'proc_count_session' => array(
        array('session_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الجلسة'),
        array('count_committee', 'VARCHAR(190) NULL DEFAULT NULL', 'لجنة الجرد'),
        array('counted_items_count', 'DECIMAL(18,2) NULL DEFAULT NULL', 'عدد الأصناف المجرودة'),
        array('variance_items_ref_kh10_2', 'VARCHAR(190) NULL DEFAULT NULL', 'بنود الفروق تفصيلها خ10-2'),
        array('investigation_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع التحقيق'),
        array('session_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الجلسة'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('reviewer', 'VARCHAR(190) NULL DEFAULT NULL', 'المراجع'),
        array('approver', 'VARCHAR(190) NULL DEFAULT NULL', 'المعتمد'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'proc_package' => array(
        array('package_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الحزمة'),
        array('grouping_period', 'VARCHAR(190) NULL DEFAULT NULL', 'فترة التجميع'),
        array('package_scope', 'VARCHAR(60) NULL DEFAULT NULL', 'نطاق الحزمة'),
        array('bundled_requests', 'VARCHAR(190) NULL DEFAULT NULL', 'الطلبات المضمومة'),
        array('items_count', 'DECIMAL(18,2) NULL DEFAULT NULL', 'عدد البنود'),
        array('total_estimate', 'VARCHAR(190) NULL DEFAULT NULL', 'التقدير الإجمالي'),
        array('single_pass_justification', 'VARCHAR(500) NULL DEFAULT NULL', 'مبرر التمرير المنفرد'),
        array('purchase_channel', 'VARCHAR(190) NULL DEFAULT NULL', 'قناة الشراء'),
        array('package_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الحزمة'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
        array('membership_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف العضوية'),
        array('purchase_request_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم طلب الشراء'),
        array('covered_request_items', 'VARCHAR(190) NULL DEFAULT NULL', 'بنود الطلب المشمولة'),
        array('bundling_date', 'DATE NULL DEFAULT NULL', 'تاريخ الضم'),
        array('membership_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة العضوية'),
    ),
    'proc_po_amendment' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('type', 'VARCHAR(190) NULL DEFAULT NULL', 'النوع'),
        array('order_or_request_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الأمر/الطلب'),
        array('justification', 'VARCHAR(190) NULL DEFAULT NULL', 'المبرر'),
        array('active_aam_rule', 'VARCHAR(190) NULL DEFAULT NULL', 'قاعدة AAM المفعلة'),
        array('approval_path', 'VARCHAR(190) NULL DEFAULT NULL', 'مسار الموافقة'),
        array('approval_decision', 'VARCHAR(60) NULL DEFAULT NULL', 'قرار الاعتماد'),
        array('financial_impact', 'VARCHAR(190) NULL DEFAULT NULL', 'الأثر المالي'),
        array('affected_items', 'VARCHAR(190) NULL DEFAULT NULL', 'بنود متأثرة'),
        array('line_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة السطر'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('reviewer', 'VARCHAR(190) NULL DEFAULT NULL', 'المراجع'),
        array('approver', 'VARCHAR(190) NULL DEFAULT NULL', 'المعتمد'),
        array('approval_date', 'DATE NULL DEFAULT NULL', 'تاريخ الاعتماد'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'proc_stock_state' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('item_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود الصنف'),
        array('balance_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الرصيد'),
        array('quantity', 'DECIMAL(18,2) NULL DEFAULT NULL', 'الكمية'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('below_minimum', 'TINYINT(1) NULL DEFAULT NULL', 'تحت الحد الأدنى؟'),
    ),
    'quotations' => array(
        array('internal_offer_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العرض الداخلي'),
        array('opportunity_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الفرصة'),
        array('request_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الطلب'),
        array('client_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العميل'),
        array('client_name_search', 'VARCHAR(190) NULL DEFAULT NULL', 'اسم العميل (بحث)'),
        array('project_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم المشروع'),
        array('official_offer_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العرض الرسمي'),
        array('date_basis', 'VARCHAR(190) NULL DEFAULT NULL', 'أساس التاريخ'),
        array('work_model', 'VARCHAR(60) NULL DEFAULT NULL', 'نموذج العمل'),
        array('validity_period', 'VARCHAR(190) NULL DEFAULT NULL', 'مدة السريان'),
        array('payment_or_billing_terms', 'VARCHAR(500) NULL DEFAULT NULL', 'شروط الدفع/الفوترة'),
        array('offer_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة العرض'),
        array('client_response', 'VARCHAR(190) NULL DEFAULT NULL', 'رد العميل'),
        array('decision_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة القرار'),
        array('decision_date', 'DATE NULL DEFAULT NULL', 'تاريخ القرار'),
        array('offer_value_usd', 'VARCHAR(190) NULL DEFAULT NULL', 'قيمة العرض ($)'),
        array('offer_value_sdg', 'VARCHAR(190) NULL DEFAULT NULL', 'قيمة العرض (ج.س)'),
        array('resulting_contract_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع العقد الناتج'),
        array('source_commitment_cycle_key', 'VARCHAR(80) NULL DEFAULT NULL', 'مفتاح دورة الالتزام المصدر'),
        array('evidence_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'مستوى الحجية'),
        array('residual_value_basis', 'VARCHAR(190) NULL DEFAULT NULL', 'أساس القيمة الرجعية'),
        array('event_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الواقعة'),
        array('record_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع السجل'),
        array('offer_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم العرض'),
        array('contract_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع العقد'),
        array('new_commitment_cycle', 'VARCHAR(190) NULL DEFAULT NULL', 'دورة الالتزام الجديدة'),
        array('previous_commitment_cycle', 'VARCHAR(190) NULL DEFAULT NULL', 'دورة الالتزام السابقة'),
        array('comparison_scope', 'VARCHAR(60) NULL DEFAULT NULL', 'نطاق المقارنة'),
        array('date_value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'التاريخ'),
        array('change_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع التغيير'),
        array('commercial_impact', 'VARCHAR(190) NULL DEFAULT NULL', 'الأثر التجاري'),
        array('reference_document', 'VARCHAR(190) NULL DEFAULT NULL', 'الوثيقة المرجعية'),
        array('reason_or_evidence', 'VARCHAR(190) NULL DEFAULT NULL', 'السبب/الدليل'),
        array('requesting_party', 'VARCHAR(190) NULL DEFAULT NULL', 'الطرف الطالب'),
    ),
    'rec_applications' => array(
        array('vacancy_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الشاغر'),
        array('opening_date', 'DATE NULL DEFAULT NULL', 'تاريخ الفتح'),
        array('job_title', 'VARCHAR(190) NULL DEFAULT NULL', 'المسمى الوظيفي'),
        array('required_headcount', 'VARCHAR(190) NULL DEFAULT NULL', 'عدد المطلوبين'),
        array('vacancy_requirements', 'VARCHAR(500) NULL DEFAULT NULL', 'اشتراطات الشاغر'),
        array('candidates', 'VARCHAR(190) NULL DEFAULT NULL', 'المرشحون'),
        array('accepted_candidate', 'VARCHAR(190) NULL DEFAULT NULL', 'المرشح المقبول'),
        array('practical_test_result', 'VARCHAR(60) NULL DEFAULT NULL', 'نتيجة الاختبار العملي'),
        array('submitted_offer', 'VARCHAR(190) NULL DEFAULT NULL', 'العرض المقدم'),
        array('vacancy_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الشاغر'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('reviewer', 'VARCHAR(190) NULL DEFAULT NULL', 'المراجع'),
        array('approver', 'VARCHAR(190) NULL DEFAULT NULL', 'المعتمد'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'risk_export_log' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('frequency', 'VARCHAR(190) NULL DEFAULT NULL', 'الدورية'),
        array('period', 'VARCHAR(190) NULL DEFAULT NULL', 'الفترة'),
        array('family', 'VARCHAR(190) NULL DEFAULT NULL', 'العائلة'),
        array('item', 'VARCHAR(190) NULL DEFAULT NULL', 'البند'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('direction', 'VARCHAR(190) NULL DEFAULT NULL', 'الاتجاه'),
        array('needs_decision', 'TINYINT(1) NULL DEFAULT NULL', 'يستلزم قرارا؟'),
        array('risk_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع الخطر'),
    ),
    'tickets' => array(
        array('report_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم البلاغ'),
        array('registration_time', 'DATETIME NULL DEFAULT NULL', 'وقت التسجيل'),
        array('registration_channel', 'VARCHAR(190) NULL DEFAULT NULL', 'قناة التسجيل'),
        array('reporter_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'Reporter_ID'),
        array('reporter_name', 'VARCHAR(190) NULL DEFAULT NULL', 'Reporter_Name'),
        array('reporter_department', 'VARCHAR(190) NULL DEFAULT NULL', 'Reporter_Department'),
        array('reporter_entity', 'VARCHAR(190) NULL DEFAULT NULL', 'Reporter_Entity'),
        array('subject_type', 'VARCHAR(60) NULL DEFAULT NULL', 'Subject_Type'),
        array('subject_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'Subject_ID'),
        array('subject_name', 'VARCHAR(190) NULL DEFAULT NULL', 'Subject_Name'),
        array('subject_owning_department', 'VARCHAR(190) NULL DEFAULT NULL', 'Subject_Owning_Department'),
        array('category', 'VARCHAR(190) NULL DEFAULT NULL', 'الفئة'),
        array('nature', 'VARCHAR(190) NULL DEFAULT NULL', 'الطبيعة'),
        array('priority_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'الأولوية'),
        array('confidentiality_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'مستوى السرية'),
        array('report_description', 'VARCHAR(500) NULL DEFAULT NULL', 'وصف البلاغ'),
        array('attachments', 'VARCHAR(190) NULL DEFAULT NULL', 'المرفقات'),
        array('ticket_owner', 'VARCHAR(190) NULL DEFAULT NULL', 'Ticket_Owner'),
        array('assigned_department', 'VARCHAR(190) NULL DEFAULT NULL', 'Assigned_Department'),
        array('resolution_owner', 'VARCHAR(190) NULL DEFAULT NULL', 'Resolution_Owner'),
        array('processing_deadline', 'VARCHAR(190) NULL DEFAULT NULL', 'مهلة المعالجة'),
        array('report_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البلاغ'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('offer_scope', 'VARCHAR(60) NULL DEFAULT NULL', 'نطاق العرض'),
        array('registration_date', 'DATE NULL DEFAULT NULL', 'تاريخ التسجيل'),
        array('report_subject', 'VARCHAR(190) NULL DEFAULT NULL', 'محل البلاغ'),
        array('entity_created_in_our_dept', 'VARCHAR(190) NULL DEFAULT NULL', 'الكيان المنشأ في إدارتنا'),
        array('sla_deadline', 'VARCHAR(190) NULL DEFAULT NULL', 'مهلة SLA'),
        array('remaining_or_delay', 'VARCHAR(190) NULL DEFAULT NULL', 'المتبقي/التأخير'),
        array('awaiting_verification', 'TINYINT(1) NULL DEFAULT NULL', 'ينتظر تحققا؟'),
    ),
    'transfer_orders' => array(
        array('order_date', 'DATE NULL DEFAULT NULL', 'تاريخ الأمر'),
        array('request_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الطلب'),
        array('cargo_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع الحمولة'),
        array('equipment_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود المعدة'),
        array('from_location', 'VARCHAR(190) NULL DEFAULT NULL', 'من موقع'),
        array('to_location', 'VARCHAR(190) NULL DEFAULT NULL', 'إلى موقع'),
        array('planned_distance', 'DECIMAL(18,2) NULL DEFAULT NULL', 'المسافة التقديرية'),
        array('transport_mode', 'VARCHAR(60) NULL DEFAULT NULL', 'وسيلة النقل'),
        array('carrier', 'VARCHAR(190) NULL DEFAULT NULL', 'الناقل'),
        array('carrier_contract', 'VARCHAR(190) NULL DEFAULT NULL', 'عقد الناقل'),
        array('driver', 'VARCHAR(190) NULL DEFAULT NULL', 'السائق'),
        array('driver_licence_valid', 'TINYINT(1) NULL DEFAULT NULL', 'رخصة السائق سارية؟'),
        array('planned_route', 'VARCHAR(190) NULL DEFAULT NULL', 'المسار المقرر'),
        array('planned_departure_date', 'DATE NULL DEFAULT NULL', 'تاريخ المغادرة المخطط'),
        array('planned_arrival_date', 'DATE NULL DEFAULT NULL', 'تاريخ الوصول المخطط'),
        array('required_permits', 'VARCHAR(190) NULL DEFAULT NULL', 'التصاريح المطلوبة'),
        array('order_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الأمر'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('reviewer', 'VARCHAR(190) NULL DEFAULT NULL', 'المراجع'),
        array('approver', 'VARCHAR(190) NULL DEFAULT NULL', 'المعتمد'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
        array('business_event_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الحدث'),
        array('event_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع الحدث'),
        array('event_time', 'DATETIME NULL DEFAULT NULL', 'وقت الحدث'),
        array('geo_location', 'VARCHAR(190) NULL DEFAULT NULL', 'الموقع الجغرافي'),
        array('carrier_meter_reading', 'VARCHAR(190) NULL DEFAULT NULL', 'قراءة عداد الناقل'),
        array('event_note', 'VARCHAR(500) NULL DEFAULT NULL', 'ملاحظة الحدث'),
        array('attachment_or_photo', 'VARCHAR(190) NULL DEFAULT NULL', 'مرفق/صورة'),
        array('recorded_offline', 'TINYINT(1) NULL DEFAULT NULL', 'مسجل دون اتصال؟'),
        array('line_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة السطر'),
    ),
    'tre_beneficiaries' => array(
        array('beneficiary_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المستفيد'),
        array('beneficiary_name', 'VARCHAR(190) NULL DEFAULT NULL', 'اسم المستفيد'),
        array('beneficiary_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع المستفيد'),
        array('account_or_iban', 'VARCHAR(190) NULL DEFAULT NULL', 'رقم الحساب/IBAN'),
        array('verification_document', 'VARCHAR(190) NULL DEFAULT NULL', 'وثيقة التحقق'),
        array('verification_date', 'DATE NULL DEFAULT NULL', 'تاريخ التحقق'),
        array('independent_verifier', 'VARCHAR(190) NULL DEFAULT NULL', 'محقق مستقل'),
        array('pending_account_change', 'TINYINT(1) NULL DEFAULT NULL', 'تغيير حساب معلق؟'),
        array('verification_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة التحقق'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'trp_kpi_period' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
    ),
);

$made = 0; $skip = 0; $fail = 0;
foreach ($SPEC as $tbl => $cols) {
    $have = ems_sm_cols($conn, $tbl);
    if (!$have) { echo "  \u{26D4} \u{62C}\u{62F}\u{648}\u{644} \u{63A}\u{627}\u{626}\u{628}: {$tbl}\n"; $fail += count($cols); continue; }
    $parts = array();
    foreach ($cols as $c) {
        if (isset($have[strtolower($c[0])])) { $skip++; continue; }
        $parts[] = "ADD COLUMN `" . $c[0] . "` " . $c[1]
                 . " COMMENT '" . $conn->real_escape_string($c[2]) . "'";
    }
    if (!$parts) { continue; }
    $sql = "ALTER TABLE `{$tbl}` " . implode(', ', $parts);
    if ($conn->query($sql)) {
        $made += count($parts);
        printf("  \u{2714} %-26s +%d\n", $tbl, count($parts));
    } else {
        $fail += count($parts);
        printf("  \u{26D4} %-26s %s\n", $tbl, $conn->error);
    }
}
printf("\n\u{623}\u{639}\u{645}\u{62F}\u{629}: \u{623}\u{646}\u{634}\u{626}\u{62A} %d \u{00B7} \u{642}\u{627}\u{626}\u{645}\u{629} %d \u{00B7} \u{641}\u{627}\u{634}\u{644}\u{629} %d\n", $made, $skip, $fail);
if ($fail > 0) { exit("\n\u{26D4} \u{633}\u{642}\u{637} \u{639}\u{645}\u{648}\u{62F} \u{2014} \u{644}\u{627} \u{64A}\u{064F}\u{639}\u{62A}\u{645}\u{62F}.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\u{2714} \u{62A}\u{645}\u{651} \u{2014} \u{62D}\u{642}\u{644}\u{064F} \u{627}\u{644}\u{648}\u{631}\u{642}\u{629} \u{635}\u{627}\u{631} \u{639}\u{645}\u{648}\u{62F}\u{064B}\u{627} \u{641}\u{064A} \u{62C}\u{62F}\u{648}\u{644} \u{634}\u{627}\u{634}\u{62A}\u{647}.\n";
