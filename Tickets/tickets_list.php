<?php
/**
 * Tickets/tickets_list.php — قائمة البلاغات.
 *
 * نطاق الرؤية (يُحسب من شجرة الأدوار parent_role_id، لا من العمود role_scope):
 *   • المدير الأعلى: كل الشركات.
 *   • مدير البلاغات: كل بلاغات شركته.
 *   • أي دور آخر: البلاغات الموجَّهة إلى دوره أو أجداده أو ذرّيّته، إضافةً
 *     إلى ما أبلغ عنه بنفسه — عرضًا ومتابعةً فقط، فقيادةُ المراحل بيد فريق
 *     البلاغات.
 *
 * الوصول متاحٌ لكل مستخدم مسجَّل (كشاشة المراسلات)، والإنفاذ التفصيلي للنطاق
 * والأزرار داخل الشاشة نفسها.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx             = tkt_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];
$current_role_id = intval($ctx['role']);
$is_tickets_mgr  = ($ctx['role'] === EMS_ROLE_TICKETS_MGR);

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$stages_map = tkt_stages();
$natures    = tkt_natures();
$roles_map  = tkt_roles_map();

// شرط النطاق (يُلحق بعد {TENANT_SCOPE})
$scope_sql = '';
if (!$is_super_admin && !$is_tickets_mgr) {
    $visible = tkt_visible_owner_role_ids($current_role_id);
    $in = implode(',', array_map('intval', $visible));
    $uid = intval($current_user_id);
    $scope_sql = " AND (t.owner_role_id IN ($in) OR t.reporter_user_id = $uid OR t.created_by = $uid)";
}

// E-13 (UX-07 §5.1): **التبويباتُ الأربعة بدل الفلاتر المنسدلة وحدَها** —
// وتبويبُ «تنتظر اعتمادًا» (done) كان غائبًا؛ و«بلاغاتي» هو M-37 (UX-07 §4):
// المرشَّحُ على المستلم لكل الأدوار لا لدور 24 وحده.
$TABS = array(
    'open'     => array('مفتوحة', " AND t.stage NOT IN ('done','closed','cancelled')"),
    'approval' => array('تنتظر اعتمادًا', " AND t.stage = 'done'"),
    'mine'     => array('بلاغاتي', ' AND (t.reporter_user_id = ' . intval($current_user_id)
                       . ' OR t.assigned_user_id = ' . intval($current_user_id)
                       . ' OR t.created_by = ' . intval($current_user_id) . ')'),
    'closed'   => array('مغلقة', " AND t.stage IN ('closed','cancelled')"),
);
$tab = isset($_GET['tab']) && isset($TABS[$_GET['tab']]) ? strval($_GET['tab']) : 'open';
$scope_sql .= $TABS[$tab][1];

$page_title = 'إيكوبيشن | البلاغات';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main tkt-list-main ems-unified-page-shell">
    <?php
    $header_title = 'البلاغات';
    $header_icon  = 'fa fa-tower-observation';
    $header_actions = array();
    $header_actions[] = array('tag' => 'a', 'href' => 'ticket_form.php', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'بلاغ جديد');
    $header_actions[] = array('tag' => 'a', 'href' => 'ticket_dashboard.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-gauge-high', 'label' => 'لوحة المتابعة');
    if ($is_tickets_mgr || $is_super_admin) {
        $header_actions[] = array('tag' => 'a', 'href' => 'ticket_types_config.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-route', 'label' => 'الأنواع والتوجيه');
    }
    // ── نظام Excel الموحّد — نفس هوية العملاء/المشاريع (نموذج + تصدير + استيراد) ──
    // الاستيراد لفريق البلاغات حصرًا (can_add على الوحدة)، لا لكل مَن يُبلّغ.
    require_once __DIR__ . '/../includes/excel_ui.php';
    $__tkt_can_import = ($is_tickets_mgr || $is_super_admin);
    foreach (ems_excel_header_actions('tickets', 'البلاغات', $__tkt_can_import) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php tkt_msg_banner(); ?>

    <?php
    // لمحةٌ خفيفة باستعلام تجميعٍ واحدٍ رخيص — والتفصيل في لوحة المتابعة.
    $glance = tkt_gate($is_super_admin)->scopedQuery(
        array('scope' => array('t' => 'tickets')),
        "SELECT SUM(CASE WHEN t.stage NOT IN ('closed','cancelled') THEN 1 ELSE 0 END) AS open_cnt,
                SUM(CASE WHEN t.resolution_due_at IS NOT NULL AND t.resolution_due_at < NOW()
                          AND t.stage NOT IN ('done','closed','cancelled') THEN 1 ELSE 0 END) AS late_cnt,
                SUM(CASE WHEN t.business_impact = 'production_critical'
                          AND t.stage NOT IN ('closed','cancelled') THEN 1 ELSE 0 END) AS crit_cnt
         FROM tickets t WHERE {TENANT_SCOPE}" . $scope_sql);
    $g = !empty($glance) ? $glance[0] : array('open_cnt' => 0, 'late_cnt' => 0, 'crit_cnt' => 0);
    $glance_cards = array(
        array('مفتوحة الآن', (int)$g['open_cnt'], 'fa-folder-open', '#fd7e14'),
        array('متأخّرة', (int)$g['late_cnt'], 'fa-triangle-exclamation', '#dc3545'),
        array('حرِجة للإنتاج', (int)$g['crit_cnt'], 'fa-bolt', '#b58900'),
    );
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:14px">
        <?php foreach ($glance_cards as $c): ?>
            <a href="ticket_dashboard.php" style="text-decoration:none;color:inherit" title="افتح لوحة المتابعة">
                <div class="card" style="border-top:3px solid <?php echo $c[3]; ?>"><div class="card-body" style="padding:12px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <i class="fa <?php echo $c[2]; ?>" style="font-size:20px;color:<?php echo $c[3]; ?>"></i>
                        <div>
                            <div style="font-size:20px;font-weight:800;line-height:1"><?php echo $c[1]; ?></div>
                            <div style="font-size:12px;color:#6c757d;margin-top:3px"><?php echo htmlspecialchars($c[0]); ?></div>
                        </div>
                    </div>
                </div></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card"><div class="card-body" style="display:flex;gap:6px;flex-wrap:wrap">
        <?php // E-13: التبويباتُ الأربعة — كلٌّ بعدّاده الحي
        foreach ($TABS as $tk => $tv):
            $cnt_row = tkt_gate($is_super_admin)->scopedQuery(
                array('scope' => array('t' => 'tickets')),
                "SELECT COUNT(*) n FROM tickets t WHERE {TENANT_SCOPE}"
                . ($is_super_admin || $is_tickets_mgr ? '' :
                   " AND (t.owner_role_id IN (" . implode(',', array_map('intval', tkt_visible_owner_role_ids($current_role_id)))
                   . ") OR t.reporter_user_id = " . intval($current_user_id)
                   . " OR t.created_by = " . intval($current_user_id) . ")")
                . $tv[1]);
            $cnt = $cnt_row ? intval($cnt_row[0]['n']) : 0;
        ?>
            <a href="?tab=<?php echo $tk; ?>" class="btn btn-sm"
               style="border:1px solid #ddd;border-radius:8px;padding:6px 14px;text-decoration:none;<?php
                   echo $tk === $tab ? 'background:#e2b93b;font-weight:800' : ''; ?>">
                <?php echo htmlspecialchars($tv[0]); ?>
                <span class="badge badge-secondary"><?php echo $cnt; ?></span></a>
        <?php endforeach; ?>
    </div></div>

    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
            <div class="filter-field"><label><i class="fa fa-flag"></i> المرحلة</label>
                <select id="fStage" class="form-control"><option value="">-- الكل --</option>
                    <?php foreach ($stages_map as $k => $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-field"><label><i class="fa fa-building"></i> الإدارة المالكة</label>
                <select id="fOwner" class="form-control"><option value="">-- الكل --</option>
                    <?php foreach (tkt_owner_role_ids() as $rid): ?><option value="<?php echo htmlspecialchars(tkt_label($roles_map, $rid)); ?>"><?php echo htmlspecialchars(tkt_label($roles_map, $rid)); ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-field"><label><i class="fa fa-tag"></i> الطبيعة</label>
                <select id="fNature" class="form-control"><option value="">-- الكل --</option>
                    <?php foreach ($natures as $k => $v): ?><option value="<?php echo htmlspecialchars($v); ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                </select></div>
            <div class="filter-actions">
                <button type="button" class="btn-reset" id="fReset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="tktTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>فتح</th><th>رقم التذكرة</th><th>النوع</th><th>الطبيعة</th><th>المرحلة</th><th>الإدارة المالكة</th>
                    <th>المُبلِّغ</th><th>المعدة</th><th>المشروع</th><th>الوصف</th><th>تاريخ البلاغ</th><th>موعد الإنجاز</th><th>متأخّر</th>
                
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                <?php
                // scopedQuery: scope على tickets + إثراءات LEFT حصرًا (مراجع مرنة قد تغيب)
                $rows = tkt_gate($is_super_admin)->scopedQuery(
                    array('scope'  => array('t' => 'tickets'),
                          'enrich' => array('tt' => 'ticket_types', 'e' => 'equipments', 'p' => 'project')),
                    "SELECT t.id, t.ticket_no, t.stage, t.ticket_nature, t.owner_role_id,
                            t.reporting_person, t.complaint, t.call_date, t.call_time,
                            t.resolution_due_at,
                            tt.name AS type_name, e.code AS equipment_code, p.name AS project_name
                     FROM tickets t
                     LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                     LEFT JOIN equipments e ON e.id = t.equipment_id
                     LEFT JOIN project p ON p.id = t.project_id
                     WHERE {TENANT_SCOPE}" . $scope_sql . "
                     ORDER BY t.id DESC"
                );
                foreach ($rows as $row) {
                    $complaint_full  = (string)$row['complaint'];
                    $complaint_short = mb_strlen($complaint_full) > 60 ? (mb_substr($complaint_full, 0, 60) . '…') : $complaint_full;
                    echo "<tr>";
                    echo "<td><div class='action-btns'><a href='ticket_form.php?id=" . intval($row['id']) . "' class='action-btn edit' title='فتح التذكرة'><i class='fas fa-up-right-from-square'></i></a></div></td>";
                    echo "<td><strong>" . htmlspecialchars((string)$row['ticket_no']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars((string)($row['type_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars(tkt_label($natures, $row['ticket_nature'])) . "</td>";
                    echo "<td>" . tkt_stage_badge($row['stage']) . "</td>";
                    echo "<td>" . htmlspecialchars(tkt_label($roles_map, intval($row['owner_role_id']))) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['reporting_person']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['equipment_code'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
                    echo "<td title='" . htmlspecialchars($complaint_full, ENT_QUOTES) . "'>" . htmlspecialchars($complaint_short) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['call_date'] . ' ' . (string)($row['call_time'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['resolution_due_at'] ?? '—')) . "</td>";
                    echo "<td>" . tkt_overdue_badge($row) . "</td>";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script>
(function () {
    $(document).ready(function () {
        // stateSave:false إلزامي مع الفلاتر الخارجية، وإلا استُرجعت فلترةٌ
        // محفوظةٌ من جلسةٍ سابقة فيظهر الجدول فارغًا بلا سبب ظاهر.
        var dt = $('#tktTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        // فلترة أعمدة (بعد عمود «فتح»): الطبيعة(3) · المرحلة(4) · الإدارة(5)
        function esc(s) { return $.fn.dataTable.util.escapeRegex(String(s)); }
        $('#fStage').on('change', function () { var v = this.value; dt.column(4).search(v ? esc(v) : '', true, false).draw(); });
        $('#fOwner').on('change', function () { var v = this.value; dt.column(5).search(v ? '^' + esc(v) + '$' : '', true, false).draw(); });
        $('#fNature').on('change', function () { var v = this.value; dt.column(3).search(v ? '^' + esc(v) + '$' : '', true, false).draw(); });
        $('#fReset').on('click', function () {
            $('#fStage,#fOwner,#fNature').val('');
            dt.columns().search('').draw();
        });
    });
})();
</script>
<?php
// نافذة معالج الاستيراد الموحّد + أصوله (تُطبع مرّة واحدة — نمط العملاء)
if (function_exists('ems_excel_render')) {
    ems_excel_render('tickets');
}
?>
</body>
</html>
