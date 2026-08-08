<?php
/**
 * Reports/exceptions_report.php — تقرير الاستثناءات (GOV-01 §9-③ · الشاشة 203)
 * ───────────────────────────────────────────────────────────────────────────
 * «قائم يُوسَّع — مركز التقارير»: كل الاستثناءات بحالتها ومدتها ودرجتها
 * ونطاقها **وعدد استعمالاتها** — وارتفاعها في حماية بعينها يعني مراجعة
 * صنفها لا زيادة الاستثناءات (PLAN-05 §13-④). قراءة واشتقاق بلا أثر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$is_super   = (strval($_SESSION['user']['role'] ?? '') === '-1');
if (!$is_super && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$state = in_array(strval($_GET['state'] ?? ''), array('Pending','Active','Expired','Rejected','Revoked'), true)
       ? strval($_GET['state']) : '';
$where = "company_id = {$company_id}" . ($state !== '' ? " AND state = '{$state}'" : '');
$rows = array();
$r = $conn->query("SELECT r.*, g.name_ar guard_name, g.guard_class
                     FROM exception_requests r JOIN guard_policies g ON g.guard_code = r.guard_code
                    WHERE r.{$where} ORDER BY r.created_at DESC LIMIT 500");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$page_title = 'إيكوبيشن | تقرير الاستثناءات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تقرير الاستثناءات'; $header_icon = 'fa fa-shield-halved';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('كل استثناءات الحمايات بحالتها ومدتها ودرجتها ونطاقها وعدد استعمالاتها — '
        . 'وارتفاع الاستعمال في حماية بعينها يعني مراجعة صنفها لا زيادة الاستثناءات.',
        array('رشّح بالحالة', 'راقب عدّاد الاستعمال'));
    ?>
    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <label>الحالة</label>
            <select name="state">
                <option value="">الكل</option>
                <?php foreach (array('Pending','Active','Expired','Rejected','Revoked') as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $s === $state ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-save" type="submit">عرض</button>
        </form>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="table-container"><table class="alltables display" style="width:100%">
        <thead><tr><th>#</th><th>الحماية</th><th>الحالة</th><th>الدرجة</th><th>النطاق</th>
                   <th>من</th><th>إلى</th><th>الاستعمالات</th><th>السبب</th></tr></thead><tbody>
        <?php foreach ($rows as $x): ?>
        <tr>
            <td><?php echo intval($x['req_id']); ?></td>
            <td><?php echo htmlspecialchars($x['guard_name'] . ' (' . $x['guard_class'] . ')'); ?></td>
            <td><span class="badge badge-<?php echo $x['state'] === 'Active' ? 'success' : ($x['state'] === 'Pending' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars($x['state']); ?></span></td>
            <td><?php echo htmlspecialchars($x['risk_level']); ?></td>
            <td><?php echo htmlspecialchars($x['scope_type'] . '#' . $x['scope_id']); ?></td>
            <td><?php echo htmlspecialchars($x['valid_from']); ?></td>
            <td><?php echo htmlspecialchars($x['valid_to']); ?></td>
            <td><strong><?php echo intval($x['usage_count']); ?></strong></td>
            <td><?php echo htmlspecialchars(mb_substr((string) $x['reason'], 0, 80)); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
</div>
<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
