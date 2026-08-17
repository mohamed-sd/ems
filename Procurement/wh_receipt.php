<?php
/**
 * Procurement/wh_receipt.php — الاستلام المؤقت قبل الإدخال (v3 · إسقاط حي)
 * ───────────────────────────────────────────────────────────────────────────
 * أُعيد بناؤها 2026-08-06 (قرار المالك «حل مشاكل الدور»): كانت v2 مخزنًا
 * بينيًّا (cmp03_screen_rows) ببذورٍ مبعثرة — فصارت **قائمةَ عملِ ما قبل
 * الوجهة** فوق proc_receipt_custody الحقيقي: كلُّ عهدةٍ لم تبلغ «مسلَّمة
 * للوجهة» تظهر هنا بعمرها وبنودها، والفعلُ يُنجَز في شاشة العهدة الأم
 * (receipt_custody_proc) — مصدرٌ واحدٌ للحقيقة ولا إدخالَ موازيًا.
 * صلاحياتُ العرض من موديول العهدة نفسِه (#71) — كيانٌ واحدٌ بابُه واحد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/receipt_custody_proc.php', $is_super_admin);
if (!$perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الاستلام المؤقت ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = proc_gate($is_super_admin);

// العهدُ التي لم تبلغ وجهتَها — قائمةُ العمل (مستلَمة · قيد الترحيل)
$rows = $gate->select('proc_receipt_custody', array(
    'whereRaw' => "state IN ('مستلَمة', 'قيد الترحيل')",
    'orderBy'  => 'receipt_date ASC, id ASC',
));

// خرائطُ الإثراء المجمَّعة
$sup_map = array();
foreach ($gate->select('proc_supplier', array('columns' => array('id', 'name'))) as $s) {
    $sup_map[intval($s['id'])] = (string) $s['name'];
}
$wh_map = array();
foreach ($gate->select('proc_warehouse', array('columns' => array('id', 'name'))) as $w) {
    $wh_map[intval($w['id'])] = (string) $w['name'];
}
$po_map = array();
foreach ($gate->select('proc_order', array('columns' => array('id', 'code'))) as $o) {
    $po_map[intval($o['id'])] = (string) $o['code'];
}
$lines_map = array();
foreach ($gate->scopedQuery(array('scope' => array('rl' => 'proc_receipt_line')),
    "SELECT rl.custody_id, COUNT(*) n, COALESCE(SUM(rl.qty),0) q
       FROM proc_receipt_line rl WHERE {TENANT_SCOPE} GROUP BY rl.custody_id") as $r) {
    $lines_map[intval($r['custody_id'])] = $r;
}

$today = new DateTime('today');

$page_title = 'إيكوبيشن | الاستلام المؤقت قبل الإدخال';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main proc-whrc-main ems-unified-page-shell ems-doc-cycle">
    <?php
    $header_title = 'الاستلام المؤقت قبل الإدخال';
    $header_icon  = 'fa fa-hourglass-half';
    $header_actions = array(array('href' => 'receipt_custody_proc.php', 'class' => 'add-btn',
                                  'icon' => 'fas fa-truck-ramp-box', 'label' => 'شاشة العهدة (الفعل هناك)'));
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    /* الدورةُ المستندية (بوابة ١٢): الاستلامُ المؤقتُ خطوتُه التاليةُ التسليمُ للوجهة */
    echo ems_next_step('تسليمُ العهدةِ للوجهة — يُسجَّل من شاشةِ العهدة', 'receipt_custody_proc.php');
    /* حزمةُ الحالاتِ الدنيا (بوابة ٩): تحميلٌ وفراغٌ وخطأٌ — مخفيةٌ افتراضًا */
    echo ems_states_bundle('لا عُهَدَ استلامٍ مؤقتٍ بانتظارِ الوجهة',
        'كلُّ استلامٍ لم يبلغ وجهتَه يظهر هنا بعمرِه — والتسليمُ يُسجَّل من شاشةِ العهدة فيغادر الصفُّ القائمة');
    ?>
    <style>
        .whr-note { color: var(--c-4b5563); margin: 0 0 10px; }
        .whr-w100 { width: 100%; }
        .whr-overdue > td { background: var(--c-fff7ed); }
        .whr-act-btn { padding: 4px 10px; }
    </style>

    <?php proc_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <p class="whr-note"><i class="fas fa-circle-info"></i>
            قائمةُ عمل: العهدُ التي **لم تبلغ وجهتَها بعد** (مستلَمة · قيد الترحيل) بعمرها منذ الاستلام —
            التسليمُ للوجهة يُسجَّل من شاشة العهدة، فيغادر الصفُّ هذه القائمة.</p>
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables whr-w100">
                <thead><tr>
                    <th>الإجراءات</th>
                    <th>رقم العهدة</th>
                    <th>تاريخ الاستلام</th>
                    <th>العمر (يوم)</th>
                    <th>المستلِم</th>
                    <th>المورد</th>
                    <th>مرجع أمر الشراء</th>
                    <th>موقع الاستلام</th>
                    <th>مخزن الإدخال</th>
                    <th>الوجهة المتوقعة</th>
                    <th>عدد الأصناف</th>
                    <th>إجمالي الكمية</th>
                    <th>الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي — عهدة الاستلام لا تُعتمد بدونه">المرفق</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($rows as $rc):
                    $rid = intval($rc['id']);
                    $ln  = isset($lines_map[$rid]) ? $lines_map[$rid] : array('n' => 0, 'q' => 0);
                    $age = '—';
                    if (!empty($rc['receipt_date'])) {
                        try { $age = (int) (new DateTime((string) $rc['receipt_date']))->diff($today)->format('%r%a'); }
                        catch (\Throwable $t) { $age = '—'; }
                    }
                    $overdue = is_int($age) && $age > 7;   // فوق أسبوعٍ بلا وجهة = متأخرة
                ?>
                    <tr<?php echo $overdue ? ' class="whr-overdue"' : ''; ?>>
                        <td><a href="receipt_custody_proc.php?edit_id=<?php echo $rid; ?>" class="add-btn whr-act-btn"
                               title="أكمل التسليم من شاشة العهدة"><i class="fas fa-pen"></i> افتح العهدة</a></td>
                        <td><?php echo htmlspecialchars((string) $rc['code']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($rc['receipt_date'] ?? '—')); ?></td>
                        <td><?php echo is_int($age) ? $age . ($overdue ? ' ⚠' : '') : '—'; ?></td>
                        <td><?php echo htmlspecialchars((string) $rc['holder_name']); ?></td>
                        <td><?php echo htmlspecialchars(isset($sup_map[intval($rc['supplier_id'])]) ? $sup_map[intval($rc['supplier_id'])] : '—'); ?></td>
                        <td><?php echo htmlspecialchars(isset($po_map[intval($rc['order_id'])]) ? $po_map[intval($rc['order_id'])] : '—'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($rc['receipt_location'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars(isset($wh_map[intval($rc['warehouse_id'])]) ? $wh_map[intval($rc['warehouse_id'])] : '—'); ?></td>
                        <td><?php echo htmlspecialchars((string) $rc['expected_destination']); ?></td>
                        <td><?php echo intval($ln['n']); ?></td>
                        <td><?php echo number_format((float) $ln['q'], 2); ?></td>
                        <td><?php echo htmlspecialchars((string) $rc['state']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

</body>
</html>
