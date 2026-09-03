<?php
/**
 * Governance/perm_matrix.php — مصفوفةُ الصلاحيات (PERM-SCR-01 ④)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **جدولٌ محوريٌّ لا سجلّ**: صفٌّ لكلِّ وحدةٍ وأربعةُ أعمدةٍ (عرض · إضافة ·
 *   تعديل · حذف) لدورٍ **واحدٍ يُختار**. والحفظُ يكتب في `role_permissions`.
 * ◆ **والحفظُ فرقيٌّ لا كاسح**: يُقارَن المُرسَلُ بالقائمِ فتُحدَّث المتغيّرةُ
 *   وحدَها، وتُحذف الصفوفُ التي صارت أربعةَ أصفار. فلا يُمسح منحُ دورٍ آخرَ
 *   ولا تتضخّم الجداولُ بصفوفٍ صفريّة.
 * ⚠ **وطبقةُ القوالبِ فوقَ هذا الجدول**: من كان مغطًّى بقالبٍ نافذٍ
 *   (`gov_authority_grants`) يُحكَم بقالبِه لا بهذه المصفوفةِ — والشاشةُ تقول
 *   ذلك صراحةً كي لا يُقرأ الصفُّ هنا وعدًا لا يُنفَّذ.
 * ⛔ ولا تُعرض المصفوفةُ بلا اختيارِ دور: 774 وحدةً في 35 دورًا جدولٌ لا يُقرأ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_matrix.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لمصفوفة الصلاحيات', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}
$can_edit = $is_super_admin || !empty($__pp['can_edit']);

$msg = ''; $err = '';
$roleId = (int) ($_POST['role_id'] ?? $_GET['role_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $err = 'رمز الحماية غير صالح. اعد تحميل الصفحة.';
    } elseif (!$can_edit) {
        $err = 'غير مصرح بالتعديل';
    } elseif ($roleId <= 0) {
        $err = 'اختر دورا اولا';
    } else {
        /* القائمُ قبلَ الكتابة — الأساسُ الذي يُقارَن به المُرسَل. */
        $cur = array();
        if ($st = mysqli_prepare($conn, 'SELECT module_id, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = ?')) {
            mysqli_stmt_bind_param($st, 'i', $roleId);
            mysqli_stmt_execute($st);
            $rs = mysqli_stmt_get_result($st);
            while ($rs && ($x = mysqli_fetch_row($rs))) {
                $cur[(int) $x[0]] = array((int) $x[1], (int) $x[2], (int) $x[3], (int) $x[4]);
            }
            mysqli_stmt_close($st);
        }
        $sent = isset($_POST['p']) && is_array($_POST['p']) ? $_POST['p'] : array();
        $ins = 0; $upd = 0; $del = 0;
        foreach ($sent as $mid => $flags) {
            $mid = (int) $mid;
            if ($mid <= 0) { continue; }
            $v = array(
                !empty($flags['v']) ? 1 : 0, !empty($flags['a']) ? 1 : 0,
                !empty($flags['e']) ? 1 : 0, !empty($flags['d']) ? 1 : 0,
            );
            $had = isset($cur[$mid]) ? $cur[$mid] : null;
            $zero = ($v[0] + $v[1] + $v[2] + $v[3]) === 0;
            if ($had === null && $zero) { continue; }
            if ($had !== null && $had === $v) { continue; }
            if ($zero) {
                if ($st = mysqli_prepare($conn, 'DELETE FROM role_permissions WHERE role_id = ? AND module_id = ?')) {
                    mysqli_stmt_bind_param($st, 'ii', $roleId, $mid);
                    if (mysqli_stmt_execute($st)) { $del++; }
                    mysqli_stmt_close($st);
                }
            } elseif ($had === null) {
                if ($st = mysqli_prepare($conn, 'INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) VALUES (?,?,?,?,?,?)')) {
                    mysqli_stmt_bind_param($st, 'iiiiii', $roleId, $mid, $v[0], $v[1], $v[2], $v[3]);
                    if (mysqli_stmt_execute($st)) { $ins++; }
                    mysqli_stmt_close($st);
                }
            } else {
                if ($st = mysqli_prepare($conn, 'UPDATE role_permissions SET can_view=?, can_add=?, can_edit=?, can_delete=? WHERE role_id=? AND module_id=?')) {
                    mysqli_stmt_bind_param($st, 'iiiiii', $v[0], $v[1], $v[2], $v[3], $roleId, $mid);
                    if (mysqli_stmt_execute($st)) { $upd++; }
                    mysqli_stmt_close($st);
                }
            }
        }
        $msg = 'حفظ الفروق: اضيف ' . $ins . ' وحدث ' . $upd . ' وحذف ' . $del;
    }
}

$allRoles = array();
$ar = @mysqli_query($conn, 'SELECT id, name, status FROM roles ORDER BY name');
while ($ar && ($x = mysqli_fetch_row($ar))) {
    $allRoles[] = array('id' => (int) $x[0], 'name' => $x[1], 'status' => (string) $x[2]);
}

/* مرشِّحُ عمودٍ: الدورُ المالكُ للوحدة — يقصر المصفوفةَ على مجالِ عملٍ واحد. */
$fowner = (string) ($_GET['owner'] ?? '');
$rows = array(); $granted = array();
$roleName = '';
if ($roleId > 0) {
    foreach ($allRoles as $r) { if ($r['id'] === $roleId) { $roleName = $r['name']; break; } }
    $sql = 'SELECT m.id, m.name, m.code, r.name FROM modules m LEFT JOIN roles r ON r.id = m.owner_role_id';
    $types = ''; $args = array();
    if ($fowner === '0') { $sql .= ' WHERE (m.owner_role_id IS NULL OR m.owner_role_id = 0)'; }
    elseif ($fowner !== '') { $sql .= ' WHERE m.owner_role_id = ?'; $types = 'i'; $args[] = (int) $fowner; }
    $sql .= ' ORDER BY m.display_order, m.id';
    if ($st = mysqli_prepare($conn, $sql)) {
        if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$args); }
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && ($x = mysqli_fetch_row($rs))) {
            $rows[] = array('id' => (int) $x[0], 'name' => $x[1], 'code' => $x[2], 'owner_name' => $x[3]);
        }
        mysqli_stmt_close($st);
    }
    if ($st = mysqli_prepare($conn, 'SELECT module_id, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = ?')) {
        mysqli_stmt_bind_param($st, 'i', $roleId);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && ($x = mysqli_fetch_row($rs))) {
            $granted[(int) $x[0]] = array((int) $x[1], (int) $x[2], (int) $x[3], (int) $x[4]);
        }
        mysqli_stmt_close($st);
    }
}

$total = count($rows);
$withView = 0; $withWrite = 0;
foreach ($rows as $x) {
    $g = isset($granted[$x['id']]) ? $granted[$x['id']] : null;
    if ($g && $g[0]) { $withView++; }
    if ($g && ($g[1] || $g[2] || $g[3])) { $withWrite++; }
}
$coverage = $total > 0 ? round(($withView / $total) * 100, 1) : 0.0;

$page_title = 'ايكوبيشن | مصفوفة الصلاحيات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function px_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function px_ck($granted, $mid, $i) {
    return (isset($granted[$mid]) && !empty($granted[$mid][$i])) ? ' checked' : '';
}
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'مصفوفة الصلاحيات';
    $header_icon = 'fa fa-table';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    if ($msg !== '') { echo '<div class="alert alert-success">' . px_e($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . px_e($err) . '</div>'; }
    ?>

    <div class="alert alert-info">
        المصفوفة تمنح الادوار حق العرض والاضافة والتعديل والحذف على كل وحدة.
        <strong>ومن كان مغطى بقالب سلطة نافذ يحكم بقالبه لا بهذه المصفوفة</strong>
        فالصف هنا لا ينفذ له حتى يضاف الى قالبه.
    </div>

    <div class="filter">
        <div class="filter-title">اختر الدور ثم صف الوحدات</div>
        <div class="filter-body">
            <form method="get" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="f_role">الدور</label>
                        <select id="f_role" name="role_id">
                            <option value="0">- اختر دورا -</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $roleId === $r['id'] ? 'selected' : ''; ?>>
                                <?php echo px_e($r['name']); ?><?php echo $r['status'] === '1' ? '' : ' (معطل)'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="f_owner">وحدات الدور المالك</label>
                        <select id="f_owner" name="owner">
                            <option value="">كل الوحدات</option>
                            <option value="0" <?php echo $fowner === '0' ? 'selected' : ''; ?>>بلا مالك</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $fowner === (string) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo px_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اعرض المصفوفة</button>
            </form>
        </div>
    </div>

    <?php if ($roleId <= 0): ?>
        <?php echo function_exists('ems_state_empty')
            ? ems_state_empty('اختر دورا لعرض مصفوفته', '', '')
            : '<div class="card"><div class="card-body">اختر دورا لعرض مصفوفته</div></div>'; ?>
    <?php else: ?>

    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'الوحدات المعروضة', 'value' => number_format($total), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_modules.php',
            'comparison' => 'للدور ' . $roleName, 'icon' => 'fa-cubes', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'لها حق العرض', 'value' => number_format($withView), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => $withView > 0 ? 'ok' : 'warn', 'drill' => 'perm_matrix.php?role_id=' . $roleId,
            'comparison' => 'من ' . $total . ' معروضة', 'icon' => 'fa-eye', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'لها حق كتابة', 'value' => number_format($withWrite), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_matrix.php?role_id=' . $roleId,
            'comparison' => 'اضافة او تعديل او حذف', 'icon' => 'fa-pen', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'التغطية', 'value' => $coverage, 'unit' => 'بالمئة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_dashboard.php',
            'comparison' => 'نسبة ما له حق العرض', 'icon' => 'fa-chart-pie', 'class' => 'ems-col-3'));
        ?>
    </div>

    <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="role_id" value="<?php echo (int) $roleId; ?>">
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-table"></i> مصفوفة الدور <?php echo px_e($roleName); ?></h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="alltables display" id="permMatrixTable" data-page-length="50">
                        <thead><tr>
                            <th>#</th><th>الوحدة</th><th>الكود</th><th>الدور المالك</th>
                            <th>عرض</th><th>اضافة</th><th>تعديل</th><th>حذف</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($rows as $x): $mid = $x['id']; ?>
                            <tr>
                                <td><?php echo (int) $mid; ?></td>
                                <td><?php echo px_e($x['name']); ?></td>
                                <td><code><?php echo px_e($x['code']); ?></code></td>
                                <td><?php echo $x['owner_name'] !== null ? px_e($x['owner_name']) : 'بلا مالك'; ?></td>
                                <td><input type="checkbox" name="p[<?php echo (int) $mid; ?>][v]" value="1"<?php echo px_ck($granted, $mid, 0) . ($can_edit ? '' : ' disabled'); ?>></td>
                                <td><input type="checkbox" name="p[<?php echo (int) $mid; ?>][a]" value="1"<?php echo px_ck($granted, $mid, 1) . ($can_edit ? '' : ' disabled'); ?>></td>
                                <td><input type="checkbox" name="p[<?php echo (int) $mid; ?>][e]" value="1"<?php echo px_ck($granted, $mid, 2) . ($can_edit ? '' : ' disabled'); ?>></td>
                                <td><input type="checkbox" name="p[<?php echo (int) $mid; ?>][d]" value="1"<?php echo px_ck($granted, $mid, 3) . ($can_edit ? '' : ' disabled'); ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit): ?>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ الفروق</button>
                </div>
                <p>الحفظ فرقي: تكتب المتغيرة وحدها، والصف الذي صار اربعة اصفار يحذف.</p>
                <?php endif; ?>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
