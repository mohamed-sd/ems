<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$perms = get_page_permissions($conn);
if (!$perms['can_view']) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ لا توجد صلاحية لعرض هذه الصفحة'));
    exit();
}

// صلاحية الاعتماد = صلاحية الحذف (مستوى الإدارة لا الإشراف) — لا يوجد دور مالي مستقل في النظام
$can_approve = !empty($perms['can_delete']);

// ── عزل الشركة ───────────────────────────────────────────────────────
$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=" . urlencode('لا توجد بيئة شركة صالحة للمستخدم ❌'));
    exit();
}
$company_val   = $is_super_admin ? null : $company_id;
$company_scope = $is_super_admin ? '' : " AND company_id = $company_id";

$has_model_table = function_exists('db_table_has_column') ? db_table_has_column($conn, 'fleet_model', 'id') : true;

/** أثر تدقيقي: يحفظ لقطة قبل/بعد كل تغيير */
function dep_audit($conn, $profile_id, $company_val, $action, $old, $new, $changed_by, $note = null)
{
    $st = $conn->prepare("INSERT INTO fleet_depreciation_profile_audit (profile_id, company_id, action, changed_by, old_data, new_data, note) VALUES (?,?,?,?,?,?,?)");
    if (!$st) return;
    $oj = ($old !== null) ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
    $nj = ($new !== null) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;
    $st->bind_param("iisisss", $profile_id, $company_val, $action, $changed_by, $oj, $nj, $note);
    $st->execute();
}

/** توليد كود تسلسلي تلقائي DEP-### ضمن الشركة */
function dep_next_code($conn, $is_super, $company_id)
{
    $where = $is_super ? "1=1" : ("company_id = " . intval($company_id));
    $res = mysqli_query($conn, "SELECT code FROM fleet_depreciation_profile WHERE $where AND code REGEXP '^DEP-[0-9]+$'");
    $max = 0;
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        $n = intval(substr($r['code'], 4));
        if ($n > $max) $max = $n;
    }
    return 'DEP-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}

/** قراءة صف ملف ضمن نطاق الشركة */
function dep_fetch($conn, $id, $company_scope)
{
    $st = $conn->prepare("SELECT * FROM fleet_depreciation_profile WHERE id = ? AND is_deleted = 0" . $company_scope);
    $st->bind_param("i", $id);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

$errors = [];
$flash  = isset($_GET['msg']) ? $_GET['msg'] : '';

// ── حذف ناعم (تعطيل) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!$perms['can_delete']) {
        header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('❌ لا توجد صلاحية للحذف'));
        exit();
    }
    $del_id = (int) $_POST['delete_id'];
    $old = dep_fetch($conn, $del_id, $company_scope);
    if ($old) {
        $st = $conn->prepare("UPDATE fleet_depreciation_profile SET is_deleted = 1 WHERE id = ?" . $company_scope);
        $st->bind_param("i", $del_id);
        $st->execute();
        dep_audit($conn, $del_id, $company_val, 'disabled', $old, null, $user_id, 'تعطيل ناعم');
    }
    header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('🗑️ تم تعطيل الملف'));
    exit();
}

// ── اعتماد ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    if (!$can_approve) {
        header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('❌ لا توجد صلاحية للاعتماد'));
        exit();
    }
    $app_id = (int) $_POST['approve_id'];
    $old = dep_fetch($conn, $app_id, $company_scope);
    if ($old && $old['state'] !== 'approved') {
        $st = $conn->prepare("UPDATE fleet_depreciation_profile SET state = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND state = 'draft'" . $company_scope);
        $st->bind_param("ii", $user_id, $app_id);
        $st->execute();
        $new = dep_fetch($conn, $app_id, $company_scope);
        dep_audit($conn, $app_id, $company_val, 'approved', $old, $new, $user_id, 'اعتماد الملف');
        header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('✅ تم اعتماد الملف'));
        exit();
    }
    header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('الملف معتمد مسبقاً'));
    exit();
}

// ── إضافة / تعديل ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id        = !empty($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $asset_category = trim($_POST['asset_category'] ?? '');
    $brand          = trim($_POST['brand'] ?? '');
    $model_id       = (isset($_POST['model_id']) && $_POST['model_id'] !== '') ? (int) $_POST['model_id'] : null;
    $method         = (($_POST['method'] ?? 'uop') === 'sl') ? 'sl' : 'uop';
    $useful_life    = ($_POST['useful_life'] ?? '') !== '' ? (float) $_POST['useful_life'] : null;
    $salvage_pct    = ($_POST['salvage_pct'] ?? '') !== '' ? (float) $_POST['salvage_pct'] : null;
    $notes          = trim($_POST['notes'] ?? '');

    if ($asset_category === '') $errors[] = 'فئة الأصل مطلوبة';
    if ($useful_life === null || $useful_life <= 0) $errors[] = 'العمر الإنتاجي يجب أن يكون أكبر من صفر';
    if ($salvage_pct === null || $salvage_pct < 0 || $salvage_pct > 1) $errors[] = 'نسبة التخريد يجب أن تكون بين 0 و 1';

    // التحقّق من الملكية + حماية الملفات المعتمدة
    $old_row = null;
    if (empty($errors) && $edit_id > 0) {
        $old_row = dep_fetch($conn, $edit_id, $company_scope);
        if (!$old_row) {
            $errors[] = 'الملف غير موجود أو لا يخصّ شركتك';
        } elseif ($old_row['state'] === 'approved' && !$can_approve) {
            // تعديل قيم ملف معتمد يتطلّب صلاحية الاعتماد (مستوى الإدارة)
            $errors[] = 'تعديل ملف معتمد يتطلّب صلاحية الاعتماد';
        }
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            $sql = "UPDATE fleet_depreciation_profile SET asset_category=?, brand=?, model_id=?, method=?, useful_life=?, salvage_pct=?, notes=? WHERE id=? AND is_deleted=0" . $company_scope;
            $st = $conn->prepare($sql);
            $st->bind_param("ssisddsi", $asset_category, $brand, $model_id, $method, $useful_life, $salvage_pct, $notes, $edit_id);
            $st->execute();
            $new_row = dep_fetch($conn, $edit_id, $company_scope);
            // أثر تدقيقي بأثر مستقبلي: لا حذف صامت للقيمة القديمة
            dep_audit($conn, $edit_id, $company_val, 'updated', $old_row, $new_row, $user_id, 'تعديل يسري مستقبلاً فقط');
            header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('✅ تم تحديث الملف (يسري على الحساب اللاحق فقط)'));
            exit();
        } else {
            $code = dep_next_code($conn, $is_super_admin, $company_id);
            $sql = "INSERT INTO fleet_depreciation_profile (company_id, code, asset_category, brand, model_id, method, useful_life, salvage_pct, notes, state, created_by) VALUES (?,?,?,?,?,?,?,?,?,'draft',?)";
            $st = $conn->prepare($sql);
            $st->bind_param("isssisddsi", $company_val, $code, $asset_category, $brand, $model_id, $method, $useful_life, $salvage_pct, $notes, $user_id);
            $st->execute();
            $new_id = $st->insert_id;
            $new_row = dep_fetch($conn, $new_id, $company_scope);
            dep_audit($conn, $new_id, $company_val, 'created', null, $new_row, $user_id, 'إنشاء ملف (مسودة)');
            header('Location: fleet_depreciation_profiles.php?msg=' . urlencode('✅ تم إضافة الملف (مسودة)'));
            exit();
        }
    }
}

// ── بيانات التعديل ───────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit_id'])) {
    $editData = dep_fetch($conn, (int) $_GET['edit_id'], $company_scope);
}

// ── قائمة الموديلات (لربط اختياري داخل الملف) ────────────────────────
$models = [];
if ($has_model_table) {
    $rm = @mysqli_query($conn, "SELECT id, code, model_name FROM fleet_model WHERE is_deleted = 0" . $company_scope . " ORDER BY code ASC");
    if ($rm) while ($r = $rm->fetch_assoc()) { $models[] = $r; }
}

$page_title = "إيكوبيشن | ملف الإهلاك المالي";
include("../inheader.php");
include("../insidebar.php");

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$method_label = function ($m) { return $m === 'sl' ? 'زمني (سنوات)' : 'بالساعة التشغيلية'; };
?>

<div class="main fleet-dep-main" style="padding:15px;background:#fff;">

    <?php
    $header_title   = 'ملف الافتراضات المالية والإهلاك';
    $header_icon    = 'fas fa-coins';
    $header_actions = array();
    if ($perms['can_add']) {
        $header_actions[] = array('tag' => 'button', 'id' => 'toggleForm', 'class' => 'add', 'icon' => 'fa-solid fa-plus-circle', 'label' => 'إضافة ملف جديد');
    }
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('fleet_depreciation_profiles', 'ملف الإهلاك المالي', $perms['can_add']) as $__xlAction) { $header_actions[] = $__xlAction; }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($flash)): ?>
        <div class="success-message is-success" style="margin:10px 0;"><?= $e($flash); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="success-message is-error" style="margin:10px 0;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $e(implode(' — ', $errors)); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info" style="background:#eef6ff;border:1px solid #cfe3fb;border-radius:8px;padding:10px 14px;margin:10px 0;font-size:13px;color:#1c4e80;">
        <i class="fa-solid fa-circle-info"></i>
        الملف ملك الإدارة المالية واعتماده لمستوى الإدارة. تعديل الافتراض <b>يسري على الحساب اللاحق فقط</b> ولا يُعدّل الإهلاك المُرحَّل، وكل تعديل يُسجَّل في الأثر التدقيقي.
    </div>

    <!-- نموذج إضافة / تعديل -->
    <form id="projectForm" method="post" class="allforms<?= (!empty($editData) || !empty($errors)) ? ' allforms-visible' : ''; ?>" style="margin:10px;">
    <div class="card-header">
                <h5><i class="fas fa-coins"></i> <?= !empty($editData) ? 'تعديل الملف' : 'إضافة ملف جديد'; ?>
                    <?php if (!empty($editData) && $editData['state'] === 'approved'): ?>
                        <span class="status-active" style="margin-inline-start:10px"><i class="fas fa-check-circle"></i> معتمد</span>
                    <?php elseif (!empty($editData)): ?>
                        <span class="status-inactive" style="margin-inline-start:10px">مسودة</span>
                    <?php endif; ?>
                </h5>
            </div>
    <div class="card">
            <div class="card-body">
                <?php if (!empty($editData)): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <?php if (!empty($editData)): ?>
                    <div>
                        <label>كود الملف</label>
                        <input type="text" value="<?= $e($editData['code']); ?>" readonly style="background:#f5f5f5">
                    </div>
                    <?php else: ?>
                    <div>
                        <label>كود الملف</label>
                        <input type="text" value="(يُولّد تلقائياً)" readonly style="background:#f5f5f5;color:#888">
                    </div>
                    <?php endif; ?>

                    <div>
                        <label>فئة الأصل <span style="color:#c0392b">*</span></label>
                        <input type="text" name="asset_category" required
                               placeholder="مثال: حفّار 22ط جديد"
                               value="<?= $e($editData['asset_category'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>الماركة (اختياري)</label>
                        <input type="text" name="brand" value="<?= $e($editData['brand'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>الموديل المرتبط (اختياري)</label>
                        <select name="model_id">
                            <option value="">-- بدون --</option>
                            <?php foreach ($models as $m): ?>
                                <option value="<?= (int) $m['id']; ?>"
                                    <?= (!empty($editData) && (int) $editData['model_id'] === (int) $m['id']) ? 'selected' : ''; ?>>
                                    <?= $e($m['code'] . ' — ' . $m['model_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>طريقة الإهلاك <span style="color:#c0392b">*</span></label>
                        <select name="method" id="methodSelect" required>
                            <option value="uop" <?= (!empty($editData) && $editData['method'] === 'uop') ? 'selected' : ''; ?>>بالساعة التشغيلية (UOP)</option>
                            <option value="sl"  <?= (!empty($editData) && $editData['method'] === 'sl') ? 'selected' : ''; ?>>زمني بالسنوات (SL)</option>
                        </select>
                    </div>

                    <div>
                        <label id="usefulLifeLabel">العمر الإنتاجي <span style="color:#c0392b">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="useful_life" required
                               value="<?= $e($editData['useful_life'] ?? ''); ?>">
                    </div>

                    <div>
                        <label>نسبة التخريد (0 إلى 1) <span style="color:#c0392b">*</span></label>
                        <input type="number" step="0.0001" min="0" max="1" name="salvage_pct" required
                               placeholder="مثال: 0.08"
                               value="<?= $e($editData['salvage_pct'] ?? ''); ?>">
                    </div>

                    <div style="grid-column:1/-1">
                        <label>ملاحظات / سياسات مالية</label>
                        <textarea name="notes" rows="2"><?= $e($editData['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> حفظ</button>
                    <button type="button" id="depFormCancel" class="btn-cancel"<?= !empty($editData) ? ' data-redirect="fleet_depreciation_profiles.php"' : ''; ?>>
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الملفات -->
    <div class="card">
         <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display fleet-dep-table">
                    <thead>
                        <tr>
                            <th>الإجراءات</th>
                            <th>#</th>
                            <th>الكود</th>
                            <th>فئة الأصل</th>
                            <th>الماركة/الموديل</th>
                            <th>الطريقة</th>
                            <th>العمر</th>
                            <th>التخريد</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $listSql =
                            "SELECT p.*, fm.code AS model_code, fm.model_name AS model_name
                             FROM fleet_depreciation_profile p
                             LEFT JOIN fleet_model fm ON fm.id = p.model_id
                             WHERE p.is_deleted = 0" . ($is_super_admin ? '' : " AND p.company_id = $company_id") . "
                             ORDER BY p.id DESC";
                        $list = @mysqli_query($conn, $listSql);
                        $i = 1;
                        if ($list) while ($row = $list->fetch_assoc()):
                            $unit = $row['method'] === 'sl' ? 'سنة' : 'ساعة';
                            $brand_model = trim(($row['brand'] ?? '') . (!empty($row['model_code']) ? (($row['brand'] ? ' / ' : '') . $row['model_code']) : ''));
                            ?>
                            <tr>
                                <td class="text-center">
                                    <div class="action-btns">
                                        <?php if ($perms['can_edit']): ?>
                                            <a href="fleet_depreciation_profiles.php?edit_id=<?= (int) $row['id']; ?>" class="action-btn edit" title="تعديل">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($can_approve && $row['state'] === 'draft'): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('اعتماد هذا الملف؟ سيصبح متاحاً للربط بالموديلات.');">
                                                <input type="hidden" name="approve_id" value="<?= (int) $row['id']; ?>">
                                                <button type="submit" class="action-btn" style="color:#1f9d55" title="اعتماد">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($perms['can_delete']): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('تعطيل هذا الملف؟');">
                                                <input type="hidden" name="delete_id" value="<?= (int) $row['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="تعطيل"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= $i++; ?></td>
                                <td><?= $e($row['code']); ?></td>
                                <td><?= $e($row['asset_category']); ?></td>
                                <td><?= $e($brand_model !== '' ? $brand_model : '—'); ?></td>
                                <td><?= $e($method_label($row['method'])); ?></td>
                                <td><?= $e(rtrim(rtrim(number_format((float) $row['useful_life'], 2, '.', ''), '0'), '.')); ?> <?= $e($unit); ?></td>
                                <td><?= $e(rtrim(rtrim(number_format((float) $row['salvage_pct'] * 100, 2, '.', ''), '0'), '.')); ?>%</td>
                                <td>
                                    <?= $row['state'] === 'approved'
                                        ? "<span class='status-active'><i class='fas fa-check-circle'></i> معتمد</span>"
                                        : "<span class='status-inactive'>مسودة</span>"; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script>
    $(document).ready(function () {
        $('#projectsTable').DataTable({
            language: { url: "/ems/assets/i18n/datatables/ar.json" }
        });

        $('#toggleForm').on('click', function () {
            const $form = $('#projectForm');
            if ($form.hasClass('allforms-visible')) {
                $form.removeClass('allforms-visible').slideUp(200);
            } else {
                $form.addClass('allforms-visible').hide().slideDown(250);
            }
        });

        // زر الإلغاء: في وضع التعديل يعود للقائمة، وفي وضع الإضافة يطوي النموذج
        $('#depFormCancel').on('click', function () {
            const redirect = $(this).data('redirect');
            if (redirect) { window.location.href = redirect; return; }
            $('#projectForm').removeClass('allforms-visible').slideUp(200);
        });

        // تبديل وحدة العمر الإنتاجي حسب الطريقة
        function syncUnit() {
            var m = document.getElementById('methodSelect').value;
            var lbl = document.getElementById('usefulLifeLabel');
            lbl.innerHTML = (m === 'sl' ? 'العمر الإنتاجي (سنوات)' : 'العمر الإنتاجي (ساعة تشغيلية)') + ' <span style="color:#c0392b">*</span>';
        }
        var ms = document.getElementById('methodSelect');
        if (ms) { ms.addEventListener('change', syncUnit); syncUnit(); }
    });
</script>
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>
