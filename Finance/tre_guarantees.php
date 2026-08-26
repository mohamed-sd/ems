<?php
/**
 * Finance/tre_guarantees.php — خطابات الضمان والاعتمادات المستندية (RPR-W11)
 * ───────────────────────────────────────────────────────────────────────────
 * خطابات الضمان والاعتمادات (TRS-15) — الاصدار على تسهيله وبقاعدته والكفالات النظامية عند الحوكمة.
 *
 * ◆ **الحبّةُ `Legal Entity × Accounting Period`** (‏`DEC-OPEN-03`): القراءةُ
 *   تمرُّ ببوّابةِ المستأجرِ التي تحقن الكيانَ — فلا صفَّ من كيانٍ آخرَ يظهر،
 *   ولا رقمَ يخلط كيانَين بلا وسمٍ مسجَّل.
 *
 * ◆ **والسطحُ سجلُّ قراءةٍ لا كاتبُ حكم**: القرارُ يُتَّخذ في خدمةِ الدورةِ
 *   بحارسِها ورمزِ ردِّها، والشاشةُ تعرض ما وقع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/w11_view.php';

$ctx = fin_ctx();
$is_super = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/tre_guarantees.php', $is_super);
if (empty($perms['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض هذه الشاشة', 'GOV-PERM-403', '');
    exit();
}

$rows = array();
try {
    $rows = fin_gate($is_super)->select('tre_guarantee', array('orderBy' => 'id DESC', 'limit' => 400));
} catch (\Throwable $t) { error_log('tre_guarantees.php list: ' . $t->getMessage()); }

$page_title = 'إيكوبيشن | خطابات الضمان والاعتمادات المستندية';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'خطابات الضمان والاعتمادات المستندية'; $header_icon = 'fa fa-file-shield'; $header_actions = array();
    $header_back = array('href' => 'funding_fin.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'التسهيلات البنكية');
    include('../includes/page_header.php'); ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= count($rows) ?></div><div class="ems-stat-label">الخطابات</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "issued") ?></div><div class="ems-stat-label">صادرة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= ems_w11_count($rows, "state", "released") ?></div><div class="ems-stat-label">مفرج عنها</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(ems_w11_sumf($rows, "amount"), 2) ?></div><div class="ems-stat-label">مجموع المبالغ</div></div>
    </div>

    <?php require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا خطابات صادرة', 'لا اصدار بلا تسهيل ولا بلا قاعدة صلاحية'); ?>

    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>الرقم</th><th>النوع</th><th>المستفيد</th><th>التسهيل</th><th>المبلغ</th><th>العملة</th><th>تاريخ الاصدار</th><th>تاريخ الانتهاء</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                    <td><?= htmlspecialchars((string) $r["doc_no"]) ?></td>
                    <td><?= ((string) $r["doc_kind"] === "letter_of_credit" ? "اعتماد مستندي" : "خطاب ضمان") ?></td>
                    <td><?= htmlspecialchars((string) $r["beneficiary"]) ?></td>
                    <td><?= (int) $r["facility_id"] ?></td>
                    <td><?= number_format((float) $r["amount"], 2) ?></td>
                    <td><?= htmlspecialchars((string) $r["currency"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["issued_at"]) ?></td>
                    <td><?= htmlspecialchars((string) $r["expires_at"]) ?></td>
                    <td><?= ems_w11_state((string) $r["state"]) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</body></html>
