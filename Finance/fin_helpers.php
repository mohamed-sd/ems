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
    function fin_project_scope($conn, $ctx)
    {
        if ($ctx['is_super'] || !in_array(strval($ctx['role']), array('5', '6'), true)) {
            return null;
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
if (!function_exists('fin_party_scope')) {
    /**
     * نطاق نوع الطرف للأدوار التشغيلية الممنوحة عرض الذمم/المدفوعات
     * (قرار 2026-07-17 — كل إدارةٍ ترى بيانات أطرافها هي):
     *   الموارد البشرية (4)            ← الموظفون حصرًا (الرواتب شأنها)
     *   الموردون (2/8) والمشتريات (16) ← الموردون حصرًا (لا رواتب موظفين)
     * عائلة المالية والسوبر بلا نطاق (null = الكل).
     *
     * @return string|null 'employee' · 'supplier' · null
     */
    function fin_party_scope($ctx)
    {
        if (!empty($ctx['is_super'])) {
            return null;
        }
        $r = strval($ctx['role']);
        if ($r === '4') {
            return 'employee';
        }
        if (in_array($r, array('2', '8', '16'), true)) {
            return 'supplier';
        }
        return null;
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
                   'finance_reviewer' => 'المراجع/المدقّق', 'finance_manager' => 'المدير المالي',
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
            'posted'        => 'مقيَّد',
            'settled'       => 'مصروف/محصّل',
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
                     'finance_reviewer' => 'المراجع المالي', 'auditor' => 'المدقّق',
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
if (!function_exists('fin_auto_journal')) {
    /**
     * محرّك القيد الآلي (§7.2): يشتقّ المدين/الدائن من نوع الحدث وينشئ قيدًا متوازنًا
     * «مسودة» مرتبطًا بالحدث (الترحيل يبقى خطوة محكومة بالفترة والدور). يعيد entry_id أو 0.
     */
    function fin_auto_journal($conn, $company_id, $event, $user_id)
    {
        $company_id = intval($company_id);
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
        // رأس + سطران متوازنان = زوجٌ ذرّي (§9) عبر البوابة
        $entry_id = 0;
        fin_gate(false)->runInTransaction(function ($g) use (&$entry_id, $entry_no, $eid, $today, $amount, $memo, $user_id, $debit, $credit, $pid, $eqid) {
            $entry_id = $g->insert('fin_journal_entries', array(
                'entry_no' => $entry_no, 'event_id' => $eid, 'posting_date' => $today,
                'total_debit' => $amount, 'total_credit' => $amount, 'memo' => $memo,
                'state' => 'draft', 'created_by' => $user_id,
            ));
            $g->insert('fin_journal_lines', array(
                'entry_id' => $entry_id, 'account_id' => $debit, 'debit' => $amount, 'credit' => 0,
                'project_id' => $pid, 'equipment_id' => $eqid, 'memo' => $memo,
            ));
            $g->insert('fin_journal_lines', array(
                'entry_id' => $entry_id, 'account_id' => $credit, 'debit' => 0, 'credit' => $amount,
                'project_id' => $pid, 'equipment_id' => $eqid, 'memo' => $memo,
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
        echo '<a href="' . htmlspecialchars($self_url . $sep . 'notif_read=all') . '" class="badge badge-secondary" style="text-decoration:none">تعليم الكل مقروءًا</a></div>';
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
        return array('pending' => 'بانتظار المصادقات', 'matched' => 'متطابق ✓', 'variance' => 'فرق — يُعالَج', 'approved' => 'معتمد (توأمان)');
    }
}
if (!function_exists('fin_downtime_causes')) {
    function fin_downtime_causes()
    {
        return array('breakdown' => 'عطل', 'standby' => 'انتظار', 'operator_shortage' => 'نقص مشغّلين',
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
        return fin_options_from_rows($rows, $selected, '— اختر حساباً بنكياً —');
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
        return array('draft' => 'مسودة', 'approved' => 'معتمد', 'active' => 'نشط', 'settled' => 'مُسدَّد', 'closed' => 'مقفل');
    }
}

// ── الفترات والإقفال (§3.16/§3.22) ──
if (!function_exists('fin_period_states')) {
    function fin_period_states()
    {
        return array('planned' => 'مخطّطة', 'open' => 'مفتوحة', 'soft_closed' => 'إقفال مرحلي',
                     'closed' => 'مقفلة', 'locked' => 'مقفلة نهائياً', 'reopened' => 'مفتوحة استثناءً');
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
        return fin_options_from_rows($rows, $selected, '— اختر موظفاً —');
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
        return array('pending' => 'معلّق', 'settled' => 'مُسوّى', 'paid' => 'مصروف');
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
    function fin_dept_modules()
    {
        $m = fin_source_modules();
        $m['general'] = 'عام';
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
        return array('draft' => 'مسودة', 'submitted' => 'مقدَّمة', 'approved' => 'معتمدة',
                     'active' => 'نشطة', 'closed' => 'مقفلة');
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
        return fin_options_from_rows($rows, $selected, '— اختر حساباً —');
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
