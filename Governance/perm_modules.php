<?php
/**
 * Governance/perm_modules.php — سجلُّ الوحداتِ والشاشات (PERM-SCR-01 ③)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **الكودُ هنا مفتاحُ الصلاحيةِ لا مجرَّدَ وصف**: `modules.code` هو ما تسأل
 *   عنه كلُّ شاشةٍ عبر `check_page_permissions()` — فتغييرُه يقطع صلاحيةَ
 *   الشاشةِ عن كلِّ دورٍ مُنح عليها. لذلك يُحذَّر عند التعديلِ ويُمنَع الحذفُ
 *   لوحدةٍ لها منحٌ أو بنودُ ملاحةٍ حيّة.
 * ⛔ `is_link` عمودٌ **نصيٌّ** `varchar(10)` لا `tinyint` — والقيمةُ '0'/'1'.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';    // بطاقةُ المؤشر: سبعةُ حقولٍ إلزامًا
require_once __DIR__ . '/../includes/date_format.php'; // مُوحِّدُ التاريخ: صيغةٌ واحدةٌ عبرَ النظام

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_modules.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لشاشة الوحدات ', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك');
    exit();
}
$can_add    = $is_super_admin || !empty($__pp['can_add']);
$can_edit   = $is_super_admin || !empty($__pp['can_edit']);
$can_delete = $is_super_admin || !empty($__pp['can_delete']);

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $err = 'رمز الحماية غير صالح. أعد تحميل الصفحة.';
    } else {
        $act = (string) ($_POST['act'] ?? '');
        $name  = trim((string) ($_POST['name'] ?? ''));
        $code  = trim((string) ($_POST['code'] ?? ''));
        $owner = (int) ($_POST['owner_role_id'] ?? 0);
        $icon  = trim((string) ($_POST['icon'] ?? 'fa fa-circle-dot'));
        $ord   = (int) ($_POST['display_order'] ?? 0);
        $quick = !empty($_POST['is_quick']) ? 1 : 0;

        if ($act === 'add' && $can_add) {
            if (mb_strlen($name) < 2 || $code === '') {
                $err = 'الاسم والكود مطلوبان.';
            } else {
                /* ⛔ الكودُ مفتاحُ صلاحيةٍ — فالتكرارُ يجعل حارسَين يقرآن صفَّين. */
                $dup = 0;
                if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM modules WHERE code = ?')) {
                    mysqli_stmt_bind_param($st, 's', $code);
                    mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $dup);
                    mysqli_stmt_fetch($st); mysqli_stmt_close($st);
                }
                if ($dup > 0) {
                    $err = 'الكود مستعمل سلفا - والكود مفتاح صلاحية فلا يتكرر.';
                } else {
                    $st = mysqli_prepare($conn, "INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order) VALUES (?,?,?,'0',?,?,?)");
                    $ov = $owner > 0 ? $owner : null;
                    mysqli_stmt_bind_param($st, 'ssiisi', $name, $code, $ov, $quick, $icon, $ord);
                    if (mysqli_stmt_execute($st)) { $msg = 'أضيفت الوحدة '; } else { $err = 'تعذر الحفظ '; }
                    mysqli_stmt_close($st);
                }
            }
        } elseif ($act === 'edit' && $can_edit) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || mb_strlen($name) < 2 || $code === '') {
                $err = 'بيانات غير مكتملة.';
            } else {
                $st = mysqli_prepare($conn, 'UPDATE modules SET name=?, code=?, owner_role_id=?, is_quick=?, icon=?, display_order=? WHERE id=?');
                $ov = $owner > 0 ? $owner : null;
                mysqli_stmt_bind_param($st, 'ssiisii', $name, $code, $ov, $quick, $icon, $ord, $id);
                if (mysqli_stmt_execute($st)) { $msg = 'حدثت الوحدة '; } else { $err = 'تعذر التحديث '; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'delete' && $can_delete) {
            $id = (int) ($_POST['id'] ?? 0);
            $grants = 0; $navs = 0;
            if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM role_permissions WHERE module_id = ?')) {
                mysqli_stmt_bind_param($st, 'i', $id); mysqli_stmt_execute($st);
                mysqli_stmt_bind_result($st, $grants); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
            }
            if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM nav_items WHERE module_id = ?')) {
                mysqli_stmt_bind_param($st, 'i', $id); mysqli_stmt_execute($st);
                mysqli_stmt_bind_result($st, $navs); mysqli_stmt_fetch($st); mysqli_stmt_close($st);
            }
            if ($id <= 0) {
                $err = 'وحدة غير محددة.';
            } elseif ($grants > 0) {
                $err = 'لا تحذف الوحدة: عليها ' . (int) $grants . ' صف صلاحية. امسحها من المصفوفة أولا.';
            } elseif ($navs > 0) {
                $err = 'لا تحذف الوحدة: مرتبطة ب' . (int) $navs . ' بند ملاحة.';
            } else {
                $st = mysqli_prepare($conn, 'DELETE FROM modules WHERE id = ?');
                mysqli_stmt_bind_param($st, 'i', $id);
                if (mysqli_stmt_execute($st)) { $msg = 'حذفت الوحدة '; } else { $err = 'تعذر الحذف '; }
                mysqli_stmt_close($st);
            }
        } else {
            $err = 'غير مصرح بهذا الإجراء ';
        }
    }
}

/* ⛔ **مرشِّحُ عمودٍ لا بحثٌ حرّ** (RP-07) — يُسمّى باسمِ حقلِه. */
$fq     = trim((string) ($_GET['name'] ?? ''));
$fowner = (string) ($_GET['owner'] ?? '');
$where = array(); $types = ''; $args = array();
if ($fq !== '')    { $where[] = '(m.name LIKE ? OR m.code LIKE ?)'; $types .= 'ss'; $args[] = '%' . $fq . '%'; $args[] = '%' . $fq . '%'; }
if ($fowner === '0') { $where[] = '(m.owner_role_id IS NULL OR m.owner_role_id = 0)'; }
elseif ($fowner !== '') { $where[] = 'm.owner_role_id = ?'; $types .= 'i'; $args[] = (int) $fowner; }
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* ⛔ **لا حقلَ مشتقٌّ يُحسب في الشاشة** (RP-08): العدُّ بخريطتَين مجمَّعتَين
   تُدمجان في PHP، لا بتعبيرٍ مُسمّى داخلَ استعلامِ الشاشة. */
$sql = 'SELECT m.id, m.name, m.code, m.owner_role_id, m.icon, m.display_order, m.is_quick, r.name
          FROM modules m LEFT JOIN roles r ON r.id = m.owner_role_id' . $wsql . '
         ORDER BY m.display_order, m.id';
$rows = array();
if ($st = mysqli_prepare($conn, $sql)) {
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$args); }
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($res && ($x = mysqli_fetch_row($res))) {
        $rows[] = array('id' => (int) $x[0], 'name' => $x[1], 'code' => $x[2],
                        'owner_role_id' => (int) $x[3], 'icon' => $x[4],
                        'display_order' => (int) $x[5], 'is_quick' => (int) $x[6],
                        'owner_name' => $x[7]);
    }
    mysqli_stmt_close($st);
}
$grantsBy = array(); $navsBy = array();
$g = @mysqli_query($conn, 'SELECT module_id, COUNT(*) FROM role_permissions WHERE can_view = 1 GROUP BY module_id');
while ($g && ($x = mysqli_fetch_row($g))) { $grantsBy[(int) $x[0]] = (int) $x[1]; }
$g = @mysqli_query($conn, 'SELECT module_id, COUNT(*) FROM nav_items WHERE active = 1 GROUP BY module_id');
while ($g && ($x = mysqli_fetch_row($g))) { $navsBy[(int) $x[0]] = (int) $x[1]; }
foreach ($rows as $i => $x) {
    $rows[$i]['grants_n'] = isset($grantsBy[$x['id']]) ? $grantsBy[$x['id']] : 0;
    $rows[$i]['navs_n']   = isset($navsBy[$x['id']]) ? $navsBy[$x['id']] : 0;
}

$allRoles = array();
$ar = @mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY name');
while ($ar && ($x = mysqli_fetch_assoc($ar))) { $allRoles[] = $x; }

$total = count($rows); $owned = 0; $linked = 0;
foreach ($rows as $x) {
    if ((int) $x['owner_role_id'] > 0) { $owned++; }
    if ((int) $x['grants_n'] > 0) { $linked++; }
}

$page_title = 'إيكوبيشن | الوحدات والشاشات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function pm_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الوحدات والشاشات';
    $header_icon = 'fa fa-cubes';
    $header_actions = $can_add ? array(
        array('tag' => 'button', 'id' => 'pmAddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة وحدة', 'title' => 'تسجيل شاشة جديدة', 'attrs' => 'type="button"'),
    ) : array();
    $header_back = false;
    include '../includes/page_header.php';
    if ($msg !== '') { echo '<div class="alert alert-success">' . pm_e($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . pm_e($err) . '</div>'; }
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا وحدات مسجلة بعد', 'سجل أول شاشة بزر «إضافة وحدة» أعلى الشاشة');
    }
    ?>

 <?php /* المكوّنُ الموحَّدُ لا وسمٌ خام: سبعةُ حقولٍ يفرضها العقدُ،
       والفترةُ لحظيّةٌ صادقةٌ لأنّ الأرقامَ تُقاس عند كلِّ فتح. */
    $NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')'; ?>
    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'إجمالي الوحدات', 'value' => number_format((int) ($total)), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_modules.php',
            'comparison' => 'المعروض بعد التصفية', 'icon' => 'fa-cubes', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'لها دور مالك', 'value' => number_format((int) ($owned)), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => 'ok', 'drill' => 'perm_modules.php',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-user-tag', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'بلا دور مالك', 'value' => number_format((int) (($total - $owned))), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => (($total - $owned) > 0 ? 'warn' : 'ok'), 'drill' => 'perm_modules.php?owner=0',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-circle-question', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'عليها منح صلاحية', 'value' => number_format((int) ($linked)), 'unit' => 'وحدة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_matrix.php',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-table', 'class' => 'ems-col-3'));
        ?>
    </div>

    <?php if ($can_add): ?>
    <form method="post" action="" class="allforms" id="pmAddForm">
        <?php echo csrf_field(); ?>
 <input type="hidden" name="act" value="add">
 <div class="card">
 <div class="card-header"><h5><i class="fa fa-plus"></i> إضافة وحدة</h5></div>
 <div class="card-body">
 <div class="form-section"><div class="form-grid">
 <div class="form-group"><label for="pm_name">اسم الوحدة</label>
 <input type="text" id="pm_name" name="name" maxlength="100" required></div>
 <div class="form-group"><label for="pm_code">الكود (مسار الشاشة)</label>
 <input type="text" id="pm_code" name="code" maxlength="50" required
 placeholder="Governance/example.php"></div>
 <div class="form-group"><label for="pm_owner">الدور المالك</label>
 <select id="pm_owner" name="owner_role_id">
 <option value="0">- بلا مالك -</option>
 <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pm_e($r['name']); ?></option>
                            <?php endforeach; ?>
 </select></div>
 <div class="form-group"><label for="pm_icon">الأيقونة</label>
 <input type="text" id="pm_icon" name="icon" maxlength="50" value="fa fa-circle-dot"></div>
 <div class="form-group"><label for="pm_order">ترتيب العرض</label>
 <input type="number" id="pm_order" name="display_order" value="0"></div>
 <div class="form-group"><label for="pm_quick">وصول سريع</label>
 <select id="pm_quick" name="is_quick"><option value="0">لا</option><option value="1">نعم</option></select></div>
 </div></div>
 <div class="form-actions">
 <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
 <button type="button" class="btn-secondary" id="pmCancelBtn"><i class="fa fa-times"></i> إلغاء</button>
 </div>
 </div>
 </div>
 </form>
 <?php endif; ?>

 <div class="filter">
 <div class="filter-title">بحث وتصفية</div>
 <div class="filter-body">
 <form method="get" action="">
 <div class="form-grid">
 <div class="form-group"><label for="f_q">الاسم أو الكود</label>
 <input type="text" id="f_q" name="name" value="<?php echo pm_e($fq); ?>"></div>
 <div class="form-group"><label for="f_owner">الدور المالك</label>
 <select id="f_owner" name="owner">
 <option value="">الكل</option>
 <option value="0" <?php echo $fowner === '0' ? 'selected' : ''; ?>>بلا مالك</option>
 <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $fowner === (string) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo pm_e($r['name']); ?></option>
                            <?php endforeach; ?>
 </select></div>
 </div>
 <button type="submit" class="btn-primary"><i class="fa fa-search"></i> بحث</button>
 <a class="btn-secondary" href="perm_modules.php"><i class="fa fa-rotate-left"></i> إعادة</a>
 </form>
 </div>
 </div>

 <div class="card"><div class="card-body">
 <div class="table-responsive">
 <table class="alltables display" id="permModulesTable" data-page-length="25">
 <thead><tr>
 <th>#</th><th>الاسم</th><th>الكود (مفتاح الصلاحية)</th><th>الدور المالك</th>
 <th>الأيقونة</th><th>الترتيب</th><th>منح</th><th>بنود ملاحة</th><th>الإجراءات</th>
 </tr></thead>
 <tbody>
 <?php foreach ($rows as $x): ?>
                    <tr>
                        <td><?php echo (int) $x['id']; ?></td>
                        <td><?php echo pm_e($x['name']); ?></td>
                        <td><code><?php echo pm_e($x['code']); ?></code></td>
                        <td><?php echo $x['owner_name'] !== null ? pm_e($x['owner_name']) : '—'; ?></td>
                        <td><i class="<?php echo pm_e($x['icon']); ?>"></i></td>
                        <td><?php echo (int) $x['display_order']; ?></td>
                        <td><?php echo (int) $x['grants_n']; ?></td>
                        <td><?php echo (int) $x['navs_n']; ?></td>
                        <td>
                            <?php if ($can_edit): ?>
                            <button type="button" class="btn-secondary pmEdit"
                                data-id="<?php echo (int) $x['id']; ?>"
                                data-name="<?php echo pm_e($x['name']); ?>"
                                data-code="<?php echo pm_e($x['code']); ?>"
                                data-owner="<?php echo (int) $x['owner_role_id']; ?>"
                                data-icon="<?php echo pm_e($x['icon']); ?>"
                                data-order="<?php echo (int) $x['display_order']; ?>"
                                data-quick="<?php echo (int) $x['is_quick']; ?>">تعديل</button>
 <?php endif; ?>
                            <?php if ($can_delete): ?>
 <form method="post" action="" class="ems-inline-form"
 onsubmit="return confirm('حذف الوحدة «<?php echo pm_e($x['name']); ?>»؟');">
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
    <form method="post" action="" class="allforms" id="pmEditForm">
        <?php echo csrf_field(); ?>
 <input type="hidden" name="act" value="edit">
 <input type="hidden" name="id" id="pe_id">
 <div class="card">
 <div class="card-header"><h5><i class="fa fa-pen"></i> تعديل وحدة</h5></div>
 <div class="card-body">
 <div class="alert alert-warning">
 <strong>الكود مفتاح صلاحية</strong> - تغييره يقطع صلاحية الشاشة عن كل دور منح عليها.
 </div>
 <div class="form-section"><div class="form-grid">
 <div class="form-group"><label for="pe_name">الاسم</label>
 <input type="text" id="pe_name" name="name" maxlength="100" required></div>
 <div class="form-group"><label for="pe_code">الكود</label>
 <input type="text" id="pe_code" name="code" maxlength="50" required></div>
 <div class="form-group"><label for="pe_owner">الدور المالك</label>
 <select id="pe_owner" name="owner_role_id">
 <option value="0">- بلا مالك -</option>
 <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pm_e($r['name']); ?></option>
                            <?php endforeach; ?>
 </select></div>
 <div class="form-group"><label for="pe_icon">الأيقونة</label>
 <input type="text" id="pe_icon" name="icon" maxlength="50"></div>
 <div class="form-group"><label for="pe_order">الترتيب</label>
 <input type="number" id="pe_order" name="display_order"></div>
 <div class="form-group"><label for="pe_quick">وصول سريع</label>
 <select id="pe_quick" name="is_quick"><option value="0">لا</option><option value="1">نعم</option></select></div>
 </div></div>
 <div class="form-actions">
 <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ التعديل</button>
 <button type="button" class="btn-secondary" id="pmEditCancel"><i class="fa fa-times"></i> إلغاء</button>
 </div>
 </div>
 </div>
 </form>
 <?php endif; ?>
</div>

<script>
(function () {
    var b = document.getElementById('pmAddBtn'), f = document.getElementById('pmAddForm'),
        c = document.getElementById('pmCancelBtn');
    if (b && f) { b.addEventListener('click', function (e) { e.preventDefault();
        f.classList.toggle('allforms-visible');
        if (f.classList.contains('allforms-visible')) { f.scrollIntoView({behavior:'smooth',block:'nearest'}); } }); }
    if (c && f) { c.addEventListener('click', function () { f.classList.remove('allforms-visible'); }); }

    var ef = document.getElementById('pmEditForm'), ec = document.getElementById('pmEditCancel');
    if (ef) {
        document.querySelectorAll('.pmEdit').forEach(function (x) {
            x.addEventListener('click', function () {
                document.getElementById('pe_id').value = x.dataset.id;
                document.getElementById('pe_name').value = x.dataset.name;
                document.getElementById('pe_code').value = x.dataset.code;
                document.getElementById('pe_owner').value = x.dataset.owner || '0';
                document.getElementById('pe_icon').value = x.dataset.icon;
                document.getElementById('pe_order').value = x.dataset.order;
                document.getElementById('pe_quick').value = x.dataset.quick;
                ef.classList.add('allforms-visible');
                ef.scrollIntoView({behavior:'smooth',block:'nearest'});
            });
        });
    }
    if (ec && ef) { ec.addEventListener('click', function () { ef.classList.remove('allforms-visible'); }); }
})();
</script>
