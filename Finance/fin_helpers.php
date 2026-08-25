<?php
/**
 * Finance/fin_helpers.php — دوال مساعدة مشتركة للإدارة المالية (fin_*).
 *
 * ملاحظات معمارية (نفس نمط proc_helpers / mnt_helpers — غير كاسر):
 *   • دوال نقية فقط؛ إقلاع الجلسة/الصلاحيات يبقى داخل كل صفحة.
 *   • قوائم الموردين/العملاء/المعدات/المشاريع تُقرأ من الجداول القائمة قراءةً فقط
 *     (لا كتابة، لا FK) ⇒ لا تأثير على النظام الحالي.
 *   • ملاحظات ربط فعلية: suppliers.name · clients.client_name · employees.name ·
 *     project.name · contracts بلا عمود اسم.
 *
 * @package EMS\Finance
 */

if (!function_exists('fin_role_level_map')) {
    /** خريطة اسم الدور → المستوى الوظيفي (فصل الواجبات). */
    function fin_role_level_map()
    {
        return array(
            'المدير المالي'          => 'finance_manager',
            'مدير الإدارة المالية'   => 'dept_manager',
            'محاسب الإدارة المالية'  => 'dept_accountant',
            'المراجع والمدقق المالي' => 'finance_reviewer',
            'أمين الخزينة'           => 'treasurer',
            'قارئ مالي'              => 'reader',
        );
    }
}
if (!function_exists('fin_user_level')) {
    /** مستوى المستخدم الحالي؛ 'all' للمدير الأعلى، 'none' لغير المعرّف. */
    function fin_user_level($conn, $role_id)
    {
        if ((string)$role_id === EMS_ROLE_SUPER_ADMIN) { return 'all'; }
        $rid = intval($role_id);
        // roles جدولٌ عالمي (T_GLOBAL) — قراءةٌ بلا نطاقٍ عبر البوابة
        $row = ems_tenant_db()->selectOne('roles', array('columns' => array('name'), 'where' => array('id' => $rid)));
        $name = $row ? trim($row['name']) : '';
        $map = fin_role_level_map();
        return isset($map[$name]) ? $map[$name] : 'none';
    }
}
if (!function_exists('fin_publish_request_fact')) {
    /**
     * حقيقة دورة الطلب من جانب D04 (§9.3 — إكمال الثمانية):
     * request.approved (الاعتماد المالي) · finance.posted (القيد) ·
     * treasury.paid/collected (الأداء). تُنشر على الجذر المحايد فقط
     * (publishFact — بلا إسقاطٍ مالي) وللحدث المربوط بطلبٍ حصرًا؛ حدثٌ
     * يدويٌّ بلا طلبٍ خارج دورة D05 فلا حقيقة له هنا (NULL دلالي).
     * لا ترمي أبدًا — فشل الحقيقة يُدوَّن ولا يكسر عملية المالية.
     */
    function fin_publish_request_fact($conn, $event_id, $event_key, $idem_kind, array $extra = array())
    {
        try {
            $req = ems_tenant_db()->selectOne('fin_requests', array(
                'where' => array('event_id' => intval($event_id)),
            ));
            if (!$req) { return null; }
            require_once __DIR__ . '/../app/Core/EventPublisher.php';
            $actor = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id'])
                : (intval($req['created_by'] ?? 0) ?: 1);
            return \App\Core\EventPublisher::publishFact($conn, array(
                'event_key' => strval($event_key),
                'category' => 'financial',
                'source_module' => strval($req['source_module']) === 'general' ? 'finance' : strval($req['source_module']),
                'company_id' => intval($req['company_id']),
                'entity_type' => 'fin_request',
                'entity_id' => intval($req['id']),
                'occurred_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => $actor,
                'idempotency_key' => 'fact:' . strval($idem_kind) . ':' . intval($req['id']),
                'amount' => floatval($req['amount']),
                'currency' => strval($req['currency']),
                'source_ref' => strval($req['request_no']),
                'project_id' => !empty($req['project_id']) ? intval($req['project_id']) : null,
                'equipment_id' => !empty($req['equipment_id']) ? intval($req['equipment_id']) : null,
                'payload' => array_merge(array(
                    'request_no' => strval($req['request_no']),
                    'request_type' => strval($req['request_type']),
                    'event_id' => intval($event_id),
                ), $extra),
            ));
        } catch (\Throwable $t) {
            error_log('fin request fact ' . $event_key . ': ' . $t->getMessage());
            return null;
        }
    }
}
if (!function_exists('fin_project_scope')) {
    /**
     * نطاق المشروع للأدوار المرتبطة بموقعٍ محدد (قرار 2026-07-17):
     * مدير الموقع (5) ومدير الحركة والتشغيل (6) يريان **مشروعهما حصرًا** في
     * شاشات المالية ذات البعد المشروعي (الوحدات · التكاليف · الأحداث)،
     * وبقية الأدوار الممنوحة ترى كل صفوف شركتها (عرضًا فقط).
     *
     * fail-closed: دورٌ مشروعيٌّ بلا users.project_id مضبوطٍ يعيد -1
     * (صفر صفوف) — لا يفتح الباب كله لغياب الضبط.
     *
     * @return int|null null = بلا تصفية · موجب = المشروع · -1 = لا شيء
     */
    /* ══ INJ-FIX-01 · GAP-22 — نطاقٌ يفتح افتراضيًّا للدورِ الجديد ═══════════
       ◆ **العطبُ**: كان الحكمُ «ليس 5 ولا 6 ⇒ `null` ⇒ بلا تصفية». فالدورُ
         الذي يُنشأ غدًا ويُمنح الشاشةَ **يرى كلَّ صفوفِ الشركةِ فورًا** بلا
         قرارٍ من أحد. والانفتاحُ الافتراضيُّ عيبُ اتجاه: القاعدةُ منعٌ ما لم
         يُصرَّح، لا سماحٌ ما لم يُمنَع.
       ◆ **والعلاجُ قائمةٌ صريحةٌ لا فشلٌ مغلقٌ إلى صفر**: قُيس أن ستةَ عشرَ
         دورًا تملك `can_view` على الشاشاتِ الثلاثِ فعلًا — فقلبُ المجهولِ إلى
         «صفرِ صفٍّ» كان سيُفرِغ الشاشةَ لعشرةِ أدوارٍ تشغيليةٍ تملكها بحقّ.
         فيُصنَّف كلُّ دورٍ **صراحةً**: مقيَّدٌ بمشروعِه · أو يرى شركتَه · وما
         لم يُصنَّف **لا يرى شيئًا** حتى يُصنَّف.
       ◆ **فالسلوكُ اليومَ لم يتغيّر حرفًا** — والبابُ أُغلق على الغد. */
    /** أدوارٌ مقيَّدةٌ بمشروعِها حصرًا (قرار 2026-07-17). */
    if (!defined('FIN_SCOPE_PROJECT_ROLES')) { define('FIN_SCOPE_PROJECT_ROLES', '5,6'); }
    /** أدوارٌ ترى صفوفَ شركتِها كلَّها — **مصنَّفةٌ صراحةً لا افتراضًا**. */
    if (!defined('FIN_SCOPE_COMPANY_ROLES')) {
        define('FIN_SCOPE_COMPANY_ROLES', '1,3,7,10,11,12,13,14,16,17,18,19,20,21,22,23');
    }

    function fin_project_scope($conn, $ctx)
    {
        if (!empty($ctx['is_super'])) { return null; }
        $r = strval($ctx['role']);
        $projectRoles = explode(',', FIN_SCOPE_PROJECT_ROLES);
        $companyRoles = explode(',', FIN_SCOPE_COMPANY_ROLES);

        if (in_array($r, $companyRoles, true)) { return null; }   // مصنَّفٌ: يرى شركتَه
        if (!in_array($r, $projectRoles, true)) {
            /* دورٌ غيرُ مصنَّفٍ في أيِّ قائمة — يُمنَع حتى يُصنَّف، ويُسجَّل
               ليُعلم أنه يحتاج تصنيفًا لا ليبقى صامتًا. */
            error_log('[GAP-22] fin_project_scope: دور غير مصنف للنطاق role=' . $r
                    . ' — أرجع صفر صف حتى يصنف في FIN_SCOPE_*_ROLES');
            return -1;
        }
        try {
            $u = ems_tenant_db()->selectOne('users', array(
                'columns' => array('project_id'),
                'where' => array('id' => intval($ctx['user_id'])),
            ));
            $pid = $u ? intval($u['project_id']) : 0;
            return $pid > 0 ? $pid : -1;
        } catch (\Throwable $t) {
            return -1; // أي فشلٍ = لا صفوف، لا كل الصفوف
        }
    }
}
/* ◆ **رمزانِ صريحانِ بدل `null` الغامض**: كان `null` يعني «كلَّ الصفوف»
 *   وهو أيضًا ما يعود عند الغياب — فالغيابُ والانفتاحُ لهما القيمةُ نفسُها.
 *   ⇒ يُفصلان: `ALL` انفتاحٌ مُعلَن · `NONE` إغلاقٌ افتراضيّ. */
if (!defined('PARTY_SCOPE_ALL'))  { define('PARTY_SCOPE_ALL',  '__ALL__'); }
if (!defined('PARTY_SCOPE_NONE')) { define('PARTY_SCOPE_NONE', '__NONE__'); }
if (!function_exists('fin_party_scope')) {
    /**
     * نطاق نوع الطرف للأدوار الممنوحة عرض الذمم/المدفوعات.
     *
     * ◆ FR-SEC-001 · FR-SEC-002 (GAP-22) — **يفشل مغلقًا**:
     *   كان يعود `null` لأيِّ دورٍ لم تسمِّه الدالة، و`null` تعني عند
     *   مستهلكَيها **كلَّ الصفوف**. فخمسةٌ وعشرون دورًا غيرَ مصنَّفٍ كانت ترى
     *   ذممَ المنشأةِ ومدفوعاتِها كاملةً — **انفتاحٌ بالصمتِ لا بقرار**.
     *   الآن: الدورُ غيرُ المسجَّلِ يُرجع `PARTY_SCOPE_NONE` ⇒ **صفرُ صفٍّ**.
     *
     * ◆ والنطاقُ يُقرأ من **مصدرٍ واحدٍ مُعلَن** — `fin_party_scope_registry` —
     *   لا من شيفرةِ هذه الدالةِ ولا من كودِ الشاشة. والانفتاحُ المشروعُ
     *   يُكتب `ALL` صراحةً فلا يبقى فراغٌ يُقرأ انفتاحًا.
     *
     * ◆ **وتعذُّرُ قراءةِ السجلِّ يفشل مغلقًا أيضًا** — لا يُقرأ عطبُ الاتصالِ
     *   إذنًا. (والسوبر أدمن وحدَه يعبر — وهو استثناءٌ قائمٌ قبلَ هذا المطلب.)
     *
     * @return string 'employee' · 'supplier' · 'client' · PARTY_SCOPE_ALL · PARTY_SCOPE_NONE
     */
    function fin_party_scope($ctx)
    {
        if (!empty($ctx['is_super'])) {
            return PARTY_SCOPE_ALL;
        }
        $role = intval(isset($ctx['role']) ? $ctx['role'] : 0);
        if ($role <= 0) {
            return PARTY_SCOPE_NONE;
        }
        static $cache = array();
        if (array_key_exists($role, $cache)) { return $cache[$role]; }

        $scope = PARTY_SCOPE_NONE;
        try {
            $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
            if ($conn instanceof mysqli) {
                $st = $conn->prepare(
                    'SELECT `party_scope` FROM `fin_party_scope_registry` WHERE `role_id` = ? LIMIT 1');
                if ($st) {
                    $st->bind_param('i', $role);
                    $st->execute();
                    $st->bind_result($found);
                    if ($st->fetch()) {
                        $scope = ($found === 'ALL') ? PARTY_SCOPE_ALL : (string) $found;
                    }
                    $st->close();
                }
            }
        } catch (\Throwable $e) {
            $scope = PARTY_SCOPE_NONE;   /* أيُّ فشلٍ = لا صفوف، لا كل الصفوف */
        }
        return $cache[$role] = $scope;
    }
}
if (!function_exists('fin_can_perform')) {
    /**
     * هل يملك دور المستخدم صلاحية أداء مستوى معيّن؟ (فصل الواجبات)
     * المدير الأعلى (all) يؤدّي الكل؛ المدير المالي يؤدّي أيضًا مستوى المراجعة (أقدميّة).
     */
    function fin_can_perform($conn, $role_id, $required_level)
    {
        $lvl = fin_user_level($conn, $role_id);
        if ($lvl === 'all') { return true; }
        if ($lvl === $required_level) { return true; }
        // المدير المالي يغطّي مستوى المراجعة/التدقيق أيضًا
        if ($lvl === 'finance_manager' && $required_level === 'finance_reviewer') { return true; }
        return false;
    }
}
if (!function_exists('fin_level_owner_label')) {
    /** اسم الدور صاحب المستوى (لرسائل الرفض). */
    function fin_level_owner_label($level)
    {
        $m = array('dept_accountant' => 'محاسب الإدارة', 'dept_manager' => 'مدير الإدارة',
                   'finance_reviewer' => 'المراجع/المدقق', 'finance_manager' => 'المدير المالي',
                   'treasurer' => 'أمين الخزينة');
        return isset($m[$level]) ? $m[$level] : $level;
    }
}
if (!function_exists('fin_base_amount')) {
    /**
     * تعبير SQL يحوّل المبلغ إلى عملة الأساس (SDG): USD × سعر الصرف (افتراضي 600 عند غيابه).
     * يمنع خلط العملات في التجميعات (إصلاح #1).
     */
    function fin_base_amount($amt = 'amount', $cur = 'currency', $fx = 'fx_rate')
    {
        return "(CASE WHEN $cur = 'USD' THEN $amt * COALESCE($fx, 600) ELSE $amt END)";
    }
}
if (!function_exists('fin_action_token')) {
    /** رمز الإجراء لروابط GET الحسّاسة ماليًّا (إصلاح #2). */
    function fin_action_token()
    {
        return function_exists('generate_csrf_token') ? generate_csrf_token() : '';
    }
}
if (!function_exists('fin_verify_action_token')) {
    /** التحقق من رمز الإجراء (GET/_t أو POST/csrf_token). */
    function fin_verify_action_token()
    {
        $t = isset($_GET['_t']) ? $_GET['_t'] : (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '');
        return function_exists('verify_csrf_token') ? verify_csrf_token($t) : true;
    }
}
if (!function_exists('fin_period_posting_open')) {
    /**
     * هل يُسمح بالترحيل في تاريخ معيّن؟ (إصلاح #3 — لا قيد في فترة مقفلة)
     * يعيد true إذا لم تُعرَّف فترة تغطّي التاريخ (توافق رجعي)، أو فترة مفتوحة تسمح بالقيد.
     */
    function fin_period_posting_open($conn, $company_id, $date)
    {
        // البوابة تعزل الشركة؛ نطاق التاريخ عبر معامِلٍ مربوط (لا فلتر حذف — الجدول soft=false)
        $row = fin_gate(false)->selectOne('fin_financial_periods', array(
            'columns'  => array('posting_allowed', 'state'),
            'where'    => array('period_type' => 'month'),
            'whereRaw' => '? BETWEEN start_date AND end_date',
            'params'   => array($date),
            'orderBy'  => 'id DESC',
        ));
        if (!$row) { return true; } // لا فترة معرّفة ⇒ يُسمح (توافق)
        return intval($row['posting_allowed']) === 1;
    }
}
if (!function_exists('fin_ctx')) {
    function fin_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'       => $role,
            'is_super'   => ($role === EMS_ROLE_SUPER_ADMIN),
            'company_id' => isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0,
            'user_id'    => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
        );
    }
}

if (!function_exists('fin_page_perms')) {
    function fin_page_perms($conn, $code, $is_super)
    {
        $p = check_page_permissions($conn, $code);
        return array(
            'can_view'   => $is_super ? true : $p['can_view'],
            'can_add'    => $is_super ? true : $p['can_add'],
            'can_edit'   => $is_super ? true : $p['can_edit'],
            'can_delete' => $is_super ? true : $p['can_delete'],
        );
    }
}

if (!function_exists('fin_scope')) {
    function fin_scope($col, $is_super, $company_id)
    {
        return $is_super ? '1=1' : ($col . ' = ' . intval($company_id));
    }
}

if (!function_exists('fin_gate')) {
    /**
     * بوابة العزل لسياق الجلسة (نمط trs_gate). $is_super=true ⇒ رؤية عابرة محروسة.
     * في سياق النظام (cron بلا جلسة): الدورة تركّب بوابتها forSystem($cid) وتحقنها
     * عبر fin_gate_override فتتبعها كل دوال fin_helpers هنا (نمط trs_gate_override).
     */
    function fin_gate($is_super = false)
    {
        if (isset($GLOBALS['__fin_gate_override']) && $GLOBALS['__fin_gate_override'] instanceof \App\Core\TenantDb) {
            return $GLOBALS['__fin_gate_override'];
        }
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('fin helpers super view') : $gate;
    }

    /** حقن بوابة دورة النظام (null = العودة لسياق الجلسة). */
    function fin_gate_override($gate)
    {
        $GLOBALS['__fin_gate_override'] = $gate;
    }
}

if (!function_exists('fin_gen_code')) {
    /** كود تسلسلي لكل شركة، مثل FIN-EV-0001. اسم الجدول من قائمة بيضاء في الكود. */
    function fin_gen_code($conn, $table, $prefix, $company_id)
    {
        // عدٌّ شامل للمحذوف عبر البوابة (يطابق COUNT(*) WHERE company_id=X الأصلي، بلا فلتر حذف)
        $n = ems_tenant_db()->count($table, array('includeDeleted' => true));
        return $prefix . '-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('fin_msg_banner')) {
    function fin_msg_banner()
    {
        if (empty($_GET['msg'])) {
            return;
        }
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        echo '<div class="success-message ' . ($isSuccess ? 'is-success' : 'is-error') . '">';
        echo '<i class="fas ' . ($isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle') . '"></i> ';
        echo htmlspecialchars($_GET['msg']);
        echo '</div>';
    }
}

if (!function_exists('fin_options_from_rows')) {
    /** تنسيق خيارات <option> من صفوفٍ قُرئت عبر البوابة (كل صف: id + label جاهز PHP-side). */
    function fin_options_from_rows(array $rows, $selected = 0, $placeholder = '— اختر —')
    {
        $out = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        $selected = intval($selected);
        foreach ($rows as $r) {
            $id  = intval($r['id']);
            $lbl = isset($r['label']) ? (string)$r['label'] : '';
            $sel = ($id === $selected) ? ' selected' : '';
            $out .= '<option value="' . $id . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
        }
        return $out;
    }
}

// ── قوائم من الجداول القائمة (قراءة فقط) ──
if (!function_exists('fin_supplier_options')) {
    function fin_supplier_options($conn, $is_super, $company_id, $selected = 0)
    {
        // الأصل بلا فلتر حذف (يعرض حتى المؤرشف) → includeDeleted وفاءً ذهبيًّا
        $rows = fin_gate($is_super)->select('suppliers', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC', 'includeDeleted' => true));
        foreach ($rows as &$r) { $r['label'] = $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— بلا مورد —');
    }
}
if (!function_exists('fin_client_options')) {
    function fin_client_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = fin_gate($is_super)->select('clients', array('columns' => array('id', 'client_name'), 'orderBy' => 'client_name ASC', 'includeDeleted' => true));
        foreach ($rows as &$r) { $r['label'] = $r['client_name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— بلا عميل —');
    }
}
if (!function_exists('fin_project_options')) {
    function fin_project_options($conn, $is_super, $company_id, $selected = 0)
    {
        // project soft=true؛ الأصل يفلتر is_deleted=0 → البوابة تفعله افتراضيًّا
        $rows = fin_gate($is_super)->select('project', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— بلا مشروع —');
    }
}
if (!function_exists('fin_equipment_options')) {
    function fin_equipment_options($conn, $is_super, $company_id, $selected = 0)
    {
        // equipments soft=false؛ التسمية CONCAT(code|#id [ — name]) تُحسب PHP-side
        $rows = fin_gate($is_super)->select('equipments', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'id DESC'));
        foreach ($rows as &$r) {
            $code = ($r['code'] !== null && $r['code'] !== '') ? $r['code'] : ('#' . $r['id']);
            $r['label'] = $code . (($r['name'] === null || $r['name'] === '') ? '' : (' — ' . $r['name']));
        } unset($r);
        return fin_options_from_rows($rows, $selected, '— بلا معدة —');
    }
}

// ── قوائم بيضاء ثابتة (enums + خرائط تسمية عربية) ──
if (!function_exists('fin_event_types')) {
    function fin_event_types()
    {
        return array(
            'revenue'    => 'إيراد',
            'expense'    => 'مصروف',
            'payable'    => 'مستحق دائن (مورد)',
            'receivable' => 'ذمة مدينة (عميل)',
            'payroll'    => 'رواتب',
            'settlement' => 'تسوية',
        );
    }
}
if (!function_exists('fin_source_modules')) {
    function fin_source_modules()
    {
        return array(
            'sales'       => 'المبيعات',
            'suppliers'   => 'الموردون',
            'workforce'   => 'القوى التشغيلية',
            'procurement' => 'المشتريات',
            'warehouse'   => 'المخازن',
            'maintenance' => 'الصيانة',
            'projects'    => 'المشاريع',
            'revenue'     => 'الإيرادات',
            'assets'      => 'الأصول',
            'treasury'    => 'الخزينة',
        );
    }
}
if (!function_exists('fin_event_states')) {
    function fin_event_states()
    {
        return array(
            'draft'         => 'مسودة',
            'dept_review'   => 'مراجعة المحاسب',
            'dept_approved' => 'اعتماد إداري',
            'fin_review'    => 'مراجعة مالية',
            'audited'       => 'تدقيق',
            'approved'      => 'معتمد مالياً',
            'posted'        => 'مقيد',
            'settled'       => 'مصروف/محصل',
            'rejected'      => 'مرفوض',
            'closed'        => 'مقفل',
        );
    }
}
// ── صندوق الاعتماد المالي (§ب/§9) ──
if (!function_exists('fin_event_flow')) {
    /** خريطة الانتقال: الحالة الحالية → [الحالة التالية, تسمية الزر, المستوى الفاعل]. */
    function fin_event_flow()
    {
        return array(
            'draft'         => array('dept_review',   'رفع للمراجعة',  'dept_accountant'),
            'dept_review'   => array('dept_approved', 'اعتماد إداري',  'dept_manager'),
            'dept_approved' => array('fin_review',    'إرسال للمالية', 'dept_manager'),
            'fin_review'    => array('audited',       'تدقيق',         'finance_reviewer'),
            'audited'       => array('approved',      'اعتماد مالي',   'finance_manager'),
        );
    }
}
if (!function_exists('fin_log_approval')) {
    /** قيد إلحاقي في سجلّ الاعتماد (لا يُمحى). */
    function fin_log_approval($conn, $company_id, $entity_id, $from, $to, $action, $level, $actor, $note = '')
    {
        fin_gate(false)->insert('fin_approvals', array(
            'entity_type' => 'financial_event', 'entity_id' => $entity_id,
            'from_state' => $from, 'to_state' => $to, 'action' => $action,
            'level' => $level, 'actor_id' => $actor, 'note' => $note,
        ));
    }
}
if (!function_exists('fin_required_level')) {
    /** المستوى المطلوب لاعتماد مبلغٍ من المصفوفة (للعرض/الحوكمة). */
    function fin_required_level($conn, $company_id, $event_type, $amount)
    {
        $amount = (float)$amount;
        // ORDER BY بتعبيرٍ (event_type='any') → scopedQuery (البوابة تعزل company_id)
        $rows = fin_gate(false)->scopedQuery(
            array('scope' => array('m' => 'fin_approval_matrix')),
            "SELECT m.required_level FROM fin_approval_matrix m
             WHERE {TENANT_SCOPE} AND m.active=1 AND (m.event_type=? OR m.event_type='any')
               AND m.min_amount <= ? AND (m.max_amount IS NULL OR m.max_amount > ?)
             ORDER BY (m.event_type='any') ASC, m.sequence DESC LIMIT 1",
            array($event_type, $amount, $amount));
        return $rows ? $rows[0]['required_level'] : null;
    }
}
if (!function_exists('fin_level_labels')) {
    function fin_level_labels()
    {
        return array('dept_accountant' => 'محاسب الإدارة', 'dept_manager' => 'مدير الإدارة',
                     'finance_reviewer' => 'المراجع المالي', 'auditor' => 'المدقق',
                     'finance_manager' => 'المدير المالي', 'executive' => 'التنفيذي', 'board' => 'المجلس');
    }
}
if (!function_exists('fin_state_tone')) {
    /** لون شارة الحالة (يوافق أصناف .badge القائمة). */
    function fin_state_tone($state)
    {
        $map = array(
            'draft' => 'secondary', 'dept_review' => 'info', 'dept_approved' => 'info',
            'fin_review' => 'primary', 'audited' => 'primary', 'approved' => 'success',
            'posted' => 'success', 'settled' => 'success', 'rejected' => 'danger', 'closed' => 'dark',
        );
        return isset($map[$state]) ? $map[$state] : 'secondary';
    }
}
if (!function_exists('fin_currencies')) {
    function fin_currencies() { return array('SDG', 'USD'); }
}

// ── المحاسبة الإدارية (§3.20/§3.21) ──
if (!function_exists('fin_center_types')) {
    function fin_center_types() { return array('cost' => 'مركز تكلفة', 'profit' => 'مركز ربح'); }
}
if (!function_exists('fin_alloc_types')) {
    function fin_alloc_types() { return array('internal_allocation' => 'تخصيص داخلي', 'intercompany_settlement' => 'تسوية بينية'); }
}
if (!function_exists('fin_center_options')) {
    function fin_center_options($conn, $is_super, $company_id, $selected = 0, $placeholder = '— بلا —')
    {
        $rows = fin_gate($is_super)->select('fin_cost_centers', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'code ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['code'] . ' — ' . $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, $placeholder);
    }
}

// ═══ إغلاق فجوات الأتمتة الأربع (§7/§9/§ب.9) ═══

// ── (فجوة 1) فرض مصفوفة الاعتماد بالمبلغ ──
if (!function_exists('fin_level_rank')) {
    /** رتبة مستويات المصفوفة تصاعديًا. */
    function fin_level_rank($level)
    {
        $r = array('dept_accountant' => 1, 'dept_manager' => 2, 'finance_manager' => 3, 'executive' => 4, 'board' => 5);
        return isset($r[$level]) ? $r[$level] : 0;
    }
}
if (!function_exists('fin_matrix_level_label')) {
    function fin_matrix_level_label($level)
    {
        $m = array('dept_accountant' => 'محاسب الإدارة', 'dept_manager' => 'مدير الإدارة',
                   'finance_manager' => 'المدير المالي', 'executive' => 'المدير التنفيذي', 'board' => 'مجلس الإدارة');
        return isset($m[$level]) ? $m[$level] : $level;
    }
}
if (!function_exists('fin_matrix_gate')) {
    /**
     * بوابة الاعتماد النهائي بالقيمة: تقرأ الشريحة من fin_approval_matrix (بمبلغ الأساس SDG).
     * المدير المالي يعتمد حتى مستواه؛ executive/board حكرٌ على المدير الأعلى (-1).
     * تعيد array(allowed, required_level).
     */
    function fin_matrix_gate($conn, $company_id, $role_id, $event_type, $base_amount)
    {
        $required = fin_required_level($conn, $company_id, $event_type, $base_amount);
        if ($required === null) { return array(true, null); } // لا شرائح معرّفة ⇒ لا تقييد إضافي
        if ((string)$role_id === '-1') { return array(true, $required); } // المدير الأعلى يغطي الكل
        $userLevel = fin_user_level($conn, $role_id);
        $userRank = $userLevel === 'finance_manager' ? fin_level_rank('finance_manager') : fin_level_rank($userLevel);
        return array($userRank >= fin_level_rank($required), $required);
    }
}

// ── (فجوة 2) توليد القيد آليًا من الحدث المعتمد ──
if (!function_exists('fin_account_by_code')) {
    /** حساب بالكود، وإلا أول حساب قابل للقيد من النوع المطلوب (احتياط). */
    function fin_account_by_code($conn, $company_id, $code, $fallback_type)
    {
        $gate = fin_gate(false);
        // البوابة تعزل الشركة وتستبعد المحذوف تلقائيًّا (fin_chart_of_accounts soft=true)
        $row = $gate->selectOne('fin_chart_of_accounts', array('columns' => array('id'), 'where' => array('code' => $code, 'is_postable' => 1)));
        if ($row) { return intval($row['id']); }
        $row = $gate->selectOne('fin_chart_of_accounts', array('columns' => array('id'), 'where' => array('account_type' => $fallback_type, 'is_postable' => 1), 'orderBy' => 'code'));
        return $row ? intval($row['id']) : 0;
    }
}
if (!function_exists('fin_cost_center_for_project')) {
    /**
     * M-38: مركزُ التكلفة المقابل لمشروعٍ — بتطابق الاسم مع دليل المراكز حرفيًّا
     * (الدليلُ لا يحمل project_id — والتطابقُ الاسميُّ اشتقاقٌ مسجَّلٌ لا تخمين).
     * يعيد id أو null إن لا تطابق (فجوةٌ معلَنة لا قيمةٌ مخترعة).
     */
    function fin_cost_center_for_project($conn, $company_id, $project_id)
    {
        $project_id = intval($project_id);
        if ($project_id <= 0) { return null; }
        $st = $conn->prepare(
            "SELECT cc.id FROM fin_cost_centers cc
               JOIN project p ON p.name = cc.name AND p.company_id = cc.company_id
              WHERE p.id = ? AND cc.company_id = ?
                AND COALESCE(cc.is_deleted,0) = 0 AND cc.active = 1
              LIMIT 1");
        $cid = intval($company_id);
        $st->bind_param('ii', $project_id, $cid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? intval($row['id']) : null;
    }
}
if (!function_exists('fin_request_thread_for_event')) {
    /**
     * M-38: خيطُ الطلب المولِّد للحدث — request_no · request_owner (لقطةُ اسم) ·
     * request_group. يعيد مصفوفةَ الثلاثة (NULL حيث لا طلبَ وراء الحدث — بحق).
     */
    function fin_request_thread_for_event($conn, $company_id, $event_id)
    {
        $out = array('request_no' => null, 'request_owner' => null, 'request_group' => null);
        $event_id = intval($event_id);
        if ($event_id <= 0) { return $out; }
        $st = $conn->prepare(
            "SELECT fr.request_no, fr.request_type, u.name owner_name, fr.requester_id
               FROM fin_requests fr LEFT JOIN users u ON u.id = fr.requester_id
              WHERE fr.event_id = ? AND fr.company_id = ? LIMIT 1");
        $cid = intval($company_id);
        $st->bind_param('ii', $event_id, $cid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            $out['request_no']    = $row['request_no'];
            $out['request_owner'] = mb_substr((string) ($row['owner_name'] ?: ('user#' . $row['requester_id'])), 0, 64);
            $out['request_group'] = $row['request_type'];
        }
        return $out;
    }
}
if (!function_exists('fin_auto_journal')) {
    /**
     * محرّك القيد الآلي (§7.2): يشتقّ المدين/الدائن من نوع الحدث وينشئ قيدًا متوازنًا
     * «مسودة» مرتبطًا بالحدث (الترحيل يبقى خطوة محكومة بالفترة والدور). يعيد entry_id أو 0.
     */
    function fin_auto_journal($conn, $company_id, $event, $user_id)
    {
        $company_id = intval($company_id);
        // N-01: العطالة قبل كل شيء — حدثٌ له قيدٌ حيٌّ قائمٌ يُعاد مرجعُه ولا
        // يولَّد ثانيةً (إعادةُ الإرسال/النقرُ المزدوج لا يضاعف القيد).
        $eid0 = intval($event['id'] ?? 0);
        if ($eid0 > 0) {
            $exist = fin_gate(false)->selectOne('fin_journal_entries', array(
                'columns' => array('id'), 'where' => array('event_id' => $eid0)));
            if ($exist) { return intval($exist['id']); }
        }
        $amount = round((float)$event['amount'] * (($event['currency'] ?? 'SDG') === 'USD' ? (float)($event['fx_rate'] ?: 600) : 1), 2);
        if ($amount <= 0) { return 0; }
        $type = $event['event_type']; $src = $event['source_module'] ?? '';
        $has_supplier = intval($event['supplier_entity_id'] ?? 0) > 0;

        // اشتقاق الحسابات (بأكواد دليلنا القياسية مع احتياط بالنوع)
        $AR = fin_account_by_code($conn, $company_id, '1200', 'asset');
        $CASH = fin_account_by_code($conn, $company_id, '1100', 'asset');
        $AP = fin_account_by_code($conn, $company_id, '2100', 'liability');
        $REV = fin_account_by_code($conn, $company_id, ($src === 'sales' ? '4200' : '4100'), 'revenue');
        $expCode = $src === 'workforce' ? '5100' : ($src === 'maintenance' ? '5300' : '5200');
        $EXP = fin_account_by_code($conn, $company_id, $expCode, 'expense');
        $SAL = fin_account_by_code($conn, $company_id, '5100', 'expense');

        switch ($type) {
            case 'revenue': case 'receivable': $debit = $AR; $credit = $REV; break;
            case 'expense': case 'payable':    $debit = $EXP; $credit = $has_supplier ? $AP : $CASH; break;
            case 'payroll':                    $debit = $SAL; $credit = $AP; break;
            case 'settlement':                 $debit = $AP;  $credit = $CASH; break;
            default:                           $debit = $EXP; $credit = $CASH;
        }
        if (!$debit || !$credit || $debit === $credit) { return 0; }

        $entry_no = fin_gen_code($conn, 'fin_journal_entries', 'FIN-JV', $company_id);
        $eid = intval($event['id']); $today = date('Y-m-d');
        $memo = 'قيد آلي من الحدث ' . $event['event_no'];
        $pid = intval($event['project_id'] ?? 0) ?: null;
        $eqid = intval($event['equipment_id'] ?? 0) ?: null;

        // ── M-38: رأسُ الحدث المالي — القيدُ الآلي يستنتج كلَّ حقوله (SPEC-01 #13) ──
        // تاريخُ الحركة = وقوعُ الحدث لا يومُ توليد القيد
        $txn_date = !empty($event['occurred_at']) ? substr((string) $event['occurred_at'], 0, 10) : $today;
        // العملةُ عملةُ القيد (المبلغُ أعلاه حُوِّل إلى SDG بمنطق المحرّك القائم)
        $jr_currency = 'SDG';
        // المعادلُ الموحّد: من سجل الأسعار النافذ يومَ الحركة — NULL معلَنٌ إن لا سعر
        require_once dirname(__DIR__) . '/includes/fx.php';
        $jr_fx = null; $jr_base = null;
        if (function_exists('ems_fx_base_currency') && ems_fx_base_currency() === $jr_currency) {
            $jr_fx = 1.0;
        } elseif (function_exists('ems_fx_rate')) {
            try { $jr_fx = ems_fx_rate($jr_currency, $txn_date); } catch (\Throwable $t) { $jr_fx = null; }
        }
        if ($jr_fx !== null) { $jr_base = round($amount * (float) $jr_fx, 2); }
        // خيطُ الطلب المولِّد (إن كان الحدثُ من طلبٍ مالي)
        $thread = fin_request_thread_for_event($conn, $company_id, $eid);
        // مركزُ التكلفة من الدليل بمطابقة اسم المشروع (NULL معلَنٌ إن لا تطابق)
        $ccid = fin_cost_center_for_project($conn, $company_id, $pid);

        // رأس + سطران متوازنان = زوجٌ ذرّي (§9) عبر البوابة
        $entry_id = 0;
        fin_gate(false)->runInTransaction(function ($g) use (&$entry_id, $entry_no, $eid, $today, $amount, $memo, $user_id, $debit, $credit, $pid, $eqid, $txn_date, $jr_currency, $jr_fx, $jr_base, $thread, $ccid) {
            $entry_id = $g->insert('fin_journal_entries', array(
                'entry_no' => $entry_no, 'event_id' => $eid, 'posting_date' => $today,
                'txn_date' => $txn_date, 'currency' => $jr_currency,
                'fx_rate' => $jr_fx, 'base_amount' => $jr_base,
                'request_no' => $thread['request_no'], 'request_owner' => $thread['request_owner'],
                'request_group' => $thread['request_group'],
                'total_debit' => $amount, 'total_credit' => $amount, 'memo' => $memo,
                'state' => 'draft', 'created_by' => $user_id,
            ));
            $g->insert('fin_journal_lines', array(
                'entry_id' => $entry_id, 'account_id' => $debit, 'debit' => $amount, 'credit' => 0,
                'project_id' => $pid, 'equipment_id' => $eqid, 'cost_center_id' => $ccid, 'memo' => $memo,
            ));
            $g->insert('fin_journal_lines', array(
                'entry_id' => $entry_id, 'account_id' => $credit, 'debit' => 0, 'credit' => $amount,
                'project_id' => $pid, 'equipment_id' => $eqid, 'cost_center_id' => $ccid, 'memo' => $memo,
            ));
        }, 'auto journal from approved event');
        return $entry_id;
    }
}

// ── (فجوة 3) تغذية «الفعلي» في الموازنة من القيود المرحّلة ──
if (!function_exists('fin_recalc_budget_actuals')) {
    /**
     * محرّك الانحراف المستمر (§7.3): يعيد احتساب actual_amount لكل بند موازنة (معتمدة/نشطة)
     * له حساب مرتبط، من مجموع القيود المرحّلة على حسابه ضمن نافذة فترته — idempotent.
     * (الأعمدة المولّدة variance/variance_pct تتحدث تلقائيًا.) يعيد عدد البنود المحدَّثة.
     */
    function fin_recalc_budget_actuals($conn, $company_id)
    {
        $n = 0;
        $gate = fin_gate(false);
        // بنود الموازنات المعتمدة/النشطة — INNER JOIN budgets → LEFT + b.id IS NOT NULL (تكافؤ)
        $lines = $gate->scopedQuery(
            array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
            "SELECT l.id, l.account_id, l.line_kind, b.fiscal_year, b.period_type, b.period_no
             FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id = l.budget_id
             WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND l.account_id IS NOT NULL
               AND b.state IN('approved','active') AND COALESCE(b.is_deleted,0)=0");
        foreach ($lines as $L) {
            $acc = intval($L['account_id']); $fy = intval($L['fiscal_year']);
            // نافذة الفترة على posting_date عبر معامِلاتٍ مربوطة
            $win = "YEAR(je.posting_date)=?"; $winParams = array($fy);
            if ($L['period_type'] === 'monthly' && $L['period_no'])   { $win .= " AND MONTH(je.posting_date)=?"; $winParams[] = intval($L['period_no']); }
            if ($L['period_type'] === 'quarterly' && $L['period_no']) { $win .= " AND QUARTER(je.posting_date)=?"; $winParams[] = intval($L['period_no']); }
            $natural = $L['line_kind'] === 'revenue' ? "jl.credit - jl.debit" : "jl.debit - jl.credit";
            // المجموع المرحَّل — INNER JOIN المرحَّل → LEFT + je.id IS NOT NULL + شروط WHERE
            $sum = $gate->scopedQuery(
                array('scope' => array('jl' => 'fin_journal_lines'), 'enrich' => array('je' => 'fin_journal_entries')),
                "SELECT COALESCE(SUM($natural),0) v FROM fin_journal_lines jl
                 LEFT JOIN fin_journal_entries je ON je.id=jl.entry_id
                 WHERE {TENANT_SCOPE} AND je.id IS NOT NULL AND je.state='posted' AND COALESCE(je.is_deleted,0)=0
                   AND jl.account_id=? AND " . $win,
                array_merge(array($acc), $winParams));
            $v = $sum ? round((float)$sum[0]['v'], 2) : 0;
            $gate->update('fin_budget_lines', array('actual_amount' => $v), array('id' => intval($L['id'])));
            $n++;
        }
        return $n;
    }
}

// ── (فجوة 4) الإشعارات ──
if (!function_exists('fin_notify')) {
    /** إشعار لمستوى دور (أو للجميع). مع منع تكرار نفس العنوان في نفس اليوم. */
    function fin_notify($conn, $company_id, $target_level, $title, $link = null)
    {
        $title = mb_substr($title, 0, 200);
        $gate = fin_gate(false);
        // منع تكرار نفس العنوان لنفس المستوى في نفس اليوم (البوابة تعزل الشركة)
        $dup = $gate->count('fin_notifications', array(
            'where' => array('target_level' => $target_level, 'title' => $title),
            'whereRaw' => 'DATE(created_at)=CURDATE()'));
        if ($dup > 0) { return; }
        $gate->insert('fin_notifications', array(
            'target_level' => $target_level, 'title' => $title, 'link' => $link,
        ));
    }
}
if (!function_exists('fin_handle_notif_read')) {
    /** يُستدعى أول الصفحة (قبل أي إخراج): تعليم إشعار مقروءًا ثم إعادة توجيه. */
    function fin_handle_notif_read($conn, $company_id, $redirect)
    {
        if (!isset($_GET['notif_read'])) { return; }
        $gate = fin_gate(false);
        if (($_GET['notif_read'] ?? '') === 'all') {
            $gate->update('fin_notifications', array('is_read' => 1), array(), '1=1'); // كل إشعارات الشركة
        } else {
            $gate->update('fin_notifications', array('is_read' => 1), array('id' => intval($_GET['notif_read'])));
        }
        header("Location: $redirect"); exit();
    }
}
if (!function_exists('fin_notifications_panel')) {
    /** لوحة الإشعارات غير المقروءة لمستوى المستخدم الحالي (تُضمَّن أعلى الشاشة). */
    function fin_notifications_panel($conn, $ctx, $self_url)
    {
        $lvl = fin_user_level($conn, $ctx['role']);
        if ($lvl === 'none') { return; }
        $opts = array('where' => array('is_read' => 0), 'orderBy' => 'id DESC', 'limit' => 8);
        if ($lvl !== 'all') {
            $opts['whereRaw'] = "(target_level=? OR target_level='all')";
            $opts['params']   = array($lvl);
        }
        $notifs = fin_gate(false)->select('fin_notifications', $opts);
        if (empty($notifs)) { return; }
        $sep = strpos($self_url, '?') === false ? '?' : '&';
        echo '<div class="card"><div class="card-body" style="padding:10px 14px">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">';
        echo '<strong><i class="fas fa-bell"></i> تنبيهاتك (' . count($notifs) . ')</strong>';
        echo '<a href="' . htmlspecialchars($self_url . $sep . 'notif_read=all') . '" class="badge badge-secondary" style="text-decoration:none">تعليم الكل مقروءا</a></div>';
        foreach ($notifs as $nf) {
            echo '<div style="display:flex;justify-content:space-between;gap:8px;padding:5px 0;border-top:1px dashed #e5e7eb">';
            echo '<span style="font-size:13px">🔔 ' . htmlspecialchars($nf['title'])
               . ($nf['link'] ? ' — <a href="' . htmlspecialchars($nf['link']) . '">فتح</a>' : '') . '</span>';
            echo '<a href="' . htmlspecialchars($self_url . $sep . 'notif_read=' . intval($nf['id'])) . '" title="مقروء" style="text-decoration:none">✓</a></div>';
        }
        echo '</div></div>';
    }
}

// ── اقتصاد الوحدة والتطابق الثلاثي (§3.23/§12) ──
if (!function_exists('fin_work_models')) {
    function fin_work_models()
    {
        return array('hour' => 'ساعة (تأجير)', 'ton' => 'طن (مقاولة)', 'meter' => 'متر (حفر)');
    }
}
if (!function_exists('fin_match_states')) {
    function fin_match_states()
    {
        return array('pending' => 'بانتظار المصادقات', 'matched' => 'متطابق ✓', 'variance' => 'فرق — يعالج', 'approved' => 'معتمد (توأمان)');
    }
}
if (!function_exists('fin_downtime_causes')) {
    function fin_downtime_causes()
    {
        return array('breakdown' => 'عطل', 'standby' => 'انتظار', 'operator_shortage' => 'نقص مشغلين',
                     'mobilization' => 'نقل وتحريك', 'client' => 'بسبب العميل');
    }
}
if (!function_exists('fin_unit_due_type')) {
    /** نوع مستحق المورد المقابل لنموذج العمل. */
    function fin_unit_due_type($work_model)
    {
        $m = array('hour' => 'hours', 'ton' => 'tons', 'meter' => 'meters');
        return isset($m[$work_model]) ? $m[$work_model] : 'other';
    }
}
if (!function_exists('fin_compute_match_state')) {
    /** التطابق الثلاثي: الثلاثة متساوية=matched · ناقصة=pending · مختلفة=variance. */
    function fin_compute_match_state($ops, $client, $supplier)
    {
        if ($client === null || $supplier === null) { return 'pending'; }
        $ops = round((float)$ops, 2); $client = round((float)$client, 2); $supplier = round((float)$supplier, 2);
        return ($ops === $client && $client === $supplier) ? 'matched' : 'variance';
    }
}

// ── المطابقة البنكية ──
if (!function_exists('fin_bank_account_options')) {
    function fin_bank_account_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = fin_gate($is_super)->select('fin_bank_accounts', array('columns' => array('id', 'name', 'bank_name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) {
            $r['label'] = $r['name'] . (($r['bank_name'] === null || $r['bank_name'] === '') ? '' : (' — ' . $r['bank_name']));
        } unset($r);
        return fin_options_from_rows($rows, $selected, '— اختر حسابا بنكيا —');
    }
}

// ── السيولة والتنبؤ (§3.18) ──
if (!function_exists('fin_horizon_types')) {
    function fin_horizon_types()
    {
        return array('daily' => 'يومي', 'weekly' => 'أسبوعي', 'monthly' => 'شهري');
    }
}
if (!function_exists('fin_cash_priorities')) {
    function fin_cash_priorities()
    {
        return array('critical' => 'حرج', 'high' => 'مرتفع', 'normal' => 'عادي');
    }
}

// ── التمويل والالتزامات (§3.19) ──
if (!function_exists('fin_facility_types')) {
    function fin_facility_types()
    {
        return array(
            'loan' => 'قرض', 'murabaha' => 'مرابحة', 'lease' => 'إيجار تمويلي',
            'bank_guarantee' => 'خطاب ضمان', 'letter_of_credit' => 'اعتماد مستندي', 'operating_finance' => 'تمويل تشغيلي',
        );
    }
}
if (!function_exists('fin_facility_purposes')) {
    function fin_facility_purposes()
    {
        return array('equipment' => 'معدات', 'supplier' => 'موردون', 'operational' => 'تشغيلي', 'general' => 'عام');
    }
}
if (!function_exists('fin_facility_states')) {
    function fin_facility_states()
    {
        return array('draft' => 'مسودة', 'approved' => 'معتمد', 'active' => 'نشط', 'settled' => 'مسدد', 'closed' => 'مقفل');
    }
}

// ── الفترات والإقفال (§3.16/§3.22) ──
if (!function_exists('fin_period_states')) {
    function fin_period_states()
    {
        return array('planned' => 'مخططة', 'open' => 'مفتوحة', 'soft_closed' => 'إقفال مرحلي',
                     'closed' => 'مقفلة', 'locked' => 'مقفلة نهائيا', 'reopened' => 'مفتوحة استثناء');
    }
}
if (!function_exists('fin_closing_steps')) {
    function fin_closing_steps()
    {
        return array(
            'reconcile_bank' => 'مطابقة البنك', 'reconcile_ar' => 'مطابقة الذمم المدينة',
            'reconcile_ap' => 'مطابقة الذمم الدائنة', 'post_accruals' => 'قيد الاستحقاقات',
            'post_depreciation' => 'قيد الإهلاك', 'settle_supplier' => 'تسوية الموردين',
            'payroll_posted' => 'ترحيل الرواتب', 'variance_reviewed' => 'مراجعة الانحرافات',
            'intercompany_settled' => 'التسويات البينية', 'reports_issued' => 'إصدار التقارير',
        );
    }
}

// ── التكاليف والربحية (§3.15) ──
if (!function_exists('fin_cost_types')) {
    function fin_cost_types()
    {
        return array(
            'equipment' => 'معدة', 'project' => 'مشروع', 'hour' => 'ساعة', 'ton' => 'طن',
            'meter' => 'متر', 'fuel' => 'وقود', 'maintenance' => 'صيانة', 'workforce' => 'قوى عاملة',
        );
    }
}

// ── المستحقات والذمم والمدفوعات (§3.11-§3.14) ──
if (!function_exists('fin_employee_options')) {
    function fin_employee_options($conn, $is_super, $company_id, $selected = 0)
    {
        // ملاحظة: جدول employees لا يحوي is_deleted (soft=false) — قراءةٌ معزولة بالشركة
        $rows = fin_gate($is_super)->select('employees', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— اختر موظفا —');
    }
}
if (!function_exists('fin_due_types')) {
    function fin_due_types()
    {
        return array(
            'hours' => 'ساعات', 'tons' => 'أطنان', 'meters' => 'أمتار', 'advance' => 'سلفة',
            'discount' => 'خصم', 'penalty' => 'غرامة', 'purchase' => 'مشتريات', 'fuel' => 'وقود',
            'parts' => 'قطع غيار', 'catering' => 'إعاشة', 'water' => 'مياه', 'transport' => 'نقل',
            'salary' => 'راتب', 'allowance' => 'بدل', 'overtime' => 'إضافي', 'deduction' => 'خصم راتب',
            'custody' => 'عهدة', 'settlement' => 'تسوية', 'end_of_service' => 'نهاية خدمة', 'other' => 'أخرى',
        );
    }
}
if (!function_exists('fin_settlement_states')) {
    function fin_settlement_states()
    {
        return array('pending' => 'معلق', 'settled' => 'مسوى', 'paid' => 'مصروف');
    }
}
if (!function_exists('fin_payment_methods')) {
    function fin_payment_methods()
    {
        return array('cash' => 'نقدي', 'bank' => 'بنكي', 'transfer' => 'تحويل', 'cheque' => 'شيك');
    }
}
if (!function_exists('fin_payment_directions')) {
    function fin_payment_directions()
    {
        return array('disbursement' => 'صرف', 'collection' => 'تحصيل');
    }
}
if (!function_exists('fin_party_types')) {
    function fin_party_types()
    {
        return array('supplier' => 'مورد', 'customer' => 'عميل', 'employee' => 'موظف', 'other' => 'أخرى');
    }
}
if (!function_exists('fin_supplier_net')) {
    /** محرك التسوية: صافي مستحق المورد = Σ(له) − Σ(عليه) على المستحقات غير المصروفة. */
    function fin_supplier_net($conn, $company_id, $supplier_id)
    {
        $supplier_id = intval($supplier_id);
        // مجاميع CASE مُعزَّلة عبر scopedQuery (البوابة تحقن company_id)
        $rows = fin_gate(false)->scopedQuery(
            array('scope' => array('d' => 'fin_dues')),
            "SELECT COALESCE(SUM(CASE WHEN d.direction='credit' THEN d.amount ELSE 0 END),0) AS credit_sum,
                    COALESCE(SUM(CASE WHEN d.direction='debit'  THEN d.amount ELSE 0 END),0) AS debit_sum
             FROM fin_dues d
             WHERE {TENANT_SCOPE} AND d.party_type='supplier' AND d.party_ref=?
               AND COALESCE(d.is_deleted,0)=0 AND d.settlement_state<>'paid'",
            array($supplier_id));
        $row = $rows ? $rows[0] : array('credit_sum' => 0, 'debit_sum' => 0);
        return (float)$row['credit_sum'] - (float)$row['debit_sum'];
    }
}

// ── الميزانيات (§3.5/§3.6) ──
if (!function_exists('fin_dept_modules')) {
    /**
     * أقسامُ الموازنة — واحدٌ لكلِّ إدارةٍ رئيسية بلا استثناء (قرار المالك 2026-07-27).
     *
     * تُبنى على `fin_source_modules()` (مفرداتُ الدفتر) ثم تُتَمَّم بالخمسِ التي
     * كانت مضمومةً إلى `general`، فصار لكلٍّ منها اسمُها ومديرُها. و`general`
     * يبقى للطلبات العابرةِ للإدارات لا بديلًا عن قسمِ أحد.
     * الترتيبُ هنا هو ترتيبُ القائمة في شاشة الموازنة.
     */
    function fin_dept_modules()
    {
        $m = fin_source_modules();
        $m['sites']     = 'المواقع';
        $m['movement']  = 'الحركة والتشغيل';
        $m['transport'] = 'النقل والترحيل';
        $m['tickets']   = 'البلاغات';
        $m['admin']     = 'الإدارة والصلاحيات';
        $m['general']   = 'عام';
        return $m;
    }
}
if (!function_exists('fin_period_types')) {
    function fin_period_types()
    {
        return array('annual' => 'سنوية', 'quarterly' => 'ربعية', 'monthly' => 'شهرية');
    }
}
if (!function_exists('fin_budget_states')) {
    function fin_budget_states()
    {
        // «معادة» أُضيفت 2026-07-27 مع دورة الرفع — الثلاثيةُ الموحّدة تقتضيها
        // (الدستور §4.3): إجازةٌ · إعادةٌ بسبب · وما بينهما انتظار.
        return array('draft' => 'مسودة', 'submitted' => 'مقدمة', 'returned' => 'معادة',
                     'approved' => 'معتمدة', 'active' => 'نشطة', 'closed' => 'مقفلة');
    }
}
if (!function_exists('fin_budget_categories')) {
    function fin_budget_categories()
    {
        return array(
            'salaries' => 'رواتب', 'fuel' => 'وقود', 'maintenance' => 'صيانة',
            'procurement' => 'مشتريات', 'catering' => 'إعاشة', 'transport' => 'نقل',
            'operational_need' => 'احتياج تشغيلي', 'capacity_need' => 'قدرة تشغيلية',
            'revenue' => 'إيراد', 'other' => 'أخرى',
        );
    }
}

// ── الوحدات المالية والمحاسبون الموزّعون (§3.3/§3.4) ──
if (!function_exists('fin_unit_options')) {
    function fin_unit_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = fin_gate($is_super)->select('fin_units', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'code ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['code'] . ' — ' . $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— اختر وحدة —');
    }
}

// ── دليل الحسابات (§3.2) ──
if (!function_exists('fin_account_types')) {
    function fin_account_types()
    {
        return array(
            'asset'     => 'أصول',
            'liability' => 'خصوم',
            'equity'    => 'حقوق ملكية',
            'revenue'   => 'إيراد',
            'expense'   => 'مصروف',
        );
    }
}
if (!function_exists('fin_account_type_tone')) {
    function fin_account_type_tone($t)
    {
        $map = array('asset' => 'primary', 'liability' => 'danger', 'equity' => 'dark',
                     'revenue' => 'success', 'expense' => 'warn');
        return isset($map[$t]) ? $map[$t] : 'secondary';
    }
}
if (!function_exists('fin_postable_account_options')) {
    /** الحسابات التي تقبل القيد المباشر (is_postable=1). */
    function fin_postable_account_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = fin_gate($is_super)->select('fin_chart_of_accounts', array('columns' => array('id', 'code', 'name'), 'whereRaw' => 'is_postable=1', 'orderBy' => 'code ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['code'] . ' — ' . $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— اختر حسابا —');
    }
}
if (!function_exists('fin_cost_center_options')) {
    /** M-38: مراكزُ التكلفة الفعّالة منسدلةً من الدليل (بديلُ النص الحر). */
    function fin_cost_center_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = fin_gate($is_super)->select('fin_cost_centers', array('columns' => array('id', 'code', 'name'), 'whereRaw' => 'active=1', 'orderBy' => 'code ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['code'] . ' — ' . $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— بلا مركز —');
    }
}
if (!function_exists('fin_currency_options')) {
    /** M-38: العملات المسجَّلة الفعّالة منسدلةً (fin_currencies — F-04). */
    function fin_currency_options($conn, $is_super, $company_id, $selected = '')
    {
        $rows = fin_gate($is_super)->select('fin_currencies', array('columns' => array('code', 'name_ar', 'is_base'), 'whereRaw' => 'active=1', 'orderBy' => 'sort_order ASC'));
        $out = '';
        foreach ($rows as $r) {
            $sel = ((string) $selected === (string) $r['code']) ? ' selected' : '';
            $out .= "<option value='" . htmlspecialchars($r['code']) . "'{$sel}>"
                  . htmlspecialchars($r['code'] . ' — ' . $r['name_ar'] . ($r['is_base'] ? ' (الأساس)' : ''))
                  . "</option>";
        }
        return $out;
    }
}
if (!function_exists('fin_account_parent_options')) {
    /** حسابات هذه الشركة كآباء محتملين (fin_chart_of_accounts). */
    function fin_account_parent_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = fin_gate($is_super)->select('fin_chart_of_accounts', array('columns' => array('id', 'code', 'name'), 'orderBy' => 'code ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['code'] . ' — ' . $r['name']; } unset($r);
        return fin_options_from_rows($rows, $selected, '— بلا أب (جذر) —');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// D02 م1-③: بوابة التحويل المالي — طابور أيام الدوام المنتظِرة ختمَ المالية
// ───────────────────────────────────────────────────────────────────────────
// دستور الوحدة التشغيلية §5: «لا يجوز لأي شاشةٍ أو معالجٍ أن يُنشئ استحقاقًا من
// وحدةٍ لم تُعتمد ماليًّا — كل إدخالٍ ماليٍّ يمرّ عبر محوِّل الوحدة حصرًا».
// الطابور استنتاجٌ خالص: لا عمودَ حالةٍ جديد ولا جدول — «اكتمل اعتماده تشغيليًّا»
// من timesheet_approvals، و«لم يُحوَّل بعد» من غياب رابطٍ في fin_event_links
// (دفتر عطالة المحرّك نفسه — مصدرٌ واحدٌ للحقيقة لا علامةٌ موازية تتناقض معه).
// ═══════════════════════════════════════════════════════════════════════════

if (!defined('FIN_UNIT_FINAL_LEVEL')) {
    /** آخر مراحل سلسلة الاعتماد التشغيلية (role_level_map في hours_approval_handler). */
    define('FIN_UNIT_FINAL_LEVEL', 4);
}

if (!function_exists('fin_convert_gate_on')) {
    /** هل بوابة التحويل نافذة؟ off = السلوك القديم (توليدٌ تلقائيٌّ عند الاعتماد الرابع). */
    function fin_convert_gate_on()
    {
        return function_exists('ems_env')
            && strtolower((string) ems_env('EMS_UNIT_CONVERT_GATE', 'off')) === 'on';
    }
}

if (!function_exists('fin_conversion_queue')) {
    /**
     * أيام الدوام المكتمِلة اعتمادًا تشغيليًّا والمنتظِرة التحويل المالي.
     * قراءةٌ خالصة عبر البوابة (عزل الشركة مفروضٌ بـ{TENANT_SCOPE}).
     *
     * @param array $filters project_id · period (Y-m) · limit
     * @return array صفوفٌ بمعرّف الدوام وتاريخه ومشروعه ومعدته ومن اعتمده أخيرًا
     */
    function fin_conversion_queue($conn, $is_super, $filters = array())
    {
        // ═══ E-01×E-02 (قرار المالك 2026-08-05): الطابور يتغذى من **السلسلة** ═══
        // «السلسلة هي المسار وtimesheet مرآة»: الأهلية = state='sales_approved'
        // في unit_entries لا اكتمال timesheet_approvals. المرآة تبقى للتسعير
        // التعاقدي وأعمدة العرض، والجسر sync_uuid='ts:{id}'. الصف بلا جسرٍ
        // يظهر معلَنًا (id فارغ · متعذّر) — فطابورٌ يُخفي الدَّينَ أخطرُ من طوله.
        $where = array(); $params = array();
        if (!empty($filters['project_id'])) { $where[] = 'ue.project_id = ?'; $params[] = intval($filters['project_id']); }
        if (!empty($filters['period']))     { $where[] = "DATE_FORMAT(ue.entry_date, '%Y-%m') = ?"; $params[] = strval($filters['period']); }
        // فحصُ أهليةِ يومٍ بعينه: نفس شروط الطابور بلا استثناء — فالتحويل يمرّ
        // بالبوابة نفسها التي بنت العرض (لا مسارَ جانبيٍّ يثق بمعرّفٍ مُرسَل).
        if (!empty($filters['only_id']))    { $where[] = 't.id = ?'; $params[] = intval($filters['only_id']); }
        $extra = $where ? (' AND ' . implode(' AND ', $where)) : '';
        $limit = isset($filters['limit']) ? max(1, intval($filters['limit'])) : 200;

        // ⚠️ الجسر يُحل رقميًّا (CAST) لا نصيًّا — خلطُ الترتيبين general/unicode
        //    على sync_uuid يفجّر Illegal mix of collations.
        $sql = "SELECT t.id, ue.id AS ue_id, ue.entry_no, ue.entry_date AS work_date,
                       t.executed_hours, t.tons_count, t.meters_count,
                       t.operator_hours, ue.project_id, ue.equipment_id,
                       p.name AS project_name, e.name AS equipment_name, e.code AS equipment_code,
                       ta.approved_at, ta.approved_by, ta.approved_by_name
                  FROM unit_entries ue
                  LEFT JOIN timesheet t
                         ON ue.sync_uuid LIKE 'ts:%'
                        AND t.id = CAST(SUBSTRING(ue.sync_uuid, 4) AS UNSIGNED)
                  LEFT JOIN timesheet_approvals ta
                         ON ta.timesheet_id = t.id AND ta.approval_level = " . FIN_UNIT_FINAL_LEVEL . "
                        AND ta.status = 1
                  LEFT JOIN project p ON p.id = ue.project_id
                  LEFT JOIN equipments e ON e.id = ue.equipment_id
                 WHERE {TENANT_SCOPE}
                   -- التحويل على السكّة = **حالة السلسلة** لا روابط المال: صفٌّ
                   -- sales_approved له مالٌ سابق (انحراف سكّتين) يبقى في الطابور
                   -- ليُتبنّى ويُختم — والخدمة لا تكرر مالًا (عطالة fin_event_links).
                   AND ue.state = 'sales_approved'
                       " . $extra . "
                 ORDER BY ue.entry_date ASC, ue.id ASC
                 LIMIT " . $limit;

        try {
            // ⚠️ عقدا البوابة في scopedQuery:
            //   ① كل جدولٍ مستأجرٍ يظهر في FROM/JOIN يجب إعلانه — بما فيه ما
            //      داخل الاستعلامات الفرعية (fin_event_links في NOT EXISTS).
            //   ② جداول الإثراء تُربط LEFT JOIN حصرًا — والنطاق هنا السلسلة
            //      وحدَها (هي مصدرُ الحقيقة)، والمرآةُ والاعتماداتُ إثراءُ عرض.
            return fin_gate($is_super)->scopedQuery(
                array('scope' => array('ue' => 'unit_entries'),
                      'enrich' => array('t' => 'timesheet', 'ta' => 'timesheet_approvals',
                                        'p' => 'project', 'e' => 'equipments')),
                $sql, $params);
        } catch (\Throwable $x) {
            // ⚠️ فشلُ الطابور يُعلَن ولا يُبتلع: طابورٌ فارغٌ كذبًا يعني «لا شيء
            //    ينتظر التحويل» بينما المال محتجَز — أخطر من رسالة خطأ.
            error_log('fin_conversion_queue: ' . $x->getMessage());
            if (function_exists('log_security_event')) {
                log_security_event('CONVERT_QUEUE_FAILED', substr($x->getMessage(), 0, 300));
            }
            $GLOBALS['fin_queue_error'] = $x->getMessage();
            return array();
        }
    }
}

if (!function_exists('fin_queue_pricing')) {
    /**
     * تسعير صفوف الطابور للعرض المسبق — «يرى المدير المالي الأثر قبل أن يقع».
     * يستدعي مترجم المروحة نفسه (مصدرٌ واحدٌ للحساب: ما يُعرض هو ما سيُولَّد).
     * ⚠️ الكلفة: استعلامان لكل صف — لذا يُستدعى للصفحة المعروضة وحدها.
     */
    function fin_queue_pricing($conn, array $rows)
    {
        require_once __DIR__ . '/../app/Services/EffectFanout.php';
        $out = array();
        foreach ($rows as $r) {
            $id = intval($r['id']);
            if ($id <= 0) {
                // صفُّ سلسلةٍ بلا جسرِ دوامٍ — دَينٌ معلَنٌ لا صفٌّ مُسقَط
                $out[0] = array('ready' => false,
                    'reason' => 'بلا جسر سجل دوام — شغل tools/e02_bridge_backfill.php ثم أعد التحميل',
                    'qty' => null, 'unit' => null, 'revenue' => null, 'due' => null);
                continue;
            }
            try { $ctx = \App\Services\EffectFanout::resolveTimesheet($conn, $id); }
            catch (\Throwable $x) { $ctx = null; }
            if ($ctx === null) {
                $out[$id] = array('ready' => false, 'reason' => 'تعذرت قراءة صف الدوام',
                                  'qty' => null, 'unit' => null, 'revenue' => null, 'due' => null);
                continue;
            }
            // ⚠️ كلُّ طرفٍ بكميته ووحدته (D02 §2.6): المعاينة تُحسب كما يُحسب التوليد
            // تمامًا — وإلا رأى المدير المالي رقمًا غير الذي سيقع.
            $revenue = $ctx['client']['ok']   ? round($ctx['client']['qty']   * $ctx['client']['price'], 2)   : null;
            $due     = $ctx['supplier']['ok'] ? round($ctx['supplier']['qty'] * $ctx['supplier']['price'], 2) : null;
            // جاهزٌ للتحويل = أثرٌ واحدٌ حقيقيٌّ على الأقل (لا تحويلَ لصفٍّ كلُّه متعذّر)
            $ready = ($revenue !== null || $due !== null);
            $reasons = array();
            if (!$ctx['client']['ok'])   { $reasons[] = 'الإيراد: ' . $ctx['client']['reason']; }
            if (!$ctx['supplier']['ok']) { $reasons[] = 'المستحق والتكلفة: ' . $ctx['supplier']['reason']; }
            $out[$id] = array(
                'ready' => $ready,
                'reason' => implode(' · ', $reasons),
                'qty' => $ctx['client']['qty'], 'unit' => $ctx['client']['unit'],
                'revenue' => $revenue, 'revenue_cur' => $ctx['client']['currency'],
                'due' => $due, 'due_cur' => $ctx['supplier']['currency'],
                // كمية المورد ووحدته منفصلتان — قد تخالفان العميل في الواقعة نفسها
                'sup_qty' => $ctx['supplier']['qty'], 'sup_unit' => $ctx['supplier']['unit'],
            );
        }
        return $out;
    }
}

if (!function_exists('fin_unit_label_ar')) {
    /** تسمية وحدة القياس بالعربية. */
    function fin_unit_label_ar($u)
    {
        $m = array('hour' => 'ساعة', 'ton' => 'طن', 'meter' => 'متر');
        return isset($m[$u]) ? $m[$u] : ($u === null ? '—' : $u);
    }
}

if (!function_exists('fin_budget_dept_scope')) {
    /**
     * أقسامُ الموازنة التي يملكها الدور (UX-02 §5 دورة ⑥ · قرار المالك 2026-07-27).
     * ─────────────────────────────────────────────────────────────────────
     * **الخريطةُ من جدول توجيه الطلبات** (`fin_request_routing.manager_role_id`)
     * لا من نصٍّ مكتوب: أقسامُه هي نفسُها الأحدَ عشرَ في `fin_budgets.dept_module`
     * بالحرف — فمصدرٌ واحدٌ يخدم بوابتَي الطلب والموازنة ولا ينحرفان.
     *
     * **مديرُ الإدارة وحده** يملك قسمَه (قرار المالك) — لا منشئو طلباتها.
     *
     * **الرؤيةُ تُفصل عن الملكية**: أدوارُ المالية كلُّها (17–22) وظيفةُ رقابةٍ لا
     * إدارةٌ صاحبةُ موازنة — فترى الكلَّ. وحصرُها في أقسامها كان يُعمي المدقّقَ
     * والمحاسبَ وأمينَ الخزينة والقارئ عن كل موازنةٍ في الشركة (انحدارٌ رُصد
     * وأُصلح 2026-07-27). والرفعُ والإجازةُ يبقيان محصورَين كما هما:
     * `fin_budget_can_submit` بمالك القسم، و`fin_budget_is_approver` بالدور 19.
     *
     * @return array|null قائمةُ أقسام، أو null = كلُّ الأقسام (المالية/السوبر)
     */
    function fin_budget_dept_scope($role, $is_super = false)
    {
        $role = strval($role);
        if ($is_super || $role === '-1') { return null; }
        // أدوارُ الإدارة المالية: رؤيةٌ كاملةٌ رقابةً
        if (in_array($role, array('17', '18', '19', '20', '21', '22'), true)) { return null; }
        $out = array();
        try {
            $rows = fin_gate(false)->select('fin_request_routing', array(
                'columns' => array('source_module', 'manager_role_id'),
            ));
            foreach ($rows as $r) {
                if (strval($r['manager_role_id']) === $role) { $out[strval($r['source_module'])] = true; }
            }
        } catch (\Throwable $t) { error_log('fin budget dept scope: ' . $t->getMessage()); }
        return array_keys($out);
    }
}

if (!function_exists('fin_budget_owned_depts')) {
    /**
     * الأقسامُ التي **يملكها** الدورُ رفعًا — من `fin_request_routing.manager_role_id`.
     * ─────────────────────────────────────────────────────────────────────
     * تختلف عن `fin_budget_dept_scope` (الرؤية): الماليةُ ترى الكلَّ رقابةً
     * وتملك أقسامَها الثلاثة وحدها (الإيرادات · الخزينة · العام) — فترفعها
     * ولا ترفع موازنةَ الصيانة. والمُجيزُ (19) لا يملك قسمًا فلا يرفع شيئًا،
     * وبذلك يبقى فصلُ اليدين بنيويًّا.
     */
    function fin_budget_owned_depts($role)
    {
        $role = strval($role);
        if ($role === strval(EMS_ROLE_FIN_DEPT_MGR)) { return array(); }   // المُجيزُ لا يملك
        $out = array();
        try {
            $rows = fin_gate(false)->select('fin_request_routing', array(
                'columns' => array('source_module', 'manager_role_id'),
            ));
            foreach ($rows as $r) {
                if (strval($r['manager_role_id']) === $role) { $out[strval($r['source_module'])] = true; }
            }
        } catch (\Throwable $t) { error_log('fin budget owned depts: ' . $t->getMessage()); }
        return array_keys($out);
    }
}

if (!function_exists('fin_budget_can_submit')) {
    /** هل يملك الدورُ رفعَ موازنة هذا القسم؟ (مديرُ القسم وحده — والمُجيزُ لا يرفع) */
    function fin_budget_can_submit($role, $dept, $is_super = false)
    {
        if ($is_super || strval($role) === '-1') { return true; }
        return in_array(strval($dept), fin_budget_owned_depts($role), true);
    }
}

if (!function_exists('fin_budget_self_owned')) {
    /**
     * هل الموازنةُ لقسمٍ يديره هذا الدورُ نفسُه؟ — فلا يُجيزها ولا يُعيدها.
     * ─────────────────────────────────────────────────────────────────────
     * حارسُ «مَن يُعدّ لا يعتمد» بعد أن اتّسع بابُ الإجازة إلى الإدارة المالية
     * كلِّها (2026-07-28): المديرُ الماليُّ يملك الإيراداتِ والخزينةَ والعام،
     * فلو أجازها لعاد الخللُ الذي أُغلق. ويبقى مديرُ الإدارة المالية (19) —
     * وهو لا يملك قسمًا أصلًا — مُجيزَ هذه الثلاثة.
     *
     * السوبر مستثنًى: دورُ تشغيلٍ لا طرفٌ في الدورة.
     */
    function fin_budget_self_owned($role, $dept, $is_super = false)
    {
        if ($is_super || strval($role) === '-1') { return false; }
        return in_array(strval($dept), fin_budget_owned_depts($role), true);
    }
}

if (!function_exists('fin_budget_approver_roles')) {
    /**
     * أدوارُ الإدارة المالية التي تُجيز الموازنات: رئيسُها ومعاونوه.
     * ─────────────────────────────────────────────────────────────────────
     * تُشتَقّ **بالتبعية** من `roles.parent_role_id` لا من قائمةٍ مكتوبة (قاعدة
     * المالك: «التبعيةُ تحدد القائمة والصلاحيةُ ترشّح») — فمعاونٌ يُضاف تحت
     * الإدارة المالية غدًا يدخل بلا تعديل كود، ومعاونٌ يُنقل يخرج بلا سهو.
     *
     * والترشيحُ بعدها لصلاحية الشاشة: مَن لا `can_edit` له لا يرى زرًّا أصلًا
     * (فالقارئُ الماليُّ داخلُ الأسرة ولا يفعل — وهذا صحيحٌ بتعريف دوره).
     */
    function fin_budget_approver_roles()
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        /* ══ **عيبٌ مقيسٌ: الاشتقاقُ كان من دورٍ لا من أسرة.**
             كانت تشتقُّ أبناءَ `EMS_ROLE_CFO` — وقيمتُه `'32'` («المدير المالي
             ط٢» من `update0013`)، **وهو دورٌ ورقةٌ لا أبٌ لأحد**. فالاستعلامُ
             يعود فارغًا دائمًا والقائمةُ تبقى عنصرًا واحدًا (`'32'`)، فلا
             الدورُ 19 (مديرُ الإدارةِ المالية) يُجيز ولا معاونوه — أي أن
             «التوسعةَ» بقرارِ المالك 2026-07-28 **لم تنفُذ قطُّ**.
             وأسرةُ المالية في `roles` أبوها **`17` «إدارة المالية»**
             (level 1 · parent NULL) وأبناؤها 18·19·20·21·22.
             ورأسُ `includes/roles.php` يشرح اللبسَ بنفسه: «الاسمُ القديمُ
             `EMS_ROLE_CFO` كان يسمّي الإدارةَ مديرًا — وهو اللبسُ نفسُه»؛
             فحين أُعيد ترقيمُه إلى 32 بقيت هذه الدالةُ على الاسمِ القديمِ
             بمعناه الجديد.
           ⇒ الاشتقاقُ من `EMS_ROLE_FINANCE_DEPT` = الأسرةُ لا الورقة. */
        $cache = array(strval(EMS_ROLE_FIN_DEPT_MGR));
        try {
            // `roles` جدولٌ عالميٌّ (T_GLOBAL) — قراءةٌ بلا نطاق
            $rows = ems_tenant_db()->select('roles', array(
                'columns' => array('id'),
                'where'   => array('parent_role_id' => intval(EMS_ROLE_FINANCE_DEPT)),
            ));
            foreach ($rows as $r) { $cache[] = strval($r['id']); }
            /* والأبُ نفسُه (إدارةُ المالية) داخلُ الأسرةِ لا خارجَها */
            $cache[] = strval(EMS_ROLE_FINANCE_DEPT);
            $cache = array_values(array_unique($cache));
        } catch (\Throwable $t) {
            error_log('fin budget approver roles: ' . $t->getMessage());
            $cache = array_map('strval', EMS_ROLES_FINANCE);   // احتياطٌ ثابتٌ لا يفتح بابًا زائدًا
        }
        return $cache;
    }
}

if (!function_exists('fin_budget_is_approver')) {
    /**
     * هل يملك الدورُ إجازةَ الموازنات؟ — الإدارةُ المالية: رئيسُها ومعاونوه
     * (قرار المالك 2026-07-28، توسعةُ ما كان محصورًا في الدور 19 وحده).
     *
     * وحدَّان يبقيان قائمَين ولا تلغيهما التوسعة:
     *   ① صلاحيةُ الشاشة (`can_edit`) ترشّح مَن يفعل فعلًا — الشاشةُ تفحصها.
     *   ② لا يُجيز أحدٌ موازنةَ قسمٍ يملكه — `fin_budget_transition` تحرسه،
     *      وإلا لأجاز المديرُ الماليُّ موازناتِ الإيرادات والخزينة والعام
     *      وهي أقسامُه هو (وتلك ثغرةُ «مَن يُعدّ يعتمد» بعينها).
     */
    function fin_budget_is_approver($role, $is_super = false)
    {
        if ($is_super || strval($role) === '-1') { return true; }
        return in_array(strval($role), fin_budget_approver_roles(), true);
    }
}

if (!function_exists('fin_budget_editable_states')) {
    /** الحالتان اللتان تُحرَّر فيهما البنود — وما سواهما مقفلٌ: لا تتغيّر أرقامٌ بعد الرفع. */
    function fin_budget_editable_states() { return array('draft', 'returned'); }
}

if (!function_exists('fin_budget_transition')) {
    /**
     * انتقالاتُ دورة الموازنة الثلاثة (الدستور §4.3: الثلاثيةُ الموحّدة).
     *   submit  : draft|returned → submitted  · مديرُ القسم
     *   approve : submitted      → approved   · المدير المالي
     *   return  : submitted      → returned   · المدير المالي بسببٍ إلزامي
     *
     * ولا يعتمد المرءُ ما رفَعه: المُجيزُ دورٌ **لا يملك رفعًا أصلًا**
     * (fin_budget_can_submit تُعيد له false) — ففصلُ اليدين بنيويٌّ لا بفحصٍ لاحق.
     *
     * @return array ('status' => ok|denied|state|failed, 'reason')
     */
    function fin_budget_transition($conn, $budget_id, $action, $role, $user_id, $is_super = false, $reason = '')
    {
        $out = array('status' => 'failed', 'reason' => '');
        $budget_id = intval($budget_id);
        if ($budget_id <= 0) { $out['reason'] = 'معرف غير صالح'; return $out; }

        $gate = fin_gate($is_super);
        try { $b = $gate->selectOne('fin_budgets', array('where' => array('id' => $budget_id))); }
        catch (\Throwable $t) { error_log('budget transition fetch: ' . $t->getMessage()); return $out; }
        if (!$b) { $out['reason'] = 'الموازنة غير موجودة'; return $out; }

        $state = strval($b['state']);
        $dept  = strval($b['dept_module']);
        $now   = date('Y-m-d H:i:s');
        $data  = array();

        if ($action === 'submit') {
            if (!fin_budget_can_submit($role, $dept, $is_super)) {
                $out['status'] = 'denied'; $out['reason'] = 'رفع موازنة القسم لمديره وحده'; return $out;
            }
            if (!in_array($state, fin_budget_editable_states(), true)) {
                $out['status'] = 'state'; $out['reason'] = 'لا ترفع إلا مسودة أو معادة'; return $out;
            }
            $data = array('state' => 'submitted', 'submitted_by' => intval($user_id), 'submitted_at' => $now);
        } elseif ($action === 'approve') {
            if (!fin_budget_is_approver($role, $is_super)) {
                $out['status'] = 'denied'; $out['reason'] = 'الإجازة للإدارة المالية'; return $out;
            }
            if (fin_budget_self_owned($role, $dept, $is_super)) {
                $out['status'] = 'denied'; $out['reason'] = 'لا تجيز موازنة قسم تديره'; return $out;
            }
            if ($state !== 'submitted') {
                $out['status'] = 'state'; $out['reason'] = 'لا تجاز إلا موازنة مرفوعة'; return $out;
            }
            /* P1-B — الفصلُ القائمُ هنا **إداريٌّ** (`fin_budget_self_owned`: لا
               تُجيز موازنةَ قسمٍ تديره) وهو لا يمنع **الشخصَ** من إجازةِ ما رفعه
               هو في قسمٍ آخر. والحكمُ «من أنشأ لا يعتمد» على الشخصِ لا القسم. */
            require_once __DIR__ . '/../includes/self_approval_guard.php';
            $__sa = ems_assert_not_self_approval($conn, 'fin_budgets', 'id', $budget_id,
                'موازنة #' . $budget_id, intval($b['company_id'] ?? 0));
            if ($__sa !== null) { $out['status'] = 'denied'; $out['reason'] = $__sa['reason']; return $out; }
            $data = array('state' => 'approved', 'approved_by' => intval($user_id), 'approved_at' => $now);
        } elseif ($action === 'return') {
            if (!fin_budget_is_approver($role, $is_super)) {
                $out['status'] = 'denied'; $out['reason'] = 'الإعادة للإدارة المالية'; return $out;
            }
            if (fin_budget_self_owned($role, $dept, $is_super)) {
                $out['status'] = 'denied'; $out['reason'] = 'لا تعيد موازنة قسم تديره'; return $out;
            }
            if ($state !== 'submitted') {
                $out['status'] = 'state'; $out['reason'] = 'لا تعاد إلا موازنة مرفوعة'; return $out;
            }
            $reason = trim((string) $reason);
            if ($reason === '') {
                $out['status'] = 'denied'; $out['reason'] = 'سبب الإعادة إلزامي'; return $out;
            }
            $data = array('state' => 'returned', 'returned_by' => intval($user_id),
                          'returned_at' => $now, 'return_reason' => mb_substr($reason, 0, 255));
        } else {
            $out['reason'] = 'إجراء غير معروف'; return $out;
        }

        try { $gate->update('fin_budgets', $data, array('id' => $budget_id)); }
        catch (\Throwable $t) { error_log('budget transition save: ' . $t->getMessage()); return $out; }

        // الإشعارُ بسببه وقفزته (الدستور §9: «كل تنبيهٍ يحمل سببَه وزرَّ الانتقال»)
        try {
            $depts = fin_dept_modules();
            $label = isset($depts[$dept]) ? $depts[$dept] : $dept;
            $no    = strval($b['budget_no']);
            if ($action === 'submit') {
                fin_notify($conn, intval($b['company_id']), 'finance_manager',
                    'موازنة مرفوعة تنتظر إجازتك: ' . $label . ' — ' . $no, 'budget_form_fin.php');
            } elseif ($action === 'approve') {
                fin_notify($conn, intval($b['company_id']), 'department',
                    'أجيزت موازنة ' . $label . ' — ' . $no, 'budget_form_fin.php');
            } else {
                fin_notify($conn, intval($b['company_id']), 'department',
                    'أعيدت موازنة ' . $label . ' لاستكمال: ' . mb_substr($reason, 0, 80), 'budget_form_fin.php');
            }
        } catch (\Throwable $t) { /* الإشعارُ لا يُسقط الانتقال */ }

        $out['status'] = 'ok';
        return $out;
    }
}
