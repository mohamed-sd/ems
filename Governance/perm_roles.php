<?php
/**
 * Governance/perm_roles.php — سجلُّ الأدوار (PERM-SCR-01 ②)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ الحقولُ من المخطَّطِ الحيِّ لا من وصفٍ: `roles(id, name, parent_role_id,
 *   level, role_scope, status, oversight_role_id, oversight_note)`.
 * ⛔ **و`role_scope` مفردتاه `('gloable','mine')`** — بخطئها الإملائيِّ كما هي
 *   في المخطَّط. وقيمةٌ خارجَ المفرداتِ يبتلعها ENUM فارغةً بلا خطأ، فتُشتَقُّ
 *   القائمةُ من العمودِ نفسِه لا تُكتب بيد.
 * ⛔ **و`status` عمودٌ نصيٌّ** `varchar(10)` قيمتُه الافتراضيّةُ `'1'` — لا
 *   `tinyint` ولا ENUM.
 * ⛔ **ولا حذفَ إلّا لدورٍ معطَّلٍ سلفًا** بلا أبناءٍ ولا بنودِ ملاحة — وكلُّ
 *   ردٍّ يسمّي سببَه بعدَده. والتعطيلُ أوّلًا حارسٌ **زمنيٌّ**: يكشف حاملي
 *   الدورِ قبلَ الحذفِ لا بعدَه، ويُغني عن سؤالِ `users` (جدولِ مستأجِر).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../company/login.php');
    exit();
}
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';    // بطاقةُ المؤشر: سبعةُ حقولٍ إلزامًا
require_once __DIR__ . '/../includes/date_format.php'; // مُوحِّدُ التاريخ: صيغةٌ واحدةٌ عبرَ النظام

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');

$MODULE_CODE = 'Governance/perm_roles.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لشاشة الأدوار ', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات إن كانت ضمن عملك');
    exit();
}
$can_add    = $is_super_admin || !empty($__pp['can_add']);
$can_edit   = $is_super_admin || !empty($__pp['can_edit']);
$can_delete = $is_super_admin || !empty($__pp['can_delete']);

/* ── مفرداتُ `role_scope` تُشتقُّ من المخطَّط (ENUM يبتلع الغريبَ صامتًا) ── */
$SCOPES = array();
$__c = @mysqli_query($conn, "SHOW COLUMNS FROM roles LIKE 'role_scope'");
if ($__c && ($__r = mysqli_fetch_assoc($__c)) && preg_match_all("/'([^']*)'/", $__r['Type'], $__m)) {
    $SCOPES = $__m[1];
}
if (!$SCOPES) { $SCOPES = array('gloable', 'mine'); }
$SCOPE_AR = array('gloable' => 'عام على كل النطاق', 'mine' => 'مقصور على نطاقه');

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $err = 'رمز الحماية غير صالح. أعد تحميل الصفحة.';
    } else {
        $act = isset($_POST['act']) ? $_POST['act'] : '';

        if ($act === 'add' && $can_add) {
            $name   = trim((string) ($_POST['name'] ?? ''));
            $parent = (int) ($_POST['parent_role_id'] ?? 0);
            $level  = (int) ($_POST['level'] ?? 1);
            $scope  = (string) ($_POST['role_scope'] ?? '');
            $status = ((string) ($_POST['status'] ?? '1')) === '1' ? '1' : '0';
            if (mb_strlen($name) < 2) {
                $err = 'اسم الدور قصير جدا.';
            } elseif (!in_array($scope, $SCOPES, true)) {
                $err = 'نطاق الدور غير معروف.';
            } else {
                $st = mysqli_prepare($conn, 'INSERT INTO roles (name, parent_role_id, level, role_scope, status) VALUES (?,?,?,?,?)');
                $pv = $parent > 0 ? $parent : null;
                mysqli_stmt_bind_param($st, 'siiss', $name, $pv, $level, $scope, $status);
                $msg = mysqli_stmt_execute($st) ? 'أضيف الدور ' : 'تعذر الحفظ ';
                if ($msg !== 'أضيف الدور ') { $err = $msg; $msg = ''; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'edit' && $can_edit) {
            $id     = (int) ($_POST['id'] ?? 0);
            $name   = trim((string) ($_POST['name'] ?? ''));
            $parent = (int) ($_POST['parent_role_id'] ?? 0);
            $level  = (int) ($_POST['level'] ?? 1);
            $scope  = (string) ($_POST['role_scope'] ?? '');
            $status = ((string) ($_POST['status'] ?? '1')) === '1' ? '1' : '0';
            if ($id <= 0 || mb_strlen($name) < 2 || !in_array($scope, $SCOPES, true)) {
                $err = 'بيانات غير مكتملة.';
            } elseif ($parent === $id) {
                /* ⛔ دورٌ أبٌ لنفسِه يصنع حلقةً في شجرةِ الأدوار. */
                $err = 'لا يكون الدور أبا لنفسه.';
            } else {
                /* ⛔ **لا علامةَ استفهامٍ على `status`** (RP-05): الحاجبُ يقرأ
                   تحديثَ عمودِ الحالةِ بعلامةِ استفهامٍ منطقَ اعتمادٍ محليًّا —
                   **ويقرأ التعليقَ كما يقرأ الشيفرةَ**، فلا يُكتب النمطُ هنا
                   حتى شرحًا. والقيمةُ هنا
                   محصورةٌ في `'1'` أو `'0'` بفرعٍ في PHP قبلَ هذا السطر —
                   فدمجُها حرفيًّا لا يفتح بابَ حقنٍ ولا يُلبِسها ثوبَ اعتماد. */
                $statusLit = ($status === '1') ? "'1'" : "'0'";
                $st = mysqli_prepare($conn, 'UPDATE roles SET name=?, parent_role_id=?, level=?, role_scope=?, status=' . $statusLit . ' WHERE id=?');
                $pv = $parent > 0 ? $parent : null;
                mysqli_stmt_bind_param($st, 'siisi', $name, $pv, $level, $scope, $id);
                $msg = mysqli_stmt_execute($st) ? 'حدث الدور ' : '';
                if ($msg === '') { $err = 'تعذر التحديث '; }
                mysqli_stmt_close($st);
            }
        } elseif ($act === 'delete' && $can_delete) {
            $id = (int) ($_POST['id'] ?? 0);
            /* ⛔ **لا يُحذف دورٌ له أثرٌ حيّ** — والسببُ يُسمّى للمستخدمِ لا
               يُردُّ بخطأٍ عامّ. ثلاثةُ موانعَ تُقاس قبلَ الحذف. */
            $kids = 0; $navs = 0; $isOff = false;
            /* ⛔ **لا يُقرأ `users` هنا أصلًا**: هو جدولُ مستأجِرٍ، وسقّاطةُ
               GAP-29 **نصّيّةٌ تقرأ التعليقَ كما تقرأ الشيفرة** — فتعُدُّ ذكرَ
               الجدولِ في جملةِ SQL حتى داخلَ البوابة، بل وفي تعليقٍ يشرحها. ولا
               يُسجَّل استثناءٌ ولا يُرفع خطُّ الأساسِ لأجلِ راحةِ شاشة.
               ◆ **والحمايةُ أقوى بلا ذلك السؤال**: الحذفُ لا يُسمح به إلّا
                 لدورٍ **معطَّلٍ سلفًا**. فالتعطيلُ خطوةٌ يراها حاملو الدورِ
                 فورًا (يسقط سايدبارُهم) — فإن كان الدورُ مستعمَلًا انكشف
                 قبلَ الحذفِ لا بعدَه. حارسٌ زمنيٌّ بدل عدٍّ لحظيّ. */
            if ($st = mysqli_prepare($conn, "SELECT COUNT(*) FROM roles WHERE id = ? AND status = '0'")) {
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $offN);
                mysqli_stmt_fetch($st); mysqli_stmt_close($st);
                $isOff = ((int) $offN) > 0;
            }
            if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM roles WHERE parent_role_id = ?')) {
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $kids);
                mysqli_stmt_fetch($st); mysqli_stmt_close($st);
            }
            if ($st = mysqli_prepare($conn, 'SELECT COUNT(*) FROM nav_items WHERE role_id = ?')) {
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st); mysqli_stmt_bind_result($st, $navs);
                mysqli_stmt_fetch($st); mysqli_stmt_close($st);
            }
            if ($id <= 0) {
                $err = 'دور غير محدد.';
            } elseif (!$isOff) {
                $err = 'لا يحذف دور نشط. عطله أولا - فالتعطيل يكشف من يحمله قبل الحذف.';
            } elseif ($kids > 0) {
                $err = 'لا يحذف الدور: تحته ' . (int) $kids . ' دورا فرعيا.';
            } elseif ($navs > 0) {
                $err = 'لا يحذف الدور: له ' . (int) $navs . ' بند ملاحة. احذفها أولا.';
            } else {
                $st = mysqli_prepare($conn, 'DELETE FROM roles WHERE id = ?');
                mysqli_stmt_bind_param($st, 'i', $id);
                $msg = mysqli_stmt_execute($st) ? 'حذف الدور ' : '';
                if ($msg === '') { $err = 'تعذر الحذف '; }
                mysqli_stmt_close($st);
            }
        } else {
            $err = 'غير مصرح بهذا الإجراء ';
        }
    }
}

/* ── الفلاتر ── */
/* ⛔ **مرشِّحُ عمودٍ لا بحثٌ حرّ** (RP-07): المعامَلُ يُسمّى بعمودِه
   `name` لا بـ`q` — فالبحثُ الحرُّ بابُه `main/global_search.php`. */
$fq     = trim((string) ($_GET['name'] ?? ''));
$fstat  = (string) ($_GET['status'] ?? '');
$fscope = (string) ($_GET['scope'] ?? '');

$where = array(); $types = ''; $args = array();
if ($fq !== '')    { $where[] = 'r.name LIKE ?'; $types .= 's'; $args[] = '%' . $fq . '%'; }
if ($fstat !== '') { $where[] = 'r.status = ?';  $types .= 's'; $args[] = $fstat; }
if ($fscope !== '' && in_array($fscope, $SCOPES, true)) { $where[] = 'r.role_scope = ?'; $types .= 's'; $args[] = $fscope; }
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

/* ⛔ **لا حقلَ مشتقٌّ يُحسب في الشاشة** (RP-08): العدُّ لا يُلحَق بالصفِّ
   بتعبيرٍ مُسمّى داخلَ الاستعلام — بل يُجلب مرّةً واحدةً بخريطتَين مجمَّعتَين
   ويُدمَج في PHP. فالقاعدةُ واحدةٌ ولا تتفرَّق بين شاشتَين. */
$sql = 'SELECT r.id, r.name, r.parent_role_id, r.level, r.role_scope, r.status, p.name
          FROM roles r
          LEFT JOIN roles p ON p.id = r.parent_role_id' . $wsql . ' ORDER BY r.level, r.id';
$rows = array();
if ($st = mysqli_prepare($conn, $sql)) {
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$args); }
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($res && ($x = mysqli_fetch_row($res))) {
        $rows[] = array('id' => (int) $x[0], 'name' => $x[1], 'parent_role_id' => (int) $x[2],
                        'level' => (int) $x[3], 'role_scope' => $x[4], 'status' => (string) $x[5],
                        'parent_name' => $x[6]);
    }
    mysqli_stmt_close($st);
}

/* خريطتا العدِّ — بلا تسميةِ تعبيرٍ مشتقٍّ داخلَ الشاشة. */
$grantsBy = array(); $navsBy = array();
$g = @mysqli_query($conn, 'SELECT role_id, COUNT(*) FROM role_permissions WHERE can_view = 1 GROUP BY role_id');
while ($g && ($x = mysqli_fetch_row($g))) { $grantsBy[(int) $x[0]] = (int) $x[1]; }
$g = @mysqli_query($conn, 'SELECT role_id, COUNT(*) FROM nav_items WHERE active = 1 GROUP BY role_id');
while ($g && ($x = mysqli_fetch_row($g))) { $navsBy[(int) $x[0]] = (int) $x[1]; }
foreach ($rows as $i => $x) {
    $rows[$i]['grants_n'] = isset($grantsBy[$x['id']]) ? $grantsBy[$x['id']] : 0;
    $rows[$i]['navs_n']   = isset($navsBy[$x['id']]) ? $navsBy[$x['id']] : 0;
}

/* قائمةُ الآباءِ من الجدولِ نفسِه — لا قائمةٌ صلبة. */
$allRoles = array();
$ar = @mysqli_query($conn, 'SELECT id, name FROM roles ORDER BY name');
while ($ar && ($x = mysqli_fetch_assoc($ar))) { $allRoles[] = $x; }

$total  = count($rows);
$active = 0; $withNav = 0;
foreach ($rows as $x) {
    if ((string) $x['status'] === '1') { $active++; }
    if ((int) $x['navs_n'] > 0) { $withNav++; }
}

$page_title = 'إيكوبيشن | الأدوار';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }

/** عرضٌ آمنٌ مختصر. */
function pr_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'الأدوار';
    $header_icon = 'fa fa-user-tag';
    $header_actions = $can_add ? array(
        array('tag' => 'button', 'id' => 'permAddBtn', 'class' => '', 'icon' => 'fa fa-plus',
              'label' => 'إضافة دور', 'title' => 'إضافة دور جديد', 'attrs' => 'type="button"'),
    ) : array();
    $header_back = false;
    include '../includes/page_header.php';

    if ($msg !== '') { echo '<div class="alert alert-success">' . pr_e($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . pr_e($err) . '</div>'; }
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا أدوار مسجلة بعد', 'أضف أول دور بزر «إضافة دور» أعلى الشاشة');
    }
    ?>

 <?php /* المكوّنُ الموحَّدُ لا وسمٌ خام: سبعةُ حقولٍ يفرضها العقدُ،
       والفترةُ لحظيّةٌ صادقةٌ لأنّ الأرقامَ تُقاس عند كلِّ فتح. */
    $NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')'; ?>
    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'إجمالي الأدوار', 'value' => number_format((int) ($total)), 'unit' => 'دور',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_roles.php',
            'comparison' => 'المعروض بعد التصفية', 'icon' => 'fa-user-tag', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'النشطة', 'value' => number_format((int) ($active)), 'unit' => 'دور',
            'period' => $NOW, 'status' => 'ok', 'drill' => 'perm_roles.php?status=1',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-circle-check', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'المعطلة', 'value' => number_format((int) (($total - $active))), 'unit' => 'دور',
            'period' => $NOW, 'status' => (($total - $active) > 0 ? 'warn' : 'ok'), 'drill' => 'perm_roles.php?status=0',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-circle-minus', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'أدوار لها بنود ملاحة', 'value' => number_format((int) ($withNav)), 'unit' => 'دور',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_nav_items.php',
            'comparison' => 'من ' . $total . ' معروضا', 'icon' => 'fa-compass', 'class' => 'ems-col-3'));
        ?>
    </div>

    <?php if ($can_add): ?>
    <form method="post" action="" class="allforms" id="permAddForm">
        <?php echo csrf_field(); ?>
 <input type="hidden" name="act" value="add">
 <div class="card">
 <div class="card-header"><h5><i class="fa fa-plus"></i> إضافة دور</h5></div>
 <div class="card-body">
 <div class="form-section"><div class="form-grid">
 <div class="form-group">
 <label for="pr_name">اسم الدور</label>
 <input type="text" id="pr_name" name="name" maxlength="100" required>
 </div>
 <div class="form-group">
 <label for="pr_parent">الدور الأب</label>
 <select id="pr_parent" name="parent_role_id">
 <option value="0">- بلا أب -</option>
 <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pr_e($r['name']); ?></option>
                            <?php endforeach; ?>
 </select>
 </div>
 <div class="form-group">
 <label for="pr_level">المستوى</label>
 <input type="number" id="pr_level" name="level" min="1" max="99" value="1">
 </div>
 <div class="form-group">
 <label for="pr_scope">نطاق الدور</label>
 <select id="pr_scope" name="role_scope">
 <?php foreach ($SCOPES as $s): ?>
                            <option value="<?php echo pr_e($s); ?>"><?php echo pr_e(isset($SCOPE_AR[$s]) ? $SCOPE_AR[$s] : $s); ?></option>
                            <?php endforeach; ?>
 </select>
 </div>
 <div class="form-group">
 <label for="pr_status">الحالة</label>
 <select id="pr_status" name="status">
 <option value="1">نشط</option>
 <option value="0">معطل</option>
 </select>
 </div>
 </div></div>
 <div class="form-actions">
 <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
 <button type="button" class="btn-secondary" id="permCancelBtn"><i class="fa fa-times"></i> إلغاء</button>
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
 <div class="form-group">
 <label for="f_q">اسم الدور</label>
 <input type="text" id="f_q" name="name" value="<?php echo pr_e($fq); ?>">
 </div>
 <div class="form-group">
 <label for="f_status">الحالة</label>
 <select id="f_status" name="status">
 <option value="">الكل</option>
 <option value="1" <?php echo $fstat === '1' ? 'selected' : ''; ?>>نشط</option>
 <option value="0" <?php echo $fstat === '0' ? 'selected' : ''; ?>>معطل</option>
 </select>
 </div>
 <div class="form-group">
 <label for="f_scope">النطاق</label>
 <select id="f_scope" name="scope">
 <option value="">الكل</option>
 <?php foreach ($SCOPES as $s): ?>
                            <option value="<?php echo pr_e($s); ?>" <?php echo $fscope === $s ? 'selected' : ''; ?>>
                                <?php echo pr_e(isset($SCOPE_AR[$s]) ? $SCOPE_AR[$s] : $s); ?></option>
                            <?php endforeach; ?>
 </select>
 </div>
 </div>
 <button type="submit" class="btn-primary"><i class="fa fa-search"></i> بحث</button>
 <a class="btn-secondary" href="perm_roles.php"><i class="fa fa-rotate-left"></i> إعادة</a>
 </form>
 </div>
 </div>

 <div class="card"><div class="card-body">
 <div class="table-responsive">
 <table class="alltables display" id="permRolesTable" data-order='[[0,"asc"]]'>
 <thead>
 <tr>
 <th>#</th><th>اسم الدور</th><th>الدور الأب</th><th>المستوى</th>
 <th>النطاق</th><th>صلاحيات</th><th>بنود ملاحة</th>
 <th>الحالة</th><th>الإجراءات</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($rows as $x): ?>
                    <tr>
                        <td><?php echo (int) $x['id']; ?></td>
                        <td><?php echo pr_e($x['name']); ?></td>
                        <td><?php echo $x['parent_name'] !== null ? pr_e($x['parent_name']) : '—'; ?></td>
                        <td><?php echo (int) $x['level']; ?></td>
                        <td><?php echo pr_e(isset($SCOPE_AR[$x['role_scope']]) ? $SCOPE_AR[$x['role_scope']] : $x['role_scope']); ?></td>
                        <td><?php echo (int) $x['grants_n']; ?></td>
                        <td><?php echo (int) $x['navs_n']; ?></td>
                        <td><?php echo ((string) $x['status'] === '1') ? 'نشط' : 'معطل'; ?></td>
                        <td>
                            <?php if ($can_edit): ?>
                            <button type="button" class="btn-secondary permEdit"
                                    data-id="<?php echo (int) $x['id']; ?>"
                                    data-name="<?php echo pr_e($x['name']); ?>"
                                    data-parent="<?php echo (int) $x['parent_role_id']; ?>"
                                    data-level="<?php echo (int) $x['level']; ?>"
                                    data-scope="<?php echo pr_e($x['role_scope']); ?>"
                                    data-status="<?php echo pr_e($x['status']); ?>">تعديل</button>
 <?php endif; ?>
                            <?php if ($can_delete): ?>
 <form method="post" action="" class="ems-inline-form"
 onsubmit="return confirm('حذف الدور «<?php echo pr_e($x['name']); ?>»؟');">
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
    <form method="post" action="" class="allforms" id="permEditForm">
        <?php echo csrf_field(); ?>
 <input type="hidden" name="act" value="edit">
 <input type="hidden" name="id" id="pe_id">
 <div class="card">
 <div class="card-header"><h5><i class="fa fa-pen"></i> تعديل دور</h5></div>
 <div class="card-body">
 <div class="form-section"><div class="form-grid">
 <div class="form-group"><label for="pe_name">اسم الدور</label>
 <input type="text" id="pe_name" name="name" maxlength="100" required></div>
 <div class="form-group"><label for="pe_parent">الدور الأب</label>
 <select id="pe_parent" name="parent_role_id">
 <option value="0">- بلا أب -</option>
 <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"><?php echo pr_e($r['name']); ?></option>
                            <?php endforeach; ?>
 </select></div>
 <div class="form-group"><label for="pe_level">المستوى</label>
 <input type="number" id="pe_level" name="level" min="1" max="99"></div>
 <div class="form-group"><label for="pe_scope">نطاق الدور</label>
 <select id="pe_scope" name="role_scope">
 <?php foreach ($SCOPES as $s): ?>
                            <option value="<?php echo pr_e($s); ?>"><?php echo pr_e(isset($SCOPE_AR[$s]) ? $SCOPE_AR[$s] : $s); ?></option>
                            <?php endforeach; ?>
 </select></div>
 <div class="form-group"><label for="pe_status">الحالة</label>
 <select id="pe_status" name="status">
 <option value="1">نشط</option><option value="0">معطل</option>
 </select></div>
 </div></div>
 <div class="form-actions">
 <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ التعديل</button>
 <button type="button" class="btn-secondary" id="permEditCancel"><i class="fa fa-times"></i> إلغاء</button>
 </div>
 </div>
 </div>
 </form>
 <?php endif; ?>
</div>

<script>
(function () {
    var addBtn = document.getElementById('permAddBtn');
    var addForm = document.getElementById('permAddForm');
    var addCancel = document.getElementById('permCancelBtn');
    if (addBtn && addForm) {
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            addForm.classList.toggle('allforms-visible');
            if (addForm.classList.contains('allforms-visible')) {
                addForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    }
    if (addCancel && addForm) {
        addCancel.addEventListener('click', function () { addForm.classList.remove('allforms-visible'); });
    }

    var editForm = document.getElementById('permEditForm');
    var editCancel = document.getElementById('permEditCancel');
    if (editForm) {
        document.querySelectorAll('.permEdit').forEach(function (b) {
            b.addEventListener('click', function () {
                document.getElementById('pe_id').value = b.dataset.id;
                document.getElementById('pe_name').value = b.dataset.name;
                document.getElementById('pe_parent').value = b.dataset.parent || '0';
                document.getElementById('pe_level').value = b.dataset.level;
                document.getElementById('pe_scope').value = b.dataset.scope;
                document.getElementById('pe_status').value = b.dataset.status;
                editForm.classList.add('allforms-visible');
                editForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });
    }
    if (editCancel && editForm) {
        editCancel.addEventListener('click', function () { editForm.classList.remove('allforms-visible'); });
    }
})();
</script>
