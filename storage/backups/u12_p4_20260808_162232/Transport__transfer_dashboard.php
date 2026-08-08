<?php
/**
 * Transport/transfer_dashboard.php — لوحة متابعة الرحلات — §11 / §4.
 * شاشة جديدة مستقلة (وحدة 113). قراءة فقط من الجداول المملوكة
 * (transfer_orders · transfer_cost_lines · trs_locations · project للاسم) — صفر كتابة، صفر مساس بأي جدول قائم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx = trs_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit();
}

$perms = trs_page_perms($conn, 'Transport/transfer_dashboard.php', $is_super_admin);
if (!$perms['can_view']) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+لوحة+الرحلات+❌"); exit(); }

$stages_ar = trs_stages();
$dir_map   = trs_directions();
$bearer_map = trs_bearers();

$trs_dash_gate = trs_gate($is_super_admin);

/** قيمة مفردة عبر scopedQuery (عقد §10) — بديل trs_scalar الخام. */
function trs_scoped_scalar($gate, $sql, array $params = array(), $default = 0) {
    $rows = $gate->scopedQuery(array('scope' => array('o' => 'transfer_orders')), $sql, $params);
    if ($rows && ($x = array_values($rows[0]))) { return $x[0]; }
    return $default;
}

// ── مؤشرات عليا — عدّادات عبر count والمجاميع عبر scopedQuery ──
$total_orders   = (int) $trs_dash_gate->count('transfer_orders');
$open_orders    = (int) $trs_dash_gate->count('transfer_orders', array('whereRaw' => "stage NOT IN('closed','cancelled')"));
$in_transit     = (int) $trs_dash_gate->count('transfer_orders', array('whereRaw' => "stage = 'in_transit'"));
$delayed        = (int) $trs_dash_gate->count('transfer_orders', array('whereRaw' => "planned_date IS NOT NULL AND planned_date < CURDATE() AND stage NOT IN('arrived','closed','cancelled')"));
$total_cost_usd = (float) trs_scoped_scalar($trs_dash_gate, "SELECT COALESCE(SUM(o.actual_cost_usd),0) FROM transfer_orders o WHERE {TENANT_SCOPE}");

// نسبة الوصول في الموعد (من الأوامر التي لها وصول فعلي وتاريخ مخطط)
$arr_total = (int) $trs_dash_gate->count('transfer_orders', array('whereRaw' => "arrival_datetime IS NOT NULL AND planned_date IS NOT NULL"));
$arr_ontime = (int) $trs_dash_gate->count('transfer_orders', array('whereRaw' => "arrival_datetime IS NOT NULL AND planned_date IS NOT NULL AND DATE(arrival_datetime) <= planned_date"));
$ontime_pct = $arr_total > 0 ? round($arr_ontime * 100.0 / $arr_total) : null;

// متوسّط زمن الترحيل (ساعات) للأوامر الواصلة
$avg_hours = trs_scoped_scalar($trs_dash_gate, "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, o.departure_datetime, o.arrival_datetime)),1)
    FROM transfer_orders o WHERE {TENANT_SCOPE} AND o.departure_datetime IS NOT NULL AND o.arrival_datetime IS NOT NULL", array(), null);

// ── عدّاد حسب المرحلة (للكانبان) ──
$stage_counts = array();
foreach (array_keys($stages_ar) as $st) { $stage_counts[$st] = 0; }
$stage_rows = $trs_dash_gate->scopedQuery(array('scope' => array('o' => 'transfer_orders')),
    "SELECT o.stage, COUNT(*) c FROM transfer_orders o WHERE {TENANT_SCOPE} GROUP BY o.stage");
foreach ($stage_rows as $x) { $stage_counts[$x['stage']] = (int)$x['c']; }

// ── تكلفة الترحيل لكل مشروع (أعلى 8) ──
$by_project = $trs_dash_gate->scopedQuery(
    array('scope' => array('o' => 'transfer_orders'), 'enrich' => array('p' => 'project')),
    "SELECT COALESCE(p.name,'— بلا مشروع —') AS pname, COUNT(*) AS orders, COALESCE(SUM(o.actual_cost_usd),0) AS cost
     FROM transfer_orders o LEFT JOIN project p ON p.id=o.project_id
     WHERE {TENANT_SCOPE} GROUP BY o.project_id ORDER BY cost DESC LIMIT 8");
$max_proj_cost = 0.0;
foreach ($by_project as $bp) { if ((float)$bp['cost'] > $max_proj_cost) { $max_proj_cost = (float)$bp['cost']; } }

// ── تكلفة حسب المتحمِّل ──
$by_bearer = array('client' => 0.0, 'company' => 0.0, 'new_client' => 0.0);
$bearer_rows = $trs_dash_gate->scopedQuery(array('scope' => array('o' => 'transfer_orders')),
    "SELECT o.cost_bearer, COALESCE(SUM(o.actual_cost_usd),0) AS cost
     FROM transfer_orders o WHERE {TENANT_SCOPE} AND o.cost_bearer IS NOT NULL GROUP BY o.cost_bearer");
foreach ($bearer_rows as $x) { $by_bearer[$x['cost_bearer']] = (float)$x['cost']; }

// ── الكانبان: آخر الأوامر لكل مرحلة نشِطة ──
$kanban_stages = array('request', 'planned', 'ready', 'in_transit', 'arrived');
$kanban = array();
foreach ($kanban_stages as $st) {
    $kanban[$st] = $trs_dash_gate->scopedQuery(
        array('scope' => array('o' => 'transfer_orders'),
              'enrich' => array('p' => 'project', 'tl' => 'trs_locations')),
        "SELECT o.id, o.order_no, o.direction, o.planned_date, o.actual_cost_usd,
                p.name AS pname, tl.name AS to_name,
                (o.planned_date IS NOT NULL AND o.planned_date < CURDATE()) AS late
         FROM transfer_orders o
         LEFT JOIN project p ON p.id=o.project_id
         LEFT JOIN trs_locations tl ON tl.id=o.to_location_id
         WHERE {TENANT_SCOPE} AND o.stage = ?
         ORDER BY o.id DESC LIMIT 12", array($st));
}

/* ── لوحة الدور بالمكوّنات السبعة (UX-00 §7 · UX-01 §8.12) ──────────────────
   البطاقات أعلاه = المكوّن ① «مؤشرات اليوم». ②-⑦ عبر المحرك والقالب المشترك. */
require_once __DIR__ . '/../includes/role_board.php';
require_once __DIR__ . '/../includes/finreq_badges.php';

$rb_uid       = intval($ctx['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
$rb_badges    = ems_finreq_nav_badges($conn);
$rb_tasks     = roleBoardTasks($conn, $trs_dash_gate, 23);
$rb_approvals = roleBoardApprovals($conn, $trs_dash_gate, 23, $rb_badges);
$rb_alerts    = roleBoardAlerts($conn, $trs_dash_gate, 23);
$rb_quick     = roleBoardQuickActions($conn, 23, $rb_uid);
$rb_recent    = roleBoardRecent($conn, $rb_uid);

// ⑥ نبض الأداء — رحلاتٌ انطلقت مقابل وصلت (7 أيام)
$rb_pulse = array('labels' => array(), 'in' => array(), 'out' => array());
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-{$d} days"));
    $rb_pulse['labels'][] = date('m/d', strtotime($day));
    $rb_pulse['in'][]  = (float) trs_scoped_scalar($trs_dash_gate,
        "SELECT COUNT(*) FROM transfer_orders o WHERE {TENANT_SCOPE} AND DATE(o.departure_datetime)=?", array($day));
    $rb_pulse['out'][] = (float) trs_scoped_scalar($trs_dash_gate,
        "SELECT COUNT(*) FROM transfer_orders o WHERE {TENANT_SCOPE} AND DATE(o.arrival_datetime)=?", array($day));
}
$rb_pulse_title  = 'نبض الأداء — انطلقت مقابل وصلت (7 أيام)';
$rb_pulse_series = array('انطلقت', 'وصلت');

$page_title = 'إيكوبيشن | لوحة الرحلات';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

$stage_colors = array(
    'request' => '#6c757d', 'planned' => '#0d6efd', 'ready' => '#0dcaf0',
    'in_transit' => '#fd7e14', 'arrived' => '#198754', 'closed' => '#212529', 'cancelled' => '#dc3545',
);
?>
<div class="main trs-dash-main ems-unified-page-shell">
    <?php
    $header_title = 'لوحة متابعة الرحلات';
    $header_icon  = 'fa fa-gauge-high';
    $header_actions = array();
    $header_back = array(
        array('href' => 'transfer_orders_list.php', 'class' => '', 'icon' => 'fas fa-list', 'label' => 'أوامر الترحيل'),
        array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع'),
    );
    include('../includes/page_header.php');
    ?>
    <?php trs_msg_banner(); ?>

    <!-- بطاقات المؤشرات -->
    <div class="trs-kpi-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px">
        <?php
        $kpis = array(
            array('إجمالي الأوامر', $total_orders, 'fa-truck-fast', '#0d6efd'),
            array('أوامر مفتوحة', $open_orders, 'fa-folder-open', '#fd7e14'),
            array('قيد الرحلة', $in_transit, 'fa-route', '#0dcaf0'),
            array('رحلات متأخّرة', $delayed, 'fa-triangle-exclamation', '#dc3545'),
            array('الوصول في الموعد', ($ontime_pct === null ? '—' : $ontime_pct . '%'), 'fa-clock', '#198754'),
            array('إجمالي التكلفة (USD)', number_format($total_cost_usd, 0), 'fa-hand-holding-dollar', '#6f42c1'),
            array('متوسّط زمن الترحيل (س)', ($avg_hours === null ? '—' : $avg_hours), 'fa-stopwatch', '#20c997'),
        );
        foreach ($kpis as $k):
        ?>
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

    <?php include __DIR__ . '/../includes/role_board_widgets.php'; ?>

    <!-- عدّاد المراحل + التكلفة حسب المتحمِّل -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:16px;margin-top:14px">
        <div class="card"><div class="card-body">
            <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-diagram-project"></i> تكلفة الترحيل لكل مشروع (USD)</h5></div>
            <?php if (empty($by_project)): ?>
                <div style="color:#6c757d">لا بيانات بعد.</div>
            <?php else: foreach ($by_project as $bp):
                $c = (float)$bp['cost'];
                $w = $max_proj_cost > 0 ? round($c * 100 / $max_proj_cost) : 0;
            ?>
                <div style="margin-bottom:10px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
                        <span><?php echo htmlspecialchars($bp['pname']); ?> <span style="color:#adb5bd">(<?php echo (int)$bp['orders']; ?> أمر)</span></span>
                        <strong><?php echo number_format($c, 2); ?></strong>
                    </div>
                    <div style="background:#eef1f4;border-radius:6px;height:10px;overflow:hidden">
                        <div style="width:<?php echo $w; ?>%;height:100%;background:#0d6efd"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div></div>

        <div class="card"><div class="card-body">
            <div class="card-header" style="border:none;padding:0 0 10px"><h5><i class="fa fa-hand-holding-dollar"></i> التكلفة حسب المتحمِّل</h5></div>
            <?php
            $bearer_colors = array('client' => '#0d6efd', 'company' => '#dc3545', 'new_client' => '#198754');
            foreach ($by_bearer as $bk => $bc):
            ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f0">
                    <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?php echo $bearer_colors[$bk]; ?>;margin-left:6px"></span><?php echo htmlspecialchars(trs_label($bearer_map, $bk)); ?></span>
                    <strong><?php echo number_format((float)$bc, 2); ?></strong>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>

    <!-- كانبان حسب المرحلة -->
    <div class="card"><div class="card-body">
        <div class="card-header" style="border:none;padding:0 0 12px"><h5><i class="fa fa-columns"></i> الرحلات حسب المرحلة</h5></div>
        <div style="display:grid;grid-template-columns:repeat(<?php echo count($kanban_stages); ?>,1fr);gap:10px;overflow-x:auto">
            <?php foreach ($kanban_stages as $st): $col = $stage_colors[$st]; ?>
            <div style="min-width:180px">
                <div style="background:<?php echo $col; ?>;color:#fff;border-radius:8px 8px 0 0;padding:8px 10px;font-weight:700;font-size:13px;display:flex;justify-content:space-between">
                    <span><?php echo htmlspecialchars($stages_ar[$st]); ?></span>
                    <span style="background:rgba(255,255,255,.25);border-radius:10px;padding:0 8px"><?php echo (int)$stage_counts[$st]; ?></span>
                </div>
                <div style="background:#f7f8fa;border:1px solid #eceff2;border-top:none;border-radius:0 0 8px 8px;padding:8px;min-height:60px">
                    <?php if (empty($kanban[$st])): ?>
                        <div style="color:#adb5bd;font-size:12px;text-align:center;padding:8px">—</div>
                    <?php else: foreach ($kanban[$st] as $o): ?>
                        <a href="transfer_order_form.php?id=<?php echo intval($o['id']); ?>"
                           style="display:block;background:#fff;border:1px solid #e6e9ec;border-radius:8px;padding:8px;margin-bottom:7px;text-decoration:none;color:#212529<?php echo ((int)$o['late'] === 1) ? ';border-right:3px solid #dc3545' : ''; ?>">
                            <div style="font-weight:700;font-size:12px"><?php echo htmlspecialchars((string)$o['order_no']); ?></div>
                            <div style="font-size:11px;color:#6c757d;margin-top:2px">
                                <?php echo htmlspecialchars(trs_label($dir_map, $o['direction'])); ?>
                                <?php if (!empty($o['pname'])): ?> · <?php echo htmlspecialchars((string)$o['pname']); ?><?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:#6c757d;margin-top:2px">
                                <i class="fa fa-location-dot"></i> <?php echo htmlspecialchars((string)($o['to_name'] ?? '—')); ?>
                                <?php if (!empty($o['planned_date'])): ?> · <?php echo htmlspecialchars((string)$o['planned_date']); ?><?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/chartjs/chart.umd.min.js"></script>
</body>
</html>
