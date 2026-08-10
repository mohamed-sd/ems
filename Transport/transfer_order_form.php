<?php
/**
 * Transport/transfer_order_form.php — أمر الترحيل المركزي — §4/§3.2.
 * رأس الأمر + تبويبات (العناصر/التكاليف/التصاريح) + سجلّ الأحداث.
 * حقول محسوبة خادميّاً: order_no · project_days · cost_bearer · actual_cost_usd.
 * دورة الحياة الكاملة (مغادرة/وصول/إغلاق) = المرحلة 3. هنا: إنشاء/تحرير الرأس والتبويبات.
 * شاشة جديدة مستقلة — كتابة على الجداول الجديدة فقط؛ قراءة مرجعية من project/equipments/employees.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx = trs_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = trs_page_perms($conn, 'Transport/transfer_orders_list.php', $is_super_admin); // نفس موديول القائمة
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض أوامر الترحيل ❌', 'GOV-PERM-403', ''); exit(); }

$scope_val = $is_super_admin ? null : intval($company_id);
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

/** تأكيد ملكية الشركة لأمرٍ ما — عبر البوابة (نقطة واحدة تؤمّن 9 مسارات POST). */
function trs_order_owned($conn, $order_id, $is_super, $company_id) {
    return trs_gate($is_super)->count('transfer_orders', array(
        'where' => array('id' => intval($order_id)),
    )) > 0;
}

/**
 * إعادة حساب actual_cost_usd = Σ بنود التكلفة — عبر البوابة على خطوتين.
 * SUM مفلتر soft عمدًا (سياسة الأرشفة): البند المؤرشف يسقط من القائمة
 * والمجموع معًا — الأصل كان subquery خامًا قبل وجود الأرشفة أصلًا.
 * تُستدعى داخل معاملة مُدارة حصرًا (الزوج الذرّي مع إدراج/أرشفة البند).
 */
function trs_recompute_actual($gate, $order_id) {
    $order_id = intval($order_id);
    // فلتر soft صريح في النص: رمز {TENANT_SCOPE} يحقن عزل الشركة فقط —
    // وبدونه يبقى المؤرشف في المجموع بينما القائمة (select المفلتر) تخفيه
    $rows = $gate->scopedQuery(array('scope' => array('c' => 'transfer_cost_lines')),
        "SELECT COALESCE(SUM(c.amount_usd),0) AS total FROM transfer_cost_lines c
         WHERE {TENANT_SCOPE} AND c.order_id = ? AND COALESCE(c.is_deleted, 0) = 0",
        array($order_id));
    $total = $rows ? (float)$rows[0]['total'] : 0.0;
    $gate->update('transfer_orders', array('actual_cost_usd' => $total), array('id' => $order_id));
}

// ════════════════ معالجة POST ════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── حفظ رأس الأمر (إنشاء/تعديل) ──
    if ($action === 'save_header') {
        $id = intval($_POST['id'] ?? 0);
        $is_editing = $id > 0;
        if ($is_editing && !$can_edit) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
        if (!$is_editing && !$can_add) { ems_gov_flash_redirect('transfer_orders_list.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }

        $transfer_type_id = intval($_POST['transfer_type_id'] ?? 0);
        $direction = trim($_POST['direction'] ?? '');
        $source_module = trim($_POST['source_module'] ?? '');
        $requested_by_user_id = ($_POST['requested_by_user_id'] ?? '') !== '' ? intval($_POST['requested_by_user_id']) : null;
        $project_id = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
        $from_location_id = intval($_POST['from_location_id'] ?? 0);
        $to_location_id = intval($_POST['to_location_id'] ?? 0);
        $request_date = trim($_POST['request_date'] ?? date('Y-m-d'));
        $planned_date = ($_POST['planned_date'] ?? '') !== '' ? trim($_POST['planned_date']) : null;
        $priority = trim($_POST['priority'] ?? 'normal');
        $vehicle_id = ($_POST['vehicle_id'] ?? '') !== '' ? intval($_POST['vehicle_id']) : null;
        $carrier_type = ($_POST['carrier_type'] ?? '') !== '' ? trim($_POST['carrier_type']) : null;
        $carrier_entity_id = ($_POST['carrier_entity_id'] ?? '') !== '' ? intval($_POST['carrier_entity_id']) : null;
        $driver_id = ($_POST['driver_id'] ?? '') !== '' ? intval($_POST['driver_id']) : null;
        $route = trim($_POST['route'] ?? '');
        $analytic_cost_center = trim($_POST['analytic_cost_center'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $dirs = trs_directions(); $srcs = trs_source_modules();
        if ($transfer_type_id <= 0 || !array_key_exists($direction, $dirs) || !array_key_exists($source_module, $srcs) || $from_location_id <= 0 || $to_location_id <= 0) {
            $back = $is_editing ? "transfer_order_form.php?id=$id" : "transfer_order_form.php";
            ems_gov_redirect("Location: $back&msg=بيانات+الرأس+غير+مكتملة+(النوع/الاتجاه/المصدر/المواقع)+❌"); exit();
        }

        // حقول محسوبة: مدة المشروع + المتحمِّل الأولي
        $project_days = trs_project_days($conn, $company_id, $project_id);
        $cost_bearer = trs_compute_bearer($conn, $company_id, $direction, $project_days);

        if ($is_editing) {
            if (!trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_flash_redirect('transfer_orders_list.php', 'أمر غير موجود ❌', 'GOV-REF-404', ''); exit(); }
            trs_gate($is_super_admin)->update('transfer_orders', array(
                'transfer_type_id' => $transfer_type_id, 'direction' => $direction, 'source_module' => $source_module,
                'requested_by_user_id' => $requested_by_user_id, 'project_id' => $project_id,
                'from_location_id' => $from_location_id, 'to_location_id' => $to_location_id,
                'request_date' => $request_date, 'planned_date' => $planned_date, 'priority' => $priority,
                'vehicle_id' => $vehicle_id, 'carrier_type' => $carrier_type, 'carrier_entity_id' => $carrier_entity_id,
                'driver_id' => $driver_id, 'route' => $route, 'analytic_cost_center' => $analytic_cost_center,
                'project_days' => $project_days, 'cost_bearer' => $cost_bearer, 'notes' => $notes,
            ), array('id' => $id));
            trs_log_event($conn, $company_id, $id, 'note', 'تعديل رأس الأمر', null, null, $current_user_id, 'transport');
            ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=تم+حفظ+رأس+الأمر+✅"); exit();
        } else {
            // كود الحركة من كود المركبة إن وُجدت
            // قراءة مرجعية معزولة: الأصل قرأ الكود بلا فلتر شركة — البوابة تحصرها
            // بمعدات الشركة (تشديد عزلٍ موثَّق؛ القوائم الشرعية أصلًا من نفس الشركة)
            $veh_code = '';
            if ($vehicle_id) {
                $vrow = trs_gate($is_super_admin)->selectOne('equipments', array(
                    'columns' => array('code'), 'where' => array('id' => intval($vehicle_id)),
                ));
                if ($vrow) { $veh_code = (string)$vrow['code']; }
            }
            $order_no = trs_gen_order_no($conn, $company_id, $direction, $veh_code);
            $stage = 'request';
            $new_id = trs_gate($is_super_admin)->insert('transfer_orders', array(
                'order_no' => $order_no, 'transfer_type_id' => $transfer_type_id, 'direction' => $direction,
                'source_module' => $source_module, 'requested_by_user_id' => $requested_by_user_id,
                'project_id' => $project_id, 'from_location_id' => $from_location_id, 'to_location_id' => $to_location_id,
                'request_date' => $request_date, 'planned_date' => $planned_date, 'priority' => $priority,
                'vehicle_id' => $vehicle_id, 'carrier_type' => $carrier_type, 'carrier_entity_id' => $carrier_entity_id,
                'driver_id' => $driver_id, 'route' => $route, 'analytic_cost_center' => $analytic_cost_center,
                'project_days' => $project_days, 'cost_bearer' => $cost_bearer, 'stage' => $stage,
                'notes' => $notes, 'created_by' => $current_user_id,
            ));
            trs_log_event($conn, $company_id, $new_id, 'system', "إنشاء أمر ترحيل ($order_no)", null, $stage, $current_user_id, 'transport');
            ems_gov_redirect("Location: transfer_order_form.php?id=$new_id&msg=تم+إنشاء+الأمر+($order_no)+✅"); exit();
        }
    }

    // ── إضافة عنصر منقول ──
    if ($action === 'add_line') {
        $id = intval($_POST['id'] ?? 0);
        if (!$can_edit || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        $item_type = trim($_POST['item_type'] ?? '');
        $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? intval($_POST['equipment_id']) : null;
        $attachment_ref = trim($_POST['attachment_ref'] ?? '');
        $product_id = ($_POST['product_id'] ?? '') !== '' ? intval($_POST['product_id']) : null;
        $employee_id = ($_POST['employee_id'] ?? '') !== '' ? intval($_POST['employee_id']) : null;
        $quantity = ($_POST['quantity'] ?? '') !== '' ? (float)$_POST['quantity'] : null;
        $note = trim($_POST['line_note'] ?? '');
        if (!array_key_exists($item_type, trs_item_types())) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=lines&msg=نوع+عنصر+غير+صالح+❌"); exit(); }
        trs_gate($is_super_admin)->insert('transfer_lines', array(
            'order_id' => $id, 'item_type' => $item_type, 'equipment_id' => $equipment_id,
            'attachment_ref' => $attachment_ref, 'product_id' => $product_id,
            'employee_id' => $employee_id, 'quantity' => $quantity, 'note' => $note,
        ));
        trs_log_event($conn, $company_id, $id, 'note', 'إضافة عنصر منقول', null, null, $current_user_id, 'transport');
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=lines&msg=تمت+إضافة+العنصر+✅"); exit();
    }
    if ($action === 'del_line') {
        $id = intval($_POST['id'] ?? 0); $line_id = intval($_POST['line_id'] ?? 0);
        if (!$can_delete || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        // أرشفة لا حذف (السياسة)؛ تحقّق الربط أولًا: السطر ينتمي لهذا الأمر
        // المملوك — يسدّ أرشفة سطرٍ عبر أمرٍ آخر (القناة id-only)
        $linked = trs_gate($is_super_admin)->selectOne('transfer_lines', array(
            'columns' => array('id'), 'where' => array('id' => $line_id, 'order_id' => $id)));
        if ($linked) { trs_gate($is_super_admin)->softDelete('transfer_lines', $line_id); }
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=lines&msg=تم+حذف+العنصر+✅"); exit();
    }

    // ── إضافة بند تكلفة ──
    if ($action === 'add_cost') {
        $id = intval($_POST['id'] ?? 0);
        if (!$can_edit || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        $cost_type = trim($_POST['cost_type'] ?? '');
        $amount_local = (float)($_POST['amount_local'] ?? 0);
        $currency = trim($_POST['currency'] ?? 'SDG');
        $fx_rate = ($_POST['fx_rate'] ?? '') !== '' ? (float)$_POST['fx_rate'] : null;
        $cost_bearer = trim($_POST['cost_bearer'] ?? 'client');
        $analytic_cost_center = trim($_POST['cl_center'] ?? '');
        if (!array_key_exists($cost_type, trs_cost_types()) || !array_key_exists($cost_bearer, trs_bearers())) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=costs&msg=بيانات+التكلفة+غير+صالحة+❌"); exit(); }
        // amount_usd = amount_local × fx_rate (أو المبلغ نفسه إن كانت العملة USD)
        $amount_usd = ($currency === 'USD') ? $amount_local : ($fx_rate ? $amount_local * $fx_rate : 0);
        // الزوج الذرّي (عقد §9): البند + المجموع المشتق لا ينفصلان — الأصل
        // كتابتان غير معاملتين؛ تقوية مسار الفشل فقط، المسار السعيد مطابق
        trs_gate($is_super_admin)->runInTransaction(function ($g) use ($id, $cost_type, $amount_local, $currency, $fx_rate, $amount_usd, $cost_bearer, $analytic_cost_center) {
            $g->insert('transfer_cost_lines', array(
                'order_id' => $id, 'cost_type' => $cost_type, 'amount_local' => $amount_local,
                'currency' => $currency, 'fx_rate' => $fx_rate, 'amount_usd' => $amount_usd,
                'cost_bearer' => $cost_bearer, 'analytic_cost_center' => $analytic_cost_center,
            ));
            trs_recompute_actual($g, $id);
        }, 'transfer_order add_cost');
        trs_log_event($conn, $company_id, $id, 'note', 'إضافة بند تكلفة', null, null, $current_user_id, 'transport');
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=costs&msg=تمت+إضافة+بند+التكلفة+✅"); exit();
    }
    if ($action === 'del_cost') {
        $id = intval($_POST['id'] ?? 0); $cid = intval($_POST['cost_id'] ?? 0);
        if (!$can_delete || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        // أرشفة لا حذف (السياسة) + الزوج الذرّي مع المجموع المشتق (عقد §9)؛
        // تحقّق الربط داخل المعاملة: البند ينتمي لهذا الأمر المملوك (القناة
        // id-only — الشرط الثلاثي الأصلي يُستوفى بالفحص لا بتوسيع القناة)
        trs_gate($is_super_admin)->runInTransaction(function ($g) use ($id, $cid) {
            $linked = $g->selectOne('transfer_cost_lines', array(
                'columns' => array('id'), 'where' => array('id' => $cid, 'order_id' => $id)));
            if ($linked) {
                $g->softDelete('transfer_cost_lines', $cid);
                trs_recompute_actual($g, $id);
            }
        }, 'transfer_order del_cost');
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=costs&msg=تم+حذف+بند+التكلفة+✅"); exit();
    }

    // ── إضافة تصريح ──
    if ($action === 'add_permit') {
        $id = intval($_POST['id'] ?? 0);
        if (!$can_edit || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        $permit_type = trim($_POST['permit_type'] ?? '');
        $authority = trim($_POST['authority'] ?? '');
        $issue_date = ($_POST['issue_date'] ?? '') !== '' ? trim($_POST['issue_date']) : null;
        $expiry_date = ($_POST['expiry_date'] ?? '') !== '' ? trim($_POST['expiry_date']) : null;
        $state = trim($_POST['permit_state'] ?? 'issuing');
        if (!array_key_exists($permit_type, trs_permit_types()) || !array_key_exists($state, trs_permit_states())) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=permits&msg=بيانات+التصريح+غير+صالحة+❌"); exit(); }
        trs_gate($is_super_admin)->insert('transfer_permits', array(
            'order_id' => $id, 'permit_type' => $permit_type, 'authority' => $authority,
            'issue_date' => $issue_date, 'expiry_date' => $expiry_date, 'state' => $state,
        ));
        trs_log_event($conn, $company_id, $id, 'note', 'إضافة تصريح', null, null, $current_user_id, 'transport');
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=permits&msg=تمت+إضافة+التصريح+✅"); exit();
    }
    if ($action === 'del_permit') {
        $id = intval($_POST['id'] ?? 0); $pid = intval($_POST['permit_id'] ?? 0);
        if (!$can_delete || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        // أرشفة لا حذف (السياسة)؛ تحقّق الربط كنمط del_line
        $linked = trs_gate($is_super_admin)->selectOne('transfer_permits', array(
            'columns' => array('id'), 'where' => array('id' => $pid, 'order_id' => $id)));
        if ($linked) { trs_gate($is_super_admin)->softDelete('transfer_permits', $pid); }
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&tab=permits&msg=تم+حذف+التصريح+✅"); exit();
    }

    // ── دورة الحياة: انتقال بين المراحل (§6) — كتابة على transfer_orders/events/attachments فقط ──
    if ($action === 'transition') {
        $id = intval($_POST['id'] ?? 0);
        $trans = trim($_POST['trans'] ?? '');
        $map = trs_transitions();
        if (!isset($map[$trans]) || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) {
            ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=انتقال+غير+صالح+❌"); exit();
        }
        $t = $map[$trans];
        $need_perm = ($t['need'] === 'delete') ? $can_delete : $can_edit;
        if (!$need_perm) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=لا+توجد+صلاحية+لهذا+الإجراء+❌"); exit(); }

        // تحميل الأمر للتحقق من المرحلة والحرّاس
        $orow = trs_gate($is_super_admin)->selectOne('transfer_orders', array('where' => array('id' => $id)));
        if (!$orow || $orow['stage'] !== $t['from']) {
            ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=المرحلة+الحالية+لا+تسمح+بهذا+الانتقال+❌"); exit();
        }

        $err = '';              // رسالة الحارس إن فشل
        $set_extra = array();   // أعمدة زمنية إضافية (كانت NOW() نصية — الآن ساعة PHP لنفس المضيف)
        $company_of = intval($orow['company_id']);

        if ($trans === 'plan') {
            // ربط بمشروع/مركز تكلفة قبل الاعتماد (§ب.8)
            $has_center = ($orow['project_id'] !== null) || (trim((string)$orow['analytic_cost_center']) !== '');
            if (!$has_center) { $err = 'الاعتماد+يتطلب+ربطاً+بمشروع+أو+مركز+تكلفة'; }
        } elseif ($trans === 'prepare') {
            // عناصر + مسار؛ وللمعدة: وسيلة نقل + تصريح ساري (§ب.8 / §7.6)
            $nlines = trs_gate($is_super_admin)->count('transfer_lines', array('where' => array('order_id' => $id)));
            $has_equip = trs_gate($is_super_admin)->count('transfer_lines', array(
                'where' => array('order_id' => $id),
                'whereRaw' => "item_type IN('equipment','attachment')",
            )) > 0;
            $has_valid_permit = trs_gate($is_super_admin)->count('transfer_permits', array(
                'where' => array('order_id' => $id, 'state' => 'valid'),
            )) > 0;
            if ($nlines === 0) { $err = 'لا+تجهيز+دون+عنصرٍ+منقولٍ+واحدٍ+على+الأقل'; }
            elseif (trim((string)$orow['route']) === '') { $err = 'لا+تجهيز+دون+تحديد+المسار'; }
            elseif ($has_equip && (empty($orow['vehicle_id']) || !$has_valid_permit)) { $err = 'ترحيل+المعدة+يتطلب+مركبة+ناقلة+وتصريحاً+سارياً'; }
        } elseif ($trans === 'confirm_departure') {
            $rel = trs_save_proof($conn, $company_of, $id, $_FILES['proof'] ?? null, 'departure_proof', $current_user_id);
            if (!$rel) { $err = 'تأكيد+المغادرة+يتطلب+إرفاق+إثبات+(صورة/PDF)'; }
            else { $set_extra = array('departure_datetime' => date('Y-m-d H:i:s')); }
        } elseif ($trans === 'confirm_arrival') {
            $rel = trs_save_proof($conn, $company_of, $id, $_FILES['proof'] ?? null, 'arrival_proof', $current_user_id,
                                  $_POST['gps_lat'] ?? null, $_POST['gps_lng'] ?? null);
            if (!$rel) { $err = 'تأكيد+الوصول+يتطلب+إرفاق+إثبات+(صورة/GPS/توقيع)'; }
            else { $set_extra = array('arrival_datetime' => date('Y-m-d H:i:s')); }
        } elseif ($trans === 'close') {
            // لا إغلاق بلا مصدر تحميل: متحمِّل الأمر محدَّد + كل بند تكلفة له مركز (§7.6)
            $no_center = trs_gate($is_super_admin)->count('transfer_cost_lines', array(
                'where' => array('order_id' => $id),
                'whereRaw' => "(analytic_cost_center IS NULL OR analytic_cost_center='')",
            ));
            if (empty($orow['cost_bearer'])) { $err = 'لا+إغلاق+دون+تحديد+متحمِّل+التكلفة'; }
            elseif ($no_center > 0) { $err = 'كل+بند+تكلفة+يجب+أن+يحمل+مركز+تكلفة+قبل+الإغلاق'; }
        }
        // reopen: بلا حارس (بيد صاحب صلاحية الحذف/المدير)

        if ($err !== '') { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=$err+❌"); exit(); }

        $to = $t['to'];
        trs_gate($is_super_admin)->update('transfer_orders',
            array_merge(array('stage' => $to), $set_extra), array('id' => $id));
        trs_log_event($conn, $company_of, $id, 'status_change', $t['label'], $t['from'], $to, $current_user_id, 'transport');
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=تم:+" . rawurlencode($t['label']) . "+✅"); exit();
    }

    // ── الإلغاء: حالة لا محو، بسببٍ إلزامي (§ب.7) ──
    if ($action === 'cancel') {
        $id = intval($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (!$can_delete || !trs_order_owned($conn, $id, $is_super_admin, $company_id)) { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=غير+مسموح+❌"); exit(); }
        if ($reason === '') { ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=الإلغاء+يتطلب+سبباً+❌"); exit(); }
        $orow = trs_gate($is_super_admin)->selectOne('transfer_orders', array(
            'columns' => array('stage', 'company_id'), 'where' => array('id' => $id),
        ));
        if (!$orow || in_array($orow['stage'], array('closed', 'cancelled'), true)) {
            ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=لا+يمكن+إلغاء+أمرٍ+مغلقٍ+أو+ملغى+❌"); exit();
        }
        $cof = intval($orow['company_id']);
        trs_gate($is_super_admin)->update('transfer_orders', array('stage' => 'cancelled'), array('id' => $id));
        trs_log_event($conn, $cof, $id, 'status_change', 'إلغاء الأمر — السبب: ' . $reason, $orow['stage'], 'cancelled', $current_user_id, 'transport');
        ems_gov_redirect("Location: transfer_order_form.php?id=$id&msg=تم+إلغاء+الأمر+✅"); exit();
    }
}

// ════════════════ تحميل الأمر للعرض ════════════════
$order = null;
if ($order_id > 0) {
    // scopedQuery (عقد §10): النص الأصلي حرفيًا + الرمز؛ 8 إثراءات LEFT مرجعية
    $order_rows = trs_gate($is_super_admin)->scopedQuery(
        array('scope' => array('o' => 'transfer_orders'),
              'enrich' => array('tt' => 'transfer_types', 'p' => 'project',
                                'fl' => 'trs_locations', 'tl' => 'trs_locations',
                                'u' => 'users', 'e' => 'equipments',
                                'd' => 'employees', 's' => 'suppliers')),
        "SELECT o.*, tt.name AS type_name, p.name AS project_name,
                fl.name AS from_name, tl.name AS to_name, u.name AS requester_name,
                e.code AS vehicle_code, d.name AS driver_name, s.name AS carrier_name
         FROM transfer_orders o
         LEFT JOIN transfer_types tt ON tt.id = o.transfer_type_id
         LEFT JOIN project p ON p.id = o.project_id
         LEFT JOIN trs_locations fl ON fl.id = o.from_location_id
         LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
         LEFT JOIN users u ON u.id = o.requested_by_user_id
         LEFT JOIN equipments e ON e.id = o.vehicle_id
         LEFT JOIN employees d ON d.id = o.driver_id
         LEFT JOIN suppliers s ON s.id = o.carrier_entity_id
         WHERE {TENANT_SCOPE} AND o.id = ? LIMIT 1",
        array($order_id)
    );
    $order = $order_rows ? $order_rows[0] : null;
    if (!$order) { ems_gov_flash_redirect('transfer_orders_list.php', 'الأمر غير موجود ❌', 'GOV-REF-404', ''); exit(); }
}

$types_opt   = trs_type_options($conn, $is_super_admin, $company_id, $order['transfer_type_id'] ?? 0);
$loc_opt_from= trs_location_options($conn, $is_super_admin, $company_id, $order['from_location_id'] ?? 0);
$loc_opt_to  = trs_location_options($conn, $is_super_admin, $company_id, $order['to_location_id'] ?? 0);
$proj_opt    = trs_project_options($conn, $is_super_admin, $company_id, $order['project_id'] ?? 0);
$veh_opt     = trs_equipment_options($conn, $is_super_admin, $company_id, $order['vehicle_id'] ?? 0);
$drv_opt     = trs_employee_options($conn, $is_super_admin, $company_id, $order['driver_id'] ?? 0);
$carrier_opt = trs_supplier_options($conn, $is_super_admin, $company_id, $order['carrier_entity_id'] ?? 0);
$user_opt    = trs_user_options($conn, $is_super_admin, $company_id, $order['requested_by_user_id'] ?? 0);

$dirs = trs_directions(); $srcs = trs_source_modules(); $prios = trs_priorities(); $carriers = trs_carrier_types();
$item_types = trs_item_types(); $cost_types = trs_cost_types(); $permit_types = trs_permit_types();
$permit_states = trs_permit_states(); $bearers = trs_bearers(); $currencies = trs_currencies();
$active_tab = $_GET['tab'] ?? 'header';

$page_title = 'إيكوبيشن | أمر الترحيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main trs-order-main ems-unified-page-shell">
    <?php
    $header_title = $order ? ('أمر الترحيل — ' . htmlspecialchars($order['order_no'])) : 'أمر ترحيل جديد';
    $header_title_html = $order ? ('أمر الترحيل — ' . htmlspecialchars($order['order_no']) . ' ' . trs_stage_badge($order['stage'])) : null;
    $header_icon  = 'fa fa-truck-fast';
    $header_actions = array();
    $header_back = array(
        array('href' => 'transfer_orders_list.php', 'class' => '', 'icon' => 'fas fa-list', 'label' => 'القائمة'),
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>
    <?php trs_msg_banner(); ?>

    <?php if ($order): ?>
    <!-- ════ لوحة دورة الحياة (§6) ════ -->
    <?php
    $stage_flow = array('request', 'planned', 'ready', 'in_transit', 'arrived', 'closed');
    $cur = $order['stage'];
    $trans_map = trs_transitions();
    // الانتقالات المتاحة من المرحلة الحالية
    $avail = array();
    foreach ($trans_map as $k => $tt) { if ($tt['from'] === $cur) { $avail[$k] = $tt; } }
    $stages_ar = trs_stages();
    ?>
    <div class="card" style="margin-bottom:14px"><div class="card-body">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:12px">
            <?php foreach ($stage_flow as $i => $st):
                $pos_cur = array_search($cur, $stage_flow, true);
                $done = ($pos_cur !== false && $i < $pos_cur);
                $isnow = ($st === $cur);
                $bg = $isnow ? '#0d6efd' : ($done ? '#198754' : '#e9ecef');
                $fg = ($isnow || $done) ? '#fff' : '#6c757d';
            ?>
                <?php if ($i > 0): ?><i class="fa fa-angle-left" style="color:#adb5bd"></i><?php endif; ?>
                <span style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;border-radius:14px;padding:3px 12px;font-size:13px;font-weight:600">
                    <?php echo htmlspecialchars($stages_ar[$st]); ?>
                </span>
            <?php endforeach; ?>
            <?php if ($cur === 'cancelled'): ?>
                <span style="background:#dc3545;color:#fff;border-radius:14px;padding:3px 12px;font-size:13px;font-weight:700">ملغى</span>
            <?php endif; ?>
        </div>

        <?php if ($can_edit || $can_delete): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
            <?php foreach ($avail as $k => $tt):
                $needs_proof = in_array($k, array('confirm_departure', 'confirm_arrival'), true);
                if ($tt['need'] === 'delete' && !$can_delete) continue;
                if ($tt['need'] === 'edit' && !$can_edit) continue;
            ?>
                <?php if ($needs_proof): ?>
                <form action="transfer_order_form.php?id=<?php echo intval($order_id); ?>" method="post" enctype="multipart/form-data"
                      style="display:flex;gap:6px;align-items:center;background:#f8f9fa;border:1px solid #e0e0e0;border-radius:10px;padding:8px 10px">
                    <input type="hidden" name="action" value="transition">
                    <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
                    <input type="hidden" name="trans" value="<?php echo $k; ?>">
                    <label style="font-weight:600;font-size:13px"><i class="fa <?php echo $tt['icon']; ?>" style="color:<?php echo $tt['color']; ?>"></i> <?php echo htmlspecialchars($tt['label']); ?></label>
                    <input type="file" name="proof" accept="image/*,application/pdf" required style="max-width:190px">
                    <?php if ($k === 'confirm_arrival'): ?>
                        <input type="text" name="gps_lat" placeholder="GPS lat" style="width:90px" aria-label="GPS lat">
                        <input type="text" name="gps_lng" placeholder="GPS lng" style="width:90px" aria-label="GPS lng">
                    <?php endif; ?>
                    <button type="submit" class="btn-save" style="background:<?php echo $tt['color']; ?>"><i class="fa fa-check"></i> تأكيد</button>
                </form>
                <?php else: ?>
                <form action="transfer_order_form.php?id=<?php echo intval($order_id); ?>" method="post" style="display:inline"
                      onsubmit="return confirm('<?php echo htmlspecialchars($tt['label']); ?>؟');">
                    <input type="hidden" name="action" value="transition">
                    <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
                    <input type="hidden" name="trans" value="<?php echo $k; ?>">
                    <button type="submit" class="btn-save" style="background:<?php echo $tt['color']; ?>"><i class="fa <?php echo $tt['icon']; ?>"></i> <?php echo htmlspecialchars($tt['label']); ?></button>
                </form>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($can_delete && !in_array($cur, array('closed', 'cancelled'), true)): ?>
            <form action="transfer_order_form.php?id=<?php echo intval($order_id); ?>" method="post"
                  style="display:flex;gap:6px;align-items:center" onsubmit="return confirm('إلغاء الأمر نهائياً (مع تسجيل السبب)؟');">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
                <input type="text" name="reason" placeholder="سبب الإلغاء (إلزامي)" required style="width:200px" aria-label="سبب الإلغاء (إلزامي)">
                <button type="submit" class="btn-cancel"><i class="fa fa-ban"></i> إلغاء الأمر</button>
            </form>
            <?php endif; ?>

            <?php if (empty($avail) && !in_array($cur, array('closed', 'cancelled'), true)): ?>
                <span style="color:#6c757d;font-size:13px">لا إجراء متاح لدورك في هذه المرحلة.</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div></div>

    <!-- تبويبات -->
    <div class="card" style="margin-bottom:14px"><div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn-ok trs-tab-btn" data-tab="header"><i class="fa fa-circle-info"></i> الرأس والتجهيز</button>
        <button type="button" class="btn-ok trs-tab-btn" data-tab="lines"><i class="fa fa-boxes-stacked"></i> العناصر المنقولة</button>
        <button type="button" class="btn-ok trs-tab-btn" data-tab="costs"><i class="fa fa-hand-holding-dollar"></i> بنود التكلفة</button>
        <button type="button" class="btn-ok trs-tab-btn" data-tab="permits"><i class="fa fa-file-shield"></i> التصاريح</button>
        <button type="button" class="btn-ok trs-tab-btn" data-tab="events"><i class="fa fa-clock-rotate-left"></i> سجلّ الأحداث</button>
    </div></div>
    <?php endif; ?>

    <!-- ════ تبويب الرأس ════ -->
    <div class="trs-tab" id="tab-header">
    <form action="transfer_order_form.php<?php echo $order ? ('?id=' . intval($order_id)) : ''; ?>" method="post" class="allforms allforms-visible" style="display:block">
        <input type="hidden" name="action" value="save_header">
        <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> رأس أمر الترحيل والتجهيز</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group"><label for="emsf_530_fbe1c">نوع الترحيل <span class="required">*</span></label>
                        <select name="transfer_type_id" required id="emsf_530_fbe1c"><?php echo $types_opt; ?></select></div>
                    <div class="form-group"><label for="emsf_531_dac95">الاتجاه <span class="required">*</span></label>
                        <select name="direction" required id="emsf_531_dac95">
                            <option value="">— اختر —</option>
                            <?php foreach ($dirs as $k => $v) { $sel = ($order && $order['direction'] === $k) ? ' selected' : ''; echo "<option value='$k'$sel>" . htmlspecialchars($v) . "</option>"; } ?>
                        </select></div>
                    <div class="form-group"><label for="emsf_532_8329f">الإدارة المصدر <span class="required">*</span></label>
                        <select name="source_module" required id="emsf_532_8329f">
                            <option value="">— اختر —</option>
                            <?php foreach ($srcs as $k => $v) { $sel = ($order && $order['source_module'] === $k) ? ' selected' : ''; echo "<option value='$k'$sel>" . htmlspecialchars($v) . "</option>"; } ?>
                        </select></div>
                    <div class="form-group"><label for="emsf_533_3525e">الجهة الطالبة</label>
                        <select name="requested_by_user_id" id="emsf_533_3525e"><?php echo $user_opt; ?></select></div>
                    <div class="form-group"><label for="emsf_534_899c6">المشروع</label>
                        <select name="project_id" id="emsf_534_899c6"><?php echo $proj_opt; ?></select></div>
                    <div class="form-group"><label for="emsf_535_b4304">من موقع <span class="required">*</span></label>
                        <select name="from_location_id" required id="emsf_535_b4304"><?php echo $loc_opt_from; ?></select></div>
                    <div class="form-group"><label for="emsf_536_dba8e">إلى موقع <span class="required">*</span></label>
                        <select name="to_location_id" required id="emsf_536_dba8e"><?php echo $loc_opt_to; ?></select></div>
                    <div class="form-group"><label for="emsf_537_dbb06">تاريخ الطلب <span class="required">*</span></label>
                        <input type="date" name="request_date" value="<?php echo htmlspecialchars($order['request_date'] ?? date('Y-m-d')); ?>" required id="emsf_537_dbb06"></div>
                    <div class="form-group"><label for="emsf_538_2e819">التاريخ المخطط</label>
                        <input type="date" name="planned_date" value="<?php echo htmlspecialchars($order['planned_date'] ?? ''); ?>" id="emsf_538_2e819"></div>
                    <div class="form-group"><label for="emsf_539_4a4e4">الأولوية</label>
                        <select name="priority" id="emsf_539_4a4e4"><?php foreach ($prios as $k => $v) { $sel = ($order && $order['priority'] === $k) ? ' selected' : ''; echo "<option value='$k'$sel>" . htmlspecialchars($v) . "</option>"; } ?></select></div>
                    <div class="form-group"><label for="emsf_540_85f72">المركبة الناقلة</label>
                        <select name="vehicle_id" id="emsf_540_85f72"><?php echo $veh_opt; ?></select></div>
                    <div class="form-group"><label for="emsf_541_15943">نوع الناقل</label>
                        <select name="carrier_type" id="emsf_541_15943"><option value="">—</option><?php foreach ($carriers as $k => $v) { $sel = ($order && $order['carrier_type'] === $k) ? ' selected' : ''; echo "<option value='$k'$sel>" . htmlspecialchars($v) . "</option>"; } ?></select></div>
                    <div class="form-group"><label for="emsf_542_7e4e2">المقاول الناقل</label>
                        <select name="carrier_entity_id" id="emsf_542_7e4e2"><?php echo $carrier_opt; ?></select></div>
                    <div class="form-group"><label for="emsf_543_f0948">السائق</label>
                        <select name="driver_id" id="emsf_543_f0948"><?php echo $drv_opt; ?></select></div>
                    <div class="form-group"><label for="emsf_544_93fc1">المسار</label>
                        <input type="text" name="route" value="<?php echo htmlspecialchars($order['route'] ?? ''); ?>" placeholder="عطبرة ← الموقع" id="emsf_544_93fc1"></div>
                    <div class="form-group"><label for="emsf_545_b25c5">مركز التكلفة</label>
                        <input type="text" name="analytic_cost_center" value="<?php echo htmlspecialchars($order['analytic_cost_center'] ?? ''); ?>" placeholder="المشروع/الإدارة/المكتب" id="emsf_545_b25c5"></div>
                    <div class="form-group" style="grid-column:1/-1"><label for="emsf_546_968bb">ملاحظات</label>
                        <input type="text" name="notes" value="<?php echo htmlspecialchars($order['notes'] ?? ''); ?>" id="emsf_546_968bb"></div>
                </div>
            </div>
            <?php if ($order): ?>
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group"><label for="emsf_547_7b262">كود الحركة (محسوب)</label><input type="text" value="<?php echo htmlspecialchars($order['order_no']); ?>" disabled id="emsf_547_7b262"></div>
                    <div class="form-group"><label for="emsf_548_7bc79">مدة المشروع (يوم — محسوب)</label><input type="text" value="<?php echo htmlspecialchars((string)($order['project_days'] ?? '—')); ?>" disabled id="emsf_548_7bc79"></div>
                    <div class="form-group"><label for="emsf_549_761eb">المتحمِّل (محسوب من القواعد)</label><input type="text" value="<?php echo htmlspecialchars($order['cost_bearer'] ? trs_label($bearers, $order['cost_bearer']) : '—'); ?>" disabled id="emsf_549_761eb"></div>
                    <div class="form-group"><label for="emsf_550_1b8a2">التكلفة الفعلية (USD — محسوب)</label><input type="text" value="<?php echo number_format((float)($order['actual_cost_usd'] ?? 0), 2); ?>" disabled id="emsf_550_1b8a2"></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <?php if ($can_add || $can_edit): ?><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ الرأس</button><?php endif; ?>
                <a href="transfer_orders_list.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>
    </div>

    <?php if ($order): ?>
    <!-- ════ تبويب العناصر ════ -->
    <div class="trs-tab" id="tab-lines" style="display:none">
        <?php if ($can_edit): ?>
        <form action="transfer_order_form.php?id=<?php echo intval($order_id); ?>" method="post" class="allforms allforms-visible" style="display:block">
            <input type="hidden" name="action" value="add_line">
            <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
            <div class="card-header"><h5><i class="fas fa-plus"></i> إضافة عنصر منقول</h5></div>
            <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_551_6ed12">نوع العنصر <span class="required">*</span></label>
                    <select name="item_type" required id="emsf_551_6ed12"><?php foreach ($item_types as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
                <div class="form-group"><label for="emsf_552_1c57b">المعدة</label><select name="equipment_id" id="emsf_552_1c57b"><?php echo trs_equipment_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
                <div class="form-group"><label for="emsf_553_2a720">مرجع المرفق</label><input type="text" name="attachment_ref" placeholder="جردل / جاك همر ..." id="emsf_553_2a720"></div>
                <div class="form-group"><label for="emsf_554_385e4">الصنف (مخزون)</label><select name="product_id" id="emsf_554_385e4"><?php echo trs_item_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
                <div class="form-group"><label for="emsf_555_5038e">الموظف/المشغّل</label><select name="employee_id" id="emsf_555_5038e"><?php echo trs_employee_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
                <div class="form-group"><label for="emsf_556_6252b">الكمية</label><input type="number" step="0.01" name="quantity" value="" id="emsf_556_6252b"></div>
                <div class="form-group" style="grid-column:1/-1"><label for="emsf_557_419d9">ملاحظة</label><input type="text" name="line_note" id="emsf_557_419d9"></div>
            </div></div>
            <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> إضافة</button></div>
            </div></div>
        </form>
        <?php endif; ?>
        <div class="card"><div class="card-body"><div class="table-container">
            <table class="display nowrap alltables no-datatable" style="width:100%"><thead><tr>
                <th>الإجراءات</th><th>النوع</th><th>المعدة</th><th>المرفق</th><th>الصنف</th><th>الموظف</th><th>الكمية</th><th>ملاحظة</th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead><tbody>
            <?php
            // scopedQuery: العزل بالرمز (company_id اليدوي يسقط — مسؤولية البوابة)
            $line_rows = trs_gate($is_super_admin)->scopedQuery(
                array('scope' => array('ln' => 'transfer_lines'),
                      'enrich' => array('e' => 'equipments', 'pi' => 'proc_item', 'em' => 'employees')),
                "SELECT ln.*, e.code AS ecode, pi.name AS pname, em.name AS emname
                 FROM transfer_lines ln
                 LEFT JOIN equipments e ON e.id = ln.equipment_id
                 LEFT JOIN proc_item pi ON pi.id = ln.product_id
                 LEFT JOIN employees em ON em.id = ln.employee_id
                 WHERE {TENANT_SCOPE} AND ln.order_id = ? ORDER BY ln.id",
                array($order_id)
            );
            foreach ($line_rows as $x) {
                echo "<tr><td><div class='action-btns'>";
                if ($can_delete) echo "<form action='transfer_order_form.php?id=" . intval($order_id) . "' method='post' style='display:inline' onsubmit='return confirm(\"حذف العنصر؟\")'><input type='hidden' name='action' value='del_line'><input type='hidden' name='id' value='" . intval($order_id) . "'><input type='hidden' name='line_id' value='" . intval($x['id']) . "'><button class='action-btn delete' title='حذف'><i class='fas fa-trash-alt'></i></button></form>";
                echo "</div></td>";
                echo "<td>" . htmlspecialchars(trs_label($item_types, $x['item_type'])) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['ecode'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['attachment_ref'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['pname'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['emname'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['quantity'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['note'] ?? '')) . "</td></tr>";
            }
            ?>
            </tbody></table>
        </div></div></div>
    </div>

    <!-- ════ تبويب التكاليف ════ -->
    <div class="trs-tab" id="tab-costs" style="display:none">
        <?php if ($can_edit): ?>
        <form action="transfer_order_form.php?id=<?php echo intval($order_id); ?>" method="post" class="allforms allforms-visible" style="display:block">
            <input type="hidden" name="action" value="add_cost">
            <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
            <div class="card-header"><h5><i class="fas fa-plus"></i> إضافة بند تكلفة</h5></div>
            <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_558_138a1">نوع التكلفة <span class="required">*</span></label>
                    <select name="cost_type" required id="emsf_558_138a1"><?php foreach ($cost_types as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
                <div class="form-group"><label for="emsf_559_3b97e">المبلغ (محلي) <span class="required">*</span></label><input type="number" step="0.01" name="amount_local" required id="emsf_559_3b97e"></div>
                <div class="form-group"><label for="emsf_560_64439">العملة</label><select name="currency" id="emsf_560_64439"><?php foreach ($currencies as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
                <div class="form-group"><label for="emsf_561_21354">سعر الصرف → USD</label><input type="number" step="0.000001" name="fx_rate" placeholder="مثال: 0.00166" id="emsf_561_21354"></div>
                <div class="form-group"><label for="emsf_562_00122">المتحمِّل <span class="required">*</span></label>
                    <select name="cost_bearer" required id="emsf_562_00122"><?php foreach ($bearers as $k => $v) { $sel = ($order['cost_bearer'] === $k) ? ' selected' : ''; echo "<option value='$k'$sel>" . htmlspecialchars($v) . "</option>"; } ?></select></div>
                <div class="form-group"><label for="emsf_563_d49af">مركز التكلفة</label><input type="text" name="cl_center" value="<?php echo htmlspecialchars($order['analytic_cost_center'] ?? ''); ?>" id="emsf_563_d49af"></div>
            </div></div>
            <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> إضافة</button></div>
            </div></div>
        </form>
        <?php endif; ?>
        <div class="card"><div class="card-body"><div class="table-container">
            <table class="display nowrap alltables no-datatable" style="width:100%"><thead><tr>
                <th>الإجراءات</th><th>النوع</th><th>المبلغ المحلي</th><th>العملة</th><th>سعر الصرف</th><th>USD</th><th>المتحمِّل</th><th>مركز التكلفة</th>
            </tr></thead><tbody>
            <?php
            $cost_rows = trs_gate($is_super_admin)->select('transfer_cost_lines', array(
                'where' => array('order_id' => $order_id), 'orderBy' => 'id',
            ));
            $sum = 0;
            foreach ($cost_rows as $x) {
                $sum += (float)$x['amount_usd'];
                echo "<tr><td><div class='action-btns'>";
                if ($can_delete) echo "<form action='transfer_order_form.php?id=" . intval($order_id) . "' method='post' style='display:inline' onsubmit='return confirm(\"حذف البند؟\")'><input type='hidden' name='action' value='del_cost'><input type='hidden' name='id' value='" . intval($order_id) . "'><input type='hidden' name='cost_id' value='" . intval($x['id']) . "'><button class='action-btn delete' title='حذف'><i class='fas fa-trash-alt'></i></button></form>";
                echo "</div></td>";
                echo "<td>" . htmlspecialchars(trs_label($cost_types, $x['cost_type'])) . "</td>";
                echo "<td>" . number_format((float)$x['amount_local'], 2) . "</td>";
                echo "<td>" . htmlspecialchars((string)$x['currency']) . "</td>";
                echo "<td>" . ($x['fx_rate'] !== null ? htmlspecialchars((string)$x['fx_rate']) : '—') . "</td>";
                echo "<td>" . number_format((float)$x['amount_usd'], 2) . "</td>";
                echo "<td>" . htmlspecialchars(trs_label($bearers, $x['cost_bearer'])) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['analytic_cost_center'] ?? '—')) . "</td></tr>";
            }
            echo "<tr style='font-weight:700;background:#fff8e6'><td colspan='5' style='text-align:left'>الإجمالي (USD)</td><td>" . number_format($sum, 2) . "</td><td colspan='2'></td></tr>";
            ?>
            </tbody></table>
        </div></div></div>
    </div>

    <!-- ════ تبويب التصاريح ════ -->
    <div class="trs-tab" id="tab-permits" style="display:none">
        <?php if ($can_edit): ?>
        <form action="transfer_order_form.php?id=<?php echo intval($order_id); ?>" method="post" class="allforms allforms-visible" style="display:block">
            <input type="hidden" name="action" value="add_permit">
            <input type="hidden" name="id" value="<?php echo intval($order_id); ?>">
            <div class="card-header"><h5><i class="fas fa-plus"></i> إضافة تصريح</h5></div>
            <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
                <div class="form-group"><label for="emsf_564_08e0b">نوع التصريح <span class="required">*</span></label>
                    <select name="permit_type" required id="emsf_564_08e0b"><?php foreach ($permit_types as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
                <div class="form-group"><label for="emsf_565_09db3">الجهة المصدِرة</label><input type="text" name="authority" id="emsf_565_09db3"></div>
                <div class="form-group"><label for="emsf_566_7ac79">تاريخ الإصدار</label><input type="date" name="issue_date" id="emsf_566_7ac79"></div>
                <div class="form-group"><label for="emsf_567_415f8">تاريخ الانتهاء</label><input type="date" name="expiry_date" id="emsf_567_415f8"></div>
                <div class="form-group"><label for="emsf_568_2ebc8">الحالة</label>
                    <select name="permit_state" id="emsf_568_2ebc8"><?php foreach ($permit_states as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            </div></div>
            <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> إضافة</button></div>
            </div></div>
        </form>
        <?php endif; ?>
        <div class="card"><div class="card-body"><div class="table-container">
            <table class="display nowrap alltables no-datatable" style="width:100%"><thead><tr>
                <th>الإجراءات</th><th>النوع</th><th>الجهة</th><th>الإصدار</th><th>الانتهاء</th><th>الحالة</th>
            </tr></thead><tbody>
            <?php
            $permit_rows = trs_gate($is_super_admin)->select('transfer_permits', array(
                'where' => array('order_id' => $order_id), 'orderBy' => 'id',
            ));
            foreach ($permit_rows as $x) {
                echo "<tr><td><div class='action-btns'>";
                if ($can_delete) echo "<form action='transfer_order_form.php?id=" . intval($order_id) . "' method='post' style='display:inline' onsubmit='return confirm(\"حذف التصريح؟\")'><input type='hidden' name='action' value='del_permit'><input type='hidden' name='id' value='" . intval($order_id) . "'><input type='hidden' name='permit_id' value='" . intval($x['id']) . "'><button class='action-btn delete' title='حذف'><i class='fas fa-trash-alt'></i></button></form>";
                echo "</div></td>";
                echo "<td>" . htmlspecialchars(trs_label($permit_types, $x['permit_type'])) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['authority'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['issue_date'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['expiry_date'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars(trs_label($permit_states, $x['state'])) . "</td></tr>";
            }
            ?>
            </tbody></table>
        </div></div></div>
    </div>

    <!-- ════ تبويب الأحداث ════ -->
    <div class="trs-tab" id="tab-events" style="display:none">
        <div class="card"><div class="card-body"><div class="table-container">
            <table class="display nowrap alltables no-datatable" style="width:100%"><thead><tr>
                <th>التاريخ</th><th>النوع</th><th>الوصف</th><th>قبل</th><th>بعد</th>
            </tr></thead><tbody>
            <?php
            $event_rows = trs_gate($is_super_admin)->select('transfer_events', array(
                'where' => array('order_id' => $order_id), 'orderBy' => 'id DESC',
            ));
            foreach ($event_rows as $x) {
                echo "<tr><td>" . htmlspecialchars((string)$x['created_at']) . "</td>";
                echo "<td>" . htmlspecialchars((string)$x['event_type']) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['body'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['old_value'] ?? '—')) . "</td>";
                echo "<td>" . htmlspecialchars((string)($x['new_value'] ?? '—')) . "</td></tr>";
            }
            ?>
            </tbody></table>
        </div></div></div>
    </div>
    <?php endif; ?>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script>
(function () {
    var active = <?php echo json_encode($active_tab); ?>;
    function showTab(t) {
        document.querySelectorAll('.trs-tab').forEach(function (el) { el.style.display = 'none'; });
        var target = document.getElementById('tab-' + t);
        if (target) target.style.display = 'block'; else { var h = document.getElementById('tab-header'); if (h) h.style.display = 'block'; }
        document.querySelectorAll('.trs-tab-btn').forEach(function (b) { b.style.opacity = (b.getAttribute('data-tab') === t) ? '1' : '0.6'; });
    }
    document.querySelectorAll('.trs-tab-btn').forEach(function (b) {
        b.addEventListener('click', function () { showTab(b.getAttribute('data-tab')); });
    });
    showTab(active);
})();
</script>
</body>
</html>
