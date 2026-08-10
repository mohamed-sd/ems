<?php
/**
 * Maintenance/return_to_service.php — العودةُ للخدمة (★ الصيانة · update0007 S-11)
 * ───────────────────────────────────────────────────────────────────────────
 * خاتمةُ دورة الصيانة: أوامرُ العمل المنجَزةُ تُعيد معدتَها للخدمة بشهادةِ
 * جاهزيةٍ موثَّقة — فلا معدةَ تعود بصمتٍ ولا أمرَ يبقى مفتوحًا بعد عودتها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['rts_order'])) {
    $oid  = intval($_POST['rts_order']);
    $cert = trim($_POST['readiness_note'] ?? '');
    if ($cert === '') { $msg = 'شهادةُ الجاهزية إلزامية (422)'; }
    else {
        $r = mysqli_query($conn, "SELECT equipment_id FROM mnt_order WHERE id=$oid AND company_id=$company_id AND state IN ('Done','Executed','QA')");
        if ($r && ($o = mysqli_fetch_assoc($r))) {
            mysqli_begin_transaction($conn);
            $ok1 = mysqli_query($conn, "UPDATE mnt_order SET state='Closed', updated_at=NOW() WHERE id=$oid");
            $ok2 = mysqli_query($conn, "UPDATE equipments SET status=1 WHERE id=" . intval($o['equipment_id']));
            if ($ok1 && $ok2) { mysqli_commit($conn); $msg = "عادت المعدةُ للخدمة وأُقفل الأمرُ #$oid — الشهادة: $cert"; }
            else { mysqli_rollback($conn); $msg = 'فشلت المعاملة: ' . mysqli_error($conn); }
        } else { $msg = 'الأمرُ ليس منجَزًا بانتظار العودة (409)'; }
    }
}

$rows = array();
$r = mysqli_query($conn, "SELECT mo.id, mo.order_no, mo.state, e.id eq_id, e.name eq_name, e.status
                          FROM mnt_order mo JOIN equipments e ON e.id = mo.equipment_id
                          WHERE mo.company_id=$company_id AND mo.state IN ('Done','Executed','QA')
                          ORDER BY mo.updated_at DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'العودة للخدمة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-check-circle';
$header_title_html = htmlspecialchars('العودةُ للخدمة', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الأمر</th><th>المعدة</th><th>حالتها</th><th>شهادةُ الجاهزية</th><th>إجراء</th>
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
    <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted">لا أوامرَ منجَزةً بانتظار العودة</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['order_no'] ?: '#' . $o['id'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['eq_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= intval($o['status']) === 1 ? 'عاملة' : 'متوقفة' ?></td>
        <form method="post">
          <input type="hidden" name="rts_order" value="<?= intval($o['id']) ?>">
          <td><input type="text" name="readiness_note" class="form-control form-control-sm" placeholder="فُحصت وجاهزة — التوقيع الفني" required aria-label="فُحصت وجاهزة — التوقيع الفني"></td>
          <td><button class="action-btn" type="submit"><i class="fa fa-undo"></i> أعد للخدمة وأقفل</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
