<?php
/**
 * Contracts/commercial_board.php — اللوحة التجارية للعقود (P-12)
 * ───────────────────────────────────────────────────────────────────────────
 * الملحق §3-`P-12` · §4 **شرطُ إغلاق الموجة**: «تعرض الأرقامَ الأربعة لعقدٍ
 * رائدٍ واحدٍ على الأقل، **وكلُّ فجوةٍ لها مالكٌ مسمًّى**».
 * **واللوحةُ قراءةٌ لا تخزين** — كلُّ رقمٍ من بيته، ولا جدولَ ثالثٌ يحفظها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/CommercialBoardService.php';

use App\Services\Contract\CommercialBoardService as CBD;
use App\Services\Contract\ContractBaselineService as CBS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit();
}

$MODULE_CODE = 'Contracts/commercial_board.php';
$can_view = false;
if ($is_super_admin) { $can_view = true; }
else {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = (intval($row['can_view']) === 1); }
    $st->close();
}
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+اللوحة+❌"); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('board super') : ems_tenant_db();
$ALL  = isset($_GET['all']) && $_GET['all'] === '1';

$rows = CBD::board($gate, !$ALL, 100);
$tot  = CBD::totals($rows);
$cl   = CBD::closureCheck($gate);
$GAPS = CBD::GAP_OWNERS;
$STATE_AR = CBS::STATE_AR;

$page_title = 'إيكوبيشن | اللوحة التجارية للعقود';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'اللوحة التجارية للعقود'; $header_icon = 'fa fa-chart-line';
    $header_actions = array();
    $header_back = array('href' => 'contract_lifecycle.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'اقتصاد دورة الحياة');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            <strong>المخططُ · المنفَّذُ · المفوترُ · المحصَّل</strong> في سطرٍ واحدٍ لكل عقدٍ نافذ —
            <strong>وكلُّ فجوةٍ بمالكها</strong>.
            و<strong>كلُّ رقمٍ من بيته</strong>: المخطَّطُ من الجدول الشهري · والمنفَّذُ من الوحدات
            <strong>بمفتاح الربط</strong> · والمفوترُ من المستخلصات · والمحصَّلُ من الذمم —
            <strong>ولا جدولَ ثالثٌ يحفظ اللوحة</strong> فلا يفترق رقمٌ عن مصدره.
            <br>
            و<strong>مصداقيةُ السطر تُعلَن مع أرقامه</strong>: وحدةٌ غيرُ موصولةٍ تعني
            <strong>منفَّذًا ناقصًا يبدو تامًّا</strong> — فيُوسَم السطرُ ولا يُقرأ على أنه تام.
        </p>
        <div style="margin-top:10px">
            <span class="badge <?php echo $cl['ok'] ? 'badge-success' : 'badge-warning'; ?>"
                style="padding:6px 12px"><?php
                echo htmlspecialchars(str_replace('**', '', (string)$cl['reason'])); ?></span>
            <a class="badge badge-secondary" style="padding:6px 12px;text-decoration:none"
               href="?all=<?php echo $ALL ? '0' : '1'; ?>">
               <?php echo $ALL ? 'النافذةُ فقط' : 'كلُّ العقود'; ?></a>
        </div>
    </div></div>

    <?php if ($tot): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-calculator"></i>
        المجاميع — <strong>بعملةٍ عملة، ولا تُجمع عملتان</strong></h5></div>
    <div class="card-body"><div style="display:flex;gap:12px;flex-wrap:wrap">
        <?php foreach ($tot as $cur => $t): ?>
            <div style="border:1px solid #d1d5db;border-radius:8px;padding:12px 18px;min-width:280px">
                <div style="font-weight:700;margin-bottom:6px"><?php echo htmlspecialchars((string)$cur); ?>
                    — <?php echo intval($t['contracts']); ?> عقدًا</div>
                <div>مخطَّط: <strong><?php echo $t['planned']; ?></strong></div>
                <div>منفَّذ: <strong><?php echo $t['executed']; ?></strong></div>
                <div>مفوتَر: <strong><?php echo $t['billed']; ?></strong></div>
                <div>محصَّل: <strong><?php echo $t['collected']; ?></strong></div>
            </div>
        <?php endforeach; ?>
    </div></div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-chart-line"></i>
        سطرٌ لكل عقد — <?php echo count($rows); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr>
                <th>#</th><th>العميل</th><th>حالُ العقد</th><th>خطُّ الأساس</th>
                <th>مخطَّط</th><th>منفَّذ</th><th>مفوتَر</th><th>محصَّل</th>
                <th>فجوةُ التنفيذ<br><small>التشغيل</small></th>
                <th>فجوةُ الفوترة<br><small>المبيعات</small></th>
                <th>فجوةُ التحصيل<br><small>المالية</small></th>
                <th>العملة</th><th>المصداقية</th>
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
            <?php foreach ($rows as $r): ?>
                <tr<?php echo $r['credible'] ? '' : " style='background:#fff7ed'"; ?>>
                    <td>#<?php echo intval($r['contract_id']); ?></td>
                    <td style="white-space:normal"><?php
                        echo htmlspecialchars((string)($r['second_party'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['contract_status'] ?? '')); ?></td>
                    <td><?php echo $r['baseline'] !== null
                        ? ('<span class="badge ' . ((string)$r['baseline'] === 'locked'
                            ? 'badge-success' : 'badge-secondary') . '">'
                           . htmlspecialchars($STATE_AR[(string)$r['baseline']]) . '</span>')
                        : '<span class="badge badge-warning">غيرُ مفتوح</span>'; ?></td>
                    <?php if (!$r['ok']): ?>
                        <td colspan="7" style="white-space:normal"><em><?php
                            echo htmlspecialchars(str_replace('**', '', (string)$r['note'])); ?></em></td>
                        <td><span class="badge badge-warning">ممتنع</span></td>
                    <?php else: ?>
                        <td><?php echo $r['planned']; ?></td>
                        <td><?php echo $r['executed']; ?></td>
                        <td><?php echo $r['billed']; ?></td>
                        <td><?php echo $r['collected']; ?></td>
                        <?php foreach (array('execution', 'billing', 'collection') as $k):
                            $v = (float) $r['gaps'][$k]['value']; ?>
                            <td title="<?php echo htmlspecialchars($GAPS[$k]['owner'] . ' — '
                                . $GAPS[$k]['question']); ?>">
                                <span class="badge <?php echo abs($v) < 0.005 ? 'badge-success'
                                    : ($v < 0 ? 'badge-warning' : 'badge-info'); ?>">
                                    <?php echo $v; ?></span></td>
                        <?php endforeach; ?>
                        <td><?php echo htmlspecialchars((string)$r['currency']); ?></td>
                        <td><?php if ($r['credible']): ?>
                                <span class="badge badge-success">تام</span>
                            <?php else: ?>
                                <span class="badge badge-warning">ناقص
                                    <?php echo intval($r['coverage']['units_total'])
                                             - intval($r['coverage']['units_linked']); ?> وحدة</span>
                            <?php endif; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="13"><em>لا عقود</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-user-tie"></i>
        <strong>وكلُّ فجوةٍ بمالكها</strong> — والسؤالُ الذي يُسأل عنه</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الفجوة</th><th>حسابُها</th><th>مالكُها</th><th>الدور</th><th>السؤال</th></tr></thead>
            <tbody>
                <tr><td><strong>فجوةُ التنفيذ</strong></td><td>منفَّذ − مخطَّط</td>
                    <td><strong><?php echo htmlspecialchars($GAPS['execution']['owner']); ?></strong></td>
                    <td><?php echo htmlspecialchars($GAPS['execution']['role']); ?></td>
                    <td><?php echo htmlspecialchars($GAPS['execution']['question']); ?></td></tr>
                <tr><td><strong>فجوةُ الفوترة</strong></td><td>مفوتَر − منفَّذ</td>
                    <td><strong><?php echo htmlspecialchars($GAPS['billing']['owner']); ?></strong></td>
                    <td><?php echo htmlspecialchars($GAPS['billing']['role']); ?></td>
                    <td><?php echo htmlspecialchars($GAPS['billing']['question']); ?></td></tr>
                <tr><td><strong>فجوةُ التحصيل</strong></td><td>محصَّل − مفوتَر</td>
                    <td><strong><?php echo htmlspecialchars($GAPS['collection']['owner']); ?></strong></td>
                    <td><?php echo htmlspecialchars($GAPS['collection']['role']); ?></td>
                    <td><?php echo htmlspecialchars($GAPS['collection']['question']); ?></td></tr>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
