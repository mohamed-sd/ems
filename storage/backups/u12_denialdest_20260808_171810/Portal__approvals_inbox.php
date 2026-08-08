<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-113 · SCN-114 · SCN-115 · SCN-117 · SCN-119 · SCN-120 · SCN-121 · SCN-122 · SCN-123 · SCN-124 · SCN-125 · SCN-126 · SCN-127 · SCN-128 · SCN-129 · SCN-130 · SCN-131 · SCN-132 · SCN-133
// شواهد المتطلبات (AC-E06-03 · موجة ٣ · تتمة): SCN-133
/**
 * Portal/approvals_inbox.php — موافقاتي (IAM-017 · WFM الورقة 09)
 * ───────────────────────────────────────────────────────────────────────────
 * «صندوقٌ واحدٌ يجمع كلَّ ما ينتظر قراره من كل الإدارات بالثلاثية الموحّدة —
 * وكل سطرٍ بزر قفزٍ إلى موضع الفعل، ولا سطرَ بلا إجراء» (IAM-017/019).
 * القرار يقع في شاشة صاحبه لا هنا (WF-01: واجهةُ قراءةٍ وقفزٍ لا مصدر) —
 * فسلسلة الوحدة تعتمد في شاشتها بحارسها، والطلب في طلباتي، والرابط approval_links.
 * المنابع الثلاثة الحية:
 *   ① سلسلة الوحدة الخماسية: الحلقة المفتوحة التي دورُ حاملها دورُ المستخدم.
 *   ② الطلبات: ما حاملُه المستخدمُ في حالات القرار.
 *   ③ approval_links: خطوات الورقة 09 المسندة إليه أو لدوره.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once '../includes/permissions_helper.php';
require_once '../includes/unit_chain_helpers.php';

$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$is_super_admin = (strval($_SESSION['user']['role'] ?? '') === '-1');
$uid            = intval($_SESSION['user']['id'] ?? 0);
$role           = strval($_SESSION['user']['role'] ?? '');
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'غير مصرح', 'GOV-PERM-403', ''); exit(); }

$__pp = check_page_permissions($conn, 'Portal/approvals_inbox.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    require_once __DIR__ . '/../includes/perm_explain_live.php';
    $__why = ems_deny_message($conn, intval($_SESSION['user']['role'] ?? 0), 'Portal/approvals_inbox.php');
    ems_gov_flash_redirect('../main/dashboard.php', $__why, 'GOV-INFO-200', '');
    exit();
}

$co = $is_super_admin && $company_id <= 0 ? 4 : $company_id;
$items = array();

/* ① سلسلة الوحدة: الحالات الوسيطة التي حلقتُها القادمة بيد دور المستخدم */
$stageRoles = array(
    'submitted'        => array('5', '6'),        // اعتماد الموقع
    'site_approved'    => array('1'),             // التشغيل
    'parties_review'   => array('2', '4'),        // الموردون والقوى
    'parties_approved' => array('12'),            // المبيعات/العقود
    'sales_approved'   => array('17', '19'),      // المالية — التحويل
);
$myStages = array();
foreach ($stageRoles as $stage => $roles) {
    if ($is_super_admin || in_array($role, $roles, true)) { $myStages[] = $stage; }
}
if ($myStages) {
    $in = "'" . implode("','", $myStages) . "'";
    $r = mysqli_query($conn,
        "SELECT ue.id, ue.entry_no, ue.entry_date, ue.state, ue.qty, ue.unit_type,
                p.name AS project_name, DATEDIFF(CURDATE(), ue.entry_date) age_d
           FROM unit_entries ue
           LEFT JOIN project p ON p.id = ue.project_id
          WHERE ue.company_id = {$co} AND ue.state IN ({$in})
            AND NOT " . ems_uc_prechain_sql('ue') . "
          ORDER BY ue.entry_date ASC LIMIT 60");
    $stageAr = array('submitted' => 'اعتماد الموقع', 'site_approved' => 'مطابقة التشغيل',
                     'parties_review' => 'أحكام الأطراف', 'parties_approved' => 'اعتماد العقود',
                     'sales_approved' => 'التحويل المالي');
    while ($r && ($x = mysqli_fetch_assoc($r))) {
        $link = ($x['state'] === 'sales_approved') ? '../Finance/unit_records_fin.php'
                                                   : '../Approvals/hours_approval.php';
        $items[] = array(
            'kind' => 'سلسلة الوحدة', 'ref' => $x['entry_no'],
            'title' => 'حلقة «' . $stageAr[$x['state']] . '» — ' . ($x['project_name'] ?: 'وحدة') . ' · ' . $x['entry_date'],
            'age' => intval($x['age_d']) . ' يومًا', 'sla' => intval($x['age_d']) > 3 ? 'متجاوز' : 'ضمن المهلة',
            'link' => $link, 'action' => 'اعتمد في شاشة الحلقة',
        );
    }
}

/* ② الطلبات المنتظرة قراري */
$r = mysqli_query($conn,
    "SELECT rq.id, rq.request_no, rq.title, rq.status, rq.submitted_at, rq.sla_due_at, rt.name_ar
       FROM requests rq JOIN request_types rt ON rt.code = rq.request_type_code
      WHERE rq.company_id = {$co} AND rq.current_holder_user_id = {$uid}
        AND rq.status IN ('submitted','routed','in_approval','approved')
      ORDER BY rq.sla_due_at ASC LIMIT 60");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $late = $x['sla_due_at'] !== null && strtotime($x['sla_due_at']) < time();
    $items[] = array(
        'kind' => 'طلب', 'ref' => $x['request_no'],
        'title' => $x['name_ar'] . ' — ' . $x['title'],
        'age' => 'قُدّم ' . $x['submitted_at'], 'sla' => $late ? 'متجاوز' : ('مهلته ' . $x['sla_due_at']),
        'link' => 'my_requests.php', 'action' => $x['status'] === 'approved' ? 'نفّذ وأغلق بالرد التسعة' : 'قرّر (اعتماد/إعادة/رفض)',
    );
}

/* ③ خطوات approval_links المسندة إليّ أو لدوري */
$r = mysqli_query($conn,
    "SELECT al.id, al.source_kind, al.source_ref, al.action_code, al.step_no, al.sla_due_at
       FROM approval_links al
      WHERE al.company_id = {$co} AND al.status = 'pending'
        AND (al.approver_user_id = {$uid} OR (al.approver_user_id IS NULL AND al.approver_role = '" . mysqli_real_escape_string($conn, $role) . "'))
      ORDER BY al.sla_due_at IS NULL, al.sla_due_at ASC LIMIT 60");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $late = $x['sla_due_at'] !== null && strtotime($x['sla_due_at']) < time();
    $items[] = array(
        'kind' => 'خطوة اعتماد', 'ref' => $x['source_kind'] . ':' . $x['source_ref'],
        'title' => 'فعل ' . $x['action_code'] . ' — خطوة ' . intval($x['step_no']),
        'age' => '—', 'sla' => $late ? 'متجاوز' : ($x['sla_due_at'] ?: 'بلا مهلة'),
        'link' => 'my_tasks.php', 'action' => 'اقفز لمستند المصدر',
    );
}

$page_title = 'إيكوبيشن | موافقاتي';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($__pp) ? $__pp : null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell" dir="rtl">
    <?php
    $header_title = 'موافقاتي';
    $header_icon = 'fa fa-stamp';
    $header_actions = array();
    $header_back = false;
    include '../includes/page_header.php';
    require_once __DIR__ . '/../includes/screen_contract.php';
    ems_screen_about('صندوقٌ واحدٌ لكل ما ينتظر قراري من كل المنابع — وكلُّ سطرٍ يقفز لموضع الفعل بحارسه.');

    ?>
    <div class="card"><div class="card-body">
        <p class="text-muted" style="margin:0 0 10px">
            <i class="fas fa-inbox"></i> <strong>صندوقٌ واحدٌ لكل ما ينتظر قرارك</strong> —
            والقرارُ يقع في شاشة صاحبه بحارسه (WF-01): كلُّ سطرٍ يقفز لموضع الفعل، ولا سطرَ بلا إجراء (IAM-019).
            <span class="badge bg-warning"><?php echo count($items); ?> بانتظارك</span>
        </p>
        <div class="table-responsive">
        <table class="alltables display" id="approvalsInboxTable">
            <thead><tr><th>النوع</th><th>المرجع</th><th>ما ينتظر قرارك</th><th>العمر/التقديم</th>
                <th>المهلة</th><th>الإجراء</th>
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
            <?php if (!$items): ?>
                <tr><td colspan="6" class="text-center text-muted">صندوقك فارغ — لا قرارَ معلَّقًا عليك</td></tr>
            <?php else: foreach ($items as $it): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($it['kind']); ?></span></td>
                    <td><code><?php echo htmlspecialchars((string) $it['ref']); ?></code></td>
                    <td style="white-space:normal;max-width:340px"><?php echo htmlspecialchars((string) $it['title']); ?></td>
                    <td><?php echo htmlspecialchars((string) $it['age']); ?></td>
                    <td><?php echo $it['sla'] === 'متجاوز'
                        ? '<span class="badge bg-danger">متجاوز</span>'
                        : htmlspecialchars((string) $it['sla']); ?></td>
                    <td><a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars((string) $it['link']); ?>">
                        <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars((string) $it['action']); ?></a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>
</div>
