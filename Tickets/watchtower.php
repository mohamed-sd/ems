<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Tickets/watchtower.php — برج المراقبة: المؤشرات وتقرير المتأخرين
 * (update0004 · TKT-17 · TKT-01 §10/§11)
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once __DIR__ . '/../app/Services/Tickets/WatchTowerService.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Tickets\WatchTowerService as WT;

$current_role = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$company_id = ems_scope_company($conn);

$MODULE_CODE = 'Tickets/watchtower.php';
$can_view = $is_super_admin;
if (!$is_super_admin) {
    /* `RPR-03` §٦ — **المسارُ الواحد**: القرارُ من `check_page_permissions()`
           لا من استعلامٍ خاصٍّ بهذا الملفّ. **والفرقُ طبقةُ القوالب**
           (`GOV-AUTH-01`): القراءةُ الخامّةُ لا ترى القالبَ النافذَ، فتُخفى
           الشاشةُ من السايدبارِ وتُفتح بالرابطِ المباشر.
        ⛔ **وفرعُ السوبر أدمن أعلاه لم يُمَسّ** — والأسماءُ كما كانت. */
    $__perm = check_page_permissions($conn, $MODULE_CODE);
    $can_view = (bool) $__perm['can_view'];
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية لبرج المراقبة ❌', 'GOV-PERM-403', ''); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_report'])) {
    $r = WT::issuePeriodicReport($conn, $company_id, intval($_SESSION['user']['id'] ?? 0));
    ems_gov_flash_redirect('watchtower.php', $r['summary'] . ' — أصدر ✅', 'GOV-OK-200', '');
    exit();
}

$msg = strval($_GET['msg'] ?? '');
$ind = WT::indicators($conn, $company_id);
$late = WT::latenessReport($conn, $company_id);

$page_title = 'إيكوبيشن | برج المراقبة';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<style>
/* UXW-01 ②: أنماطُ برجِ المراقبةِ الثابتةُ — بادئةُ الشاشة tkt-wt- */
.tkt-wt-tiles        { display: flex; gap: 12px; flex-wrap: wrap; }
.tkt-wt-tile         { border: 1px solid var(--c-s-ddd); border-radius: 8px; padding: 10px 14px; min-width: 170px; flex: 1; }
.tkt-wt-tile-label   { color: var(--c-s-666); font-size: 12px; }
.tkt-wt-tile-value   { font-size: 22px; font-weight: bold; }
.tkt-wt-tile-target  { color: var(--c-s-888); }
.tkt-wt-inline-form  { display: inline; }
.tkt-wt-table        { width: 100%; }
.tkt-wt-noresponse   { color: var(--c-c0392b); font-weight: bold; }
</style>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'برج المراقبة — يقيس المركز ولا يعمل'; $header_icon = 'fa fa-broadcast-tower';
    $header_actions = array();
    include('../includes/page_header.php');
    ems_screen_about(
        'المؤشرات الثمانية (TKT-01 §11) وتقرير «من يتأخر ومن لا يستجيب» بالاسم والإدارة — '
        . 'المركز يرصد ويتواصل ويسجل، ولا يوجه ولا يصعد (النظام يفعل ذلك آليا).',
        array('التقرير الدوري يرفع لمدير التشغيل والإدارة العامة',
              'عدم الاستجابة مؤشر إهمال لا تأخير — مستهدفه صفر'));
    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا مؤشرات مراقبة في النافذة المقيسة', 'وسع نافذة القياس أو تحقق من تسجيل مسارات البلاغات');
    ?>

    <div class="card"><div class="card-header"><h5>المؤشرات الثمانية — نافذة <?php echo intval($ind['window_days']); ?> يوما
        (<?php echo intval($ind['workstreams_measured']); ?> مسارا)</h5></div>
    <div class="card-body tkt-wt-tiles">
        <?php
        $tiles = array(
            array('① الاستجابة داخل المهلة', $ind['①_response_compliance_pct'] . '%', $ind['①_target']),
            array('② الإنجاز داخل المهلة', $ind['②_resolve_compliance_pct'] . '%', $ind['②_target']),
            array('③ لم يستجب له إطلاقا', $ind['③_never_responded'], $ind['③_target']),
            array('④ متوسط زمن التعليق', $ind['④_avg_hold_minutes'] . ' دقيقة', 'ارتفاعه مخبأ للتأخير'),
            array('⑤ المعاد فتحها', $ind['⑤_reopen_pct'] . '%', $ind['⑤_target']),
            array('⑥ بلاغات التكرار', $ind['⑥_recurrence_tickets'], 'ترفع مشكلة لا حوادث'),
            array('⑦ مغلق بلا أثر', $ind['⑦_closed_without_effect'], $ind['⑦_target']),
            array('⑧ المتأخرون بالاسم', count($late), 'أساس التقرير الدوري'),
        );
        foreach ($tiles as $t) {
            echo '<div class="tkt-wt-tile">'
                . '<div class="tkt-wt-tile-label">' . htmlspecialchars($t[0]) . '</div>'
                . '<div class="tkt-wt-tile-value">' . htmlspecialchars((string) $t[1]) . '</div>'
                . '<small class="tkt-wt-tile-target">المستهدف: ' . htmlspecialchars((string) $t[2]) . '</small></div>';
        } ?>
    </div></div>

    <div class="card"><div class="card-header"><h5>⑧ من يتأخر ومن لا يستجيب — بالاسم والإدارة</h5>
        <form method="post" class="tkt-wt-inline-form">
        <?php echo csrf_field(); ?><button type="submit" name="issue_report" value="1" class="btn-primary">أصدر التقرير الدوري</button></form>
    </div>
    <div class="card-body">
        <?php if (!$late) { ems_state_empty('لا متأخر ولا غير مستجيب في النافذة — نظيف ✨'); } else { ?>
        <div class="table-container"><table class="alltables display nowrap tkt-wt-table" data-no-dt="1">
            <thead><tr><th>المكلف</th><th>الإدارة</th><th>مسند إليه</th><th>لم يستجب</th><th>استجاب متأخرا</th><th>متوسط التأخير (دقيقة)</th>
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
            <?php foreach ($late as $x): ?>
                <tr><td><?php echo htmlspecialchars($x['person_name'] ?: ('#' . $x['assignee_person_id'])); ?></td>
                    <td><?php echo htmlspecialchars($x['unit_name'] ?: '—'); ?></td>
                    <td><?php echo intval($x['assigned']); ?></td>
                    <td class="tkt-wt-noresponse"><?php echo intval($x['no_response']); ?></td>
                    <td><?php echo intval($x['late_response']); ?></td>
                    <td><?php echo intval($x['avg_delay_min']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php } ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
