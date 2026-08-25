<?php
/**
 * حارس صلاحية الفعل لمعالجات AJAX — Action Permission Guard (ADR-06 · المرحلة 1)
 * ───────────────────────────────────────────────────────────────────────────
 * علاج الخطر R3: الحارس المركزي القائم يتأكد «من أنت» (جلسة + AJAX + معدل)،
 * لكن لا يسأل «هل يحق لك هذا الفعل؟». هذا الملف يضيف السؤال الثاني في نفس
 * نقطة الاعتراض المركزية (config.php · ems_enforce_ajax_endpoint_security) —
 * فيغطّي كل الـ34 معالجًا ولاحقًا كل معالجٍ جديد تلقائيًا، بلا تعديل أي ملف.
 *
 * سجل المعالجات (fail-closed): كل معالجٍ يُربط بشاشته الأم (كود الموديول)
 * والفعل المطلوب. معالجٌ غير مسجَّل ⇒ يُرصد؛ وعند الإنفاذ يُرفض (سجّله واعيًا).
 *
 * الأفعال: 'view' لـ get_* · للمعالجات (*_handler) يُشتق الفعل من
 * $_POST['action'] عبر ACTION_VERB_MAP (add/edit/delete)، وإلا 'edit' تحفّظًا.
 *
 * ثلاثة أوضاع (نمط CSRF/DDL المجرَّب — مراقبة ← إنفاذ ← تراجع):
 *   EMS_ACTION_GUARD='monitor' (افتراضي) → يسجّل action_permission_violation بلا حجب.
 *   EMS_ACTION_GUARD='enforce'           → يرفض (403 JSON) عند غياب الصلاحية.
 *   القيمة من .env؛ التراجع = إعادتها إلى monitor (بلا نشر كود).
 */

if (!function_exists('ems_action_guard_registry')) {

    /**
     * خريطة المعالج → [موديولاته الأم، الفعل]. المفتاح مسارٌ منتهٍ به السكربت
     * (يُطابَق بنهاية SCRIPT_NAME، أحرف صغيرة). 'modules' قد تحمل أكثر من كودٍ
     * (نقطة مشتركة بين شاشات) — يكفي امتلاك الصلاحية على أحدها.
     * 'action':
     *   'view'   قراءة (get_*).
     *   'auto'   يُشتق من $_POST['action'] (المعالجات).
     *   'public' متاحٌ لأي مستخدمٍ مصادَقٍ بقرارٍ واعٍ (لا فحص فعل) — موثّق أدناه.
     */
    function ems_action_guard_registry()
    {
        return array(
            // ── ENG-01 · المحرّكاتُ المشتركة (TS-01 §٤-١٥) ──
            // الفعلُ يُسجَّل قبلَ شاشتِه — وهنا يُربط المعالجُ بوحدتِه كي لا
            // يُحجب بـfail-closed. والأفعالُ في قاموسِ الأفعال: bus.* · job.* ·
            // dr.restore.drill · asset.hours.link · depr.run/reverse.
            'governance/bus_outbox.php'      => array('modules' => array('Governance/bus_outbox.php'),      'action' => 'auto'),
            'governance/bus_deliveries.php'  => array('modules' => array('Governance/bus_deliveries.php'),  'action' => 'auto'),
            'governance/bus_board.php'       => array('modules' => array('Governance/bus_board.php'),       'action' => 'view'),
            'governance/job_queue.php'       => array('modules' => array('Governance/job_queue.php'),       'action' => 'auto'),
            'governance/job_schedule.php'    => array('modules' => array('Governance/job_schedule.php'),    'action' => 'auto'),
            'governance/dr_restore.php'      => array('modules' => array('Governance/dr_restore.php'),      'action' => 'auto'),
            'finance/asset_hours_link.php'   => array('modules' => array('Finance/asset_hours_link.php'),   'action' => 'auto'),
            'finance/depr_run.php'           => array('modules' => array('Finance/depr_run.php'),           'action' => 'auto'),

            // ── Timesheet — شاشة الساعات ──
            // M-16 (update0011): معالج أفعال المخاطر الموحد — الحسم الدقيق
            // بالسلطة داخل RiskService (مصفوفة ورقة 27) فوق صلاحية الشاشة.
            'risk/risk_actions.php'                => array('modules' => array('Risk/'), 'action' => 'auto'),
            'timesheet/get_timesheet.php'          => array('modules' => array('Timesheet/'), 'action' => 'view'),
            'timesheet/get_timesheet_data.php'     => array('modules' => array('Timesheet/'), 'action' => 'view'),
            'timesheet/get_timesheet_failures.php' => array('modules' => array('Timesheet/'), 'action' => 'view'),
            'timesheet/get_operations.php'         => array('modules' => array('Timesheet/'), 'action' => 'view'),
            'timesheet/get_drivers.php'            => array('modules' => array('Timesheet/'), 'action' => 'view'),
            'timesheet/get_failure_codes.php'      => array('modules' => array('Timesheet/'), 'action' => 'view'),
            'timesheet/get_contract_hours.php'     => array('modules' => array('Timesheet/'), 'action' => 'view'),

            // ── Contracts ──
            // ⚠️ **الشاشةُ بكودها الكامل لا ببادئة المجلد** — وهو فخُّ `Approvals/`
            //    الموثَّقُ أدناه، وقع هنا ثانيةً. بادئةُ `Contracts/` لا تُطابق أيَّ
            //    `code` تطابقًا دقيقًا ولا ذيلًا `%/Contracts/.php`، فيسقط
            //    `check_page_permissions` إلى آخرِ محاولاته:
            //    `code LIKE '%Contracts/%' ORDER BY CHAR_LENGTH(code) ASC, id ASC
            //    LIMIT 1` (permissions_helper.php:575) — فيعيد **أقصرَ** كودٍ تحت
            //    المجلد: `Contracts/claims.php` (المستخلصات · #142)، لا شاشةَ العقد.
            //    فقيست صلاحيةُ ملفِّ عقدِ المشروع على المستخلصات: دورُ المبيعات (12)
            //    يملك `can_edit=1` على `Contracts/contracts_details.php` (#21) و`0`
            //    على المستخلصات، فرُدَّ كلُّ إجراءٍ فعلُه `edit` بـ403 — pause ·
            //    resume · terminate · complete · merge · settlement ·
            //    change_obligation · update_project_info/services/parties/payment —
            //    ونجا `renewal` وحدَه لأن «renewal» تحوي «new» فصنّفها
            //    `ems_action_verb_map` فعلَ `add`، والمستخلصاتُ تحمل `can_add=1`.
            //    **الدرسُ**: نجاةُ فعلٍ واحدٍ من اثني عشر ليست صلاحيةً جزئية — بل
            //    دليلٌ على أن المقيسَ شاشةٌ أخرى. الأكوادُ أدناه هي الشاشاتُ الأمُّ
            //    الفعليةُ لكلِّ معالجٍ كما تُنادَى في الرمز لا كما يُوحي المجلد.
            'contracts/get_contract_equipments.php'   => array('modules' => array('Contracts/contracts_details.php'), 'action' => 'view'),
            'contracts/get_equipments.php'            => array('modules' => array('Contracts/contracts.php'), 'action' => 'view'),
            'contracts/contract_actions_handler.php'  => array('modules' => array('Contracts/contracts_details.php'), 'action' => 'auto'),
            // يُضمَّن من `contracts.php` و`contracts_details.php` كلتيهما — وكلتاهما
            // شاشةٌ أمٌّ مشروعة، ويكفي امتلاكُ الصلاحيةِ على إحداهما.
            'contracts/contractequipments_handler.php'=> array('modules' => array('Contracts/contracts.php', 'Contracts/contracts_details.php'), 'action' => 'auto'),

            // ── Suppliers ──
            'suppliers/get_mine_contracts.php'                => array('modules' => array('Suppliers/'), 'action' => 'view'),
            'suppliers/get_project_hours.php'                 => array('modules' => array('Suppliers/'), 'action' => 'view'),
            'suppliers/get_supplier_contract_equipments.php'  => array('modules' => array('Suppliers/'), 'action' => 'view'),
            'suppliers/supplier_contract_actions_handler.php' => array('modules' => array('Suppliers/'), 'action' => 'auto'),

            // ── Equipments ──
            'equipments/get_contract_stats.php'    => array('modules' => array('Equipments/'), 'action' => 'view'),
            'equipments/get_equipment_details.php' => array('modules' => array('Equipments/'), 'action' => 'view'),
            'equipments/get_mine_contracts.php'    => array('modules' => array('Equipments/'), 'action' => 'view'),
            'equipments/get_model_data.php'        => array('modules' => array('Equipments/'), 'action' => 'view'),

            // ── Oprators — شاشة التشغيل ──
            'oprators/get_contract_stats.php'    => array('modules' => array('Oprators/'), 'action' => 'view'),
            'oprators/get_contract_suppliers.php'=> array('modules' => array('Oprators/'), 'action' => 'view'),
            'oprators/get_mine_contracts.php'    => array('modules' => array('Oprators/'), 'action' => 'view'),

            // ── Employees ──
            'employees/get_employee_data.php'               => array('modules' => array('Employees/'), 'action' => 'view'),
            'employees/get_employee_contract_equipments.php'=> array('modules' => array('Employees/'), 'action' => 'view'),
            'employees/get_mine_contracts.php'              => array('modules' => array('Employees/'), 'action' => 'view'),
            'employees/get_project_hours.php'               => array('modules' => array('Employees/'), 'action' => 'view'),
            'employees/employee_contract_actions_handler.php'=> array('modules' => array('Employees/'), 'action' => 'auto'),

            // ── Maintenance — عدّادات الجرس قراءةٌ خفيفة ──
            // breakdown_count عمومي-بوعي (K10 · قرار المستخدم 2026-07-09): شارة
            // التوبار المشتركة تستطلعه من كل صفحةٍ لكل الأدوار (صنف chats) —
            // ربطه بMaintenance/ أنتج 12 would-block حقيقيًا لمستخدم مبيعات في
            // نافذة المراقبة. النقطة حميدة: عدّاد معزول بشركة الجلسة يعيد رقمًا
            // فقط. إخفاء الشارة بشرط صلاحيةٍ = قرار مُلّاكٍ منفصلٌ لاحقًا —
            // الهجرة لا تغيّر ما يراه المستخدم.
            'maintenance/get_breakdown_count.php'   => array('modules' => array(), 'action' => 'public'),
            'maintenance/get_open_orders_count.php' => array('modules' => array('Maintenance/'), 'action' => 'view'),
            'maintenance/get_project_equipment.php' => array('modules' => array('Maintenance/'), 'action' => 'view'),

            // ── Reports ──
            'reports/get_mine_contracts.php' => array('modules' => array('Reports/'), 'action' => 'view'),

            // ── Approvals — محصّنٌ أصلًا بمجموعة أدواره؛ نبقيه بفحص view على شاشته ──
            // ⚠️ **الشاشةُ بكودها الكامل لا ببادئة المجلد.** فـ`check_page_permissions`
            //    يطابق بـ`code LIKE '%…%' … LIMIT 1` **بلا ORDER BY**
            //    (permissions_helper.php:353)، فبادئةُ `Approvals/` تلتقط **أيَّ**
            //    وحدةٍ تُسجَّل لاحقًا تحت المجلد ثم تُقاس عليها الصلاحية.
            //    وقد وقع ذلك فعلًا: تسجيلُ «لوحة الإسناد اليومي» (الوحدة 146)
            //    جعل الاستعلامَ يعيدها، فحُجب مديرو الموردين والأسطول والمشغّلين
            //    (الأدوار 2·3·4) عن اعتماد الساعات — وكشفه `unit_chain_e2e_proof`
            //    بسقوطه من 37 إلى 10 ناجحًا. الكودُ الكاملُ يقطع الالتباس.
            'approvals/hours_approval_handler.php' => array('modules' => array('Approvals/hours_approval.php'), 'action' => 'auto'),

            // ── الدردشة: متاحة لأي مستخدمٍ مصادَق بقرارٍ واعٍ (رسائل داخلية عامة) ──
            'chats/get_messages.php'     => array('modules' => array(), 'action' => 'public'),
            'chats/get_unread_count.php' => array('modules' => array(), 'action' => 'public'),

            // ── البلاغات: عدّاد شارة الشريط العلوي المشترك ──
            // «عمومي-بوعي» بنفس حجّة عدّاد المراسلات: الشارة يستطلعها كلُّ
            // صفحةٍ ولكل الأدوار، وهي نقطةٌ حميدة تعيد رقمًا فقط — معزولةً
            // بشركة الجلسة **وبنطاق رؤية المستخدم** داخلها، فلا تكشف وجود ما
            // لا يراه على الشاشة أصلًا.
            'tickets/get_tickets_count.php' => array('modules' => array(), 'action' => 'public'),
        );
    }

    /** خريطة قيمة $_POST['action'] → فعل الصلاحية. */
    function ems_action_verb_map($rawAction)
    {
        $a = strtolower(trim((string) $rawAction));
        if ($a === '') {
            return 'edit'; // معالجٌ بلا فعلٍ محدد ⇒ يُعامَل ككتابة تحفّظًا.
        }
        $add = array('add', 'create', 'insert', 'new', 'store', 'assign', 'approve');
        $del = array('delete', 'remove', 'destroy', 'cancel', 'unassign');
        foreach ($add as $k) { if (strpos($a, $k) !== false) { return 'add'; } }
        foreach ($del as $k) { if (strpos($a, $k) !== false) { return 'delete'; } }
        // get_/list_/fetch_ داخل معالجٍ = قراءة.
        if (strpos($a, 'get') === 0 || strpos($a, 'list') === 0 || strpos($a, 'fetch') === 0 || strpos($a, 'load') === 0) {
            return 'view';
        }
        return 'edit';
    }

    /**
     * الحارس — يُستدعى من نقطة اعتراض AJAX المركزية. لا يعمل إلا على معالجٍ
     * فعلي وبجلسة مستخدمٍ نظامي (super admin والبوابات الأخرى تمر).
     */
    function ems_enforce_action_permission($conn)
    {
        if (php_sapi_name() === 'cli') {
            return;
        }
        $mode = function_exists('ems_env') ? strtolower((string) ems_env('EMS_ACTION_GUARD', 'monitor')) : 'monitor';
        if ($mode !== 'monitor' && $mode !== 'enforce') {
            $mode = 'monitor';
        }

        $script = isset($_SERVER['SCRIPT_NAME']) ? strtolower(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])) : '';
        if ($script === '') {
            return;
        }

        // المدير الأعلى يمر (يملك كل شيء).
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        $superRole = defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1';
        if ($role === $superRole) {
            return;
        }
        // بوابات غير مستخدمي النظام (super_admin panel / company_user) خارج النطاق هنا.
        if (!isset($_SESSION['user'])) {
            return;
        }

        // مطابقة السجل بنهاية المسار.
        $entry = null;
        foreach (ems_action_guard_registry() as $suffix => $def) {
            if (substr($script, -strlen($suffix)) === $suffix) {
                $entry = $def;
                break;
            }
        }

        if ($entry === null) {
            // معالجٌ غير مسجَّل — fail-closed.
            ems_action_guard_log('unregistered', $script, '-', $mode);
            if ($mode === 'enforce') {
                ems_ajax_guard_response(403, 'هذا الإجراء غير مصرح (معالج غير مسجل).');
            }
            return;
        }

        /* ◆ **FR-GOV-004 (GAP-14) — الفعلُ يُحسَب قبلَ أوّلِ تسجيلِ رفض**:
           كان `$verb` يُستعمل في تسجيلِ `scope_denied` **قبلَ تعريفِه بأربعةَ
           عشرَ سطرًا** — فكلُّ واقعةِ رفضٍ بسببِ المساحةِ تُسجَّل **بفعلٍ فارغ**،
           والسجلُّ يقول «رُفض» ولا يقول «رُفض ماذا». ⇒ يُحسَب هنا. */
        $verb = $entry['action'] === 'auto'
            ? ems_action_verb_map(isset($_POST['action']) ? $_POST['action'] : '')
            : $entry['action'];

        /* ══ عزلُ الإدارات (سابعًا-⑧) — الفعلُ يعرف مساحتَه ══════════════════
           ◆ **الصلاحيةُ وحدَها تترك البابَ الثامنَ مفتوحًا**: الشاشةُ أُزيلت من
             السايدبارِ ومُنعت بالعنوانِ المباشر، **ومعالجُها يظل يقبل الفعلَ**
             لأنه لا يسأل إلا «أيحق لك؟» لا «أهنا؟». فيمرُّ الفعلُ الجماعيُّ
             والنداءُ الخلفيُّ إلى شاشةٍ ممنوعةٍ في هذه المساحة.
           ◆ **والسؤالُ بالمساحةِ لا بالمستخدم**: الفعلُ نفسُه مشروعٌ في المساحةِ
             المالكةِ ويُرفض في الأجنبية — فلا تُكسر صلاحيةٌ مشروعة.
           ◆ ويُسجَّل الرفضُ بنوعِه (`scope_denied`) فلا يختلط بمنعِ الصلاحية:
             **اثنانِ يُرفضان بالرمزِ نفسِه لسببَين مختلفَين يُخفيان أحدَهما.** */
        $__sf = dirname(__DIR__) . '/includes/space_scope.php';
        if (is_file($__sf) && isset($_SESSION['user'])
            && strval(isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '') !== '-1') {
            require_once $__sf;
            if (function_exists('ems_scope_forbids')) {
                $__owner = isset($entry['screen']) ? (string) $entry['screen']
                         : (isset($entry['route']) ? (string) $entry['route'] : '');
                foreach (array($script, $__owner) as $__cand) {
                    if ($__cand === '' || !ems_scope_forbids($__cand)) { continue; }
                    ems_action_guard_log('scope_denied', $script, $verb, $mode);
                    if ($mode === 'enforce') {
                        ems_ajax_guard_response(403,
                            'هذا الإجراء يخص مساحة عمل أخرى — بدل المساحة لتنفيذه.');
                    }
                    return;
                }
            }
        }

        if ($entry['action'] === 'public') {
            return; // قرارٌ واعٍ موثَّق في السجل.
        }


        // هل يملك الدور الصلاحية على أيٍّ من موديولات الشاشة الأم؟
        // دوال الصلاحيات تُحمَّل من الصفحات لا من config — نحمّلها هنا لأن الحارس
        // يعمل أثناء تضمين config قبل أن يحمّلها المعالج (تعريفات دوالٍ آمنة).
        if (!function_exists('check_page_permissions')) {
            $helper = __DIR__ . '/permissions_helper.php';
            if (is_readable($helper)) {
                require_once $helper;
            }
        }
        if (!function_exists('check_page_permissions')) {
            // تعذّر تحميلها — سجّل ولا تحجب أعمى (fail-safe للتوفّر لا للأمن).
            ems_action_guard_log('helper_unavailable', $script, '-', $mode);
            return;
        }
        $key = 'can_' . $verb; // can_view | can_add | can_edit | can_delete
        $allowed = false;
        foreach ($entry['modules'] as $code) {
            $perms = check_page_permissions($conn, $code);
            if (!empty($perms[$key])) {
                $allowed = true;
                break;
            }
        }

        if ($allowed) {
            // خطافُ نهاية الطلب (ACT-01 §8-④): إن أُنجز فعلُ كتابةٍ مسجَّلٌ لهذا
            // المسار بنجاح (استجابة < 400) طُبّقت خريطةُ أثره — فلا لوحةَ تبقى قديمة.
            $agConn = $conn; $agScript = $script;
            register_shutdown_function(function () use ($agConn, $agScript) {
                if (http_response_code() >= 400) { return; }
                if (!$agConn || !@mysqli_ping($agConn)) { return; }
                $es = mysqli_real_escape_string($agConn, $agScript);
                $r = @mysqli_query($agConn, "SELECT action_code FROM actions
                        WHERE is_write = 1 AND active = 1 AND handler_path LIKE '%$es'");
                if (!$r || !mysqli_num_rows($r)) { return; }
                $svc = __DIR__ . '/../app/Services/Actions/ImpactResolver.php';
                if (!is_file($svc)) { return; }
                require_once $svc;
                $co = intval($_SESSION['user']['company_id'] ?? 0);
                $uid = intval($_SESSION['user']['id'] ?? 0);
                while ($a = mysqli_fetch_assoc($r)) {
                    \App\Services\Actions\ImpactResolver::apply($agConn, $co, $a['action_code'], $agScript, $uid);
                }
            });
            return;
        }

        ems_action_guard_log('denied', $script, $verb, $mode);
        if ($mode === 'enforce') {
            ems_ajax_guard_response(403, 'ليس لديك صلاحية تنفيذ هذا الإجراء.');
        }
    }

    /** تسجيل مخالفةٍ في security.log (مراقبةً أو إنفاذًا). */
    function ems_action_guard_log($kind, $script, $verb, $mode)
    {
        if (!function_exists('log_security_event')) {
            return;
        }
        $user = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 'GUEST';
        $role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '-';
        log_security_event('action_permission_violation', sprintf(
            'mode=%s kind=%s script=%s verb=%s user=%s role=%s',
            $mode, $kind, $script, $verb, $user, $role
        ));
    }
}
