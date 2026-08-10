<?php
/**
 * Procurement/issue_proc.php — الصرف وتحميل التكلفة + سلسلة عهدة الصرف
 *   (proc_issue + proc_issue_line + proc_stock_move + proc_custody) — §15.8 / §15.9 / §11.
 *
 * عند الحفظ لكل سطر: (1) حركة مخزون صرف في proc_stock_move، (2) سجل عهدة صرف في proc_custody
 * يحمل أبعاد التكلفة (معدة/مشروع/أمر صيانة). قاعدة §15.8: لا صرف بلا مستلِم وبُعد تحميلٍ واحدٍ على الأقل.
 * قراءة مراجع المعدات/المشاريع/أوامر الصيانة قراءةً فقط — لا كتابة على أي جدول قائم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/issue_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الصرف ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$states     = proc_issue_states();
$maint_types = array('وقائية', 'تصحيحية', 'رأسمالية');

// خيارات أوامر الصيانة (قراءة فقط من mnt_order) — عبر البوابة والتسمية في PHP
$mnt_order_option_rows = proc_gate($is_super_admin)->select('mnt_order', array(
    'columns' => array('id', 'code'),
    'orderBy' => 'id DESC',
));
foreach ($mnt_order_option_rows as &$mor) {
    $mc = (string) $mor['code'];
    $mor['label'] = ($mc === '') ? ('#' . intval($mor['id'])) : $mc;
}
unset($mor);

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['holder_name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('issue_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('issue_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('issue_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $warehouse_id = ($_POST['warehouse_id'] ?? '') !== '' ? intval($_POST['warehouse_id']) : null;
    $holder_name = trim($_POST['holder_name'] ?? '');
    $issue_date = ($_POST['issue_date'] ?? '') !== '' ? trim($_POST['issue_date']) : null;
    $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? intval($_POST['equipment_id']) : null;
    $project_id   = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
    $maintenance_order_id = ($_POST['maintenance_order_id'] ?? '') !== '' ? intval($_POST['maintenance_order_id']) : null;
    $maint_type = trim($_POST['maint_type'] ?? '');
    $contract_id = ($_POST['contract_id'] ?? '') !== '' ? intval($_POST['contract_id']) : null;
    $supplier_id = ($_POST['supplier_id'] ?? '') !== '' ? intval($_POST['supplier_id']) : null;
    $state = trim($_POST['state'] ?? 'مسودة');
    $notes = trim($_POST['notes'] ?? '');

    // قاعدة §15.8: لا صرف بلا مستلِم وبُعد تحميلٍ واحدٍ على الأقل
    if ($holder_name === '') { ems_gov_flash_redirect('issue_proc.php', 'المستلِم إلزامي ❌', 'GOV-FAIL-409', ''); exit(); }
    if ($equipment_id === null && $project_id === null && $maintenance_order_id === null) {
        ems_gov_flash_redirect('issue_proc.php', 'لا صرف بلا بُعد تحميل (معدة/مشروع/أمر) ❌', 'GOV-FAIL-409', ''); exit();
    }
    if ($maint_type !== '' && !in_array($maint_type, $maint_types, true)) { $maint_type = ''; }
    if (!in_array($state, $states, true)) { $state = 'مسودة'; }

    // احسب الإجمالي
    $item_ids = $_POST['line_item_id'] ?? array();
    $item_names = $_POST['line_item_name'] ?? array();
    $qtys = $_POST['line_qty'] ?? array();
    $costs = $_POST['line_cost'] ?? array();

    // ① طبقةُ التكاليف: صنفُ الكتالوج يُصرف **بالمتوسط المرجح** لا باليدوي —
    // التكلفةُ من الدفتر لا من ذاكرة المستخدم (EMS_PROC_COSTING=off يعيد اليدوي).
    // السطرُ الحر (بلا صنف) يبقى بتكلفته اليدوية — لا متوسطَ له أصلًا.
    require_once __DIR__ . '/../app/Services/Procurement/ProcCostingService.php';
    if (\App\Services\Procurement\ProcCostingService::enabled()) {
        $costing_gate = proc_gate(false);
        for ($i = 0; $i < count($item_names); $i++) {
            $iid = (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : 0;
            if ($iid <= 0) { continue; }
            $avg = \App\Services\Procurement\ProcCostingService::avgCostOf($costing_gate, $iid);
            if ($avg !== null) { $costs[$i] = $avg; }
        }
    }

    $total = 0.0;
    for ($i = 0; $i < count($item_names); $i++) {
        if (trim($item_names[$i] ?? '') === '') { continue; }
        $total += ((float)($qtys[$i] ?? 0)) * ((float)($costs[$i] ?? 0));
    }

    // K9-M1: العملية النطاقية المترابطة كاملةً في معاملةٍ مُدارةٍ واحدة
    // (runInTransaction — عقد البوابة §9): أب + مسح ثلاثيّ الأبناء عبر
    // replaceChildren (تتبنّى المعاملة) + إدراج سطر←حركة←عهدة بترابط line_id
    // الوليد — ذرّية مشتركة وحُرّاس أحياء في كل عملية.
    // ملاحظة stock_move: المسح بref_id+الشركة (القناة أحادية عمود الأب)؛
    // الثابت الكودي: هذه الشاشة هي الكاتب الوحيد للجدول وref_type دائمًا
    // 'proc_issue' — عند ظهور كاتبٍ بنوعٍ آخر يلزم توسيع القناة بشرطٍ معلن.
    // ORG-13 · حارس الأذونات: خروج مواد من الموقع/المخزن (ORG-01 §5-⑥)
    require_once dirname(__DIR__) . '/includes/permit_gate.php';
    $pg_site = $project_id !== null ? ems_default_site_of_project($conn, $company_id, $project_id) : 0;
    $pg = ems_permit_gate($conn, $company_id, 'material_site_exit',
        'ISSUE:' . ($id > 0 ? $id : 'new'), $pg_site, intval($current_user_id ?? 0));
    if (!$pg['ok']) { ems_gov_flash_redirect('issue_proc.php', $pg['reason'] . ' ❌', 'GOV-FAIL-409', ''); exit(); }

    $parent = array(
        'warehouse_id' => $warehouse_id, 'holder_name' => $holder_name, 'issue_date' => $issue_date,
        'equipment_id' => $equipment_id, 'project_id' => $project_id,
        'maintenance_order_id' => $maintenance_order_id, 'maint_type' => $maint_type,
        'contract_id' => $contract_id, 'supplier_id' => $supplier_id,
        'total_cost' => $total, 'state' => $state, 'notes' => $notes,
    );
    try {
        $issue_saved_id = proc_gate(false)->runInTransaction(function ($g) use (
            $is_editing, $id, $parent, $company_id, $current_user_id, $conn,
            $item_ids, $item_names, $qtys, $costs,
            $warehouse_id, $holder_name, $issue_date, $equipment_id, $project_id, $maintenance_order_id
        ) {
            if ($is_editing) {
                $g->update('proc_issue', $parent, array('id' => $id, 'is_deleted' => 0));
                $issue_id = $id;
            } else {
                $parent['code'] = proc_gen_code($conn, 'proc_issue', 'PRC-ISS', $company_id);
                $parent['created_by'] = $current_user_id;
                $issue_id = $g->insert('proc_issue', $parent);
            }
            // مسح الأبناء الثلاثة (تتبنّى معاملة المدير — لا commit ضمني)
            $g->replaceChildren('proc_issue', $issue_id, 'proc_issue_line', 'issue_id', array(), 'issue lines clear');
            $g->replaceChildren('proc_issue', $issue_id, 'proc_custody', 'issue_id', array(), 'issue custody clear');
            // الحذفُ مقيَّدٌ بالنوع والرقم معًا — ref_id وحده يصيب حركاتِ استلامٍ تشاركه الرقم
            proc_stock_moves_clear($g, 'proc_issue', $issue_id);

            // سطر ← حركة (إن ارتبط بصنف) ← عهدة بline_id الوليد
            for ($i = 0; $i < count($item_names); $i++) {
                $iname = trim($item_names[$i] ?? '');
                if ($iname === '') { continue; }
                $iid = (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null;
                $qty = (float)($qtys[$i] ?? 1);
                $cost = (float)($costs[$i] ?? 0);

                $line_id = $g->insert('proc_issue_line', array(
                    'issue_id' => $issue_id, 'item_id' => $iid, 'item_name' => $iname,
                    'qty' => $qty, 'unit_cost' => $cost, 'subtotal' => $qty * $cost,
                ));
                if ($iid !== null) {
                    $g->insert('proc_stock_move', array(
                        'item_id' => $iid, 'warehouse_id' => $warehouse_id,
                        'move_type' => 'صرف', 'qty' => $qty,
                        // تجميدُ متوسط لحظة الصرف في الحركة — دفترُ قيمةٍ دائم
                        'unit_cost' => ($cost > 0 ? round($cost, 4) : null),
                        'ref_type' => 'proc_issue', 'ref_id' => $issue_id,
                        'note' => 'صرف ' . $iname, 'created_by' => $current_user_id,
                    ));
                }
                $g->insert('proc_custody', array(
                    'issue_id' => $issue_id, 'issue_line_id' => $line_id,
                    'item_id' => $iid, 'item_name' => $iname, 'holder_name' => $holder_name,
                    'transfer_date' => $issue_date, 'equipment_id' => $equipment_id,
                    'project_id' => $project_id, 'maintenance_order_id' => $maintenance_order_id,
                    'qty_issued' => $qty, 'qty_returned' => 0, 'qty_consumed' => $qty,
                    'state' => 'مصروفة', 'created_by' => $current_user_id,
                ));
            }
            return $issue_id;
        }, 'issue save ' . ($is_editing ? 'edit#' . $id : 'new'));
    } catch (\Throwable $e) {
        error_log('issue_proc save rolled back: ' . $e->getMessage());
        ems_gov_flash_redirect('issue_proc.php', 'تعذّر الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
    // ① أثرُ الصرف المالي على أبعاده (معدة/مشروع/أمر) — من منبعه بعطالته.
    // لا يرمي: الصرفُ حقيقةٌ تشغيليةٌ لا تُفقد لتعثّرِ نشرٍ مالي.
    proc_publish_issue_cost($conn, intval($issue_saved_id ?? 0), $current_user_id);
    // E-17 (UX-09 §2.2): بعد الصرف — الصنفُ الذي هبط رصيدُه لحدِّه الأدنى
    // يُعلَن **بزرِّ «طلبُ شراءٍ بمرجع الأمر»** لا رسالةً تُنسى
    require_once __DIR__ . '/../app/Services/Procurement/ProcReorderService.php';
    $shortages = array();
    for ($i = 0; $i < count($item_names); $i++) {
        $iid = (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null;
        if ($iid === null) { continue; }
        $bal = \App\Services\Procurement\ProcReorderService::balance($conn, $company_id, $iid);
        $it = proc_gate(false)->selectOne('proc_item', array('columns' => array('name', 'min_qty'),
            'where' => array('id' => $iid)));
        if ($it && (float) $it['min_qty'] > 0 && $bal <= (float) $it['min_qty']) {
            $shortages[] = array('item_id' => $iid, 'name' => (string) $it['name'], 'balance' => $bal);
        }
    }
    $_SESSION['proc_shortage_flash'] = array('issue_ref' => 'ISS#' . intval($issue_saved_id ?? 0),
        'items' => $shortages);
    ems_gov_redirect("Location: issue_proc.php?msg=" . ($is_editing ? 'تم+تعديل+الصرف+بنجاح+✅' : 'تم+الصرف+بنجاح+✅')); exit();
}

// ── حذف ناعم (يبطل الرأس؛ الحركات/العهدة تبقى للأثر ما لم يُعاد الحفظ) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('issue_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        proc_gate(false)->runInTransaction(function ($g) use ($delete_id) {
            $g->softDelete('proc_issue', $delete_id);
            // اعكس أثر المخزون والعهدة لهذا الصرف — ذرّيًا مع الحذف الناعم
            // (الحركاتُ بحذفٍ مقيَّدِ النوع — لا ref_id وحده)
            proc_stock_moves_clear($g, 'proc_issue', $delete_id);
            $g->replaceChildren('proc_issue', $delete_id, 'proc_custody', 'issue_id', array(), 'issue delete: custody reversal');
        }, 'issue delete#' . $delete_id);
    } catch (\Throwable $e) {
        error_log('issue_proc delete rolled back: ' . $e->getMessage());
    }
    ems_gov_flash_redirect('issue_proc.php', 'تم حذف الصرف بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── تحميل للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $edit = proc_gate($is_super_admin)->selectOne('proc_issue', array('where' => array('id' => $eid)));
    if ($edit) {
        $edit_lines = proc_gate($is_super_admin)->select('proc_issue_line', array(
            'where' => array('issue_id' => $eid), 'orderBy' => 'id ASC',
        ));
    }
}

$page_title = 'إيكوبيشن | صرف المخزون والعهدة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/** صف سطر صرف. */
function proc_iss_line_row($conn, $is_super_admin, $company_id, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $cost = $line ? htmlspecialchars((string)$line['unit_cost'], ENT_QUOTES) : '0';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    return '<div class="proc-line form-grid" style="align-items:end;margin-bottom:8px">'
        . '<div class="form-group"><label for="emsf_380_a0417">الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item" id="emsf_380_a0417">' . $opts . '</select></div>'
        . '<div class="form-group"><label for="emsf_381_fafc5">اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" value="' . $iname . '" required id="emsf_381_fafc5"></div>'
        . '<div class="form-group"><label for="emsf_382_16b8d">الكمية</label><input type="number" step="0.01" name="line_qty[]" class="line-qty" value="' . $qty . '" id="emsf_382_16b8d"></div>'
        . '<div class="form-group"><label for="emsf_383_3f8fe">تكلفة الوحدة <small>(صنفُ الكتالوج يُحتسب آليًّا بالمتوسط المرجح)</small></label><input type="number" step="0.01" name="line_cost[]" class="line-cost" value="' . $cost . '" id="emsf_383_3f8fe"></div>'
        . '<div class="form-group"><button type="button" class="btn-secondary removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-issue-main ems-unified-page-shell">
    <?php
    $header_title = 'صرف المخزون والعهدة';
    $header_icon  = 'fa fa-hand-holding-box';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'صرف جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <?php
    // E-17: أصنافٌ هبطت لحدّها بعد آخر صرف — **زرُّ طلب شراءٍ بمرجع الأمر**
    if (!empty($_SESSION['proc_shortage_flash']['items'])):
        $psf = $_SESSION['proc_shortage_flash'];
        unset($_SESSION['proc_shortage_flash']);
    ?>
    <div class="card" style="border-inline-start:4px solid #c62828"><div class="card-body">
        <strong><i class="fa fa-triangle-exclamation"></i> نقصُ مخزونٍ بعد الصرف
            (<?php echo htmlspecialchars((string)$psf['issue_ref']); ?>):</strong>
        <?php foreach ($psf['items'] as $sh): ?>
            <a class="btn-primary" style="margin:0 4px"
               href="requests_proc.php?prefill_item=<?php echo intval($sh['item_id']); ?>&need_source=<?php
                   echo rawurlencode('نقص مخزون'); ?>&source_ref=<?php echo rawurlencode((string)$psf['issue_ref']); ?>">
                <i class="fa fa-cart-plus"></i> طلبُ شراءٍ بمرجع الأمر —
                <?php echo htmlspecialchars($sh['name'] . ' (الرصيد ' . $sh['balance'] . ')'); ?></a>
        <?php endforeach; ?>
    </div></div>
    <?php endif; ?>

    <form id="procForm" action="issue_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل صرف' : 'صرف جديد'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emsf_384_d97b8">المخزن المصروف منه</label>
                        <select name="warehouse_id" id="emsf_384_d97b8"><?php echo proc_warehouses_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['warehouse_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_385_945c4">المستلِم <span class="required">*</span></label>
                        <input type="text" name="holder_name" value="<?php echo $edit ? htmlspecialchars((string)$edit['holder_name']) : ''; ?>" required id="emsf_385_945c4">
                    </div>
                    <div class="form-group">
                        <label for="emsf_386_49337">تاريخ الصرف</label>
                        <input type="date" name="issue_date" value="<?php echo $edit ? htmlspecialchars((string)$edit['issue_date']) : ''; ?>" id="emsf_386_49337">
                    </div>
                    <div class="form-group">
                        <label for="emsf_387_0ed6d">المعدة <small>(بُعد تكلفة)</small></label>
                        <select name="equipment_id" id="emsf_387_0ed6d"><?php echo proc_equipment_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['equipment_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_388_2789a">المشروع <small>(بُعد تكلفة)</small></label>
                        <select name="project_id" id="emsf_388_2789a"><?php echo proc_project_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['project_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_389_14730">أمر الصيانة <small>(بُعد تكلفة)</small></label>
                        <select name="maintenance_order_id" id="emsf_389_14730"><?php echo proc_options_from_rows($mnt_order_option_rows, $edit ? intval($edit['maintenance_order_id']) : 0, '— بلا أمر صيانة —'); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_390_ab006">نوع الصيانة</label>
                        <select name="maint_type" id="emsf_390_ab006">
                            <option value="">— بلا —</option>
                            <?php foreach ($maint_types as $mt): $sel = ($edit && $edit['maint_type'] === $mt) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($mt); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($mt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_391_7e80d">المورد التشغيلي</label>
                        <select name="supplier_id" id="emsf_391_7e80d"><?php echo proc_suppliers_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['supplier_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_392_9d977">معرّف العقد (اختياري)</label>
                        <input type="number" name="contract_id" value="<?php echo $edit && $edit['contract_id'] !== null ? intval($edit['contract_id']) : ''; ?>" id="emsf_392_9d977">
                    </div>
                    <div class="form-group">
                        <label for="emsf_393_baf02">الحالة</label>
                        <select name="state" id="emsf_393_baf02">
                            <?php foreach ($states as $st): $sel = ($edit && $edit['state'] === $st) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="emsf_394_01563">ملاحظات</label>
                        <input type="text" name="notes" value="<?php echo $edit ? htmlspecialchars((string)$edit['notes']) : ''; ?>" id="emsf_394_01563">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="card-header"><h5><i class="fas fa-list"></i> الأصناف المصروفة</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_iss_line_row($conn, $is_super_admin, $company_id, $l); }
                    } else {
                        echo proc_iss_line_row($conn, $is_super_admin, $company_id, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn" style="margin-top:6px"><i class="fas fa-plus"></i> إضافة سطر</button>
                <div style="margin-top:10px;font-weight:700">إجمالي التكلفة: <span id="issTotal">0.00</span></div>
                <p style="margin-top:6px;color:#666">القاعدة: لا يُصرف صنفٌ دون مستلِمٍ وبُعد تحميلٍ واحدٍ على الأقل (معدة/مشروع/أمر صيانة).</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ الصرف</button>
                <a href="issue_proc.php" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_iss_line_row($conn, $is_super_admin, $company_id, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>كود المعدة</th><th>المستلِم</th><th>تاريخ الصرف</th><th>المعدة</th>
                    <th>نوع الصيانة</th><th>إجمالي التكلفة</th><th>الحالة</th><th>عدد الأصناف</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم السند</th>
                    <th class="ems-fn-th" data-fn="1">المخزن</th>
                    <th class="ems-fn-th" data-fn="1">الجهة الطالبة</th>
                    <th class="ems-fn-th" data-fn="1">مرجع الطلب</th>
                    <th class="ems-fn-th" data-fn="1">أمر العمل أو المشروع</th>
                    <th class="ems-fn-th" data-fn="1">الوحدة التعاقدية</th>
                    <th class="ems-fn-th" data-fn="1">قراءة العدّاد</th>
                    <th class="ems-fn-th" data-fn="1">رقم الصنف</th>
                    <th class="ems-fn-th" data-fn="1">اسم الصنف</th>
                    <th class="ems-fn-th" data-fn="1">الكمية المصروفة</th>
                    <th class="ems-fn-th" data-fn="1">الوحدة</th>
                    <th class="ems-fn-th" data-fn="1">تكلفة الوحدة</th>
                    <th class="ems-fn-th" data-fn="1">صفة المستلِم</th>
                    <th class="ems-fn-th none" data-fn="1">نوع الصرف</th>
                    <th class="ems-fn-th none" data-fn="1">صرفه</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                    <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    <th class="ems-gov-th none" data-gov="loaded_cost_center" data-slice="3" title="المركز الذي حُمّلت عليه التكلفة">مركز التكلفة المحمَّل</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // ترطيب ثنائي: الصرفيات ثم بيانات المعدات وعدّ السطور بجلبٍ واحد لكلٍّ
                    $gv = proc_gate($is_super_admin);
                    $issue_rows = $gv->select('proc_issue', array(
                        'columns' => array('id', 'code', 'holder_name', 'issue_date', 'maint_type', 'total_cost', 'state', 'equipment_id'),
                        'orderBy' => 'id DESC',
                    ));
                    $eq_map = array(); $line_counts = array();
                    if (!empty($issue_rows)) {
                        $eids = array(); $iids = array();
                        foreach ($issue_rows as $ir) {
                            if ($ir['equipment_id'] !== null) { $eids[intval($ir['equipment_id'])] = true; }
                            $iids[] = intval($ir['id']);
                        }
                        if (!empty($eids)) {
                            foreach ($gv->select('equipments', array(
                                'columns' => array('id', 'code', 'name'),
                                'whereRaw' => 'id IN (' . implode(',', array_keys($eids)) . ')',
                            )) as $er) { $eq_map[intval($er['id'])] = $er; }
                        }
                        foreach ($gv->select('proc_issue_line', array(
                            'columns' => array('issue_id'),
                            'whereRaw' => 'issue_id IN (' . implode(',', $iids) . ')',
                        )) as $lr) {
                            $liid = intval($lr['issue_id']);
                            $line_counts[$liid] = ($line_counts[$liid] ?? 0) + 1;
                        }
                    }
                    { foreach ($issue_rows as $row) {
                        $eq = ($row['equipment_id'] !== null && isset($eq_map[intval($row['equipment_id'])]))
                            ? $eq_map[intval($row['equipment_id'])] : null;
                        $row['equip_code'] = $eq ? $eq['code'] : null;
                        $row['equip_name'] = $eq ? $eq['name'] : null;
                        $row['line_count'] = $line_counts[intval($row['id'])] ?? 0;
                        $equip = trim((string)($row['equip_code'] ?? '') . ' ' . (string)($row['equip_name'] ?? ''));
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='?edit_id=" . intval($row['id']) . "' class='action-btn edit' title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['holder_name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['issue_date'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars($equip) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['maint_type'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format((float)$row['total_cost'], 2)) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['state']) . "</span></td>";
                        echo "<td>" . intval($row['line_count']) . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
(function () {
    function recalcTotal() {
        var t = 0;
        $('#linesBody .proc-line').each(function () {
            var q = parseFloat($(this).find('.line-qty').val()) || 0;
            var c = parseFloat($(this).find('.line-cost').val()) || 0;
            t += q * c;
        });
        $('#issTotal').text(t.toFixed(2));
    }
    $(document).ready(function () {
        $('#procTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) { toggleBtn.addEventListener('click', function () { $('#procForm').toggleClass('allforms-visible'); }); }
        $('#addLine').on('click', function () {
            var tpl = document.getElementById('lineTemplate');
            document.getElementById('linesBody').appendChild(document.importNode(tpl.content, true));
        });
        $(document).on('click', '.removeLine', function () {
            var rows = $('#linesBody .proc-line');
            if (rows.length > 1) { $(this).closest('.proc-line').remove(); }
            else { $(this).closest('.proc-line').find('input,select').val(''); }
            recalcTotal();
        });
        $(document).on('input', '.line-qty, .line-cost', recalcTotal);
        $(document).on('change', '.line-item', function () {
            var txt = $(this).find('option:selected').text().trim();
            var $name = $(this).closest('.proc-line').find('.line-name');
            if (txt && !$name.val()) {
                var parts = txt.split(' — ');
                $name.val(parts.length > 1 ? parts[1] : txt);
            }
        });
        recalcTotal();
    });
})();
</script>
</body>
</html>
