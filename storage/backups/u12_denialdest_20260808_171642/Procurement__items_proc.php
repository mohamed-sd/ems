<?php
/**
 * Procurement/items_proc.php — كتالوج الأصناف والقطع الحرجة (proc_item) — §15.5.
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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/items_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض كتالوج الأصناف ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$natures  = proc_material_natures();
$cats_dyn = proc_lookup_names($conn, $is_super_admin, $company_id, 'فئة صنف');
$categories = array_values(array_unique(array_merge(
    array('فلاتر', 'زيوت وشحوم', 'إسبيرات', 'بطاريات', 'أسنان جردل', 'سيور', 'قطع جاك همر', 'مواد سلامة'),
    $cats_dyn
)));
$uoms_dyn = proc_lookup_names($conn, $is_super_admin, $company_id, 'وحدة قياس');
$uoms = array_values(array_unique(array_merge(array('قطعة', 'لتر', 'كجم', 'متر', 'طقم'), $uoms_dyn)));

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('items_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('items_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('items_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $material_nature = trim($_POST['material_nature'] ?? 'قابل للتخزين');
    $uom = trim($_POST['uom'] ?? 'قطعة');
    $is_critical = isset($_POST['is_critical']) ? 1 : 0;
    $min_qty = (float)($_POST['min_qty'] ?? 0);
    $max_qty = (float)($_POST['max_qty'] ?? 0);
    $lead_time_days = (int)($_POST['lead_time_days'] ?? 0);
    $safety_stock = (float)($_POST['safety_stock'] ?? 0);
    $served_equipment_id = ($_POST['served_equipment_id'] ?? '') !== '' ? intval($_POST['served_equipment_id']) : null;
    $served_category = trim($_POST['served_category'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '' || !in_array($material_nature, $natures, true)) {
        ems_gov_flash_redirect('items_proc.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
    }

    // K9-M1: الكتابة عبر البوابة
    $data = array(
        'name' => $name, 'category' => $category, 'material_nature' => $material_nature, 'uom' => $uom,
        'is_critical' => $is_critical, 'min_qty' => $min_qty, 'max_qty' => $max_qty,
        'lead_time_days' => $lead_time_days, 'safety_stock' => $safety_stock,
        'served_equipment_id' => $served_equipment_id, 'served_category' => $served_category, 'notes' => $notes,
    );
    try {
        if ($is_editing) {
            proc_gate(false)->update('proc_item', $data, array('id' => $id, 'is_deleted' => 0));
            ems_gov_flash_redirect('items_proc.php', 'تم تعديل الصنف بنجاح ✅', 'GOV-OK-200', ''); exit();
        } else {
            $data['code'] = proc_gen_code($conn, 'proc_item', 'PRC-ITM', $company_id);
            $data['created_by'] = $current_user_id;
            proc_gate(false)->insert('proc_item', $data);
            ems_gov_flash_redirect('items_proc.php', 'تمت إضافة الصنف بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('items_proc save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('items_proc.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('items_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        proc_gate(false)->softDelete('proc_item', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('items_proc softDelete refused: ' . $e->getMessage());
    }
    ems_gov_flash_redirect('items_proc.php', 'تم حذف الصنف بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | كتالوج الأصناف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main proc-items-main ems-unified-page-shell">
    <?php
    $header_title = 'كتالوج الأصناف والقطع الحرجة';
    $header_icon  = 'fa fa-boxes-stacked';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة صنف');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <!-- فورم إضافة/تعديل -->
    <form id="procForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل صنف</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="p_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم الصنف <span class="required">*</span></label>
                        <input type="text" name="name" id="p_name" required>
                    </div>
                    <div class="form-group">
                        <label>الفئة</label>
                        <select name="category" id="p_category">
                            <option value="">— اختر —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>طبيعة المادة <span class="required">*</span></label>
                        <select name="material_nature" id="p_nature" required>
                            <?php foreach ($natures as $n): ?>
                                <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>وحدة القياس</label>
                        <select name="uom" id="p_uom">
                            <?php foreach ($uoms as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الحد الأدنى (Min)</label>
                        <input type="number" step="0.01" name="min_qty" id="p_min" value="0">
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى (Max)</label>
                        <input type="number" step="0.01" name="max_qty" id="p_max" value="0">
                    </div>
                    <div class="form-group">
                        <label>مخزون الأمان</label>
                        <input type="number" step="0.01" name="safety_stock" id="p_safety" value="0">
                    </div>
                    <div class="form-group">
                        <label>مدة التوريد (أيام)</label>
                        <input type="number" name="lead_time_days" id="p_lead" value="0">
                    </div>
                    <div class="form-group">
                        <label>المعدة المخدومة</label>
                        <select name="served_equipment_id" id="p_equip">
                            <?php echo proc_equipment_options($conn, $is_super_admin, $company_id, 0); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>العائلة المخدومة</label>
                        <input type="text" name="served_category" id="p_served_cat">
                    </div>
                    <div class="form-group">
                        <label>قطعة حرجة؟</label>
                        <label class="switch-inline"><input type="checkbox" name="is_critical" id="p_critical" value="1"> نعم، قطعة حرجة</label>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>ملاحظات</label>
                        <input type="text" name="notes" id="p_notes">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="procToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <!-- فلاتر البحث (نفس تصميم شاشة العملاء) -->
    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label><i class="fa fa-layer-group"></i> الفئة</label>
                <select id="filterCategory" class="form-control">
                    <option value="">-- كل الفئات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-box"></i> طبيعة المادة</label>
                <select id="filterNature" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-triangle-exclamation"></i> قطعة حرجة</label>
                <select id="filterCritical" class="form-control">
                    <option value="">-- الكل --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>اسم الصنف</th><th>الفئة</th><th>طبيعة المادة</th>
                    <th>وحدة القياس</th><th>قطعة حرجة؟</th><th>Min</th><th>Max</th><th>مخزون الأمان</th><th>مدة التوريد بالأيام</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم الصنف</th>
                    <th class="ems-fn-th" data-fn="1">الموديل أو العائلة المخدومة</th>
                    <th class="ems-fn-th" data-fn="1">حد إعادة الطلب</th>
                    <th class="ems-fn-th" data-fn="1">الحد الأدنى</th>
                    <th class="ems-fn-th" data-fn="1">الحد الأقصى</th>
                    <th class="ems-fn-th" data-fn="1">متوسط التكلفة</th>
                    <th class="ems-fn-th" data-fn="1">المورد المفضَّل</th>
                    <th class="ems-fn-th" data-fn="1">المخزن الافتراضي</th>
                    <th class="ems-fn-th" data-fn="1">عرّفه</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                    <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $item_rows = proc_gate($is_super_admin)->select('proc_item', array(
                        'columns' => array('id', 'code', 'name', 'category', 'material_nature', 'uom', 'is_critical', 'min_qty', 'max_qty', 'safety_stock', 'lead_time_days'),
                        'orderBy' => 'name ASC',
                    ));
                    { foreach ($item_rows as $row) {
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-category='" . htmlspecialchars((string)($row['category'] ?? ''), ENT_QUOTES) . "' " .
                            "data-nature='" . htmlspecialchars((string)$row['material_nature'], ENT_QUOTES) . "' " .
                            "data-uom='" . htmlspecialchars((string)$row['uom'], ENT_QUOTES) . "' " .
                            "data-critical='" . intval($row['is_critical']) . "' " .
                            "data-min='" . htmlspecialchars((string)$row['min_qty'], ENT_QUOTES) . "' " .
                            "data-max='" . htmlspecialchars((string)$row['max_qty'], ENT_QUOTES) . "' " .
                            "data-safety='" . htmlspecialchars((string)$row['safety_stock'], ENT_QUOTES) . "' " .
                            "data-lead='" . intval($row['lead_time_days']) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['category'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['material_nature']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['uom']) . "</td>";
                        echo "<td>" . ((int)$row['is_critical'] === 1 ? "<span class='action-btn' style='color:#c0392b'>حرجة</span>" : "—") . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['min_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['max_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['safety_stock']) . "</td>";
                        echo "<td>" . intval($row['lead_time_days']) . "</td>";
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
        var procTable = $('#procTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        // ── فلاتر البحث (نفس منطق شاشة العملاء) ──
        function fillFilterOptions(columnIndex, selectId) {
            var select = $(selectId);
            var currentValue = select.val();
            var values = [];
            procTable.column(columnIndex).data().each(function (value) {
                var text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) {
                    values.push(text);
                }
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
            if (currentValue) { select.val(currentValue); }
        }

        fillFilterOptions(3, '#filterCategory'); // الفئة
        fillFilterOptions(4, '#filterNature');   // طبيعة المادة
        fillFilterOptions(6, '#filterCritical'); // حرجة

        $('#filterCategory').on('change', function () {
            var value = $.fn.dataTable.util.escapeRegex($(this).val());
            procTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterNature').on('change', function () {
            var value = $.fn.dataTable.util.escapeRegex($(this).val());
            procTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterCritical').on('change', function () {
            var value = $.fn.dataTable.util.escapeRegex($(this).val());
            procTable.column(6).search(value ? '^' + value + '$' : '', true, false).draw();
        });

        // زر «تطبيق» (البحث فوري عند التغيير؛ يعيد الرسم فقط)
        $('.filter .btn-ok').on('click', function () { procTable.draw(); });

        // زر «إعادة تعيين»
        $('.filter .btn-reset').on('click', function () {
            $('#filterCategory, #filterNature, #filterCritical').val('');
            procTable.column(3).search('').column(4).search('').column(6).search('').draw();
        });

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.getElementById('procForm').reset();
                $('#p_id').val('');
                $('#procForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#p_id').val($t.data('id'));
            $('#p_name').val($t.data('name'));
            $('#p_category').val($t.data('category'));
            $('#p_nature').val($t.data('nature'));
            $('#p_uom').val($t.data('uom'));
            $('#p_min').val($t.data('min'));
            $('#p_max').val($t.data('max'));
            $('#p_safety').val($t.data('safety'));
            $('#p_lead').val($t.data('lead'));
            $('#p_critical').prop('checked', String($t.data('critical')) === '1');
            $('#procForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#procForm').offset().top }, 400);
        });
    });

    window.procToggleForm = function () {
        var form = $('#procForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('procForm').reset();
            $('#p_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
