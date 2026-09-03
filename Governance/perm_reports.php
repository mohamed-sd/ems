<?php
/**
 * Governance/perm_reports.php — صلاحيات التقارير (PERM-SCR-01 ⑧)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **جدول حضور لا اعلام**: `report_role_permissions(role_id, report_code)` -
 *   وجود الصف يعني السماح وحذفه يعني المنع. فلا عمود `allow` يقلب، والتبديل
 *   ادراج او حذف لا تحديث.
 * ◆ **ومفردات التقارير تشتق من المسجل لا من قائمة صلبة**: الرموز الخمسة
 *   والعشرون تقرا من الجدول نفسه، فرمز جديد يظهر هنا بلا تعديل شيفرة.
 * ◆ والتبديل يمر بالمعالج `perm_quick_update.php` عبر fetch - وهو يحرس
 *   بالحارس نفسه فلا يفتح بابا لا تفتحه الشاشة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../company/login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/kpi_card.php';
require_once __DIR__ . '/../includes/date_format.php';

$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$MODULE_CODE = 'Governance/perm_reports.php';
$__pp = check_page_permissions($conn, $MODULE_CODE);
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لصلاحيات التقارير', 'GOV-PERM-403',
        'اطلب المنحة من مدير الصلاحيات ان كانت ضمن عملك');
    exit();
}
$can_edit = $is_super_admin || !empty($__pp['can_edit']);

$roleId = (int) ($_GET['role_id'] ?? 0);

$allRoles = array();
$ar = @mysqli_query($conn, 'SELECT id, name, status FROM roles ORDER BY name');
while ($ar && ($x = mysqli_fetch_row($ar))) {
    $allRoles[] = array('id' => (int) $x[0], 'name' => $x[1], 'status' => (string) $x[2]);
}

/* الرموز من المسجل نفسه - لا قائمة صلبة تتقادم. */
$codes = array();
$rc = @mysqli_query($conn, 'SELECT DISTINCT report_code FROM report_role_permissions ORDER BY report_code');
while ($rc && ($x = mysqli_fetch_row($rc))) { $codes[] = (string) $x[0]; }

/* كم دورا يفتح كل تقرير - خريطة مجمعة تدمج في PHP لا تعبير مسمى في الشاشة. */
$byCode = array();
$rc = @mysqli_query($conn, 'SELECT report_code, COUNT(*) FROM report_role_permissions GROUP BY report_code');
while ($rc && ($x = mysqli_fetch_row($rc))) { $byCode[(string) $x[0]] = (int) $x[1]; }

$mine = array();
$roleName = '';
if ($roleId > 0) {
    foreach ($allRoles as $r) { if ($r['id'] === $roleId) { $roleName = $r['name']; break; } }
    if ($st = mysqli_prepare($conn, 'SELECT report_code FROM report_role_permissions WHERE role_id = ?')) {
        mysqli_stmt_bind_param($st, 'i', $roleId);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && ($x = mysqli_fetch_row($rs))) { $mine[(string) $x[0]] = true; }
        mysqli_stmt_close($st);
    }
}

$totalCodes = count($codes);
$openN = count($mine);
$totalGrants = 0;
foreach ($byCode as $n) { $totalGrants += $n; }

$page_title = 'ايكوبيشن | صلاحيات التقارير';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
function pq_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$NOW = 'لحظي (' . ems_fmt_date(time(), 'datetime') . ')';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'صلاحيات التقارير';
    $header_icon = 'fa fa-file-shield';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    ?>

    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'التقارير المسجلة', 'value' => number_format($totalCodes), 'unit' => 'تقرير',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_reports.php',
            'comparison' => 'رموز مشتقة من المسجل', 'icon' => 'fa-file-lines', 'class' => 'ems-col-4'));
        echo ems_kpi_card(array(
            'title' => 'اجمالي المنح', 'value' => number_format($totalGrants), 'unit' => 'منحة',
            'period' => $NOW, 'status' => 'neutral', 'drill' => 'perm_reports.php',
            'comparison' => 'على كل الادوار', 'icon' => 'fa-key', 'class' => 'ems-col-4'));
        echo ems_kpi_card(array(
            'title' => $roleId > 0 ? ('المفتوح للدور ' . $roleName) : 'المفتوح للدور المختار',
            'value' => number_format($openN), 'unit' => 'تقرير',
            'period' => $NOW, 'status' => $roleId > 0 ? ($openN > 0 ? 'ok' : 'warn') : 'neutral',
            'drill' => 'perm_reports.php' . ($roleId > 0 ? ('?role_id=' . $roleId) : ''),
            'comparison' => $roleId > 0 ? ('من ' . $totalCodes . ' مسجلا') : 'اختر دورا اولا',
            'icon' => 'fa-unlock', 'class' => 'ems-col-4'));
        ?>
    </div>

    <div class="filter">
        <div class="filter-title">اختر الدور</div>
        <div class="filter-body">
            <form method="get" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="f_role">الدور</label>
                        <select id="f_role" name="role_id">
                            <option value="0">- اختر دورا -</option>
                            <?php foreach ($allRoles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>" <?php echo $roleId === $r['id'] ? 'selected' : ''; ?>>
                                <?php echo pq_e($r['name']); ?><?php echo $r['status'] === '1' ? '' : ' (معطل)'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اعرض</button>
            </form>
        </div>
    </div>

    <?php if ($roleId <= 0): ?>
        <?php echo function_exists('ems_state_empty')
            ? ems_state_empty('اختر دورا لعرض تقاريره', '', '')
            : '<div class="card"><div class="card-body">اختر دورا لعرض تقاريره</div></div>'; ?>
    <?php elseif (!$codes): ?>
        <?php echo function_exists('ems_state_empty')
            ? ems_state_empty('لا رموز تقارير مسجلة بعد', '', '')
            : '<div class="card"><div class="card-body">لا رموز تقارير مسجلة بعد</div></div>'; ?>
    <?php else: ?>
    <div class="card">
        <div class="card-header"><h5><i class="fa fa-file-shield"></i> تقارير الدور <?php echo pq_e($roleName); ?></h5></div>
        <div class="card-body">
            <div id="pqStatus" class="alert alert-info" hidden></div>
            <div class="table-responsive">
                <table class="alltables display" id="permReportsTable" data-page-length="25">
                    <thead><tr>
                        <th>#</th><th>رمز التقرير</th><th>ادوار تفتحه</th><th>مسموح لهذا الدور</th>
                    </tr></thead>
                    <tbody>
                    <?php $i = 0; foreach ($codes as $c): $i++; ?>
                        <tr>
                            <td><?php echo (int) $i; ?></td>
                            <td><code><?php echo pq_e($c); ?></code></td>
                            <td><?php echo (int) (isset($byCode[$c]) ? $byCode[$c] : 0); ?></td>
                            <td>
                                <input type="checkbox" class="pqToggle" value="1"
                                       data-code="<?php echo pq_e($c); ?>"
                                       <?php echo isset($mine[$c]) ? 'checked' : ''; ?>
                                       <?php echo $can_edit ? '' : 'disabled'; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$can_edit): ?>
            <p>العرض فقط: لا صلاحية تعديل لديك على هذه الشاشة.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var box = document.getElementById('pqStatus');
    var roleId = <?php echo (int) $roleId; ?>;
    var token = <?php echo json_encode((string) ($_SESSION['csrf_token'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
    function say(text, bad) {
        if (!box) { return; }
        box.textContent = text;
        box.className = bad ? 'alert alert-danger' : 'alert alert-success';
        box.hidden = false;
    }
    document.querySelectorAll('.pqToggle').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var body = new URLSearchParams();
            body.append('csrf_token', token);
            body.append('role_id', String(roleId));
            body.append('report_code', cb.dataset.code);
            body.append('allow', cb.checked ? '1' : '0');
            fetch('perm_quick_update.php', {
                method: 'POST', credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: body
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j || !j.success) {
                    cb.checked = !cb.checked;           /* الرد لم ينجح: تعاد الحالة الظاهرة الى ما كانت */
                    say((j && j.message) ? j.message : 'تعذر الحفظ', true);
                } else {
                    say(cb.dataset.code + ': ' + j.message, false);
                }
            }).catch(function () {
                cb.checked = !cb.checked;
                say('تعذر الاتصال بالخادم', true);
            });
        });
    });
})();
</script>
