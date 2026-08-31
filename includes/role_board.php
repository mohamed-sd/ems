<?php
require_once __DIR__ . '/permissions_helper.php'; // FINAL_CLOSE ⑦ — المصدر الواحد
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * لوحة الدور — المكوّنات السبعة (UX-00 §7 · UX-01 §5)
 * ─────────────────────────────────────────────────────────────────────────
 * «تُلغى اللوحة العامة الواحدة، وتُبنى لكل دورٍ لوحتُه من سبعة مكوّناتٍ ثابتة
 *  الترتيب — تختلف محتوياتُها لا بنيتُها» (UX-01 §5):
 *
 *   ① مؤشرات اليوم — ثلاثةٌ إلى خمسة أرقامٍ تُقرَّر منها أفعالُ اليوم
 *   ② مهامي        — ما يجب فعلُه الآن بأزرار قفز
 *   ③ موافقاتي     — عدّادُ المنتظر وفتحُ الصندوق بنقرة
 *   ④ التنبيهات    — المتأخر والحرج **بسببٍ وقفزة** (§9: لا تنبيهَ بلا إجراء)
 *   ⑤ إنشاء سريع   — أكثر ثلاث عملياتٍ استخدامًا
 *   ⑥ نبض الأداء   — رسمٌ أو اثنان يخصّان قرار الدور
 *   ⑦ عملي الأخير  — آخر ما لمسه ليتابع
 *
 * قاعدتان حاكمتان يفرضهما الدستور وتُنفَّذان هنا بنيويًّا:
 *   • كل رقمٍ ينقر إلى مصدره — فلا مؤشرَ بلا `href` (يُرفض عند البناء).
 *   • التنبيهُ يحمل سببَه وقفزتَه — وتعريفاتُ التنبيهات لكل دورٍ منقولةٌ
 *     **حرفيًّا** من UX-01 §8، لا اجتهادًا (انظر roleBoardAlertSpecs).
 *
 * التنبيهات تُشتق من الحالة التشغيلية ولا تُقرأ من جدول تنبيهاتٍ موحّد —
 * لأن الوثيقة لم تطلب جدولًا قط: كلُّ تنبيهٍ فيها شرطٌ محسوب («وحداتٌ غير
 * معتمدة منذ أمس» · «عقدٌ ينتهي خلال 30 يومًا») لا صفٌّ مخزَّن.
 *
 * التشغيل المزدوج بعلَمٍ لكل دور: EMS_ROLE_BOARD_ROLES في .env — كما فعلنا
 * في المصدر الموحّد للسايدبار؛ ودورٌ خارج العلم يبقى على اللوحة العامة حرفيًّا.
 */

require_once __DIR__ . '/dynamic_nav.php';

/** هل الدور على لوحة الدور؟ ($csv تجاوزٌ اختباري؛ null = من البيئة) */
function roleBoardEnabled($roleId, $csv = null)
{
    if ($csv === null) {
        $csv = function_exists('ems_env') ? (string) ems_env('EMS_ROLE_BOARD_ROLES', '') : '';
    }
    if (trim($csv) === '') { return false; }
    return in_array(strval(intval($roleId)), array_map('trim', explode(',', $csv)), true);
}

/**
 * لوحةُ كلِّ دور (قرار المالك 2026-07-26: «الرئيسية» تفتح لوحةَ الدور مباشرةً —
 * نصُّ UX-01 §4: «أول ما يُفتح — لوحة الدور»). الدورُ الفرعي يرث لوحةَ أبيه
 * (قرار المالك: «الفرعية ترث أباها») — فيُمرَّر parent إن وُجد.
 * يعيد المسار من جذر التطبيق، أو null لدورٍ بلا لوحةٍ بعد (يبقى على العامة).
 */
function roleBoardRoute($roleId, $parentRoleId = null)
{
    $map = array(
        // لوحاتٌ مخصصةٌ قائمة
        13 => 'Maintenance/dashboard_mnt.php',
        23 => 'Transport/transfer_dashboard.php',
        26 => 'Financing/financing_board.php',   // FIN-26 — لوحة إدارة التمويل (الشاشة 214)
        // ── كلُّ صاحبِ لوحةٍ عامةٍ لوحتُه «الرئيسية» نفسُها ──────────────────
        // قرارُ المالك 2026-08-21 (نُفِّذ دورًا بعد دورٍ حتى اكتمل): المكوّناتُ
        // السبعةُ تُصيَّر داخلَ main/dashboard.php بلغةِ تصميمِها بدل شاشةٍ
        // ثانية. ولا شيءَ في العرضِ يخصُّ دورًا بعينه — القالبُ يقرأ إعدادَ
        // الدورِ من roleBoardGenericConfig — فالرجوعُ لأيِّ دورٍ سطرٌ واحد.
        // (اللوحاتُ المخصصةُ أعلاه ليست من هذا: بُنيت شاشاتٍ قائمةً بذاتها
        //  ولا إعدادَ عامًّا لها، فتبقى حيث هي.)
        1  => 'main/dashboard.php',   // ادارة التشغيل
        2  => 'main/dashboard.php',   // ادارة الموردين
        3  => 'main/dashboard.php',   // ادارة الاسطول
        4  => 'main/dashboard.php',   // ادارة الموارد البشرية
        5  => 'main/dashboard.php',   // إدارة الموقع (القديم — مدمج في 6)
        6  => 'main/dashboard.php',   // إدارة الموقع / الحركة والتشغيل
        12 => 'main/dashboard.php',   // ادارة المبيعات
        15 => 'main/dashboard.php',   // إدارة الصلاحيات
        16 => 'main/dashboard.php',   // إدارة المشتريات — سبعُ بطاقاتٍ وجدولُ «بانتظار قطع»
        17 => 'main/dashboard.php',   // الإدارة المالية — بطاقاتُها العشرُ منقولةٌ حرفًا، وحارسُ can_view معها
        24 => 'main/dashboard.php',   // إدارة البلاغات (ابنُ 1 — ومدخلُه الصريحُ يسبق وراثةَ أبيه)
    );
    $rid = intval($roleId);
    if (isset($map[$rid])) { return $map[$rid]; }
    $pid = intval($parentRoleId);
    return ($pid > 0 && isset($map[$pid])) ? $map[$pid] : null;
}

/**
 * قياسٌ عدديٌّ معزول عبر البوابة — يعيد 0 عند أي فشلٍ ولا يرمي أبدًا:
 * لوحةُ الدور لا تتعطل لعطبِ مؤشرٍ واحد (نمط finreq_badges المثبَت).
 */
/**
 * عددُ الصفوف العائدة — لمؤشّرٍ سؤالُه «كم مجموعةً استوفت الشرط؟».
 * البوابةُ تشترط WHERE عليا واحدة، فلا يُلَفُّ الاستعلامُ في جدولٍ مشتق
 * (تصير WHERE داخلَ قوسٍ فتُرفض)؛ الحلُّ أن يُجمَّع بـGROUP BY/HAVING
 * وتُعَدَّ الصفوفُ هنا بدل COUNT(*) على مشتقٍّ مرفوض.
 */
function roleBoardRowCount($gate, array $scope, $sql, array $params = array())
{
    try {
        $r = $gate->scopedQuery($scope, $sql, $params);
        return is_array($r) ? count($r) : 0;
    } catch (\Throwable $t) {
        error_log('role_board rowcount: ' . $t->getMessage());
        return 0;
    }
}

function roleBoardScalar($gate, array $scope, $sql, array $params = array())
{
    try {
        $r = $gate->scopedQuery($scope, $sql, $params);
        return $r ? (float) array_values($r[0])[0] : 0.0;
    } catch (\Throwable $t) {
        error_log('role_board scalar: ' . $t->getMessage());
        return 0.0;
    }
}

/**
 * تعريفٌ **واحد** لعدّادِ الاعتماداتِ — الدورُ 2.
 * ═══════════════════════════════════════════════════════════════════════
 * ◆ **كان مكتوبًا ثلاثَ مراتٍ ونسخةٌ رابعةٌ تخالفه**: البطاقةُ والمهمّةُ
 *   والتنبيهُ تحمل الجملةَ نفسَها حرفًا، وشارةُ السايدبار تحمل تعدادَ
 *   حالاتٍ آخر (`finreq_badges.php`). **وأربعُ نسخٍ تتفرّق بأوَّلِ تعديل** —
 *   فقارئٌ واحدٌ لكلِّ معيار.
 *
 * ◆ **الجملةُ هي الأصليةُ حرفًا بقرارِ المالك 2026-08-23**: جُرِّب مصدرانِ
 *   آخرانِ ثم أُعيد الأوَّل — فيبقى الرقمُ **٥٨** كما كان.
 *
 * ◆ **وما قِيس أثناءَ التجربةِ يُقيَّد هنا لئلّا يُعاد اكتشافُه**:
 *   ① من الثمانيةِ والخمسين **سبعةٌ وخمسون بذرةُ تهيئة** (`legacy-seed-20260812`)
 *      والصادقُ **واحد** — فالرقمُ يقيس البذرةَ أكثرَ مما يقيس العمل.
 *   ② ودفترُ الاعتماداتِ `timesheet_approvals` فيه **٨٥** صفًّا،
 *      **ومستواه الثاني ٢٢ بالضبط** — وهو الرقمُ الذي ذكره المالكُ أوَّلًا.
 *   ⇐ فإن أُريد يومًا رقمٌ يقيس عملًا حقيقيًّا، فالبابانِ معروفانِ بسطرِهما.
 */
function roleBoardSupplierPendingSql()
{
    /* ◆ **ولا استعلامَ فرعيٌّ على `unit_approvals` هنا**: البوابةُ ترفض جدولَ
         مستأجِرٍ غيرَ مُعلَن («جدولٌ مستأجرٌ غير معلَن») **ويبتلع الحارسُ الخطأَ
         فيعود صفرًا** — عدّادٌ يُظهر صفرًا لأنَّ استعلامَه رُفض لا لأنَّ العملَ
         انتهى. وهو أسوأُ من رقمٍ كبيرٍ خاطئ.
       ◆ **والحالةُ تُغني عن الوصل**: `site_approved` تعني أنَّ الموقعَ اعتمد
         ولم يعتمدْ طرفٌ بعدَه — وقِيس أنَّ **صفرًا** من صفوفِ هذه الحالةِ يحمل
         اعتمادَ مرحلةِ `supplier`. وشاهدُ `tests/rb2_supplier_pending_test.php`
         يحرس هذا التكافؤَ: إن حمل صفٌّ منها اعتمادَ موردٍ يومًا **يرسُب**. */
    return "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state='site_approved'";
}
/**
 * ④ تعريفات التنبيهات لكل دور — منقولةٌ حرفيًّا من UX-01 §8.
 * لكل تنبيه: النص (السبب) · الشرط (دالّة تعيد العدد) · القفزة (الشاشة).
 * يُبنى هنا التعريفُ وحده؛ والتنفيذُ في roleBoardAlerts.
 */
function roleBoardAlertSpecs($roleId)
{
    $specs = array(
        // §8.11 · المدير المالي
        17 => array(
            array('key' => 'liquidity', 'label' => 'سيولة دون الحد',            'href' => '../Finance/cash_forecast_fin.php',  'tone' => 'err'),
            array('key' => 'aged_dues', 'label' => 'ذمم تجاوزت أعمارها',        'href' => '../Finance/dues_fin.php',           'tone' => 'err'),
            array('key' => 'req_sla',   'label' => 'طلبات فوق مهلة الاعتماد',   'href' => '../FinRequests/finance_gateway.php','tone' => 'warn'),
            array('key' => 'variance',  'label' => 'انحراف ميزانية فوق النسبة', 'href' => '../Finance/budget_form_fin.php',    'tone' => 'warn'),
        ),
        // §8.8 · ادارة الصيانة — التنبيه الرابع في النص («قطعةٌ منتظرةٌ توقف
        // أمرًا») بلا مصدرِ بياناتٍ اليوم: لا حالةَ wait_parts في آلة الأمر
        // والقطعُ نصٌّ حرٌّ (قرار DEC-10) — لا يُعرض حتى يُبنى مصدرُه
        // (تدقيق الصيانة، المستوى الأول #6). قاعدةُ عدم التلفيق نافذة.
        13 => array(
            array('key' => 'critical_down', 'label' => 'معدة حرجة متوقفة',        'href' => '../Maintenance/orders.php',           'tone' => 'err'),
            array('key' => 'order_overdue', 'label' => 'أمر مفتوح فوق مدته',      'href' => '../Maintenance/orders.php',           'tone' => 'warn'),
            array('key' => 'pm_late',       'label' => 'وقائية متأخرة',            'href' => '../Maintenance/preventive_plans.php', 'tone' => 'warn'),
        ),
        // §8.10 · مسؤول المشتريات — الأربعة نصًّا
        16 => array(
            array('key' => 'reorder_hit',   'label' => 'صنف بلغ حد الطلب',          'href' => '../Procurement/reordering_proc.php',      'tone' => 'err'),
            array('key' => 'po_late',       'label' => 'أمر شراء متأخر التوريد',    'href' => '../Procurement/orders_proc.php',          'tone' => 'warn'),
            array('key' => 'custody_over',  'label' => 'استلام مؤقت تجاوز مهلته',    'href' => '../Procurement/receipt_custody_proc.php', 'tone' => 'warn'),
            array('key' => 'issue_waiting', 'label' => 'طلب صرف ينتظر',              'href' => '../Procurement/issue_proc.php',           'tone' => 'warn'),
        ),
        // §8.12 · مدير النقل والترحيل — الثلاثة نصًّا
        23 => array(
            array('key' => 'trip_late',     'label' => 'رحلة متأخرة عن موعدها',      'href' => '../Transport/transfer_orders_list.php', 'tone' => 'err'),
            array('key' => 'req_waiting',   'label' => 'طلب ترحيل ينتظر اعتمادك',    'href' => '../Transport/transfer_requests.php',    'tone' => 'warn'),
            array('key' => 'undocumented',  'label' => 'تسليم بلا توثيق',             'href' => '../Transport/transfer_orders_list.php', 'tone' => 'warn'),
        ),
        // §8.13 · إدارة البلاغات — الثلاثة نصًّا
        24 => array(
            array('key' => 'critical_unassigned', 'label' => 'بلاغ حرج بلا مستلم',   'href' => '../Tickets/tickets_list.php', 'tone' => 'err'),
            array('key' => 'sla_broken',          'label' => 'بلاغ كسر مهلته',          'href' => '../Tickets/tickets_list.php', 'tone' => 'err'),
            array('key' => 'repeat_late_dept',    'label' => 'إدارة تكرر التأخر',       'href' => '../Tickets/ticket_dashboard.php', 'tone' => 'warn'),
        ),
        // §8.7 · ادارة المبيعات — **الأربعةُ كاملةً منذ 2026-07-29**.
        // كان هنا تعليقٌ يقول إن «مطالبةً معلَّقة» و«وحداتٍ جاهزةً للفوترة» بلا
        // مصدر — وقد صار لهما مصدرٌ حيٌّ يوم بُني `claims`/`claim_lines`
        // (2026-07-27)، فبقاءُ التعليق كذبٌ موثَّق: قاعدةُ عدم التلفيق تعمل في
        // الاتجاهين — لا رقمَ بلا مصدر، ولا إعلانَ عدمِ مصدرٍ ومصدرُه قائم.
        // والتعريفان يُقرآن من `claim_helpers` نفسِها التي تقرأ منها الشاشة.
        12 => array(
            array('key' => 'unbilled_units',  'label' => 'وحدات جاهزة للفوترة لم تفوتر', 'href' => '../Contracts/claims.php?unbilled=1', 'tone' => 'err'),
            array('key' => 'claim_pending',   'label' => 'مطالبة معلقة',                 'href' => '../Contracts/claims.php?pending=1',  'tone' => 'warn'),
            array('key' => 'contract_ending', 'label' => 'عقد ينتهي خلال 30 يوما',     'href' => '../Contracts/contracts.php',   'tone' => 'warn'),
            array('key' => 'quote_stale',     'label' => 'عرض بلا رد فوق أسبوع',      'href' => '../Clients/quotations.php',    'tone' => 'warn'),
        ),
        // §8.2 · ادارة الموردين — «توقفٌ محسوبٌ على المورد يحتاج حسمًا» بلا
        // حقلِ مسؤوليةٍ للتوقف في الدوام بعد (ينتظر سجل الزمن الموحّد UX-03)
        2 => array(
            array('key' => 'units_pending_sup', 'label' => 'وحدات تنتظر اعتماد المورد', 'href' => '../Approvals/hours_approval.php',      'tone' => 'warn'),
            array('key' => 'sup_contract_end',  'label' => 'عقد مورد ينتهي قريبا',    'href' => '../Suppliers/supplierscontracts.php',  'tone' => 'warn'),
        ),
        // §8.3 · ادارة الاسطول — «فوق حدّها» بلا حدٍّ معرَّفٍ بعد → «متوقفةٌ الآن»
        3 => array(
            array('key' => 'eq_stopped',   'label' => 'معدة متوقفة (تحت الصيانة)',   'href' => '../Equipments/equipments_fleet.php', 'tone' => 'err'),
            array('key' => 'meter_stale',  'label' => 'عداد لم يحدث (30 يوما)',   'href' => '../Equipments/equipments_fleet.php', 'tone' => 'warn'),
            array('key' => 'doc_expiring', 'label' => 'وثيقة معدة تنتهي (30 يوما)',  'href' => '../Equipments/equipments_fleet.php', 'tone' => 'warn'),
        ),
        // §8.4 · ادارة الموارد البشرية — الأربعة نصًّا
        4 => array(
            array('key' => 'absence_noperm', 'label' => 'غياب بلا إذن',                'href' => '../Workforce/worker_leave_absence.php', 'tone' => 'err'),
            array('key' => 'units_unapproved','label' => 'وحدات عامل غير معتمدة',     'href' => '../Approvals/hours_approval.php',       'tone' => 'warn'),
            array('key' => 'wcontract_end',  'label' => 'عقد عامل ينتهي قريبا',      'href' => '../Workforce/worker_contract.php',      'tone' => 'warn'),
            array('key' => 'settle_waiting', 'label' => 'تسوية تنتظر الاعتماد',        'href' => '../Workforce/worker_settlement.php',    'tone' => 'warn'),
        ),
        // §8.5 · مدير الموقع — الثلاثة نصًّا (تجاوزُ الطاقة من حارس D02 الحي)
        5 => array(
            array('key' => 'ts_today_missing', 'label' => 'تايم شيت اليوم لم يرسل',    'href' => '../Timesheet/timesheet_type.php',   'tone' => 'err'),
            array('key' => 'units_await_me',   'label' => 'وحدات تنتظر اعتمادك',       'href' => '../Approvals/hours_approval.php',   'tone' => 'warn'),
            array('key' => 'capacity_flag',    'label' => 'تجاوز طاقة يحتاج سببا',   'href' => '../Timesheet/timesheet_type.php',   'tone' => 'err'),
        ),
        // §8.6 · مدير حركة وتشغيل — الثلاثة نصًّا
        6 => array(
            array('key' => 'eq_no_operator', 'label' => 'معدة عاملة بلا مشغل',           'href' => '../Oprators/select_project.php',    'tone' => 'err'),
            array('key' => 'assign_conflict','label' => 'تعارض توزيع (مشغلان لمعدة)',    'href' => '../Oprators/select_project.php',    'tone' => 'err'),
            array('key' => 'move_waiting',   'label' => 'طلب تنقل ينتظر',                'href' => '../Workforce/worker_movement.php',  'tone' => 'warn'),
        ),
        // §8.1 · ادارة التشغيل (UX-03 §1 و§6) — ثلاثةٌ من أربعة: «انحرافُ
        // التزامٍ فوق الحد» بلا محركِ التزامٍ بعد (contract_commitments تصريحيةٌ
        // بلا قياس منفَّذ — محرّكُها تبويبُ غرفة العمليات ④ القادم) فلا يُعرض.
        1 => array(
            array('key' => 'units_since_yday', 'label' => 'وحدات غير معتمدة منذ أمس',   'href' => '../Approvals/hours_approval.php',  'tone' => 'err'),
            array('key' => 'site_no_ts',       'label' => 'موقع لم يرفع تايم شيته اليوم', 'href' => '../Timesheet/timesheet_type.php', 'tone' => 'warn'),
            array('key' => 'eq_down_noreason', 'label' => 'معدة متوقفة بلا سبب',        'href' => '../Oprators/select_project.php',   'tone' => 'err'),
        ),
        // §8.9 · مدير الصلاحيات — الثلاثة نصًّا
        15 => array(
            array('key' => 'user_no_role', 'label' => 'مستخدم بلا دور صالح',   'href' => '../main/users.php',  'tone' => 'err'),
            array('key' => 'role_no_screens', 'label' => 'دور بلا شاشات',       'href' => '../main/users.php',  'tone' => 'warn'),
            array('key' => 'dormant_account', 'label' => 'حساب خامل طويلا (90 يوما)', 'href' => '../main/users.php', 'tone' => 'warn'),
        ),
    );
    return isset($specs[intval($roleId)]) ? $specs[intval($roleId)] : array();
}

/** ④ حساب التنبيهات الحية — كلٌّ بعدده وسببه وقفزته؛ وما عدده صفرٌ يختفي. */
function roleBoardAlerts($conn, $gate, $roleId)
{
    $rid = intval($roleId);
    $out = array();
    $today = date('Y-m-d');

    if ($rid === 17) {
        $counts = array();
        // سيولةٌ دون الحد: صافي 7 أيامٍ سالب (الذمم المستحقة ناقص الملتزم)
        $wk = date('Y-m-d', strtotime('+7 days'));
        $in7 = roleBoardScalar($gate, array('scope' => array('r' => 'fin_receivables')),
            "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE}
             AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<=?", array($wk));
        $out7 = roleBoardScalar($gate, array('scope' => array('f' => 'fin_funding_schedules')),
            "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f
             WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date<=?", array($wk));
        $counts['liquidity'] = ($in7 - $out7) < 0 ? 1 : 0;

        $counts['aged_dues'] = (int) roleBoardScalar($gate, array('scope' => array('r' => 'fin_receivables')),
            "SELECT COUNT(*) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
             AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<?", array($today));

        // الحالات المفتوحة بأسمائها الفعلية في ENUM الجدول (لا اجتهاد)
        $counts['req_sla'] = (int) roleBoardScalar($gate, array('scope' => array('q' => 'fin_requests')),
            "SELECT COUNT(*) FROM fin_requests q WHERE {TENANT_SCOPE} AND COALESCE(q.is_deleted,0)=0
             AND q.state IN('draft','under_review','pending_approval','returned')
             AND q.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");

        $counts['variance'] = (int) roleBoardScalar($gate,
            array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
            "SELECT COUNT(*) FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id=l.budget_id
             WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0
             AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct)>10");

        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }   // «المُنجَز يختفي فورًا» (§9)
        }
    }

    if ($rid === 13) {
        $counts = array();
        // «حرجة» = أمرٌ مفتوحٌ بأولوية عاجلة/عالية (الحقول العربية بقيمها الحية)
        $counts['critical_down'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'mnt_order')),
            "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
             AND o.state IN('بلاغ','تنفيذ','فحص') AND o.priority IN('عاجلة','عالية')");
        // لا عمودَ مدةٍ مستهدفةٍ على الأمر — «فوق مدته» = مفتوحٌ منذ أكثر من 7 أيام
        // (تقريبٌ موثَّق حتى تُبنى الأزمنة المحسوبة — تدقيق الصيانة، المستوى الأول #1)
        $counts['order_overdue'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'mnt_order')),
            "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
             AND o.state IN('بلاغ','تنفيذ','فحص') AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $counts['pm_late'] = (int) roleBoardScalar($gate, array('scope' => array('p' => 'mnt_plan')),
            "SELECT COUNT(*) FROM mnt_plan p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
             AND p.next_due_date IS NOT NULL AND p.next_due_date < CURDATE()");

        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }
        }
    }

    if ($rid === 16) {
        $counts = array();
        // الرصيد محسوبٌ من الحركات (صيغة stock_proc: استلام + إرجاع − صرف) مقارنًا
        // بنقطة الطلب — الاستعلام الفرعي بجدوله المعلَن (عقد scopedQuery)
        $counts['reorder_hit'] = (int) roleBoardScalar($gate,
            array('scope' => array('op' => 'proc_orderpoint'), 'enrich' => array('m' => 'proc_stock_move')),
            "SELECT COUNT(*) FROM proc_orderpoint op WHERE {TENANT_SCOPE} AND COALESCE(op.is_deleted,0)=0
             AND (SELECT COALESCE(SUM(CASE WHEN m.move_type IN('استلام','إرجاع') THEN m.qty ELSE -m.qty END),0)
                    FROM proc_stock_move m WHERE m.item_id = op.item_id AND m.company_id = op.company_id) <= op.min_qty");
        // لا عمودَ موعدِ توريدٍ على الأمر — «متأخر» = مؤكَّدٌ بلا استلامٍ فوق 7 أيام (تقريبٌ موثَّق)
        $counts['po_late'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'proc_order')),
            "SELECT COUNT(*) FROM proc_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
             AND o.state = 'مؤكَّد' AND o.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $counts['custody_over'] = (int) roleBoardScalar($gate, array('scope' => array('rc' => 'proc_receipt_custody')),
            "SELECT COUNT(*) FROM proc_receipt_custody rc WHERE {TENANT_SCOPE} AND COALESCE(rc.is_deleted,0)=0
             AND rc.state <> 'مسلَّمة للوجهة' AND rc.receipt_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        // طلباتُ الشراء الواردة تنتظر قرار المشتريات (بحالاتها العربية الحية)
        $counts['issue_waiting'] = (int) roleBoardScalar($gate, array('scope' => array('r' => 'proc_request')),
            "SELECT COUNT(*) FROM proc_request r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0
             AND r.state IN('مقدَّم','اعتماد المشتريات')");

        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }
        }
    }

    if ($rid === 23) {
        $counts = array();
        $counts['trip_late'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'transfer_orders')),
            "SELECT COUNT(*) FROM transfer_orders o WHERE {TENANT_SCOPE}
             AND o.planned_date IS NOT NULL AND o.planned_date < CURDATE()
             AND o.stage NOT IN('arrived','closed','cancelled')");
        $counts['req_waiting'] = (int) roleBoardScalar($gate, array('scope' => array('r' => 'transfer_requests')),
            "SELECT COUNT(*) FROM transfer_requests r WHERE {TENANT_SCOPE} AND r.state = 'submitted'");
        // «تسليمٌ بلا توثيق» = وصلت ولم تُغلق بتوثيق تسليمها (تقريبٌ موثَّق حتى يُبنى سجل التسليم)
        $counts['undocumented'] = (int) roleBoardScalar($gate, array('scope' => array('o' => 'transfer_orders')),
            "SELECT COUNT(*) FROM transfer_orders o WHERE {TENANT_SCOPE} AND o.stage = 'arrived'");

        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }
        }
    }

    // update0005 · CAP-35 (CAP-01 §10-①): الفجوةُ في لوحة مدير التشغيل
    // **بالساعات لا بالعدد فقط** — من مرقب الفجوة اليومي، والمقفلةُ تختفي.
    if ($rid === 1) {
        $gapHours = (float) roleBoardScalar($gate, array('scope' => array('g' => 'capacity_gap_watch')),
            "SELECT COALESCE(SUM(g.gap_hours),0) FROM capacity_gap_watch g
              WHERE {TENANT_SCOPE} AND g.closed_on IS NULL");
        $gapCount = (int) roleBoardScalar($gate, array('scope' => array('g' => 'capacity_gap_watch')),
            "SELECT COUNT(*) FROM capacity_gap_watch g WHERE {TENANT_SCOPE} AND g.closed_on IS NULL");
        if ($gapCount > 0) {
            $out[] = array('key' => 'coverage_gap_hours',
                'label' => 'فجوة تغطية تعاقدية: ' . number_format($gapHours, 1) . ' ساعة شهرية غير مغطاة',
                'href' => '../Contracts/contracts.php', 'tone' => 'err', 'count' => $gapCount);
        }
    }

    // DEC-01 ⑦ (قرار المالك): مؤشرُ الاعتمادات المتأخرة على لوحتَي 1و6 —
    // الرقمان نصًّا: غيرُ المعتمد وأقدمُه بالأيام (+نسبة الأسبوع) · والموروثُ
    // قبل السلسلة مستثنًى (unit_chain_helpers) · أحمرُ فوق سبعة أيام.
    if ($rid === 1 || $rid === 6) {
        require_once __DIR__ . '/unit_chain_helpers.php';
        $cidLag = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
        if ($cidLag > 0) {
            $mLag = ems_uc_lag_metrics($conn, $cidLag);
            if ($mLag['pending'] > 0) {
                $out[] = array('key' => 'chain_lag',
                    'label' => 'بانتظار الاعتماد: ' . $mLag['pending'] . ' وحدة · الأقدم '
                             . $mLag['oldest_days'] . ' يوما'
                             . ($mLag['ratio'] !== null ? ' · نسبة الأسبوع ' . $mLag['ratio'] . '٪' : ''),
                    'href' => '../Approvals/hours_approval.php',
                    'tone' => ($mLag['oldest_days'] > 7 ? 'err' : 'warn'),
                    'count' => $mLag['pending']);
            }
        }
    }

    // الأدوار على الشاشة العامة — عدّاداتُها بجدولٍ واحدٍ لكل تنبيه
    $generic = array(
        1 => array(
            // UI-DEF-03: كانت تعدّ صفوف timesheet كلَّها (status=1 يشمل الجميع) —
            // مصدرُ حقيقة الاعتماد سلسلةُ قيود الوحدات (القرار النافذ: السلسلة هي المسار).
            'units_since_yday' => array(array('t' => 'unit_entries', 'a' => 'ue'),
                "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state IN('submitted','site_approved','parties_approved') AND ue.entry_date < CURDATE()"),
            'site_no_ts' => array(array('t' => 'operations', 'a' => 'o', 'enrich' => array('ts' => 'timesheet')),
                "SELECT COUNT(DISTINCT o.project_id) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state = 'تعمل'
                 AND NOT EXISTS (SELECT 1 FROM timesheet ts WHERE ts.operator = o.id AND ts.date = CURDATE())"),
            'eq_down_noreason' => array(array('t' => 'operations', 'a' => 'o', 'enrich' => array('m' => 'mnt_order')),
                "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state = 'معطلة'
                 AND NOT EXISTS (SELECT 1 FROM mnt_order m WHERE m.equipment_id = o.equipment
                                 AND COALESCE(m.is_deleted,0) = 0 AND m.state IN('بلاغ','تنفيذ','فحص'))"),
        ),
        24 => array(
            'critical_unassigned' => array(array('t' => 'tickets', 'a' => 't'),
                "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.priority='critical'
                 AND t.assigned_user_id IS NULL AND t.stage NOT IN('closed','cancelled','done')"),
            'sla_broken' => array(array('t' => 'tickets', 'a' => 't'),
                "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.resolution_due_at IS NOT NULL
                 AND t.resolution_due_at < NOW() AND t.stage NOT IN('closed','cancelled','done')"),
            // صفٌّ لكل إدارةٍ تجاوزت بلاغين متأخّرين — والعدُّ على الصفوف
            // ('rows')، فالجدولُ المشتقُّ يُخفي WHERE داخل قوسٍ فترفضه البوابة.
            'repeat_late_dept' => array(array('t' => 'tickets', 'a' => 't'),
                "SELECT t.owner_role_id FROM tickets t WHERE {TENANT_SCOPE}
                 AND t.resolution_due_at IS NOT NULL AND t.resolution_due_at < NOW()
                 AND t.stage NOT IN('closed','cancelled','done')
                 GROUP BY t.owner_role_id HAVING COUNT(*) > 1", 'rows'),
        ),
        12 => array(
            'contract_ending' => array(array('t' => 'contracts', 'a' => 'c'),
                "SELECT COUNT(*) FROM contracts c WHERE {TENANT_SCOPE} AND c.status=1
                 AND c.actual_end IS NOT NULL AND c.actual_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'quote_stale' => array(array('t' => 'quotations', 'a' => 'q'),
                "SELECT COUNT(*) FROM quotations q WHERE {TENANT_SCOPE} AND q.state='مقدم'
                 AND q.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        ),
        2 => array(
            // UI-DEF-03: ما ينتظر أطرافَ الاعتماد (ومنهم المورد) = بعد اعتماد الموقع
            'units_pending_sup' => array(array('t' => 'unit_entries', 'a' => 'ue'),
                roleBoardSupplierPendingSql()),
            'sup_contract_end' => array(array('t' => 'supplierscontracts', 'a' => 'sc'),
                "SELECT COUNT(*) FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.status=1
                 AND sc.actual_end IS NOT NULL AND sc.actual_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
        ),
        3 => array(
            'eq_stopped' => array(array('t' => 'equipments', 'a' => 'e'),
                "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE} AND e.availability_status='تحت الصيانة'"),
            'meter_stale' => array(array('t' => 'equipments', 'a' => 'e', 'enrich' => array('mr' => 'meter_readings')),
                "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE}
                 AND NOT EXISTS (SELECT 1 FROM meter_readings mr WHERE mr.equipment_id = e.id
                                 AND mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))"),
            'doc_expiring' => array(array('t' => 'equipment_documents', 'a' => 'd'),
                "SELECT COUNT(*) FROM equipment_documents d WHERE {TENANT_SCOPE}
                 AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
        ),
        4 => array(
            'absence_noperm' => array(array('t' => 'worker_leave_absence', 'a' => 'wl'),
                "SELECT COUNT(*) FROM worker_leave_absence wl WHERE {TENANT_SCOPE}
                 AND wl.event_type LIKE '%غياب%' AND wl.state <> 'معتمد'"),
            'units_unapproved' => array(array('t' => 'unit_entries', 'a' => 'ue'),
                "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state IN('submitted','site_approved','parties_approved')"),
            'wcontract_end' => array(array('t' => 'worker_contract', 'a' => 'wc'),
                "SELECT COUNT(*) FROM worker_contract wc WHERE {TENANT_SCOPE} AND wc.state='نافذ'
                 AND wc.date_end IS NOT NULL AND wc.date_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            'settle_waiting' => array(array('t' => 'worker_settlement', 'a' => 'ws'),
                "SELECT COUNT(*) FROM worker_settlement ws WHERE {TENANT_SCOPE} AND ws.state='محتسب'"),
        ),
        5 => array(
            'ts_today_missing' => array(array('t' => 'timesheet', 'a' => 'ts'),
                "SELECT CASE WHEN COUNT(*)=0 THEN 1 ELSE 0 END FROM timesheet ts
                 WHERE {TENANT_SCOPE} AND ts.date = CURDATE()"),
            // مدير الموقع: ما يقف عنده فعلًا = المقدَّم الذي لم يعتمده الموقع بعد
            'units_await_me' => array(array('t' => 'unit_entries', 'a' => 'ue'),
                "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state='submitted'"),
            'capacity_flag' => array(array('t' => 'unit_capacity_flags', 'a' => 'f'),
                "SELECT COUNT(*) FROM unit_capacity_flags f WHERE {TENANT_SCOPE} AND f.cleared_at IS NULL"),
        ),
        6 => array(
            'eq_no_operator' => array(array('t' => 'operations', 'a' => 'o', 'enrich' => array('ed' => 'equipment_drivers')),
                // ed.operation_id لا وجودَ له: equipment_drivers يربط **معدةً بموظّف**
                // (equipment_id · employee_id) لا عمليةً بموظّف. والوصلُ المعتمَد في
                // المستودع كلِّه هو equipments.id = operations.equipment.
                "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='تعمل'
                 AND NOT EXISTS (SELECT 1 FROM equipment_drivers ed WHERE ed.equipment_id = o.equipment AND ed.status = 1)"),
            'assign_conflict' => array(array('t' => 'equipment_drivers', 'a' => 'ed'),
                // تعارضُ الإسناد = أكثرُ من مشغّلٍ نشطٍ على المعدة **في الوردية
                // الواحدة**. الوردية جزءٌ من التجميع لا زينة: معدةٌ لها مشغّلٌ
                // نهاريٌّ وآخرُ ليليٌّ تشغيلٌ طبيعيٌّ لا تعارض — وإسقاطُ shift_type
                // كان يعدّها تعارضًا (قِيس على البيانات الحية: 9 بلا الوردية · 6 بها).
                "SELECT COUNT(*) FROM (SELECT ed.equipment_id FROM equipment_drivers ed
                 WHERE {TENANT_SCOPE} AND ed.status = 1
                 GROUP BY ed.equipment_id, ed.shift_type HAVING COUNT(*) > 1) d"),
            'move_waiting' => array(array('t' => 'worker_movement', 'a' => 'wm'),
                "SELECT COUNT(*) FROM worker_movement wm WHERE {TENANT_SCOPE} AND wm.state = 'مسودة'"),
        ),
        15 => array(
            'user_no_role' => array(array('t' => 'users', 'a' => 'u'),
                "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE}
                 AND (u.role IS NULL OR u.role = '' OR u.role NOT IN (SELECT id FROM roles))"),
            // «دورٌ بلا شاشات» يُحسب أدناه مباشرةً — جداولُه (roles·role_permissions·modules)
            // كلُّها مراجعُ عالمية T_GLOBAL خارج نطاق البوابة
            'dormant_account' => array(array('t' => 'users', 'a' => 'u'),
                "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE} AND u.status = 'active'
                 AND (u.last_login_at IS NULL OR u.last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY))"),
        ),
    );
    if (isset($generic[$rid])) {
        $counts = array();
        foreach ($generic[$rid] as $key => $def) {
            $scopeArr = array('scope' => array($def[0]['a'] => $def[0]['t']));
            if (isset($def[0]['enrich'])) { $scopeArr['enrich'] = $def[0]['enrich']; }
            $counts[$key] = (isset($def[2]) && $def[2] === 'rows')
                ? roleBoardRowCount($gate, $scopeArr, $def[1])
                : (int) roleBoardScalar($gate, $scopeArr, $def[1]);
        }
        if ($rid === 15) {
            try {
                $r = $conn->query("SELECT COUNT(*) FROM roles ro WHERE (ro.status='1' OR ro.status=1) AND ro.id <> -1
                    AND NOT " . perm_role_any_view_exists_sql('ro.id') . "
                    AND NOT EXISTS (SELECT 1 FROM modules m WHERE m.owner_role_id = ro.id)");
                $counts['role_no_screens'] = $r ? intval($r->fetch_row()[0]) : 0;
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'فشل يعامل بقيمة افتراضية — $counts[\'role_no_screens\'] = 0'); $counts['role_no_screens'] = 0; }
        }
        // ── §8.7 عدّادا المبيعات — من `claim_helpers` لا باستعلامٍ منسوخ ──────
        // خارجَ الجدول التصريحي عمدًا: تعريفُ «الجاهز للفوترة» ثلاثُ وصلاتٍ
        // وشرطُ `IS NULL`، ونسخُه هنا يفتح بابَ الانفصام عن الشاشة (وهو الفخُّ
        // نفسُه الذي وقع في المستخلص حين سعّر من `executed_hours` والمروحةُ من
        // الكمية المحكومة). فالمصدرُ واحدٌ والنداءُ نداء — كنمط الدور 15 أعلاه.
        if ($rid === 12) {
            try {
                require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';
                $counts['unbilled_units'] = claim_unbilled_days($gate);
                $counts['claim_pending']  = claim_pending_count($gate);
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'تعذر يسجل ويطفئ التنبيه — ولا يخترع له رقم');
                // تعذّرٌ يُسجَّل ويُطفئ التنبيهَ — ولا يُخترع له رقم
                error_log('role_board sales counters: ' . $t->getMessage());
                $counts['unbilled_units'] = 0;
                $counts['claim_pending']  = 0;
            }
        }
        foreach (roleBoardAlertSpecs($rid) as $spec) {
            $n = isset($counts[$spec['key']]) ? intval($counts[$spec['key']]) : 0;
            if ($n > 0) { $spec['count'] = $n; $out[] = $spec; }
        }
    }
    return $out;
}

/**
 * ③ موافقاتي — سجل العدّادات: لكل مصدرٍ تعريفٌ واحد فلا يُحسب في شاشتين
 * بقيمتين (UX-01 §10.3). المفتاحُ نفسُه المستعمل في nav_items.counter_source.
 */
function roleBoardApprovals($conn, $gate, $roleId, array $navBadges = array())
{
    $rid = intval($roleId);
    $out = array();
    // العدّادات المحسوبة سلفًا للسايدبار تُعاد استعمالًا — مصدرٌ واحدٌ لا اثنان
    $map = array(
        'FinRequests/dept_inbox.php'      => array('موافقات إدارتي',  'fa fa-inbox'),
        'FinRequests/finance_gateway.php' => array('الطلبات المالية', 'fa fa-building-columns'),
        'FinRequests/accountant_desk.php' => array('مكتب المحاسب',    'fa fa-calculator'),
        'Approvals/hours_approval.php'    => array('اعتماد الوحدات التشغيلية', 'fa fa-check-double'),
    );
    foreach ($map as $route => $meta) {
        $n = isset($navBadges[$route]) ? intval($navBadges[$route]) : 0;
        if ($n > 0) {
            $out[] = array('label' => $meta[0], 'icon' => $meta[1], 'count' => $n, 'href' => '../' . $route);
        }
    }
    return $out;
}

/** ⑦ عملي الأخير — من سجل النشاط القائم (لا جدولَ جديد). */
function roleBoardRecent($conn, $userId, $limit = 6)
{
    $out = array();
    $uid = intval($userId);
    if ($uid <= 0) { return $out; }
    try {
        $q = $conn->prepare(
            "SELECT screen_name, url, MAX(created_at) AS last_at
               FROM activity_logs
              WHERE user_id = ? AND screen_name IS NOT NULL AND screen_name <> ''
              GROUP BY screen_name, url
              ORDER BY last_at DESC LIMIT " . intval($limit));
        $q->bind_param('i', $uid);
        $q->execute();
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) {
            // رسالةُ الفلاش التاريخية (msg=…) لا تُعاد بثُّها من رابط المتابعة —
            // نقرُ «عملي الأخير» كان يعرض «تمت الإضافة ✅» قديمةً كأنها الآن
            if (isset($r['url']) && $r['url'] !== null) {
                $r['url'] = preg_replace('/([?&])msg=[^&]*(&|$)/', '$1', (string) $r['url']);
                $r['url'] = rtrim((string) $r['url'], '?&');
            }
            $out[] = $r;
        }
        $q->close();
    } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'role_board recent'); error_log('role_board recent: ' . $t->getMessage()); }
    return $out;
}

/**
 * ⑤ إنشاء سريع — أكثر ثلاث عملياتٍ استخدامًا للدور: تُشتق من المصدر الموحّد
 * (باب العمل اليومي) مرتَّبةً باستعمال المستخدم الفعلي من سجل النشاط.
 */
function roleBoardQuickActions($conn, $roleId, $userId, $limit = 3)
{
    $out = array();
    $rid = intval($roleId); $uid = intval($userId);
    try {
        $rows = array();
        $q = $conn->prepare(
            "SELECT n.label_ar, n.route, n.icon,
                    (SELECT COUNT(*) FROM activity_logs a
                      WHERE a.user_id = ? AND a.url LIKE CONCAT('%', n.route, '%')) AS uses
               FROM nav_items n
              WHERE n.role_id = ? AND n.active = 1 AND n.door = 'DAILY'
                AND (n.permission_code IS NULL OR " . perm_nav_view_exists_sql('n') . ")
              ORDER BY uses DESC, n.sort_order ASC LIMIT " . intval($limit));
        $q->bind_param('ii', $uid, $rid);
        $q->execute();
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $q->close();
        $out = $rows;
    } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'role_board quick'); error_log('role_board quick: ' . $t->getMessage()); }
    return $out;
}

/**
 * إعدادُ اللوحة العامة للأدوار الثمانية (main/role_board.php):
 * بطاقاتُ ① من أسئلة الدور (UX-01 §8) + مهامُّ ② + نبضُ ⑥ — كلُّ عنصرٍ
 * [label, icon, scope[], sql, href] والعزلُ عبر البوابة، وكلُّ رقمٍ بقفزة.
 */
function roleBoardGenericConfig($rid)
{
    $C = array(
        // UX-03 §6 · إدارة التشغيل — «تغطيةُ ورديات الغد» بلا جدولِ خطةِ غدٍ بعد
        // (دورةُ التوزيع §2.2 تُبنى مع غرفة العمليات) → بديلُها الصادق اليوم:
        // «تعمل بلا مشغّلٍ نشط». و«انحرافُ الالتزام» ينتظر محرّكَه (تبويب ④).
        1 => array('title' => 'لوحة ادارة التشغيل', 'icon' => 'fa fa-tower-observation',
            'cards' => array(
                array('مواقع لم ترفع اليوم', 'fa-location-crosshairs', array('t' => 'operations', 'a' => 'o', 'enrich' => array('ts' => 'timesheet')), "SELECT COUNT(DISTINCT o.project_id) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='تعمل' AND NOT EXISTS(SELECT 1 FROM timesheet ts WHERE ts.operator=o.id AND ts.date=CURDATE())", '../Timesheet/timesheet_type.php', 'err'),
                // UI-DEF-03: كانت تعدّ 48,746 صفَّ دوامٍ كلَّها وتسميها «وحدات» —
                // المصدر الصادق قيود الوحدات في حالات ما قبل التحويل (المقام معلن بالتسمية).
                array('وحدات تنتظر الاعتماد (قيود الوحدات قبل التحويل)', 'fa-check-double', array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state IN('submitted','site_approved','parties_approved')", '../Approvals/hours_approval.php', 'warn'),
                array('معدات متوقفة', 'fa-heart-crack', array('t' => 'operations', 'a' => 'o'), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='معطلة'", '../Oprators/select_project.php', 'err'),
                array('تعمل بلا مشغل نشط', 'fa-user-slash', array('t' => 'operations', 'a' => 'o', 'enrich' => array('ed' => 'equipment_drivers')), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='تعمل' AND NOT EXISTS(SELECT 1 FROM equipment_drivers ed WHERE ed.equipment_id=o.equipment AND ed.status=1)", '../Oprators/select_project.php', 'err'),
                array('التزامات العقود', 'fa-file-signature', array('t' => 'contract_commitments', 'a' => 'cc'), "SELECT COUNT(*) FROM contract_commitments cc WHERE {TENANT_SCOPE} AND COALESCE(cc.is_deleted,0)=0", '../Clients/contract_commitments.php', 'or'),
            ),
            'tasks' => array(
                array('اعتماد الوحدات المنتظرة (قبل التحويل)', 'fa fa-check-double', array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state IN('submitted','site_approved','parties_approved')", '../Approvals/hours_approval.php'),
                array('مطالبة المواقع المتأخرة بالرفع', 'fa fa-bullhorn', array('t' => 'operations', 'a' => 'o', 'enrich' => array('ts' => 'timesheet')), "SELECT COUNT(DISTINCT o.project_id) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='تعمل' AND NOT EXISTS(SELECT 1 FROM timesheet ts WHERE ts.operator=o.id AND ts.date=CURDATE())", '../Timesheet/timesheet_type.php'),
            ),
            'pulse' => array('نبض الأداء — وحدات الدوام (7 أيام)', array('أدخلت', 'اعتمدت'),
                array('t' => 'timesheet', 'a' => 'ts'), "SELECT COUNT(*) FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=?",
                // كانت السلسلة «اعتُمدت» ميتةً (status<>1 لا يطابق شيئًا) — التحويل هو الاعتماد النهائي
                array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND DATE(ue.converted_at)=?")),
        24 => array('title' => 'لوحة إدارة البلاغات', 'icon' => 'fa fa-headset',
            'cards' => array(
                array('بلاغات مفتوحة', 'fa-envelope-open', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.stage NOT IN('closed','cancelled','done')", '../Tickets/tickets_list.php', 'or'),
                array('حرجة مفتوحة', 'fa-fire', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.priority='critical' AND t.stage NOT IN('closed','cancelled','done')", '../Tickets/tickets_list.php', 'err'),
                array('كسرت مهلتها', 'fa-hourglass-end', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.resolution_due_at<NOW() AND t.stage NOT IN('closed','cancelled','done')", '../Tickets/tickets_list.php', 'err'),
                array('بلا مستلم', 'fa-user-slash', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.assigned_user_id IS NULL AND t.stage NOT IN('closed','cancelled','done')", '../Tickets/tickets_list.php', 'warn'),
                array('أغلقت هذا الأسبوع', 'fa-circle-check', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.stage='closed' AND t.updated_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)", '../Tickets/tickets_list.php', 'ok'),
            ),
            'tasks' => array(
                array('بلاغات موجهة تنتظر الاستلام', 'fa fa-inbox', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.stage='routed'", '../Tickets/tickets_list.php'),
                array('قيد المعالجة والمتابعة', 'fa fa-spinner', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.stage IN('in_progress','waiting','follow_up')", '../Tickets/tickets_list.php'),
                array('منجزة تنتظر الإغلاق', 'fa fa-flag-checkered', array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.stage='done'", '../Tickets/tickets_list.php'),
            ),
            'pulse' => array('نبض الأداء — فتحت مقابل أغلقت (7 أيام)', array('فتحت', 'أغلقت'),
                array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND DATE(t.created_at)=?",
                array('t' => 'tickets', 'a' => 't'), "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE} AND t.stage='closed' AND DATE(t.updated_at)=?")),
        12 => array('title' => 'لوحة ادارة المبيعات', 'icon' => 'fa fa-chart-line',
            'cards' => array(
                array('فرص مفتوحة', 'fa-bullseye', array('t' => 'opportunities', 'a' => 'op'), "SELECT COUNT(*) FROM opportunities op WHERE {TENANT_SCOPE} AND op.stage NOT IN('فوز','خسارة','مستبعدة')", '../Opportunities/opportunities.php', 'or'),
                array('عروض مقدمة بلا رد', 'fa-file-signature', array('t' => 'quotations', 'a' => 'q'), "SELECT COUNT(*) FROM quotations q WHERE {TENANT_SCOPE} AND q.state='مقدم'", '../Clients/quotations.php', 'warn'),
                array('عقود نشطة', 'fa-file-contract', array('t' => 'contracts', 'a' => 'c'), "SELECT COUNT(*) FROM contracts c WHERE {TENANT_SCOPE} AND c.status=1", '../Contracts/contracts.php', 'ok'),
                array('تنتهي خلال 30 يوما', 'fa-hourglass-half', array('t' => 'contracts', 'a' => 'c'), "SELECT COUNT(*) FROM contracts c WHERE {TENANT_SCOPE} AND c.status=1 AND c.actual_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)", '../Contracts/contracts.php', 'err'),
                array('مناقصات', 'fa-gavel', array('t' => 'tenders', 'a' => 'tn'), "SELECT COUNT(*) FROM tenders tn WHERE {TENANT_SCOPE}", '../Clients/tenders.php', 'or'),
            ),
            'tasks' => array(
                array('فرص في التفاوض تحتاج دفعة', 'fa fa-handshake', array('t' => 'opportunities', 'a' => 'op'), "SELECT COUNT(*) FROM opportunities op WHERE {TENANT_SCOPE} AND op.stage='تفاوض'", '../Opportunities/opportunities.php'),
                array('عروض مسودة لم ترسل', 'fa fa-pen', array('t' => 'quotations', 'a' => 'q'), "SELECT COUNT(*) FROM quotations q WHERE {TENANT_SCOPE} AND q.state='مسودة'", '../Clients/quotations.php'),
            ),
            'pulse' => array('نبض الأداء — فرص أنشئت مقابل عروض قدمت (7 أيام)', array('فرص', 'عروض'),
                array('t' => 'opportunities', 'a' => 'op'), "SELECT COUNT(*) FROM opportunities op WHERE {TENANT_SCOPE} AND DATE(op.created_at)=?",
                array('t' => 'quotations', 'a' => 'q'), "SELECT COUNT(*) FROM quotations q WHERE {TENANT_SCOPE} AND DATE(q.created_at)=?")),
        2 => array('title' => 'لوحة ادارة الموردين', 'icon' => 'fa fa-truck-field',
            'cards' => array(
                array('الموردون', 'fa-truck-field', array('t' => 'suppliers', 'a' => 's'), "SELECT COUNT(*) FROM suppliers s WHERE {TENANT_SCOPE}", '../Suppliers/suppliers.php', 'or'),
                array('عقود نشطة', 'fa-file-contract', array('t' => 'supplierscontracts', 'a' => 'sc'), "SELECT COUNT(*) FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.status=1", '../Suppliers/supplierscontracts.php', 'ok'),
                array('تنتهي خلال 30 يوما', 'fa-hourglass-half', array('t' => 'supplierscontracts', 'a' => 'sc'), "SELECT COUNT(*) FROM supplierscontracts sc WHERE {TENANT_SCOPE} AND sc.status=1 AND sc.actual_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)", '../Suppliers/supplierscontracts.php', 'err'),
                /* العنوانُ يقول ما يقيسه الاستعلامُ حرفًا — «أطرافُ الاعتماد» كانت
                   تعني أيَّ طرفٍ بينما الإدارةُ تنتظر طرفَ الموردِ وحدَه. */
                array('وحدات تنتظر أطراف الاعتماد (بعد الموقع)', 'fa-scale-balanced', array('t' => 'unit_entries', 'a' => 'ue'), roleBoardSupplierPendingSql(), '../Approvals/hours_approval.php', 'warn'),
            ),
            'tasks' => array(
                array('وحدات تنتظر اعتماد جزء المورد (بعد الموقع)', 'fa fa-check-double', array('t' => 'unit_entries', 'a' => 'ue'), roleBoardSupplierPendingSql(), '../Approvals/hours_approval.php'),
            ),
            'pulse' => array('نبض الأداء — وحدات الدوام المدخلة (7 أيام)', array('أدخلت', 'اعتمدت'),
                array('t' => 'timesheet', 'a' => 'ts'), "SELECT COUNT(*) FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=?",
                // كانت السلسلة «اعتُمدت» ميتةً (status<>1 لا يطابق شيئًا) — التحويل هو الاعتماد النهائي
                array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND DATE(ue.converted_at)=?")),
        3 => array('title' => 'لوحة ادارة الاسطول', 'icon' => 'fa fa-tractor',
            'cards' => array(
                array('إجمالي المعدات', 'fa-tractor', array('t' => 'equipments', 'a' => 'e'), "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE}", '../Equipments/equipments_fleet.php', 'or'),
                array('قيد الاستخدام', 'fa-play-circle', array('t' => 'equipments', 'a' => 'e'), "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE} AND e.availability_status='قيد الاستخدام'", '../Equipments/equipments_fleet.php', 'ok'),
                array('متاحة للعمل', 'fa-circle-check', array('t' => 'equipments', 'a' => 'e'), "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE} AND e.availability_status='متاحة للعمل'", '../Equipments/equipments_fleet.php', 'ok'),
                array('تحت الصيانة', 'fa-screwdriver-wrench', array('t' => 'equipments', 'a' => 'e'), "SELECT COUNT(*) FROM equipments e WHERE {TENANT_SCOPE} AND e.availability_status='تحت الصيانة'", '../Equipments/equipments_fleet.php', 'err'),
                array('معطلة (التشغيل)', 'fa-heart-crack', array('t' => 'operations', 'a' => 'o'), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.equipment_health='معطلة'", '../Equipments/equipments_fleet.php', 'err'),
            ),
            'tasks' => array(
                // مؤشرٌ فاسدٌ أُصلح 2026-07-27: كان يقرأ meter_readings **غيرَ الموجود**
                // فيفشل صامتًا — استُبدل بمؤشرَي الوثائق (المصدرُ قائمٌ ومقيس:
                // 37 من 39 وثيقةً منتهيةً يومَ البناء). قراءاتُ العدّاد حين يُبنى
                // جدولُها (UX-10 §8.1 ب) يعود مؤشرُها بمصدرٍ حقيقي.
                array('وثائق منتهية (معدات ومشغلون)', 'fa fa-file-circle-xmark', array('t' => 'equipment_documents', 'a' => 'd'), "SELECT COUNT(*) FROM equipment_documents d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة' AND d.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()", '../Equipments/equipment_documents.php'),
                array('وثائق توشك على الانتهاء (بمهلة كل وثيقة)', 'fa fa-hourglass-half', array('t' => 'equipment_documents', 'a' => 'd'), "SELECT COUNT(*) FROM equipment_documents d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة' AND d.expiry_date >= CURDATE() AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL d.alert_days DAY)", '../Equipments/equipment_documents.php'),
            ),
            'pulse' => array('نبض الأداء — أوامر صيانة فتحت مقابل أغلقت على الأسطول (7 أيام)', array('فتحت', 'أغلقت'),
                array('t' => 'mnt_order', 'a' => 'mo'), "SELECT COUNT(*) FROM mnt_order mo WHERE {TENANT_SCOPE} AND COALESCE(mo.is_deleted,0)=0 AND DATE(mo.created_at)=?",
                array('t' => 'mnt_order', 'a' => 'mo'), "SELECT COUNT(*) FROM mnt_order mo WHERE {TENANT_SCOPE} AND COALESCE(mo.is_deleted,0)=0 AND mo.state='إغلاق' AND DATE(mo.closed_at)=?")),
        4 => array('title' => 'لوحة ادارة الموارد البشرية', 'icon' => 'fa fa-id-card',
            'cards' => array(
                array('الموظفون', 'fa-id-card', array('t' => 'employees', 'a' => 'em'), "SELECT COUNT(*) FROM employees em WHERE {TENANT_SCOPE}", '../Employees/employees.php', 'or'),
                array('وحدات تنتظر الاعتماد (قيود الوحدات قبل التحويل)', 'fa-scale-balanced', array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state IN('submitted','site_approved','parties_approved')", '../Approvals/hours_approval.php', 'warn'),
                array('طلبات إجازة منتظرة', 'fa-umbrella-beach', array('t' => 'worker_leave_absence', 'a' => 'wl'), "SELECT COUNT(*) FROM worker_leave_absence wl WHERE {TENANT_SCOPE} AND wl.state='مطلوب'", '../Workforce/worker_leave_absence.php', 'warn'),
                array('عقود تنتهي (30 يوما)', 'fa-hourglass-half', array('t' => 'worker_contract', 'a' => 'wc'), "SELECT COUNT(*) FROM worker_contract wc WHERE {TENANT_SCOPE} AND wc.state='نافذ' AND wc.date_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)", '../Workforce/worker_contract.php', 'err'),
                array('تسويات تنتظر الاعتماد', 'fa-hand-holding-dollar', array('t' => 'worker_settlement', 'a' => 'ws'), "SELECT COUNT(*) FROM worker_settlement ws WHERE {TENANT_SCOPE} AND ws.state='محتسب'", '../Workforce/worker_settlement.php', 'warn'),
                // ── تنبيهاتُ وثائق الأفراد (طلبُ المالك 2026-07-27) ──────────────
                // المقيسُ يومَ الإضافة: 25 رخصةَ قيادةٍ منتهية و26 هويةً منتهية —
                // وأصحابُها كلُّهم بحالةٍ نشطةٍ يعملون. المصدرُ ملفُّ الوثائق
                // الموحّد لا الأعمدةُ المتناثرة، فالتجديدُ يُسجَّل مرةً واحدةً ويُرى هنا.
                array('رخص قيادة منتهية', 'fa-id-card-clip', array('t' => 'equipment_documents', 'a' => 'd'), "SELECT COUNT(*) FROM equipment_documents d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة' AND d.subject_type='operator' AND d.doc_type='رخصة قيادة' AND d.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()", '../Equipments/equipment_documents.php', 'err'),
                array('هويات منتهية', 'fa-address-card', array('t' => 'equipment_documents', 'a' => 'd'), "SELECT COUNT(*) FROM equipment_documents d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة' AND d.subject_type='operator' AND d.doc_type='هوية' AND d.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()", '../Equipments/equipment_documents.php', 'err'),
            ),
            'tasks' => array(
                array('وثائق أفراد توشك على الانتهاء (بمهلة كل وثيقة)', 'fa fa-hourglass-half', array('t' => 'equipment_documents', 'a' => 'd'), "SELECT COUNT(*) FROM equipment_documents d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.status<>'ملغاة' AND d.subject_type='operator' AND d.expiry_date >= CURDATE() AND d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL d.alert_days DAY)", '../Equipments/equipment_documents.php'),
                array('تقييمات وتنقلات قيد المعالجة', 'fa fa-people-arrows', array('t' => 'worker_movement', 'a' => 'wm'), "SELECT COUNT(*) FROM worker_movement wm WHERE {TENANT_SCOPE} AND wm.state='مسودة'", '../Workforce/worker_movement.php'),
            ),
            'pulse' => array('نبض الأداء — وحدات الدوام (7 أيام)', array('أدخلت', 'اعتمدت'),
                array('t' => 'timesheet', 'a' => 'ts'), "SELECT COUNT(*) FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=?",
                // كانت السلسلة «اعتُمدت» ميتةً (status<>1 لا يطابق شيئًا) — التحويل هو الاعتماد النهائي
                array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND DATE(ue.converted_at)=?")),
        5 => array('title' => 'لوحة مدير الموقع', 'icon' => 'fa fa-map-location-dot',
            'cards' => array(
                array('تايم شيت اليوم', 'fa-business-time', array('t' => 'timesheet', 'a' => 'ts'), "SELECT COUNT(*) FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=CURDATE()", '../Timesheet/timesheet_type.php', 'or'),
                array('وحدات تنتظر اعتماد الموقع (المقدمة)', 'fa-check-double', array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state='submitted'", '../Approvals/hours_approval.php', 'warn'),
                array('أعلام تجاوز الطاقة', 'fa-triangle-exclamation', array('t' => 'unit_capacity_flags', 'a' => 'f'), "SELECT COUNT(*) FROM unit_capacity_flags f WHERE {TENANT_SCOPE} AND f.cleared_at IS NULL", '../Timesheet/timesheet_type.php', 'err'),
            ),
            'tasks' => array(
                array('إدخال تايم شيت اليوم', 'fa fa-plus', array('t' => 'timesheet', 'a' => 'ts'), "SELECT CASE WHEN COUNT(*)=0 THEN 1 ELSE 0 END FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=CURDATE()", '../Timesheet/timesheet_type.php'),
                array('اعتماد وحدات الموقع (المقدمة)', 'fa fa-check-double', array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND ue.state='submitted'", '../Approvals/hours_approval.php'),
            ),
            'pulse' => array('نبض الأداء — وحدات الموقع (7 أيام)', array('أدخلت', 'اعتمدت'),
                array('t' => 'timesheet', 'a' => 'ts'), "SELECT COUNT(*) FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=?",
                // كانت السلسلة «اعتُمدت» ميتةً (status<>1 لا يطابق شيئًا) — التحويل هو الاعتماد النهائي
                array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND DATE(ue.converted_at)=?")),
        6 => array('title' => 'لوحة مدير الحركة والتشغيل', 'icon' => 'fa fa-people-arrows',
            'cards' => array(
                array('تشغيلات تعمل الآن', 'fa-play-circle', array('t' => 'operations', 'a' => 'o'), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='تعمل'", '../Oprators/select_project.php', 'ok'),
                array('جاهزة (احتياط)', 'fa-circle-pause', array('t' => 'operations', 'a' => 'o'), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='جاهزة'", '../Oprators/select_project.php', 'or'),
                array('معطلة', 'fa-heart-crack', array('t' => 'operations', 'a' => 'o'), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='معطلة'", '../Oprators/select_project.php', 'err'),
                array('عاملة بلا مشغل', 'fa-user-slash', array('t' => 'operations', 'a' => 'o', 'enrich' => array('ed' => 'equipment_drivers')), "SELECT COUNT(*) FROM operations o WHERE {TENANT_SCOPE} AND o.op_state='تعمل' AND NOT EXISTS(SELECT 1 FROM equipment_drivers ed WHERE ed.equipment_id=o.equipment AND ed.status=1)", '../Oprators/select_project.php', 'err'),
            ),
            'tasks' => array(
                array('طلبات تنقل تنتظر', 'fa fa-people-arrows', array('t' => 'worker_movement', 'a' => 'wm'), "SELECT COUNT(*) FROM worker_movement wm WHERE {TENANT_SCOPE} AND wm.state='مسودة'", '../Workforce/worker_movement.php'),
            ),
            'pulse' => array('نبض الأداء — وحدات الدوام (7 أيام)', array('أدخلت', 'اعتمدت'),
                array('t' => 'timesheet', 'a' => 'ts'), "SELECT COUNT(*) FROM timesheet ts WHERE {TENANT_SCOPE} AND ts.date=?",
                // كانت السلسلة «اعتُمدت» ميتةً (status<>1 لا يطابق شيئًا) — التحويل هو الاعتماد النهائي
                array('t' => 'unit_entries', 'a' => 'ue'), "SELECT COUNT(*) FROM unit_entries ue WHERE {TENANT_SCOPE} AND DATE(ue.converted_at)=?")),
        15 => array('title' => 'لوحة مدير الصلاحيات', 'icon' => 'fa fa-user-shield',
            'cards' => array(
                array('المستخدمون', 'fa-users', array('t' => 'users', 'a' => 'u'), "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE} AND u.role<>'-1'", '../main/users.php', 'or'),
                array('نشطون', 'fa-user-check', array('t' => 'users', 'a' => 'u'), "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE} AND u.status='active'", '../main/users.php', 'ok'),
                array('خاملون (90 يوما)', 'fa-user-clock', array('t' => 'users', 'a' => 'u'), "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE} AND u.status='active' AND (u.last_login_at IS NULL OR u.last_login_at<DATE_SUB(NOW(),INTERVAL 90 DAY))", '../main/users.php', 'warn'),
                array('بلا دور صالح', 'fa-user-slash', array('t' => 'users', 'a' => 'u'), "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE} AND (u.role IS NULL OR u.role='' OR u.role NOT IN(SELECT id FROM roles))", '../main/users.php', 'err'),
            ),
            'tasks' => array(),
            'pulse' => array('نبض الأداء — دخول المستخدمين (7 أيام)', array('دخلوا', ''),
                array('t' => 'users', 'a' => 'u'), "SELECT COUNT(*) FROM users u WHERE {TENANT_SCOPE} AND DATE(u.last_login_at)=?",
                null, null)),
        /* ── §8.10 · إدارة المشتريات ───────────────────────────────────────
           نُقلت لوحتُها من `Procurement/dashboard_proc.php` إلى «الرئيسية»
           (قرار المالك 2026-08-21). البطاقاتُ السبعُ **بعدّاداتِ البوابةِ
           نفسِها** (`count` بشروطِها حرفًا) فلا يتبدّل رقمٌ بسببِ نقلِ مكان.
           ومهامُّها ② من `roleBoardTasks(16)`، وتنبيهاتُها ④ من §8.10.
           ◆ **وجدولُ «معداتٌ بانتظار قطع» لم يسقط**: استُخرج جزءَ عرضٍ
             (`view`) يُصيَّر داخلَ اللوحة بمصدرِه نفسِه.
           ◆ **وجدولُ «القطع الحرجة» لم يُنقَل عمدًا**: هو سجلٌّ لا مؤشر،
             وأعمدتُه السّتُّ كلُّها قائمةٌ في `items_proc.php` ومعها «قطعة
             حرجة؟» — فالبطاقةُ تنقر إليه ولا يضيع شيء. */
        16 => array('title' => 'لوحة إدارة المشتريات', 'icon' => 'fa fa-cart-flatbed',
            'perm' => 'Procurement/dashboard_proc.php',
            'view' => 'Procurement/_board_waiting.php',
            'cards' => array(
                array('الأصناف', 'fa-boxes-stacked', array('t' => 'proc_item', 'a' => 'i'),
                    function ($g) { return (float) $g->count('proc_item'); },
                    '../Procurement/items_proc.php', 'or', array('unit' => 'صنف')),

                array('القطع الحرجة', 'fa-triangle-exclamation', array('t' => 'proc_item', 'a' => 'i'),
                    function ($g) { return (float) $g->count('proc_item', array('where' => array('is_critical' => 1))); },
                    '../Procurement/items_proc.php', 'err', array('unit' => 'صنف')),

                array('طلبات شراء مفتوحة', 'fa-file-lines', array('t' => 'proc_request', 'a' => 'r'),
                    function ($g) { return (float) $g->count('proc_request', array('whereRaw' => 'state NOT IN (?, ?)', 'params' => array('مغلق', 'مرفوض'))); },
                    '../Procurement/requests_proc.php', 'warn', array('unit' => 'طلب')),

                array('أوامر شراء مؤكدة', 'fa-file-invoice-dollar', array('t' => 'proc_order', 'a' => 'o'),
                    function ($g) { return (float) $g->count('proc_order', array('where' => array('state' => 'مؤكَّد'))); },
                    '../Procurement/orders_proc.php', 'ok', array('unit' => 'أمر')),

                array('عهد استلام مفتوحة', 'fa-truck-ramp-box', array('t' => 'proc_receipt_custody', 'a' => 'c'),
                    function ($g) { return (float) $g->count('proc_receipt_custody', array('whereRaw' => 'state <> ?', 'params' => array('مسلَّمة للوجهة'))); },
                    '../Procurement/receipt_custody_proc.php', 'warn', array('unit' => 'عهدة')),

                array('عمليات الصرف', 'fa-hand-holding-box', array('t' => 'proc_issue', 'a' => 's'),
                    function ($g) { return (float) $g->count('proc_issue'); },
                    '../Procurement/issue_proc.php', 'or', array('unit' => 'عملية')),

                array('الموردون التشغيليون', 'fa-truck-field', array('t' => 'proc_supplier', 'a' => 'p'),
                    function ($g) { return (float) $g->count('proc_supplier'); },
                    '../Procurement/suppliers_proc.php', 'or', array('unit' => 'مورد')),
            ),
            // ② مهامُّ الدورِ منطقُها مكتوبٌ في roleBoardTasks(16) — لا تُكرَّر هنا
            'tasks' => array(),
            'pulse' => array('نبض الأداء — طلبات واردة مقابل صرفيات (7 أيام)', array('طلبات واردة', 'صرفيات'),
                array('t' => 'proc_request', 'a' => 'r'),
                "SELECT COUNT(*) FROM proc_request r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND DATE(r.created_at)=?",
                array('t' => 'proc_issue', 'a' => 'i'),
                "SELECT COUNT(*) FROM proc_issue i WHERE {TENANT_SCOPE} AND COALESCE(i.is_deleted,0)=0 AND DATE(i.created_at)=?")),
        /* ── §8.11 · الإدارة المالية ───────────────────────────────────────
           نُقلت لوحتُها من `Finance/cfo_daily_board_fin.php` إلى «الرئيسية»
           (قرار المالك 2026-08-21). البطاقاتُ العشرُ **منقولةٌ حرفًا**: نفسُ
           الاستعلاماتِ ونفسُ النطاقاتِ ونفسُ النغمات — فلا يتبدّل رقمٌ ماليٌّ
           بسببِ نقلِ مكان. ومهامُّها ② تأتي من `roleBoardTasks(17)` (منطقٌ
           مكتوبٌ لا تصريحيّ)، وتنبيهاتُها ④ من `roleBoardAlertSpecs(17)`.
           ◆ **الوحدةُ تُعلَن ولا تُلفَّق**: هذه مبالغُ لا سجلّات، وأربعةٌ من
             مصادرِها تحمل عمودَ `currency` فالمجموعُ قد يخلط عملات — وهو
             واقعٌ مقيسٌ في القاعدة (`fin_payments`: SDG وUSD · `fin_dues`:
             أربعُ عملات). فتُعلن البطاقةُ ذلك في سطرِ نطاقِها بدل أن تسمّي
             عملةً لا تملكها. والإصلاحُ (القراءةُ من `base_amount`) قرارُ
             مالكٍ مستقلٌّ عن النقل — لأنه يغيّر أرقامًا لا مواضع. */
        17 => array('title' => 'لوحة الإدارة المالية', 'icon' => 'fa fa-building-columns',
            'perm' => 'Finance/cfo_daily_board_fin.php',
            // «جدولُ القرارِ اليومي» كان في جسدِ الشاشةِ المنقولة — استُخرج
            // جزءَ عرضٍ فلا يسقط بالنقل (كاد يسقط: أُضيف بعد اكتشافِ سقوطه).
            'view' => 'Finance/_board_decisions.php',
            'cards' => array(
                // ① النقد المتاح = افتتاحيُّ البنوك + صافي حركة الكشوف (قراءتان معزولتان تُجمعان)
                array('النقد المتاح (البنوك)', 'fa-wallet', array('t' => 'fin_bank_accounts', 'a' => 'a'),
                    function ($g) {
                        return roleBoardScalar($g, array('scope' => array('a' => 'fin_bank_accounts')),
                                "SELECT COALESCE(SUM(a.opening_balance),0) FROM fin_bank_accounts a WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0)=0")
                             + roleBoardScalar($g, array('scope' => array('l' => 'fin_bank_statement_lines')),
                                "SELECT COALESCE(SUM(CASE WHEN l.direction='deposit' THEN l.amount ELSE -l.amount END),0) FROM fin_bank_statement_lines l WHERE {TENANT_SCOPE}");
                    },
                    '../Finance/bank_reconciliation_fin.php', 'ok',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'scope' => 'يجمع عملات الحسابات')),

                array('متحصلات اليوم', 'fa-arrow-down', array('t' => 'fin_payments', 'a' => 'p'),
                    "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.direction='collection' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=CURDATE()",
                    '../Finance/payments_fin.php', 'ok',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'scope' => 'يجمع عملات الدفعات')),

                array('مدفوعات اليوم', 'fa-arrow-up', array('t' => 'fin_payments', 'a' => 'p'),
                    "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.direction='disbursement' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=CURDATE()",
                    '../Finance/payments_fin.php', 'or',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'scope' => 'يجمع عملات الدفعات')),

                // ④ صافي الأسبوع = ذممٌ تستحق خلال 7 أيام − (مستحقاتٌ معلّقة + أقساطُ 7 أيام)
                array('صافي الأسبوع المتوقع', 'fa-scale-unbalanced', array('t' => 'fin_receivables', 'a' => 'r'),
                    function ($g) {
                        $wk = date('Y-m-d', strtotime('+7 days'));
                        $in  = roleBoardScalar($g, array('scope' => array('r' => 'fin_receivables')),
                                "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<=?", array($wk));
                        $out = roleBoardScalar($g, array('scope' => array('d' => 'fin_dues')),
                                "SELECT COALESCE(SUM(d.amount),0) FROM fin_dues d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit' AND d.settlement_state='pending'")
                             + roleBoardScalar($g, array('scope' => array('f' => 'fin_funding_schedules')),
                                "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date<=?", array($wk));
                        return $in - $out;
                    },
                    '../Finance/cash_forecast_fin.php', 'ok',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'period' => 'الأيام السبعة القادمة', 'scope' => 'يجمع عملات الذمم والمستحقات')),

                array('وحدات أمس المعتمدة', 'fa-cubes', array('t' => 'fin_unit_records', 'a' => 'u'),
                    "SELECT COALESCE(SUM(u.approved_qty),0) FROM fin_unit_records u WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.match_state='approved' AND u.record_date=DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
                    '../Finance/unit_records_fin.php', 'or',
                    array('unit' => 'وحدة', 'decimals' => 2, 'period' => 'أمس')),

                array('هامش الوحدة الجاري', 'fa-percent', array('t' => 'fin_unit_records', 'a' => 'u'),
                    "SELECT COALESCE(SUM(u.unit_margin),0) FROM fin_unit_records u WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.match_state='approved' AND DATE_FORMAT(u.record_date,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')",
                    '../Finance/unit_records_fin.php', 'ok',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'period' => 'هذا الشهر')),

                array('الذمم المتأخرة', 'fa-hourglass-end', array('t' => 'fin_receivables', 'a' => 'r'),
                    "SELECT COALESCE(SUM(r.outstanding),0) FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.outstanding>0 AND r.due_date IS NOT NULL AND r.due_date<CURDATE()",
                    '../Finance/dues_fin.php', 'err',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'scope' => 'يجمع عملات الذمم')),

                array('المسوى الجاهز للصرف', 'fa-hand-holding-dollar', array('t' => 'fin_dues', 'a' => 'd'),
                    "SELECT COALESCE(SUM(d.amount),0) FROM fin_dues d WHERE {TENANT_SCOPE} AND COALESCE(d.is_deleted,0)=0 AND d.direction='credit' AND d.settlement_state='settled'",
                    '../Finance/payments_fin.php', 'or',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'scope' => 'يجمع عملات المستحقات')),

                array('انحرافات فوق 10%', 'fa-triangle-exclamation', array('t' => 'fin_budget_lines', 'a' => 'l', 'enrich' => array('b' => 'fin_budgets')),
                    "SELECT COUNT(*) FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id=l.budget_id WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0 AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct)>10",
                    '../Finance/budget_form_fin.php', 'err',
                    array('unit' => 'سطر موازنة')),

                array('أقساط تمويل خلال 7 أيام', 'fa-landmark', array('t' => 'fin_funding_schedules', 'a' => 'f'),
                    "SELECT COALESCE(SUM(f.total_due-f.paid_amount),0) FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
                    '../Finance/funding_fin.php', 'or',
                    array('unit' => 'مبلغ', 'decimals' => 0, 'period' => 'الأيام السبعة القادمة')),
            ),
            // ② مهامُّ الدورِ منطقُها مكتوبٌ في roleBoardTasks(17) — لا تُكرَّر هنا
            'tasks' => array(),
            'pulse' => array('نبض الأداء — التحصيل مقابل الصرف (7 أيام)', array('تحصيل', 'صرف'),
                array('t' => 'fin_payments', 'a' => 'p'),
                "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.direction='collection' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=?",
                array('t' => 'fin_payments', 'a' => 'p'),
                "SELECT COALESCE(SUM(p.amount),0) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 AND p.direction='disbursement' AND p.state IN('executed','reconciled') AND DATE(COALESCE(p.paid_at,p.created_at))=?")),
    );
    return isset($C[intval($rid)]) ? $C[intval($rid)] : null;
}

/**
 * الدورُ صاحبُ الإعدادِ العام: دورُ الجلسةِ نفسُه، أو أبوه إن كان فرعيًّا بلا
 * إعدادٍ خاص («الفرعيةُ ترث أباها» — قرار المالك). يعيد 0 إن لم يُوجد إعداد.
 */
function roleBoardConfigRole($gate, $roleId)
{
    $rid = intval($roleId);
    if (roleBoardGenericConfig($rid) !== null) { return $rid; }
    try {
        $p = $gate->selectOne('roles', array('columns' => array('parent_role_id'), 'where' => array('id' => $rid)));
        if ($p && !empty($p['parent_role_id'])) {
            $pid = intval($p['parent_role_id']);
            if (roleBoardGenericConfig($pid) !== null) { return $pid; }
        }
    } catch (\Throwable $t) { error_log('role_board config role: ' . $t->getMessage()); }
    return 0;
}

/**
 * محرّكُ اللوحةِ العامة — يُجهّز المكوّناتِ السبعةَ (UX-01 §5) لدورٍ مرةً واحدة،
 * فتقرأ منه الشاشتان اللتان تعرضانها: `main/role_board.php` و«الرئيسية»
 * `main/dashboard.php` حين تكون هي لوحةَ الدور (إدارة التشغيل — قرار المالك
 * 2026-08-21). مصدرٌ واحدٌ لا نسختان تتفرّقان بعد أولِ تعديلٍ على مؤشّر
 * (قانونُ «التوأمِ الراكد» — كان الحسابُ كلُّه في جسدِ الشاشةِ لا في دالّة).
 *
 * @param int $roleId دورُ الإعداد (يُحَلُّ سلفًا عبر roleBoardConfigRole)
 * @return array|null بنيةُ العرضِ كاملةً، أو null لدورٍ بلا إعدادٍ عام
 */
function roleBoardBuild($conn, $gate, $roleId, $userId)
{
    $rid = intval($roleId);
    $cfg = roleBoardGenericConfig($rid);
    if ($cfg === null) { return null; }

    /**
     * تنفيذُ عنصرِ إعدادٍ عدًّا معزولًا.
     * العنصرُ: [label, icon, scope, sql, href, tone?, extras?]
     *   • `sql` نصٌّ يُنفَّذ في نطاقِ `scope`، **أو دالّةٌ** `fn($gate): float`
     *     لمؤشرٍ يُركَّب من قراءتين معزولتين (البوابةُ تشترط WHERE عليا واحدة،
     *     فالنقدُ = افتتاحيُّ البنوك + صافي الكشوف قراءتان تُجمعان — كما في
     *     `Finance/cfo_daily_board_fin.php` حرفًا).
     *   • `extras` مصفوفةٌ اختيارية: unit · period · scope · decimals.
     */
    $exec = function (array $def, array $params = array()) use ($gate) {
        if (is_callable($def[3])) {
            try { return (float) call_user_func($def[3], $gate); }
            catch (\Throwable $t) { error_log('role_board calc: ' . $t->getMessage()); return 0.0; }
        }
        $scopeArr = array('scope' => array($def[2]['a'] => $def[2]['t']));
        if (isset($def[2]['enrich'])) { $scopeArr['enrich'] = $def[2]['enrich']; }
        return roleBoardScalar($gate, $scopeArr, $def[3], $params);
    };

    $today = date('Y-m-d');

    // ① مؤشرات اليوم — البطاقةُ تخرج جاهزةً للعرضِ بعقدِها السباعيّ، فلا يخترع
    //    القالبُ وحدةً ولا فترة (كان يفرض «سجل · لحظي» على كلِّ رقم — وهو صدقٌ
    //    لعدّاداتِ COUNT وكذبٌ على مبلغٍ أو كميةٍ أو نافذةِ سبعةِ أيام).
    $cards = array();
    foreach ($cfg['cards'] as $def) {
        $n  = $exec($def);
        $ex = isset($def[6]) && is_array($def[6]) ? $def[6] : array();
        $tone = isset($def[5]) ? $def[5] : 'or';
        if ($tone === 'err' && $n <= 0) { $tone = 'ok'; }   // الحرجُ الصفري يهدأ لونًا لا يختفي
        $dec = array_key_exists('decimals', $ex) ? $ex['decimals'] : null;
        $cards[] = array(
            'icon'    => $def[1],
            'value'   => $n,
            'display' => ($dec === null) ? (string) (int) $n : number_format((float) $n, (int) $dec),
            'label'   => $def[0],
            'tone'    => $tone,
            'href'    => $def[4],
            'unit'    => isset($ex['unit'])   ? $ex['unit']   : 'سجل',
            'period'  => isset($ex['period']) ? $ex['period'] : 'لحظي (' . $today . ')',
            'scope'   => isset($ex['scope'])  ? $ex['scope']  : '',
        );
    }

    // ② مهامي — من الإعداد، ثم ما تكتبه roleBoardTasks لأدوارٍ منطقُ مهامِّها
    //    مكتوبٌ لا تصريحيّ (17·13·16·23). والصفريُّ يختفي في الحالتين.
    $tasks = array();
    foreach ($cfg['tasks'] as $def) {
        $n = (int) $exec($def);
        if ($n > 0) { $tasks[] = array('label' => $def[0], 'icon' => $def[1], 'count' => $n, 'href' => $def[4]); }
    }
    foreach (roleBoardTasks($conn, $gate, $rid) as $wt) {
        if ((int) $wt['count'] > 0) { $tasks[] = $wt; }
    }

    // ⑥ نبض الأداء من إعداده (سلسلةٌ ثانيةٌ اختيارية)
    list($pulseTitle, $pulseSeries, $psA, $sqlA, $psB, $sqlB) = $cfg['pulse'];
    $pulse = array('labels' => array(), 'in' => array(), 'out' => array());
    for ($d = 6; $d >= 0; $d--) {
        $day = date('Y-m-d', strtotime("-{$d} days"));
        $pulse['labels'][] = date('m/d', strtotime($day));
        $pulse['in'][]  = $sqlA !== null ? roleBoardScalar($gate, array('scope' => array($psA['a'] => $psA['t'])), $sqlA, array($day)) : 0;
        $pulse['out'][] = $sqlB !== null ? roleBoardScalar($gate, array('scope' => array($psB['a'] => $psB['t'])), $sqlB, array($day)) : 0;
    }

    // ③④⑤⑦ عبر المحرك الموحّد — وشاراتُ السايدبار مصدرُ عدّادِ الموافقات نفسُه
    if (!function_exists('ems_finreq_nav_badges')) {
        require_once __DIR__ . '/finreq_badges.php';
    }
    /* ◆ **اللوحةُ كانت تستدعي المُحلِّلَ الأفقرَ**: `ems_finreq_nav_badges`
         لا يُصدر مفتاحَ `Approvals/hours_approval.php` أبدًا، فبقي صفُّ
         «اعتماد الوحدات التشغيلية» في ③ **غيرَ قابلٍ للوصولِ لكلِّ الأدوار**
         وتُصيَّر حالةُ الفراغِ دائمًا. والسايدبارُ يستدعي `ems_nav_all_badges`
         (`insidebar.php:296`) — فيُوحَّد المصدر. */
    $badges = function_exists('ems_nav_all_badges')
        ? ems_nav_all_badges($conn, $rid)
        : (function_exists('ems_finreq_nav_badges') ? ems_finreq_nav_badges($conn) : array());

    return array(
        'role_id'      => $rid,
        'title'        => $cfg['title'],
        'icon'         => $cfg['icon'],
        // الشاشةُ التي يحكم `can_view` عليها رؤيةَ هذه اللوحة: اللوحةُ العامة
        // افتراضًا، ولوحةٌ مخصصةٌ لمن نُقلت لوحتُه (المالية). الحارسُ في
        // `main/dashboard.php` — فالتضمينُ لا يُسقط قفلًا كانت الشاشةُ تحمله.
        'perm'         => isset($cfg['perm']) ? $cfg['perm'] : 'main/role_board.php',
        // جزءُ عرضٍ خاصٌّ باللوحة (اختياري) — لما لا يسعه نحوُ المكوّناتِ السبعة
        // ولا يجوز فقدُه عند نقلِ لوحةٍ مخصصةٍ إلى «الرئيسية».
        'view'         => isset($cfg['view']) ? $cfg['view'] : '',
        'cards'        => $cards,
        'tasks'        => $tasks,
        'approvals'    => roleBoardApprovals($conn, $gate, $rid, $badges),
        'alerts'       => roleBoardAlerts($conn, $gate, $rid),
        'quick'        => roleBoardQuickActions($conn, $rid, intval($userId)),
        'recent'       => roleBoardRecent($conn, intval($userId)),
        'pulse'        => $pulse,
        'pulse_title'  => $pulseTitle,
        'pulse_series' => $pulseSeries,
    );
}

/**
 * ② مهامي — ما يجب فعلُه الآن: صناديقُ العمل المفتوحة للدور بأزرار قفز.
 * تُبنى من المؤشرات ذات الطابع الإجرائي (لا من قائمةٍ ثابتة).
 */
function roleBoardTasks($conn, $gate, $roleId)
{
    $rid = intval($roleId);
    $out = array();
    if ($rid === 17) {
        // «جاهزة للتنفيذ» = approved (لا قيمةَ ready في ENUM الجدول)
        $n = (int) roleBoardScalar($gate, array('scope' => array('p' => 'fin_payments')),
            "SELECT COUNT(*) FROM fin_payments p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
             AND p.direction='disbursement' AND p.state='approved'");
        if ($n > 0) { $out[] = array('label' => 'مدفوعات جاهزة للتنفيذ', 'count' => $n, 'href' => '../Finance/payments_fin.php', 'icon' => 'fa fa-money-bill-transfer'); }

        $n = (int) roleBoardScalar($gate, array('scope' => array('e' => 'fin_financial_events')),
            "SELECT COUNT(*) FROM fin_financial_events e WHERE {TENANT_SCOPE} AND COALESCE(e.is_deleted,0)=0 AND e.state='draft'");
        if ($n > 0) { $out[] = array('label' => 'معاملات واردة تنتظر المراجعة', 'count' => $n, 'href' => '../Finance/events_list_fin.php', 'icon' => 'fa fa-receipt'); }

        $n = (int) roleBoardScalar($gate, array('scope' => array('u' => 'fin_unit_records')),
            "SELECT COUNT(*) FROM fin_unit_records u WHERE {TENANT_SCOPE} AND COALESCE(u.is_deleted,0)=0 AND u.match_state='matched'");
        if ($n > 0) { $out[] = array('label' => 'وحدات تنتظر ختم المالية', 'count' => $n, 'href' => '../Finance/unit_records_fin.php', 'icon' => 'fa fa-scale-balanced'); }
    }

    if ($rid === 13) {
        // دورة الأمر بحالاتها العربية الخمس — كل حالةٍ مفتوحةٍ صندوقُ عملٍ بقفزة
        $map = array(
            array('بلاغ',  'بلاغات تنتظر بدء التنفيذ', 'fa fa-bell'),
            array('تنفيذ', 'أوامر قيد التنفيذ',          'fa fa-screwdriver-wrench'),
            array('فحص',   'أوامر تنتظر الفحص والإقفال', 'fa fa-clipboard-check'),
        );
        foreach ($map as $m) {
            $n = (int) roleBoardScalar($gate, array('scope' => array('o' => 'mnt_order')),
                "SELECT COUNT(*) FROM mnt_order o WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0 AND o.state=?", array($m[0]));
            if ($n > 0) { $out[] = array('label' => $m[1], 'count' => $n, 'href' => '../Maintenance/orders.php', 'icon' => $m[2]); }
        }
        // وقائيةُ الأسبوع المستحقة (UX-04 §6)
        $n = (int) roleBoardScalar($gate, array('scope' => array('p' => 'mnt_plan')),
            "SELECT COUNT(*) FROM mnt_plan p WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0
             AND p.next_due_date IS NOT NULL AND p.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        if ($n > 0) { $out[] = array('label' => 'وقائية الأسبوع المستحقة', 'count' => $n, 'href' => '../Maintenance/preventive_plans.php', 'icon' => 'fa fa-calendar-check'); }
    }

    if ($rid === 16) {
        // خيطُ القطعة بحالاته العربية الحية: وارد ← أمرٌ مؤكَّد ← عهدةٌ مفتوحة
        $map = array(
            array("r.state IN('مقدَّم','اعتماد المشتريات')", 'proc_request', 'r', 'طلبات شراء واردة تنتظر قرارك', '../Procurement/requests_proc.php', 'fa fa-file-lines'),
            array("o.state = 'مؤكَّد'",                      'proc_order',   'o', 'أوامر مؤكدة تنتظر الاستلام',   '../Procurement/orders_proc.php',   'fa fa-file-invoice-dollar'),
            array("rc.state <> 'مسلَّمة للوجهة'",            'proc_receipt_custody', 'rc', 'عهد استلام مفتوحة',   '../Procurement/receipt_custody_proc.php', 'fa fa-truck-ramp-box'),
        );
        foreach ($map as $m) {
            $n = (int) roleBoardScalar($gate, array('scope' => array($m[2] => $m[1])),
                "SELECT COUNT(*) FROM {$m[1]} {$m[2]} WHERE {TENANT_SCOPE} AND COALESCE({$m[2]}.is_deleted,0)=0 AND {$m[0]}");
            if ($n > 0) { $out[] = array('label' => $m[3], 'count' => $n, 'href' => $m[4], 'icon' => $m[5]); }
        }
    }

    if ($rid === 23) {
        // دورة الرحلة بمراحلها: طلبٌ وارد ← جاهزةٌ للانطلاق ← جاريةٌ ← وصلت تنتظر الإغلاق
        $map = array(
            array("r.state = 'submitted'", 'transfer_requests', 'r', 'طلبات ترحيل تنتظر اعتمادك', '../Transport/transfer_requests.php', 'fa fa-inbox'),
            array("o.stage IN('planned','ready')", 'transfer_orders', 'o', 'رحلات مخططة تنتظر الانطلاق', '../Transport/transfer_orders_list.php', 'fa fa-route'),
            array("o.stage = 'in_transit'", 'transfer_orders', 'o', 'رحلات جارية الآن', '../Transport/transfer_orders_list.php', 'fa fa-truck-fast'),
            array("o.stage = 'arrived'", 'transfer_orders', 'o', 'وصلت — تنتظر توثيق التسليم والإغلاق', '../Transport/transfer_orders_list.php', 'fa fa-flag-checkered'),
        );
        foreach ($map as $m) {
            $n = (int) roleBoardScalar($gate, array('scope' => array($m[2] => $m[1])),
                "SELECT COUNT(*) FROM {$m[1]} {$m[2]} WHERE {TENANT_SCOPE} AND {$m[0]}");
            if ($n > 0) { $out[] = array('label' => $m[3], 'count' => $n, 'href' => $m[4], 'icon' => $m[5]); }
        }
    }
    return $out;
}
