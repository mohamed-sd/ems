<?php
/**
 * Portal/ceo_unified_queue.php — جميعُ الطلباتِ المرفوعةِ إليّ (CEO-08)
 * ───────────────────────────────────────────────────────────────────────────
 * Grain: **طلبٌ مرفوعٌ × إدارةٌ مصدر — Unified Queue لا شاشةَ معاملاتٍ
 * جديدة** (نصُّ الحبّةِ) — رحلةٌ عابرةُ نطاقاتٍ (CROSS_JOURNEY): إثباتُها
 * تسليمٌ صحيحٌ من كلِّ نطاقٍ لصندوقِ القمّة.
 *
 * ◆ الروافدُ الأربعةُ بالاسم، وكلُّ صفٍّ يحمل نطاقَه المصدرَ وفاعلَ فعلِه:
 *   اعتماداتُ الرئيسِ الماليّةُ الواردةُ (`exec_approvals`) · العقودُ
 *   المعروضةُ للتوقيعِ (`exec_contract_signings` غيرُ الموقَّعةِ) ·
 *   ملاحظاتُ المراجعةِ المصعَّدةُ للرئيس (`iaf_findings`) · تصعيداتُ
 *   المخاطرِ غيرُ المُقَرِّ بها (`risk_escalations`).
 * ◆ ⛔ لا شاشةَ معاملاتٍ جديدةً — الفعلُ عند فاعلِه المسمّى في كلِّ صفّ.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';

$is_super_admin = ((isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '') === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'بيئة شركة غير صالحة', 'GOV-SCOPE-403', ''); exit(); }

$pp = check_page_permissions($conn, 'Portal/ceo_unified_queue.php');
$can_view = $pp['can_view'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('ceo unified queue super') : ems_tenant_db();

$rows = array(); $nOpen = 0; $srcSet = array();
$push = function ($kind, $src, $ref, $desc, $state, $pending, $at, $link, $label) use (&$rows, &$nOpen, &$srcSet) {
    if ($pending) { $nOpen++; }
    $srcSet[$src] = true;
    $rows[] = array('kind' => $kind, 'src' => $src, 'ref' => $ref, 'desc' => $desc,
                    'state' => $state, 'at' => $at, 'link' => $link, 'label' => $label,
                    'pending' => $pending);
};
try {
    foreach ($gate->select('exec_approvals', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        $dec = trim((string) $x0['decision']);
        $push('اعتماد مالي مرفوع', (string) $x0['requesting_dept'], (string) $x0['request_no'],
              mb_substr((string) $x0['raise_reason'], 0, 60) . ' بمبلغ ' . number_format((float) $x0['amount'], 0) . ' ' . (string) $x0['currency'],
              $dec !== '' ? ('قرر: ' . $dec) : 'بانتظار قرار الرئيس', $dec === '',
              (string) $x0['received_date'], 'ceo_approvals.php', 'اعتمادات الرئيس');
    }
} catch (\Throwable $t) { error_log('ceo_queue approvals: ' . $t->getMessage()); }
try {
    foreach ($gate->select('exec_contract_signings', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        $signed = trim((string) $x0['signed_by_us']) !== '';
        $push('عقد معروض للتوقيع', (string) $x0['contract_kind'], (string) $x0['contract_no'],
              'مع ' . (string) $x0['other_party'] . ' بقيمة ' . number_format((float) $x0['amount'], 0) . ' ' . (string) $x0['currency'],
              $signed ? ('وقع بصفة ' . (string) $x0['signer_capacity']) : 'بانتظار التوقيع', !$signed,
              (string) $x0['signing_date'], 'ceo_contracts.php', 'توقيعات الرئيس');
    }
} catch (\Throwable $t) { error_log('ceo_queue signings: ' . $t->getMessage()); }
try {
    foreach ($gate->select('iaf_findings', array('orderBy' => 'id DESC', 'limit' => 2000)) as $x0) {
        if ((string) $x0['escalated_to'] !== 'ceo') { continue; }
        $closed = (string) $x0['state'] === 'closed';
        $push('ملاحظة مراجعة مصعدة', (string) $x0['auditee_dept'], (string) $x0['finding_no'],
              mb_substr((string) $x0['title'], 0, 60), $closed ? 'اقفلت' : 'بانتظار قرار الرئيس', !$closed,
              (string) $x0['escalated_at'], 'ceo_assurance_box.php', 'صندوق التأكيد');
    }
} catch (\Throwable $t) { error_log('ceo_queue iaf: ' . $t->getMessage()); }
try {
    foreach ($gate->select('risk_escalations', array('orderBy' => 'id DESC', 'limit' => 1000)) as $x0) {
        $ack = (int) $x0['acknowledged_by'] > 0;
        $push('تصعيد خطر', 'ادارة المخاطر', 'تصعيد رقم ' . (int) $x0['id'],
              mb_substr((string) $x0['reason_ar'], 0, 60) . ' نحو ' . (string) $x0['to_authority'],
              $ack ? 'اقر به' : 'بانتظار الاقرار', !$ack,
              (string) $x0['created_at'], '../Risk/risk_escalations.php', 'تصعيدات المخاطر');
    }
} catch (\Throwable $t) { error_log('ceo_queue risk: ' . $t->getMessage()); }
usort($rows, function ($a, $b) { return ($b['pending'] <=> $a['pending']) ?: strcmp((string) $b['at'], (string) $a['at']); });

$page_title = 'إيكوبيشن | جميع الطلبات المرفوعة الي';
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($pp) ? $pp : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php $header_title = 'جميع الطلبات المرفوعة الي: صندوق موحد من كل الادارات والفعل عند فاعله'; $header_icon = 'fa fa-inbox'; $header_actions = array();
    $header_back = array('href' => 'ceo_board.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'لوحة الرئيس');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> جميع الطلبات المرفوعة بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'Request_ID' => 'g108',
            'Source_Department' => 'g109',
            'Source_Screen' => 'g110',
            'Request_Type' => 'g111',
            'Request_Title' => 'g112',
            'Requested_By' => 'g113',
            'Requested_Date' => 'g114',
            'Amount' => 'g115',
            'Currency' => 'g116',
            'Project' => 'g117',
            'Priority' => 'g118',
            'Risk_Level' => 'g119',
            'Previous_Approvals' => 'g120',
            'سقف الإدارة المصدر' => 'g121',
            'مقدار التجاوز عن السقف' => 'g122',
            'سبب الرفع للأعلى' => 'g123',
            'Current_Approval_Level' => 'g124',
            'Required_By' => 'g125',
            'Supporting_Documents' => 'g126',
            'Recommendation' => 'g127',
            'CEO_Decision' => 'g128',
            'Decision_Conditions' => 'g129',
            'Decision_Date' => 'g130',
            'المنشئ' => 'g131',
            'تاريخ الإنشاء' => 'g132',
            'حالة البيانات' => 'g133',
            'مرجع المصدر' => 'g134',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('exec_request_queue');
        echo ems_w14_grid('emsList_exec_request_queue', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في جميع الطلبات المرفوعة'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php  ?>

    <div class="ems-stat-cards">
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($rows)) ?></div><div class="ems-stat-label">الوارد الموحد كله</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format($nOpen) ?></div><div class="ems-stat-label">بانتظار فعل الرئيس</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value">4</div><div class="ems-stat-label">روافد مسماة</div></div>
        <div class="ems-stat-card"><div class="ems-stat-value"><?= number_format(count($srcSet)) ?></div><div class="ems-stat-label">جهات مصدرة</div></div>
    </div>

    <div class="table-container">
        <table class="ems-data-table">
            <thead>
                <tr><th>نوع المرفوع</th><th>الجهة المصدر</th><th>المرجع</th><th>البيان</th><th>الحالة</th><th>التاريخ</th><th>فعله عند فاعله</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $x0): ?>
                <tr>
                    <td><?= htmlspecialchars($x0['kind']) ?></td>
                    <td><?= htmlspecialchars($x0['src']) ?></td>
                    <td><?= htmlspecialchars($x0['ref']) ?></td>
                    <td><?= htmlspecialchars($x0['desc']) ?></td>
                    <td><?= htmlspecialchars($x0['state']) ?></td>
                    <td><?= htmlspecialchars((string) $x0['at']) ?></td>
                    <td><a href="<?= htmlspecialchars($x0['link']) ?>"><?= htmlspecialchars($x0['label']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7">لا وارد من الروافد الاربعة بعد</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="ems-note-box">
        صندوق موحد لا شاشة معاملات جديدة، نص الحبة نفسه: كل صف يسمي جهته المصدر وفاعل فعله،
        والمعلق يتقدم الصفوف. الروافد الاربعة اعتمادات الرئيس والعقود المعروضة للتوقيع
        وملاحظات المراجعة المصعدة وتصعيدات المخاطر. لا كتابة من هذه الشاشة.
    </div>
</div>
<?php include '../infooter.php'; ?>
