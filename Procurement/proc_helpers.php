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
            'is_super'      => ($role === '-1'),
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

if (!function_exists('proc_gen_code')) {
    /** توليد كود تسلسلي بسيط لكل شركة، مثل PRC-ITM-0001. */
    function proc_gen_code($conn, $table, $prefix, $company_id)
    {
        $n = 0;
        // اسم الجدول من قائمة بيضاء ثابتة (يُمرَّر من الكود لا من المستخدم)
        $sql = "SELECT COUNT(*) AS c FROM `" . $table . "` WHERE company_id = " . intval($company_id);
        if ($res = mysqli_query($conn, $sql)) {
            $row = mysqli_fetch_assoc($res);
            $n = intval($row['c']);
        }
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
    /** بناء <option> من استعلام (id => label). */
    function proc_options_from_query($conn, $sql, $selected = 0, $placeholder = '— اختر —')
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

if (!function_exists('proc_items_options')) {
    /** قائمة أصناف الكتالوج (proc_item). */
    function proc_items_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = proc_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(COALESCE(NULLIF(code,''),''), CASE WHEN code IS NULL OR code='' THEN '' ELSE ' — ' END, name) AS label
                FROM proc_item WHERE $scope AND COALESCE(is_deleted,0)=0 AND status=1 ORDER BY name ASC";
        return proc_options_from_query($conn, $sql, $selected, '— اختر صنفاً —');
    }
}

if (!function_exists('proc_suppliers_options')) {
    /** قائمة الموردين التشغيليين (proc_supplier). */
    function proc_suppliers_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = proc_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, name AS label FROM proc_supplier
                WHERE $scope AND COALESCE(is_deleted,0)=0 AND status=1 ORDER BY name ASC";
        return proc_options_from_query($conn, $sql, $selected, '— اختر مورداً —');
    }
}

if (!function_exists('proc_warehouses_options')) {
    /** قائمة المخازن (proc_warehouse). */
    function proc_warehouses_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = proc_scope('company_id', $is_super, $company_id);
        $sql = "SELECT id, CONCAT(name, ' (', type, ')') AS label FROM proc_warehouse
                WHERE $scope AND COALESCE(is_deleted,0)=0 AND status=1 ORDER BY name ASC";
        return proc_options_from_query($conn, $sql, $selected, '— اختر مخزناً —');
    }
}

if (!function_exists('proc_lookup_names')) {
    /** أسماء قيم مرجعية حسب النوع (proc_lookup). */
    function proc_lookup_names($conn, $is_super, $company_id, $type)
    {
        $scope = proc_scope('company_id', $is_super, $company_id);
        $type  = mysqli_real_escape_string($conn, $type);
        $out = array();
        $sql = "SELECT name FROM proc_lookup WHERE $scope AND type='$type'
                AND COALESCE(is_deleted,0)=0 AND is_active=1 ORDER BY name ASC";
        if ($res = mysqli_query($conn, $sql)) {
            while ($r = mysqli_fetch_assoc($res)) { $out[] = $r['name']; }
        }
        return $out;
    }
}

if (!function_exists('proc_equipment_options')) {
    /** قائمة المعدات — قراءة فقط من equipments (لا كتابة). */
    function proc_equipment_options($conn, $is_super, $company_id, $selected = 0)
    {
        // ملاحظة: جدول equipments لا يحوي عمود is_deleted؛ يستخدم status (افتراضي 1).
        $scope = $is_super ? '1=1' : ('company_id = ' . intval($company_id));
        $del   = "AND COALESCE(status,1)=1";
        $sql = "SELECT id, CONCAT(COALESCE(NULLIF(code,''),CONCAT('#',id)), CASE WHEN name IS NULL OR name='' THEN '' ELSE CONCAT(' — ', name) END) AS label
                FROM equipments WHERE $scope $del ORDER BY id DESC";
        return proc_options_from_query($conn, $sql, $selected, '— بلا معدة —');
    }
}

if (!function_exists('proc_project_options')) {
    /** قائمة المشاريع — قراءة فقط من project (لا كتابة). */
    function proc_project_options($conn, $is_super, $company_id, $selected = 0)
    {
        $scope = $is_super ? '1=1' : ('company_id = ' . intval($company_id));
        $del   = "AND COALESCE(is_deleted,0)=0";
        $sql = "SELECT id, name AS label FROM project WHERE $scope $del ORDER BY name ASC";
        return proc_options_from_query($conn, $sql, $selected, '— بلا مشروع —');
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
