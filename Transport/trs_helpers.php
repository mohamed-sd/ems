<?php
/**
 * Transport/trs_helpers.php — دوال مساعدة مشتركة لوحدة النقل والترحيل (transfer_* / trs_*).
 *
 * ملاحظات معمارية (نفس نمط وحدتَي المشتريات والمالية):
 *   • دوال نقية قابلة لإعادة الاستخدام فقط — إقلاع الجلسة/الصلاحيات يبقى داخل كل صفحة.
 *   • قوائم المعدات/المشاريع/الموظفين/الموردين تُقرأ من الجداول القائمة قراءةً فقط
 *     (لا كتابة، لا FK صلب) ⇒ لا تأثير على النظام الحالي.
 *
 * @package EMS\Transport
 */

if (!function_exists('trs_ctx')) {
    /** سياق المستخدم الحالي من الجلسة. */
    function trs_ctx()
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

if (!function_exists('trs_page_perms')) {
    /** حل صلاحيات صفحة النقل (super admin يملك الكل). */
    function trs_page_perms($conn, $code, $is_super)
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

if (!function_exists('trs_scope')) {
    /** شرط عزل الشركة لعمود معطى. */
    function trs_scope($col, $is_super, $company_id)
    {
        return $is_super ? '1=1' : ($col . ' = ' . intval($company_id));
    }
}


if (!function_exists("trs_gate")) {
    /** K9-M2b: بوابة العزل للوحدة — سياق الجلسة؛ is_super عبر forAllTenants المسجلة. */
    function trs_gate($is_super = false)
    {
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants("trs helpers super view") : $gate;
    }
}
if (!function_exists('trs_gen_code')) {
    /** توليد كود تسلسلي بسيط لكل شركة، مثل LOC-0001. اسم الجدول من قائمة بيضاء بالكود. */
    function trs_gen_code($conn, $table, $prefix, $company_id)
    {
        // K9-M2b: عبر البوابة — includeDeleted يطابق العدّ الخام الأصلي
        $n = trs_gate(false)->count($table, array('includeDeleted' => true));
        return $prefix . '-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('trs_msg_banner')) {
    /** شريط رسالة النجاح/الخطأ من msg في الـ query string. */
    function trs_msg_banner()
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

if (!function_exists('trs_options_from_rows')) {
    /** K9-M2b: بناء <option> من صفوف بوابة (id + label جاهزان) — لا تنفيذ نص خام
     *  (لا مستدعي خارجيًا لسلف هذه الدالة — أزيلت بلا جسر، درس M1). */
    function trs_options_from_rows(array $rows, $selected = 0, $placeholder = '— اختر —')
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

if (!function_exists('trs_project_options')) {
    /** قائمة المشاريع — قراءة فقط من project (لا كتابة). */
    function trs_project_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('project', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— بلا مشروع —');
    }
}

if (!function_exists('trs_location_options')) {
    /** قائمة المواقع (trs_locations) الخاصة بالوحدة. */
    function trs_location_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('trs_locations', array(
            'columns' => array('id', 'name', 'code'),
            'where'   => array('active' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name'] . ' (' . $r['code'] . ')'; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— اختر موقعاً —');
    }
}

if (!function_exists('trs_type_options')) {
    /** قائمة أنواع الترحيل (transfer_types). */
    function trs_type_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('transfer_types', array(
            'columns' => array('id', 'name'),
            'where'   => array('active' => 1),
            'orderBy' => 'id ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— اختر نوعاً —');
    }
}

if (!function_exists('trs_equipment_options')) {
    /** قائمة المعدات/المركبات — قراءة فقط من equipments. */
    function trs_equipment_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('equipments', array(
            'columns'  => array('id', 'code', 'name'),
            'whereRaw' => 'COALESCE(status,1)=1',
            'orderBy'  => 'id DESC',
        ));
        foreach ($rows as &$r) {
            $code = (string) $r['code'];
            $base = ($code === '') ? ('#' . intval($r['id'])) : $code;
            $name = (string) $r['name'];
            $r['label'] = $base . ($name === '' ? '' : ' — ' . $name);
        }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— اختر معدة —');
    }
}

if (!function_exists('trs_employee_options')) {
    /** قائمة الموظفين — قراءة فقط من employees. */
    function trs_employee_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('employees', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— اختر موظفاً —');
    }
}

if (!function_exists('trs_supplier_options')) {
    /** قائمة الموردين — قراءة فقط من suppliers (المقاول الناقل). */
    function trs_supplier_options($conn, $is_super, $company_id, $selected = 0)
    {
        // includeDeleted للوفاء الحرفي: الأصل يعرض الكل (يوجد موردان مؤرشفان فعلاً)؛
        // إخفاء المؤرشف قرار سياسة لمالكي الوحدة لاحقًا، لا أثر هجرةٍ صامت.
        $rows = trs_gate($is_super)->select('suppliers', array(
            'columns' => array('id', 'name'), 'orderBy' => 'name ASC', 'includeDeleted' => true,
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— اختر مقاولاً ناقلاً —');
    }
}

if (!function_exists('trs_item_options')) {
    /** قائمة الأصناف — قراءة فقط من proc_item (المخزون). */
    function trs_item_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('proc_item', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— اختر صنفاً —');
    }
}

if (!function_exists('trs_user_options')) {
    /** قائمة المستخدمين (الجهة الطالبة) — قراءة فقط من users. */
    function trs_user_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = trs_gate($is_super)->select('users', array('columns' => array('id', 'name'), 'orderBy' => 'name ASC'));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return trs_options_from_rows($rows, $selected, '— الجهة الطالبة —');
    }
}

if (!function_exists('trs_project_days')) {
    /**
     * مدة المشروع (يوم) لقاعدة الستين — من أحدث عقدٍ ساري (status=1) لهذا المشروع
     * حسب actual_start، عبر contract_duration_days. قراءة فقط من contracts.
     * @return int|null
     */
    function trs_project_days($conn, $company_id, $project_id)
    {
        $project_id = intval($project_id);
        if ($project_id <= 0) { return null; }
        // وفاء حرفي لفلتر الأصل (deleted_at لا is_deleted) — includeDeleted+whereRaw
        $row = trs_gate(false)->selectOne('contracts', array(
            'columns'  => array('contract_duration_days'),
            'where'    => array('project_id' => $project_id, 'status' => 1),
            'whereRaw' => "COALESCE(deleted_at, '') = ''",
            'includeDeleted' => true,
            'orderBy'  => 'actual_start DESC, id DESC',
        ));
        if ($row) {
            return ($row['contract_duration_days'] !== null) ? intval($row['contract_duration_days']) : null;
        }
        return null;
    }
}

if (!function_exists('trs_compute_bearer')) {
    /**
     * متحمِّل التكلفة من قواعد transfer_cost_rules حسب نوع الحركة ومدة المشروع.
     * يفضّل القاعدة المحدّدة (lt/gte) على القاعدة العامة (any).
     * @return string|null  client|company|new_client
     */
    function trs_compute_bearer($conn, $company_id, $movement_type, $project_days = null)
    {
        $pd = ($project_days === null) ? null : intval($project_days);
        // القواعد المرشّحة عبر البوابة، والانتقاء بأفضلية (المحدّدة قبل any) في PHP
        // (ORDER BY التعبيري خارج صرامة معرّفات البوابة — النتيجة مطابقة للأصل)
        $rules = trs_gate(false)->select('transfer_cost_rules', array(
            'columns' => array('id', 'duration_operator', 'duration_threshold_days', 'default_bearer'),
            'where'   => array('movement_type' => (string) $movement_type, 'active' => 1),
            'orderBy' => 'id ASC',
        ));
        $anyBearer = null;
        foreach ($rules as $r) {
            $op = $r['duration_operator'];
            if ($op === 'any') {
                if ($anyBearer === null) { $anyBearer = $r['default_bearer']; }
                continue;
            }
            if ($pd === null) { continue; }
            $thr = intval($r['duration_threshold_days']);
            if (($op === 'lt' && $pd < $thr) || ($op === 'gte' && $pd >= $thr)) {
                return $r['default_bearer']; // أول قاعدةٍ محدّدةٍ مطابقة (id ASC) — كالأصل
            }
        }
        return $anyBearer;
    }
}

if (!function_exists('trs_gen_order_no')) {
    /**
     * توليد كود الحركة: <معدة/TRP>-<DIR>-<YYYY>-<MM>-<NNNN>  مثل EM01-MOB-2026-07-0015.
     * التسلسل لكل شركة/سنة/شهر. يُسنِده الخادم حصريّاً.
     */
    function trs_gen_order_no($conn, $company_id, $direction, $equipment_code = '')
    {
        $company_id = intval($company_id);
        $y = date('Y'); $m = date('m');
        $dir = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $direction), 0, 8));
        $veh = trim((string)$equipment_code);
        $veh = ($veh === '') ? 'TRP' : strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $veh));
        $n = trs_gate(false)->count('transfer_orders', array(
            'whereRaw' => 'order_no LIKE ?', 'params' => array("%-$y-$m-%"),
            'includeDeleted' => true, // كالعدّ الخام الأصلي
        ));
        return $veh . '-' . $dir . '-' . $y . '-' . $m . '-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('trs_log_event')) {
    /** إضافة حدث إلى سجلّ الأمر (إلحاقي فقط). */
    function trs_log_event($conn, $company_id, $order_id, $event_type, $body, $old = null, $new = null, $actor_user_id = null, $actor_dept = null)
    {
        // عبر البوابة — الشركة تُحقن من سياقها (وسيط $company_id باقٍ للتوافق؛
        // تحت forSystem($cid) في الـcron يطابق سياق الدورة)
        try {
            trs_gate(false)->insert('transfer_events', array(
                'order_id' => $order_id, 'event_type' => $event_type,
                'actor_user_id' => $actor_user_id, 'actor_dept' => $actor_dept,
                'body' => $body, 'old_value' => $old, 'new_value' => $new,
            ));
        } catch (\App\Core\TenantGateException $e) {
            error_log('trs_log_event refused: ' . $e->getMessage());
        }
    }
}

// ── قوائم بيضاء (خرائط ترجمة القيم الثابتة إلى عربية للعرض) ──

if (!function_exists('trs_location_types')) {
    function trs_location_types() {
        return array('base' => 'قاعدة', 'project' => 'مشروع', 'workshop' => 'ورشة', 'office' => 'مكتب');
    }
}
if (!function_exists('trs_operational_categories')) {
    function trs_operational_categories() {
        return array(
            'equipment_transfer'  => 'ترحيل معدة',
            'parts_transfer'      => 'نقل قطع غيار',
            'personnel_move'      => 'تنقّل أفراد',
            'equipment_plus_move' => 'ترحيل معدة + تنقّل',
        );
    }
}
if (!function_exists('trs_bearers')) {
    function trs_bearers() {
        return array('client' => 'العميل', 'company' => 'الشركة', 'new_client' => 'العميل الجديد');
    }
}
if (!function_exists('trs_bearers_rule')) {
    function trs_bearers_rule() {
        $b = trs_bearers();
        $b['by_rule'] = 'حسب القاعدة';
        return $b;
    }
}
if (!function_exists('trs_movement_types')) {
    function trs_movement_types() {
        return array(
            'mob'         => 'ذهاب (MOB)',
            'demob'       => 'عودة (DEMOB)',
            'direct'      => 'نقل مباشر',
            'internal'    => 'حركة داخلية',
            'admin'       => 'إداري / مكاتب',
            'spare_parts' => 'قطع غيار',
            'travel'      => 'نثرية سفر',
        );
    }
}
if (!function_exists('trs_directions')) {
    function trs_directions() {
        return array('mob' => 'ذهاب (MOB)', 'demob' => 'عودة (DEMOB)', 'direct' => 'نقل مباشر', 'internal' => 'حركة داخلية');
    }
}
if (!function_exists('trs_duration_operators')) {
    function trs_duration_operators() {
        return array('any' => 'أي مدة', 'lt' => 'أقل من', 'gte' => 'أكبر أو يساوي');
    }
}
if (!function_exists('trs_stages')) {
    function trs_stages() {
        return array(
            'request'    => 'طلب',
            'planned'    => 'مخطَّط',
            'ready'      => 'جاهز',
            'in_transit' => 'قيد الرحلة',
            'arrived'    => 'وصل',
            'closed'     => 'مغلق',
            'cancelled'  => 'ملغى',
        );
    }
}
if (!function_exists('trs_source_modules')) {
    function trs_source_modules() {
        return array(
            'operations'  => 'التشغيل',
            'fleet'       => 'الأسطول',
            'maintenance' => 'الصيانة',
            'workforce'   => 'القوى التشغيلية',
            'procurement' => 'المشتريات/المخزون',
        );
    }
}
if (!function_exists('trs_request_states')) {
    function trs_request_states() {
        return array('submitted' => 'مقدَّم', 'approved' => 'معتمد', 'converted' => 'محوَّل لأمر', 'rejected' => 'مرفوض');
    }
}
if (!function_exists('trs_priorities')) {
    function trs_priorities() {
        return array('normal' => 'عادي', 'urgent' => 'عاجل');
    }
}
if (!function_exists('trs_item_types')) {
    function trs_item_types() {
        return array('equipment' => 'معدة', 'attachment' => 'مرفق', 'material' => 'مادة/قطعة', 'person' => 'شخص');
    }
}
if (!function_exists('trs_cost_types')) {
    function trs_cost_types() {
        return array('fuel' => 'وقود', 'labor' => 'أجور', 'contractor' => 'مقاول', 'misc' => 'نثريات', 'permit' => 'تصاريح');
    }
}
if (!function_exists('trs_permit_types')) {
    function trs_permit_types() {
        return array('route' => 'مسار', 'load' => 'حمولة', 'transit' => 'عبور', 'safety' => 'سلامة');
    }
}
if (!function_exists('trs_permit_states')) {
    function trs_permit_states() {
        return array('issuing' => 'قيد الإصدار', 'valid' => 'ساري', 'expired' => 'منتهٍ');
    }
}
if (!function_exists('trs_carrier_types')) {
    function trs_carrier_types() {
        return array('internal' => 'داخلي (شاحنة الشركة)', 'contractor' => 'مقاول ناقل');
    }
}
if (!function_exists('trs_currencies')) {
    function trs_currencies() {
        return array('SDG' => 'جنيه سوداني (SDG)', 'USD' => 'دولار (USD)');
    }
}
if (!function_exists('trs_stage_badge')) {
    /** لون مرحلة الأمر للعرض. */
    function trs_stage_badge($stage) {
        $colors = array(
            'request' => '#6c757d', 'planned' => '#0d6efd', 'ready' => '#0dcaf0',
            'in_transit' => '#fd7e14', 'arrived' => '#198754', 'closed' => '#212529', 'cancelled' => '#dc3545',
        );
        $map = trs_stages();
        $c = isset($colors[$stage]) ? $colors[$stage] : '#6c757d';
        $lbl = isset($map[$stage]) ? $map[$stage] : $stage;
        return "<span class='action-btn' style='color:#fff;background:$c;border-radius:12px;padding:2px 10px'>" . htmlspecialchars($lbl) . "</span>";
    }
}
if (!function_exists('trs_label')) {
    /** ترجمة قيمة إلى عنوانها العربي من خريطة، مع تمرير القيمة نفسها إن لم توجد. */
    function trs_label($map, $key) {
        return isset($map[$key]) ? $map[$key] : (string)$key;
    }
}

// ── دورة الحياة (§6) — جداول مملوكة فقط، بلا مساس بأي جدول قائم ──

if (!function_exists('trs_transitions')) {
    /**
     * خريطة انتقالات مرحلة الأمر: action ⇒ [from, to, need (perm), label, icon, color].
     * need: edit = يتطلب صلاحية تعديل · delete = يتطلب صلاحية حذف (إلغاء/إعادة فتح).
     */
    function trs_transitions() {
        return array(
            'plan'              => array('from' => 'request',    'to' => 'planned',    'need' => 'edit',   'label' => 'اعتماد وتحويل لمخطَّط', 'icon' => 'fa-clipboard-check', 'color' => '#0d6efd'),
            'prepare'           => array('from' => 'planned',    'to' => 'ready',      'need' => 'edit',   'label' => 'تجهيز (جاهز للتنفيذ)',  'icon' => 'fa-toolbox',         'color' => '#0dcaf0'),
            'confirm_departure' => array('from' => 'ready',      'to' => 'in_transit', 'need' => 'edit',   'label' => 'تأكيد المغادرة',        'icon' => 'fa-truck-arrow-right','color' => '#fd7e14'),
            'confirm_arrival'   => array('from' => 'in_transit', 'to' => 'arrived',    'need' => 'edit',   'label' => 'تأكيد الوصول',          'icon' => 'fa-flag-checkered',  'color' => '#198754'),
            'close'             => array('from' => 'arrived',    'to' => 'closed',     'need' => 'edit',   'label' => 'إغلاق الأمر',           'icon' => 'fa-lock',            'color' => '#212529'),
            'reopen'            => array('from' => 'closed',     'to' => 'arrived',    'need' => 'delete', 'label' => 'إعادة فتح',             'icon' => 'fa-lock-open',       'color' => '#6f42c1'),
        );
    }
}

if (!function_exists('trs_notify')) {
    /**
     * إضافة إشعار إلى trs_notifications (جدول مملوك جديد) مع منع التكرار عبر dedupe_key.
     * لا يكتب في أي جدول إشعارات قائم (fin_*) — صفر تداخل.
     */
    function trs_notify($conn, $company_id, $notif_type, $title, $body, $order_id, $target_role, $link_url, $dedupe_key) {
        $sql = "INSERT IGNORE INTO trs_notifications
                (company_id, order_id, notif_type, target_role, title, body, link_url, dedupe_key)
                VALUES (?,?,?,?,?,?,?,?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            $oid = ($order_id === null) ? null : intval($order_id);
            $trg = ($target_role === null) ? null : intval($target_role);
            mysqli_stmt_bind_param($stmt, 'iisissss', $company_id, $oid, $notif_type, $trg, $title, $body, $link_url, $dedupe_key);
            mysqli_stmt_execute($stmt);
            $ok = mysqli_stmt_affected_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $ok;
        }
        return false;
    }
}

if (!function_exists('trs_save_proof')) {
    /**
     * حفظ ملف إثبات (مغادرة/وصول) خارج مسار الكود المصدري (Transport/uploads/proofs)
     * وتسجيله في transfer_attachments (جدول مملوك). يُرجع المسار النسبي أو null.
     * تحقّق من النوع والحجم كما في §14.
     */
    function trs_save_proof($conn, $company_id, $order_id, $file, $file_type, $uploaded_by, $gps_lat = null, $gps_lng = null) {
        if (!isset($file) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return null; }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 8 * 1024 * 1024) { return null; }   // ≤ 8MB
        $allowed = array('jpg' => 1, 'jpeg' => 1, 'png' => 1, 'gif' => 1, 'webp' => 1, 'pdf' => 1);
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) { return null; }
        $dir = __DIR__ . '/uploads/proofs';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $safe = intval($company_id) . '_' . intval($order_id) . '_' . preg_replace('/[^a-z_]/', '', (string)$file_type)
              . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        $dest = $dir . '/' . $safe;
        if (!@move_uploaded_file($file['tmp_name'], $dest)) { return null; }
        $rel = 'uploads/proofs/' . $safe;
        $lat = ($gps_lat === null || $gps_lat === '') ? null : (float)$gps_lat;
        $lng = ($gps_lng === null || $gps_lng === '') ? null : (float)$gps_lng;
        $stmt = mysqli_prepare($conn, "INSERT INTO transfer_attachments
            (company_id, order_id, file_path, file_type, gps_lat, gps_lng, captured_at, uploaded_by)
            VALUES (?,?,?,?,?,?,NOW(),?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iissddi', $company_id, $order_id, $rel, $file_type, $lat, $lng, $uploaded_by);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        return $rel;
    }
}
