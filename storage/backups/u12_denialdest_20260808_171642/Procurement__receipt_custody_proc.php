<?php
/**
 * Procurement/receipt_custody_proc.php — عهدة الاستلام المؤقت (proc_receipt_custody + proc_receipt_line) — §15.3.
 * تتبّع المواد من المورد (غالباً خارج المخزن) حتى الوجهة النهائية. رأس + سطور. شاشة جديدة مستقلة.
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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/receipt_custody_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض عهدة الاستلام ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$states       = proc_receipt_states();
$destinations = proc_destinations();

// خيارات أوامر الشراء (للربط) — عبر البوابة والتسمية في PHP
$order_option_rows = proc_gate($is_super_admin)->select('proc_order', array(
    'columns' => array('id', 'code'),
    'orderBy' => 'id DESC',
));
foreach ($order_option_rows as &$oor) {
    $oc = (string) $oor['code'];
    $oor['label'] = ($oc === '') ? ('#' . intval($oor['id'])) : $oc;
}
unset($oor);

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expected_destination'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('receipt_custody_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('receipt_custody_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('receipt_custody_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $holder_name = trim($_POST['holder_name'] ?? '');
    $receipt_date = ($_POST['receipt_date'] ?? '') !== '' ? trim($_POST['receipt_date']) : null;
    $supplier_id = ($_POST['supplier_id'] ?? '') !== '' ? intval($_POST['supplier_id']) : null;
    $order_id    = ($_POST['order_id'] ?? '') !== '' ? intval($_POST['order_id']) : null;
    $receipt_location = trim($_POST['receipt_location'] ?? '');
    $warehouse_id = ($_POST['warehouse_id'] ?? '') !== '' ? intval($_POST['warehouse_id']) : null;
    $expected_destination = trim($_POST['expected_destination'] ?? 'مخزن');
    $state = trim($_POST['state'] ?? 'مستلَمة');
    $notes = trim($_POST['notes'] ?? '');

    if ($holder_name === '') { ems_gov_flash_redirect('receipt_custody_proc.php', 'اسم المستلِم إلزامي ❌', 'GOV-FAIL-409', ''); exit(); }
    if (!in_array($expected_destination, $destinations, true)) { $expected_destination = 'مخزن'; }
    if (!in_array($state, $states, true)) { $state = 'مستلَمة'; }
    // §15.6: الوجهةُ المخزنية بلا مخزنِ إدخالٍ = رصيدٌ لا يتحرك — تُرفض
    if ($expected_destination === 'مخزن' && !$warehouse_id) {
        ems_gov_flash_redirect('receipt_custody_proc.php', 'حدد مخزن الإدخال — الوجهة مخزن ❌', 'GOV-FAIL-409', ''); exit();
    }

    // K9-M1: الأب عبر البوابة، والسطور عبر قناة replaceChildren (عقد البوابة §8 —
    // النمط المبرَّر: تحرير أبٍ يعيد كتابة تفاصيله). حذف/إدراج السطور ذرّيٌّ داخل
    // القناة؛ نافذة «رأسٌ محدَّث وسطور سابقة» الضيقة بين العمليتين لا تُفقد فيها
    // السطور أبدًا (تبقى القديمة كاملةً حتى ينجح الاستبدال ذرّيًا).
    $parent = array(
        'holder_name' => $holder_name, 'receipt_date' => $receipt_date,
        'supplier_id' => $supplier_id, 'order_id' => $order_id,
        'receipt_location' => $receipt_location, 'warehouse_id' => $warehouse_id,
        'expected_destination' => $expected_destination,
        'state' => $state, 'notes' => $notes,
    );
    $item_ids = $_POST['line_item_id'] ?? array();
    $item_names = $_POST['line_item_name'] ?? array();
    $qtys = $_POST['line_qty'] ?? array();
    $line_rows = array();
    for ($i = 0; $i < count($item_names); $i++) {
        $iname = trim($item_names[$i] ?? '');
        if ($iname === '') { continue; }
        $line_rows[] = array(
            'item_id'   => (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null,
            'item_name' => $iname,
            'qty'       => (float)($qtys[$i] ?? 1),
        );
    }
    // ORG-13 · حارس الأذونات: دخول مشتريات للموقع/المخزن (ORG-01 §5-⑤)
    if (!$is_editing) {
        require_once dirname(__DIR__) . '/includes/permit_gate.php';
        $pg = ems_permit_gate($conn, $company_id, 'material_site_entry',
            'RCPT:' . ($order_id ?: 'direct'), 0, intval($current_user_id ?? 0));
        if (!$pg['ok']) { ems_gov_flash_redirect('receipt_custody_proc.php', $pg['reason'] . ' ❌', 'GOV-FAIL-409', ''); exit(); }
    }

    try {
        proc_gate(false)->runInTransaction(function ($g) use (
            $is_editing, $id, $parent, $company_id, $current_user_id, $conn, $line_rows,
            $expected_destination, $warehouse_id, $receipt_date
        ) {
            if ($is_editing) {
                $g->update('proc_receipt_custody', $parent, array('id' => $id, 'is_deleted' => 0));
                $custody_id = $id;
            } else {
                $parent['code'] = proc_gen_code($conn, 'proc_receipt_custody', 'PRC-RC', $company_id);
                $parent['created_by'] = $current_user_id;
                $custody_id = $g->insert('proc_receipt_custody', $parent);
            }
            $g->replaceChildren('proc_receipt_custody', $custody_id, 'proc_receipt_line', 'custody_id', $line_rows, 'receipt lines rewrite');

            // §15.6: حركةُ «استلام» للمخزون — للوجهة المخزنية حصرًا (المعدة/المشروع/
            // الورشة عهدةٌ لا مخزون). إعادةُ الكتابة عند التعديل بحذفٍ **مقيَّدٍ بالنوع**
            // (ref_type + ref_id) — لا replaceChildren: الحذفُ بـ ref_id وحده يصيب
            // حركاتِ كاتبٍ آخرَ يشاركه الرقم (proc_issue).
            proc_receipt_stock_rewrite($g, $custody_id, $line_rows, $expected_destination,
                $warehouse_id, $receipt_date, $current_user_id);
            // ① طبقةُ التكاليف: تسعيرُ حركات الاستلام من سطور الأمر (+ نصيبها
            // الوصولي) وإعادةُ احتساب المتوسط المرجح — ذرّيًّا مع الحفظ
            if (!empty($parent['order_id'])) {
                require_once __DIR__ . '/../app/Services/Procurement/ProcCostingService.php';
                \App\Services\Procurement\ProcCostingService::repriceOrderReceipts($g, intval($parent['order_id']));
            }
            return $custody_id;
        }, 'receipt save ' . ($is_editing ? 'edit#' . $id : 'new'));
    } catch (\Throwable $e) {
        error_log('receipt_custody_proc save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('receipt_custody_proc.php', 'تعذّر الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }

    // الحالةُ تتبع الواقعة (UX-09 §5.1-② · §8.2): تُعاد نسبةُ الاستلام من
    // الكميات وتتقدّم حالةُ الأمر تبعًا لها — وعند الاستلام النهائي يُنشر أثرُه
    // الماليُّ من منبعه (لا زرَّ سحبٍ بعد اليوم). لا يرمي: الاستلامُ محفوظٌ سلفًا.
    if ($order_id) {
        proc_sync_order_receipt($conn, $order_id, $current_user_id);
    }
    ems_gov_redirect("Location: receipt_custody_proc.php?msg=" . ($is_editing ? 'تم+تعديل+العهدة+بنجاح+✅' : 'تمت+إضافة+العهدة+بنجاح+✅')); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('receipt_custody_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    $del_order_id = null;
    try {
        $prev = proc_gate(false)->selectOne('proc_receipt_custody', array(
            'columns' => array('order_id'), 'where' => array('id' => $delete_id)));
        $del_order_id = $prev ? intval($prev['order_id']) : null;
        proc_gate(false)->runInTransaction(function ($g) use ($delete_id) {
            // أصنافُ الحركات قبل مسحها — لإعادة احتساب متوسطاتها بعده
            $affected = array();
            foreach ($g->select('proc_stock_move', array('columns' => array('item_id'),
                'where' => array('ref_type' => 'proc_receipt_custody', 'ref_id' => $delete_id))) as $mv) {
                if (intval($mv['item_id']) > 0) { $affected[intval($mv['item_id'])] = true; }
            }
            $g->softDelete('proc_receipt_custody', $delete_id);
            // أرشفةُ العهدة تُرجع أثرَها المخزونيَّ — ذرّيًا معها
            proc_stock_moves_clear($g, 'proc_receipt_custody', $delete_id);
            require_once __DIR__ . '/../app/Services/Procurement/ProcCostingService.php';
            foreach (array_keys($affected) as $iid) {
                \App\Services\Procurement\ProcCostingService::recomputeItemAvg($g, $iid);
            }
        }, 'receipt delete#' . $delete_id);
    } catch (\Throwable $e) {
        error_log('receipt_custody softDelete refused: ' . $e->getMessage());
    }
    // الحالةُ تتبع الواقعة: حذفُ استلامٍ يعيد حسابَ نسبة الأمر (تقدُّمٌ لا نكوص)
    if ($del_order_id) { proc_sync_order_receipt($conn, $del_order_id, $current_user_id); }
    ems_gov_flash_redirect('receipt_custody_proc.php', 'تم حذف العهدة بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── تحميل للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $edit = proc_gate($is_super_admin)->selectOne('proc_receipt_custody', array('where' => array('id' => $eid)));
    if ($edit) {
        $edit_lines = proc_gate($is_super_admin)->select('proc_receipt_line', array(
            'where' => array('custody_id' => $eid), 'orderBy' => 'id ASC',
        ));
    }
}

$page_title = 'إيكوبيشن | الاستلام المؤقت';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

/** صف سطر عهدة استلام. */
function proc_rc_line_row($conn, $is_super_admin, $company_id, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    return '<div class="proc-line form-grid" style="align-items:end;margin-bottom:8px">'
        . '<div class="form-group"><label>الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item">' . $opts . '</select></div>'
        . '<div class="form-group"><label>اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" value="' . $iname . '" required></div>'
        . '<div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="line_qty[]" value="' . $qty . '"></div>'
        . '<div class="form-group"><button type="button" class="btn-cancel removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-rc-main ems-unified-page-shell">
    <?php
    $header_title = 'الاستلام المؤقت';
    $header_icon  = 'fa fa-truck-ramp-box';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'عهدة جديدة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <form id="procForm" action="receipt_custody_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل عهدة استلام' : 'عهدة استلام جديدة'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>المستلِم <span class="required">*</span></label>
                        <input type="text" name="holder_name" value="<?php echo $edit ? htmlspecialchars((string)$edit['holder_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>تاريخ الاستلام</label>
                        <input type="date" name="receipt_date" value="<?php echo $edit ? htmlspecialchars((string)$edit['receipt_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>المورد التشغيلي</label>
                        <select name="supplier_id"><?php echo proc_suppliers_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['supplier_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>المرجع الشرائي (أمر شراء)</label>
                        <select name="order_id"><?php echo proc_options_from_rows($order_option_rows, $edit ? intval($edit['order_id']) : 0, '— بلا أمر —'); ?></select>
                    </div>
                    <div class="form-group">
                        <label>موقع الاستلام</label>
                        <input type="text" name="receipt_location" value="<?php echo $edit ? htmlspecialchars((string)$edit['receipt_location']) : ''; ?>" placeholder="عطبرة / موقع المورد …">
                    </div>
                    <div class="form-group">
                        <label>مخزن الإدخال <span class="required">*</span> <small>(إلزامي حين الوجهة «مخزن» — يحرّك الرصيد)</small></label>
                        <select name="warehouse_id"><?php echo proc_warehouses_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['warehouse_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>الوجهة النهائية المتوقعة</label>
                        <select name="expected_destination">
                            <?php foreach ($destinations as $d): $sel = ($edit && $edit['expected_destination'] === $d) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الحالة</label>
                        <select name="state">
                            <?php foreach ($states as $st): $sel = ($edit && $edit['state'] === $st) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>ملاحظات</label>
                        <input type="text" name="notes" value="<?php echo $edit ? htmlspecialchars((string)$edit['notes']) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="card-header"><h5><i class="fas fa-list"></i> الكميات المستلمة</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_rc_line_row($conn, $is_super_admin, $company_id, $l); }
                    } else {
                        echo proc_rc_line_row($conn, $is_super_admin, $company_id, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn" style="margin-top:6px"><i class="fas fa-plus"></i> إضافة سطر</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <a href="receipt_custody_proc.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_rc_line_row($conn, $is_super_admin, $company_id, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>المستلِم</th><th>تاريخ الصرف</th><th>المورد</th>
                    <th>موقع الاستلام</th><th>الوجهة</th><th>الحالة</th><th>عدد الأصناف</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم العهدة</th>
                    <th class="ems-fn-th" data-fn="1">سند الصرف</th>
                    <th class="ems-fn-th" data-fn="1">الصفة</th>
                    <th class="ems-fn-th" data-fn="1">الصنف</th>
                    <th class="ems-fn-th" data-fn="1">الكمية المصروفة</th>
                    <th class="ems-fn-th" data-fn="1">الكمية المستهلكة</th>
                    <th class="ems-fn-th" data-fn="1">الكمية المرتجعة</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ الإرجاع</th>
                    <th class="ems-fn-th" data-fn="1">حالة المرتجع</th>
                    <th class="ems-fn-th" data-fn="1">قرار المرتجع</th>
                    <th class="ems-fn-th" data-fn="1">المتبقي في العهدة</th>
                    <th class="ems-fn-th" data-fn="1">المسؤول</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // ترطيب ثنائي عبر البوابة (أسماء الموردين + عدّ السطور بجلبٍ واحد لكل منهما)
                    $gv = proc_gate($is_super_admin);
                    $custody_rows = $gv->select('proc_receipt_custody', array(
                        'columns' => array('id', 'code', 'holder_name', 'receipt_date', 'receipt_location', 'expected_destination', 'state', 'supplier_id'),
                        'orderBy' => 'id DESC',
                    ));
                    $sup_names = array(); $line_counts = array();
                    if (!empty($custody_rows)) {
                        $sids = array(); $cids = array();
                        foreach ($custody_rows as $cr) {
                            if ($cr['supplier_id'] !== null) { $sids[intval($cr['supplier_id'])] = true; }
                            $cids[] = intval($cr['id']);
                        }
                        if (!empty($sids)) {
                            foreach ($gv->select('proc_supplier', array(
                                'columns' => array('id', 'name'),
                                'whereRaw' => 'id IN (' . implode(',', array_keys($sids)) . ')',
                                'includeDeleted' => true, // كدلالة LEFT JOIN الأصلية
                            )) as $sr) { $sup_names[intval($sr['id'])] = $sr['name']; }
                        }
                        foreach ($gv->select('proc_receipt_line', array(
                            'columns' => array('custody_id'),
                            'whereRaw' => 'custody_id IN (' . implode(',', $cids) . ')',
                        )) as $lr) {
                            $lcid = intval($lr['custody_id']);
                            $line_counts[$lcid] = ($line_counts[$lcid] ?? 0) + 1;
                        }
                    }
                    { foreach ($custody_rows as $row) {
                        $row['supplier_name'] = ($row['supplier_id'] !== null && isset($sup_names[intval($row['supplier_id'])]))
                            ? $sup_names[intval($row['supplier_id'])] : null;
                        $row['line_count'] = $line_counts[intval($row['id'])] ?? 0;
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
                        echo "<td>" . htmlspecialchars((string)($row['receipt_date'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['supplier_name'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['receipt_location'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['expected_destination']) . "</td>";
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
        });
        $(document).on('change', '.line-item', function () {
            var txt = $(this).find('option:selected').text().trim();
            var $name = $(this).closest('.proc-line').find('.line-name');
            if (txt && !$name.val()) {
                var parts = txt.split(' — ');
                $name.val(parts.length > 1 ? parts[1] : txt);
            }
        });
    });
})();
</script>
</body>
</html>
