<?php
/**
 * Governance/perm_nav_items.php — بنود الملاحة (PERM-SCR-01 ⑥)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **هذا ما يصير في السايدبار**: `nav_items` المصدر الموحد لبنود التنقل -
 *   صف لكل (دور × مسار) بمجموعته وبابه وترتيبه.
 * ⛔ **والاعمدة `label_ar` و`route` لا `label` و`link`** - من المخطط الحي.
 * ⛔ **و`permission_code` هو مفتاح الحارس**: البند بلا كود صلاحية يظهر لكل
 *   من يملك الدور بلا سؤال عن الشاشة. فالافتراض ان يساوي المسار.
 * ⚠ **والظهور ليس هذا الجدول وحده**: المصير الحاكم ياخذ المواضع من سجل
 *   مواضع المساحة ويستعمل هذا الجدول **مرشحا بالصلاحية**. فبند هنا بلا موضع
 *   هناك لا يظهر - والشاشة تقول ذلك كي لا يقرا الصف وعدا لا ينفذ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_nav_items.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لبنود الملاحة', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}
$can_add    = $is_super_admin || !empty($__pp['can_add']);
$can_edit   = $is_super_admin || !empty($__pp['can_edit']);
$can_delete = $is_super_admin || !empty($__pp['can_delete']);

/* مفردات الباب تشتق من المستعمل فعلا - لا قائمة صلبة تتقادم. */
$DOORS = array();
$dq = @mysqli_query($conn, 'SELECT door, COUNT(*) FROM nav_items GROUP BY door ORDER BY door');
while ($dq && ($x = mysqli_fetch_row($dq))) { $DOORS[(string) $x[0]] = (int) $x[1]; }

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $err = 'رمز الحماية غير صالح. اعد تحميل الصفحة.';
    } else {
        $act   = (string) ($_POST['act'] ?? '');
        $id    = (int) ($_POST['id'] ?? 0);
        $rid   = (int) ($_POST['role_id'] ?? 0);
        $gid   = (int) ($_POST['group_id'] ?? 0);
        $mid   = (int) ($_POST['module_id'] ?? 0);
        $label = trim((string) ($_POST['label_ar'] ?? ''));
        $route = trim((string) ($_POST['route'] ?? ''));
        $icon  = trim((string) ($_POST['icon'] ?? 'fa fa-circle-dot'));
        $ord   = (int) ($_POST['sort_order'] ?? 0);
        $door  = (string) ($_POST['door'] ?? '');
        $pcode = trim((string) ($_POST['permission_code'] ?? ''));
        if ($pcode === '') { $pcode = $route; }

        if ($act === 'toggle' && $can_edit) {
            if ($id <= 0) {
                $err = 'بند غير محدد.';
            } else {
                /* القلب يقرا ثم يكتب النقيض - فلا يعتمد على قيمة مرسلة من المتصفح. */
                $cur = -1;
                if ($st = mysqli_prepare($conn, 'SELECT active FROM nav_items WHERE id = ?')) {
                    mysqli_stmt_bind_param($st, 'i', $id);
                    mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $cur);
                    mysqli_stmt_fetch($st); mysqli_stmt_close($st);
                }
                if ($cur < 0) {
                    $err = 'البند غير موجود.';
                } else {
                    $next = $cur ? 0 : 1;
                    $st = mysqli_prepare($conn, 'UPDATE nav_items SET active = ? WHERE id = ?');
                    mysqli_stmt_bind_param($st, 'ii', $next, $id);
                    if (mysqli_stmt_execute($st)) { $msg = $next ? 'فعل البند' : 'عطل البند'; }
                    else { $err = 'تعذر التبديل'; }
                    mysqli_stmt_close($st);
                }
            }
        } elseif ($act === 'add' && $can_add) {
            if ($rid <= 0 || $gid <= 0 || mb_strlen($label) < 2 || $route === '' || $door === '') {
                $err = 'الدور والمجموعة والتسمية والمسار والباب مطلوبة.';
            } elseif (!isset($DOORS[$door])) {
                $err = 'الباب غير معروف.';
            } else {
                $st = mysqli_prepare($conn, 'INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
                $mv = $mid > 0 ? $mid : null;
                mysqli_stmt_bind_param($st, 'isiisssis', $rid, $door, $gid, $mv, $label, $route, $icon, $ord, $pcode);
                if (mysqli_stmt_execute($st)) { $msg = 'اضيف البند'; } else { $err = 'تعذر الحفظ'; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'edit' && $can_edit) {
            if ($id <= 0 || $rid <= 0 || $gid <= 0 || mb_strlen($label) < 2 || $route === '' || !isset($DOORS[$door])) {
                $err = 'بيانات غير مكتملة.';
            } else {
                $st = mysqli_prepare($conn, 'UPDATE nav_items SET role_id=?, door=?, group_id=?, module_id=?, label_ar=?, route=?, icon=?, sort_order=?, permission_code=?, updated_at=NOW() WHERE id=?');
                $mv = $mid > 0 ? $mid : null;
                mysqli_stmt_bind_param($st, 'isiisssisi', $rid, $door, $gid, $mv, $label, $route, $icon, $ord, $pcode, $id);
                if (mysqli_stmt_execute($st)) { $msg = 'حدث البند'; } else { $err = 'تعذر التحديث'; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'delete' && $can_delete) {
            if ($id <= 0) {
                $err = 'بند غير محدد.';
            } else {
                $st = mysqli_prepare($conn, 'DELETE FROM nav_items WHERE id = ?');
                mysqli_stmt_bind_param($st, 'i', $id);
                if (mysqli_stmt_execute($st)) { $msg = 'حذف البند'; } else { $err = 'تعذر الحذف'; }
                mysqli_stmt_close($st);
            }
        } else {
            $err = 'غير مصرح بهذا الاجراء';
        }
    }
}

$frole   = (string) ($_GET['role_id'] ?? '');
$fgroup  = (string) ($_GET['group_id'] ?? '');
$fdoor   = (string) ($_GET['door'] ?? '');
$factive = (string) ($_GET['active'] ?? '');
$where = array(); $types = ''; $args = array();
if ($frole !== '')   { $where[] = 'n.role_id = ?';  $types .= 'i'; $args[] = (int) $frole; }
if ($fgroup !== '')  { $where[] = 'n.group_id = ?'; $types .= 'i'; $args[] = (int) $fgroup; }
if ($fdoor !== '')   { $where[] = 'n.door = ?';     $types .= 's'; $args[] = $fdoor; }
if ($factive !== '') { $where[] = 'n.active = ?';   $types .= 'i'; $args[] = (int) $factive; }
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* ⛔ بلا مرشح يصير الجدول الفين وسبعمئة صف - يقصر العرض على دور واحد ابتداء. */
$rows = array();
if ($wsql !== '') {
    $sql = 'SELECT n.id, n.role_id, n.door, n.group_id, n.module_id, n.label_ar, n.route,
                   n.icon, n.sort_order, n.permission_code, n.active, r.name, g.name
              FROM nav_items n
              LEFT JOIN roles r ON r.id = n.role_id
              LEFT JOIN link_groups g ON g.id = n.group_id' . $wsql . '
             ORDER BY n.role_id, n.group_id, n.sort_order, n.id';
    if ($st = mysqli_prepare($conn, $sql)) {
        if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$args); }
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && ($x = mysqli_fetch_row($rs))) {
            $rows[] = array('id' => (int) $x[0], 'role_id' => (int) $x[1], 'door' => $x[2],
                            'group_id' => (int) $x[3], 'module_id' => (int) $x[4], 'label_ar' => $x[5],
                            'route' => $x[6], 'icon' => $x[7], 'sort_order' => (int) $x[8],
                            'permission_code' => $x[9], 'active' => (int) $x[10],
                            'role_name' => $x[11], 'group_name' => $x[12]);
        }
        mysqli_stmt_close($st);
    }
}

$allRoles = array();
$ar = @mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY name');
while ($ar && ($x = mysqli_fetch_row($ar))) { $allRoles[] = array('id' => (int) $x[0], 'name' => $x[1]); }
$allGroups = array();
$gq = @mysqli_query($conn, 'SELECT id, name, owner_role_id FROM link_groups WHERE is_active = 1 ORDER BY owner_role_id, display_order');
while ($gq && ($x = mysqli_fetch_row($gq))) { $allGroups[] = array('id' => (int) $x[0], 'name' => $x[1], 'owner' => (int) $x[2]); }

$total = count($rows); $onN = 0; $noPerm = 0;
foreach ($rows as $x) {
    if ($x['active'] === 1) { $onN++; }
    if ($x['permission_code'] === null || $x['permission_code'] === '') { $noPerm++; }
}
$allItems = 0;
$cq = @mysqli_query($conn, 'SELECT COUNT(*) FROM nav_items WHERE active = 1');
if ($cq && ($x = mysqli_fetch_row($cq))) { $allItems = (int) $x[0]; }

$page_title = 'ايكوبيشن | بنود الملاحة';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function pn_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'بنود الملاحة';
    $header_icon = 'fa fa-compass';
    $header_actions = $can_add ? array(
        array('tag' => 'button', 'id' => 'pnAddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'اضافة بند', 'title' => 'اضافة بند ملاحة', 'attrs' => 'type="button"'),
    ) : array();
    $header_back = false;
    include '../includes/page_header.php';
    if ($msg !== '') { echo '<div class="alert alert-success">' . pn_e($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . pn_e($err) . '</div>'; }
    ?>

    <div class="alert alert-info">
        هذا الجدول مصدر بنود التنقل. <strong>والمصير الحاكم ياخذ المواضع من سجل
        مواضع المساحة ويستعمل هذا الجدول مرشحا بالصلاحية</strong> فبند هنا بلا
        موضع هناك لا يظهر في السايدبار.
    </div>

    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'البنود المعروضة', 'value' => number_format($total), 'unit' => 'بند',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_nav_items.php',
            'comparison' => 'بعد التصفية', 'icon' => 'fa-compass', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'النشطة منها', 'value' => number_format($onN), 'unit' => 'بند',
            'period' => $NOW, 'status' => 'ok', 'drill' => 'perm_nav_items.php?active=1',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-circle-check', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'بلا كود صلاحية', 'value' => number_format($noPerm), 'unit' => 'بند',
            'period' => $NOW, 'status' => $noPerm > 0 ? 'warn' : 'ok', 'drill' => 'perm_nav_items.php',
            'comparison' => 'يظهر بلا سؤال عن الشاشة', 'icon' => 'fa-unlock', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'كل البنود النشطة', 'value' => number_format($allItems), 'unit' => 'بند',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_dashboard.php',
            'comparison' => 'على كل الادوار', 'icon' => 'fa-list', 'class' => 'ems-col-3'));
        ?>
    </div>

    <?php if ($can_add): ?>
    <form method="post" action="" class="allforms" id="pnAddForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="add">
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-plus"></i> اضافة بند ملاحة</h5></div>
            <div class="card-body">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label for="pn_role">الدور</label>
                        <select id="pn_role" name="role_id" required>
                            <option value="">- اختر -</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pn_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pn_group">المجموعة</label>
                        <select id="pn_group" name="group_id" required>
                            <option value="">- اختر -</option>
                            <?php foreach ($allGroups as $g): ?>
                            <option value="<?php echo (int) $g['id']; ?>"><?php echo pn_e($g['name']); ?> (<?php echo (int) $g['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pn_door">الباب</label>
                        <select id="pn_door" name="door" required>
                            <?php foreach ($DOORS as $d => $n): ?>
                            <option value="<?php echo pn_e($d); ?>"><?php echo pn_e($d); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pn_label">التسمية الظاهرة</label>
                        <input type="text" id="pn_label" name="label_ar" maxlength="64" required></div>
                    <div class="form-group"><label for="pn_route">المسار</label>
                        <input type="text" id="pn_route" name="route" maxlength="128" required></div>
                    <div class="form-group"><label for="pn_perm">كود الصلاحية</label>
                        <input type="text" id="pn_perm" name="permission_code" maxlength="128"
                               placeholder="يساوي المسار ان ترك فارغا"></div>
                    <div class="form-group"><label for="pn_icon">الايقونة</label>
                        <input type="text" id="pn_icon" name="icon" maxlength="50" value="fa fa-circle-dot"></div>
                    <div class="form-group"><label for="pn_order">الترتيب</label>
                        <input type="number" id="pn_order" name="sort_order" value="0"></div>
                    <div class="form-group"><label for="pn_module">الوحدة المرتبطة</label>
                        <input type="number" id="pn_module" name="module_id" value="0"
                               placeholder="صفر يعني بلا ربط"></div>
                </div></div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                    <button type="button" class="btn-secondary" id="pnCancelBtn"><i class="fa fa-times"></i> الغاء</button>
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
                    <div class="form-group"><label for="f_role">الدور</label>
                        <select id="f_role" name="role_id">
                            <option value="">- اختر دورا -</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $frole === (string) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo pn_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="f_group">المجموعة</label>
                        <select id="f_group" name="group_id">
                            <option value="">الكل</option>
                            <?php foreach ($allGroups as $g): ?>
                            <option value="<?php echo (int) $g['id']; ?>" <?php echo $fgroup === (string) $g['id'] ? 'selected' : ''; ?>>
                                <?php echo pn_e($g['name']); ?> (<?php echo (int) $g['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="f_door">الباب</label>
                        <select id="f_door" name="door">
                            <option value="">الكل</option>
                            <?php foreach ($DOORS as $d => $n): ?>
                            <option value="<?php echo pn_e($d); ?>" <?php echo $fdoor === $d ? 'selected' : ''; ?>>
                                <?php echo pn_e($d); ?> (<?php echo (int) $n; ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="f_active">الحالة</label>
                        <select id="f_active" name="active">
                            <option value="">الكل</option>
                            <option value="1" <?php echo $factive === '1' ? 'selected' : ''; ?>>نشط</option>
                            <option value="0" <?php echo $factive === '0' ? 'selected' : ''; ?>>معطل</option>
                        </select></div>
                </div>
                <button type="submit" class="btn-primary"><i class="fa fa-search"></i> اعرض</button>
                <a class="btn-secondary" href="perm_nav_items.php"><i class="fa fa-rotate-left"></i> اعادة</a>
            </form>
        </div>
    </div>

    <?php if ($wsql === ''): ?>
        <?php echo function_exists('ems_state_empty')
            ? ems_state_empty('اختر دورا او مجموعة او بابا لعرض البنود', '', '')
            : '<div class="card"><div class="card-body">اختر مرشحا لعرض البنود</div></div>'; ?>
    <?php else: ?>
    <div class="card"><div class="card-body">
        <div class="table-responsive">
            <table class="alltables display" id="permNavTable" data-page-length="50">
                <thead><tr>
                    <th>#</th><th>الدور</th><th>المجموعة</th><th>الباب</th><th>التسمية</th>
                    <th>المسار</th><th>كود الصلاحية</th><th>الترتيب</th><th>الحالة</th><th>الاجراءات</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $x): ?>
                    <tr>
                        <td><?php echo (int) $x['id']; ?></td>
                        <td><?php echo $x['role_name'] !== null ? pn_e($x['role_name']) : (int) $x['role_id']; ?></td>
                        <td><?php echo $x['group_name'] !== null ? pn_e($x['group_name']) : (int) $x['group_id']; ?></td>
                        <td><?php echo pn_e($x['door']); ?></td>
                        <td><i class="<?php echo pn_e($x['icon']); ?>"></i> <?php echo pn_e($x['label_ar']); ?></td>
                        <td><code><?php echo pn_e($x['route']); ?></code></td>
                        <td><?php echo ($x['permission_code'] === null || $x['permission_code'] === '')
                                ? 'بلا كود' : ('<code>' . pn_e($x['permission_code']) . '</code>'); ?></td>
                        <td><?php echo (int) $x['sort_order']; ?></td>
                        <td><?php echo $x['active'] === 1 ? 'نشط' : 'معطل'; ?></td>
                        <td>
                            <?php if ($can_edit): ?>
                            <form method="post" action="" class="ems-inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="act" value="toggle">
                                <input type="hidden" name="id" value="<?php echo (int) $x['id']; ?>">
                                <button type="submit" class="btn-secondary"><?php echo $x['active'] === 1 ? 'تعطيل' : 'تفعيل'; ?></button>
                            </form>
                            <button type="button" class="btn-secondary pnEdit"
                                data-id="<?php echo (int) $x['id']; ?>"
                                data-role="<?php echo (int) $x['role_id']; ?>"
                                data-group="<?php echo (int) $x['group_id']; ?>"
                                data-door="<?php echo pn_e($x['door']); ?>"
                                data-label="<?php echo pn_e($x['label_ar']); ?>"
                                data-route="<?php echo pn_e($x['route']); ?>"
                                data-perm="<?php echo pn_e($x['permission_code']); ?>"
                                data-icon="<?php echo pn_e($x['icon']); ?>"
                                data-order="<?php echo (int) $x['sort_order']; ?>"
                                data-module="<?php echo (int) $x['module_id']; ?>">تعديل</button>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                            <form method="post" action="" class="ems-inline-form"
                                  onsubmit="return confirm('حذف البند؟');">
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
    <?php endif; ?>

    <?php if ($can_edit): ?>
    <form method="post" action="" class="allforms" id="pnEditForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="edit">
        <input type="hidden" name="id" id="pne_id">
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-pen"></i> تعديل بند</h5></div>
            <div class="card-body">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label for="pne_role">الدور</label>
                        <select id="pne_role" name="role_id" required>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pn_e($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pne_group">المجموعة</label>
                        <select id="pne_group" name="group_id" required>
                            <?php foreach ($allGroups as $g): ?>
                            <option value="<?php echo (int) $g['id']; ?>"><?php echo pn_e($g['name']); ?> (<?php echo (int) $g['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pne_door">الباب</label>
                        <select id="pne_door" name="door" required>
                            <?php foreach ($DOORS as $d => $n): ?>
                            <option value="<?php echo pn_e($d); ?>"><?php echo pn_e($d); ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="pne_label">التسمية</label>
                        <input type="text" id="pne_label" name="label_ar" maxlength="64" required></div>
                    <div class="form-group"><label for="pne_route">المسار</label>
                        <input type="text" id="pne_route" name="route" maxlength="128" required></div>
                    <div class="form-group"><label for="pne_perm">كود الصلاحية</label>
                        <input type="text" id="pne_perm" name="permission_code" maxlength="128"></div>
                    <div class="form-group"><label for="pne_icon">الايقونة</label>
                        <input type="text" id="pne_icon" name="icon" maxlength="50"></div>
                    <div class="form-group"><label for="pne_order">الترتيب</label>
                        <input type="number" id="pne_order" name="sort_order"></div>
                    <div class="form-group"><label for="pne_module">الوحدة المرتبطة</label>
                        <input type="number" id="pne_module" name="module_id"></div>
                </div></div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ التعديل</button>
                    <button type="button" class="btn-secondary" id="pnEditCancel"><i class="fa fa-times"></i> الغاء</button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
(function () {
    var b = document.getElementById('pnAddBtn'), f = document.getElementById('pnAddForm'),
        c = document.getElementById('pnCancelBtn');
    if (b && f) { b.addEventListener('click', function (e) { e.preventDefault();
        f.classList.toggle('allforms-visible');
        if (f.classList.contains('allforms-visible')) { f.scrollIntoView({behavior:'smooth',block:'nearest'}); } }); }
    if (c && f) { c.addEventListener('click', function () { f.classList.remove('allforms-visible'); }); }
    var ef = document.getElementById('pnEditForm'), ec = document.getElementById('pnEditCancel');
    if (ef) {
        document.querySelectorAll('.pnEdit').forEach(function (x) {
            x.addEventListener('click', function () {
                document.getElementById('pne_id').value = x.dataset.id;
                document.getElementById('pne_role').value = x.dataset.role;
                document.getElementById('pne_group').value = x.dataset.group;
                document.getElementById('pne_door').value = x.dataset.door;
                document.getElementById('pne_label').value = x.dataset.label;
                document.getElementById('pne_route').value = x.dataset.route;
                document.getElementById('pne_perm').value = x.dataset.perm;
                document.getElementById('pne_icon').value = x.dataset.icon;
                document.getElementById('pne_order').value = x.dataset.order;
                document.getElementById('pne_module').value = x.dataset.module;
                ef.classList.add('allforms-visible');
                ef.scrollIntoView({behavior:'smooth',block:'nearest'});
            });
        });
    }
    if (ec && ef) { ec.addEventListener('click', function () { ef.classList.remove('allforms-visible'); }); }
})();
</script>
