<?php
/** بوابة الطلب المالي D05 — طلباتي: قائمة طلبات المستخدم بحالتها المشتقة وسجلها */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = strval($_SESSION['user']['role']);
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin my requests super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/my_requests.php');
if (!$is_super && !$__pp['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا صلاحية', 'GOV-PERM-403', '');
    exit();
}

$catalog = finreq_catalog();
// النطاق (§10): الطالب طلباته — والأدوار المالية والسوبر يرون عبر بوابة المالية لا هنا
$rows = $gate->select('fin_requests', array(
    'whereRaw' => 'created_by = ? OR requester_id = ?',
    'params' => array($user_id, $user_id),
    'orderBy' => 'id DESC', 'limit' => 500,
));
foreach ($rows as $k => $r) { $rows[$k] = finreq_sync_state($gate, $r); }

$page_title = 'إيكوبيشن | طلباتي المالية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'طلباتي المالية';
    $header_icon    = 'fa fa-list-check';
    $header_actions = array(array('href' => 'request_form.php', 'class' => 'add-btn', 'icon' => 'fa fa-file-circle-plus', 'label' => 'طلب مالي جديد'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info" style="margin-bottom:14px;font-weight:700;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h5><i class="fas fa-table"></i> الطلبات (آخر 500)</h5></div>
        <div class="card-body table-container">
            <table class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>رقم الطلب</th><th>نوع الطلب</th><th>المبرّر</th><th>المستفيد</th>
                        <th>المبلغ</th><th>الحالة</th><th>الحدث</th><th>أُنشئ</th><th></th>
                        <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                        <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
                        <th class="ems-fn-th" data-fn="1">مقدّم الطلب</th>
                        <th class="ems-fn-th" data-fn="1">الإدارة</th>
                        <th class="ems-fn-th" data-fn="1">الوصف</th>
                        <th class="ems-fn-th" data-fn="1">الجهة المعنية</th>
                        <th class="ems-fn-th" data-fn="1">سبب الرفض</th>
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                        <th class="ems-gov-th" data-gov="required_approver" data-slice="1" title="من يلزم اعتماده بحسب سلسلة الاعتماد">المعتمِد المطلوب</th>
                        <th class="ems-gov-th none" data-gov="attachments" data-slice="3" title="مرفقات الإثبات">المرفقات</th>
                        </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['request_no']); ?></strong><?php echo intval($r['duplicate_flag']) === 1 ? ' <span class="badge bg-warning">تكرار؟</span>' : ''; ?></td>
                        <td><?php echo htmlspecialchars($catalog[$r['request_type']]['label'] ?? $r['request_type']); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($r['justification'] ?? '', 0, 40)); ?></td>
                        <td><?php echo htmlspecialchars($r['beneficiary_name'] ?? '-'); ?></td>
                        <td><?php echo number_format(floatval($r['amount']), 2) . ' ' . htmlspecialchars($r['currency']); ?></td>
                        <td><?php echo finreq_state_badge($r['state']); ?></td>
                        <td><?php echo $r['event_id'] ? ('#' . intval($r['event_id'])) : '—'; ?></td>
                        <td><?php echo htmlspecialchars(substr($r['created_at'], 0, 10)); ?></td>
                        <td>
                            <a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="action-btn view" title="فتح"><i class="fa fa-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
