<?php
/**
 * 2028_06_03_sheet_merge_fields_b3.php — حقولُ «سجل حقول الورقة» تصير أعمدةً في جدولِ الشاشة
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
 * ◆ **ولا يُنشأ عمودٌ لحقلٍ له نظيرٌ بالاسمِ نفسِه**: حقلان وجدا
 *   عمودَها قائمًا في جدولِ الشاشة، **فتُغذّى منه** ولا يُصنَع لها توأم.
 *
 * ⛔ **والمدى محسوم**: سبعَ عشرةَ شاشةً **قُطع فيها جدولُها الحقيقيُّ بشاهدَين**
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
    'achievement_records' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('account', 'VARCHAR(190) NULL DEFAULT NULL', 'الحساب'),
        array('range_label', 'VARCHAR(190) NULL DEFAULT NULL', 'المدى'),
        array('achievement_indicator', 'VARCHAR(190) NULL DEFAULT NULL', 'مؤشر الإنجاز'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('unit', 'VARCHAR(190) NULL DEFAULT NULL', 'الوحدة'),
        array('in_role_language', 'VARCHAR(190) NULL DEFAULT NULL', 'بلغة الدور'),
        array('vs_previous_range', 'VARCHAR(190) NULL DEFAULT NULL', 'مقارنة بالمدى السابق'),
        array('last_update', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر تحديث'),
    ),
    'exec_approvals' => array(
        array('item_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف البند'),
        array('source', 'VARCHAR(190) NULL DEFAULT NULL', 'المصدر'),
        array('action_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع الفعل'),
        array('delay_days', 'DECIMAL(18,2) NULL DEFAULT NULL', 'أيام التأخير'),
        array('priority_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'الأولوية'),
        array('state', 'VARCHAR(190) NULL DEFAULT NULL', 'الحالة'),
    ),
    'fin_budget_lines' => array(
        array('indicator_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المؤشر'),
        array('kpi_catalog_indicator', 'VARCHAR(190) NULL DEFAULT NULL', 'المؤشر KPI Catalog'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('unit', 'VARCHAR(190) NULL DEFAULT NULL', 'الوحدة'),
        array('currency', 'VARCHAR(190) NULL DEFAULT NULL', 'العملة'),
        array('last_update', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر تحديث'),
    ),
    'fin_chart_of_accounts' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('list_name', 'VARCHAR(190) NULL DEFAULT NULL', 'القائمة'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('comparison_period', 'VARCHAR(190) NULL DEFAULT NULL', 'فترة المقارنة'),
        array('disclosure_note', 'VARCHAR(500) NULL DEFAULT NULL', 'ملاحظة إفصاح'),
    ),
    'proc_request' => array(
        array('indicator_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المؤشر'),
        array('kpi_catalog_indicator', 'VARCHAR(190) NULL DEFAULT NULL', 'المؤشر KPI Catalog'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('unit', 'VARCHAR(190) NULL DEFAULT NULL', 'الوحدة'),
        array('last_update', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر تحديث'),
    ),
    'proc_rfq' => array(
        array('invitation_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الدعوة'),
        array('rfq_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف RFQ'),
        array('supplier_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم المورد'),
        array('invitation_date', 'DATE NULL DEFAULT NULL', 'تاريخ الدعوة'),
        array('receipt_confirmation', 'VARCHAR(190) NULL DEFAULT NULL', 'تأكيد الاستلام'),
        array('response', 'VARCHAR(190) NULL DEFAULT NULL', 'الاستجابة'),
        array('incoming_offer_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع العرض الوارد'),
        array('invitation_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الدعوة'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'scr_attendance' => array(
        array('record_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السجل'),
        array('employee_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الموظف'),
        array('overtime_hours', 'DECIMAL(18,2) NULL DEFAULT NULL', 'ساعات إضافية'),
        array('mission_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المأمورية'),
        array('leave_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع الإجازة'),
        array('registration_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدر التسجيل'),
        array('note', 'VARCHAR(500) NULL DEFAULT NULL', 'ملاحظة'),
        array('record_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة السجل'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'scr_monthly_close' => array(
        array('closing_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الإقفال'),
        array('closing_month', 'VARCHAR(60) NULL DEFAULT NULL', 'شهر الإقفال'),
        array('month_days', 'DECIMAL(18,2) NULL DEFAULT NULL', 'أيام الشهر'),
        array('fully_approved_days', 'DECIMAL(18,2) NULL DEFAULT NULL', 'أيام معتمدة كاملا'),
        array('incomplete_days', 'DECIMAL(18,2) NULL DEFAULT NULL', 'أيام ناقصة'),
        array('pending_records', 'VARCHAR(190) NULL DEFAULT NULL', 'سجلات معلقة'),
        array('open_stop_decisions', 'VARCHAR(190) NULL DEFAULT NULL', 'قرارات توقف مفتوحة'),
        array('approved_units_total', 'DECIMAL(18,2) NULL DEFAULT NULL', 'إجمالي الوحدات المعتمدة'),
        array('actual_hours_total', 'DECIMAL(18,2) NULL DEFAULT NULL', 'إجمالي ساعات الفعلي'),
        array('downtime_total', 'DECIMAL(18,2) NULL DEFAULT NULL', 'إجمالي التوقف'),
        array('plan_achievement_rate', 'DECIMAL(18,2) NULL DEFAULT NULL', 'نسبة تحقق الخطة'),
        array('carried_exceptions_list', 'VARCHAR(190) NULL DEFAULT NULL', 'قائمة الاستثناءات المرحلة'),
        array('closing_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الإقفال'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('reviewer', 'VARCHAR(190) NULL DEFAULT NULL', 'المراجع'),
        array('approver', 'VARCHAR(190) NULL DEFAULT NULL', 'المعتمد'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'scr_project_contracts' => array(
        array('line_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف السطر'),
        array('contract_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف العقد'),
        array('employee_no', 'VARCHAR(80) NULL DEFAULT NULL', 'رقم الموظف'),
        array('project_link_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'Project_ID المشروع الرابط الأساسي'),
        array('reference_hiring_need', 'VARCHAR(190) NULL DEFAULT NULL', 'احتياج التوظيف المرجعي'),
        array('supplier_contract_as_trigger', 'VARCHAR(190) NULL DEFAULT NULL', 'عقد المورد محفزا لا رابطا'),
        array('agreed_end_trigger', 'VARCHAR(190) NULL DEFAULT NULL', 'محفز الانتهاء المتفق'),
        array('actual_end_trigger', 'VARCHAR(190) NULL DEFAULT NULL', 'محفز الانتهاء الواقع'),
        array('occurrence_date', 'DATE NULL DEFAULT NULL', 'تاريخ الوقوع'),
        array('notice_period', 'VARCHAR(190) NULL DEFAULT NULL', 'فترة الإخطار'),
        array('settlement_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع التصفية'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'scr_site_gate_equip' => array(
        array('entity_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع الكيان'),
        array('entity_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع الكيان'),
        array('movement_direction', 'VARCHAR(190) NULL DEFAULT NULL', 'اتجاه الحركة'),
        array('movement_time', 'DATETIME NULL DEFAULT NULL', 'وقت الحركة'),
        array('site_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود الموقع'),
        array('active_allocation_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع التخصيص الساري'),
        array('allocation_match', 'VARCHAR(60) NULL DEFAULT NULL', 'مطابقة التخصيص'),
        array('escort_or_driver', 'VARCHAR(190) NULL DEFAULT NULL', 'مرافق/سائق'),
        array('purpose', 'VARCHAR(190) NULL DEFAULT NULL', 'الغرض'),
        array('pass_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدر الإذن'),
        array('event_without_pass', 'TINYINT(1) NULL DEFAULT NULL', 'واقعة بلا إذن؟'),
        array('pass_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الإذن'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('created_date', 'DATE NULL DEFAULT NULL', 'تاريخ الإنشاء'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'scr_transfer_fleet' => array(
        array('carrier_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود الناقلة'),
        array('rental_supplier_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المورد عند التأجير'),
        array('weight_capacity_tons', 'VARCHAR(190) NULL DEFAULT NULL', 'السعة الوزنية (طن)'),
        array('allowed_length_and_width', 'VARCHAR(190) NULL DEFAULT NULL', 'الطول والعرض المسموحان'),
        array('axles_count', 'DECIMAL(18,2) NULL DEFAULT NULL', 'عدد المحاور'),
        array('road_licence_and_expiry', 'VARCHAR(190) NULL DEFAULT NULL', 'رخصة السير وانتهاؤها'),
        array('cargo_insurance_and_expiry', 'VARCHAR(190) NULL DEFAULT NULL', 'تأمين البضاعة وانتهاؤه'),
        array('valid_route_permits', 'VARCHAR(190) NULL DEFAULT NULL', 'تصاريح المسار السارية'),
        array('cargo_match_check', 'VARCHAR(60) NULL DEFAULT NULL', 'فحص المطابقة مع الحمولة'),
        array('carrier_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الناقلة'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'scr_workshop' => array(
        array('capability_code', 'VARCHAR(80) NULL DEFAULT NULL', 'كود القدرة'),
        array('type', 'VARCHAR(190) NULL DEFAULT NULL', 'النوع'),
        array('name', 'VARCHAR(190) NULL DEFAULT NULL', 'الاسم'),
        array('location', 'VARCHAR(190) NULL DEFAULT NULL', 'الموقع'),
        array('specialities', 'VARCHAR(190) NULL DEFAULT NULL', 'التخصصات'),
        array('technician_level', 'DECIMAL(18,2) NULL DEFAULT NULL', 'مستوى الفني'),
        array('certificates_and_validity', 'VARCHAR(190) NULL DEFAULT NULL', 'الشهادات وصلاحيتها'),
        array('daily_capacity', 'DECIMAL(18,2) NULL DEFAULT NULL', 'الطاقة اليومية (ساعات/أوامر)'),
        array('available_now', 'TINYINT(1) NULL DEFAULT NULL', 'متاح الآن؟'),
        array('reporting_line', 'VARCHAR(190) NULL DEFAULT NULL', 'التبعية'),
        array('external_contract_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع العقد عند الخارجي'),
        array('capability_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة القدرة'),
        array('creator_name', 'VARCHAR(190) NULL DEFAULT NULL', 'المنشئ'),
        array('data_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة البيانات'),
        array('source_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع المصدر'),
    ),
    'tre_cash_move' => array(
        array('indicator_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المؤشر'),
        array('kpi_catalog_indicator', 'VARCHAR(190) NULL DEFAULT NULL', 'المؤشر KPI Catalog'),
        array('value', 'DECIMAL(18,2) NULL DEFAULT NULL', 'القيمة'),
        array('state', 'VARCHAR(190) NULL DEFAULT NULL', 'الحالة'),
        array('last_update', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر تحديث'),
    ),
    'user_capacities' => array(
        array('capacity_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الصفة'),
        array('her_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدرها'),
        array('range_to', 'DATE NULL DEFAULT NULL', 'إلى'),
        array('active_now', 'TINYINT(1) NULL DEFAULT NULL', 'نشطة الآن؟'),
        array('delegation_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع التفويض عند الإنابة'),
        array('capacity_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة الصفة'),
    ),
    'work_items' => array(
        array('task_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المهمة'),
        array('task_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع المهمة'),
        array('task_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدر المهمة'),
        array('origin_screen', 'VARCHAR(190) NULL DEFAULT NULL', 'الشاشة الأصلية'),
        array('reference', 'VARCHAR(190) NULL DEFAULT NULL', 'المرجع'),
        array('task_deadline', 'VARCHAR(190) NULL DEFAULT NULL', 'مهلة المهمة'),
        array('task_state', 'VARCHAR(60) NULL DEFAULT NULL', 'حالة المهمة'),
        array('postponement_reason', 'VARCHAR(500) NULL DEFAULT NULL', 'سبب التأجيل'),
        array('component_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف المكون'),
        array('account', 'VARCHAR(190) NULL DEFAULT NULL', 'الحساب'),
        array('role_label', 'VARCHAR(190) NULL DEFAULT NULL', 'الدور'),
        array('component', 'VARCHAR(190) NULL DEFAULT NULL', 'المكون'),
        array('live_content', 'VARCHAR(190) NULL DEFAULT NULL', 'محتواه الحي'),
        array('its_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدره'),
        array('last_update', 'VARCHAR(190) NULL DEFAULT NULL', 'آخر تحديث'),
    ),
    'worker_qualification' => array(
        array('business_event_uid', 'VARCHAR(80) NULL DEFAULT NULL', 'معرف الحدث'),
        array('event_time', 'DATETIME NULL DEFAULT NULL', 'وقت الحدث'),
        array('event_type', 'VARCHAR(60) NULL DEFAULT NULL', 'نوع الحدث'),
        array('its_source', 'VARCHAR(190) NULL DEFAULT NULL', 'مصدره'),
        array('project_label', 'VARCHAR(190) NULL DEFAULT NULL', 'المشروع'),
        array('location', 'VARCHAR(190) NULL DEFAULT NULL', 'الموقع'),
        array('equipment', 'VARCHAR(190) NULL DEFAULT NULL', 'المعدة'),
        array('event_summary', 'VARCHAR(190) NULL DEFAULT NULL', 'ملخص الحدث'),
        array('origin_record_ref', 'VARCHAR(80) NULL DEFAULT NULL', 'مرجع السجل الأصلي'),
        array('importance_degree', 'DECIMAL(18,2) NULL DEFAULT NULL', 'درجة الأهمية'),
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
