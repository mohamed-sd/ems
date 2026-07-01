<?php
/**
 * Procurement/dashboard_proc.php — لوحة المشتريات والمؤشرات (عرض فقط) — §16.
 * بطاقات إحصائية + جدول القطع الحرجة. كلها مقيّدة بالشركة، قراءة فقط.
 */
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

/** عدّاد بسيط مقيّد بالشركة. */
function proc_count($conn, $sql)
{
    $n = 0;
    if ($res = mysqli_query($conn, $sql)) {
        if ($row = mysqli_fetch_assoc($res)) { $n = intval($row['c']); }
    }
    return $n;
}

$sc = proc_scope('company_id', $is_super_admin, $company_id);
$k_items    = proc_count($conn, "SELECT COUNT(*) c FROM proc_item WHERE $sc AND COALESCE(is_deleted,0)=0");
$k_critical = proc_count($conn, "SELECT COUNT(*) c FROM proc_item WHERE $sc AND COALESCE(is_deleted,0)=0 AND is_critical=1");
$k_req_open = proc_count($conn, "SELECT COUNT(*) c FROM proc_request WHERE $sc AND COALESCE(is_deleted,0)=0 AND state NOT IN ('مغلق','مرفوض')");
$k_po_conf  = proc_count($conn, "SELECT COUNT(*) c FROM proc_order WHERE $sc AND COALESCE(is_deleted,0)=0 AND state='مؤكَّد'");
$k_rc_open  = proc_count($conn, "SELECT COUNT(*) c FROM proc_receipt_custody WHERE $sc AND COALESCE(is_deleted,0)=0 AND state<>'مسلَّمة للوجهة'");
$k_issues   = proc_count($conn, "SELECT COUNT(*) c FROM proc_issue WHERE $sc AND COALESCE(is_deleted,0)=0");
$k_suppliers= proc_count($conn, "SELECT COUNT(*) c FROM proc_supplier WHERE $sc AND COALESCE(is_deleted,0)=0");

$cards = array(
    array('label' => 'الأصناف',                 'value' => $k_items,    'icon' => 'fa fa-boxes-stacked',       'href' => 'items.php'),
    array('label' => 'القطع الحرجة',            'value' => $k_critical, 'icon' => 'fa fa-triangle-exclamation','href' => 'items.php'),
    array('label' => 'طلبات شراء مفتوحة',       'value' => $k_req_open, 'icon' => 'fa fa-file-lines',          'href' => 'requests.php'),
    array('label' => 'أوامر شراء مؤكَّدة',       'value' => $k_po_conf,  'icon' => 'fa fa-file-invoice-dollar', 'href' => 'orders.php'),
    array('label' => 'عهد استلام مفتوحة',       'value' => $k_rc_open,  'icon' => 'fa fa-truck-ramp-box',      'href' => 'receipt_custody.php'),
    array('label' => 'عمليات الصرف',            'value' => $k_issues,   'icon' => 'fa fa-hand-holding-box',    'href' => 'issue.php'),
    array('label' => 'الموردون التشغيليون',     'value' => $k_suppliers,'icon' => 'fa fa-truck-field',         'href' => 'suppliers.php'),
);

$page_title = 'إيكوبيشن | لوحة المشتريات';
include '../inheader.php';
include '../insidebar.php';
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
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT code, name, category, min_qty, safety_stock, lead_time_days
                            FROM proc_item WHERE $sc AND COALESCE(is_deleted,0)=0 AND is_critical=1 ORDER BY name ASC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
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
