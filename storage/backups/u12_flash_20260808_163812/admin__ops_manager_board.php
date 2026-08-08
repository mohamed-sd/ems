<?php
/**
 * admin/ops_manager_board.php — لوحة مدير التشغيل (update0004 · ORG-18)
 * ───────────────────────────────────────────────────────────────────────────
 * ORG-01 §3.1: «مساحة قرار لا تقرير يُقرأ» — المجموعات السبع، وكل رقم معه
 * إجراؤه. و«ما ينتظر قراره **بالساعات لا بالعدد فقط**» (§8 القبول): كل بند
 * معلَّق بعمر انتظاره ساعاتٍ من ساعة القاعدة، ومجموع ساعات الانتظار ظاهر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
if ($is_super_admin && $company_id <= 0) { $company_id = 4; }

$MODULE_CODE = 'admin/ops_manager_board.php';
$can_view = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; }
    $st->close();
}
if (!$can_view) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحية للوحة مدير التشغيل ❌')); exit(); }

$q1 = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_assoc() : null; };
$qa = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : array(); };

// ① الأداء التشغيلي
$ops = $q1("SELECT COUNT(*) c, COALESCE(SUM(target_daily_hours),0) target_h,
                   SUM(op_state='تعمل') working, SUM(op_state='معطلة') broken
              FROM operations o JOIN project p ON p.id = o.project_id
             WHERE p.company_id = {$company_id} AND o.status = 1");

// ② خطط المواقع المرفوعة (draft → تنتظر اعتماده)
$plans = $qa("SELECT dp.id, dp.plan_date, dp.state, p.name project_name,
                     TIMESTAMPDIFF(HOUR, dp.created_at, NOW()) wait_h
                FROM daily_plans dp JOIN project p ON p.id = dp.project_id
               WHERE dp.company_id = {$company_id} AND dp.is_deleted = 0 AND dp.state = 'draft'
               ORDER BY dp.plan_date LIMIT 30");

// ③ الصيانة والجاهزية
$mnt = $q1("SELECT SUM(state NOT IN ('إغلاق','ملغى')) open_orders,
                   COALESCE(SUM(CASE WHEN state NOT IN ('إغلاق','ملغى') THEN downtime_hours END),0) downtime_h
              FROM mnt_order WHERE company_id = {$company_id}");

// ④ القوى التشغيلية
$ops4 = $q1("SELECT COUNT(*) c FROM equipment_drivers ed
              JOIN operations o ON o.id = ed.operation_id AND o.status = 1
              JOIN project p ON p.id = o.project_id
             WHERE p.company_id = {$company_id} AND ed.status = 1");
$ops4 = $ops4 ?: $q1("SELECT COUNT(*) c FROM equipment_drivers WHERE status = 1");

// ⑤ المشتريات والمخازن
$proc = $q1("SELECT COUNT(*) c FROM proc_order WHERE company_id = {$company_id} AND is_deleted = 0
              AND state NOT IN ('مغلق','ملغي')");

// ⑥ النقل والترحيل
$trs = $q1("SELECT COUNT(*) c FROM transfer_requests WHERE company_id = {$company_id}
             AND state = 'submitted'");

// ⑦ ما ينتظر قراره — **بالساعات لا بالعدد** من مصادره الحية
$pending = array();
foreach ($qa("SELECT CONCAT('خطة يومية — ', p.name, ' (', dp.plan_date, ')') label,
                     TIMESTAMPDIFF(HOUR, dp.created_at, NOW()) wait_h,
                     '../Operations/daily_plans.php' link
                FROM daily_plans dp JOIN project p ON p.id = dp.project_id
               WHERE dp.company_id = {$company_id} AND dp.is_deleted = 0 AND dp.state = 'draft'") as $x) { $pending[] = $x; }
foreach ($qa("SELECT CONCAT('إذن #', r.req_id, ' — ', t.name_ar) label,
                     TIMESTAMPDIFF(HOUR, r.created_at, NOW()) wait_h,
                     CONCAT('org_permits.php?id=', r.req_id) link
                FROM permit_requests r JOIN permit_types t ON t.permit_type_code = r.permit_type_code
               WHERE r.company_id = {$company_id} AND r.state = 'pending'") as $x) { $pending[] = $x; }
foreach ($qa("SELECT CONCAT('تكليف #', a.asg_id, ' (', t.name_ar, ') ينتهي ', a.valid_to) label,
                     TIMESTAMPDIFF(HOUR, NOW(), CONCAT(a.valid_to, ' 23:59:59')) * -1 + 0 wait_h,
                     'org_assignments.php' link
                FROM org_assignments a JOIN org_assignment_types t ON t.type_code = a.assignment_type_code
               WHERE a.company_id = {$company_id} AND a.state = 'active'
                 AND a.valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)") as $x) {
    $x['wait_h'] = 0; $x['label'] .= ' (خلال 30 يومًا)';
    $pending[] = $x;
}
usort($pending, function ($a, $b) { return intval($b['wait_h']) - intval($a['wait_h']); });
$totalWaitH = 0;
foreach ($pending as $x) { $totalWaitH += max(0, intval($x['wait_h'])); }

$page_title = 'إيكوبيشن | لوحة مدير التشغيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';

function ems_board_tile($title, $value, $action, $link)
{
    echo '<div style="border:1px solid #ddd;border-radius:8px;padding:12px 16px;min-width:200px;flex:1">'
        . '<div style="color:#666;font-size:13px">' . htmlspecialchars($title) . '</div>'
        . '<div style="font-size:26px;font-weight:bold">' . $value . '</div>'
        . '<a class="btn-save" style="font-size:12px" href="' . htmlspecialchars($link) . '">' . htmlspecialchars($action) . ' ▸</a>'
        . '</div>';
}
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة مدير التشغيل — مساحة قرار'; $header_icon = 'fa fa-tachometer-alt';
    $header_actions = array();
    include('../includes/page_header.php');

    ems_screen_about(
        'المجموعات السبع (ORG-01 §3.1) — كل رقم معه إجراؤه، وما ينتظر قرارك '
        . 'معروض بساعات الانتظار لا بالعدد فقط: البند الأقدم انتظارًا أولًا.',
        array('ابدأ من «ما ينتظر قراره» — الأقدم ساعاتٍ أولًا',
              'كل بطاقة بزر يقفز إلى موضع الفعل'));
    ?>

    <div class="card"><div class="card-header"><h5>⑦ ما ينتظر قراره —
        <strong><?php echo count($pending); ?></strong> بندًا بمجموع انتظار
        <strong><?php echo $totalWaitH; ?> ساعة</strong> (بالساعات لا بالعدد)</h5></div>
    <div class="card-body">
        <?php if (!$pending) { ems_state_empty('لا شيء ينتظر قرارك — نظيف ✨'); } else { ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>البند</th><th>منتظرًا منذ (ساعة)</th><th></th>
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
            <?php foreach (array_slice($pending, 0, 40) as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars($x['label']); ?></td>
                    <td><strong><?php echo max(0, intval($x['wait_h'])); ?></strong></td>
                    <td><a class="btn-save" href="<?php echo htmlspecialchars($x['link']); ?>">إلى موضع الفعل ▸</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php } ?>
    </div></div>

    <div class="card"><div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap">
        <?php
        ems_board_tile('① الأداء التشغيلي — تشغيلات نشطة',
            intval($ops['c'] ?? 0) . ' <small style="font-size:13px">(تعمل ' . intval($ops['working'] ?? 0)
            . ' · معطلة ' . intval($ops['broken'] ?? 0) . ' · هدف يومي ' . round(floatval($ops['target_h'] ?? 0)) . ' س)</small>',
            'توزيع الموارد', '../movement/movement_operations.php');
        ems_board_tile('② خطط المواقع المرفوعة', count($plans), 'اعتماد أو إعادة', '../Operations/daily_plans.php');
        ems_board_tile('③ الصيانة — أوامر مفتوحة',
            intval($mnt['open_orders'] ?? 0) . ' <small style="font-size:13px">(توقف ' . round(floatval($mnt['downtime_h'] ?? 0)) . ' س)</small>',
            'رفع أولوية', '../Maintenance/orders.php');
        ems_board_tile('④ القوى — مشغّلون معيَّنون', intval($ops4['c'] ?? 0), 'طلب نقل أو بديل', '../Oprators/oprators.php');
        ems_board_tile('⑤ المشتريات — أوامر مفتوحة', intval($proc['c'] ?? 0), 'أولوية صرف', '../Procurement/orders_proc.php');
        ems_board_tile('⑥ النقل — طلبات مفتوحة', intval($trs['c'] ?? 0), 'اعتماد تحرك', '../Transport/transfer_requests.php');
        ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
