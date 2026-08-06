<?php
/**
 * tools/cmp03_wave2_generate.php — مولّد الموجة ٢: جداول الشاشات الأصلية + سجل الربط
 * ───────────────────────────────────────────────────────────────────────────
 * لكل شاشةٍ باقيةٍ على المخزن البيني cmp03_screen_rows (خارج حارة الجلسة
 * الموازية): يولّد جدولها الأصلي `scr_<اسمها>` بأعمدةٍ دلاليةٍ مشتقةٍ من
 * تسميات حقولها عبر قاموس كلمة←رمز (fail-closed: كلمة خارج القاموس توقف
 * التوليد)، ويكتب:
 *   • database/migrations/2026_11_14_cmp03_wave2_tables.sql (DDL إضافي فقط)
 *   • includes/cmp03_registry.php (canonical ← جدول + خريطة تسمية←عمود)
 * التشغيل: php tools/cmp03_wave2_generate.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
$ROOT = dirname(__DIR__);

/* ── الشاشات المستهدفة (مطابق للماسح — الحارة الموازية مستبعدة) ─────────── */
$SKIP = array('Procurement/po_match.php', 'Procurement/wh_receipt.php', 'Suppliers/supplier_bank.php');
$files = array();
foreach (explode("\n", trim((string) shell_exec('git -C "' . $ROOT . '" grep -l cmp03_screen_rows -- "*.php"'))) as $f) {
    $f = trim($f);
    if ($f === '' || strpos($f, 'tools/') === 0 || strpos($f, 'database/') === 0
        || strpos($f, 'includes/') === 0 || strpos($f, 'app/') === 0
        || basename($f) === 'cron_wfm_engine.php' || basename($f) === 'gov_reports.php') { continue; }
    if (in_array($f, $SKIP, true)) { continue; }
    $files[] = $f;
}

/* ── تطبيع التسمية (يوافق cmp03_screen_norm) ────────────────────────────── */
function w2_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    return preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
}

/* ── تسميات كاملة ← أعمدة (الحاكمة أولًا — عرف جداول exec_*) ─────────────── */
$LABEL_MAP = array(
    'الحالة' => 'status_label', 'تاريخ الاعتماد' => 'approved_date',
    'المعتمد — الاسم والصفة' => 'approver_name', 'المعتمِد — الاسم والصفة' => 'approver_name',
    'مرجع التفويض' => 'authority_ref', 'المرفق' => 'attachment', 'المرجع الأب' => 'parent_ref',
    'مركز التكلفة' => 'cost_center', 'سعر الصرف ومصدره' => 'fx_rate_source', 'العملة' => 'currency',
    'درجة الأثر' => 'impact_grade', 'المستند المرفق' => 'attached_doc',
    'المنشئ — الاسم والصفة' => 'creator_name', 'المُنشئ — الاسم والصفة' => 'creator_name',
    'التاريخ والوقت' => 'happened_at', 'التاريخ' => 'entry_date', 'الشهر' => 'month_ref',
    'الفترة' => 'period_ref', 'الأسبوع' => 'week_ref', 'الموقع' => 'site_name', 'المشروع' => 'project_name',
    'المعدة' => 'equipment_name', 'المورد' => 'supplier_name', 'العقد' => 'contract_ref',
    'الوردية' => 'shift_name', 'الوحدة' => 'unit_name', 'المسؤول' => 'responsible_name',
    'الملاحظات' => 'notes', 'الاسم' => 'name_ar', 'النوع' => 'kind', 'الوصف' => 'description',
    'الملكية' => 'ownership_kind', 'المالك' => 'owner_name', 'الرخصة' => 'license_ref',
    'التأمين' => 'insurance_ref', 'التعرفة' => 'tariff', 'الرسوم' => 'fees', 'الأساس' => 'basis',
    'المعادلة' => 'formula', 'المسير' => 'payroll_ref', 'الرقم' => 'item_no', 'الكمية' => 'qty',
    'القيمة' => 'amount', 'الفرق' => 'variance', 'الفارق' => 'variance', 'النسبة' => 'pct',
    'الغياب' => 'absent_count', 'المسلم' => 'handed_by', 'المستلم' => 'received_by',
    'المدخل' => 'entered_by', 'المدخلون' => 'entered_by_list', 'المقيم' => 'assessor_name',
    'الفاحص' => 'inspector_name', 'الفني' => 'technician_name', 'الورشة' => 'workshop_name',
    'الوظيفة' => 'job_title', 'التخصص' => 'specialty', 'الموديل' => 'model_ref',
    'المسمى' => 'position_title', 'الممول' => 'financier_name', 'الجدول' => 'table_name',
    'الحقل' => 'field_name', 'الشاشة' => 'screen_ref', 'الفعل' => 'action_ref',
    'الحساب' => 'account_ref', 'الشخص' => 'person_name', 'الصنف' => 'category',
    'النطاق' => 'scope_ref', 'النسخة' => 'version_no', 'المخول' => 'authorized_role',
    'الإدارة' => 'dept_name', 'العميل' => 'client_name', 'المتبقي من حصة المورد' => 'supplier_share_remaining',
    'في الأسطول المشغل؟' => 'in_active_fleet', 'قابل للعكس؟' => 'reversible_flag',
    'يحتاج اعتمادا؟' => 'needs_approval_flag', 'له أثر مالي؟' => 'has_fin_impact_flag',
    'يسجل الاطلاع؟' => 'log_views_flag', 'يصدر؟' => 'exportable_flag',
    'يحجب الاعتماد؟' => 'blocks_approval_flag', 'تضارب مصالح مكتشف؟' => 'conflict_found_flag',
    'الساعات حسب التايم شيت' => 'hours_per_timesheet', 'الساعات حسب سجل الأصول' => 'hours_per_asset_log',
    'الساعات حسب سجلنا' => 'hours_per_our_log', 'الساعات المعتمدة من العميل' => 'hours_client_approved',
    'الكمية حسب سجلنا' => 'qty_per_our_log', 'الكمية المعتمدة من العميل' => 'qty_client_approved',
    'رأس المال المموَّل' => 'financed_capital', 'رأس المال قبل' => 'capital_before',
    'رأس المال بعد' => 'capital_after', 'نسبة من الصافي' => 'pct_of_net',
    'الطرف المتحمل الأغلب' => 'main_bearing_party', 'العجز عن التعاقدي' => 'contract_shortfall',
    'حالة قيد المئة' => 'sum100_state', 'مجموع النسب النشطة' => 'active_pct_total',
    'الموافق الأول' => 'approver_first', 'الموافق الثاني' => 'approver_second',
    'الموافق على الفتح' => 'open_approver', 'الموافق على الإغلاق' => 'close_approver',
    'مصدر المنح 1' => 'grant_source_1', 'مصدر المنح 2' => 'grant_source_2',
    'محفز الانتهاء 1' => 'end_trigger_1', 'محفز الانتهاء 2' => 'end_trigger_2',
    'محفز الانتهاء 3' => 'end_trigger_3', 'المحفز الواقع' => 'end_trigger_hit',
    'مهلة التنبيه قبل الانتهاء' => 'expiry_alert_lead', 'البديل عند تعذر التبادل' => 'fallback_substitute',
    'ساعات التوقف — عميل' => 'downtime_hours_client', 'ساعات التوقف — مورد' => 'downtime_hours_supplier',
    'ساعات التوقف — نحن' => 'downtime_hours_ours', 'من الساعة' => 'time_from', 'إلى الساعة' => 'time_to',
    'من تاريخ' => 'date_from', 'إلى تاريخ' => 'date_to', 'المدة من' => 'period_from', 'المدة إلى' => 'period_to',
);

/* ── قاموس كلمة ← رمز (بعد نزع أل والتشكيل) ─────────────────────────────── */
$W = array(
'1'=>'1','2'=>'2','3'=>'3','آخر'=>'last','آلة'=>'machine','آليا'=>'auto','أب'=>'parent','أثر'=>'impact',
'أجر'=>'wage','أرشفة'=>'archiving','أساس'=>'basis','أسبوع'=>'week','أسبوعية'=>'weekly','أسطول'=>'fleet',
'أصل'=>'asset','أصول'=>'assets','أعده'=>'prepared_by','أعمدة'=>'columns','أغلب'=>'main','أفعال'=>'actions',
'أقساط'=>'installments','أمر'=>'order','أمنية'=>'security','أنشأه'=>'created_by','أنواع'=>'types','أو'=>'or',
'أول'=>'first','أيام'=>'days','إجازة'=>'leave','إجراء'=>'procedure','إجمالي'=>'total','إخفاء'=>'masking',
'إخلاء'=>'vacate','إدارة'=>'dept','إذن'=>'permit','إصدار'=>'release','إضافية'=>'overtime','إطلاق'=>'launch',
'إغلاق'=>'close','إقفال'=>'closing','إلى'=>'to','إنتاج'=>'production','إنتاجي'=>'production','إنجاز'=>'achievement',
'إهلاك'=>'depreciation','احتباس'=>'retention','احتمال'=>'likelihood','اختبارات'=>'tests','استثناء'=>'exception',
'استثناءات'=>'exceptions','استجابة'=>'response','استحقاق'=>'due','استخرجه'=>'issued_by','استعداد'=>'standby',
'استعمال'=>'usage','استهلاك'=>'consumption','اسم'=>'name','اطلاع'=>'view','اعتراف'=>'recognition',
'اعتماد'=>'approval','اعتمادا'=>'approval','اعتمدته'=>'approved_by','اعتمده'=>'approved_by','اقترحه'=>'proposed_by',
'اقتصادي'=>'economic','اكتمال'=>'completeness','التزام'=>'commitment','انتقال'=>'transition','انتهاء'=>'expiry',
'انحراف'=>'deviation','بادئة'=>'prefix','بدء'=>'start','بداية'=>'start','بديل'=>'substitute','بشرية'=>'hr',
'بصمة'=>'fingerprint','بعد'=>'after','بلاغ'=>'ticket','بند'=>'line','بها'=>'','تأخير'=>'delay','تأمين'=>'insurance',
'تأهيل'=>'qualification','تابع'=>'belongs','تاريخ'=>'date','تايم'=>'time','تبادل'=>'swap','تبعية'=>'affiliation',
'تجاري'=>'commercial','تحتها'=>'under_it','تخارج'=>'exit','تخصص'=>'specialty','تخصيص'=>'allocation',
'تخويل'=>'authorization','ترحيل'=>'haulage','ترقيم'=>'numbering','تسعير'=>'pricing','تسلسل'=>'sequence',
'تسلسلي'=>'serial','تسويات'=>'settlements','تشغيل'=>'operations','تصحيح'=>'correction','تصريح'=>'permit',
'تصفية'=>'liquidation','تصنيف'=>'classification','تضارب'=>'conflict','تطابق'=>'match','تعارض'=>'conflict',
'تعارضات'=>'conflicts','تعاقد'=>'contracting','تعاقدي'=>'contractual','تعاقدية'=>'contractual','تعذر'=>'failed',
'تعرفة'=>'tariff','تعطل'=>'downtime','تغيير'=>'change','تفريغ'=>'unloading','تفسير'=>'explanation',
'تفويض'=>'delegation','تقرير'=>'report','تقييم'=>'evaluation','تكرار'=>'duplicate','تكلفة'=>'cost',
'تكليف'=>'assignment','تمويل'=>'financing','تناوب'=>'rotation','تنبيه'=>'alert','تنفيذ'=>'execution',
'تواجد'=>'presence','توقف'=>'stoppage','تمنح'=>'granted','ثاني'=>'second','جاهزية'=>'readiness','جبهة'=>'front',
'جداول'=>'tables','جدول'=>'schedule','جدولها'=>'scheduled_by','جديد'=>'new','جزاء'=>'penalty','جسر'=>'bridge',
'جهة'=>'party','حارس'=>'guard','حاضرين'=>'present','حافز'=>'incentive','حالة'=>'state','حالي'=>'current',
'حامل'=>'holder','حاوية'=>'container','حد'=>'threshold','حدث'=>'event','حركة'=>'movement','حساب'=>'account',
'حسابات'=>'accounts','حساسية'=>'sensitivity','حسب'=>'per','حصة'=>'share','حصص'=>'shares','حفظ'=>'retention',
'حقل'=>'field','حكمه'=>'its_ruling','حلقات'=>'loops','حماية'=>'protection','حمولة'=>'load','خاملة'=>'dormant',
'خبرة'=>'experience','خروج'=>'exit','خصم'=>'deduction','خصصه'=>'allocated_by','خطورة'=>'severity',
'دخول'=>'entry','درجة'=>'grade','دقائق'=>'minutes','دمج'=>'merge','دوام'=>'attendance','دور'=>'role',
'دورة'=>'cycle','دورية'=>'periodicity','رأس'=>'capital','رؤية'=>'visibility','راتب'=>'salary',
'راجعته'=>'reviewed_by','راسبة'=>'failed','راصدة'=>'observing','ربط'=>'link','رجوع'=>'rollback','رحلة'=>'trip',
'رخصة'=>'license','رسالة'=>'message','رسوم'=>'fees','رصد'=>'observation','رصيد'=>'balance','رقم'=>'no',
'رمز'=>'code','سائق'=>'driver','سابق'=>'previous','ساعات'=>'hours','ساعة'=>'hour','سبب'=>'reason',
'سجل'=>'log','سجلات'=>'records','سجلنا'=>'our_log','سجله'=>'recorded_by','سجلها'=>'recorded_by',
'سحبها'=>'revoke','سريان'=>'effective','سرية'=>'confidentiality','سعة'=>'capacity','سعر'=>'rate','سقف'=>'cap',
'سكن'=>'housing','سياسة'=>'policy','شاشات'=>'screens','شاشة'=>'screen','شخص'=>'person','شذوذ'=>'anomaly',
'شراء'=>'purchase','شرط'=>'condition','شركة'=>'company','شغور'=>'vacancy','شهادة'=>'certificate','شهر'=>'month',
'شهري'=>'monthly','شهرية'=>'monthly','شيت'=>'sheet','صافي'=>'net','صرف'=>'fx','صفة'=>'capacity_role',
'صلاحية'=>'permission','صنف'=>'category','صنفها'=>'classified_by','صيانة'=>'maintenance','صيغة'=>'formula',
'ضريبي'=>'tax','ضمان'=>'warranty','طابقه'=>'matched_by','طالب'=>'requester','طالبة'=>'requesting',
'طبي'=>'medical','طرف'=>'party','طريقة'=>'method','طلب'=>'request','طوارئ'=>'emergency','عائد'=>'return',
'عامة'=>'general','عجز'=>'shortfall','عدد'=>'count','عداد'=>'meter','عقد'=>'contract','عكس'=>'reversal',
'علاقة'=>'relation','على'=>'on','علم'=>'flag','عمر'=>'age','عمل'=>'work','عملة'=>'currency','عملية'=>'operation',
'عميل'=>'client','عن'=>'of','عند'=>'at','عين'=>'asset_item','غياب'=>'absence','غير'=>'not','فئة'=>'category',
'فاحص'=>'inspector','فارق'=>'variance','فاقد'=>'loss','فتح'=>'open','فترة'=>'period','فحص'=>'check',
'فرق'=>'variance','فض'=>'resolution','فعل'=>'action','فعلي'=>'actual','فعلية'=>'actual','فعليا'=>'actual',
'فك'=>'unlink','فني'=>'technician','فوترة'=>'billing','في'=>'in','قائم'=>'outstanding','قائمة'=>'standing',
'قابل'=>'able','قاعدة'=>'rule','قانوني'=>'legal','قاهرة'=>'majeure','قبل'=>'before','قديم'=>'old',
'قراءة'=>'reading','قرار'=>'decision','قسط'=>'installment','قصوى'=>'max','قلب'=>'flip','قواعد'=>'rules',
'قوة'=>'force','قياس'=>'measure','قيد'=>'constraint','قيمة'=>'value','كامل'=>'full','كلية'=>'total',
'كلفه'=>'assigned_by','كمية'=>'qty','كود'=>'code','كيان'=>'entity','لدى'=>'at','لم'=>'not','له'=>'has',
'لو'=>'if','لوحة'=>'plate','مؤكدة'=>'confirmed','مؤيد'=>'supporting','مؤيدة'=>'supporting','مئة'=>'hundred',
'مال'=>'capital','مالك'=>'owner','مالكة'=>'owning','مالي'=>'financial','مالية'=>'finance','متأثر'=>'affected',
'متأثرة'=>'affected','متابعة'=>'followup','متبادل'=>'swapped','متبقي'=>'remaining','متحمل'=>'bearing',
'متعاقد'=>'contracted','متغيرة'=>'changed','متوقع'=>'expected','مجتازة'=>'passed','مجدولة'=>'scheduled',
'مجموع'=>'total','محاسبي'=>'accounting','محاسبية'=>'accounting','محتمل'=>'potential','محجوبة'=>'blocked',
'محضر'=>'minutes_doc','محفز'=>'trigger','محولة'=>'migrated','مخصص'=>'allocated','مخصصة'=>'allocated',
'مخطط'=>'planned','مخططة'=>'planned','مخول'=>'authorized','مدة'=>'duration','مدير'=>'manager','مرات'=>'times',
'مراجع'=>'auditor','مراجعة'=>'review','مرادفات'=>'synonyms','مرافقة'=>'escort','مرتبط'=>'linked',
'مرتبطة'=>'linked','مرتهن'=>'pledgee','مرجع'=>'ref','مرجعي'=>'reference','مرجح'=>'probable','مرفق'=>'attachment',
'مركز'=>'center','مسؤول'=>'responsible','مسار'=>'route','مسبق'=>'pre','مستثناة'=>'exempted','مستعملة'=>'used',
'مستلم'=>'receiver','مستند'=>'doc','مستندات'=>'docs','مستهدفة'=>'target','مستوى'=>'level','مسجلة'=>'registered',
'مسحوبة'=>'revoked','مسلم'=>'handover','مسموح'=>'allowed','مسموحة'=>'allowed','مسمى'=>'title','مسير'=>'payroll',
'مشتري'=>'buyer','مشروع'=>'project','مشغل'=>'operator','مشغلون'=>'operators','مشغلين'=>'operators',
'مصادقة'=>'attestation','مصالح'=>'interests','مصدر'=>'source','مصدرة'=>'issuing','مصروفة'=>'disbursed',
'مصرح'=>'authorized','مصرحة'=>'authorized','مصنعة'=>'manufacturer','مضافة'=>'added','مطالبة'=>'claim',
'مطبق'=>'applied','مطبقة'=>'applied','مطلوب'=>'required','مطلوبة'=>'required','مطلوبون'=>'required',
'معادلة'=>'formula','معالجة'=>'handling','معتمد'=>'approved','معتمدة'=>'approved','معدات'=>'equipment',
'معدة'=>'equipment','معدل'=>'rate','معدلة'=>'modified','معرضة'=>'exposed','معطلة'=>'disabled','معه'=>'with',
'مفتوح'=>'open','مفوتر'=>'billable','مقيم'=>'assessor','مكتشف'=>'detected','مكتشفة'=>'detected',
'مكلف'=>'assignee','ملاحظات'=>'notes','ملاحظة'=>'note','ملكية'=>'ownership','مملوك'=>'owned','ممنوعة'=>'denied',
'ممول'=>'financier','من'=>'from','مناوب'=>'rotator','منتفع'=>'beneficiary','منح'=>'grant','منسوب'=>'attributed',
'منشور'=>'published','منطبقة'=>'applicable','منع'=>'denial','منفذ'=>'executed','منفذة'=>'executed',
'مهلة'=>'deadline','موارد'=>'hr','موافق'=>'approver','موافقات'=>'approvals','موافقون'=>'approvers',
'موحد'=>'unified','موديل'=>'model','مورد'=>'supplier','موظف'=>'employee','موقع'=>'site','ميدانية'=>'field',
'مثبت'=>'proving','مدخلة'=>'entered','مدخل'=>'enterer','مدخلون'=>'enterers','مطلق'=>'triggering',
'ناتج'=>'resulting','ناشر'=>'publisher','نافذة'=>'window','ناقل'=>'carrier','نتيجة'=>'result','نحن'=>'ours',
'نسب'=>'pcts','نسبة'=>'pct','نسخة'=>'version','نشر'=>'publish','نشطة'=>'active','نطاق'=>'scope',
'نظامي'=>'statutory','نظامية'=>'statutory','نقطة'=>'point','نقل'=>'transfer','نمط'=>'pattern','نموذج'=>'model',
'نهائية'=>'final','نهاية'=>'end','نوافذ'=>'windows','نوع'=>'type','هجرات'=>'migrations','هدف'=>'target',
'واجبات'=>'duties','واقع'=>'actual','وثائق'=>'documents','وحدات'=>'units','وحدة'=>'unit','ورديات'=>'shifts',
'وردية'=>'shift','ورشة'=>'workshop','وزن'=>'weight','وسم'=>'tag','وصف'=>'description','وضع'=>'mode',
'وظيفة'=>'job','وقائية'=>'preventive','وقت'=>'time','ومصدره'=>'and_source','يحتاج'=>'needs','يحجب'=>'blocks',
'يراه'=>'visible_to','يومية'=>'daily','يسجل'=>'logged','يصدر'=>'exported',
);

/** تسمية عربية ← اسم عمود إنجليزي (قاموس التسميات ثم كلمة كلمة) */
function w2_col($label, $LABEL_MAP, $W) {
    $n = w2_norm($label);
    if (isset($LABEL_MAP[$n])) { return $LABEL_MAP[$n]; }
    // نزع علامات لا تدخل الاسم
    $n2 = str_replace(array('—', '؟', '?', '·', '(', ')', '/', '،', '«', '»'), ' ', $n);
    $parts = array();
    $missing = array();
    foreach (preg_split('/\s+/u', trim($n2)) as $wd) {
        if ($wd === '' || $wd === '-' || $wd === '–') { continue; }
        $bare = preg_replace('/^(وال|بال|لل|ال)/u', '', $wd);
        if ($bare === '') { continue; }
        if (!isset($W[$bare])) { $missing[] = $bare; continue; }
        if ($W[$bare] === '') { continue; }
        $parts[] = $W[$bare];
    }
    if ($missing) { return array('missing' => $missing); }
    $col = implode('_', $parts);
    $col = preg_replace('/_+/', '_', trim($col, '_'));
    if ($col === '') { $col = 'field'; }
    return substr($col, 0, 58);
}

/* ── المسح ──────────────────────────────────────────────────────────────── */
$screens = array();
foreach ($files as $f) {
    $src = file_get_contents($ROOT . '/' . $f);
    if (!preg_match("/\\\$CANONICAL\s*=\s*'([^']+)'/u", $src, $m)) { continue; }
    $canon = $m[1];
    $fields = array();
    if (preg_match("/\\\$FIELDS\s*=\s*array\s*\((.*?)\);/us", $src, $m2)) {
        preg_match_all("/=>\s*'([^']*)'/u", $m2[1], $mm);
        $fields = $mm[1];
    }
    if (!$fields) { fwrite(STDOUT, "⚠ بلا حقول: {$f}\n"); continue; }
    $screens[$canon] = array('file' => $f, 'fields' => $fields);
}

/* ── التوليد ────────────────────────────────────────────────────────────── */
$sql = "-- CMP-03 الموجة ٢ — تحرير بقية الشاشات من المخزن البيني (2026-08-06 · ق-15/ق-01)\n";
$sql .= "-- ═══════════════════════════════════════════════════════════════════════════\n";
$sql .= "-- لكل شاشةٍ جدولها الأصلي scr_* بأعمدةٍ دلاليةٍ مشتقةٍ من تسميات حقولها\n";
$sql .= "-- (مولَّد بأداة tools/cmp03_wave2_generate.php — القاموس فيها).\n";
$sql .= "-- DDL إضافي خالص: CREATE TABLE IF NOT EXISTS فقط — صفر ALTER على قائم.\n";
$sql .= "-- التواريخ DATE والباقي VARCHAR (عرف جداول exec_* المرجعية) والبذور is_seed.\n\n";

$registry = array();
$fatal = false;
foreach ($screens as $canon => $s) {
    $table = 'scr_' . preg_replace('/\.php$/', '', $canon);
    $cols = array();      // col => [label, type]
    $map = array();       // norm(label) => col
    $labelsOf = array();  // col => التسمية الأصلية
    foreach ($s['fields'] as $lb) {
        $col = w2_col($lb, $LABEL_MAP, $W);
        if (is_array($col)) {
            fwrite(STDOUT, "✖ {$canon}: «{$lb}» كلمات خارج القاموس: " . implode('، ', $col['missing']) . "\n");
            $fatal = true;
            continue;
        }
        // الأسماء القياسية محجوزة لأعمدة البنية — تصادمها يعلَّق بلاحقة _f
        static $RESERVED = array('id' => 1, 'company_id' => 1, 'status' => 1, 'is_seed' => 1,
            'created_by' => 1, 'created_by_name' => 1, 'created_at' => 1, 'updated_at' => 1);
        if (isset($RESERVED[$col])) { $col .= '_f'; }
        $base = $col; $i = 2;
        while (isset($cols[$col])) { $col = substr($base, 0, 55) . '_' . $i; $i++; }
        $nl = w2_norm($lb);
        $isDate = (bool) preg_match('/^(تاريخ |من تاريخ$|إلى تاريخ$)/u', $nl);
        $type = $isDate ? 'DATE' : 'VARCHAR(300)';
        $cols[$col] = array($lb, $type);
        $map[$nl] = $col;
        $labelsOf[$col] = $lb; // التسمية الأصلية (بتشكيلها) — مفتاح الحمولة في الشاشات
    }
    if ($fatal) { continue; }
    $registry[$canon] = array('table' => $table, 'map' => $map, 'labels' => $labelsOf);

    $sql .= "-- ── {$canon} ← {$s['file']} ──\n";
    $sql .= "CREATE TABLE IF NOT EXISTS `{$table}` (\n";
    $sql .= "  `id` INT NOT NULL AUTO_INCREMENT,\n";
    $sql .= "  `company_id` INT NOT NULL COMMENT 'الكيان المالك — EN-03',\n";
    foreach ($cols as $c => $def) {
        $cm = str_replace("'", "''", $def[0]);
        $sql .= "  `{$c}` {$def[1]} DEFAULT NULL COMMENT '{$cm}',\n";
    }
    $sql .= "  `status` VARCHAR(40) NOT NULL DEFAULT 'مسودة' COMMENT 'حالة الصف في دورته',\n";
    $sql .= "  `is_seed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'بذرة تجريبية — لا تنشر وقائع',\n";
    $sql .= "  `created_by` INT DEFAULT NULL,\n";
    $sql .= "  `created_by_name` VARCHAR(120) DEFAULT NULL COMMENT 'المُنشئ — الاسم والصفة',\n";
    $sql .= "  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
    $sql .= "  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
    $sql .= "  PRIMARY KEY (`id`),\n";
    $sql .= "  KEY `ix_" . substr($table, 4, 20) . "_live` (`company_id`, `status`)\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n";
    $sql .= "  COMMENT='CMP-03 موجة ٢: الجدول الأصلي لشاشة {$canon}';\n\n";
}

if ($fatal) { fwrite(STDOUT, "✖ التوليد متوقف — أكمل القاموس أولًا\n"); exit(1); }

file_put_contents($ROOT . '/database/migrations/2026_11_14_cmp03_wave2_tables.sql', $sql);

$php = "<?php\n/**\n * includes/cmp03_registry.php — سجل تحرير الشاشات من المخزن البيني (الموجة ٢)\n";
$php .= " * مولَّد آليًّا بـ tools/cmp03_wave2_generate.php — لا يُحرَّر يدويًّا.\n";
$php .= " * canonical_file ← جدولها الأصلي + خريطة (تسمية مطبَّعة ← عمود).\n */\n";
$php .= "if (!function_exists('cmp03_registry')) {\nfunction cmp03_registry() {\n    return " . var_export($registry, true) . ";\n}\n}\n";
file_put_contents($ROOT . '/includes/cmp03_registry.php', $php);

fwrite(STDOUT, "✔ جداول: " . count($registry) . " — الهجرة والسجل كُتبا\n");
