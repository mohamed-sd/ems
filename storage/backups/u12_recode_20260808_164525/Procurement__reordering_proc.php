<?php
/**
 * Procurement/reordering_proc.php — قواعد إعادة الطلب (proc_orderpoint).
 * نمط موحّد: ترويسة + توبار + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 * شاشة جديدة مستقلة تماماً — لا تلمس أي جدول قائم.
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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-INFO-200', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/reordering_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض قواعد إعادة الطلب ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$modes = array('يدوي', 'تلقائي');

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('reordering_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('reordering_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('reordering_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-INFO-200', ''); exit(); }

    $item_id = intval($_POST['item_id'] ?? 0);
    $warehouse_id = ($_POST['warehouse_id'] ?? '') !== '' ? intval($_POST['warehouse_id']) : null;
    $min_qty = (float)($_POST['min_qty'] ?? 0);
    $max_qty = (float)($_POST['max_qty'] ?? 0);
    $trigger_qty = (float)($_POST['trigger_qty'] ?? 0);
    $safety_stock = (float)($_POST['safety_stock'] ?? 0);
    $mode = trim($_POST['mode'] ?? 'يدوي');

    if ($item_id <= 0 || !in_array($mode, $modes, true)) {
        ems_gov_flash_redirect('reordering_proc.php', 'بيانات غير مكتملة ❌', 'GOV-INFO-200', ''); exit();
    }

    // K9-M1 ذيل: الكتابة عبر البوابة
    $data = array(
        'item_id' => $item_id, 'warehouse_id' => $warehouse_id,
        'min_qty' => $min_qty, 'max_qty' => $max_qty, 'trigger_qty' => $trigger_qty,
        'safety_stock' => $safety_stock, 'mode' => $mode,
    );
    try {
        if ($is_editing) {
            proc_gate(false)->update('proc_orderpoint', $data, array('id' => $id, 'is_deleted' => 0));
            ems_gov_flash_redirect('reordering_proc.php', 'تم تعديل قاعدة إعادة الطلب بنجاح ✅', 'GOV-OK-200', ''); exit();
        } else {
            $data['created_by'] = $current_user_id;
            proc_gate(false)->insert('proc_orderpoint', $data);
            ems_gov_flash_redirect('reordering_proc.php', 'تمت إضافة قاعدة إعادة الطلب بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('reordering save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('reordering_proc.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-INFO-200', ''); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('reordering_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try { proc_gate(false)->softDelete('proc_orderpoint', $delete_id); }
    catch (\App\Core\TenantGateException $e) { error_log('reordering softDelete refused: ' . $e->getMessage()); }
    ems_gov_flash_redirect('reordering_proc.php', 'تم حذف قاعدة إعادة الطلب بنجاح ✅', 'GOV-OK-200', ''); exit();
}

// ── تعبئة نموذج التعديل عبر ?edit_id (السيليكتات) ──
$edit_row = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_row = proc_gate($is_super_admin)->selectOne('proc_orderpoint', array(
        'columns' => array('id', 'item_id', 'warehouse_id', 'min_qty', 'max_qty', 'trigger_qty', 'safety_stock', 'mode'),
        'where'   => array('id' => $edit_id),
    ));
}

$sel_item      = $edit_row ? intval($edit_row['item_id']) : 0;
$sel_warehouse = $edit_row ? intval($edit_row['warehouse_id']) : 0;

$page_title = 'إيكوبيشن | حدود إعادة الطلب';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main proc-reordering-main ems-unified-page-shell">
    <?php
    $header_title = 'حدود إعادة الطلب';
    $header_icon  = 'fa fa-arrows-rotate';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة قاعدة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <?php
    // M-43 + M-51: التوليدُ الآلي بمفتاح (صنف × دورة) + المتوسطُ مصدرًا للحد
    require_once __DIR__ . '/../app/Services/Procurement/ProcReorderService.php';
    $reorderResult = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['reorder_action'] ?? '', array('dry', 'apply'), true)) {
        if ($can_add) {
            $reorderResult = \App\Services\Procurement\ProcReorderService::run(
                $conn, proc_gate(false), $company_id, $current_user_id,
                ($_POST['reorder_action'] === 'dry'));
        }
    }
    ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-rotate"></i>
        التوليدُ الآلي لطلبات الشراء (M-43) — بمفتاح (صنف × دورة)</h5></div>
    <div class="card-body">
        <p style="color:#666">لكل نقطةِ طلبٍ بلغ رصيدُها الحيُّ حدَّها: يولَّد طلبُ شراءٍ واحدٌ
            بكمية (الحدُّ الأعلى − الرصيد) — <strong>والدورةُ الجاريةُ تمنع توليدًا ثانيًا</strong>
            حتى تُقفل. والمتوسطُ اليوميُّ (آخر 90 يومًا) يُعرض <strong>مصدرًا مقترحًا للحد</strong> (M-51).</p>
        <?php if ($can_add): ?>
        <div style="display:flex;gap:8px">
            <form method="post"><input type="hidden" name="reorder_action" value="dry">
                <button type="submit" class="btn-save"><i class="fa fa-flask"></i> جرّب (بلا كتابة)</button></form>
            <form method="post"><input type="hidden" name="reorder_action" value="apply">
                <button type="submit" class="btn-save"><i class="fa fa-play"></i> ولّد فعلًا</button></form>
        </div>
        <?php endif; ?>
        <?php if ($reorderResult !== null): ?>
            <div style="margin-top:10px">
                <strong><?php echo $reorderResult['dry'] ? 'تجريب:' : 'توليد:'; ?></strong>
                <?php echo count($reorderResult['generated']); ?> مرشحًا ·
                <?php echo count($reorderResult['skipped']); ?> متجاوَزًا
                <?php foreach ($reorderResult['generated'] as $g): ?>
                    <div class="alert alert-success" style="margin:4px 0">
                        <?php echo htmlspecialchars($g['item'] . ' — الرصيد ' . $g['balance']
                            . ' ≤ الحد ' . $g['trigger'] . ' ⇒ كمية ' . $g['qty']
                            . (isset($g['request_id']) ? (' · طلب #' . $g['request_id']) : '')); ?></div>
                <?php endforeach; ?>
                <?php foreach ($reorderResult['skipped'] as $s): ?>
                    <div class="alert alert-info" style="margin:4px 0">
                        <?php echo htmlspecialchars($s['item'] . ' — ' . $s['reason']); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div></div>

    <!-- فورم إضافة/تعديل -->
    <form id="procForm" action="" method="post" class="allforms<?php echo $edit_row ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل قاعدة إعادة طلب</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="p_id" value="<?php echo $edit_row ? intval($edit_row['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>الصنف <span class="required">*</span></label>
                        <select name="item_id" id="p_item" required>
                            <?php echo proc_items_options($conn, $is_super_admin, $company_id, $sel_item); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>المخزن</label>
                        <select name="warehouse_id" id="p_warehouse">
                            <?php echo proc_warehouses_options($conn, $is_super_admin, $company_id, $sel_warehouse); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الحد الأدنى (Min)</label>
                        <input type="number" step="0.01" name="min_qty" id="p_min" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['min_qty'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى (Max)</label>
                        <input type="number" step="0.01" name="max_qty" id="p_max" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['max_qty'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>نقطة إعادة الطلب (ROP)</label>
                        <input type="number" step="0.01" name="trigger_qty" id="p_trigger" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['trigger_qty'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>مخزون الأمان</label>
                        <input type="number" step="0.01" name="safety_stock" id="p_safety" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['safety_stock'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>الوضع</label>
                        <select name="mode" id="p_mode">
                            <?php foreach ($modes as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>"<?php echo ($edit_row && $edit_row['mode'] === $m) ? ' selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="form-hint" style="grid-column:1/-1;color:#666;font-size:13px;margin-top:8px;">
                    <i class="fas fa-info-circle"></i>
                    نقطة إعادة الطلب ≈ (متوسط الاستهلاك اليومي × مدة التوريد) + مخزون الأمان
                </p>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="procToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الصنف</th><th>المخزن</th><th>Min</th><th>Max</th>
                    <th>ROP</th><th>مخزون الأمان</th><th>الوضع</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // القراءة المركّبة عبر scopedQuery (عقد §10) — النص الأصلي حرفيًا + الرمز
                    $rop_rows = proc_gate($is_super_admin)->scopedQuery(
                        array(
                            'scope'  => array('op' => 'proc_orderpoint'),
                            'enrich' => array('i' => 'proc_item', 'w' => 'proc_warehouse'),
                        ),
                        "SELECT op.id, op.min_qty, op.max_qty, op.trigger_qty, op.safety_stock, op.mode,
                                i.name AS item_name, w.name AS warehouse_name
                         FROM proc_orderpoint op
                         LEFT JOIN proc_item i ON i.id = op.item_id
                         LEFT JOIN proc_warehouse w ON w.id = op.warehouse_id
                         WHERE {TENANT_SCOPE} AND COALESCE(op.is_deleted,0)=0
                         ORDER BY i.name ASC"
                    );
                    { foreach ($rop_rows as $row) {
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='?edit_id=" . intval($row['id']) . "' class='action-btn edit' title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['item_name'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['warehouse_name'] ?? '—')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['min_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['max_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['trigger_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['safety_stock']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['mode']) . "</td>";
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
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                window.location.href = 'reordering.php';
            });
        }

        <?php if ($edit_row): ?>
        $('html, body').animate({ scrollTop: $('#procForm').offset().top }, 400);
        <?php endif; ?>
    });

    window.procToggleForm = function () {
        window.location.href = 'reordering.php';
    };
})();
</script>
</body>
</html>
