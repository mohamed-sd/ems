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
        if ((string)$role_id === '-1') { return 'all'; }
        $rid = intval($role_id);
        $r = mysqli_query($conn, "SELECT name FROM roles WHERE id = $rid LIMIT 1");
        $name = ($r && ($x = mysqli_fetch_assoc($r))) ? trim($x['name']) : '';
        $map = fin_role_level_map();
        return isset($map[$name]) ? $map[$name] : 'none';
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
        $company_id = intval($company_id);
        $date = mysqli_real_escape_string($conn, $date);
        $sql = "SELECT posting_allowed, state FROM fin_financial_periods
                WHERE company_id = $company_id AND period_type = 'month'
                  AND '$date' BETWEEN start_date AND end_date
                ORDER BY id DESC LIMIT 1";
        $r = mysqli_query($conn, $sql);
        if (!$r || mysqli_num_rows($r) === 0) { return true; } // لا فترة معرّفة ⇒ يُسمح (توافق)
        $row = mysqli_fetch_assoc($r);
        return intval($row['posting_allowed']) === 1;
    }
}
if (!function_exists('fin_ctx')) {
    function fin_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'       => $role,
            'is_super'   => ($role === '-1'),
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

if (!function_exists('fin_gen_code')) {
    /** كود تسلسلي لكل شركة، مثل FIN-EV-0001. اسم الجدول من قائمة بيضاء في الكود. */
    function fin_gen_code($conn, $table, $prefix, $company_id)
    {
        $n = 0;
        $sql = "SELECT COUNT(*) AS c FROM `" . $table . "` WHERE company_id = " . intval($company_id);
        if ($res = mysqli_query($conn, $sql)) {
            $row = mysqli_fetch_assoc($res);
            $n = intval($row['c']);
        }
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

if (!function_exists('fin_options_from_query')) {
    function fin_options_from_query($conn, $sql, $selected = 0, $placeholder = '— اختر —')
    {
        $out = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        $selected = intval($selected);
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) {
                $id  = intval($r['id']);
                $lbl = isset($r['label']) ? (string)$r['label'] : '';
                $sel = ($id === $selected) ? ' selected' : '';
                $out .= '<option value="' . $id . '"' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
            }
        }
        return $out;
    }
}

// ── قوائم من الجداول القائمة (قراءة فقط) ──
if (!function_exists('fin_supplier_options')) {
    function fin_supplier_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, name AS label FROM suppliers WHERE $scope ORDER BY name ASC";
        return fin_options_from_query($conn, $sql, $selected, '— بلا مورد —');
    }
}
if (!function_exists('fin_client_options')) {
    function fin_client_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, client_name AS label FROM clients WHERE $scope ORDER BY client_name ASC";
        return fin_options_from_query($conn, $sql, $selected, '— بلا عميل —');
    }
}
if (!function_exists('fin_project_options')) {
    function fin_project_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, name AS label FROM project WHERE $scope AND COALESCE(is_deleted,0)=0 ORDER BY name ASC";
        return fin_options_from_query($conn, $sql, $selected, '— بلا مشروع —');
    }
}
if (!function_exists('fin_equipment_options')) {
    function fin_equipment_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(COALESCE(NULLIF(code,''),CONCAT('#',id)),
                CASE WHEN name IS NULL OR name='' THEN '' ELSE CONCAT(' — ', name) END) AS label
                FROM equipments WHERE $scope ORDER BY id DESC";
        return fin_options_from_query($conn, $sql, $selected, '— بلا معدة —');
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
        $sql = "INSERT INTO fin_approvals (company_id, entity_type, entity_id, from_state, to_state, action, level, actor_id, note)
                VALUES (?, 'financial_event', ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'iissssss', $company_id, $entity_id, $from, $to, $action, $level, $actor, $note);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
    }
}
if (!function_exists('fin_required_level')) {
    /** المستوى المطلوب لاعتماد مبلغٍ من المصفوفة (للعرض/الحوكمة). */
    function fin_required_level($conn, $company_id, $event_type, $amount)
    {
        $company_id = intval($company_id); $amount = (float)$amount;
        $et = mysqli_real_escape_string($conn, $event_type);
        $sql = "SELECT required_level FROM fin_approval_matrix
                WHERE company_id=$company_id AND active=1 AND (event_type='$et' OR event_type='any')
                  AND min_amount <= $amount AND (max_amount IS NULL OR max_amount > $amount)
                ORDER BY (event_type='any') ASC, sequence DESC LIMIT 1";
        $r = mysqli_query($conn, $sql);
        return ($r && ($row = mysqli_fetch_assoc($r))) ? $row['required_level'] : null;
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
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(code, ' — ', name) AS label FROM fin_cost_centers
                WHERE $scope AND COALESCE(is_deleted,0)=0 ORDER BY code ASC";
        return fin_options_from_query($conn, $sql, $selected, $placeholder);
    }
}

// ── المطابقة البنكية ──
if (!function_exists('fin_bank_account_options')) {
    function fin_bank_account_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(name, CASE WHEN bank_name IS NULL OR bank_name='' THEN '' ELSE CONCAT(' — ', bank_name) END) AS label
                FROM fin_bank_accounts WHERE $scope AND COALESCE(is_deleted,0)=0 ORDER BY name ASC";
        return fin_options_from_query($conn, $sql, $selected, '— اختر حساباً بنكياً —');
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
        // ملاحظة: جدول employees لا يحوي is_deleted (يستخدم status/employee_status).
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, name AS label FROM employees WHERE $scope ORDER BY name ASC";
        return fin_options_from_query($conn, $sql, $selected, '— اختر موظفاً —');
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
        $company_id = intval($company_id); $supplier_id = intval($supplier_id);
        $sql = "SELECT
                  COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END),0) AS credit_sum,
                  COALESCE(SUM(CASE WHEN direction='debit'  THEN amount ELSE 0 END),0) AS debit_sum
                FROM fin_dues
                WHERE company_id=$company_id AND party_type='supplier' AND party_ref=$supplier_id
                  AND COALESCE(is_deleted,0)=0 AND settlement_state<>'paid'";
        $r = mysqli_query($conn, $sql);
        $row = $r ? mysqli_fetch_assoc($r) : array('credit_sum' => 0, 'debit_sum' => 0);
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
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(code, ' — ', name) AS label FROM fin_units
                WHERE $scope AND COALESCE(is_deleted,0)=0 ORDER BY code ASC";
        return fin_options_from_query($conn, $sql, $selected, '— اختر وحدة —');
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
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(code, ' — ', name) AS label FROM fin_chart_of_accounts
                WHERE $scope AND COALESCE(is_deleted,0)=0 AND is_postable=1 ORDER BY code ASC";
        return fin_options_from_query($conn, $sql, $selected, '— اختر حساباً —');
    }
}
if (!function_exists('fin_account_parent_options')) {
    /** حسابات هذه الشركة كآباء محتملين (fin_chart_of_accounts). */
    function fin_account_parent_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = fin_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(code, ' — ', name) AS label FROM fin_chart_of_accounts
                WHERE $scope AND COALESCE(is_deleted,0)=0 ORDER BY code ASC";
        return fin_options_from_query($conn, $sql, $selected, '— بلا أب (جذر) —');
    }
}
