<?php
/**
 * Tickets/ticket_dashboard.php — لوحة متابعة البلاغات.
 *
 * شاشةٌ مستقلّةٌ للقراءة فقط. لا تنشئ بياناتٍ جديدة — تقرأ ما تولّده الوحدة
 * أصلًا وتحوّله إلى معرفةٍ إدارية:
 *   ① الإنجاز والاستجابة  — من طوابع أول إجراءٍ والإغلاق ومواعيد الاستحقاق
 *   ② التأخير والاختناقات — زمن الاحتجاز لكل إدارة من سجلّ التحويلات
 *   ③ الأثر على الإنتاج والسلامة
 *   ④ تقارير الترتيب (الأسرع استجابةً وإنجازًا)
 *   ⑤ التوزيع والتكرار
 *
 * النطاق: نفس قاعدة الرؤية المطبَّقة في القائمة، تُحقن كشرطٍ في كل استعلام.
 * الفلترة الزمنية على تاريخ البلاغ (افتراضيًّا آخر 90 يومًا).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
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
    ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit();
}
$perms = tkt_page_perms($conn, 'Tickets/ticket_dashboard.php', $is_super_admin);
if (!$perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض لوحة برج المراقبة ❌', 'GOV-PERM-403', ''); exit();
}

$stages_map = tkt_stages();
$natures    = tkt_natures();
$impacts    = tkt_impacts();
$roles_map  = tkt_roles_map();
$gate       = tkt_gate($is_super_admin);

// ── الفلترة الزمنية (على تاريخ البلاغ) ──
$from = isset($_GET['from']) && $_GET['from'] !== '' ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-d', strtotime('-90 days'));
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-d');
$period_sql = " AND t.call_date BETWEEN '" . $from . "' AND '" . $to . "'";

// ── شرط نطاق الرؤية — مطابقٌ لقاعدة القائمة حرفيًّا ──
$scope_sql = '';
if (!$is_super_admin && !$is_tickets_mgr) {
    $vis = implode(',', array_map('intval', tkt_visible_owner_role_ids($current_role_id)));
    $uid = intval($current_user_id);
    $scope_sql = " AND (t.owner_role_id IN ($vis) OR t.reporter_user_id = $uid OR t.created_by = $uid)";
}
$W = $period_sql . $scope_sql;   // لاحقةُ كل استعلامٍ بعد {TENANT_SCOPE}

/** صفٌّ واحدٌ من scopedQuery على tickets (مع الإثراءات عند الحاجة). */
function tkt_dash_rows($gate, $sql, array $enrich = array()) {
    $decl = array('scope' => array('t' => 'tickets'));
    if (!empty($enrich)) { $decl['enrich'] = $enrich; }
    return $gate->scopedQuery($decl, $sql);
}
function tkt_dash_one($gate, $sql, $default = 0) {
    $rows = tkt_dash_rows($gate, $sql);
    if ($rows && isset($rows[0])) { $v = array_values($rows[0]); return isset($v[0]) ? $v[0] : $default; }
    return $default;
}

// ══ ① الإنجاز والاستجابة ═════════════════════════════════════════════════
$total_tickets = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W");
$open_tickets  = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W AND t.stage NOT IN ('closed','cancelled')");
$overdue_now   = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W AND t.resolution_due_at IS NOT NULL AND t.resolution_due_at < NOW() AND t.stage NOT IN ('done','closed','cancelled')");
$prod_critical = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W AND t.business_impact = 'production_critical' AND t.stage NOT IN ('closed','cancelled')");
$safety_open   = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W AND t.business_impact = 'safety' AND t.stage NOT IN ('closed','cancelled')");

// متوسّط الاستجابة (إنشاء ← أول إجراء) والإنجاز (إنشاء ← إغلاق) بالساعات
$avg_response = tkt_dash_one($gate, "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.first_action_at))/60, 1)
    FROM tickets t WHERE {TENANT_SCOPE}$W AND t.first_action_at IS NOT NULL", null);
$avg_resolution = tkt_dash_one($gate, "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.created_at, CONCAT(t.close_date,' ',COALESCE(NULLIF(t.close_time,''),'00:00'))))/60, 1)
    FROM tickets t WHERE {TENANT_SCOPE}$W AND t.close_date IS NOT NULL", null);

// نسبة الإغلاق في الموعد
$closed_with_due = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W AND t.close_date IS NOT NULL AND t.resolution_due_at IS NOT NULL");
$closed_ontime   = (int) tkt_dash_one($gate, "SELECT COUNT(*) FROM tickets t WHERE {TENANT_SCOPE}$W AND t.close_date IS NOT NULL AND t.resolution_due_at IS NOT NULL
    AND CONCAT(t.close_date,' ',COALESCE(NULLIF(t.close_time,''),'00:00')) <= t.resolution_due_at");
$ontime_pct = $closed_with_due > 0 ? round($closed_ontime * 100.0 / $closed_with_due) : null;

// ══ ② الاختناقات — زمن الاحتجاز لكل إدارة (من سجلّ التحويلات) ════════════
// لكل تحويل: المدّة التي قضتها التذكرة لدى الإدارة المُحوِّلة (from_role) =
// من التحويل السابق (أو إنشاء التذكرة) حتى هذا التحويل.
$bottlenecks = $gate->scopedQuery(
    array('scope' => array('t' => 'tickets'), 'enrich' => array('tr' => 'ticket_transfers')),
    "SELECT tr.from_role_id AS role_id, COUNT(*) AS moves,
            ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.created_at, tr.transfer_datetime))/1440, 1) AS avg_days
     FROM tickets t
     LEFT JOIN ticket_transfers tr ON tr.ticket_id = t.id
     WHERE {TENANT_SCOPE}$W AND tr.id IS NOT NULL
     GROUP BY tr.from_role_id
     ORDER BY avg_days DESC");

// الإدارات المالكة حاليًّا: كم تذكرةً مفتوحةً لدى كلٍّ ومنذ متى (اختناقٌ حيّ)
$holding = tkt_dash_rows($gate,
    "SELECT t.owner_role_id AS role_id, COUNT(*) AS open_cnt,
            ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.created_at, NOW()))/1440, 1) AS avg_age_days,
            SUM(CASE WHEN t.resolution_due_at IS NOT NULL AND t.resolution_due_at < NOW() THEN 1 ELSE 0 END) AS late_cnt
     FROM tickets t
     WHERE {TENANT_SCOPE}$W AND t.stage NOT IN ('closed','cancelled')
     GROUP BY t.owner_role_id
     ORDER BY late_cnt DESC, avg_age_days DESC");

// ══ ⑤ التوزيع: حسب المرحلة · النوع · الطبيعة ═════════════════════════════
$by_stage = array();
foreach (array_keys($stages_map) as $s) { $by_stage[$s] = 0; }
foreach (tkt_dash_rows($gate, "SELECT t.stage, COUNT(*) c FROM tickets t WHERE {TENANT_SCOPE}$W GROUP BY t.stage") as $r) {
    $by_stage[$r['stage']] = (int) $r['c'];
}
$by_type = $gate->scopedQuery(
    array('scope' => array('t' => 'tickets'), 'enrich' => array('tt' => 'ticket_types')),
    "SELECT COALESCE(tt.name,'— بلا نوع —') AS label, COUNT(*) AS c
     FROM tickets t LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
     WHERE {TENANT_SCOPE}$W GROUP BY t.ticket_type_id ORDER BY c DESC LIMIT 10");
$max_type = 0;
foreach ($by_type as $x) { if ((int)$x['c'] > $max_type) { $max_type = (int)$x['c']; } }

// ══ ④ تقارير الترتيب «مَن الأسرع؟» ═══════════════════════════════════════
// أسرع إدارةٍ استجابةً (متوسّط إنشاء ← أول إجراء)
$fastest_dept = tkt_dash_rows($gate,
    "SELECT t.owner_role_id AS role_id, COUNT(*) AS n,
            ROUND(AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.first_action_at))/60, 1) AS avg_h
     FROM tickets t WHERE {TENANT_SCOPE}$W AND t.first_action_at IS NOT NULL
     GROUP BY t.owner_role_id ORDER BY avg_h ASC LIMIT 8");

// أعلى التزامًا بالاستحقاق (نسبة الإغلاق في الموعد لكل إدارة)
$best_compliance = tkt_dash_rows($gate,
    "SELECT t.owner_role_id AS role_id, COUNT(*) AS closed_n,
            SUM(CASE WHEN CONCAT(t.close_date,' ',COALESCE(NULLIF(t.close_time,''),'00:00')) <= t.resolution_due_at THEN 1 ELSE 0 END) AS ontime_n
     FROM tickets t WHERE {TENANT_SCOPE}$W AND t.close_date IS NOT NULL AND t.resolution_due_at IS NOT NULL
     GROUP BY t.owner_role_id ORDER BY closed_n DESC LIMIT 8");

// أكثر المعدّات بلاغًا
$top_equipment = $gate->scopedQuery(
    array('scope' => array('t' => 'tickets'), 'enrich' => array('e' => 'equipments')),
    "SELECT COALESCE(e.code, CONCAT('#', t.equipment_id)) AS label, COUNT(*) AS n,
            SUM(CASE WHEN t.stage NOT IN ('closed','cancelled') THEN 1 ELSE 0 END) AS open_n
     FROM tickets t LEFT JOIN equipments e ON e.id = t.equipment_id
     WHERE {TENANT_SCOPE}$W AND t.equipment_id IS NOT NULL
     GROUP BY t.equipment_id ORDER BY n DESC LIMIT 8");

// أكثر المُبلِّغين نشاطًا
$top_reporters = tkt_dash_rows($gate,
    "SELECT t.reporting_person AS label, COUNT(*) AS n
     FROM tickets t WHERE {TENANT_SCOPE}$W
     GROUP BY t.reporting_person ORDER BY n DESC LIMIT 8");

$page_title = 'إيكوبيشن | أداء البلاغات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

$stage_colors = array(
    'new' => '#6c757d', 'classified' => '#6610f2', 'routed' => '#0d6efd',
    'in_progress' => '#fd7e14', 'waiting' => '#b58900', 'follow_up' => '#0dcaf0',
    'done' => '#198754', 'closed' => '#212529', 'cancelled' => '#dc3545',
);
?>
<div class="main tkt-dash-main ems-unified-page-shell">
    <?php
    $header_title = 'أداء البلاغات';
    $header_icon  = 'fa fa-gauge-high';
    $header_actions = array();
    // ── تصدير سجل التحويلات (تصديرٌ فقط — مادة تحليل الاختناقات في إكسل) ──
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('ticket_transfers', 'سجل التحويلات', false, array('exportOnly' => true)) as $__xlAction) {
        $header_actions[] = $__xlAction;
    }
    $header_back = array(
        array('href' => 'tickets_list.php', 'class' => '', 'icon' => 'fas fa-list', 'label' => 'قائمة البلاغات'),
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>
    <?php tkt_msg_banner(); ?>

    <!-- ═══ الفلترة الزمنية ═══ -->
    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-calendar-days"></i></span> المدى الزمني (تاريخ البلاغ)</div>
        <div class="filter-body">
            <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;width:100%">
                <div class="filter-field"><label>من</label><input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>"></div>
                <div class="filter-field"><label>إلى</label><input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>"></div>
                <div class="filter-actions">
                    <button type="submit" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                    <a href="ticket_dashboard.php" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ ① بطاقات المؤشرات ═══ -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px">
        <?php
        $kpis = array(
            array('إجمالي البلاغات', $total_tickets, 'fa-inbox', '#0d6efd'),
            array('مفتوحة الآن', $open_tickets, 'fa-folder-open', '#fd7e14'),
            array('متأخّرة عن الاستحقاق', $overdue_now, 'fa-triangle-exclamation', '#dc3545'),
            array('حرِجة للإنتاج (مفتوحة)', $prod_critical, 'fa-bolt', '#b58900'),
            array('بلاغات سلامة مفتوحة', $safety_open, 'fa-helmet-safety', '#6f42c1'),
            array('متوسّط الاستجابة (ساعة)', ($avg_response === null ? '—' : $avg_response), 'fa-stopwatch', '#20c997'),
            array('متوسّط الإنجاز (ساعة)', ($avg_resolution === null ? '—' : $avg_resolution), 'fa-hourglass-end', '#0dcaf0'),
            array('الإغلاق في الموعد', ($ontime_pct === null ? '—' : $ontime_pct . '%'), 'fa-circle-check', '#198754'),
        );
        foreach ($kpis as $k): ?>
        <div class="card" style="border-top:3px solid <?php echo $k[3]; ?>"><div class="card-body" style="padding:14px">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="fa <?php echo $k[2]; ?>" style="font-size:22px;color:<?php echo $k[3]; ?>"></i>
                <div>
                    <div style="font-size:22px;font-weight:800;line-height:1"><?php echo htmlspecialchars((string)$k[1]); ?></div>
                    <div style="font-size:12px;color:#6c757d;margin-top:4px"><?php echo htmlspecialchars($k[0]); ?></div>
                </div>
            </div>
        </div></div>
        <?php endforeach; ?>
    </div>

    <!-- ═══ ② الاختناقات ═══ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
        <div class="card"><div class="card-body">
            <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-hourglass-half"></i> أين تقف البلاغات الآن؟ (اختناقٌ حيّ)</h5></div>
            <?php if (empty($holding)): ?><div style="color:#6c757d">لا بلاغات مفتوحة في المدى المحدّد.</div>
            <?php else: ?>
            <table class="alltables no-datatable" style="width:100%">
                <thead><tr><th>الإدارة المالكة</th><th>مفتوحة</th><th>متوسّط العمر (يوم)</th><th>متأخّرة</th></tr></thead>
                <tbody>
                <?php foreach ($holding as $h): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(tkt_label($roles_map, intval($h['role_id']))); ?></td>
                        <td><strong><?php echo (int)$h['open_cnt']; ?></strong></td>
                        <td><?php echo htmlspecialchars((string)$h['avg_age_days']); ?></td>
                        <td><?php echo ((int)$h['late_cnt'] > 0)
                            ? "<span class='action-btn' style='color:#fff;background:#c0392b;border-radius:12px;padding:2px 10px'>" . (int)$h['late_cnt'] . "</span>"
                            : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-right-left"></i> متوسّط زمن الاحتجاز قبل التحويل</h5></div>
            <?php if (empty($bottlenecks)): ?><div style="color:#6c757d">لا تحويلات مُقيَّدةٌ بعد.</div>
            <?php else: ?>
            <table class="alltables no-datatable" style="width:100%">
                <thead><tr><th>الإدارة</th><th>عدد التحويلات</th><th>متوسّط الاحتجاز (يوم)</th></tr></thead>
                <tbody>
                <?php foreach ($bottlenecks as $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(tkt_label($roles_map, intval($b['role_id']))); ?></td>
                        <td><?php echo (int)$b['moves']; ?></td>
                        <td><strong><?php echo htmlspecialchars((string)$b['avg_days']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div></div>
    </div>

    <!-- ═══ ⑤ التوزيع ═══ -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
        <div class="card"><div class="card-body">
            <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-flag"></i> التوزيع حسب المرحلة</h5></div>
            <?php foreach ($by_stage as $st => $cnt): if ($cnt === 0) { continue; }
                $pct = $total_tickets > 0 ? round($cnt * 100 / $total_tickets) : 0; ?>
                <div style="margin-bottom:9px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
                        <span><?php echo htmlspecialchars($stages_map[$st]); ?></span><strong><?php echo $cnt; ?></strong>
                    </div>
                    <div style="background:#eef1f4;border-radius:6px;height:10px;overflow:hidden">
                        <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo $stage_colors[$st]; ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-tags"></i> أكثر أنواع البلاغات تكرارًا</h5></div>
            <?php if (empty($by_type)): ?><div style="color:#6c757d">لا بيانات.</div>
            <?php else: foreach ($by_type as $ty): $w = $max_type > 0 ? round((int)$ty['c'] * 100 / $max_type) : 0; ?>
                <div style="margin-bottom:9px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
                        <span><?php echo htmlspecialchars($ty['label']); ?></span><strong><?php echo (int)$ty['c']; ?></strong>
                    </div>
                    <div style="background:#eef1f4;border-radius:6px;height:10px;overflow:hidden">
                        <div style="width:<?php echo $w; ?>%;height:100%;background:#0d6efd"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div></div>
    </div>

    <!-- ═══ ④ تقارير الترتيب ═══ -->
    <div class="card"><div class="card-body">
        <div class="card-header" style="border:none;padding:0 0 12px"><h5><i class="fa fa-ranking-star"></i> تقارير الترتيب — «مَن الأسرع؟»</h5></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">

            <div>
                <div style="font-weight:700;margin-bottom:6px;color:#20c997"><i class="fa fa-bolt"></i> أسرع إدارةٍ استجابةً</div>
                <table class="alltables no-datatable" style="width:100%">
                    <thead><tr><th>#</th><th>الإدارة</th><th>ساعة</th></tr></thead>
                    <tbody>
                    <?php if (empty($fastest_dept)): ?><tr><td colspan="3" style="color:#6c757d">لا بيانات</td></tr><?php endif; ?>
                    <?php foreach ($fastest_dept as $i => $f): ?>
                        <tr><td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars(tkt_label($roles_map, intval($f['role_id']))); ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$f['avg_h']); ?></strong></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div style="font-weight:700;margin-bottom:6px;color:#198754"><i class="fa fa-circle-check"></i> أعلى التزامًا بالاستحقاق</div>
                <table class="alltables no-datatable" style="width:100%">
                    <thead><tr><th>#</th><th>الإدارة</th><th>نسبة الالتزام</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">الفترة</th>
              <th class="ems-fn-th" data-fn="1">الملتزم بمهلة الاستجابة</th>
              <th class="ems-fn-th" data-fn="1">الملتزم بمهلة الإنجاز</th>
              <th class="ems-fn-th" data-fn="1">بلا استجابة</th>
              <th class="ems-fn-th" data-fn="1">متوسط زمن التعليق</th>
              <th class="ems-fn-th" data-fn="1">إعادة الفتح</th>
              <th class="ems-fn-th" data-fn="1">نسبة إعادة الفتح</th>
              <th class="ems-fn-th" data-fn="1">المغلق بلا أثر</th>
              <th class="ems-fn-th" data-fn="1">متوسط التأخير</th>
              <th class="ems-fn-th" data-fn="1">المستهدف</th>
              <th class="ems-fn-th" data-fn="1">الحكم</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              </tr></thead>
                    <tbody>
                    <?php if (empty($best_compliance)): ?><tr><td colspan="3" style="color:#6c757d">لا بيانات</td></tr><?php endif; ?>
                    <?php foreach ($best_compliance as $i => $b):
                        $p = (int)$b['closed_n'] > 0 ? round((int)$b['ontime_n'] * 100 / (int)$b['closed_n']) : 0; ?>
                        <tr><td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars(tkt_label($roles_map, intval($b['role_id']))); ?></td>
                            <td><strong><?php echo $p; ?>%</strong></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div style="font-weight:700;margin-bottom:6px;color:#dc3545"><i class="fa fa-truck-monster"></i> أكثر المعدّات بلاغًا</div>
                <table class="alltables no-datatable" style="width:100%">
                    <thead><tr><th>#</th><th>المعدة</th><th>البلاغات الواردة</th><th>مفتوح</th></tr></thead>
                    <tbody>
                    <?php if (empty($top_equipment)): ?><tr><td colspan="4" style="color:#6c757d">لا بيانات</td></tr><?php endif; ?>
                    <?php foreach ($top_equipment as $i => $e): ?>
                        <tr><td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($e['label']); ?></td>
                            <td><strong><?php echo (int)$e['n']; ?></strong></td>
                            <td><?php echo (int)$e['open_n']; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div style="font-weight:700;margin-bottom:6px;color:#6f42c1"><i class="fa fa-user-check"></i> أنشط المُبلِّغين</div>
                <table class="alltables no-datatable" style="width:100%">
                    <thead><tr><th>#</th><th>المُبلِّغ</th><th>البلاغات المتكررة</th></tr></thead>
                    <tbody>
                    <?php if (empty($top_reporters)): ?><tr><td colspan="3" style="color:#6c757d">لا بيانات</td></tr><?php endif; ?>
                    <?php foreach ($top_reporters as $i => $r): ?>
                        <tr><td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($r['label']); ?></td>
                            <td><strong><?php echo (int)$r['n']; ?></strong></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>

    <!-- ═══ التصدير: جدولٌ مسطّحٌ واحدٌ لكل مؤشرات اللوحة ═══ -->
    <div class="card"><div class="card-body">
        <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-file-excel"></i> تصدير مؤشرات اللوحة</h5></div>
        <div class="table-container">
            <table id="tktExportTable" class="display nowrap alltables no-datatable" style="width:100%">
                <thead><tr><th>المؤشّر</th><th>القيمة</th></tr></thead>
                <tbody>
                <?php
                $export = array(
                    'المدى الزمني' => $from . ' → ' . $to,
                    'إجمالي البلاغات' => $total_tickets,
                    'مفتوحة الآن' => $open_tickets,
                    'متأخّرة عن الاستحقاق' => $overdue_now,
                    'حرِجة للإنتاج (مفتوحة)' => $prod_critical,
                    'بلاغات سلامة مفتوحة' => $safety_open,
                    'متوسّط الاستجابة (ساعة)' => ($avg_response === null ? '—' : $avg_response),
                    'متوسّط الإنجاز (ساعة)' => ($avg_resolution === null ? '—' : $avg_resolution),
                    'الإغلاق في الموعد' => ($ontime_pct === null ? '—' : $ontime_pct . '%'),
                );
                foreach ($holding as $h) {
                    $export['مفتوحة لدى: ' . tkt_label($roles_map, intval($h['role_id']))] =
                        (int)$h['open_cnt'] . ' (متأخّر: ' . (int)$h['late_cnt'] . ')';
                }
                foreach ($export as $k => $v) {
                    echo '<tr><td>' . htmlspecialchars($k) . '</td><td>' . htmlspecialchars((string)$v) . '</td></tr>';
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
        $('#tktExportTable').DataTable({
            paging: false, searching: false, info: false, ordering: false,
            stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'excel', text: '📊 تصدير Excel', title: 'لوحة متابعة البلاغات' },
                { extend: 'print', text: '🖨️ طباعة', title: 'لوحة متابعة البلاغات' },
                { extend: 'copy',  text: '📋 نسخ' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });
    });
})();
</script>
</body>
</html>
