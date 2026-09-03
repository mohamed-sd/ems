<?php
/**
 * Governance/perm_screen_guide.php — دليل الشاشات (PERM-SCR-01 ⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **عرض وتعديل لا اضافة ولا حذف**: صفوف `screen_about` تنشئها الشاشات نفسها
 *   عند اول فتح (`ems_screen_about_auto`) - فالاضافة اليدوية تخلق سجلا يتيما
 *   لشاشة لا وجود لها، والحذف يمحو وصفا ستعيد الشاشة انشاءه فارغا.
 * ⛔ **والاعمدة `screen_path` و`title_ar` و`description`** لا `screen_code`
 *   و`description_ar` - من المخطط الحي.
 * ◆ **و`source` يقول من كتب الوصف**: `authored` بيد و`derived` باشتقاق
 *   و`composed` بتركيب. والتحرير هنا يرفعه الى `authored` لان اليد كتبته.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_screen_guide.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لدليل الشاشات', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}
$can_edit = $is_super_admin || !empty($__pp['can_edit']);

/* مفردات المصدر تشتق من المخطط - ENUM يبتلع الغريب فارغا بلا خطا. */
$SOURCES = array();
$sc = @mysqli_query($conn, "SHOW COLUMNS FROM screen_about LIKE 'source'");
if ($sc && ($sr = mysqli_fetch_assoc($sc)) && preg_match_all("/'([^']*)'/", $sr['Type'], $sm)) {
    $SOURCES = $sm[1];
}
if (!$SOURCES) { $SOURCES = array('authored', 'composed', 'derived'); }
$SOURCE_AR = array('authored' => 'مكتوب بيد', 'composed' => 'مركب', 'derived' => 'مشتق');

/* الوصف يعد ناقصا تحت هذا الحد - والحد معلن لا مضمر. */
$MIN_DESC = 40;

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $err = 'رمز الحماية غير صالح. اعد تحميل الصفحة.';
    } elseif (!$can_edit) {
        $err = 'غير مصرح بالتعديل';
    } else {
        $id    = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title_ar'] ?? ''));
        $desc  = trim((string) ($_POST['description'] ?? ''));
        if ($id <= 0 || mb_strlen($title) < 2) {
            $err = 'العنوان مطلوب.';
        } else {
            /* التحرير يرفع المصدر الى مكتوب بيد - فاليد هي التي كتبته الان. */
            $src = 'authored';
            $st = mysqli_prepare($conn, 'UPDATE screen_about SET title_ar=?, description=?, source=?, updated_at=NOW() WHERE id=?');
            mysqli_stmt_bind_param($st, 'sssi', $title, $desc, $src, $id);
            if (mysqli_stmt_execute($st)) { $msg = 'حدث وصف الشاشة'; } else { $err = 'تعذر التحديث'; }
            mysqli_stmt_close($st);
        }
    }
}

$fpath  = trim((string) ($_GET['screen_path'] ?? ''));
$fstate = (string) ($_GET['state'] ?? '');
$where = array(); $types = ''; $args = array();
if ($fpath !== '') {
    $where[] = '(a.screen_path LIKE ? OR a.title_ar LIKE ?)';
    $types .= 'ss'; $args[] = '%' . $fpath . '%'; $args[] = '%' . $fpath . '%';
}
if ($fstate === 'full')  { $where[] = 'CHAR_LENGTH(a.description) >= ?'; $types .= 'i'; $args[] = $MIN_DESC; }
if ($fstate === 'short') { $where[] = 'CHAR_LENGTH(a.description) < ?';  $types .= 'i'; $args[] = $MIN_DESC; }
$wsql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sql = 'SELECT a.id, a.screen_path, a.title_ar, a.description, a.source, a.active, a.updated_at
          FROM screen_about a' . $wsql . ' ORDER BY a.screen_path';
$rows = array();
if ($st = mysqli_prepare($conn, $sql)) {
    if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$args); }
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($x = mysqli_fetch_row($rs))) {
        $rows[] = array('id' => (int) $x[0], 'screen_path' => $x[1], 'title_ar' => $x[2],
                        'description' => (string) $x[3], 'source' => $x[4],
                        'active' => (int) $x[5], 'updated_at' => $x[6]);
    }
    mysqli_stmt_close($st);
}

$total = count($rows); $fullN = 0; $authoredN = 0;
foreach ($rows as $x) {
    if (mb_strlen($x['description']) >= $MIN_DESC) { $fullN++; }
    if ($x['source'] === 'authored') { $authoredN++; }
}

$page_title = 'ايكوبيشن | دليل الشاشات';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function ps_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'دليل الشاشات';
    $header_icon = 'fa fa-book';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    if ($msg !== '') { echo '<div class="alert alert-success">' . ps_e($msg) . '</div>'; }
    if ($err !== '') { echo '<div class="alert alert-danger">' . ps_e($err) . '</div>'; }
    if (function_exists('ems_states_bundle')) {
        echo ems_states_bundle('لا شاشات مسجلة في الدليل بعد',
            'السجل ينشا تلقائيا عند اول فتح لكل شاشة');
    }
    ?>

    <div class="alert alert-info">
        سجلات هذا الدليل تنشئها الشاشات نفسها عند اول فتح، فلا اضافة يدوية ولا
        حذف هنا - <strong>التحرير وحده</strong>. والوصف يعد ناقصا تحت
        <?php echo (int) $MIN_DESC; ?> حرفا.
    </div>

    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'الشاشات المعروضة', 'value' => number_format($total), 'unit' => 'شاشة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_screen_guide.php',
            'comparison' => 'بعد التصفية', 'icon' => 'fa-book', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'مكتملة الوصف', 'value' => number_format($fullN), 'unit' => 'شاشة',
            'period' => $NOW, 'status' => 'ok', 'drill' => 'perm_screen_guide.php?state=full',
            'comparison' => 'من ' . $total . ' معروضة', 'icon' => 'fa-circle-check', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'ناقصة الوصف', 'value' => number_format($total - $fullN), 'unit' => 'شاشة',
            'period' => $NOW, 'status' => ($total - $fullN) > 0 ? 'warn' : 'ok',
            'drill' => 'perm_screen_guide.php?state=short',
            'comparison' => 'دون ' . $MIN_DESC . ' حرفا', 'icon' => 'fa-triangle-exclamation', 'class' => 'ems-col-3'));
        echo ems_kpi_card(array(
            'title' => 'مكتوبة بيد', 'value' => number_format($authoredN), 'unit' => 'شاشة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_screen_guide.php',
            'comparison' => 'والباقي مشتق او مركب', 'icon' => 'fa-pen', 'class' => 'ems-col-3'));
        ?>
    </div>

    <div class="filter">
        <div class="filter-title">تصفية</div>
        <div class="filter-body">
            <form method="get" action="">
                <div class="form-grid">
                    <div class="form-group"><label for="f_path">المسار او العنوان</label>
                        <input type="text" id="f_path" name="screen_path" value="<?php echo ps_e($fpath); ?>"></div>
                    <div class="form-group"><label for="f_state">حالة الاكتمال</label>
                        <select id="f_state" name="state">
                            <option value="">الكل</option>
                            <option value="full" <?php echo $fstate === 'full' ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="short" <?php echo $fstate === 'short' ? 'selected' : ''; ?>>ناقصة</option>
                        </select></div>
                </div>
                <button type="submit" class="btn-primary"><i class="fa fa-search"></i> بحث</button>
                <a class="btn-secondary" href="perm_screen_guide.php"><i class="fa fa-rotate-left"></i> اعادة</a>
            </form>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-responsive">
            <table class="alltables display" id="permGuideTable" data-page-length="25">
                <thead><tr>
                    <th>#</th><th>مسار الشاشة</th><th>العنوان</th><th>الوصف</th>
                    <th>المصدر</th><th>اخر تحديث</th><th>الاجراءات</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $x): $len = mb_strlen($x['description']); ?>
                    <tr>
                        <td><?php echo (int) $x['id']; ?></td>
                        <td><code><?php echo ps_e($x['screen_path']); ?></code></td>
                        <td><?php echo ps_e($x['title_ar']); ?></td>
                        <td><?php echo $len === 0 ? 'بلا وصف'
                                : ps_e(mb_substr($x['description'], 0, 90) . ($len > 90 ? '...' : '')); ?>
                            (<?php echo (int) $len; ?> حرفا)</td>
                        <td><?php echo ps_e(isset($SOURCE_AR[$x['source']]) ? $SOURCE_AR[$x['source']] : $x['source']); ?></td>
                        <td><?php echo ps_e(ems_fmt_date($x['updated_at'], 'datetime', 'بلا تاريخ')); ?></td>
                        <td>
                            <?php if ($can_edit): ?>
                            <button type="button" class="btn-secondary psEdit"
                                data-id="<?php echo (int) $x['id']; ?>"
                                data-path="<?php echo ps_e($x['screen_path']); ?>"
                                data-title="<?php echo ps_e($x['title_ar']); ?>"
                                data-desc="<?php echo ps_e($x['description']); ?>">تحرير</button>
                            <?php else: ?>
                            عرض فقط
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>

    <?php if ($can_edit): ?>
    <form method="post" action="" class="allforms" id="psEditForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" id="pse_id">
        <div class="card">
            <div class="card-header"><h5><i class="fa fa-pen"></i> تحرير وصف شاشة</h5></div>
            <div class="card-body">
                <div class="form-section"><div class="form-grid">
                    <div class="form-group"><label for="pse_path">المسار</label>
                        <input type="text" id="pse_path" readonly disabled></div>
                    <div class="form-group"><label for="pse_title">العنوان</label>
                        <input type="text" id="pse_title" name="title_ar" maxlength="190" required></div>
                </div>
                <div class="form-group"><label for="pse_desc">الوصف الظاهر في بطاقة عن الشاشة</label>
                    <textarea id="pse_desc" name="description" rows="5"></textarea></div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> حفظ</button>
                    <button type="button" class="btn-secondary" id="psEditCancel"><i class="fa fa-times"></i> الغاء</button>
                </div>
                <p>الحفظ يرفع مصدر الوصف الى مكتوب بيد.</p>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
(function () {
    var ef = document.getElementById('psEditForm'), ec = document.getElementById('psEditCancel');
    if (ef) {
        document.querySelectorAll('.psEdit').forEach(function (x) {
            x.addEventListener('click', function () {
                document.getElementById('pse_id').value = x.dataset.id;
                document.getElementById('pse_path').value = x.dataset.path;
                document.getElementById('pse_title').value = x.dataset.title;
                document.getElementById('pse_desc').value = x.dataset.desc;
                ef.classList.add('allforms-visible');
                ef.scrollIntoView({behavior:'smooth',block:'nearest'});
            });
        });
    }
    if (ec && ef) { ec.addEventListener('click', function () { ef.classList.remove('allforms-visible'); }); }
})();
</script>
