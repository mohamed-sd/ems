<?php
/**
 * Governance/perm_link_groups.php — مجموعات السايدبار (PERM-SCR-01 ⑤)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **المجموعة وعاء بنود لا زينة**: `link_groups` هي ما يجمع بنود الملاحة
 *   تحت راس مطوي في سايدبار الدور المالك.
 * ⚠ **وراس الطي المصير قد يكون غيرها**: المصير الحاكم يجمع بمجموعات دورة
 *   المساحة (`nav_lifecycle_groups`)، وهذا الجدول مرجع `nav_items.group_id`.
 *   فتعطيل مجموعة هنا يسحب بنودها من المصدر الموحد لا من كل مسار تصيير.
 * ⛔ ولا تحذف مجموعة فيها بنود: البند بلا وعاء يسقط من السايدبار صامتا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_link_groups.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لمجموعات السايدبار', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}
$can_add    = $is_super_admin || !empty($__pp['can_add']);
$can_edit   = $is_super_admin || !empty($__pp['can_edit']);
$can_delete = $is_super_admin || !empty($__pp['can_delete']);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $err = 'رمز الحماية غير صالح. اعد تحميل الصفحة.';
    } else {
        $act    = (string) ($_POST['act'] ?? '');
        $name   = trim((string) ($_POST['name'] ?? ''));
        $owner  = (int) ($_POST['owner_role_id'] ?? 0);
        $icon   = trim((string) ($_POST['icon'] ?? 'fa fa-folder'));
        $ord    = (int) ($_POST['display_order'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;

        if ($act === 'add' && $can_add) {
            if (mb_strlen($name) < 2 || $owner <= 0) {
                $err = 'الاسم والدور المالك مطلوبان.';
            } else {
                $st = mysqli_prepare($conn, 'INSERT INTO link_groups (name, owner_role_id, icon, display_order, is_active) VALUES (?,?,?,?,?)');
                mysqli_stmt_bind_param($st, 'sisii', $name, $owner, $icon, $ord, $active);
                if (mysqli_stmt_execute($st)) { $msg = 'اضيفت المجموعة'; } else { $err = 'تعذر الحفظ'; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'edit' && $can_edit) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || mb_strlen($name) < 2 || $owner <= 0) {
                $err = 'بيانات غير مكتملة.';
            } else {
                $st = mysqli_prepare($conn, 'UPDATE link_groups SET name=?, owner_role_id=?, icon=?, display_order=?, is_active=? WHERE id=?');
                mysqli_stmt_bind_param($st, 'sisiii', $name, $owner, $icon, $ord, $active, $id);
                if (mysqli_stmt_execute($st)) { $msg = 'حدثت المجموعة'; } else { $err = 'تعذر التحديث'; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'delete' && $can_delete) {
            $id = (int) ($_POST['id'] ?? 0);
            $items = 0;
            if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM nav_items WHERE group_id = ?')) {
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $items);
                mysqli_stmt_fetch($st); mysqli_stmt_close($st);
            }
            if ($id <= 0) {
                $err = 'مجموعة غير محددة.';
            } elseif ($items > 0) {
                $err = 'لا تحذف المجموعة: فيها ' . (int) $items . ' بند ملاحة. انقلها او احذفها اولا.';
            } else {
                $st = mysqli_prepare($conn, 'DELETE FROM link_groups WHERE id = ?');
                mysqli_stmt_bind_param($st, 'i', $id);
                if (mysqli_stmt_execute($st)) { $msg = 'حذفت المجموعة'; } else { $err = 'تعذر الحذف'; }
                mysqli_stmt_close($st);
            }
        } else {
            $err = 'غير مصرح بهذا الاجراء';
        }
    }
}

$fowner  = (string) ($_GET['owner'] ?? '');
$factive = (string) ($_GET['is_active'] ?? '');
$where = array(); $types = ''; $args = array();
if ($fowner !== '')  { $where[] = 'g.owner_role_id = ?'; $types .= 'i'; $args[] = (int) $fowner; }
if ($factive !== '') { $where[] = 'g.is_active = ?';     $types .= 'i'; $args[] = (int) $factive; }
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sql = 'SELECT g.id, g.name, g.group_code, g.owner_role_id, g.icon, g.display_order, g.is_active, r.name
          FROM link_groups g LEFT JOIN roles r ON r.id = g.owner_role_id' . $wsql . '
         ORDER BY g.owner_role_id, g.display_order, g.id';
$rows = array();
if ($st = mysqli_prepare($conn, $sql)) {
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$args); }
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($x = mysqli_fetch_row($rs))) {
        $rows[] = array('id' => (int) $x[0], 'name' => $x[1], 'group_code' => $x[2],
                        'owner_role_id' => (int) $x[3], 'icon' => $x[4],
                        'display_order' => (int) $x[5], 'is_active' => (int) $x[6], 'owner_name' => $x[7]);
    }
    mysqli_stmt_close($st);
}

/* عدد البنود لكل مجموعة - خريطة مجمعة تدمج في PHP. */
$itemsBy = array();
$g = @mysqli_query($conn, 'SELECT group_id, COUNT(*) FROM nav_items WHERE active = 1 GROUP BY group_id');
while ($g && ($x = mysqli_fetch_row($g))) { $itemsBy[(int) $x[0]] = (int) $x[1]; }
foreach ($rows as $i => $x) { $rows[$i]['items_n'] = isset($itemsBy[$x['id']]) ? $itemsBy[$x['id']] : 0; }

$allRoles = array();
$ar = @mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY name');
while ($ar && ($x = mysqli_fetch_row($ar))) { $allRoles[] = array('id' => (int) $x[0], 'name' => $x[1]); }

$total = count($rows); $onN = 0; $emptyN = 0;
foreach ($rows as $x) {
    if ($x['is_active'] === 1) { $onN++; }
    if ($x['items_n'] === 0) { $emptyN++; }
}

$page_title = 'ايكوبيشن | مجموعات السايدبار';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function pg_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'مجموعات السايدبار';
    $header_icon = 'fa fa-layer-group';
    $header_actions = $can_add ? array(
        array('tag' => 'button', 'id' => 'pgAddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'اضافة مجموعة', 'title' => 'اضافة مجموعة روابط', 'attrs' => 'type="button"'),
    ) : array();
    $header_back = false;
    include '../includes/page_header.php';
    if ($msg !== '') { echo '<div class="alert alert-success">' . pg_e($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . pg_e($err) . '</div>'; }
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا مجموعات مسجلة بعد', 'اضف اول مجموعة بزر اضافة مجموعة اعلى الشاشة');
    }
    ?>

    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'المجموعات المعروضة', 'value' => number_format($total), 'unit' => 'مجموعة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_link_groups.php',
            'comparison' => 'بعد التصفية', 'icon' => 'fa-layer-group', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'النشطة', 'value' => number_format($onN), 'unit' => 'مجموعة',
            'period' => $NOW, 'status' => 'ok', 'drill' => 'perm_link_groups.php?is_active=1',
            'comparison' => 'من ' . $total . ' معروضة', 'icon' => 'fa-circle-check', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'المعطلة', 'value' => number_format($total - $onN), 'unit' => 'مجموعة',
            'period' => $NOW, 'status' => ($total - $onN) > 0 ? 'warn' : 'ok',
            'drill' => 'perm_link_groups.php?is_active=0',
            'comparison' => 'من ' . $total . ' معروضة', 'icon' => 'fa-circle-minus', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'بلا بنود', 'value' => number_format($emptyN), 'unit' => 'مجموعة',
            'period' => $NOW, 'status' => $emptyN > 0 ? 'warn' : 'ok', 'drill' => 'perm_nav_items.php',
            'comparison' => 'وعاء فارغ لا يظهر', 'icon' => 'fa-box-open', 'class' => 'ems-col-3'));
        ?>
    </div>

    <?php if ($can_add): ?>
    <form method="post" action="" class="allforms" id="pgAddForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="add">
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-plus"></i> اضافة مجموعة</h5></div>
            <div class="card-body">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label for="pg_name">اسم المجموعة</label>
                        <input type="text" id="pg_name" name="name" maxlength="100" required></div>
                    <div class="form-group"><label for="pg_owner">الدور المالك</label>
                        <select id="pg_owner" name="owner_role_id" required>
                            <option value="">- اختر -</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pg_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pg_icon">الايقونة</label>
                        <input type="text" id="pg_icon" name="icon" maxlength="50" value="fa fa-folder"></div>
                    <div class="form-group"><label for="pg_order">ترتيب الظهور</label>
                        <input type="number" id="pg_order" name="display_order" value="0"></div>
                    <div class="form-group"><label for="pg_active">الحالة</label>
                        <select id="pg_active" name="is_active"><option value="1">نشطة</option><option value="0">معطلة</option></select></div>
                </div></div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                    <button type="button" class="btn-secondary" id="pgCancelBtn"><i class="fa fa-times"></i> الغاء</button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <div class="filter">
        <div class="filter-title">تصفية</div>
        <div class="filter-body">
            <form method="get" action="">
                <div class="form-grid">
                    <div class="form-group"><label for="f_owner">الدور المالك</label>
                        <select id="f_owner" name="owner">
                            <option value="">الكل</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $fowner === (string) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo pg_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="f_active">الحالة</label>
                        <select id="f_active" name="is_active">
                            <option value="">الكل</option>
                            <option value="1" <?php echo $factive === '1' ? 'selected' : ''; ?>>نشطة</option>
                            <option value="0" <?php echo $factive === '0' ? 'selected' : ''; ?>>معطلة</option>
                        </select></div>
                </div>
                <button type="submit" class="btn-primary"><i class="fa fa-search"></i> بحث</button>
                <a class="btn-secondary" href="perm_link_groups.php"><i class="fa fa-rotate-left"></i> اعادة</a>
            </form>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
            <table class="alltables display" id="permGroupsTable" data-page-length="25">
                <thead><tr>
                    <th>#</th><th>الاسم</th><th>الرمز</th><th>الدور المالك</th>
                    <th>الايقونة</th><th>الترتيب</th><th>بنود</th><th>الحالة</th><th>الاجراءات</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $x): ?>
                    <tr>
                        <td><?php echo (int) $x['id']; ?></td>
                        <td><?php echo pg_e($x['name']); ?></td>
                        <td><?php echo $x['group_code'] !== null ? pg_e($x['group_code']) : 'بلا رمز'; ?></td>
                        <td><?php echo $x['owner_name'] !== null ? pg_e($x['owner_name']) : 'بلا مالك'; ?></td>
                        <td><i class="<?php echo pg_e($x['icon']); ?>"></i></td>
                        <td><?php echo (int) $x['display_order']; ?></td>
                        <td><?php echo (int) $x['items_n']; ?></td>
                        <td><?php echo $x['is_active'] === 1 ? 'نشطة' : 'معطلة'; ?></td>
                        <td>
                            <?php if ($can_edit): ?>
                            <button type="button" class="btn-secondary pgEdit"
                                data-id="<?php echo (int) $x['id']; ?>"
                                data-name="<?php echo pg_e($x['name']); ?>"
                                data-owner="<?php echo (int) $x['owner_role_id']; ?>"
                                data-icon="<?php echo pg_e($x['icon']); ?>"
                                data-order="<?php echo (int) $x['display_order']; ?>"
                                data-active="<?php echo (int) $x['is_active']; ?>">تعديل</button>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                            <form method="post" action="" class="ems-inline-form"
                                  onsubmit="return confirm('حذف المجموعة؟');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="act" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $x['id']; ?>">
                                <button type="submit" class="btn-danger">حذف</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>

    <?php if ($can_edit): ?>
    <form method="post" action="" class="allforms" id="pgEditForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="edit">
        <input type="hidden" name="id" id="pge_id">
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-pen"></i> تعديل مجموعة</h5></div>
            <div class="card-body">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label for="pge_name">الاسم</label>
                        <input type="text" id="pge_name" name="name" maxlength="100" required></div>
                    <div class="form-group"><label for="pge_owner">الدور المالك</label>
                        <select id="pge_owner" name="owner_role_id" required>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pg_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pge_icon">الايقونة</label>
                        <input type="text" id="pge_icon" name="icon" maxlength="50"></div>
                    <div class="form-group"><label for="pge_order">الترتيب</label>
                        <input type="number" id="pge_order" name="display_order"></div>
                    <div class="form-group"><label for="pge_active">الحالة</label>
                        <select id="pge_active" name="is_active"><option value="1">نشطة</option><option value="0">معطلة</option></select></div>
                </div></div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ التعديل</button>
                    <button type="button" class="btn-secondary" id="pgEditCancel"><i class="fa fa-times"></i> الغاء</button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
(function () {
    var b = document.getElementById('pgAddBtn'), f = document.getElementById('pgAddForm'),
        c = document.getElementById('pgCancelBtn');
    if (b && f) { b.addEventListener('click', function (e) { e.preventDefault();
        f.classList.toggle('allforms-visible');
        if (f.classList.contains('allforms-visible')) { f.scrollIntoView({behavior:'smooth',block:'nearest'}); } }); }
    if (c && f) { c.addEventListener('click', function () { f.classList.remove('allforms-visible'); }); }
    var ef = document.getElementById('pgEditForm'), ec = document.getElementById('pgEditCancel');
    if (ef) {
        document.querySelectorAll('.pgEdit').forEach(function (x) {
            x.addEventListener('click', function () {
                document.getElementById('pge_id').value = x.dataset.id;
                document.getElementById('pge_name').value = x.dataset.name;
                document.getElementById('pge_owner').value = x.dataset.owner;
                document.getElementById('pge_icon').value = x.dataset.icon;
                document.getElementById('pge_order').value = x.dataset.order;
                document.getElementById('pge_active').value = x.dataset.active;
                ef.classList.add('allforms-visible');
                ef.scrollIntoView({behavior:'smooth',block:'nearest'});
            });
        });
    }
    if (ec && ef) { ec.addEventListener('click', function () { ef.classList.remove('allforms-visible'); }); }
})();
</script>
