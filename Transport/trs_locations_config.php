<?php
/**
 * Transport/trs_locations_config.php — إعداد المواقع (قاعدة/مشروع/ورشة/مكتب) — §4.
 * نمط موحّد: ترويسة + توبار + DataTables + فورم .allforms + عزل الشركة.
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
require_once __DIR__ . '/trs_helpers.php';

$ctx             = trs_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = trs_page_perms($conn, 'Transport/trs_locations_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المواقع ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = trs_scope('company_id', $is_super_admin, $company_id);
$loc_types = trs_location_types();

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('trs_locations_config.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('trs_locations_config.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('trs_locations_config.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $name = trim($_POST['name'] ?? '');
    $location_type = trim($_POST['location_type'] ?? 'base');
    $project_id = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || !array_key_exists($location_type, $loc_types)) {
        ems_gov_flash_redirect('trs_locations_config.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
    }
    // المشروع يُربط فقط عندما يكون النوع مشروعاً
    if ($location_type !== 'project') { $project_id = null; }

    try {
        if ($is_editing) {
            trs_gate(false)->update('trs_locations',
                array('name' => $name, 'location_type' => $location_type, 'project_id' => $project_id, 'active' => $active),
                array('id' => $id));
            ems_gov_flash_redirect('trs_locations_config.php', 'تم تعديل الموقع بنجاح ✅', 'GOV-OK-200', ''); exit();
        } else {
            trs_gate(false)->insert('trs_locations', array(
                'code' => trs_gen_code($conn, 'trs_locations', 'LOC', $company_id),
                'name' => $name, 'location_type' => $location_type,
                'project_id' => $project_id, 'active' => $active,
            ));
            ems_gov_flash_redirect('trs_locations_config.php', 'تمت إضافة الموقع بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('trs_locations save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('trs_locations_config.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
}

// ── حذف — سياسة «الأرشفة لا الحذف» (نفس دلالة types الموثقة) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('trs_locations_config.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        trs_gate(false)->softDelete('trs_locations', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('trs_locations softDelete refused: ' . $e->getMessage());
        ems_gov_flash_redirect('trs_locations_config.php', 'تعذر الحذف ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('trs_locations_config.php', 'تم حذف الموقع بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | المواقع';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<style>
/* UXW-01: أنماطُ شاشةِ المواقعِ أصنافًا برموزِ الألوان */
.trs-lc-tbl{width:100%}
.trs-lc-on{color:var(--c-1e7e34, #1e7e34)}
.trs-lc-off{color:var(--c-c0392b, #c0392b)}
</style>
<div class="main trs-locations-main ems-unified-page-shell">
    <?php
    $header_title = 'المواقع';
    $header_icon  = 'fa fa-location-dot';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة موقع');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا مواقع ترحيل معرفة بعد', 'عرف أول موقع بزر «إضافة موقع» في رأس الشاشة');
    ?>

    <?php trs_msg_banner(); ?>

    <!-- فورم إضافة/تعديل -->
    <form id="trsForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل موقع</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="l_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="l_name">اسم الموقع <span class="required">*</span></label>
                        <input type="text" name="name" id="l_name" required>
                    </div>
                    <div class="form-group">
                        <label for="l_type">نوع الموقع <span class="required">*</span></label>
                        <select name="location_type" id="l_type" required>
                            <?php foreach ($loc_types as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="l_project_wrap">
                        <label for="l_project">المشروع المرتبط (عند نوع «مشروع»)</label>
                        <select name="project_id" id="l_project">
                            <?php echo trs_project_options($conn, $is_super_admin, $company_id, 0); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>مفعل؟</label>
                        <label class="switch-inline"><input type="checkbox" name="active" id="l_active" aria-label="تفعيل الموقع" value="1" checked> نعم، مفعل</label>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" onclick="trsToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <!-- فلاتر البحث -->
    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label><i class="fa fa-map"></i> نوع الموقع</label>
                <select id="filterType" aria-label="تصفية المواقع بالنوع" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-secondary" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="trsTable" class="display nowrap alltables trs-lc-tbl" data-state-save="false" data-scroll-x="true">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>النوع</th><th>المشروع</th><th>الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // ترطيب ثنائي: المواقع ثم أسماء المشاريع بجلبٍ واحد (دلالة LEFT JOIN)
                    $gv = trs_gate($is_super_admin);
                    $loc_rows = $gv->select('trs_locations', array(
                        'columns' => array('id', 'code', 'name', 'location_type', 'project_id', 'active'),
                        'orderBy' => 'name ASC',
                    ));
                    $proj_names = array();
                    $pids = array();
                    foreach ($loc_rows as $lr) {
                        if ($lr['project_id'] !== null) { $pids[intval($lr['project_id'])] = true; }
                    }
                    if (!empty($pids)) {
                        foreach ($gv->select('project', array(
                            'columns' => array('id', 'name'),
                            'whereRaw' => 'id IN (' . implode(',', array_keys($pids)) . ')',
                            'includeDeleted' => true,
                        )) as $pr) { $proj_names[intval($pr['id'])] = $pr['name']; }
                    }
                    { foreach ($loc_rows as $row) {
                        $row['project_name'] = ($row['project_id'] !== null && isset($proj_names[intval($row['project_id'])]))
                            ? $proj_names[intval($row['project_id'])] : null;
                        $type_ar = trs_label($loc_types, $row['location_type']);
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-type='" . htmlspecialchars((string)$row['location_type'], ENT_QUOTES) . "' " .
                            "data-project='" . intval($row['project_id'] ?? 0) . "' " .
                            "data-active='" . intval($row['active']) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($type_ar) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
                        echo "<td>" . ((int)$row['active'] === 1 ? "<span class='action-btn trs-lc-on'>مفعل</span>" : "<span class='action-btn trs-lc-off'>معطل</span>") . "</td>";
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
<script>
(function () {
    $(document).ready(function () {
        // UXW-01 ⑤: التهيئةُ المحليةُ حُذفت — المكوّنُ المركزيُّ (ui-unification.js)
        // يهيّئ الجدولَ بسماتِ <table>. ونمسك نسختَه حين تجهز لنُبقيَ مرشِّحَ النوعِ عاملًا.
        function withTrsTable(cb) {
            if (window.jQuery && $.fn.dataTable && $.fn.dataTable.isDataTable('#trsTable')) {
                cb($('#trsTable').DataTable());
                return;
            }
            setTimeout(function () { withTrsTable(cb); }, 120);
        }

        function fillFilterOptions(trsTable, columnIndex, selectId) {
            var select = $(selectId);
            var values = [];
            trsTable.column(columnIndex).data().each(function (value) {
                var text = $('<div>').html(value).text().trim();
                if (text !== '' && values.indexOf(text) === -1) { values.push(text); }
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }

        withTrsTable(function (trsTable) {
            fillFilterOptions(trsTable, 3, '#filterType');

            $('#filterType').on('change', function () {
                var value = $.fn.dataTable.util.escapeRegex($(this).val());
                trsTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
            });
            $('.filter .btn-primary').on('click', function () { trsTable.draw(); });
            $('.filter .btn-secondary').on('click', function () {
                $('#filterType').val('');
                trsTable.column(3).search('').draw();
            });
        });

        // إظهار/إخفاء حقل المشروع حسب النوع
        function toggleProjectField() {
            if ($('#l_type').val() === 'project') { $('#l_project_wrap').show(); }
            else { $('#l_project_wrap').hide(); $('#l_project').val(''); }
        }
        $('#l_type').on('change', toggleProjectField);
        toggleProjectField();

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.getElementById('trsForm').reset();
                $('#l_id').val('');
                toggleProjectField();
                $('#trsForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#l_id').val($t.data('id'));
            $('#l_name').val($t.data('name'));
            $('#l_type').val($t.data('type'));
            $('#l_project').val($t.data('project') ? String($t.data('project')) : '');
            $('#l_active').prop('checked', String($t.data('active')) === '1');
            toggleProjectField();
            $('#trsForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#trsForm').offset().top }, 400);
        });
    });

    window.trsToggleForm = function () {
        var form = $('#trsForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('trsForm').reset();
            $('#l_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
