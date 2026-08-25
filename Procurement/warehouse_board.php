<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Procurement/warehouse_board.php — لوحةُ أمين المستودع (M-50 · الشاشة 199)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-09 §6: لوحةُ الدور المستقل (25) — أسئلةُ صباحه: ما الأصنافُ تحت الحد؟
 * ما استلاماتُ وصرفياتُ اليوم؟ ما العهدُ المفتوحة؟ — قراءةٌ وقفزٌ بلا أثر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/proc_helpers.php';
require_once __DIR__ . '/../app/Services/Procurement/ProcReorderService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Procurement\ProcReorderService as PRS;

$ctx = proc_ctx();
$company_id = $ctx['company_id'];
$is_super_admin = $ctx['is_super'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$perms = proc_page_perms($conn, 'Procurement/warehouse_board.php', $is_super_admin);
if (!$perms['can_view']) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية عرض للوحة المستودع ❌', 'GOV-PERM-403', ''); exit(); }
$co = intval($company_id);

// ① الأصنافُ تحت الحد — الرصيدُ الحي مقابل min_qty (بعدّاد)
$lowItems = array();
$r = $conn->query("SELECT id, name, min_qty FROM proc_item
                    WHERE company_id={$co} AND COALESCE(is_deleted,0)=0 AND min_qty > 0");
while ($r && ($x = $r->fetch_assoc())) {
    $bal = PRS::balance($conn, $co, intval($x['id']));
    if ($bal <= (float) $x['min_qty']) {
        $c = PRS::consumption($conn, $co, intval($x['id']));
        $lowItems[] = array('id' => intval($x['id']), 'name' => (string) $x['name'],
            'balance' => $bal, 'min' => (float) $x['min_qty'],
            'avg_daily' => $c['avg_daily'], 'suggested' => $c['suggested_trigger']);
    }
}

// ② استلاماتُ وصرفياتُ اليوم
$rcv = $conn->query("SELECT COUNT(*) n FROM proc_receipt_custody
                      WHERE company_id={$co} AND receipt_date = CURDATE()
                        AND COALESCE(is_deleted,0)=0")->fetch_assoc();
$iss = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(total_cost),0),2) v FROM proc_issue
                      WHERE company_id={$co} AND issue_date = CURDATE()
                        AND COALESCE(is_deleted,0)=0")->fetch_assoc();

// ③ العهدُ المفتوحة
$cust = $conn->query("SELECT COUNT(*) n FROM proc_custody
                       WHERE company_id={$co} AND state NOT IN ('مرجعة','مستهلكة','مغلقة')")->fetch_assoc();

$page_title = 'إيكوبيشن | لوحة أمين المستودع';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة أمين المستودع'; $header_icon = 'fa fa-warehouse';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about('لوحة الدور المستقل (25): الأصناف تحت الحد بمتوسط استهلاكها والحد '
        . 'المقترح · استلامات وصرفيات اليوم · العهد المفتوحة — قراءة وقفز إلى موضع الفعل.',
        array('اقرأ الأصناف تحت الحد أولا', 'ولد طلبات الشراء من قواعد إعادة الطلب'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا أصناف تحت الحد ولا حركة مستودع اليوم',
        'اضبط الحدود الدنيا من قواعد إعادة الطلب، أو سجل استلاما أو صرفا من الشاشتين المرتبطتين');
    ?>

    <div class="card"><div class="card-body proc-wb-chips">
        <div class="badge proc-wb-chip <?php echo $lowItems ? 'badge-danger' : 'badge-success'; ?>">
            أصناف تحت الحد: <strong><?php echo count($lowItems); ?></strong></div>
        <a class="badge badge-secondary proc-wb-chip proc-wb-chip-link"
           href="receipt_custody_proc.php">استلامات اليوم: <strong><?php echo intval($rcv['n']); ?></strong> ▸</a>
        <a class="badge badge-secondary proc-wb-chip proc-wb-chip-link"
           href="issue_proc.php">صرفيات اليوم: <strong><?php echo intval($iss['n']); ?></strong>
            (<?php echo htmlspecialchars((string)$iss['v']); ?>) ▸</a>
        <a class="badge badge-warning proc-wb-chip proc-wb-chip-link"
           href="receipt_custody_proc.php">عهد مفتوحة: <strong><?php echo intval($cust['n']); ?></strong> ▸</a>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-arrow-trend-down"></i>
        الأصناف تحت الحد — بمتوسط الاستهلاك والحد المقترح (M-51)</h5></div>
    <div class="card-body">
        <?php if (!$lowItems): ems_state_empty('لا أصناف تحت الحد — المخزون سليم ✨'); else: ?>
        <div class="table-container"><table class="alltables display nowrap proc-wb-table" data-no-dt="1">
            <thead><tr><th>الصنف</th><th>الرصيد الحي</th><th>الحد الأدنى</th>
                <th>متوسط الاستهلاك/يوم (90ي)</th><th>الحد المقترح (M-51)</th><th></th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($lowItems as $li): ?>
                <tr class="proc-wb-row-low">
                    <td><strong><?php echo htmlspecialchars($li['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$li['balance']); ?></td>
                    <td><?php echo htmlspecialchars((string)$li['min']); ?></td>
                    <td><?php echo htmlspecialchars((string)$li['avg_daily']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$li['suggested']); ?></strong>
                        <small class="proc-wb-hint">(متوسط×مهلة+أمان)</small></td>
                    <td><a class="btn-primary" href="reordering_proc.php">قواعد إعادة الطلب ▸</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>
</div>

<style>
    /* UXW-01 ①②: أصنافٌ محلَّ الأنماطِ الموضعيةِ — والألوانُ برموزِ اللوحةِ بقيمِها الاحتياطية */
    .proc-wb-chips { display: flex; gap: 14px; flex-wrap: wrap; }
    .proc-wb-chip { font-size: 15px; padding: 8px 14px; }
    .proc-wb-chip-link { text-decoration: none; }
    .proc-wb-table { width: 100%; }
    .proc-wb-row-low { background: var(--c-fff3f0, #fff3f0); }
    .proc-wb-hint { color: var(--c-s-888); }
</style>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
