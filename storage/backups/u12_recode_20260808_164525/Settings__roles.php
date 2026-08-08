<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';

// ════════════════════════════════════════════════════════════════════════════
// [ح-1] حصر شاشة إدارة الأدوار — نفس تصعيد الامتياز في role_permissions.php.
// الشاشة الآن مسجَّلة موديولًا (owner 15). الفحص صريحٌ لأن معالج POST أدناه يسبق
// insidebar. المخوَّلون: الدور 15 (مدير الصلاحيات) + الدور 1 (التشغيل).
// ════════════════════════════════════════════════════════════════════════════
$roles_perms = get_current_page_permissions($conn);
if ($roles_perms['id'] !== null && !$roles_perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية لهذه الصفحة ❌', 'GOV-PERM-403', '');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $roles_perms['id'] !== null && !$roles_perms['can_edit']) {
    ems_gov_flash_redirect('roles.php', 'لا توجد صلاحية للتعديل ❌', 'GOV-PERM-403', '');
    exit();
}

/* جلب بيانات التعديل (roles مرجع عام T_GLOBAL — قراءته عبر البوابة متاحة بلا نطاق) */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    try {
        $editData = ems_tenant_db()->selectOne('roles', array(
            'columns' => array('id', 'name', 'parent_role_id', 'level', 'status', 'created_at'),
            'where' => array('id' => $id)));
    } catch (\Throwable $t) { $editData = null; error_log('roles.php edit: ' . $t->getMessage()); }
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_role_id = !empty($_POST['parent_role_id']) && $_POST['parent_role_id'] !== '' ? (int) $_POST['parent_role_id'] : null;
    $level = (int) ($_POST['level'] ?? 1);
    $status = (int) ($_POST['status'] ?? 1);

    // التحقق من صحة البيانات
    if (empty($name)) {
        $error_msg = 'اسم الصلاحية مطلوب ❌';
    } else {
        // [مُستثنى موثَّق — كتابة RBAC مجمَّدة] roles مرجعٌ عام جمَّد عقدُ البوابة كتابته على
        // المدير الأعلى حصرًا (assertWritable)، بينما الشاشة الحالية تتيحه لمديري الشركات
        // (ثغرة UAT §15 المفتوحة). حفاظًا على السلوك حرفيًا تبقى الكتابة خامًا بانتظار
        // قرار التحصين الأمني — عندها تُنقَل للبوابة أو تُحصَر بالسوبر.
        if (!empty($_POST['edit_id'])) {
            // تعديل
            $id = (int) $_POST['edit_id'];
            $stmt = $conn->prepare(
                "UPDATE `roles` SET `name` = ?, `parent_role_id` = ?, `level` = ?, `status` = ? WHERE `id` = ?"
            );
            $stmt->bind_param("siiii", $name, $parent_role_id, $level, $status, $id);
        } else {
            // إضافة
            $stmt = $conn->prepare(
                "INSERT INTO `roles` (`name`, `parent_role_id`, `level`, `status`, `created_at`) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param("siii", $name, $parent_role_id, $level, $status);
        }

        if ($stmt->execute()) {
            ems_gov_flash_redirect('roles.php', 'تم البحفاظ على البيانات بنجاح ✅', 'GOV-OK-200', '');
            exit;
        } else {
            $error_msg = 'حدث خطأ: ' . htmlspecialchars($stmt->error) . ' ❌';
        }
    }
}

/* حذف */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    // التحقق من عدم استخدام هذا الدور كدور أب (قراءة عبر البوابة)
    $children_count = 0;
    try { $children_count = intval(ems_tenant_db()->count('roles', array('where' => array('parent_role_id' => $id)))); }
    catch (\Throwable $t) { error_log('roles.php delete check: ' . $t->getMessage()); }

    if ($children_count > 0) {
        ems_gov_flash_redirect('roles.php', 'لا يمكن حذف هذا الدور لأنه يمتلك أدوار فرعية ❌', 'GOV-INFO-200', '');
    } else {
        // [مُستثنى موثَّق — كتابة RBAC مجمَّدة] كما في الحفظ أعلاه: الحذف يبقى خامًا
        // بانتظار قرار التحصين (deleteRow تعاقديًا لجداول المستأجر لا المراجع العامة).
        $stmt = $conn->prepare("DELETE FROM `roles` WHERE `id` = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            ems_gov_flash_redirect('roles.php', 'تم حذف الدور بنجاح ✅', 'GOV-OK-200', '');
        } else {
            ems_gov_flash_redirect('roles.php', 'حدث خطأ في الحذف ❌', 'GOV-INFO-200', '');
        }
    }
    exit;
}

// جلب جميع الأدوار الرئيسية (بدون دور أب) — قراءة مرجعٍ عام عبر البوابة
$parent_roles = array();
try {
    $parent_roles = ems_tenant_db()->select('roles', array(
        'columns' => array('id', 'name'),
        'where' => array('parent_role_id' => null),
        'orderBy' => 'name'));
} catch (\Throwable $t) { error_log('roles.php parents: ' . $t->getMessage()); }

$page_title = "الأدوار";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/admin-style.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link rel="stylesheet" href="/ems/assets/css/all.min.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<div class="main">
    <!-- Unified header: pre-built final structure (data-ems-unified-header skips the JS rebuild). Styling: ems.main.all.style.css (.header) -->
    <div class="header" data-ems-unified-header="1">
        <div class="actions">
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة صلاحية جديدة
            </a>
        </div>
        <div class="title">
            <h1 class="title-content">
                <div class="title-icon"><i class="fas fa-shield-alt"></i></div>
                إدارة الصلاحيات والأدوار
            </h1>
        </div>
        <div class="back">
            <a href="settings.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- فورم إضافة / تعديل -->
    <form id="roleForm" action="" method="post" class="ems-form" style="display:<?= !empty($editData) ? 'block' : 'none'; ?>">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> <?= !empty($editData) ? 'تعديل الصلاحية' : 'إضافة صلاحية جديدة'; ?></h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div class="form-grid">
                    <!-- اسم الصلاحية -->
                    <div>
                        <label><i class="fas fa-tag"></i> اسم الصلاحية *</label>
                        <input type="text" name="name" id="name" placeholder="مثال: مدير المشاريع" required />
                    </div>

                    <!-- الدور الأب (الصلاحية الأب) -->
                    <div>
                        <label><i class="fas fa-sitemap"></i> الدور الأب (اختياري)</label>
                        <select id="parent_role_id" name="parent_role_id">
                            <option value="">-- بدون دور أب (مدير رئيسي) --</option>
                            <?php foreach ($parent_roles as $pRole): ?>
                                <option value="<?= $pRole['id']; ?>">
                                    <?= htmlspecialchars($pRole['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- المستوى -->
                    <div>
                        <label><i class="fas fa-layer-group"></i> مستوى الهرمية</label>
                        <select name="level" id="level" required>
                            <option value="1" selected> مدير </option>
                            <option value="2"> مشرف </option>
                        </select>
                    </div>

                    <!-- الحالة -->
                    <div>
                        <label><i class="fas fa-toggle-on"></i> حالة الصلاحية *</label>
                        <select name="status" id="status" required>
                            <option value="1" selected>نشطة ✅</option>
                            <option value="0">غير نشطة ⏸</option>
                        </select>
                    </div>

                    <button type="submit" style="grid-column: 1 / -1; justify-self: center;">
                        <i class="fas fa-save"></i> حفظ الصلاحية
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الصلاحيات -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> جميع الصلاحيات</h5>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="rolesTable" class="display">
                    <thead>
                        <tr>
                            <th width="80"><i class="fas fa-barcode"></i> #</th>
                            <th><i class="fas fa-tag"></i> اسم الصلاحية</th>
                            <th><i class="fas fa-sitemap"></i> الدور الأب</th>
                            <th width="100"><i class="fas fa-layer-group"></i> المستوى التنظيمي</th>
                            <th width="100"><i class="fas fa-toggle-on"></i> الحالة</th>
                            <th width="120"><i class="fas fa-calendar"></i> تاريخ الإنشاء</th>
                            <th width="120"><i class="fas fa-cogs"></i> إجراءات</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">كود الدور</th>
                            <th class="ems-fn-th" data-fn="1">اسم الدور</th>
                            <th class="ems-fn-th" data-fn="1">العائلة الوظيفية</th>
                            <th class="ems-fn-th" data-fn="1">الإدارة</th>
                            <th class="ems-fn-th" data-fn="1">قالب الصلاحيات</th>
                            <th class="ems-fn-th" data-fn="1">النطاق الافتراضي</th>
                            <th class="ems-fn-th" data-fn="1">سقف الاعتماد</th>
                            <th class="ems-fn-th" data-fn="1">عدد حامليه</th>
                            <th class="ems-fn-th" data-fn="1">تعارض واجبات مع</th>
                            <th class="ems-fn-th" data-fn="1">تاريخ التعريف</th>
                            <th class="ems-fn-th" data-fn="1">عرّفه</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                            <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        // قراءة المرجع العام عبر البوابة؛ اسم الأب يُشتق من خريطة id→name
                        // بدل self-JOIN (scopedQuery يشترط جدول نطاقٍ مستأجرًا وهذا عامّ محض).
                        $result = array();
                        try {
                            $result = ems_tenant_db()->select('roles', array(
                                'columns' => array('id', 'name', 'parent_role_id', 'level', 'status', 'created_at'),
                                'orderBy' => 'level, parent_role_id, name'));
                        } catch (\Throwable $t) { $result = false; error_log('roles.php list: ' . $t->getMessage()); }

                        if ($result === false) {
                            echo '<tr><td colspan="7" class="text-center text-danger">خطأ في جلب البيانات: ' . htmlspecialchars($conn->error) . '</td></tr>';
                        } else {
                            $roles_name_map = array();
                            foreach ($result as $rn) { $roles_name_map[intval($rn['id'])] = $rn['name']; }
                            $i = 1;
                            foreach ($result as $row):
                                $row['parent_name'] = ($row['parent_role_id'] !== null && isset($roles_name_map[intval($row['parent_role_id'])]))
                                    ? $roles_name_map[intval($row['parent_role_id'])] : null;
                                $status_badge = $row['status'] == 1
                                    ? '<span class="status-active"><i class="fas fa-check-circle"></i> نشط</span>'
                                    : '<span class="status-inactive"><i class="fas fa-times-circle"></i> غير نشط</span>';
                                ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td>
                                        <a href="modules.php?role_id=<?= $row['id']; ?>"
                                            style="color: var(--navy); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                            onmouseover="this.style.color='var(--gold)'; this.style.textDecoration='underline';"
                                            onmouseout="this.style.color='var(--navy)'; this.style.textDecoration='none';">
                                            <strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </a>
                                        <?php if ($row['parent_role_id'] === null): ?>
                                            <br><small style="color: var(--gold); font-weight: 600;">ðŸ”µ مدير رئيسي</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['parent_name'])): ?>
                                            <a href="modules.php?role_id=<?= $row['parent_role_id']; ?>"
                                                style="color: var(--blue); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                                onmouseover="this.style.color='var(--navy)'; this.style.textDecoration='underline';"
                                                onmouseout="this.style.color='var(--blue)'; this.style.textDecoration='none';">
                                                <i class="fas fa-link"></i>
                                                <?= htmlspecialchars($row['parent_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--sub);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-active"
                                            style="background: rgba(37,99,235,.12); color: var(--blue);"><i
                                                class="fas fa-layer-group"></i> <?= (int) $row['level']; ?></span></td>
                                    <td><?= $status_badge; ?></td>
                                    <td>
                                        <small style="color: var(--sub);">
                                            <?= date('Y-m-d', strtotime($row['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <a href="javascript:void(0);"
                                            onclick="editRole(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="btn btn-sm btn-primary" title="تعديل"
                                            style="background: var(--blue-soft); color: var(--blue); border: 1.5px solid rgba(37,99,235,.18);">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);"
                                            onclick="confirmDelete(<?= $row['id']; ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>')"
                                            class="btn btn-sm btn-danger" title="حذف"
                                            style="background: var(--red-soft); color: var(--red); border: 1.5px solid rgba(220,38,38,.18);">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endforeach;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.responsive.min.js"></script>


<script>
    // تهيئة DataTable
    $('#rolesTable').DataTable({
        responsive: true,
        language: {
            url: "/ems/assets/i18n/datatables/ar.json"
        },
        columnDefs: [
            { "orderable": false, "targets": [6] }
        ]
    });

    $(document).ready(function () {
        // إظهار/إخفاء النموذج
        $('#toggleForm').on('click', function () {
            $('#roleForm').slideToggle(300);
            $('html, body').animate({
                scrollTop: $('#roleForm').offset().top - 100
            }, 500);
        });
    });

    // دالة تعديل البيانات
    function editRole(data) {
        document.getElementById('roleForm').style.display = 'block';
        document.getElementById('edit_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('parent_role_id').value = data.parent_role_id || '';
        document.getElementById('level').value = data.level || 1;
        document.getElementById('status').value = data.status || 1;

        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#roleForm').offset().top - 100
        }, 500);
    }

    // دالة تأكيد الحذف
    function confirmDelete(id, name) {
        if (confirm(`هل أنت متأكد من رغبتك في حذف الصلاحية "${name}"؟`)) {
            window.location.href = 'roles.php?delete_id=' + id;
        }
    }
</script>

</body>

</html>


