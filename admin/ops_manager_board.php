<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * admin/ops_manager_board.php — لوحة مدير التشغيل (update0004 · ORG-18)
 * ───────────────────────────────────────────────────────────────────────────
 * ORG-01 §3.1: «مساحة قرار لا تقرير يُقرأ» — المجموعات السبع، وكل رقم معه
 * إجراؤه. و«ما ينتظر قراره **بالساعات لا بالعدد فقط**» (§8 القبول): كل بند
 * معلَّق بعمر انتظاره ساعاتٍ من ساعة القاعدة، ومجموع ساعات الانتظار ظاهر.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/tenant_scope.php';   // نطاقُ الكيانِ من السياقِ لا من رقمٍ صلب
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
$company_id = ems_scope_company($conn);

$MODULE_CODE = 'admin/ops_manager_board.php';
$can_view = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) { $can_view = intval($row['can_view']) === 1; }
    $st->close();
}
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا صلاحية للوحة مدير التشغيل ❌', 'GOV-PERM-403', ''); exit(); }

$q1 = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_assoc() : null; };
$qa = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : array(); };

// ① الأداء التشغيلي
$ops = $q1("SELECT COUNT(*) c, COALESCE(SUM(target_daily_hours),0) target_h,
                   SUM(op_state='تعمل') working, SUM(op_state='معطلة') broken
              FROM operations o JOIN project p ON p.id = o.project_id
             WHERE p.company_id = {$company_id} AND o.status = 1");

// ② خطط المواقع المرفوعة (draft → تنتظر اعتماده)
$plans = $qa("SELECT dp.id, dp.plan_date, dp.state, p.name project_name,
                     TIMESTAMPDIFF(HOUR, dp.created_at, NOW()) wait_h
                FROM daily_plans dp JOIN project p ON p.id = dp.project_id
               WHERE dp.company_id = {$company_id} AND dp.is_deleted = 0 AND dp.state = 'draft'
               ORDER BY dp.plan_date LIMIT 30");

// ③ الصيانة والجاهزية
$mnt = $q1("SELECT SUM(state NOT IN ('إغلاق','ملغى')) open_orders,
                   COALESCE(SUM(CASE WHEN state NOT IN ('إغلاق','ملغى') THEN downtime_hours END),0) downtime_h
              FROM mnt_order WHERE company_id = {$company_id}");

// ④ القوى التشغيلية
$ops4 = $q1("SELECT COUNT(*) c FROM equipment_drivers ed
              JOIN operations o ON o.id = ed.operation_id AND o.status = 1
              JOIN project p ON p.id = o.project_id
             WHERE p.company_id = {$company_id} AND ed.status = 1");
$ops4 = $ops4 ?: $q1("SELECT COUNT(*) c FROM equipment_drivers WHERE status = 1");

// ⑤ المشتريات والمخازن
$proc = $q1("SELECT COUNT(*) c FROM proc_order WHERE company_id = {$company_id} AND is_deleted = 0
              AND state NOT IN ('مغلق','ملغي')");

// ⑥ النقل والترحيل
$trs = $q1("SELECT COUNT(*) c FROM transfer_requests WHERE company_id = {$company_id}
             AND state = 'submitted'");

// ⑦ ما ينتظر قراره — **بالساعات لا بالعدد** من مصادره الحية
$pending = array();
foreach ($qa("SELECT CONCAT('خطة يومية — ', p.name, ' (', dp.plan_date, ')') label,
                     TIMESTAMPDIFF(HOUR, dp.created_at, NOW()) wait_h,
                     '../Operations/daily_plans.php' link
                FROM daily_plans dp JOIN project p ON p.id = dp.project_id
               WHERE dp.company_id = {$company_id} AND dp.is_deleted = 0 AND dp.state = 'draft'") as $x) { $pending[] = $x; }
foreach ($qa("SELECT CONCAT('إذن #', r.req_id, ' — ', t.name_ar) label,
                     TIMESTAMPDIFF(HOUR, r.created_at, NOW()) wait_h,
                     CONCAT('org_permits.php?id=', r.req_id) link
                FROM permit_requests r JOIN permit_types t ON t.permit_type_code = r.permit_type_code
               WHERE r.company_id = {$company_id} AND r.state = 'pending'") as $x) { $pending[] = $x; }
foreach ($qa("SELECT CONCAT('تكليف #', a.asg_id, ' (', t.name_ar, ') ينتهي ', a.valid_to) label,
                     TIMESTAMPDIFF(HOUR, NOW(), CONCAT(a.valid_to, ' 23:59:59')) * -1 + 0 wait_h,
                     'org_assignments.php' link
                FROM org_assignments a JOIN org_assignment_types t ON t.type_code = a.assignment_type_code
               WHERE a.company_id = {$company_id} AND a.state = 'active'
                 AND a.valid_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)") as $x) {
    $x['wait_h'] = 0; $x['label'] .= ' (خلال 30 يوما)';
    $pending[] = $x;
}
usort($pending, function ($a, $b) { return intval($b['wait_h']) - intval($a['wait_h']); });
$totalWaitH = 0;
foreach ($pending as $x) { $totalWaitH += max(0, intval($x['wait_h'])); }

$page_title = 'إيكوبيشن | لوحة مدير التشغيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';

/**
 * بطاقةُ المجموعة — بمكوّنِ بطاقةِ الإحصاءِ الموحَّد (`assets/css/ems-statcards.css`).
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ **الخللُ المُقاس**: كانت البطاقةُ تُرسَم بأصنافٍ محليّةٍ `ops-mb-tile*`
 *   وتصميمٍ في `<style>` داخلَ الصفحة. والاسمُ نفسُه كان الخلل: `ops-mb-tile-t`
 *   يطابق عائلةَ بطاقاتِ الإحصاءِ في `assets/js/ems-statcards.js`
 *   (`(^|-)(stat|stats|kpi|metric|metrics|tile)(-|$)`) عبر `-tile-`. فوُسم
 *   **كلُّ جزءٍ في البطاقةِ بطاقةً**، ووُسمت **البطاقةُ نفسُها شبكةً** — شبكةٌ
 *   بأربعةِ أعمدةٍ `minmax(170px, 1fr)` داخلَ صندوقٍ عرضُه 212px. فخرجت
 *   الأجزاءُ من حدودِها وتراكبت على جاراتِها: قِيس في المتصفّحِ طفلٌ عند
 *   x=-298 داخلَ بطاقةٍ تبدأ عند x=41، وثلاثةُ أطفالٍ عرضُ كلٍّ منهم 170px
 *   في صندوقٍ عرضُه 212. وهذا هو «التداخلُ» الظاهرُ أسفلَ الصفحة.
 * ◆ **والعلاجُ ليس ترقيعَ الاسم** — فأيُّ اسمٍ محليٍّ يعود يتفرَّق عن النظام —
 *   بل استعمالُ المكوّنِ الموحَّدِ بأسمائِه المعتمدة:
 *   `stats-section` ⇐ `stats-grid` ⇐ `stats-card` (أيقونةٌ · قيمةٌ · عنوانٌ · تابع).
 * ◆ **والبطاقةُ كلُّها رابطٌ** إلى موضعِ الفعل — والمكوّنُ يدعم ذلك صراحةً
 *   (`color:inherit` و`text-decoration:none` و`display:block` على البطاقة) —
 *   فبقي «كلُّ رقمٍ معه إجراؤه» (ORG-01 §3.1) بلا زرٍّ ذهبيٍّ يزاحم الرقم.
 */
function ems_board_tile($icon, $title, $value, $meta, $action, $link)
{
    echo '<a class="stats-card" href="' . htmlspecialchars($link) . '" title="' . htmlspecialchars($action) . '">'
        . '<div class="stats-icon"><i class="fa ' . htmlspecialchars($icon) . '"></i></div>'
        . '<div class="stats-value">' . htmlspecialchars(strval($value)) . '</div>'
        . '<div class="stats-title">' . htmlspecialchars($title) . '</div>'
        . ($meta !== '' ? '<div class="ems-statcard__meta">' . htmlspecialchars($meta) . '</div>' : '')
        . '<div class="ems-statcard__meta">' . htmlspecialchars($action) . ' ▸</div>'
        . '</a>';
}
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة مدير التشغيل — مساحة قرار'; $header_icon = 'fa fa-tachometer-alt';
    $header_actions = array();
    include('../includes/page_header.php');

    ems_screen_about(
        'المجموعات السبع (ORG-01 §3.1) — كل رقم معه إجراؤه، وما ينتظر قرارك '
        . 'معروض بساعات الانتظار لا بالعدد فقط: البند الأقدم انتظارا أولا.',
        array('ابدأ من «ما ينتظر قراره» — الأقدم ساعات أولا',
              'كل بطاقة بزر يقفز إلى موضع الفعل'));
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بند ينتظر قرارك الآن',
        'تظهر هنا خطط المواقع المرفوعة وأذونات العمل والتكليفات المنتهية حال رفعها');
    ?>

    <style>
    /* لم يبقَ من تصميمِ الصفحةِ المحليِّ إلا عرضُ الجدول. وبطاقاتُ المجموعاتِ
       صارت بمكوّنِ `assets/css/ems-statcards.css` الموحَّد — لا تصميمَ لها هنا،
       ولا اسمَ محليًّا يطابق عائلةَ البطاقاتِ فيَقلبَ البطاقةَ شبكةً. */
    .ops-mb-table { width:100%; }
    </style>

    <div class="card"><div class="card-header"><h5>⑦ ما ينتظر قراره —
        <strong><?php echo count($pending); ?></strong> بندا بمجموع انتظار
        <strong><?php echo $totalWaitH; ?> ساعة</strong> (بالساعات لا بالعدد)</h5></div>
    <div class="card-body">
        <?php if (!$pending) { ems_state_empty('لا شيء ينتظر قرارك — نظيف ✨'); } else { ?>
        <div class="table-container">
        <table class="alltables display nowrap ops-mb-table" data-no-dt="1">
            <thead><tr><th>البند</th><th>منتظرا منذ (ساعة)</th><th>الإجراء</th>
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
            <?php foreach (array_slice($pending, 0, 40) as $x): ?>
                <tr>
                    <td><?php echo htmlspecialchars($x['label']); ?></td>
                    <td><strong><?php echo max(0, intval($x['wait_h'])); ?></strong></td>
                    <!-- زرُّ الصفِّ بنمطِ الجداولِ الموحَّد (`.action-btn` في
                         `assets/css/ems-tables.css`): قرصٌ رماديٌّ محايدٌ بحدٍّ
                         وأيقونة. وكان `btn-primary` — ذهبيَّ العلامة — والذهبيُّ
                         نمطُ «الفعلِ الملتزِمِ في الشاشة» لا نمطُ فعلٍ داخلَ صفٍّ
                         يتكرَّر بعددِ الصفوف، فكان يزاحم بياناتِ الجدولِ لونًا. -->
                    <td><a class="action-btn" href="<?php echo htmlspecialchars($x['link']); ?>" title="إلى موضع الفعل"><i class="fa fa-arrow-left"></i> إلى موضع الفعل</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php } ?>
    </div></div>

    <!-- بطاقاتُ المجموعاتِ الستّ — القسمُ والشبكةُ والبطاقةُ بأسماءِ المكوّنِ
         الموحَّد. و`data-cols="3"` تُعلن عدَدَ الأعمدةِ على الحاويةِ نفسِها
         (⑨ في `ems-statcards.css`) فتخرج ستُّ بطاقاتٍ في صفَّين تامَّين بلا
         خانةٍ خاوية، ولا تنقض الشاشةُ الشبكةَ بـ`!important` خاصٍّ بها. -->
    <div class="stats-section">
    <div class="stats-grid ems-statgrid" data-cols="3">
        <?php
        ems_board_tile('fa-gauge-high', '① الأداء التشغيلي — تشغيلات نشطة',
            intval($ops['c'] ?? 0),
            'تعمل ' . intval($ops['working'] ?? 0) . ' · معطلة ' . intval($ops['broken'] ?? 0)
            . ' · هدف يومي ' . round(floatval($ops['target_h'] ?? 0)) . ' س',
            'توزيع الموارد', '../movement/movement_operations.php');
        ems_board_tile('fa-calendar-day', '② خطط المواقع المرفوعة',
            count($plans), '',
            'اعتماد أو إعادة', '../Operations/daily_plans.php');
        ems_board_tile('fa-screwdriver-wrench', '③ الصيانة — أوامر مفتوحة',
            intval($mnt['open_orders'] ?? 0),
            'توقف ' . round(floatval($mnt['downtime_h'] ?? 0)) . ' س',
            'رفع أولوية', '../Maintenance/orders.php');
        ems_board_tile('fa-users', '④ القوى — مشغلون معينون',
            intval($ops4['c'] ?? 0), '',
            'طلب نقل أو بديل', '../Oprators/oprators.php');
        ems_board_tile('fa-cart-shopping', '⑤ المشتريات — أوامر مفتوحة',
            intval($proc['c'] ?? 0), '',
            'أولوية صرف', '../Procurement/orders_proc.php');
        ems_board_tile('fa-truck', '⑥ النقل — طلبات مفتوحة',
            intval($trs['c'] ?? 0), '',
            'اعتماد تحرك', '../Transport/transfer_requests.php');
        ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
