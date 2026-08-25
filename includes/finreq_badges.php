<?php
require_once __DIR__ . '/../includes/catch_log.php';
/**
 * شارات بوابة الطلب المالي D05 للقائمة الجانبية — عدّاداتٌ خفيفة معزولة بالبوابة:
 * صندوق الإدارة (تحت المراجعة لإدارات الدور) · مكتب المحاسب (بانتظار ولادة الحدث) ·
 * طلباتي (المعاد لمنشئه). مستقلٌّ عن مساعدات الوحدة (يُحمَّل في كل صفحةٍ عبر
 * insidebar) — أي فشلٍ يعيد خريطةً صفرية ولا يمسّ الواجهة.
 *
 * الاستعمال: $badges = ems_finreq_nav_badges($conn); ثم تمريرها لـ renderDynamicNavigation.
 */

if (!function_exists('ems_finreq_nav_links')) {
    /**
     * روابط بوابة الطلبات التي يستحقها المستخدم — تُحسب من جدول التوجيه المفعّل
     * لا من مالك الوحدة (owner_role_id) الواحد؛ فكل دورٍ له طريقٌ في أي إدارةٍ
     * مفعّلة يرى النموذج وطلباته، وكل مراجعٍ/معتمِدٍ يرى صندوق إدارته.
     * تُستدعى من insidebar بعد الروابط الديناميكية.
     *
     * @return array من code => array('label','icon')
     */
    function ems_finreq_nav_links($conn)
    {
        $out = array();
        if (!isset($_SESSION['user']['id']) || !function_exists('ems_tenant_db')) {
            return $out;
        }
        try {
            $role = strval($_SESSION['user']['role'] ?? '');
            $is_super = ($role === '-1');
            $g = $is_super ? ems_tenant_db()->forAllTenants('finreq nav links super') : ems_tenant_db();

            $can_create = $is_super;
            $can_review = $is_super;
            foreach ($g->select('fin_request_routing', array('where' => array('is_active' => 1))) as $rt) {
                $creators = array_map('trim', explode(',', strval($rt['requester_roles'])));
                if (in_array($role, $creators, true)) { $can_create = true; }
                if (strval($rt['reviewer_role_id']) === $role || strval($rt['manager_role_id']) === $role) {
                    $can_review = true;
                }
            }
            // أدوار المالية: مكتب المحاسب وبوابة المالية
            $is_acct = in_array($role, array('17', '18'), true) || $is_super;
            $is_fin  = in_array($role, array('17', '18', '19', '20', '21', '22'), true) || $is_super;

            if ($can_create) {
                /* رابطٌ واحدٌ بعدَ دمجِ `my_requests.php` في `request_form.php`
                   (2026-08-21): الشاشةُ نفسُها تُنشئ وتَسرد وتفتح السجل. */
                $out['FinRequests/request_form.php'] = array('label' => 'طلباتي المالية', 'icon' => 'fa fa-list-check');
            }
            if ($can_review) {
                $out['FinRequests/dept_inbox.php'] = array('label' => 'موافقات إدارتي', 'icon' => 'fa fa-inbox');
            }
            if ($is_acct) {
                $out['FinRequests/accountant_desk.php'] = array('label' => 'مكتب المحاسب', 'icon' => 'fa fa-calculator');
            }
            if ($is_fin) {
                $out['FinRequests/finance_gateway.php'] = array('label' => 'الطلبات المالية', 'icon' => 'fa fa-building-columns');
                $out['FinRequests/cycle_time_board.php'] = array('label' => 'زمن دورة الطلبات', 'icon' => 'fa fa-stopwatch');
                $out['FinRequests/requests_reports.php'] = array('label' => 'تقارير الطلبات المالية', 'icon' => 'fa fa-chart-column');
            }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'القائمة الجانبية لا تتعطل بأي فشل هنا');
            // القائمة الجانبية لا تتعطل بأي فشلٍ هنا
        }
        return $out;
    }
}

if (!function_exists('ems_finance_nav_links')) {
    /**
     * روابط شاشات المالية الممنوحة للأدوار التشغيلية (قرار 2026-07-17):
     * السايدبار مملوكيٌّ (owner_role_id=17) فلا يعرضها لغير عائلة المالية —
     * هنا تُشتق من role_permissions مباشرةً (can_view=1) فيصل كل دورٍ لما
     * مُنح له عرضًا. أدوار المالية (17-22) يغطيها dynamic_nav فلا تُكرَّر.
     *
     * @return array من code => array('label','icon')
     */
    function ems_finance_nav_links($conn)
    {
        $out = array();
        if (!isset($_SESSION['user']['id'])) {
            return $out;
        }
        $role = strval($_SESSION['user']['role'] ?? '');
        if ($role === '' || $role === '-1' || in_array($role, array('17', '18', '19', '20', '21', '22'), true)) {
            return $out;
        }
        try {
            $rid = intval($role);
            $q = $conn->prepare(
                "SELECT m.code, m.name, COALESCE(NULLIF(TRIM(m.icon), ''), 'fa fa-coins') AS icon
                   FROM modules m
                   JOIN role_permissions rp ON rp.module_id = m.id
                  WHERE rp.role_id = ? AND rp.can_view = 1
                    AND m.code LIKE 'Finance/%' AND m.is_link = '1'
                  ORDER BY m.display_order ASC, m.id ASC"
            );
            $q->bind_param('i', $rid);
            $q->execute();
            $res = $q->get_result();
            while ($m = $res->fetch_assoc()) {
                $out[strval($m['code'])] = array('label' => strval($m['name']), 'icon' => strval($m['icon']));
            }
            $q->close();
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'القائمة الجانبية لا تتعطل بأي فشل هنا');
            // القائمة الجانبية لا تتعطل بأي فشلٍ هنا
        }
        return $out;
    }
}

if (!function_exists('ems_finreq_nav_badges')) {
    function ems_finreq_nav_badges($conn)
    {
        $out = array();
        if (!isset($_SESSION['user']['id']) || !function_exists('ems_tenant_db')) {
            return $out;
        }
        try {
            $role = strval($_SESSION['user']['role'] ?? '');
            $uid = intval($_SESSION['user']['id']);
            $is_super = ($role === '-1');
            $g = $is_super ? ems_tenant_db()->forAllTenants('finreq nav badges super') : ems_tenant_db();

            // إدارات هذا الدور (مراجعًا/معتمدًا) من التوجيه المفعّل
            $mods = array();
            foreach ($g->select('fin_request_routing', array('where' => array('is_active' => 1))) as $rt) {
                $allowed = ($is_super
                    || strval($rt['reviewer_role_id']) === $role
                    || strval($rt['manager_role_id']) === $role);
                if ($allowed) { $mods[] = strval($rt['source_module']); }
            }
            if ($mods) {
                $ph = implode(',', array_fill(0, count($mods), '?'));
                $n = intval($g->count('fin_requests', array(
                    'whereRaw' => "state = 'under_review' AND source_module IN ($ph)",
                    'params' => $mods,
                )));
                if ($n > 0) { $out['FinRequests/dept_inbox.php'] = $n; }
            }
            if ($role === '18' || $role === '17' || $is_super) {
                $n = intval($g->count('fin_requests', array(
                    'whereRaw' => "state = 'pending_approval' AND event_id IS NULL",
                    'params' => array(),
                )));
                if ($n > 0) { $out['FinRequests/accountant_desk.php'] = $n; }
            }
            $n = intval($g->count('fin_requests', array(
                'whereRaw' => "state = 'returned' AND (created_by = ? OR requester_id = ?)",
                'params' => array($uid, $uid),
            )));
            // الشارةُ تتبع الشاشةَ الباقيةَ بعدَ الدمج — والمعادُ يُستكمل فيها نفسِها
            if ($n > 0) { $out['FinRequests/request_form.php'] = $n; }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'شارات فقط — الواجهة لا تتأثر بأي فشل');
            // شاراتٌ فقط — الواجهة لا تتأثر بأي فشل
        }
        return $out;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   E-20 · شاراتٌ من `nav_items.counter_source` — تعميمُ العدّادِ فعلًا
   ───────────────────────────────────────────────────────────────────────────
   **العطبُ الذي يعالجه هذا القسم**: العمودُ `counter_source` كان **يُكتَب ولا
   يُقرأ**. `includes/unified_nav.php:72` يجلبه في الاستعلام، لكن التصييرَ يبني
   الشارةَ من `$badges[$it['route']]` — مصفوفةٍ تأتي من خارجٍ بثلاثةِ مساراتٍ
   **مثبَّتةٍ نصًّا** في `ems_finreq_nav_badges` أعلاه. فالعمودُ بيانٌ ميتٌ في
   مسارِ العرض: خمسةُ صفوفٍ تحمل مفتاحًا صحيحًا ولا شارةَ تظهر لأحدٍ منها.
   أي أن حكمَ E-20 («تعميمُ العدّادات») **لم يُسلَّم**، وإن بدا مُسلَّمًا لأن
   العمودَ موجودٌ ومملوء.

   ⇒ هنا يُقرأ العمودُ: قاموسٌ يربط كلَّ مفتاحٍ بدالةِ عدٍّ، ثم تُقرأ صفوفُ
     الدورِ ويُبنى `route => count`. فمن يضيف مفتاحًا في `nav_items` تظهر شارتُه
     بلا لمسِ شيفرةِ التصيير — وذاك معنى «التعميم».

   ◆ والعدُّ **مرةً واحدةً لكلِّ مفتاحٍ** ولو تكرّر المفتاحُ في أدوارٍ وصفوفٍ.
   ◆ ولا تتعطّل القائمةُ بأيِّ فشلٍ هنا: كلُّ عدٍّ في `try` وحدَه، وإخفاقُه
     يُسقط شارتَه لا الشجرةَ كلَّها.
   ═══════════════════════════════════════════════════════════════════════════ */
if (!function_exists('ems_nav_counter_dictionary')) {
    /**
     * قاموسُ مفاتيحِ العدّ. المفتاحُ نفسُه المستعمل في `nav_items.counter_source`
     * (وفي `includes/role_board.php:500` للوحاتِ الأدوار — مصدرٌ واحدٌ للتسمية).
     *
     * @return array<string,callable> مفتاح ⇒ دالةٌ تُرجِع عددًا صحيحًا
     */
    function ems_nav_counter_dictionary()
    {
        return array(
            /* البلاغاتُ المتأخرةُ على إدارةِ المستخدم
               ◆ كان يستعلم عن `state` و`due_at` من `tickets` — وهما عمودا
                 `ticket_workstreams` لا الرؤوس (فيها `stage` و`resolution_due_at`).
                 فالاستعلامُ يرمي «Unknown column» فيبتلعه الـcatch أعلاه، وتظهر
                 الشارةُ **صفرًا دائمًا**: عطبٌ صامتٌ يُقرأ «لا تأخّر».
               ◆ ولم يكن يرشِّح إدارةَ المستخدمِ أصلًا رغم اسمِه — فيعدُّ
                 بلاغاتِ الشركةِ كلَّها. والعدُّ الآن على مسارِ وحدتِه هو،
                 مطابقًا لتبويبِ «موجَّهة لإدارتي» الذي تحمله الشارة. */
            'dept_tickets_late' => function ($g, $roleId = 0) {
                require_once dirname(__DIR__) . '/Tickets/dept_inbox_map.php';
                $unit = ems_dept_unit_of_role(intval($roleId));
                if ($unit <= 0) { return 0; }
                return (int) $g->count('ticket_workstreams', array(
                    'whereRaw' => "org_unit_id = ? AND state NOT IN ('closed','admin_closed')
                                   AND resolve_due_at IS NOT NULL AND resolve_due_at < NOW()",
                    'params' => array($unit),
                ));
            },
            /* أيامُ عملٍ تنتظر اعتمادَ سلسلةِ الساعات */
            'hours_approval' => function ($g) {
                return (int) $g->count('unit_entries', array(
                    'whereRaw' => "state IN ('submitted','site_approved','parties_review')",
                    'params' => array(),
                ));
            },
            /* وقائعُ وحدةٍ تنتظر اعتمادًا (غرفةُ العمليات) */
            'units_pending_approval' => function ($g) {
                return (int) $g->count('unit_entries', array(
                    'whereRaw' => "state = 'submitted'", 'params' => array(),
                ));
            },
            /* صندوقُ الإدارةِ للطلباتِ المالية */
            'finreq_dept_inbox' => function ($g) {
                return (int) $g->count('fin_requests', array(
                    'whereRaw' => "state = 'under_review'", 'params' => array(),
                ));
            },
            /* ── مفاتيحُ E-20 الثلاثةُ التي لم تكن تُقرأ ── */
            'tickets_open' => function ($g) {
                return (int) $g->count('tickets', array(
                    'whereRaw' => "state IN ('open','in_progress')", 'params' => array(),
                ));
            },
            'finreq_pending' => function ($g) {
                return (int) $g->count('fin_requests', array(
                    'whereRaw' => "state IN ('under_review','pending_approval')", 'params' => array(),
                ));
            },
            'claims_unbilled' => function ($g) {
                return (int) $g->count('claims', array(
                    'whereRaw' => "COALESCE(is_deleted,0) = 0 AND state IN ('draft','review')",
                    'params' => array(),
                ));
            },
        );
    }
}

if (!function_exists('ems_nav_counter_badges')) {
    /**
     * شاراتُ الدورِ من `counter_source` — `route => count`.
     *
     * @param mysqli   $conn   (يُقبل للتوافق؛ العدُّ يمرُّ ببوابةِ العزل)
     * @param int|null $roleId الدورُ المطلوب — وإلا دورُ الجلسة
     */
    function ems_nav_counter_badges($conn, $roleId = null)
    {
        $out = array();
        if (!isset($_SESSION['user']['id']) || !function_exists('ems_tenant_db')) { return $out; }
        $role = ($roleId === null) ? strval($_SESSION['user']['role'] ?? '') : strval($roleId);
        if ($role === '') { return $out; }
        $dict = ems_nav_counter_dictionary();
        try {
            $g = ($role === '-1')
                ? ems_tenant_db()->forAllTenants('nav counter badges super')
                : ems_tenant_db();
            $rows = $g->select('nav_items', array(
                'columns' => array('route', 'counter_source'),
                'whereRaw' => "role_id = ? AND active = 1 AND counter_source IS NOT NULL AND counter_source <> ''",
                'params' => array(intval($role)),
            ));
            $memo = array();
            foreach ($rows as $r) {
                $key = trim(strval($r['counter_source']));
                $route = trim(strval($r['route']));
                if ($key === '' || $route === '' || !isset($dict[$key])) { continue; }
                if (!array_key_exists($key, $memo)) {
                    // الدورُ يُمرَّر ثانيًا: عدّادٌ «على إدارتي» يلزمه صاحبُ
                    // الإدارة، و`$roleId` قد يخالف دورَ الجلسةِ عند الطلبِ
                    // الصريح. والدوالُّ التي لا تعلنه تتجاهله (PHP يقبل الزائد).
                    try { $memo[$key] = (int) call_user_func($dict[$key], $g, intval($role)); }
                    catch (\Throwable $t) {
                        ems_catch_ignored($t, __METHOD__, 'شارة واحدة تسقط ولا تسقط الشجرة');
                        $memo[$key] = 0;
                    }
                }
                if ($memo[$key] > 0) { $out[$route] = $memo[$key]; }
            }
        } catch (\Throwable $t) {
            ems_catch_ignored($t, __METHOD__, 'شارات فقط — الواجهة لا تتأثر بأي فشل');
        }
        return $out;
    }
}

if (!function_exists('ems_nav_all_badges')) {
    /**
     * كلُّ الشارات: المثبَّتةُ لطلباتِ المالية **+** المعمَّمةُ من `counter_source`.
     * والمثبَّتةُ تُرجَّح عند التعارضِ (فهي أدقُّ: مبنيةٌ على توجيهِ الإدارةِ نفسِه).
     */
    function ems_nav_all_badges($conn, $roleId = null)
    {
        $generic = ems_nav_counter_badges($conn, $roleId);
        $fixed   = ems_finreq_nav_badges($conn);
        return array_merge($generic, $fixed);
    }
}
