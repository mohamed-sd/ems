<?php
/**
 * Procurement/proc_helpers.php — دوال مساعدة مشتركة لوحدة المشتريات (proc_*).
 *
 * ملاحظات معمارية:
 *   • دوال نقية قابلة لإعادة الاستخدام فقط — إقلاع الجلسة/الصلاحيات يبقى داخل كل
 *     صفحة (نفس نمط وحدة الصيانة) لتفادي مشاكل ترتيب session_start/الإخراج.
 *   • قوائم المعدات/المشاريع تُقرأ من الجداول القائمة قراءةً فقط (لا كتابة، لا FK)
 *     ⇒ لا تأثير على النظام الحالي.
 *
 * @package EMS\Procurement
 */

if (!function_exists('proc_ctx')) {
    /** سياق المستخدم الحالي من الجلسة. */
    function proc_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'          => $role,
            'is_super'      => ($role === EMS_ROLE_SUPER_ADMIN),
            'company_id'    => isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0,
            'user_id'       => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
        );
    }
}

if (!function_exists('proc_page_perms')) {
    /** حل صلاحيات صفحة المشتريات (super admin يملك الكل). */
    function proc_page_perms($conn, $code, $is_super)
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

if (!function_exists('proc_scope')) {
    /** شرط عزل الشركة لعمود معطى. */
    function proc_scope($col, $is_super, $company_id)
    {
        return $is_super ? '1=1' : ($col . ' = ' . intval($company_id));
    }
}

if (!function_exists('proc_gate')) {
    /**
     * K9-M1: بوابة العزل للوحدة — سياق الجلسة؛ وis_super يمرّ عبر القناة
     * الشرعية forAllTenants (يحفظ سلوكه القائم: رؤية عابرة مسجَّلة).
     * وسائط $conn/$company_id في الدوال أدناه بقيت للتوافق أثناء الدفعة —
     * البوابة مصدر الوصول الوحيد فعليًا.
     */
    function proc_gate($is_super = false)
    {
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('proc helpers super view') : $gate;
    }
}

if (!function_exists('proc_gen_code')) {
    /** توليد كود تسلسلي بسيط لكل شركة، مثل PRC-ITM-0001 (عبر البوابة). */
    function proc_gen_code($conn, $table, $prefix, $company_id)
    {
        // includeDeleted يطابق العدّ الأصلي (كان يعدّ كل صفوف الشركة بما فيها المؤرشف)
        $n = proc_gate(false)->count($table, array('includeDeleted' => true));
        return $prefix . '-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('proc_msg_banner')) {
    /** شريط رسالة النجاح/الخطأ من msg في الـ query string. */
    function proc_msg_banner()
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

if (!function_exists('proc_options_from_query')) {
    /**
     * ⚠ جسر توافقٍ مؤقت أثناء دفعة M1: ثلاث شاشات (orders/receipt/issue) تمرّر
     * SQL خاصًا بها هنا — يتحول كلٌّ عند هجرة ملفه إلى صفوف بوابة، ثم تُزال هذه
     * الدالة في ختام الدفعة (حينها T3=0 على الوحدة كلها).
     */
    function proc_options_from_query($conn, $sql, $selected = 0, $placeholder = '— اختر —')
    {
        $rows = array();
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
        }
        return proc_options_from_rows($rows, $selected, $placeholder);
    }
}

if (!function_exists('proc_options_from_rows')) {
    /** بناء <option> من صفوف (id + label جاهزان) — بديل التنفيذ بالنص الخام. */
    function proc_options_from_rows(array $rows, $selected = 0, $placeholder = '— اختر —')
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

if (!function_exists('proc_items_options')) {
    /** قائمة أصناف الكتالوج (proc_item) — عبر البوابة، والتسمية في PHP (مطابقة CONCAT الأصلية). */
    function proc_items_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('proc_item', array(
            'columns' => array('id', 'code', 'name'),
            'where'   => array('status' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) {
            $code = (string) $r['code'];
            $r['label'] = ($code === '' ? '' : $code . ' — ') . $r['name'];
        }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— اختر صنفاً —');
    }
}

if (!function_exists('proc_suppliers_options')) {
    /** قائمة الموردين التشغيليين (proc_supplier) — عبر البوابة. */
    function proc_suppliers_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('proc_supplier', array(
            'columns' => array('id', 'name'),
            'where'   => array('status' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— اختر مورداً —');
    }
}

if (!function_exists('proc_warehouses_options')) {
    /** قائمة المخازن (proc_warehouse) — عبر البوابة. */
    function proc_warehouses_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('proc_warehouse', array(
            'columns' => array('id', 'name', 'type'),
            'where'   => array('status' => 1),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name'] . ' (' . $r['type'] . ')'; }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— اختر مخزناً —');
    }
}

if (!function_exists('proc_lookup_names')) {
    /** أسماء قيم مرجعية حسب النوع (proc_lookup) — عبر البوابة. */
    function proc_lookup_names($conn, $is_super, $company_id, $type)
    {
        $rows = proc_gate($is_super)->select('proc_lookup', array(
            'columns' => array('name'),
            'where'   => array('type' => (string) $type, 'is_active' => 1),
            'orderBy' => 'name ASC',
        ));
        $out = array();
        foreach ($rows as $r) { $out[] = $r['name']; }
        return $out;
    }
}

if (!function_exists('proc_equipment_options')) {
    /** قائمة المعدات — قراءة فقط من equipments عبر البوابة (لا كتابة). */
    function proc_equipment_options($conn, $is_super, $company_id, $selected = 0)
    {
        // equipments بلا عمود is_deleted؛ يستخدم status (افتراضي 1) — كالأصل.
        $rows = proc_gate($is_super)->select('equipments', array(
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
        return proc_options_from_rows($rows, $selected, '— بلا معدة —');
    }
}

if (!function_exists('proc_project_options')) {
    /** قائمة المشاريع — قراءة فقط من project عبر البوابة (لا كتابة). */
    function proc_project_options($conn, $is_super, $company_id, $selected = 0)
    {
        $rows = proc_gate($is_super)->select('project', array(
            'columns' => array('id', 'name'),
            'orderBy' => 'name ASC',
        ));
        foreach ($rows as &$r) { $r['label'] = $r['name']; }
        unset($r);
        return proc_options_from_rows($rows, $selected, '— بلا مشروع —');
    }
}

// ── قوائم بيضاء للقيم الثابتة (enums منطقية على مستوى التطبيق) ──
if (!defined('PROC_CLASSIFICATIONS')) {
    define('PROC_CLASSIFICATIONS', 'وقائية,تصحيحية,رأسمالية,استهلاكية');
}
if (!function_exists('proc_classifications')) {
    function proc_classifications() { return explode(',', PROC_CLASSIFICATIONS); }
}
if (!function_exists('proc_need_sources')) {
    function proc_need_sources() { return array('خطة وقائية', 'أمر صيانة', 'نقص مخزون', 'إعادة طلب'); }
}
if (!function_exists('proc_priorities')) {
    function proc_priorities() { return array('عادي', 'عاجل', 'حرج'); }
}
if (!function_exists('proc_request_states')) {
    function proc_request_states() { return array('مسودة', 'مقدَّم', 'اعتماد المشتريات', 'مراجعة مالية', 'معتمد مالياً', 'محوَّل لأمر شراء', 'مغلق', 'مرفوض'); }
}
if (!function_exists('proc_order_states')) {
    function proc_order_states() { return array('مسودة', 'مؤكَّد', 'استلام أولي', 'استلام نهائي', 'مطابَق', 'مغلق'); }
}
if (!function_exists('proc_receipt_states')) {
    function proc_receipt_states() { return array('مستلَمة', 'قيد الترحيل', 'مسلَّمة للوجهة'); }
}
if (!function_exists('proc_issue_states')) {
    function proc_issue_states() { return array('مسودة', 'محجوز', 'مصروف', 'محمَّل التكلفة'); }
}
if (!function_exists('proc_custody_states')) {
    function proc_custody_states() { return array('مصروفة', 'إرجاع جزئي', 'مستهلكة', 'مُقفلة'); }
}
if (!function_exists('proc_material_natures')) {
    function proc_material_natures() { return array('قابل للتخزين', 'غير قابل للتخزين', 'خدمة ومصنعيات'); }
}
if (!function_exists('proc_destinations')) {
    function proc_destinations() { return array('مخزن', 'ورشة', 'مشروع', 'معدة'); }
}
if (!function_exists('proc_receipt_types')) {
    function proc_receipt_types() { return array('مخزن', 'مباشر للمعدة', 'مشروع', 'ورشة'); }
}
if (!function_exists('proc_currencies')) {
    function proc_currencies() { return array('SDG', 'USD'); }
}
if (!function_exists('proc_payment_times')) {
    function proc_payment_times() { return array('فوري', 'مؤجل', 'آجل 30', 'آجل 60', 'آجل 90'); }
}
if (!function_exists('proc_warehouse_types')) {
    function proc_warehouse_types() { return array('مخزن', 'ورشة', 'مباشر للآلية'); }
}
if (!function_exists('proc_lookup_types')) {
    function proc_lookup_types() { return array('فئة صنف', 'وحدة قياس', 'طبيعة مادة'); }
}
