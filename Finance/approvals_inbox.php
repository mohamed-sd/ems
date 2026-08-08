<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Finance/approvals_inbox.php — صندوقُ الاعتمادات الموحّد (M-42 · الشاشة 192)
 * ───────────────────────────────────────────────────────────────────────────
 * UX-02 §5: صناديقُ الاعتماد الأربعةُ المتفرقة (طلبٌ · تسويةٌ · قيدٌ ·
 * إقفال) في **صندوقٍ واحدٍ بعدّاده** — والقرارُ يمرّ **بخدمة مصدره**:
 * كلُّ سطرٍ بزرِّ قفزٍ إلى موضع الفعل، ولا قرارَ يُنفَّذ هنا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Finance/ApprovalsInboxService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Finance\ApprovalsInboxService as AIS;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }

$MODULE_CODE = 'Finance/approvals_inbox.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحيةَ عرضٍ للصندوق الموحد ❌', 'GOV-PERM-403', ''); exit(); }

$inbox = AIS::inbox($conn, $company_id);

$page_title = 'إيكوبيشن | صندوق الاعتمادات الموحد';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'صندوق الاعتمادات الموحد'; $header_icon = 'fa fa-inbox';
    $header_actions = array();
    include('../includes/page_header.php');

    // M-44: «ما هذه الشاشة؟» — المكوّنُ الموحّد
    ems_screen_about(
        'صندوقٌ واحدٌ يجمع كلَّ ما ينتظر قرارًا ماليًّا من الصناديق الأربعة: '
        . 'الطلباتُ المالية · تسوياتُ الموردين · القيودُ اليدوية · إقفالُ الفترات. '
        . 'القرارُ لا يُنفَّذ هنا — كلُّ سطرٍ يقفز بك إلى شاشة مالكه حيث الحرّاسُ كاملة.',
        array('افتح الصندوقَ صباحًا واقرأ العدّادات',
              'انقر السطرَ فيفتح موضعَ الفعل في شاشة مالكه',
              'اتخذ القرارَ هناك — يدان لا يدٌ واحدة، ومن أنشأ لا يعتمد'));
    ?>

    <div class="card"><div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap">
        <?php foreach ($inbox['boxes'] as $b): ?>
            <div class="badge <?php echo $b['count'] > 0 ? 'badge-warning' : 'badge-secondary'; ?>"
                 style="font-size:15px;padding:8px 14px">
                <?php echo htmlspecialchars($b['title']); ?>: <strong><?php echo intval($b['count']); ?></strong>
            </div>
        <?php endforeach; ?>
        <div class="badge badge-danger" style="font-size:16px;padding:8px 14px">
            المجموع: <strong><?php echo intval($inbox['total']); ?></strong></div>
    </div></div>

    <?php foreach ($inbox['boxes'] as $b): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-folder-open"></i>
        <?php echo htmlspecialchars($b['title']); ?> (<?php echo intval($b['count']); ?>)
        <small style="color:#888">— القرارُ في <?php echo htmlspecialchars($b['owner']); ?></small></h5></div>
    <div class="card-body">
        <?php if (!$b['rows']):
            // M-44: الحالةُ الفارغة الموحّدة — لا نصَّ DataTables العام
            ems_state_empty('لا شيءَ ينتظر قرارًا في هذا الصندوق — نظيف ✨');
        else: ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>العنصر</th><th>منذ</th><th></th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الورود</th>
              <th class="ems-fn-th" data-fn="1">نوع المستند</th>
              <th class="ems-fn-th" data-fn="1">المستند</th>
              <th class="ems-fn-th" data-fn="1">القيمة</th>
              <th class="ems-fn-th" data-fn="1">سقفي</th>
              <th class="ems-fn-th" data-fn="1">داخل سقفي؟</th>
              <th class="ems-fn-th" data-fn="1">الحلقة في السلسلة</th>
              <th class="ems-fn-th" data-fn="1">المهلة</th>
              <th class="ems-fn-th" data-fn="1">المتبقي</th>
              <th class="ems-fn-th" data-fn="1">قراري</th>
              <th class="ems-fn-th" data-fn="1">سبب القرار</th>
              <th class="ems-fn-th" data-fn="1">تاريخ القرار</th>
              <th class="ems-fn-th" data-fn="1">مرجع تفويضي</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th none" data-gov="creating_entity" data-slice="1" title="الجهة التي أنشأت المستند">الجهة المُنشئة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
            <tbody>
            <?php foreach ($b['rows'] as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['label']); ?></td>
                    <td><small><?php echo htmlspecialchars($r['since']); ?></small></td>
                    <td><a class="btn-save" href="<?php echo htmlspecialchars($r['link']); ?>">
                        اذهب إلى موضع الفعل ▸</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>
    <?php endforeach; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
