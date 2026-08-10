<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Portal/visibility_simulator.php — من يرى ماذا (H-16 · الشاشة 185)
 * ───────────────────────────────────────────────────────────────────────────
 * ADM-01 §3-③: «"ماذا يرى هذا الحساب؟" و"من يرى هذا العنصر؟" — إجابتان
 * مباشرتان **بمصدر كل قرارٍ ونطاقه**».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Portal/VisibilityPolicyService.php';

use App\Services\Portal\VisibilityPolicyService as VPS;
use App\Services\Portal\CapacityService as CAP;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$MODULE_CODE = 'Portal/visibility_simulator.php';
$can_view = false;
if ($is_super_admin) { $can_view = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; }
    $st->close();
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحيةَ عرضٍ للمحاكاة ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('visibility sim super') : ems_tenant_db();

$accounts = array();
$r = $conn->query("SELECT id, name, username FROM users
                    WHERE company_id = {$company_id} AND status='active'
                      AND COALESCE(is_deleted,0)=0 ORDER BY id");
while ($r && ($row = $r->fetch_assoc())) { $accounts[] = $row; }
$elements = VPS::elements($conn);

$askAccount = intval($_GET['account_id'] ?? 0);
$askElement = strval($_GET['element_code'] ?? '');

$simulation = null; $watchers = null; $ctxUsed = null;
if ($askAccount > 0) {
    // سياقُ الحساب من صفاته النشطة (H-15) — لا من تخمين
    $ctx = array('account_id' => $askAccount);
    foreach (CAP::activeOf($conn, $gate, $askAccount) as $c) {
        if ((string) $c['state'] !== 'active') { continue; }
        $ctx['capacity_type'] = (string) $c['capacity_type'];
        if ((string) $c['scope_type'] === 'project')  { $ctx['project_id'] = intval($c['scope_id']); }
        if ((string) $c['scope_type'] === 'supplier') { $ctx['supplier_id'] = intval($c['scope_id']); }
        if ((string) $c['scope_type'] === 'client')   { $ctx['client_id'] = intval($c['scope_id']); }
        break;
    }
    $ctxUsed = $ctx;
    $simulation = VPS::simulate($conn, $gate, $company_id, $ctx);
}
if ($askElement !== '') {
    $watchers = VPS::whoSees($conn, $gate, $company_id, $askElement);
}

$page_title = 'إيكوبيشن | من يرى ماذا';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'من يرى ماذا (المحاكاة)'; $header_icon = 'fa fa-user-secret';
    $header_actions = array();
    include('../includes/page_header.php');
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>ماذا يرى الحساب؟</strong>
            <select name="account_id">
                <option value="0">— اختر حسابًا —</option>
                <?php foreach ($accounts as $a): ?>
                    <option value="<?php echo intval($a['id']); ?>" <?php echo $askAccount === intval($a['id']) ? 'selected' : ''; ?>>
                        #<?php echo intval($a['id']); ?> — <?php echo htmlspecialchars((string)$a['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <strong>· من يرى العنصر؟</strong>
            <select name="element_code">
                <option value="">— اختر عنصرًا —</option>
                <?php foreach ($elements as $e): ?>
                    <option value="<?php echo htmlspecialchars((string)$e['element_code']); ?>"
                        <?php echo $askElement === (string)$e['element_code'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$e['title_ar']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-primary"><i class="fa fa-magnifying-glass"></i> أجب</button>
        </form>
    </div></div>

    <?php if ($simulation !== null): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-eye"></i>
        ماذا يرى الحساب #<?php echo $askAccount; ?>؟
        <small style="color:#888">(السياقُ من صفته النشطة: <?php echo htmlspecialchars(json_encode($ctxUsed, JSON_UNESCAPED_UNICODE)); ?>)</small></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>العنصر</th><th>القرار</th><th>مصدرُ القرار</th><th>سببُه</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
            <tbody>
            <?php foreach ($simulation as $code => $d): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($code); ?></code></td>
                    <td><?php echo $d['visible']
                        ? "<span class='badge badge-success'>يظهر</span>"
                        : "<span class='badge badge-danger'>لا يُصيَّر</span>"; ?></td>
                    <td><?php echo htmlspecialchars((string)$d['source']); ?></td>
                    <td><small><?php echo htmlspecialchars((string)($d['reason'] ?? '')); ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>

    <?php if ($watchers !== null): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-users-viewfinder"></i>
        من يرى «<?php echo htmlspecialchars($askElement); ?>»؟ — <?php echo count($watchers); ?> حسابًا</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الحساب</th><th>فئةُ صفته</th><th>مصدرُ القرار</th></tr></thead>
            <tbody>
            <?php foreach ($watchers as $w): ?>
                <tr>
                    <td>#<?php echo intval($w['account_id']); ?></td>
                    <td><?php echo htmlspecialchars((string)$w['capacity_type']); ?></td>
                    <td><?php echo htmlspecialchars((string)$w['source']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
