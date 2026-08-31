<?php
/**
 * tools/lib/repair01_w13_scan.php — مقاييسُ المرحلةِ الثالثةَ عشرة (RPR-W13)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **مكتبةُ قياسٍ لا مكتبةُ إعلان**: كلُّ دالّةٍ هنا **تعيد القياسَ من الحيِّ**
 *   ولا تقرأ ما خزّنَته بوّابةٌ سابقة. والمقامُ يُعاد بناؤه في كلِّ نداء.
 *
 * ◆ **ومحورا هذه المرحلةِ يُقاسان بنيويًّا لا بالنيّة**:
 *   ① **طرفٌ من الأربعةِ مدموج** — يُقاس على خمسِ جبهات: بلاغٌ في السجلِّ
 *     بأقلَّ من أربعةِ أطراف · فاعلٌ واحدٌ يشغل دورَين في بلاغٍ واحد ·
 *     مُبلِّغٌ نصٌّ بلا مفتاح · محلُّ بلاغٍ بلا نوعٍ من الكتالوج ·
 *     ومالكُ حلٍّ في إدارةِ البلاغاتِ نفسِها.
 *   ② **تنفيذُ حلٍّ مملوكٌ للبلاغات** — يُقاس على خمسٍ أيضًا: إجراءُ معالجةٍ
 *     منفِّذُه `DEP-10` · إجراءٌ بلا مرجعٍ في شاشةِ الإدارةِ المعالِجة ·
 *     توجيهٌ إلى `DEP-10` كمالكِ حلّ · إسنادٌ إلى `DEP-10` · وإغلاقٌ عالجه
 *     `DEP-10` أو تحقّقٌ من المنفِّذِ نفسِه.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — كلُّها في `repair01_w13_thresholds`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/* ══════════════════════════════════════════════════════════════════════════
   ① أدواتُ قياسٍ عامّة
   ══════════════════════════════════════════════════════════════════════════ */

function repair01_w13_one(mysqli $c, $sql)
{
    $r = @$c->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}

function repair01_w13_table_exists(mysqli $c, $t)
{
    $r = @$c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w13_col_exists(mysqli $c, $t, $col)
{
    if (!repair01_w13_table_exists($c, $t)) { return false; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function repair01_w13_check_exists(mysqli $c, $t, $name)
{
    $n = (int) repair01_w13_one($c, "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                                      WHERE CONSTRAINT_SCHEMA = DATABASE()
                                        AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                                        AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $n > 0;
}

/**
 * **العمودُ يحمل الكيانَ ولا يقبل العدم** — الحبّةُ لا تُثبَت بوجودِ العمودِ
 * وحدَه: عمودٌ يقبل `NULL` يسمح بصفٍّ بلا كيانٍ قانونيّ (‏درسُ W11).
 */
function repair01_w13_entity_scoped(mysqli $c, $t)
{
    return repair01_w13_scope_verdict($c, $t) !== 'UNSCOPED';
}

/**
 * **وحكمُ الحبّةِ ثلاثةٌ لا اثنان.** عمودٌ غيرُ قابلٍ للعدم · **أو ابنٌ يُعزَل
 * بأبيه** · أو غيرُ معزول.
 * ◆ **والابنُ بلا `company_id` ليس بلا كيان**: `rec_stage_log` مصنَّفٌ في سجلِّ
 *   البوّابةِ ابنًا لـ`rec_applications` بمفتاحِ `app_id` — فعزلُه بالأبِ
 *   المملوكِ لا بعمودٍ فيه. وعدُّه «بلا كيان» **رقمٌ يُقرأ صحيحًا وهو خطأ**:
 *   يطلب عمودًا تمنعه بنيةُ البوّابةِ نفسُها.
 */
function repair01_w13_scope_verdict(mysqli $c, $t)
{
    if (!repair01_w13_table_exists($c, $t)) { return 'UNSCOPED'; }
    $r = @$c->query("SHOW COLUMNS FROM `$t` LIKE 'company_id'");
    $x = $r ? $r->fetch_assoc() : null;
    if ($x && strtoupper((string) $x['Null']) === 'NO') { return 'COLUMN_NOT_NULL'; }
    if (!$x) {
        /* **والتصنيفُ يُقرأ من سجلِّ البوّابةِ لا يُخمَّن من اسمِ الجدول** */
        if (class_exists('\App\Core\TenantRegistry')) {
            $def = \App\Core\TenantRegistry::get($t);
            if (is_array($def) && isset($def['parent']) && $def['parent'] !== '') { return 'PARENT_SCOPED'; }
        }
    }
    return 'UNSCOPED';
}

/** العتباتُ المسجَّلة — تُقرأ ولا تُكتب */
function repair01_w13_thresholds(mysqli $c)
{
    $out = array();
    if (!repair01_w13_table_exists($c, 'repair01_w13_thresholds')) { return $out; }
    $r = $c->query("SELECT threshold_key, value_num, why, decision_ref FROM repair01_w13_thresholds");
    while ($r && $x = $r->fetch_assoc()) {
        $out[$x['threshold_key']] = array('value' => (float) $x['value_num'],
                                          'why' => (string) $x['why'], 'ref' => (string) $x['decision_ref']);
    }
    return $out;
}

/** حارسُ الشاشةِ كما يُقاس من ملفِّها — لا كما يُدَّعى في السجلّ */
function repair01_w13_guard_of($ROOT, $route)
{
    $path = $ROOT . '/' . $route;
    if (!is_file($path)) { return array('kind' => 'NONE', 'evidence' => 'لا ملف على القرص'); }
    $src = (string) file_get_contents($path);
    if (strpos($src, 'check_page_permissions') !== false
        || strpos($src, 'enforce_current_page_view_permission') !== false) {
        return array('kind' => 'SELF_EARLY', 'evidence' => 'حارس صلاحية في الملف نفسه');
    }
    if (strpos($src, 'permissions_helper.php') !== false || strpos($src, 'permit_gate.php') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'حارس القشرة عبر المساعد المركزي');
    }
    if (strpos($src, 'session_bootstrap.php') !== false) {
        return array('kind' => 'SHELL', 'evidence' => 'اقلاع الجلسة المركزي');
    }
    return array('kind' => 'NONE', 'evidence' => 'لا حارس مقيس في الملف');
}

/* ══════════════════════════════════════════════════════════════════════════
   ② مِرساةُ كلِّ متطلَّبٍ — الطريقُ والمِسبارُ وموضعُه من دورةِ العمل
   ══════════════════════════════════════════════════════════════════════════
   `step` **موضعُ السطحِ من دورةِ العمل** — لا الأبجديّةُ ولا تاريخُ الإنشاء.
   دورةُ الموظّف 1..24 · دورةُ البلاغ 30..40.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w13_anchors()
{
    return array(
        /* ── الموارد البشرية · اللوحة خارج الدورة ─────────────────────── */
        'HR-01' => array('route' => 'Employees/hr_board.php', 'probe' => 'employees',
                         'kind' => 'TABLE', 'step' => 0,
                         'why' => 'لوحة الموارد البشرية - مؤشر مشتق من السجل والعقود والحضور والمسيرات'),
        /* ── التوظيف والتعاقد ─────────────────────────────────────────── */
        'HR-02' => array('route' => 'Employees/job_titles.php', 'probe' => 'job_titles',
                         'kind' => 'TABLE', 'step' => 1,
                         'why' => 'المنصب يسبق الشاغر - الهيكل المعتمد يعرف المناصب'),
        'HR-03' => array('route' => 'Workforce/workforce_requirement.php', 'probe' => 'PlanningService',
                         'kind' => 'SERVICE', 'step' => 2,
                         'why' => 'خطة القوى العاملة تعتمد سنويا وتحكم فتح الشواغر'),
        'HR-04' => array('route' => 'Workforce/recruitment_pipeline.php', 'probe' => 'rec_vacancies',
                         'kind' => 'TABLE', 'step' => 3,
                         'why' => 'الموارد تملك مسار التوظيف بمراحله والشاغر يفتح بطلب ادارته'),
        'HR-05' => array('route' => 'Workforce/rec_applications.php', 'probe' => 'rec_applications',
                         'kind' => 'TABLE', 'step' => 4,
                         'why' => 'الشاغر الواحد له عشرات المرشحين - كل ترشح سطر بمصدره وحالته'),
        'HR-06' => array('route' => 'Workforce/rec_stages.php', 'probe' => 'rec_stage_log',
                         'kind' => 'TABLE', 'step' => 5,
                         'why' => 'كل مرحلة سطر بنتيجتها ومقيمها ولا قفز مرحلة'),
        'HR-07' => array('route' => 'Employees/employees.php', 'probe' => 'employees',
                         'kind' => 'TABLE', 'step' => 6,
                         'why' => 'سجل الموظفين الشاشة الام - الموارد تملك حقيقة التوظيف'),
        'HR-08' => array('route' => 'Employees/hr_employee_documents.php', 'probe' => 'hr_employee_document',
                         'kind' => 'TABLE', 'step' => 7,
                         'why' => 'كل مستند بصلاحيته وتنبيه انتهائه والالزامي المنتهي يعلم الملف'),
        'HR-09' => array('route' => 'Employees/hr_onboarding.php', 'probe' => 'hr_onboarding_item',
                         'kind' => 'TABLE', 'step' => 8,
                         'why' => 'لا مباشرة كاملة قبل اكتمال بنود التهيئة او توثيق استثنائها'),
        'HR-10' => array('route' => 'Employees/employee_contracts.php', 'probe' => 'employee_contracts',
                         'kind' => 'TABLE', 'step' => 9,
                         'why' => 'العقد المكتوب مصدر حقيقة العلاقة - لا اجر ولا خصم الا على عقد ساري'),
        'HR-11' => array('route' => 'Workforce/project_contracts.php', 'probe' => 'cmp03_local_store',
                         'kind' => 'SERVICE', 'step' => 10,
                         'why' => 'العقد المشروعي مربوط بمحفز انتهاء واقعي يفتح التصفية'),
        'HR-12' => array('route' => 'Employees/hr_job_movements.php', 'probe' => 'hr_job_movement',
                         'kind' => 'TABLE', 'step' => 11,
                         'why' => 'النقل والترقية والانتداب حركات موثقة بموجبها واعتمادها'),
        /* ── الدوام والإجازات ─────────────────────────────────────────── */
        'HR-13' => array('route' => 'Operations/attendance.php', 'probe' => 'cmp03_local_store',
                         'kind' => 'SERVICE', 'step' => 12,
                         'why' => 'الحضور النظامي للاجر عند الموارد بالرموز العشرة'),
        'HR-14' => array('route' => 'Workforce/worker_leave_absence.php', 'probe' => 'worker_leave_absence',
                         'kind' => 'TABLE', 'step' => 13,
                         'why' => 'الرصيد النظامي عند الموارد والاجازة التبادلية تجدول عند القوى'),
        'HR-15' => array('route' => 'Employees/hr_training.php', 'probe' => 'hr_training_record',
                         'kind' => 'TABLE', 'step' => 14,
                         'why' => 'التدريب الالزامي يتابع بانتهاء صلاحيته'),
        'HR-16' => array('route' => 'Employees/hr_performance.php', 'probe' => 'hr_performance_review',
                         'kind' => 'TABLE', 'step' => 15,
                         'why' => 'التقييم الوظيفي للاداريين دوري بمعاييره والتشغيلي مشتق عند القوى'),
        'HR-17' => array('route' => 'Employees/hr_disciplinary.php', 'probe' => 'hr_disciplinary_case',
                         'kind' => 'TABLE', 'step' => 16,
                         'why' => 'القضية عملية تاديبية بمراحلها واقعة ثم تحقيق ثم قرار'),
        'HR-18' => array('route' => 'Workforce/deductions.php', 'probe' => 'employees',
                         'kind' => 'TABLE', 'step' => 17,
                         'why' => 'الخصم يتفرع بمرجع قراره في القضية ولا يكتب في شاشة القضية'),
        /* ── الاستحقاق والصرف ─────────────────────────────────────────── */
        'HR-19' => array('route' => 'Employees/hr_benefits.php', 'probe' => 'hr_benefit_enrollment',
                         'kind' => 'TABLE', 'step' => 18,
                         'why' => 'الاشتراكات النظامية والتامين الطبي بحصتيها تصب في المسير بمرجعها'),
        'HR-20' => array('route' => 'Workforce/employee_advances.php', 'probe' => 'OffsetService',
                         'kind' => 'SERVICE', 'step' => 19,
                         'why' => 'السلفة بقاعدة اعتمادها وسقفها والصرف عند الخزينة'),
        'HR-21' => array('route' => 'Workforce/payroll_runs.php', 'probe' => 'PayrollRunService',
                         'kind' => 'SERVICE', 'step' => 20,
                         'why' => 'المسير يقرا الحضور والعقود والخصومات ويعتمد بمساره'),
        'HR-22' => array('route' => 'Workforce/payroll_lines.php', 'probe' => 'payroll_lines',
                         'kind' => 'TABLE', 'step' => 21,
                         'why' => 'اهم علاقة واحد الى متعدد - لكل موظف سطر داخل المسير بمكوناته'),
        'HR-23' => array('route' => 'Workforce/final_settlement.php', 'probe' => 'FinalSettlementService',
                         'kind' => 'SERVICE', 'step' => 22,
                         'why' => 'لا تصفية قبل ابراء العهد وتسوية السلف والصرف بعد الاعتماد'),
        /* ── التقارير ─────────────────────────────────────────────────── */
        'HR-24' => array('route' => 'Workforce/hr_workforce_report.php', 'probe' => 'employees',
                         'kind' => 'TABLE', 'step' => 23,
                         'why' => 'تقرير القوى العاملة مشتق من السجل والعقود والحضور ولا ادخال'),
        /* ── البلاغات · اللوحة خارج الدورة ────────────────────────────── */
        'TKT-01' => array('route' => 'Tickets/ticket_dashboard.php', 'probe' => 'tickets',
                          'kind' => 'TABLE', 'step' => 30,
                          'why' => 'لوحة مركز البلاغات - مؤشر مشتق من البلاغات ومهلها وتصعيداتها'),
        'TKT-02' => array('route' => 'Tickets/ticket_sla_config.php', 'probe' => 'tkt_helpers',
                          'kind' => 'SERVICE', 'step' => 31,
                          'why' => 'المهلة مصفوفة مركزية لا رقم مبعثر - استجابة ومعالجة وسلم تصعيد'),
        /* ── دورةُ البلاغِ بأطرافِها الأربعة ──────────────────────────── */
        'TKT-03' => array('route' => 'Tickets/tkt_subject_types.php', 'probe' => 'tkt_subject_type',
                          'kind' => 'TABLE', 'step' => 32,
                          'why' => 'كتالوج كيانات محل البلاغ - لا قائمة ثابتة قصيرة'),
        'TKT-04' => array('route' => 'Tickets/tkt_parties.php', 'probe' => 'tkt_party',
                          'kind' => 'TABLE', 'step' => 33,
                          'why' => 'الفصل الثلاثي الحاكم بطرف رابع - من بلغ وعم بلغ ومن يملك ومن يعالج'),
        'TKT-05' => array('route' => 'Tickets/tkt_routing.php', 'probe' => 'tkt_routing_history',
                          'kind' => 'TABLE', 'step' => 34,
                          'why' => 'كل توجيه سطر - الالي بقاعدته وتصحيح المركز بسببه'),
        /* FC: تصحيح ازاحة الخريطة الصلبة بموضعين (درس W08) — الدفتر الرسمي يحكم:
           TKT-06 تسجيل البلاغ وTKT-07 سجل التوجيه كانا خارج الخريطة */
        'TKT-06' => array('route' => 'Tickets/ticket_form.php', 'probe' => 'tickets',
                          'kind' => 'TABLE', 'step' => 33,
                          'why' => 'تسجيل البلاغ — راس الدورة بكيانها tickets'),
        'TKT-07' => array('route' => 'Tickets/tkt_routing.php', 'probe' => 'tkt_routing_history',
                          'kind' => 'TABLE', 'step' => 34,
                          'why' => 'سجل التوجيه — كل تحويل سطر بوقته وسببه'),
        'TKT-08' => array('route' => 'Tickets/tkt_assignment.php', 'probe' => 'tkt_assignment_history',
                          'kind' => 'TABLE', 'step' => 35,
                          'why' => 'كل تغيير مكلف سطر بسببه ولا مكلف بلا وقت استلام'),
        'TKT-09' => array('route' => 'Tickets/tkt_resolution_actions.php', 'probe' => 'tkt_resolution_action',
                          'kind' => 'TABLE', 'step' => 36,
                          'why' => 'كل اجراء سطر بمرجعه في شاشة الادارة المعالجة - المعالجة عند مالكها'),
        'TKT-10' => array('route' => 'Tickets/tkt_communications.php', 'probe' => 'ticket_communications',
                          'kind' => 'TABLE', 'step' => 37,
                          'why' => 'دور المركز تواصل موثق - كل تواصل سطر بقناته ووقته'),
        'TKT-11' => array('route' => 'Tickets/tkt_escalation.php', 'probe' => 'ticket_escalations',
                          'kind' => 'TABLE', 'step' => 38,
                          'why' => 'التصعيد الي بمستوياته عند تجاوز المهل ولا يسكت الا بمعالجة'),
        'TKT-12' => array('route' => 'Tickets/tkt_reopen.php', 'probe' => 'tkt_reopen',
                          'kind' => 'TABLE', 'step' => 39,
                          'why' => 'اعتراض المبلغ او تكرار المشكلة يعيد الفتح بسجل ويعود لمساره'),
        /* ⚠ **والمِرساةُ سطحٌ جديدٌ لا `ticket_close.php` القائم**: الإغلاقُ
           القائمُ زرٌّ يقلب حالةَ الرأس، ودورةُ التحقّقِ الثلاثيّةُ كيانٌ بدورتِه
           وعددِ أشواطِه — فحقنُها في سطحِ الإغلاقِ يجعل الدورةَ حقلًا في زرّ. */
        'TKT-13' => array('route' => 'Tickets/tkt_verification.php', 'probe' => 'tkt_verification',
                          'kind' => 'TABLE', 'step' => 40,
                          'why' => 'المسار الحاكم معالجة ثم تحقق ثم اغلاق - ولا اغلاق بلا تحقق'),
    );
}

/** إثباتُ المِرساةِ من القرصِ — لا من دعوى السجلّ */
function repair01_w13_prove_anchor(mysqli $c, $ROOT, array $a)
{
    if ($a['route'] === '') {
        return array('sid' => '', 'owner' => '', 'verdict' => 'NOT_BUILT', 'rule' => 'W13_TARGET_GAP');
    }
    $rt = $c->real_escape_string($a['route']);
    $row = $c->query("SELECT screen_id, owner_code, on_disk FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { return array('sid' => '', 'owner' => '', 'verdict' => 'ROUTE_NOT_IN_REGISTRY',
                              'rule' => 'W13_ANCHOR_UNPROVEN'); }
    if ((int) $row['on_disk'] !== 1) {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'ROUTE_NOT_ON_DISK', 'rule' => 'W13_ANCHOR_UNPROVEN');
    }
    $path = $ROOT . '/' . $a['route'];
    $src = is_file($path) ? (string) file_get_contents($path) : '';
    if ($src === '') {
        return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                     'verdict' => 'FILE_UNREADABLE', 'rule' => 'W13_ANCHOR_UNPROVEN');
    }
    /* ⚠ **الرسوُّ على الاسمِ مقتبَسًا لا على جزءٍ منه** (‏درسُ `W11-22`):
         `tkt_party_removed` يحوي `tkt_party` نصًّا، فبحثٌ بلا حدِّ كلمةٍ
         يُخضِرُّ الحاجبَ وقد نُزع المكشوف. */
    $p = preg_quote($a['probe'], '~'); $hit = false; $rule = '';
    if ($a['kind'] === 'TABLE') {
        $hit = (bool) (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $p . '`?(?![A-Za-z0-9_])~i', $src)
                    || preg_match('~[\'"]' . $p . '[\'"]\s*[,\)]~', $src));
        $rule = 'W13_ROUTE_TOUCHES_TABLE';
    } elseif ($a['kind'] === 'SERVICE') {
        $hit = strpos($src, $a['probe']) !== false;
        $rule = 'W13_ROUTE_REQUIRES_SERVICE';
    }
    return array('sid' => $row['screen_id'], 'owner' => (string) $row['owner_code'],
                 'verdict' => $hit ? 'ANCHORED' : 'ANCHOR_PROBE_MISSED',
                 'rule' => $hit ? $rule : 'W13_ANCHOR_UNPROVEN');
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — تُبنى في هذه الموجةِ وتُختَم بها (RPR-PATCH-02)
   ══════════════════════════════════════════════════════════════════════════
   `sort` هو **موضعُ السطحِ من دورةِ العمل** — لا الأبجديّةُ ولا الإنشاء.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w13_new_surfaces()
{
    return array(
        array('route' => 'Employees/hr_board.php', 'ar' => 'لوحة الموارد البشرية',
              'icon' => 'fa fa-users-gear', 'group' => 'اللوحة', 'sort' => 0, 'step' => 0,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-01', 'doc' => 'قراءة حية بلا مستند',
              'next' => 'فتح شاشة الدورة المعنية', 'cons' => 'الموارد البشرية والقيادة', 'fin' => 'لا'),
        array('route' => 'Workforce/rec_applications.php', 'ar' => 'طلبات الترشح',
              'icon' => 'fa fa-file-signature', 'group' => 'التوظيف والتعاقد', 'sort' => 4, 'step' => 4,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Workforce/recruitment_pipeline.php',
              'req' => 'HR-05', 'doc' => 'سطر ترشح بمصدره',
              'next' => 'تسجيل مرحلة تقييم للمرشح', 'cons' => 'الموارد البشرية', 'fin' => 'لا'),
        array('route' => 'Workforce/rec_stages.php', 'ar' => 'مراحل التوظيف',
              'icon' => 'fa fa-list-check', 'group' => 'التوظيف والتعاقد', 'sort' => 5, 'step' => 5,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Workforce/recruitment_pipeline.php',
              'req' => 'HR-06', 'doc' => 'سطر مرحلة بنتيجتها ومقيمها',
              'next' => 'اصدار عرض عمل او استبعاد معلل', 'cons' => 'الموارد البشرية', 'fin' => 'لا'),
        array('route' => 'Employees/hr_employee_documents.php', 'ar' => 'مستندات الموظف',
              'icon' => 'fa fa-folder-open', 'group' => 'التوظيف والتعاقد', 'sort' => 7, 'step' => 7,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-08', 'doc' => 'مستند بصلاحيته ومرجع ملفه',
              'next' => 'تنبيه انتهاء المستند الالزامي', 'cons' => 'الموارد البشرية والحوكمة', 'fin' => 'لا'),
        array('route' => 'Employees/hr_onboarding.php', 'ar' => 'التهيئة والمباشرة',
              'icon' => 'fa fa-clipboard-check', 'group' => 'التوظيف والتعاقد', 'sort' => 8, 'step' => 8,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-09', 'doc' => 'قائمة تهيئة مكتملة او استثناء موثق',
              'next' => 'اعتماد المباشرة الكاملة', 'cons' => 'الموارد البشرية والتشغيل', 'fin' => 'لا'),
        array('route' => 'Employees/hr_job_movements.php', 'ar' => 'الحركات الوظيفية',
              'icon' => 'fa fa-arrows-turn-right', 'group' => 'التوظيف والتعاقد', 'sort' => 11, 'step' => 11,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-12', 'doc' => 'قرار حركة وظيفية بموجبه',
              'next' => 'تطبيق الحركة على المنصب والاجر', 'cons' => 'الموارد البشرية والمالية', 'fin' => 'نعم'),
        array('route' => 'Employees/hr_training.php', 'ar' => 'التدريب والكفاءة',
              'icon' => 'fa fa-graduation-cap', 'group' => 'الدوام والإجازات', 'sort' => 14, 'step' => 14,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-15', 'doc' => 'شهادة تدريب بتاريخ انتهائها',
              'next' => 'تنبيه انتهاء صلاحية التدريب الالزامي', 'cons' => 'الموارد البشرية والسلامة', 'fin' => 'لا'),
        array('route' => 'Employees/hr_performance.php', 'ar' => 'تقييم الأداء الوظيفي',
              'icon' => 'fa fa-chart-line', 'group' => 'الدوام والإجازات', 'sort' => 15, 'step' => 15,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-16', 'doc' => 'تقييم دوري نهائي بمعاييره',
              'next' => 'اثر التقييم على الترقية او الحوافز', 'cons' => 'الموارد البشرية والقيادة', 'fin' => 'لا'),
        array('route' => 'Employees/hr_disciplinary.php', 'ar' => 'القضايا التأديبية والتحقيق',
              'icon' => 'fa fa-gavel', 'group' => 'الدوام والإجازات', 'sort' => 16, 'step' => 16,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-17', 'doc' => 'قرار تاديبي بمرجعه',
              'next' => 'انشاء خصم بمرجع قرار القضية', 'cons' => 'الموارد البشرية والحوكمة', 'fin' => 'نعم'),
        array('route' => 'Employees/hr_benefits.php', 'ar' => 'المزايا والتأمينات',
              'icon' => 'fa fa-shield-heart', 'group' => 'الاستحقاق والصرف', 'sort' => 18, 'step' => 18,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Workforce/payroll_runs.php',
              'req' => 'HR-19', 'doc' => 'اشتراك بحصتيه ومرجعه في المسير',
              'next' => 'ادراج مكون المزايا في اسطر المسير', 'cons' => 'الموارد البشرية والمالية', 'fin' => 'نعم'),
        array('route' => 'Workforce/payroll_lines.php', 'ar' => 'أسطر مسير الرواتب',
              'icon' => 'fa fa-money-check-dollar', 'group' => 'الاستحقاق والصرف', 'sort' => 21, 'step' => 21,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Workforce/payroll_runs.php',
              'req' => 'HR-22', 'doc' => 'سطر مسير لموظف بمكوناته',
              'next' => 'اعتماد المسير وطلب الصرف', 'cons' => 'الموارد البشرية والمالية والخزينة', 'fin' => 'نعم'),
        array('route' => 'Workforce/hr_workforce_report.php', 'ar' => 'تقرير القوى العاملة',
              'icon' => 'fa fa-chart-pie', 'group' => 'التقارير', 'sort' => 23, 'step' => 23,
              'owner' => 'DEP-07', 'role' => 'إدارة الموارد البشرية', 'sibling' => 'Employees/employees.php',
              'req' => 'HR-24', 'doc' => 'تقرير قوى عاملة لفترة وادارة',
              'next' => 'قراءة القيادة للفجوة والفائض', 'cons' => 'الموارد البشرية والقيادة', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_subject_types.php', 'ar' => 'كتالوج أنواع محل البلاغ',
              'icon' => 'fa fa-diagram-project', 'group' => 'دورة البلاغ', 'sort' => 32, 'step' => 32,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-03', 'doc' => 'نوع محل بلاغ بسجله المرجعي',
              'next' => 'تحديد محل البلاغ عند التسجيل', 'cons' => 'البلاغات وكل الادارات', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_parties.php', 'ar' => 'أطراف البلاغ',
              'icon' => 'fa fa-user-group', 'group' => 'دورة البلاغ', 'sort' => 33, 'step' => 33,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-04', 'doc' => 'سجل الاطراف الاربعة للبلاغ',
              'next' => 'توجيه البلاغ لادارة مالك الحل', 'cons' => 'البلاغات وادارة مالك الحل', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_routing.php', 'ar' => 'سجل التوجيه',
              'icon' => 'fa fa-route', 'group' => 'السجلات التابعة', 'sort' => 34, 'step' => 34,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-05', 'doc' => 'واقعة توجيه بقاعدتها او سببها',
              'next' => 'اسناد مكلف في الادارة الموجه اليها', 'cons' => 'البلاغات والادارة المستقبلة', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_assignment.php', 'ar' => 'سجل الإسناد',
              'icon' => 'fa fa-user-check', 'group' => 'السجلات التابعة', 'sort' => 35, 'step' => 35,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-06', 'doc' => 'واقعة اسناد بسببها ووقت استلامها',
              'next' => 'تسجيل اجراء معالجة من المكلف', 'cons' => 'البلاغات ومالك الحل', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_resolution_actions.php', 'ar' => 'إجراءات المعالجة',
              'icon' => 'fa fa-screwdriver-wrench', 'group' => 'السجلات التابعة', 'sort' => 36, 'step' => 36,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-07', 'doc' => 'اجراء معالجة بمرجعه في شاشة ادارته',
              'next' => 'اعلان المعالجة وفتح نافذة التحقق', 'cons' => 'مالك الحل والبلاغات', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_communications.php', 'ar' => 'سجل التواصل',
              'icon' => 'fa fa-comments', 'group' => 'السجلات التابعة', 'sort' => 37, 'step' => 37,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-08', 'doc' => 'سطر تواصل بقناته ووقته',
              'next' => 'رد المبلغ او استيفاء ناقص', 'cons' => 'البلاغات والمبلغ', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_escalation.php', 'ar' => 'التصعيد',
              'icon' => 'fa fa-arrow-up-right-dots', 'group' => 'السجلات التابعة', 'sort' => 38, 'step' => 38,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-09', 'doc' => 'واقعة تصعيد بمستواها',
              'next' => 'معالجة او تعليق مبرر', 'cons' => 'البلاغات وقيادة ادارة مالك الحل', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_reopen.php', 'ar' => 'إعادة الفتح',
              'icon' => 'fa fa-rotate-left', 'group' => 'السجلات التابعة', 'sort' => 39, 'step' => 39,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-10', 'doc' => 'واقعة اعادة فتح بسببها',
              'next' => 'عودة البلاغ لمسار معالجته', 'cons' => 'البلاغات ومالك الحل', 'fin' => 'لا'),
        array('route' => 'Tickets/tkt_verification.php', 'ar' => 'التحقق والإغلاق',
              'icon' => 'fa fa-circle-check', 'group' => 'دورة البلاغ', 'sort' => 40, 'step' => 40,
              'owner' => 'DEP-10', 'role' => 'إدارة البلاغات', 'sibling' => 'Tickets/ticket_dashboard.php',
              'req' => 'TKT-11', 'doc' => 'دورة تحقق باقفالها',
              'next' => 'اغلاق البلاغ او اعادة فتحه', 'cons' => 'البلاغات والمبلغ ومالك الحل', 'fin' => 'لا'),
    );
}

/** مجموعاتُ دورةِ العملِ كما تُعرَض — بلا تشكيلٍ ولا زخرفة */
function repair01_w13_group_rewrites()
{
    return array(
        'اللوحة — خارج الدورة (Overview)' => 'اللوحة',
        'اللوحة' => 'اللوحة',
        'التوظيف والتعاقد' => 'التوظيف والتعاقد',
        'الدوام والإجازات' => 'الدوام والإجازات',
        'الاستحقاق والصرف' => 'الاستحقاق والصرف',
        'التقارير' => 'التقارير',
        'دورة البلاغ' => 'دورة البلاغ',
        'السجلات التابعة' => 'السجلات التابعة',
    );
}

function repair01_w13_group_ar($raw)
{
    $map = repair01_w13_group_rewrites();
    $raw = (string) $raw;
    if (isset($map[$raw])) { return $map[$raw]; }
    /* والاسمُ المركَّبُ يُقصُّ عند الشرطةِ لا يُترك تقنيًّا في الواجهة */
    $cut = preg_split('~\s+—\s+~u', $raw);
    $head = trim((string) $cut[0]);
    return isset($map[$head]) ? $map[$head] : $head;
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ الأطرافُ الأربعةُ — دفترُ الإعلانِ ومقياسُ الدمج
   ══════════════════════════════════════════════════════════════════════════ */

/** إعلانُ الأطرافِ الأربعةِ — مقامٌ ثابتٌ لا يخلو */
function repair01_w13_party_roles()
{
    return array(
        array('REPORTER', 'المبلغ',
              'رواية الواقعة ومتابعة اثرها والاعتراض على الحل',
              'لا يملك المعالجة ولا الاغلاق ولا التوجيه',
              'tkt_party.actor_id', 'tickets.reporting_person',
              'W13_MERGE_ACTOR_TWICE', 'uq_tkp_actor',
              'المبلغ كان اسما نصيا في راس البلاغ - والاسم بلا مفتاح لا يربط بشخص ولا يقاس تمايزه'),
        array('SUBJECT', 'محل البلاغ',
              'الكيان الذي وقع عليه او فيه موضوع البلاغ',
              'لا يملك شيئا من الدورة - هو موضوعها لا فاعلها',
              'tkt_party.actor_id', 'tickets.source_entity_id',
              'W13_MERGE_ACTOR_TWICE', 'chk_tkp_subject_typed',
              'محل البلاغ لم يكن طرفا معلنا بل خلط بشاشة المصدر - فتعذر قراءة عم بلغ منفصلة عن من بلغ'),
        array('TICKET_OWNER', 'مالك دورة التذكرة',
              'التسجيل والتصنيف والتوجيه والاسناد والتواصل والتصعيد والاغلاق الاداري',
              'لا يملك تنفيذ الحل الفني الخاص بالادارات المختصة',
              'tkt_party.actor_id', 'tickets.owner_role_id',
              'W13_MERGE_ACTOR_TWICE', 'chk_tkp_own_crp',
              'مالك التذكرة كان دورا لا شخصا - والدور لا يقاس تمايزه عن شخص المكلف'),
        array('RESOLUTION_OWNER', 'مالك الحل',
              'تنفيذ المعالجة الفنية في ادارته وتوثيق اجرائها بمرجعه',
              'لا يملك دورة التذكرة ولا يغلقها ولا يتحقق من نفسه',
              'tkt_party.actor_id', 'tickets.assigned_user_id',
              'W13_MERGE_ACTOR_TWICE', 'chk_tkp_res_not_crp',
              'مالك الحل كان مكلفا مدموجا بمالك التذكرة - فصار المركز يعالج ويغلق ويتحقق من عمله'),
    );
}

/** أدوارُ الأطرافِ الأربعةِ رموزًا */
function repair01_w13_party_codes()
{
    $out = array();
    foreach (repair01_w13_party_roles() as $p) { $out[] = $p[0]; }
    return $out;
}

/* ── المحورُ ① · «طرفٌ من الأربعةِ مدموج» — خمسُ جبهات ─────────────────── */

/** ① بلاغٌ في سجلِّ الأطرافِ بأقلَّ من أربعةِ أطراف */
function repair01_w13_party_incomplete(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_party')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM (
              SELECT ticket_id FROM tkt_party GROUP BY company_id, ticket_id
               HAVING COUNT(DISTINCT party_role) < 4) x");
}

/** ② فاعلٌ واحدٌ يشغل دورَين في بلاغٍ واحد */
function repair01_w13_party_actor_twice(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_party')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM (
              SELECT company_id, ticket_id, actor_kind, actor_id FROM tkt_party
               GROUP BY company_id, ticket_id, actor_kind, actor_id
               HAVING COUNT(DISTINCT party_role) > 1) x");
}

/** ③ مُبلِّغٌ في سجلِّ الأطرافِ بلا مفتاحِ إنسان */
function repair01_w13_reporter_without_key(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_party')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_party
              WHERE party_role = 'REPORTER' AND (actor_kind <> 'PERSON' OR actor_id = 0)");
}

/** ④ محلُّ بلاغٍ بلا نوعٍ قائمٍ في الكتالوج */
function repair01_w13_subject_without_catalog(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_party')
        || !repair01_w13_table_exists($c, 'tkt_subject_type')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_party p
              LEFT JOIN tkt_subject_type t ON t.company_id = p.company_id
                                          AND t.type_code = p.subject_type_code
                                          AND t.active = 1
             WHERE p.party_role = 'SUBJECT' AND t.id IS NULL");
}

/** ⑤ مالكُ حلٍّ في إدارةِ البلاغاتِ نفسِها أو بلا إدارة */
function repair01_w13_resolution_owner_in_crp(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_party')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_party
              WHERE party_role = 'RESOLUTION_OWNER' AND (actor_dept = 'DEP-10' OR actor_dept = '')");
}

/** المحورُ ① مجموعًا على جبهاتِه الخمس */
function repair01_w13_party_merged(mysqli $c)
{
    $f = array(
        'PARTY_INCOMPLETE'       => repair01_w13_party_incomplete($c),
        'ACTOR_IN_TWO_ROLES'     => repair01_w13_party_actor_twice($c),
        'REPORTER_WITHOUT_KEY'   => repair01_w13_reporter_without_key($c),
        'SUBJECT_WITHOUT_TYPE'   => repair01_w13_subject_without_catalog($c),
        'RESOLUTION_OWNER_IN_CRP' => repair01_w13_resolution_owner_in_crp($c),
    );
    $n = 0; $miss = array();
    foreach ($f as $k => $v) {
        if ($v < 0) { $miss[] = $k; continue; }
        $n += $v;
    }
    return array('total' => $n, 'fronts' => $f, 'unmeasured' => $miss);
}

/* ── المحورُ ② · «تنفيذُ حلٍّ مملوكٌ للبلاغات» — خمسُ جبهات ────────────── */

/** ① إجراءُ معالجةٍ منفِّذُه إدارةُ البلاغات */
function repair01_w13_action_by_crp(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_resolution_action')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_resolution_action
              WHERE executor_dept = 'DEP-10' OR executor_dept = ''");
}

/** ② إجراءٌ بلا مرجعٍ في شاشةِ الإدارةِ المعالِجة */
function repair01_w13_action_without_ref(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_resolution_action')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_resolution_action
              WHERE dept_screen_ref = '' OR action_ar = ''");
}

/** ③ توجيهٌ إلى إدارةِ البلاغاتِ كمالكِ حلّ */
function repair01_w13_routed_to_crp(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_routing_history')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_routing_history WHERE to_dept = 'DEP-10'");
}

/** ④ إسنادٌ إلى إدارةِ البلاغاتِ للمعالجة */
function repair01_w13_assigned_to_crp(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_assignment_history')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_assignment_history WHERE to_dept = 'DEP-10'");
}

/** ⑤ إغلاقٌ عالجَه المركزُ أو تحقّقٌ من المنفِّذِ نفسِه */
function repair01_w13_close_by_executor(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'tkt_verification')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM tkt_verification
              WHERE resolved_dept = 'DEP-10'
                 OR (verified_by IS NOT NULL AND verified_by = resolved_by)
                 OR (closed_at IS NOT NULL AND verified_at IS NULL)");
}

/** المحورُ ② مجموعًا على جبهاتِه الخمس */
function repair01_w13_resolution_owned_by_crp(mysqli $c)
{
    $f = array(
        'ACTION_BY_CRP'      => repair01_w13_action_by_crp($c),
        'ACTION_WITHOUT_REF' => repair01_w13_action_without_ref($c),
        'ROUTED_TO_CRP'      => repair01_w13_routed_to_crp($c),
        'ASSIGNED_TO_CRP'    => repair01_w13_assigned_to_crp($c),
        'CLOSE_BY_EXECUTOR'  => repair01_w13_close_by_executor($c),
    );
    $n = 0; $miss = array();
    foreach ($f as $k => $v) {
        if ($v < 0) { $miss[] = $k; continue; }
        $n += $v;
    }
    return array('total' => $n, 'fronts' => $f, 'unmeasured' => $miss);
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ قياساتُ دورةِ الموظّف
   ══════════════════════════════════════════════════════════════════════════ */

/** خصمٌ تأديبيٌّ بلا قرارِ قضيّةٍ يسنده (‏`HR-18` مقابل `HR-17`) */
function repair01_w13_deduction_without_case(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'payroll_deductions')
        || !repair01_w13_table_exists($c, 'hr_disciplinary_case')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM payroll_deductions d
              LEFT JOIN hr_disciplinary_case k ON k.id = d.source_id
                                              AND k.state IN ('decided','closed')
                                              AND k.decision_kind = 'deduction'
             WHERE d.source_type = 'penalty' AND k.id IS NULL");
}

/** قضيّةٌ تأديبيّةٌ قفزت مرحلةً — قرارٌ بلا تحقيقٍ مسجَّل */
function repair01_w13_case_stage_skipped(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'hr_disciplinary_case')
        || !repair01_w13_table_exists($c, 'hr_disciplinary_stage')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM hr_disciplinary_case k
             WHERE k.state IN ('decided','closed')
               AND (SELECT COUNT(DISTINCT s.stage) FROM hr_disciplinary_stage s
                     WHERE s.case_id = k.id AND s.stage IN ('incident','investigation','decision')) < 3");
}

/** مباشرةٌ كاملةٌ ببندِ تهيئةٍ إلزاميٍّ معلَّقٍ بلا استثناءٍ موثَّق */
function repair01_w13_onboarding_incomplete(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'hr_onboarding_item')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM hr_onboarding_item
             WHERE mandatory = 1 AND state = 'pending'");
}

/** مستندٌ إلزاميٌّ منتهٍ يعلّم الملفَّ — العددُ يُقرأ ولا يُدَّعى صفرًا */
function repair01_w13_expired_mandatory_docs(mysqli $c)
{
    if (!repair01_w13_table_exists($c, 'hr_employee_document')) { return -1; }
    return (int) repair01_w13_one($c, "SELECT COUNT(*) FROM hr_employee_document
             WHERE is_mandatory = 1 AND expires_at IS NOT NULL AND expires_at < CURDATE()
               AND state <> 'replaced'");
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑥ فصلُ الواجباتِ وعقودُ الأثر
   ══════════════════════════════════════════════════════════════════════════ */

/** رموزُ ردِّ فصلِ الواجباتِ — كلٌّ منها **مُنفَّذٌ في الخدمةِ** لا مُعلَنٌ فقط */
function repair01_w13_sod_codes()
{
    return array(
        'hr.movement.approve'   => 'SAME_ACTOR_REQUEST_AND_APPROVE_MOVEMENT',
        'hr.discipline.investigate' => 'SAME_ACTOR_REPORT_AND_INVESTIGATE',
        'hr.discipline.decide'  => 'SAME_ACTOR_INVESTIGATE_AND_DECIDE',
        'hr.deduction.raise'    => 'DEDUCTION_WITHOUT_DECIDED_CASE',
        'hr.review.final'       => 'SAME_ACTOR_REVIEW_SELF',
        'hr.settlement.approve' => 'SAME_ACTOR_PREPARE_AND_APPROVE_SETTLEMENT',
        'tkt.route.correct'     => 'ROUTE_CORRECTION_WITHOUT_REASON',
        'tkt.assign'            => 'ASSIGN_TO_TICKET_CENTER',
        'tkt.resolve'           => 'RESOLUTION_BY_TICKET_CENTER',
        'tkt.verify'            => 'SAME_ACTOR_RESOLVE_AND_VERIFY',
        'tkt.close'             => 'CLOSE_WITHOUT_VERIFICATION',
        'tkt.reopen'            => 'REOPEN_WITHOUT_REASON',
    );
}

/**
 * **الكياناتُ الرئيسيّةُ التي يلزمها آلةُ حالة** — مقامٌ مُعلَنٌ لا رقمٌ في حاجب.
 * ◆ الكيانُ بلا آلةِ حالةٍ لا يُغلَق (§٧)، فالقائمةُ تُعلَن هنا ويقارنها الحاجبُ
 *   بالمسجَّل — فإضافةُ كيانٍ بلا آلتِه تُسقط، وحذفُ آلةٍ كذلك.
 */
function repair01_w13_state_entities()
{
    return array(
        'tkt_verification', 'tkt_assignment', 'tkt_routing', 'tkt_reopen', 'tkt_subject_type',
        'hr_disciplinary_case', 'hr_job_movement', 'hr_onboarding_item',
        'hr_employee_document', 'hr_training_record', 'hr_performance_review',
        'hr_benefit_enrollment',
    );
}

/** أحداثُ المرحلةِ ورمزُ منعِ التكرارِ لكلٍّ منها */
function repair01_w13_stage_events()
{
    return array(
        'hr.employee.onboarded', 'hr.movement.approved', 'hr.discipline.decided',
        'hr.deduction.raised', 'hr.payroll.approved', 'hr.settlement.approved',
        'tkt.reported', 'tkt.routed', 'tkt.assigned', 'tkt.action.recorded',
        'tkt.escalated', 'tkt.resolved', 'tkt.verified', 'tkt.closed', 'tkt.reopened',
    );
}

/** ناشرُ الحدثِ — الدالّةُ التي تصدره في الخدمة */
function repair01_w13_event_publisher($code)
{
    $map = array();
    foreach (repair01_w13_stage_events() as $e) {
        $parts = explode('.', $e);
        $tail = array_pop($parts);
        $map[$e] = 'PeopleCycleService::on' . ucfirst(str_replace('_', '', $tail));
    }
    return isset($map[$code]) ? $map[$code] : '';
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑦ حراسةُ العتبةِ الصلبةِ في شيفرةِ النطاق
   ══════════════════════════════════════════════════════════════════════════
   ⚠ **والماسحُ يمسح أدواتِ النطاقِ كلَّها ومنها ملفُّ الفحصِ السلبيِّ نفسُه**
     (‏عطبُ W12 الرابع). فحمولةُ الكسرِ هناك **تُركَّب رقمًا** ولا يُستثنى ملفٌّ
     من المقام — فالمقامُ يبقى كاملًا.
   ══════════════════════════════════════════════════════════════════════════ */
function repair01_w13_hardcoded_thresholds($ROOT, mysqli $c = null)
{
    /* **والمقياسُ قيمةُ العتبةِ نفسِها لا حجمُ الرقم** (‏تشديدٌ على W12): كاشفُ
       W12 كان يرسو على «ثلاثةِ أرقامٍ فأكثر»، فعتبةٌ من رقمَين — وهي أغلبُ
       عتباتِ هذه المرحلة: ٥ و٧ و١٠ و١٢ و١٤ و٣٠ و٤٥ و٤٨ و٧٢ — تمرُّ مكتوبةً
       في الشيفرةِ والحاجبُ أخضر. فالمقامُ هنا **قيمُ السجلِّ نفسُها** مضافًا
       إليها ما فوقَ الأربعِ والعشرين، وما دونها عدّادُ حلقةٍ أو حدُّ وجودٍ لا
       عتبةُ أعمال. */
    $vals = array();
    if ($c instanceof mysqli && repair01_w13_table_exists($c, 'repair01_w13_thresholds')) {
        $r = @$c->query("SELECT value_num FROM repair01_w13_thresholds");
        while ($r && $x = $r->fetch_row()) { $vals[(string) (float) $x[0]] = true; }
    }
    $files = array(
        $ROOT . '/app/Services/People/PeopleCycleService.php',
        $ROOT . '/tools/repair01_w13_apply.php',
        $ROOT . '/tools/repair01_w13_gate.php',
        $ROOT . '/tools/repair01_w13_journey.php',
        $ROOT . '/tools/repair01_w13_negative.php',
        $ROOT . '/tools/lib/repair01_w13_scan.php',
    );
    /* **والمقامُ أسطحُ هذه الموجةِ بأسمائها من السجلّ** — لا كلُّ ما يطابق نمطَ
       اسمٍ: `Tickets/tkt_helpers.php` ملفٌّ موروثٌ سابقٌ للموجة، وعدُّ عتباتِه
       عليها يخلط دَينًا قائمًا بدَينٍ جديدٍ ويمنع قراءةَ أثرِ البناء. */
    foreach (repair01_w13_new_surfaces() as $s) { $files[] = $ROOT . '/' . $s['route']; }
    $files[] = $ROOT . '/includes/w13_view.php';
    $hits = array();
    foreach ($files as $f) {
        if (!is_file($f)) { continue; }
        $src = (string) file_get_contents($f);
        $toks = @token_get_all($src);
        if (!$toks) { continue; }
        $prev = null;
        foreach ($toks as $t) {
            if (is_array($t) && in_array($t[0], array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE), true)) { continue; }
            if (is_string($t) && in_array($t, array('<', '>'), true)) { $prev = $t; continue; }
            if (is_array($t) && in_array($t[0], array(T_IS_GREATER_OR_EQUAL, T_IS_SMALLER_OR_EQUAL), true)) {
                $prev = 'cmp'; continue;
            }
            if (($prev === '<' || $prev === '>' || $prev === 'cmp')
                && is_array($t) && in_array($t[0], array(T_LNUMBER, T_DNUMBER), true)) {
                $v = (float) $t[1];
                if ($v > 24 || isset($vals[(string) $v])) {
                    $hits[] = basename($f) . ':' . $t[2] . ' ⇐ ' . $t[1];
                }
            }
            $prev = is_array($t) ? $t[0] : $t;
        }
    }
    return $hits;
}

/** رموزُ النطاقِ التي تُعرَض عربيًّا — مقامٌ ثابتٌ لا يخلو (‏درسُ `W12-27`) */
function repair01_w13_declared_codes()
{
    return array(
        'REPORTER', 'SUBJECT', 'TICKET_OWNER', 'RESOLUTION_OWNER',
        'AUTO', 'CENTER_CORRECTION', 'REPORTER_OBJECTION', 'RECURRENCE',
        'SPECIALIST', 'AUTO_WINDOW',
        'resolved', 'verification', 'verified', 'closed', 'reopened',
        'incident', 'investigation', 'decided', 'appealed',
        'warning', 'deduction', 'suspension', 'termination',
        'transfer', 'promotion', 'secondment', 'demotion', 'return',
        'pending', 'done', 'waived',
        'planned', 'in_progress', 'completed', 'expired', 'failed',
        'draft', 'submitted', 'moderated', 'finalized', 'disputed',
        'valid', 'expiring', 'replaced',
        'active', 'suspended', 'ended',
        'safety', 'compliance', 'technical', 'admin',
        'PERSON', 'ASSET', 'CONTRACT', 'SITE', 'ORG_UNIT', 'DOCUMENT',
        'approved', 'rejected', 'applied',
        /* ── ورموزُ الجداولِ الحيّةِ التي تُصيَّرها أسطحُ هذه الموجة ──────────
           **والمقامُ يشمل ما تعرضه لا ما أنشأته وحدَه**: سطحُ الترشُّحاتِ يعرض
           مراحلَ `rec_applications` وسطحُ الأسطرِ يعرض أصنافَ `payroll_lines` —
           ورمزٌ يُعرَض بلا مسمًّى يخرق معيارَ نقاءِ اللغةِ ولو لم تُنشئه الموجة. */
        'screening', 'interview', 'practical_test', 'offer', 'offer_accepted',
        'contracting', 'onboarded', 'probation', 'confirmed', 'withdrawn', 'received',
        'component', 'overtime', 'absence_deduction', 'production', 'incentive',
        'computed', 'pending_slice', 'blocked',
        'system', 'phone', 'field',
        'sla_breach', 'reopen_threshold', 'hold_overdue',
    );
}

/** الرموزُ المُعلَنةُ التي لا مسمّى عربيًّا لها في القاموسِ المركزيّ */
function repair01_w13_dict_missing(mysqli $c)
{
    $miss = array();
    if (!repair01_w13_table_exists($c, 'repair01_w6_code_dict')) { return array('DICT_TABLE_MISSING'); }
    foreach (repair01_w13_declared_codes() as $code) {
        $n = (int) repair01_w13_one($c, "SELECT COUNT(*) FROM repair01_w6_code_dict
                  WHERE raw_code = '" . $c->real_escape_string($code) . "' AND display_ar <> ''");
        if ($n === 0) { $miss[] = $code; }
    }
    return $miss;
}
