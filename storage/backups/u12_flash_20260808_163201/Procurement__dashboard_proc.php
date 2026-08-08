<?php
/**
 * Procurement/dashboard_proc.php — لوحة المشتريات والمؤشرات (عرض فقط) — §16.
 * بطاقات إحصائية + جدول القطع الحرجة. كلها مقيّدة بالشركة، قراءة فقط.
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
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/dashboard_proc.php', $is_super_admin);
if (!$perms['can_view']) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+لوحة+المشتريات+❌");
    exit();
}

// K9-M1: العدّادات عبر البوابة (العزل والحذف الناعم مسؤوليتها؛ العربية بمعاملات محضّرة)
$g = proc_gate($is_super_admin);
$k_items    = $g->count('proc_item');
$k_critical = $g->count('proc_item', array('where' => array('is_critical' => 1)));
$k_req_open = $g->count('proc_request', array('whereRaw' => 'state NOT IN (?, ?)', 'params' => array('مغلق', 'مرفوض')));
$k_po_conf  = $g->count('proc_order', array('where' => array('state' => 'مؤكَّد')));
$k_rc_open  = $g->count('proc_receipt_custody', array('whereRaw' => 'state <> ?', 'params' => array('مسلَّمة للوجهة')));
$k_issues   = $g->count('proc_issue');
$k_suppliers= $g->count('proc_supplier');

$cards = array(
    array('label' => 'الأصناف',                 'value' => $k_items,    'icon' => 'fa fa-boxes-stacked',       'href' => 'items_proc.php'),
    array('label' => 'القطع الحرجة',            'value' => $k_critical, 'icon' => 'fa fa-triangle-exclamation','href' => 'items_proc.php'),
    array('label' => 'طلبات شراء مفتوحة',       'value' => $k_req_open, 'icon' => 'fa fa-file-lines',          'href' => 'requests_proc.php'),
    array('label' => 'أوامر شراء مؤكَّدة',       'value' => $k_po_conf,  'icon' => 'fa fa-file-invoice-dollar', 'href' => 'orders_proc.php'),
    array('label' => 'عهد استلام مفتوحة',       'value' => $k_rc_open,  'icon' => 'fa fa-truck-ramp-box',      'href' => 'receipt_custody_proc.php'),
    array('label' => 'عمليات الصرف',            'value' => $k_issues,   'icon' => 'fa fa-hand-holding-box',    'href' => 'issue_proc.php'),
    array('label' => 'الموردون التشغيليون',     'value' => $k_suppliers,'icon' => 'fa fa-truck-field',         'href' => 'suppliers_proc.php'),
);

/* ── لوحة الدور بالمكوّنات السبعة (UX-00 §7 · UX-01 §8.10) ──────────────────
   البطاقات أعلاه = المكوّن ① «مؤشرات اليوم» (قائمةٌ من قبل — تبقى ويُضاف
   الناقص بقرار المالك). ②-⑦ عبر المحرك الموحّد والقالب المشترك. */
require_once __DIR__ . '/../includes/role_board.php';
require_once __DIR__ . '/../includes/finreq_badges.php';

$rb_uid       = intval($ctx['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$rb_badges    = ems_finreq_nav_badges($conn);
$rb_tasks     = roleBoardTasks($conn, $g, 16);
$rb_approvals = roleBoardApprovals($conn, $g, 16, $rb_badges);
$rb_alerts    = roleBoardAlerts($conn, $g, 16);
$rb_quick     = roleBoardQuickActions($conn, 16, $rb_uid);
$rb_recent    = roleBoardRecent($conn, $rb_uid);

// ⑥ نبض الأداء — طلباتُ شراءٍ وردت مقابل صرفياتٍ نُفِّذت (7 أيام)
$rb_pulse = array('labels' => array(), 'in' => array(), 'out' => array());
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-{$d} days"));
    $rb_pulse['labels'][] = date('m/d', strtotime($day));
    $rb_pulse['in'][]  = roleBoardScalar($g, array('scope' => array('r' => 'proc_request')),
        "SELECT COUNT(*) FROM proc_request r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND DATE(r.created_at)=?", array($day));
    $rb_pulse['out'][] = roleBoardScalar($g, array('scope' => array('i' => 'proc_issue')),
        "SELECT COUNT(*) FROM proc_issue i WHERE {TENANT_SCOPE} AND COALESCE(i.is_deleted,0)=0 AND DATE(i.created_at)=?", array($day));
}
$rb_pulse_title  = 'نبض الأداء — طلباتٌ واردة مقابل صرفيات (7 أيام)';
$rb_pulse_series = array('طلبات واردة', 'صرفيات');

$page_title = 'إيكوبيشن | لوحة المشتريات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main proc-dashboard-main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة المشتريات والمؤشرات';
    $header_icon  = 'fa fa-gauge-high';
    $header_actions = array();
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء التفاصيل', 'icon' => 'fas fa-eye', 'label' => 'إظهار التفاصيل', 'label_class' => 'proc-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <div class="stats-section proc-hidden" id="procStatsSection">
        <div class="stats-grid">
            <?php foreach ($cards as $c): ?>
                <a href="<?php echo htmlspecialchars($c['href']); ?>" class="stats-card" title="<?php echo htmlspecialchars($c['label']); ?>">
                    <div class="stats-icon"><i class="<?php echo htmlspecialchars($c['icon']); ?>"></i></div>
                    <div class="stats-value"><?php echo intval($c['value']); ?></div>
                    <div class="stats-title"><?php echo htmlspecialchars($c['label']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // ③ نزيفُ التوقف: معداتٌ أوامرُ صيانتها المفتوحةُ بقطعٍ لم يكتمل شراؤها بعد
    require_once __DIR__ . '/../app/Services/Procurement/MntProcBridgeService.php';
    $waiting_eq = \App\Services\Procurement\MntProcBridgeService::waitingEquipment($conn, $company_id, 8);
    if ($waiting_eq): ?>
    <div class="card" style="margin-bottom:14px;border-inline-start:4px solid #b3261e">
        <div class="card-header"><h5><i class="fas fa-triangle-exclamation" style="color:#b3261e"></i>
            معداتٌ بانتظار قطعٍ (<?php echo count($waiting_eq); ?>) — كلُّ يومٍ هنا إيرادُ تأجيرٍ ضائع</h5></div>
        <div class="card-body"><div class="table-container">
            <table class="alltables display" data-no-dt="1" style="width:100%">
                <thead><tr><th>المعدة</th><th>أمر الصيانة</th><th>الانتظار (يوم)</th><th>طلب الشراء</th><th>حالته</th><th>الأولوية</th></tr></thead>
                <tbody>
                <?php foreach ($waiting_eq as $we): ?>
                    <tr<?php echo intval($we['waiting_days']) > 7 ? ' style="background:#fff1f0"' : ''; ?>>
                        <td><?php echo htmlspecialchars((string)($we['equipment_name'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string)$we['mnt_code'] . ' (' . $we['mnt_state'] . ')'); ?></td>
                        <td><strong><?php echo intval($we['waiting_days']); ?></strong></td>
                        <td><?php echo $we['req_code']
                            ? '<a href="requests_proc.php">' . htmlspecialchars((string)$we['req_code']) . '</a>'
                            : '<span style="color:#b3261e;font-weight:700">بلا طلبٍ بعد — ولّده من شاشة الطلبات</span>'; ?></td>
                        <td><?php echo htmlspecialchars((string)($we['req_state'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string)($we['priority'] ?? '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/../includes/role_board_widgets.php'; ?>

    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label><i class="fa fa-layer-group"></i> الفئة</label>
                <select id="filterCategory" class="form-control">
                    <option value="">-- كل الفئات --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-magnifying-glass"></i> بحث بالكود / الصنف</label>
                <input type="text" id="filterSearch" class="form-control" placeholder="اكتب للبحث...">
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-ok" id="filterApply"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" id="filterReset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="card-header"><h5><i class="fa fa-triangle-exclamation"></i> القطع الحرجة</h5></div>
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الكود</th><th>الصنف</th><th>الفئة</th><th>الحد الأدنى</th><th>مخزون الأمان</th><th>مدة التوريد (يوم)</th>
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
                    <?php
                    $critical_rows = $g->select('proc_item', array(
                        'columns' => array('code', 'name', 'category', 'min_qty', 'safety_stock', 'lead_time_days'),
                        'where'   => array('is_critical' => 1),
                        'orderBy' => 'name ASC',
                    ));
                    { foreach ($critical_rows as $row) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['category'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['min_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['safety_stock']) . "</td>";
                        echo "<td>" . intval($row['lead_time_days']) . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<style>
    /* بطاقات الإحصائيات — نفس تصميم شاشة العملاء (Clients/clients.php) */
    .proc-dashboard-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .proc-dashboard-main .proc-hidden { display: none; }
    .proc-dashboard-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .proc-dashboard-main .stats-card {
        background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px;
        box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden;
        text-decoration: none; color: inherit; display: block; transition: all .2s ease;
    }
    .proc-dashboard-main .stats-card:hover { border-color: #E0AE2E; box-shadow: 0 4px 14px rgba(26,18,8,.14); transform: translateY(-2px); }
    .proc-dashboard-main .stats-card .stats-icon {
        width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background: #fff; color: #000;
    }
    .proc-dashboard-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .proc-dashboard-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 35px; }
    @media (max-width: 900px) { .proc-dashboard-main .stats-grid { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
    @media (max-width: 560px) { .proc-dashboard-main .stats-grid { grid-template-columns: 1fr; } }
</style>

<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    var procTable = $('#procTable').DataTable({
        scrollX: true, autoWidth: false, stateSave: false,
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
    });

    // تعبئة خيارات الفلتر من بيانات العمود (نفس أسلوب شاشة العملاء)
    function fillFilterOptions(columnIndex, selectId) {
        var select = $(selectId);
        var current = select.val();
        var values = [];
        procTable.column(columnIndex).data().each(function (value) {
            var text = $('<div>').html(value).text().trim();
            if (text !== '' && values.indexOf(text) === -1) values.push(text);
        });
        values.sort();
        values.forEach(function (val) {
            select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
        });
        if (current) select.val(current);
    }
    fillFilterOptions(2, '#filterCategory'); // عمود الفئة

    // فلترة حسب الفئة (بحث عمود مطابق تام)
    $('#filterCategory').on('change', function () {
        var value = $.fn.dataTable.util.escapeRegex($(this).val());
        procTable.column(2).search(value ? '^' + value + '$' : '', true, false).draw();
    });

    // بحث عام بالكود/الصنف
    $('#filterSearch').on('keyup', function () {
        procTable.search($(this).val()).draw();
    });

    // زر «تطبيق» — يعيد تطبيق الفلاتر الحالية
    $('#filterApply').on('click', function () {
        var value = $.fn.dataTable.util.escapeRegex($('#filterCategory').val());
        procTable.column(2).search(value ? '^' + value + '$' : '', true, false);
        procTable.search($('#filterSearch').val()).draw();
    });

    // زر «إعادة تعيين»
    $('#filterReset').on('click', function () {
        $('#filterCategory').val('');
        $('#filterSearch').val('');
        procTable.search('').columns().search('').draw();
    });

    // ── إظهار/إخفاء التفاصيل (بطاقات المؤشرات) — بنفس آلية شاشة العملاء ──
    var statsToggleBtn = $('#toggleStats');
    var statsSection   = $('#procStatsSection');

    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.attr('aria-expanded', isVisible ? 'true' : 'false');
        statsToggleBtn.find('.proc-toggle-stats-text').text(isVisible ? 'إخفاء التفاصيل' : 'إظهار التفاصيل');
        var icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    updateStatsToggleState(statsSection.is(':visible'));

    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () {
                statsSection.addClass('proc-hidden');
                updateStatsToggleState(false);
            });
        } else {
            statsSection.removeClass('proc-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () {
                updateStatsToggleState(true);
            });
        }
    });
});
</script>
</body>
</html>
